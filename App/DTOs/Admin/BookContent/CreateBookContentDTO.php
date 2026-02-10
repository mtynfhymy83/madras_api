<?php

namespace App\DTOs\Admin\BookContent;

class CreateBookContentDTO
{
    public function __construct(
        public readonly int $bookId,
        public readonly int $pageNumber,
        public readonly int $paragraphNumber,
        public readonly ?string $text = null,
        public readonly ?string $description = null,
        public readonly ?int $order = null,
        public readonly bool $isIndex = false,
        public readonly ?string $indexTitle = null,
        public readonly int $indexLevel = 0
    ) {}

    public static function fromArray(array $data, int $bookId): self
    {
        return new self(
            bookId: $bookId,
            pageNumber: (int) ($data['page_number'] ?? 1),
            paragraphNumber: (int) ($data['paragraph_number'] ?? 1),
            text: $data['text'] ?? null,
            description: $data['description'] ?? null,
            order: isset($data['order']) ? (int) $data['order'] : null,
            isIndex: (bool) ($data['is_index'] ?? false),
            indexTitle: $data['index_title'] ?? null,
            indexLevel: (int) ($data['index_level'] ?? 0)
        );
    }

    public function toArray(): array
    {
        $data = [
            'book_id' => $this->bookId,
            'page_number' => $this->pageNumber,
            'paragraph_number' => $this->paragraphNumber,
            'is_index' => $this->isIndex,
            'index_level' => $this->indexLevel,
        ];

        if ($this->text !== null) {
            $data['text'] = $this->text;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->order !== null) {
            $data['order'] = $this->order;
        }
        if ($this->indexTitle !== null) {
            $data['index_title'] = $this->indexTitle;
        }

        return $data;
    }
}
