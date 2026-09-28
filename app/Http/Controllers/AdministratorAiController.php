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
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\Document;
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
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,rtf,odt,ods,xlsx,xls,csv,tsv,txt,md,json,sql,log,png,jpg,jpeg,webp,gif,bmp,tif,tiff'],
        ]);

        $agentKey = $validated['agent'] ?? 'admin_executive';
        $agent = $this->resolveAgent($agentKey);

        $selectedProvider = $validated['provider'] ?? null;
        $selectedModel = $validated['model'] ?? null;
        $modelProviderPrefix = filled($selectedModel) && str_contains($selectedModel, ':')
            ? explode(':', $selectedModel, 2)[0]
            : null;
        if ($modelProviderPrefix !== null && $selectedProvider === null) {
            [$selectedProvider, $selectedModel] = explode(':', $selectedModel, 2);
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
        // Attachment support is derived from the server's configured model list.
        $supportsDocumentAttachments = $this->providerSupportsDocumentAttachments($selectedProvider, $selectedModel);
        if ($rawFiles instanceof UploadedFile) {
            $rawFiles = [$rawFiles];
        }

        if (is_string($prompt) && ! empty($rawFiles)) {
            $processor = app(AiAttachmentProcessor::class);
            $processed = $processor->process($rawFiles, $prompt, $supportsDocumentAttachments);
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

        return response()->stream(function () use ($agentInstance, $prompt, $aiAttachments, $selectedProvider, $selectedModel, $agentKey, $aiSettings, $supportsDocumentAttachments) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            try {
                $stream = null;
                $iterator = null;

                try {
                    $stream = $this->streamWithDocumentCompatibility(
                        $agentInstance,
                        $prompt,
                        $aiAttachments,
                        $selectedProvider,
                        $selectedModel,
                        $supportsDocumentAttachments,
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
                        $stream = $this->streamWithDocumentCompatibility(
                            $agentInstance,
                            $prompt,
                            $aiAttachments,
                            $fallbackProvider,
                            $fallbackModel,
                            $this->providerSupportsDocumentAttachments($fallbackProvider, $fallbackModel),
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
            $supportsDocuments = in_array($key, ['openai', 'anthropic', 'gemini'], true)
                && config("ai.providers.{$key}.driver") !== 'openai-compatible';

            // Default model
            if (filled($cfg['default_chat_model'] ?? null)) {
                $mId = (string) $cfg['default_chat_model'];
                $fullId = "{$key}:{$mId}";
                $modelOptions[] = [
                    'id' => $fullId,
                    'name' => $mId,
                    'provider' => $key,
                    'provider_name' => $providerName,
                    'supports_documents' => $supportsDocuments,
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
                    'supports_documents' => $supportsDocuments,
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
                        'supports_documents' => $supportsDocuments,
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
                        'supports_documents' => $supportsDocuments,
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
                'supports_documents' => false,
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
                        'supports_documents' => false,
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
                        'supports_documents' => false,
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

    /**
     * Stream using only images for image-only providers, retrying document
     * capability errors after dropping Document inputs. Extracted spreadsheet,
     * text, and PDF content has already been added to the prompt context.
     *
     * @param  array<int, mixed>  $attachments
     */
    private function streamWithDocumentCompatibility(
        mixed $agentInstance,
        string $prompt,
        array $attachments,
        ?string $provider,
        ?string $model,
        bool $supportsDocuments,
    ): mixed {
        $providerValue = filled($provider) ? $provider : null;
        $documentFreeAttachments = array_values(array_filter(
            $attachments,
            static fn (mixed $attachment): bool => ! $attachment instanceof Document,
        ));

        try {
            return $agentInstance->stream(
                $prompt,
                attachments: $attachments,
                provider: $providerValue,
                model: filled($model) ? $model : null,
            );
        } catch (Throwable $exception) {
            $message = mb_strtolower($this->extractErrorMessage($exception));
            $isAttachmentCapabilityError = str_contains($message, 'does not support document attachments')
                || str_contains($message, 'document attachments are not supported')
                || str_contains($message, 'only image attachments are supported');

            if (! $isAttachmentCapabilityError || $documentFreeAttachments === $attachments) {
                throw $exception;
            }

            Log::warning('AI provider rejected document attachments; retrying with extracted prompt text and image attachments only.', [
                'provider' => $provider,
                'model' => $model,
                'agent' => $agentInstance::class,
            ]);

            return $agentInstance->stream(
                $prompt,
                attachments: $documentFreeAttachments,
                provider: $providerValue,
                model: filled($model) ? $model : null,
            );
        }
    }

    private function providerSupportsDocumentAttachments(?string $provider, ?string $model): bool
    {
        $settings = app(AiSettingsService::class)->get();
        $modelOption = $this->configuredModelOption($settings, $provider, $model);
        if ($modelOption !== null) {
            return (bool) ($modelOption['supports_documents'] ?? false);
        }

        if (filled($provider) && isset($settings['custom_providers'][$provider])) {
            return false;
        }

        if (filled($provider)) {
            $providerKey = $provider;
            if (str_contains($providerKey, ':')) {
                [$providerKey] = explode(':', $providerKey, 2);
            }

            $configuredDriver = data_get($settings, "custom_providers.{$providerKey}.driver")
                ?? data_get($settings, "providers.{$providerKey}.driver")
                ?? config("ai.providers.{$providerKey}.driver");

            if ($configuredDriver === 'openai-compatible'
                || isset($settings['custom_providers'][$providerKey])
                || $providerKey === 'openai-compatible') {
                return false;
            }

            $supported = AiSettingsService::supportedProviders();
            if (isset($supported[$providerKey])) {
                return in_array($supported[$providerKey]['driver'] ?? $providerKey, ['openai', 'anthropic', 'gemini'], true)
                    && config("ai.providers.{$providerKey}.driver") !== 'openai-compatible';
            }

            return false;
        }

        $provider = (string) ($settings['primary_provider'] ?? config('ai.default', 'anthropic'));
        if (isset($settings['custom_providers'][$provider])) {
            return false;
        }

        $supported = AiSettingsService::supportedProviders();

        return isset($supported[$provider])
            && in_array($supported[$provider]['driver'] ?? $provider, ['openai', 'anthropic', 'gemini'], true)
            && config("ai.providers.{$provider}.driver") !== 'openai-compatible';
    }

    /** @param array<string, mixed> $settings @return array<string, mixed>|null */
    private function configuredModelOption(array $settings, ?string $provider, ?string $model): ?array
    {
        if (blank($model)) {
            return null;
        }

        $options = $this->availableModelOptions($settings);
        foreach ($options as $option) {
            $optionId = (string) ($option['id'] ?? '');
            $optionModel = str_contains($optionId, ':') ? explode(':', $optionId, 2)[1] : $optionId;
            $optionProvider = $option['provider'] ?? (str_contains($optionId, ':') ? explode(':', $optionId, 2)[0] : null);
            if ($optionModel !== $model && $optionId !== $model) {
                continue;
            }
            if (filled($provider) && $optionProvider !== $provider) {
                continue;
            }
            if (! filled($provider) && filled($optionProvider) && $optionProvider !== $settings['primary_provider']) {
                continue;
            }

            return $option;
        }

        return null;
    }

    /** @param array<string, mixed> $settings @return list<array<string, mixed>> */
    private function availableModelOptions(array $settings): array
    {
        $options = [];
        foreach (AiSettingsService::supportedProviders() as $key => $meta) {
            $config = $settings['providers'][$key] ?? [];
            if (! (bool) ($config['enabled'] ?? false)) {
                continue;
            }
            if (($meta['requires_key'] ?? false) && blank($config['api_key'] ?? config("ai.providers.{$key}.key"))) {
                continue;
            }
            $configuredDriver = config("ai.providers.{$key}.driver");
            $supportsDocuments = in_array($key, ['openai', 'anthropic', 'gemini'], true)
                && $configuredDriver !== 'openai-compatible';
            foreach (['default_chat_model', 'default_fast_model'] as $modelKey) {
                $model = $config[$modelKey] ?? null;
                if (filled($model)) {
                    $options[] = ['id' => "{$key}:{$model}", 'provider' => $key, 'supports_documents' => $supportsDocuments];
                }
            }
            $customModels = $config['custom_models'] ?? [];
            foreach ($customModels as $customModel) {
                $model = is_array($customModel) ? ($customModel['id'] ?? null) : $customModel;
                if (filled($model)) {
                    $options[] = ['id' => "{$key}:{$model}", 'provider' => $key, 'supports_documents' => $supportsDocuments];
                }
            }
            foreach ($config['discovered_models'] ?? [] as $discoveredModel) {
                $model = is_array($discoveredModel) ? ($discoveredModel['id'] ?? null) : $discoveredModel;
                if (filled($model)) {
                    $options[] = ['id' => "{$key}:{$model}", 'provider' => $key, 'supports_documents' => $supportsDocuments];
                }
            }
        }
        foreach ($settings['custom_providers'] ?? [] as $key => $config) {
            if (! (bool) ($config['enabled'] ?? true) || blank($config['base_url'] ?? null)) {
                continue;
            }
            $models = array_filter([
                $config['default_chat_model'] ?? null,
                $config['default_fast_model'] ?? null,
                ...($config['custom_models'] ?? []),
                ...array_map(static fn ($item) => is_array($item) ? ($item['id'] ?? null) : $item, $config['discovered_models'] ?? []),
            ]);
            foreach (array_unique($models) as $model) {
                $options[] = ['id' => "{$key}:{$model}", 'provider' => $key, 'supports_documents' => (bool) ($config['supports_documents'] ?? false)];
            }
        }

        return $options;
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
            $rawToolCalls = $msg->tool_calls;

            foreach ($rawToolCalls as $tc) {
                $tcId = (string) ($tc['id'] ?? '');
                $hasResult = array_key_exists('result', $tc);

                $toolCalls[] = [
                    'id' => $tcId,
                    'toolName' => (string) ($tc['name'] ?? $tc['toolName'] ?? 'Tool'),
                    'state' => $hasResult
                        ? (($tc['failed'] ?? false) ? 'output-error' : 'output-available')
                        : 'input-available',
                    'input' => is_array($tc['arguments'] ?? null) ? $tc['arguments'] : (is_array($tc['input'] ?? null) ? $tc['input'] : []),
                    'output' => $tc['result'] ?? null,
                    'errorText' => ($tc['failed'] ?? false) ? ($tc['result'] ?? 'Tool execution failed.') : null,
                ];
            }

            $pendingApprovals = [];
            foreach ($rawToolCalls as $call) {
                if (PendingApproval::isPending($call)) {
                    $pendingApprovals[] = [
                        'id' => (string) ($call['id'] ?? ''),
                        'tool' => (string) ($call['name'] ?? 'Tool Execution'),
                        'reason' => $call['approval_reason'] ?? null,
                        'arguments' => is_array($call['arguments'] ?? null) ? $call['arguments'] : [],
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
