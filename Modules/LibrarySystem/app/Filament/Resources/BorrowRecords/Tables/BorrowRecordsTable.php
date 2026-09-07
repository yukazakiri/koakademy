<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Services\LibraryBorrowStockService;

final class BorrowRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('book.title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(30),
                TextColumn::make('book.author.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Borrower')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => fn ($state, $record) => $state === 'borrowed' && ! $record->isOverdue(),
                        'danger' => fn ($state, $record) => $state === 'borrowed' && $record->isOverdue(),
                        'success' => 'returned',
                        'danger' => 'lost',
                    ]),
                TextColumn::make('borrowed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('returned_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('fine_amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->getStateUsing(fn (BorrowRecord $record): string => $record->isOverdue() ? (string) $record->days_overdue : '-'
                    )
                    ->color(fn ($state) => $state !== '-' ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'borrowed' => 'Borrowed',
                        'returned' => 'Returned',
                        'lost' => 'Lost',
                    ]),
                Filter::make('overdue')
                    ->query(fn ($query) => $query->where('status', 'borrowed')
                        ->where('due_date', '<', now()))
                    ->label('Overdue Books'),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->after(fn (BorrowRecord $record) => app(LibraryBorrowStockService::class)->recordDeleted($record)),
                RestoreAction::make()
                    ->after(fn (BorrowRecord $record) => app(LibraryBorrowStockService::class)->recordRestored($record)),
                ForceDeleteAction::make()
                    ->after(fn (BorrowRecord $record) => app(LibraryBorrowStockService::class)->recordDeleted($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (Collection $records): void {
                            $service = app(LibraryBorrowStockService::class);
                            foreach ($records as $record) {
                                $service->recordDeleted($record);
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->after(function (Collection $records): void {
                            $service = app(LibraryBorrowStockService::class);
                            foreach ($records as $record) {
                                $service->recordDeleted($record);
                            }
                        }),
                    RestoreBulkAction::make()
                        ->after(function (Collection $records): void {
                            $service = app(LibraryBorrowStockService::class);
                            foreach ($records as $record) {
                                $service->recordRestored($record);
                            }
                        }),
                ]),
            ])
            ->defaultSort('borrowed_at', 'desc');
    }
}
