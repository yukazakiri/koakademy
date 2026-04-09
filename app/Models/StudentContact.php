<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StudentContact
 *
 * @method static Builder<static>|StudentContact newModelQuery()
 * @method static Builder<static>|StudentContact newQuery()
 * @method static Builder<static>|StudentContact query()
 *
 * @mixin \Eloquent
 */
final class StudentContact extends Model
{
    public $timestamps = false;

    protected $table = 'student_contacts';

    protected $fillable = [
        'personal_contact',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
    ];
}
