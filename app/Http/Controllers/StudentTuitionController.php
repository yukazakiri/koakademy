<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Services\GeneralSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class StudentTuitionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        /** @var Student|null $student */
        $student = Student::where('email', $user->email)->orWhere('user_id', $user->id)->first();

        // Handle case where no student record exists
        if (! $student) {
            return Inertia::render('student/tuition/index', [
                'tuition' => null,
                'transactions' => [],
                'filters' => [
                    'semester' => app(GeneralSettingsService::class)->getCurrentSemester(),
                    'school_year' => app(GeneralSettingsService::class)->getCurrentSchoolYearString(),
                ],
                'history' => [],
                'error' => 'No enrollment record found. Please contact the registrar.',
            ]);
        }

        $settings = app(GeneralSettingsService::class);

        // Get filter inputs or defaults
        $semester = (int) $request->input('semester', $settings->getCurrentSemester());
        $schoolYear = $request->input('school_year', $settings->getCurrentSchoolYearString());

        // Fetch Tuition Record with Enrollment
        // First try by student_id (preferred), then fallback to enrollment relationship
        /** @var StudentTuition|null $tuition */
        $tuition = StudentTuition::with('enrollment.student')
            ->where('student_id', $student->id)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->first();

        // Fallback: If no tuition found by student_id, try via enrollment relationship
        // This handles cases where student_tuition.student_id is null but enrollment_id is set
        if (! $tuition) {
            $enrollment = StudentEnrollment::where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();

            if ($enrollment) {
                $tuition = StudentTuition::with('enrollment.student')
                    ->where('enrollment_id', $enrollment->id)
                    ->first();
            }
        }

        // Append formatted attributes if tuition exists
        if ($tuition) {
            $tuition->append([
                'formatted_total_balance',
                'formatted_overall_tuition',
                'formatted_total_tuition',
                'formatted_semester',
                'formatted_total_lectures',
                'formatted_total_laboratory',
                'formatted_total_miscelaneous_fees',
                'formatted_downpayment',
                'formatted_discount',
                'formatted_total_paid',
                'payment_progress',
                'payment_status',
                'status_class',
            ]);
        }

        // Fetch Transactions
        $transactions = collect();

        if ($tuition && $tuition->enrollment) {
            // Use the enrollment's transaction logic which respects GeneralSettings dates
            // Ensure student relation is loaded to avoid extra query or null error if relying on lazy loading without context
            if (! $tuition->enrollment->relationLoaded('student')) {
                $tuition->enrollment->setRelation('student', $student);
            }

            $transactions = $tuition->enrollment->enrollmentTransactions()
                ->with('transaction')
                ->get();
        } else {
            // Fallback: Try to find enrollment directly
            $enrollment = StudentEnrollment::where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();

            if ($enrollment) {
                $enrollment->setRelation('student', $student);
                $transactions = $enrollment->enrollmentTransactions()
                    ->with('transaction')
                    ->get();
            } else {
                // Last resort: Use simple date range logic from Transaction model
                $schoolYearForScope = str_replace(' ', '', $schoolYear);
                $transactions = $student->Transaction()
                    ->forAcademicPeriod($schoolYearForScope, $semester)
                    ->orderBy('transactions.created_at', 'desc')
                    ->get();
            }
        }

        // Map transactions - handle both StudentTransaction (with nested transaction) and Transaction models
        $mappedTransactions = $transactions->map(function ($t): array {
            // If this is a StudentTransaction, get data from the related Transaction
            $transaction = $t->relationLoaded('transaction') && $t->transaction ? $t->transaction : $t;

            return [
                'id' => $t->id,
                'date' => ($transaction->created_at ?? $t->created_at)?->format('M d, Y'),
                'description' => $transaction->description ?? 'Tuition Payment',
                'amount' => $transaction->total_amount,
                'status' => $t->status ?? $transaction->status,
                'invoice' => $transaction->invoicenumber,
                'method' => $transaction->payment_method,
            ];
        });

        // Get History for Dropdown
        // Use both Tuition and Enrollment history to be comprehensive
        $tuitionHistory = StudentTuition::where('student_id', $student->id)
            ->select('school_year', 'semester')
            ->distinct();

        $history = StudentEnrollment::where('student_id', $student->id)
            ->select('school_year', 'semester')
            ->distinct()
            ->union($tuitionHistory)
            ->orderBy('school_year', 'desc')
            ->orderBy('semester', 'desc')
            ->get()
            ->map(fn ($h): array => [
                'school_year' => $h->school_year,
                'semester' => $h->semester,
                'label' => $h->school_year.' - '.($h->semester === 1 ? '1st' : '2nd').' Sem',
            ]);

        return Inertia::render('student/tuition/index', [
            'tuition' => $tuition,
            'transactions' => $mappedTransactions,
            'filters' => [
                'semester' => $semester,
                'school_year' => $schoolYear,
            ],
            'history' => $history,
        ]);
    }

    /**
     * Display the Statement of Account (SOA) for printing
     */
    public function soa(Request $request): Response
    {
        $user = $request->user();
        /** @var Student|null $student */
        $student = Student::with(['course'])->where('email', $user->email)->orWhere('user_id', $user->id)->first();

        // Handle case where no student record exists
        if (! $student) {
            return Inertia::render('student/tuition/soa', [
                'student' => null,
                'tuition' => null,
                'transactions' => [],
                'filters' => [
                    'semester' => app(GeneralSettingsService::class)->getCurrentSemester(),
                    'school_year' => app(GeneralSettingsService::class)->getCurrentSchoolYearString(),
                ],
                'school' => [],
                'generated_at' => now()->format('F d, Y h:i A'),
                'error' => 'No enrollment record found. Please contact the registrar.',
            ]);
        }

        $settings = app(GeneralSettingsService::class);

        // Get filter inputs or defaults
        $semester = (int) $request->input('semester', $settings->getCurrentSemester());
        $schoolYear = $request->input('school_year', $settings->getCurrentSchoolYearString());

        // Fetch Tuition Record with Enrollment
        /** @var StudentTuition|null $tuition */
        $tuition = StudentTuition::with('enrollment.student')
            ->where('student_id', $student->id)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->first();

        // Fallback via enrollment
        if (! $tuition) {
            $enrollment = StudentEnrollment::where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();

            if ($enrollment) {
                $tuition = StudentTuition::with('enrollment.student')
                    ->where('enrollment_id', $enrollment->id)
                    ->first();
            }
        }

        // Append formatted attributes if tuition exists
        if ($tuition) {
            $tuition->append([
                'formatted_total_balance',
                'formatted_overall_tuition',
                'formatted_total_tuition',
                'formatted_semester',
                'formatted_total_lectures',
                'formatted_total_laboratory',
                'formatted_total_miscelaneous_fees',
                'formatted_downpayment',
                'formatted_discount',
                'formatted_total_paid',
                'payment_progress',
                'payment_status',
                'status_class',
            ]);
        }

        // Fetch Transactions
        $transactions = collect();
        $enrollment = $tuition?->enrollment;

        if (! $enrollment) {
            $enrollment = StudentEnrollment::where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();
        }

        if ($enrollment) {
            $enrollment->setRelation('student', $student);
            $transactions = $enrollment->enrollmentTransactions()
                ->with('transaction')
                ->get();
        }

        // Map transactions
        $mappedTransactions = $transactions->map(function ($t): array {
            $transaction = $t->relationLoaded('transaction') && $t->transaction ? $t->transaction : $t;

            return [
                'id' => $t->id,
                'date' => ($transaction->created_at ?? $t->created_at)?->format('M d, Y'),
                'description' => $transaction->description ?? 'Tuition Payment',
                'amount' => $transaction->total_amount,
                'status' => $t->status ?? $transaction->status,
                'invoice' => $transaction->invoicenumber,
                'method' => $transaction->payment_method,
            ];
        });

        // Get school info from general settings
        $generalSettings = DB::table('general_settings')->first();

        return Inertia::render('student/tuition/soa', [
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name ?? $student->name,
                'email' => $student->email,
                'course' => $student->course?->name ?? $student->course?->course_name ?? $student->course?->code ?? 'N/A',
            ],
            'tuition' => $tuition,
            'transactions' => $mappedTransactions,
            'filters' => [
                'semester' => $semester,
                'school_year' => $schoolYear,
            ],
            'school' => [
                'name' => $generalSettings?->school_portal_title ?? $generalSettings?->site_name ?? app(\App\Settings\SiteSettings::class)->getOrganizationName(),
                'address' => app(\App\Settings\SiteSettings::class)->getOrganizationAddress() ?? '',
                'logo' => $generalSettings?->school_portal_logo ?? '/web-app-manifest-192x192.png',
            ],
            'generated_at' => now()->format('F d, Y h:i A'),
        ]);
    }
}
