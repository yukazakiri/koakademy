<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $school_id
 * @property int|null $department_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $avatar_url
 * @property string|null $theme_color
 * @property-read bool $is_cashier
 * @property-read bool $is_dept_head
 * @property-read bool $is_registrar
 * @property-read bool $is_super_admin
 * @property-read string $view_title_course
 * @property-read array $viewable_courses
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, AdminTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read School|null $school
 * @property-read Department|null $department
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereAvatarUrl($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereSchoolId($value)
 * @method static Builder<static>|User whereDepartmentId($value)
 * @method static Builder<static>|User whereThemeColor($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication, HasPasskeys
{
    use BroadcastsEvents;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use InteractsWithPasskeys;
    use Notifiable;
    use Searchable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'school_id',
        'department_id',
        'avatar_url',
        'theme_color',
        'faculty_id_number',
        'record_id',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'bio',
        'website',
        'department',
        'position',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /**
     * Get the channels that event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(string $event): array
    {
        return match ($event) {
            'created' => [new \Illuminate\Broadcasting\PrivateChannel('administrators')],
            default => [],
        };
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(string $event): ?string
    {
        return match ($event) {
            'created' => 'UserCreated',
            default => null,
        };
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // return true;
        // Null safety check - if role is not set, deny access
        if (! $this->role) {
            return false;
        }

        // Allow access based on role hierarchy and specific permissions
        // Students and general users have limited access
        if ($this->role->isStudent() || $this->role->isFaculty()) {
            return false; // Students typically use a separate portal
        }
        // All other roles (faculty, staff, administration) can access the admin panel
        if ($this->role->isAdministrative()) {
            return true;
        }
        if ($this->role->isCashier()) {
            return true;
        }
        if ($this->role->isDeptHead()) {
            return true;
        }
        if ($this->role->isRegistrar()) {
            return true;
        }
        if ($this->role->isSuperAdmin()) {
            return true;
        }
        if ($this->role->isStudentServices()) {
            return true;
        }
        if ($this->role->isFinance()) {
            return true;
        }
        if ($this->role === UserRole::ITSupport) {
            return true;
        }
        if ($this->role === UserRole::HRManager) {
            return true;
        }
        if ($this->role === UserRole::AdministrativeAssistant) {
            return true;
        }
        if ($this->role === UserRole::Developer) {
            return true;
        }

        return $this->role === UserRole::Admin;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            if (str_starts_with($this->avatar_url, 'http')) {
                return $this->avatar_url;
            }

            return Storage::disk('r2')->url($this->avatar_url);
        }

        // Fall back to Gravatar
        $hash = md5(mb_strtolower(mb_trim($this->email)));

        return 'https://www.gravatar.com/avatar/'.$hash.'?d=mp&r=g&s=250';
    }

    /** @returns array<int, UserRole> */
    public function lowerRoles(): array
    {
        if (! $this->role) {
            return [];
        }

        return $this->role->getManageableRoles();
    }

    /** @returns bool */
    public function isLowerInRole(): bool
    {
        $currentUser = Auth::user();
        if (! $currentUser instanceof self) {
            return false;
        }

        // Check if current user can manage this user's role
        return in_array($this->role, $currentUser->lowerRoles());
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        // This method should return the user's saved app authentication recovery codes.

        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string> | null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        // This method should save the user's app authentication recovery codes.

        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /**
     * Check if user can manage another user
     */
    public function canManageUser(self $user): bool
    {
        if (! $this->role || ! $user->role) {
            return false;
        }

        return in_array($user->role, $this->lowerRoles());
    }

    /**
     * Check if user has higher authority than another user
     */
    public function hasHigherAuthorityThan(self $user): bool
    {
        if (! $this->role || ! $user->role) {
            return false;
        }

        return $this->role->getHierarchyLevel() > $user->role->getHierarchyLevel();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        // This method should return the user's saved app authentication secret.

        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        // This method should save the user's app authentication secret.

        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        // In a user's authentication app, each account can be represented by a "holder name".
        // If the user has multiple accounts in your app, it might be a good idea to use
        // their email address as then they are still uniquely identifiable.

        return $this->email;
    }

    /**
     * Get the user's role with safe enum casting
     */
    public function getRoleAttribute($value): UserRole
    {
        if ($value === null || $value === '') {
            return UserRole::User;
        }

        return UserRole::tryFrom($value) ?? UserRole::User;
    }

    /**
     * Set the role attribute with validation
     */
    public function setRoleAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['role'] = UserRole::User->value;

            return;
        }

        // If it's already a UserRole enum, get its value
        if ($value instanceof UserRole) {
            $this->attributes['role'] = $value->value;

            return;
        }

        // Validate the string value and fallback to User if invalid
        $enum = UserRole::tryFrom($value);
        $this->attributes['role'] = ($enum ?? UserRole::User)->value;
    }

    /**
     * Override attributesToArray to handle role enum properly for Filament forms
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        // Convert role enum to string value for forms
        if (isset($attributes['role']) && $attributes['role'] instanceof UserRole) {
            $attributes['role'] = $attributes['role']->value;
        }

        return $attributes;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AdminTransaction::class, 'admin_id', 'id');
    }

    public function helpTickets(): HasMany
    {
        return $this->hasMany(HelpTicket::class);
    }

    /**
     * Get the school that this user belongs to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<School, self>
     */
    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id', 'id');
    }

    /**
     * Get all organizations (schools) this user has access to.
     * This supports multi-organization membership for users who work across multiple schools.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<School, self>
     */
    public function organizations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(School::class, 'organization_user', 'user_id', 'school_id')
            ->withPivot(['role', 'is_primary', 'is_active', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Get the primary organization for this user.
     */
    public function primaryOrganization(): ?School
    {
        // First check for explicitly marked primary org
        $primary = $this->organizations()->wherePivot('is_primary', true)->first();

        if ($primary) {
            return $primary;
        }

        // Fallback to the user's school_id
        return $this->school;
    }

    /**
     * Add user to an organization.
     */
    public function addToOrganization(School|int $school, array $attributes = []): void
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        $this->organizations()->syncWithoutDetaching([
            $schoolId => array_merge([
                'is_active' => true,
                'is_primary' => false,
            ], $attributes),
        ]);
    }

    /**
     * Remove user from an organization.
     */
    public function removeFromOrganization(School|int $school): void
    {
        $schoolId = $school instanceof School ? $school->id : $school;
        $this->organizations()->detach($schoolId);
    }

    /**
     * Check if user has access to a specific organization.
     */
    public function hasAccessToOrganization(School|int $school): bool
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        // Check primary school
        if ($this->school_id === $schoolId) {
            return true;
        }

        // Check organization memberships
        return $this->organizations()
            ->wherePivot('is_active', true)
            ->where('schools.id', $schoolId)
            ->exists();
    }

    /**
     * Get the department that this user belongs to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Department, self>
     */
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * Check if user belongs to a specific school
     */
    public function belongsToSchool(School|int $school): bool
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        return $this->school_id === $schoolId;
    }

    /**
     * Check if user belongs to a specific department
     */
    public function belongsToDepartment(Department|int $department): bool
    {
        $departmentId = $department instanceof Department ? $department->id : $department;

        return $this->department_id === $departmentId;
    }

    /**
     * Check if user can manage users in the same or lower organizational level
     */
    public function canManageInOrganization(self $user): bool
    {
        // System administrators can manage anyone
        if ($this->role === UserRole::Developer || $this->role === UserRole::Admin) {
            return true;
        }

        // Presidents can manage anyone in the university
        if ($this->role === UserRole::President) {
            return in_array($user->role, $this->role->getManageableRoles());
        }

        // Vice presidents can manage anyone except presidents and system admins
        if ($this->role === UserRole::VicePresident) {
            return in_array($user->role, $this->role->getManageableRoles()) &&
                   ! in_array($user->role, [UserRole::President, UserRole::Developer, UserRole::Admin]);
        }

        // Deans can only manage users in their school
        if ($this->role === UserRole::Dean || $this->role === UserRole::AssociateDean) {
            if (! $this->school_id || $this->school_id !== $user->school_id) {
                return false;
            }
            // Get manageable roles for this specific role
            $manageableRoles = $this->role->getManageableRoles();

            return in_array($user->role, $manageableRoles);
        }

        // Department heads can only manage users in their department
        if ($this->role === UserRole::DepartmentHead) {
            if (! $this->department_id || $this->department_id !== $user->department_id) {
                return false;
            }

            return in_array($user->role, $this->role->getManageableRoles());
        }

        // For other roles, use the basic role-based management
        return $this->canManageUser($user);
    }

    /**
     * Get organizational context string
     */
    public function getOrganizationalContextAttribute(): string
    {
        $context = [];

        if ($this->school) {
            $context[] = $this->school->name;
        }

        if ($this->department) {
            $context[] = $this->department->name;
        }

        return empty($context) ? 'No organizational assignment' : implode(' > ', $context);
    }

    public function isSuperAdmin(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->hasRoles([UserRole::Developer, UserRole::SuperAdmin, UserRole::Admin]));
    }

    /**
     * Check if user has administrative privileges
     */
    public function isAdministrative(): bool
    {
        return $this->role?->isAdministrative() ?? false;
    }

    /**
     * Check if user is faculty
     */
    public function isFaculty(): bool
    {
        return $this->role?->isFaculty() ?? false;
    }

    /**
     * Check if user handles student services
     */
    public function isStudentServices(): bool
    {
        return $this->role?->isStudentServices() ?? false;
    }

    /**
     * Check if user handles finance
     */
    public function isFinance(): bool
    {
        return $this->role?->isFinance() ?? false;
    }

    /**
     * Check if user is a student
     */
    public function isStudentRole(): bool
    {
        return $this->role?->isStudent() ?? false;
    }

    public function hasEmailAuthentication(): bool
    {
        // This method should return true if the user has enabled email authentication.

        return (bool) $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        // This method should save whether or not the user has enabled email authentication.

        $this->has_email_authentication = $condition;
        $this->save();
    }

    public function sendPasswordResetNotification($token): void
    {
        ResetPasswordNotification::createUrlUsing(fn ($user, string $token): string => route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $this->notify(new ResetPasswordNotification($token));
    }

    public function canAccessAdminPortal(): bool
    {
        if ($this->isAdministrative()) {
            return true;
        }
        if ($this->isStudentServices()) {
            return true;
        }
        if ($this->isFinance()) {
            return true;
        }

        return $this->isSupportStaff();
    }

    public function isSupportStaff(): bool
    {
        if (! $this->role) {
            return false;
        }

        return in_array($this->role, [
            UserRole::ITSupport,
            UserRole::SecurityGuard,
            UserRole::MaintenanceStaff,
            UserRole::AdministrativeAssistant,
        ]);
    }

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
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
            // Note: role is handled manually via getRoleAttribute/setRoleAttribute
        ];
    }

    protected function isCashier(): Attribute
    {
        return Attribute::make(get: function (): bool {
            if (! $this->role) {
                return false;
            }

            // Use proper role instead of name detection
            return $this->role === UserRole::Cashier ||
                   ($this->role === UserRole::User && str_contains(mb_strtolower($this->name), 'cashier'));
        });
    }

    protected function isRegistrar(): Attribute
    {
        return Attribute::make(get: function (): bool {
            if (! $this->role) {
                return false;
            }

            // Use proper role instead of name detection
            return $this->role === UserRole::Registrar ||
                   $this->role === UserRole::AssistantRegistrar ||
                   ($this->role === UserRole::User && str_contains(mb_strtolower($this->name), 'registrar'));
        });
    }

    protected function isDeptHead(): Attribute
    {
        return Attribute::make(get: function (): bool {
            if (! $this->role) {
                return false;
            }

            // Use proper role instead of name detection
            $name = mb_strtolower($this->name);

            return $this->role === UserRole::DepartmentHead ||
                   $this->role === UserRole::ProgramChair ||
                   ($this->role === UserRole::User && (
                       str_contains($name, 'it-head-dept') ||
                       str_contains($name, 'ba-head-dept') ||
                       str_contains($name, 'hm-head-dept') ||
                       str_contains($name, 'head')
                   ));
        });
    }

    protected function viewableCourses(): Attribute
    {
        return Attribute::make(get: function (): array {
            if (! $this->role) {
                return [];
            }

            // System administrators and high-level roles can see all courses
            if (in_array($this->role, [UserRole::Developer, UserRole::Admin, UserRole::President, UserRole::VicePresident], true)) {
                return [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13];
            }

            // Deans can see courses in their college
            if ($this->role === UserRole::Dean || $this->role === UserRole::AssociateDean) {
                return [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13]; // For now, all courses
            }

            // Department heads can see courses in their department
            if ($this->role === UserRole::DepartmentHead || $this->role === UserRole::ProgramChair) {
                // TODO: Implement department-specific course filtering based on user's department
                return [1, 2, 3]; // Example: IT department courses
            }

            // Registrar and student services can see all courses
            if ($this->isStudentServices()) {
                return [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13];
            }

            // Faculty can see courses they teach
            if ($this->isFaculty()) {
                // TODO: Implement faculty-specific course filtering
                return [1, 2, 3]; // Example courses
            }

            // For regular users and students, return empty array or implement specific logic
            return [];
        });
    }

    protected function viewTitleCourse(): Attribute
    {
        return Attribute::make(get: function (): string {
            if (! $this->role) {
                return 'No Access';
            }

            if (in_array($this->role, [UserRole::Developer, UserRole::Admin, UserRole::President, UserRole::VicePresident], true)) {
                return 'All Departments';
            }

            if ($this->role === UserRole::Dean || $this->role === UserRole::AssociateDean) {
                return 'College Level Access';
            }

            if ($this->role === UserRole::DepartmentHead) {
                return 'Department Head Access';
            }

            if ($this->role === UserRole::ProgramChair) {
                return 'Program Chair Access';
            }

            if ($this->isStudentServices()) {
                return 'Student Services Access';
            }

            if ($this->isFaculty()) {
                return 'Faculty Access';
            }

            if ($this->isFinance()) {
                return 'Finance Access';
            }

            return 'Limited Access';
        });
    }
}
