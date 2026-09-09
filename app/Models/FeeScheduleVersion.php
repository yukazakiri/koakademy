<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class FeeScheduleVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'course_id',
        'school_year',
        'semester',
        'academic_year',
        'version',
        'lecture_rate_per_unit',
        'laboratory_rate_per_unit',
        'miscellaneous_fee',
        'modular_fee',
        'nstp_multiplier',
        'modular_lab_multiplier',
        'rules',
        'is_active',
        'created_by_user_id',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assessmentRevisions(): HasMany
    {
        return $this->hasMany(AssessmentRevision::class, 'fee_schedule_version_id');
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'academic_year' => 'integer',
            'version' => 'integer',
            'lecture_rate_per_unit' => 'float',
            'laboratory_rate_per_unit' => 'float',
            'miscellaneous_fee' => 'float',
            'modular_fee' => 'float',
            'nstp_multiplier' => 'float',
            'modular_lab_multiplier' => 'float',
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
