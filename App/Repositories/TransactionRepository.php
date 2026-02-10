<?php

namespace App\Repositories;

use App\Database\DB;

class TransactionRepository
{
    /**
     * تراکنش با شناسه و (اختیاری) بخش
     */
    public function getById(int $id, ?string $section = null): ?array
    {
        $sql = "SELECT * FROM transactions WHERE id = ?";
        $params = [$id];
        if ($section !== null) {
            $sql .= " AND section = ?";
            $params[] = $section;
        }
        $row = DB::fetch($sql, $params);
        return $row ?: null;
    }

    public function updateState(int $id, string $state): void
    {
        DB::execute("UPDATE transactions SET state = ? WHERE id = ?", [$state, $id]);
    }

    /**
     * ثبت پرداخت موفق: paid, ref_id, pdate, state
     */
    public function setPaid(int $id, string $refId, int $paidAmount, string $stateSuccess): void
    {
        $pdate = time();
        DB::execute(
            "UPDATE transactions SET paid = ?, ref_id = ?, pdate = ?, state = ?, status = 0 WHERE id = ?",
            [$paidAmount, $refId, $pdate, $stateSuccess, $id]
        );
    }

    /**
     * آپدیت وضعیت برای پرداخت ناموفق
     */
    public function setFailed(int $id, string $stateMessage, ?string $refId = null): void
    {
        $params = [$stateMessage, time(), $id];
        $sql = "UPDATE transactions SET state = ?, pdate = ? WHERE id = ?";
        if ($refId !== null) {
            $sql = "UPDATE transactions SET state = ?, ref_id = ?, pdate = ? WHERE id = ?";
            $params = [$stateMessage, $refId, time(), $id];
        }
        DB::execute($sql, $params);
    }

    /**
     * آیا ref_id قبلاً برای تراکنش دیگری استفاده شده؟
     */
    public function isRefIdUsed(string $refId): bool
    {
        $row = DB::fetch("SELECT 1 FROM transactions WHERE ref_id = ? LIMIT 1", [$refId]);
        return (bool)$row;
    }
}
