<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Transaction
 *
 * @property-read Collection<int, AdminTransaction> $adminTransactions
 * @property-read int|null $admin_transactions_count
 * @property-read array $academic_period
 * @property-read float $raw_total_amount
 * @property-read string $student_course
 * @property-read mixed $student_email
 * @property-read mixed $student_full_name
 * @property-read mixed $student_id
 * @property-read mixed $student_personal_contact
 * @property-read string|float $total_amount
 * @property-read string $transaction_type_string
 * @property-read Collection<int, Student> $student
 * @property-read int|null $student_count
 * @property-read Collection<int, StudentTransaction> $studentTransactions
 * @property-read int|null $student_transactions_count
 *
 * @method static Builder<static>|Transaction dateRange($startDate = null, $endDate = null)
 * @method static Builder<static>|Transaction forAcademicPeriod(string $schoolYear, int $semester)
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction query()
 * @method static Builder<static>|Transaction sort($field = 'created_at', $direction = 'desc')
 * @method static Builder<static>|Transaction status($status = null)
 *
 * @mixin \Eloquent
 */
final class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'description',
        'payment_method',
        'status',
        'transaction_date',
        'transaction_number',
        'settlements',
        'invoicenumber',
        'signature',
        'user_id',
    ];

    public function student()
    {
        return $this->belongsToMany(Student::class, 'student_transactions', 'transaction_id', 'student_id');
    }

    public function studentTransactions()
    {
        return $this->hasMany(StudentTransaction::class, 'transaction_id');
    }

    public function adminTransactions()
    {
        return $this->hasMany(AdminTransaction::class, 'transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($transaction): void {
            $transaction->transaction_number = null; // Set to null initially
        });

        self::created(function ($transaction): void {
            $randomNumber = mt_rand(1000000000, 9999999999); // 10 digit random number
            $transaction->update(['transaction_number' => $randomNumber]);
        });

        self::deleting(function ($transaction): void {
            $transaction->studentTransactions()->delete();
            $transaction->adminTransactions()->delete();
        });
    }

    protected function transactionTypeString(): Attribute
    {
        return Attribute::make(get: fn (): string => ucwords(str_replace('_', ' ', $this->transaction_type)));
    }

    protected function studentFullName(): Attribute
    {
        return Attribute::make(get: function () {
            $student = $this->student()->first();

            return $student->full_name ?? 'No Name Found';
        });
    }

    protected function studentCourse(): Attribute
    {
        return Attribute::make(get: function (): string {
            $student = $this->student()->first();

            return $student->course->code.' '.$student->academic_year;
        });
    }

    protected function studentEmail(): Attribute
    {
        return Attribute::make(get: function () {
            $student = $this->student()->first();

            return $student->email;
        });
    }

    protected function studentPersonalContact(): Attribute
    {
        return Attribute::make(get: function () {
            $student = $this->student()->first();

            return $student->studentContactsInfo->personal_contact ?? '';
        });
    }

    protected function studentId(): Attribute
    {
        return Attribute::make(get: function () {
            $student = $this->student()->first();

            return $student->id ?? 'No ID Found';
        });
    }

    protected function totalAmount(): Attribute
    {
        return Attribute::make(get: function (): float|string {
            $settlements = $this->settlements;
            if (is_string($settlements)) {
                $settlements = json_decode($settlements, true);
            }

            if (! is_array($settlements)) {
                return 0.00;
            }

            $total = array_reduce(array_values($settlements), fn ($carry, $value): float => $carry + (float) $value, 0.0);

            return number_format($total, 2);
        });
    }

    /**
     * Scope a query to sort transactions by a specific field.
     *
     * @param  Builder  $query
     * @param  string  $field
     * @param  string  $direction
     * @return Builder
     */
    protected function scopeSort($query, $field = 'created_at', $direction = 'desc')
    {
        $allowedFields = [
            'invoicenumber',
            'description',
            'transaction_date',
            'created_at',
            'status',
            'transaction_number',
        ];

        $field = in_array($field, $allowedFields) ? $field : 'created_at';
        $direction = in_array(mb_strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';

        // Qualify created_at to avoid ambiguity in joins
        if ($field === 'created_at') {
            $field = 'transactions.created_at';
        }

        return $query->orderBy($field, $direction);
    }

    /**
     * Scope a query to filter transactions by date range.
     *
     * @param  Builder  $query
     * @param  string|null  $startDate
     * @param  string|null  $endDate
     * @return Builder
     */
    protected function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('transactions.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('transactions.created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope a query to filter transactions by status.
     *
     * @param  Builder  $query
     * @param  string  $status
     * @return Builder
     */
    protected function scopeStatus($query, $status = null)
    {
        if ($status) {
            return $query->where('status', $status);
        }

        return $query;
    }

    protected function tuitionAmount(): Attribute
    {
        return Attribute::make(get: function (): float {
            $settlements = $this->settlements;
            if (is_string($settlements)) {
                $settlements = json_decode($settlements, true);
            }

            if (! is_array($settlements)) {
                return 0.00;
            }

            // Only count if it's explicitly for tuition_fee
            return isset($settlements['tuition_fee']) ? (float) $settlements['tuition_fee'] : 0.00;
        });
    }

    /**
     * Get the raw numeric value of the total amount.
     */
    protected function rawTotalAmount(): Attribute
    {
        return Attribute::make(get: function (): float {
            $settlements = $this->settlements;
            if (is_string($settlements)) {
                $settlements = json_decode($settlements, true);
            }

            if (! is_array($settlements)) {
                return 0.00;
            }

            return array_reduce(array_values($settlements), fn ($carry, $value): float => $carry + (float) $value, 0.0);
        });
    }

    /**
     * Get the academic period this transaction belongs to
     * Returns an array with school_year and semester
     */
    protected function academicPeriod(): Attribute
    {
        return Attribute::make(get: function (): array {
            $transactionDate = $this->created_at;
            $year = $transactionDate->year;
            $month = $transactionDate->month;
            // Determine semester based on month
            if ($month >= 8 && $month <= 12) {
                // First semester (August to December)
                $semester = 1;
                $schoolYear = $year.'-'.($year + 1);
            } else {
                // Second semester (January to July)
                $semester = 2;
                $schoolYear = ($year - 1).'-'.$year;
            }

            return [
                'school_year' => $schoolYear,
                'semester' => $semester,
            ];
        });
    }

    /**
     * Scope to filter transactions by academic period
     */
    protected function scopeForAcademicPeriod($query, string $schoolYear, int $semester)
    {
        // Parse school year (e.g., "2024-2025")
        $years = explode('-', $schoolYear);
        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        if ($semester === 1) {
            // First semester: August to December (extended to Feb next year to cover late payments/enrollments)
            // Range: StartYear-06-01 to StartYear+1-02-28
            $startDate = $startYear.'-06-01 00:00:00';
            $endDate = ($startYear + 1).'-02-28 23:59:59';
        } else {
            // Second semester: January to July (extended to StartYear-11-01 to cover early downpayments)
            // Range: StartYear-11-01 to EndYear-07-31
            $startDate = $startYear.'-11-01 00:00:00';
            $endDate = $endYear.'-07-31 23:59:59';
        }

        return $query->whereBetween('transactions.created_at', [$startDate, $endDate]);
    }

    protected function casts(): array
    {
        return [
            'settlements' => 'array',
            'transaction_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
