<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class StudentTuitionUpdateRequestReceipt extends Model
{
    protected $fillable = [
        'student_tuition_update_request_id',
        'receipt_number',
        'amount',
        'image_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(StudentTuitionUpdateRequest::class, 'student_tuition_update_request_id');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            if (! filled($this->image_path)) {
                return null;
            }

            if (str_starts_with((string) $this->image_path, 'http://') || str_starts_with((string) $this->image_path, 'https://')) {
                return $this->image_path;
            }

            return Storage::disk('public')->url($this->image_path);
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'file_size' => 'integer',
        ];
    }
}
