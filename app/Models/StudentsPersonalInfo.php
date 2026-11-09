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
 * Class StudentsPersonalInfo
 *
 * @method static Builder<static>|StudentsPersonalInfo newModelQuery()
 * @method static Builder<static>|StudentsPersonalInfo newQuery()
 * @method static Builder<static>|StudentsPersonalInfo query()
 *
 * @mixin \Eloquent
 */
final class StudentsPersonalInfo extends Model
{
    #[Override]
    protected $table = 'students_personal_info';

    #[Override]
    protected $fillable = [
        'birthplace',
        'place_of_birth',
        'civil_status',
        'citizenship',
        'religion',
        'weight',
        'height',
        'blood_type',
        'distinguishing_marks',
        'father_occupation',
        'mother_occupation',
        'hobbies',
        'special_skills',
        'current_adress',
        'permanent_address',
    ];

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'student_personal_id');
    }
}
