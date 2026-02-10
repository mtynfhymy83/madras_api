<?php

namespace App\DTOs\Admin\Book;

class CreateBookRequestDTO
{
    public string $title;
    public ?int $authorId;
    public string $priority;
    public ?string $abstract;
    public array $categoryIds;
    public int $price;
    public ?int $startPageNumber;
    public ?string $verticalImage;
    public ?string $horizontalImage;
    public ?string $sampleQuestionsFile;
    public array $attachedFiles;
    public string $status; // publish, draft, ready
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?int $publisherId;
    public ?string $description;

    public function __construct(
        string $title,
        ?int $authorId,
        string $priority,
        ?string $abstract,
        array $categoryIds,
        int $price,
        ?int $startPageNumber,
        ?string $verticalImage,
        ?string $horizontalImage,
        ?string $sampleQuestionsFile,
        array $attachedFiles,
        string $status,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?int $publisherId = null,
        ?string $description = null
    ) {
        $this->title = $title;
        $this->authorId = $authorId;
        $this->priority = $priority;
        $this->abstract = $abstract;
        $this->categoryIds = $categoryIds;
        $this->price = $price;
        $this->startPageNumber = $startPageNumber;
        $this->verticalImage = $verticalImage;
        $this->horizontalImage = $horizontalImage;
        $this->sampleQuestionsFile = $sampleQuestionsFile;
        $this->attachedFiles = $attachedFiles;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->publisherId = $publisherId;
        $this->description = $description;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            trim($data['title'] ?? ''),
            isset($data['author_id']) && $data['author_id'] !== '' ? (int)$data['author_id'] : null,
            self::normalizePriority($data['priority'] ?? 'normal'),
            $data['abstract'] ?? null,
            isset($data['category_ids']) && is_array($data['category_ids']) ? array_map('intval', $data['category_ids']) : [],
            (int)($data['price'] ?? 0),
            isset($data['start_page_number']) && $data['start_page_number'] !== '' ? (int)$data['start_page_number'] : null,
            $data['vertical_image'] ?? null,
            $data['horizontal_image'] ?? null,
            $data['sample_questions_file'] ?? null,
            isset($data['attached_files']) && is_array($data['attached_files']) ? $data['attached_files'] : [],
            $data['status'] ?? 'draft',
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null,
            isset($data['publisher_id']) && $data['publisher_id'] !== '' ? (int)$data['publisher_id'] : null,
            $data['description'] ?? null
        );
    }

    private static function normalizePriority(string $priority): string
    {
        $map = [
            'عادی' => 'normal',
            'بالا' => 'high',
            'normal' => 'normal',
            'high' => 'high',
        ];
        return $map[$priority] ?? 'normal';
    }
}
