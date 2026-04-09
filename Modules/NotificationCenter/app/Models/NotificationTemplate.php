<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'mail_template',
        'default_channels',
        'variables',
        'styles',
        'is_active',
    ];

    public static function findBySlug(string $slug): ?self
    {
        return self::active()->where('slug', $slug)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    protected function casts(): array
    {
        return [
            'default_channels' => 'array',
            'variables' => 'array',
            'styles' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
