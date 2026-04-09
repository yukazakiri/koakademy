<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $recordTitleAttribute = 'reminder_type';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('reminder_type')
                    ->label('Reminder Type')
                    ->required()
                    ->options([
                        'email' => 'Email',
                        'push' => 'Push Notification',
                        'sms' => 'SMS',
                        'in_app' => 'In-App Notification',
                    ])
                    ->default('email'),

                TextInput::make('minutes_before')
                    ->label('Minutes Before Event')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(60)
                    ->helperText('How many minutes before the event to send the reminder'),

                DateTimePicker::make('scheduled_at')
                    ->label('Scheduled At')
                    ->required()
                    ->native(false)
                    ->displayFormat('M j, Y g:i A'),

                Textarea::make('message')
                    ->label('Custom Message')
                    ->rows(3)
                    ->placeholder('Leave empty to use default message'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reminder_type')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reminder_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'email' => 'primary',
                        'push' => 'success',
                        'sms' => 'warning',
                        'in_app' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_app' => 'In-App',
                        default => mb_strtoupper($state),
                    }),

                TextColumn::make('minutes_before')
                    ->label('Minutes Before')
                    ->numeric()
                    ->suffix(' min'),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Not sent')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('retry_count')
                    ->label('Retries')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('scheduled_at', 'asc');
    }
}
