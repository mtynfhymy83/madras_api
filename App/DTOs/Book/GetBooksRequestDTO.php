<?php

namespace App\DTOs\Book;

class GetBooksRequestDTO
{
    public int $page;
    public int $limit;
    public ?string $search;
    public ?int $categoryId;
    public string $sort;
    public string $order;

    public function __construct(int $page, int $limit, ?string $search, ?int $categoryId, string $sort, string $order)
    {
        $this->page = $page;
        $this->limit = $limit;
        $this->search = $search;
        $this->categoryId = $categoryId;
        $this->sort = $sort;
        $this->order = $order;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)($data['page'] ?? 1),
            (int)($data['limit'] ?? 20),
            isset($data['search']) ? trim($data['search']) : null,
            isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null,
            $data['sort'] ?? 'id',
            strtoupper($data['order'] ?? 'DESC')
        );
    }
}
