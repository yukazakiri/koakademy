<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsProviderInstance;
use App\Models\GeneralSetting;
use App\Support\Analytics\AnalyticsProviderCatalog;
use Illuminate\Support\Collection;

/**
 * Resolves the analytics provider instances for an installation and renders
 * their tracking snippets.
 *
 * There is no global provider any more. Every configured instance is rendered
 * independently, so an operator can run Umami and Plausible at the same time,
 * or add a second Umami instance, and each one is toggled on its own. The
 * `analytics_enabled` flag on the general settings row remains a master switch
 * that gates all of them at once.
 */
final readonly class AnalyticsSettingsService
{
    public function __construct(
        private GeneralSettingsService $generalSettingsService,
    ) {}

    /**
     * All configured instances for the active installation.
     *
     * @return Collection<int, AnalyticsProviderInstance>
     */
    public function instances(): Collection
    {
        $generalSetting = $this->generalSettingsService->getGlobalSettingsModel();

        if (! $generalSetting instanceof GeneralSetting) {
            return new Collection;
        }

        return AnalyticsProviderInstance::query()
            ->where('general_setting_id', $generalSetting->id)
            ->ordered()
            ->get();
    }

    /**
     * Enabled instances that resolve to a non-empty snippet.
     *
     * @return Collection<int, AnalyticsProviderInstance>
     */
    public function activeInstances(): Collection
    {
        return $this->instances()
            ->filter(fn (AnalyticsProviderInstance $instance): bool => $instance->enabled)
            ->filter(fn (AnalyticsProviderInstance $instance): bool => $this->snippetFor($instance) !== '')
            ->values();
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->generalSettingsService->getGlobalSettingsModel()?->analytics_enabled ?? false);
    }

    /**
     * Whether any enabled instance is actually going to emit a script.
     */
    public function hasActiveProviders(): bool
    {
        return $this->isEnabled() && $this->activeInstances()->isNotEmpty();
    }

    /**
     * The snippet for a single instance: its manual override when set,
     * otherwise the snippet generated from its settings.
     */
    public function snippetFor(AnalyticsProviderInstance $instance): string
    {
        $manual = $instance->manualScript();

        if ($manual !== '') {
            return $manual;
        }

        return AnalyticsProviderCatalog::buildSnippet(
            $instance->provider,
            is_array($instance->settings) ? $instance->settings : [],
        );
    }

    /**
     * Every active snippet joined together, for server-rendered pages.
     */
    public function renderHeadMarkup(): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $snippets = $this->activeInstances()
            ->map(fn (AnalyticsProviderInstance $instance): string => $this->snippetFor($instance))
            ->filter(static fn (string $snippet): bool => $snippet !== '')
            ->unique()
            ->values();

        if ($snippets->isEmpty()) {
            return '';
        }

        return $snippets->implode("\n");
    }

    /**
     * Config handed to the React app. The frontend injects snippets itself so
     * that providers can be toggled without a full page render.
     *
     * @return array{
     *     enabled: bool,
     *     has_providers: bool,
     *     providers: array<int, array{
     *         key: string,
     *         label: string,
     *         snippet: string,
     *         session: bool
     *     }>
     * }
     */
    public function getFrontendConfig(): array
    {
        $enabled = $this->isEnabled();
        $active = $enabled ? $this->activeInstances() : new Collection;

        return [
            'enabled' => $enabled,
            'has_providers' => $active->isNotEmpty(),
            'providers' => $active
                ->map(function (AnalyticsProviderInstance $instance): array {
                    $definition = AnalyticsProviderCatalog::get($instance->provider);

                    return [
                        'key' => $instance->provider,
                        'label' => $instance->label ?: (is_array($definition) ? $definition['label'] : $instance->provider),
                        'snippet' => $this->snippetFor($instance),
                        // Google Analytics page views are sent by the client on
                        // Inertia navigation, so it needs an active JS hook.
                        'session' => $instance->provider === 'google',
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
