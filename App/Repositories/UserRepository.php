<?php

namespace App\Repositories;

use App\Database\QueryBuilder;
use App\Database\DB;
use App\Cache\Cache;

class UserRepository
{
    public function getPaginatedWithStats(array $filters, int $page, int $perPage): array
    {
        $qb = (new QueryBuilder())->table('users');
    
       
        $this->applyFilters($qb, $filters);
    
       
    
        $countQb = clone $qb;
        $total = $countQb->count();
    
       
        $qb->select([
            'users.*',
            '(SELECT COUNT(*) FROM "user_library" WHERE "user_library"."user_id" = "users"."id") as userbooks'
        ]);
    
        $sort = $filters['sort'] ?? 'id';
        $order = $filters['order'] ?? 'DESC';
        $qb->orderBy($sort, $order);
    
        
        $offset = ($page - 1) * $perPage;
        $data = $qb->limit($perPage)->offset($offset)->get();
    
        return [
            'data' => $data,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => ceil($total / $perPage),
                'from'         => $offset + 1,
                'to'           => $offset + count($data)
            ]
        ];
    }
    
    protected function applyFilters($qb, array $filters): void
    {
        if (!empty($filters['username'])) $qb->where('username', 'LIKE', '%' . $filters['username'] . '%');
        if (!empty($filters['email']))    $qb->where('email', 'LIKE', '%' . $filters['email'] . '%');
        if (!empty($filters['tel']))      $qb->where('mobile', 'LIKE', '%' . $filters['tel'] . '%');
        
        if (isset($filters['level']) && $filters['level'] !== '') {
            $qb->where('role', $filters['level']);
        }
        
        if (isset($filters['active']) && $filters['active'] !== '') {
            $qb->where('status', $filters['active']);
        }
    }
    public function findByIdWithProfile(int $id)
    {
        return (new QueryBuilder())
            ->table('users')
            ->select(['users.*', 'up.avatar_path as avatar', 'up.cover_path as cover', 'up.birth_date', 'up.province', 'up.city'])
            ->join('user_profiles as up', 'up.user_id', '=', 'users.id', 'LEFT')
            ->where('users.id', $id)
            ->first();
    }

    public function update(int $id, array $data): bool
    {
        return (new QueryBuilder())->table('users')->where('id', $id)->update($data);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $exists = (new QueryBuilder())->table('user_profiles')->where('user_id', $id)->first();
        
        $qb = (new QueryBuilder())->table('user_profiles');
        
        if ($exists) {
            return $qb->where('user_id', $id)->update($data);
        } else {
            $data['user_id'] = $id;
            return (bool)$qb->insert($data);
        }
    }

    public function getUserBooks(int $userId): array
    {
        return (new QueryBuilder())
            ->table('user_library as ul')
            ->select(['ul.product_id as ubid', 'p.title', 'c.title as cname'])
            ->join('products as p', 'p.id', '=', 'ul.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id', 'LEFT')
            ->where('ul.user_id', $userId)
            ->get();
    }
    
    public function searchBooksByTitle(string $query): array
    {
        return (new QueryBuilder())
            ->table('products')
            ->select(['id as idx', 'title'])
            ->where('title', 'LIKE', "%$query%")
            ->limit(10)
            ->get();
    }

    public function addUserBook(int $userId, int $bookId): void
    {
        $exists = DB::fetch(
            'SELECT 1 FROM user_library WHERE user_id = ? AND product_id = ? LIMIT 1',
            [$userId, $bookId]
        );
        if ($exists) {
            return;
        }
        DB::run(function (\PDO $pdo) use ($userId, $bookId) {
            $stmt = $pdo->prepare('INSERT INTO user_library (user_id, product_id, obtained_at) VALUES (?, ?, ?) ON CONFLICT (user_id, product_id) DO NOTHING');
            $stmt->execute([$userId, $bookId, date('Y-m-d H:i:s')]);
            return true;
        });
        Cache::delete("book:details:{$bookId}");
    }
}