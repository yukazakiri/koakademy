<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasAcademicPeriodScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Class
 *
 * @property-read Collection<int, ClassEnrollment> $ClassStudents
 * @property-read int|null $class_students_count
 * @property-read Faculty|null $Faculty
 * @property-read Room|null $Room
 * @property-read Collection<int, Schedule> $Schedule
 * @property-read int|null $schedule_count
 * @property-read ShsStrand|null $ShsStrand
 * @property-read StrandSubject|null $ShsSubject
 * @property-read ShsTrack|null $ShsTrack
 * @property-read Subject|null $Subject
 * @property-read Subject|null $SubjectByCode
 * @property-read Subject|null $SubjectByCodeFallback
 * @property-read Subject|null $SubjectById
 * @property-read Collection<int, ClassEnrollment> $class_enrollments
 * @property-read int|null $class_enrollments_count
 * @property-read Collection<int, ClassEnrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read mixed $active_subject
 * @property-read mixed $assigned_room_i_ds
 * @property-read mixed $assigned_rooms
 * @property-read mixed $class_subject_title
 * @property-read mixed $display_info
 * @property-read mixed $faculty_full_name
 * @property-read mixed $formatted_course_codes
 * @property-read mixed $formatted_track_strand
 * @property-read mixed[] $formatted_weekly_schedule
 * @property-read mixed $schedule_days
 * @property-read mixed $schedule_rooms
 * @property-read mixed $student_count
 * @property-read mixed $subject_title
 * @property-read mixed $subject_with_courses
 * @property-read mixed $subject_with_fallback
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 *
 * @method static Builder<static>|Classes college()
 * @method static Builder<static>|Classes currentAcademicPeriod()
 * @method static Builder<static>|Classes forAcademicPeriod(string $schoolYear, int $semester)
 * @method static Builder<static>|Classes newModelQuery()
 * @method static Builder<static>|Classes newQuery()
 * @method static Builder<static>|Classes query()
 * @method static Builder<static>|Classes shs()
 *
 * @mixin \Eloquent
 */
final class Classes extends Model
{
    use BelongsToSchool;
    use HasAcademicPeriodScope;
    use HasFactory;
    use LogsActivity;
    use Searchable;

    protected $table = 'classes';

    protected $fillable = [
        'subject_id',
        'subject_code',
        'subject_ids',
        'faculty_id',
        'academic_year',
        'semester',
        'start_date',
        'schedule_id',
        'school_year',
        'course_codes',
        'section',
        'room_id',
        'classification',
        'maximum_slots',
        'shs_track_id',
        'shs_strand_id',
        'grade_level',
        'settings',
        'school_id',
    ];

    /**
     * Cached subjects collection to avoid duplicate queries.
     */
    private ?\Illuminate\Support\Collection $cachedSubjects = null;

    /**
     * Cached formatted course codes to avoid duplicate queries
     */
    private ?string $cachedFormattedCourseCodes = null;

    /**
     * Get default settings for a new class
     *
     * @return array<string, mixed>
     */
    public static function getDefaultSettings(): array
    {
        return [
            // Visual Customization
            'background_color' => '#ffffff',
            'accent_color' => '#3b82f6',
            'banner_image' => null,
            'theme' => 'default',

            // Feature Toggles
            'enable_announcements' => true,
            'enable_grade_visibility' => true,
            'enable_attendance_tracking' => false,
            'allow_late_submissions' => false,
            'enable_discussion_board' => false,

            // Custom Preferences
            'custom' => [],
        ];
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
                'subject_code' => $this->subject_code,
                'section' => $this->section,
                'school_year' => $this->school_year,
                'semester' => (string) $this->semester,
                'classification' => $this->classification,
            ];
        }

        return [
            'id' => $this->id,
            'record_title' => $this->record_title,
            'subject_code' => $this->subject_code,
            'subject_title' => $this->subject_title,
            'section' => $this->section,
            'school_year' => $this->school_year,
            'semester' => (string) $this->semester,
            'classification' => $this->classification,
            'faculty' => $this->faculty_full_name,
        ];
    }

    /**
     * Modify the database search query for Scout database driver.
     * This ensures only actual database columns are used in the WHERE clause.
     */
    public function scoutDatabaseQuery($query): Builder
    {
        return $query->select('classes.*')
            ->leftJoin('subjects', 'classes.subject_id', '=', 'subjects.id')
            ->leftJoin('faculties', 'classes.faculty_id', '=', 'faculties.id');
    }

    /**
     * Configure activity logging options for this model
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['faculty_id', 'section', 'semester', 'school_year', 'maximum_slots', 'subject_code'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "Class {$this->record_title} was {$eventName}")
            ->useLogName('classes');
    }

    public function class_enrollments()
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id', 'id');
    }

    /**
     * Get class enrollments scoped to the current academic period.
     * Delegates to ClassEnrollment's currentAcademicPeriod scope.
     */
    public function currentEnrollments()
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id', 'id')
            ->currentAcademicPeriod();
    }

    public function Subject()
    {
        // Use subject_id relationship if available, otherwise fallback to code
        return $this->belongsTo(Subject::class, 'subject_id', 'id');
    }

    public function SubjectByCodeFallback()
    {
        return $this->belongsTo(Subject::class, 'subject_code', 'code');
    }

    public function SubjectById()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id');
    }

    public function SubjectByCode()
    {
        return $this->belongsTo(Subject::class, 'subject_code', 'code');
    }

    public function ShsSubject()
    {
        return $this->belongsTo(StrandSubject::class, 'subject_code', 'code');
    }

    public function ShsTrack()
    {
        return $this->belongsTo(ShsTrack::class, 'shs_track_id', 'id');
    }

    public function ShsStrand()
    {
        return $this->belongsTo(ShsStrand::class, 'shs_strand_id', 'id');
    }

    public function Faculty(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'faculty_id', 'id');
    }

    public function Room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }

    public function Schedule()
    {
        return $this->hasMany(Schedule::class, 'class_id', 'id');
    }

    public function ClassStudents()
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id', 'id');
    }

    public function enrollments()
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    public function attendanceSessions()
    {
        return $this->hasMany(ClassAttendanceSession::class, 'class_id');
    }

    public function classPosts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClassPost::class, 'class_id');
    }

    /**
     * The courses this class belongs to
     */
    public function courses(): Relations\ArrayBasedRelation
    {
        return new Relations\ArrayBasedRelation($this, Course::class, 'course_codes', 'id');
    }

    /**
     * Get the subjects associated with this class (multiple subjects support)
     * This is an accessor method, not a relationship.
     * Results are cached per instance to avoid duplicate queries.
     */
    public function getSubjectsAttribute()
    {
        if ($this->cachedSubjects instanceof \Illuminate\Support\Collection) {
            return $this->cachedSubjects;
        }

        if (empty($this->subject_ids) || ! is_array($this->subject_ids)) {
            $this->cachedSubjects = collect();

            return $this->cachedSubjects;
        }

        // Filter out any null or empty values and remove duplicates
        $validSubjectIds = array_unique(array_filter($this->subject_ids, fn ($id): bool => ! empty($id)));

        if ($validSubjectIds === []) {
            $this->cachedSubjects = collect();

            return $this->cachedSubjects;
        }

        $this->cachedSubjects = Subject::query()->whereIn('id', $validSubjectIds)->get();

        return $this->cachedSubjects;
    }

    /**
     * Get a display title for this class (used in global search and record title)
     */
    public function getRecordTitleAttribute(): string
    {
        $subjects = $this->subjects;

        if (! $subjects->isEmpty()) {
            // Multiple subjects: show all unique subject codes
            $codes = $subjects->pluck('code')->filter()->unique()->implode(', ');
            if ($codes) {
                return $codes.' - '.$this->section;
            }
        }

        // Fallback to subject_code field
        if ($this->subject_code) {
            return $this->subject_code.' - '.$this->section;
        }

        // Last resort: use section only
        return $this->section ?? 'Class #'.$this->id;
    }

    /**
     * Check if this class is for College students
     */
    public function isCollege(): bool
    {
        return $this->classification === 'college' || empty($this->classification);
    }

    /**
     * Check if this class is for SHS students
     */
    public function isShs(): bool
    {
        return $this->classification === 'shs';
    }

    public function getActiveSubjectAttribute()
    {
        if ($this->isShs()) {
            return $this->ShsSubject;
        }

        // Use subject_id relationship if available, otherwise fallback to code
        if ($this->subject_id) {
            return $this->Subject;
        }

        return $this->SubjectByCodeFallback;
    }

    public function getScheduleDetails($seletedCourse, $selectedAcademicYear)
    {
        return Schedule::query()->whereHas('class', function ($query) use (
            $seletedCourse,
            $selectedAcademicYear
        ): void {
            $query
                ->where('academic_year', $selectedAcademicYear)
                ->whereHas('subject', function ($subQuery) use (
                    $seletedCourse
                ): void {
                    $subQuery->where('course_id', $seletedCourse->id);
                });
        })->get();
    }

    /**
     * Get a specific setting value with optional default
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    protected static function boot(): void
    {
        parent::boot();

        // Auto-populate subject_code when it's null
        self::saving(function ($model): void {
            if (empty($model->subject_code)) {
                $model->subject_code = $model->getSubjectCodeFromRelationships();
            }
        });

        // Also ensure it's populated after relationships are loaded
        self::retrieved(function ($model): void {
            if (empty($model->subject_code)) {
                $subjectCode = $model->getSubjectCodeFromRelationships();
                if ($subjectCode && $subjectCode !== 'N/A') {
                    $model->subject_code = $subjectCode;
                    $model->saveQuietly(); // Save without triggering events
                }
            }
        });

        self::deleting(function ($model): void {
            $model->Schedule()->delete();
            $model->ClassStudents()->delete();
        });
    }

    /**
     * Get the subject with fallback to code-based lookup
     */
    protected function subjectWithFallback(): Attribute
    {
        return Attribute::make(get: function () {
            // First try the direct relationship
            if ($this->subject_id) {
                $subject = $this->belongsTo(Subject::class, 'subject_id', 'id')->first();
                if ($subject) {
                    return $subject;
                }
            }

            // Fallback to code-based lookup
            if ($this->subject_code) {
                return Subject::query()->where('code', $this->subject_code)->first();
            }

            return null;
        });
    }

    protected function scheduleDays(): Attribute
    {
        return Attribute::make(get: fn () => $this->Schedule->pluck('day_of_week')->toArray());
    }

    protected function scheduleRooms(): Attribute
    {
        return Attribute::make(get: fn () => $this->Schedule->pluck('rooms')->toArray());
    }

    protected function classSubjectTitle(): Attribute
    {
        return Attribute::make(get: fn () => $this->Subject->title);
    }

    protected function facultyFullName(): Attribute
    {
        return Attribute::make(get: fn () => $this->Faculty->full_name ?? 'N/A');
    }

    protected function assignedRoomIDs(): Attribute
    {
        return Attribute::make(get: fn () => $this->Schedule->pluck('room_id')->toArray());
    }

    protected function assignedRooms(): Attribute
    {
        return Attribute::make(get: fn () => Room::query()->whereIn('id', $this->assigned_room_ids)
            ->pluck('name')
            ->toArray());
    }

    protected function studentCount(): Attribute
    {
        return Attribute::make(get: fn () => $this->ClassStudents->count());
    }

    /**
     * Get the formatted course codes for display
     */
    protected function formattedCourseCodes(): Attribute
    {
        return Attribute::make(get: function () {
            if (empty($this->course_codes)) {
                return 'N/A';
            }

            // Return cached value if available
            if ($this->cachedFormattedCourseCodes !== null) {
                return $this->cachedFormattedCourseCodes;
            }

            $result = $this->courses()->map(function ($course): string {
                // Extract just the course abbreviation from the code
                // e.g., "BSBA (2018 - 2019) NON-ABM" becomes "BSBA"
                $parts = explode('(', (string) $course->code);

                return mb_trim($parts[0]);
            })->unique()->join(', ');

            $this->cachedFormattedCourseCodes = $result;

            return $result;
        });
    }

    /**
     * Get the subject title with course codes for display
     */
    protected function subjectWithCourses(): Attribute
    {
        return Attribute::make(get: function () {
            $subjectTitle = $this->Subject?->title ?? $this->subject_code;
            $courseCodes = $this->formatted_course_codes;

            return ($courseCodes && $courseCodes !== 'N/A') ? sprintf('%s (%s)', $subjectTitle, $courseCodes) : $subjectTitle;
        });
    }

    /**
     * Get the appropriate subject based on classification
     */
    protected function activeSubject(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->isShs()) {
                return $this->ShsSubject;
            }

            // Use subject_id relationship if available, otherwise fallback to code
            if ($this->subject_id) {
                return $this->Subject;
            }

            return $this->SubjectByCodeFallback;
        });
    }

    /**
     * Get the subject title for both College and SHS
     */
    protected function subjectTitle(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->isShs()) {
                return $this->ShsSubject?->title ?? $this->subject_code;
            }

            // Use the active subject which handles the fallback logic
            return $this->getActiveSubjectAttribute()?->title ?? $this->subject_code;
        });
    }

    /**
     * Get formatted track/strand information for SHS classes
     */
    protected function formattedTrackStrand(): Attribute
    {
        return Attribute::make(get: function () {
            if (! $this->isShs()) {
                return null;
            }

            $track = $this->ShsTrack?->track_name;
            $strand = $this->ShsStrand?->strand_name;
            if ($track && $strand) {
                return sprintf('%s - %s', $track, $strand);
            }

            if ($track) {
                return $track;
            }

            return 'N/A';
        });
    }

    /**
     * Get display information based on class type (College courses or SHS track/strand)
     */
    protected function displayInfo(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->isShs()) {
                return $this->formatted_track_strand;
            }

            return $this->formatted_course_codes;
        });
    }

    /**
     * Scope a query to only include College classes
     */
    protected function scopeCollege(Builder $builder): Builder
    {
        return $builder->where(function ($q): void {
            $q->where('classification', 'college')
                ->orWhereNull('classification');
        });
    }

    /**
     * Scope a query to only include SHS classes
     */
    protected function scopeShs(Builder $builder): Builder
    {
        return $builder->where('classification', 'shs');
    }

    /**
     * @return mixed[]
     */
    protected function formattedWeeklySchedule(): Attribute
    {
        return Attribute::make(get: function (): array {
            $days = [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
            ];
            $scheduleByDay = $this->schedules->groupBy(fn ($schedule) => mb_strtolower((string) $schedule->day_of_week));
            $formattedSchedule = [];
            foreach ($days as $day) {
                $formattedSchedule[$day] = $scheduleByDay
                    ->get($day, collect())
                    ->map(fn ($schedule): array => [
                        'start_time' => $schedule->formatted_start_time,
                        'end_time' => $schedule->formatted_end_time,
                        'time_range' => $schedule->time_range,
                        'room' => [
                            'id' => $schedule->room_id,
                            'name' => $schedule->room?->name ?? 'TBA',
                        ],
                        'has_conflict' => false, // You could calculate this if needed
                    ]);
            }

            return $formattedSchedule;
        });
    }

    protected function casts(): array
    {
        return [
            'subject_id' => 'int',
            'subject_ids' => 'array',
            'faculty_id' => 'string',
            'schedule_id' => 'int',
            'room_id' => 'int',
            'maximum_slots' => 'int',
            'course_codes' => 'array',
            'shs_track_id' => 'int',
            'shs_strand_id' => 'int',
            'settings' => 'array',
            'start_date' => 'date',
        ];
    }

    /**
     * Get subject code from relationships when subject_code field is null
     */
    private function getSubjectCodeFromRelationships(): ?string
    {
        // For SHS classes
        if ($this->isShs() && $this->ShsSubject) {
            return $this->ShsSubject->code;
        }

        // Try to get from subject_ids (multiple subjects) - return all codes
        if (! empty($this->subject_ids) && is_array($this->subject_ids)) {
            $subjects = $this->subjects;
            if (! $subjects->isEmpty()) {
                $codes = $subjects->pluck('code')->filter()->unique()->toArray();
                if (! empty($codes)) {
                    return implode(', ', $codes);
                }
            }
        }

        // Try to get from single subject relationship
        if ($this->subject_id && $this->Subject) {
            return $this->Subject->code;
        }

        return null;
    }
}
