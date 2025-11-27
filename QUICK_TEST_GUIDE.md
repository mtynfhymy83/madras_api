# Quick Test Guide - ETA Login

## Step 1: Set Your Token

Make sure your `.env` has:
```env
EITAA_TOKEN="60930039:laKi6ig-Ml)Q8[?-EMpqNKn-UL(vPo}-dD7Xsx8-A%hpXLw-1WvuO4V-YxXSC9E-@v6g5mz-C5p*R7q-4KAfOhm-Oa~PsRi-oV9R^/T-eyunIYD-0%PfYWo-vHk{JF1-W1g7B,s-yYlYmIb-BZLs(V0-EUGJh"
APP_DEBUG=true
```

## Step 2: Generate Test Data

**Request:**
```
GET http://localhost:8000/api/auth/generate-test-data
```

**Response:**
```json
{
    "success": true,
    "data": {
        "eitaa_data": "auth_date=1735689600&device_id=...&query_id=...&user={...}&hash=...",
        "full_request": {
            "eitaa_data": "...",
            "utm": "source=test&medium=postman"
        }
    }
}
```

**Copy the `eitaa_data` value!**

## Step 3: Test Login

**Request:**
```
POST http://localhost:8000/api/auth/eta-login
Content-Type: application/json

{
    "eitaa_data": "<paste from step 2>",
    "utm": "source=test"
}
```

**Expected Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "refresh_token": "def50200...",
        "token_type": "bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "eitaa_id": 10865407,
            "username": "10865407",
            "name": "MahdiAli",
            "family": "Pak",
            ...
        }
    }
}
```

## Step 4: Use the Token

**Request:**
```
GET http://localhost:8000/api/auth/me
Authorization: Bearer <access_token from step 3>
```

## Postman Quick Setup

1. **Create Environment:**
   - Variable: `base_url` = `http://localhost:8000`
   - Variable: `eta_test_data` = (will be set automatically)
   - Variable: `jwt_access_token` = (will be set automatically)

2. **Request 1: Generate Test Data**
   - Method: `GET`
   - URL: `{{base_url}}/api/auth/generate-test-data`
   - Tests Tab:
     ```javascript
     if (pm.response.code === 200) {
         var json = pm.response.json();
         pm.environment.set("eta_test_data", json.data.eitaa_data);
     }
     ```

3. **Request 2: ETA Login**
   - Method: `POST`
   - URL: `{{base_url}}/api/auth/eta-login`
   - Body (raw JSON):
     ```json
     {
         "eitaa_data": "{{eta_test_data}}",
         "utm": "source=test"
     }
     ```
   - Tests Tab:
     ```javascript
     if (pm.response.code === 200) {
         var json = pm.response.json();
         if (json.success) {
             pm.environment.set("jwt_access_token", json.data.access_token);
             pm.environment.set("jwt_refresh_token", json.data.refresh_token);
         }
     }
     ```

4. **Request 3: Get User Info**
   - Method: `GET`
   - URL: `{{base_url}}/api/auth/me`
   - Headers:
     - `Authorization: Bearer {{jwt_access_token}}`

## Troubleshooting

### "This endpoint is only available in debug mode"
- Set `APP_DEBUG=true` in `.env`
- Run `php artisan config:clear`

### "EITAA_TOKEN not configured"
- Check `.env` has `EITAA_TOKEN=...`
- Run `php artisan config:clear`

### "دیتا معتبر نمی باشد!" (Invalid data)
- Make sure you're using fresh data from `/generate-test-data`
- Check token matches in `.env`
- Use `/test-validation` endpoint to debug

### Hash doesn't match
- Token in `.env` must match the token used to generate the data
- Use the generate endpoint to create data with your current token

