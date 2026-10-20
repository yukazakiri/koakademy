<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

/**
 * Grouped aggregate helpers for the dashboard widgets.
 *
 * The widgets used to run one COUNT query per enum value, which turned a
 * single dashboard into dozens of full scans over the `students` table. The
 * helpers below collapse those into one grouped statement each, and the
 * composite indexes on `students` make the grouped scans cheap.
 *
 * Every helper takes the model class it aggregates, the filters it applies
 * and the column it groups by, so a widget never has to assemble a query
 * itself.
 */
final class DashboardStatisticsService
{
    /**
     * Count the rows of a model grouped by a column.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $filters
     * @param  array<int, mixed>  $only  when given, only these values are counted
     * @return array<array-key, int> the grouped value mapped to its count
     */
    public function countsByColumn(string $model, string $column, array $filters = [], array $only = []): array
    {
        $query = $this->query($model, $filters);

        if ($only !== []) {
            $query->whereIn($column, $only);
        }

        return $this->bucketCounts(
            $query
                ->addSelect($this->raw("{$column} as bucket, count(*) as aggregate"))
                ->groupBy($column)
                ->get()
        );
    }

    /**
     * Count the rows of a model matching a set of conditions in a single
     * statement.
     *
     * Each condition is a `column`/`operator`/`value` triplet evaluated with a
     * CASE expression, so several counters never need several round trips.
     * An array of values is expanded into an `in (...)` comparison.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, array{0: string, 1: string, 2: mixed}>  $conditions
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $filters
     * @return array<string, int>
     */
    public function countsByConditions(string $model, array $conditions, array $filters = []): array
    {
        $selects = [];
        $bindings = [];

        foreach ($conditions as $alias => [$column, $operator, $value]) {
            if (is_array($value)) {
                $placeholders = implode(', ', array_fill(0, max(1, count($value)), '?'));

                $selects[] = "sum(case when {$column} {$operator} ({$placeholders}) then 1 else 0 end) as {$alias}";

                foreach ($value as $item) {
                    $bindings[] = $item;
                }

                continue;
            }

            $selects[] = "sum(case when {$column} {$operator} ? then 1 else 0 end) as {$alias}";
            $bindings[] = $value;
        }

        $row = $this->query($model, $filters)
            ->addSelect($this->raw('count(*) as total'))
            ->addSelect($this->raw(implode(', ', $selects)))
            ->addBinding($bindings, 'select')
            ->first();

        $attributes = $row?->getAttributes() ?? [];

        $counts = [];

        foreach (array_keys($conditions) as $alias) {
            $counts[$alias] = $this->toInt($attributes[$alias] ?? 0);
        }

        $counts['total'] = $this->toInt($attributes['total'] ?? 0);

        return $counts;
    }

    /**
     * Count the rows of a model per day for a list of day labels, optionally
     * grouped by another column.
     *
     * The day label is compared with `date(created_at)`, exactly like the
     * `whereDate()` calls this replaces, so the buckets stay identical.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $labels  `Y-m-d` labels, oldest first
     * @param  string|null  $column  an optional second grouping column
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $filters
     * @return array<string, array<array-key, int>> the day label mapped to its counts
     */
    public function dailyCounts(string $model, array $labels, ?string $column = null, array $filters = []): array
    {
        if ($labels === []) {
            return [];
        }

        $case = 'case';
        $bindings = [];

        foreach (array_values($labels) as $index => $label) {
            $case .= ' when date(created_at) = ? then '.$index;
            $bindings[] = $label;
        }

        $case .= ' else -1 end';

        $query = $this->query($model, $filters)
            ->whereBetween('created_at', [
                $labels[array_key_first($labels)].' 00:00:00',
                $labels[array_key_last($labels)].' 23:59:59',
            ]);

        if ($column !== null) {
            $query
                ->addSelect($this->raw("{$case} as bucket, {$column} as bucket_value, count(*) as aggregate"))
                ->addBinding($bindings, 'select')
                ->groupBy($this->raw("{$case}, {$column}"))
                ->addBinding($bindings, 'groupBy');
        } else {
            $query
                ->addSelect($this->raw("{$case} as bucket, count(*) as aggregate"))
                ->addBinding($bindings, 'select')
                ->groupBy($this->raw($case))
                ->addBinding($bindings, 'groupBy');
        }

        $counts = [];

        foreach ($query->get() as $row) {
            $attributes = $row->getAttributes();

            $label = $labels[$this->toInt($attributes['bucket'] ?? -1)] ?? null;

            if ($label === null) {
                continue;
            }

            $bucket = $column === null ? 'total' : $this->toString($attributes['bucket_value'] ?? '');

            $counts[$label][$bucket] = ($counts[$label][$bucket] ?? 0) + $this->toInt($attributes['aggregate'] ?? 0);
        }

        return $counts;
    }

    /**
     * Count the rows of a model per period, optionally grouped by another
     * column.
     *
     * The period is resolved with a CASE expression over the given ranges, so
     * a chart covering a year of months still needs a single statement.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array{start: DateTimeInterface|string, end: DateTimeInterface|string}>  $periods
     * @param  string  $table  the table the timestamp column belongs to
     * @param  string|null  $column  an optional second grouping column
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $filters
     * @param  Closure(Builder<Model>): void|null  $scope  extra query preparation
     * @return array<int, array<array-key, int>> the period index mapped to its counts
     */
    public function countsByPeriods(
        string $model,
        array $periods,
        string $table,
        string $timestampColumn,
        ?string $column = null,
        array $filters = [],
        ?Closure $scope = null,
    ): array {
        if ($periods === []) {
            return [];
        }

        $case = 'case';
        $bindings = [];

        foreach (array_values($periods) as $index => $period) {
            $case .= " when {$table}.{$timestampColumn} between ? and ? then {$index}";
            $bindings[] = $period['start'];
            $bindings[] = $period['end'];
        }

        $case .= ' else -1 end';

        $query = $this->query($model, $filters);

        if ($scope !== null) {
            $scope($query);
        }

        // Keep the scan bounded to the periods that are actually charted.
        $query->whereBetween(
            "{$table}.{$timestampColumn}",
            [
                $periods[array_key_first($periods)]['start'],
                $periods[array_key_last($periods)]['end'],
            ],
        );

        if ($column !== null) {
            $query
                ->addSelect($this->raw("{$case} as bucket, {$column} as bucket_value, count(*) as aggregate"))
                ->addBinding($bindings, 'select')
                ->groupBy($this->raw("{$case}, {$column}"))
                ->addBinding($bindings, 'groupBy');
        } else {
            $query
                ->addSelect($this->raw("{$case} as bucket, count(*) as aggregate"))
                ->addBinding($bindings, 'select')
                ->groupBy($this->raw($case))
                ->addBinding($bindings, 'groupBy');
        }

        $counts = [];

        foreach ($query->get() as $row) {
            $attributes = $row->getAttributes();

            $index = $this->toInt($attributes['bucket'] ?? -1);

            if ($index < 0 || ! array_key_exists($index, $periods)) {
                continue;
            }

            $bucket = $column === null ? 'total' : $this->toString($attributes['bucket_value'] ?? '');

            $counts[$index][$bucket] = ($counts[$index][$bucket] ?? 0) + $this->toInt($attributes['aggregate'] ?? 0);
        }

        return $counts;
    }

    /**
     * A raw expression for a grouped aggregate.
     *
     * The expression is assembled at runtime, so it cannot be a literal
     * string; the column names it is built from are developer provided.
     *
     * @return Expression<literal-string>
     */
    private function raw(string $expression): Expression
    {
        return new Expression($expression);
    }

    /**
     * The query of a model with the given conditions applied.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $filters
     * @return Builder<Model>
     */
    private function query(string $model, array $filters): Builder
    {
        $query = $model::query();

        foreach ($filters as [$column, $operator, $value]) {
            if (is_array($value)) {
                $query->whereIn($column, $value);

                continue;
            }

            $query->where($column, $operator, $value);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return array<array-key, int>
     */
    private function bucketCounts(Collection $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $attributes = $row->getAttributes();

            $bucket = $this->toString($attributes['bucket'] ?? '');

            $counts[$bucket] = ($counts[$bucket] ?? 0) + $this->toInt($attributes['aggregate'] ?? 0);
        }

        return $counts;
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
