<?php

declare(strict_types=1);

namespace App\Filament\Resources\SanityContents;

use App\Filament\Resources\SanityContents\Pages\CreateSanityContent;
use App\Filament\Resources\SanityContents\Pages\EditSanityContent;
use App\Filament\Resources\SanityContents\Pages\ListSanityContents;
use App\Filament\Resources\SanityContents\Schemas\SanityContentForm;
use App\Filament\Resources\SanityContents\Tables\SanityContentsTable;
use App\Models\SanityContent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class SanityContentResource extends Resource
{
    protected static ?string $model = SanityContent::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'CMS Content';

    public static function form(Schema $schema): Schema
    {
        return SanityContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SanityContentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSanityContents::route('/'),
            'create' => CreateSanityContent::route('/create'),
            'edit' => EditSanityContent::route('/{record}/edit'),
        ];
    }
}
