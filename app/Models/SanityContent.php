<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SanityContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SanityContent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'sanity_id',
        'post_kind',
        'title',
        'slug',
        'excerpt',
        'content',
        'content_focus',
        'featured_image',
        'priority',
        'activation_window',
        'channels',
        'cta',
        'status',
        'published_at',
        'sanity_updated_at',
        'featured',
        'category',
        'author',
        'tags',
        'audiences',
        'primary_category_id',
        'department_ids',
        'program_ids',
        'author_ids',
        'related_post_ids',
        'seo',
        'meta_data',
    ];

    public function isAlert(): bool
    {
        return in_array($this->post_kind, ['announcement', 'alert']);
    }

    public function isNewsOrStory(): bool
    {
        return in_array($this->post_kind, ['news', 'story']);
    }

    protected static function newFactory(): SanityContentFactory
    {
        return SanityContentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'featured_image' => 'array',
            'activation_window' => 'array',
            'channels' => 'array',
            'cta' => 'array',
            'tags' => 'array',
            'audiences' => 'array',
            'department_ids' => 'array',
            'program_ids' => 'array',
            'author_ids' => 'array',
            'related_post_ids' => 'array',
            'seo' => 'array',
            'meta_data' => 'array',
            'published_at' => 'datetime',
            'sanity_updated_at' => 'datetime',
            'featured' => 'boolean',
        ];
    }
}
