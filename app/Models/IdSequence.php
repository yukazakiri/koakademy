<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

final class IdSequence extends Model
{
    #[Override]
    protected $fillable = [
        'key',
        'label',
        'start_number',
        'next_number',
        'increment_by',
        'padding',
        'prefix_mode',
        'prefix_value',
        'enforce_prefix',
        'enforce_length',
        'exact_length',
        'min_length',
        'max_length',
        'type_prefixes',
    ];

    protected function casts(): array
    {
        return [
            'start_number' => 'integer',
            'next_number' => 'integer',
            'increment_by' => 'integer',
            'padding' => 'integer',
            'enforce_prefix' => 'boolean',
            'enforce_length' => 'boolean',
            'exact_length' => 'integer',
            'min_length' => 'integer',
            'max_length' => 'integer',
            'type_prefixes' => 'array',
        ];
    }
}
