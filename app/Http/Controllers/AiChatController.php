<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\AdminExecutiveAgent;
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
use InvalidArgumentException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Models\Conversation;
use Laravel\Pennant\Feature;
use Throwable;

final class AiChatController extends Controller
{
    /**
     * Send a prompt or tool approval decision to the selected agent and stream response.
     */
    public function chat(Request $request): mixed
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $aiSettingsService = app(AiSettingsService::class);
        $aiSettingsService->applyRuntimeConfig();

        $aiSettings = $aiSettingsService->get();
        if (! ($aiSettings['enabled'] ?? true)) {
            abort(503, 'AI Assistant services are currently disabled by the administration.');
        }

        $validated = $request->validate([
            'agent' => ['required', 'string', Rule::in([
                'admin_executive',
                'student_advisor',
                'faculty_copilot',
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

        $featureClass = match ($validated['agent']) {
            'admin_executive' => AiRegistrarAuditor::class,
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
            : (string) ($validated['message'] ?? '');

        $aiAttachments = [];
        $rawFiles = $request->file('attachments', []);
        if ($rawFiles instanceof UploadedFile) {
            $rawFiles = [$rawFiles];
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

        $selectedProvider = $validated['provider'] ?? null;
        $selectedModel = $validated['model'] ?? null;

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

        $supportsDocumentAttachments = $this->providerSupportsDocumentAttachments($selectedProvider, $selectedModel);
        if (is_string($prompt) && ! empty($rawFiles)) {
            $processor = app(AiAttachmentProcessor::class);
            $processed = $processor->process($rawFiles, $prompt, $supportsDocumentAttachments);
            $prompt = $processed['enrichedPrompt'];
            $aiAttachments = $processed['attachments'];
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

        $supportsDocumentAttachments = $this->providerSupportsDocumentAttachments($selectedProvider, $selectedModel);
        if (is_string($prompt) && ! empty($rawFiles)) {
            $processor = app(AiAttachmentProcessor::class);
            $processed = $processor->process($rawFiles, $prompt, $supportsDocumentAttachments);
            $prompt = $processed['enrichedPrompt'];
            $aiAttachments = $processed['attachments'];
        }

        $agentKey = $validated['agent'];

        return response()->stream(function () use ($agentInstance, $prompt, $aiAttachments, $selectedProvider, $selectedModel, $agentKey, $aiSettings, $supportsDocumentAttachments) {
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
                        Log::warning("General AI model [{$selectedModel}] on provider [{$selectedProvider}] failed on init. Falling back to [{$fallbackModel}] on provider [{$fallbackProvider}].", [
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

                Log::error('AI Streaming Exception', [
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

    /**
     * Download a generated document by ID.
     */
    public function downloadDocument(string $documentId, AiDocumentGeneratorService $docService): HttpResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $docService->downloadDocument($documentId);
    }

    /**
     * Instant client-requested document export.
     */
    public function exportDocument(Request $request, AiDocumentGeneratorService $docService): HttpResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'format' => 'required|string|in:pdf,csv,markdown',
            'content' => 'required|string',
        ]);

        return $docService->exportDocument($validated['title'], $validated['format'], $validated['content']);
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
     * Resolve a configured provider/model fallback without sending an OmniRoute
     * model identifier to a provider that cannot understand it.
     *
     * @param  array<string, mixed>  $aiSettings
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveFallbackTarget(array $aiSettings, ?string $currentProvider, ?string $failedModel): array
    {
        $primaryKey = (string) ($aiSettings['primary_provider'] ?? config('ai.default', 'anthropic'));
        $providerKey = filled($currentProvider) ? $currentProvider : $primaryKey;

        // Try another configured default for the current provider first.
        $providerConfig = $aiSettings['custom_providers'][$providerKey] ?? $aiSettings['providers'][$providerKey] ?? [];
        $providerDefaultModel = (string) ($providerConfig['default_chat_model'] ?? '');

        if (filled($providerDefaultModel) && $providerDefaultModel !== $failedModel) {
            return [$providerKey, $providerDefaultModel];
        }

        // Then use the explicitly configured failover provider when enabled.
        $fallbackKey = (string) ($aiSettings['fallback_provider'] ?? '');
        $failoverEnabled = (bool) ($aiSettings['failover_enabled'] ?? false);

        if ($failoverEnabled && filled($fallbackKey) && $fallbackKey !== $providerKey) {
            $fallbackConfig = $aiSettings['custom_providers'][$fallbackKey] ?? $aiSettings['providers'][$fallbackKey] ?? [];
            $fallbackModel = (string) ($fallbackConfig['default_chat_model'] ?? '');
            if (filled($fallbackModel)) {
                return [$fallbackKey, $fallbackModel];
            }
        }

        // A distinct primary provider is the final configured fallback.
        if ($providerKey !== $primaryKey) {
            $primaryConfig = $aiSettings['custom_providers'][$primaryKey] ?? $aiSettings['providers'][$primaryKey] ?? [];
            $primaryModel = (string) ($primaryConfig['default_chat_model'] ?? '');
            if (filled($primaryModel)) {
                return [$primaryKey, $primaryModel];
            }
        }

        // OmniRoute-style custom providers support this stable route alias.
        if (isset($aiSettings['custom_providers'][$providerKey])) {
            return [$providerKey, 'auto/best-free'];
        }

        return [$providerKey, null];
    }

    private function streamWithDocumentCompatibility(
        mixed $agentInstance,
        string $prompt,
        array $attachments,
        ?string $provider,
        ?string $model,
        bool $supportsDocuments,
    ): mixed {
        $documentsRemoved = array_values(array_filter(
            $attachments,
            static fn (mixed $attachment): bool => ! $attachment instanceof Document,
        ));

        try {
            return $agentInstance->stream(
                $prompt,
                attachments: $attachments,
                provider: filled($provider) ? $provider : null,
                model: filled($model) ? $model : null,
            );
        } catch (Throwable $exception) {
            $message = mb_strtolower($this->extractErrorMessage($exception));
            $documentError = str_contains($message, 'does not support document attachments')
                || str_contains($message, 'document attachments are not supported')
                || str_contains($message, 'only image attachments are supported');

            if (! $documentError || $documentsRemoved === $attachments) {
                throw $exception;
            }

            Log::warning('AI provider rejected document attachments; retrying with extracted text and images only.', [
                'provider' => $provider,
                'model' => $model,
                'agent' => $agentInstance::class,
            ]);

            return $agentInstance->stream(
                $prompt,
                attachments: $documentsRemoved,
                provider: filled($provider) ? $provider : null,
                model: filled($model) ? $model : null,
            );
        }
    }

    private function providerSupportsDocumentAttachments(?string $provider, ?string $model): bool
    {
        $settings = app(AiSettingsService::class)->get();
        if (filled($provider) && isset($settings['custom_providers'][$provider])) {
            return false;
        }

        if (filled($provider)) {
            $providerKey = str_contains($provider, ':') ? explode(':', $provider, 2)[0] : $provider;
            if ($providerKey === 'openai-compatible'
                || isset($settings['custom_providers'][$providerKey])
                || config("ai.providers.{$providerKey}.driver") === 'openai-compatible') {
                return false;
            }

            $supported = AiSettingsService::supportedProviders();

            return isset($supported[$providerKey])
                && in_array($supported[$providerKey]['driver'], ['openai', 'anthropic', 'gemini'], true);
        }

        $primary = (string) ($settings['primary_provider'] ?? config('ai.default', 'anthropic'));

        return $this->providerSupportsDocumentAttachments($primary, $model);
    }

    private function resolveAgent(string $key): Agent
    {
        return match ($key) {
            'admin_executive' => new AdminExecutiveAgent,
            'student_advisor' => new StudentAdvisorAgent,
            'faculty_copilot' => new FacultyCopilotAgent,
            'registrar_auditor' => new RegistrarAuditAgent,
            'bursar_finance' => new BursarFinanceAgent,
            'campus_support' => new CampusSupportAgent,
            default => throw new InvalidArgumentException("Unknown agent [{$key}]."),
        };
    }
}
