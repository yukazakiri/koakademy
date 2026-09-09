<?php

declare(strict_types=1);

namespace App\Filament\Resources\CodeAuthorities;

use App\Enums\UserRole;
use App\Filament\Resources\CodeAuthorities\Pages\CreateCodeAuthority;
use App\Filament\Resources\CodeAuthorities\Pages\EditCodeAuthority;
use App\Filament\Resources\CodeAuthorities\Pages\ListCodeAuthorities;
use App\Filament\Resources\CodeAuthorities\Schemas\CodeAuthorityForm;
use App\Filament\Resources\CodeAuthorities\Tables\CodeAuthoritiesTable;
use App\Models\CodeAuthority;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class CodeAuthorityResource extends Resource
{
    #[Override]
    protected static ?string $model = CodeAuthority::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    #[Override]
    protected static ?int $navigationSort = 2;

    #[Override]
    protected static ?string $navigationLabel = 'Code Authorities';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if ($user?->role->isAdministrative()) {
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
        return CodeAuthorityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CodeAuthoritiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCodeAuthorities::route('/'),
            'create' => CreateCodeAuthority::route('/create'),
            'edit' => EditCodeAuthority::route('/{record}/edit'),
        ];
    }
}
