<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\CategoryService;
use App\DTOs\Admin\Category\CreateCategoryRequestDTO;
use App\DTOs\Admin\Category\UpdateCategoryRequestDTO;
use Exception;

class CategoryController extends Controller
{
    private CategoryService $categoryService;

    public function __construct()
    {
        parent::__construct();
        $this->categoryService = new CategoryService();
    }

    /**
     * List categories
     */
    public function index($request)
    {
        $queryParams = [];

        if (is_array($request)) {
            $queryParams = $request;
        } elseif (is_object($request) && isset($request->get) && is_array($request->get)) {
            $queryParams = $request->get;
        }

        try {
            $this->validate([
                'page' => 'integer',
                'limit' => 'integer',
                'search' => 'string',
                'parent_id' => 'integer',
                'type' => 'string|max:50',
                'is_active' => 'integer',
            ], $queryParams);

            $result = $this->categoryService->getCategoriesList($queryParams);
            return $this->sendResponse($result, 'لیست دسته‌بندی‌ها با موفقیت دریافت شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, 'خطا در دریافت لیست دسته‌بندی‌ها: ' . $e->getMessage(), true, 500);
        }
    }

    /**
     * Create a new category
     */
    public function store($request)
    {
        $data = [];
        if (is_array($request)) {
            $data = $request;
        } elseif (is_object($request)) {
            if (method_exists($request, 'getPostData')) {
                $data = $request->getPostData();
            } elseif (isset($request->post)) {
                $data = $request->post;
            } else {
                $data = (array)$request;
            }
        }

        try {
            $this->validate([
                'title' => 'required|string|min:2|max:255',
                'slug' => 'string|max:255',
                'parent_id' => 'integer',
                'icon' => 'string|max:255',
                'type' => 'string|max:50',
                'is_active' => 'integer',
                'sort_order' => 'integer',
            ], $data);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, 400);
        }

        try {
            $dto = CreateCategoryRequestDTO::fromArray($data);
            $category = $this->categoryService->createCategory($dto);
            return $this->sendResponse($category, 'دسته‌بندی با موفقیت ایجاد شد');
        } catch (Exception $e) {
            $code = (int)$e->getCode();
            $status = ($code >= 400 && $code <= 499) ? $code : 500;
            return $this->sendResponse(null, 'خطا در ایجاد دسته‌بندی: ' . $e->getMessage(), true, $status);
        }
    }

    /**
     * Update category
     */
    public function update($request, $id)
    {
        $data = [];
        if (is_array($request)) {
            $data = $request;
        } elseif (is_object($request)) {
            if (method_exists($request, 'getPostData')) {
                $data = $request->getPostData();
            } elseif (isset($request->post)) {
                $data = $request->post;
            } else {
                $data = (array)$request;
            }
        }

        try {
            $this->validate([
                'title' => 'string|min:2|max:255',
                'slug' => 'string|max:255',
                'parent_id' => 'integer',
                'icon' => 'string|max:255',
                'type' => 'string|max:50',
                'is_active' => 'integer',
                'sort_order' => 'integer',
            ], $data);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, 400);
        }

        try {
            $dto = UpdateCategoryRequestDTO::fromArray($data);
            $category = $this->categoryService->updateCategory((int)$id, $dto);
            return $this->sendResponse($category, 'دسته‌بندی با موفقیت ویرایش شد');
        } catch (Exception $e) {
            $code = (int)$e->getCode();
            $status = ($code >= 400 && $code <= 499) ? $code : 500;
            return $this->sendResponse(null, 'خطا در ویرایش دسته‌بندی: ' . $e->getMessage(), true, $status);
        }
    }

    /**
     * Delete category
     */
    public function destroy($request, $id)
    {
        try {
            $this->categoryService->deleteCategory((int)$id);
            return $this->sendResponse(null, 'دسته‌بندی با موفقیت حذف شد');
        } catch (Exception $e) {
            $code = (int)$e->getCode();
            $status = ($code >= 400 && $code <= 499) ? $code : 500;
            return $this->sendResponse(null, 'خطا در حذف دسته‌بندی: ' . $e->getMessage(), true, $status);
        }
    }
}
