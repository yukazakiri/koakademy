<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use InvalidArgumentException;

final readonly class ModuleManifest
{
    /**
     * @param  array{core?: string, php?: string, modules?: array<string, string>}  $requires
     * @param  array<string, string>  $compatibility
     * @param  list<string>  $providers
     */
    public function __construct(
        public string $name,
        public string $alias,
        public string $version,
        public string $description,
        public string $author,
        public string $license,
        public array $requires,
        public array $compatibility,
        public array $providers,
        public ?string $composerPackage = null,
        public ?string $repository = null,
        public ?string $homepage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $name = self::requiredString($data, 'name');
        $alias = self::requiredString($data, 'alias');
        $version = self::requiredString($data, 'version');
        $description = self::optionalString($data, 'description') ?? '';
        $author = self::optionalString($data, 'author') ?? '';
        $license = self::requiredString($data, 'license');
        $providers = self::stringList($data['providers'] ?? null, 'providers');
        $requires = self::requirements($data['requires'] ?? []);
        $compatibility = self::stringMap($data['compatibility'] ?? [], 'compatibility');

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
            throw new InvalidArgumentException("Module name [{$name}] is invalid.");
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $alias)) {
            throw new InvalidArgumentException("Module alias [{$alias}] is invalid.");
        }

        if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException("Module version [{$version}] must be semantic versioning.");
        }

        if ($providers === []) {
            throw new InvalidArgumentException("Module [{$name}] must declare at least one provider.");
        }

        $composerPackage = self::optionalString($data, 'composer_package');

        if ($composerPackage !== null && ! preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/', $composerPackage)) {
            throw new InvalidArgumentException("Module Composer package [{$composerPackage}] is invalid.");
        }

        return new self(
            name: $name,
            alias: $alias,
            version: $version,
            description: $description,
            author: $author,
            license: $license,
            requires: $requires,
            compatibility: $compatibility,
            providers: $providers,
            composerPackage: $composerPackage,
            repository: self::optionalString($data, 'repository'),
            homepage: self::optionalString($data, 'homepage'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'license' => $this->license,
            'requires' => $this->requires,
            'compatibility' => $this->compatibility,
            'providers' => $this->providers,
            ...array_filter([
                'composer_package' => $this->composerPackage,
                'repository' => $this->repository,
                'homepage' => $this->homepage,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException("Module manifest field [{$key}] must be a non-empty string.");
        }

        return mb_trim($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException("Module manifest field [{$key}] must be a string.");
        }

        return mb_trim($value);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $key): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("Module manifest field [{$key}] must be a list of strings.");
        }

        $values = [];

        foreach ($value as $item) {
            if (! is_string($item) || mb_trim($item) === '') {
                throw new InvalidArgumentException("Module manifest field [{$key}] must contain only non-empty strings.");
            }

            $values[] = mb_trim($item);
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value, string $key): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("Module manifest field [{$key}] must be an object of version constraints.");
        }

        $values = [];

        foreach ($value as $mapKey => $mapValue) {
            if (! is_string($mapKey) || $mapKey === '' || ! is_string($mapValue) || mb_trim($mapValue) === '') {
                throw new InvalidArgumentException("Module manifest field [{$key}] must contain string keys and values.");
            }

            $values[$mapKey] = mb_trim($mapValue);
        }

        return $values;
    }

    /**
     * @return array{core?: string, php?: string, modules?: array<string, string>}
     */
    private static function requirements(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Module manifest field [requires] must be an object.');
        }

        $requirements = [];

        foreach (['core', 'php'] as $key) {
            if (array_key_exists($key, $value)) {
                if (! is_string($value[$key]) || mb_trim($value[$key]) === '') {
                    throw new InvalidArgumentException("Module manifest requirement [{$key}] must be a string.");
                }

                $requirements[$key] = mb_trim($value[$key]);
            }
        }

        if (array_key_exists('modules', $value)) {
            $requirements['modules'] = self::stringMap($value['modules'], 'requires.modules');
        }

        return $requirements;
    }
}
