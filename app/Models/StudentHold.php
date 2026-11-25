<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Cross-office administrative hold placed on a student record.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int $student_id
 * @property string $office
 * @property string $hold_type
 * @property string $severity
 * @property string $title
 * @property string $reason
 * @property bool $blocks_enrollment
 * @property bool $blocks_add_drop
 * @property bool $blocks_grade_release
 * @property string $status
 * @property int|null $placed_by
 * @property \Illuminate\Support\Carbon|null $placed_at
 * @property int|null $released_by
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property string|null $release_notes
 * @property int|null $overridden_by
 * @property \Illuminate\Support\Carbon|null $overridden_at
 * @property string|null $override_reason
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property-read Student|null $student
 * @property-read User|null $placedBy
 * @property-read User|null $releasedBy
 * @property-read User|null $overriddenBy
 *
 * @method static Builder<static>|StudentHold active()
 * @method static Builder<static>|StudentHold blockingEnrollment()
 * @method static Builder<static>|StudentHold blockingAddDrop()
 *
 * @mixin \Eloquent
 */
final class StudentHold extends Model
{
    use BelongsToSchool;
    use LogsActivity;

    public const string StatusActive = 'active';

    public const string StatusReleased = 'released';

    public const string StatusOverridden = 'overridden';

    public const string SeverityBlocking = 'blocking';

    public const string SeverityWarning = 'warning';

    /**
     * @var array<string, string>
     */
    public const array OFFICES = [
        'registrar' => 'Registrar Office',
        'accounting' => 'Accounting & Finance',
        'library' => 'Library Services',
        'dean' => 'Academic Dean / Department',
        'guidance' => 'Guidance & Student Affairs',
        'medical' => 'Clinic / Medical Office',
        'disciplinary' => 'Disciplinary Committee',
    ];

    #[Override]
    protected $table = 'student_holds';

    #[Override]
    protected $fillable = [
        'school_id',
        'student_id',
        'office',
        'hold_type',
        'severity',
        'title',
        'reason',
        'blocks_enrollment',
        'blocks_add_drop',
        'blocks_grade_release',
        'status',
        'placed_by',
        'placed_at',
        'released_by',
        'released_at',
        'release_notes',
        'overridden_by',
        'overridden_at',
        'override_reason',
        'expires_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'office',
                'severity',
                'title',
                'status',
                'blocks_enrollment',
                'blocks_add_drop',
                'release_notes',
                'override_reason',
            ])
            ->logOnlyDirty()
            ->useLogName('student_holds');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::StatusActive)
            ->where(function (Builder $expiry): void {
                $expiry->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBlockingEnrollment(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('blocks_enrollment', true)
            ->where('severity', self::SeverityBlocking);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBlockingAddDrop(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('blocks_add_drop', true)
            ->where('severity', self::SeverityBlocking);
    }

    public function officeLabel(): string
    {
        return self::OFFICES[$this->office] ?? ucfirst($this->office);
    }

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'blocks_enrollment' => 'boolean',
            'blocks_add_drop' => 'boolean',
            'blocks_grade_release' => 'boolean',
            'placed_at' => 'datetime',
            'released_at' => 'datetime',
            'overridden_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
