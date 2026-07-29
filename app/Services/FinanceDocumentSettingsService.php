<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Models\GeneralSetting;

final readonly class FinanceDocumentSettingsService
{
    /**
     * @return array{automatic_receipts_enabled: bool, require_paper_or_reference: bool, manual_invoices_enabled: bool, mail_delivery_available: bool}
     */
    public function get(): array
    {
        $settings = GeneralSetting::query()->first();
        $configured = $settings?->more_configs['finance_documents'] ?? [];

        return [
            'automatic_receipts_enabled' => (bool) ($configured['automatic_receipts_enabled'] ?? true),
            'require_paper_or_reference' => (bool) ($configured['require_paper_or_reference'] ?? true),
            'manual_invoices_enabled' => (bool) ($configured['manual_invoices_enabled'] ?? true),
            'mail_delivery_available' => $this->mailDeliveryAvailable($settings),
        ];
    }

    /**
     * @param  array{automatic_receipts_enabled: bool, require_paper_or_reference: bool, manual_invoices_enabled: bool}  $configuration
     */
    public function update(array $configuration): void
    {
        $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => config('app.name')]);
        $moreConfigs = $settings->more_configs ?? [];
        $moreConfigs['finance_documents'] = $configuration;
        $settings->update(['more_configs' => $moreConfigs]);
    }

    private function mailDeliveryAvailable(?GeneralSetting $settings): bool
    {
        $enabledChannels = $settings?->more_configs['notification_channels']['enabled_channels']
            ?? array_map(
                static fn (NotificationChannel $channel): string => $channel->value,
                NotificationChannel::defaultChannels(),
            );

        return in_array(NotificationChannel::Mail->value, $enabledChannels, true)
            && filled($settings?->email_from_address ?: config('mail.from.address'));
    }
}
