<?php

namespace App\Repositories;

use App\Database\QueryBuilder;

class ProductRepository
{
    public function getPaginatedList(array $filters, int $page, int $limit): array
    {
        $qb = (new QueryBuilder())->table('products');

        // انتخاب ستون‌ها (نام دسته‌بندی و ناشر را هم می‌گیریم)
        $qb->select([
            'products.*',
            'categories.title as category_title',
            'publishers.title as publisher_title'
        ]);

        // JOIN طبق m.sql
        $qb->join('categories', 'categories.id', '=', 'products.category_id', 'LEFT');
        $qb->join('publishers', 'publishers.id', '=', 'products.publisher_id', 'LEFT');

        // --- اعمال فیلترها ---
        
        // جستجو در عنوان یا اسلاگ
        if (!empty($filters['search'])) {
            $qb->where('products.title', 'LIKE', '%' . $filters['search'] . '%');
        }

        // فیلتر دسته‌بندی
        if (!empty($filters['category_id'])) {
            $qb->where('products.category_id', $filters['category_id']);
        }
        
        // فیلتر ناشر
        if (!empty($filters['publisher_id'])) {
            $qb->where('products.publisher_id', $filters['publisher_id']);
        }

        // فیلتر وضعیت (فعال/غیرفعال) - مخصوص ادمین
        if (isset($filters['status']) && $filters['status'] !== '') {
            $qb->where('products.status', $filters['status']);
        }

        // مرتب‌سازی
        $sort = $filters['sort'] ?? 'id';
        $order = $filters['order'] ?? 'DESC';
        // جلوگیری از ارور Ambiguous column (چون id در همه جداول هست)
        $qb->orderBy("products.$sort", $order);

        // صفحه‌بندی
        $offset = ($page - 1) * $limit;
        $qb->limit($limit)->offset($offset);

        // دریافت داده‌ها
        $data = $qb->get();

        // --- کوئری شمارش کل (برای Pagination) ---
        $countQb = (new QueryBuilder())->table('products');
        
        // تکرار فیلترها برای شمارش صحیح
        if (!empty($filters['search'])) $countQb->where('title', 'LIKE', '%' . $filters['search'] . '%');
        if (!empty($filters['category_id'])) $countQb->where('category_id', $filters['category_id']);
        if (!empty($filters['publisher_id'])) $countQb->where('publisher_id', $filters['publisher_id']);
        if (isset($filters['status']) && $filters['status'] !== '') $countQb->where('status', $filters['status']);
        
        $total = $countQb->count();

        return [
            'data' => $data,
            'total' => $total
        ];
    }
}