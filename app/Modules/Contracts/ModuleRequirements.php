<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use Composer\Semver\VersionParser;
use InvalidArgumentException;
use UnexpectedValueException;

final class ModuleRequirements
{
    /**
     * @return array{core?: string, php?: string, modules?: array<string, string>}
     */
    public static function fromArray(mixed $data): array
    {
        if (! is_array($data)) {
            throw new InvalidArgumentException('Module requirements must be an object.');
        }

        $requirements = [];
        foreach ($data as $key => $value) {
            if ($key === 'modules') {
                if (! is_array($value)) {
                    throw new InvalidArgumentException('Module requirements.modules must be an object.');
                }
                $requirements['modules'] = [];
                foreach ($value as $name => $constraint) {
                    if (! is_string($name) || ! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
                        throw new InvalidArgumentException('Module dependency names must be valid module identifiers.');
                    }
                    $requirements['modules'][$name] = self::constraint($constraint);
                }
            } elseif ($key === 'core' || $key === 'php') {
                $requirements[$key] = self::constraint($value);
            } else {
                throw new InvalidArgumentException('Unknown module requirement.');
            }
        }

        return $requirements;
    }

    public static function constraint(mixed $value): string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException('Module version constraints must be non-empty strings.');
        }
        $constraint = mb_trim($value);
        try {
            (new VersionParser)->parseConstraints($constraint);
        } catch (UnexpectedValueException $exception) {
            throw new InvalidArgumentException("Invalid module version constraint [{$constraint}].", previous: $exception);
        }

        return $constraint;
    }
}
