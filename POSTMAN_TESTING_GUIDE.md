# Postman Testing Guide for JWT Authentication API

This guide will help you test the Register and Login APIs with JWT authentication in Postman.

## Prerequisites

1. **Install JWT Package** (if not already done):
   ```bash
   composer require tymon/jwt-auth
   php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
   php artisan jwt:secret
   ```

2. **Start Laravel Server**:
   ```bash
   php artisan serve
   ```
   Your API will be available at: `http://localhost:8000` or `http://127.0.0.1:8000`

## API Endpoints

Base URL: `http://localhost:8000/api`

### Public Endpoints (No Authentication Required)
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user

### Protected Endpoints (Require JWT Token)
- `POST /api/logout` - Logout user
- `POST /api/refresh` - Refresh JWT token
- `GET /api/me` - Get authenticated user info

---

## Postman Setup

### Step 1: Create a New Collection

1. Open Postman
2. Click **New** → **Collection**
3. Name it: "Laravel JWT Auth API"
4. Click **Create**

### Step 2: Set Collection Variables

1. Click on your collection
2. Go to **Variables** tab
3. Add these variables:
   - `base_url`: `http://localhost:8000/api`
   - `token`: (leave empty, will be set automatically)

---

## Testing Register Endpoint

### Request Setup

1. **Method**: `POST`
2. **URL**: `{{base_url}}/register`
3. **Headers**:
   - `Content-Type`: `application/json`
   - `Accept`: `application/json`

4. **Body** (raw JSON):
   ```json
   {
       "username": "testuser",
       "email": "test@example.com",
       "password": "password123",
       "name": "Test",
       "family": "User",
       "tel": "09123456789"
   }
   ```

### Expected Response (Success - 201)

```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "username": "testuser",
            "email": "test@example.com",
            "name": "Test",
            "family": "User",
            "displayname": "Test User",
            "active": true,
            "approved": false,
            "level": "user"
        }
    }
}
```

### Save Token Automatically

1. In Postman, go to **Tests** tab
2. Add this script to save the token:
   ```javascript
   if (pm.response.code === 201) {
       var jsonData = pm.response.json();
       if (jsonData.data && jsonData.data.token) {
           pm.collectionVariables.set("token", jsonData.data.token);
           console.log("Token saved:", jsonData.data.token);
       }
   }
   ```

---

## Testing Login Endpoint

### Request Setup

1. **Method**: `POST`
2. **URL**: `{{base_url}}/login`
3. **Headers**:
   - `Content-Type`: `application/json`
   - `Accept`: `application/json`

4. **Body** (raw JSON):
   ```json
   {
       "username": "testuser",
       "password": "password123",
       "remember": false
   }
   ```

   Or with remember me:
   ```json
   {
       "username": "testuser",
       "password": "password123",
       "remember": true
   }
   ```

### Expected Response (Success - 200)

```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "username": "testuser",
            "email": "test@example.com",
            "name": "Test",
            "family": "User",
            "active": true,
            "approved": false,
            "level": "user"
        }
    }
}
```

### Save Token (Same as Register)

Add the same test script in the **Tests** tab to save the token automatically.

### Error Response Examples

**Invalid Credentials (401)**:
```json
{
    "success": false,
    "message": "Invalid credentials or account not active"
}
```

**Validation Error (422)**:
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "username": ["The username field is required."],
        "password": ["The password field is required."]
    }
}
```

---

## Testing Protected Endpoints

### Get Authenticated User (GET /api/me)

1. **Method**: `GET`
2. **URL**: `{{base_url}}/me`
3. **Headers**:
   - `Authorization`: `Bearer {{token}}`
   - `Accept`: `application/json`

### Expected Response (Success - 200)

```json
{
    "success": true,
    "data": {
        "id": 1,
        "username": "testuser",
        "email": "test@example.com",
        "name": "Test",
        "family": "User",
        "active": true,
        "approved": false,
        "level": "user"
    }
}
```

### Logout (POST /api/logout)

1. **Method**: `POST`
2. **URL**: `{{base_url}}/logout`
3. **Headers**:
   - `Authorization`: `Bearer {{token}}`
   - `Accept`: `application/json`

### Expected Response (Success - 200)

```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

### Refresh Token (POST /api/refresh)

1. **Method**: `POST`
2. **URL**: `{{base_url}}/refresh`
3. **Headers**:
   - `Authorization`: `Bearer {{token}}`
   - `Accept`: `application/json`

### Expected Response (Success - 200)

```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "username": "testuser",
            "email": "test@example.com"
        }
    }
}
```

---

## Postman Collection Setup (Complete)

### Create Environment Variables

1. Click **Environments** → **+**
2. Name: "Laravel Local"
3. Add variables:
   - `base_url`: `http://localhost:8000/api`
   - `token`: (leave empty)

### Authorization Setup for Collection

1. Select your collection
2. Go to **Authorization** tab
3. Type: **Bearer Token**
4. Token: `{{token}}`
5. This will automatically add the token to all requests in the collection

---

## Quick Test Flow

### 1. Register New User
```
POST {{base_url}}/register
Body: {
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "password123",
    "name": "New",
    "family": "User"
}
```

### 2. Login
```
POST {{base_url}}/login
Body: {
    "username": "newuser",
    "password": "password123"
}
```

### 3. Get User Info
```
GET {{base_url}}/me
Headers: Authorization: Bearer {{token}}
```

### 4. Refresh Token
```
POST {{base_url}}/refresh
Headers: Authorization: Bearer {{token}}
```

### 5. Logout
```
POST {{base_url}}/logout
Headers: Authorization: Bearer {{token}}
```

---

## Common Issues & Solutions

### Issue: "Token absent" or "Token invalid"

**Solution**: 
- Make sure you're sending the token in the Authorization header
- Format: `Bearer {your_token_here}`
- Check if token variable is set in Postman

### Issue: "User not authenticated"

**Solution**:
- Token might be expired (default: 60 minutes)
- Use `/refresh` endpoint to get a new token
- Login again to get a fresh token

### Issue: "Invalid credentials"

**Solution**:
- Check username/email and password are correct
- Make sure user account is `active` and `approved` in database
- For testing, you can manually set `approved = true` in database

### Issue: CORS Error

**Solution**: 
- Make sure CORS is configured in `config/cors.php`
- Or add CORS middleware to your API routes

---

## Testing Tips

1. **Use Postman Pre-request Scripts**: Automatically set variables before requests
2. **Use Postman Tests**: Automatically save tokens and validate responses
3. **Use Environments**: Switch between local, staging, and production easily
4. **Export Collection**: Share your collection with team members

---

## Example Postman Test Scripts

### Auto-save Token (for Login/Register)
```javascript
if (pm.response.code === 200 || pm.response.code === 201) {
    var jsonData = pm.response.json();
    if (jsonData.data && jsonData.data.token) {
        pm.collectionVariables.set("token", jsonData.data.token);
        pm.environment.set("token", jsonData.data.token);
        console.log("✅ Token saved successfully");
    }
}
```

### Validate Response
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has success field", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('success');
    pm.expect(jsonData.success).to.be.true;
});
```

---

## Complete Postman Collection JSON

You can import this collection directly into Postman:

```json
{
    "info": {
        "name": "Laravel JWT Auth API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "http://localhost:8000/api"
        },
        {
            "key": "token",
            "value": ""
        }
    ],
    "item": [
        {
            "name": "Register",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"username\": \"testuser\",\n    \"email\": \"test@example.com\",\n    \"password\": \"password123\",\n    \"name\": \"Test\",\n    \"family\": \"User\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/register",
                    "host": ["{{base_url}}"],
                    "path": ["register"]
                }
            }
        },
        {
            "name": "Login",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"username\": \"testuser\",\n    \"password\": \"password123\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/login",
                    "host": ["{{base_url}}"],
                    "path": ["login"]
                }
            }
        },
        {
            "name": "Get Me",
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/me",
                    "host": ["{{base_url}}"],
                    "path": ["me"]
                }
            }
        },
        {
            "name": "Logout",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/logout",
                    "host": ["{{base_url}}"],
                    "path": ["logout"]
                }
            }
        },
        {
            "name": "Refresh Token",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/refresh",
                    "host": ["{{base_url}}"],
                    "path": ["refresh"]
                }
            }
        }
    ]
}
```

Save this as a `.json` file and import it into Postman!

---

## Next Steps

1. Test all endpoints in the order shown above
2. Verify token expiration works correctly
3. Test error scenarios (invalid credentials, expired tokens, etc.)
4. Integrate with your frontend application

Happy Testing! 🚀

