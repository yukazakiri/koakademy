<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\EventRsvp;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RsvpsRelationManager extends RelationManager
{
    protected static string $relationship = 'rsvps';

    protected static ?string $recordTitleAttribute = 'user.name';

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

                Select::make('response')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'attending' => 'Attending',
                        'not_attending' => 'Not Attending',
                        'maybe' => 'Maybe',
                    ])
                    ->default('pending'),

                TextInput::make('guest_count')
                    ->label('Number of Guests')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),

                Textarea::make('dietary_requirements')
                    ->label('Dietary Requirements')
                    ->rows(2),

                Textarea::make('special_requests')
                    ->label('Special Requests')
                    ->rows(2),

                Toggle::make('checked_in')
                    ->label('Checked In'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('response')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'attending' => 'success',
                        'not_attending' => 'danger',
                        'maybe' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'not_attending' => 'Not Attending',
                        default => ucfirst($state),
                    }),

                TextColumn::make('guest_count')
                    ->label('Guests')
                    ->numeric(),

                TextColumn::make('total_people')
                    ->label('Total People')
                    ->state(fn (EventRsvp $record): int => $record->total_people),

                IconColumn::make('checked_in')
                    ->label('Checked In')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheck)
                    ->falseIcon(Heroicon::OutlinedXMark),

                TextColumn::make('responded_at')
                    ->label('Responded')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Not responded')
                    ->sortable(),

                TextColumn::make('checked_in_at')
                    ->label('Check-in Time')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Not checked in')
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
            ->defaultSort('responded_at', 'desc');
    }
}
