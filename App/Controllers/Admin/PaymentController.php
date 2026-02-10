<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Admin\PaymentService;
use Exception;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct()
    {
        parent::__construct();
        $this->paymentService = new PaymentService();
    }

    /**
     * List transactions for admin payments page
     */
    public function index($request)
    {
        $queryParams = [];

        if (is_array($request)) {
            $queryParams = $request;
        } elseif (is_object($request) && isset($request->get) && is_array($request->get)) {
            $queryParams = $request->get;
        }

        try {
            $this->validate([
                'page' => 'integer',
                'limit' => 'integer',
                'id' => 'integer',
                'status' => 'integer',
                'price' => 'integer',
                'ref_id' => 'string',
                'section' => 'string',
                'username' => 'string',
                'email' => 'string',
                'mobile' => 'string',
                'from_date' => 'string',
                'to_date' => 'string',
            ], $queryParams);

            $result = $this->paymentService->getTransactionsList($queryParams);
            return $this->sendResponse($result, 'لیست پرداخت‌ها با موفقیت دریافت شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, 'خطا در دریافت لیست پرداخت‌ها: ' . $e->getMessage(), true, 500);
        }
    }
}
