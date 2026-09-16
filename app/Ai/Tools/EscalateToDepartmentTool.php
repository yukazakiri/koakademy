<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\HelpTicket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class EscalateToDepartmentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Escalate an ongoing student or faculty inquiry directly to a specific institutional department office (Registrar, Accounting, IT Support, Guidance Office) with conversation summary.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'target_department' => 'required|string',
            'summary_of_inquiry' => 'required|string',
            'urgency' => 'sometimes|string',
        ]);

        $user = User::query()->find($validated['user_id']);
        $name = $user ? $user->name : 'User';

        $ticket = HelpTicket::query()->create([
            'user_id' => $validated['user_id'],
            'type' => mb_strtolower($validated['target_department']),
            'subject' => "AI Escalation to {$validated['target_department']}: {$name}",
            'message' => $validated['summary_of_inquiry'],
            'status' => 'open',
            'priority' => $validated['urgency'] ?? 'medium',
        ]);

        return json_encode([
            'escalation_id' => "esc_{$ticket->id}",
            'target_department' => $validated['target_department'],
            'ticket_id' => $ticket->id,
            'status' => 'escalated_to_staff',
            'message' => "Your inquiry has been escalated to the {$validated['target_department']} department desk. A staff officer will review ticket #{$ticket->id} promptly.",
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->required(),
            'target_department' => $schema->string()->enum(['Registrar', 'Accounting', 'IT_Support', 'Guidance_Counseling', 'Student_Affairs'])->required(),
            'summary_of_inquiry' => $schema->string()->required(),
            'urgency' => $schema->string()->enum(['low', 'medium', 'high', 'urgent']),
        ];
    }
}
