<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Contracts\RecordsActivity;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasAcademicPeriodScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Searchable;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Class ClassEnrollment
 *
 * @property-read Classes|null $class
 * @property-read Student|null $student
 * @property-read User|null $verifier
 *
 * @method static Builder<static>|ClassEnrollment newModelQuery()
 * @method static Builder<static>|ClassEnrollment newQuery()
 * @method static Builder<static>|ClassEnrollment onlyTrashed()
 * @method static Builder<static>|ClassEnrollment query()
 * @method static Builder<static>|ClassEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ClassEnrollment currentAcademicPeriod()
 * @method static Builder<static>|ClassEnrollment forAcademicPeriod(string $schoolYear, int $semester)
 *
 * @mixin \Eloquent
 */
final class ClassEnrollment extends Model implements RecordsActivity
{
    use BelongsToSchool;
    use HasAcademicPeriodScope;
    use HasFactory;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    public const string STATUS_ENROLLED = 'enrolled';

    public const string STATUS_DROPPED = 'dropped';

    public const string STATUS_WITHDRAWN = 'withdrawn';

    public const string STATUS_INCOMPLETE = 'incomplete';

    public const string STATUS_AUDIT = 'audit';

    /** @var list<string> */
    public const array ENROLLMENT_STATUSES = [
        self::STATUS_ENROLLED,
        self::STATUS_DROPPED,
        self::STATUS_WITHDRAWN,
        self::STATUS_INCOMPLETE,
        self::STATUS_AUDIT,
    ];

    /** @var list<string> */
    public const array ACTIVE_ENROLLMENT_STATUSES = [
        self::STATUS_ENROLLED,
        self::STATUS_INCOMPLETE,
        self::STATUS_AUDIT,
    ];

    #[Override]
    protected $table = 'class_enrollments';

    #[Override]
    protected $fillable = [
        'class_id',
        'student_id',
        'completion_date',
        'status',
        'enrollment_status',
        'status_reason',
        'remarks',
        'prelim_grade',
        'midterm_grade',
        'finals_grade',
        'total_average',
        'grading_policy_version_id',
        'grading_components',
        'grade_symbol',
        'grade_outcome',
        'grade_quality_points',
        'is_grades_finalized',
        'is_grades_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
        'school_id',
    ];

    public static function statusBooleanFor(string $enrollmentStatus): bool
    {
        return in_array($enrollmentStatus, self::ACTIVE_ENROLLMENT_STATUSES, true);
    }

    /** Match resolvedEnrollmentStatus() for records eligible to receive schedule notices. */
    public static function applyActiveForNotices(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        return $query->where(function ($active): void {
            $active->where(function ($explicit): void {
                $explicit->whereIn('class_enrollments.enrollment_status', self::ACTIVE_ENROLLMENT_STATUSES)
                    ->where(function ($status): void {
                        $status->whereNull('class_enrollments.status')
                            ->orWhere('class_enrollments.status', true);
                    });
            })->orWhere(function ($legacy): void {
                $legacy->where(function ($status): void {
                    $status->whereNull('class_enrollments.enrollment_status')
                        ->orWhereNotIn('class_enrollments.enrollment_status', self::ENROLLMENT_STATUSES);
                })->where('class_enrollments.status', true);
            });
        });
    }

    public function resolvedEnrollmentStatus(): string
    {
        $status = $this->enrollment_status;

        if ($this->status === false) {
            return $status === self::STATUS_WITHDRAWN
                ? self::STATUS_WITHDRAWN
                : self::STATUS_DROPPED;
        }

        if (is_string($status) && in_array($status, self::ENROLLMENT_STATUSES, true)) {
            return $status;
        }

        return $this->status ? self::STATUS_ENROLLED : self::STATUS_DROPPED;
    }

    public function scopeActiveForNotices(Builder $query): Builder
    {
        self::applyActiveForNotices($query);

        return $query;
    }

    public function isGradeProtected(): bool
    {
        return (bool) $this->is_grades_verified
            || (bool) $this->is_grades_finalized
            || $this->prelim_grade !== null
            || $this->midterm_grade !== null
            || $this->finals_grade !== null
            || $this->total_average !== null;
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'remarks' => $this->remarks,
        ];
    }

    /**
     * Configure activity logging options for this model
     * Tracks: new enrollments, class changes (section moves), and grade updates
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'class_id',       // Track when student moves to different class/section
                'student_id',     // Track which student
                'prelim_grade',
                'midterm_grade',
                'finals_grade',
                'is_grades_finalized',
                'status',
                'enrollment_status',
                'status_reason',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "Enrollment was {$eventName}")
            ->useLogName('class_enrollments');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id', 'id');
    }

    /**
     * Override: ClassEnrollment has no school_year/semester columns of its own.
     * Scope via the related class instead.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentAcademicPeriod(Builder $query): Builder
    {
        /** @var \App\Services\GeneralSettingsService $service */
        $service = app(\App\Services\GeneralSettingsService::class);

        return $this->scopeForAcademicPeriod(
            $query,
            $service->getCurrentSchoolYearString(),
            $service->getCurrentSemester(),
        );
    }

    /**
     * Override: scope via the related class's school_year and semester columns.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAcademicPeriod(Builder $query, string $schoolYear, int $semester): Builder
    {
        $variants = $this->resolveSchoolYearVariants($schoolYear);

        return $query->whereHas('class', function (Builder $classQuery) use ($variants, $semester): void {
            $classQuery->whereIn('school_year', $variants)
                ->where('semester', $semester);
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    /**
     * The staff member who verified this enrollment's grades.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<GradingPolicyVersion, $this> */
    public function gradingPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(GradingPolicyVersion::class);
    }

    protected function casts(): array
    {
        return [
            'class_id' => 'int',
            'student_id' => 'int',
            'completion_date' => 'datetime',
            'status' => 'bool',
            'prelim_grade' => 'decimal:2',
            'midterm_grade' => 'decimal:2',
            'finals_grade' => 'decimal:2',
            'total_average' => 'decimal:2',
            'grading_policy_version_id' => 'integer',
            'grading_components' => 'array',
            'grade_quality_points' => 'decimal:2',
            'is_grades_finalized' => 'boolean',
            'is_grades_verified' => 'boolean',
            'verified_by' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}
