<?php

use App\Routers\Router;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\BookController;
use App\Controllers\BookContentController;
use App\Controllers\BookReviewController;
use App\Controllers\BenchmarkController;
use App\Controllers\PaymentController;
use App\Controllers\BookActionController;

return static function (Router $router): void {

    // =====================================
    // احراز هویت (Auth)
    // =====================================
    $router->prefix('/auth', function (Router $r) {
        $r->post('v1', '/login', AuthController::class, 'login');
        $r->post('v1', '/register', AuthController::class, 'register');
        $r->post('v1', '/eitaa', AuthController::class, 'eitaaLogin');
        $r->post('v1', '/otp/send', AuthController::class, 'sendOtp');
        $r->post('v1', '/refresh', AuthController::class, 'refresh');

        // روت‌های نیازمند لاگین در Auth
        $r->get('v1', '/me', AuthController::class, 'me', 'auth'); // میدلور auth
        $r->post('v1', '/logout', AuthController::class, 'logout', 'auth');
    });

    // =====================================
    // پروفایل کاربر
    // =====================================
    $router->prefix('/profile', function (Router $r) {
        $r->put('v1', '/update', UserController::class, 'updateProfile', 'auth');
        $r->post('v1', '/avatar', UserController::class, 'uploadAvatar', 'auth');
        $r->get('v1', '/library', BookController::class, 'myLibrary');
    });

    // =====================================
    // کتاب‌ها (Public API)
    // =====================================
    $router->prefix('/books', function (Router $r) {
        // لیست کتاب‌ها
        $r->get('v1', '', BookController::class, 'index');
        // خرید کتاب (نیاز به auth)
        $r->post('v1', '/buy', BookController::class, 'buyBook', 'auth');
        // جزئیات یک کتاب (قبل از /{book_id}/... تا تداخل نداشته باشه)
        $r->get('v1', '/{id}', BookController::class, 'show');
    });

    // =====================================
    // عملیات کاربری روی کتاب (سبد خرید / مطالعه شده / میز مطالعه)
    // =====================================
    $router->prefix('/books', function (Router $r) {
        $r->post('v1', '/{book_id}/cart', BookActionController::class, 'addToCart', 'auth');
        $r->delete('v1', '/{book_id}/cart', BookActionController::class, 'removeFromCart', 'auth');

        $r->post('v1', '/{book_id}/read', BookActionController::class, 'markAsRead', 'auth');
        $r->delete('v1', '/{book_id}/read', BookActionController::class, 'unmarkRead', 'auth');

        $r->post('v1', '/{book_id}/desk', BookActionController::class, 'addToStudyDesk', 'auth');
        $r->delete('v1', '/{book_id}/desk', BookActionController::class, 'removeFromStudyDesk', 'auth');
    });

    // =====================================
    // محتوای کتاب (خواندن توسط کاربر - نیاز به توکن)
    // =====================================
    $router->prefix('/books', function (Router $r) {
        // بررسی دسترسی کاربر به کتاب
        $r->get('v1', '/{book_id}/access', BookContentController::class, 'access');
        // لیست صفحات کتاب
        $r->get('v1', '/{book_id}/pages/list', BookContentController::class, 'getPages');
        // محتوای یک صفحه
        $r->get('v1', '/{book_id}/pages/{page_number}', BookContentController::class, 'getPageContents');
        // لیست محتواها با صفحه‌بندی
        $r->get('v1', '/{book_id}/contents', BookContentController::class, 'index');
        // دانلود کل کتاب (یکجا، با gzip توصیه می‌شود)
        $r->get('v1', '/{book_id}/contents/full', BookContentController::class, 'getFullContents');
        // جستجو در محتوا (قبل از /{id} تا با dynamic route تداخل نگیرد)
        $r->get('v1', '/{book_id}/contents/search', BookContentController::class, 'search');
        // یک محتوا با ID
        $r->get('v1', '/{book_id}/contents/{id}', BookContentController::class, 'show');
        // فهرست کتاب (Table of Contents)
        $r->get('v1', '/{book_id}/index', BookContentController::class, 'getIndex');
    });

    // =====================================
    // دیدگاه‌های کتاب (Reviews)
    // =====================================
    $router->prefix('/books', function (Router $r) {
        // لیست دیدگاه‌ها (public)
        $r->get('v1', '/{book_id}/reviews', BookReviewController::class, 'index');
        // ثبت دیدگاه (نیاز به توکن)
        $r->post('v1', '/{book_id}/reviews', BookReviewController::class, 'store');
        // ویرایش دیدگاه (نیاز به توکن، فقط صاحب)
        $r->put('v1', '/{book_id}/reviews/{id}', BookReviewController::class, 'update');
        // حذف دیدگاه (نیاز به توکن، فقط صاحب)
        $r->delete('v1', '/{book_id}/reviews/{id}', BookReviewController::class, 'destroy');
        // پسندیدن دیدگاه (نیاز به توکن)
        $r->post('v1', '/{book_id}/reviews/{id}/like', BookReviewController::class, 'like');
    });

    // =====================================
    // پرداخت (درگاه - بدون auth؛ paybook لینک مستقیم، verify callback بانک)
    // =====================================
    $router->prefix('/payment', function (Router $r) {
        $r->get('v1', '/paybook/{id}', PaymentController::class, 'paybook');
        $r->post('v1', '/verify/{section}', PaymentController::class, 'verify');
    });

    // =====================================
    // Benchmark (برای تست عملکرد - public)
    // =====================================
    $router->prefix('/benchmark', function (Router $r) {
        // بررسی گلوگاه‌های عملکرد برای book details
        $r->get('v1', '/book/{id}', BenchmarkController::class, 'bookDetails');
        // میانگین چند بار اجرا
        $r->get('v1', '/book/{id}/avg', BenchmarkController::class, 'bookDetailsAvg');
    });
};
