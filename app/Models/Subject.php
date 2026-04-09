<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Enums\SubjectEnrolledEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * Class Subject
 *
 * @property SubjectEnrolledEnum $classification
 * @property-read Collection<int, Classes> $classes
 * @property-read int|null $classes_count
 * @property-read Course|null $course
 * @property-read mixed $all_pre_requisites
 * @property-read int|float $laboratory_fee
 * @property-read int|float $lecture_fee
 * @property-read Collection<int, SubjectEnrollment> $subjectEnrolleds
 * @property-read int|null $subject_enrolleds_count
 *
 * @method static Builder<static>|Subject credited()
 * @method static Builder<static>|Subject newModelQuery()
 * @method static Builder<static>|Subject newQuery()
 * @method static Builder<static>|Subject nonCredited()
 * @method static Builder<static>|Subject query()
 *
 * @mixin \Eloquent
 */
final class Subject extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'subject';

    protected $fillable = [
        'classification',
        'code',
        'title',
        'units',
        'lecture',
        'laboratory',
        'pre_riquisite',
        'academic_year',
        'semester',
        'course_id',
        'group',
        'is_credited',
    ];

    public static function getSubjectsDetailsByYear($subjects, $year)
    {
        return $subjects->where('academic_year', $year)->map(fn ($subject): string => sprintf('%s (Code: %s, Units: %s)', $subject->title, $subject->code, $subject->units))->join(', ');
    }

    public static function getAvailableSubjects($selectedCourse, $academicYear, $selectedSemester, $schoolYear, $type, $selectedSubjects)
    {
        $classes = Classes::query()->where('school_year', $schoolYear)
            ->when($type !== 'transferee', fn ($query) => $query->where('academic_year', $academicYear)
                ->where('semester', $selectedSemester))
            ->whereJsonContains('course_codes', (string) $selectedCourse)
            ->get(['subject_code']);

        // Create a map of trimmed subject codes for matching
        $classSubjectCodes = [];
        foreach ($classes as $class) {
            $trimmedCode = mb_trim($class->subject_code);
            $classSubjectCodes[] = $trimmedCode;
        }

        $builder = self::query()
            ->where('course_id', $selectedCourse)
            ->where(function ($query) use ($classSubjectCodes): void {
                foreach ($classSubjectCodes as $classSubjectCode) {
                    $query->orWhereRaw('TRIM(code) = ?', [$classSubjectCode]);
                }
            })
            ->whereNotIn('id', $selectedSubjects);

        if ($type !== 'transferee') {
            $builder->where('academic_year', $academicYear)
                ->where('semester', $selectedSemester);
        }

        return $builder->pluck('code', 'id');
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'title' => $this->title,
        ];
    }

    public function isCredited(): bool
    {
        return $this->classification === SubjectEnrolledEnum::CREDITED->value;
    }

    public function isNonCredited(): bool
    {
        return $this->classification === SubjectEnrolledEnum::NON_CREDITED->value;
    }

    public function isInternal(): bool
    {
        return $this->classification === SubjectEnrolledEnum::INTERNAL->value;
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function subjectEnrolleds()
    {
        return $this->hasMany(SubjectEnrollment::class, 'subject_id');
    }

    public function classes()
    {
        return $this->hasMany(Classes::class, 'subject_code', 'code');
    }

    protected static function boot(): void
    {
        parent::boot();
        // self::observe(SubjectObserver::class);

        self::creating(function ($model): void {
            $ids = self::all()->pluck('id')->toArray();
            $model->id = empty($ids) ? 1 : max($ids) + 1;
        });
    }

    protected function scopeCredited($query)
    {
        return $query->where('is_credited', true);
    }

    protected function scopeNonCredited($query)
    {
        return $query->where('is_credited', false);
    }

    protected function allPreRequisites(): Attribute
    {
        return Attribute::make(get: fn () => $this->pre_riquisite);
    }

    protected function lectureFee(): Attribute
    {
        return Attribute::make(get: fn (): int|float => $this->lecture * $this->course->lab_per_unit);
    }

    protected function laboratoryFee(): Attribute
    {
        return Attribute::make(get: fn (): int|float => $this->laboratory * $this->course->lab_per_unit);
    }

    protected function casts(): array
    {
        return [
            'classification' => SubjectEnrolledEnum::class,
            'units' => 'int',
            'lecture' => 'int',
            'laboratory' => 'int',
            'academic_year' => 'int',
            'semester' => 'int',
            'course_id' => 'int',
            'is_credited' => 'bool',
            'pre_riquisite' => 'array',
        ];
    }
}
