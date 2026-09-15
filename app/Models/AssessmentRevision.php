<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class AssessmentRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'student_tuition_id',
        'student_enrollment_id',
        'fee_schedule_version_id',
        'tuition_adjustment_id',
        'actor_user_id',
        'predecessor_id',
        'revision_number',
        'calculation_method',
        'status',
        'source',
        'reason',
        'gross_lecture',
        'discount_percentage',
        'discount_amount',
        'discounted_lecture',
        'laboratory',
        'modular',
        'total_tuition',
        'miscellaneous',
        'additional_fees',
        'assessment_adjustment',
        'overall_tuition',
        'required_downpayment',
        'opening_paid',
        'verified_paid',
        'total_paid',
        'balance_due',
        'credit',
        'installments',
        'calculation_inputs',
        'fingerprint',
        'pdf_resource_id',
    ];

    public function tuition(): BelongsTo
    {
        return $this->belongsTo(StudentTuition::class, 'student_tuition_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function feeScheduleVersion(): BelongsTo
    {
        return $this->belongsTo(FeeScheduleVersion::class, 'fee_schedule_version_id');
    }

    public function tuitionAdjustment(): BelongsTo
    {
        return $this->belongsTo(TuitionAdjustment::class, 'tuition_adjustment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }

    public function successors(): HasMany
    {
        return $this->hasMany(self::class, 'predecessor_id');
    }

    public function pdfResource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'pdf_resource_id');
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
            'revision_number' => 'integer',
            'gross_lecture' => 'float',
            'discount_percentage' => 'float',
            'discount_amount' => 'float',
            'discounted_lecture' => 'float',
            'laboratory' => 'float',
            'modular' => 'float',
            'total_tuition' => 'float',
            'miscellaneous' => 'float',
            'additional_fees' => 'float',
            'assessment_adjustment' => 'float',
            'overall_tuition' => 'float',
            'required_downpayment' => 'float',
            'opening_paid' => 'float',
            'verified_paid' => 'float',
            'total_paid' => 'float',
            'balance_due' => 'float',
            'credit' => 'float',
            'installments' => 'array',
            'calculation_inputs' => 'array',
        ];
    }
}
