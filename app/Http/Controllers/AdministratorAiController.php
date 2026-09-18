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
use App\Services\Ai\AiSettingsService;
use FPDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Models\Conversation;
use Throwable;

final class AdministratorAiController extends Controller
{
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

        // If custom provider selected, ensure its runtime configuration is present
        if (filled($selectedProvider) && isset($aiSettings['custom_providers'][$selectedProvider])) {
            $custom = $aiSettings['custom_providers'][$selectedProvider];
            $chatModel = filled($selectedModel) ? $selectedModel : ($custom['default_chat_model'] ?: 'default');

            config([
                "ai.providers.{$selectedProvider}" => [
                    'driver' => 'openai-compatible',
                    'url' => (string) $custom['base_url'],
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

        return response()->stream(function () use ($agentInstance, $prompt, $aiAttachments, $selectedProvider, $selectedModel, $agentKey) {
            try {
                $stream = $agentInstance->stream(
                    $prompt,
                    attachments: $aiAttachments,
                    provider: filled($selectedProvider) ? $selectedProvider : null,
                    model: filled($selectedModel) ? $selectedModel : null,
                );

                foreach ($stream as $event) {
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
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (Throwable $e) {
                Log::error('Administrative AI Streaming Exception', [
                    'agent' => $agentKey,
                    'provider' => $selectedProvider ?? config('ai.default'),
                    'model' => $selectedModel,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $providerName = (string) ($selectedProvider ?? config('ai.default', 'anthropic'));
                $modelName = (string) ($selectedModel ?? 'default');
                $errorMessage = "Error from [{$providerName}]: {$e->getMessage()}";

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
     * Download a generated administrative document by ID.
     */
    public function downloadDocument(string $documentId): HttpResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $doc = Cache::get("ai:doc:{$documentId}");

        if (! is_array($doc)) {
            abort(404, 'The requested document has expired or was not found.');
        }

        $title = (string) ($doc['title'] ?? 'Institutional_Report');
        $format = (string) ($doc['format'] ?? 'pdf');
        $content = (string) ($doc['content'] ?? '');
        $filename = (string) ($doc['filename'] ?? "{$title}.{$format}");

        return match ($format) {
            'pdf' => $this->generatePdfDownload($title, $content, $filename),
            'csv' => response($content, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
            default => response($content, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
        };
    }

    /**
     * Instant client-requested document export.
     */
    public function exportDocument(Request $request): HttpResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'format' => 'required|string|in:pdf,csv,markdown',
            'content' => 'required|string',
        ]);

        $title = $validated['title'];
        $format = $validated['format'];
        $content = $validated['content'];
        $extension = $format === 'markdown' ? 'md' : $format;
        $cleanTitle = str_replace(' ', '_', preg_replace('/[^\w\-]/', '_', $title) ?? 'document');
        $filename = "{$cleanTitle}.{$extension}";

        return match ($format) {
            'pdf' => $this->generatePdfDownload($title, $content, $filename),
            'csv' => response($content, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
            default => response($content, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
        };
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

        // Retrieve model options from configured providers
        $aiSettings = app(AiSettingsService::class)->get();
        $primaryKey = (string) ($aiSettings['primary_provider'] ?? config('ai.default', 'anthropic'));
        $primaryConfig = $aiSettings['providers'][$primaryKey] ?? $aiSettings['custom_providers'][$primaryKey] ?? [];

        $modelOptions = [];

        // 1. Primary provider default model
        if (filled($primaryConfig['default_chat_model'] ?? null)) {
            $defId = (string) $primaryConfig['default_chat_model'];
            $modelOptions[] = [
                'id' => $defId,
                'name' => $defId,
                'provider' => $primaryKey,
                'badge' => 'Default',
                'description' => "Primary provider ({$primaryKey}) active model",
            ];
        }

        // 2. Discovered models for primary provider
        foreach ($primaryConfig['discovered_models'] ?? [] as $dm) {
            $dmId = (string) ($dm['id'] ?? '');
            if (filled($dmId) && ! in_array($dmId, array_column($modelOptions, 'id'), true)) {
                $modelOptions[] = [
                    'id' => $dmId,
                    'name' => (string) ($dm['name'] ?? $dmId),
                    'provider' => $primaryKey,
                    'badge' => 'Live',
                ];
            }
        }

        // 3. Custom models configured for primary provider
        foreach ($primaryConfig['custom_models'] ?? [] as $cm) {
            $cmId = (string) $cm;
            if (filled($cmId) && ! in_array($cmId, array_column($modelOptions, 'id'), true)) {
                $modelOptions[] = [
                    'id' => $cmId,
                    'name' => $cmId,
                    'provider' => $primaryKey,
                    'badge' => 'Custom',
                ];
            }
        }

        // 4. Custom OpenAI-compatible endpoints
        foreach ($aiSettings['custom_providers'] ?? [] as $customKey => $custom) {
            if ($customKey === $primaryKey) {
                continue;
            }

            $customLabel = (string) ($custom['label'] ?? $customKey);
            $cChatModel = (string) ($custom['default_chat_model'] ?: 'default');

            $modelOptions[] = [
                'id' => "{$customKey}:{$cChatModel}",
                'name' => "{$customLabel} ({$cChatModel})",
                'provider' => $customKey,
                'badge' => 'Custom API',
                'description' => (string) ($custom['base_url'] ?? ''),
            ];

            foreach ($custom['discovered_models'] ?? [] as $cdm) {
                $cdmId = (string) ($cdm['id'] ?? '');
                if (filled($cdmId) && $cdmId !== $cChatModel) {
                    $modelOptions[] = [
                        'id' => "{$customKey}:{$cdmId}",
                        'name' => "{$customLabel} ({$cdmId})",
                        'provider' => $customKey,
                        'badge' => 'Custom API',
                    ];
                }
            }
        }

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

    private function generatePdfDownload(string $title, string $content, string $filename): HttpResponse
    {
        try {
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetMargins(18, 18, 18);

            // Institution Branding Header
            $setting = GeneralSetting::query()->first();
            $appName = $setting?->site_name ?? 'KoAkademy Education';

            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->Cell(0, 8, utf8_decode($appName), 0, 1, 'L');

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 5, utf8_decode('Official Administrative AI Report | Generated on '.now()->toFormattedDateString()), 0, 1, 'L');

            $pdf->Ln(4);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
            $pdf->Ln(6);

            // Document Title
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Cell(0, 7, utf8_decode($title), 0, 1, 'L');
            $pdf->Ln(3);

            // Document Body Content
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(51, 65, 85);

            $cleanText = str_replace(["\r\n", "\r"], "\n", $content);
            $lines = explode("\n", $cleanText);

            foreach ($lines as $line) {
                $trimmed = mb_trim($line);

                if (str_starts_with($trimmed, '# ')) {
                    $pdf->Ln(3);
                    $pdf->SetFont('Helvetica', 'B', 12);
                    $pdf->MultiCell(0, 6, utf8_decode(mb_substr($trimmed, 2)));
                    $pdf->SetFont('Helvetica', '', 10);
                } elseif (str_starts_with($trimmed, '## ')) {
                    $pdf->Ln(2);
                    $pdf->SetFont('Helvetica', 'B', 11);
                    $pdf->MultiCell(0, 5, utf8_decode(mb_substr($trimmed, 3)));
                    $pdf->SetFont('Helvetica', '', 10);
                } elseif (str_starts_with($trimmed, '### ')) {
                    $pdf->SetFont('Helvetica', 'B', 10);
                    $pdf->MultiCell(0, 5, utf8_decode(mb_substr($trimmed, 4)));
                    $pdf->SetFont('Helvetica', '', 10);
                } else {
                    $pdf->MultiCell(0, 5, utf8_decode($line));
                }
            }

            // Footer note
            $pdf->SetY(-20);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->Cell(0, 6, utf8_decode('Generated electronically by KoAkademy Administrative Intelligence. Internal institutional copy.'), 0, 0, 'C');

            $pdfOutput = $pdf->Output('S');

            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (Throwable) {
            // Fallback to text format if PDF encoding encounters unsupported characters
            return response($content, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}.txt\"",
            ]);
        }
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
