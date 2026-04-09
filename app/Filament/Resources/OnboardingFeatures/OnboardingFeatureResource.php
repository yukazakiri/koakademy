<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures;

use App\Filament\Resources\OnboardingFeatures\Pages\CreateOnboardingFeature;
use App\Filament\Resources\OnboardingFeatures\Pages\EditOnboardingFeature;
use App\Filament\Resources\OnboardingFeatures\Pages\ListOnboardingFeatures;
use App\Filament\Resources\OnboardingFeatures\Schemas\OnboardingFeatureForm;
use App\Filament\Resources\OnboardingFeatures\Tables\OnboardingFeaturesTable;
use App\Models\OnboardingFeature;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class OnboardingFeatureResource extends Resource
{
    protected static ?string $model = OnboardingFeature::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Onboarding';

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
}
