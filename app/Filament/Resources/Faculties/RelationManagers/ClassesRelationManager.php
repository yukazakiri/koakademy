<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\RelationManagers;

use App\Services\ClassAssignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ClassesRelationManager extends RelationManager
{
    protected static string $relationship = 'classes';

    protected static ?string $title = 'Assigned Classes';

    protected static ?string $recordTitleAttribute = 'subject_code';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject_code')
            ->columns([
                TextColumn::make('subject_code')
                    ->label('Subject Code')
                    ->searchable(),
                TextColumn::make('subject_title')
                    ->label('Subject Title')
                    ->getStateUsing(fn ($record) => $record->subject_title ?? 'N/A'),
                TextColumn::make('section')
                    ->label('Section'),
                TextColumn::make('school_year')
                    ->label('School Year'),
                TextColumn::make('semester')
                    ->label('Semester'),
            ])
            ->headerActions([
                Action::make('assignNewClasses')
                    ->label('Assign New Classes')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Select::make('class_ids')
                            ->label('Select Classes to Assign')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => app(ClassAssignmentService::class)->getUnassignedClassOptions()),
                    ])
                    ->action(function (array $data): void {
                        if (! empty($data['class_ids'])) {
                            $count = app(ClassAssignmentService::class)->assignClassesToFaculty(
                                $data['class_ids'],
                                (string) $this->getOwnerRecord()->id
                            );

                            Notification::make()
                                ->title('Classes Assigned Successfully')
                                ->body(sprintf('Assigned %d class(es) to %s', $count, $this->getOwnerRecord()->full_name))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => route('filament.admin.resources.classes.view', $record)),
            ]);
    }
}
