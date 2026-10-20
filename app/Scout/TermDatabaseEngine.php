<?php

declare(strict_types=1);

namespace App\Scout;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;

/**
 * Database Scout engine that matches every search term instead of the raw query.
 *
 * Laravel's DatabaseEngine feeds the whole query string into a single LIKE, so
 * "alicia mercado" only matches a column that literally contains that phrase.
 * People search by typing several words, so each whitespace-separated term is
 * matched independently and every term has to match somewhere in the record.
 *
 * A model may also declare related records that are worth searching — a class is
 * normally found by its subject title, which lives on another table. Those columns
 * are matched through `searchableRelations()`.
 *
 * Registered for the `database` driver in AppServiceProvider.
 */
final class TermDatabaseEngine extends DatabaseEngine
{
    /**
     * {@inheritDoc}
     */
    protected function addTextSearchConstraints($query, Builder $builder, array $columns, array $prefixColumns = [], array $fullTextColumns = [])
    {
        $terms = $this->searchTerms($builder->query);

        if ($terms === []) {
            return parent::addTextSearchConstraints($query, $builder, $columns, $prefixColumns, $fullTextColumns);
        }

        $model = $builder->model;
        $originalQuery = $builder->query;

        try {
            return $query->where(function ($termGroup) use ($builder, $model, $columns, $prefixColumns, $fullTextColumns, $terms): void {
                foreach ($terms as $term) {
                    $builder->query = $term;

                    // Each term is one group: this record's own columns OR a
                    // related record the model declared as searchable.
                    $termGroup->where(function ($singleTerm) use ($builder, $model, $columns, $prefixColumns, $fullTextColumns, $term): void {
                        parent::addTextSearchConstraints($singleTerm, $builder, $columns, $prefixColumns, $fullTextColumns);

                        $this->addRelationConstraints($singleTerm, $model, $term);
                    });
                }
            });
        } finally {
            $builder->query = $originalQuery;
        }
    }

    /**
     * Match the term against the related columns a model opted into.
     */
    private function addRelationConstraints(EloquentBuilder $query, Model $model, string $term): void
    {
        if (! method_exists($model, 'searchableRelations')) {
            return;
        }

        /** @var array<string, list<string>> $relations */
        $relations = $model->searchableRelations();

        foreach ($relations as $relation => $columns) {
            $query->orWhereHas($relation, function (EloquentBuilder $related) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $related->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }
    }

    /**
     * Split a search query into the terms that must all be present in a record.
     *
     * Punctuation is treated as a separator so pasted names such as
     * "Mercado, Alicia" still match the individual name columns.
     *
     * @return list<string>
     */
    private function searchTerms(?string $query): array
    {
        $separated = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $query);

        $terms = preg_split('/\s+/u', mb_trim($separated)) ?: [];

        return array_values(array_filter($terms, static fn (string $term): bool => $term !== ''));
    }
}
