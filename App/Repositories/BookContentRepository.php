<?php

namespace App\Repositories;

use App\Database\DB;
use App\Database\QueryBuilder;
use App\DTOs\Admin\BookContent\CreateBookContentDTO;
use App\DTOs\Admin\BookContent\UpdateBookContentDTO;
use App\DTOs\Admin\BookContent\GetBookContentsRequestDTO;

class BookContentRepository
{
    private string $table = 'book_contents';

    /**
     * Get all contents for a book with pagination
     */
    public function getByBookId(GetBookContentsRequestDTO $dto): array
    {
        $applyFilters = function (QueryBuilder $q) use ($dto): QueryBuilder {
            $q->table($this->table)->where('book_id', '=', $dto->bookId);
            if ($dto->pageNumber !== null) {
                $q->where('page_number', '=', $dto->pageNumber);
            }
            if ($dto->indexOnly === true) {
                $q->where('is_index', '=', 't');
            }
            return $q;
        };

        // Count: use a fresh query (count() calls reset() and would clear filters for get())
        $queryCount = $applyFilters(new QueryBuilder());
        $total = $queryCount->count();

        // Get paginated results: same filters on a new query
        $queryGet = $applyFilters(new QueryBuilder());
        $offset = ($dto->page - 1) * $dto->perPage;
        $items = $queryGet
            ->orderBy($dto->sortBy, $dto->sortOrder)
            ->limit($dto->perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $dto->page,
                'per_page' => $dto->perPage,
                'total_pages' => (int) ceil($total / $dto->perPage)
            ]
        ];
    }

    /**
     * Get a single content by ID
     */
    public function findById(int $id): ?array
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Get all contents for a specific page
     */
    public function getByPageNumber(int $bookId, int $pageNumber): array
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('book_id', '=', $bookId)
            ->where('page_number', '=', $pageNumber)
            ->orderBy('paragraph_number', 'asc')
            ->get();
    }

    /**
     * Get book index (table of contents) - simplified format
     * Returns: id, part_id (same as id), name, level, page_number
     */
    public function getBookIndex(int $bookId): array
    {
        // PostgreSQL boolean: use 't' or 'f' in raw SQL, not true/false
        // Use ? placeholder instead of $1 for PDO compatibility
        $sql = "SELECT id, id as part_id, index_title as name, index_level as level, page_number
                FROM {$this->table}
                WHERE book_id = ? AND is_index = 't' AND deleted_at IS NULL
                ORDER BY page_number ASC, paragraph_number ASC";
        
        return DB::fetchAll($sql, [$bookId]);
    }

    /**
     * Get full book contents (all pages) for download - excludes tsv to save memory/bandwidth
     */
    public function getFullByBookId(int $bookId): array
    {
        $sql = "SELECT id, book_id, page_number, paragraph_number, \"order\", text, description,
                sound_path, image_paths, video_path, is_index, index_title, index_level,
                created_at, updated_at
                FROM {$this->table}
                WHERE book_id = ? AND deleted_at IS NULL
                ORDER BY page_number ASC, paragraph_number ASC";
        $items = DB::fetchAll($sql, [$bookId]);
        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    /**
     * Get list of pages in a book
     */
    public function getPagesList(int $bookId): array
    {
        return DB::fetchAll(
            "SELECT DISTINCT page_number, 
                    MIN(id) as first_content_id,
                    COUNT(*) as paragraph_count,
                    MIN(CASE WHEN is_index = 't' THEN index_title END) as page_title
             FROM {$this->table} 
             WHERE book_id = ? AND deleted_at IS NULL AND page_number IS NOT NULL
             GROUP BY page_number 
             ORDER BY page_number ASC",
            [$bookId]
        );
    }

    /**
     * Get max page number for a book
     */
    public function getMaxPageNumber(int $bookId): int
    {
        $result = DB::fetch(
            "SELECT MAX(page_number) as max_page FROM {$this->table} WHERE book_id = ? AND deleted_at IS NULL",
            [$bookId]
        );
        return (int) ($result['max_page'] ?? 0);
    }

    /**
     * Get max paragraph number for a page
     */
    public function getMaxParagraphNumber(int $bookId, int $pageNumber): int
    {
        $result = DB::fetch(
            "SELECT MAX(paragraph_number) as max_para FROM {$this->table} WHERE book_id = ? AND page_number = ? AND deleted_at IS NULL",
            [$bookId, $pageNumber]
        );
        return (int) ($result['max_para'] ?? 0);
    }

    /**
     * Get max order for a book
     */
    public function getMaxOrder(int $bookId): int
    {
        $result = DB::fetch(
            "SELECT MAX(\"order\") as max_order FROM {$this->table} WHERE book_id = ? AND deleted_at IS NULL",
            [$bookId]
        );
        return (int) ($result['max_order'] ?? 0);
    }

    /**
     * Create new content
     */
    public function create(CreateBookContentDTO $dto): ?array
    {
        $data = $dto->toArray();
        
        // Auto-set order if not provided
        if (!isset($data['order'])) {
            $data['order'] = $this->getMaxOrder($dto->bookId) + 1;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id = (new QueryBuilder())
            ->table($this->table)
            ->insert($data);

        if ($id) {
            return $this->findById((int) $id);
        }

        return null;
    }

    /**
     * Update content
     */
    public function update(UpdateBookContentDTO $dto): ?array
    {
        $data = $dto->toArray();
        
        if (empty($data)) {
            return $this->findById($dto->id);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $dto->id)
            ->update($data);

        return $this->findById($dto->id);
    }

    /**
     * Update media paths
     */
    public function updateMedia(int $id, array $mediaData): bool
    {
        $data = array_merge($mediaData, ['updated_at' => date('Y-m-d H:i:s')]);

        return (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $id)
            ->update($data);
    }

    /**
     * Delete content (soft delete)
     */
    public function delete(int $id): bool
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $id)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Delete all contents for a page
     */
    public function deleteByPageNumber(int $bookId, int $pageNumber): bool
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('book_id', '=', $bookId)
            ->where('page_number', '=', $pageNumber)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Reorder contents
     */
    public function reorder(int $bookId, array $orderedIds): bool
    {
        return DB::transaction(function ($pdo) use ($bookId, $orderedIds) {
            $order = 1;
            foreach ($orderedIds as $id) {
                $stmt = $pdo->prepare(
                    "UPDATE {$this->table} SET \"order\" = ?, updated_at = NOW() WHERE id = ? AND book_id = ?"
                );
                $stmt->execute([$order, $id, $bookId]);
                $order++;
            }
            return true;
        });
    }

    /**
     * Batch create contents (for importing paragraphs)
     */
    public function batchCreate(int $bookId, array $contents): array
    {
        $createdIds = [];
        
        DB::transaction(function ($pdo) use ($bookId, $contents, &$createdIds) {
            $order = $this->getMaxOrder($bookId);
            
            foreach ($contents as $content) {
                $order++;
                $dto = CreateBookContentDTO::fromArray($content, $bookId);
                $data = $dto->toArray();
                $data['order'] = $order;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                
                $columns = array_keys($data);
                $placeholders = array_map(fn($i) => '$' . ($i + 1), array_keys($data));
                
                $sql = sprintf(
                    'INSERT INTO %s ("%s") VALUES (%s) RETURNING id',
                    $this->table,
                    implode('", "', $columns),
                    implode(', ', $placeholders)
                );
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($result) {
                    $createdIds[] = (int) $result['id'];
                }
            }
        });
        
        return $createdIds;
    }

    /**
     * Search in book contents
     */
    public function search(int $bookId, string $query, int $limit = 20): array
    {
        return DB::fetchAll(
            "SELECT id, page_number, paragraph_number, text, 
                    ts_headline('simple', text, plainto_tsquery('simple', ?)) as highlight
             FROM {$this->table}
             WHERE book_id = ? 
               AND deleted_at IS NULL
               AND tsv @@ plainto_tsquery('simple', ?)
             ORDER BY ts_rank(tsv, plainto_tsquery('simple', ?)) DESC
             LIMIT ?",
            [$query, $bookId, $query, $query, $limit]
        );
    }
}
