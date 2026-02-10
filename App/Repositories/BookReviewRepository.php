<?php

namespace App\Repositories;

use App\Database\DB;
use App\Database\QueryBuilder;
use App\Cache\Cache;

class BookReviewRepository
{
    private string $table = 'book_reviews';

    /**
     * Get paginated reviews for a book (optimized single query with user info)
     */
    public function getByBookId(int $bookId, int $page, int $perPage, string $sort = 'newest'): array
    {
        $offset = ($page - 1) * $perPage;

        $orderBy = match($sort) {
            'oldest' => 'r.created_at ASC',
            'helpful' => 'r.likes_count DESC, r.created_at DESC',
            'rating_high' => 'r.rating DESC, r.created_at DESC',
            'rating_low' => 'r.rating ASC, r.created_at DESC',
            default => 'r.created_at DESC', // newest
        };

        $sql = "
            SELECT 
                r.id, r.rating, r.text, r.likes_count, r.created_at,
                u.id as user_id,
                COALESCE(up.full_name, u.username) as user_name,
                up.avatar_path as user_avatar
            FROM {$this->table} r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE r.book_id = ? AND r.deleted_at IS NULL AND r.is_approved = true
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";

        $items = DB::fetchAll($sql, [$bookId, $perPage, $offset]);

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE book_id = ? AND deleted_at IS NULL AND is_approved = true";
        $countRow = DB::fetch($countSql, [$bookId]);
        $total = (int)($countRow['total'] ?? 0);

        // Get summary (avg rating, distribution)
        $summarySql = "
            SELECT 
                COALESCE(AVG(rating), 0) as avg_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
            FROM {$this->table}
            WHERE book_id = ? AND deleted_at IS NULL AND is_approved = true
        ";
        $summary = DB::fetch($summarySql, [$bookId]);

        return [
            'items' => array_map(fn($r) => [
                'id' => (int)$r['id'],
                'rating' => (int)$r['rating'],
                'text' => $r['text'],
                'likes_count' => (int)$r['likes_count'],
                'created_at' => $r['created_at'],
                'user' => [
                    'id' => (int)$r['user_id'],
                    'name' => $r['user_name'],
                    'avatar' => $r['user_avatar'],
                ],
            ], $items),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
            'summary' => [
                'avg_rating' => round((float)($summary['avg_rating'] ?? 0), 1),
                'total_reviews' => (int)($summary['total_reviews'] ?? 0),
                'distribution' => [
                    5 => (int)($summary['rating_5'] ?? 0),
                    4 => (int)($summary['rating_4'] ?? 0),
                    3 => (int)($summary['rating_3'] ?? 0),
                    2 => (int)($summary['rating_2'] ?? 0),
                    1 => (int)($summary['rating_1'] ?? 0),
                ],
            ],
        ];
    }

    /**
     * Find review by ID
     */
    public function findById(int $id): ?array
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Find user's review for a book
     */
    public function findByUserAndBook(int $userId, int $bookId): ?array
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('user_id', '=', $userId)
            ->where('book_id', '=', $bookId)
            ->first();
    }

    /**
     * Create a new review
     */
    public function create(array $data): int
    {
        return (int)(new QueryBuilder())
            ->table($this->table)
            ->insert([
                'user_id' => $data['user_id'],
                'book_id' => $data['book_id'],
                'rating' => $data['rating'],
                'text' => $data['text'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Update a review
     */
    public function update(int $id, array $data): bool
    {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        
        if (isset($data['rating'])) {
            $updateData['rating'] = $data['rating'];
        }
        if (array_key_exists('text', $data)) {
            $updateData['text'] = $data['text'];
        }

        return (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $id)
            ->update($updateData);
    }

    /**
     * Soft delete a review
     */
    public function delete(int $id): bool
    {
        return (new QueryBuilder())
            ->table($this->table)
            ->where('id', '=', $id)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Check if user has liked a review
     */
    public function hasUserLiked(int $userId, int $reviewId): bool
    {
        $row = DB::fetch(
            "SELECT 1 FROM book_review_likes WHERE user_id = ? AND review_id = ?",
            [$userId, $reviewId]
        );
        return (bool)$row;
    }

    /**
     * Toggle like on a review
     */
    public function toggleLike(int $userId, int $reviewId): bool
    {
        $hasLiked = $this->hasUserLiked($userId, $reviewId);

        if ($hasLiked) {
            // Remove like
            DB::execute("DELETE FROM book_review_likes WHERE user_id = ? AND review_id = ?", [$userId, $reviewId]);
            DB::execute("UPDATE {$this->table} SET likes_count = likes_count - 1 WHERE id = ? AND likes_count > 0", [$reviewId]);
            return false; // Now unliked
        } else {
            // Add like
            DB::execute("INSERT INTO book_review_likes (user_id, review_id) VALUES (?, ?)", [$userId, $reviewId]);
            DB::execute("UPDATE {$this->table} SET likes_count = likes_count + 1 WHERE id = ?", [$reviewId]);
            return true; // Now liked
        }
    }

    /**
     * Update book's average rating in products table
     */
    public function updateBookRating(int $bookId): void
    {
        $sql = "
            UPDATE products 
            SET rate_avg = (
                SELECT COALESCE(AVG(rating), 0) 
                FROM {$this->table} 
                WHERE book_id = ? AND deleted_at IS NULL AND is_approved = true
            ),
            rate_count = (
                SELECT COUNT(*) 
                FROM {$this->table} 
                WHERE book_id = ? AND deleted_at IS NULL AND is_approved = true
            )
            WHERE id = ?
        ";
        DB::execute($sql, [$bookId, $bookId, $bookId]);
        
        // Invalidate book details cache (rating changed)
        Cache::delete("book:details:{$bookId}");
    }
}
