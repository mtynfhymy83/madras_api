# Troubleshooting ETA Login Validation

## Error: "دیتا معتبر نمی باشد!" (Invalid data)

This error means the ETA data validation failed. Here's how to fix it:

## Common Issues and Solutions

### 1. **EITAA_TOKEN Not Set**

**Problem:** The bot token is not configured in `.env`

**Solution:**
```env
EITAA_TOKEN=your_bot_token_here
```

Then clear config cache:
```bash
php artisan config:clear
```

### 2. **Wrong Token**

**Problem:** The token in `.env` doesn't match your ETA bot token

**Solution:**
- Get your bot token from ETA BotFather
- Update `.env` file
- Clear config cache

### 3. **Data Format Issue**

**Problem:** The `eitaa_data` might be incorrectly formatted

**Check:**
- Should be URL-encoded query string format
- Should contain: `auth_date`, `device_id`, `query_id`, `user`, `hash`
- The `user` field should be a JSON string

**Example format:**
```
auth_date=1760874684&device_id=...&query_id=...&user={"id":10865407,...}&hash=...
```

### 4. **Hash Validation Fails**

**Problem:** The hash doesn't match the calculated hash

**Possible causes:**
- Wrong bot token
- Data was modified/corrupted
- Missing fields in data

**Debug steps:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Look for "ETA validation debug" entries
3. Compare `provided_hash` vs `calculated_hash`

### 5. **Double URL Encoding**

**Problem:** Data might be double-encoded

**Solution:** The service now automatically decodes, but if issues persist:
- Ensure data is sent as-is from ETA
- Don't manually encode it again

## Debug Mode

Enable debug logging in `.env`:
```env
APP_DEBUG=true
```

Then check `storage/logs/laravel.log` for detailed validation information.

## Testing with Real Data

1. **Get real ETA init data** from your bot
2. **Copy it exactly** as received
3. **Don't modify** the data
4. **Ensure token matches** your bot token

## Manual Validation Test

You can test the validation manually:

```php
// In tinker: php artisan tinker
$service = app(\App\Services\Eta\EtaValidationService::class);
$data = "auth_date=...&user={...}&hash=...";
$token = "your_bot_token";
$result = $service->validateEitaData($data, $token);
// Should return true if valid
```

## Check Logs

View validation debug info:
```bash
tail -f storage/logs/laravel.log | grep "ETA validation"
```

## Common Mistakes

1. ❌ **Sending JSON instead of query string**
   - ✅ Use URL-encoded query string format

2. ❌ **Modifying the hash**
   - ✅ Send hash exactly as received

3. ❌ **Using wrong token**
   - ✅ Use the exact bot token from ETA

4. ❌ **Missing fields**
   - ✅ Ensure all required fields are present

## Still Not Working?

1. Check Laravel logs for detailed error messages
2. Verify token is correct
3. Ensure data format matches exactly
4. Test with a fresh ETA init data from your bot
5. Check if `APP_DEBUG=true` shows more details


