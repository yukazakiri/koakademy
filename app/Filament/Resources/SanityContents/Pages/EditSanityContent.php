<?php

declare(strict_types=1);

namespace App\Filament\Resources\SanityContents\Pages;

use App\Filament\Resources\SanityContents\SanityContentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditSanityContent extends EditRecord
{
    protected static string $resource = SanityContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
