<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InventoryProduct;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorFinanceController extends Controller
{
    public function index(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();

        // --- Analytics / Key Metrics ---
        // 1. Total Revenue (Tuition Paid) for current period
        $totalRevenue = StudentTransaction::query()
            ->whereHas('transaction', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->forAcademicPeriod($currentSchoolYear, $currentSemester);
            })
            ->sum('amount');

        // 2. Total Collectibles (Remaining Balance)
        $totalCollectibles = StudentTuition::query()
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->sum('total_balance');

        // 3. Total Tuition Assessed (Overall)
        $totalAssessed = StudentTuition::query()
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->sum('overall_tuition');

        // 4. Collection Rate
        $collectionRate = $totalAssessed > 0
            ? round(($totalRevenue / $totalAssessed) * 100, 2)
            : 0;

        // 5. Fully Paid Students Count
        $fullyPaidCount = StudentTuition::query()
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->where('total_balance', '<=', 0)
            ->count();

        // 6. Students with Outstanding Balance
        $outstandingCount = StudentTuition::query()
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->where('total_balance', '>', 0)
            ->count();

        // 7. Total Enrolled Students
        $totalEnrolled = StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->count();

        // 8. Today's Collection
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd = Carbon::now()->endOfDay();
        $todayCollection = Transaction::query()
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->get()
            ->sum(fn ($tx) => $tx->raw_total_amount);

        $todayTransactions = Transaction::query()
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->count();

        // 9. Payment Methods Breakdown
        $paymentMethods = Transaction::query()
            ->forAcademicPeriod($currentSchoolYear, $currentSemester)
            ->get()
            ->groupBy('payment_method')
            ->map(fn ($group): array => [
                'method' => $group->first()->payment_method,
                'count' => $group->count(),
                'total' => $group->sum(fn ($tx) => $tx->raw_total_amount),
            ])
            ->values();

        // 10. Daily Collection (Last 7 Days)
        $dailyCollection = collect(range(0, 6))->map(function ($daysAgo) use ($todayStart): array {
            $date = $todayStart->copy()->subDays($daysAgo);
            $transactions = Transaction::query()
                ->whereDate('transaction_date', $date)
                ->get();

            return [
                'date' => $date->format('M d'),
                'day' => $date->format('l'),
                'count' => $transactions->count(),
                'total' => $transactions->sum(fn ($tx) => $tx->raw_total_amount),
            ];
        })->reverse()->values();

        // 11. Recent Transactions (Last 10)
        $recentTransactions = Transaction::query()
            ->with(['studentTransactions.student', 'user'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($tx): array => [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'student_name' => $tx->studentTransactions->first()?->student?->full_name ?? 'N/A',
                'student_id' => $tx->studentTransactions->first()?->student?->student_id ?? 'N/A',
                'amount' => $tx->raw_total_amount,
                'payment_method' => $tx->payment_method,
                'status' => $tx->status,
                'cashier' => $tx->user?->name ?? 'System',
                'date' => $tx->transaction_date->format('M d, Y'),
                'time' => $tx->transaction_date->format('h:i A'),
            ]);

        // 12. Top Students by Payment (This Period)
        $topStudents = StudentTransaction::query()
            ->whereHas('transaction', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->forAcademicPeriod($currentSchoolYear, $currentSemester);
            })
            ->with('student')
            ->select('student_id', DB::raw('sum(amount) as total_paid'), DB::raw('count(*) as transaction_count'))
            ->groupBy('student_id')
            ->orderByDesc('total_paid')
            ->limit(5)
            ->get()
            ->map(fn ($item): array => [
                'student_id' => $item->student_id,
                'student_name' => $item->student?->full_name ?? 'N/A',
                'total_paid' => (float) $item->total_paid,
                'transaction_count' => $item->transaction_count,
            ]);

        // 13. Scholarship/Discount Summary
        $totalDiscounts = StudentTuition::query()
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->where('discount', '>', 0)
            ->get()
            ->sum(fn ($t): int|float => ($t->total_tuition * $t->discount) / 100);

        $discountedStudents = StudentTuition::query()
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->where('discount', '>', 0)
            ->count();

        // 14. Fee Type Breakdown
        $feeBreakdown = Transaction::query()
            ->forAcademicPeriod($currentSchoolYear, $currentSemester)
            ->get()
            ->flatMap(fn ($tx) => $tx->settlements ?? [])
            ->groupBy(fn ($value, $key): int|string => $key)
            ->map(fn ($amounts): float => array_sum($amounts->toArray()))
            ->map(fn ($total, $key): array => [
                'key' => $key,
                'label' => match ($key) {
                    'tuition_fee' => 'Tuition Fee',
                    'registration_fee' => 'Registration Fee',
                    'miscelanous_fee' => 'Miscellaneous Fee',
                    'diploma_or_certificate' => 'Diploma/Certificate',
                    'transcript_of_records' => 'Transcript of Records',
                    'certification' => 'Certification',
                    'special_exam' => 'Special Exam',
                    'others' => 'Others',
                    default => ucfirst((string) $key),
                },
                'total' => $total,
            ])
            ->filter(fn ($item): bool => $item['total'] > 0)
            ->values();

        // 15. Monthly Revenue Chart Data (Past 12 months)
        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(11)->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        $transactionsByMonth = Transaction::query()
            ->select(
                DB::raw("DATE_TRUNC('month', transaction_date) as month"),
                'settlements'
            )
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn ($item): string => Carbon::parse($item->month)->format('Y-m'))
            ->map(fn ($transactions) => $transactions->sum(function ($tx): int|float {
                $settlements = $tx->settlements;
                if (is_string($settlements)) {
                    $settlements = json_decode($settlements, true);
                }
                if (! is_array($settlements)) {
                    return 0;
                }

                return array_reduce(array_values($settlements), fn ($carry, $value): float => $carry + (float) $value, 0.0);
            }));

        $monthlyRevenue = collect(range(0, 11))->map(function ($monthsAgo) use ($now, $transactionsByMonth): array {
            $date = $now->copy()->subMonths(11 - $monthsAgo)->startOfMonth();
            $key = $date->format('Y-m');

            return [
                'month' => $date->format('M Y'),
                'total' => $transactionsByMonth->get($key, 0),
            ];
        });

        return Inertia::render('administrators/finance/dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'stats' => [
                'total_revenue' => $totalRevenue,
                'total_collectibles' => $totalCollectibles,
                'total_assessed' => $totalAssessed,
                'collection_rate' => $collectionRate,
                'fully_paid_count' => $fullyPaidCount,
                'outstanding_count' => $outstandingCount,
                'total_enrolled' => $totalEnrolled,
                'today_collection' => $todayCollection,
                'today_transactions' => $todayTransactions,
                'total_discounts' => $totalDiscounts,
                'discounted_students' => $discountedStudents,
            ],
            'payment_methods' => $paymentMethods,
            'daily_collection' => $dailyCollection,
            'recent_transactions' => $recentTransactions,
            'top_students' => $topStudents,
            'fee_breakdown' => $feeBreakdown,
            'chart_data' => $monthlyRevenue,
            'current_period' => [
                'school_year' => $currentSchoolYear,
                'semester' => $currentSemester,
            ],
        ]);
    }

    public function create(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $items = InventoryProduct::query()
            ->where('is_active', true)
            ->get()
            ->map(fn ($product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'sku' => $product->sku,
                'category' => $product->category->name ?? 'Uncategorized',
            ]);

        return Inertia::render('administrators/finance/create-payment', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'items' => $items,
            'currency' => $settingsService->getCurrency(),
        ]);
    }

    public function store(Request $request, GeneralSettingsService $settingsService): RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_method' => ['required', 'string'],
            'reference_number' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'in:tuition,item,fee'],
            'items.*.name' => ['required', 'string'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.id' => ['nullable', 'exists:inventory_products,id'], // For items
            'items.*.fee_key' => ['nullable', 'string'], // For specific fee types
            'items.*.tuition_id' => ['nullable', 'exists:student_tuition,id'], // For specific tuition payments
        ]);

        try {
            // 1. Create Transaction
            $transaction = null; // Initialize variable

            DB::transaction(function () use ($validated, $settingsService, &$transaction): void {
                $student = Student::findOrFail($validated['student_id']);
                $totalAmount = collect($validated['items'])->sum('amount');
                $schoolYear = $settingsService->getCurrentSchoolYearString();
                $semester = $settingsService->getCurrentSemester();

                // Prepare settlements JSON with default structure
                $settlements = [
                    'registration_fee' => 0,
                    'tuition_fee' => 0,
                    'miscelanous_fee' => 0,
                    'diploma_or_certificate' => 0,
                    'transcript_of_records' => 0,
                    'certification' => 0,
                    'special_exam' => 0,
                    'others' => 0,
                ];

                $tuitionPayment = 0;
                // Group tuition payments by tuition_id for batch updates
                $tuitionPaymentsByRecord = [];

                foreach ($validated['items'] as $item) {
                    $amount = (float) $item['amount'];

                    if ($item['type'] === 'tuition') {
                        $settlements['tuition_fee'] += $amount;
                        $tuitionPayment += $amount;

                        // Track which specific tuition record this payment is for
                        if (! empty($item['tuition_id'])) {
                            if (! isset($tuitionPaymentsByRecord[$item['tuition_id']])) {
                                $tuitionPaymentsByRecord[$item['tuition_id']] = 0;
                            }
                            $tuitionPaymentsByRecord[$item['tuition_id']] += $amount;
                        } else {
                            // Fallback to "current" tuition if no ID provided (legacy behavior)
                            // We'll handle this in the update block
                            if (! isset($tuitionPaymentsByRecord['current'])) {
                                $tuitionPaymentsByRecord['current'] = 0;
                            }
                            $tuitionPaymentsByRecord['current'] += $amount;
                        }

                    } elseif ($item['type'] === 'fee' && ! empty($item['fee_key']) && array_key_exists($item['fee_key'], $settlements)) {
                        // Map specific fee types to their corresponding keys in settlements
                        $settlements[$item['fee_key']] += $amount;
                    } else {
                        // Fallback for items or unmapped fees
                        $settlements['others'] += $amount;
                    }

                    // If it's an inventory item, we should probably deduct stock here
                    if ($item['type'] === 'item' && ! empty($item['id'])) {
                        $product = InventoryProduct::find($item['id']);
                        if ($product && $product->track_stock) {
                            $product->decrement('stock_quantity', 1); // Assuming quantity 1 for now or add quantity to input
                        }
                    }
                }

                // Convert all values to string to match existing structure if needed, though JSON handles numbers fine.
                // But looking at the example {"tuition_fee":"2500"}, they are strings.
                $settlements = array_map(fn (int|float $val): string => (string) $val, $settlements);

                $transaction = Transaction::create([
                    'description' => $validated['remarks'] ?? 'Payment for '.implode(', ', array_map(fn (array $i) => $i['name'], $validated['items'])),
                    'payment_method' => $validated['payment_method'],
                    'status' => 'paid', // Assuming immediate payment
                    'transaction_date' => now(),
                    'settlements' => $settlements,
                    'invoicenumber' => $validated['reference_number'] ?? null,
                    'user_id' => Auth::id(),
                ]);

                // 2. Link to Student
                StudentTransaction::create([
                    'student_id' => $student->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $totalAmount,
                    'status' => 'paid',
                ]);

                // 3. Update Tuition Records
                foreach ($tuitionPaymentsByRecord as $tuitionId => $amount) {
                    if ($amount <= 0) {
                        continue;
                    }

                    $tuition = null;

                    if ($tuitionId === 'current') {
                        // Logic for "current" tuition fallback
                        $enrollment = StudentEnrollment::query()
                            ->where('student_id', $student->id)
                            ->where('school_year', $schoolYear)
                            ->where('semester', $semester)
                            ->first();

                        if ($enrollment && $enrollment->studentTuition) {
                            $tuition = $enrollment->studentTuition;
                        }
                    } else {
                        // Specific tuition record
                        $tuition = StudentTuition::find($tuitionId);
                    }

                    if ($tuition) {
                        $tuition->paid += $amount;
                        $tuition->total_balance -= $amount;
                        $tuition->save();
                    }
                }
            });

            if ($transaction) {
                return redirect()->route('administrators.finance.payments.show', $transaction->id)->with('flash', [
                    'success' => 'Payment recorded successfully.',
                ]);
            }

            return redirect()->route('administrators.finance.payments')->with('flash', [
                'error' => 'Transaction failed to create.',
            ]);

        } catch (Exception $e) {
            return back()->with('flash', [
                'error' => 'Failed to record payment: '.$e->getMessage(),
            ]);
        }
    }

    public function show(Transaction $transaction): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        $transaction->load(['student', 'studentTransactions', 'user']);

        return Inertia::render('administrators/finance/receipt', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'transaction' => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'date' => $transaction->transaction_date->format('M d, Y h:i A'),
                'student_name' => $transaction->student->first()?->full_name ?? 'N/A',
                'student_id' => $transaction->student->first()?->student_id ?? 'N/A',
                'amount' => $transaction->raw_total_amount,
                'method' => $transaction->payment_method,
                'items' => $transaction->settlements,
                'cashier' => $transaction->user->name ?? 'System',
                'remarks' => $transaction->description,
            ],
        ]);
    }

    public function getStudentDetails(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $request->validate(['student_id' => 'required|exists:students,id']);

        $student = Student::with(['Course', 'StudentTuition.enrollment'])->find($request->student_id);

        $outstandingBalance = $student->StudentTuition()
            ->sum('total_balance');

        // Get enrollments with balance
        $unpaidEnrollments = $student->StudentTuition()
            ->where('total_balance', '>', 0)
            ->with('enrollment')
            ->get()
            ->map(fn ($tuition): array => [
                'id' => $tuition->id,
                'enrollment_id' => $tuition->enrollment_id,
                'school_year' => $tuition->school_year,
                'semester' => $tuition->semester,
                'total_amount' => $tuition->overall_tuition,
                'paid' => $tuition->paid,
                'balance' => $tuition->total_balance,
            ]);

        return response()->json([
            'id' => $student->id,
            'full_name' => $student->full_name,
            'student_id' => $student->student_id,
            'course' => $student->Course->code ?? 'N/A',
            'year_level' => $student->academic_year,
            'outstanding_balance' => $outstandingBalance,
            'unpaid_enrollments' => $unpaidEnrollments,
        ]);
    }

    public function invoices(): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        // For now, listing StudentTuitions as "Invoices" or Billing Statements
        // Or we could list Enrollments which act as the main billing record.

        $invoices = StudentEnrollment::query()
            ->with(['student', 'studentTuition'])
            ->latest()
            ->paginate(15)
            ->through(fn ($enrollment): array => [
                'id' => $enrollment->id,
                'invoice_number' => 'INV-'.mb_str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT),
                'student_name' => $enrollment->student->full_name,
                'total_amount' => $enrollment->studentTuition?->overall_tuition ?? 0,
                'balance' => $enrollment->studentTuition?->total_balance ?? 0,
                'status' => $enrollment->studentTuition && $enrollment->studentTuition->total_balance <= 0 ? 'Paid' : 'Unpaid',
                'date' => $enrollment->created_at->format('M d, Y'),
            ]);

        return Inertia::render('administrators/finance/invoices', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'invoices' => $invoices,
        ]);
    }

    public function payments(): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $payments = Transaction::query()
            ->with(['studentTransactions.student'])
            ->latest()
            ->paginate(15)
            ->through(fn ($tx): array => [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'student_name' => $tx->studentTransactions->first()?->student?->full_name ?? 'N/A',
                'amount' => $tx->raw_total_amount,
                'method' => 'Cash', // Placeholder, assuming mostly cash or need column check
                'status' => $tx->status,
                'date' => $tx->transaction_date->format('M d, Y H:i A'),
            ]);

        return Inertia::render('administrators/finance/payments', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'payments' => $payments,
        ]);
    }

    public function reports(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $currentSemester = $settingsService->getCurrentSemester();

        // Get all available school years for filter
        $schoolYears = StudentEnrollment::query()
            ->select('school_year')
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year')
            ->toArray();

        return Inertia::render('administrators/finance/reports', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'filters' => [
                'school_years' => $schoolYears,
                'semesters' => [1, 2],
                'payment_methods' => array_column(\App\Enums\PaymentMethod::cases(), 'value'),
                'current_school_year' => $settingsService->getCurrentSchoolYearString(),
                'current_semester' => $currentSemester,
            ],
        ]);
    }

    /**
     * Generate daily collection report
     */
    public function dailyCollectionReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now())->startOfDay();

        $transactions = Transaction::query()
            ->with(['studentTransactions.student', 'user'])
            ->whereDate('transaction_date', $date)
            ->orderByDesc('transaction_date')
            ->get();

        $data = $transactions->map(fn ($tx): array => [
            'id' => $tx->id,
            'transaction_number' => $tx->transaction_number,
            'student_name' => $tx->studentTransactions->first()?->student?->full_name ?? 'N/A',
            'student_id' => $tx->studentTransactions->first()?->student?->student_id ?? 'N/A',
            'amount' => $tx->raw_total_amount,
            'payment_method' => $tx->payment_method,
            'description' => $tx->description,
            'cashier' => $tx->user?->name ?? 'System',
            'time' => $tx->transaction_date->format('h:i A'),
        ]);

        // Summary statistics
        $summary = [
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum(fn ($tx) => $tx->raw_total_amount),
            'by_payment_method' => $transactions->groupBy('payment_method')
                ->map(fn ($group): array => [
                    'count' => $group->count(),
                    'total' => $group->sum(fn ($tx) => $tx->raw_total_amount),
                ]),
            'date' => $date->format('F d, Y'),
        ];

        return response()->json([
            'transactions' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate collection report for date range
     */
    public function collectionReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $query = Transaction::query()
            ->with(['studentTransactions.student', 'user'])
            ->whereBetween('transaction_date', [
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
            ]);

        if (! empty($validated['payment_method'])) {
            $query->where('payment_method', $validated['payment_method']);
        }

        $transactions = $query->orderByDesc('transaction_date')->get();

        $data = $transactions->map(fn ($tx): array => [
            'id' => $tx->id,
            'transaction_number' => $tx->transaction_number,
            'student_name' => $tx->studentTransactions->first()?->student?->full_name ?? 'N/A',
            'student_id' => $tx->studentTransactions->first()?->student?->student_id ?? 'N/A',
            'amount' => $tx->raw_total_amount,
            'payment_method' => $tx->payment_method,
            'description' => $tx->description,
            'cashier' => $tx->user?->name ?? 'System',
            'date' => $tx->transaction_date->format('M d, Y'),
            'time' => $tx->transaction_date->format('h:i A'),
        ]);

        // Daily breakdown
        $dailyBreakdown = $transactions->groupBy(fn ($tx) => $tx->transaction_date->format('Y-m-d'))
            ->map(fn ($group, DateTimeInterface|\Carbon\WeekDay|\Carbon\Month|string|int|float|null $date): array => [
                'date' => Carbon::parse($date)->format('M d, Y'),
                'count' => $group->count(),
                'total' => $group->sum(fn ($tx) => $tx->raw_total_amount),
            ])->values();

        $summary = [
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum(fn ($tx) => $tx->raw_total_amount),
            'by_payment_method' => $transactions->groupBy('payment_method')
                ->map(fn ($group): array => [
                    'count' => $group->count(),
                    'total' => $group->sum(fn ($tx) => $tx->raw_total_amount),
                ]),
            'daily_breakdown' => $dailyBreakdown,
            'start_date' => Carbon::parse($validated['start_date'])->format('F d, Y'),
            'end_date' => Carbon::parse($validated['end_date'])->format('F d, Y'),
        ];

        return response()->json([
            'transactions' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate outstanding balances report
     */
    public function outstandingBalancesReport(Request $request, GeneralSettingsService $settingsService): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'school_year' => ['nullable', 'string'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'min_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $schoolYear = $validated['school_year'] ?? $settingsService->getCurrentSchoolYearString();
        $semester = $validated['semester'] ?? $settingsService->getCurrentSemester();

        $query = StudentTuition::query()
            ->with(['student.Course', 'enrollment'])
            ->whereHas('enrollment', function ($q) use ($schoolYear, $semester): void {
                $q->where('school_year', $schoolYear)
                    ->where('semester', $semester);
            })
            ->where('total_balance', '>', $validated['min_balance'] ?? 0);

        $tuitions = $query->orderByDesc('total_balance')->get();

        $data = $tuitions->map(fn ($tuition): array => [
            'id' => $tuition->id,
            'student_id' => $tuition->student?->student_id ?? 'N/A',
            'student_name' => $tuition->student?->full_name ?? 'N/A',
            'course' => $tuition->student?->Course?->code ?? 'N/A',
            'year_level' => $tuition->student?->academic_year ?? 'N/A',
            'total_tuition' => $tuition->overall_tuition,
            'total_paid' => $tuition->paid ?? ($tuition->overall_tuition - $tuition->total_balance),
            'balance' => $tuition->total_balance,
            'payment_progress' => $tuition->payment_progress,
            'school_year' => $tuition->school_year,
            'semester' => $tuition->semester,
        ]);

        $summary = [
            'total_students' => $tuitions->count(),
            'total_outstanding' => $tuitions->sum('total_balance'),
            'total_collectible' => $tuitions->sum('overall_tuition'),
            'total_collected' => $tuitions->sum(fn ($t) => $t->paid ?? ($t->overall_tuition - $t->total_balance)),
            'collection_rate' => $tuitions->sum('overall_tuition') > 0
                ? round(($tuitions->sum(fn ($t) => $t->paid ?? ($t->overall_tuition - $t->total_balance)) / $tuitions->sum('overall_tuition')) * 100, 2)
                : 0,
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];

        return response()->json([
            'students' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate scholarship/discount summary report
     */
    public function scholarshipReport(Request $request, GeneralSettingsService $settingsService): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'school_year' => ['nullable', 'string'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $schoolYear = $validated['school_year'] ?? $settingsService->getCurrentSchoolYearString();
        $semester = $validated['semester'] ?? $settingsService->getCurrentSemester();

        $tuitions = StudentTuition::query()
            ->with(['student.Course', 'enrollment'])
            ->whereHas('enrollment', function ($q) use ($schoolYear, $semester): void {
                $q->where('school_year', $schoolYear)
                    ->where('semester', $semester);
            })
            ->where('discount', '>', 0)
            ->orderByDesc('discount')
            ->get();

        $data = $tuitions->map(fn ($tuition): array => [
            'id' => $tuition->id,
            'student_id' => $tuition->student?->student_id ?? 'N/A',
            'student_name' => $tuition->student?->full_name ?? 'N/A',
            'course' => $tuition->student?->Course?->code ?? 'N/A',
            'year_level' => $tuition->student?->academic_year ?? 'N/A',
            'discount_percentage' => $tuition->discount,
            'original_tuition' => $tuition->total_tuition,
            'discount_amount' => ($tuition->total_tuition * $tuition->discount) / 100,
            'discounted_tuition' => $tuition->overall_tuition,
            'school_year' => $tuition->school_year,
            'semester' => $tuition->semester,
        ]);

        // Group by discount percentage
        $byDiscountLevel = $tuitions->groupBy('discount')
            ->map(fn ($group, $discount): array => [
                'discount' => $discount.'%',
                'count' => $group->count(),
                'total_discount' => $group->sum(fn ($t): int|float => ($t->total_tuition * $t->discount) / 100),
            ])->values();

        $summary = [
            'total_scholars' => $tuitions->count(),
            'total_discount_granted' => $tuitions->sum(fn ($t): int|float => ($t->total_tuition * $t->discount) / 100),
            'original_revenue' => $tuitions->sum('total_tuition'),
            'discounted_revenue' => $tuitions->sum('overall_tuition'),
            'by_discount_level' => $byDiscountLevel,
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];

        return response()->json([
            'scholars' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate revenue breakdown report
     */
    public function revenueBreakdownReport(Request $request, GeneralSettingsService $settingsService): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'school_year' => ['nullable', 'string'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $schoolYear = $validated['school_year'] ?? $settingsService->getCurrentSchoolYearString();
        $semester = $validated['semester'] ?? $settingsService->getCurrentSemester();

        $transactions = Transaction::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->get();

        // Aggregate by fee type
        $feeTypes = [
            'registration_fee' => 'Registration Fee',
            'tuition_fee' => 'Tuition Fee',
            'miscelanous_fee' => 'Miscellaneous Fee',
            'diploma_or_certificate' => 'Diploma/Certificate',
            'transcript_of_records' => 'Transcript of Records',
            'certification' => 'Certification',
            'special_exam' => 'Special Exam',
            'others' => 'Others',
        ];

        $breakdown = [];
        foreach ($feeTypes as $key => $label) {
            $total = $transactions->sum(function ($tx) use ($key): float {
                $settlements = $tx->settlements;
                if (is_string($settlements)) {
                    $settlements = json_decode($settlements, true);
                }

                return is_array($settlements) && isset($settlements[$key]) ? (float) $settlements[$key] : 0.0;
            });

            $breakdown[] = [
                'key' => $key,
                'label' => $label,
                'total' => $total,
            ];
        }

        // Monthly trend
        $monthlyData = $transactions->groupBy(fn ($tx): string => Carbon::parse($tx->transaction_date)->format('Y-m'))
            ->map(fn ($group, $month): array => [
                'month' => Carbon::parse($month.'-01')->format('M Y'),
                'total' => $group->sum(fn ($tx) => $tx->raw_total_amount),
                'count' => $group->count(),
            ])->values();

        $summary = [
            'total_revenue' => $transactions->sum(fn ($tx) => $tx->raw_total_amount),
            'total_transactions' => $transactions->count(),
            'breakdown' => $breakdown,
            'monthly_trend' => $monthlyData,
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];

        return response()->json([
            'summary' => $summary,
        ]);
    }

    /**
     * Generate fully paid students report
     */
    public function fullyPaidReport(Request $request, GeneralSettingsService $settingsService): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'school_year' => ['nullable', 'string'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $schoolYear = $validated['school_year'] ?? $settingsService->getCurrentSchoolYearString();
        $semester = $validated['semester'] ?? $settingsService->getCurrentSemester();

        $tuitions = StudentTuition::query()
            ->with(['student.Course', 'enrollment'])
            ->whereHas('enrollment', function ($q) use ($schoolYear, $semester): void {
                $q->where('school_year', $schoolYear)
                    ->where('semester', $semester);
            })
            ->where('total_balance', '<=', 0)
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = $tuitions->map(fn ($tuition): array => [
            'id' => $tuition->id,
            'student_id' => $tuition->student?->student_id ?? 'N/A',
            'student_name' => $tuition->student?->full_name ?? 'N/A',
            'course' => $tuition->student?->Course?->code ?? 'N/A',
            'year_level' => $tuition->student?->academic_year ?? 'N/A',
            'total_paid' => $tuition->overall_tuition,
            'discount' => $tuition->discount.'%',
            'school_year' => $tuition->school_year,
            'semester' => $tuition->semester,
        ]);

        $summary = [
            'total_students' => $tuitions->count(),
            'total_collected' => $tuitions->sum('overall_tuition'),
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];

        return response()->json([
            'students' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate cashier performance report
     */
    public function cashierPerformanceReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $transactions = Transaction::query()
            ->with(['user'])
            ->whereBetween('transaction_date', [
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
            ])
            ->get();

        $byCashier = $transactions->groupBy(fn ($tx) => $tx->user?->id ?? 'unknown')
            ->map(fn ($group): array => [
                'cashier_id' => $group->first()?->user?->id,
                'cashier_name' => $group->first()?->user?->name ?? 'Unknown',
                'transaction_count' => $group->count(),
                'total_collected' => $group->sum(fn ($tx) => $tx->raw_total_amount),
                'average_transaction' => $group->count() > 0
                    ? round($group->sum(fn ($tx) => $tx->raw_total_amount) / $group->count(), 2)
                    : 0,
            ])
            ->sortByDesc('total_collected')
            ->values();

        $summary = [
            'total_cashiers' => $byCashier->count(),
            'total_transactions' => $transactions->count(),
            'total_collected' => $transactions->sum(fn ($tx) => $tx->raw_total_amount),
            'start_date' => Carbon::parse($validated['start_date'])->format('F d, Y'),
            'end_date' => Carbon::parse($validated['end_date'])->format('F d, Y'),
        ];

        return response()->json([
            'cashiers' => $byCashier,
            'summary' => $summary,
        ]);
    }

    private function authorizeFinanceAccess(): void
    {
        $user = Auth::user();

        $this->abortUnlessUserHasAnyPermission($user instanceof User ? $user : null, 'View:Cashier');
    }
}
