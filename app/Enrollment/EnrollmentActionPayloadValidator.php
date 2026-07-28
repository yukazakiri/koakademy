<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Enrollment\Exceptions\EnrollmentTransitionException;

final readonly class EnrollmentActionPayloadValidator
{
    /** @param array<string, mixed> $schema */
    public function validate(mixed $value, array $schema, string $path = 'payload'): void
    {
        $type = $schema['type'] ?? null;
        if (is_string($type) && ! $this->matchesType($value, $type)) {
            throw new EnrollmentTransitionException("Enrollment action {$path} must be {$type}.");
        }

        if (is_array($schema['enum'] ?? null) && ! in_array($value, $schema['enum'], true)) {
            throw new EnrollmentTransitionException("Enrollment action {$path} contains an unsupported value.");
        }
        if (is_numeric($value)) {
            if (is_numeric($schema['minimum'] ?? null) && (float) $value < (float) $schema['minimum']) {
                throw new EnrollmentTransitionException("Enrollment action {$path} is below its minimum value.");
            }
            if (is_numeric($schema['maximum'] ?? null) && (float) $value > (float) $schema['maximum']) {
                throw new EnrollmentTransitionException("Enrollment action {$path} exceeds its maximum value.");
            }
        }

        if ($type === 'object' && is_array($value)) {
            foreach ($schema['required'] ?? [] as $required) {
                if (is_string($required) && ! array_key_exists($required, $value)) {
                    throw new EnrollmentTransitionException("Enrollment action {$path}.{$required} is required.");
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $propertySchema) {
                if (! array_key_exists($key, $value) || ! is_array($propertySchema)) {
                    continue;
                }
                $this->validate($value[$key], $propertySchema, "{$path}.{$key}");
            }
        }

        if ($type === 'array' && is_array($value) && is_array($schema['items'] ?? null)) {
            foreach (array_values($value) as $index => $item) {
                $this->validate($item, $schema['items'], "{$path}.{$index}");
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value),
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            default => true,
        };
    }
}
