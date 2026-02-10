<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Requests\Auth\LoginRequest;
use App\Helpers\ViewHelper;
use App\Helpers\Context;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        // سرویس لاگین را اینجا لود می‌کنیم
        $this->authService = new AuthService(); 
    }

    /**
     * نمایش فرم لاگین (GET)
     */
    public function showLoginForm()
    {
        // اگر کاربر قبلاً لاگین است، به داشبورد هدایت شود
        $request = Context::getRequest();
        if (isset($request->cookie['admin_token'])) {
             return '<script>window.location.href="/v1/admin/home";</script>';
        }

        // رندر کردن فایل views/admin/login.php
        // دقت کنید که در ViewHelper جدید، پسوند .php و پیشوند v_ حذف شده
        return ViewHelper::render('admin/login', [
            'title' => 'ورود به مدیریت'
        ]);
    }

    /**
     * پردازش لاگین (POST AJAX)
     */
    public function login()
    {
        // دریافت داده‌ها از درخواست
        $request = Context::getRequest();
        // چون درخواست JSON است، باید بادی را بخوانیم
        $data = json_decode($request->rawContent(), true) ?? $request->post;

        try {
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($username) || empty($password)) {
                return ['success' => false, 'message' => 'نام کاربری و رمز عبور الزامی است'];
            }

            // استفاده از AuthService برای لاگین واقعی
            $requestObj = (object) [
                'username' => $username,
                'password' => $password,
                'device_name' => 'Admin Panel Web',
                'device_type' => 'web',
                'platform' => 'admin'
            ];
            
            $loginRequest = LoginRequest::fromObject($requestObj);
            $result = $this->authService->loginWithCredentials($loginRequest);
            
            if (!isset($result['access_token'])) {
                throw new \Exception('خطا در دریافت توکن');
            }
            
            $token = $result['access_token'];
            
            // ذخیره توکن در کوکی
            $response = Context::getResponse();
            $response->cookie(
                'admin_token', 
                $token, 
                time() + 3600, 
                '/', 
                '', 
                false, 
                true
            );

            return [
                'success' => true,
                'message' => 'ورود موفق',
                'token' => $token,
                'redirect' => '/v1/admin/home'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false, 
                'message' => $e->getMessage() ?: 'نام کاربری یا رمز عبور اشتباه است'
            ];
        }
    }

    public function logout()
    {
        $response = Context::getResponse();
        // حذف کوکی
        $response->cookie('admin_token', '', time() - 3600);
        
        return '<script>window.location.href="/v1/admin/login";</script>';
    }
}