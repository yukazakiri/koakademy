<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class IndustryCourseCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['authority:id,name,key', 'school:id,name'])->withCount('courses'))
            ->defaultSort('code')
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),
                TextColumn::make('title')
                    ->label('Official title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('category_name')
                    ->label('Category / Discipline')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn ($record): ?string => $record->category_code ? "Code: {$record->category_code}" : null)
                    ->toggleable(),
                TextColumn::make('authority.name')
                    ->label('Authority')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('courses_count')
                    ->label('Programs')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'import' ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('code_authority_id')
                    ->label('Authority')
                    ->relationship('authority', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options([
                        'import' => 'Spreadsheet import',
                        'manual' => 'Manually registered',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All codes')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
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
