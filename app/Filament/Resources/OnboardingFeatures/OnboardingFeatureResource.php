<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures;

use App\Features\Onboarding\FeatureClassRegistry;
use App\Filament\Resources\OnboardingFeatures\Pages\CreateOnboardingFeature;
use App\Filament\Resources\OnboardingFeatures\Pages\EditOnboardingFeature;
use App\Filament\Resources\OnboardingFeatures\Pages\ListOnboardingFeatures;
use App\Filament\Resources\OnboardingFeatures\Schemas\OnboardingFeatureForm;
use App\Filament\Resources\OnboardingFeatures\Tables\OnboardingFeaturesTable;
use App\Models\OnboardingFeature;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use UnitEnum;

/**
 * @extends \Filament\Resources\Resource<OnboardingFeature, ResourceConfiguration>
 */
final class OnboardingFeatureResource extends Resource
{
    protected static ?string $model = OnboardingFeature::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::Sparkles;

    protected static ?string $navigationLabel = 'Onboarding';

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getEloquentQuery()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return self::getEloquentQuery()->where('is_active', true)->count() > 0 ? 'success' : 'gray';
    }

    public static function form(Schema $schema): Schema
    {
        return OnboardingFeatureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnboardingFeaturesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnboardingFeatures::route('/'),
            'create' => CreateOnboardingFeature::route('/create'),
            'edit' => EditOnboardingFeature::route('/{record}/edit'),
        ];
    }

    /**
     * Activate a feature via its Pennant class.
     */
    public static function activateFeature(string $featureKey): void
    {
        $featureClass = FeatureClassRegistry::classForKey($featureKey);
        Feature::activateForEveryone($featureClass ?? $featureKey);
    }

    /**
     * Deactivate a feature via its Pennant class.
     */
    public static function deactivateFeature(string $featureKey): void
    {
        $featureClass = FeatureClassRegistry::classForKey($featureKey);
        Feature::deactivateForEveryone($featureClass ?? $featureKey);
    }

    /**
     * Count users with explicit overrides for a feature.
     */
    public static function getUserOverrideCount(string $featureClass): int
    {
        return DB::table('features')
            ->where('name', $featureClass)
            ->where('scope', 'not like', '%__laravel_null%')
            ->count();
    }

    /**
     * Check if a feature is force-activated for everyone (global override).
     */
    public static function isGloballyActivated(string $featureClass): bool
    {
        return DB::table('features')
            ->where('name', $featureClass)
            ->where('scope', '__laravel_null')
            ->where('value', 'true')
            ->exists();
    }
}
