<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes;

use App\Enums\UserRole;
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
        $user = auth()->user();

        if ($user?->role->isAdministrative()) {
            return true;
        }

        if ($user?->role->isFaculty()) {
            return true;
        }

        return (bool) $user?->role->isStudentServices();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if ($user?->role->isAdministrative()) {
            return true;
        }

        return $user?->role === UserRole::Registrar;
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if ($user?->role->isAdministrative()) {
            return true;
        }

        return $user?->role === UserRole::Registrar;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->role->isAdministrative() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->role->isAdministrative() ?? false;
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
