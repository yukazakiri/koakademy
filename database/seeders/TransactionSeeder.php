<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminTransaction;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

final class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();
        $users = User::all();
        $cashier = $users->where('name', 'like', '%Cashier%')->first();
        $admin = $users->where('role', 'admin')->first();

        $transactions = [
            // Enrollment payments
            [
                'description' => 'Enrollment Payment - First Semester 2024-2025',
                'status' => 'completed',
                'transaction_date' => now()->subDays(30),
                'settlements' => json_encode([
                    'tuition_fee' => 12500.00,
                    'miscellaneous_fee' => 3700.00,
                    'laboratory_fee' => 2800.00,
                ]),
                'invoicenumber' => 'INV-2024-001',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'enrollment_payment',
                'student_id' => 2021001,
                'admin_id' => $cashier?->id ?? 1,
            ],
            [
                'description' => 'Enrollment Payment - First Semester 2024-2025',
                'status' => 'completed',
                'transaction_date' => now()->subDays(28),
                'settlements' => json_encode([
                    'tuition_fee' => 11800.00,
                    'miscellaneous_fee' => 3500.00,
                    'laboratory_fee' => 2200.00,
                ]),
                'invoicenumber' => 'INV-2024-002',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'enrollment_payment',
                'student_id' => 2021002,
                'admin_id' => $cashier?->id ?? 1,
            ],
            [
                'description' => 'Enrollment Payment - First Semester 2024-2025',
                'status' => 'completed',
                'transaction_date' => now()->subDays(25),
                'settlements' => json_encode([
                    'tuition_fee' => 12100.00,
                    'miscellaneous_fee' => 3600.00,
                    'laboratory_fee' => 2500.00,
                ]),
                'invoicenumber' => 'INV-2024-003',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'enrollment_payment',
                'student_id' => 2021003,
                'admin_id' => $cashier?->id ?? 1,
            ],

            // Partial payments
            [
                'description' => 'Partial Payment - Tuition Balance',
                'status' => 'completed',
                'transaction_date' => now()->subDays(15),
                'settlements' => json_encode([
                    'partial_payment' => 5000.00,
                ]),
                'invoicenumber' => 'INV-2024-004',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'partial_payment',
                'student_id' => 2024001,
                'admin_id' => $cashier?->id ?? 1,
            ],
            [
                'description' => 'Partial Payment - Laboratory Fees',
                'status' => 'completed',
                'transaction_date' => now()->subDays(12),
                'settlements' => json_encode([
                    'laboratory_fee' => 1500.00,
                ]),
                'invoicenumber' => 'INV-2024-005',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'partial_payment',
                'student_id' => 2024002,
                'admin_id' => $cashier?->id ?? 1,
            ],

            // Late payment fees
            [
                'description' => 'Late Payment Fee',
                'status' => 'completed',
                'transaction_date' => now()->subDays(10),
                'settlements' => json_encode([
                    'late_fee' => 500.00,
                ]),
                'invoicenumber' => 'INV-2024-006',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'late_fee',
                'student_id' => 2023001,
                'admin_id' => $cashier?->id ?? 1,
            ],

            // Miscellaneous transactions
            [
                'description' => 'ID Replacement Fee',
                'status' => 'completed',
                'transaction_date' => now()->subDays(8),
                'settlements' => json_encode([
                    'id_replacement' => 200.00,
                ]),
                'invoicenumber' => 'INV-2024-007',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'miscellaneous',
                'student_id' => 2022001,
                'admin_id' => $cashier?->id ?? 1,
            ],
            [
                'description' => 'Certificate Request Fee',
                'status' => 'completed',
                'transaction_date' => now()->subDays(5),
                'settlements' => json_encode([
                    'certificate_fee' => 150.00,
                ]),
                'invoicenumber' => 'INV-2024-008',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'certificate',
                'student_id' => 2020001,
                'admin_id' => $cashier?->id ?? 1,
            ],

            // Pending transactions
            [
                'description' => 'Pending Enrollment Payment - Second Semester',
                'status' => 'pending',
                'transaction_date' => now()->subDays(2),
                'settlements' => json_encode([
                    'tuition_fee' => 13000.00,
                    'miscellaneous_fee' => 3700.00,
                ]),
                'invoicenumber' => 'INV-2024-009',
                'signature' => null,
                'transaction_type' => 'enrollment_payment',
                'student_id' => 2024001,
                'admin_id' => $admin?->id ?? 1,
            ],
            [
                'description' => 'Pending Balance Settlement',
                'status' => 'pending',
                'transaction_date' => now()->subDays(1),
                'settlements' => json_encode([
                    'balance_payment' => 7500.00,
                ]),
                'invoicenumber' => 'INV-2024-010',
                'signature' => null,
                'transaction_type' => 'balance_payment',
                'student_id' => 2024002,
                'admin_id' => $admin?->id ?? 1,
            ],

            // Refund transactions
            [
                'description' => 'Overpayment Refund',
                'status' => 'completed',
                'transaction_date' => now()->subDays(20),
                'settlements' => json_encode([
                    'refund_amount' => -1200.00, // Negative for refunds
                ]),
                'invoicenumber' => 'REF-2024-001',
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'refund',
                'student_id' => 2021001,
                'admin_id' => $admin?->id ?? 1,
            ],
        ];

        foreach ($transactions as $transactionData) {
            $studentId = $transactionData['student_id'];
            $adminId = $transactionData['admin_id'];
            unset($transactionData['student_id'], $transactionData['admin_id']);

            // Create the main transaction
            $transaction = Transaction::query()->create($transactionData);

            // Create student transaction relationship
            StudentTransaction::query()->create([
                'student_id' => $studentId,
                'transaction_id' => $transaction->id,
                'amount' => (int) $transaction->raw_total_amount,
                'status' => $transaction->status,
            ]);

            // Create admin transaction relationship
            AdminTransaction::query()->create([
                'admin_id' => $adminId,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->raw_total_amount,
                'type' => $transaction->raw_total_amount >= 0 ? 'credit' : 'debit',
                'description' => $transaction->description,
                'status' => $transaction->status,
            ]);
        }

        // Create some additional random transactions for variety
        $randomTransactions = [
            'Transcript Request Fee',
            'Clearance Processing Fee',
            'Library Fine Payment',
            'Uniform Purchase',
            'Graduation Fee',
            'Medical Exam Fee',
            'Insurance Payment',
            'Activity Fee',
        ];

        foreach ($students->take(5) as $student) {
            $randomDesc = $randomTransactions[array_rand($randomTransactions)];
            $randomAmount = random_int(100, 1000);

            $transaction = Transaction::query()->create([
                'description' => $randomDesc,
                'status' => 'completed',
                'transaction_date' => now()->subDays(random_int(1, 60)),
                'settlements' => json_encode(['fee' => $randomAmount]),
                'invoicenumber' => 'INV-2024-'.mb_str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT),
                'signature' => 'Digital Signature Hash',
                'transaction_type' => 'miscellaneous',
            ]);

            StudentTransaction::query()->create([
                'student_id' => $student->id,
                'transaction_id' => $transaction->id,
                'amount' => $randomAmount,
                'status' => 'completed',
            ]);

            AdminTransaction::query()->create([
                'admin_id' => $cashier?->id ?? 1,
                'transaction_id' => $transaction->id,
                'amount' => $randomAmount,
                'type' => 'credit',
                'description' => $randomDesc,
                'status' => 'completed',
            ]);
        }

        $this->command->info('Transactions seeded successfully!');
    }
}
