<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Driver-aware SQL fragments for statements that have no portable spelling.
 *
 * The application ships with SQLite as its default connection, while
 * installations may also run PostgreSQL, MySQL, MariaDB or SQL Server.
 * PostgreSQL accepts `ilike` and `::type` casts, MySQL needs `SIGNED`/`CHAR`,
 * and SQLite only understands `CAST(... AS TEXT|INTEGER)`. Raw SQL that has to
 * branch on the driver lives here instead of being duplicated per call site.
 */
final class SqlDialect
{
    /**
     * The case-insensitive LIKE operator for the connection's driver.
     *
     * Use this only in raw fragments; regular query builder calls can use
     * whereLike(), which performs the same driver switch internally.
     */
    public static function likeOperator(Builder|QueryBuilder $query): string
    {
        return self::driver($query) === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Render an expression as text so it can be compared with a string column.
     */
    public static function textCast(Builder|QueryBuilder $query, string $expression): string
    {
        return match (self::driver($query)) {
            'pgsql' => sprintf('(%s)::text', $expression),
            'mysql', 'mariadb' => sprintf('CAST(%s AS CHAR)', $expression),
            'sqlsrv' => sprintf('CAST(%s AS NVARCHAR(MAX))', $expression),
            default => sprintf('CAST(%s AS TEXT)', $expression),
        };
    }

    /**
     * Render an expression as an integer so it can be compared with an id.
     */
    public static function integerCast(Builder|QueryBuilder $query, string $expression): string
    {
        return match (self::driver($query)) {
            'pgsql' => sprintf('(%s)::bigint', $expression),
            'mysql', 'mariadb' => sprintf('CAST(%s AS SIGNED)', $expression),
            'sqlsrv' => sprintf('CAST(%s AS BIGINT)', $expression),
            default => sprintf('CAST(%s AS INTEGER)', $expression),
        };
    }

    private static function driver(Builder|QueryBuilder $query): string
    {
        return $query->getConnection()->getDriverName();
    }
}
