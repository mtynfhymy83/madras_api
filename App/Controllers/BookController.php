<?php

namespace App\Controllers;

use App\Auth\JWTAuth;
use App\Traits\ResponseTrait;
use App\Repositories\BookRepository;
use App\Services\PurchaseService;
use Swoole\Http\Request;

/**
 * Public API: Book details for end users.
 * Optimized for speed - single query with JOINs.
 */
class BookController
{
    use ResponseTrait;
    use JWTAuth;

    private BookRepository $bookRepo;

    public function __construct()
    {
        $this->bookRepo = new BookRepository();
    }

    /**
     * Get book details (optimized single query)
     * GET /api/v1/books/{id}
     */
    public function show(Request $request, int $id): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $userId = $this->getAuthUserId($request);
        $book = $this->bookRepo->getBookDetails($id, $userId);

        if (!$book) {
            throw new \RuntimeException('Book not found', 404);
        }

        // همیشه permission را در پاسخ داشته باش (fallback اگر از کش بدون permission برگشته باشد)
        if (!array_key_exists('permission', $book)) {
            $book['permission'] = $userId
                ? $this->bookRepo->userHasBookAccess($userId, $id)
                : (($book['price_with_discount'] ?? $book['price'] ?? 0) <= 0);
        }

        if (($_ENV['APP_DEBUG'] ?? '0') === '1') {
            $book['_debug'] = ['auth_user_id' => $userId];
        }

        if ($userId === null) {
            return [
                '__headers' => [
                    'Cache-Control' => 'public, max-age=60, s-maxage=300, stale-while-revalidate=60',
                    'Vary' => 'Accept-Encoding',
                ],
                '__data' => $book,
            ];
        }

        return $book;
    }

    /**
     * List books with pagination (lightweight)
     * GET /api/v1/books?page=1&limit=20&category_id=...
     */
    public function index(Request $request): array
    {
        $params = $request->get ?? [];
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? 20)));

        $result = $this->bookRepo->getBooksList($params, $page, $limit);

        return $result;
    }

    /**
     * کتابخانه من (کتاب‌های رایگان + خریداری‌شده)
     * GET /api/v1/profile/library?page=1&limit=20
     */
    public function myLibrary(Request $request): array
    {
        $userId = $this->getAuthUserId($request);
        if ($userId === null) {
            throw new \RuntimeException('Unauthorized', 401);
        }

        $params = $request->get ?? [];
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? 20)));

        return $this->bookRepo->getUserLibraryBooks($userId, $page, $limit);
    }

    /**
     * خرید کتاب (نیاز به توکن).
     * POST /api/v1/books/buy
     * Body: { "book_id": 123, "code": "optional_coupon" }
     * بدون اعتبارسنجی کتاب.
     */
    public function buyBook(Request $request): array
    {
        $userId = $this->getAuthUserId($request);
        if ($userId === null) {
            throw new \RuntimeException('Unauthorized', 401);
        }

        $body = $this->getBody($request);
        $bookId = (int)($body['book_id'] ?? 0);
        $code = isset($body['code']) ? trim((string)$body['code']) : null;
        if ($code === '') $code = null;

        if ($bookId <= 0) {
            return $this->sendResponse(null, 'book_id معتبر نیست', true, 400);
        }

        $baseUrl = $this->getBaseUrlFromRequest($request);

        try {
            $service = new PurchaseService();
            $data = $service->buyBook($userId, $bookId, $code, $baseUrl);
            return $this->sendResponse($data, $data['free'] ? 'کتاب به کتابخانه اضافه شد' : 'لینک پرداخت', false, 200);
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            if ($code === 404) {
                return $this->sendResponse(null, $e->getMessage(), true, 404);
            }
            if ($code === 401) {
                throw $e;
            }
            return $this->sendResponse(null, $e->getMessage(), true, $code >= 400 ? $code : 500);
        }
    }

    private function getBody(Request $request): array
    {
        $raw = $request->rawContent();
        if (empty($raw)) return [];
        return json_decode($raw, true) ?? [];
    }

    /** پایهٔ URL درخواست (برای لینک پرداخت تا روی همان هاست باشد، مثلاً localhost:9501) */
    private function getBaseUrlFromRequest(Request $request): ?string
    {
        $headers = $request->header ?? [];
        $server = $request->server ?? [];

        // اگر کلاینت صریحاً آدرس پایه فرستاده (مثلاً برای localhost) استفاده کن
        $explicit = $headers['x-request-base-url'] ?? $headers['X-Request-Base-URL'] ?? null;
        if ($explicit !== null && $explicit !== '') {
            $explicit = rtrim(trim($explicit), '/');
            if (preg_match('#^https?://#i', $explicit)) {
                return $explicit;
            }
        }

        // هدر Host (در Swoole ممکن است با حروف کوچک باشد)
        $host = $headers['host'] ?? $headers['Host'] ?? $server['http_host'] ?? $server['server_name'] ?? null;
        if ($host === null || $host === '') {
            // fallback: از Referer مبدأ را استخراج کن (مثلاً http://localhost:9501/...)
            $referer = $headers['referer'] ?? $headers['Referer'] ?? null;
            if ($referer !== null && preg_match('#^(https?://[^/]+)#i', trim($referer), $m)) {
                return rtrim($m[1], '/');
            }
            return null;
        }
        $host = trim($host);

        // پشت پروکسی
        $scheme = $headers['x-forwarded-proto'] ?? $headers['X-Forwarded-Proto'] ?? null;
        if ($scheme === null) {
            $raw = $server['request_scheme'] ?? $server['https'] ?? null;
            $scheme = ($raw === 'on' || $raw === 'https') ? 'https' : 'http';
        } else {
            $scheme = strtolower(trim($scheme));
            if ($scheme !== 'https') {
                $scheme = 'http';
            }
        }

        if (!str_contains($host, ':')) {
            $port = (int)($server['server_port'] ?? 80);
            $forwardedPort = $headers['x-forwarded-port'] ?? $headers['X-Forwarded-Port'] ?? null;
            if ($forwardedPort !== null && $forwardedPort !== '') {
                $port = (int)$forwardedPort;
            }
            if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
                $host .= ':' . $port;
            }
        }

        return $scheme . '://' . $host;
    }

    private function getAuthUserId(Request $request): ?int
    {
        $token = $this->extractToken($request);
        if (!$token) return null;

        $payload = $this->verifyToken($token);
        if (!$payload) return null;

        $id = (int)($payload->id ?? $payload->sub ?? $payload->user_id ?? 0);
        return $id > 0 ? $id : null;
    }

    private function extractToken(Request $request): ?string
    {
        $headers = $request->header ?? [];
        // جستجوی case-insensitive برای Authorization (مثلاً Swagger ممکن است با حروف مختلف بفرستد)
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        // fallback: هدر token (بعضی کلاینت‌ها فقط این را می‌فرستند)
        $tokenHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'token') {
                $tokenHeader = $value;
                break;
            }
        }
        if ($tokenHeader) {
            return trim($tokenHeader);
        }
        // fallback: token از query string (برای تست در Swagger)
        $tokenQuery = $request->get['token'] ?? null;
        if ($tokenQuery) {
            return trim((string)$tokenQuery);
        }
        return null;
    }
}
