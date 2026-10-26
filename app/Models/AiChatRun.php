<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AiChatRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'school_id', 'request_id', 'conversation_id',
        'user_message_id', 'assistant_message_id', 'agent', 'provider',
        'model', 'status', 'options', 'payload_hash', 'error',
    ];

    public function metadata(): array
    {
        return $this->only(['id', 'request_id', 'status', 'agent', 'provider', 'model', 'options']);
    }

    protected function casts(): array
    {
        return ['options' => 'array'];
    }
}
