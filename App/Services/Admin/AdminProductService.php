<?php

namespace App\Services\Admin;

use App\Repositories\ProductRepository;
use App\DTOs\Admin\Product\GetAdminProductsRequestDTO;

class AdminProductService
{
    private ProductRepository $productRepo;

    public function __construct()
    {
        $this->productRepo = new ProductRepository();
    }

    public function getProductsList(GetAdminProductsRequestDTO $dto): array
    {
        // تبدیل DTO به آرایه برای ریپازیتوری
        $filters = [
            'search' => $dto->search,
            'category_id' => $dto->categoryId,
            'publisher_id' => $dto->publisherId,
            'status' => $dto->status,
            'sort' => $dto->sort,
            'order' => $dto->order
        ];

        $repoResult = $this->productRepo->getPaginatedList($filters, $dto->page, $dto->limit);

        // فرمت‌دهی (Resource Mapping)
        $formattedItems = array_map(function ($item) {
            // باز کردن فیلد jsonb
            $attributes = json_decode($item['attributes'] ?? '{}', true);

            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'slug' => $item['slug'],
                'cover_image' => $item['cover_image'],
                
                // قیمت‌گذاری
                'price' => (float)$item['price'],
                'price_with_discount' => (float)$item['price_with_discount'],
                
                // وضعیت
                'status' => (int)$item['status'],
                'status_label' => $item['status'] == 1 ? 'فعال' : 'غیرفعال', // فقط جهت نمایش راحت
                
                // روابط
                'category' => [
                    'id' => $item['category_id'],
                    'title' => $item['category_title'] ?? 'بدون دسته‌بندی'
                ],
                'publisher' => [
                    'id' => $item['publisher_id'],
                    'title' => $item['publisher_title'] ?? 'ناشر نامشخص'
                ],

                // آمار
                'stats' => [
                    'views' => (int)$item['view_count'],
                    'sales' => (int)$item['sale_count'],
                    'rating' => (float)$item['rate_avg']
                ],
                
                // ویژگی‌های خاص (تعداد صفحه، شابک و...)
                'details' => $attributes,
                
                'created_at' => $item['created_at'],
            ];
        }, $repoResult['data']);

        return [
            'items' => $formattedItems,
            'pagination' => [
                'total' => $repoResult['total'],
                'per_page' => $dto->limit,
                'current_page' => $dto->page,
                'last_page' => ceil($repoResult['total'] / $dto->limit),
                'from' => (($dto->page - 1) * $dto->limit) + 1,
                'to' => (($dto->page - 1) * $dto->limit) + count($formattedItems)
            ]
        ];
    }
}