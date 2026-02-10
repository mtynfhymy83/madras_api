<?php

namespace App\DTOs\Admin\Publisher;

class CreatePublisherRequestDTO
{
    public string $title;
    public ?string $slug;
    public ?string $logo;
    public ?string $description;
    public ?string $website;

    public function __construct(
        string $title,
        ?string $slug = null,
        ?string $logo = null,
        ?string $description = null,
        ?string $website = null
    ) {
        $this->title = $title;
        $this->slug = $slug;
        $this->logo = $logo;
        $this->description = $description;
        $this->website = $website;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            slug: $data['slug'] ?? null,
            logo: $data['logo'] ?? null,
            description: $data['description'] ?? null,
            website: $data['website'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'description' => $this->description,
            'website' => $this->website,
        ];
    }
}
