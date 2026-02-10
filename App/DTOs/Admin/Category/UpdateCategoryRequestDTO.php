<?php

namespace App\DTOs\Admin\Category;

class UpdateCategoryRequestDTO
{
    public ?string $title;
    public ?string $slug;
    public ?int $parentId;
    public ?string $icon;
    public ?string $type;
    public ?bool $isActive;
    public ?int $sortOrder;
    public bool $parentIdProvided = false;

    public function __construct(
        ?string $title = null,
        ?string $slug = null,
        ?int $parentId = null,
        ?string $icon = null,
        ?string $type = null,
        ?bool $isActive = null,
        ?int $sortOrder = null,
        bool $parentIdProvided = false
    ) {
        $this->title = $title;
        $this->slug = $slug;
        $this->parentId = $parentId;
        $this->icon = $icon;
        $this->type = $type;
        $this->isActive = $isActive;
        $this->sortOrder = $sortOrder;
        $this->parentIdProvided = $parentIdProvided;
    }

    public static function fromArray(array $data): self
    {
        $isActive = null;
        if (array_key_exists('is_active', $data)) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive === null) {
                $isActive = (bool)$data['is_active'];
            }
        }

        $parentProvided = array_key_exists('parent_id', $data);

        return new self(
            title: $data['title'] ?? null,
            slug: $data['slug'] ?? null,
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null,
            icon: $data['icon'] ?? null,
            type: $data['type'] ?? null,
            isActive: $isActive,
            sortOrder: array_key_exists('sort_order', $data) && $data['sort_order'] !== '' ? (int)$data['sort_order'] : null,
            parentIdProvided: $parentProvided
        );
    }
}
