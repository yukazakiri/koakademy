<?php

declare(strict_types=1);

namespace App\Filament\Resources\CodeAuthorities\Pages;

use App\Filament\Resources\CodeAuthorities\CodeAuthorityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListCodeAuthorities extends ListRecords
{
    #[Override]
    protected static string $resource = CodeAuthorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
