<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryHistoryEventType: string
{
    case ItemCreated = 'item_created';
    case ItemUpdated = 'item_updated';
    case LocationMoved = 'location_moved';
    case StockRebalanced = 'stock_rebalanced';
    case BorrowingCreated = 'borrowing_created';
    case BorrowingUpdated = 'borrowing_updated';
    case BorrowingReverted = 'borrowing_reverted';
    case BorrowingReassigned = 'borrowing_reassigned';
    case BorrowingDeleted = 'borrowing_deleted';
}
