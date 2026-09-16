<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\HelpTicket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class LookupTicketStatusTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up the status, latest staff replies, and resolution progress for an existing Help Ticket ID.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'ticket_id' => 'required|integer',
        ]);

        $ticket = HelpTicket::query()
            ->with(['replies', 'user'])
            ->find($validated['ticket_id']);

        if (! $ticket instanceof HelpTicket) {
            return "Help Ticket #{$validated['ticket_id']} was not found in active records.";
        }

        return json_encode([
            'ticket_id' => $ticket->id,
            'subject' => $ticket->subject,
            'type' => $ticket->type,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'opened_by' => $ticket->user?->name ?? 'User',
            'created_at' => $ticket->created_at?->toFormattedDateString(),
            'reply_count' => $ticket->replies->count(),
            'latest_reply' => $ticket->replies->last()?->message ?? 'No replies recorded yet. Ticket is queued with department staff.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()->required(),
        ];
    }
}
