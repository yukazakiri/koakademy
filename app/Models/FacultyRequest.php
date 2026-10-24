<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultyRequest extends Model
{
    protected $table = 'faculty_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            
            'steps' => 'array', 'decided_at' => 'datetime',
            
            
            
            
        ];
    }
}
