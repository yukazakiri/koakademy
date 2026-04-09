<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class StudentTuition
 *
 * @property-read StudentEnrollment|null $enrollment
 * @property-read string $formatted_discount
 * @property-read string $formatted_downpayment
 * @property-read string $formatted_overall_tuition
 * @property-read string $formatted_semester
 * @property-read string $formatted_total_balance
 * @property-read string $formatted_total_laboratory
 * @property-read string $formatted_total_lectures
 * @property-read string $formatted_total_miscelaneous_fees
 * @property-read string $formatted_total_tuition
 * @property-read int $payment_progress
 * @property-read string $payment_status
 * @property-read string $status_class
 * @property-read Student|null $student
 *
 * @method static Builder<static>|StudentTuition newModelQuery()
 * @method static Builder<static>|StudentTuition newQuery()
 * @method static Builder<static>|StudentTuition onlyTrashed()
 * @method static Builder<static>|StudentTuition query()
 * @method static Builder<static>|StudentTuition withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudentTuition withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class StudentTuition extends Model
{
    use SoftDeletes;

    protected $table = 'student_tuition';

    protected $fillable = [
        'total_tuition',
        'total_balance',
        'total_lectures',
        'total_laboratory',
        'total_miscelaneous_fees',
        'status',
        'semester',
        'school_year',
        'academic_year',
        'student_id',
        'enrollment_id',
        'discount',
        'downpayment',
        'overall_tuition',
        'paid',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function studentTransactions()
    {
        return $this->student->transactions();
    }

    public function enrollment()
    {
        return $this->belongsTo(
            StudentEnrollment::class,
            'enrollment_id',
            'id'
        );
    }

    /**
     * Get the calculated total paid amount
     */
    protected function totalPaid(): Attribute
    {
        return Attribute::make(get: function (): float {
            // Use the paid column if it has a value greater than 0
            if (isset($this->attributes['paid']) && $this->attributes['paid'] > 0) {
                return (float) $this->attributes['paid'];
            }

            // Try to get the student - first via direct relationship, then via enrollment
            $student = $this->student;
            if (! $student && $this->enrollment) {
                $student = $this->enrollment->student;
            }

            // If still no student, return 0
            if (! $student) {
                return 0.00;
            }

            // Clean school year format (remove spaces)
            if (! is_string($this->school_year) || $this->school_year === '') {
                return 0.00;
            }

            $schoolYear = str_replace(' ', '', $this->school_year);

            // Get transactions using the scope from Transaction model
            $transactions = $student->Transaction()
                ->forAcademicPeriod($schoolYear, $this->semester)
                ->get();

            $total = 0.00;

            foreach ($transactions as $transaction) {
                $settlements = $transaction->settlements;

                // Handle JSON decoding if it's a string
                if (is_string($settlements)) {
                    $settlements = json_decode($settlements, true);
                }

                // Sum up tuition_fee
                if (is_array($settlements) && isset($settlements['tuition_fee'])) {
                    $total += (float) $settlements['tuition_fee'];
                }
            }

            return $total;
        });
    }

    /**
     * Get the formatted total paid amount
     */
    protected function formattedTotalPaid(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->total_paid, 2));
    }

    /**
     * Calculate the payment progress percentage
     *
     * @return int
     */
    protected function paymentProgress(): Attribute
    {
        return Attribute::make(get: function (): int|float {
            if ($this->overall_tuition <= 0) {
                return 0;
            }

            $paid = $this->overall_tuition - $this->total_balance;

            return min(100, round(($paid / $this->overall_tuition) * 100));
        });
    }

    /**
     * Get the formatted total balance
     */
    protected function formattedTotalBalance(): Attribute
    {
        return Attribute::make(get: function (): string {
            // Calculate balance dynamically if paid was calculated from transactions
            // Total Balance = Overall Tuition - Total Paid

            // If the DB total_balance seems inconsistent with our calculated paid, prefer calculated
            // But let's stick to simple logic: If we rely on calculated Paid, we should rely on (Overall - Paid) for balance
            // UNLESS paid column was used directly.

            // However, modifying total_balance accessor might affect other things.
            // The UI uses `formatted_total_balance`.

            // Let's use the calculated total_paid to determine balance for consistency
            $balance = $this->overall_tuition - $this->total_paid;

            return $this->getCurrencySymbol().' '.number_format($balance, 2);
        });
    }

    /**
     * Get the formatted overall tuition
     */
    protected function formattedOverallTuition(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->overall_tuition, 2));
    }

    /**
     * Get the formatted total tuition
     */
    protected function formattedTotalTuition(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->total_tuition, 2));
    }

    /**
     * Get the formatted semester
     */
    protected function formattedSemester(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->semester.($this->semester === 1 ? 'st' : 'nd').' Semester');
    }

    /**
     * Get the payment status
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->total_balance <= 0 ? 'Fully Paid' : 'Not Fully Paid');
    }

    /**
     * Get the payment status class for UI
     */
    protected function statusClass(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->total_balance <= 0
            ? 'bg-green-100 text-green-800 dark:bg-green-200 dark:text-green-900'
            : 'bg-red-100 text-red-800 dark:bg-red-200 dark:text-red-900');
    }

    /**
     * Get the formatted total lectures
     */
    protected function formattedTotalLectures(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->total_lectures, 2));
    }

    /**
     * Get the formatted total laboratory
     */
    protected function formattedTotalLaboratory(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->total_laboratory, 2));
    }

    /**
     * Get the formatted total miscellaneous fees
     */
    protected function formattedTotalMiscelaneousFees(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->total_miscelaneous_fees, 2));
    }

    /**
     * Get the formatted downpayment
     */
    protected function formattedDownpayment(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->getCurrencySymbol().' '.number_format($this->downpayment, 2));
    }

    /**
     * Get the formatted discount
     */
    protected function formattedDiscount(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->discount.'%');
    }

    protected function casts(): array
    {
        return [
            'total_tuition' => 'float',
            'total_balance' => 'float',
            'total_lectures' => 'float',
            'total_laboratory' => 'float',
            'total_miscelaneous_fees' => 'float',
            'semester' => 'integer',
            'academic_year' => 'integer',
            'student_id' => 'integer',
            'enrollment_id' => 'integer',
            'discount' => 'integer',
            'downpayment' => 'float',
            'overall_tuition' => 'float',
            'paid' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    private function getCurrencySymbol(): string
    {
        $currency = app(\App\Settings\SiteSettings::class)->getCurrency();

        return $currency === 'USD' ? '$' : '₱';
    }
}
