<?php

namespace App\Services\Admin;

use App\Repositories\BookContentRepository;
use App\DTOs\Admin\BookContent\CreateBookContentDTO;
use App\DTOs\Admin\BookContent\UpdateBookContentDTO;
use App\DTOs\Admin\BookContent\GetBookContentsRequestDTO;
use App\Services\UploadService;

class BookContentService
{
    private BookContentRepository $repository;
    private UploadService $uploadService;

    public function __construct()
    {
        $this->repository = new BookContentRepository();
        $this->uploadService = new UploadService();
    }

    /**
     * Remove heavy tsv (full-text vector) from content items before returning to API.
     * Array must be passed by reference so modifications apply to the original.
     */
    private function stripTsvFromItems(array &$items): void
    {
        foreach ($items as &$item) {
            if (is_array($item) && array_key_exists('tsv', $item)) {
                unset($item['tsv']);
            }
        }
    }

    /**
     * Get all contents for a book with pagination
     */
    public function getContents(GetBookContentsRequestDTO $dto): array
    {
        $result = $this->repository->getByBookId($dto);
        $this->stripTsvFromItems($result['items']);
        return $result;
    }

    /**
     * Get a single content by ID
     */
    public function getContent(int $id): ?array
    {
        $content = $this->repository->findById($id);
        
        if ($content) {
            unset($content['tsv']);
            // Parse image_paths from JSON if exists
            if (!empty($content['image_paths'])) {
                $content['image_paths'] = json_decode($content['image_paths'], true) ?? [];
            } else {
                $content['image_paths'] = [];
            }
        }
        
        return $content;
    }

    /**
     * Get all contents for a specific page
     */
    public function getPageContents(int $bookId, int $pageNumber): array
    {
        $contents = $this->repository->getByPageNumber($bookId, $pageNumber);
        $this->stripTsvFromItems($contents);

        // Parse image_paths for each content
        foreach ($contents as &$content) {
            if (!empty($content['image_paths'])) {
                $content['image_paths'] = json_decode($content['image_paths'], true) ?? [];
            } else {
                $content['image_paths'] = [];
            }
        }

        return $contents;
    }

    /**
     * Get full book contents (all pages) for one-shot download - no tsv, image_paths parsed
     */
    public function getFullContents(int $bookId): array
    {
        $result = $this->repository->getFullByBookId($bookId);
        $items = &$result['items'];
        foreach ($items as &$item) {
            if (!empty($item['image_paths'])) {
                $item['image_paths'] = json_decode($item['image_paths'], true) ?? [];
            } else {
                $item['image_paths'] = [];
            }
        }
        $totalPages = 0;
        if (!empty($items)) {
            $totalPages = (int) max(array_column($items, 'page_number'));
        }
        return [
            'items' => $items,
            'total' => $result['total'],
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get list of pages in a book
     */
    public function getPagesList(int $bookId): array
    {
        return $this->repository->getPagesList($bookId);
    }

    /**
     * Get book index (table of contents)
     */
    public function getBookIndex(int $bookId): array
    {
        return $this->repository->getBookIndex($bookId);
    }

    /**
     * Create new content
     */
    public function createContent(CreateBookContentDTO $dto): array
    {
        $content = $this->repository->create($dto);
        
        if (!$content) {
            throw new \RuntimeException('Failed to create content');
        }
        
        return $content;
    }

    /**
     * Create a new page with optional initial content
     */
    public function createPage(int $bookId, ?string $text = null, ?string $indexTitle = null): array
    {
        $pageNumber = $this->repository->getMaxPageNumber($bookId) + 1;
        
        $dto = new CreateBookContentDTO(
            bookId: $bookId,
            pageNumber: $pageNumber,
            paragraphNumber: 1,
            text: $text,
            isIndex: $indexTitle !== null,
            indexTitle: $indexTitle,
            indexLevel: 1
        );
        
        return $this->createContent($dto);
    }

    /**
     * Add paragraph to existing page
     */
    public function addParagraph(int $bookId, int $pageNumber, string $text, ?string $description = null): array
    {
        $paragraphNumber = $this->repository->getMaxParagraphNumber($bookId, $pageNumber) + 1;
        
        $dto = new CreateBookContentDTO(
            bookId: $bookId,
            pageNumber: $pageNumber,
            paragraphNumber: $paragraphNumber,
            text: $text,
            description: $description
        );
        
        return $this->createContent($dto);
    }

    /**
     * Update content
     */
    public function updateContent(UpdateBookContentDTO $dto): array
    {
        $content = $this->repository->update($dto);
        
        if (!$content) {
            throw new \RuntimeException('Failed to update content');
        }
        
        return $content;
    }

    /**
     * Set content as index item
     */
    public function setAsIndex(int $id, string $title, int $level = 1): array
    {
        $dto = new UpdateBookContentDTO(
            id: $id,
            isIndex: true,
            indexTitle: $title,
            indexLevel: $level
        );
        
        return $this->updateContent($dto);
    }

    /**
     * Remove from index
     */
    public function removeFromIndex(int $id): array
    {
        $dto = new UpdateBookContentDTO(
            id: $id,
            isIndex: false,
            indexTitle: null,
            indexLevel: 0
        );
        
        return $this->updateContent($dto);
    }

    /**
     * Upload and attach audio to content
     */
    public function uploadAudio(int $id, array $file): array
    {
        $content = $this->repository->findById($id);
        if (!$content) {
            throw new \RuntimeException('Content not found');
        }

        // Upload audio file
        $result = $this->uploadService->uploadFile($file, 'book_audio');
        
        if (!$result['success']) {
            throw new \RuntimeException($result['message'] ?? 'Failed to upload audio');
        }

        // Update content with audio path
        $this->repository->updateMedia($id, ['sound_path' => $result['path']]);

        return [
            'success' => true,
            'sound_path' => $result['path'],
            'url' => $result['url'] ?? null
        ];
    }

    /**
     * Upload and attach image to content
     */
    public function uploadImage(int $id, array $file): array
    {
        $content = $this->repository->findById($id);
        if (!$content) {
            throw new \RuntimeException('Content not found');
        }

        // Upload image file
        $result = $this->uploadService->uploadFile($file, 'book_images');
        
        if (!$result['success']) {
            throw new \RuntimeException($result['message'] ?? 'Failed to upload image');
        }

        // Get existing images
        $existingImages = [];
        if (!empty($content['image_paths'])) {
            $existingImages = json_decode($content['image_paths'], true) ?? [];
        }

        // Add new image
        $existingImages[] = $result['path'];

        // Update content with image paths
        $this->repository->updateMedia($id, ['image_paths' => json_encode($existingImages)]);

        return [
            'success' => true,
            'image_path' => $result['path'],
            'url' => $result['url'] ?? null,
            'all_images' => $existingImages
        ];
    }

    /**
     * Remove audio from content
     */
    public function removeAudio(int $id): bool
    {
        return $this->repository->updateMedia($id, ['sound_path' => null]);
    }

    /**
     * Remove specific image from content
     */
    public function removeImage(int $id, string $imagePath): bool
    {
        $content = $this->repository->findById($id);
        if (!$content) {
            return false;
        }

        $existingImages = [];
        if (!empty($content['image_paths'])) {
            $existingImages = json_decode($content['image_paths'], true) ?? [];
        }

        // Remove the specific image
        $existingImages = array_filter($existingImages, fn($img) => $img !== $imagePath);
        $existingImages = array_values($existingImages); // Re-index array

        return $this->repository->updateMedia($id, ['image_paths' => json_encode($existingImages)]);
    }

    /**
     * Delete content
     */
    public function deleteContent(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Delete entire page
     */
    public function deletePage(int $bookId, int $pageNumber): bool
    {
        return $this->repository->deleteByPageNumber($bookId, $pageNumber);
    }

    /**
     * Reorder contents
     */
    public function reorder(int $bookId, array $orderedIds): bool
    {
        return $this->repository->reorder($bookId, $orderedIds);
    }

    /**
     * Batch create contents (for importing)
     */
    public function batchCreate(int $bookId, array $contents): array
    {
        $createdIds = $this->repository->batchCreate($bookId, $contents);
        
        return [
            'success' => true,
            'created_count' => count($createdIds),
            'ids' => $createdIds
        ];
    }

    /**
     * Search in book contents
     */
    public function search(int $bookId, string $query, int $limit = 20): array
    {
        $results = $this->repository->search($bookId, $query, $limit);
        $this->stripTsvFromItems($results);
        return $results;
    }
}
