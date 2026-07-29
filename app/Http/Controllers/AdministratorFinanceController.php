<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ResendFinancialDocumentRequest;
use App\Http\Requests\ResendTransactionReceiptRequest;
use App\Http\Requests\SendEnrollmentInvoiceRequest;
use App\Models\FinancialDocumentIssuance;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use App\Services\FinanceDocumentSettingsService;
use App\Services\FinancialDocumentService;
use App\Services\GeneralSettingsService;
use App\Services\TransactionReceiptDataService;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\InventoryProduct;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $collectionQueue = StudentTuition::query()
            ->with(['student.Course', 'enrollment'])
            ->whereHas('enrollment', function ($q) use ($currentSchoolYear, $currentSemester): void {
                $q->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->where('total_balance', '>', 0)
            ->orderByDesc('total_balance')
            ->limit(8)
            ->get()
            ->map(fn (StudentTuition $tuition): array => [
                'id' => $tuition->id,
                'student_id' => $tuition->student?->student_id ?? 'N/A',
                'student_name' => $tuition->student?->full_name ?? 'N/A',
                'course' => $tuition->student?->Course?->code ?? 'N/A',
                'year_level' => $tuition->student?->academic_year ?? 'N/A',
                'total_amount' => $tuition->overall_tuition,
                'paid' => $tuition->paid,
                'balance' => $tuition->total_balance,
                'payment_progress' => $tuition->payment_progress,
            ]);

        $cashierDesk = [
            'ready_for_collection' => $collectionQueue->count(),
            'average_transaction_today' => $todayTransactions > 0
                ? round($todayCollection / $todayTransactions, 2)
                : 0,
            'next_actions' => [
                [
                    'label' => 'Receive student payment',
                    'description' => 'Search student, apply tuition or fee payments, then print receipt.',
                    'href' => route('administrators.finance.payments.create'),
                ],
                [
                    'label' => 'Review payment history',
                    'description' => 'Find receipts, cashier entries, and reference numbers.',
                    'href' => route('administrators.finance.payments'),
                ],
                [
                    'label' => 'Check billing balances',
                    'description' => 'See unpaid enrollment billings before accepting payment.',
                    'href' => route('administrators.finance.invoices'),
                ],
            ],
        ];

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
            ->select(['transaction_date', 'settlements'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn (Transaction $transaction): string => $transaction->transaction_date->format('Y-m'))
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
            'collection_queue' => $collectionQueue,
            'cashier_desk' => $cashierDesk,
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
            $receiptRecipient = null;
            $receiptDeliveryId = null;

            DB::transaction(function () use ($validated, $settingsService, &$transaction, &$receiptRecipient, &$receiptDeliveryId): void {
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

                    } elseif ($item['type'] === 'fee' && ! empty($item['fee_key']) && array_key_exists((string) $item['fee_key'], $settlements)) {
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
                    'receipt_email_recipient' => $student->email ?: null,
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
                        $tuition->paid = (float) $tuition->paid + $amount;
                        $tuition->save();

                        app(EnrollmentBillingService::class)->syncTuitionBalance($tuition);
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

    public function show(Transaction $transaction, TransactionReceiptDataService $receiptDataService): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        return Inertia::render('administrators/finance/receipt', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'transaction' => $receiptDataService->build($transaction),
        ]);
    }

    public function resendReceipt(
        ResendTransactionReceiptRequest $request,
        Transaction $transaction,
        FinancialDocumentService $documents,
    ): RedirectResponse {
        $this->authorizeFinanceAccess();

        $recipient = (string) $request->validated('recipient');
        $issuance = FinancialDocumentIssuance::query()
            ->where('type', \App\Enums\FinancialDocumentType::Receipt->value)
            ->where('transaction_id', $transaction->id)
            ->first();

        if (! $issuance) {
            $studentTransaction = $transaction->studentTransactions()->oldest('id')->firstOrFail();
            $issuance = $documents->issueReceipt($studentTransaction, autoDeliver: false);
        }

        abort_unless($issuance instanceof FinancialDocumentIssuance, 422);
        $documents->queueDelivery(
            $issuance,
            $recipient,
            $request->validated('reference_number'),
        );

        return back()->with('flash', [
            'success' => 'Official eReceipt queued for delivery.',
        ]);
    }

    public function sendInvoice(
        SendEnrollmentInvoiceRequest $request,
        StudentEnrollment $enrollment,
        FinancialDocumentService $documents,
    ): RedirectResponse {
        $this->authorizeFinanceAccess();
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $issuance = $documents->issueInvoice(
            $enrollment,
            $user,
            (string) $request->validated('recipient'),
        );

        return back()->with('flash', [
            'success' => "Official eInvoice {$issuance->document_number} queued for delivery.",
        ]);
    }

    public function resendFinancialDocument(
        ResendFinancialDocumentRequest $request,
        FinancialDocumentIssuance $issuance,
        FinancialDocumentService $documents,
    ): RedirectResponse {
        $this->authorizeFinanceAccess();
        $documents->queueDelivery($issuance, (string) $request->validated('recipient'));

        return back()->with('flash', [
            'success' => "{$issuance->type->label()} queued for redelivery.",
        ]);
    }

    public function downloadFinancialDocument(FinancialDocumentIssuance $issuance): StreamedResponse
    {
        $this->authorizeFinanceAccess();

        abort_unless(
            $issuance->disk !== null
                && $issuance->pdf_path !== null
                && Storage::disk($issuance->disk)->exists($issuance->pdf_path),
            404,
        );
        $pdfBytes = Storage::disk($issuance->disk)->get($issuance->pdf_path);
        abort_unless(
            $issuance->pdf_checksum !== null
                && hash_equals($issuance->pdf_checksum, hash('sha256', $pdfBytes)),
            409,
            'The stored document failed its integrity check.',
        );

        return Storage::disk($issuance->disk)->download(
            $issuance->pdf_path,
            $issuance->attachmentFilename(),
            ['Content-Type' => 'application/pdf'],
        );
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

    public function studentTransactions(Student $student): \Illuminate\Http\JsonResponse
    {
        $this->authorizeFinanceAccess();

        $transactions = StudentTransaction::query()
            ->whereBelongsTo($student)
            ->with(['transaction.user'])
            ->latest()
            ->get()
            ->map(function (StudentTransaction $studentTransaction): array {
                $transaction = $studentTransaction->transaction;
                $settlements = collect($transaction->settlements ?? [])
                    ->map(fn (mixed $amount): float => (float) $amount)
                    ->filter(fn (float $amount): bool => $amount > 0)
                    ->all();

                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'reference_number' => $transaction->invoicenumber,
                    'date' => $transaction->transaction_date?->format('M d, Y'),
                    'time' => $transaction->transaction_date?->format('h:i A'),
                    'amount' => (float) $studentTransaction->amount,
                    'payment_method' => $transaction->payment_method,
                    'status' => $studentTransaction->status,
                    'cashier' => $transaction->user?->name ?? 'System',
                    'remarks' => $transaction->description,
                    'settlements' => $settlements,
                    'receipt_url' => route('administrators.finance.payments.show', $transaction, false),
                ];
            });

        return response()->json([
            'transactions' => $transactions,
            'summary' => [
                'count' => $transactions->count(),
                'total_paid' => $transactions->sum('amount'),
            ],
        ]);
    }

    public function invoices(
        Request $request,
        FinanceDocumentSettingsService $documentSettings,
    ): Response|RedirectResponse {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $search = mb_trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $invoiceQuery = StudentEnrollment::query()
            ->with(['student.Course', 'studentTuition', 'latestInvoice'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->whereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery
                            ->where('student_id', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($status === 'paid', function ($query): void {
                $query->whereHas('studentTuition', fn ($tuitionQuery) => $tuitionQuery->where('total_balance', '<=', 0));
            })
            ->when($status === 'unpaid', function ($query): void {
                $query->whereHas('studentTuition', fn ($tuitionQuery) => $tuitionQuery->where('total_balance', '>', 0));
            })
            ->latest();

        $invoiceSummary = (clone $invoiceQuery)
            ->get()
            ->reduce(function (array $summary, StudentEnrollment $enrollment): array {
                $tuition = $enrollment->studentTuition;
                $totalAmount = (float) ($tuition?->overall_tuition ?? 0);
                $balance = (float) ($tuition?->total_balance ?? 0);

                $summary['total_billings']++;
                $summary['total_assessed'] += $totalAmount;
                $summary['total_outstanding'] += $balance;

                if ($tuition !== null && $balance <= 0) {
                    $summary['paid_count']++;
                } else {
                    $summary['unpaid_count']++;
                }

                return $summary;
            }, [
                'total_billings' => 0,
                'total_assessed' => 0.0,
                'total_outstanding' => 0.0,
                'paid_count' => 0,
                'unpaid_count' => 0,
            ]);

        $invoices = $invoiceQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($enrollment): array => [
                'id' => $enrollment->id,
                'invoice_number' => 'INV-'.mb_str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT),
                'student_id' => $enrollment->student?->student_id ?? 'N/A',
                'student_name' => $enrollment->student?->full_name ?? 'N/A',
                'course' => $enrollment->student?->Course?->code ?? 'N/A',
                'year_level' => $enrollment->student?->academic_year ?? 'N/A',
                'total_amount' => $enrollment->studentTuition?->overall_tuition ?? 0,
                'balance' => $enrollment->studentTuition?->total_balance ?? 0,
                'status' => $enrollment->studentTuition && $enrollment->studentTuition->total_balance <= 0 ? 'Paid' : 'Unpaid',
                'date' => $enrollment->created_at->format('M d, Y'),
                'payment_progress' => $enrollment->studentTuition?->payment_progress ?? 0,
                'student_email' => $enrollment->student?->email,
                'latest_invoice' => $enrollment->latestInvoice ? [
                    'uuid' => $enrollment->latestInvoice->uuid,
                    'number' => $enrollment->latestInvoice->document_number,
                    'status' => $enrollment->latestInvoice->status->value,
                    'recipient' => $enrollment->latestInvoice->recipient,
                    'sent_at' => $enrollment->latestInvoice->sent_at?->format('M d, Y h:i A'),
                    'download_url' => $enrollment->latestInvoice->pdf_path
                        ? route('administrators.finance.documents.download', $enrollment->latestInvoice, false)
                        : null,
                ] : null,
            ]);

        return Inertia::render('administrators/finance/invoices', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'invoices' => $invoices,
            'summary' => $invoiceSummary,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'finance_document_settings' => $documentSettings->get(),
        ]);
    }

    public function payments(Request $request): Response|RedirectResponse
    {
        $this->authorizeFinanceAccess();

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $search = mb_trim((string) $request->query('search', ''));
        $method = (string) $request->query('method', 'all');
        $status = (string) $request->query('status', 'all');

        $paymentsQuery = Transaction::query()
            ->with(['studentTransactions.student', 'user'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('transaction_number', 'like', "%{$search}%")
                        ->orWhere('invoicenumber', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('studentTransactions.student', function ($studentQuery) use ($search): void {
                            $studentQuery
                                ->where('student_id', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($method !== 'all', fn ($query) => $query->where('payment_method', $method))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest();

        $paymentRows = (clone $paymentsQuery)->get();
        $paymentSummary = [
            'total_transactions' => $paymentRows->count(),
            'total_collected' => $paymentRows->sum(fn (Transaction $transaction): float => (float) $transaction->raw_total_amount),
            'today_transactions' => $paymentRows->filter(fn (Transaction $transaction): bool => $transaction->transaction_date->isToday())->count(),
            'today_collected' => $paymentRows
                ->filter(fn (Transaction $transaction): bool => $transaction->transaction_date->isToday())
                ->sum(fn (Transaction $transaction): float => (float) $transaction->raw_total_amount),
            'payment_methods' => $paymentRows
                ->pluck('payment_method')
                ->filter()
                ->unique()
                ->values(),
        ];

        $payments = $paymentsQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($tx): array => [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'student_id' => $tx->studentTransactions->first()?->student?->student_id ?? 'N/A',
                'student_name' => $tx->studentTransactions->first()?->student?->full_name ?? 'N/A',
                'amount' => $tx->raw_total_amount,
                'method' => $tx->payment_method,
                'status' => $tx->status,
                'date' => $tx->transaction_date->format('M d, Y H:i A'),
                'cashier' => $tx->user?->name ?? 'System',
                'description' => $tx->description,
                'receipt_url' => route('administrators.finance.payments.show', $tx->id),
            ]);

        return Inertia::render('administrators/finance/payments', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'payments' => $payments,
            'summary' => $paymentSummary,
            'filters' => [
                'search' => $search,
                'method' => $method,
                'status' => $status,
            ],
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
        $billing = app(EnrollmentBillingService::class);

        $data = $tuitions->map(fn (StudentTuition $tuition): array => [
            'id' => $tuition->id,
            'student_id' => $tuition->student?->student_id ?? 'N/A',
            'student_name' => $tuition->student?->full_name ?? 'N/A',
            'course' => $tuition->student?->Course?->code ?? 'N/A',
            'year_level' => $tuition->student?->academic_year ?? 'N/A',
            'total_tuition' => $tuition->overall_tuition,
            'total_paid' => $billing->totalPaid($tuition),
            'balance' => $billing->balanceDue($tuition),
            'payment_progress' => $tuition->payment_progress,
            'school_year' => $tuition->school_year,
            'semester' => $tuition->semester,
        ]);

        $totalCollectible = (float) $tuitions->sum('overall_tuition');
        $totalCollected = (float) $tuitions->sum(fn (StudentTuition $tuition): float => $billing->totalPaid($tuition));

        $summary = [
            'total_students' => $tuitions->count(),
            'total_outstanding' => $tuitions->sum(fn (StudentTuition $tuition): float => $billing->balanceDue($tuition)),
            'total_collectible' => $totalCollectible,
            'total_collected' => $totalCollected,
            'collection_rate' => $totalCollectible > 0
                ? round(($totalCollected / $totalCollectible) * 100, 2)
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
