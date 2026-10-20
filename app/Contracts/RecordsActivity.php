<?php

declare(strict_types=1);

namespace App\Contracts;

use Spatie\Activitylog\Support\LogOptions;

/**
 * A model that records its changes in the activity log.
 *
 * The methods are provided by the `LogsActivity` trait of the activity log
 * package; the contract only makes them discoverable to static analysis.
 */
interface RecordsActivity
{
    /**
     * The activity log options of the model.
     */
    public function getActivitylogOptions(): LogOptions;

    /**
     * The attributes of the model that are written to the activity log.
     *
     * @return array<int, string>
     */
    public function attributesToBeLogged(): array;
}
