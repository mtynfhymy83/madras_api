<?php

namespace App\Services;

use App\Repositories\BookRepository;
use App\Repositories\CouponRepository;
use App\Repositories\UserRepository;
use App\Database\DB;

/**
 * خرید کتاب: ایجاد فاکتور (تراکنش)، اعمال تخفیف، در صورت رایگان اضافه به کتابخانه.
 * بدون اعتبارسنجی کتاب (طبق درخواست).
 */
class PurchaseService
{
    private BookRepository $bookRepo;
    private CouponRepository $couponRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->bookRepo = new BookRepository();
        $this->couponRepo = new CouponRepository();
        $this->userRepo = new UserRepository();
    }

    /**
     * خرید کتاب: ایجاد تراکنش، در صورت رایگان اضافه به user_library و برگرداندن لینک پرداخت یا free.
     * @param int $userId کاربر لاگین‌شده
     * @param int $bookId محصول (product id نوع book)
     * @param string|null $code کد تخفیف
     * @param string|null $baseUrl پایهٔ URL برای لینک پرداخت (ندهید = از APP_URL/BASE_URL)
     * @return array ['free' => bool, 'link' => ?string, 'factor' => array]
     */
    public function buyBook(int $userId, int $bookId, ?string $code = null, ?string $baseUrl = null): array
    {
        $product = $this->bookRepo->getProductRowForPurchase($bookId);
        if (!$product) {
            throw new \RuntimeException('کتاب یافت نشد', 404);
        }

        $cprice = (int)($product['price'] ?? 0);
        $priceWithDiscount = (int)($product['price_with_discount'] ?? $product['price'] ?? 0);
        $basePrice = $priceWithDiscount > 0 ? $priceWithDiscount : $cprice;

        $discountId = null;
        $discountAmount = 0;
        if ($code !== null && trim($code) !== '') {
            $couponResult = $this->couponRepo->checkDiscountCode(trim($code), -1, $userId, $bookId);
            if (!$couponResult) {
                $couponResult = $this->couponRepo->checkDiscountCode(trim($code), -2, $userId);
            }
            if ($couponResult) {
                $discountId = $couponResult['id'];
                $discountAmount = $this->couponRepo->calculateDiscountAmount($couponResult, $basePrice);
            }
        }

        $finalPrice = max(0, $basePrice - $discountAmount);
        $owner = 0; // می‌توان از product_contributors نویسنده را گرفت

        $factorId = $this->createTransaction($userId, $bookId, $cprice, $basePrice, $finalPrice, $discountId, $discountAmount, $owner);

        $factor = $this->getTransactionById($factorId);
        $data = ['factor' => $factor];

        if ($finalPrice === 0) {
            $stateMsg = $discountId !== null ? "خرید با کد تخفیف ({$code})" : 'رایگان';
            DB::execute(
                "UPDATE transactions SET state = ?, status = 0, paid = 0, pdate = ? WHERE id = ?",
                [$stateMsg, time(), $factorId]
            );

            if ($discountId !== null) {
                $this->couponRepo->setDiscountUsed($discountId, $factorId, $userId);
            }

            $this->userRepo->addUserBook($userId, $bookId);
            $data['free'] = true;
            $data['link'] = null;
        } else {
            $data['free'] = false;
            $data['link'] = $this->getPaymentLink($factorId, $baseUrl);
        }

        return $data;
    }

    private function createTransaction(
        int $userId,
        int $bookId,
        int $cprice,
        int $basePrice,
        int $finalPrice,
        ?int $discountId,
        int $discountAmount,
        int $owner
    ): int {
        $now = time();
        $section = 'book';
        $dataId = (string)$bookId;

        $sql = "
            INSERT INTO transactions (user_id, status, state, cprice, price, discount, discount_id, paid, ref_id, cdate, pdate, owner, section, data_id)
            VALUES (?, 1, ?, ?, ?, ?, ?, 0, NULL, ?, NULL, ?, ?, ?)
            RETURNING id
        ";
        $state = $discountAmount > 0 ? 'تخفیف اعمال شده' : 'در انتظار پرداخت';
        $params = [
            $userId,
            $state,
            $cprice,
            $finalPrice,
            $discountAmount,
            $discountId,
            $now,
            $owner,
            $section,
            $dataId,
        ];

        $row = DB::run(function (\PDO $pdo) use ($sql, $params) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        });

        $id = is_array($row) ? ($row['id'] ?? null) : null;
        if ($id === null) {
            throw new \RuntimeException('خطا در ایجاد فاکتور', 500);
        }

        return (int)$id;
    }

    private function getTransactionById(int $id): array
    {
        $row = DB::fetch("SELECT * FROM transactions WHERE id = ?", [$id]);
        return $row ?: [];
    }

    private function getPaymentLink(int $factorId, ?string $baseUrl = null): string
    {
        $base = $baseUrl ?? $_ENV['APP_URL'] ?? $_ENV['BASE_URL'] ?? 'https://api-dev.madras.app';
        return rtrim($base, '/') . '/api/v1/payment/paybook/' . $factorId;
    }
}
