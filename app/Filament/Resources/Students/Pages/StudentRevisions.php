<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\EditAction;
use Mansoor\FilamentVersionable\RevisionsPage;

final class StudentRevisions extends RevisionsPage
{
    protected static string $resource = StudentResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         EditAction::make(),
    //     ];
    // }
}
