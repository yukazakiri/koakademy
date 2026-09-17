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
use App\Services\Ai\AiSettingsService;
use FPDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
     * Handle streaming AI assistant chat for administrators.
     */
    public function chat(Request $request): mixed
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessAdminPortal(), 403);

        $aiSettings = app(AiSettingsService::class)->get();
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
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'message' => ['nullable', 'string', 'required_without:decisions', 'prohibits:decisions'],
            'decisions' => ['nullable', 'array', 'required_without:message', 'prohibits:message'],
            'decisions.*.action' => ['required_with:decisions', Rule::in(['approve', 'reject'])],
            'decisions.*.result' => ['nullable', 'string'],
        ]);

        $agentKey = $validated['agent'] ?? 'admin_executive';
        $agent = $this->resolveAgent($agentKey);

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

        return $agentInstance
            ->stream($prompt)
            ->usingVercelDataProtocol();
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
     * Provide rapid institutional KPI summary for administrative chat.
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
                'Formulate a faculty academic intervention circular',
            ],
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
