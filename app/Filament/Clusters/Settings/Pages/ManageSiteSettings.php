<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Settings\SiteSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Exception;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ManageSiteSettings extends SettingsPage
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::GlobeAlt;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string $settings = SiteSettings::class;

    protected static ?string $cluster = SettingsCluster::class;

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Admin Panel Settings
                Section::make('Admin Panel Settings')
                    ->description('These settings apply to the admin panel ('.config('app.admin_host').')')
                    ->schema([
                        TextInput::make('name')
                            ->label('Admin Panel Name')
                            ->required(),
                        TextInput::make('description')
                            ->label('Admin Panel Description')
                            ->required(),
                        FileUpload::make('logo')
                            ->image()
                            ->imageEditor()
                            ->openable()
                            ->preserveFilenames()
                            ->previewable()
                            ->downloadable()
                            ->deletable(),
                        FileUpload::make('favicon')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->maxWidth('50')
                            ->openable()
                            ->preserveFilenames()
                            ->previewable()
                            ->downloadable()
                            ->imageResizeTargetWidth('50')
                            ->imageResizeTargetHeight('50')
                            ->imagePreviewHeight('250')
                            ->deletable()
                            ->rules([
                                'dimensions:ratio=1:1',
                                'dimensions:max_width=50,max_height=50',
                            ]),
                        FileUpload::make('og_image')
                            ->label('Admin OG Image')
                            ->helperText('Recommended size: 1200x630px (40:21 ratio)')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('40:21')
                            ->openable()
                            ->preserveFilenames()
                            ->previewable()
                            ->downloadable()
                            ->deletable()
                            ->rules([
                                'dimensions:ratio=40/21',
                            ]),
                    ])
                    ->collapsible(),

                // Portal Settings
                Section::make('Portal Settings')
                    ->description('These settings apply to the faculty portal ('.config('app.portal_host').')')
                    ->schema([
                        TextInput::make('portal_name')
                            ->label('Portal Name')
                            ->helperText('e.g., "Faculty Portal"')
                            ->placeholder('Faculty Portal'),
                        TextInput::make('portal_description')
                            ->label('Portal Description')
                            ->helperText('Used for meta tags and link previews')
                            ->placeholder('Faculty Portal - Manage your classes, students, and schedules'),
                        FileUpload::make('portal_og_image')
                            ->label('Portal OG Image')
                            ->helperText('Recommended size: 1200x630px (40:21 ratio). Used when sharing portal links.')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('40:21')
                            ->openable()
                            ->preserveFilenames()
                            ->previewable()
                            ->downloadable()
                            ->deletable()
                            ->rules([
                                'dimensions:ratio=40/21',
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
