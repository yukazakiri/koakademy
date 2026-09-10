<?php

declare(strict_types=1);

namespace App\Filament\Resources\CodeAuthorities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CodeAuthoritiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['codes', 'codes as active_codes_count' => fn (Builder $inner): Builder => $inner->where('is_active', true)]))
            ->defaultSort('name')
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->label('Authority')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record): ?string => $record->description
                        ? (mb_strlen((string) $record->description) > 80
                            ? mb_substr((string) $record->description, 0, 80).'...'
                            : $record->description)
                        : null),
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('curriculum_framework')
                    ->label('Framework')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('active_codes_count')
                    ->label('Codes')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All authorities')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(fn (): array => \App\Models\CodeAuthority::query()
                        ->whereNotNull('country_code')
                        ->distinct()
                        ->pluck('country_code', 'country_code')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
