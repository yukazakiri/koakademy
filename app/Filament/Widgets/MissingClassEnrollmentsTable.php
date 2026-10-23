<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Override;

/**
 * Table widget showing Student Enrollments where some subjects
 * are not yet enrolled in their corresponding classes.
 */
final class MissingClassEnrollmentsTable extends BaseWidget
{
    private const int ENROLLMENT_CHUNK_SIZE = 500;

    private const int ID_FILTER_CHUNK_SIZE = 500;

    #[Override]
    protected static ?int $sort = 5;

    #[Override]
    protected static ?string $heading = 'Enrollments with Missing Class Assignments';

    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected static bool $isLazy = false;

    // Cache for missing classes to avoid repeated calculations
    private array $missingClassesCache = [];

    // Cache for enrollment IDs with missing classes
    private ?array $enrollmentIdsWithMissing = null;

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

                TextColumn::make('missing_subjects')
                    ->label('Missing Classes')
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(function (StudentEnrollment $record): string {
                        $missing = $this->getMissingClasses($record);
                        $count = $missing->pluck('subject_code')->unique()->count();

                        return (string) $count;
                    })
                    ->description(function (StudentEnrollment $record): string {
                        $missing = $this->getMissingClasses($record);
                        $subjectCodes = $missing->pluck('subject_code')->unique()->take(3)->values();
                        $more = $missing->pluck('subject_code')->unique()->count() - 3;
                        $description = $subjectCodes->implode(', ');
                        if ($more > 0) {
                            $description .= " +{$more} more";
                        }

                        return $description;
                    }),

                TextColumn::make('subjects_enrolled_count')
                    ->label('Total Subjects')
                    ->getStateUsing(fn (StudentEnrollment $record): int => $record->subjectsEnrolled->count()),

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
                        $record->trashed() => 'Enrolled',
                        $pipeline->isPending($record->status) => 'Pending',
                        $pipeline->isDepartmentVerified($record->status) => 'Verified by Head',
                        default => $labels[(string) $record->status] ?? (string) $record->status,
                    }),

                TextColumn::make('school_year')
                    ->label('S.Y.')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->relationship('course', 'code')
                    ->label('Course')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('enroll_all')
                    ->label('Enroll in All Missing')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Auto-Enroll in Missing Classes')
                    ->modalDescription(fn (StudentEnrollment $record): string => sprintf(
                        'This will enroll %s in all available classes for their missing subjects. Continue?',
                        $record->student->full_name ?? 'the student'
                    ))
                    ->action(function (StudentEnrollment $record): void {
                        $this->enrollInMissingClasses($record);
                    }),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn (StudentEnrollment $record): string => StudentEnrollmentResource::getUrl('view', ['record' => $record]))
                    ->tooltip('View enrollment details'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->poll('30s');
    }

    protected function getTableQuery(): Builder
    {
        // Get IDs of enrollments with missing classes (computed once)
        $enrollmentIds = $this->getEnrollmentIdsWithMissingClasses();

        $query = StudentEnrollment::query()
            ->withTrashed()
            // Loading the relations here keeps the accessor query-free for
            // the rows that are actually rendered on the current page.
            ->with(['student.classEnrollments.class', 'course', 'subjectsEnrolled.subject'])
            ->latest('created_at');

        if ($enrollmentIds === []) {
            return $query->whereRaw('1 = 0');
        }

        // A single whereIn with tens of thousands of IDs exceeds database
        // placeholder limits, so the filter is split into chunks.
        return $query->where(function (Builder $query) use ($enrollmentIds): void {
            foreach (array_chunk($enrollmentIds, self::ID_FILTER_CHUNK_SIZE) as $chunk) {
                $query->orWhereIn('id', $chunk);
            }
        });
    }

    protected function getTableEmptyStateHeading(): string
    {
        return 'All enrollments have complete class assignments';
    }

    protected function getTableEmptyStateDescription(): string
    {
        return 'There are no student enrollments with missing class assignments for the current academic period.';
    }

    protected function getTableEmptyStateIcon(): string
    {
        return 'heroicon-o-check-circle';
    }

    /**
     * Pre-compute and cache enrollment IDs that have missing classes.
     *
     * Enrollments are processed in chunks so a period with thousands of
     * records does not hydrate every enrollment, subject and class offering
     * at once. Missing-class existence is derived from the already-loaded
     * relations, so this stays at a constant number of statements per chunk.
     */
    private function getEnrollmentIdsWithMissingClasses(): array
    {
        if ($this->enrollmentIdsWithMissing !== null) {
            return $this->enrollmentIdsWithMissing;
        }

        $enrollmentIds = [];

        StudentEnrollment::query()
            ->currentAcademicPeriod()
            ->withTrashed()
            ->whereHas('subjectsEnrolled')
            ->with(['subjectsEnrolled.subject', 'student.classEnrollments.class'])
            ->chunkById(self::ENROLLMENT_CHUNK_SIZE, function ($enrollments) use (&$enrollmentIds): void {
                foreach ($enrollments as $enrollment) {
                    if ($enrollment->hasMissingClasses()) {
                        $enrollmentIds[] = $enrollment->id;
                    }
                }
            });

        return $this->enrollmentIdsWithMissing = $enrollmentIds;
    }

    /**
     * Get missing classes for a record (with caching)
     */
    private function getMissingClasses(StudentEnrollment $record): Collection
    {
        if (! isset($this->missingClassesCache[$record->id])) {
            $this->missingClassesCache[$record->id] = $record->missing_classes_for_infolist;
        }

        return $this->missingClassesCache[$record->id];
    }

    /**
     * Enroll student in all available missing classes
     */
    private function enrollInMissingClasses(StudentEnrollment $record): void
    {
        $missingClasses = $this->getMissingClasses($record);
        $student = $record->student;
        $successCount = 0;
        $errorCount = 0;
        $alreadyEnrolledCount = 0;

        foreach ($missingClasses as $classInfo) {
            try {
                $classId = $classInfo->class_id ?? null;

                if (! $classId) {
                    continue;
                }

                // Check if already enrolled
                $existingEnrollment = ClassEnrollment::where('class_id', $classId)
                    ->where('student_id', $student->id)
                    ->first();

                if ($existingEnrollment) {
                    $alreadyEnrolledCount++;

                    continue;
                }

                // Check if class is full
                $class = Classes::find($classId);
                if ($class && $class->maximum_slots > 0) {
                    $enrolledCount = ClassEnrollment::where('class_id', $classId)->count();
                    if ($enrolledCount >= $class->maximum_slots) {
                        $errorCount++;

                        continue;
                    }
                }

                // Create enrollment
                app(\App\Services\ClassEnrollmentService::class)->enrollOnce(
                    (int) $student->id,
                    (int) $classId,
                    [
                        'status' => true,
                    ],
                );

                $successCount++;
            } catch (Exception) {
                $errorCount++;
            }
        }

        // Clear cache for this record
        unset($this->missingClassesCache[$record->id]);

        // Send notification
        if ($successCount > 0) {
            Notification::make()
                ->success()
                ->title('Enrollment Successful')
                ->body(sprintf('Enrolled in %d class(es).', $successCount))
                ->send();
        }

        if ($alreadyEnrolledCount > 0) {
            Notification::make()
                ->info()
                ->title('Already Enrolled')
                ->body(sprintf('%d class(es) were already enrolled.', $alreadyEnrolledCount))
                ->send();
        }

        if ($errorCount > 0) {
            Notification::make()
                ->warning()
                ->title('Some Classes Failed')
                ->body(sprintf('%d class(es) could not be enrolled (full or error).', $errorCount))
                ->send();
        }

        if ($successCount === 0 && $errorCount === 0 && $alreadyEnrolledCount === 0) {
            Notification::make()
                ->info()
                ->title('No Classes Available')
                ->body('No available classes found for the missing subjects.')
                ->send();
        }
    }
}
