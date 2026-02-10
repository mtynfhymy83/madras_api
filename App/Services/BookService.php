<?php

namespace App\Services;

use App\Repositories\BookRepository;
use App\DTOs\Admin\Book\CreateBookRequestDTO;
use App\Database\QueryBuilder;
use Exception;

class BookService
{
    private BookRepository $bookRepo;

    public function __construct()
    {
        $this->bookRepo = new BookRepository();
    }

    public function getBooksList(array $params): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);

        unset($params['page'], $params['limit']);

        $repoData = $this->bookRepo->getPaginatedWithStats($params, $page, $limit);

        $cleanItems = array_map(function ($book) {
            $attributes = json_decode($book['attributes'] ?? '{}', true);

            // Parse category path
            $categoryPath = $this->parseCategoryPath($book['category_path'] ?? null, $book['category_title'] ?? null, $book['category_parent_id'] ?? null);

            // Calculate relative time
            $lastUpdated = $this->getRelativeTime($book['updated_at'] ?? $book['created_at']);

            // Get download/share counts from attributes or calculate
            $downloadCount = (int)($attributes['download_count'] ?? $this->bookRepo->getDownloadCount($book['id']));
            $shareCount = (int)($attributes['share_count'] ?? $this->bookRepo->getShareCount($book['id']));

            return [
                'id' => (int)$book['id'],
                'old_id' => (int)($book['old_id'] ?? 0),
                'title' => $book['title'],
                'priority' => $this->getPriority($attributes),
                'category' => $categoryPath,
                'file_size_kb' => round((float)($attributes['file_size'] ?? 0) / 1024, 2),
                'price' => (int)($book['price'] ?? 0),
                'page_count' => (int)($attributes['pages'] ?? 0),
                'has_table_of_contents' => (bool)($attributes['has_description'] ?? false),
                'paragraph_count' => (int)($attributes['part_count'] ?? 0),
                'description_elements_count' => (int)($attributes['has_description'] ?? 0),
                'audio_count' => (int)($attributes['has_sound'] ?? 0),
                'video_count' => (int)($attributes['has_video'] ?? 0),
                'image_count' => (int)($attributes['has_image'] ?? 0),
                'test_exam_count' => (int)($attributes['has_test'] ?? 0),
                'descriptive_exam_count' => (int)($attributes['has_tashrihi'] ?? 0),
                'is_subscription_available' => (bool)($attributes['has_membership'] ?? false),
                'download_count' => $downloadCount,
                'share_count' => $shareCount,
                'author_name' => $book['author_name'] ?? null,
                'last_updated_at' => $book['updated_at'] ?? $book['created_at'],
                'last_updated_relative' => $lastUpdated,
                'publisher' => $book['publisher_title'] ?? null,
                'cover_image' => $book['cover_image'],
                'status' => (int)$book['status'],
                'rate_avg' => round((float)($book['rate_avg'] ?? 0), 2),
                'rate_count' => (int)($book['rate_count'] ?? 0),
            ];
        }, $repoData['data']);

        return [
            'items' => $cleanItems,
            'pagination' => $repoData['pagination']
        ];
    }

    private function getPriority(array $attributes): string
    {
        $position = (int)($attributes['position'] ?? 0);
        if ($position > 0) {
            return 'بالا';
        }
        return 'عادی';
    }

    private function parseCategoryPath(?string $path, ?string $title, ?int $parentId): ?string
    {
        if (empty($title)) {
            return null;
        }

        // If there's a parent, we might need to build the path
        // For now, return just the category title
        // TODO: Build full path if parent_id exists
        return $title;
    }

    private function getRelativeTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return 'نامشخص';
        }

        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'همین الان';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "$minutes دقیقه";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "$hours ساعت";
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return "$days روز";
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return "$months ماه";
        } else {
            $years = floor($diff / 31536000);
            return "$years سال";
        }
    }

    public function createBook(CreateBookRequestDTO $dto): array
    {
        // Generate slug
        $slug = $this->bookRepo->generateSlug($dto->title);

        // Prepare attributes
        $attributes = [
            'position' => $dto->priority === 'high' ? 1 : 0,
            'start_page_number' => $dto->startPageNumber,
            'vertical_image' => $dto->verticalImage,
            'horizontal_image' => $dto->horizontalImage,
            'sample_questions_file' => $dto->sampleQuestionsFile,
            'attached_files' => $dto->attachedFiles,
        ];

        // Map status
        $status = $this->mapStatus($dto->status);

        // Validate publisher_id exists
        $publisherId = null;
        if (!empty($dto->publisherId)) {
            $publisher = (new QueryBuilder())
                ->table('publishers')
                ->where('id', '=', $dto->publisherId)
                ->where('deleted_at', 'IS', null)
                ->first();
            
            if ($publisher) {
                $publisherId = $dto->publisherId;
            } else {
                // Publisher doesn't exist, set to null to avoid foreign key violation
                // Log warning for debugging
                error_log("Warning: Publisher ID {$dto->publisherId} not found in publishers table, setting publisher_id to null");
            }
        }

        // Validate category_id exists
        $categoryId = null;
        if (!empty($dto->categoryIds) && !empty($dto->categoryIds[0])) {
            $category = (new QueryBuilder())
                ->table('categories')
                ->withoutSoftDelete() // Categories table doesn't have deleted_at column
                ->where('id', '=', $dto->categoryIds[0])
                ->where('is_active', '=', true)
                ->first();
            
            if ($category) {
                $categoryId = $dto->categoryIds[0];
            } else {
                error_log("Warning: Category ID {$dto->categoryIds[0]} not found or inactive, setting to null");
            }
        }

        // Extract cover image URL from upload result
        $coverImageUrl = null;
        if ($dto->horizontalImage) {
            // If it's JSON from upload result, extract URL
            if (is_string($dto->horizontalImage) && (str_starts_with($dto->horizontalImage, '{') || str_starts_with($dto->horizontalImage, '['))) {
                $uploadData = json_decode($dto->horizontalImage, true);
                $coverImageUrl = $uploadData['original']['url'] ?? null;
            } else {
                $coverImageUrl = $dto->horizontalImage;
            }
        } elseif ($dto->verticalImage) {
            // If it's JSON from upload result, extract URL
            if (is_string($dto->verticalImage) && (str_starts_with($dto->verticalImage, '{') || str_starts_with($dto->verticalImage, '['))) {
                $uploadData = json_decode($dto->verticalImage, true);
                $coverImageUrl = $uploadData['original']['url'] ?? null;
            } else {
                $coverImageUrl = $dto->verticalImage;
            }
        }

        // Debug: Log cover image URL length
        if ($coverImageUrl) {
            error_log("Cover Image URL length: " . strlen($coverImageUrl) . " - First 100: " . substr($coverImageUrl, 0, 100));
        }

        // Prepare product data
        $productData = [
            'title' => $dto->title,
            'slug' => $slug,
            'status' => $status,
            'price' => $dto->price,
            'publisher_id' => $publisherId,
            'category_id' => $categoryId,
            'cover_image' => $coverImageUrl ? substr($coverImageUrl, 0, 1000) : null, // Limit to 1000 chars
            'description' => $dto->description ?? $dto->abstract,
            'attributes' => $attributes,
            'created_at' => $dto->createdAt ?? date('Y-m-d H:i:s'),
            'updated_at' => $dto->updatedAt ?? date('Y-m-d H:i:s'),
        ];

        // Create product
        $productId = $this->bookRepo->create($productData);

        // Attach author (validate author exists first)
        if ($dto->authorId) {
            $author = (new QueryBuilder())
                ->table('persons')
                ->withoutSoftDelete() // persons table might not have deleted_at
                ->where('id', '=', $dto->authorId)
                ->first();
            
            if ($author) {
                $this->bookRepo->attachAuthor($productId, $dto->authorId);
            } else {
                error_log("Warning: Author ID {$dto->authorId} not found in persons table, skipping author attachment");
            }
        }

        // Attach categories (if multiple supported)
        if (!empty($dto->categoryIds)) {
            $this->bookRepo->attachCategories($productId, $dto->categoryIds);
        }

        // Return created book
        $book = (new QueryBuilder())
            ->table('products')
            ->where('id', '=', $productId)
            ->first();

        return [
            'id' => $productId,
            'title' => $book['title'],
            'slug' => $book['slug'],
            'status' => $book['status'],
        ];
    }

    private function mapStatus(string $status): int
    {
        $map = [
            'publish' => 1,
            'draft' => 0,
            'ready' => 2,
            'انتشار' => 1,
            'پیش_نویس' => 0,
            'آماده_انتشار' => 2,
        ];
        return $map[$status] ?? 0;
    }
}
