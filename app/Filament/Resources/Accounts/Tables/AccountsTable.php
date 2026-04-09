<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Tables;

use App\Models\Account;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AccountsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied to clipboard')
                    ->copyMessageDuration(1500),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not set'),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'faculty' => 'success',
                        'staff' => 'warning',
                        'student' => 'info',
                        'guest' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),

                TextColumn::make('person_type')
                    ->label('Person Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        \App\Models\Student::class => 'info',
                        \App\Models\Faculty::class => 'success',
                        \App\Models\ShsStudent::class => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        \App\Models\Student::class => 'Student',
                        \App\Models\Faculty::class => 'Faculty',
                        \App\Models\ShsStudent::class => 'SHS Student',
                        default => 'Unknown',
                    })
                    ->sortable(),

                TextColumn::make('last_login')
                    ->label('Last Login')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Administrator',
                        'student' => 'Student',
                        'faculty' => 'Faculty',
                        'staff' => 'Staff',
                        'guest' => 'Guest',
                    ]),

                SelectFilter::make('person_type')
                    ->label('Person Type')
                    ->options([
                        \App\Models\Student::class => 'Student',
                        \App\Models\Faculty::class => 'Faculty',
                        \App\Models\ShsStudent::class => 'SHS Student',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    Action::make('impersonate')
                        ->label('Impersonate')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('warning')
                        ->url(fn (Account $record): string => route('filament.portal.auth.login', [
                            'email' => $record->email,
                            '_token' => csrf_token(),
                        ]))
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => auth()->user()?->is_admin ?? false),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No accounts found')
            ->emptyStateDescription('No accounts have been created yet.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create Account')
                    ->url(fn (): string => route('filament.admin.resources.accounts.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getTableQuery(): Builder
    {
        return Account::query()->withTrashed();
    }
}
