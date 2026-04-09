<?php

declare(strict_types=1);

namespace App\Filament\Resources\SanityContents\Pages;

use App\Filament\Resources\SanityContents\SanityContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSanityContents extends ListRecords
{
    protected static string $resource = SanityContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
