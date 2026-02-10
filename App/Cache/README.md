# Cache System Refactoring

## تغییرات

سیستم Cache به صورت کامل refactor شد با این اهداف:

1. ✅ **Interface-based Design**: استفاده از `CacheStoreInterface` برای قابلیت تست و تعویض
2. ✅ **حذف Memory Fallback**: در Swoole، memory cache با restart شدن workers از بین می‌ره
3. ✅ **استفاده از Redis Extension**: اگر نصب باشه، از `\Redis` (native C) استفاده می‌کنه که سریعتر از Predis هست
4. ✅ **Auto-fallback**: اگر Redis extension نصب نباشه، خودکار به Predis fallback می‌کنه

## ساختار

```
App/Cache/
├── CacheStoreInterface.php  # Interface اصلی
├── RedisCache.php           # Implementation با \Redis extension
├── PredisCache.php          # Fallback با Predis (pure PHP)
└── Cache.php                # Facade/Singleton wrapper
```

## استفاده

```php
use App\Cache\Cache;

// Get
$value = Cache::get('key');

// Set (TTL optional, default 600 seconds)
Cache::set('key', $value, 600);

// Delete
Cache::delete('key');

// Check exists
if (Cache::has('key')) {
    // ...
}

// Clear all
Cache::clear();
```

## Migration از Cache قدیمی

همه استفاده‌های `App\Database\Cache` به `App\Cache\Cache` تغییر کرده.

**تغییرات:**
- ✅ `BookRepository.php`
- ✅ `BookReviewRepository.php`
- ✅ `UserController.php`
- ✅ `CacheInvalidationTrait.php`

## Configuration

از طریق `.env`:

```env
CACHE_ENABLED=true
CACHE_DRIVER=redis
CACHE_TTL=600
CACHE_PREFIX=madras_
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_PASSWORD=
```

## Performance

- **Redis Extension**: ~2-3x سریعتر از Predis
- **Serialization**: از `serialize()` استفاده می‌کنه (سریعتر از JSON)
- **No Memory Fallback**: در Swoole memory cache مشکل‌ساز بود

## نکات مهم

1. **Initialization**: Cache در `server.php` -> `WorkerStart` initialize می‌شه
2. **Error Handling**: خطاهای Redis باعث crash نمی‌شن، فقط log می‌شن
3. **Swoole Compatibility**: Connection pooling و error handling برای Swoole optimize شده
