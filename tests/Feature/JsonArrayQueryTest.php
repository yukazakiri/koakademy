<?php

declare(strict_types=1);

use App\Support\Database\JsonArrayQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The classes table keeps its program codes and subject ids in JSON arrays, and
 * every driver spells the query for reading a JSON array differently. These
 * tests pin the SQL each driver gets, so a change to the helper cannot quietly
 * break the database production runs on.
 */
function jsonArrayQueryFor(string $driver): Builder
{
    $connection = DB::connection($driver);

    return $connection->table('classes');
}

it('reads a JSON array of ids on PostgreSQL', function (): void {
    $query = jsonArrayQueryFor('pgsql');

    JsonArrayQuery::whereContainsInt($query, 'classes.course_codes', 7);

    $sql = $query->toSql();

    expect($sql)
        ->toContain('jsonb_array_elements_text')
        ->toContain('jsonb_typeof')
        ->toContain('classes.course_codes')
        ->not->toContain('json_each');

    expect($query->getBindings())->toBe([7]);
});

it('reads a JSON array of ids on SQLite', function (): void {
    $query = jsonArrayQueryFor('sqlite');

    JsonArrayQuery::whereContainsInt($query, 'classes.course_codes', 7);

    $sql = $query->toSql();

    expect($sql)
        ->toContain('json_each')
        ->toContain('json_valid')
        ->not->toContain('jsonb_array_elements_text');

    expect($query->getBindings())->toBe([7]);
});

it('joins the elements of a JSON array against another table', function (): void {
    $query = jsonArrayQueryFor('pgsql');

    JsonArrayQuery::whereElementMatches(
        $query,
        'classes.subject_ids',
        'subject.code ILIKE ?',
        ['%IT%'],
        'or',
        'JOIN subject ON subject.id = {element}',
    );

    $sql = $query->toSql();

    expect($sql)
        ->toContain('JOIN subject ON subject.id = json_element::bigint')
        ->toContain('subject.code ILIKE ?');

    expect($query->getBindings())->toBe(['%IT%']);
});

it('replaces the element placeholder with the driver expression', function (): void {
    $sqlite = jsonArrayQueryFor('sqlite');
    JsonArrayQuery::whereElementMatches($sqlite, 'classes.course_codes', 'courses.code = {element}');

    expect($sqlite->toSql())->toContain('courses.code = CAST(json_element.value AS INTEGER)');
});

it('guards the JSON array against a column that holds no array', function (): void {
    $postgres = jsonArrayQueryFor('pgsql');
    JsonArrayQuery::whereContainsInt($postgres, 'classes.course_codes', 1);

    $sqlite = jsonArrayQueryFor('sqlite');
    JsonArrayQuery::whereContainsInt($sqlite, 'classes.course_codes', 1);

    // A null column, a JSON scalar or a text column must not raise an error,
    // they must simply match nothing.
    expect($postgres->toSql())->toContain("ELSE '[]'::jsonb");
    expect($sqlite->toSql())->toContain('json_array()');
});
