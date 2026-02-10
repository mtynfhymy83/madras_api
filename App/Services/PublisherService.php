<?php

namespace App\Services;

use App\Database\QueryBuilder;
use App\DTOs\Admin\Publisher\CreatePublisherRequestDTO;
use Exception;

class PublisherService
{
    /**
     * Generate slug from title
     */
    private function generateSlug(string $title): string
    {
        // Convert Persian/Arabic characters to English equivalents
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $title = str_replace($persian, $english, $title);
        $title = str_replace($arabic, $english, $title);
        
        // Convert to lowercase
        $title = mb_strtolower($title, 'UTF-8');
        
        // Replace spaces and special characters with hyphens
        $title = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $title);
        $title = preg_replace('/[\s-]+/', '-', $title);
        $title = trim($title, '-');
        
        // Ensure slug is not empty
        if (empty($title)) {
            $title = 'publisher-' . time();
        }
        
        // Check if slug exists, if yes, append number
        $baseSlug = $title;
        $counter = 1;
        while ($this->slugExists($title)) {
            $title = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $title;
    }

    /**
     * Check if slug exists
     */
    private function slugExists(string $slug): bool
    {
        $qb = new QueryBuilder();
        $publisher = $qb->table('publishers')
            ->where('slug', '=', $slug)
            ->where('deleted_at', 'IS', null)
            ->first();
        
        return $publisher !== null;
    }

    /**
     * Create a new publisher
     */
    public function createPublisher(CreatePublisherRequestDTO $dto): array
    {
        // Generate slug if not provided
        $slug = $dto->slug ?? $this->generateSlug($dto->title);
        
        // Prepare data
        $data = [
            'title' => $dto->title,
            'slug' => $slug,
            'logo' => $dto->logo,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        // Add optional fields
        if ($dto->description !== null) {
            $data['description'] = $dto->description;
        }
        if ($dto->website !== null) {
            $data['website'] = $dto->website;
        }
        
        // Insert into database
        $qb = new QueryBuilder();
        $publisherId = $qb->table('publishers')->insert($data);
        
        // Get created publisher
        $publisher = $qb->table('publishers')
            ->where('id', '=', $publisherId)
            ->first();
        
        return [
            'id' => (int)$publisherId,
            'title' => $publisher['title'],
            'slug' => $publisher['slug'],
            'logo' => $publisher['logo'] ?? null,
            'description' => $publisher['description'] ?? null,
            'website' => $publisher['website'] ?? null,
            'created_at' => $publisher['created_at'],
        ];
    }

    /**
     * Get list of publishers
     */
    public function getPublishersList(array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $search = $params['search'] ?? null;
        
        $qb = new QueryBuilder();
        $qb->table('publishers')
            ->where('deleted_at', 'IS', null);
        
        // Search by title
        if (!empty($search)) {
            $qb->where('title', 'LIKE', "%{$search}%");
        }
        
        // Get total count
        $countQb = new QueryBuilder();
        $countQb->table('publishers')
            ->where('deleted_at', 'IS', null);
        
        if (!empty($search)) {
            $countQb->where('title', 'LIKE', "%{$search}%");
        }
        
        $total = $countQb->count();
        
        // Get paginated results
        $qb->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset(($page - 1) * $limit);
        
        $publishers = $qb->get();
        
        // Format results
        $items = array_map(function ($publisher) {
            return [
                'id' => (int)$publisher['id'],
                'title' => $publisher['title'],
                'slug' => $publisher['slug'],
                'logo' => $publisher['logo'] ?? null,
                'description' => $publisher['description'] ?? null,
                'website' => $publisher['website'] ?? null,
                'created_at' => $publisher['created_at'],
            ];
        }, $publishers);
        
        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get publisher by ID
     */
    public function getPublisherById(int $id): ?array
    {
        $qb = new QueryBuilder();
        $publisher = $qb->table('publishers')
            ->where('id', '=', $id)
            ->where('deleted_at', 'IS', null)
            ->first();
        
        if (!$publisher) {
            return null;
        }
        
        return [
            'id' => (int)$publisher['id'],
            'title' => $publisher['title'],
            'slug' => $publisher['slug'],
            'logo' => $publisher['logo'] ?? null,
            'description' => $publisher['description'] ?? null,
            'website' => $publisher['website'] ?? null,
            'created_at' => $publisher['created_at'],
        ];
    }
}
