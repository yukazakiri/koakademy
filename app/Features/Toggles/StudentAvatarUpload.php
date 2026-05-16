<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Enums\UserRole;
use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class StudentAvatarUpload implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-avatar-upload';
    }

    public function name(): string
    {
        return 'Student Avatar Upload';
    }

    public function summary(): ?string
    {
        return 'Drag-and-drop avatar uploads with preview.';
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
