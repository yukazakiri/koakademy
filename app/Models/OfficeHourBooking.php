<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeHourBooking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['booked_at' => 'datetime'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(OfficeHourSlot::class, 'slot_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
