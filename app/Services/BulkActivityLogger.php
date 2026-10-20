<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RecordsActivity;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\ActivityLogger;
use Spatie\Activitylog\Support\ChangeDetector;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records activity log entries for rows that were written in bulk.
 *
 * Eloquent only raises model events for `save()`/`delete()` calls, so a bulk
 * write (query builder update, upsert, mass delete) never reaches the Spatie
 * activity log listener. This service replays the bookkeeping the
 * `LogsActivity` trait performs, without the `fresh()` round trip the trait
 * makes for every single model it logs.
 */
final class BulkActivityLogger
{
    /**
     * Log an "updated" event for a model whose attributes were changed by a
     * bulk write.
     *
     * @param  Model&RecordsActivity  $model
     * @param  array<string, mixed>  $oldAttributes  raw attributes before the change
     * @param  array<string, mixed>  $newAttributes  raw attributes after the change
     */
    public function updated(Model $model, array $oldAttributes, array $newAttributes): void
    {
        $options = $model->getActivitylogOptions();

        $candidates = $options->logOnlyDirty
            ? $this->changedCandidates($model, $oldAttributes, $newAttributes)
            : $model->attributesToBeLogged();

        $new = $this->loggedValues($model, $candidates, $newAttributes);
        $old = $this->loggedValues($model, $candidates, $oldAttributes);

        if ($options->logOnlyDirty) {
            $filtered = ChangeDetector::filterDirty($new, $old);
            $new = $filtered['attributes'];
            $old = $filtered['old'];
        }

        if ($new === [] && $old === []) {
            return;
        }

        $this->write($model, $options, 'updated', [
            'attributes' => $new,
            'old' => $old,
        ]);
    }

    /**
     * Log a "deleted" event for a model that was removed by a bulk write.
     *
     * @param  Model&RecordsActivity  $model
     * @param  array<string, mixed>  $oldAttributes  raw attributes before the removal
     */
    public function deleted(Model $model, array $oldAttributes): void
    {
        $options = $model->getActivitylogOptions();

        $this->write($model, $options, 'deleted', [
            'old' => $this->loggedValues($model, $model->attributesToBeLogged(), $oldAttributes),
        ]);
    }

    /**
     * @param  Model&RecordsActivity  $model
     * @param  array<string, mixed>  $changes
     */
    private function write(Model $model, LogOptions $options, string $event, array $changes): void
    {
        $describe = $options->descriptionForEvent;

        $description = $describe !== null
            ? $describe($event)
            : $event;

        if (! is_string($description) || $description === '') {
            return;
        }

        $logName = $options->logName;

        app(ActivityLogger::class)
            ->useLog(is_string($logName) ? $logName : null)
            ->event($event)
            ->performedOn($model)
            ->withChanges($changes)
            ->log($description);
    }

    /**
     * Resolve the logged attributes of a model from a set of raw attributes.
     *
     * @param  Model&RecordsActivity  $model
     * @param  array<int, string>  $attributes
     * @param  array<string, mixed>  $rawAttributes
     * @return array<string, mixed>
     */
    private function loggedValues(Model $model, array $attributes, array $rawAttributes): array
    {
        if ($attributes === []) {
            return [];
        }

        $instance = $model->newInstance();
        $instance->setRawAttributes($rawAttributes);

        $values = [];

        foreach ($attributes as $attribute) {
            if (str_contains($attribute, '.') || str_contains($attribute, '->')) {
                continue;
            }

            $value = $instance->getAttribute($attribute);

            $values[$attribute] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return $values;
    }

    /**
     * The logged attributes whose raw value changed.
     *
     * An attribute whose raw value is identical cannot change once the model
     * casts are applied, so only those attributes need their value resolved.
     *
     * @param  Model&RecordsActivity  $model
     * @param  array<string, mixed>  $oldAttributes
     * @param  array<string, mixed>  $newAttributes
     * @return array<int, string>
     */
    private function changedCandidates(Model $model, array $oldAttributes, array $newAttributes): array
    {
        $candidates = [];

        foreach ($model->attributesToBeLogged() as $attribute) {
            if (! array_key_exists($attribute, $oldAttributes)
                || ($oldAttributes[$attribute] ?? null) !== ($newAttributes[$attribute] ?? null)) {
                $candidates[] = $attribute;
            }
        }

        return $candidates;
    }
}
