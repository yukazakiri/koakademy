<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class OnboardingFeature extends Model
{
    /** @use HasFactory<\Database\Factories\OnboardingFeatureFactory> */
    use HasFactory;

    protected $fillable = [
        'feature_key',
        'name',
        'audience',
        'summary',
        'badge',
        'accent',
        'cta_label',
        'cta_url',
        'steps',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
