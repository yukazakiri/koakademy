<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasAcademicPeriodScope;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class SubjectEnrollment
 *
 * @property-read Classes|null $class
 * @property-read Student|null $student
 * @property-read StudentEnrollment|null $studentEnrollment
 * @property-read Subject|null $subject
 *
 * @method static Builder<static>|SubjectEnrollment newModelQuery()
 * @method static Builder<static>|SubjectEnrollment newQuery()
 * @method static Builder<static>|SubjectEnrollment currentAcademicPeriod()
 * @method static Builder<static>|SubjectEnrollment forAcademicPeriod(string $schoolYear, int $semester)
 *
 * @mixin \Eloquent
 */
final class SubjectEnrollment extends Model
{
    use BelongsToSchool;
    use HasAcademicPeriodScope;
    use HasFactory;
    use LogsActivity;
    use Searchable;

    protected $table = 'subject_enrollments';

    protected $fillable = [
        'subject_id',
        'class_id',
        'grade',
        'instructor',
        'student_id',
        'academic_year',
        'school_year',
        'semester',
        'enrollment_id',
        'remarks',
        'classification',
        'school_name',
        'external_subject_code',
        'external_subject_title',
        'external_subject_units',
        'is_credited',
        'credited_subject_id',
        'section',
        'is_modular',
        'lecture_fee',
        'laboratory_fee',
        'enrolled_lecture_units',
        'enrolled_laboratory_units',
        'school_id',
    ];

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'student_id' => $this->student_id,
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'remarks' => $this->remarks,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName): string {
                $studentName = $this->student?->full_name ?? 'Unknown Student';
                $subjectLabel = $this->subject?->code
                    ?? $this->external_subject_code
                    ?? $this->external_subject_title
                    ?? 'Standalone Subject';

                return "Subject Enrollment for {$studentName} ({$subjectLabel}) was {$eventName}";
            })
            ->useLogName('subject_enrollment');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function creditedSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'credited_subject_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $highestId = self::query()->max('id');
            $model->id = $highestId ? $highestId + 1 : 1;
        });

        self::updating(function ($model): void {
            // Check if class_id has changed
            if ($model->isDirty('class_id') && $model->getOriginal('class_id') !== null) {
                $oldClassId = $model->getOriginal('class_id');
                $newClassId = $model->class_id;

                // Only proceed if both old and new class IDs are valid
                if ($oldClassId && $newClassId && $oldClassId !== $newClassId) {
                    self::handleClassEnrollmentUpdate($model, $oldClassId, $newClassId);
                }
            }
        });

        self::deleting(function ($model): void {
            // When a subject enrollment is deleted, also remove the associated class enrollment
            if ($model->class_id) {
                self::handleClassEnrollmentDeletion($model);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'subject_id' => 'int',
            'class_id' => 'int',
            'grade' => 'float',
            'student_id' => 'int',
            'semester' => 'int',
            'enrollment_id' => 'int',
            'external_subject_units' => 'int',
            'is_credited' => 'bool',
            'credited_subject_id' => 'int',
            'is_modular' => 'bool',
            'lecture_fee' => 'float',
            'laboratory_fee' => 'float',
            'enrolled_lecture_units' => 'int',
            'enrolled_laboratory_units' => 'int',
        ];
    }

    /**
     * Handle updating class enrollment when subject enrollment class changes
     */
    private static function handleClassEnrollmentUpdate($subjectEnrollment, $oldClassId, $newClassId): void
    {
        try {
            $oldClass = Classes::query()->find($oldClassId);
            $newClass = Classes::query()->find($newClassId);

            if (! $oldClass || ! $newClass) {
                Log::warning('Class not found during enrollment update', [
                    'old_class_id' => $oldClassId,
                    'new_class_id' => $newClassId,
                    'student_id' => $subjectEnrollment->student_id,
                ]);

                return;
            }

            // Check if both classes are for the same subject
            if ($oldClass->subject_code !== $newClass->subject_code) {
                Log::warning('Attempting to move student between different subjects', [
                    'old_subject' => $oldClass->subject_code,
                    'new_subject' => $newClass->subject_code,
                    'student_id' => $subjectEnrollment->student_id,
                ]);

                return;
            }

            // Find existing class enrollment for the old class
            $existingClassEnrollment = ClassEnrollment::query()->where('student_id', $subjectEnrollment->student_id)
                ->where('class_id', $oldClassId)
                ->first();

            // Check if student is already enrolled in the new class
            $newClassEnrollment = ClassEnrollment::query()->where('student_id', $subjectEnrollment->student_id)
                ->where('class_id', $newClassId)
                ->first();

            if ($newClassEnrollment) {
                // Student is already enrolled in the new class
                if ($existingClassEnrollment) {
                    // Remove the old enrollment to avoid duplicates
                    $existingClassEnrollment->delete();
                    $message = sprintf('Student moved from %s Section %s to Section %s', $oldClass->subject_code, $oldClass->section, $newClass->section);
                } else {
                    $message = sprintf('Student already enrolled in %s Section %s', $newClass->subject_code, $newClass->section);
                }
            } elseif ($existingClassEnrollment) {
                // Update the existing class enrollment to the new class
                $existingClassEnrollment->class_id = $newClassId;
                $existingClassEnrollment->save();
                $message = sprintf('Student moved from %s Section %s to Section %s', $oldClass->subject_code, $oldClass->section, $newClass->section);
            } else {
                // Create new class enrollment if none existed
                ClassEnrollment::query()->create([
                    'student_id' => $subjectEnrollment->student_id,
                    'class_id' => $newClassId,
                    'status' => true,
                ]);
                $message = sprintf('Student enrolled in %s Section %s', $newClass->subject_code, $newClass->section);
            }

            Log::info('Class enrollment updated', [
                'student_id' => $subjectEnrollment->student_id,
                'old_class_id' => $oldClassId,
                'new_class_id' => $newClassId,
                'old_section' => $oldClass->section,
                'new_section' => $newClass->section,
                'subject_code' => $oldClass->subject_code,
                'action' => $message,
            ]);

            // Send Filament notification
            Notification::make()
                ->title('Class Enrollment Updated')
                ->body($message)
                ->success()
                ->send();

        } catch (Exception $exception) {
            Log::error('Error updating class enrollment', [
                'error' => $exception->getMessage(),
                'student_id' => $subjectEnrollment->student_id,
                'old_class_id' => $oldClassId,
                'new_class_id' => $newClassId,
                'trace' => $exception->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Class Enrollment Update Failed')
                ->body('Failed to update class enrollment: '.$exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Handle deleting class enrollment when subject enrollment is deleted
     */
    private static function handleClassEnrollmentDeletion($subjectEnrollment): void
    {
        try {
            $classId = $subjectEnrollment->class_id;
            $studentId = $subjectEnrollment->student_id;

            if (! $classId || ! $studentId) {
                return;
            }

            // Find and delete the class enrollment for this student and class
            $classEnrollment = ClassEnrollment::query()
                ->where('student_id', $studentId)
                ->where('class_id', $classId)
                ->first();

            if ($classEnrollment) {
                $class = Classes::query()->find($classId);
                $classEnrollment->delete();

                $message = sprintf(
                    'Student removed from %s Section %s',
                    $class?->subject_code ?? 'Unknown',
                    $class?->section ?? 'Unknown'
                );

                Log::info('Class enrollment deleted with subject enrollment', [
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'subject_enrollment_id' => $subjectEnrollment->id,
                    'action' => $message,
                ]);
            }
        } catch (Exception $exception) {
            Log::error('Error deleting class enrollment', [
                'error' => $exception->getMessage(),
                'student_id' => $subjectEnrollment->student_id,
                'class_id' => $subjectEnrollment->class_id,
                'subject_enrollment_id' => $subjectEnrollment->id,
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
