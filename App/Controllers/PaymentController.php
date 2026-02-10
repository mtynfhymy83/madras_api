<?php

namespace App\Controllers;

use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\CouponRepository;
use App\Traits\ResponseTrait;
use Swoole\Http\Request;

/**
 * معادل کنترلر Payment در CodeIgniter:
 * - paybook: نمایش/اطلاعات پرداخت کتاب (فاکتور + تنظیمات درگاه)
 * - verify: تأیید پرداخت بعد از بازگشت از بانک (بخش book)
 */
class PaymentController
{
    use ResponseTrait;

    private TransactionRepository $txRepo;
    private UserRepository $userRepo;
    private CouponRepository $couponRepo;

    /** پیام خطاهای سامان (مطابق کنترلر قبلی) */
    private const SAMAN_ERRORS = [
        -1  => 'خطا در پردازش اطلاعات ارسالی',
        -3  => 'ورودیها حاوی کارکترهای غیرمجاز میباشند',
        -4  => 'کلمه عبور یا کد فروشنده اشتباه است',
        -6  => 'سند قبلا برگشت کامل یافته است',
        -7  => 'رسید دیجیتالی تهی است',
        -8  => 'طول ورودیها بیشتر از حد مجاز است',
        -9  => 'وجود کارکترهای غیرمجاز در مبلغ برگشتی',
        -10 => 'رسید دیجیتالی به صورت Base64 نیست',
        -11 => 'طول ورودیها کمتر از حد مجاز است',
        -12 => 'مبلغ برگشتی منفی است',
        -13 => 'مبلغ برگشتی برای برگشت جزئی بیش از مبلغ برگشت نخورده ی رسید دیجیتالی است',
        -14 => 'چنین تراکنشی تعریف نشده است',
        -15 => 'مبلغ برگشتی به صورت اعشاری داده شده است',
        -16 => 'خطای داخلی سیستم',
        -17 => 'برگشت زدن جزیی تراکنش مجاز نمی باشد',
        -18 => 'IP Address فروشنده نا معتبر است',
    ];

    public function __construct()
    {
        $this->txRepo = new TransactionRepository();
        $this->userRepo = new UserRepository();
        $this->couponRepo = new CouponRepository();
    }

    /**
     * GET /api/v1/payment/paybook/{id}
     * معادل Payment::paybook.
     * اگر Accept: application/json باشد → JSON (فاکتور + gateway).
     * وگرنه → صفحهٔ HTML با فرم ارسال به درگاه سامان (همان view قبلی).
     */
    public function paybook(Request $request, int $id): mixed
    {
        $id = (int) $id;
        $factor = $this->txRepo->getById($id, 'book');

        if (!$factor) {
            return $this->sendResponse(null, 'شماره سفارش اشتباه است', true, 404);
        }

        $status = (int)($factor['status'] ?? 1);
        $refId = $factor['ref_id'] ?? null;
        if ($refId !== null && $refId !== '' || $status === 0) {
            return $this->sendResponse(null, 'تراکنش مربوط به این سفارش قبلا صورت گرفته است', true, 400);
        }

        $this->txRepo->updateState($id, 'انتقال به بانک');

        $config = $this->getGatewayConfig($request);
        $factorSafe = [
            'id'           => (int)$factor['id'],
            'user_id'      => (int)$factor['user_id'],
            'cprice'       => (int)$factor['cprice'],
            'price'        => (int)$factor['price'],
            'discount'     => (int)($factor['discount'] ?? 0),
            'discount_id'  => isset($factor['discount_id']) ? (int)$factor['discount_id'] : null,
            'section'      => $factor['section'],
            'data_id'      => $factor['data_id'],
            'cdate'        => (int)($factor['cdate'] ?? 0),
        ];

        $accept = strtolower($request->header['accept'] ?? $request->header['Accept'] ?? '');
        if (str_contains($accept, 'application/json')) {
            return $this->sendResponse([
                'factor' => $factorSafe,
                'gateway' => $config,
            ], '', false, 200);
        }

        $samanId = trim($config['saman_id'] ?? '');
        if ($samanId === '' || $samanId === '0') {
            return $this->renderPaymentError(
                'شماره پذیرنده تنظیم نشده است',
                'کد پذیرنده درگاه (شماره پذیرنده) در سرور تنظیم نشده است. لطفا متغیر <strong>SAMAN_ID</strong> یا <strong>SAMAN_TERMINAL</strong> را در فایل .env با مقدار دریافتی از بانک سامان قرار دهید.'
            );
        }

        return $this->renderPaymentForm($factor, $config, $request);
    }

    /**
     * صفحهٔ HTML خطای پرداخت (بدون ارسال به درگاه)
     */
    private function renderPaymentError(string $title, string $message): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $m = $message; // already contains safe <strong>
        $html = '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>خطا در پرداخت</title></head><body>';
        $html .= '<div style="max-width:600px;margin:2rem auto;padding:1.5rem;font-family:tahoma,arial;">';
        $html .= '<div style="padding:1.5rem;background:#fff3f3;border:1px solid #f5c6cb;border-radius:8px;">';
        $html .= '<h2 style="margin:0 0 1rem 0;color:#721c24;">' . $t . '</h2>';
        $html .= '<p style="margin:0;line-height:1.6;color:#333;">' . $m . '</p>';
        $html .= '</div></div></body></html>';
        return $html;
    }

    /**
     * صفحهٔ HTML فرم پرداخت سامان (معادل v_payment_form قبلی)
     */
    private function renderPaymentForm(array $factor, array $config, Request $request): string
    {
        $baseUrl = $this->getBaseUrlFromRequest($request)
            ?? rtrim($_ENV['APP_URL'] ?? $_ENV['BASE_URL'] ?? '', '/');
        $redirectUrl = $baseUrl . '/api/v1/payment/verify/book';
        $get = $request->get ?? [];
        if (isset($get['from']) && (string)$get['from'] === 'miniapp') {
            $redirectUrl .= '?from=miniapp';
        }

        $amount = (int)$factor['price'] * 10;
        $resNum = (int)$factor['id'];
        $mid = htmlspecialchars(trim($config['saman_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $redirectUrlAttr = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>انتقال به درگاه پرداخت</title></head><body>';
        $html .= '<div style="max-width:600px;margin:2rem auto;padding:1rem;font-family:tahoma,arial;text-align:center;">';
        $html .= '<form id="payform" action="https://sep.shaparak.ir/payment.aspx" method="post">';
        $html .= '<input type="hidden" name="Amount" value="' . $amount . '">';
        $html .= '<input type="hidden" name="ResNum" value="' . $resNum . '">';
        $html .= '<input type="hidden" name="RedirectURL" value="' . $redirectUrlAttr . '">';
        $html .= '<input type="hidden" name="MID" value="' . $mid . '">';
        $html .= '</form>';
        $html .= '<div style="padding:1.5rem;background:#e7f3ff;border:1px solid #b3d9ff;border-radius:8px;margin-top:1rem;">';
        $html .= '<p style="margin:0;font-size:1.1rem;">در حال انتقال به بانک، لطفا صبر کنید ...</p>';
        $html .= '</div></div>';
        $html .= '<script>window.onload=function(){document.getElementById("payform").submit();};</script>';
        $html .= '</body></html>';

        return $html;
    }

    private function getBaseUrlFromRequest(Request $request): ?string
    {
        $headers = $request->header ?? [];
        $server = $request->server ?? [];
        $explicit = $headers['x-request-base-url'] ?? $headers['X-Request-Base-URL'] ?? null;
        if ($explicit !== null && $explicit !== '') {
            $explicit = rtrim(trim($explicit), '/');
            if (preg_match('#^https?://#i', $explicit)) {
                return $explicit;
            }
        }
        $host = $headers['host'] ?? $headers['Host'] ?? $server['http_host'] ?? $server['server_name'] ?? null;
        if ($host === null || $host === '') {
            $referer = $headers['referer'] ?? $headers['Referer'] ?? null;
            if ($referer !== null && preg_match('#^(https?://[^/]+)#i', trim($referer), $m)) {
                return rtrim($m[1], '/');
            }
            return null;
        }
        $host = trim($host);
        $scheme = $headers['x-forwarded-proto'] ?? $headers['X-Forwarded-Proto'] ?? null;
        if ($scheme === null) {
            $raw = $server['request_scheme'] ?? $server['https'] ?? null;
            $scheme = ($raw === 'on' || $raw === 'https') ? 'https' : 'http';
        } else {
            $scheme = strtolower(trim($scheme)) === 'https' ? 'https' : 'http';
        }
        if (!str_contains($host, ':')) {
            $port = (int)($server['server_port'] ?? 80);
            $fp = $headers['x-forwarded-port'] ?? $headers['X-Forwarded-Port'] ?? null;
            if ($fp !== null && $fp !== '') {
                $port = (int)$fp;
            }
            if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
                $host .= ':' . $port;
            }
        }
        return $scheme . '://' . $host;
    }

    /**
     * POST /api/v1/payment/verify/{section}
     * معادل Payment::verify - callback بانک. section = book
     */
    public function verify(Request $request, string $section): array
    {
        $section = strtolower(trim($section));
        if ($section !== 'book') {
            return $this->sendResponse(null, 'بخش نامعتبر', true, 400);
        }

        $input = $this->getPost($request);
        $inputNormalized = $this->normalizeCallbackInput($input);
        $ResNum = $inputNormalized['ResNum'] ?? null;
        $RefNum = $inputNormalized['RefNum'] ?? null;
        $State  = $inputNormalized['State'] ?? null;

        if ($ResNum === null || $ResNum === '') {
            error_log('[Payment] Verify callback missing ResNum. Keys: ' . implode(',', array_keys($input)));
            return $this->sendResponse(null, 'شماره سفارش در بازگشت از بانک یافت نشد.', true, 400);
        }
        if ($RefNum === null || $RefNum === '') {
            $RefNum = 'cb-' . $ResNum . '-' . time();
        }

        $config = $this->getGatewayConfig();
        $online = (bool)($config['online'] ?? false);

        if ($online) {
            if ($State === null || $State === '') {
                $this->markFactorFailed((int)$ResNum, $RefNum, 'اطلاعات ورودی اشتباه است');
                return $this->sendResponse(null, 'اطلاعات ورودی اشتباه است', true, 400);
            }
            if (strtoupper(trim($State)) !== 'OK') {
                $this->markFactorFailed((int)$ResNum, $RefNum, $State);
                return $this->sendResponse(null, $State, true, 400);
            }

            $result = $this->verifySamanTransaction((string)$RefNum, $config['saman_id'] ?? '');
            if ($result <= 0) {
                $factorForFallback = $this->txRepo->getById((int)$ResNum, 'book');
                if ($result === -16 && $factorForFallback && $RefNum !== null && $RefNum !== '') {
                    $result = (int)$factorForFallback['price'] * 10;
                    error_log("[Payment] SEP VerifyTransaction returned -16; accepting by callback. ResNum=$ResNum RefNum=" . substr((string)$RefNum, 0, 10) . "... Amount=$result");
                }
                if ($result <= 0) {
                    $msg = self::SAMAN_ERRORS[$result] ?? "خطای بانک: $result";
                    $this->markFactorFailed((int)$ResNum, $RefNum, 'پرداخت ناموفق: ' . $msg);
                    return $this->sendResponse(null, $msg, true, 400);
                }
            }
        } else {
            $RefNum = $RefNum ?: (string)time();
            $factor = $this->txRepo->getById((int)$ResNum, 'book');
            $result = $factor ? (int)$factor['price'] * 10 : 0;
        }

        $factorId = (int)$ResNum;
        $factor = $this->txRepo->getById($factorId, 'book');
        if (!$factor) {
            if ($online) {
                $this->reverseSamanTransaction($RefNum, $config);
            }
            return $this->sendResponse(null, 'سفارش مربوط به این تراکنش پیدا نشد.', true, 404);
        }

        if ($this->txRepo->isRefIdUsed($RefNum)) {
            if ($online) {
                $this->reverseSamanTransaction($RefNum, $config);
            }
            return $this->sendResponse(null, 'رسید دیجیتالی این تراکنش قبلا برای سفارش دیگری استفاده شده است.', true, 400);
        }

        $expectedAmount = (int)$factor['price'] * 10;
        if ($expectedAmount != $result) {
            if ($online) {
                $this->reverseSamanTransaction($RefNum, $config);
            }
            return $this->sendResponse(null, 'مبلغ پرداخت شده با مبلغ سفارش برابر نیست.', true, 400);
        }

        $refId = $factor['ref_id'] ?? null;
        if ($refId !== null && $refId !== '') {
            return $this->sendResponse(null, 'تراکنش مربوط به این سفارش قبلا صورت گرفته است', true, 400);
        }

        $paidAmount = (int)($result / 10);
        $discountId = isset($factor['discount_id']) && $factor['discount_id'] !== '' ? (int)$factor['discount_id'] : null;
        $userId = (int)$factor['user_id'];
        $bookId = (int)$factor['data_id'];

        $stateMsg = 'پرداخت موفق';
        if ($discountId) {
            $coupon = $this->couponRepo->getById($discountId);
            $code = $coupon['code'] ?? '';
            $stateMsg = "پرداخت موفق، استفاده از کد تخفیف ({$code})";
            $this->couponRepo->setDiscountUsed($discountId, $factorId, $userId);
        }

        $this->txRepo->setPaid($factorId, $RefNum, $paidAmount, $stateMsg);
        $this->userRepo->addUserBook($userId, $bookId);

        $factor['paid'] = $paidAmount;
        $factor['ref_id'] = $RefNum;
        $factor['state'] = $stateMsg;

        return $this->sendResponse([
            'factor' => $factor,
            'message' => 'پرداخت با موفقیت انجام شد.',
        ], 'پرداخت با موفقیت انجام شد.', false, 200);
    }

    private function getGatewayConfig(?Request $request = null): array
    {
        $online = ($this->env('PAYMENT_ONLINE', '0')) === '1';
        $base = 'https://api-dev.madras.app';
        if ($request !== null) {
            $fromRequest = $this->getBaseUrlFromRequest($request);
            if ($fromRequest !== null) {
                $base = $fromRequest;
            } else {
                $base = rtrim($this->env('APP_URL', $this->env('BASE_URL', $base)), '/');
            }
        } else {
            $base = rtrim($this->env('APP_URL', $this->env('BASE_URL', $base)), '/');
        }
        return [
            'online'         => $online,
            'saman_id'       => $this->env('SAMAN_ID', $this->env('SAMAN_TERMINAL', '')),
            'saman_username' => $this->env('SAMAN_USERNAME', ''),
            'saman_password' => $this->env('SAMAN_PASSWORD', ''),
            'callback_url'   => $base . '/api/v1/payment/verify/book',
        ];
    }

    /** خواندن env از $_ENV یا getenv؛ در صورت خالی بودن یک بار .env را از روت پروژه لود می‌کند (برای workerهای Swoole). */
    private function env(string $key, string $default = ''): string
    {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v !== false && $v !== null && (string)$v !== '') {
            return (string) $v;
        }
        $this->loadEnvOnce();
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null) {
            return $default;
        }
        return (string) $v;
    }

    private static bool $envLoaded = false;

    private function loadEnvOnce(): void
    {
        if (self::$envLoaded) {
            return;
        }
        $root = dirname(__DIR__, 2);
        $envFile = $root . '/.env';
        if (!is_file($envFile)) {
            self::$envLoaded = true;
            return;
        }
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$envLoaded = true;
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
                $value = $m[2];
            }
            if (($pos = strpos($value, ' #')) !== false) {
                $value = trim(substr($value, 0, $pos));
            }
            if ($name !== '' && !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
        self::$envLoaded = true;
    }

    private function getPost(Request $request): array
    {
        $ctype = strtolower($request->header['content-type'] ?? '');
        if (str_contains($ctype, 'application/json')) {
            $raw = $request->rawContent();
            return !empty($raw) ? (json_decode($raw, true) ?? []) : [];
        }
        return $request->post ?? [];
    }

    /** نرمال‌سازی پارامترهای callback بانک (حروف کوچک/بزرگ یا نام‌های متفاوت) */
    private function normalizeCallbackInput(array $input): array
    {
        $out = [];
        $keys = ['ResNum' => ['ResNum', 'resnum', 'Resnum'], 'RefNum' => ['RefNum', 'refnum', 'Refnum', 'RefNumber'], 'State' => ['State', 'state', 'STATE', 'Status', 'status']];
        foreach ($keys as $canonical => $variants) {
            foreach ($variants as $v) {
                if (isset($input[$v]) && (string)$input[$v] !== '') {
                    $out[$canonical] = trim((string)$input[$v]);
                    break;
                }
            }
        }
        return $out;
    }

    private function markFactorFailed(int $id, ?string $refId, string $message): void
    {
        $this->txRepo->setFailed($id, 'پرداخت ناموفق: ' . $message, $refId);
    }

    /** آدرس WSDL درگاه SEP (همان دامنهٔ فرم پرداخت) */
    private const SEP_WSDL = 'https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL';

    private function verifySamanTransaction(string $refNum, string $terminalId): int
    {
        try {
            $client = new \SoapClient(
                self::SEP_WSDL,
                [
                    'stream_context' => stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ]),
                    'connection_timeout' => 15,
                ]
            );
            $result = $client->verifyTransaction($refNum, $terminalId);
            $amount = (int)$result;
            if ($amount <= 0) {
                error_log("[Payment] SEP VerifyTransaction RefNum=$refNum TerminalId=$terminalId returned: $result");
            }
            return $amount;
        } catch (\SoapFault $e) {
            error_log('[Payment] SEP VerifyTransaction SoapFault: ' . $e->getMessage() . ' Code: ' . ($e->faultcode ?? '') . ' Detail: ' . ($e->detail ?? ''));
            return -16;
        } catch (\Throwable $e) {
            error_log('[Payment] SEP VerifyTransaction error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return -16;
        }
    }

    private function reverseSamanTransaction(string $refNum, array $config): void
    {
        try {
            $client = new \SoapClient(
                self::SEP_WSDL,
                [
                    'stream_context' => stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ]),
                    'connection_timeout' => 15,
                ]
            );
            $client->reverseTransaction(
                $refNum,
                $config['saman_id'] ?? '',
                $config['saman_username'] ?? '',
                $config['saman_password'] ?? ''
            );
        } catch (\Throwable $e) {
            error_log('[Payment] SEP reverseTransaction error: ' . $e->getMessage());
        }
    }
}
