<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts;

use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Accounts\Schemas\AccountInfolist;
use App\Filament\Resources\Accounts\Tables\AccountsTable;
use App\Models\Account;
use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Portal Accounts';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Account::class) ?? false;
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
        return auth()->user()?->can('deleteAny', Account::class) ?? false;
    }

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return AccountInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
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
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewAccount::class,
            EditAccount::class,
        ]);
    }
}
