<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasAcademicPeriodScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class ClassEnrollment
 *
 * @property-read Classes|null $class
 * @property-read Student|null $student
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
final class ClassEnrollment extends Model
{
    use BelongsToSchool;
    use HasAcademicPeriodScope;
    use HasFactory;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    protected $table = 'class_enrollments';

    protected $fillable = [
        'class_id',
        'student_id',
        'completion_date',
        'status',
        'remarks',
        'prelim_grade',
        'midterm_grade',
        'finals_grade',
        'total_average',
        'is_grades_finalized',
        'is_grades_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
        'school_id',
    ];

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

    protected function casts(): array
    {
        return [
            'class_id' => 'int',
            'student_id' => 'int',
            'completion_date' => 'datetime',
            'status' => 'bool',
            'prelim_grade' => 'float',
            'midterm_grade' => 'float',
            'finals_grade' => 'float',
            'total_average' => 'float',
            'is_grades_finalized' => 'boolean',
            'is_grades_verified' => 'boolean',
            'verified_by' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}
