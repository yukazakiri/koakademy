<?php

declare(strict_types=1);

namespace App\Filament\Resources\CodeAuthorities\Pages;

use App\Filament\Resources\CodeAuthorities\CodeAuthorityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditCodeAuthority extends EditRecord
{
    #[Override]
    protected static string $resource = CodeAuthorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
