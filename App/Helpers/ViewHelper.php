<?php

namespace App\Helpers;

use App\Database\QueryBuilder;

class ViewHelper
{
    /**
     * رندر کردن ویو
     */
    public static function render($view, $data = [])
    {
        // مسیردهی: فرض بر این است که فایل در App/Helpers است و views در روت پروژه
        $viewPath = __DIR__ . "/../views/" . $view . ".php"; 

        if (!file_exists($viewPath)) {
            // برای اطمینان مسیرهای دیگر را هم چک میکنیم (اگر ساختار فرق داشت)
            $viewPath = __DIR__ . "/../views/" . $view . ".php";
            if (!file_exists($viewPath)) {
                return "<div style='color:red;padding:10px;border:1px solid red;'>View [$view] not found. Path: $viewPath</div>";
            }
        }

        extract($data);
        ob_start();
        include $viewPath;
        return ob_get_clean();
    }

    /**
     * تولید درخت چک‌باکس برای دسته‌بندی‌ها
     * @param string $type نوع پست (مثلا book)
     * @param array|string|null $selectedIds دسته‌های انتخاب شده
     */
    public static function categoryCheckboxTree($type, $selectedIds = [])
    {
        // تبدیل به آرایه اگر رشته یا نال باشد
        if (is_string($selectedIds)) {
            $selectedIds = explode(',', $selectedIds);
        } elseif (is_null($selectedIds)) {
            $selectedIds = [];
        }

        // 1. گرفتن همه دسته‌بندی‌های فعال از دیتابیس
        $qb = new QueryBuilder();
        $categories = $qb->table('categories')
                         ->where('type', $type) 
                         ->where('is_active', true)
                         ->orderBy('order_column', 'ASC') // طبق فایل SQL شما این ستون وجود دارد
                         ->orderBy('id', 'ASC')
                         ->get();

        if (empty($categories)) {
            return '<div style="padding:10px; color:#777;">دسته‌بندی یافت نشد.</div>';
        }

        // 2. تبدیل لیست فلت به درختی
        $tree = self::buildTree($categories);
        
        // 3. تولید HTML
        return '<ul>' . self::renderTreeHtml($tree, $selectedIds) . '</ul>';
    }

    // --- توابع داخلی ---

    private static function buildTree(array $elements, $parentId = 0)
    {
        $branch = [];
        foreach ($elements as $element) {
            $eleParentId = $element['parent_id'] ?? 0;
            if ($eleParentId == $parentId) {
                $children = self::buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }

    private static function renderTreeHtml($tree, $selectedIds)
    {
        $html = '';
        foreach ($tree as $node) {
            $checked = in_array($node['id'], $selectedIds) ? 'checked' : '';
            
            $html .= '<li item-id="' . $node['id'] . '" parent="' . ($node['parent_id']??0) . '" name="' . htmlspecialchars($node['name']) . '">';
            $html .= '<label>';
            $html .= '<input type="checkbox" name="category[]" value="' . $node['id'] . '" ' . $checked . '> ';
            $html .= htmlspecialchars($node['name']);
            $html .= '</label>';
            
            if (!empty($node['children'])) {
                $html .= '<ul>';
                $html .= self::renderTreeHtml($node['children'], $selectedIds);
                $html .= '</ul>';
            }
            
            $html .= '</li>';
        }
        return $html;
    }
}