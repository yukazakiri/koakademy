<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class RecentStudentsTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Recent Students';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    private static ?string $description = 'Latest students added to the system';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('full_name')
                    ->label('Student Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn (Student $record): string => (string) ($record->student_id ?? '')),

                TextColumn::make('course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'BSIT') => 'info',
                        str_contains($state, 'BSHM') => 'success',
                        str_contains($state, 'BSBA') => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('formatted_academic_year')
                    ->label('Year Level')
                    ->badge()
                    ->color(fn (Student $record): string => match ($record->academic_year) {
                        1 => 'info',
                        2 => 'success',
                        3 => 'warning',
                        4 => 'danger',
                        5 => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->since()
                    ->description(fn (Student $record): string => $record->created_at->format('M j, Y g:i A')),
            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Student $record): string => StudentResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    protected function getTableQuery(): Builder
    {
        return Student::query()
            ->with(['course'])
            ->latest('created_at');
    }

    protected function getTableEmptyStateHeading(): string
    {
        return 'No students found';
    }

    protected function getTableEmptyStateDescription(): string
    {
        return 'There are no students in the system yet.';
    }

    private function isTableSearchable(): bool
    {
        return true;
    }
}
