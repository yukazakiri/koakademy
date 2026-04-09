<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\RelationManagers;

use App\Enums\ClassPostType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class ClassPostsRelationManager extends RelationManager
{
    protected static string $relationship = 'classPosts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options(collect(ClassPostType::cases())->mapWithKeys(fn ($case): array => [$case->value => $case->name]))
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('attachments')
                    ->multiple()
                    ->directory('class-post-attachments')
                    ->reorderable()
                    ->appendFiles()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
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
            ]);
    }
}
