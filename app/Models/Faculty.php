<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use Override;

/**
 * Class Faculty
 *
 * @property-read Account|null $account
 * @property-read Collection<int, ClassEnrollment> $classEnrollments
 * @property-read int|null $class_enrollments_count
 * @property-read Collection<int, Classes> $classes
 * @property-read int|null $classes_count
 * @property-read string $full_name
 * @property-read string $name
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static Builder<static>|Faculty newModelQuery()
 * @method static Builder<static>|Faculty newQuery()
 * @method static Builder<static>|Faculty query()
 *
 * @mixin \Eloquent
 */
final class Faculty extends Authenticatable implements FilamentUser, HasAvatar
{
    use BelongsToSchool;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use Searchable;

    #[Override]
    public $incrementing = false;

    #[Override]
    protected $table = 'faculty';

    #[Override]
    protected $fillable = [
        'id',
        'faculty_id_number',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'phone_number',
        'department',
        'position',
        'office_hours',
        'birth_date',
        'date_employed',
        'address_line1',
        'biography',
        'education',
        'courses_taught',
        'photo_url',
        'status',
        'gender',
        'age',
        'school_id',
        'department_id',
    ];

    #[Override]
    protected $hidden = [
        'password',
        'remember_token',
    ];

    #[Override]
    protected $primaryKey = 'id';

    #[Override]
    protected $keyType = 'string';

    private string $guard = 'faculty';

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'department' => $this->department,
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->photo_url) {
            return $this->photo_url;
        }

        // If no faculty photo, try to get from User record
        $user = User::where('email', $this->email)->first();
        if ($user && $user->avatar_url) {
            return $user->getFilamentAvatarUrl();
        }

        // Default to gravatar
        $hash = md5(mb_strtolower(mb_trim($this->email)));

        return 'https://www.gravatar.com/avatar/'.$hash.'?d=mp&r=g&s=250';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'faculty';
    }

    // Relationships

    /**
     * The owning department through the department_id foreign key.
     *
     * Preferred over departmentBelongsTo(), which joins the legacy free-text `department`
     * column to departments.code and so misses rows recorded with the department's full name.
     * Populated by 2026_10_01_120000_add_department_id_to_faculty_table.
     */
    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * @deprecated Use departmentRecord() instead. Kept because Filament, the MCP faculty tools
     *             and DigitalIdCardService still read the free-text `department` column.
     */
    public function departmentBelongsTo(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'code');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classes::class, 'faculty_id', 'id');
    }

    /**
     * Get only the classes assigned to this faculty member for the current academic period.
     */
    public function currentClasses(): HasMany
    {
        return $this->hasMany(Classes::class, 'faculty_id', 'id')
            ->currentAcademicPeriod();
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(FacultyDeadline::class, 'faculty_id', 'id');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(FacultyCustomFieldValue::class);
    }

    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class, 'email', 'email');
    }

    public function classEnrollments()
    {
        return $this->hasManyThrough(
            ClassEnrollment::class,
            Classes::class,
            'faculty_id',
            'class_id'
        );
    }

    /**
     * Get class enrollments for the current academic period across all this faculty's classes.
     */
    public function currentClassEnrollments()
    {
        return $this->hasManyThrough(
            ClassEnrollment::class,
            Classes::class,
            'faculty_id',
            'class_id'
        )->currentAcademicPeriod();
    }

    /**
     * Get the account associated with this faculty member
     * Since Faculty uses UUID and accounts.person_id is bigint, we match by email
     */
    public function account()
    {
        return $this->hasOne(Account::class, 'email', 'email')
            ->where('person_type', self::class);
    }

    /**
     * Keep department_id in step with the legacy free-text `department` column.
     *
     * Writers still submit only that string: StoreFacultyRequest, the Filament faculty form
     * and API requests, AdministratorFacultyManagementController::store(), the profile editor
     * and FacultyBulkImportService. Syncing here rather than at each of those means a faculty
     * member created or reassigned after the department_id migration still lands in
     * Department::faculty() and the academic, HR and executive desk counts.
     *
     * The string is left as the author wrote it, because Filament, the MCP tools,
     * DigitalIdCardService and Scout all still read and display it.
     */
    #[Override]
    protected static function booted(): void
    {
        self::saving(function (self $faculty): void {
            // A foreign key the caller set in this same request is authoritative and never
            // derived away, whether the string also moved or not.
            if ($faculty->isDirty('department_id')) {
                return;
            }

            // Otherwise the free-text column is the input, so re-resolve whenever it changes.
            // This is what makes a reassignment move the faculty to the new department's count
            // rather than leaving the foreign key from the previous value.
            if (! $faculty->isDirty('department')) {
                return;
            }

            $raw = is_string($faculty->department) ? mb_trim($faculty->department) : '';

            $faculty->department_id = $raw === ''
                ? null
                : static::resolveDepartmentId($raw, $faculty->school_id);
        });
    }

    /**
     * Get the full URL for the profile photo
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return null;
                }

                // If it's already a full URL, return as is
                if (str_starts_with($value, 'http')) {
                    return $value;
                }

                // Otherwise, treat it as a path relative to storage
                return Storage::url($value);
            }
        );
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(get: function (): string {
            $name = mb_trim(sprintf('%s, %s %s', $this->last_name, $this->first_name, $this->middle_name));

            return $name !== '' && $name !== '0' ? $name : 'N/A';
            // Return 'N/A' if the name is empty
        });
    }

    protected function name(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->fullName);
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'faculty_id_number' => 'string',
            'first_name' => 'string',
            'last_name' => 'string',
            'middle_name' => 'string',
            'email' => 'string',
            'department_id' => 'integer',
            'phone_number' => 'string',
            'department' => 'string',
            'position' => 'string',
            'office_hours' => 'string',
            'birth_date' => 'datetime',
            'date_employed' => 'date',
            'address_line1' => 'string',
            'biography' => 'string',
            'education' => 'string',
            'courses_taught' => 'string',
            'photo_url' => 'string',
            'status' => 'string',
            'gender' => 'string',
            'age' => 'int',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Resolve a free-text department to an id, preferring an exact code match over the name.
     *
     * Same precedence as the backfill in
     * 2026_10_01_120000_add_department_id_to_faculty_table, so a row resolved at write time
     * agrees with the same value resolved at migration time.
     */
    private static function resolveDepartmentId(string $value, ?int $schoolId): ?int
    {
        $normalized = mb_strtoupper($value);

        $query = Department::query()
            ->where(function ($q) use ($normalized): void {
                $q->whereRaw('UPPER(TRIM(code)) = ?', [$normalized])
                    ->orWhereRaw('UPPER(TRIM(name)) = ?', [$normalized]);
            });

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderByRaw('case when UPPER(TRIM(code)) = ? then 0 else 1 end', [$normalized])
            ->value('id');
    }
}
