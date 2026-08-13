<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Services\EnrollmentBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * Class StudentTuition
 *
 * @property int|null $adjusted_by_user_id
 * @property \Illuminate\Support\Carbon|null $adjusted_at
 * @property string|null $adjustment_note
 * @property-read StudentEnrollment|null $enrollment
 * @property-read EnrollmentDiscount|null $enrollmentDiscount
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

    #[Override]
    protected $table = 'student_tuition';

    #[Override]
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
        'discount_id',
        'downpayment',
        'overall_tuition',
        'paid',
        'adjustment_note',
        'adjusted_by_user_id',
        'adjusted_at',
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

    public function enrollmentDiscount(): BelongsTo
    {
        return $this->belongsTo(EnrollmentDiscount::class, 'discount_id');
    }

    /**
     * Get the calculated total paid amount
     */
    protected function totalPaid(): Attribute
    {
        return Attribute::make(get: fn (): float => app(EnrollmentBillingService::class)->totalPaid($this));
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
     */
    protected function paymentProgress(): Attribute
    {
        return Attribute::make(get: function (): int|float {
            if ($this->overall_tuition <= 0) {
                return 0;
            }

            $paid = app(EnrollmentBillingService::class)->totalPaid($this);

            return min(100, round(($paid / $this->overall_tuition) * 100));
        });
    }

    /**
     * Get the formatted total balance
     */
    protected function formattedTotalBalance(): Attribute
    {
        return Attribute::make(get: function (): string {
            $balance = app(EnrollmentBillingService::class)->balanceDue($this);

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
        return Attribute::make(get: fn (): string => app(EnrollmentBillingService::class)->balanceDue($this) <= 0 ? 'Fully Paid' : 'Not Fully Paid');
    }

    /**
     * Get the payment status class for UI
     */
    protected function statusClass(): Attribute
    {
        return Attribute::make(get: fn (): string => app(EnrollmentBillingService::class)->balanceDue($this) <= 0
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
            'discount_id' => 'integer',
            'downpayment' => 'float',
            'overall_tuition' => 'float',
            'paid' => 'float',
            'adjusted_by_user_id' => 'integer',
            'adjusted_at' => 'datetime',
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
