<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\BursarFinanceAgent;
use App\Ai\Agents\CampusSupportAgent;
use App\Ai\Agents\FacultyCopilotAgent;
use App\Ai\Agents\RegistrarAuditAgent;
use App\Ai\Agents\StudentAdvisorAgent;
use App\Features\Toggles\AiFacultyAssistant;
use App\Features\Toggles\AiFinanceAssistant;
use App\Features\Toggles\AiHelpDeskAssistant;
use App\Features\Toggles\AiRegistrarAuditor;
use App\Features\Toggles\AiStudentAdvisor;
use App\Models\User;
use App\Services\Ai\AiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Models\Conversation;
use Laravel\Pennant\Feature;

final class AiChatController extends Controller
{
    /**
     * Send a prompt or tool approval decision to the selected agent and stream response.
     */
    public function chat(Request $request): mixed
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'agent' => ['required', 'string', Rule::in([
                'student_advisor',
                'faculty_copilot',
                'registrar_auditor',
                'bursar_finance',
                'campus_support',
            ])],
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'message' => ['nullable', 'string', 'required_without:decisions', 'prohibits:decisions'],
            'decisions' => ['nullable', 'array', 'required_without:message', 'prohibits:message'],
            'decisions.*.action' => ['required_with:decisions', Rule::in(['approve', 'reject'])],
            'decisions.*.result' => ['nullable', 'string'],
        ]);

        $aiSettings = app(AiSettingsService::class)->get();
        if (! ($aiSettings['enabled'] ?? true)) {
            abort(503, 'AI Assistant services are currently disabled by the administration.');
        }

        $featureClass = match ($validated['agent']) {
            'student_advisor' => AiStudentAdvisor::class,
            'faculty_copilot' => AiFacultyAssistant::class,
            'registrar_auditor' => AiRegistrarAuditor::class,
            'bursar_finance' => AiFinanceAssistant::class,
            'campus_support' => AiHelpDeskAssistant::class,
        };

        if (Feature::defined($featureClass) && ! Feature::for($user)->active($featureClass)) {
            abort(403, 'The requested AI assistant is not active for your role.');
        }

        $agent = $this->resolveAgent($validated['agent']);

        $prompt = isset($validated['decisions'])
            ? Decisions::from(collect($validated['decisions'])->map(
                fn (array $d) => match ($d['action']) {
                    'approve' => Decision::approve(),
                    'reject' => Decision::reject($d['result'] ?? null),
                }
            )->all())
            : $validated['message'];

        $conversationId = $validated['conversation_id'] ?? null;

        if ($conversationId) {
            Conversation::query()
                ->where('id', $conversationId)
                ->where('participant_id', $user->id)
                ->firstOrFail();

            $agentInstance = $agent->continue($conversationId, as: $user);
        } else {
            $agentInstance = $agent->forUser($user);
        }

        // Stream using Vercel AI SDK protocol for seamless Inertia/React consumption
        return $agentInstance
            ->stream($prompt)
            ->usingVercelDataProtocol();
    }

    /**
     * List user conversations.
     */
    public function conversations(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $conversations = $user->conversations()
            ->latest('updated_at')
            ->paginate(15);

        return response()->json($conversations);
    }

    /**
     * Retrieve messages for a given conversation.
     */
    public function messages(string $conversationId): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('participant_id', $user->id)
            ->with(['messages'])
            ->firstOrFail();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages,
        ]);
    }

    private function resolveAgent(string $key): Agent
    {
        return match ($key) {
            'student_advisor' => new StudentAdvisorAgent,
            'faculty_copilot' => new FacultyCopilotAgent,
            'registrar_auditor' => new RegistrarAuditAgent,
            'bursar_finance' => new BursarFinanceAgent,
            'campus_support' => new CampusSupportAgent,
            default => throw new InvalidArgumentException("Unknown agent [{$key}]."),
        };
    }
}
