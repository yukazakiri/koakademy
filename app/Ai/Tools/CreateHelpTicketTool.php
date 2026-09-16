<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\HelpTicket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class CreateHelpTicketTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Create an official support help ticket for staff follow-up when an issue cannot be resolved conversationally.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'subject' => 'required|string|max:255',
            'type' => 'required|string',
            'message' => 'required|string',
            'priority' => 'required|string',
        ]);

        $user = User::query()->find($validated['user_id']);
        if (! $user instanceof User) {
            return "User ID {$validated['user_id']} was not found.";
        }

        $ticket = HelpTicket::query()->create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
            'priority' => $validated['priority'],
        ]);

        return json_encode([
            'ticket_id' => $ticket->id,
            'status' => 'open',
            'subject' => $ticket->subject,
            'message' => "Help ticket #{$ticket->id} has been opened for staff response.",
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->required(),
            'subject' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'message' => $schema->string()->required(),
            'priority' => $schema->string()->enum(['low', 'medium', 'high', 'urgent'])->required(),
        ];
    }
}
