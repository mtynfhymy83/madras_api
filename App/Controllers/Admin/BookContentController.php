<?php

namespace App\Controllers\Admin;

use App\Traits\ResponseTrait;
use App\Services\Admin\BookContentService;
use App\DTOs\Admin\BookContent\CreateBookContentDTO;
use App\DTOs\Admin\BookContent\UpdateBookContentDTO;
use App\DTOs\Admin\BookContent\GetBookContentsRequestDTO;
use Swoole\Http\Request;

class BookContentController
{
    use ResponseTrait;

    private BookContentService $service;

    public function __construct()
    {
        $this->service = new BookContentService();
    }

    /**
     * Get list of pages in a book
     * GET /admin/books/{book_id}/pages/list
     */
    public function getPages(Request $swooleRequest, int $book_id): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $pages = $this->service->getPagesList($book_id);

        return $this->sendResponse([
            'pages' => $pages,
            'total' => count($pages)
        ]);
    }

    /**
     * Get all contents for a specific page
     * GET /admin/books/{book_id}/pages/{page_number}
     */
    public function getPageContents(Request $swooleRequest, int $book_id, int $page_number): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $contents = $this->service->getPageContents($book_id, $page_number);

        return $this->sendResponse([
            'page_number' => $page_number,
            'contents' => $contents,
            'paragraph_count' => count($contents)
        ]);
    }

    /**
     * Get all contents for a book with pagination and filters
     * GET /admin/books/{book_id}/contents
     */
    public function index(Request $swooleRequest, int $book_id): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $queryParams = $swooleRequest->get ?? [];
        $dto = GetBookContentsRequestDTO::fromArray($queryParams, $book_id);
        
        $result = $this->service->getContents($dto);

        return $this->sendResponse($result);
    }

    /**
     * Get book index (table of contents)
     * GET /admin/books/{book_id}/index
     */
    public function getIndex(Request $swooleRequest, int $book_id): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $index = $this->service->getBookIndex($book_id);

        return $this->sendResponse([
            'index' => $index,
            'total' => count($index)
        ]);
    }

    /**
     * Get a single content by ID
     * GET /admin/books/{book_id}/contents/{id}
     */
    public function show(Request $swooleRequest, int $book_id, int $id): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        return $this->sendResponse($content);
    }

    /**
     * Create new page
     * POST /admin/books/{book_id}/pages/create
     */
    public function createPage(Request $swooleRequest, int $book_id, array $data = []): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $text = $data['text'] ?? null;
        $indexTitle = $data['index_title'] ?? null;

        $content = $this->service->createPage($book_id, $text, $indexTitle);

        return $this->sendResponse($content, 'Page created successfully');
    }

    /**
     * Add paragraph to a page
     * POST /admin/books/{book_id}/pages/{page_number}/paragraphs
     */
    public function addParagraph(Request $swooleRequest, int $book_id, int $page_number, array $data = []): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        if (empty($data['text'])) {
            return $this->sendResponse(null, 'Text is required', true, 400);
        }

        $content = $this->service->addParagraph(
            $book_id,
            $page_number,
            $data['text'],
            $data['description'] ?? null
        );

        return $this->sendResponse($content, 'Paragraph added successfully');
    }

    /**
     * Create new content (generic)
     * POST /admin/books/{book_id}/contents
     */
    public function store(Request $swooleRequest, int $book_id, array $data = []): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        if (!isset($data['page_number'])) {
            return $this->sendResponse(null, 'Page number is required', true, 400);
        }

        $dto = CreateBookContentDTO::fromArray($data, $book_id);
        $content = $this->service->createContent($dto);

        return $this->sendResponse($content, 'Content created successfully');
    }

    /**
     * Update content
     * PUT /admin/books/{book_id}/contents/{id}
     */
    public function update(Request $swooleRequest, int $book_id, int $id, array $data = []): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        $dto = UpdateBookContentDTO::fromArray($data, $id);
        $updated = $this->service->updateContent($dto);

        return $this->sendResponse($updated, 'Content updated successfully');
    }

    /**
     * Set content as index item
     * POST /admin/books/{book_id}/contents/{id}/set-index
     */
    public function setAsIndex(Request $swooleRequest, int $book_id, int $id, array $data = []): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        if (empty($data['title'])) {
            return $this->sendResponse(null, 'Index title is required', true, 400);
        }

        $updated = $this->service->setAsIndex(
            $id,
            $data['title'],
            (int)($data['level'] ?? 1)
        );

        return $this->sendResponse($updated, 'Content set as index item');
    }

    /**
     * Remove content from index
     * DELETE /admin/books/{book_id}/contents/{id}/remove-index
     */
    public function removeFromIndex(Request $swooleRequest, int $book_id, int $id): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        $updated = $this->service->removeFromIndex($id);

        return $this->sendResponse($updated, 'Content removed from index');
    }

    /**
     * Upload audio to content
     * POST /admin/books/{book_id}/contents/{id}/upload-audio
     */
    public function uploadAudio(Request $swooleRequest, int $book_id, int $id): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        $files = $swooleRequest->files ?? [];
        if (empty($files['audio'])) {
            return $this->sendResponse(null, 'Audio file is required', true, 400);
        }

        $result = $this->service->uploadAudio($id, $files['audio']);

        return $this->sendResponse($result, 'Audio uploaded successfully');
    }

    /**
     * Upload image to content
     * POST /admin/books/{book_id}/contents/{id}/upload-image
     */
    public function uploadImage(Request $swooleRequest, int $book_id, int $id): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        $files = $swooleRequest->files ?? [];
        if (empty($files['image'])) {
            return $this->sendResponse(null, 'Image file is required', true, 400);
        }

        $result = $this->service->uploadImage($id, $files['image']);

        return $this->sendResponse($result, 'Image uploaded successfully');
    }

    /**
     * Remove audio from content
     * DELETE /admin/books/{book_id}/contents/{id}/remove-audio
     */
    public function removeAudio(Request $swooleRequest, int $book_id, int $id): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        $this->service->removeAudio($id);

        return $this->sendResponse(null, 'Audio removed successfully');
    }

    /**
     * Remove image from content
     * DELETE /admin/books/{book_id}/contents/{id}/remove-image
     */
    public function removeImage(Request $swooleRequest, int $book_id, int $id, array $data = []): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        if (empty($data['image_path'])) {
            return $this->sendResponse(null, 'Image path is required', true, 400);
        }

        $this->service->removeImage($id, $data['image_path']);

        return $this->sendResponse(null, 'Image removed successfully');
    }

    /**
     * Delete content
     * DELETE /admin/books/{book_id}/contents/{id}
     */
    public function destroy(Request $swooleRequest, int $book_id, int $id): array
    {
        $content = $this->service->getContent($id);

        if (!$content) {
            return $this->sendResponse(null, 'Content not found', true, 404);
        }

        if ((int)$content['book_id'] !== $book_id) {
            return $this->sendResponse(null, 'Content does not belong to this book', true, 403);
        }

        $this->service->deleteContent($id);

        return $this->sendResponse(null, 'Content deleted successfully');
    }

    /**
     * Delete entire page
     * DELETE /admin/books/{book_id}/pages/{page_number}
     */
    public function deletePage(Request $swooleRequest, int $book_id, int $page_number): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $this->service->deletePage($book_id, $page_number);

        return $this->sendResponse(null, 'Page deleted successfully');
    }

    /**
     * Reorder contents
     * PUT /admin/books/{book_id}/contents/reorder
     */
    public function reorder(Request $swooleRequest, int $book_id, array $data = []): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        if (empty($data['ordered_ids']) || !is_array($data['ordered_ids'])) {
            return $this->sendResponse(null, 'ordered_ids array is required', true, 400);
        }

        $this->service->reorder($book_id, $data['ordered_ids']);

        return $this->sendResponse(null, 'Contents reordered successfully');
    }

    /**
     * Batch create contents
     * POST /admin/books/{book_id}/contents/batch
     */
    public function batchCreate(Request $swooleRequest, int $book_id, array $data = []): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        if (empty($data['contents']) || !is_array($data['contents'])) {
            return $this->sendResponse(null, 'contents array is required', true, 400);
        }

        $result = $this->service->batchCreate($book_id, $data['contents']);

        return $this->sendResponse($result, 'Contents created successfully');
    }

    /**
     * Search in book contents
     * GET /admin/books/{book_id}/contents/search
     */
    public function search(Request $swooleRequest, int $book_id): array
    {
        if ($book_id <= 0) {
            return $this->sendResponse(null, 'Invalid book ID', true, 400);
        }

        $query = $swooleRequest->get['q'] ?? '';
        if (empty($query)) {
            return $this->sendResponse(null, 'Search query is required', true, 400);
        }

        $results = $this->service->search($book_id, $query);

        return $this->sendResponse([
            'results' => $results,
            'total' => count($results)
        ]);
    }
}
