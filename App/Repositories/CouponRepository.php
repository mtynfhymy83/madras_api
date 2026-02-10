<?php

namespace App\Repositories;

use App\Database\QueryBuilder;
use App\Database\DB;

/**
 * کوپن / کد تخفیف (ساختار مطابق ci_discounts)
 */
class CouponRepository
{
    /**
     * پیدا کردن کوپن با کد (فعال و منقضی نشده)
     */
    public function findByCode(string $code): ?array
    {
        $row = (new QueryBuilder())
            ->table('coupons')
            ->withoutSoftDelete()
            ->where('code', '=', $code)
            ->first();

        if (!$row) {
            return null;
        }

        $now = time();
        if ($row['expdate'] !== null && (int)$row['expdate'] > 0 && (int)$row['expdate'] < $now) {
            return null; // منقضی شده
        }

        return $row;
    }

    /**
     * پیدا کردن کوپن با شناسه (برای نمایش کد در پیام پرداخت)
     */
    public function getById(int $id): ?array
    {
        $row = (new QueryBuilder())
            ->table('coupons')
            ->withoutSoftDelete()
            ->where('id', '=', $id)
            ->first();
        return $row ?: null;
    }

    /**
     * بررسی کد تخفیف برای خرید کتاب
     * @param string $code کد تخفیف
     * @param int $scope -1 = مخصوص کتاب (با book_id چک شود), -2 = عمومی
     * @param int $userId کاربر
     * @param int|null $bookId شناسه کتاب (برای scope=-1)
     * @return array|null ['id' => discount_id, 'percent' => ?, 'price' => ?, 'final_discount' => مبلغ تخفیف] یا null
     */
    public function checkDiscountCode(string $code, int $scope, int $userId, ?int $bookId = null): ?array
    {
        $coupon = $this->findByCode($code);
        if (!$coupon) {
            return null;
        }

        $categoryId = (int)($coupon['category_id'] ?? 0);
        $bookid = (int)($coupon['bookid'] ?? 0);
        $maxallow = (int)($coupon['maxallow'] ?? 0);
        $used = (int)($coupon['used'] ?? 0);

        if ($maxallow > 0 && $used >= $maxallow) {
            return null;
        }

        if ($scope === -1) {
            if ($bookid !== 0 && $bookId !== null && $bookid !== $bookId) {
                return null;
            }
            if ($categoryId !== -1 && $categoryId !== 0) {
                return null;
            }
        } elseif ($scope === -2) {
            if ($categoryId !== -2 && $categoryId !== 0) {
                return null;
            }
        } else {
            if ($categoryId !== $scope && $categoryId !== 0) {
                return null;
            }
        }

        // محدودیت استفاده هر کاربر
        $userUsed = DB::fetch(
            "SELECT COUNT(*) as cnt FROM discount_used WHERE user_id = ? AND discount_id = ?",
            [$userId, $coupon['id']]
        );
        if ((int)($userUsed['cnt'] ?? 0) >= 1) {
            return null; // هر کاربر یک بار (مطابق منطق قدیم)
        }

        $percent = $coupon['percent'] !== null && $coupon['percent'] !== '' ? (int)$coupon['percent'] : 0;
        $priceDiscount = 0;
        if ($coupon['price'] !== null && $coupon['price'] !== '') {
            $priceDiscount = (int)$coupon['price'];
        }

        return [
            'id' => (int)$coupon['id'],
            'percent' => $percent,
            'price' => $priceDiscount,
            'coupon' => $coupon,
        ];
    }

    /**
     * محاسبه مبلغ تخفیف روی مبلغ کل
     */
    public function calculateDiscountAmount(array $couponResult, int $totalPrice): int
    {
        $discount = 0;
        if ($couponResult['percent'] > 0) {
            $discount = (int)round($totalPrice * $couponResult['percent'] / 100);
        }
        if ($couponResult['price'] > 0) {
            $discount += $couponResult['price'];
        }
        return min($discount, $totalPrice);
    }

    /**
     * ثبت استفاده کاربر از کد تخفیف (در discount_used و آپدیت used در coupons)
     */
    public function setDiscountUsed(int $discountId, int $factorId, int $userId): void
    {
        $now = time();
        DB::execute(
            "INSERT INTO discount_used (user_id, discount_id, udate, factor_id) VALUES (?, ?, ?, ?)",
            [$userId, $discountId, $now, $factorId]
        );
        DB::execute(
            "UPDATE coupons SET used = used + 1, udate = ?, factor_id = ? WHERE id = ?",
            [$now, $factorId, $discountId]
        );
    }
}
