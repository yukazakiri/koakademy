<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $dean_name
 * @property string|null $dean_email
 * @property string|null $location
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Department> $departments
 * @property-read int|null $departments_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Collection<int, Faculty> $faculty
 * @property-read int|null $faculty_count
 *
 * @method static SchoolFactory factory($count = null, $state = [])
 * @method static Builder<static>|School newModelQuery()
 * @method static Builder<static>|School newQuery()
 * @method static Builder<static>|School query()
 * @method static Builder<static>|School whereCode($value)
 * @method static Builder<static>|School whereCreatedAt($value)
 * @method static Builder<static>|School whereDeanEmail($value)
 * @method static Builder<static>|School whereDeanName($value)
 * @method static Builder<static>|School whereDescription($value)
 * @method static Builder<static>|School whereEmail($value)
 * @method static Builder<static>|School whereId($value)
 * @method static Builder<static>|School whereIsActive($value)
 * @method static Builder<static>|School whereLocation($value)
 * @method static Builder<static>|School whereMetadata($value)
 * @method static Builder<static>|School whereName($value)
 * @method static Builder<static>|School wherePhone($value)
 * @method static Builder<static>|School whereUpdatedAt($value)
 * @method static Builder<static>|School active()
 *
 * @mixin \Eloquent
 */
final class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'schools';

    protected $fillable = [
        'name',
        'code',
        'description',
        'dean_name',
        'dean_email',
        'location',
        'phone',
        'email',
        'is_active',
        'metadata',
    ];

    protected $primaryKey = 'id';

    /**
     * Get all departments belonging to this school
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'school_id', 'id');
    }

    /**
     * Get all active departments belonging to this school
     */
    public function activeDepartments(): HasMany
    {
        return $this->departments()->where('is_active', true);
    }

    /**
     * Get all users belonging to this school
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'school_id', 'id');
    }

    /**
     * Get all faculty members belonging to this school
     * Note: This assumes faculty will be updated to have school relationships
     */
    public function faculty(): HasMany
    {
        return $this->hasMany(Faculty::class, 'school_id', 'id');
    }

    /**
     * Get all courses belonging to departments in this school
     */
    public function courses()
    {
        return $this->hasManyThrough(
            Course::class,
            Department::class,
            'school_id', // Foreign key on departments table
            'department_id', // Foreign key on courses table (will need to be added)
            'id', // Local key on schools table
            'id' // Local key on departments table
        );
    }

    /**
     * Get the dean user if exists
     */
    public function dean()
    {
        if (! $this->dean_email) {
            return null;
        }

        return User::where('email', $this->dean_email)
            ->where('school_id', $this->id)
            ->whereIn('role', ['dean', 'associate_dean'])
            ->first();
    }

    /**
     * Scope to get only active schools
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get departments count
     */
    public function getDepartmentsCount(): int
    {
        return $this->departments()->count();
    }

    /**
     * Get active departments count
     */
    public function getActiveDepartmentsCount(): int
    {
        return $this->activeDepartments()->count();
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
        return $this->faculty()->count();
    }

    /**
     * Get full school name with code
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    /**
     * Check if school has any active departments
     */
    public function hasActiveDepartments(): bool
    {
        return $this->activeDepartments()->exists();
    }

    /**
     * Get all administrative users for this school
     */
    public function administrators()
    {
        return $this->users()
            ->whereIn('role', [
                'dean',
                'associate_dean',
                'department_head',
                'program_chair',
            ]);
    }

    /**
     * Get all faculty users for this school
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
     * Boot method for model events
     */
    protected static function boot(): void
    {
        parent::boot();

        // Ensure code is uppercase when creating/updating
        self::creating(function (School $school): void {
            $school->code = mb_strtoupper($school->code);
        });

        self::updating(function (School $school): void {
            $school->code = mb_strtoupper($school->code);
        });

        // When deleting a school, handle related records
        self::deleting(function (School $school): void {
            if (! $school->isForceDeleting()) {
                return;
            }

            // Set users' school_id to null instead of deleting users
            $school->users()->update(['school_id' => null]);

            // Departments will be cascade deleted due to foreign key constraint
            // But we should also handle users in those departments
            $school->departments()->each(function (Department $department): void {
                $department->users()->update(['department_id' => null]);
            });
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
