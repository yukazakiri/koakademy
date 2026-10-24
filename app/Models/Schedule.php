<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\GeneralSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Import Builder
use Illuminate\Database\Eloquent\Model; // Import Cache
// Import GeneralSetting
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

// Add this import
/**
 * @property-read Classes|null $class
 * @property-read string $formatted_end_time
 * @property-read string $formatted_start_time
 * @property-read mixed $subject
 * @property-read string $time_range
 * @property-read Room|null $room
 *
 * @method static Builder<static>|Schedule currentAcademicPeriod()
 * @method static Builder<static>|Schedule newModelQuery()
 * @method static Builder<static>|Schedule newQuery()
 * @method static Builder<static>|Schedule onlyTrashed()
 * @method static Builder<static>|Schedule query()
 * @method static Builder<static>|Schedule withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Schedule withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Schedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    #[Override]
    protected $table = 'schedule';

    #[Override]
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'room_id',
        'class_id',
        'subject_id',
        'component_type',
        'pattern_group_id',
        'status',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subjectModel()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function getSchedulesByClass($classId)
    {
        return $this->where('class_id', $classId)->get();
    }

    protected static function booted(): void
    {
        // Sessions of an online class are never given a room.
        self::saving(function (Schedule $schedule): void {
            if ($schedule->room_id !== null && Classes::withTrashed()->whereKey($schedule->class_id)->value('delivery_mode') === Classes::DELIVERY_ONLINE) {
                $schedule->room_id = null;
                $schedule->unsetRelation('room');
            }
        });
    }

    protected function formattedStartTime(): Attribute
    {
        return Attribute::make(get: fn () => $this->start_time->format('h:i A'));
    }

    protected function formattedEndTime(): Attribute
    {
        return Attribute::make(get: fn () => $this->end_time->format('h:i A'));
    }

    protected function timeRange(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->formatted_start_time.' - '.$this->formatted_end_time);
    }

    protected function subject(): Attribute
    {
        return Attribute::make(get: fn () => $this->class->subject->title);
    }

    /**
     * Scope a query to only include schedules for the current school year and semester,
     * based on the associated class.
     */
    protected function scopeCurrentAcademicPeriod(Builder $builder): Builder
    {
        // Use the GeneralSettingsService to get effective settings
        $generalSettingsService = app(GeneralSettingsService::class);
        $schoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $semester = $generalSettingsService->getCurrentSemester();

        // Use whereHas to filter based on the related class's properties
        return $builder->whereHas('class', function (Builder $builder) use ($schoolYear, $semester): void {
            $builder->where('school_year', $schoolYear)
                ->where('semester', $semester);
        });
    }

    protected function scopeForAcademicPeriod(Builder $builder, string $schoolYear, int|string $semester): Builder
    {
        $normalizedSchoolYear = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $compactSchoolYear = str_replace(' ', '', $normalizedSchoolYear);
        $schoolYearVariants = array_values(array_unique([$schoolYear, $normalizedSchoolYear, $compactSchoolYear]));

        return $builder->whereHas('class', function (Builder $builder) use ($schoolYearVariants, $semester): void {
            $builder->whereIn('school_year', $schoolYearVariants)
                ->where('semester', $semester);
        });
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'room_id' => 'integer',
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'component_type' => 'string',
            'pattern_group_id' => 'string',
            'status' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
