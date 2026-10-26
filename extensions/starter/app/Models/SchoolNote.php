<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_id
 * @property int $created_by
 * @property string $body
 */
final class SchoolNote extends Model
{
    use BelongsToSchool;

    protected $table = 'school_notes_extension_notes';

    protected $fillable = ['body', 'school_id', 'created_by'];
}
