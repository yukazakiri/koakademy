<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Ai\AiSettingsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class AiAssistantOverviewWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 35;

    #[Override]
    protected ?string $heading = 'AI Infrastructure & Multi-Provider Health';

    #[Override]
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $settingsService = app(AiSettingsService::class);
        $settings = $settingsService->get();

        $primaryKey = (string) ($settings['primary_provider'] ?? 'anthropic');
        $fallbackKey = (string) ($settings['fallback_provider'] ?? 'None');
        $failoverEnabled = (bool) ($settings['failover_enabled'] ?? false);
        $engineActive = (bool) ($settings['enabled'] ?? true);

        $supported = AiSettingsService::supportedProviders();
        $primaryLabel = $supported[$primaryKey]['label'] ?? ucfirst($primaryKey);
        $fallbackLabel = $supported[$fallbackKey]['label'] ?? ucfirst($fallbackKey);

        $primaryConfig = $settings['providers'][$primaryKey] ?? [];
        $activeModel = (string) ($primaryConfig['default_chat_model'] ?? 'Default');

        return [
            Stat::make('Primary AI Provider', $engineActive ? $primaryLabel : 'Engine Disabled')
                ->description($engineActive ? 'Active routing driver' : 'System-wide disabled')
                ->descriptionIcon($engineActive ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($engineActive ? 'success' : 'danger')
                ->url('/administrators/system-management/ai'),

            Stat::make('Default Agent Model', $activeModel ?: 'Unspecified')
                ->description('Serving student & faculty workflows')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info')
                ->url('/administrators/system-management/ai'),

            Stat::make('Failover Protection', $failoverEnabled && filled($fallbackKey) ? "Active ({$fallbackLabel})" : 'Standby')
                ->description($failoverEnabled ? 'Automatic error failover ready' : 'Direct execution only')
                ->descriptionIcon($failoverEnabled ? 'heroicon-m-shield-check' : 'heroicon-m-shield-exclamation')
                ->color($failoverEnabled ? 'success' : 'warning')
                ->url('/administrators/system-management/ai'),
        ];
    }
}
