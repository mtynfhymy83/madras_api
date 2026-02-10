@echo off
REM غیرفعال کردن proxy و اجرای تست k6
set HTTP_PROXY=
set HTTPS_PROXY=
set http_proxy=
set https_proxy=
k6 run k6/load-test-book-details.js
