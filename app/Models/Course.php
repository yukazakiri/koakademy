<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

/**
 * Class Course
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $units
 * @property string|null $lec_per_unit
 * @property string|null $lab_per_unit
 * @property int $year_level
 * @property int $semester
 * @property string|null $school_year
 * @property string|null $miscellaneous
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $course_code
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read Collection<int, Student> $students
 * @property-read int|null $students_count
 * @property-read Collection<int, Subject> $subjects
 * @property-read int|null $subjects_count
 *
 * @method static Builder<static>|Course newModelQuery()
 * @method static Builder<static>|Course newQuery()
 * @method static Builder<static>|Course query()
 * @method static Builder<static>|Course whereCode($value)
 * @method static Builder<static>|Course whereCreatedAt($value)
 * @method static Builder<static>|Course whereDescription($value)
 * @method static Builder<static>|Course whereId($value)
 * @method static Builder<static>|Course whereIsActive($value)
 * @method static Builder<static>|Course whereLabPerUnit($value)
 * @method static Builder<static>|Course whereLecPerUnit($value)
 * @method static Builder<static>|Course whereMiscellaneous($value)
 * @method static Builder<static>|Course whereSchoolYear($value)
 * @method static Builder<static>|Course whereSemester($value)
 * @method static Builder<static>|Course whereTitle($value)
 * @method static Builder<static>|Course whereUnits($value)
 * @method static Builder<static>|Course whereUpdatedAt($value)
 * @method static Builder<static>|Course whereYearLevel($value)
 *
 * @mixin \Eloquent
 */
final class Course extends Model
{
    use BelongsToSchool;
    use HasFactory;
    use Searchable;

    protected $table = 'courses';

    protected $fillable = [
        'code',
        'title',
        'description',
        'department_id',
        'units',
        'lec_per_unit',
        'lab_per_unit',
        'year_level',
        'semester',
        'school_year',
        'curriculum_year',
        'miscellaneous',
        'miscelaneous',
        'remarks',
        'is_active',
        'school_id',
    ];

    protected $primaryKey = 'id';

    public static function getCourseDetails($courseId): string
    {
        $course = self::query()->with('department')->find($courseId);

        return $course ? sprintf('%s (Code: %s, Department: %s)', $course->title, $course->code, $course->department?->code ?? 'N/A') : 'Course details not available';
    }

    /**
     * Get all courses with their student counts
     */
    public static function getCoursesWithStudentCount()
    {
        return self::query()->select([
            'courses.id',
            'courses.code',
            'courses.title',
            'courses.department_id',
            DB::raw('COUNT(students.id) as student_count'),
        ])
            ->leftJoin('students', function ($join): void {
                $join->on('courses.id', '=', 'students.course_id')
                    ->whereNull('students.deleted_at');
            })
            ->groupBy('courses.id', 'courses.code', 'courses.title', 'courses.department_id')
            ->orderBy('courses.code')
            ->get();
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
            'code' => $this->code,
            'title' => $this->title,
            'department' => $this->department?->code,
        ];
    }

    /**
     * Get the department this course belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'course_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'course_id', 'id');
    }

    /**
     * Get all students enrolled in this course
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'course_id', 'id');
    }

    /**
     * Get the count of students enrolled in this course
     */
    public function studentCount()
    {
        return $this->hasMany(Student::class, 'course_id', 'id')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Get the appropriate miscellaneous fee based on curriculum year
     */
    public function getMiscellaneousFee(): int
    {
        // If the course has a specific miscellaneous fee set, use it
        if ($this->miscelaneous !== null) {
            return (int) $this->miscelaneous;
        }

        // Determine fee based on curriculum year
        return $this->getMiscellaneousFeeBasedOnCurriculumYear();
    }

    /**
     * Get miscellaneous fee based on curriculum year
     * New curriculum (2024-2025): 3700
     * Old curriculum (2018-2019): 3500
     */
    public function getMiscellaneousFeeBasedOnCurriculumYear(): int
    {
        if (empty($this->curriculum_year)) {
            return 3500; // Default to old curriculum fee
        }

        $curriculumYear = mb_strtolower(mb_trim($this->curriculum_year));

        // Check for new curriculum patterns
        if (str_contains($curriculumYear, '2024') && str_contains($curriculumYear, '2025')) {
            return 3700;
        }

        // Default to old curriculum fee for unrecognized patterns
        return 3500;
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $model->code = mb_strtoupper((string) $model->code);
        });

        self::deleting(function ($model): void {
            $model->subjects()->delete();
        });
    }

    protected function courseCode(): Attribute
    {
        return Attribute::make(get: fn () => mb_strtoupper((string) $this->attributes['code']));
    }

    protected function casts(): array
    {
        return [
            'units' => 'integer',
            'year_level' => 'integer',
            'semester' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
