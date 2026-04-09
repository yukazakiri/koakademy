<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $head_name
 * @property string|null $head_email
 * @property string|null $location
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read School $school
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Collection<int, Faculty> $faculty
 * @property-read int|null $faculty_count
 * @property-read Collection<int, Course> $courses
 * @property-read int|null $courses_count
 *
 * @method static DepartmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Department newModelQuery()
 * @method static Builder<static>|Department newQuery()
 * @method static Builder<static>|Department query()
 * @method static Builder<static>|Department whereCode($value)
 * @method static Builder<static>|Department whereCreatedAt($value)
 * @method static Builder<static>|Department whereDescription($value)
 * @method static Builder<static>|Department whereEmail($value)
 * @method static Builder<static>|Department whereHeadEmail($value)
 * @method static Builder<static>|Department whereHeadName($value)
 * @method static Builder<static>|Department whereId($value)
 * @method static Builder<static>|Department whereIsActive($value)
 * @method static Builder<static>|Department whereLocation($value)
 * @method static Builder<static>|Department whereMetadata($value)
 * @method static Builder<static>|Department whereName($value)
 * @method static Builder<static>|Department wherePhone($value)
 * @method static Builder<static>|Department whereSchoolId($value)
 * @method static Builder<static>|Department whereUpdatedAt($value)
 * @method static Builder<static>|Department active()
 * @method static Builder<static>|Department forSchool(School|int $school)
 *
 * @mixin \Eloquent
 */
use Illuminate\Support\Carbon;

final class Department extends Model
{
    use BelongsToSchool;
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'head_name',
        'head_email',
        'location',
        'phone',
        'email',
        'is_active',
        'metadata',
    ];

    protected $primaryKey = 'id';

    /**
     * Get the school that this department belongs to
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id', 'id');
    }

    /**
     * Get all users belonging to this department
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id', 'id');
    }

    /**
     * Get all faculty members belonging to this department
     * Matches by both department code and name for flexibility
     */
    public function faculty()
    {
        return Faculty::where(function ($query): void {
            $query->where('department', $this->code)
                ->orWhere('department', $this->name);
        });
    }

    /**
     * Get all courses belonging to this department
     * Matches by both department code and name for flexibility
     */
    public function courses()
    {
        return Course::where(function ($query): void {
            $query->where('department', $this->code)
                ->orWhere('department', $this->name);
        });
    }

    /**
     * Get the department head user if exists
     */
    public function head()
    {
        if (! $this->head_email) {
            return null;
        }

        return User::where('email', $this->head_email)
            ->where('department_id', $this->id)
            ->whereIn('role', ['department_head', 'program_chair'])
            ->first();
    }

    /**
     * Scope to get only active departments
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter departments by school
     */
    public function scopeForSchool(Builder $query, School|int $school): Builder
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        return $query->where('school_id', $schoolId);
    }

    /**
     * Get users count
     */
    public function getUsersCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Get faculty count
     */
    public function getFacultyCount(): int
    {
        return Faculty::where('department', $this->code)
            ->orWhere('department', $this->name)
            ->count();
    }

    /**
     * Get courses count
     */
    public function getCoursesCount(): int
    {
        return Course::where('department', $this->code)
            ->orWhere('department', $this->name)
            ->count();
    }

    /**
     * Get full department name with code and school
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->code}) - {$this->school->name}";
    }

    /**
     * Get department name with code only
     */
    public function getNameWithCodeAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    /**
     * Get all administrative users for this department
     */
    public function administrators()
    {
        return $this->users()
            ->whereIn('role', [
                'department_head',
                'program_chair',
            ]);
    }

    /**
     * Get all faculty users for this department
     */
    public function facultyUsers()
    {
        return $this->users()
            ->whereIn('role', [
                'professor',
                'associate_professor',
                'assistant_professor',
                'instructor',
                'part_time_faculty',
            ]);
    }

    /**
     * Get all support staff users for this department
     */
    public function supportStaff()
    {
        return $this->users()
            ->whereIn('role', [
                'administrative_assistant',
                'it_support',
            ]);
    }

    /**
     * Check if department has any users
     */
    public function hasUsers(): bool
    {
        return $this->users()->exists();
    }

    /**
     * Check if department has any faculty
     */
    public function hasFaculty(): bool
    {
        return Faculty::where('department', $this->code)
            ->orWhere('department', $this->name)
            ->exists();
    }

    /**
     * Check if department has any courses
     */
    public function hasCourses(): bool
    {
        return Course::where('department', $this->code)
            ->orWhere('department', $this->name)
            ->exists();
    }

    /**
     * Boot method for model events
     */
    protected static function boot(): void
    {
        parent::boot();

        // Ensure code is uppercase when creating/updating
        self::creating(function (Department $department): void {
            $department->code = mb_strtoupper($department->code);
        });

        self::updating(function (Department $department): void {
            $department->code = mb_strtoupper($department->code);
        });

        // When deleting a department, handle related records
        self::deleting(function (Department $department): void {
            // Set users' department_id to null instead of deleting users
            $department->users()->update(['department_id' => null]);

            // Handle faculty records - set department field to null for faculty in this department (by code or name)
            Faculty::where('department', $department->code)
                ->orWhere('department', $department->name)
                ->update(['department' => null]);

            // Handle course records - set department field to null for courses in this department (by code or name)
            Course::where('department', $department->code)
                ->orWhere('department', $department->name)
                ->update(['department' => null]);
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
