<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Splits a list of identifiers into chunks a bulk operation can work on.
 *
 * Bulk endpoints read their rows with a single query and write them back with
 * an upsert. Both statements are bound parameter heavy, so the identifiers are
 * processed in chunks instead of one unbounded `whereIn`.
 */
final class BulkChunk
{
    /**
     * Number of records handled per chunk.
     *
     * @return int<1, max>
     */
    public static function size(): int
    {
        $size = config('bulk_operations.chunk_size', 500);

        return max(1, is_numeric($size) ? (int) $size : 500);
    }

    /**
     * Split the given identifiers into chunks.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, array<int, int>>
     */
    public static function of(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $normalized[] = (int) $id;
            }
        }

        return array_chunk(array_values(array_unique($normalized)), self::size());
    }
}
