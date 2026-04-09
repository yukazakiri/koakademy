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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

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

    public $incrementing = false;

    protected $table = 'faculty';

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
        'office_hours',
        'birth_date',
        'address_line1',
        'biography',
        'education',
        'courses_taught',
        'photo_url',
        'status',
        'gender',
        'age',
        'school_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $primaryKey = 'id';

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
    public function departmentBelongsTo(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'code');
    }

    public function classes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Classes::class, 'faculty_id', 'id');
    }

    /**
     * Get only the classes assigned to this faculty member for the current academic period.
     */
    public function currentClasses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Classes::class, 'faculty_id', 'id')
            ->currentAcademicPeriod();
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
            'phone_number' => 'string',
            'department' => 'string',
            'office_hours' => 'string',
            'birth_date' => 'datetime',
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
}
