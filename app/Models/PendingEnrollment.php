<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Import Attribute class
/**
 * @property-read User|null $approver
 * @property-read Course|null $course
 * @property-read mixed $course_id
 * @property-read mixed $email
 * @property-read mixed $first_name
 * @property-read mixed $last_name
 *
 * @method static Builder<static>|PendingEnrollment newModelQuery()
 * @method static Builder<static>|PendingEnrollment newQuery()
 * @method static Builder<static>|PendingEnrollment query()
 *
 * @mixin \Eloquent
 */
final class PendingEnrollment extends Model
{
    use HasFactory;

    protected $table = 'pending_enrollments';

    protected $fillable = [
        'data',
        'status',
        'remarks',
        'approved_by',
        'processed_at',
    ];

    /**
     * Get the user who approved/rejected the enrollment.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Add a relationship to Course if needed for display
    public function course()
    {
        // Check if course_id exists in data before trying to relate
        if (isset($this->data['course_id'])) {
            return $this->belongsTo(Course::class, 'data->course_id'); // Adjust if course_id is stored differently
        }

        // Return a dummy relationship or null if course_id is not set
        return $this->belongsTo(Course::class)->whereRaw('1 = 0'); // Example: always returns empty
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(get: fn () => $this->data['first_name'] ?? null);
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(get: fn () => $this->data['last_name'] ?? null);
    }

    protected function email(): Attribute
    {
        return Attribute::make(get: fn () => $this->data['email'] ?? null);
    }

    protected function courseId(): Attribute
    {
        return Attribute::make(get: fn () => $this->data['course_id'] ?? null);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array', // Cast the JSON column to an array
            'processed_at' => 'datetime',
        ];
    }
}
