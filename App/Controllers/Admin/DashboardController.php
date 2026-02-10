<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Exception;

class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DashboardService();
    }

    public function stats($request)
    {
        $queryParams = [];

        if (is_array($request)) {
            $queryParams = $request;
        } elseif (is_object($request) && isset($request->get) && is_array($request->get)) {
            $queryParams = $request->get;
        }

        try {
            $this->validate([
                'month' => 'integer',
                'year' => 'integer',
            ], $queryParams);

            $month = isset($queryParams['month']) ? (int)$queryParams['month'] : null;
            $year = isset($queryParams['year']) ? (int)$queryParams['year'] : null;

            $result = $this->service->getStats($month, $year);

            return $this->sendResponse($result, 'آمار داشبورد با موفقیت دریافت شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, 500);
        }
    }
}
