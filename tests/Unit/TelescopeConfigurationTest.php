<?php

declare(strict_types=1);

it('uses the application database connection for Telescope storage by default', function (): void {
    expect(config('telescope.storage.database.connection'))
        ->toBe(config('database.default'));
});
