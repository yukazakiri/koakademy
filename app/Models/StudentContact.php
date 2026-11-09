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
    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'student_contacts';

    #[Override]
    protected $fillable = [
        'personal_contact',
        'facebook',
        'facebook_contact',
        'twitter',
        'instagram',
        'linkedin',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_address',
        'emergency_contact_relationship',
        'student_id',
    ];

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'student_contact_id');
    }
}
