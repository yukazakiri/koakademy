<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyInbox implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-inbox';
    }

    public function name(): string
    {
        return 'Inbox';
    }

    public function summary(): string
    {
        return 'Messaging and templates for student communication.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Toolkit';
    }

    public function accent(): string
    {
        return 'text-sky-500';
    }

    public function ctaLabel(): ?string
    {
        return null;
    }

    public function ctaUrl(): ?string
    {
        return null;
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Inbox',
                'summary' => 'Message students quickly with saved templates.',
                'highlights' => ['Messaging workflows', 'Reusable templates'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Inbox'],
                ],
                'badge' => 'Toolkit',
                'accent' => 'text-sky-500',
                'icon' => 'messages-square',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
