<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryBorrowingStatus: string
{
    case Borrowed = 'borrowed';
    case Returned = 'returned';
    case Overdue = 'overdue';
    case Lost = 'lost';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
