<?php

declare(strict_types=1);

namespace App\Scout;

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
 * Registered for the `[REDACTED]` driver in AppServiceProvider.
 */
final class TermDatabaseEngine extends DatabaseEngine
{
    /**
     * {@inheritDoc}
     */
    protected function addTextSearchConstraints($query, Builder $builder, array $columns, array $prefixColumns = [], array $fullTextColumns = [])
    {
        $terms = $this->searchTerms($builder->query);

        if (count($terms) < 2) {
            return parent::addTextSearchConstraints($query, $builder, $columns, $prefixColumns, $fullTextColumns);
        }

        $originalQuery = $builder->query;

        try {
            return $query->where(function ($termGroup) use ($builder, $columns, $prefixColumns, $fullTextColumns, $terms): void {
                foreach ($terms as $term) {
                    $builder->query = $term;

                    parent::addTextSearchConstraints($termGroup, $builder, $columns, $prefixColumns, $fullTextColumns);
                }
            });
        } finally {
            $builder->query = $originalQuery;
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
