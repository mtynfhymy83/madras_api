<?php

namespace App\Controllers;

use App\Traits\ResponseTrait;
use App\Repositories\BookReviewRepository;
use App\Auth\JWTAuth;
use Swoole\Http\Request;

/**
 * Book Reviews API
 * GET (list) is public, POST/PUT/DELETE/LIKE require auth
 */
class BookReviewController
{
    use ResponseTrait;
    use JWTAuth;

    private BookReviewRepository $repo;

    public function __construct()
    {
        $this->repo = new BookReviewRepository();
    }

    /**
     * List reviews for a book (public, paginated)
     * GET /api/v1/books/{book_id}/reviews?page=1&per_page=10&sort=newest
     */
    public function index(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $params = $request->get ?? [];
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(50, max(1, (int)($params['per_page'] ?? 10)));
        $sort = $params['sort'] ?? 'newest'; // newest, oldest, helpful, rating_high, rating_low

        return $this->repo->getByBookId($book_id, $page, $perPage, $sort);
    }

    /**
     * Create a review (requires auth)
     * POST /api/v1/books/{book_id}/reviews
     * Body: { "rating": 5, "text": "..." }
     */
    public function store(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $userId = $this->getAuthUserId($request);
        if (!$userId) {
            throw new \RuntimeException('Unauthorized', 401);
        }

        // Check if user already reviewed this book
        $existing = $this->repo->findByUserAndBook($userId, $book_id);
        if ($existing) {
            throw new \RuntimeException('شما قبلاً برای این کتاب نظر ثبت کرده‌اید', 400);
        }

        // Parse body
        $body = $this->getBody($request);
        $rating = (int)($body['rating'] ?? 0);
        $text = trim($body['text'] ?? '');

        // Validate
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('امتیاز باید بین 1 تا 5 باشد', 400);
        }

        $reviewId = $this->repo->create([
            'user_id' => $userId,
            'book_id' => $book_id,
            'rating' => $rating,
            'text' => $text ?: null,
        ]);

        // Update book's average rating
        $this->repo->updateBookRating($book_id);

        return [
            'id' => $reviewId,
            'message' => 'نظر شما با موفقیت ثبت شد',
        ];
    }

    /**
     * Update own review (requires auth)
     * PUT /api/v1/books/{book_id}/reviews/{id}
     */
    public function update(Request $request, int $book_id, int $id): array
    {
        $userId = $this->getAuthUserId($request);
        if (!$userId) {
            throw new \RuntimeException('Unauthorized', 401);
        }

        $review = $this->repo->findById($id);
        if (!$review || (int)$review['book_id'] !== $book_id) {
            throw new \RuntimeException('نظر یافت نشد', 404);
        }

        if ((int)$review['user_id'] !== $userId) {
            throw new \RuntimeException('شما فقط می‌توانید نظر خودتان را ویرایش کنید', 403);
        }

        $body = $this->getBody($request);
        $updateData = [];

        if (isset($body['rating'])) {
            $rating = (int)$body['rating'];
            if ($rating < 1 || $rating > 5) {
                throw new \InvalidArgumentException('امتیاز باید بین 1 تا 5 باشد', 400);
            }
            $updateData['rating'] = $rating;
        }

        if (array_key_exists('text', $body)) {
            $updateData['text'] = trim($body['text']) ?: null;
        }

        if (empty($updateData)) {
            throw new \InvalidArgumentException('هیچ داده‌ای برای بروزرسانی ارسال نشده', 400);
        }

        $this->repo->update($id, $updateData);
        
        // Update book's average rating if rating changed
        if (isset($updateData['rating'])) {
            $this->repo->updateBookRating($book_id);
        }

        return ['message' => 'نظر با موفقیت بروزرسانی شد'];
    }

    /**
     * Delete own review (requires auth)
     * DELETE /api/v1/books/{book_id}/reviews/{id}
     */
    public function destroy(Request $request, int $book_id, int $id): array
    {
        $userId = $this->getAuthUserId($request);
        if (!$userId) {
            throw new \RuntimeException('Unauthorized', 401);
        }

        $review = $this->repo->findById($id);
        if (!$review || (int)$review['book_id'] !== $book_id) {
            throw new \RuntimeException('نظر یافت نشد', 404);
        }

        if ((int)$review['user_id'] !== $userId) {
            throw new \RuntimeException('شما فقط می‌توانید نظر خودتان را حذف کنید', 403);
        }

        $this->repo->delete($id);
        $this->repo->updateBookRating($book_id);

        return ['message' => 'نظر با موفقیت حذف شد'];
    }

    /**
     * Like/unlike a review (requires auth)
     * POST /api/v1/books/{book_id}/reviews/{id}/like
     */
    public function like(Request $request, int $book_id, int $id): array
    {
        $userId = $this->getAuthUserId($request);
        if (!$userId) {
            throw new \RuntimeException('Unauthorized', 401);
        }

        $review = $this->repo->findById($id);
        if (!$review || (int)$review['book_id'] !== $book_id) {
            throw new \RuntimeException('نظر یافت نشد', 404);
        }

        $isLiked = $this->repo->toggleLike($userId, $id);

        return [
            'liked' => $isLiked,
            'message' => $isLiked ? 'پسندیدید' : 'پسند برداشته شد',
        ];
    }

    /**
     * Get authenticated user ID from token
     */
    private function getAuthUserId(Request $request): ?int
    {
        $token = $this->extractToken($request);
        if (!$token) return null;

        $payload = $this->verifyToken($token);
        if (!$payload) return null;

        // verifyToken returns object; this project uses 'id' in JWT claims (see JWTAuth::generateToken)
        return $payload->id ?? $payload->sub ?? $payload->user_id ?? null;
    }

    /**
     * Extract token from request header
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header['authorization'] ?? $request->header['Authorization'] ?? null;
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get request body as array
     */
    private function getBody(Request $request): array
    {
        $raw = $request->rawContent();
        if (empty($raw)) return [];
        return json_decode($raw, true) ?? [];
    }
}
