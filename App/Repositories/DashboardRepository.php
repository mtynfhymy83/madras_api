<?php

namespace App\Repositories;

use App\Database\QueryBuilder;
use App\Database\DB;

class DashboardRepository
{
    public function getTotalUsers(): int
    {
        return (new QueryBuilder())
            ->table('users')
            ->count();
    }

    public function getTotalBooks(): int
    {
        return (new QueryBuilder())
            ->table('products')
            ->where('type', '=', 'book')
            ->count();
    }

    public function getMonthlySalesTotal(int $startTs, int $endTs): int
    {
        $sql = "
            SELECT COALESCE(SUM(paid), 0) AS total
            FROM transactions
            WHERE status = 0
              AND paid > 0
              AND pdate BETWEEN ? AND ?
        ";

        $row = DB::fetch($sql, [$startTs, $endTs]);
        return (int)($row['total'] ?? 0);
    }
}
