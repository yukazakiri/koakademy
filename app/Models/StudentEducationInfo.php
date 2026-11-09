<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

/**
 * Class StudentEducationInfo
 *
 * @method static Builder<static>|StudentEducationInfo newModelQuery()
 * @method static Builder<static>|StudentEducationInfo newQuery()
 * @method static Builder<static>|StudentEducationInfo query()
 *
 * @mixin \Eloquent
 */
final class StudentEducationInfo extends Model
{
    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'student_education_info';

    #[Override]
    protected $fillable = [
        'elementary_school',
        'elementary_graduate_year',
        'elementary_school_address',
        'elementary_year_graduated',
        'high_school',
        'junior_high_school_name',
        'junior_high_school_address',
        'junior_high_graduation_year',
        'high_school_year_graduated',
        'senior_high_school',
        'senior_high_name',
        'senior_high_address',
        'senior_high_graduate_year',
        'senior_high_year_graduated',
        'college_school',
        'college_course',
        'college_year_graduated',
        'vocational_school',
        'vocational_course',
        'vocational_year_graduated',
    ];

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'student_education_id');
    }

    protected function casts(): array
    {
        return [
            // Removed incorrect casts
        ];
    }
}
