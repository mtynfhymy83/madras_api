<?php

namespace App\Services\Admin;

use App\Repositories\DashboardRepository;
use App\Cache\Cache;

class DashboardService
{
    private DashboardRepository $repo;

    public function __construct()
    {
        $this->repo = new DashboardRepository();
    }

    public function getStats(?int $month = null, ?int $year = null): array
    {
        $now = time();
        $month = $month ?: (int)date('m', $now);
        $year = $year ?: (int)date('Y', $now);

        if ($month < 1) $month = 1;
        if ($month > 12) $month = 12;
        if ($year < 1970) $year = (int)date('Y', $now);

        $start = strtotime(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end = strtotime(date('Y-m-t 23:59:59', $start));

        $cacheKey = "admin:dashboard:stats:{$year}{$month}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $data = [
            'total_users' => $this->repo->getTotalUsers(),
            'total_books' => $this->repo->getTotalBooks(),
            'sales_month' => [
                'year' => $year,
                'month' => $month,
                'from' => $start,
                'to' => $end,
                'total_paid' => $this->repo->getMonthlySalesTotal($start, $end),
            ],
        ];

        Cache::set($cacheKey, $data, 300);

        return $data;
    }
}
