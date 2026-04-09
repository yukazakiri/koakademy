<?php

declare(strict_types=1);

use Carbon\Carbon;

if (! function_exists('format_timestamp')) {
    /**
     * Format a timestamp for frontend consumption with correct timezone handling.
     *
     * This function corrects the timezone labeling issue where the database stores
     * Manila time with a UTC offset label. It shifts the timezone to ensure the
     * frontend displays the correct relative time.
     *
     * @param  mixed  $timestamp
     */
    function format_timestamp($timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        if (is_string($timestamp)) {
            try {
                $timestamp = Carbon::parse($timestamp);
            } catch (Exception) {
                return null;
            }
        }

        if (! $timestamp instanceof Carbon) {
            return null;
        }

        return $timestamp->shiftTimezone(config('app.timezone'))->toIso8601String();
    }
}

if (! function_exists('format_timestamp_now')) {
    /**
     * Get the current timestamp formatted for frontend consumption.
     *
     * Use this when generating new timestamps (like now()) that are already
     * in the correct timezone, so we don't need to shift.
     */
    function format_timestamp_now(): string
    {
        return now()->toIso8601String();
    }
}
