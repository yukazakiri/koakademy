<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeHourSlot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(OfficeHourBooking::class, 'slot_id');
    }
}
