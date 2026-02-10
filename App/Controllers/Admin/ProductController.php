<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Admin\AdminProductService;
use App\DTOs\Admin\Product\GetAdminProductsRequestDTO;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Exception;

class ProductController extends Controller
{
    private AdminProductService $service;

    public function __construct()
    {
        $this->service = new AdminProductService();
    }

    public function index(Request $req, Response $res)
    {
        // دریافت پارامترهای GET
        $inputData = $req->get ?? [];

        try {
            // اعتبارسنجی ورودی‌ها
            $this->validate([
                'page' => 'integer',
                'limit' => 'integer',
                'category_id' => 'integer',
                'publisher_id' => 'integer',
                'status' => 'integer',
                'sort' => 'string',
                'order' => 'string'
            ], $inputData);

            // ساخت DTO
            $dto = GetAdminProductsRequestDTO::fromArray($inputData);

            // فراخوانی سرویس
            $result = $this->service->getProductsList($dto);

            return $this->sendResponse($res, $result, "لیست محصولات با موفقیت دریافت شد");

        } catch (Exception $e) {
            // لاگ کردن خطا در سرور (اختیاری)
            // error_log($e->getMessage());
            
            return $this->sendResponse($res, null, "خطا در دریافت لیست محصولات", true, 500);
        }
    }
}