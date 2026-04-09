<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HelpTicketReply extends Model
{
    protected $fillable = [
        'help_ticket_id',
        'user_id',
        'message',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpTicket::class, 'help_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
