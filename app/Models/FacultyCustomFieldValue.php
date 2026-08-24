<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FacultyCustomFieldValue extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'faculty_id', 'faculty_custom_field_definition_id', 'value'];

    protected $hidden = ['value'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(FacultyCustomFieldDefinition::class, 'faculty_custom_field_definition_id');
    }

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
