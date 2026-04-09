<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
    public $timestamps = false;

    protected $table = 'student_education_info';

    protected $fillable = [
        'elementary_school',
        'elementary_year_graduated',
        'high_school',
        'high_school_year_graduated',
        'senior_high_school',
        'senior_high_year_graduated',
        'college_school',
        'college_course',
        'college_year_graduated',
        'vocational_school',
        'vocational_course',
        'vocational_year_graduated',
    ];

    protected function casts(): array
    {
        return [
            // Removed incorrect casts
        ];
    }
}
