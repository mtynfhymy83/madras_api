<?php

namespace App\Controllers;

use App\Auth\JWTAuth;
use App\Database\DB;
use App\Repositories\BookRepository;
use App\Traits\ResponseTrait;
use Swoole\Http\Request;

/**
 * Book actions (cart, read, study desk)
 * All endpoints require auth.
 */
class BookActionController
{
    use ResponseTrait;
    use JWTAuth;

    private BookRepository $bookRepo;

    public function __construct()
    {
        $this->bookRepo = new BookRepository();
    }

    /**
     * POST /api/v1/books/{book_id}/cart
     */
    public function addToCart(Request $request, int $book_id): array
    {
        $userId = $this->requireUserId($request);
        $this->ensureBookExists($book_id);

        DB::execute(
            'INSERT INTO user_cart (user_id, product_id, created_at) VALUES (?, ?, NOW()) ON CONFLICT (user_id, product_id) DO NOTHING',
            [$userId, $book_id]
        );

        return $this->sendResponse(['in_cart' => true], 'به سبد خرید اضافه شد');
    }

    /**
     * DELETE /api/v1/books/{book_id}/cart
     */
    public function removeFromCart(Request $request, int $book_id): array
    {
        $userId = $this->requireUserId($request);
        $this->ensureBookExists($book_id);

        DB::execute('DELETE FROM user_cart WHERE user_id = ? AND product_id = ?', [$userId, $book_id]);

        return $this->sendResponse(['in_cart' => false], 'از سبد خرید حذف شد');
    }

    /**
     * POST /api/v1/books/{book_id}/read
     */
    public function markAsRead(Request $request, int $book_id): array
    {
        $userId = $this->requireUserId($request);
        $this->ensureBookAccess($userId, $book_id);

        DB::execute(
            'INSERT INTO user_book_reads (user_id, product_id, read_at) VALUES (?, ?, NOW()) ON CONFLICT (user_id, product_id) DO UPDATE SET read_at = EXCLUDED.read_at',
            [$userId, $book_id]
        );

        return $this->sendResponse(['is_read' => true], 'مطالعه شده ثبت شد');
    }

    /**
     * DELETE /api/v1/books/{book_id}/read
     */
    public function unmarkRead(Request $request, int $book_id): array
    {
        $userId = $this->requireUserId($request);
        $this->ensureBookExists($book_id);

        DB::execute('DELETE FROM user_book_reads WHERE user_id = ? AND product_id = ?', [$userId, $book_id]);

        return $this->sendResponse(['is_read' => false], 'علامت مطالعه شده برداشته شد');
    }

    /**
     * POST /api/v1/books/{book_id}/desk
     */
    public function addToStudyDesk(Request $request, int $book_id): array
    {
        $userId = $this->requireUserId($request);
        $this->ensureBookExists($book_id);

        DB::execute(
            'INSERT INTO user_study_desk (user_id, product_id, created_at) VALUES (?, ?, NOW()) ON CONFLICT (user_id, product_id) DO NOTHING',
            [$userId, $book_id]
        );

        return $this->sendResponse(['on_desk' => true], 'به میز مطالعه اضافه شد');
    }

    /**
     * DELETE /api/v1/books/{book_id}/desk
     */
    public function removeFromStudyDesk(Request $request, int $book_id): array
    {
        $userId = $this->requireUserId($request);
        $this->ensureBookExists($book_id);

        DB::execute('DELETE FROM user_study_desk WHERE user_id = ? AND product_id = ?', [$userId, $book_id]);

        return $this->sendResponse(['on_desk' => false], 'از میز مطالعه حذف شد');
    }

    private function ensureBookExists(int $bookId): void
    {
        if ($bookId <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }
        $row = DB::fetch("SELECT 1 FROM products WHERE id = ? AND type = 'book' AND deleted_at IS NULL", [$bookId]);
        if (!$row) {
            throw new \RuntimeException('کتاب یافت نشد', 404);
        }
    }

    private function ensureBookAccess(int $userId, int $bookId): void
    {
        $this->ensureBookExists($bookId);
        if (!$this->bookRepo->userHasBookAccess($userId, $bookId)) {
            throw new \RuntimeException('دسترسی به کتاب ندارید', 403);
        }
    }

    private function requireUserId(Request $request): int
    {
        $userId = $this->getAuthUserId($request);
        if ($userId === null) {
            throw new \RuntimeException('Unauthorized', 401);
        }
        return $userId;
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
        $tokenQuery = $request->get['token'] ?? null;
        if ($tokenQuery) {
            return trim((string)$tokenQuery);
        }
        return null;
    }
}
