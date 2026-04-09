<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttritionCategory;
use App\Enums\EmploymentStatus;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasAcademicPeriodScope;
use App\Services\GeneralSettingsService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $institution_id
 * @property int $student_id
 * @property string|null $lrn
 * @property string $student_type
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon $birth_date
 * @property string $gender
 * @property string $civil_status
 * @property string $nationality
 * @property string|null $religion
 * @property string|null $address
 * @property string|null $emergency_contact
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ClassEnrollment> $Classes
 * @property-read int|null $classes_count
 * @property-read Course|null $Course
 * @property-read DocumentLocation|null $DocumentLocation
 * @property-read Collection<int, StudentTransaction> $StudentTransactions
 * @property-read int|null $student_transactions_count
 * @property-read Collection<int, StudentTuition> $StudentTuition
 * @property-read int|null $student_tuition_count
 * @property-read Collection<int, Transaction> $Transaction
 * @property-read int|null $transaction_count
 * @property-read Account|null $account
 * @property-read Collection<int, ClassEnrollment> $classEnrollments
 * @property-read int|null $class_enrollments_count
 * @property-read Collection<int, StudentClearance> $clearances
 * @property-read int|null $clearances_count
 * @property-read string $formatted_academic_year
 * @property-read mixed $full_name
 * @property-read mixed $picture1x1
 * @property-read mixed $student_picture
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read StudentsPersonalInfo|null $personalInfo
 * @property-read Collection<int, resource> $resources
 * @property-read int|null $resources_count
 * @property-read StudentContact|null $studentContactsInfo
 * @property-read StudentEducationInfo|null $studentEducationInfo
 * @property-read StudentParentsInfo|null $studentParentInfo
 * @property-read Collection<int, SubjectEnrollment> $subjectEnrolled
 * @property-read int|null $subject_enrolled_count
 * @property-read Collection<int, SubjectEnrollment> $subjectEnrolledCurrent
 * @property-read int|null $subject_enrolled_current_count
 * @property-read Collection<int, Subject> $subjects
 * @property-read int|null $subjects_count
 * @property-read Collection<int, StudentTransaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static Builder<static>|Student newModelQuery()
 * @method static Builder<static>|Student newQuery()
 * @method static Builder<static>|Student onlyTrashed()
 * @method static Builder<static>|Student query()
 * @method static Builder<static>|Student whereAddress($value)
 * @method static Builder<static>|Student whereBirthDate($value)
 * @method static Builder<static>|Student whereCivilStatus($value)
 * @method static Builder<static>|Student whereCreatedAt($value)
 * @method static Builder<static>|Student whereEmail($value)
 * @method static Builder<static>|Student whereEmergencyContact($value)
 * @method static Builder<static>|Student whereFirstName($value)
 * @method static Builder<static>|Student whereGender($value)
 * @method static Builder<static>|Student whereId($value)
 * @method static Builder<static>|Student whereInstitutionId($value)
 * @method static Builder<static>|Student whereLastName($value)
 * @method static Builder<static>|Student whereLrn($value)
 * @method static Builder<static>|Student whereMiddleName($value)
 * @method static Builder<static>|Student whereNationality($value)
 * @method static Builder<static>|Student wherePhone($value)
 * @method static Builder<static>|Student whereReligion($value)
 * @method static Builder<static>|Student whereStatus($value)
 * @method static Builder<static>|Student whereStudentId($value)
 * @method static Builder<static>|Student whereStudentType($value)
 * @method static Builder<static>|Student whereSuffix($value)
 * @method static Builder<static>|Student whereUpdatedAt($value)
 * @method static Builder<static>|Student withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Student withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Student extends Model
{
    use BelongsToSchool;
    use HasAcademicPeriodScope;
    use HasFactory;
    use LogsActivity;
    use Notifiable;
    use Searchable;
    use SoftDeletes;
    use Versionable;

    public $timestamps = true;

    public $incrementing = false;

    protected $versionable = ['institution_id',
        'student_id',
        'lrn',
        'student_type',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'gender',
        'birth_date',
        'age',
        'address',
        'contacts',
        'course_id',
        'academic_year',
        'email',
        'phone',
        'civil_status',
        'nationality',
        'religion',
        'emergency_contact',
        'remarks',
        'profile_url',
        'student_contact_id',
        'student_parent_info',
        'student_education_id',
        'student_personal_id',
        'document_location_id',
        'status',
        'clearance_status',
        'year_graduated',
        'special_order',
        'issued_date',
        'subject_enrolled',
        'user_id',
        'ethnicity',
        'city_of_origin',
        'province_of_origin',
        'region_of_origin',
        'is_indigenous_person',
        'indigenous_group',
        'withdrawal_date',
        'withdrawal_reason',
        'attrition_category',
        'dropout_date',
        'employment_status',
        'employer_name',
        'job_position',
        'employment_date',
        'employed_by_institution',
        'scholarship_type',
        'scholarship_details'];

    protected $table = 'students';

    protected $versionStrategy = VersionStrategy::SNAPSHOT;

    protected $fillable = [
        'institution_id',
        'student_id',
        'lrn',
        'student_type',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'gender',
        'birth_date',
        'age',
        'address',
        'contacts',
        'course_id',
        'academic_year',
        'email',
        'phone',
        'civil_status',
        'nationality',
        'religion',
        'emergency_contact',
        'remarks',
        'profile_url',
        'student_contact_id',
        'student_parent_info',
        'student_education_id',
        'student_personal_id',
        'document_location_id',
        'status',
        'clearance_status',
        'year_graduated',
        'special_order',
        'issued_date',
        'subject_enrolled',
        'user_id',
        'ethnicity',
        'city_of_origin',
        'province_of_origin',
        'region_of_origin',
        'is_indigenous_person',
        'indigenous_group',
        'withdrawal_date',
        'withdrawal_reason',
        'attrition_category',
        'dropout_date',
        'employment_status',
        'employer_name',
        'job_position',
        'employment_date',
        'employed_by_institution',
        'scholarship_type',
        'scholarship_details',
        'shs_strand_id',
        'shs_track_id',
        'school_id',
    ];

    private static int $cacheFor = 3600; // Cache for 1 hour

    private static array $cacheKeys = [
        'id',
        'full_name',
        'course_id',
        'academic_year',
    ];

    /**
     * Generate the next available 6-digit student ID.
     * Uses prefixes based on student type: College (2), SHS (3), TESDA (4)
     * Generates ID in format: [prefix][5-digit number] = 6-digit total
     */
    public static function generateNextId(?StudentType $studentType = null): int
    {
        // Default to College if no type specified
        if (! $studentType instanceof StudentType) {
            $studentType = StudentType::College;
        }

        if ($studentType === StudentType::College) {
            $minId = 200000;
            $maxId = 209999; // IDs must start with 20
        } else {
            $prefix = $studentType->getIdPrefix();
            $minId = (int) ($prefix.'00000');
            $maxId = (int) ($prefix.'99999');
        }

        try {
            // Find the highest existing student_id for this prefix
            $query = self::withTrashed()
                ->where('student_id', '>=', $minId)
                ->where('student_id', '<=', $maxId);

            if ($studentType === StudentType::College) {
                $query->whereRaw('LENGTH(CAST(student_id AS VARCHAR)) = 6');
            }

            $maxStudentId = $query->max('student_id');

            $nextId = $maxStudentId ? (int) $maxStudentId + 1 : $minId;

            // Validate that the generated ID is not a duplicate
            while (self::withTrashed()->where('student_id', $nextId)->exists()) {
                $nextId++;
            }

            // Ensure we don't exceed the range for this type
            if ($nextId > $maxId) {
                throw new Exception("No available student IDs in range {$minId}-{$maxId} for student type {$studentType->value}");
            }

            return $nextId;
        } catch (Exception $e) {
            Log::error('Error generating student ID', [
                'error' => $e->getMessage(),
                'student_type' => $studentType->value,
            ]);

            // Fallback: return minimum ID for the type
            return $minId;
        }
    }

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
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'middle_name' => $this->middle_name,
                'email' => $this->email,
                'lrn' => $this->lrn,
            ];
        }

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'lrn' => $this->lrn,
            'course_code' => $this->Course?->code,
            'course_title' => $this->Course?->title,
            'academic_year' => $this->formatted_academic_year,
            'status' => $this->status,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "Student {$this->full_name} ({$this->student_id}) was {$eventName}")
            ->useLogName('student');
    }

    /**
     * Get the clearances for the student.
     */
    public function clearances()
    {
        return $this->hasMany(StudentClearance::class);
    }

    /**
     * Get the current semester clearance for the student.
     */
    public function currentClearance()
    {
        $period = $this->getCurrentAcademicPeriod();

        return $this->clearances()
            ->whereIn('academic_year', $period['school_year_variants'])
            ->where('semester', $period['semester']);
    }

    /**
     * Get the current clearance record as a relationship.
     * This method returns a relationship instance for use with Filament forms.
     */
    public function getCurrentClearanceRecord()
    {
        $period = $this->getCurrentAcademicPeriod();

        return $this->hasOne(StudentClearance::class)
            ->whereIn('academic_year', $period['school_year_variants'])
            ->where('semester', $period['semester']);
    }

    /**
     * Get the current clearance record as a model instance.
     *
     * @return StudentClearance|null
     */
    public function getCurrentClearanceModel()
    {
        return $this->getCurrentClearanceRecord()->first();
    }

    /**
     * Check if student has cleared their clearance for current semester.
     */
    public function hasCurrentClearance(): bool
    {
        $clearance = $this->getCurrentClearanceModel();

        return $clearance && $clearance->is_cleared;
    }

    /**
     * Get or create clearance for current semester.
     */
    public function getOrCreateCurrentClearance()
    {
        $clearance = $this->getCurrentClearanceModel();

        if (! $clearance) {
            return StudentClearance::createForCurrentSemester($this);
        }

        return $clearance;
    }

    /**
     * Mark student clearance as cleared for the current semester.
     *
     * @param  string|null  $clearedBy
     * @param  string|null  $remarks
     */
    public function markClearanceAsCleared(
        $clearedBy = null,
        $remarks = null
    ): bool {
        $clearance = $this->getOrCreateCurrentClearance();

        return $clearance->markAsCleared($clearedBy, $remarks);
    }

    /**
     * Mark student clearance as not cleared for the current semester.
     *
     * @param  string|null  $remarks
     */
    public function markClearanceAsNotCleared($remarks = null): bool
    {
        $clearance = $this->getOrCreateCurrentClearance();

        return $clearance->markAsNotCleared($remarks);
    }

    /**
     * Undo the last clearance action.
     */
    public function undoClearance(?string $remarks = null): bool
    {
        $clearance = $this->getCurrentClearanceModel();

        if (! $clearance) {
            return false;
        }

        // If clearance was marked as cleared, mark it as not cleared
        if ($clearance->is_cleared) {
            return $clearance->markAsNotCleared($remarks);
        }

        // If clearance was already marked as not cleared, mark it as cleared
        return $clearance->markAsCleared(null, $remarks);
    }

    /**
     * Calculate the previous academic period (school year and semester).
     *
     * @return array{academic_year: string, semester: int}
     */
    public function getPreviousAcademicPeriod(?string $currentYear = null, ?int $currentSemester = null): array
    {
        $settings = app(GeneralSettingsService::class);

        // Use provided values or get current from settings
        $currentYear ??= $settings->getCurrentSchoolYearString();
        $currentSemester ??= $settings->getCurrentSemester();

        // If current semester is 2nd, previous is 1st of the same year
        if ($currentSemester === 2) {
            return [
                'academic_year' => $currentYear,
                'semester' => 1,
            ];
        }

        // If current semester is 1st, previous is 2nd of the previous year
        // Parse "2024 - 2025" format
        $years = explode(' - ', $currentYear);
        $startYear = (int) mb_trim($years[0]);
        $previousStartYear = $startYear - 1;
        $previousYear = "{$previousStartYear} - {$startYear}";

        return [
            'academic_year' => $previousYear,
            'semester' => 2,
        ];
    }

    /**
     * Get the clearance record for the previous academic period.
     */
    public function getPreviousSemesterClearance(?string $currentYear = null, ?int $currentSemester = null): ?StudentClearance
    {
        $previous = $this->getPreviousAcademicPeriod($currentYear, $currentSemester);

        return $this->clearances()
            ->where('academic_year', $previous['academic_year'])
            ->where('semester', $previous['semester'])
            ->first();
    }

    /**
     * Check if student has cleared their clearance for the previous semester.
     */
    public function hasPreviousSemesterClearance(?string $currentYear = null, ?int $currentSemester = null): bool
    {
        $clearance = $this->getPreviousSemesterClearance($currentYear, $currentSemester);

        return $clearance && $clearance->is_cleared;
    }

    public function statusRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentStatusRecord::class);
    }

    public function getStatusForPeriod(string $academicYear, int $semester): ?StudentStatusRecord
    {
        return $this->statusRecords()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->first();
    }

    public function getCurrentStatusRecord(): ?StudentStatusRecord
    {
        $settings = app(GeneralSettingsService::class);

        return $this->getStatusForPeriod(
            $settings->getCurrentSchoolYearString(),
            $settings->getCurrentSemester()
        );
    }

    /**
     * Get detailed clearance validation result for enrollment.
     *
     * @return array{allowed: bool, message: string, clearance: StudentClearance|null}
     */
    public function validateEnrollmentClearance(?string $currentYear = null, ?int $currentSemester = null): array
    {
        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();

        // Check if clearance checking is enabled
        if (! $settings?->enable_clearance_check) {
            return [
                'allowed' => true,
                'message' => 'Clearance checking is disabled.',
                'clearance' => null,
            ];
        }

        $clearance = $this->getPreviousSemesterClearance($currentYear, $currentSemester);
        $previous = $this->getPreviousAcademicPeriod($currentYear, $currentSemester);

        // If no clearance record exists for previous semester
        if (! $clearance instanceof StudentClearance) {
            return [
                'allowed' => true,
                'message' => "No clearance record found for {$previous['academic_year']} Semester {$previous['semester']}. Student may proceed.",
                'clearance' => null,
            ];
        }

        // If clearance exists but is not cleared
        if (! $clearance->is_cleared) {
            return [
                'allowed' => false,
                'message' => "Student has not cleared requirements for {$previous['academic_year']} Semester {$previous['semester']}. Please clear previous semester before enrolling.",
                'clearance' => $clearance,
            ];
        }

        // Clearance is approved
        return [
            'allowed' => true,
            'message' => "Student is cleared for {$previous['academic_year']} Semester {$previous['semester']}.",
            'clearance' => $clearance,
        ];
    }

    public function DocumentLocation()
    {
        return $this->belongsTo(
            DocumentLocation::class,
            'document_location_id'
        );
    }

    public function Course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subjects()
    {
        return $this->hasManyThrough(
            Subject::class,
            Course::class,
            'id', // Foreign key on Course table
            'course_id', // Foreign key on Subject table
            'course_id', // Local key on Students table
            'id' // Local key on Course table
        );
    }

    public function personalInfo()
    {
        return $this->belongsTo(
            StudentsPersonalInfo::class,
            'student_personal_id',
            'id'
        );
    }

    public function studentEducationInfo()
    {
        return $this->belongsTo(
            StudentEducationInfo::class,
            'student_education_id',
            'id'
        );
    }

    public function studentContactsInfo()
    {
        return $this->belongsTo(
            StudentContact::class,
            'student_contact_id',
            'id'
        );
    }

    public function studentParentInfo()
    {
        return $this->belongsTo(
            StudentParentsInfo::class,
            'student_parent_info',
            'id'
        );
    }

    public function classEnrollments()
    {
        return $this->hasMany(ClassEnrollment::class, 'student_id', 'id');
    }

    public function subjectEnrolled()
    {
        return $this->hasMany(SubjectEnrollment::class, 'student_id', 'id');
    }

    public function subjectEnrolledCurrent()
    {
        $period = $this->getCurrentAcademicPeriod();

        return $this->hasMany(SubjectEnrollment::class, 'student_id', 'id')
            ->whereIn('school_year', $period['school_year_variants'])
            ->where('semester', $period['semester']);
    }

    public function Classes()
    {
        return $this->hasMany(ClassEnrollment::class, 'student_id', 'id');
    }

    /**
     * Get current classes for the student based on current academic period
     */
    public function getCurrentClasses()
    {
        $period = $this->getCurrentAcademicPeriod();

        return $this->classEnrollments()
            ->whereHas('class', function ($query) use ($period): void {
                $query->whereIn('school_year', $period['school_year_variants'])
                    ->where('semester', $period['semester']);
            })
            ->with([
                'class.subject',
                'class.subjectByCode',
                'class.subjectByCodeFallback',
                'class.shsSubject',
                'class.faculty',
            ])
            ->get();
    }

    /**
     * Automatically enrolls a student in classes based on their subject enrollments
     *
     * Process:
     * 1. Gets all subject enrollments for the student based on the enrollment_id
     * 2. For each subject enrollment:
     *    - Gets the associated subject details
     *    - Finds matching classes based on:
     *      * Subject code
     *      * Course codes (JSON array containing the subject's course ID)
     *      * School year from settings
     *      * Academic year from subject
     *      * Current semester from settings
     * 3. For each matching class:
     *    - Checks if student is already enrolled
     *    - If not enrolled, creates a new class enrollment record
     *    - If already enrolled, skips creation
     * 4. Logs the enrollment process at key points
     */
    public function autoEnrollInClasses($enrollment_id = null): void
    {
        $subjectEnrollments = $this->subjectEnrolled->where(
            'enrollment_id',
            $enrollment_id
        );

        $period = $this->getCurrentAcademicPeriod();
        $notificationData = [];
        $errors = []; // Initialize $errors array

        // Flag to allow enrollment even when class appears full (override maximum_slots check)
        $forceEnrollWhenFull = config(
            'enrollment.force_enroll_when_full',
            false
        );

        foreach ($subjectEnrollments as $subjectEnrollment) {
            $subject = $subjectEnrollment->subject;

            Log::info(
                sprintf('Attempting to enroll student %d in classes for subject: %s', $this->id, $subject->code),
                [
                    'student_id' => $this->id,
                    'subject_code' => $subject->code,
                    'course_id' => $subject->course_id,
                    'subject_academic_year' => $subject->academic_year,
                    'enrollment_academic_year' => $subjectEnrollment->academic_year,
                ]
            );

            try {
                // First, check if the student is already enrolled in a class for this subject
                // in the CURRENT school year and semester
                $existingEnrollment = ClassEnrollment::query()->whereHas('class', function ($query) use ($subject, $period): void {
                    $query->where('subject_code', $subject->code)
                        ->whereIn('school_year', $period['school_year_variants'])
                        ->where('semester', $period['semester']);
                })
                    ->where('student_id', $this->id)
                    ->first();

                if ($existingEnrollment) {
                    Log::info(
                        sprintf('Student %d is already enrolled in a class for subject %s in current period', $this->id, $subject->code),
                        [
                            'class_id' => $existingEnrollment->class_id,
                            'school_year' => $period['school_year'],
                            'semester' => $period['semester'],
                        ]
                    );
                    $notificationData[] = [
                        'subject' => $subject->code,
                        'section' => $existingEnrollment->class->section ?? 'Unknown',
                        'status' => 'Already enrolled',
                    ];

                    continue; // Skip to next subject
                }

                // Find all potential classes for this subject
                $query = Classes::query()
                    ->whereIn('school_year', $period['school_year_variants'])
                    ->where('semester', $period['semester'])
                    ->whereJsonContains(
                        'course_codes',
                        $subject->course_id  // Don't cast to string - let Laravel handle the JSON type
                    )
                    ->where(function ($query) use ($subject): void {
                        // Match classes where subject_ids contains this subject (for new JSON structure)
                        $query->whereJsonContains('subject_ids', $subject->id)
                            // OR where subject_code matches exactly (for backward compatibility)
                            ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                            // OR where subject_code contains this subject code (for comma-separated codes)
                            ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                    });

                // Handle academic_year filtering flexibly
                // Try to match classes based on the subject enrollment's academic year OR the subject's academic year
                // Also accept classes with NULL academic_year (open to all year levels)
                $enrollmentAcademicYear = $subjectEnrollment->academic_year;
                $subjectAcademicYear = $subject->academic_year;

                // REMOVED: Academic Year constraint was too strict and prevented irregular students from enrolling
                // in classes that didn't match their current year level, even if the subject matched.
                // Since we already filter by course_codes and subject_ids, the class is guaranteed to be
                // for the correct subject and course.
                /*
                $query->where(function ($academicYearQuery) use ($enrollmentAcademicYear, $subjectAcademicYear): void {
                    // Accept classes with NULL academic_year (available to all year levels)
                    $academicYearQuery->whereNull('academic_year');

                    // OR accept classes matching the enrollment's academic year
                    if ($enrollmentAcademicYear !== null) {
                        $academicYearQuery->orWhere('academic_year', $enrollmentAcademicYear);
                    }

                    // OR accept classes matching the subject's academic year (if different from enrollment)
                    if ($subjectAcademicYear !== null && $subjectAcademicYear !== $enrollmentAcademicYear) {
                        $academicYearQuery->orWhere('academic_year', $subjectAcademicYear);
                    }
                });
                */

                // Log the full query with all details for debugging
                Log::info('Query for classes: '.$query->toSql(), [
                    'bindings' => $query->getBindings(),
                    'subject_code' => $subject->code,
                    'subject_academic_year' => $subject->academic_year,
                    'enrollment_academic_year' => $subjectEnrollment->academic_year,
                    'course_id' => $subject->course_id,
                    'school_year' => $period['school_year'],
                    'semester' => $period['semester'],
                ]);

                // Also log a simpler query without academic_year filter to see if classes exist at all
                $debugClassCount = Classes::query()
                    ->whereIn('school_year', $period['school_year_variants'])
                    ->where('semester', $period['semester'])
                    ->whereJsonContains('course_codes', $subject->course_id)  // Don't cast to string
                    ->where(function ($query) use ($subject): void {
                        // Match classes where subject_ids contains this subject (for new JSON structure)
                        $query->whereJsonContains('subject_ids', $subject->id)
                            // OR where subject_code matches exactly (for backward compatibility)
                            ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                            // OR where subject_code contains this subject code (for comma-separated codes)
                            ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                    })
                    ->count();

                Log::info('Debug: Total classes for subject without academic_year filter', [
                    'subject_code' => $subject->code,
                    'subject_id' => $subject->id,
                    'course_id' => $subject->course_id,
                    'count' => $debugClassCount,
                ]);

                // Temporarily disable JSON contains for debugging - test simple LIKE
                $debugClassCountNoJson = Classes::query()
                    ->whereIn('school_year', $period['school_year_variants'])
                    ->where('semester', $period['semester'])
                    ->whereJsonContains('course_codes', $subject->course_id)  // Test without casting
                    ->where('subject_code', 'LIKE', '%'.$subject->code.'%')  // Use actual subject code
                    ->count();

                Log::info('Debug: Simple LIKE query without JSON contains', [
                    'subject_code' => $subject->code,
                    'course_id' => $subject->course_id,
                    'count' => $debugClassCountNoJson,
                ]);

                // Additional debugging - check if there are any classes for this course/period
                $anyClassCount = Classes::query()
                    ->whereIn('school_year', $period['school_year_variants'])
                    ->where('semester', $period['semester'])
                    ->whereJsonContains('course_codes', $subject->course_id)  // Don't cast to string
                    ->count();

                Log::info('Debug: Any classes for this course/period', [
                    'course_id' => $subject->course_id,
                    'school_year' => $period['school_year'],
                    'semester' => $period['semester'],
                    'count' => $anyClassCount,
                ]);

                $classes = $query->get();

                if ($classes->isEmpty()) {
                    $errorMessage = 'No classes found for subject '.$subject->code;
                    Log::warning($errorMessage, [
                        'student_id' => $this->id,
                        'subject_code' => $subject->code,
                        'course_id' => $subject->course_id,
                    ]);
                    $errors[] = $errorMessage;

                    continue;
                }

                Log::info(
                    sprintf('Found %s classes for subject %s', $classes->count(), $subject->code)
                );

                // Sort classes by available slots (least full first)
                $availableClasses = $classes->sortBy(function ($class): int|float {
                    // Check if maximum_slots is set and not zero to avoid division by zero
                    if (empty($class->maximum_slots)) {
                        return PHP_INT_MAX; // Sort classes with no maximum to the end
                    }

                    $enrolledCount = ClassEnrollment::query()->where('class_id', $class->id)->count();

                    // Calculate fill percentage
                    return ($enrolledCount / $class->maximum_slots) * 100;
                });

                $enrolled = false;
                $fullClasses = 0;

                foreach ($availableClasses as $availableClass) {
                    $enrolledCount = ClassEnrollment::query()->where('class_id', $availableClass->id)->count();
                    $maxSlots = $availableClass->maximum_slots ?: 0;

                    // Log detailed class information
                    Log::info(
                        sprintf('Checking class section %s for subject %s', $availableClass->section, $subject->code),
                        [
                            'class_id' => $availableClass->id,
                            'enrolled_count' => $enrolledCount,
                            'maximum_slots' => $maxSlots,
                            'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                        ]
                    );

                    // If class is full and we're not forcing enrollment, skip to next class
                    if (
                        $maxSlots > 0 &&
                        $enrolledCount >= $maxSlots &&
                        ! $forceEnrollWhenFull
                    ) {
                        Log::info(
                            sprintf('Class %s (Section %s) is full, trying next section', $availableClass->id, $availableClass->section)
                        );
                        $fullClasses++;

                        continue;
                    }

                    // Try to enroll the student in this class
                    try {
                        // Check if already enrolled before creating
                        $alreadyEnrolled = ClassEnrollment::query()
                            ->where('class_id', $availableClass->id)
                            ->where('student_id', $this->id)
                            ->exists();

                        if ($alreadyEnrolled) {
                            Log::info(
                                sprintf('Student %d is already enrolled in class %s (Section %s)', $this->id, $availableClass->id, $availableClass->section)
                            );
                            $notificationData[] = [
                                'subject' => $subject->code,
                                'section' => $availableClass->section,
                                'status' => 'Already enrolled',
                            ];
                            $enrolled = true;
                            break;
                        }

                        ClassEnrollment::query()->create([
                            'class_id' => $availableClass->id,
                            'student_id' => $this->id,
                        ]);

                        // If we got here, enrollment was successful
                        $remainingSlots = $maxSlots
                            ? $maxSlots - $enrolledCount - 1
                            : 'unlimited';
                        $notificationData[] = [
                            'subject' => $subject->code,
                            'section' => $availableClass->section,
                            'slots' => $maxSlots > 0
                                ? $remainingSlots.' remaining'
                                : 'no slot limit',
                        ];

                        Log::info(
                            sprintf('Successfully enrolled student %d in class %s (Section %s)', $this->id, $availableClass->id, $availableClass->section),
                            [
                                'remaining_slots' => $remainingSlots,
                            ]
                        );
                        $enrolled = true;
                        break;
                    } catch (Exception $e) {
                        $errorMessage =
                            sprintf('Failed to enroll in %s Section %s: ', $subject->code, $availableClass->section).
                            $e->getMessage();
                        Log::error($errorMessage, ['exception' => $e]);
                        // Continue trying other classes rather than failing immediately
                    }
                }

                if (! $enrolled) {
                    $errorMessage = 'No available slots in any section for subject '.$subject->code;
                    // Add more diagnostic info
                    if ($fullClasses > 0) {
                        $errorMessage .= sprintf(' (%d classes found but all are full)', $fullClasses);
                    }

                    Log::warning($errorMessage);
                    $errors[] = $errorMessage;
                }
            } catch (Exception $e) {
                $errorMessage =
                    sprintf('Error processing subject %s: ', $subject->code).
                    $e->getMessage();
                Log::error($errorMessage, ['exception' => $e]);
                $errors[] = $errorMessage;
            }
        }

        // Create Filament notification
        $notificationTitle = $errors === []
            ? 'Enrollment Successful'
            : 'Enrollment Completed with Issues';
        $notificationColor = $errors === [] ? 'success' : 'warning';

        // Build notification content
        $content = '';
        foreach ($notificationData as $data) {
            $content .=
                sprintf('Subject: %s - Section: %s', $data['subject'], $data['section']).
                (isset($data['slots']) ? sprintf(' (%s)', $data['slots']) : '').
                (isset($data['status']) ? sprintf(' (%s)', $data['status']) : '').
                "\n";
        }

        if ($errors !== []) {
            $content .= "\nIssues:\n".implode("\n", $errors);
        }

        // Send notification
        Notification::make()
            ->title($notificationTitle)
            ->body($content)
            ->color($notificationColor)
            ->persistent()
            ->send();
    }

    public function subjectsByYear($academicYear)
    {
        return $this->subjects()
            ->where('academic_year', $academicYear)
            ->get()
            ->map(fn ($subject): string => sprintf('%s (Code: %s, Units: %s)', $subject->title, $subject->code, $subject->units))
            ->join(', ');
    }

    public function StudentTuition()
    {
        return $this->hasMany(StudentTuition::class, 'student_id', 'id');
    }

    public function studentEnrollments()
    {
        // Handle type mismatch by using a query that casts the student ID to string
        // Include soft-deleted records for complete data integrity
        return StudentEnrollment::withTrashed()->where('student_id', (string) $this->id);
    }

    /**
     * Get the current semester tuition record as a relationship.
     * This method returns a relationship instance for use with Filament forms.
     */
    public function getCurrentTuitionRecord()
    {
        $period = $this->getCurrentAcademicPeriod();

        return $this->hasOne(StudentTuition::class)
            ->whereIn('school_year', $period['school_year_variants'])
            ->where('semester', $period['semester']);
    }

    /**
     * Get the current semester tuition record as a model instance.
     *
     * @return StudentTuition|null
     */
    public function getCurrentTuitionModel()
    {
        return $this->getCurrentTuitionRecord()->first();
    }

    /**
     * Get or create tuition record for current semester.
     */
    public function getOrCreateCurrentTuition()
    {
        $tuition = $this->getCurrentTuitionModel();

        if (! $tuition) {
            $period = $this->getCurrentAcademicPeriod();
            $course = $this->Course;

            $tuition = StudentTuition::query()->create([
                'student_id' => $this->id,
                'academic_year' => $this->academic_year,
                'semester' => $period['semester'],
                'school_year' => $period['school_year'],
                'total_tuition' => 0,
                'total_lectures' => 0,
                'total_laboratory' => 0,
                'total_miscelaneous_fees' => $course ? $course->getMiscellaneousFee() : 3500,
                'overall_tuition' => 0,
                'total_balance' => 0,
                'downpayment' => 0,
                'discount' => 0,
                'status' => 'pending',
            ]);
        }

        return $tuition;
    }

    public function StudentTransactions()
    {
        return $this->hasMany(StudentTransaction::class, 'student_id', 'id');
    }

    public function StudentTransact($type, $amount, $description): void
    {
        StudentTransaction::query()->create([
            'student_id' => $this->id,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'balance' => $this->StudentTuition->balance +
                ($type === 'credit' ? $amount : -$amount),
            'date' => now(),
        ]);
    }

    public function account()
    {
        return $this->hasOne(Account::class, 'person_id', 'id');
    }

    /**
     * Get the SHS student record associated with this student (if student type is SHS).
     */
    public function shsStudent()
    {
        return $this->hasOne(ShsStudent::class, 'student_lrn', 'lrn');
    }

    /**
     * Get the SHS strand that this student belongs to.
     */
    public function shsStrand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShsStrand::class, 'shs_strand_id');
    }

    /**
     * Get the SHS track that this student belongs to.
     */
    public function shsTrack(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShsTrack::class, 'shs_track_id');
    }

    //    transaction for students
    public function Transaction()
    {
        return $this->belongsToMany(
            Transaction::class,
            'student_transactions',
            'student_id',
            'transaction_id'
        );
    }

    // Add this relationship to the Students class
    public function resources()
    {
        return $this->morphMany(Resource::class, 'resourceable');
    }

    public function documents()
    {
        return $this->resources()->whereIn('type', [
            'assessment',
            'certificate',
        ]);
    }

    public function assessmentDocuments()
    {
        return $this->resources()->where('type', 'assessment');
    }

    public function certificateDocuments()
    {
        return $this->resources()->where('type', 'certificate');
    }

    public function withoutGraduatesScope()
    {
        return $this->where('academic_year', '!=', '5');
    }

    public function transactions()
    {
        return $this->hasMany(StudentTransaction::class, 'student_id', 'id');
    }

    /**
     * Get the medical records for the student.
     */
    public function medicalRecords()
    {
        return $this->hasMany(\Modules\StudentMedicalRecords\Models\MedicalRecord::class, 'student_id', 'id');
    }

    // Query Scopes for Statistical Reporting

    /**
     * Scope a query to only include enrolled students
     */
    public function scopeEnrolled(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Enrolled);
    }

    /**
     * Scope a query to only include applicants
     */
    public function scopeApplicants(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Applicant);
    }

    /**
     * Scope a query to only include graduates
     */
    public function scopeGraduates(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Graduated);
    }

    /**
     * Scope a query to only include indigenous students
     */
    public function scopeIndigenous(Builder $query): Builder
    {
        return $query->where('is_indigenous_person', true);
    }

    /**
     * Scope a query to only include students with scholarships
     */
    public function scopeScholars(Builder $query): Builder
    {
        return $query->whereNotNull('scholarship_type')
            ->where('scholarship_type', '!=', ScholarshipType::None);
    }

    /**
     * Scope a query to only include employed graduates
     */
    public function scopeEmployed(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Graduated)
            ->whereIn('employment_status', [
                EmploymentStatus::Employed,
                EmploymentStatus::SelfEmployed,
                EmploymentStatus::Underemployed,
            ]);
    }

    /**
     * Scope a query to only include withdrawn students
     */
    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Withdrawn);
    }

    /**
     * Scope a query to only include dropped out students
     */
    public function scopeDropped(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Dropped);
    }

    /**
     * Scope a query to filter by region
     */
    public function scopeByRegion(Builder $query, string $region): Builder
    {
        return $query->where('region_of_origin', $region);
    }

    /**
     * Scope a query to filter by scholarship type
     */
    public function scopeByScholarship(Builder $query, ScholarshipType $type): Builder
    {
        return $query->where('scholarship_type', $type);
    }

    // Helper Methods for Status Checks

    /**
     * Check if student is an applicant
     */
    public function isApplicant(): bool
    {
        return $this->status === StudentStatus::Applicant;
    }

    /**
     * Check if student is currently enrolled
     */
    public function isEnrolled(): bool
    {
        return $this->status === StudentStatus::Enrolled;
    }

    /**
     * Check if student is a graduate
     */
    public function isGraduate(): bool
    {
        return $this->status === StudentStatus::Graduated;
    }

    /**
     * Check if student has withdrawn
     */
    public function isWithdrawn(): bool
    {
        return $this->status === StudentStatus::Withdrawn;
    }

    /**
     * Check if student has dropped out
     */
    public function isDropped(): bool
    {
        return $this->status === StudentStatus::Dropped;
    }

    /**
     * Check if student has any scholarship
     */
    public function hasScholarship(): bool
    {
        return $this->scholarship_type !== null && $this->scholarship_type !== ScholarshipType::None;
    }

    /**
     * Check if student is an indigenous person
     */
    public function isIndigenousPerson(): bool
    {
        return $this->is_indigenous_person === true;
    }

    /**
     * Check if student is employed
     */
    public function isEmployed(): bool
    {
        return $this->employment_status !== null && $this->employment_status->isEmployed();
    }

    /**
     * Check if student is employed by the institution
     */
    public function isEmployedByInstitution(): bool
    {
        return $this->employed_by_institution === true;
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Student $student): void {
            if (! $student->id) {
                // Find the maximum ID in the students table (including soft deleted)
                $maxId = self::withTrashed()->max('id') ?? 0;
                $student->id = $maxId + 1;

                // Ensure the ID doesn't already exist (handle race conditions)
                while (self::withTrashed()->where('id', $student->id)->exists()) {
                    $student->id++;
                }
            }
        });

        self::created(function (Student $student): void {
            // Sync to ShsStudent model if student type is SHS
            if ($student->student_type === StudentType::SeniorHighSchool->value) {
                self::syncToShsStudent($student);
            }
        });

        self::updated(function (Student $student): void {
            // Check if student type changed to or from SHS
            if ($student->wasChanged('student_type')) {
                if ($student->student_type === StudentType::SeniorHighSchool->value) {
                    // Changed to SHS - create or update ShsStudent record
                    self::syncToShsStudent($student);
                } else {
                    // Changed from SHS - delete ShsStudent record if exists
                    ShsStudent::where('student_lrn', $student->lrn)->delete();
                }
            } elseif ($student->student_type === StudentType::SeniorHighSchool->value) {
                // Student is SHS and other fields were updated - sync to ShsStudent
                self::syncToShsStudent($student);
            }
        });

        self::saving(function (Student $student): void {
            if ($student->studentContactsInfo) {
                $student->studentContactsInfo->save();
                $student->student_contact_id =
                    $student->studentContactsInfo->id;
            }

            if ($student->studentParentInfo) {
                $student->studentParentInfo->save();
                $student->student_parent_info = $student->studentParentInfo->id;
            }

            if ($student->studentEducationInfo) {
                $student->studentEducationInfo->save();
                $student->student_education_id =
                    $student->studentEducationInfo->id;
            }

            if ($student->personalInfo) {
                $student->personalInfo->save();
                $student->student_personal_id = $student->personalInfo->id;
            }

            if ($student->DocumentLocation) {
                $student->DocumentLocation->save();
                $student->document_location_id = $student->DocumentLocation->id;
            }
        });
        self::forceDeleting(function ($student): void {
            $student->StudentTransactions()->delete();
            $student->StudentTuition()->delete();
            $student->StudentParentInfo()->delete();
            $student->StudentEducationInfo()->delete();
            $student->StudentContactsInfo()->delete();
            $student->personalInfo()->delete();
            $student->subjectEnrolled()->delete();
            // $student->DocumentLocation()->delete();
            $student->account()->delete();
            $student->classEnrollments()->delete();

            // Delete corresponding ShsStudent record if exists
            if ($student->lrn) {
                ShsStudent::where('student_lrn', $student->lrn)->delete();
            }
        });
        // Note: Student ID updates are handled by StudentIdUpdateService
        // to ensure proper transaction management and data integrity
    }

    protected function studentPicture(): Attribute
    {
        return Attribute::make(get: fn () => $this->DocumentLocation->picture_1x1 ?? '');
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(get: fn () => cache()->remember(
            sprintf('student_%d_full_name', $this->id),
            3600,
            fn (): string => sprintf('%s, %s %s', $this->last_name, $this->first_name, $this->middle_name)
        ));
    }

    protected function picture1x1(): Attribute
    {
        return Attribute::make(get: fn () => $this->DocumentLocation->picture_1x1 ?? '');
    }

    protected function formattedAcademicYear(): Attribute
    {
        return Attribute::make(get: function (): string {
            $years = [
                1 => '1st year',
                2 => '2nd year',
                3 => '3rd year',
                4 => '4th year',
            ];

            return $years[$this->academic_year] ?? 'Unknown year';
        });
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'institution_id' => 'integer',
            'student_id' => 'integer',
            'age' => 'integer',
            'course_id' => 'integer',
            'academic_year' => 'integer',
            'student_contact_id' => 'integer',
            'student_parent_info' => 'integer',
            'student_education_id' => 'integer',
            'student_personal_id' => 'integer',
            'document_location_id' => 'integer',
            'year_graduated' => 'integer',
            'birth_date' => 'date',
            'issued_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'contacts' => 'array',
            'subject_enrolled' => 'array',
            'user_id' => 'integer',
            'student_type' => StudentType::class,
            'is_indigenous_person' => 'boolean',
            'employed_by_institution' => 'boolean',
            'withdrawal_date' => 'date',
            'dropout_date' => 'date',
            'employment_date' => 'date',
            'status' => StudentStatus::class,
            'scholarship_type' => ScholarshipType::class,
            'employment_status' => EmploymentStatus::class,
            'attrition_category' => AttritionCategory::class,
            'shs_strand_id' => 'integer',
            'shs_track_id' => 'integer',
        ];
    }

    /**
     * Sync student data to ShsStudent model.
     * This ensures that SHS students have records in both tables.
     */
    private static function syncToShsStudent(self $student): void
    {
        try {
            $fullName = mb_trim(sprintf(
                '%s %s %s',
                $student->first_name,
                $student->middle_name ?? '',
                $student->last_name
            ));

            $guardianContact = $student->studentContactsInfo?->emergency_contact_phone;
            $studentContact = $student->studentContactsInfo?->personal_contact;

            $contacts = is_array($student->contacts) ? $student->contacts : [];
            $guardianContact ??= $contacts['emergency_contact_phone'] ?? null;
            $studentContact ??= $contacts['personal_contact'] ?? $contacts['phone'] ?? null;

            $completeAddress = $student->personalInfo?->current_adress ?? $student->address;

            $gradeLevel = match ($student->academic_year) {
                11 => 'Grade 11',
                12 => 'Grade 12',
                default => (string) $student->academic_year,
            };

            // Prepare data for ShsStudent
            $shsStudentData = [
                'student_lrn' => $student->lrn,
                'fullname' => $fullName,
                'civil_status' => $student->civil_status ?? $student->personalInfo?->civil_status,
                'religion' => $student->religion ?? $student->personalInfo?->religion,
                'nationality' => $student->nationality,
                'birthdate' => $student->birth_date?->format('Y-m-d'),
                'guardian_name' => $student->studentContactsInfo?->emergency_contact_name,
                'guardian_contact' => $guardianContact,
                'student_contact' => $studentContact,
                'complete_address' => $completeAddress,
                'grade_level' => $gradeLevel,
                'gender' => $student->gender,
                'email' => $student->email,
                'remarks' => $student->remarks,
                'strand_id' => $student->shs_strand_id,
                'track_id' => $student->shs_track_id,
            ];

            $shsStudentData = array_filter($shsStudentData, static fn ($value): bool => $value !== null);

            // Update or create ShsStudent record based on LRN
            ShsStudent::updateOrCreate(
                ['student_lrn' => $student->lrn],
                $shsStudentData
            );

            Log::info('Successfully synced SHS student to ShsStudent model', [
                'student_id' => $student->id,
                'lrn' => $student->lrn,
                'strand_id' => $student->shs_strand_id,
                'track_id' => $student->shs_track_id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to sync SHS student to ShsStudent model', [
                'student_id' => $student->id,
                'lrn' => $student->lrn,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
