<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
    protected $table = 'students_personal_info';

    protected $fillable = [
        'birthplace',
        'civil_status',
        'citizenship',
        'religion',
        'weight',
        'height',
        'current_adress',
        'permanent_address',
    ];
}
