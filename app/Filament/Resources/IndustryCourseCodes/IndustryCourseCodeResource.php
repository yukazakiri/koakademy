<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes;

use App\Filament\Resources\IndustryCourseCodes\Pages\CreateIndustryCourseCode;
use App\Filament\Resources\IndustryCourseCodes\Pages\EditIndustryCourseCode;
use App\Filament\Resources\IndustryCourseCodes\Pages\ListIndustryCourseCodes;
use App\Filament\Resources\IndustryCourseCodes\Schemas\IndustryCourseCodeForm;
use App\Filament\Resources\IndustryCourseCodes\Tables\IndustryCourseCodesTable;
use App\Models\IndustryCourseCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class IndustryCourseCodeResource extends Resource
{
    #[Override]
    protected static ?string $model = IndustryCourseCode::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'code';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static ?string $navigationLabel = 'Industry Course Codes';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', IndustryCourseCode::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', IndustryCourseCode::class) ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('deleteAny', IndustryCourseCode::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return IndustryCourseCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndustryCourseCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndustryCourseCodes::route('/'),
            'create' => CreateIndustryCourseCode::route('/create'),
            'edit' => EditIndustryCourseCode::route('/{record}/edit'),
        ];
    }
}
