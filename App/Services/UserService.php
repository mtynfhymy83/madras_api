<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Helpers\JalaliHelper;

class UserService
{
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    public function getUsersList(array $params): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        unset($params['page'], $params['limit']);

        $repoData = $this->userRepo->getPaginatedWithStats($params, $page, $limit);

        $cleanItems = array_map(function ($user) {
            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'displayname' => $user['displayname'] ?? '',
                'email' => $user['email'],
                'mobile' => $user['mobile'],
                'role' => $user['role'],
                'status' => $user['status'],
                'created_at' => JalaliHelper::toJalali($user['created_at'] ?? null, 'Y/m/d H:i'),
                'stats' => [
                    'books_count' => (int)$user['userbooks'],
                    'devices_count' => (int)($user['usermobiles'] ?? 0),
                ]
            ];
        }, $repoData['data']);

        return [
            'items' => $cleanItems,
            'pagination' => $repoData['pagination']
        ];
    }


    private function getLevelLabel($level)
    {
        $map = [
            'admin'   => 'مدیر کل',
            'user'    => 'کاربر عادی',
            'teacher' => 'استاد',
            'support' => 'پشتیبان'
        ];
        return $map[$level] ?? $level;
    }

    private function generateImageUrl(?string $path): string
    {
        if (empty($path) || !file_exists(__DIR__ . '/../../../public/' . $path)) {
            return 'https://api.yoursite.com/assets/img/default-avatar.png';
        }
        return 'https://api.yoursite.com/' . $path;
    }
}
