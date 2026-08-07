<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Plugins\PennantManager\Support\FeatureValue;
use App\Filament\Plugins\PennantManager\Support\ScopeConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

final class PennantActivateScheduledFeatures extends Command
{
    protected $signature = 'pennant:activate-scheduled';

    protected $description = 'Activate any features whose scheduled activation time has passed';

    public function handle(): void
    {
        $features = DB::table('features')
            ->where('scope', '__laravel_null')
            ->whereRaw("value LIKE '{%'")
            ->get();

        $activated = 0;

        foreach ($features as $feature) {
            $data = FeatureValue::decode($feature->value);

            if (empty($data['activate_at']) || ($data['enabled'] ?? false)) {
                continue;
            }

            if (now()->gte(Carbon::parse($data['activate_at']))) {
                $data['enabled'] = true;

                if (! empty($data['percentage']) && ! empty($data['scope_type'])) {
                    $modelClass = ScopeConfig::modelClass($data['scope_type']);

                    $existingIds = DB::table('features')
                        ->where('name', $feature->name)
                        ->where('scope', '!=', '__laravel_null')
                        ->get()
                        ->map(fn ($f) => explode('|', $f->scope)[1] ?? null)
                        ->filter()
                        ->toArray();

                    $ids = $modelClass::whereNotIn('id', $existingIds)
                        ->pluck('id')
                        ->shuffle()
                        ->take((int) ceil($modelClass::count() * $data['percentage'] / 100));

                    foreach ($ids as $id) {
                        DB::table('features')->updateOrInsert(
                            ['name' => $feature->name, 'scope' => $modelClass.'|'.$id],
                            ['value' => FeatureValue::encode(enabled: true), 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                }

                DB::table('features')
                    ->where('id', $feature->id)
                    ->update(['value' => json_encode($data), 'updated_at' => now()]);

                $activated++;
            }
        }

        if ($activated > 0) {
            Feature::flushCache();
        }

        $this->info("Activated {$activated} scheduled feature(s).");
    }
}
