<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Categories\Schemas;

use Filament\Schemas\Schema;

final class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
