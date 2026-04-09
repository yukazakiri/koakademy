<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\GeneralSetting;
use App\Services\EnrollmentPipelineService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

final class GeneralSettings extends Page
{
    public ?array $data = [];
    // public static function canAccess(): bool
    // {
    //     return Auth::user()->can('page_GeneralSetting');
    // }

    protected string $view = 'filament-general-settings::filament.pages.general-settings-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'School Settings';
    }

    public function mount(): void
    {
        // Ensure you are fetching the first record or handle it according to your application logic.
        $settings = GeneralSetting::query()->first();
        // dd($settings);
        if ($settings) {
            $this->form->fill($settings->toArray());
        }
        // Handle the case where no settings are found if necessary

    }

    public function form(Schema $schema): Schema
    {
        $pipelineConfig = app(EnrollmentPipelineService::class)->getConfiguration();

        $arrTabs = [];

        if (config('filament-general-settings.show_school_portal_tab')) {
            $arrTabs[] = Tab::make('School Portal Tab')
                ->label(__('School Portal'))
                ->icon('fas-school')
                ->schema([
                    TextInput::make('school_portal_url')
                        ->label('School Portal URL')
                        ->url()
                        ->columnSpanFull(),
                    ToggleButtons::make('school_portal_enabled')->label(
                        'Enable School Portal'
                    )->boolean()
                        ->grouped(),
                    ToggleButtons::make('online_enrollment_enabled')->label(
                        'Enable Online Enrollment'
                    )->boolean()
                        ->grouped(),
                    ToggleButtons::make('enable_clearance_check')->label(
                        'Enable Clearance Checking'
                    )->boolean()
                        ->grouped(),

                    ToggleButtons::make('enable_support_page')->label(
                        'Enable Support Page'
                    )->boolean()
                        ->grouped(),
                    FileUpload::make('school_portal_logo')
                        ->label('School Portal Logo')
                        ->image()
                        ->disk('supabase')
                        ->visibility('public'),
                    FileUpload::make('school_portal_favicon')
                        ->label('School Portal Favicon')
                        ->image()
                        ->disk('supabase')
                        ->visibility('public'),
                    TextInput::make('school_portal_title')
                        ->label('School Portal Title')
                        ->columnSpanFull(),
                    TextInput::make('school_portal_description')
                        ->label('School Portal Description')
                        ->columnSpanFull(),
                    Section::make('Features')
                        ->schema([
                            Fieldset::make('student_features')
                                ->label('Student Features')
                                ->columnSpan(1)
                                ->columns(1)
                                ->schema([
                                    ToggleButtons::make('features.student_features.enable_tuition_fees_page')
                                        ->label('Enable Tuition & Fees Page'),
                                    ToggleButtons::make('features.student_features.enable_cheklist_page')
                                        ->label('Enable Checklist Page'),
                                    ToggleButtons::make('features.student_features.enable_schedules_page')
                                        ->label('Enable Schedules Page'),
                                    ToggleButtons::make('features.student_features.enable_class_rooms_page')
                                        ->label('Enable Class Rooms Page'),
                                    // ToggleButton::make('features.student_features.enable_grades_page')
                                    //     ->label('Enable Grades Page'),
                                    // ToggleButton::make('features.student_features.enable_attendance_page')
                                    //     ->label('Enable Attendance Page'),
                                ]),
                            Fieldset::make('teacher_features')
                                ->label('Teacher Features')
                                ->columnSpan(1)
                                ->columns(1)
                                ->schema([
                                    ToggleButtons::make('features.teacher_features.enable_class_rooms_page')
                                        ->label('Enable Class Rooms Page'),
                                    ToggleButtons::make('features.teacher_features.enable_schedules_page')
                                        ->label('Enable Schedules Page'),
                                    ToggleButtons::make('features.teacher_features.enable_grades_page')
                                        ->label('Enable Grades Page'),
                                    ToggleButtons::make('features.teacher_features.enable_attendance_page')
                                        ->label('Enable Attendance Page'),
                                ]),
                        ])
                        ->columns(2),
                ])
                ->columns(3);
        }

        $arrTabs[] = Tab::make('Enrollment Settings Tab')
            ->label(__('Enrollment Settings'))
            ->icon('fas-user-graduate')
            ->schema([
                DatePicker::make('school_starting_date')
                    ->label('School Starting Date')
                    ->displayFormat('Y-m-d'),
                DatePicker::make('school_ending_date')
                    ->label('School Ending Date')
                    ->displayFormat('Y-m-d'),
                Select::make('semester')
                    ->label('Semester')
                    ->options([
                        '1' => '1st Semester',
                        '2' => '2nd Semester',
                    ]),
                Select::make('enrollment_courses')
                    ->label(
                        'Select the courses that will be available for enrollment'
                    )
                    ->columnSpanFull()

                    ->multiple()
                    ->options(Course::all()->pluck('code', 'id')),
                ToggleButtons::make('enable_signatures')->label(
                    'Enable Signatures'
                )->boolean()
                    ->grouped(),
                ToggleButtons::make('enable_qr_codes')
                    ->label('Enable QR codes')
                    ->hint(
                        'Turning this one makes each transaction have thier own QR code'
                    )->boolean()
                    ->grouped(),
                ToggleButtons::make('enable_public_transactions')
                    ->label('make Transactions Public')
                    ->hint(
                        'this will make all transactions can be viewed by the public'
                    )->boolean()
                    ->grouped(),
                Section::make('Enrollment Pipeline Workflow')
                    ->description('Configure enrollment status values, labels, and color coding. Changes here will be used by enrollment logic and dashboards.')
                    ->schema([
                        TextInput::make('more_configs.enrollment_pipeline.submitted_label')
                            ->label('Submitted Step Label')
                            ->default($pipelineConfig['submitted_label'])
                            ->required(),
                        Repeater::make('more_configs.enrollment_pipeline.steps')
                            ->label('Workflow Steps (Dynamic)')
                            ->default($pipelineConfig['steps'] ?? [])
                            ->schema([
                                TextInput::make('key')->label('Step Key')->required(),
                                TextInput::make('status')->label('Status Value')->required(),
                                TextInput::make('label')->label('Step Label')->required(),
                                Select::make('color')
                                    ->label('Badge Color')
                                    ->options([
                                        'yellow' => 'Yellow',
                                        'blue' => 'Blue',
                                        'green' => 'Green',
                                        'emerald' => 'Emerald',
                                        'teal' => 'Teal',
                                        'gray' => 'Gray',
                                        'amber' => 'Amber',
                                        'red' => 'Red',
                                        'indigo' => 'Indigo',
                                        'orange' => 'Orange',
                                    ])
                                    ->default('blue')
                                    ->required(),
                                Select::make('action_type')
                                    ->label('Step Type')
                                    ->options([
                                        'standard' => 'Standard',
                                        'department_verification' => 'Verification',
                                        'cashier_verification' => 'Payment Verification',
                                    ])
                                    ->default('standard')
                                    ->required(),
                                Select::make('allowed_roles')
                                    ->label('Allowed Roles')
                                    ->options(Role::query()->orderBy('name')->pluck('name', 'name')->all())
                                    ->multiple(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->collapsible(),
                        TextInput::make('more_configs.enrollment_pipeline.entry_step_key')
                            ->label('Entry Step Key')
                            ->default($pipelineConfig['entry_step_key'] ?? null),
                        TextInput::make('more_configs.enrollment_pipeline.completion_step_key')
                            ->label('Completion Step Key')
                            ->default($pipelineConfig['completion_step_key'] ?? null),
                        TextInput::make('more_configs.enrollment_pipeline.pending_status')
                            ->label('Pending Status Value')
                            ->default($pipelineConfig['pending_status'])
                            ->required(),
                        TextInput::make('more_configs.enrollment_pipeline.pending_label')
                            ->label('Pending Step Label')
                            ->default($pipelineConfig['pending_label'])
                            ->required(),
                        Select::make('more_configs.enrollment_pipeline.pending_color')
                            ->label('Pending Status Color')
                            ->options([
                                'yellow' => 'Yellow',
                                'blue' => 'Blue',
                                'green' => 'Green',
                                'emerald' => 'Emerald',
                                'teal' => 'Teal',
                                'gray' => 'Gray',
                                'amber' => 'Amber',
                                'red' => 'Red',
                                'indigo' => 'Indigo',
                                'orange' => 'Orange',
                            ])
                            ->default($pipelineConfig['pending_color'])
                            ->required(),
                        Select::make('more_configs.enrollment_pipeline.pending_roles')
                            ->label('Pending Allowed Roles')
                            ->options(Role::query()->orderBy('name')->pluck('name', 'name')->all())
                            ->multiple()
                            ->default($pipelineConfig['pending_roles'] ?? []),
                        TextInput::make('more_configs.enrollment_pipeline.department_verified_status')
                            ->label('Department Verified Status Value')
                            ->default($pipelineConfig['department_verified_status'])
                            ->required(),
                        TextInput::make('more_configs.enrollment_pipeline.department_verified_label')
                            ->label('Department Verified Step Label')
                            ->default($pipelineConfig['department_verified_label'])
                            ->required(),
                        Select::make('more_configs.enrollment_pipeline.department_verified_color')
                            ->label('Department Verified Status Color')
                            ->options([
                                'yellow' => 'Yellow',
                                'blue' => 'Blue',
                                'green' => 'Green',
                                'emerald' => 'Emerald',
                                'teal' => 'Teal',
                                'gray' => 'Gray',
                                'amber' => 'Amber',
                                'red' => 'Red',
                                'indigo' => 'Indigo',
                                'orange' => 'Orange',
                            ])
                            ->default($pipelineConfig['department_verified_color'])
                            ->required(),
                        Select::make('more_configs.enrollment_pipeline.department_verified_roles')
                            ->label('Department Step Allowed Roles')
                            ->options(Role::query()->orderBy('name')->pluck('name', 'name')->all())
                            ->multiple()
                            ->default($pipelineConfig['department_verified_roles'] ?? []),
                        TextInput::make('more_configs.enrollment_pipeline.cashier_verified_status')
                            ->label('Cashier Verified Status Value')
                            ->default($pipelineConfig['cashier_verified_status'])
                            ->required(),
                        TextInput::make('more_configs.enrollment_pipeline.cashier_verified_label')
                            ->label('Cashier Verified Step Label')
                            ->default($pipelineConfig['cashier_verified_label'])
                            ->required(),
                        Select::make('more_configs.enrollment_pipeline.cashier_verified_color')
                            ->label('Cashier Verified Status Color')
                            ->options([
                                'yellow' => 'Yellow',
                                'blue' => 'Blue',
                                'green' => 'Green',
                                'emerald' => 'Emerald',
                                'teal' => 'Teal',
                                'gray' => 'Gray',
                                'amber' => 'Amber',
                                'red' => 'Red',
                                'indigo' => 'Indigo',
                                'orange' => 'Orange',
                            ])
                            ->default($pipelineConfig['cashier_verified_color'])
                            ->required(),
                        Select::make('more_configs.enrollment_pipeline.cashier_verified_roles')
                            ->label('Cashier Step Allowed Roles')
                            ->options(Role::query()->orderBy('name')->pluck('name', 'name')->all())
                            ->multiple()
                            ->default($pipelineConfig['cashier_verified_roles'] ?? []),
                        Repeater::make('more_configs.enrollment_pipeline.additional_steps')
                            ->label('Additional Steps')
                            ->default($pipelineConfig['additional_steps'] ?? [])
                            ->schema([
                                TextInput::make('status')->label('Status Value')->required(),
                                TextInput::make('label')->label('Step Label')->required(),
                                Select::make('color')
                                    ->label('Badge Color')
                                    ->options([
                                        'yellow' => 'Yellow',
                                        'blue' => 'Blue',
                                        'green' => 'Green',
                                        'emerald' => 'Emerald',
                                        'teal' => 'Teal',
                                        'gray' => 'Gray',
                                        'amber' => 'Amber',
                                        'red' => 'Red',
                                        'indigo' => 'Indigo',
                                        'orange' => 'Orange',
                                    ])
                                    ->default('indigo')
                                    ->required(),
                                Select::make('allowed_roles')
                                    ->label('Allowed Roles')
                                    ->options(Role::query()->orderBy('name')->pluck('name', 'name')->all())
                                    ->multiple(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->collapsible(),
                        Repeater::make('more_configs.enrollment_stats.cards')
                            ->label('Enrollment Stats Cards')
                            ->schema([
                                TextInput::make('key')->label('Card Key')->required(),
                                TextInput::make('label')->label('Card Label')->required(),
                                Select::make('metric')
                                    ->label('Metric')
                                    ->options([
                                        'total_records' => 'Total Records',
                                        'active_records' => 'Active Records',
                                        'trashed_records' => 'Deleted Records',
                                        'status_count' => 'Status Count',
                                        'paid_count' => 'Fully Paid Count',
                                    ])
                                    ->required(),
                                Select::make('color')
                                    ->label('Card Color')
                                    ->options([
                                        'yellow' => 'Yellow',
                                        'blue' => 'Blue',
                                        'green' => 'Green',
                                        'emerald' => 'Emerald',
                                        'teal' => 'Teal',
                                        'gray' => 'Gray',
                                        'amber' => 'Amber',
                                        'red' => 'Red',
                                        'indigo' => 'Indigo',
                                        'orange' => 'Orange',
                                    ])
                                    ->default('blue')
                                    ->required(),
                                Select::make('statuses')
                                    ->label('Tracked Statuses')
                                    ->options(collect(app(EnrollmentPipelineService::class)->getStatusOptions())->pluck('label', 'value')->all())
                                    ->multiple(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->collapsible(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(3);

        // Add Module Settings Tab
        $arrTabs[] = Tab::make('Module Settings Tab')
            ->label(__('Module Settings'))
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make('Module Configuration')
                    ->description('Enable or disable specific modules in the system')
                    ->schema([
                        ToggleButtons::make('inventory_module_enabled')
                            ->label('Enable Inventory Module')
                            ->hint('Enable the inventory management system with products, categories, suppliers, and stock tracking')
                            ->columnSpanFull()
                            ->grouped()
                            ->boolean(),
                        ToggleButtons::make('library_module_enabled')
                            ->label('Enable Library Module')
                            ->hint('Enable the library management system with books, authors, categories, and borrowing records')
                            ->columnSpanFull()
                            ->grouped()
                            ->boolean(),
                    ])
                    ->columns(1),
            ]);

        return $schema
            ->schema([Tabs::make('Tabs')->tabs($arrTabs)])
            ->statePath('data');
    }

    public function update(): void
    {
        $data = $this->form->getState();
        $pipelineService = app(EnrollmentPipelineService::class);

        if (isset($data['more_configs']['enrollment_pipeline']) && is_array($data['more_configs']['enrollment_pipeline'])) {
            $data['more_configs']['enrollment_pipeline'] = $pipelineService->sanitizeForStorage($data['more_configs']['enrollment_pipeline']);
        }
        if (isset($data['more_configs']['enrollment_stats']) && is_array($data['more_configs']['enrollment_stats'])) {
            $data['more_configs']['enrollment_stats'] = $pipelineService->sanitizeStatsForStorage($data['more_configs']['enrollment_stats']);
        }
        // dd($data);
        // $data = EmailDataHelper::setEmailConfigToDatabase($data);

        GeneralSetting::updateOrCreate([], $data);
        Cache::forget('general_settings');

        $this->successNotification(
            __('filament-general-settings::default.settings_saved')
        );
        redirect(request()?->header('Referer'));
    }

    // public function castAcademicYearStart(string $value): \DateTime
    // {
    //     return new \DateTime($value);
    // }

    // public function castAcademicYearEnd(string $value): \DateTime
    // {
    //     return new \DateTime($value);
    // }
    private function getFormActions(): array
    {
        return [
            Action::make('Save')
                ->label(__('filament-general-settings::default.save'))
                ->color('primary')
                ->requiresConfirmation()
                ->submit('Update'),
        ];
    }

    private function successNotification(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }

    private function errorNotification(string $title, string $body): void
    {
        Log::error('[EMAIL] '.$body);

        Notification::make()->title($title)->danger()->body($body)->send();
    }
}
