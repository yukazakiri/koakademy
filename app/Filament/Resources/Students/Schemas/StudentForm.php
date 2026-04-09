<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\AttritionCategory;
use App\Enums\EmploymentStatus;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

final class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema(self::getBasicInfoSection())
                    ->columns(2)
                    ->columnSpan([
                        'lg' => fn (?Student $student): int => $student instanceof Student
                            ? 2
                            : 3,
                    ]),
                Section::make()
                    ->schema(self::getMetadataSection())
                    ->columnSpan(['lg' => 1])
                    ->hidden(
                        fn (?Student $student): bool => ! $student instanceof Student,
                    ),
                Section::make()
                    ->schema(self::getAdditionalInfoSection())
                    ->columns(2)
                    ->columnSpan([
                        'lg' => fn (?Student $student): int => $student instanceof Student
                            ? 2
                            : 3,
                    ])
                    ->collapsed()
                    ->hidden(
                        fn (?Student $student): bool => ! $student instanceof Student,
                    )
                    ->lazy(),
                Section::make('Statistical & Reporting Data')
                    ->schema(self::getStatisticalDataSection())
                    ->columns(2)
                    ->columnSpan(['lg' => 3])
                    ->collapsed()
                    ->hidden(
                        fn (?Student $student): bool => ! $student instanceof Student,
                    )
                    ->lazy(),
            ])
            ->columns(3);

    }

    private static function getBasicInfoSection(): array
    {
        return [
            Select::make('student_type')
                ->label('Student Type')
                ->options(StudentType::asSelectOptions())
                ->default(StudentType::College->value)
                ->required()
                ->live(onBlur: false)
                ->afterStateUpdated(function ($set, $state, $get): void {
                    // Clear student_id when student type changes
                    $set('student_id', null);
                    // Clear LRN when switching away from SHS
                    if ($state !== StudentType::SeniorHighSchool->value) {
                        $set('lrn', null);
                        // Clear SHS fields when switching away from SHS
                        $set('shs_track_id', null);
                        $set('shs_strand_id', null);
                    }
                    // Clear course_id when switching to SHS
                    if ($state === StudentType::SeniorHighSchool->value) {
                        $set('course_id', null);
                    }
                }),

            self::getStudentIdField(),
            self::getLrnField(),

            TextInput::make('first_name')
                ->maxLength(50)
                ->required(),
            TextInput::make('last_name')
                ->maxLength(50)
                ->required(),
            TextInput::make('middle_name')->maxLength(20),
            self::getGenderSelect(),
            self::getBirthDatePicker(),
            TextInput::make('age')
                ->readonly()
                ->numeric()
                ->required(),
            TextInput::make('email')
                ->label('Email address')
                ->email()
                ->maxLength(255),
            Select::make('course_id')
                ->relationship('course', 'code')
                ->searchable()
                ->preload()
                ->hidden(fn ($get): bool => $get('student_type') === StudentType::SeniorHighSchool->value),
            Select::make('academic_year')
                ->options(function ($get): array {
                    if ($get('student_type') === StudentType::SeniorHighSchool->value) {
                        return [
                            '11' => 'Grade 11',
                            '12' => 'Grade 12',
                        ];
                    }

                    return [
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                        '5' => 'Graduate',
                    ];
                })
                ->required(),

            self::getShsStrandField(),

            Textarea::make('remarks')
                ->label('Remarks')
                ->columnSpanFull(),
        ];
    }

    private static function getGenderSelect(): Select
    {
        return Select::make('gender')
            ->options([
                'male' => 'Male',
                'female' => 'Female',
            ])
            ->required();
    }

    private static function getBirthDatePicker(): DatePicker
    {
        return DatePicker::make('birth_date')
            ->maxDate('today')
            ->live(debounce: 500)
            ->afterStateUpdated(function ($set, $state): void {
                if ($state) {
                    $age = Carbon::parse($state)->age;
                    $set('age', $age);
                }
            })
            ->required();
    }

    private static function getMetadataSection(): array
    {
        return [
            Placeholder::make('student_id')
                ->label('Student ID')
                ->content(fn (?Student $student): ?int => $student?->student_id),
            Placeholder::make('created_at')
                ->label('Created at')
                ->content(
                    fn (
                        Student $student,
                    ): ?string => $student->created_at?->diffForHumans(),
                ),
            Placeholder::make('Course')
                ->label('Course')
                ->content(
                    fn (Student $student): ?string => $student->Course->code ??
                        null,
                ),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            self::getGuardianContactInfo(),
            self::getParentInfo(),
            self::getEducationInfo(),
            self::getAddressInfo(),
            self::getPersonalInfo(),
        ];
    }

    private static function getGuardianContactInfo(): Fieldset
    {
        return Fieldset::make('Guardian Contact Informations')
            ->relationship('studentContactsInfo')
            ->schema([
                PhoneInput::make('personal_contact')
                    ->label('Student Contact Number')
                    ->initialCountry('ph'),
                TextInput::make(
                    'emergency_contact_name',
                )->label('Guardian Name'),
                PhoneInput::make('emergency_contact_phone')
                    ->defaultCountry('PH')
                    ->initialCountry('ph')
                    ->label('Guardian Contact Number'),
                TextInput::make(
                    'emergency_contact_address',
                )->label('Guardian Address'),
            ]);
    }

    private static function getParentInfo(): Fieldset
    {
        return Fieldset::make('Parent Information')
            ->relationship('studentParentInfo')
            ->schema([
                TextInput::make('fathers_name')->label(
                    "Father's Name",
                ),
                TextInput::make('mothers_name')->label(
                    "Mother's Name",
                ),
            ]);
    }

    private static function getEducationInfo(): Fieldset
    {
        return Fieldset::make('Education Information')
            ->relationship('studentEducationInfo')
            ->schema([
                TextInput::make('elementary_school')->label(
                    'Elementary School',
                ),
                TextInput::make(
                    'elementary_graduate_year',
                )->label('Elementary School Graduation Year'),
                TextInput::make('elementary_school_address')
                    ->columnSpanFull()
                    ->label('Elementary School Address'),
                TextInput::make(
                    'junior_high_school_name',
                )->label('Junior High School Name'),
                TextInput::make(
                    'junior_high_graduation_year',
                )->label('Junior High School Graduation Year'),
                TextInput::make('junior_high_school_address')
                    ->columnSpanFull()
                    ->label('Junior High School Address'),
                TextInput::make('senior_high_name')->label(
                    'Senior High School',
                ),
                TextInput::make(
                    'senior_high_graduate_year',
                )->label('Senior High School Graduation Year'),
                TextInput::make('senior_high_address')
                    ->columnSpanFull()
                    ->label('Senior High School Address'),
            ]);
    }

    private static function getAddressInfo(): Fieldset
    {
        return Fieldset::make('Address Information')
            ->relationship('personalInfo')
            ->schema([
                TextInput::make('current_adress')->label(
                    'Current Address',
                ),
                TextInput::make('permanent_address')->label(
                    'Permanent Address',
                ),
            ])
            ->columns(1);
    }

    private static function getPersonalInfo(): Fieldset
    {
        return Fieldset::make('Personal Information')
            ->relationship('personalInfo')
            ->schema([
                TextInput::make('birthplace')
                    ->hint('(Municipality / City)')
                    ->label('Birthplace'),
                TextInput::make('civil_status')->label(
                    'Civil Status',
                ),
                TextInput::make('citizenship')->label(
                    'Citizenship',
                ),
                TextInput::make('religion')->label('Religion'),
                TextInput::make('weight')
                    ->label('Weight')
                    ->numeric(),
                TextInput::make('height')->label('Height'),
                TextInput::make('current_adress')->label(
                    'Current Address',
                ),
                TextInput::make('permanent_address')->label(
                    'Permanent Address',
                ),
            ])
            ->columns(2);
    }

    private static function getStudentIdField(): TextInput
    {
        return TextInput::make('student_id')
            ->label('Student ID')
            ->unique(Student::class, 'student_id', ignoreRecord: true)
            ->required()
            ->live()
            ->visible(function ($get): bool {
                $studentType = $get('student_type');

                // For SHS, we use LRN as student_id, so hide this field
                return $studentType !== StudentType::SeniorHighSchool->value;
            })
            ->rules(function ($get): array {
                $studentType = $get('student_type');
                if ($studentType === StudentType::SeniorHighSchool->value) {
                    return [];
                }

                return [
                    'required',
                    'numeric',
                    'digits:6', // Ensure exactly 6 digits for College, TESDA, and DHRT
                    function ($attribute, $value, $fail) use ($get): void {
                        $studentType = $get('student_type');
                        if (! $studentType) {
                            return;
                        }

                        $type = StudentType::tryFrom($studentType);
                        if (! $type) {
                            return;
                        }

                        $expectedPrefix = $type->getIdPrefix();
                        $valueStr = (string) $value;

                        if (! str_starts_with($valueStr, $expectedPrefix)) {
                            $typeName = $type->getLabel();
                            $fail("The student ID must start with {$expectedPrefix} for {$typeName}.");
                        }
                    },
                ];
            })
            ->placeholder(function ($get): string {
                $studentType = $get('student_type');
                if (! $studentType) {
                    return '6-digit student ID';
                }

                $type = StudentType::tryFrom($studentType);
                if (! $type) {
                    return '6-digit student ID';
                }

                $prefix = $type->getIdPrefix();

                return "6-digit ID starting with {$prefix} (e.g., {$prefix}00001)";
            })
            ->helperText(function ($get): ?string {
                $studentType = $get('student_type');
                if (! $studentType) {
                    return null;
                }

                $type = StudentType::tryFrom($studentType);
                if (! $type) {
                    return null;
                }

                $prefix = $type->getIdPrefix();

                return "Must be exactly 6 digits starting with {$prefix}";
            })
            ->suffixAction(
                Action::make('generate')
                    ->icon('heroicon-m-sparkles')
                    ->color('gray')
                    ->tooltip('Generate next available student ID')
                    ->action(function ($get, $set): void {
                        $studentType = $get('student_type');
                        if (! $studentType) {
                            return;
                        }

                        $type = StudentType::tryFrom($studentType);
                        if (! $type || $type === StudentType::SeniorHighSchool) {
                            return;
                        }

                        // Generate next available ID for the student type
                        $nextId = Student::generateNextId($type);
                        $set('student_id', $nextId);
                    })
            );
    }

    private static function getLrnField(): TextInput
    {
        return TextInput::make('lrn')
            ->label('Learner Reference Number (LRN)')
            ->unique(Student::class, 'lrn', ignoreRecord: true)
            ->visible(function ($get): bool {
                $studentType = $get('student_type');

                return $studentType === StudentType::SeniorHighSchool->value;
            })
            ->required(function ($get): bool {
                $studentType = $get('student_type');

                return $studentType === StudentType::SeniorHighSchool->value;
            })
            ->rules(function ($get): array {
                $studentType = $get('student_type');
                if ($studentType === StudentType::SeniorHighSchool->value) {
                    return [
                        'required',
                        'string',
                        'max:20', // LRN can be alphanumeric and longer
                    ];
                }

                return [];
            })
            ->placeholder('12-digit LRN (e.g., 123456789012)')
            ->helperText('For Senior High School students, LRN is used as the student identifier')
            ->afterStateUpdated(function ($set, $state, $get): void {
                // For SHS students, copy LRN to student_id field
                $studentType = $get('student_type');
                if ($studentType === StudentType::SeniorHighSchool->value && $state) {
                    $set('student_id', $state);
                }
            });
    }

    private static function getShsStrandField(): Select
    {
        return Select::make('shs_strand_id')
            ->label('SHS Strand')
            ->relationship('shsStrand', 'strand_name')
            ->searchable()
            ->preload()
            ->visible(fn ($get): bool => $get('student_type') === StudentType::SeniorHighSchool->value)
            ->required(fn ($get): bool => $get('student_type') === StudentType::SeniorHighSchool->value)
            ->helperText('Select the SHS strand (e.g., STEM, HUMSS, ABM, GAS, ICT, etc.)');
    }

    private static function getStatisticalDataSection(): array
    {
        return [
            // Demographics Fieldset
            Fieldset::make('Demographics')
                ->schema([
                    TextInput::make('ethnicity')
                        ->maxLength(100)
                        ->placeholder('e.g., Filipino, Chinese, etc.'),

                    TextInput::make('city_of_origin')
                        ->label('City of Origin')
                        ->maxLength(100),

                    TextInput::make('province_of_origin')
                        ->label('Province of Origin')
                        ->maxLength(100),

                    Select::make('region_of_origin')
                        ->label('Region of Origin')
                        ->options([
                            'NCR' => 'National Capital Region (NCR)',
                            'CAR' => 'Cordillera Administrative Region (CAR)',
                            'Region I' => 'Region I - Ilocos Region',
                            'Region II' => 'Region II - Cagayan Valley',
                            'Region III' => 'Region III - Central Luzon',
                            'Region IV-A' => 'Region IV-A - CALABARZON',
                            'Region IV-B' => 'Region IV-B - MIMAROPA',
                            'Region V' => 'Region V - Bicol Region',
                            'Region VI' => 'Region VI - Western Visayas',
                            'Region VII' => 'Region VII - Central Visayas',
                            'Region VIII' => 'Region VIII - Eastern Visayas',
                            'Region IX' => 'Region IX - Zamboanga Peninsula',
                            'Region X' => 'Region X - Northern Mindanao',
                            'Region XI' => 'Region XI - Davao Region',
                            'Region XII' => 'Region XII - SOCCSKSARGEN',
                            'Region XIII' => 'Region XIII - Caraga',
                            'BARMM' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
                        ])
                        ->searchable(),

                    Toggle::make('is_indigenous_person')
                        ->label('Indigenous Person (IP)')
                        ->live()
                        ->helperText('Check if student belongs to an indigenous group'),

                    TextInput::make('indigenous_group')
                        ->label('Indigenous Group')
                        ->maxLength(100)
                        ->placeholder('e.g., Igorot, Lumad, Mangyan, etc.')
                        ->visible(fn ($get): bool => $get('is_indigenous_person') === true),
                ])
                ->columns(2),

            // Academic Status Fieldset
            Fieldset::make('Academic Status')
                ->schema([
                    Select::make('status')
                        ->label('Student Status')
                        ->options(StudentStatus::class)
                        ->default(StudentStatus::Enrolled)
                        ->required()
                        ->live()
                        ->helperText('Current enrollment status of the student'),

                    DatePicker::make('withdrawal_date')
                        ->label('Withdrawal Date')
                        ->visible(fn ($get): bool => $get('status') === StudentStatus::Withdrawn->value)
                        ->maxDate(now()),

                    Textarea::make('withdrawal_reason')
                        ->label('Withdrawal Reason')
                        ->rows(3)
                        ->visible(fn ($get): bool => $get('status') === StudentStatus::Withdrawn->value),

                    Select::make('attrition_category')
                        ->label('Attrition Category')
                        ->options(AttritionCategory::class)
                        ->visible(fn ($get): bool => in_array($get('status'), [
                            StudentStatus::Withdrawn->value,
                            StudentStatus::Dropped->value,
                        ]))
                        ->helperText('Reason category for withdrawal or dropout'),

                    DatePicker::make('dropout_date')
                        ->label('Dropout Date')
                        ->visible(fn ($get): bool => $get('status') === StudentStatus::Dropped->value)
                        ->maxDate(now()),
                ])
                ->columns(2),

            // Scholarship Information Fieldset
            Fieldset::make('Scholarship Information')
                ->schema([
                    Select::make('scholarship_type')
                        ->label('Scholarship Type')
                        ->options(ScholarshipType::class)
                        ->default(ScholarshipType::None)
                        ->live()
                        ->helperText('Type of scholarship or financial assistance'),

                    Textarea::make('scholarship_details')
                        ->label('Scholarship Details')
                        ->rows(3)
                        ->visible(fn ($get): bool => $get('scholarship_type') !== null && $get('scholarship_type') !== ScholarshipType::None->value)
                        ->placeholder('Additional information about the scholarship (e.g., amount, duration, conditions)'),
                ])
                ->columns(2),

            // Employment Information Fieldset (for graduates)
            Fieldset::make('Employment Information')
                ->schema([
                    Select::make('employment_status')
                        ->label('Employment Status')
                        ->options(EmploymentStatus::class)
                        ->default(EmploymentStatus::NotApplicable)
                        ->live()
                        ->helperText('Post-graduation employment status'),

                    TextInput::make('employer_name')
                        ->label('Employer Name')
                        ->maxLength(255)
                        ->visible(fn ($get): bool => in_array($get('employment_status'), [
                            EmploymentStatus::Employed->value,
                            EmploymentStatus::SelfEmployed->value,
                            EmploymentStatus::Underemployed->value,
                        ])),

                    TextInput::make('job_position')
                        ->label('Job Position/Title')
                        ->maxLength(255)
                        ->visible(fn ($get): bool => in_array($get('employment_status'), [
                            EmploymentStatus::Employed->value,
                            EmploymentStatus::SelfEmployed->value,
                            EmploymentStatus::Underemployed->value,
                        ])),

                    DatePicker::make('employment_date')
                        ->label('Employment Date')
                        ->visible(fn ($get): bool => in_array($get('employment_status'), [
                            EmploymentStatus::Employed->value,
                            EmploymentStatus::SelfEmployed->value,
                            EmploymentStatus::Underemployed->value,
                        ]))
                        ->maxDate(now()),

                    Toggle::make('employed_by_institution')
                        ->label('Employed by This Institution')
                        ->visible(fn ($get): bool => in_array($get('employment_status'), [
                            EmploymentStatus::Employed->value,
                            EmploymentStatus::SelfEmployed->value,
                            EmploymentStatus::Underemployed->value,
                        ]))
                        ->helperText('Check if the graduate is employed by this institution'),
                ])
                ->columns(2)
                ->visible(fn ($get): bool => $get('status') === StudentStatus::Graduated->value),
        ];
    }
}
