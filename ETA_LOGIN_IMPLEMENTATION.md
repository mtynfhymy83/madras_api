# ETA Login Implementation - Complete Guide

This document explains the complete ETA login system implementation matching your original CodeIgniter logic.

## Overview

The system implements automatic user authentication via ETA (Eitaa) init data:
- **Validates ETA init data** using HMAC SHA256
- **Checks user existence** by `eitaa_id` in `user_meta` table
- **If exists**: Logs in and returns JWT tokens
- **If doesn't exist**: Creates user, saves `eitaa_id` in `user_meta`, sends welcome message, returns JWT tokens
- **Tracks UTM** parameters for analytics

## Setup

### 1. Environment Variables

Add to your `.env` file:

```env
EITAA_TOKEN=your_eitaa_bot_token_here
EITAA_TOKEN2=your_test_token_here  # Optional, for testing
```

### 2. Run Migrations

```bash
php artisan migrate
```

This will create:
- `utm` table for UTM tracking
- Add `eitaa_id` field to `ci_users` (optional, for direct access)

## API Endpoint

### POST `/api/auth/eta-login`

**Request Body:**
```json
{
    "eitaa_data": "auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={\"id\":10865407,\"first_name\":\"MahdiAli\",\"last_name\":\"Pak\",\"language_code\":\"en\",\"allows_write_to_pm\":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47",
    "utm": "source=google&medium=cpc&campaign=test"  // Optional
}
```

**Note:** ETA sends init data as a URL-encoded query string, not JSON. The `user` field contains a JSON string that will be parsed automatically.

**Success Response - Existing User (200):**
```json
{
    "login": true,
    "user": {
        "id": 1,
        "username": "johndoe",
        "name": "John",
        "family": "Doe",
        "displayname": "John Doe",
        "email": "john@example.com",
        "active": true,
        "approved": true
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "random64characterstring...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**Success Response - New User (201):**
```json
{
    "register": true,
    "user": {
        "id": 2,
        "username": "newuser",
        "name": "New",
        "family": "User",
        "displayname": "New User",
        "email": "newuser@eitaa.com",
        "active": true,
        "approved": true
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "random64characterstring...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**Error Responses:**

```json
{
    "success": false,
    "message": "ایتا توکن الزامی می باشد!",
    "errors": null
}
```

```json
{
    "success": false,
    "message": "دیتا معتبر نمی باشد!",
    "errors": null
}
```

```json
{
    "success": false,
    "message": "آیدی ایتا معتبر نمی باشد!",
    "errors": null
}
```

## How It Works

### 1. ETA Data Validation

The system validates ETA init data using HMAC SHA256:
- Parses URL-encoded query string format
- Extracts hash from data
- Sorts data by key (excluding hash)
- Creates data check string (format: `key=value\nkey=value\n...`)
- Calculates secret key: `HMAC_SHA256(token, "WebAppData")`
- Calculates hash: `hex(HMAC_SHA256(data_check_string, secret_key))`
- Compares calculated hash with provided hash (timing-safe)

**Data Format:**
```
auth_date=1760874684&device_id=...&query_id=...&user={"id":...}&hash=...
```

### 2. User Lookup

- Searches `user_meta` table for `meta_name = 'eitaa_id'` and `meta_value = {eitaa_id}`
- If found: User exists, proceed to login
- If not found: User doesn't exist, proceed to registration

### 3. User Creation

When creating a new user:
- Generates unique username (format: `user_{eitaa_id}` or from ETA data)
- Creates user in `ci_users` table
- Saves `eitaa_id` in `user_meta` table
- Sends welcome message via ETA bot
- Tracks UTM if provided (`is_registered = 1`)

### 4. User Login

When user exists:
- Updates `last_seen` timestamp
- Tracks UTM if provided (`is_registered = 0`)
- Generates JWT tokens

### 5. UTM Tracking

UTM data is saved in `utm` table:
- `user_id`: User ID
- `eitaa_id`: ETA user ID
- `is_registered`: `1` for new users, `0` for existing users
- `utm`: UTM string
- `created_at`: Timestamp

## Testing in Postman

### Request Setup

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
    "eitaa_data": "auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={\"id\":10865407,\"first_name\":\"Test\",\"last_name\":\"User\",\"language_code\":\"en\",\"allows_write_to_pm\":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47",
    "utm": "source=eitaa&medium=webapp&campaign=signup"
}
```

**Note:** The `eitaa_data` is a URL-encoded query string. The `user` field is a JSON string that will be parsed automatically.

### Save Tokens Script

In Postman **Tests** tab:
```javascript
if (pm.response.code === 200 || pm.response.code === 201) {
    var jsonData = pm.response.json();
    if (jsonData.access_token) {
        pm.collectionVariables.set("access_token", jsonData.access_token);
        pm.collectionVariables.set("refresh_token", jsonData.refresh_token);
        console.log("✅ Tokens saved");
    }
}
```

## Files Created/Modified

### New Files:
1. `app/Services/Eta/EtaValidationService.php` - ETA data validation
2. `app/Services/Eta/EtaMessageService.php` - ETA message sending
3. `app/Models/Utm.php` - UTM model
4. `database/migrations/2025_12_01_000002_create_utm_table.php` - UTM table

### Modified Files:
1. `app/Http/Controllers/AuthController.php` - Main login logic
2. `config/services.php` - Added ETA token configuration
3. `routes/api.php` - Updated routes

## Key Differences from Previous Implementation

1. **Uses `user_meta` table** for `eitaa_id` storage (not direct column)
2. **Validates ETA init data** using HMAC SHA256
3. **Extracts data from JSON** init data string
4. **Tracks UTM parameters** for analytics
5. **Sends welcome message** to new users via ETA bot
6. **Response format** matches original CodeIgniter format

## Security Features

- ✅ ETA data validation using HMAC SHA256
- ✅ Token-based authentication
- ✅ User activity tracking
- ✅ UTM parameter tracking
- ✅ Welcome message verification

## Next Steps

1. Add `EITAA_TOKEN` to `.env` file
2. Run migrations: `php artisan migrate`
3. Test the endpoint with real ETA init data
4. Configure ETA bot for sending messages
5. Monitor UTM tracking data

The system is now ready to handle ETA authentication exactly like your original CodeIgniter implementation! 🚀

