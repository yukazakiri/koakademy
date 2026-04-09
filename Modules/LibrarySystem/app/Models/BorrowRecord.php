<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LibrarySystem\Database\Factories\BorrowRecordFactory;

final class BorrowRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'library_borrow_records';

    protected $fillable = [
        'book_id',
        'user_id',
        'borrowed_at',
        'due_date',
        'returned_at',
        'status',
        'fine_amount',
        'notes',
    ];

    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_date' => 'datetime',
        'returned_at' => 'datetime',
        'fine_amount' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'borrowed' && $this->due_date < now();
    }

    public function getDaysOverdueAttribute(): float
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'borrowed' => $this->isOverdue() ? 'danger' : 'warning',
            'returned' => 'success',
            'lost' => 'danger',
            default => 'gray',
        };
    }

    // protected static function newFactory()
    // {
    //     return BorrowRecordFactory::new();
    // }
}
