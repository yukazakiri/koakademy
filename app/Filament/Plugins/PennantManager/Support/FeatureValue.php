<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class FeatureValue
{
    public static function isActive(string $raw): bool
    {
        if (in_array($raw, ['true', '1'], true)) {
            return true;
        }

        if (in_array($raw, ['false', '0', '', 'null'], true)) {
            return false;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return (bool) json_decode($raw);
        }

        if (! empty($decoded['activate_at'])) {
            if (now()->lt(Carbon::parse($decoded['activate_at']))) {
                return false;
            }
        }

        return (bool) ($decoded['enabled'] ?? false);
    }

    public static function encode(
        bool $enabled,
        ?int $percentage = null,
        ?string $activateAt = null,
        mixed $richValue = null,
        ?array $meta = null,
    ): string {
        $data = ['enabled' => $enabled];

        if ($percentage !== null) {
            $data['percentage'] = $percentage;
        }

        if ($activateAt !== null) {
            $data['activate_at'] = $activateAt;
        }

        if ($richValue !== null) {
            $data['rich_value'] = $richValue;
        }

        if ($meta !== null) {
            $data['meta'] = $meta;
        }

        return json_encode($data);
    }

    public static function decode(string $raw): array
    {
        if ($raw === 'true') {
            return ['enabled' => true];
        }

        if ($raw === 'false') {
            return ['enabled' => false];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return ['enabled' => false];
        }

        return $decoded;
    }

    public static function resolve(string $raw): mixed
    {
        $data = self::decode($raw);

        return $data['rich_value'] ?? ($data['enabled'] ?? false);
    }

    public static function resolveValue(string $featureName, string $scope = '__laravel_null'): mixed
    {
        $raw = DB::table('features')
            ->where('name', $featureName)
            ->where('scope', $scope)
            ->value('value');

        if (! $raw) {
            return false;
        }

        if (! self::isActive($raw)) {
            return false;
        }

        return self::resolve($raw);
    }
}
