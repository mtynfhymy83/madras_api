<?php

namespace App\Controllers;

use App\Auth\JWTAuth;
use App\Exceptions\AccessDeniedException;
use App\Repositories\BookRepository;
use App\Traits\ResponseTrait;
use App\Services\Admin\BookContentService;
use App\DTOs\Admin\BookContent\GetBookContentsRequestDTO;
use Swoole\Http\Request;

/**
 * Public API: Read-only book content for end users.
 * Requires user authentication (Bearer token).
 */
class BookContentController
{
    use ResponseTrait;
    use JWTAuth;

    private BookContentService $service;
    private BookRepository $bookRepo;

    public function __construct()
    {
        $this->service = new BookContentService();
        $this->bookRepo = new BookRepository();
    }

    /**
     * Get list of pages in a book
     * GET /api/v1/books/{book_id}/pages/list
     */
    public function getPages(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $this->requireBookAccess($request, $book_id);

        $pages = $this->service->getPagesList($book_id);

        return [
            'pages' => $pages,
            'total' => count($pages)
        ];
    }

    /**
     * Get all contents for a specific page
     * GET /api/v1/books/{book_id}/pages/{page_number}
     */
    public function getPageContents(Request $request, int $book_id, int $page_number): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $this->requireBookAccess($request, $book_id);

        $contents = $this->service->getPageContents($book_id, $page_number);

        return [
            'page_number' => $page_number,
            'contents' => $contents,
            'paragraph_count' => count($contents)
        ];
    }

    /**
     * Get all contents for a book with pagination
     * GET /api/v1/books/{book_id}/contents?page=1&per_page=50&page_number=...
     */
    public function index(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $this->requireBookAccess($request, $book_id);

        $queryParams = $request->get ?? [];
        $dto = GetBookContentsRequestDTO::fromArray($queryParams, $book_id);

        return $this->service->getContents($dto);
    }

    /**
     * Get full book contents (all pages) for one-shot download - use Accept-Encoding: gzip for compression
     * GET /api/v1/books/{book_id}/contents/full
     */
    public function getFullContents(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $this->requireBookAccess($request, $book_id);

        return $this->service->getFullContents($book_id);
    }

    /**
     * Get book table of contents (index)
     * GET /api/v1/books/{book_id}/index
     */
    public function getIndex(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $this->requireBookAccess($request, $book_id);

        $index = $this->service->getBookIndex($book_id);

        return [
            'index' => $index,
            'total' => count($index)
        ];
    }

    /**
     * Get a single content by ID
     * GET /api/v1/books/{book_id}/contents/{id}
     */
    public function show(Request $request, int $book_id, int $id): array
    {
        $this->requireBookAccess($request, $book_id);

        $content = $this->service->getContent($id);

        if (!$content) {
            throw new \RuntimeException('Content not found', 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            throw new \RuntimeException('Content does not belong to this book', 403);
        }

        return $content;
    }

    /**
     * Search in book content (full-text)
     * GET /api/v1/books/{book_id}/contents/search?q=keyword
     */
    public function search(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $this->requireBookAccess($request, $book_id);

        $query = $request->get['q'] ?? '';
        if (empty(trim($query))) {
            throw new \InvalidArgumentException('Search query (q) is required', 400);
        }

        $limit = min((int)($request->get['limit'] ?? 20), 100);
        $results = $this->service->search($book_id, $query, $limit);

        return [
            'results' => $results,
            'total' => count($results)
        ];
    }

    /**
     * Check access to a book (lightweight, non-cacheable)
     * GET /api/v1/books/{book_id}/access
     */
    public function access(Request $request, int $book_id): array
    {
        if ($book_id <= 0) {
            throw new \InvalidArgumentException('Invalid book ID', 400);
        }

        $userId = $this->getAuthUserId($request);
        if (!$userId) {
            throw new AccessDeniedException('توکن شما معتبر نیست!', 401);
        }

        $hasAccess = $this->bookRepo->userHasBookAccess($userId, $book_id);

        return [
            'has_access' => $hasAccess
        ];
    }

    /**
     * Ensure the authenticated user has access to the book.
     * Returns user id for optional downstream use.
     */
    private function requireBookAccess(Request $request, int $bookId): int
    {
        $payload = $this->getAuthPayload($request);
        if (!$payload) {
            throw new AccessDeniedException('توکن شما معتبر نیست!', 401);
        }

        $role = $payload->role ?? null;
        $level = $payload->level ?? null;
        if ($role === 'admin' || $role === 'support' || $level == 2) {
            return (int)($payload->id ?? 0);
        }

        $userId = (int)($payload->id ?? $payload->sub ?? $payload->user_id ?? 0);
        if ($userId <= 0) {
            throw new AccessDeniedException('توکن شما معتبر نیست!', 401);
        }

        if (!$this->bookRepo->userHasBookAccess($userId, $bookId)) {
            throw new AccessDeniedException('شما به این کتاب دسترسی ندارید', 403);
        }

        return $userId;
    }

    /**
     * Extract JWT payload from request (Authorization: Bearer ...)
     */
    private function getAuthPayload(Request $request): ?object
    {
        $token = $this->extractToken($request);
        if (!$token) return null;

        $payload = $this->verifyToken($token);
        if (!$payload) return null;

        return $payload;
    }

    /**
     * Get authenticated user ID from token
     */
    private function getAuthUserId(Request $request): ?int
    {
        $payload = $this->getAuthPayload($request);
        if (!$payload) return null;

        return (int)($payload->id ?? $payload->sub ?? $payload->user_id ?? 0) ?: null;
    }

    /**
     * Extract token from request header
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header['authorization'] ?? $request->header['Authorization'] ?? null;
        if ($authHeader && preg_match('/Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
