<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Eloquent;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;

/**
 * Class Account
 *
 * @property-read Model|Eloquent $UserPerson
 * @property-read Faculty|null $faculty
 * @property-read mixed $approved_pending_enrollment
 * @property-read mixed $is_faculty
 * @property-read mixed $is_student
 * @property-read mixed $profile_photo_url
 * @property-read Model|Eloquent $person
 * @property-read ShsStudent|null $shsStudent
 * @property-read Student|null $student
 *
 * @method static Builder<static>|Account newModelQuery()
 * @method static Builder<static>|Account newQuery()
 * @method static Builder<static>|Account onlyTrashed()
 * @method static Builder<static>|Account query()
 * @method static Builder<static>|Account withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Account withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Account extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'person_id',
        'person_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    // protected $appends = [
    //     'profile_photo_url',
    // ];

    /**
     * Get the team that the invitation belongs to.
     *
     * @return HasMany<Team, covariant $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->ownedTeamsBase();
    }

    public function person()
    {
        return $this->morphTo();
    }

    public function UserPerson()
    {
        return $this->morphTo();
    }

    /**
     * Get the student record if this user is a student.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'person_id', 'id');
    }

    /**
     * Get the faculty record if this user is a faculty.
     */
    public function faculty()
    {
        // Since Faculty uses UUID and accounts.person_id is bigint, we match by email
        return $this->belongsTo(Faculty::class, 'email', 'email');
    }

    /**
     * Get the SHS student record if this user is an SHS student.
     */
    public function shsStudent()
    {
        return $this->belongsTo(ShsStudent::class, 'person_id', 'student_lrn');
    }

    /**
     * Check if the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student' &&
               ($this->person_type === Student::class || $this->person_type === ShsStudent::class);
    }

    /**
     * Check if the user is a faculty member.
     */
    public function isFaculty(): bool
    {
        return $this->role === 'faculty' && $this->faculty()->exists();
    }

    /**
     * Get the person record (polymorphic relationship).
     */
    public function getPerson()
    {
        if ($this->person_type === Student::class) {
            return $this->student;
        }

        if ($this->person_type === Faculty::class) {
            return $this->faculty;
        }

        if ($this->person_type === ShsStudent::class) {
            return $this->shsStudent;
        }

        return null;
    }

    /**
     * Check if account has any linked person
     */
    public function hasLinkedPerson(): bool
    {
        if ($this->person_type === Faculty::class) {
            // For Faculty, check if email matches and person_type is set
            return ! empty($this->email) && ($this->person_type !== '' && $this->person_type !== '0');
        }

        // For other person types, check person_id and person_type
        return ! empty($this->person_id) && ! empty($this->person_type);
    }

    /**
     * Determine if the user can access the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Only allow access to the portal panel
        if ($panel->getId() !== 'portal') {
            return false;
        }

        // Only allow active accounts
        // You can add additional authorization logic here
        // For example, checking roles, email domains, etc.
        return (bool) $this->is_active;
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if (! $this->profile_photo_path) {
                return null;
            }

            // If profile_photo_path starts with http:// or https://, it's already a URL
            if (str_starts_with($this->profile_photo_path, 'http://') ||
                str_starts_with($this->profile_photo_path, 'https://')) {
                return $this->profile_photo_path;
            }

            // Otherwise get URL from S3
            return Storage::disk('s3')->url($this->profile_photo_path);
        });
    }

    protected function getIsStudentAttribute()
    {
        return $this->hasOne(Student::class, 'person_id');
    }

    protected function getIsFacultyAttribute()
    {
        return $this->hasOne(Faculty::class, 'person_id');
    }

    // public function getPhotoUrl(): Attribute
    // {
    //     return Attribute::get(fn() => $this->profile_photo_path
    //         ? Storage::disk('s3')->url($this->profile_photo_path)
    //         : null);
    // }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_login' => 'boolean',
            'is_notification_active' => 'boolean',
            'person_id' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
            'otp_activated_at' => 'datetime',
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user's approved pending enrollment if the user is a guest.
     */
    protected function approvedPendingEnrollment(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->role !== 'guest') {
                return null;
            }

            return PendingEnrollment::query()->where(function ($query): void {
                $query->whereJsonContains('data->email', $this->email)
                    ->orWhereJsonContains('data->enrollment_google_email', $this->email);
            })->where('status', 'approved')->first();
        });
    }
}
