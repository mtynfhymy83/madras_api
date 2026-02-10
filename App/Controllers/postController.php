<?php

namespace App\Controllers\Admin;

use App\Helpers\Context;
use App\Models\Book;
use App\Database\QueryBuilder;

class PostController
{
    /**
     * ذخیره کتاب (ایجاد یا ویرایش)
     * مسیر: /admin/api/post/save/{action}
     */
    public function save($action = 'draft')
    {
        // 1. دریافت داده‌های فرم
        $postData = $_POST['data'] ?? [];
        $metaData = $_POST['meta'] ?? [];
        $nashrData = $_POST['nashr'] ?? [];
        $categories = $_POST['category'] ?? [];
        
        // آی‌دی برای تشخیص ویرایش یا افزودن
        $id = $_POST['id'] ?? null;
        if (empty($id)) $id = null;

        // 2. اعتبارسنجی اولیه
        if (empty($postData['title'])) {
            return $this->jsonResponse(false, 'عنوان کتاب الزامی است.');
        }

        try {
            // 3. مپ کردن داده‌های فرم به ستون‌های جدول books
            $dbData = [
                'title' => $postData['title'],
                'excerpt' => $postData['excerpt'] ?? null,
                'meta_keywords' => $postData['meta_keywords'] ?? null,
                'meta_description' => $postData['meta_description'] ?? null,
                'thumb' => $postData['thumb'] ?? null,
                'icon' => $postData['icon'] ?? null,
                'accept_cm' => isset($postData['accept_cm']) ? (int)$postData['accept_cm'] : 1,
                
                // اطلاعات محصول (قیمت و تخفیف)
                'price' => isset($metaData['price']) ? (float)$metaData['price'] : 
                          (isset($metaData['product']['price']) ? (float)$metaData['product']['price'] : 0),
                
                'discount_price' => isset($metaData['product']['off']) ? (float)$metaData['product']['off'] : 0,
                
                // اطلاعات نشر
                'publisher_id' => !empty($nashrData['publisher']) ? (int)$nashrData['publisher'] : null,
                
                // وضعیت انتشار (بر اساس دکمه‌ای که زده شده)
                'published' => ($action === 'publish') ? 1 : 0,
                'draft' => ($action === 'draft') ? 1 : 0,
            ];

            // 4. ذخیره در دیتابیس (استفاده از مدل Book)
            // این متد را در مرحله قبل در مدل Book نوشتیم
            $bookId = Book::save($dbData, $id);

            if (!$bookId) {
                throw new \Exception('خطا در ذخیره اطلاعات کتاب.');
            }

            // 5. ذخیره دسته‌بندی‌ها (Pivot Table)
            if (!empty($categories)) {
                Book::syncCategories($bookId, $categories);
            }

            // 6. ذخیره نویسنده (Pivot Table)
            // در فرم شما name="data[author]" است
            if (!empty($postData['author'])) {
                Book::syncAuthors($bookId, $postData['author']);
            }

            // 7. بازگشت پاسخ موفقیت‌آمیز
            return $this->jsonResponse(true, 'عملیات با موفقیت انجام شد.', [
                'id' => $bookId,
                'redirect' => '/v1/admin/book/primary' // بعد از ذخیره به لیست برگردد
            ]);

        } catch (\Exception $e) {
            // لاگ کردن خطا برای دیباگ
            error_log($e->getMessage());
            return $this->jsonResponse(false, 'خطای سرور: ' . $e->getMessage());
        }
    }

    /**
     * متد کمکی برای پاسخ JSON
     */
    private function jsonResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
        return json_encode([
            'success' => $success,
            'message' => $message,
            'data'   => $data
        ]);
    }
}