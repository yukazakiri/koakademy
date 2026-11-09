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
 * Class StudentParentsInfo
 *
 * @method static Builder<static>|StudentParentsInfo newModelQuery()
 * @method static Builder<static>|StudentParentsInfo newQuery()
 * @method static Builder<static>|StudentParentsInfo query()
 *
 * @mixin \Eloquent
 */
final class StudentParentsInfo extends Model
{
    #[Override]
    protected $table = 'student_parents_info';

    #[Override]
    protected $fillable = [
        'father_name',
        'fathers_name',
        'father_occupation',
        'father_contact',
        'father_email',
        'mother_name',
        'mothers_name',
        'mother_occupation',
        'mother_contact',
        'mother_email',
        'guardian_name',
        'guardian_relationship',
        'guardian_contact',
        'guardian_email',
        'family_address',
    ];

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'student_parent_info');
    }
}
