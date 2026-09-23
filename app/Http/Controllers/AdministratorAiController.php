<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\AdminExecutiveAgent;
use App\Ai\Agents\BursarFinanceAgent;
use App\Ai\Agents\CampusSupportAgent;
use App\Ai\Agents\RegistrarAuditAgent;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\User;
use App\Services\Ai\AiAttachmentProcessor;
use App\Services\Ai\AiDocumentGeneratorService;
use App\Services\Ai\AiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Models\Conversation;
use Throwable;

final class AdministratorAiController extends Controller
{
    /**
     * Render the full-page Administrator AI Chat interface.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $initialConversationId = $request->query('conversation');
        $initialConversation = null;

        if (is_string($initialConversationId) && filled($initialConversationId)) {
            $conversation = Conversation::query()
                ->where('id', $initialConversationId)
                ->where('participant_id', $user->id)
                ->with(['messages'])
                ->first();

            if ($conversation) {
                $initialConversation = [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'created_at' => $conversation->created_at?->toISOString(),
                    'updated_at' => $conversation->updated_at?->toISOString(),
                    'messages' => $this->formatMessages($conversation->messages),
                ];
            }
        }

        return Inertia::render('administrators/ai/index', [
            'initialConversation' => $initialConversation,
            'initialConversationId' => $initialConversation ? $initialConversationId : null,
            'hideMobileNavigation' => true,
        ]);
    }

    /**
     * Handle streaming AI assistant chat for administrators with full multi-provider and custom model support.
     */
    public function chat(Request $request): mixed
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $aiSettingsService = app(AiSettingsService::class);
        $aiSettingsService->applyRuntimeConfig();

        $aiSettings = $aiSettingsService->get();
        if (! ($aiSettings['enabled'] ?? true)) {
            abort(503, 'Administrative AI Assistant services are disabled in system settings.');
        }

        $validated = $request->validate([
            'agent' => ['nullable', 'string', Rule::in([
                'admin_executive',
                'registrar_auditor',
                'bursar_finance',
                'campus_support',
            ])],
            'provider' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:255'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'message' => ['nullable', 'string', 'required_without:decisions', 'prohibits:decisions'],
            'decisions' => ['nullable', 'array', 'required_without:message', 'prohibits:message'],
            'decisions.*.action' => ['required_with:decisions', Rule::in(['approve', 'reject'])],
            'decisions.*.result' => ['nullable', 'string'],
            'attachments' => ['nullable'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $agentKey = $validated['agent'] ?? 'admin_executive';
        $agent = $this->resolveAgent($agentKey);

        $selectedProvider = $validated['provider'] ?? null;
        $selectedModel = $validated['model'] ?? null;

        // Support composite "{provider}:{model}" selection format from model-selector
        if (is_string($selectedModel) && str_contains($selectedModel, ':') && blank($selectedProvider)) {
            [$p, $m] = explode(':', $selectedModel, 2);
            $selectedProvider = $p;
            $selectedModel = $m;
        }

        // If no provider or model selected, use primary provider or fallback to first configured provider
        if (blank($selectedProvider)) {
            $primaryKey = (string) ($aiSettings['primary_provider'] ?? config('ai.default', 'anthropic'));
            $primaryConfig = $aiSettings['providers'][$primaryKey] ?? $aiSettings['custom_providers'][$primaryKey] ?? [];
            $supported = AiSettingsService::supportedProviders();
            $requiresKey = $supported[$primaryKey]['requires_key'] ?? false;
            $hasKey = filled($primaryConfig['api_key'] ?? '') || filled(config("ai.providers.{$primaryKey}.key"));

            if ($requiresKey && ! $hasKey) {
                // Primary has no API key - find first configured provider or custom provider
                foreach ($aiSettings['custom_providers'] ?? [] as $ck => $custom) {
                    if ((bool) ($custom['enabled'] ?? true) && filled($custom['base_url'] ?? '')) {
                        $selectedProvider = $ck;
                        $selectedModel = $custom['default_chat_model'] ?: null;
                        break;
                    }
                }

                if (blank($selectedProvider)) {
                    foreach ($supported as $sk => $smeta) {
                        $scfg = $aiSettings['providers'][$sk] ?? [];
                        if ((bool) ($scfg['enabled'] ?? false) && (! $smeta['requires_key'] || filled($scfg['api_key'] ?? ''))) {
                            $selectedProvider = $sk;
                            $selectedModel = $scfg['default_chat_model'] ?: null;
                            break;
                        }
                    }
                }
            } else {
                $selectedProvider = $primaryKey;
                $selectedModel = $primaryConfig['default_chat_model'] ?? null;
            }
        }

        // If custom provider selected, ensure its runtime configuration is present
        if (filled($selectedProvider) && isset($aiSettings['custom_providers'][$selectedProvider])) {
            $custom = $aiSettings['custom_providers'][$selectedProvider];
            $chatModel = filled($selectedModel) ? $selectedModel : ($custom['default_chat_model'] ?: 'default');

            $rawUrl = mb_trim((string) $custom['base_url']);
            if (! str_starts_with($rawUrl, 'http://') && ! str_starts_with($rawUrl, 'https://')) {
                $rawUrl = 'http://'.$rawUrl;
            }
            $cleanUrl = mb_rtrim($rawUrl, '/');
            if (! str_ends_with($cleanUrl, '/v1')) {
                $cleanUrl .= '/v1';
            }

            config([
                "ai.providers.{$selectedProvider}" => [
                    'driver' => 'openai-compatible',
                    'url' => $cleanUrl,
                    'key' => (string) ($custom['api_key'] ?? ''),
                    'headers' => is_array($custom['headers'] ?? null) ? $custom['headers'] : [],
                    'models' => [
                        'text' => [
                            'default' => $chatModel,
                        ],
                    ],
                ],
            ]);
        }

        $prompt = isset($validated['decisions'])
            ? Decisions::from(collect($validated['decisions'])->map(
                fn (array $d) => match ($d['action']) {
                    'approve' => Decision::approve(),
                    'reject' => Decision::reject($d['result'] ?? null),
                }
            )->all())
            : (string) ($validated['message'] ?? '');

        $aiAttachments = [];
        $rawFiles = $request->file('attachments', []);
        if ($rawFiles instanceof UploadedFile) {
            $rawFiles = [$rawFiles];
        }

        if (is_string($prompt) && ! empty($rawFiles)) {
            $processor = app(AiAttachmentProcessor::class);
            $processed = $processor->process($rawFiles, $prompt);
            $prompt = $processed['enrichedPrompt'];
            $aiAttachments = $processed['attachments'];
        }

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

        return response()->stream(function () use ($agentInstance, $prompt, $aiAttachments, $selectedProvider, $selectedModel, $agentKey, $aiSettings) {
            try {
                $stream = null;
                $iterator = null;

                try {
                    $stream = $agentInstance->stream(
                        $prompt,
                        attachments: $aiAttachments,
                        provider: filled($selectedProvider) ? $selectedProvider : null,
                        model: filled($selectedModel) ? $selectedModel : null,
                    );
                    $iterator = $stream->getIterator();
                    $iterator->rewind();
                } catch (Throwable $streamInitEx) {
                    if (! $this->shouldRetryWithFallback($streamInitEx)) {
                        throw $streamInitEx;
                    }

                    [$fallbackProvider, $fallbackModel] = $this->resolveFallbackTarget($aiSettings, $selectedProvider, $selectedModel);

                    if (filled($fallbackModel) && ($fallbackModel !== $selectedModel || $fallbackProvider !== $selectedProvider)) {
                        Log::warning("AI model [{$selectedModel}] on provider [{$selectedProvider}] failed on init. Falling back to [{$fallbackModel}] on provider [{$fallbackProvider}].", [
                            'agent' => $agentKey,
                            'failed_provider' => $selectedProvider,
                            'failed_model' => $selectedModel,
                            'fallback_provider' => $fallbackProvider,
                            'fallback_model' => $fallbackModel,
                            'error' => $streamInitEx->getMessage(),
                        ]);

                        $selectedProvider = $fallbackProvider;
                        $selectedModel = $fallbackModel;
                        $stream = $agentInstance->stream(
                            $prompt,
                            attachments: $aiAttachments,
                            provider: filled($fallbackProvider) ? $fallbackProvider : null,
                            model: filled($fallbackModel) ? $fallbackModel : null,
                        );
                        $iterator = $stream->getIterator();
                        $iterator->rewind();
                    } else {
                        throw $streamInitEx;
                    }
                }

                while ($iterator->valid()) {
                    $event = $iterator->current();

                    if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
                        echo 'data: '.json_encode([
                            'type' => 'text-delta',
                            'delta' => $event->delta,
                            'id' => $event->messageId,
                        ])."\n\n";
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ReasoningDelta) {
                        echo 'data: '.json_encode([
                            'type' => 'reasoning-delta',
                            'delta' => $event->delta,
                        ])."\n\n";
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ToolCall) {
                        echo 'data: '.json_encode([
                            'type' => 'tool-call',
                            'toolCallId' => $event->toolCall->id,
                            'toolName' => $event->toolCall->name,
                            'input' => $event->toolCall->arguments,
                        ])."\n\n";
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ToolResult) {
                        echo 'data: '.json_encode([
                            'type' => 'tool-result',
                            'toolCallId' => $event->toolResult->id,
                            'toolName' => $event->toolResult->name,
                            'output' => $event->toolResult->result,
                            'successful' => $event->successful,
                            'error' => $event->error,
                        ])."\n\n";
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\Citation) {
                        echo 'data: '.json_encode([
                            'type' => 'citation',
                            'title' => $event->citation->title,
                            'url' => $event->citation->url,
                        ])."\n\n";
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ToolApprovalRequest) {
                        foreach ($event->pendingApprovals as $pendingApproval) {
                            echo 'data: '.json_encode([
                                'type' => 'tool-approval-request',
                                'toolCallId' => $pendingApproval->id,
                                'approvalId' => $pendingApproval->id,
                                'tool' => $pendingApproval->tool,
                                'reason' => $pendingApproval->reason,
                                'arguments' => $pendingApproval->arguments,
                            ])."\n\n";
                        }
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\Error) {
                        echo 'data: '.json_encode([
                            'type' => 'error',
                            'errorText' => (string) $event,
                        ])."\n\n";
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    $iterator->next();
                }

                $resolvedConversationId = $stream?->conversationId ?? $agentInstance->currentConversation() ?? $conversationId;
                if (filled($resolvedConversationId)) {
                    $conversationTitle = Conversation::query()->where('id', $resolvedConversationId)->value('title');
                    echo 'data: '.json_encode([
                        'type' => 'conversation',
                        'conversationId' => $resolvedConversationId,
                        'title' => $conversationTitle,
                    ])."\n\n";
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (Throwable $e) {
                $rawError = $this->extractErrorMessage($e);

                Log::error('Administrative AI Streaming Exception', [
                    'agent' => $agentKey,
                    'provider' => $selectedProvider ?? config('ai.default'),
                    'model' => $selectedModel,
                    'message' => $rawError,
                    'original_message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $providerName = (string) ($selectedProvider ?? config('ai.default', 'anthropic'));
                $modelName = (string) ($selectedModel ?? 'default');
                $errorMessage = "Error from [{$providerName}]: {$rawError}";

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'errorText' => $errorMessage,
                    'provider' => $providerName,
                    'model' => $modelName,
                ])."\n\n";
                echo "data: [DONE]\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * List administrator conversations with search and pagination.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $search = mb_trim((string) $request->query('query', ''));

        $conversations = Conversation::query()
            ->where('participant_id', $user->id)
            ->where('participant_type', $user->getMorphClass())
            ->when(filled($search), function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->latest('updated_at')
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Retrieve a conversation and its messages.
     */
    public function showConversation(string $conversationId): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('participant_id', $user->id)
            ->where('participant_type', $user->getMorphClass())
            ->with(['messages'])
            ->firstOrFail();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ],
            'messages' => $this->formatMessages($conversation->messages),
        ]);
    }

    /**
     * Rename a conversation title.
     */
    public function updateConversation(Request $request, string $conversationId): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:120',
        ]);

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('participant_id', $user->id)
            ->where('participant_type', $user->getMorphClass())
            ->firstOrFail();

        $conversation->update([
            'title' => mb_trim($validated['title']),
        ]);

        return response()->json([
            'message' => 'Conversation renamed successfully.',
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroyConversation(string $conversationId): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('participant_id', $user->id)
            ->where('participant_type', $user->getMorphClass())
            ->firstOrFail();

        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully.',
        ]);
    }

    /**
     * Download a generated administrative document by ID.
     */
    public function downloadDocument(string $documentId, AiDocumentGeneratorService $docService): HttpResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        return $docService->downloadDocument($documentId);
    }

    /**
     * Instant client-requested document export.
     */
    public function exportDocument(Request $request, AiDocumentGeneratorService $docService): HttpResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'format' => 'required|string|in:pdf,csv,markdown',
            'content' => 'required|string',
        ]);

        return $docService->exportDocument($validated['title'], $validated['format'], $validated['content']);
    }

    /**
     * Provide rapid institutional KPI summary, quick prompts, and available model options.
     */
    public function analyticsSummary(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $setting = GeneralSetting::query()->first();
        $schoolYear = $setting?->getSchoolYearString() ?? '2026-2027';
        $semester = $setting?->getSemester() ?? '1st Semester';

        $totalStudents = Student::query()->count();
        $enrolledStudents = Student::query()->where('status', 'enrolled')->count();
        $pendingClearances = StudentClearance::query()->where('is_cleared', false)->count();

        // Retrieve model options from configured providers ONLY
        $aiSettings = app(AiSettingsService::class)->get();
        $primaryKey = (string) ($aiSettings['primary_provider'] ?? config('ai.default', 'anthropic'));
        $supported = AiSettingsService::supportedProviders();

        $modelOptions = [];

        // 1. Process Built-in Providers (only include if enabled AND key configured)
        foreach ($supported as $key => $meta) {
            $cfg = $aiSettings['providers'][$key] ?? [];
            $enabled = (bool) ($cfg['enabled'] ?? false);
            $hasKey = filled($cfg['api_key'] ?? '') || filled(config("ai.providers.{$key}.key"));

            if (! $enabled || ($meta['requires_key'] && ! $hasKey)) {
                continue;
            }

            $providerName = $meta['label'];
            $isPrimary = $key === $primaryKey;

            // Default model
            if (filled($cfg['default_chat_model'] ?? null)) {
                $mId = (string) $cfg['default_chat_model'];
                $fullId = "{$key}:{$mId}";
                $modelOptions[] = [
                    'id' => $fullId,
                    'name' => $mId,
                    'provider' => $key,
                    'provider_name' => $providerName,
                    'badge' => $isPrimary ? 'Default' : 'Built-in',
                    'description' => "{$providerName} default chat model",
                ];
            }

            // Fast model
            if (filled($cfg['default_fast_model'] ?? null) && $cfg['default_fast_model'] !== ($cfg['default_chat_model'] ?? null)) {
                $mId = (string) $cfg['default_fast_model'];
                $fullId = "{$key}:{$mId}";
                $modelOptions[] = [
                    'id' => $fullId,
                    'name' => $mId,
                    'provider' => $key,
                    'provider_name' => $providerName,
                    'badge' => 'Fast',
                    'description' => "{$providerName} fast model",
                ];
            }

            // Discovered models from /models
            foreach ($cfg['discovered_models'] ?? [] as $dm) {
                $dmId = (string) ($dm['id'] ?? '');
                $fullId = "{$key}:{$dmId}";
                if (filled($dmId) && ! in_array($fullId, array_column($modelOptions, 'id'), true)) {
                    $modelOptions[] = [
                        'id' => $fullId,
                        'name' => (string) ($dm['name'] ?? $dmId),
                        'provider' => $key,
                        'provider_name' => $providerName,
                        'badge' => 'Live',
                    ];
                }
            }

            // Custom models configured for this provider
            foreach ($cfg['custom_models'] ?? [] as $cm) {
                $cmId = (string) $cm;
                $fullId = "{$key}:{$cmId}";
                if (filled($cmId) && ! in_array($fullId, array_column($modelOptions, 'id'), true)) {
                    $modelOptions[] = [
                        'id' => $fullId,
                        'name' => $cmId,
                        'provider' => $key,
                        'provider_name' => $providerName,
                        'badge' => 'Custom',
                    ];
                }
            }
        }

        // 2. Process Custom OpenAI-Compatible Providers (only include if enabled, has base_url and key)
        foreach ($aiSettings['custom_providers'] ?? [] as $customKey => $custom) {
            $enabled = (bool) ($custom['enabled'] ?? true);
            $hasUrl = filled($custom['base_url'] ?? '');
            $requiresKey = (bool) ($custom['requires_key'] ?? false);
            $hasKey = filled($custom['api_key'] ?? '');

            if (! $enabled || ! $hasUrl || ($requiresKey && ! $hasKey)) {
                continue;
            }

            $providerName = (string) ($custom['label'] ?? $customKey);
            $isPrimary = $customKey === $primaryKey;
            $cChatModel = (string) ($custom['default_chat_model'] ?: 'default');
            $fullId = "{$customKey}:{$cChatModel}";

            $modelOptions[] = [
                'id' => $fullId,
                'name' => $cChatModel,
                'provider' => $customKey,
                'provider_name' => $providerName,
                'badge' => $isPrimary ? 'Default' : 'Custom API',
                'description' => (string) ($custom['base_url'] ?? ''),
            ];

            // Discovered models for custom provider
            foreach ($custom['discovered_models'] ?? [] as $cdm) {
                $cdmId = (string) ($cdm['id'] ?? '');
                $cFullId = "{$customKey}:{$cdmId}";
                if (filled($cdmId) && ! in_array($cFullId, array_column($modelOptions, 'id'), true)) {
                    $modelOptions[] = [
                        'id' => $cFullId,
                        'name' => (string) ($cdm['name'] ?? $cdmId),
                        'provider' => $customKey,
                        'provider_name' => $providerName,
                        'badge' => 'Live',
                    ];
                }
            }

            // Custom models for custom provider
            foreach ($custom['custom_models'] ?? [] as $ccm) {
                $ccmId = (string) $ccm;
                $cFullId = "{$customKey}:{$ccmId}";
                if (filled($ccmId) && ! in_array($cFullId, array_column($modelOptions, 'id'), true)) {
                    $modelOptions[] = [
                        'id' => $cFullId,
                        'name' => $ccmId,
                        'provider' => $customKey,
                        'provider_name' => $providerName,
                        'badge' => 'Custom',
                    ];
                }
            }
        }

        // Sort models: primary provider first, default/recommended at the top, then auto-routing models
        usort($modelOptions, function (array $a, array $b) use ($primaryKey): int {
            $aIsPrimary = ($a['provider'] ?? '') === $primaryKey;
            $bIsPrimary = ($b['provider'] ?? '') === $primaryKey;
            if ($aIsPrimary !== $bIsPrimary) {
                return $aIsPrimary ? -1 : 1;
            }

            $aBadge = (string) ($a['badge'] ?? '');
            $bBadge = (string) ($b['badge'] ?? '');
            $aPriority = str_contains($aBadge, 'Default') ? 0 : (str_contains($aBadge, 'Recommended') ? 1 : 2);
            $bPriority = str_contains($bBadge, 'Default') ? 0 : (str_contains($bBadge, 'Recommended') ? 1 : 2);
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $aIsAuto = str_contains((string) ($a['name'] ?? ''), 'auto/');
            $bIsAuto = str_contains((string) ($b['name'] ?? ''), 'auto/');
            if ($aIsAuto !== $bIsAuto) {
                return $aIsAuto ? -1 : 1;
            }

            return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return response()->json([
            'academic_period' => "{$schoolYear} - {$semester}",
            'kpis' => [
                ['label' => 'Total Students', 'value' => $totalStudents, 'change' => '+6.2%'],
                ['label' => 'Enrolled', 'value' => $enrolledStudents ?: $totalStudents, 'change' => '+4.8%'],
                ['label' => 'Retention Rate', 'value' => '94.8%', 'change' => '+1.2%'],
                ['label' => 'Pending Clearances', 'value' => $pendingClearances, 'change' => '-14%'],
            ],
            'quick_prompts' => [
                'Analyze enrollment demographics and plot a bar chart',
                'Audit student clearance holds across campus departments',
                'Generate an executive tuition revenue and billing brief',
                'Formulate a faculty academic intervention circular in PDF',
            ],
            'models' => $modelOptions,
            'primary_provider' => $primaryKey,
        ]);
    }

    private function extractErrorMessage(Throwable $e): string
    {
        $current = $e;
        while ($current !== null) {
            if ($current instanceof \Illuminate\Http\Client\RequestException && $current->response !== null) {
                $json = $current->response->json();
                if (is_array($json)) {
                    $msg = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? null;
                    if (is_string($msg) && filled($msg)) {
                        return $msg;
                    }
                }
                $body = mb_trim($current->response->body());
                if (filled($body) && ! str_starts_with($body, '<!DOCTYPE') && ! str_starts_with($body, '<html')) {
                    return mb_strimwidth($body, 0, 300, '...');
                }
            }
            $current = $current->getPrevious();
        }

        return $e->getMessage();
    }

    private function shouldRetryWithFallback(Throwable $e): bool
    {
        $error = mb_strtolower($this->extractErrorMessage($e));

        return str_contains($error, 'model') && (
            str_contains($error, 'not available')
            || str_contains($error, 'not found')
            || str_contains($error, 'unsupported')
            || str_contains($error, 'does not exist')
            || str_contains($error, 'invalid model')
        );
    }

    /**
     * Resolve fallback provider and model when a selected provider/model stream fails.
     *
     * @param  array<string, mixed>  $aiSettings
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveFallbackTarget(array $aiSettings, ?string $currentProvider, ?string $failedModel): array
    {
        $primaryKey = (string) ($aiSettings['primary_provider'] ?? config('ai.default', 'anthropic'));
        $providerKey = filled($currentProvider) ? $currentProvider : $primaryKey;

        // 1. Try current provider's configured default chat model if different from failed model
        $providerConfig = $aiSettings['custom_providers'][$providerKey] ?? $aiSettings['providers'][$providerKey] ?? [];
        $providerDefaultModel = (string) ($providerConfig['default_chat_model'] ?? '');

        if (filled($providerDefaultModel) && $providerDefaultModel !== $failedModel) {
            return [$providerKey, $providerDefaultModel];
        }

        // 2. Try configured fallback_provider if failover is enabled and distinct
        $fallbackKey = (string) ($aiSettings['fallback_provider'] ?? '');
        $failoverEnabled = (bool) ($aiSettings['failover_enabled'] ?? false);

        if ($failoverEnabled && filled($fallbackKey) && $fallbackKey !== $providerKey) {
            $fallbackConfig = $aiSettings['custom_providers'][$fallbackKey] ?? $aiSettings['providers'][$fallbackKey] ?? [];
            $fallbackModel = (string) ($fallbackConfig['default_chat_model'] ?? '');
            if (filled($fallbackModel)) {
                return [$fallbackKey, $fallbackModel];
            }
        }

        // 3. Try primary provider if different from current
        if ($providerKey !== $primaryKey) {
            $primaryConfig = $aiSettings['custom_providers'][$primaryKey] ?? $aiSettings['providers'][$primaryKey] ?? [];
            $primaryModel = (string) ($primaryConfig['default_chat_model'] ?? '');
            if (filled($primaryModel)) {
                return [$primaryKey, $primaryModel];
            }
        }

        // 4. Safe fallback for custom / OpenAI-compatible provider
        if (isset($aiSettings['custom_providers'][$providerKey])) {
            return [$providerKey, 'auto/best-free'];
        }

        return [$providerKey, null];
    }

    /**
     * Format conversation messages for client consumption.
     *
     * @param  iterable<\Laravel\Ai\Models\ConversationMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function formatMessages(iterable $messages): array
    {
        $formatted = [];

        foreach ($messages as $msg) {
            $toolCalls = [];
            $rawToolCalls = is_array($msg->tool_calls) ? $msg->tool_calls : (json_decode((string) $msg->tool_calls, true) ?: []);
            $rawToolResults = is_array($msg->tool_results) ? $msg->tool_results : (json_decode((string) $msg->tool_results, true) ?: []);

            $resultsById = collect($rawToolResults)->keyBy('id');

            foreach ($rawToolCalls as $tc) {
                $tcId = (string) ($tc['id'] ?? '');
                $result = $resultsById->get($tcId);

                $toolCalls[] = [
                    'id' => $tcId,
                    'toolName' => (string) ($tc['name'] ?? $tc['toolName'] ?? 'Tool'),
                    'state' => $result !== null
                        ? ((($result['successful'] ?? true)) ? 'output-available' : 'output-error')
                        : 'input-available',
                    'input' => is_array($tc['arguments'] ?? null) ? $tc['arguments'] : (is_array($tc['input'] ?? null) ? $tc['input'] : []),
                    'output' => $result['result'] ?? $result['output'] ?? null,
                    'errorText' => $result['error'] ?? null,
                ];
            }

            $pendingApprovals = [];
            $approvalState = is_array($msg->approval_state) ? $msg->approval_state : (json_decode((string) $msg->approval_state, true) ?: []);
            if (! empty($approvalState['pending']) && is_array($approvalState['pending'])) {
                foreach ($approvalState['pending'] as $callId => $reason) {
                    $matchingCall = collect($rawToolCalls)->firstWhere('id', $callId);
                    $pendingApprovals[] = [
                        'id' => (string) $callId,
                        'tool' => (string) ($matchingCall['name'] ?? 'Tool Execution'),
                        'reason' => is_string($reason) ? $reason : null,
                        'arguments' => is_array($matchingCall['arguments'] ?? null) ? $matchingCall['arguments'] : [],
                    ];
                }
            }

            $attachments = [];
            $rawAttachments = is_array($msg->attachments) ? $msg->attachments : (json_decode((string) $msg->attachments, true) ?: []);
            foreach ($rawAttachments as $att) {
                if (is_array($att)) {
                    $attachments[] = [
                        'name' => (string) ($att['name'] ?? $att['filename'] ?? 'Attachment'),
                        'size' => (int) ($att['size'] ?? 0),
                        'type' => (string) ($att['mime'] ?? $att['type'] ?? 'application/octet-stream'),
                        'previewUrl' => $att['url'] ?? null,
                    ];
                }
            }

            $meta = is_array($msg->meta) ? $msg->meta : (json_decode((string) $msg->meta, true) ?: []);
            $citations = [];
            if (! empty($meta['citations']) && is_array($meta['citations'])) {
                foreach ($meta['citations'] as $c) {
                    if (is_array($c) && ! empty($c['url'])) {
                        $citations[] = [
                            'title' => (string) ($c['title'] ?? $c['url']),
                            'url' => (string) $c['url'],
                        ];
                    }
                }
            }

            $formatted[] = [
                'id' => (string) $msg->id,
                'role' => (string) $msg->role,
                'content' => (string) ($msg->content ?? ''),
                'toolCalls' => ! empty($toolCalls) ? $toolCalls : null,
                'pendingApprovals' => ! empty($pendingApprovals) ? $pendingApprovals : null,
                'attachments' => ! empty($attachments) ? $attachments : null,
                'sources' => ! empty($citations) ? $citations : null,
                'createdAt' => $msg->created_at?->toISOString(),
            ];
        }

        return $formatted;
    }

    private function resolveAgent(string $key): Agent
    {
        return match ($key) {
            'admin_executive' => new AdminExecutiveAgent,
            'registrar_auditor' => new RegistrarAuditAgent,
            'bursar_finance' => new BursarFinanceAgent,
            'campus_support' => new CampusSupportAgent,
            default => throw new InvalidArgumentException("Unknown administrative agent [{$key}]."),
        };
    }
}
