<?php

declare(strict_types=1);

/**
 * Cross-platform development log watcher invoked by `composer dev`.
 *
 * Uses Laravel Pail when ext-pcntl is available (Linux/macOS), and falls back
 * to a pure-PHP file tail on platforms without pcntl (native Windows) so
 * `composer dev` never exits immediately on startup.
 */
$artisan = dirname(__DIR__).DIRECTORY_SEPARATOR.'artisan';

if (extension_loaded('pcntl')) {
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($artisan).' pail --timeout=0';
    passthru($command, $exitCode);
    exit((int) $exitCode);
}

$logPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'laravel.log';
$logDir = dirname($logPath);

if (! is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

if (! file_exists($logPath)) {
    touch($logPath);
}

fwrite(STDOUT, "ext-pcntl is unavailable on this platform; streaming {$logPath}".PHP_EOL);

$offset = filesize($logPath) ?: 0;

while (true) {
    clearstatcache(true, $logPath);
    $size = filesize($logPath);

    if ($size === false) {
        usleep(250_000);

        continue;
    }

    if ($size < $offset) {
        $offset = 0;
    }

    if ($size > $offset) {
        $handle = fopen($logPath, 'rb');

        if ($handle !== false) {
            fseek($handle, $offset);
            $chunk = fread($handle, $size - $offset);
            fclose($handle);

            if (is_string($chunk) && $chunk !== '') {
                fwrite(STDOUT, $chunk);
            }

            $offset = $size;
        }
    }

    usleep(250_000);
}
