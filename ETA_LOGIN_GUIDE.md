# ETA Login System with JWT - Implementation Guide

This guide explains how the ETA login system works with JWT authentication.

## Overview

The system automatically handles user login/registration through ETA (Eitaa) authentication:
- **If user exists** (by `eitaa_id`): Logs in and updates user data
- **If user doesn't exist**: Creates new user and logs in
- **Returns JWT tokens** (access + refresh tokens)

## Setup

### 1. Run Migrations

```bash
php artisan migrate
```

This will:
- Add `eitaa_id` field to `ci_users` table
- Remove `mini_app_uuid` from `jwt_tokens` table (if exists)

### 2. Install JWT Package (if not already done)

```bash
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

## API Endpoints

### POST `/api/auth/eta-login`

Login or register user via ETA init data.

**Request Body:**
```json
{
    "eitaa_id": "123456789",           // Required: ETA user ID
    "name": "John",                     // Optional: First name
    "family": "Doe",                    // Optional: Last name
    "username": "johndoe",              // Optional: Username (auto-generated if not provided)
    "email": "john@example.com",        // Optional: Email
    "tel": "09123456789",               // Optional: Phone number
    "avatar": "https://...",            // Optional: Avatar URL
    "device_info": "iPhone 13"          // Optional: Device information
}
```

**Success Response (200/201):**
```json
{
    "success": true,
    "message": "Login successful" or "User created and logged in successfully",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "random64characterstring...",
        "expires_in": 3600,
        "token_type": "Bearer",
        "user": {
            "id": 1,
            "eitaa_id": "123456789",
            "username": "johndoe",
            "name": "John",
            "family": "Doe",
            "displayname": "John Doe",
            "email": "john@example.com",
            "active": true,
            "approved": true,
            "level": "user"
        },
        "is_new_user": false
    }
}
```

**Error Responses:**

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "eitaa_id": ["The eitaa id field is required."]
    }
}
```

**User Not Active (403):**
```json
{
    "success": false,
    "message": "User account is not active"
}
```

### POST `/api/auth/refresh`

Refresh access token using refresh token.

**Request Body:**
```json
{
    "refresh_token": "your_refresh_token_here"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "access_token": "new_access_token...",
        "refresh_token": "same_refresh_token...",
        "expires_in": 3600,
        "token_type": "Bearer"
    }
}
```

### POST `/api/auth/logout`

Logout user and revoke refresh token.

**Request Body:**
```json
{
    "refresh_token": "your_refresh_token_here"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

### GET `/api/auth/me`

Get authenticated user information (requires JWT token).

**Headers:**
```
Authorization: Bearer {access_token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "eitaa_id": "123456789",
        "username": "johndoe",
        "name": "John",
        "family": "Doe",
        "email": "john@example.com",
        "active": true,
        "approved": true
    }
}
```

## How It Works

### 1. User Login/Registration Flow

```
1. Client sends ETA init data to /api/auth/eta-login
2. System checks if user exists by eitaa_id
3. If exists:
   - Update last_seen
   - Update user data if provided
   - Generate JWT tokens
4. If doesn't exist:
   - Create new user with ETA data
   - Auto-approve user (approved = true)
   - Generate JWT tokens
5. Return tokens and user data
```

### 2. Token Structure

**Access Token Claims:**
- `user_id`: User database ID
- `eitaa_id`: ETA user ID
- `roles`: User roles (array)
- `is_super_admin`: Boolean
- `device_info`: Device information

**Refresh Token:**
- Stored in database (hashed)
- Used to get new access tokens
- Has expiration time (configurable)

### 3. User Creation

When a new user is created:
- `eitaa_id`: From ETA init data
- `username`: Auto-generated if not provided (format: `user_{eitaa_id}`)
- `displayname`: Generated from name + family
- `password`: Random hash (ETA handles authentication)
- `active`: `true`
- `approved`: `true` (auto-approved for ETA users)
- `level`: `'user'`
- `register`: `'done'`

## Testing in Postman

### 1. ETA Login Request

**Method:** `POST`  
**URL:** `http://localhost:8000/api/auth/eta-login`  
**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (raw JSON):**
```json
{
    "eitaa_id": "123456789",
    "name": "Test",
    "family": "User",
    "email": "test@example.com",
    "tel": "09123456789",
    "device_info": "Postman"
}
```

**Save Token Script (Tests tab):**
```javascript
if (pm.response.code === 200 || pm.response.code === 201) {
    var jsonData = pm.response.json();
    if (jsonData.data) {
        pm.collectionVariables.set("access_token", jsonData.data.access_token);
        pm.collectionVariables.set("refresh_token", jsonData.data.refresh_token);
        console.log("✅ Tokens saved");
    }
}
```

### 2. Get User Info

**Method:** `GET`  
**URL:** `http://localhost:8000/api/auth/me`  
**Headers:**
```
Authorization: Bearer {{access_token}}
Accept: application/json
```

### 3. Refresh Token

**Method:** `POST`  
**URL:** `http://localhost:8000/api/auth/refresh`  
**Body:**
```json
{
    "refresh_token": "{{refresh_token}}"
}
```

### 4. Logout

**Method:** `POST`  
**URL:** `http://localhost:8000/api/auth/logout`  
**Body:**
```json
{
    "refresh_token": "{{refresh_token}}"
}
```

## Security Features

1. **JWT Token Expiration**: Access tokens expire (default: 60 minutes)
2. **Refresh Token Rotation**: New access token on refresh, same refresh token
3. **Token Blacklisting**: Old access tokens are blacklisted
4. **User Validation**: Checks if user is active before login
5. **Unique eitaa_id**: Prevents duplicate ETA accounts

## Database Changes

### New Field in `ci_users`:
- `eitaa_id` (string, unique, indexed)

### Updated `jwt_tokens`:
- Removed `mini_app_uuid` field
- Simplified token storage

## Configuration

Update `.env`:
```env
JWT_SECRET=your-secret-key
JWT_TTL=60                    # Access token lifetime (minutes)
JWT_REFRESH_TTL=20160         # Refresh token lifetime (minutes)
JWT_ALGO=HS256
JWT_BLACKLIST_ENABLED=true
```

## Error Handling

The system handles:
- Missing required fields (validation errors)
- Duplicate eitaa_id (database constraint)
- Inactive users (403 error)
- Invalid refresh tokens (401 error)
- Expired tokens (401 error)

## Next Steps

1. Run migrations: `php artisan migrate`
2. Test ETA login endpoint
3. Integrate with your frontend
4. Customize user creation logic if needed
5. Add additional user fields if required


