<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Database-agnostic predicates for JSON array columns that hold scalar ids.
 *
 * The classes table stores `course_codes` and `subject_ids` as JSON arrays of
 * integers. Laravel's whereJsonContains() compiles to `jsonb @> ?` on
 * PostgreSQL and to `json_each(...)` on SQLite, and the two forms accept
 * different value shapes, so the drivers are handled explicitly here.
 *
 * Every generated fragment guards against a column that holds SQL NULL, a JSON
 * scalar, or text that is not valid JSON — otherwise the set-returning
 * function raises an error instead of simply matching nothing.
 */
final class JsonArrayQuery
{
    /**
     * Match rows whose JSON array column holds the given integer.
     */
    public static function whereContainsInt(Builder|QueryBuilder $query, string $column, int $value, string $boolean = 'and'): void
    {
        [$from, $element] = self::elements($query, $column);

        $query->whereRaw(
            sprintf('EXISTS (SELECT 1 FROM %s WHERE %s = ?)', $from, $element),
            [$value],
            $boolean,
        );
    }

    /**
     * Match rows where at least one element of the JSON array column satisfies
     * a condition.
     *
     * `{element}` inside $condition and $join is replaced by the
     * driver-specific expression that yields the element as an integer, so the
     * element can be joined against another table.
     *
     * @param  array<int, mixed>  $bindings
     */
    public static function whereElementMatches(
        Builder|QueryBuilder $query,
        string $column,
        string $condition,
        array $bindings = [],
        string $boolean = 'and',
        string $join = '',
    ): void {
        [$from, $element] = self::elements($query, $column);

        $sql = sprintf(
            'EXISTS (SELECT 1 FROM %s %s WHERE %s)',
            $from,
            str_replace('{element}', $element, $join),
            str_replace('{element}', $element, $condition),
        );

        $query->whereRaw($sql, $bindings, $boolean);
    }

    /**
     * The FROM clause that exposes the elements of a JSON array column, and the
     * expression that reads one element as an integer.
     *
     * @return array{0: string, 1: string}
     */
    private static function elements(Builder|QueryBuilder $query, string $column): array
    {
        $driver = $query->getConnection()->getDriverName();

        return match ($driver) {
            'pgsql' => [
                sprintf(
                    'jsonb_array_elements_text(CASE WHEN jsonb_typeof((%1$s)::jsonb) = \'array\' THEN (%1$s)::jsonb ELSE \'[]\'::jsonb END) AS json_element',
                    $column,
                ),
                'json_element::bigint',
            ],
            'mysql' => [
                sprintf("JSON_TABLE(%s, '$[*]' COLUMNS (value BIGINT PATH '$')) AS json_element", $column),
                'json_element.value',
            ],
            default => [
                sprintf(
                    'json_each(CASE WHEN json_valid(%1$s) THEN %1$s ELSE json_array() END) AS json_element',
                    $column,
                ),
                'CAST(json_element.value AS INTEGER)',
            ],
        };
    }
}
