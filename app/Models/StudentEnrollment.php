<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

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
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

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

    protected $table = 'student_enrollment';

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
    ];

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

    /**
     * Modify the database search query for Scout database driver.
     * This ensures only actual database columns are used in the WHERE clause.
     */
    public function scoutDatabaseQuery($query): Builder
    {
        return $query;
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

    /**
     * Get transactions for this specific enrollment period
     * Shows transactions from the current academic year based on school calendar
     */
    public function enrollmentTransactions()
    {
        // Use the Transaction model scope to determine the correct date range for this enrollment's semester
        // This ensures consistency with the StudentTuition calculation and covers edge cases like early downpayments

        $schoolYear = str_replace(' ', '', (string) $this->school_year);
        $semester = (int) $this->semester;

        // Parse school year for date range (logic mirrored from Transaction::scopeForAcademicPeriod to apply to nested relation)
        $years = explode('-', $schoolYear);
        if (count($years) < 2) {
            // Fallback if school year is invalid
            $currentYear = date('Y');
            $startYear = $currentYear;
            $endYear = $currentYear + 1;
        } else {
            $startYear = (int) $years[0];
            $endYear = (int) $years[1];
        }

        if ($semester === 1) {
            $startDate = $startYear.'-06-01 00:00:00';
            $endDate = ($startYear + 1).'-02-28 23:59:59';
        } else {
            $startDate = $startYear.'-11-01 00:00:00';
            $endDate = $endYear.'-07-31 23:59:59';
        }

        return $this->hasMany(StudentTransaction::class, 'student_id', 'student_id')
            ->whereHas('transaction', function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('transactions.created_at', [$startDate, $endDate]);
            })
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
     * Get missing classes as a collection for RepeatableEntry
     * This is an accessor for the infolist
     */
    public function getMissingClassesForInfolistAttribute(): Collection
    {
        $student = $this->student;

        if (! $student) {
            return $this->newCollection();
        }

        $subjectEnrollments = $this->subjectsEnrolled()->with('subject')->get();

        $period = $this->getCurrentAcademicPeriod();
        $schoolYearVariants = $this->school_year
            ? $this->resolveSchoolYearVariants($this->school_year)
            : $period['school_year_variants'];
        $semester = $this->semester ?: $period['semester'];

        $enrolledSubjectCodes = $student->classEnrollments()
            ->with('class')
            ->where('status', true)
            ->whereHas('class', function ($query) use ($schoolYearVariants, $semester): void {
                $query->whereIn('school_year', $schoolYearVariants)
                    ->where('semester', $semester);
            })
            ->get()
            ->pluck('class.subject_code')
            ->flatMap(fn ($subjectCode): array => collect(explode(',', (string) $subjectCode))
                ->map(fn (string $code): string => mb_strtoupper(mb_trim($code)))
                ->filter()
                ->all())
            ->unique()
            ->values()
            ->all();
        $allMissingClasses = [];

        // Only process if we have subject enrollments
        if ($subjectEnrollments->isNotEmpty()) {
            foreach ($subjectEnrollments as $subjectEnrollment) {
                $subject = $subjectEnrollment->subject;

                if (! $subject) {
                    continue;
                }

                $subjectCode = (string) $subject->code;
                $normalizedSubjectCode = mb_strtoupper(mb_trim($subjectCode));

                if (! in_array($normalizedSubjectCode, $enrolledSubjectCodes, true)) {
                    // Find available classes for this subject with relationships
                    $availableClasses = Classes::query()
                        ->with('faculty', 'schedules.room')
                        ->whereIn('school_year', $schoolYearVariants)
                        ->where('semester', $semester)
                        ->whereJsonContains('course_codes', $subject->course_id)
                        ->where(function ($query) use ($subject): void {
                            $query->whereJsonContains('subject_ids', $subject->id)
                                ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                                ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                        })
                        ->get()
                        ->map(function ($class) use ($subjectCode) {
                            $enrolledCount = ClassEnrollment::where('class_id', $class->id)->count();
                            $maxSlots = $class->maximum_slots ?: 0;
                            $availableSlots = $maxSlots > 0 ? $maxSlots - $enrolledCount : PHP_INT_MAX;

                            // Format schedule
                            $scheduleText = 'TBA';
                            if ($class->schedules->isNotEmpty()) {
                                $schedules = $class->schedules
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
                            $roomName = $class->room?->name ?? 'TBA';

                            return (object) [
                                'class_id' => $class->id,
                                'subject_code' => $subjectCode,
                                'subject_title' => $class->subject_title,
                                'section' => $class->section,
                                'faculty' => $class->faculty->full_name ?? 'TBA',
                                'schedule' => $scheduleText,
                                'room' => $roomName,
                                'available_slots' => $availableSlots,
                                'max_slots' => $maxSlots,
                                'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                            ];
                        });

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

                    $allMissingClasses = array_merge($allMissingClasses, $availableClasses->all());
                }
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
            $settings = GeneralSetting::query()->first();
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
        ];
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
