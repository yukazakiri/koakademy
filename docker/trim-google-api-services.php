<?php

declare(strict_types=1);

/**
 * Trim google/apiclient-services down to the API services the application
 * actually uses, then verify the surviving classes still autoload.
 *
 * The package ships 336 generated service classes (~138 MB) but KoAkademy only
 * talks to the Google Calendar API. Composer's classmap-authoritative
 * autoloader maps every one of those classes to a file, so the unused ones
 * stay resident in the runtime image for no reason.
 *
 * Usage:
 *   php docker/trim-google-api-services.php trim   <source dir>...
 *   php docker/trim-google-api-services.php verify <source dir>...
 *
 * The trim step runs before `composer dump-autoload`, so the regenerated
 * classmap only contains the classes that are still on disk. The verify step
 * runs afterwards and fails the build when a class the application references
 * is missing, so a new Google API usage can never silently break at runtime.
 */
$mode = $argv[1] ?? 'trim';
$sourceDirectories = array_slice($argv, 2);

if (! in_array($mode, ['trim', 'verify'], true) || $sourceDirectories === []) {
    fwrite(STDERR, "Usage: php docker/trim-google-api-services.php <trim|verify> <source dir>...\n");
    exit(1);
}

$vendorDirectory = dirname(__DIR__).'/vendor';
$servicesSource = $vendorDirectory.'/google/apiclient-services/src';

/**
 * Collect the Google API classes the application references.
 *
 * Both the modern (Google\Service\Calendar) and the legacy
 * (Google_Service_Calendar) spellings are accepted so the check keeps
 * working whichever style a module uses. Legacy names are stored in their
 * modern form, because the package's own autoloader maps one onto the other.
 *
 * @param  array<int, string>  $directories
 * @return array<int, string>
 */
function referencedServiceClasses(array $directories): array
{
    $classes = [];

    foreach ($directories as $directory) {
        if (! is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            if (preg_match_all('/Google\\\\Service\\\\([A-Za-z0-9_\\\\]+)/', $contents, $matches) > 0) {
                foreach ($matches[1] as $class) {
                    $classes['Google\\Service\\'.str_replace('\\\\', '\\', $class)] = true;
                }
            }

            if (preg_match_all('/Google_Service_([A-Za-z0-9_]+)/', $contents, $matches) > 0) {
                foreach ($matches[1] as $class) {
                    $classes['Google\\Service\\'.str_replace('_', '\\', $class)] = true;
                }
            }
        }
    }

    $classes = array_keys($classes);
    sort($classes);

    return $classes;
}

/**
 * The top level service a class belongs to. Google\Service\Calendar\Event
 * lives in the Calendar service directory, and the package maps
 * Google\Service\X onto the src/X.php file and the src/X/ directory.
 */
function serviceOf(string $class): string
{
    $relative = mb_substr($class, mb_strlen('Google\\Service\\'));
    $parts = explode('\\', $relative);

    return $parts[0];
}

if ($mode === 'trim') {
    if (! is_dir($servicesSource)) {
        fwrite(STDERR, "google/apiclient-services is not installed, nothing to trim.\n");
        exit(0);
    }

    $keep = [];

    foreach (referencedServiceClasses($sourceDirectories) as $class) {
        $keep[serviceOf($class)] = true;
    }

    $removed = 0;

    foreach (scandir($servicesSource) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        // A service is stored as both src/<Service>.php and src/<Service>/.
        if (isset($keep[pathinfo($entry, PATHINFO_FILENAME)])) {
            continue;
        }

        $path = $servicesSource.'/'.$entry;

        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }

            @rmdir($path);
        } else {
            @unlink($path);
        }

        $removed++;
    }

    printf(
        "Trimmed google/apiclient-services to %d service(s) (%s), removed %d entries.\n",
        count($keep),
        $keep === [] ? 'none' : implode(', ', array_keys($keep)),
        $removed
    );

    exit(0);
}

require $vendorDirectory.'/autoload.php';

$referenced = referencedServiceClasses($sourceDirectories);
$missing = [];

foreach ($referenced as $class) {
    if (! class_exists($class)) {
        $missing[] = $class;
    }
}

if ($missing !== []) {
    fwrite(STDERR, 'Google API classes referenced by the application are missing: ');
    fwrite(STDERR, implode(', ', $missing));
    fwrite(STDERR, "\n");
    exit(1);
}

printf("Google API classes OK: %d class(es) resolve.\n", count($referenced));
