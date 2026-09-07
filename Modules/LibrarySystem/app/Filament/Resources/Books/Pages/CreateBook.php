<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;

final class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
