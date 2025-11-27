# Postman Testing Guide - ETA Login

## Step 1: Set Up Postman Environment

1. **Create New Environment:**
   - Click the gear icon (⚙️) in top right
   - Click "Add" to create new environment
   - Name it: `Laravel ETA Test`

2. **Add Variables:**
   - `base_url` = `http://localhost:8000`
   - `eta_test_data` = (leave empty, will be auto-filled)
   - `jwt_access_token` = (leave empty, will be auto-filled)
   - `jwt_refresh_token` = (leave empty, will be auto-filled)

3. **Select the environment** from the dropdown in top right

## Step 2: Create Request Collection

Create a new collection: `ETA Login Tests`

## Step 3: Request 1 - Generate Test Data

### Setup:
- **Method:** `GET`
- **URL:** `{{base_url}}/api/auth/generate-test-data`
- **Headers:** (none needed)

### Tests Tab (Auto-save test data):
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    if (jsonData.success && jsonData.data.eitaa_data) {
        pm.environment.set("eta_test_data", jsonData.data.eitaa_data);
        console.log("✅ Test data generated and saved!");
        console.log("EITA Data:", jsonData.data.eitaa_data);
    }
}
```

### Send Request:
Click "Send" - this will generate fresh test data and save it to `eta_test_data` variable.

---

## Step 4: Request 2 - Test Validation (Optional Debug)

### Setup:
- **Method:** `POST`
- **URL:** `{{base_url}}/api/auth/test-validation`
- **Headers:**
  - `Content-Type`: `application/json`
  - `Accept`: `application/json`

### Body (raw JSON):
```json
{
    "eitaa_data": "{{eta_test_data}}"
}
```

### Tests Tab:
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    var debug = jsonData.debug;
    
    console.log("🔍 Validation Debug:");
    console.log("Hash Match (Token 1):", debug.hash_match_token1);
    console.log("Validation Result:", debug.validation_result);
    
    if (debug.hash_match_token1) {
        console.log("✅ Token matches! Validation should work.");
    } else {
        console.log("❌ Token doesn't match. Check your EITAA_TOKEN in .env");
    }
}
```

---

## Step 5: Request 3 - ETA Login (Main Test)

### Setup:
- **Method:** `POST`
- **URL:** `{{base_url}}/api/auth/eta-login`
- **Headers:**
  - `Content-Type`: `application/json`
  - `Accept`: `application/json`

### Body (raw JSON):
```json
{
    "eitaa_data": "{{eta_test_data}}",
    "utm": "source=test&medium=postman"
}
```

### Tests Tab (Auto-save tokens):
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    
    if (jsonData.success && jsonData.data.access_token) {
        pm.environment.set("jwt_access_token", jsonData.data.access_token);
        pm.environment.set("jwt_refresh_token", jsonData.data.refresh_token);
        
        console.log("✅ Login successful!");
        console.log("Access Token saved to environment");
        console.log("User:", jsonData.data.user);
    } else {
        console.log("❌ Login failed:", jsonData.message);
    }
} else {
    console.log("❌ Request failed:", pm.response.status);
    console.log("Response:", pm.response.text());
}
```

### Expected Response:
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

---

## Step 6: Request 4 - Get User Info (Protected Route)

### Setup:
- **Method:** `GET`
- **URL:** `{{base_url}}/api/auth/me`
- **Headers:**
  - `Authorization`: `Bearer {{jwt_access_token}}`
  - `Accept`: `application/json`

### Tests Tab:
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    console.log("✅ User authenticated!");
    console.log("User data:", jsonData.data);
} else if (pm.response.code === 401) {
    console.log("❌ Unauthorized - Token invalid or expired");
} else {
    console.log("❌ Error:", pm.response.status);
}
```

---

## Step 7: Request 5 - Refresh Token (Optional)

### Setup:
- **Method:** `POST`
- **URL:** `{{base_url}}/api/auth/refresh`
- **Headers:**
  - `Content-Type`: `application/json`
  - `Accept`: `application/json`

### Body (raw JSON):
```json
{
    "refresh_token": "{{jwt_refresh_token}}"
}
```

### Tests Tab:
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    if (jsonData.success && jsonData.data.access_token) {
        pm.environment.set("jwt_access_token", jsonData.data.access_token);
        pm.environment.set("jwt_refresh_token", jsonData.data.refresh_token);
        console.log("✅ Token refreshed!");
    }
}
```

---

## Step 8: Request 6 - Logout (Optional)

### Setup:
- **Method:** `POST`
- **URL:** `{{base_url}}/api/auth/logout`
- **Headers:**
  - `Content-Type`: `application/json`
  - `Accept`: `application/json`

### Body (raw JSON):
```json
{
    "refresh_token": "{{jwt_refresh_token}}"
}
```

---

## Complete Postman Collection JSON

Import this into Postman for a ready-to-use collection:

```json
{
    "info": {
        "name": "ETA Login Tests",
        "description": "Complete ETA login testing collection",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "item": [
        {
            "name": "1. Generate Test Data",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.success && jsonData.data.eitaa_data) {",
                            "        pm.environment.set(\"eta_test_data\", jsonData.data.eitaa_data);",
                            "        console.log(\"✅ Test data generated and saved!\");",
                            "    }",
                            "}"
                        ],
                        "type": "text/javascript"
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [],
                "url": {
                    "raw": "{{base_url}}/api/auth/generate-test-data",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "generate-test-data"]
                }
            }
        },
        {
            "name": "2. Test Validation (Debug)",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    var debug = jsonData.debug;",
                            "    console.log(\"Hash Match:\", debug.hash_match_token1);",
                            "    console.log(\"Validation Result:\", debug.validation_result);",
                            "}"
                        ],
                        "type": "text/javascript"
                    }
                }
            ],
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
                    "raw": "{\n    \"eitaa_data\": \"{{eta_test_data}}\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/api/auth/test-validation",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "test-validation"]
                }
            }
        },
        {
            "name": "3. ETA Login",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.success && jsonData.data.access_token) {",
                            "        pm.environment.set(\"jwt_access_token\", jsonData.data.access_token);",
                            "        pm.environment.set(\"jwt_refresh_token\", jsonData.data.refresh_token);",
                            "        console.log(\"✅ Login successful! Tokens saved.\");",
                            "    }",
                            "}"
                        ],
                        "type": "text/javascript"
                    }
                }
            ],
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
                    "raw": "{\n    \"eitaa_data\": \"{{eta_test_data}}\",\n    \"utm\": \"source=test&medium=postman\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/api/auth/eta-login",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "eta-login"]
                }
            }
        },
        {
            "name": "4. Get User Info",
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{jwt_access_token}}"
                    },
                    {
                        "key": "Accept",
                        "value": "application/json"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/api/auth/me",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "me"]
                }
            }
        },
        {
            "name": "5. Refresh Token",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.success && jsonData.data.access_token) {",
                            "        pm.environment.set(\"jwt_access_token\", jsonData.data.access_token);",
                            "        pm.environment.set(\"jwt_refresh_token\", jsonData.data.refresh_token);",
                            "        console.log(\"✅ Token refreshed!\");",
                            "    }",
                            "}"
                        ],
                        "type": "text/javascript"
                    }
                }
            ],
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
                    "raw": "{\n    \"refresh_token\": \"{{jwt_refresh_token}}\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/api/auth/refresh",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "refresh"]
                }
            }
        },
        {
            "name": "6. Logout",
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
                    "raw": "{\n    \"refresh_token\": \"{{jwt_refresh_token}}\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/api/auth/logout",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "logout"]
                }
            }
        }
    ],
    "variable": [
        {
            "key": "base_url",
            "value": "http://localhost:8000"
        }
    ]
}
```

---

## Quick Testing Workflow

1. **Run Request 1** (Generate Test Data) - This auto-saves `eta_test_data`
2. **Run Request 3** (ETA Login) - This auto-saves `jwt_access_token` and `jwt_refresh_token`
3. **Run Request 4** (Get User Info) - Uses the saved token automatically

## Troubleshooting

### "This endpoint is only available in debug mode"
- Set `APP_DEBUG=true` in `.env`
- Run `php artisan config:clear` in terminal

### "EITAA_TOKEN not configured"
- Check `.env` has `EITAA_TOKEN=...`
- Run `php artisan config:clear`

### Variables not saving
- Make sure you selected the environment in top right
- Check the Tests tab code is correct
- Look at Postman Console (View → Show Postman Console) for errors

### Token expired
- Run Request 1 again to get fresh test data
- Run Request 3 again to get new tokens

---

## Visual Guide

### Environment Setup:
```
⚙️ Environments → Add
Name: Laravel ETA Test
Variables:
  base_url = http://localhost:8000
  eta_test_data = (empty)
  jwt_access_token = (empty)
  jwt_refresh_token = (empty)
```

### Request Structure:
```
📁 ETA Login Tests
  ├── 1. Generate Test Data (GET)
  ├── 2. Test Validation (POST) [Optional]
  ├── 3. ETA Login (POST) ⭐ Main
  ├── 4. Get User Info (GET)
  ├── 5. Refresh Token (POST)
  └── 6. Logout (POST)
```

---

## Tips

1. **Use Collection Runner:** Select all requests and click "Run" to test the full flow
2. **Check Console:** View → Show Postman Console to see test script outputs
3. **Save Responses:** Right-click response → Save Response to save examples
4. **Use Pre-request Scripts:** Add delays if needed between requests

