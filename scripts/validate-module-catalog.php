<?php

declare(strict_types=1);

use App\Modules\Contracts\RegistryEntry;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $path = $argv[1] ?? null;
    if (! is_string($path) || ! is_file($path)) {
        throw new RuntimeException('Usage: php scripts/validate-module-catalog.php /path/to/registry.json');
    }
    $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($payload['schema'] ?? null) !== 1 || ! is_array($payload['modules'] ?? null) || ! array_is_list($payload['modules'])) {
        throw new RuntimeException('A schema 1 module catalog is required.');
    }
    $identities = [];
    foreach ($payload['modules'] as $index => $data) {
        if (! is_array($data)) {
            throw new RuntimeException('Each module must be an object.');
        }
        $entry = RegistryEntry::fromArray($data);
        foreach (array_filter([$entry->manifest->name, $entry->manifest->alias, $entry->manifest->composerPackage]) as $identity) {
            $key = mb_strtolower($identity);
            if (isset($identities[$key]) && $identities[$key] !== $index) {
                throw new RuntimeException("Duplicate catalog identity [{$identity}].");
            }
            $identities[$key] = $index;
        }
    }
    fwrite(STDOUT, 'Catalog contract accepted by the Koakademy application parser.'.PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
