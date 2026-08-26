<?php

declare(strict_types=1);

namespace App\Modules;

final readonly class CompatibilityResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public array $errors = []) {}

    public function isCompatible(): bool
    {
        return $this->errors === [];
    }
}
