<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\GeneralSetting;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

final class NotificationChannelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $settings = GeneralSetting::query()->first();

            if (! $settings) {
                return;
            }

            $channelConfig = $settings->more_configs['notification_channels'] ?? null;

            $servicesConfig = $settings->more_configs['third_party_services'] ?? null;
            if ($servicesConfig) {
                $this->applyThirdPartyServices($servicesConfig);
            }

            if (! $channelConfig) {
                return;
            }

            $this->applyNotificationChannelConfig($channelConfig);
        } catch (Exception $exception) {
            Log::warning('NotificationChannelServiceProvider: unable to load config from DB.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $channelConfig
     */
    private function applyNotificationChannelConfig(array $channelConfig): void
    {
        $enabledChannels = $channelConfig['enabled_channels'] ?? [];
        config()->set('notification_channels.enabled', $enabledChannels);

        if (in_array('broadcast', $enabledChannels) || in_array('pusher', $enabledChannels)) {
            $this->applyPusherConfig($channelConfig['pusher'] ?? []);
        }

        if (in_array('sms', $enabledChannels)) {
            $this->applySmsConfig($channelConfig['sms'] ?? []);
        }
    }

    /**
     * @param  array<string, mixed>  $pusherConfig
     */
    private function applyPusherConfig(array $pusherConfig): void
    {
        if (empty($pusherConfig)) {
            return;
        }

        config()->set('broadcasting.default', 'pusher');

        if (! empty($pusherConfig['app_id'])) {
            config()->set('broadcasting.connections.pusher.app_id', $pusherConfig['app_id']);
        }
        if (! empty($pusherConfig['key'])) {
            config()->set('broadcasting.connections.pusher.key', $pusherConfig['key']);
        }
        if (! empty($pusherConfig['secret'])) {
            config()->set('broadcasting.connections.pusher.secret', $pusherConfig['secret']);
        }
        if (! empty($pusherConfig['cluster'])) {
            config()->set('broadcasting.connections.pusher.options.cluster', $pusherConfig['cluster']);
        }
    }

    /**
     * @param  array<string, mixed>  $smsConfig
     */
    private function applySmsConfig(array $smsConfig): void
    {
        if (empty($smsConfig)) {
            return;
        }

        if (! empty($smsConfig['provider'])) {
            config()->set('notification_channels.sms.provider', $smsConfig['provider']);
        }
        if (! empty($smsConfig['api_key'])) {
            config()->set('notification_channels.sms.api_key', $smsConfig['api_key']);
        }
        if (! empty($smsConfig['sender_id'])) {
            config()->set('notification_channels.sms.sender_id', $smsConfig['sender_id']);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $servicesConfig
     */
    private function applyThirdPartyServices(array $servicesConfig): void
    {
        foreach ($servicesConfig as $serviceName => $configs) {
            if (! is_array($configs)) {
                continue;
            }

            foreach ($configs as $key => $value) {
                /** @var string|null $value */
                if ($value !== null && $value !== '') {
                    config()->set("services.{$serviceName}.{$key}", $value);
                }
            }
        }
    }
}
