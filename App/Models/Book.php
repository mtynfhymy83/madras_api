<?php

namespace App\Models;

use App\Database\QueryBuilder;

class Book
{
    // نام جدول را اینجا تعریف می‌کنیم تا اگر عوض شد، فقط همین‌جا تغییر دهیم
    protected static $table = 'books';

    /**
     * گرفتن تعداد کل کتاب‌ها
     */
    public static function count()
    {
        try {
            $qb = new QueryBuilder();
            $result = $qb->table(self::$table)
                         ->select('COUNT(*) as total')
                         ->first();
            
            return $result ? (int)$result['total'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * پیدا کردن یک کتاب با ID
     */
    public static function find($id)
    {
        $qb = new QueryBuilder();
        return $qb->table(self::$table)->where('id', $id)->first();
    }

    /**
     * گرفتن همه کتاب‌ها (مثلاً برای لیست)
     */
    public static function all($limit = 60)
    {
        $qb = new QueryBuilder();
        return $qb->table(self::$table)->limit($limit)->get();
    }
    
    /**
     * ایجاد کتاب جدید
     */
    public static function create(array $data)
    {
        $qb = new QueryBuilder();
        return $qb->table(self::$table)->insert($data);
    }
}