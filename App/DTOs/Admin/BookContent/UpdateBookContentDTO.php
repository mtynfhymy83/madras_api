<?php

namespace App\DTOs\Admin\BookContent;

class UpdateBookContentDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $pageNumber = null,
        public readonly ?int $paragraphNumber = null,
        public readonly ?string $text = null,
        public readonly ?string $description = null,
        public readonly ?int $order = null,
        public readonly ?bool $isIndex = null,
        public readonly ?string $indexTitle = null,
        public readonly ?int $indexLevel = null
    ) {}

    public static function fromArray(array $data, int $id): self
    {
        return new self(
            id: $id,
            pageNumber: isset($data['page_number']) ? (int) $data['page_number'] : null,
            paragraphNumber: isset($data['paragraph_number']) ? (int) $data['paragraph_number'] : null,
            text: $data['text'] ?? null,
            description: $data['description'] ?? null,
            order: isset($data['order']) ? (int) $data['order'] : null,
            isIndex: isset($data['is_index']) ? (bool) $data['is_index'] : null,
            indexTitle: array_key_exists('index_title', $data) ? $data['index_title'] : null,
            indexLevel: isset($data['index_level']) ? (int) $data['index_level'] : null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->pageNumber !== null) {
            $data['page_number'] = $this->pageNumber;
        }
        if ($this->paragraphNumber !== null) {
            $data['paragraph_number'] = $this->paragraphNumber;
        }
        if ($this->text !== null) {
            $data['text'] = $this->text;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->order !== null) {
            $data['order'] = $this->order;
        }
        if ($this->isIndex !== null) {
            $data['is_index'] = $this->isIndex;
        }
        if ($this->indexTitle !== null) {
            $data['index_title'] = $this->indexTitle;
        }
        if ($this->indexLevel !== null) {
            $data['index_level'] = $this->indexLevel;
        }

        return $data;
    }
}
