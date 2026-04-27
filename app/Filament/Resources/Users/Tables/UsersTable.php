<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use STS\FilamentImpersonate\Actions\Impersonate;

final class UsersTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://www.gravatar.com/avatar/'.md5(mb_strtolower(mb_trim($record->email))).'?d=mp&r=g&s=250')
                    ->size(40),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('role')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('primary')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('roles.name')
                    ->label('Permissions')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (User $record): bool => $record->email_verified_at !== null),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(UserRole::class)
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('email_verified')
                    ->label('Email Verified')
                    ->placeholder('All users')
                    ->trueLabel('Verified only')
                    ->falseLabel('Unverified only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                        blank: fn ($query) => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->deferFilters(false)
            ->deferColumnManager(false)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                ActionGroup::make([
                    Impersonate::make()
                        ->visible(function (User $record): bool {
                            /** @var User|null $currentUser */
                            $currentUser = Auth::user();

                            return $currentUser?->hasHigherAuthorityThan($record) ?? false;
                        }),
                    Action::make('send_password_reset')
                        ->label('Reset Password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Send Password Reset Email')
                        ->modalDescription(fn (User $record): string => "Send a password reset link to {$record->email}?")
                        ->action(function (User $record): void {
                            Password::sendResetLink(['email' => $record->email]);
                            Notification::make()
                                ->title('Password Reset Sent')
                                ->body("Password reset link sent to {$record->email}")
                                ->success()
                                ->send();
                        }),
                    Action::make('change_password')
                        ->label('Change Password')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->modalHeading(fn (User $record): string => "Change Password for {$record->name}")
                        ->modalDescription('Set a new password directly. The user will need to use this new password to log in.')
                        ->form([
                            \Filament\Forms\Components\TextInput::make('new_password')
                                ->label('New Password')
                                ->password()
                                ->revealable()
                                ->required()
                                ->minLength(8)
                                ->confirmed(),
                            \Filament\Forms\Components\TextInput::make('new_password_confirmation')
                                ->label('Confirm Password')
                                ->password()
                                ->revealable()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data): void {
                            $record->password = Hash::make($data['new_password']);
                            $record->save();
                            Notification::make()
                                ->title('Password Changed')
                                ->body("Password updated for {$record->name}")
                                ->success()
                                ->send();
                        }),
                    Action::make('verify_email')
                        ->label('Verify Email')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (User $record): bool => $record->email_verified_at === null)
                        ->requiresConfirmation()
                        ->modalHeading('Mark Email as Verified')
                        ->modalDescription(fn (User $record): string => "Mark {$record->email} as verified?")
                        ->action(function (User $record): void {
                            $record->email_verified_at = now();
                            $record->save();
                            Notification::make()
                                ->title('Email Verified')
                                ->body("Email verified for {$record->name}")
                                ->success()
                                ->send();
                        }),
                    Action::make('send_notification')
                        ->label('Send Notification')
                        ->icon('heroicon-o-bell')
                        ->color('info')
                        ->modalHeading(fn (User $record): string => "Send Notification to {$record->name}")
                        ->form([
                            \Filament\Forms\Components\TextInput::make('title')
                                ->label('Notification Title')
                                ->required()
                                ->maxLength(100),
                            \Filament\Forms\Components\Textarea::make('message')
                                ->label('Message')
                                ->required()
                                ->rows(3),
                            \Filament\Forms\Components\Select::make('type')
                                ->label('Type')
                                ->options([
                                    'info' => 'Information',
                                    'success' => 'Success',
                                    'warning' => 'Warning',
                                    'error' => 'Error',
                                ])
                                ->default('info')
                                ->required(),
                        ])
                        ->action(function (User $record, array $data): void {
                            $record->notify(new \App\Notifications\TestDatabaseNotification(
                                title: $data['title'],
                                message: $data['message'],
                                icon: 'heroicon-o-bell',
                                type: $data['type'],
                            ));
                            Notification::make()
                                ->title('Notification Sent')
                                ->body("Notification sent to {$record->name}")
                                ->success()
                                ->send();
                        }),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            // ->denseSpacing()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
    }
}
