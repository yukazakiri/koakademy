<?php

declare(strict_types=1);

namespace App\Data;

final readonly class NewsletterContact
{
    /**
     * @param  list<string>  $tags
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public string $email,
        public string $externalId,
        public string $role,
        public ?string $firstName,
        public ?string $lastName,
        public array $tags,
        public array $attributes,
    ) {}
}
