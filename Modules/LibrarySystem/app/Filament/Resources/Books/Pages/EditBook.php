<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;

final class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $totalCopies = max(1, (int) ($data['total_copies'] ?? 1));
        $availableCopies = isset($data['available_copies']) && $data['available_copies'] !== null && $data['available_copies'] !== ''
            ? (int) $data['available_copies']
            : $totalCopies;

        $data['total_copies'] = $totalCopies;
        $data['available_copies'] = min($availableCopies, $totalCopies);
        $data['status'] = $data['status'] ?? 'available';

        return $data;
    }
}
