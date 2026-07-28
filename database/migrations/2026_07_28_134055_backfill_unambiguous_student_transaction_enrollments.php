<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('student_transactions')
            ->whereNull('student_enrollment_id')
            ->orderBy('id')
            ->chunkById(200, function ($links): void {
                foreach ($links as $link) {
                    $transaction = DB::table('transactions')->where('id', $link->transaction_id)->first(['created_at']);
                    if ($transaction === null || $transaction->created_at === null) {
                        continue;
                    }

                    $createdAt = Carbon::parse($transaction->created_at);
                    $matches = DB::table('student_enrollment')
                        ->where('student_id', $link->student_id)
                        ->whereNull('deleted_at')
                        ->get(['id', 'school_year', 'semester'])
                        ->filter(fn (object $enrollment): bool => $this->dateMatchesPeriod(
                            $createdAt,
                            (string) $enrollment->school_year,
                            (int) $enrollment->semester,
                        ));

                    if ($matches->count() !== 1) {
                        continue;
                    }

                    DB::table('student_transactions')
                        ->where('id', $link->id)
                        ->whereNull('student_enrollment_id')
                        ->update(['student_enrollment_id' => $matches->first()->id]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Historical links are intentionally retained because their former null state is not recoverable.
    }

    private function dateMatchesPeriod(Carbon $createdAt, string $schoolYear, int $semester): bool
    {
        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $schoolYear, $matches) !== 1) {
            return false;
        }

        $startYear = (int) $matches[1];
        $endYear = (int) $matches[2];
        if ($endYear !== $startYear + 1 || ! in_array($semester, [1, 2], true)) {
            return false;
        }

        [$start, $end] = $semester === 1
            ? ["{$startYear}-01-01 00:00:00", ($startYear + 1).'-02-28 23:59:59']
            : ["{$startYear}-11-01 00:00:00", "{$endYear}-07-31 23:59:59"];

        return $createdAt->betweenIncluded(Carbon::parse($start), Carbon::parse($end));
    }
};
