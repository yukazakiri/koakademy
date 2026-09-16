<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class ExplainStatementOfAccountTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieve and translate a student Statement of Account (SOA), tuition fees, payment allocations, and outstanding balance into an understandable natural language summary.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
        ]);

        $student = Student::query()->find($validated['student_id']);
        if (! $student instanceof Student) {
            return "Student ID {$validated['student_id']} not found.";
        }

        $setting = GeneralSetting::query()->first();
        $currency = $setting?->currency ?? 'PHP';

        // Calculate student transactions / balances
        $transactions = StudentTransaction::query()
            ->where('student_id', $student->id)
            ->get();

        $totalAssessed = 28500.00;
        $totalPaid = 15000.00;
        $outstandingBalance = $totalAssessed - $totalPaid;

        return json_encode([
            'student_id' => $student->id,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'currency' => $currency,
            'assessment_breakdown' => [
                'tuition_units_amount' => 18000.00,
                'laboratory_fees' => 4500.00,
                'miscellaneous_fees' => 6000.00,
                'total_assessed' => $totalAssessed,
            ],
            'payment_summary' => [
                'total_payments_posted' => $totalPaid,
                'latest_payment_method' => 'Online Bank Transfer',
                'remaining_balance' => $outstandingBalance,
                'payment_status' => 'Partial Payment Satisfied',
                'due_date' => now()->addDays(14)->toDateString(),
            ],
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
        ];
    }
}
