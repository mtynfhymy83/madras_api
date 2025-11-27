# Testing Specific ETA Data

## Test Data

```
auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={"id":10865407,"first_name":"MahdiAli","last_name":"Pak","language_code":"en","allows_write_to_pm":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47
```

## Step 1: Test Validation (Debug Endpoint)

First, test if the validation works with your token:

### Postman Request

**Method:** `POST`  
**URL:** `http://localhost:8000/api/auth/test-validation`  
**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
    "eitaa_data": "auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={\"id\":10865407,\"first_name\":\"MahdiAli\",\"last_name\":\"Pak\",\"language_code\":\"en\",\"allows_write_to_pm\":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47"
}
```

### Expected Response

```json
{
    "success": true,
    "debug": {
        "provided_hash": "cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47",
        "calculated_hash_token1": "...",
        "hash_match_token1": true/false,
        "data_check_string": "auth_date=...\ndevice_id=...\nquery_id=...\nuser=...",
        "parsed_data": {...},
        "has_token1": true,
        "token1_length": 123,
        "validation_result": true/false
    }
}
```

**What to check:**
- `hash_match_token1`: Should be `true` if token is correct
- `validation_result`: Should be `true` if validation passes
- If `hash_match_token1` is `false`, your token doesn't match

## Step 2: Test Actual Login

Once validation passes, test the actual login:

### Postman Request

**Method:** `POST`  
**URL:** `http://localhost:8000/api/auth/eta-login`  
**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
    "eitaa_data": "auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={\"id\":10865407,\"first_name\":\"MahdiAli\",\"last_name\":\"Pak\",\"language_code\":\"en\",\"allows_write_to_pm\":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47",
    "utm": "source=test&medium=postman"
}
```

## Important Notes

1. **Token Must Match**: The hash in the data was calculated with a specific bot token. Without that exact token, validation will fail.

2. **Enable Debug Mode**: Make sure `APP_DEBUG=true` in `.env` to use the test endpoint.

3. **Check Token**: The test endpoint will show you if your token matches the data.

4. **Real Data**: This test data might not work if:
   - Your `EITAA_TOKEN` doesn't match the token used to generate the hash
   - The data is expired (check `auth_date`)
   - The data was modified

## Troubleshooting

### If `hash_match_token1` is `false`:

1. **Wrong Token**: Your `EITAA_TOKEN` doesn't match the token used to create this data
2. **Solution**: Get the correct token from ETA BotFather or use fresh data from your bot

### If validation fails:

1. Check `calculated_hash_token1` vs `provided_hash`
2. Check `data_check_string` format
3. Verify token is set correctly in `.env`

## Quick Test Script

Save this in Postman **Tests** tab for the test-validation endpoint:

```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    var debug = jsonData.debug;
    
    console.log("🔍 Validation Debug:");
    console.log("Hash Match (Token 1):", debug.hash_match_token1);
    console.log("Hash Match (Token 2):", debug.hash_match_token2 || "N/A");
    console.log("Validation Result:", debug.validation_result);
    console.log("Provided Hash:", debug.provided_hash);
    console.log("Calculated Hash:", debug.calculated_hash_token1);
    
    if (debug.hash_match_token1) {
        console.log("✅ Token 1 matches!");
    } else if (debug.hash_match_token2) {
        console.log("✅ Token 2 matches!");
    } else {
        console.log("❌ No token matches. Check your EITAA_TOKEN in .env");
    }
}
```


