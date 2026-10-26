<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Providers;

use App\Modules\ExtensionServiceProvider;
use Illuminate\Support\Facades\Gate;
use Koakademy\SchoolNotes\Models\SchoolNote;
use Koakademy\SchoolNotes\Policies\SchoolNotePolicy;

final class SchoolNotesServiceProvider extends ExtensionServiceProvider
{
    public function moduleName(): string
    {
        return 'SchoolNotes';
    }

    protected function bootExtension(): void
    {
        Gate::policy(SchoolNote::class, SchoolNotePolicy::class);
    }
}
