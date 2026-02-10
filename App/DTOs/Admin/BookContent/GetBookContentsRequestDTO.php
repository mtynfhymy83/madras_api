<?php

namespace App\DTOs\Admin\BookContent;

class GetBookContentsRequestDTO
{
    public function __construct(
        public readonly int $bookId,
        public readonly ?int $pageNumber = null,
        public readonly ?bool $indexOnly = null,
        public readonly int $page = 1,
        public readonly int $perPage = 50,
        public readonly string $sortBy = 'order',
        public readonly string $sortOrder = 'asc'
    ) {}

    public static function fromArray(array $data, int $bookId): self
    {
        return new self(
            bookId: $bookId,
            pageNumber: isset($data['page_number']) ? (int) $data['page_number'] : null,
            indexOnly: isset($data['index_only']) ? (bool) $data['index_only'] : null,
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 50), 100),
            sortBy: $data['sort_by'] ?? 'order',
            sortOrder: strtolower($data['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc'
        );
    }
}
