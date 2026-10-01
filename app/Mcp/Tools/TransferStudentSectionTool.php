<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\Ai\InstitutionEntityResolver;
use App\Services\Ai\SectionTransferService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Moves a student between sections of the same subject.
 *
 * The tool is deliberately split into a read-only `preview` and a mutating
 * `transfer`. The preview is what makes the tool safe to use conversationally:
 * it reports the resulting timetable, clashes with the student's other classes,
 * seat availability in the destination, and the tuition delta, so a client can
 * tell the requester what will happen — and what to do instead — before anything
 * is written.
 */
#[Description('Move a student between class sections of the same subject. Use action="preview" first to see the resulting timetable, schedule conflicts, seat availability, and tuition impact before committing with action="transfer". Omit to_section to keep the subject but drop the section.')]
#[IsDestructive]
#[IsIdempotent]
final class TransferStudentSectionTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?SectionTransferService $transfers = null,
        private ?InstitutionEntityResolver $entities = null,
    ) {
        $this->transfers ??= app(SectionTransferService::class);
        $this->entities ??= app(InstitutionEntityResolver::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action', 'preview'));

        $user = $action === 'preview'
            ? $this->requireRead($request)
            : $this->requireWrite($request);

        $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view class enrollments.');

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'in:preview,transfer'],
            'student' => ['nullable', 'string', 'max:120'],
            'student_id' => ['nullable'],
            'subject' => ['nullable', 'string', 'max:120'],
            'from_class_id' => ['nullable', 'integer', 'min:1'],
            'to_section' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:500'],
            'force' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:96'],
        ]);

        $identifier = $validated['student'] ?? $validated['student_id'] ?? null;

        if (blank($identifier)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], ['student' => 'A student number, name, or email is required.']),
            );
        }

        $student = $this->entities->student($identifier, $this->school());

        if ($action === 'preview') {
            return Response::structured($this->transfers->preview(
                student: $student,
                sourceIdentifier: $validated['subject'] ?? null,
                destinationIdentifier: $validated['to_section'] ?? null,
                fromClassId: isset($validated['from_class_id']) ? (int) $validated['from_class_id'] : null,
            ));
        }

        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to move students between sections.');

        return Response::structured($this->transfers->transfer(
            student: $student,
            sourceIdentifier: $validated['subject'] ?? null,
            destinationIdentifier: $validated['to_section'] ?? null,
            actor: $user,
            reason: $validated['reason'] ?? null,
            fromClassId: isset($validated['from_class_id']) ? (int) $validated['from_class_id'] : null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
            force: (bool) ($validated['force'] ?? false),
        ));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['preview', 'transfer'])->description('"preview" (default) is read-only. "transfer" performs the move.'),
            'student' => $schema->string()->max(120)->description('Student number, full name, or email (e.g. "2024-0012", "Maria Santos").'),
            'student_id' => $schema->string()->description('Alias for "student"; accepts the internal student record ID as well.'),
            'subject' => $schema->string()->max(120)->description('Which subject to move: a subject code (e.g. "GE-3"), the current section ("B"), or "all" to list the student\'s active subjects.'),
            'from_class_id' => $schema->integer()->min(1)->description('Disambiguates the source when the identifier is ambiguous.'),
            'to_section' => $schema->string()->max(120)->description('Destination section (e.g. "GE-3 Section A", or a class ID). Omit to keep the subject enrollment but remove the section.'),
            'reason' => $schema->string()->max(500)->description('Why the student is moving. Recorded in the enrollment audit trail.'),
            'force' => $schema->boolean()->description('Proceed despite schedule clashes, a full destination, or a subject mismatch. Default false.'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->description('Unique key so a retried transfer does not run twice.'),
        ];
    }
}
