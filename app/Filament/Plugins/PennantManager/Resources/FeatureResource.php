<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Resources;

use App\Filament\Plugins\PennantManager\Models\Feature;
use App\Filament\Plugins\PennantManager\Resources\FeatureResource\Pages\ListFeatures;
use App\Services\FeatureManagementService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only legacy diagnostic. All writes belong to the portal service. */
final class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-flag';

    public static function getNavigationLabel(): string
    {
        return 'Feature access (legacy)';
    }

    public static function canAccess(): bool
    {
        return FeatureManagementService::canManage(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope', '__laravel_null');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('value')->label('Legacy value (read only)'),
        ])->recordActions([])->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFeatures::route('/')];
    }
}
