<?php

declare(strict_types=1);

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A safe enum cast that uses tryFrom() instead of from(), so unknown/legacy
 * backing values stored in the database return null rather than throwing ValueError.
 *
 * Usage in model casts():
 *   'status' => SafeEnumCast::of(EnrollStat::class),
 *
 * @template TEnum of \BackedEnum
 */
final readonly class SafeEnumCast implements CastsAttributes
{
    /**
     * @param  class-string<TEnum>  $enumClass
     */
    public function __construct(private string $enumClass) {}

    /**
     * @param  class-string<TEnum>  $enumClass
     */
    public static function of(string $enumClass): string
    {
        return self::class.':'.$enumClass;
    }

    /**
     * @return TEnum|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return ($this->enumClass)::tryFrom((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
