<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Resources\FeatureResource\Pages;

use Filament\Resources\Pages\Page;

/** No longer routable; reject stale Livewire actions as well as new mounts. */
final class ManageScopes extends Page
{
    protected string $view = 'filament.plugins.pennant-manager.pages.manage-scopes';

    public function mount($record): void
    {
        abort(403, 'Manage feature access in Administrators → Feature access.');
    }

    public function activateScope(): void
    {
        abort(403);
    }

    public function removeScope($id): void
    {
        abort(403);
    }

    public function activateBySegment(): void
    {
        abort(403);
    }
}
