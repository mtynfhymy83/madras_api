# ETA Test Data Generation

## Quick Test Data Generation

Use the debug endpoint to generate valid test data with your configured token:

### Generate Test Data

**Method:** `GET`  
**URL:** `http://localhost:8000/api/auth/generate-test-data`

**Optional Query Parameters:**
- `device_id`: Custom device ID (default: random)
- `query_id`: Custom query ID (default: random)
- `user_id`: Custom user ID (default: 10865407)
- `first_name`: Custom first name (default: MahdiAli)
- `last_name`: Custom last name (default: Pak)
- `language_code`: Custom language code (default: en)

**Example:**
```
GET http://localhost:8000/api/auth/generate-test-data?user_id=10865407&first_name=Test&last_name=User
```

**Response:**
```json
{
    "success": true,
    "data": {
        "eitaa_data": "auth_date=...&device_id=...&query_id=...&user={...}&hash=...",
        "full_request": {
            "eitaa_data": "...",
            "utm": "source=test&medium=postman"
        }
    },
    "debug": {
        "auth_date": 1234567890,
        "device_id": "...",
        "query_id": "...",
        "user_data": {...},
        "data_check_string": "...",
        "calculated_hash": "..."
    }
}
```

## Manual Test Data

Based on your token, here's a pre-generated test data (valid for a limited time):

### Current Test Data

Copy the `eitaa_data` from the generate endpoint response, or use this format:

**For Postman:**

1. **Call the generate endpoint first:**
   ```
   GET http://localhost:8000/api/auth/generate-test-data
   ```

2. **Copy the `eitaa_data` from response**

3. **Use it in ETA Login:**
   ```
   POST http://localhost:8000/api/auth/eta-login
   Content-Type: application/json
   
   {
       "eitaa_data": "<paste from step 2>",
       "utm": "source=test&medium=postman"
   }
   ```

## Testing Workflow

1. **Generate Test Data:**
   ```
   GET /api/auth/generate-test-data
   ```
   Copy the `eitaa_data` value

2. **Test Validation:**
   ```
   POST /api/auth/test-validation
   {
       "eitaa_data": "<from step 1>"
   }
   ```
   Should return `hash_match_token1: true`

3. **Test Login:**
   ```
   POST /api/auth/eta-login
   {
       "eitaa_data": "<from step 1>",
       "utm": "source=test"
   }
   ```
   Should return JWT tokens

## Important Notes

- Test data expires based on `auth_date` (typically valid for a few minutes)
- Each call to `/generate-test-data` creates fresh data with current timestamp
- The hash is calculated using your `EITAA_TOKEN` from `.env`
- Make sure `APP_DEBUG=true` to use these debug endpoints

## Postman Collection

Import this into Postman:

```json
{
    "info": {
        "name": "ETA Login Test",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "item": [
        {
            "name": "1. Generate Test Data",
            "request": {
                "method": "GET",
                "header": [],
                "url": {
                    "raw": "{{base_url}}/api/auth/generate-test-data",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "generate-test-data"]
                }
            },
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    pm.environment.set('eta_test_data', jsonData.data.eitaa_data);",
                            "    console.log('✅ Test data generated and saved to environment');",
                            "}"
                        ],
                        "type": "text/javascript"
                    }
                }
            ]
        },
        {
            "name": "2. Test Validation",
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
                    "raw": "{\n    \"eitaa_data\": \"{{eta_test_data}}\",\n    \"utm\": \"source=test&medium=postman\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/api/auth/eta-login",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "eta-login"]
                }
            },
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.success && jsonData.data.access_token) {",
                            "        pm.environment.set('jwt_access_token', jsonData.data.access_token);",
                            "        pm.environment.set('jwt_refresh_token', jsonData.data.refresh_token);",
                            "        console.log('✅ Tokens saved to environment');",
                            "    }",
                            "}"
                        ],
                        "type": "text/javascript"
                    }
                }
            ]
        },
        {
            "name": "4. Get User Info",
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{jwt_access_token}}"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/api/auth/me",
                    "host": ["{{base_url}}"],
                    "path": ["api", "auth", "me"]
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

