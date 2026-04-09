<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AccounsRelationManager extends RelationManager
{
    protected static string $relationship = 'Accounts';

    protected static ?string $recordTitleAttribute = 'Accounts';

    public function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->label('Email Address'),
                TextInput::make('password')
                    ->required()
                    ->password()
                    ->maxLength(255)
                    ->label('Password'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->label('user Name'),
                Select::make('role')
                    ->required()
                    ->options([
                        'student' => 'Student',
                        'faculty' => 'Faculty',
                        'admin' => 'Admin',
                    ])
                    ->label('Role'),
                Toggle::make('two_factor_auth')
                    ->label('Enable Two-Factor Authentication'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Accounts')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('role'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
