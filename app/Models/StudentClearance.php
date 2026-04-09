<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $formatted_semester
 * @property-read Student|null $student
 *
 * @method static Builder<static>|StudentClearance newModelQuery()
 * @method static Builder<static>|StudentClearance newQuery()
 * @method static Builder<static>|StudentClearance query()
 *
 * @mixin \Eloquent
 */
final class StudentClearance extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year',
        'semester',
        'is_cleared',
        'remarks',
        'cleared_by',
        'cleared_at',
    ];

    /**
     * Create a new clearance record for the current semester.
     *
     * @param  mixed  $student
     */
    public static function createForCurrentSemester($student, ?GeneralSetting $generalSetting = null): self
    {
        $generalSetting ??= GeneralSetting::query()->first();

        return self::query()->create([
            'student_id' => $student->id,
            'academic_year' => $generalSetting->getSchoolYear(),
            'semester' => $generalSetting->semester,
            'is_cleared' => false,
        ]);
    }

    /**
     * Get the student that owns the clearance.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Mark the clearance as cleared.
     */
    public function markAsCleared(?string $clearedBy = null, ?string $remarks = null): bool
    {
        $this->is_cleared = true;
        $this->cleared_by = $clearedBy;
        $this->cleared_at = now();

        if (! in_array($remarks, [null, '', '0'], true)) {
            $this->remarks = $remarks;
        }

        return $this->save();
    }

    /**
     * Mark the clearance as not cleared.
     */
    public function markAsNotCleared(?string $remarks = null): bool
    {
        $this->is_cleared = false;
        $this->cleared_by = null;
        $this->cleared_at = null;

        if (! in_array($remarks, [null, '', '0'], true)) {
            $this->remarks = $remarks;
        }

        return $this->save();
    }

    /**
     * Get formatted semester name.
     */
    protected function formattedSemester(): Attribute
    {
        return Attribute::make(get: fn (): string => match ($this->semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            default => 'Unknown Semester',
        });
    }

    protected function casts(): array
    {
        return [
            'is_cleared' => 'boolean',
            'semester' => 'integer',
            'cleared_at' => 'datetime',
        ];
    }
}
