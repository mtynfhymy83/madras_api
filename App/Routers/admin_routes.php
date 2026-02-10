<?php

use App\Routers\Router;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\BookController;
use App\Controllers\Admin\PublisherController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\PermissionController;
use App\Controllers\Admin\BookContentController;

return static function (Router $router): void {
    
    $router->prefix('/admin', function (Router $r) {

        // =====================================
        // بخش محافظت شده (نیاز به AuthMiddleware)
        // =====================================

        // --- Users Management ---
        $r->prefix('/users', function ($u) {
            // لیست کاربران (Index)
            $u->get('v1', '/list', \App\Controllers\Admin\UserController::class, 'index');
        });
        
        // --- Books Management ---
        $r->prefix('/books', function ($b) {
            // لیست کتاب‌ها (Index)
            $b->get('v1', '/list', \App\Controllers\Admin\BookController::class, 'index');
            // ایجاد کتاب (Store)
            $b->post('v1', '/create', \App\Controllers\Admin\BookController::class, 'store');
            
            // --- Book Contents Management ---
            // Pages
            $b->get('v1', '/{book_id}/pages/list', BookContentController::class, 'getPages');
            $b->get('v1', '/{book_id}/pages/{page_number}', BookContentController::class, 'getPageContents');
            $b->post('v1', '/{book_id}/pages/create', BookContentController::class, 'createPage');
            $b->post('v1', '/{book_id}/pages/{page_number}/paragraphs', BookContentController::class, 'addParagraph');
            $b->delete('v1', '/{book_id}/pages/{page_number}', BookContentController::class, 'deletePage');
            
            // Contents (paragraphs)
            $b->get('v1', '/{book_id}/contents', BookContentController::class, 'index');
            $b->get('v1', '/{book_id}/contents/search', BookContentController::class, 'search');
            $b->get('v1', '/{book_id}/contents/{id}', BookContentController::class, 'show');
            $b->post('v1', '/{book_id}/contents', BookContentController::class, 'store');
            $b->post('v1', '/{book_id}/contents/batch', BookContentController::class, 'batchCreate');
            $b->put('v1', '/{book_id}/contents/reorder', BookContentController::class, 'reorder');
            $b->put('v1', '/{book_id}/contents/{id}', BookContentController::class, 'update');
            $b->delete('v1', '/{book_id}/contents/{id}', BookContentController::class, 'destroy');
            
            // Index (Table of Contents)
            $b->get('v1', '/{book_id}/index', BookContentController::class, 'getIndex');
            $b->post('v1', '/{book_id}/contents/{id}/set-index', BookContentController::class, 'setAsIndex');
            $b->delete('v1', '/{book_id}/contents/{id}/remove-index', BookContentController::class, 'removeFromIndex');
            
            // Media (Audio/Image)
            $b->post('v1', '/{book_id}/contents/{id}/upload-audio', BookContentController::class, 'uploadAudio');
            $b->post('v1', '/{book_id}/contents/{id}/upload-image', BookContentController::class, 'uploadImage');
            $b->delete('v1', '/{book_id}/contents/{id}/remove-audio', BookContentController::class, 'removeAudio');
            $b->delete('v1', '/{book_id}/contents/{id}/remove-image', BookContentController::class, 'removeImage');
        });

        // --- Publishers Management ---
        $r->prefix('/publishers', function ($p) {
            // لیست ناشران (Index)
            $p->get('v1', '/list', \App\Controllers\Admin\PublisherController::class, 'index');
            // ایجاد ناشر (Store)
            $p->post('v1', '/create', \App\Controllers\Admin\PublisherController::class, 'store');
            // دریافت ناشر (Show)
            $p->get('v1', '/{id}', \App\Controllers\Admin\PublisherController::class, 'show');
        });

        // --- Categories Management ---
        $r->prefix('/categories', function ($c) {
            // لیست دسته‌بندی‌ها (Index)
            $c->get('v1', '/list', \App\Controllers\Admin\CategoryController::class, 'index');
            // ایجاد دسته‌بندی (Store)
            $c->post('v1', '/create', \App\Controllers\Admin\CategoryController::class, 'store');
            // ویرایش دسته‌بندی (Update)
            $c->put('v1', '/{id}', \App\Controllers\Admin\CategoryController::class, 'update');
            // حذف دسته‌بندی (Delete)
            $c->delete('v1', '/{id}', \App\Controllers\Admin\CategoryController::class, 'destroy');
        });

        // --- Payments Management ---
        $r->prefix('/payments', function ($pa) {
            // لیست پرداخت‌ها (Transactions)
            $pa->get('v1', '/list', \App\Controllers\Admin\PaymentController::class, 'index');
        });

        // --- Roles Management (فقط سوپرادمین) ---
        $r->prefix('/roles', function ($ro) {
            // لیست تمام نقش‌ها
            $ro->get('v1', '/list', \App\Controllers\Admin\RoleController::class, 'index');
            // دریافت یک نقش با permissions
            $ro->get('v1', '/{id}', \App\Controllers\Admin\RoleController::class, 'show');
            // تخصیص permission به role
            $ro->post('v1', '/assign-permission', \App\Controllers\Admin\RoleController::class, 'assignPermission');
            // حذف permission از role
            $ro->delete('v1', '/remove-permission', \App\Controllers\Admin\RoleController::class, 'removePermission');
            // تخصیص role به user
            $ro->post('v1', '/assign-to-user', \App\Controllers\Admin\RoleController::class, 'assignToUser');
            // حذف role از user
            $ro->delete('v1', '/remove-from-user', \App\Controllers\Admin\RoleController::class, 'removeFromUser');
            // دریافت نقش‌های یک کاربر
            $ro->get('v1', '/user/{user_id}', \App\Controllers\Admin\RoleController::class, 'getUserRoles');
        });
        
        // --- Permissions Management (فقط سوپرادمین) ---
        $r->prefix('/permissions', function ($pe) {
            // لیست تمام دسترسی‌ها
            $pe->get('v1', '/list', \App\Controllers\Admin\PermissionController::class, 'index');
            // دریافت دسترسی‌ها به تفکیک category
            $pe->get('v1', '/grouped', \App\Controllers\Admin\PermissionController::class, 'grouped');
            // دریافت دسترسی‌های یک نقش
            $pe->get('v1', '/role/{role_id}', \App\Controllers\Admin\PermissionController::class, 'getRolePermissions');
            // دریافت دسترسی‌های یک کاربر
            $pe->get('v1', '/user/{user_id}', \App\Controllers\Admin\PermissionController::class, 'getUserPermissions');
        });

        // --- Dashboard Stats ---
        $r->prefix('/dashboard', function ($d) {
            // آمار کلی داشبورد
            $d->get('v1', '/stats', \App\Controllers\Admin\DashboardController::class, 'stats');
        });

    }); 
};
