<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Configures registration, late-enrollment, and add/drop windows for a term.
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $school_year
 * @property int $semester
 * @property \Illuminate\Support\Carbon|null $regular_starts_at
 * @property \Illuminate\Support\Carbon|null $regular_ends_at
 * @property \Illuminate\Support\Carbon|null $late_starts_at
 * @property \Illuminate\Support\Carbon|null $late_ends_at
 * @property \Illuminate\Support\Carbon|null $add_drop_deadline_at
 * @property string|float $late_fee_amount
 * @property string $late_fee_label
 * @property bool $auto_apply_late_fee
 * @property bool $enforce_windows
 * @property bool $is_manually_closed
 * @property string|null $notes
 * @property int|null $updated_by
 * @property-read User|null $updatedBy
 *
 * @method static Builder<static>|EnrollmentPeriod newModelQuery()
 * @method static Builder<static>|EnrollmentPeriod newQuery()
 * @method static Builder<static>|EnrollmentPeriod query()
 *
 * @mixin \Eloquent
 */
final class EnrollmentPeriod extends Model
{
    use BelongsToSchool;

    #[Override]
    protected $table = 'enrollment_periods';

    #[Override]
    protected $fillable = [
        'school_id',
        'school_year',
        'semester',
        'regular_starts_at',
        'regular_ends_at',
        'late_starts_at',
        'late_ends_at',
        'add_drop_deadline_at',
        'late_fee_amount',
        'late_fee_label',
        'auto_apply_late_fee',
        'enforce_windows',
        'is_manually_closed',
        'notes',
        'updated_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'regular_starts_at' => 'datetime',
            'regular_ends_at' => 'datetime',
            'late_starts_at' => 'datetime',
            'late_ends_at' => 'datetime',
            'add_drop_deadline_at' => 'datetime',
            'late_fee_amount' => 'decimal:2',
            'auto_apply_late_fee' => 'boolean',
            'enforce_windows' => 'boolean',
            'is_manually_closed' => 'boolean',
        ];
    }
}
