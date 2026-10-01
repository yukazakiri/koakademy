<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One configured analytics provider.
 *
 * An installation may hold any number of rows, including several rows for the
 * same provider key (for example two Umami instances, or several custom
 * snippets). Each row carries its own enable flag, settings, and script
 * override, so nothing is global and providers can be toggled independently.
 *
 * @property int $id
 * @property string $provider
 * @property string $label
 * @property bool $enabled
 * @property array<string, mixed> $settings
 * @property string|null $script
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AnalyticsProviderInstance extends Model
{
    protected $table = 'analytics_provider_instances';

    protected $fillable = [
        'provider',
        'label',
        'enabled',
        'settings',
        'script',
        'position',
    ];

    /**
     * @return BelongsTo<GeneralSetting, $this>
     */
    public function generalSetting(): BelongsTo
    {
        return $this->belongsTo(GeneralSetting::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * The manual snippet for this instance, if one was set. A manual snippet
     * always wins over the generated one.
     */
    public function manualScript(): string
    {
        return is_string($this->script) ? mb_trim($this->script) : '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'position' => 'integer',
        ];
    }
}
