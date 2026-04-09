<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class RecentEnrollmentsTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Recent Enrollment Applications';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    private static ?string $description = 'Latest student enrollment applications and their processing status';

    public function table(Table $table): Table
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $labels = $pipeline->getStatusLabels();

        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student Name')
                    ->searchable(['students.first_name', 'students.last_name'])
                    ->sortable()
                    ->description(fn (StudentEnrollment $record): string => (string) ($record->student->student_id ?? '')),

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

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (StudentEnrollment $record): string => match (true) {
                        $record->trashed() => 'success',
                        $pipeline->isPending($record->status) => 'gray',
                        $pipeline->isDepartmentVerified($record->status) => 'info',
                        ! $record->trashed() && $pipeline->isCashierVerified($record->status) => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (StudentEnrollment $record): string => match (true) {
                        $record->trashed() => 'Enrolled (Receipt)',
                        ! $record->trashed() && $pipeline->isCashierVerified($record->status) => 'Enrolled (No Receipt)',
                        $pipeline->isPending($record->status) => 'Processing',
                        $pipeline->isDepartmentVerified($record->status) => 'Academic Review Complete',
                        default => $labels[(string) $record->status] ?? (string) $record->status,
                    }),

                TextColumn::make('semester')
                    ->label('Sem')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('school_year')
                    ->label('S.Y.')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->since()
                    ->description(fn (StudentEnrollment $record): string => $record->created_at->format('M j, Y g:i A')
                    ),
            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (StudentEnrollment $record): string => StudentEnrollmentResource::getUrl('view', ['record' => $record])
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    protected function getTableQuery(): Builder
    {
        return StudentEnrollment::query()
            ->currentAcademicPeriod()
            ->withTrashed()
            ->with(['student', 'course'])
            ->latest('created_at');
    }

    protected function getTableEmptyStateHeading(): string
    {
        return 'No enrollment applications found';
    }

    protected function getTableEmptyStateDescription(): string
    {
        return 'There are no recent enrollment applications to display for the current academic period.';
    }

    private function isTableSearchable(): bool
    {
        return true;
    }
}
