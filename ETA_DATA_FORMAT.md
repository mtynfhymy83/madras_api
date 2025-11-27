# ETA Init Data Format

## Format

ETA sends init data as a **URL-encoded query string**, not JSON.

## Example

```
auth_date=1760874684&device_id=0261d09fc99223b94b07ded42c2f340b&query_id=2721441469101642&user={"id":10865407,"first_name":"MahdiAli","last_name":"Pak","language_code":"en","allows_write_to_pm":true}&hash=cddf185431e041c88eedf8a9bc4e3e7f6f8e2c798fde294018e4b1a5ec9a0f47
```

## Fields

- `auth_date`: Unix timestamp of authentication
- `device_id`: Unique device identifier
- `query_id`: Query identifier
- `user`: JSON string containing user data
  - `id`: ETA user ID (required)
  - `first_name`: User's first name
  - `last_name`: User's last name
  - `username`: Username (optional)
  - `language_code`: Language code (e.g., "en", "fa")
  - `allows_write_to_pm`: Boolean, whether user allows PM
- `hash`: HMAC SHA256 hash for validation

## User JSON Structure

The `user` field is a JSON string that gets parsed:

```json
{
    "id": 10865407,
    "first_name": "MahdiAli",
    "last_name": "Pak",
    "language_code": "en",
    "allows_write_to_pm": true
}
```

## Validation Process

1. Parse URL-encoded query string
2. Extract `hash` field
3. Sort remaining fields by key
4. Create data check string: `key=value\nkey=value\n...`
5. Calculate secret key: `HMAC_SHA256(token, "WebAppData")`
6. Calculate hash: `hex(HMAC_SHA256(data_check_string, secret_key))`
7. Compare with provided hash

## Usage in API

Send the entire query string as `eitaa_data`:

```json
{
    "eitaa_data": "auth_date=1760874684&device_id=...&user={...}&hash=...",
    "utm": "source=eitaa&medium=webapp"
}
```

The system will:
1. Parse the query string
2. Extract and parse the `user` JSON field
3. Validate the hash
4. Process authentication


