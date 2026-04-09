<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class HelpTicket extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'message',
        'status',
        'priority',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HelpTicketReply::class);
    }
}
