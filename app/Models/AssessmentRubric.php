<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentRubric extends Model
{
    protected $table = 'assessment_rubrics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'criteria' => 'array', 'is_shared' => 'boolean',
            
            
            
            
            
        ];
    }
}
