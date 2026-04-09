<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Enums\UserRole;
use App\Filament\Pages\Backups;
use App\Filament\Pages\GeneralSettings;
use App\Services\AnalyticsSettingsService;
use App\Settings\SiteSettings;
use Awcodes\Gravatar\GravatarPlugin;
use Awcodes\Gravatar\GravatarProvider;
use Backstage\Mails\MailsPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Coolsam\Modules\ModulesPlugin;
use Exception;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Platform;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;
use MarcelWeidum\Passkeys\PasskeysPlugin;
use Moataz01\FilamentNotificationSound\FilamentNotificationSoundPlugin;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;
use pxlrbt\FilamentSpotlight\SpotlightPlugin;
use Rupadana\ApiService\ApiServicePlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Spatie\LaravelSettings\Settings;
use Stephenjude\FilamentDebugger\DebuggerPlugin;

final class AdminPanelProvider extends PanelProvider
{
    // private readonly Settings $settings;

    // public function __construct($app)
    // {
    //     parent::__construct($app);

    //     $this->settings = app(SiteSettings::class);

    // }

    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $settings = app(SiteSettings::class);
        $plugins = [
            FilamentEditProfilePlugin::make()
                ->slug('edit-profile')
                ->setIcon('heroicon-o-user')
                ->setTitle('Profile')
                ->setNavigationLabel('Profile')
                ->setNavigationGroup('Profile')
                ->shouldRegisterNavigation(false)
                ->shouldShowAvatarForm(true)
                ->shouldShowDeleteAccountForm(true)
                ->shouldShowThemeColorForm(true)
                ->shouldShowMultiFactorAuthentication()
                ->shouldShowSanctumTokens()
                ->shouldShowEmailForm(true)
                ->customProfileComponents([
                    \App\Livewire\PasskeyForm::class,
                ]),
            ActivityLogPlugin::make()
                ->label('Activity Log')
                ->pluralLabel('Activity Logs')
                ->navigationGroup('System Tools'),
            ApiServicePlugin::make(),
            FilamentShieldPlugin::make()
                ->navigationLabel('Roles and Permissions')
                ->navigationGroup('Administration')
                ->globallySearchable(false)
                ->gridColumns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 3,
                ])
                ->sectionColumnSpan(1)
                ->checkboxListColumns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 4,
                ])
                ->resourceCheckboxListColumns([
                    'default' => 1,
                    'sm' => 2,
                ]),
            FilamentLogViewer::make()
                ->navigationGroup('System Tools')
                ->authorize(fn (): bool => Auth::user()?->hasRole(UserRole::Developer)),
            EnvironmentIndicatorPlugin::make()
                ->visible(fn (): bool => Auth::user()?->hasRole(UserRole::Developer) ?? false)
                ->showDebugModeWarning()
                ->showGitBranch(),
            GravatarPlugin::make()
                ->default('initials')
                ->size(200),
            FilamentSpatieLaravelBackupPlugin::make()
                ->usingPage(Backups::class),
            FilamentNotificationSoundPlugin::make()
                ->showAnimation(true),
            MailsPlugin::make(),
            DebuggerPlugin::make()
                ->navigationGroup(condition: true, label: 'System Tools')
                ->authorize(condition: fn (): bool => Auth::user()?->hasRole(UserRole::SuperAdmin)),
            SpotlightPlugin::make(),
            PasskeysPlugin::make(),
        ];

        $isTestingEnvironment = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null) === 'testing';

        if (! $isTestingEnvironment) {
            $plugins[] = ModulesPlugin::make();
        }

        $panel->renderHook(
            'panels::global-search.before',
            fn (): View|Factory => // Return a view instance containing the Livewire component
            view('livewire.semester-school-year-selector')
        );

        $panel->renderHook(
            'panels::head.end',
            fn (): HtmlString => new HtmlString(app(AnalyticsSettingsService::class)->renderHeadMarkup())
        );

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->domain((string) config('app.admin_host'))
            ->brandName(fn (): string => $settings->getAppName())
            ->brandLogo(fn () => $settings->logo ? '/'.$settings->logo : null)
            ->brandLogoHeight('3rem')
            ->passwordReset()
            ->favicon(fn () => $settings->favicon ? '/'.$settings->favicon : null)
            ->login()

            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Academics')
                    ->icon('heroicon-o-academic-cap')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('People')
                    ->icon('heroicon-o-users')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Campus')
                    ->icon('heroicon-o-building-office-2')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Finance')
                    ->icon('heroicon-o-banknotes')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Inventory')
                    ->icon('heroicon-o-archive-box')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Library')
                    ->icon('heroicon-o-book-open')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Operations')
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Communications')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Content')
                    ->icon('heroicon-o-document-text')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Administration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('System Tools')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->collapsible(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins($plugins)
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label('Edit Profile')
                    ->url(fn (): string => EditProfilePage::getUrl())
                    ->icon(Heroicon::OutlinedPencilSquare),
                'System Settings' => Action::make('general-settings')
                    ->label('General Settings')
                    ->url(fn (): string => GeneralSettings::getUrl())
                    ->icon(Heroicon::OutlinedCog),
            ])
            ->defaultAvatarProvider(GravatarProvider::class)
            ->maxContentWidth(Width::Full)
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+l', 'ctrl+l'])
            ->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                Platform::Windows, Platform::Linux => 'CTRL+L',
                Platform::Mac => '⌘L',
                default => null,
            })
            ->sidebarCollapsibleOnDesktop()
            ->databaseTransactions()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
                EmailAuthentication::make()
                    ->codeExpiryMinutes(30),

            ])
            // ->routes(fn () => FilamentMails::routes())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->unsavedChangesAlerts()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->spa();
    }
}
