<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bulk Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of records a bulk operation processes per chunk. Every bulk
    | endpoint fetches its rows in one query, writes them with an upsert and
    | wraps the whole thing in a transaction; the chunk size keeps the number
    | of bound parameters per statement within what the database accepts.
    |
    */

    'chunk_size' => (int) env('BULK_OPERATION_CHUNK_SIZE', 500),
];
