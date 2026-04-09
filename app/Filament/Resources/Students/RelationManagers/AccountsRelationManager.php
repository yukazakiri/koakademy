<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'Account';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->default('account'),
                TextInput::make('parent_id')
                    ->numeric(),
                TextInput::make('name')
                    ->default(null),
                TextInput::make('username')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->default(null),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                TextInput::make('loginby')
                    ->default('email'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('lang')
                    ->default(null),
                TextInput::make('password')
                    ->password()
                    ->default(null),
                Textarea::make('two_factor_secret')
                    ->columnSpanFull(),
                Textarea::make('two_factor_recovery_codes')
                    ->columnSpanFull(),
                DateTimePicker::make('two_factor_confirmed_at'),
                TextInput::make('otp_code')
                    ->default(null),
                DateTimePicker::make('otp_activated_at'),
                DateTimePicker::make('last_login'),
                Textarea::make('agent')
                    ->columnSpanFull(),
                TextInput::make('host')
                    ->default(null),
                Toggle::make('is_login'),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_notification_active')
                    ->required(),
                TextInput::make('person_id'),
                TextInput::make('profile_photo_url')
                    ->default(null),
                TextInput::make('email_verified_at')
                    ->email()
                    ->default(null),
                TextInput::make('role')
                    ->default(null),
                TextInput::make('avatar')
                    ->default(null),
                TextInput::make('person_type')
                    ->default(null),
                TextInput::make('profile_photo_path')
                    ->default(null),
                TextInput::make('current_team_id')
                    ->numeric(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type'),
                TextEntry::make('parent_id')
                    ->numeric(),
                TextEntry::make('name'),
                TextEntry::make('username'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('phone'),
                TextEntry::make('loginby'),
                TextEntry::make('lang'),
                TextEntry::make('two_factor_confirmed_at')
                    ->dateTime(),
                TextEntry::make('otp_code'),
                TextEntry::make('otp_activated_at')
                    ->dateTime(),
                TextEntry::make('last_login')
                    ->dateTime(),
                TextEntry::make('host'),
                IconEntry::make('is_login')
                    ->boolean(),
                IconEntry::make('is_active')
                    ->boolean(),
                IconEntry::make('is_notification_active')
                    ->boolean(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('person_id'),
                TextEntry::make('profile_photo_url'),
                TextEntry::make('email_verified_at'),
                TextEntry::make('role'),
                TextEntry::make('avatar'),
                TextEntry::make('person_type'),
                TextEntry::make('profile_photo_path'),
                TextEntry::make('current_team_id')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('parent_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('loginby')
                    ->searchable(),
                TextColumn::make('lang')
                    ->searchable(),
                TextColumn::make('two_factor_confirmed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('otp_code')
                    ->searchable(),
                TextColumn::make('otp_activated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_login')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('host')
                    ->searchable(),
                IconColumn::make('is_login')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_notification_active')
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('person_id')
                    ->searchable(),
                TextColumn::make('profile_photo_url')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->searchable(),
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('avatar')
                    ->searchable(),
                TextColumn::make('person_type')
                    ->searchable(),
                TextColumn::make('profile_photo_path')
                    ->searchable(),
                TextColumn::make('current_team_id')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $builder) => $builder
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
