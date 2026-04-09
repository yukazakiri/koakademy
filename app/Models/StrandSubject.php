<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Class StrandSubject
 *
 * @property-read ShsStrand|null $strand
 * @property-read ShsTrack|null $track
 *
 * @method static Builder<static>|StrandSubject newModelQuery()
 * @method static Builder<static>|StrandSubject newQuery()
 * @method static Builder<static>|StrandSubject query()
 *
 * @mixin \Eloquent
 */
final class StrandSubject extends Model
{
    use HasFactory;

    protected $table = 'strand_subjects';

    protected $fillable = [
        'code',
        'title',
        'description',
        'grade_year',
        'semester',
        'strand_id',
    ];

    /**
     * Get the strand that this subject belongs to.
     */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(ShsStrand::class, 'strand_id');
    }

    /**
     * Get the track that this subject belongs to (via strand).
     */
    public function track(): HasOneThrough
    {
        return $this->hasOneThrough(ShsTrack::class, ShsStrand::class, 'id', 'id', 'strand_id', 'track_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (StrandSubject $subject): void {
            // Generate unique code if not provided
            if (empty($subject->code)) {
                $subject->code = $subject->generateUniqueCode();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'strand_id' => 'int',
            'grade_year' => 'int',
            'semester' => 'int',
        ];
    }

    /**
     * Generate a unique subject code based on strand, grade, and semester
     */
    private function generateUniqueCode(): string
    {
        $strandCode = $this->getStrandCode();
        $gradeYear = $this->grade_year ?? 11;
        $semester = $this->semester ?? 1;

        // Create base code
        $baseCode = sprintf('%s%d%d', $strandCode, $gradeYear, $semester);

        // Ensure uniqueness by appending a suffix if needed
        $counter = 1;
        $code = $baseCode;

        while (self::where('code', $code)
            ->where('id', '!=', $this->id ?? 0)
            ->exists()) {
            $code = sprintf('%s%03d', $baseCode, $counter);
            $counter++;
        }

        return $code;
    }

    /**
     * Get the 3-letter code for the strand
     */
    private function getStrandCode(): string
    {
        $strandName = $this->strand?->strand_name ?? 'GEN';

        // Clean strand name to 3-letter code
        $strandMap = [
            'STEM' => 'STM',
            'ABM' => 'ABM',
            'HUMSS' => 'HMS',
            'GAS' => 'GAS',
            'ICT' => 'ICT',
            'HOME ECONOMICS' => 'HME',
            'INDUSTRIAL ARTS' => 'INA',
            'AGRI-FISHERY ARTS' => 'AGR',
        ];

        return $strandMap[$strandName] ?? mb_strtoupper(mb_substr($strandName, 0, 3));
    }
}
