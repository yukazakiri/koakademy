<?php

declare(strict_types=1);

namespace App\Finance;

use App\Enums\PaymentMethod;
use App\Models\AdminTransaction;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryStockMovement;

final readonly class RecordFinancePayment
{
    /** @var array<string, string> */
    private const FEE_LABELS = [
        'registration_fee' => 'Registration Fee',
        'miscelanous_fee' => 'Miscellaneous Fee',
        'diploma_or_certificate' => 'Diploma / Certificate',
        'transcript_of_records' => 'Transcript of Records',
        'certification' => 'Certification',
        'special_exam' => 'Special Exam',
        'others' => 'Other Fee',
    ];

    public function __construct(private EnrollmentBillingService $billing) {}

    /**
     * Record one receipt. A batch caller invokes this once per row, so a rejected row
     * rolls back independently while other rows can still be recorded.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(User $cashier, array $payload, ?string $idempotencyKey = null): RecordedFinancePayment
    {
        try {
            return DB::transaction(fn (): RecordedFinancePayment => $this->recordWithinTransaction(
                cashier: $cashier,
                payload: $payload,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (QueryException $exception) {
            if ($idempotencyKey !== null) {
                $existing = $this->existingPayment($idempotencyKey);
                if ($existing instanceof StudentTransaction) {
                    return new RecordedFinancePayment($existing->transaction, true);
                }
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    private function recordWithinTransaction(User $cashier, array $payload, ?string $idempotencyKey): RecordedFinancePayment
    {
        if ($idempotencyKey !== null) {
            $existing = StudentTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->with('transaction')
                ->first();
            if ($existing instanceof StudentTransaction) {
                return new RecordedFinancePayment($existing->transaction, true);
            }
        }

        $student = Student::query()->lockForUpdate()->find($payload['student_id'] ?? null);
        if (! $student instanceof Student) {
            $this->fail('student_id', 'The selected student no longer exists.');
        }

        $paymentMethod = PaymentMethod::tryFrom((string) ($payload['payment_method'] ?? ''));
        if (! $paymentMethod instanceof PaymentMethod) {
            $this->fail('payment_method', 'Choose a supported payment method.');
        }

        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if ($items === []) {
            $this->fail('items', 'Add at least one charge.');
        }

        $tuitionIds = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'tuition')
            ->pluck('tuition_id')
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $tuitions = StudentTuition::query()
            ->whereIn('id', $tuitionIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $enrollmentIds = $tuitions
            ->pluck('enrollment_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $enrollments = StudentEnrollment::query()
            ->whereIn('id', $enrollmentIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $productIds = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'item')
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $products = InventoryProduct::query()
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $settlements = array_fill_keys(array_keys(self::FEE_LABELS), 0.0);
        $settlements['tuition_fee'] = 0.0;
        $lineLabels = [];
        $tuitionAmounts = [];
        $stockDeductions = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $this->fail("items.{$index}", 'This charge is invalid.');
            }

            $type = (string) ($item['type'] ?? '');
            if ($type === 'tuition') {
                $tuitionId = (int) ($item['tuition_id'] ?? 0);
                $tuition = $tuitions->get($tuitionId);
                if (! $tuition instanceof StudentTuition) {
                    $this->fail("items.{$index}.tuition_id", 'The selected tuition balance no longer exists.');
                }
                if ((int) $tuition->student_id !== $student->id) {
                    $this->fail("items.{$index}.tuition_id", 'The selected tuition balance does not belong to this student.');
                }

                $enrollment = $tuition->enrollment_id ? $enrollments->get($tuition->enrollment_id) : null;
                if ($tuition->enrollment_id && ! $enrollment instanceof StudentEnrollment) {
                    $this->fail("items.{$index}.tuition_id", 'The selected tuition enrollment is unavailable.');
                }
                if ($enrollment instanceof StudentEnrollment && (int) $enrollment->student_id !== $student->id) {
                    $this->fail("items.{$index}.tuition_id", 'The selected tuition enrollment does not belong to this student.');
                }

                $amount = $this->money($item['amount'] ?? null, "items.{$index}.amount");
                $tuitionAmounts[$tuitionId] = ($tuitionAmounts[$tuitionId] ?? 0.0) + $amount;
                $settlements['tuition_fee'] += $amount;
                $lineLabels[] = "Tuition: {$tuition->school_year} / Semester {$tuition->semester}";

                continue;
            }

            if ($type === 'fee') {
                $feeKey = (string) ($item['fee_key'] ?? '');
                if (! array_key_exists($feeKey, self::FEE_LABELS)) {
                    $this->fail("items.{$index}.fee_key", 'Choose a supported fee.');
                }

                $amount = $this->money($item['amount'] ?? null, "items.{$index}.amount");
                $settlements[$feeKey] += $amount;
                $lineLabels[] = self::FEE_LABELS[$feeKey];

                continue;
            }

            if ($type === 'item') {
                $productId = (int) ($item['id'] ?? 0);
                $product = $products->get($productId);
                if (! $product instanceof InventoryProduct || ! $product->is_active) {
                    $this->fail("items.{$index}.id", 'The selected inventory item is unavailable.');
                }

                $quantity = (int) ($item['quantity'] ?? 1);
                if ($quantity < 1 || $quantity > 100) {
                    $this->fail("items.{$index}.quantity", 'Choose a valid item quantity.');
                }
                if ((float) $product->price <= 0.0) {
                    $this->fail("items.{$index}.id", 'The selected inventory item does not have a payable price.');
                }

                $stockDeductions[$productId] = ($stockDeductions[$productId] ?? 0) + $quantity;
                $settlements['others'] += round((float) $product->price * $quantity, 2);
                $lineLabels[] = $quantity > 1 ? "{$product->name} × {$quantity}" : $product->name;

                continue;
            }

            $this->fail("items.{$index}.type", 'Choose a supported charge type.');
        }

        foreach ($tuitionAmounts as $tuitionId => $amount) {
            $tuition = $tuitions->get($tuitionId);
            if (! $tuition instanceof StudentTuition || $amount > $this->billing->balanceDue($tuition) + 0.00001) {
                $this->fail('items', 'A tuition payment cannot exceed its current balance.');
            }
        }

        foreach ($stockDeductions as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product instanceof InventoryProduct && $product->track_stock && $product->stock_quantity < $quantity) {
                $this->fail('items', "{$product->name} does not have enough stock.");
            }
        }

        $settlements = collect($settlements)
            ->map(fn (float $amount): float => round($amount, 2))
            ->all();
        $total = round(array_sum($settlements), 2);
        if ($total <= 0.0) {
            $this->fail('items', 'The payment total must be greater than zero.');
        }

        $transaction = Transaction::query()->create([
            'description' => filled($payload['remarks'] ?? null)
                ? Str::limit((string) $payload['remarks'], 2000)
                : Str::limit('Payment for '.implode(', ', array_unique($lineLabels)), 2000),
            'payment_method' => $paymentMethod->value,
            'status' => 'paid',
            'transaction_date' => now(),
            'settlements' => $settlements,
            'invoicenumber' => filled($payload['reference_number'] ?? null) ? (string) $payload['reference_number'] : null,
            'user_id' => $cashier->id,
            'receipt_email_recipient' => $student->email ?: null,
        ]);

        $primaryEnrollmentId = count($tuitionAmounts) === 1 && abs($total - array_sum($tuitionAmounts)) < 0.00001
            ? $tuitions->get((int) array_key_first($tuitionAmounts))?->enrollment_id
            : null;
        StudentTransaction::query()->create([
            'student_id' => $student->id,
            'student_enrollment_id' => $primaryEnrollmentId,
            'transaction_id' => $transaction->id,
            'amount' => $total,
            'status' => 'paid',
            'idempotency_key' => $idempotencyKey,
        ]);
        AdminTransaction::query()->create([
            'admin_id' => $cashier->id,
            'transaction_id' => $transaction->id,
            'amount' => $total,
            'type' => 'credit',
            'description' => 'Cashier payment',
            'status' => 'paid',
        ]);

        foreach ($tuitionAmounts as $tuitionId => $amount) {
            $tuition = $tuitions->get($tuitionId);
            if (! $tuition instanceof StudentTuition) {
                continue;
            }

            $tuition->paid = round((float) $tuition->paid + $amount, 2);
            $tuition->save();
            $this->billing->syncTuitionBalance($tuition);
        }

        foreach ($stockDeductions as $productId => $quantity) {
            $product = $products->get($productId);
            if (! $product instanceof InventoryProduct || ! $product->track_stock) {
                continue;
            }

            $previousStock = (int) $product->stock_quantity;
            $newStock = $previousStock - $quantity;
            $product->forceFill(['stock_quantity' => $newStock])->save();
            InventoryStockMovement::query()->create([
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference' => "Payment #{$transaction->transaction_number}",
                'reason' => 'Cashier payment',
                'user_id' => $cashier->id,
                'movement_date' => now(),
            ]);
        }

        return new RecordedFinancePayment($transaction, false);
    }

    private function existingPayment(string $idempotencyKey): ?StudentTransaction
    {
        return StudentTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->with('transaction')
            ->first();
    }

    private function money(mixed $amount, string $field): float
    {
        if (! is_numeric($amount) || (float) $amount <= 0.0) {
            $this->fail($field, 'Enter a positive amount with no more than two decimal places.');
        }

        return round((float) $amount, 2);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
