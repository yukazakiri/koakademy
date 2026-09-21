<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentBillingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the actual statement-of-account summary for an enrollment. Requires finance access in addition to MCP read access.')]
#[IsReadOnly]
final class GetStatementOfAccountTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?EnrollmentBillingService $billing = null)
    {
        $this->billing ??= app(EnrollmentBillingService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        if (! $user->can('View:Cashier') && ! $user->can('view_tuition_fees')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not permitted to view statements of account.');
        }

        $validated = $request->validate(['enrollment_id' => ['required', 'integer', 'min:1']]);
        $enrollment = StudentEnrollment::query()
            ->with([
                'student:id,student_id,first_name,middle_name,last_name,suffix',
                'studentTuition',
                'additionalFees:id,enrollment_id,amount',
            ])
            ->findOrFail($validated['enrollment_id']);

        if ($enrollment->studentTuition === null) {
            return Response::structured([
                'enrollment_id' => $enrollment->id,
                'statement_available' => false,
                'message' => 'No tuition assessment is available for this enrollment.',
            ]);
        }

        $summary = $this->billing->toSummaryArray(
            $enrollment->studentTuition,
            (float) $enrollment->additionalFees->sum('amount'),
        );

        return Response::structured([
            'enrollment_id' => $enrollment->id,
            'statement_available' => true,
            'student' => $enrollment->student === null ? null : [
                'id' => $enrollment->student->id,
                'student_number' => (string) $enrollment->student->student_id,
                'name' => $enrollment->student->full_name,
            ],
            'academic_period' => [
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
            ],
            'summary' => $summary,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->required()->description('The internal enrollment ID.'),
        ];
    }
}
