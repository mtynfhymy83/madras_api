# Postman Testing Guide - ETA Login with JWT

Complete guide for testing the ETA login system with Postman.

## Prerequisites

1. **Postman installed** (Desktop or Web)
2. **Laravel server running** (`php artisan serve`)
3. **EITA_TOKEN configured** in `.env` file
4. **Migrations run** (`php artisan migrate`)

## Setup

### 1. Create a New Collection

1. Open Postman
2. Click **New** → **Collection**
3. Name it: `Madras Laravel API`
4. Click **Create**

### 2. Set Collection Variables

1. Click on your collection
2. Go to **Variables** tab
3. Add these variables:
   - `base_url`: `http://localhost:8000/api`
   - `access_token`: (leave empty, will be set automatically)
   - `refresh_token`: (leave empty, will be set automatically)

## Request 1: ETA Login

### Setup

1. **Method**: `POST`
2. **URL**: `{{base_url}}/auth/eta-login`
3. **Headers**:
   ```
   Content-Type: application/json
   Accept: application/json
   ```

### Request Body

Go to **Body** tab → Select **raw** → Choose **JSON**

```json
{
    "eitaa_data": "auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={\"id\":10865407,\"first_name\":\"MahdiAli\",\"last_name\":\"Pak\",\"language_code\":\"en\",\"allows_write_to_pm\":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47",
    "utm": "source=postman&medium=api&campaign=test"
}
```

**Important Notes:**
- Replace the `eitaa_data` with actual ETA init data from your ETA bot
- The `user` field inside `eitaa_data` must be a JSON string (with escaped quotes)
- The `hash` must match the calculated hash for validation to pass
- `utm` is optional

### Save Tokens Automatically

Go to **Tests** tab and add this script:

```javascript
// Check if request was successful
if (pm.response.code === 200 || pm.response.code === 201) {
    var jsonData = pm.response.json();
    
    // Save access token
    if (jsonData.access_token) {
        pm.collectionVariables.set("access_token", jsonData.access_token);
        console.log("✅ Access token saved");
    }
    
    // Save refresh token
    if (jsonData.refresh_token) {
        pm.collectionVariables.set("refresh_token", jsonData.refresh_token);
        console.log("✅ Refresh token saved");
    }
    
    // Log user info
    if (jsonData.user) {
        console.log("👤 User:", jsonData.user.username || jsonData.user.displayname);
        console.log("🆔 User ID:", jsonData.user.id);
    }
    
    // Check if new user
    if (jsonData.register) {
        console.log("🆕 New user registered!");
    } else if (jsonData.login) {
        console.log("🔐 User logged in!");
    }
} else {
    console.log("❌ Error:", pm.response.json());
}
```

### Expected Response

**Success (200 or 201):**
```json
{
    "login": true,
    "user": {
        "id": 1,
        "username": "user_10865407",
        "name": "MahdiAli",
        "family": "Pak",
        "displayname": "MahdiAli Pak",
        "email": "user_10865407@eitaa.com",
        "active": true,
        "approved": true
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "random64characterstring...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**Error (400/422):**
```json
{
    "success": false,
    "message": "دیتا معتبر نمی باشد!",
    "errors": null
}
```

## Request 2: Get Authenticated User (Me)

### Setup

1. **Method**: `GET`
2. **URL**: `{{base_url}}/auth/me`
3. **Headers**:
   ```
   Authorization: Bearer {{access_token}}
   Accept: application/json
   ```

### Expected Response

**Success (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "username": "user_10865407",
        "name": "MahdiAli",
        "family": "Pak",
        "displayname": "MahdiAli Pak",
        "email": "user_10865407@eitaa.com",
        "active": true,
        "approved": true
    }
}
```

**Error (401):**
```json
{
    "success": false,
    "message": "User not authenticated"
}
```

## Request 3: Refresh Access Token

### Setup

1. **Method**: `POST`
2. **URL**: `{{base_url}}/auth/refresh`
3. **Headers**:
   ```
   Content-Type: application/json
   Accept: application/json
   ```

### Request Body

```json
{
    "refresh_token": "{{refresh_token}}"
}
```

### Save New Access Token

Go to **Tests** tab:

```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    if (jsonData.data && jsonData.data.access_token) {
        pm.collectionVariables.set("access_token", jsonData.data.access_token);
        console.log("✅ New access token saved");
    }
} else {
    console.log("❌ Error:", pm.response.json());
}
```

### Expected Response

**Success (200):**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "same_refresh_token...",
        "expires_in": 3600,
        "token_type": "Bearer"
    }
}
```

## Request 4: Logout

### Setup

1. **Method**: `POST`
2. **URL**: `{{base_url}}/auth/logout`
3. **Headers**:
   ```
   Content-Type: application/json
   Accept: application/json
   ```

### Request Body

```json
{
    "refresh_token": "{{refresh_token}}"
}
```

### Clear Tokens After Logout

Go to **Tests** tab:

```javascript
if (pm.response.code === 200) {
    pm.collectionVariables.set("access_token", "");
    pm.collectionVariables.set("refresh_token", "");
    console.log("✅ Logged out, tokens cleared");
} else {
    console.log("❌ Error:", pm.response.json());
}
```

### Expected Response

**Success (200):**
```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

## Complete Postman Collection JSON

Save this as `Madras_Laravel_API.postman_collection.json`:

```json
{
    "info": {
        "name": "Madras Laravel API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "http://localhost:8000/api"
        },
        {
            "key": "access_token",
            "value": ""
        },
        {
            "key": "refresh_token",
            "value": ""
        }
    ],
    "item": [
        {
            "name": "ETA Login",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    },
                    {
                        "key": "Accept",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"eitaa_data\": \"auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={\\\"id\\\":10865407,\\\"first_name\\\":\\\"MahdiAli\\\",\\\"last_name\\\":\\\"Pak\\\",\\\"language_code\\\":\\\"en\\\",\\\"allows_write_to_pm\\\":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47\",\n    \"utm\": \"source=postman&medium=api&campaign=test\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/auth/eta-login",
                    "host": ["{{base_url}}"],
                    "path": ["auth", "eta-login"]
                }
            },
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200 || pm.response.code === 201) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.access_token) {",
                            "        pm.collectionVariables.set(\"access_token\", jsonData.access_token);",
                            "    }",
                            "    if (jsonData.refresh_token) {",
                            "        pm.collectionVariables.set(\"refresh_token\", jsonData.refresh_token);",
                            "    }",
                            "}"
                        ]
                    }
                }
            ]
        },
        {
            "name": "Get Authenticated User",
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{access_token}}"
                    },
                    {
                        "key": "Accept",
                        "value": "application/json"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/auth/me",
                    "host": ["{{base_url}}"],
                    "path": ["auth", "me"]
                }
            }
        },
        {
            "name": "Refresh Token",
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
                    "raw": "{\n    \"refresh_token\": \"{{refresh_token}}\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/auth/refresh",
                    "host": ["{{base_url}}"],
                    "path": ["auth", "refresh"]
                }
            }
        },
        {
            "name": "Logout",
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
                    "raw": "{\n    \"refresh_token\": \"{{refresh_token}}\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/auth/logout",
                    "host": ["{{base_url}}"],
                    "path": ["auth", "logout"]
                }
            }
        }
    ]
}
```

## Step-by-Step Testing Workflow

### 1. First Time Setup

1. Import the collection JSON above (or create manually)
2. Set `base_url` variable to your Laravel API URL
3. Make sure your Laravel server is running

### 2. Test ETA Login

1. Open **ETA Login** request
2. **Important**: Replace `eitaa_data` with real ETA init data from your bot
3. Click **Send**
4. Check **Console** (bottom of Postman) for token save confirmation
5. Verify response contains `access_token` and `refresh_token`

### 3. Test Authenticated Endpoint

1. Open **Get Authenticated User** request
2. The `{{access_token}}` variable should be automatically filled
3. Click **Send**
4. Should return user data

### 4. Test Token Refresh

1. Wait for access token to expire (or manually test)
2. Open **Refresh Token** request
3. Click **Send**
4. New access token should be saved automatically

### 5. Test Logout

1. Open **Logout** request
2. Click **Send**
3. Tokens should be cleared from variables

## Troubleshooting

### Error: "دیتا معتبر نمی باشد!" (Invalid data)

**Causes:**
- Hash doesn't match (wrong token or corrupted data)
- Missing required fields in `eitaa_data`
- Incorrect format

**Solution:**
- Verify `EITAA_TOKEN` in `.env` matches your bot token
- Ensure `eitaa_data` is a valid URL-encoded query string
- Check that `hash` field is present and correct

### Error: "User not authenticated"

**Causes:**
- Access token expired
- Invalid token
- Token not sent in Authorization header

**Solution:**
- Refresh the token using **Refresh Token** request
- Check Authorization header format: `Bearer {{access_token}}`
- Verify token was saved correctly

### Error: "Invalid refresh token"

**Causes:**
- Refresh token expired
- Token already revoked
- Wrong token format

**Solution:**
- Login again to get new tokens
- Check refresh token in collection variables
- Verify token wasn't revoked

## Tips

1. **Use Environment Variables**: Create different environments for dev/staging/prod
2. **Save Responses**: Use Postman's "Save Response" feature for debugging
3. **Use Console**: Check Postman console for detailed logs
4. **Test Scripts**: Add test scripts to validate responses automatically
5. **Collection Runner**: Use collection runner to test all endpoints in sequence

## Quick Test Checklist

- [ ] Collection created with variables
- [ ] Base URL set correctly
- [ ] ETA Login request works
- [ ] Tokens saved automatically
- [ ] Get User (me) works with token
- [ ] Refresh token works
- [ ] Logout works and clears tokens

## Example: Real ETA Data Format

When testing, you'll receive actual ETA init data from your bot. It will look like:

```
auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={"id":10865407,"first_name":"MahdiAli","last_name":"Pak","language_code":"en","allows_write_to_pm":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47
```

Just paste this entire string as the value of `eitaa_data` in your request body.

Happy Testing! 🚀
