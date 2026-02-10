# تست فشار API با k6

## نصب k6

- **Windows (Scoop):** `scoop install k6`
- **macOS:** `brew install k6`
- **Linux:** [راهنمای نصب](https://k6.io/docs/get-started/installation/)

## اجرای تست جزئیات کتاب

### Windows (PowerShell)

```powershell
# غیرفعال کردن proxy و اجرای تست
$env:HTTP_PROXY=""; $env:HTTPS_PROXY=""; $env:http_proxy=""; $env:https_proxy=""; k6 run k6/load-test-book-details.js
```

### Windows (CMD)

```cmd
set HTTP_PROXY= && set HTTPS_PROXY= && set http_proxy= && set https_proxy= && k6 run k6/load-test-book-details.js
```

### Linux/macOS

```bash
# غیرفعال کردن proxy و اجرای تست
HTTP_PROXY= HTTPS_PROXY= http_proxy= https_proxy= k6 run k6/load-test-book-details.js
```

سرور پیش‌فرض: `https://api-dev.madras.app`

```bash
# تست روی آدرس دیگر
HTTP_PROXY= HTTPS_PROXY= k6 run -e BASE_URL=https://api.example.com k6/load-test-book-details.js
```

### مشکل Proxy

اگر خطای `proxyconnect tcp: dial tcp 127.0.0.1:9` می‌بینی، یعنی k6 از proxy سیستم استفاده می‌کند. با دستورات بالا proxy را غیرفعال کن.

## معیارهای موفقیت (Thresholds)

- **p95 < 100ms** — ۹۵٪ درخواست‌ها زیر ۱۰۰ میلی‌ثانیه
- **p99 < 250ms** — ۹۹٪ زیر ۲۵۰ms
- **حداقل ۹۰٪** درخواست‌ها زیر 100ms
- **نرخ خطا < 1٪**

اگر هر کدام رد شود، خروجی k6 با کد خطا (non-zero) تمام می‌شود.

## خروجی‌ها

- در پایان، خلاصه در کنسول چاپ می‌شود.
- `k6/summary.json` — خام متریک‌ها
- `k6/summary.html` — گزارش خلاصه برای باز کردن در مرورگر

## تنظیم فشار

در فایل `load-test-book-details.js` آرایهٔ `options.stages` را می‌توانی عوض کنی؛ مثلاً تعداد کاربر همزمان (target) یا مدت هر مرحله (duration). آرایهٔ `BOOK_IDS` را با آیدی کتاب‌های واقعی پر کن.
