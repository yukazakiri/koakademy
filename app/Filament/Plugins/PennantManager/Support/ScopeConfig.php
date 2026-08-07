<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Support;

final class ScopeConfig
{
    public static function modelClass(string $type): ?string
    {
        $entry = config("pennant-manager.scope_models.{$type}");

        if (is_string($entry)) {
            return $entry;
        }

        if (is_array($entry)) {
            return $entry['model'] ?? null;
        }

        return null;
    }

    public static function searchColumn(string $type): string
    {
        $entry = config("pennant-manager.scope_models.{$type}");

        if (is_array($entry) && isset($entry['search_column'])) {
            return $entry['search_column'];
        }

        if (is_string($entry)) {
            return config('pennant-manager.scope_search_column', 'email');
        }

        return 'id';
    }

    public static function labelColumn(string $type): string
    {
        $entry = config("pennant-manager.scope_models.{$type}");

        if (is_array($entry) && isset($entry['label_column'])) {
            return $entry['label_column'];
        }

        // fall back to search column so the list still shows something readable
        return self::searchColumn($type);
    }

    public static function segmentColumns(string $type): array
    {
        $entry = config("pennant-manager.scope_models.{$type}");

        if (is_array($entry) && isset($entry['segment_columns'])) {
            return $entry['segment_columns'];
        }

        return [];
    }

    public static function modelOptions(): array
    {
        return array_combine(
            array_keys(config('pennant-manager.scope_models', [])),
            array_keys(config('pennant-manager.scope_models', []))
        );
    }
}
