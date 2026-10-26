<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Exceptions\FinancialAuditLockException;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasAcademicPeriodScope;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use Carbon\Carbon;
use Deprecated;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Override;

/**
 * Class StudentEnrollment
 *
 * @property-read Collection<int, AdditionalFee> $additionalFees
 * @property-read int|null $additional_fees_count
 * @property-read Course|null $course
 * @property-read string $assessment_path
 * @property-read string $assessment_url
 * @property-read string $certificate_path
 * @property-read string $certificate_url
 * @property-read string $student_name
 * @property-read Collection<int, resource> $resources
 * @property-read int|null $resources_count
 * @property-read Student|null $student
 * @property-read StudentTuition|null $studentTuition
 * @property-read Collection<int, SubjectEnrollment> $subjectsEnrolled
 * @property-read int|null $subjects_enrolled_count
 *
 * @method static Builder<static>|StudentEnrollment currentAcademicPeriod()
 * @method static Builder<static>|StudentEnrollment forAcademicPeriod(string $schoolYear, int $semester)
 * @method static Builder<static>|StudentEnrollment newModelQuery()
 * @method static Builder<static>|StudentEnrollment newQuery()
 * @method static Builder<static>|StudentEnrollment onlyTrashed()
 * @method static Builder<static>|StudentEnrollment query()
 * @method static Builder<static>|StudentEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudentEnrollment withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class StudentEnrollment extends Model
{
    use BelongsToSchool;
    use HasAcademicPeriodScope;
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    public const string WorkflowRuntimeLegacy = 'legacy';

    public const string WorkflowRuntimePolicyV1 = 'policy_v1';

    #[Override]
    protected $table = 'student_enrollment';

    #[Override]
    protected $fillable = [
        'student_id',
        'course_id',
        'status',
        'semester',
        'academic_year',
        'school_year',
        'downpayment',
        'remarks',
        'school_id',
        'enrollment_policy_snapshot_id',
        'current_step_key',
        'terminal_outcome',
        'deduplication_key',
        'submission_channel',
        'submission_idempotency_key',
        'intake_category',
    ];

    #[Override]
    protected $casts = ['deleted_at' => 'datetime'];

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        if (config('scout.driver') === 'database') {
            return [
                'id' => $this->id,
                'student_id' => $this->student_id,
                'course_id' => $this->course_id,
                'status' => $this->status,
                'academic_year' => (string) $this->academic_year,
                'school_year' => $this->school_year,
                'semester' => (string) $this->semester,
            ];
        }

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student_number' => $this->student?->student_id ?? $this->student_id,
            'student_name' => $this->student?->full_name ?? '',
            'course_code' => $this->course?->code ?? '',
            'course_id' => $this->course_id,
            'academic_year' => (string) $this->academic_year,
            'school_year' => $this->school_year,
            'semester' => (string) $this->semester,
            'status' => $this->status,
        ];
    }

    // public function signature()
    // {
    //     return $this->morphOne(EnrollmentSignature::class, 'enrollment');
    // }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id')
            ->withoutGlobalScopes()
            ->withDefault();
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function subjectsEnrolled()
    {
        return $this->hasMany(SubjectEnrollment::class, 'enrollment_id', 'id');
    }

    public function studentTuition()
    {
        return $this->hasOne(StudentTuition::class, 'enrollment_id', 'id');
    }

    public function resources()
    {
        return $this->morphMany(Resource::class, 'resourceable');
    }

    public function additionalFees()
    {
        return $this->hasMany(AdditionalFee::class, 'enrollment_id');
    }

    public function financialDocuments(): HasMany
    {
        return $this->hasMany(FinancialDocumentIssuance::class, 'enrollment_id');
    }

    public function latestInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FinancialDocumentIssuance::class, 'enrollment_id')
            ->where('type', \App\Enums\FinancialDocumentType::Invoice->value)
            ->latestOfMany('issued_at');
    }

    /** @return BelongsTo<EnrollmentPolicySnapshot, $this> */
    public function policySnapshot(): BelongsTo
    {
        return $this->belongsTo(EnrollmentPolicySnapshot::class, 'enrollment_policy_snapshot_id');
    }

    /** @return HasMany<EnrollmentWorkflowEvent, $this> */
    public function workflowEvents(): HasMany
    {
        return $this->hasMany(EnrollmentWorkflowEvent::class, 'student_enrollment_id');
    }

    /** @return HasMany<EnrollmentRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(EnrollmentRequirement::class, 'student_enrollment_id');
    }

    public function assessmentRevisions(): HasMany
    {
        return $this->hasMany(AssessmentRevision::class, 'student_enrollment_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'student_enrollment_id');
    }

    public function hasFinancialRecords(): bool
    {
        return $this->studentTuition()->where(function ($query): void {
            $query->where('overall_tuition', '>', 0)
                ->orWhere('paid', '>', 0)
                ->orWhere('total_balance', '>', 0);
        })->exists()
            || $this->additionalFees()->exists()
            || $this->financialDocuments()->exists()
            || $this->studentTransactions()->exists()
            || $this->assessmentRevisions()->exists()
            || $this->paymentAllocations()->exists();
    }

    public function allowsConfiguredNoReceiptTransition(): bool
    {
        return $this->hasConfiguredTransitionAction(
            'enrollment.verify_payment',
            function (array $action): bool {
                if (($action['handler'] ?? null) !== 'enrollment.verify_payment') {
                    return false;
                }
                $configuration = is_array($action['configuration'] ?? null) ? $action['configuration'] : [];

                return ($configuration['receipt_mode'] ?? null) === 'none'
                    || ($configuration['allow_no_receipt'] ?? false) === true;
            },
        );
    }

    public function hasConfiguredTransitionAction(string $handler, ?callable $filter = null): bool
    {
        if ($this->workflow_runtime !== self::WorkflowRuntimePolicyV1) {
            return false;
        }

        $this->loadMissing('policySnapshot');
        $steps = collect(data_get($this->policySnapshot?->configuration, 'workflow.steps', []))->keyBy('key');
        $current = $steps->get($this->current_step_key);
        if (! is_array($current)) {
            return false;
        }

        return collect($current['transitions'] ?? [])->contains(function (array $transition) use ($steps, $handler, $filter): bool {
            $target = $steps->get($transition['to'] ?? '');
            if (! is_array($target)) {
                return false;
            }

            return collect($target['actions'] ?? [])->contains(
                fn (array $action): bool => ($action['handler'] ?? null) === $handler
                    && ($filter === null || $filter($action)),
            );
        });
    }

    /**
     * Get transactions for this specific enrollment period
     * Shows transactions from the current academic year based on school calendar
     */
    public function enrollmentTransactions()
    {
        return $this->hasMany(StudentTransaction::class, 'student_enrollment_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get transactions for this enrollment through the student (legacy method)
     */
    #[Deprecated(message: 'Use enrollmentTransactions() instead')]
    public function transactions()
    {
        return $this->enrollmentTransactions();
    }

    /**
     * Get student transactions for this enrollment through the student (legacy method)
     */
    #[Deprecated(message: 'Use enrollmentStudentTransactions() instead')]
    public function studentTransactions()
    {
        return $this->enrollmentStudentTransactions();
    }

    /**
     * Get enrolled classes as a collection for RepeatableEntry
     * This is an accessor for the infolist
     */
    public function getEnrolledClassesForInfolistAttribute(): Collection
    {
        $student = $this->student;

        if (! $student) {
            return $this->newCollection();
        }

        $period = $this->getCurrentAcademicPeriod();
        $schoolYearVariants = $this->school_year
            ? $this->resolveSchoolYearVariants($this->school_year)
            : $period['school_year_variants'];
        $semester = $this->semester ?: $period['semester'];

        // Get classes student is already enrolled in with relationships
        // Only include active enrollments (status = true), matching ClassEnrollmentsRelationManager pattern
        $mapped = $student->classEnrollments()
            ->with('class.faculty', 'class.schedules.room')
            ->where('status', true)
            ->whereHas('class', function ($query) use ($schoolYearVariants, $semester): void {
                $query->whereIn('school_year', $schoolYearVariants)
                    ->where('semester', $semester);
            })
            ->get()
            ->map(function ($classEnrollment) {
                // Format schedule
                $scheduleText = 'TBA';
                if ($classEnrollment->class->schedules->isNotEmpty()) {
                    $schedules = $classEnrollment->class->schedules
                        ->map(function ($schedule): string {
                            $day = $schedule->day_of_week;
                            // Handle both string and Carbon instances
                            $startTime = $schedule->start_time instanceof Carbon
                                ? $schedule->start_time->format('h:i A')
                                : date('h:i A', strtotime((string) $schedule->start_time));
                            $endTime = $schedule->end_time instanceof Carbon
                                ? $schedule->end_time->format('h:i A')
                                : date('h:i A', strtotime((string) $schedule->end_time));
                            $roomName = $schedule->room->name ?? 'TBA';

                            return "{$day} {$startTime}-{$endTime} ({$roomName})";
                        })
                        ->implode(', ');
                    $scheduleText = $schedules;
                }

                // Get room name (fallback)
                $roomName = $classEnrollment->class->room?->name ?? 'TBA';

                // Format grades - show enrollment status instead of N/A
                $grades = (object) [
                    'prelim' => $classEnrollment->prelim_grade,
                    'midterm' => $classEnrollment->midterm_grade,
                    'finals' => $classEnrollment->finals_grade,
                    'average' => $classEnrollment->total_average,
                    'enrolled' => true, // Flag to indicate student is enrolled
                ];

                return (object) [
                    'id' => $classEnrollment->id,
                    'class_id' => $classEnrollment->class_id,
                    'subject_code' => $classEnrollment->class->subject_code,
                    'subject_title' => $classEnrollment->class->subject_title,
                    'section' => $classEnrollment->class->section,
                    'faculty' => $classEnrollment->class->faculty->full_name ?? 'TBA',
                    'schedule' => $scheduleText,
                    'room' => $roomName,
                    'enrolled_at' => $classEnrollment->created_at,
                    'status' => $classEnrollment->status,
                    'grades' => $grades,
                ];
            });

        // Wrap in Eloquent Collection to ensure correct return type
        return $this->newCollection($mapped->all());
    }

    /**
     * Determine whether at least one enrolled subject has no class assignment.
     *
     * This is intentionally query-free once the caller has eager-loaded
     * `subjectsEnrolled.subject` and `student.classEnrollments.class`; the
     * widget uses it to filter a whole chunk of enrollments without hydrating
     * every matching class offering.
     */
    public function hasMissingClasses(): bool
    {
        $student = $this->student;

        if (! $student) {
            return false;
        }

        $period = $this->missingClassPeriod();

        return $this->missingSubjectEnrollments(
            $student,
            $period['school_year_variants'],
            $period['semester'],
        )->isNotEmpty();
    }

    /**
     * Get missing classes as a collection for RepeatableEntry
     * This is an accessor for the infolist
     */
    public function getMissingClassesForInfolistAttribute(): Collection
    {
        $student = $this->student;

        if (! $student) {
            return $this->newCollection();
        }

        $period = $this->missingClassPeriod();
        $missingSubjectEnrollments = $this->missingSubjectEnrollments(
            $student,
            $period['school_year_variants'],
            $period['semester'],
        );

        if ($missingSubjectEnrollments->isEmpty()) {
            return $this->newCollection();
        }

        $subjects = $missingSubjectEnrollments
            ->map(fn (SubjectEnrollment $subjectEnrollment): ?Subject => $subjectEnrollment->subject)
            ->filter()
            ->unique('id')
            ->values();

        [$classesBySubjectId, $slotCounts] = $this->candidateClassesForSubjects($subjects, $period);

        $allMissingClasses = [];

        foreach ($missingSubjectEnrollments as $subjectEnrollment) {
            $subject = $subjectEnrollment->subject;

            if (! $subject) {
                continue;
            }

            $subjectCode = (string) $subject->code;
            $availableClasses = $classesBySubjectId->get($subject->id, collect());

            if ($availableClasses->isEmpty()) {
                $allMissingClasses[] = (object) [
                    'class_id' => null,
                    'subject_code' => $subjectCode,
                    'subject_title' => $subject->title,
                    'section' => '—',
                    'faculty' => '—',
                    'schedule' => '—',
                    'room' => '—',
                    'available_slots' => null,
                    'max_slots' => 0,
                    'is_full' => false,
                ];

                continue;
            }

            foreach ($availableClasses as $class) {
                $enrolledCount = (int) ($slotCounts[$class->id] ?? 0);
                $maxSlots = $class->maximum_slots ?: 0;
                $availableSlots = $maxSlots > 0 ? $maxSlots - $enrolledCount : PHP_INT_MAX;

                $allMissingClasses[] = (object) [
                    'class_id' => $class->id,
                    'subject_code' => $subjectCode,
                    'subject_title' => $class->subject_title,
                    'section' => $class->section,
                    'faculty' => $class->faculty->full_name ?? 'TBA',
                    'schedule' => $this->formatSchedule($class),
                    'room' => $class->room?->name ?? 'TBA',
                    'available_slots' => $availableSlots,
                    'max_slots' => $maxSlots,
                    'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                ];
            }
        }

        // Wrap in Eloquent Collection to ensure correct return type
        return $this->newCollection($allMissingClasses);
    }

    /**
     * Get class enrollment status for the student
     * This method is used by the infolist to display enrollment status
     */
    public function getClassEnrollmentStatus(): array
    {
        return [
            'enrolled_classes' => $this->enrolled_classes_for_infolist,
            'missing_classes' => $this->missing_classes_for_infolist,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (self $model): void {
            $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
            $pipeline = app(EnrollmentPipelineService::class);

            if (! $model->status) {
                $model->status = $pipeline->getPendingStatus();
            }

            if ($settings) {
                if (! $model->school_year) {
                    $model->school_year = $settings->getSchoolYearString();
                }

                if (! $model->semester) {
                    $model->semester = $settings->semester;
                }
            }
        });

        // delete also the subjects enrolled
        self::forceDeleting(function (self $model): void {
            if ($model->hasFinancialRecords()) {
                throw FinancialAuditLockException::enrollmentHasFinancialRecords($model->id);
            }
        });

        self::forceDeleted(function (self $model): void {
            $model->subjectsEnrolled()->forceDelete();
            $model->studentTuition()->forceDelete();
        });
    }

    protected function studentName(): Attribute
    {
        return Attribute::make(get: fn () => $this->student->full_name);
    }

    protected function assessmentPath(): Attribute
    {
        return Attribute::make(get: fn () => $this->resources()
            ->where('type', 'assessment')
            ->latest()
            ->first()->file_path);
    }

    protected function certificatePath(): Attribute
    {
        return Attribute::make(get: fn () => $this->resources()
            ->where('type', 'certificate')
            ->latest()
            ->first()->file_path);
    }

    protected function assessmentUrl(): Attribute
    {
        return Attribute::make(get: function (): string {
            $resource = $this->resources()
                ->where('type', 'assessment')
                ->latest()
                ->first();
            if (! $resource) {
                return '';
            }

            // Use asset helper instead of Storage::url
            try {
                return asset('storage/'.mb_ltrim($resource->file_path, '/'));
            } catch (Exception) {
                return '';
            }
        });
    }

    protected function certificateUrl(): Attribute
    {
        return Attribute::make(get: function (): string {
            $resource = $this->resources()
                ->where('type', 'certificate')
                ->latest()
                ->first();
            if (! $resource) {
                return '';
            }

            // Use asset helper instead of Storage::url
            try {
                return asset('storage/'.mb_ltrim($resource->file_path, '/'));
            } catch (Exception) {
                return '';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'student_id' => 'string', // Explicitly cast to string for consistency
            'status' => 'string',
            'semester' => 'integer',
            'academic_year' => 'integer',
            'downpayment' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'enrollment_policy_snapshot_id' => 'integer',
        ];
    }

    /**
     * @return array{school_year_variants: array<string>, semester: int}
     */
    private function missingClassPeriod(): array
    {
        $period = $this->getCurrentAcademicPeriod();

        return [
            'school_year_variants' => $this->school_year
                ? $this->resolveSchoolYearVariants($this->school_year)
                : $period['school_year_variants'],
            'semester' => (int) ($this->semester ?: $period['semester']),
        ];
    }

    /**
     * Subject enrollments whose subject has no active class assignment.
     *
     * @param  array<string>  $schoolYearVariants
     * @return Collection<int, SubjectEnrollment>
     */
    private function missingSubjectEnrollments(Student $student, array $schoolYearVariants, int $semester): Collection
    {
        $this->loadMissing(['subjectsEnrolled.subject']);

        $enrolledSubjectCodes = $this->enrolledSubjectCodes($student, $schoolYearVariants, $semester);

        return $this->subjectsEnrolled
            ->filter(function (SubjectEnrollment $subjectEnrollment) use ($enrolledSubjectCodes): bool {
                $subject = $subjectEnrollment->subject;

                return $subject instanceof Subject
                    && ! in_array($this->normalizedSubjectCode($subject->code), $enrolledSubjectCodes, true);
            })
            ->values();
    }

    /**
     * Normalized subject codes from the student's active class enrollments.
     *
     * Uses the eager-loaded relation when available so a page of enrollments
     * does not issue one query per record.
     *
     * @param  array<string>  $schoolYearVariants
     * @return array<int, string>
     */
    private function enrolledSubjectCodes(Student $student, array $schoolYearVariants, int $semester): array
    {
        $student->loadMissing(['classEnrollments.class']);

        return $student->classEnrollments
            ->filter(function (ClassEnrollment $classEnrollment) use ($schoolYearVariants, $semester): bool {
                $class = $classEnrollment->class;

                return (bool) $classEnrollment->status
                    && $class instanceof Classes
                    && in_array($class->school_year, $schoolYearVariants, true)
                    && (int) $class->semester === $semester;
            })
            ->pluck('class.subject_code')
            ->flatMap(fn (?string $subjectCode): array => collect(explode(',', (string) $subjectCode))
                ->map(fn (string $code): string => $this->normalizedSubjectCode($code))
                ->filter()
                ->all())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve every candidate class for the given subjects in a constant
     * number of statements: one offerings query, its eager loads, and one
     * grouped count for the enrolled slots.
     *
     * @param  Collection<int, Subject>  $subjects
     * @param  array{school_year_variants: array<string>, semester: int}  $period
     * @return array{0: Collection<int, Collection<int, Classes>>, 1: array<int, int>}
     */
    private function candidateClassesForSubjects(Collection $subjects, array $period): array
    {
        if ($subjects->isEmpty()) {
            return [collect(), []];
        }

        $classes = Classes::query()
            ->with(['faculty', 'room', 'Subject', 'SubjectByCodeFallback', 'ShsSubject', 'schedules.room'])
            ->whereIn('school_year', $period['school_year_variants'])
            ->where('semester', $period['semester'])
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as $subject) {
                    $query->orWhere(function (Builder $subjectQuery) use ($subject): void {
                        $subjectQuery
                            ->where(function (Builder $courseQuery) use ($subject): void {
                                $courseQuery->whereJsonContains('course_codes', $subject->course_id);

                                if ($subject->course_id !== null) {
                                    $courseQuery->orWhereJsonContains('course_codes', (string) $subject->course_id);
                                }
                            })
                            ->where(function (Builder $matchingQuery) use ($subject): void {
                                $matchingQuery
                                    ->whereJsonContains('subject_ids', $subject->id)
                                    ->orWhereJsonContains('subject_ids', (string) $subject->id)
                                    ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                                    ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                            });
                    });
                }
            })
            ->get();

        $slotCounts = $classes->isEmpty()
            ? []
            : ClassEnrollment::query()
                ->whereIn('class_id', $classes->modelKeys())
                ->groupBy('class_id')
                ->selectRaw('class_id, COUNT(*) as aggregate')
                ->pluck('aggregate', 'class_id')
                ->map(fn (int|string $count): int => (int) $count)
                ->all();

        $classesBySubjectId = $subjects->mapWithKeys(fn (Subject $subject): array => [
            $subject->id => $classes
                ->filter(fn (Classes $class): bool => $this->classTeachesSubject($class, $subject))
                ->values(),
        ]);

        return [$classesBySubjectId, $slotCounts];
    }

    private function classTeachesSubject(Classes $class, Subject $subject): bool
    {
        $courseId = $subject->course_id;
        $courseCodes = (array) $class->course_codes;
        $matchesCourse = in_array($courseId, $courseCodes, true)
            || ($courseId !== null && in_array((string) $courseId, array_map(static fn (mixed $code): string => (string) $code, $courseCodes), true));

        if (! $matchesCourse) {
            return false;
        }

        $subjectIds = (array) $class->subject_ids;
        if (
            in_array($subject->id, $subjectIds, true)
            || in_array((string) $subject->id, array_map(static fn (mixed $id): string => (string) $id, $subjectIds), true)
        ) {
            return true;
        }

        $subjectCode = $this->normalizedSubjectCode($subject->code);
        $classCode = $this->normalizedSubjectCode($class->subject_code);

        return $classCode === $subjectCode || ($subjectCode !== '' && str_contains($classCode, $subjectCode));
    }

    private function normalizedSubjectCode(?string $code): string
    {
        return mb_strtoupper(mb_trim((string) $code));
    }

    private function formatSchedule(Classes $class): string
    {
        if ($class->schedules->isEmpty()) {
            return 'TBA';
        }

        return $class->schedules
            ->map(function ($schedule): string {
                $day = $schedule->day_of_week;
                // Handle both string and Carbon instances
                $startTime = $schedule->start_time instanceof Carbon
                    ? $schedule->start_time->format('h:i A')
                    : date('h:i A', strtotime((string) $schedule->start_time));
                $endTime = $schedule->end_time instanceof Carbon
                    ? $schedule->end_time->format('h:i A')
                    : date('h:i A', strtotime((string) $schedule->end_time));
                $roomName = $schedule->room->name ?? 'TBA';

                return "{$day} {$startTime}-{$endTime} ({$roomName})";
            })
            ->implode(', ');
    }

    /**
     * Get the start date for the current academic period
     */
    private function getCurrentAcademicPeriodStartDate(): string
    {
        $generalSettingsService = new GeneralSettingsService;
        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();

        if ($schoolStartingDate instanceof Carbon) {
            // Use the actual school starting date for the current academic year
            $currentYear = $generalSettingsService->getCurrentSchoolYearStart();
            $startMonth = $schoolStartingDate->month;
            $startDay = $schoolStartingDate->day;

            return sprintf('%d-%02d-%02d 00:00:00', $currentYear, $startMonth, $startDay);
        }
        // Fallback to August 1st of current academic year
        $currentYear = $generalSettingsService->getCurrentSchoolYearStart();

        return $currentYear.'-08-01 00:00:00';
    }

    /**
     * Calculate the start date for an academic period using GeneralSettings
     */
    private function getAcademicPeriodStartDate(string $schoolYear, ?int $semester): string
    {
        // Default to semester 1 if null
        $semester ??= 1;

        // Parse school year (e.g., "2024-2025" -> start year is 2024)
        $years = explode('-', $schoolYear);
        if (count($years) < 2) {
            // Fallback for invalid school year format
            $currentYear = date('Y');
            $years = [$currentYear, $currentYear + 1];
        }

        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        // Get the actual school starting date from GeneralSettings
        $generalSettingsService = new GeneralSettingsService;
        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();
        if ($schoolStartingDate instanceof Carbon) {
            // Use the actual school starting date
            $startMonth = $schoolStartingDate->month;
            $startDay = $schoolStartingDate->day;
            if ($semester === 1) {
                // First semester starts on the school starting date
                return sprintf('%d-%02d-%02d 00:00:00', $startYear, $startMonth, $startDay);
            }

            // Second semester - calculate based on school calendar
            // Typically starts in January after the first semester ends
            return $endYear.'-01-01 00:00:00';
        }

        if ($semester === 1) {
            // Fallback to default dates if no school starting date is set
            return $startYear.'-08-01 00:00:00';
        }

        return $endYear.'-01-01 00:00:00';

    }

    /**
     * Calculate the end date for an academic period using GeneralSettings
     */
    private function getAcademicPeriodEndDate(string $schoolYear, ?int $semester): string
    {
        // Default to semester 1 if null
        $semester ??= 1;

        // Parse school year (e.g., "2024-2025" -> end year is 2025)
        $years = explode('-', $schoolYear);
        if (count($years) < 2) {
            // Fallback for invalid school year format
            $currentYear = date('Y');
            $years = [$currentYear, $currentYear + 1];
        }

        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        // Get the actual school ending date from GeneralSettings
        $generalSettingsService = new GeneralSettingsService;
        $schoolEndingDate = $generalSettingsService->getGlobalSchoolEndingDate();
        if ($schoolEndingDate instanceof Carbon) {
            // Use the actual school ending date
            $endMonth = $schoolEndingDate->month;
            $endDay = $schoolEndingDate->day;
            if ($semester === 1) {
                // First semester ends around December (mid-year)
                // Calculate based on school calendar - typically December
                return $startYear.'-12-31 23:59:59';
            }

            // Second semester ends on the school ending date
            return sprintf('%d-%02d-%02d 23:59:59', $endYear, $endMonth, $endDay);
        }

        if ($semester === 1) {
            // Fallback to default dates if no school ending date is set
            return $startYear.'-12-31 23:59:59';
        }

        return $endYear.'-06-30 23:59:59';

    }
}
