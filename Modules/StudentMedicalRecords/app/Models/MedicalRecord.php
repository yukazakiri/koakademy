<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Models;

use App\Models\Account;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StudentMedicalRecords\Enums\MedicalRecordPriority;
use Modules\StudentMedicalRecords\Enums\MedicalRecordStatus;
use Modules\StudentMedicalRecords\Enums\MedicalRecordType;

/**
 * @property int $id
 * @property int $student_id
 * @property MedicalRecordType $record_type
 * @property string $title
 * @property string|null $description
 * @property string|null $diagnosis
 * @property string|null $treatment
 * @property string|null $prescription
 * @property string|null $notes
 * @property string|null $doctor_name
 * @property string|null $clinic_name
 * @property string|null $clinic_address
 * @property string|null $doctor_contact
 * @property \Carbon\Carbon $visit_date
 * @property \Carbon\Carbon|null $next_appointment
 * @property \Carbon\Carbon|null $follow_up_date
 * @property MedicalRecordStatus $status
 * @property MedicalRecordPriority $priority
 * @property bool $is_confidential
 * @property float|null $height
 * @property float|null $weight
 * @property int|null $blood_pressure_systolic
 * @property int|null $blood_pressure_diastolic
 * @property float|null $temperature
 * @property int|null $heart_rate
 * @property float|null $bmi
 * @property array|null $vital_signs
 * @property array|null $lab_results
 * @property array|null $attachments
 * @property bool $emergency_contact_notified
 * @property \Carbon\Carbon|null $emergency_notification_sent_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Student $student
 * @property-read Account|null $creator
 * @property-read Account|null $updater
 * @property-read string $formatted_visit_date
 * @property-read string $bmi_status
 * @property-read string $blood_pressure_status
 */
final class MedicalRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'medical_records';

    protected $fillable = [
        'student_id',
        'record_type',
        'title',
        'description',
        'diagnosis',
        'treatment',
        'prescription',
        'notes',
        'doctor_name',
        'clinic_name',
        'clinic_address',
        'doctor_contact',
        'visit_date',
        'next_appointment',
        'follow_up_date',
        'status',
        'priority',
        'is_confidential',
        'height',
        'weight',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'temperature',
        'heart_rate',
        'bmi',
        'vital_signs',
        'lab_results',
        'attachments',
        'emergency_contact_notified',
        'emergency_notification_sent_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'record_type' => MedicalRecordType::class,
        'status' => MedicalRecordStatus::class,
        'priority' => MedicalRecordPriority::class,
        'visit_date' => 'date',
        'next_appointment' => 'date',
        'follow_up_date' => 'date',
        'is_confidential' => 'boolean',
        'emergency_contact_notified' => 'boolean',
        'emergency_notification_sent_at' => 'datetime',
        'height' => 'decimal:2',
        'weight' => 'decimal:2',
        'temperature' => 'decimal:1',
        'bmi' => 'decimal:1',
        'vital_signs' => 'array',
        'lab_results' => 'array',
        'attachments' => 'array',
    ];

    /**
     * Get the student that owns the medical record.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'updated_by');
    }

    /**
     * Scope a query to only include records of a given type.
     */
    public function scopeOfType(Builder $query, MedicalRecordType $type): Builder
    {
        return $query->where('record_type', $type);
    }

    /**
     * Scope a query to only include records with a given status.
     */
    public function scopeWithStatus(Builder $query, MedicalRecordStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include records with a given priority.
     */
    public function scopeWithPriority(Builder $query, MedicalRecordPriority $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include confidential records.
     */
    public function scopeConfidential(Builder $query): Builder
    {
        return $query->where('is_confidential', true);
    }

    /**
     * Scope a query to only include non-confidential records.
     */
    public function scopeNonConfidential(Builder $query): Builder
    {
        return $query->where('is_confidential', false);
    }

    /**
     * Scope a query to only include urgent records.
     */
    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', MedicalRecordPriority::Urgent);
    }

    /**
     * Scope a query to only include emergency records.
     */
    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('record_type', MedicalRecordType::Emergency);
    }

    /**
     * Scope a query to only include records needing follow-up.
     */
    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_date')
            ->where('follow_up_date', '<=', now()->addDays(7))
            ->where('status', '!=', MedicalRecordStatus::Resolved);
    }

    /**
     * Calculate BMI from height and weight.
     */
    public function calculateBmi(): float
    {
        if (! $this->height || ! $this->weight) {
            return 0;
        }

        // Convert height from cm to meters
        $heightInMeters = $this->height / 100;

        // BMI = weight (kg) / height (m)²
        return round($this->weight / ($heightInMeters * $heightInMeters), 1);
    }

    /**
     * Get BMI status based on calculated BMI.
     */
    public function getBmiStatusAttribute(): string
    {
        if (! $this->bmi) {
            return 'Unknown';
        }

        return match (true) {
            $this->bmi < 18.5 => 'Underweight',
            $this->bmi >= 18.5 && $this->bmi < 25 => 'Normal',
            $this->bmi >= 25 && $this->bmi < 30 => 'Overweight',
            $this->bmi >= 30 => 'Obese',
            default => 'Unknown',
        };
    }

    /**
     * Get blood pressure status based on readings.
     */
    public function getBloodPressureStatusAttribute(): string
    {
        if (! $this->blood_pressure_systolic || ! $this->blood_pressure_diastolic) {
            return 'Unknown';
        }

        $systolic = $this->blood_pressure_systolic;
        $diastolic = $this->blood_pressure_diastolic;

        return match (true) {
            $systolic < 90 || $diastolic < 60 => 'Low',
            $systolic <= 120 && $diastolic <= 80 => 'Normal',
            $systolic <= 129 && $diastolic <= 80 => 'Elevated',
            $systolic <= 139 || $diastolic <= 89 => 'High Stage 1',
            $systolic <= 179 || $diastolic <= 119 => 'High Stage 2',
            $systolic >= 180 || $diastolic >= 120 => 'Hypertensive Crisis',
            default => 'Unknown',
        };
    }

    /**
     * Get formatted visit date.
     */
    public function getFormattedVisitDateAttribute(): string
    {
        return $this->visit_date->format('M d, Y');
    }

    /**
     * Check if the record is urgent.
     */
    public function isUrgent(): bool
    {
        return $this->priority === MedicalRecordPriority::Urgent;
    }

    /**
     * Check if the record is an emergency.
     */
    public function isEmergency(): bool
    {
        return $this->record_type === MedicalRecordType::Emergency;
    }

    /**
     * Check if the record needs follow-up.
     */
    public function needsFollowUp(): bool
    {
        return $this->follow_up_date &&
               $this->follow_up_date->isFuture() &&
               $this->status !== MedicalRecordStatus::Resolved;
    }

    /**
     * Mark emergency contact as notified.
     */
    public function markEmergencyContactNotified(): void
    {
        $this->update([
            'emergency_contact_notified' => true,
            'emergency_notification_sent_at' => now(),
        ]);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (MedicalRecord $record) {
            if (auth()->check()) {
                $record->created_by = auth()->id();
            }
        });

        self::updating(function (MedicalRecord $record) {
            if (auth()->check()) {
                $record->updated_by = auth()->id();
            }
        });

        self::saving(function (MedicalRecord $record) {
            // Calculate BMI if height and weight are provided
            if ($record->height && $record->weight) {
                $record->bmi = $record->calculateBmi();
            }
        });
    }
}
