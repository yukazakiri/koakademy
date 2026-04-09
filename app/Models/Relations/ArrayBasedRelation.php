<?php

declare(strict_types=1);

namespace App\Models\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ArrayBasedRelation extends Relation
{
    /**
     * Cached results to avoid duplicate queries
     */
    private ?Collection $cachedResults = null;

    /**
     * Create a new array-based relation instance
     */
    public function __construct(Model $parent, Model|string $related, /**
     * The array column that contains the IDs
     */
        private readonly string $arrayColumn, /**
     * The related model's primary key name
     */
        private readonly string $relatedKey = 'id')
    {
        $relatedModel = $related instanceof Model ? $related : new $related();

        // Initialize properties first
        $this->related = $relatedModel;

        // Then call parent constructor
        parent::__construct($relatedModel->newQuery(), $parent);
    }

    /**
     * Forward calls to the underlying query builder or execute and call on results
     */
    public function __call($method, $parameters)
    {
        // If it's a collection method, execute the query first
        if (method_exists(Collection::class, $method)) {
            return $this->getResults()->{$method}(...$parameters);
        }

        // Otherwise, forward to the builder
        return $this->query->{$method}(...$parameters);
    }

    /**
     * Get the base query for the relationship
     */
    public function getBaseQuery()
    {
        return $this->related->newQuery();
    }

    /**
     * Add constraints to the query
     */
    public function addConstraints(): void
    {
        $ids = $this->getArrayIds();

        if ($ids !== []) {
            $this->query->whereIn($this->related->getTable().'.'.$this->relatedKey, $ids);
        }
    }

    /**
     * Add eager loading constraints to the query
     */
    public function addEagerConstraints(array $models): void
    {
        $allIds = collect();

        foreach ($models as $model) {
            $ids = $model->getAttribute($this->arrayColumn);
            if (is_array($ids)) {
                $allIds = $allIds->merge($ids);
            }
        }

        if ($allIds->isNotEmpty()) {
            $this->query->whereIn($this->related->getTable().'.'.$this->relatedKey, $allIds->unique()->toArray());
        }
    }

    /**
     * Match the eagerly loaded results to their parents
     */
    public function match(array $models, Collection $results, mixed $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $ids = $model->getAttribute($this->arrayColumn);
            $items = [];

            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if (isset($dictionary[$id])) {
                        $items[] = $dictionary[$id];
                    }
                }
            }

            $model->setRelation($relation, new Collection($items));
        }

        return $models;
    }

    /**
     * Initialize the relation on a set of models
     */
    public function initRelation(array $models, mixed $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection());
        }

        return $models;
    }

    /**
     * Get the results of the relationship (cached)
     */
    public function getResults()
    {
        // Return cached results if available
        if ($this->cachedResults instanceof Collection) {
            return $this->cachedResults;
        }

        $ids = $this->getArrayIds();

        if ($ids === []) {
            $this->cachedResults = new Collection();

            return $this->cachedResults;
        }

        $this->cachedResults = $this->getBaseQuery()
            ->whereIn($this->related->getTable().'.'.$this->relatedKey, $ids)
            ->get();

        return $this->cachedResults;
    }

    /**
     * Get the query for the relation
     */
    public function getQuery()
    {
        $ids = $this->getArrayIds();

        if ($ids === []) {
            return $this->getBaseQuery()->whereRaw('1=0'); // Return empty result
        }

        return $this->getBaseQuery()->whereIn($this->related->getTable().'.'.$this->relatedKey, $ids);
    }

    /**
     * Get the wrapped relationship query
     */
    public function getRelationQuery()
    {
        return $this->getQuery();
    }

    /**
     * Get the array of IDs from the parent model
     */
    protected function getArrayIds(): array
    {
        $ids = $this->parent->getAttribute($this->arrayColumn);

        return is_array($ids) ? array_filter($ids) : [];
    }

    /**
     * Build a dictionary of the related results
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->relatedKey)] = $result;
        }

        return $dictionary;
    }
}
