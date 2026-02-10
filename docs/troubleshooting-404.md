# عیب‌یابی خطای 404 در Production

## مشکل: همه endpoint ها خطای 404 از Google می‌دهند

این یعنی درخواست‌ها به سرور Swoole نمی‌رسند.

## بررسی‌های لازم

### 1. آیا سرور Swoole در حال اجراست؟

```bash
# در سرور production
ps aux | grep "php.*server.php"
# یا
docker ps | grep php_swoole
```

### 2. آیا پورت 9501 باز است و گوش می‌دهد؟

```bash
# تست مستقیم به پورت 9501
curl http://localhost:9501/api/v1/books/7

# یا از خارج
curl http://[SERVER_IP]:9501/api/v1/books/7
```

### 3. آیا یک Reverse Proxy (Nginx/Apache) در جلوی سرور است؟

اگر از Nginx استفاده می‌کنی، باید تنظیمات proxy_pass درست باشد:

```nginx
location /api/ {
    proxy_pass http://127.0.0.1:9501;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

### 4. آیا DNS درست است؟

```bash
# بررسی DNS
nslookup api-dev.madras.app
# یا
dig api-dev.madras.app
```

### 5. آیا فایروال پورت 9501 را بسته است؟

```bash
# بررسی فایروال
sudo ufw status
# یا
sudo iptables -L -n | grep 9501
```

## راه‌حل‌های احتمالی

### اگر از Nginx استفاده می‌کنی:

1. بررسی کن که Nginx در حال اجراست: `sudo systemctl status nginx`
2. بررسی تنظیمات Nginx: `sudo nginx -t`
3. بررسی لاگ Nginx: `sudo tail -f /var/log/nginx/error.log`

### اگر سرور Swoole در حال اجرا نیست:

```bash
# راه‌اندازی مجدد
cd /path/to/Madras_Api
php server.php
# یا
docker-compose up -d
```

### اگر پورت اشتباه است:

بررسی کن که `APP_PORT` در `.env` یا environment variables درست تنظیم شده باشد.
