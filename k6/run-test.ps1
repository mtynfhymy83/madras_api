# غیرفعال کردن proxy و اجرای تست k6
$env:HTTP_PROXY = ""
$env:HTTPS_PROXY = ""
$env:http_proxy = ""
$env:https_proxy = ""
k6 run k6/load-test-book-details.js
