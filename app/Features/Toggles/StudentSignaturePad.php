<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Enums\UserRole;
use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class StudentSignaturePad implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-signature-pad';
    }

    public function name(): string
    {
        return 'Student Signature Pad';
    }

    public function summary(): ?string
    {
        return 'Digital signature capture for student documents.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function category(): string
    {
        return 'Student';
    }

    public function steps(): array
    {
        return [];
    }

    public function badge(): ?string
    {
        return null;
    }

    public function accent(): ?string
    {
        return null;
    }

    public function ctaLabel(): ?string
    {
        return null;
    }

    public function ctaUrl(): ?string
    {
        return null;
    }

    /**
     * Resolve the feature's initial value.
     *
     * Admins get the feature by default. Other roles can be
     * opted-in via Feature::activate() or incremental rollout
     * using Lottery.
     */
    public function resolve(User $scope): bool
    {
        return $scope->role === UserRole::Admin;
    }
}
