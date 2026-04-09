<?php

declare(strict_types=1);

namespace Modules\Cashier\Filament\Pages;

use App\Models\GeneralSetting;
use App\Models\Transaction;
use App\Notifications\InvoiceTransact;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
// use Filament\Pages\Actions\Action;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
// use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
// use Filament\Schemas\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\HtmlString;
use UnitEnum;

final class Cashier extends Page implements HasForms
{
    use HasPageShield;

    // use HasPageShield;
    use InteractsWithForms;

    public ?array $data = [];

    public ?\App\Models\Student $selectedStudent = null;

    public array $studentTuitionData = [];

    public string $view = 'cashier::filament.pages.cashier';

    // protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    // public $selectedStudent = null;
    // public static function canAccess(): bool
    // {
    //     return Auth::user()->hasPermissionTo('page_Cashier');
    // }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $merged = collect();

        \App\Models\Student::query()
            ->whereNotNull('first_name')
            ->whereNotNull('id')
            ->with('course')
            ->select([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'course_id',
            ])
            ->chunk(50, function ($chunk) use (&$merged): void {
                $mappedChunk = $chunk
                    ->map(function ($student): array {
                        $fullname = array_filter([
                            $student->first_name,
                            $student->middle_name,
                            $student->last_name,
                        ]);

                        return [
                            'id' => $student->id,
                            'fullname' => $fullname === []
                                ? 'Unknown Name'
                                : implode(' ', $fullname),
                        ];
                    })
                    ->filter(fn ($student): bool => isset($student['id']) &&
                        isset($student['fullname']));

                $merged = $merged->merge($mappedChunk);
            });

        return $schema
            ->components([
                Section::make('Student Info')
                    ->schema([
                        Select::make('student_id')
                            ->label('Student ID')
                            ->live()
                            ->options(fn () => $merged
                                ->filter(fn ($student): bool => ! empty($student['id']) &&
                                    ! empty($student['fullname']) &&
                                    is_numeric($student['id']) &&
                                    is_string($student['fullname']))
                                ->mapWithKeys(function ($student) {
                                    $label = sprintf(
                                        '%d -> %s',
                                        $student['id'],
                                        $student['fullname'] ??
                                            'Unknown Student'
                                    );

                                    return [
                                        (string) $student['id'] => $label,
                                    ];
                                })
                                ->toArray())
                            ->preload()
                            ->helperText('Search for the student by ID or Name')
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set): void {
                                if (! empty($state)) {
                                    $student = \App\Models\Student::with([
                                        'account',
                                        'StudentTuition',
                                        'course',
                                    ])->find((int) $state);

                                    if ($student) {
                                        $set('selectedStudent', $student);
                                    }
                                }
                            }),
                        Fieldset::make('Student Info')
                            ->visible(
                                fn ($get): bool => $get('selectedStudent') !== null
                            )
                            ->schema([
                                Placeholder::make('Student Name')
                                    ->label('Student Name')
                                    ->columnSpanFull()
                                    ->content(
                                        fn ($get): HtmlString => new HtmlString(
                                            '<span class="">'.
                                                ($get('selectedStudent')
                                                    ? $get('selectedStudent')
                                                        ->fullname
                                                    : 'N/A').
                                                '</span>'
                                        )
                                    ),
                                Placeholder::make('Student Course')
                                    ->label('Student Course')
                                    ->columnSpanFull()
                                    ->content(
                                        fn ($get): HtmlString => new HtmlString(
                                            '<span class="">'.
                                                ($get('selectedStudent')
                                                    ? $get('selectedStudent')
                                                        ->course->code
                                                    : 'N/A').
                                                '</span>'
                                        )
                                    ),
                                Placeholder::make('Student Account')
                                    ->label('Student ID')
                                    ->content(function ($get): HtmlString {
                                        $student = $get('selectedStudent');
                                        $account = $student->account;

                                        // dd($student->StudentTuition);
                                        return new HtmlString(
                                            '
                                            <span class="bg-primary-500/10 p-2 rounded-md text-primary-500 text-sm border border-primary-500"> ID#'.
                                                ($student
                                                    ? $student->id
                                                    : 'N/A').
                                                '</span>
                                        '
                                        );
                                    }),
                                Placeholder::make('Student Account')
                                    ->label('Student Year Level')
                                    ->content(function ($get): HtmlString {
                                        $student = $get('selectedStudent');
                                        // dd($student);
                                        $account = $student->account;

                                        // dd($student->StudentTuition);
                                        return new HtmlString(
                                            '
                                            <span class="bg-primary-500/10 p-2 rounded-md text-primary-500 text-sm border border-primary-500">'.
                                                ($student
                                                    ? $student->formatted_academic_year
                                                    : 'N/A').
                                                '</span>
                                        '
                                        );
                                    }),
                            ]),
                    ])
                    ->columnSpan(1),
                Tabs::make('Student Info')
                    ->visible(fn ($get): bool => $get('selectedStudent') !== null)
                    ->columnSpan(2)
                    ->tabs([
                        Tabs\Tab::make('Current Balance')->schema([
                            Select::make('school_year')
                                ->label('School Year')
                                ->options(function ($get) {
                                    $student = $get('selectedStudent');
                                    if ($student) {
                                        return $student->StudentTuition
                                            ->filter(
                                                fn ($t): bool => ! empty(
                                                    $t->school_year
                                                )
                                            )
                                            ->pluck(
                                                'school_year',
                                                'school_year'
                                            )
                                            ->map(fn ($year): string => (string) $year)
                                            ->toArray();
                                    }

                                    return [];
                                })
                                ->default(
                                    fn () => GeneralSetting::first()->getSchoolYear()
                                )
                                ->reactive()
                                ->afterStateUpdated(
                                    fn ($state, $set) => $set(
                                        'selectedSchoolYear',
                                        $state
                                    )
                                )
                                ->afterStateHydrated(
                                    fn ($state, $set) => $set(
                                        'selectedSchoolYear',
                                        $state
                                    )
                                ),
                            Select::make('semester')
                                ->label('Semester')
                                ->options([
                                    '1' => '1st Semester',
                                    '2' => '2nd Semester',
                                ])
                                ->default(
                                    fn () => GeneralSetting::first()->semester
                                )
                                ->reactive()
                                ->afterStateUpdated(
                                    fn ($state, $set) => $set(
                                        'selectedSemester',
                                        $state
                                    )
                                )
                                ->afterStateHydrated(
                                    fn ($state, $set) => $set(
                                        'selectedSemester',
                                        $state
                                    )
                                ),
                            Placeholder::make('Student Name')
                                ->visible(
                                    fn ($get) => $get(
                                        'selectedStudent'
                                    )->StudentTuition->isEmpty()
                                )
                                ->label('Student Name')
                                ->columnSpanFull()
                                ->content(
                                    fn ($get): HtmlString => new HtmlString(
                                        '<span class=""> This Student Does Not Have any Balances in this Semester</span>'
                                    )
                                ),
                            Fieldset::make('Student Balance')
                                ->visible(
                                    fn ($get) => $get(
                                        'selectedStudent'
                                    )->StudentTuition->isNotEmpty()
                                )
                                ->schema([
                                    Actions::make([
                                        Action::make('edit_tuition')
                                            ->label('Edit Tuition')
                                            ->icon('heroicon-o-pencil')
                                            ->color('primary')
                                            ->visible(fn ($get): bool => $get('selectedStudent') && $get('selectedSchoolYear') && $get('selectedSemester'))
                                            ->form(function ($get): array {
                                                $student = $get('selectedStudent');
                                                $schoolYear = $get('selectedSchoolYear');
                                                $semester = $get('selectedSemester');

                                                if (! $student || ! $schoolYear || ! $semester) {
                                                    return [];
                                                }

                                                $tuition = $student->StudentTuition
                                                    ->where('school_year', $schoolYear)
                                                    ->where('semester', $semester)
                                                    ->first();

                                                if (! $tuition) {
                                                    return [];
                                                }

                                                return [
                                                    Grid::make(2)->schema([
                                                        TextInput::make('total_lectures')
                                                            ->label('Tuition Fee (Lecture Fee)')
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->default($tuition->total_lectures)
                                                            ->reactive()
                                                            ->afterStateUpdated(function ($state, $set, $get): void {
                                                                $this->calculateTotalTuition($set, $get);
                                                            }),

                                                        TextInput::make('total_laboratory')
                                                            ->label('Laboratory Fee')
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->default($tuition->total_laboratory)
                                                            ->reactive()
                                                            ->afterStateUpdated(function ($state, $set, $get): void {
                                                                $this->calculateTotalTuition($set, $get);
                                                            }),

                                                        TextInput::make('total_miscelaneous_fees')
                                                            ->label('Registration Fee (Miscellaneous)')
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->default($tuition->total_miscelaneous_fees)
                                                            ->reactive()
                                                            ->afterStateUpdated(function ($state, $set, $get): void {
                                                                $this->calculateTotalTuition($set, $get);
                                                            }),

                                                        TextInput::make('discount')
                                                            ->label('Discount (%)')
                                                            ->numeric()
                                                            ->suffix('%')
                                                            ->default($tuition->discount)
                                                            ->reactive()
                                                            ->afterStateUpdated(function ($state, $set, $get): void {
                                                                $this->calculateTotalTuition($set, $get);
                                                            }),

                                                        TextInput::make('downpayment')
                                                            ->label('Downpayment')
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->default($tuition->downpayment)
                                                            ->reactive()
                                                            ->afterStateUpdated(function ($state, $set, $get): void {
                                                                $this->calculateTotalBalance($set, $get);
                                                            }),

                                                        Placeholder::make('calculated_values')
                                                            ->label('Calculated Values')
                                                            ->columnSpanFull()
                                                            ->content(function ($get): HtmlString {
                                                                $lectures = (float) ($get('total_lectures') ?? 0);
                                                                $laboratory = (float) ($get('total_laboratory') ?? 0);
                                                                $miscellaneous = (float) ($get('total_miscelaneous_fees') ?? 0);
                                                                $discount = (float) ($get('discount') ?? 0);
                                                                $downpayment = (float) ($get('downpayment') ?? 0);

                                                                $subtotal = $lectures + $laboratory + $miscellaneous;
                                                                $discountAmount = $subtotal * ($discount / 100);
                                                                $totalTuition = $subtotal - $discountAmount;
                                                                $totalBalance = $totalTuition - $downpayment;

                                                                return new HtmlString('
                                                                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg space-y-2">
                                                                        <div class="flex justify-between">
                                                                            <span class="font-medium">Subtotal:</span>
                                                                            <span>₱ '.number_format($subtotal, 2).'</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="font-medium">Discount Amount:</span>
                                                                            <span>₱ '.number_format($discountAmount, 2).'</span>
                                                                        </div>
                                                                        <div class="flex justify-between font-bold text-lg border-t pt-2">
                                                                            <span>Total Tuition:</span>
                                                                            <span>₱ '.number_format($totalTuition, 2).'</span>
                                                                        </div>
                                                                        <div class="flex justify-between font-bold text-lg text-yellow-600">
                                                                            <span>Total Balance:</span>
                                                                            <span>₱ '.number_format($totalBalance, 2).'</span>
                                                                        </div>
                                                                    </div>
                                                                ');
                                                            }),
                                                    ]),
                                                ];
                                            })
                                            ->action(function (array $data, $get): void {
                                                $student = $get('selectedStudent');
                                                $schoolYear = $get('selectedSchoolYear');
                                                $semester = $get('selectedSemester');

                                                if (! $student || ! $schoolYear || ! $semester) {
                                                    return;
                                                }

                                                $tuition = $student->StudentTuition
                                                    ->where('school_year', $schoolYear)
                                                    ->where('semester', $semester)
                                                    ->first();

                                                if ($tuition) {
                                                    // Calculate values
                                                    $lectures = (float) $data['total_lectures'];
                                                    $laboratory = (float) $data['total_laboratory'];
                                                    $miscellaneous = (float) $data['total_miscelaneous_fees'];
                                                    $discount = (float) $data['discount'];
                                                    $downpayment = (float) $data['downpayment'];

                                                    $subtotal = $lectures + $laboratory + $miscellaneous;
                                                    $discountAmount = $subtotal * ($discount / 100);
                                                    $totalTuition = $subtotal - $discountAmount;
                                                    $totalBalance = $totalTuition - $downpayment;

                                                    // Update the tuition record
                                                    $tuition->update([
                                                        'total_lectures' => $lectures,
                                                        'total_laboratory' => $laboratory,
                                                        'total_miscelaneous_fees' => $miscellaneous,
                                                        'discount' => $discount,
                                                        'total_tuition' => $totalTuition,
                                                        'overall_tuition' => $totalTuition,
                                                        'downpayment' => $downpayment,
                                                        'total_balance' => $totalBalance,
                                                        'status' => $totalBalance <= 0 ? 'Fully Paid' : 'Not Fully Paid',
                                                    ]);

                                                    Notification::make()
                                                        ->title('Tuition Updated Successfully')
                                                        ->success()
                                                        ->send();
                                                }
                                            }),
                                    ])->columnSpanFull(),

                                    Placeholder::make('Student Balance')
                                        ->label('Current Balance Summary')
                                        ->columnSpanFull()
                                        ->content(function ($get): HtmlString {
                                            $student = $get('selectedStudent');
                                            $schoolYear = $get('selectedSchoolYear');
                                            $semester = $get('selectedSemester');

                                            if (! $student || ! $schoolYear || ! $semester) {
                                                return new HtmlString('<span class="text-gray-500">N/A</span>');
                                            }

                                            $studentTuition = $student->StudentTuition
                                                ->where('school_year', $schoolYear)
                                                ->where('semester', $semester);

                                            if ($studentTuition->isEmpty()) {
                                                return new HtmlString('<span class="text-gray-500">N/A</span>');
                                            }

                                            $html = '<div class="overflow-x-auto">
                                                        <table class="w-full border-collapse border border-gray-300 dark:border-gray-600">
                                                            <thead>
                                                                <tr class="bg-gray-50 dark:bg-gray-800">
                                                                    <th class="border border-gray-300 dark:border-gray-600 py-2 px-4 text-left font-medium">Description</th>
                                                                    <th class="border border-gray-300 dark:border-gray-600 py-2 px-4 text-left font-medium">Value</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>';

                                            foreach ($studentTuition as $tuition) {
                                                $html .= '
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-medium">Tuition Fee (Lecture Fee)</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">₱ '.number_format((float) ($tuition->total_lectures ?? 0), 2).'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-medium">Laboratory Fee</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">₱ '.number_format((float) ($tuition->total_laboratory ?? 0), 2).'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-medium">Registration Fee (Miscellaneous)</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">₱ '.number_format((float) ($tuition->total_miscelaneous_fees ?? 0), 2).'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-medium">Discount</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">'.$tuition->discount.'%</td>
                                                    </tr>
                                                    <tr class="bg-blue-50 dark:bg-blue-900/20">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-bold">Total Tuition Fee</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-bold">₱ '.number_format((float) ($tuition->total_tuition ?? 0), 2).'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-medium">Downpayment</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">₱ '.number_format((float) ($tuition->downpayment ?? 0), 2).'</td>
                                                    </tr>
                                                    <tr class="bg-yellow-50 dark:bg-yellow-900/20">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-bold text-lg">Total Balance</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 font-bold text-lg">₱ '.number_format((float) ($tuition->total_balance ?? 0), 2).'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">Status</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">
                                                            <span class="px-2 py-1 rounded-full text-xs '.
                                                                ($tuition->total_balance <= 0
                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-200 dark:text-green-900'
                                                                    : 'bg-red-100 text-red-800 dark:bg-red-200 dark:text-red-900').'">
                                                                '.$tuition->status.'
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">Semester</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">'.$tuition->semester.'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">School Year</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">'.$tuition->school_year.'</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">Academic Year</td>
                                                        <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">'.$tuition->academic_year.'</td>
                                                    </tr>';
                                            }

                                            $html .= '</tbody></table></div>';

                                            return new HtmlString($html);
                                        }),
                                ]),
                        ]),
                        Tabs\Tab::make('History')->schema([
                            Section::make()
                                ->schema([
                                    Fieldset::make('Tuition History Filters')
                                        ->schema([
                                            Select::make('tuition_status_filter')
                                                ->label('Payment Status')
                                                ->options([
                                                    '' => 'All',
                                                    'paid' => 'Fully Paid',
                                                    'unpaid' => 'Not Fully Paid',
                                                ])
                                                ->default('')
                                                ->reactive(),
                                            Select::make('tuition_sort_direction')
                                                ->label('Order')
                                                ->options([
                                                    'desc' => 'Latest First',
                                                    'asc' => 'Oldest First',
                                                ])
                                                ->default('desc')
                                                ->reactive(),
                                        ])
                                        ->columns(2),
                                    Placeholder::make('student_tuition_table')
                                        ->columnSpanFull()
                                        ->content(function ($record, $get): HtmlString {
                                            $student = $get('selectedStudent');

                                            if (! $student) {
                                                return new HtmlString(
                                                    '<div class="p-4 text-center text-gray-500">Please select a student to view tuition history</div>'
                                                );
                                            }

                                            $statusFilter = $get('tuition_status_filter') ?? '';
                                            $sortDirection = $get('tuition_sort_direction') ?? 'desc';

                                            $studentTuition = $student->StudentTuition;

                                            // Apply status filter if selected
                                            if ($statusFilter === 'paid') {
                                                $studentTuition = $studentTuition->filter(fn ($tuition): bool => $tuition->total_balance <= 0);
                                            } elseif ($statusFilter === 'unpaid') {
                                                $studentTuition = $studentTuition->filter(fn ($tuition): bool => $tuition->total_balance > 0);
                                            }

                                            // Apply sorting
                                            $studentTuition = $sortDirection === 'desc'
                                                ? $studentTuition->sortByDesc('created_at')
                                                : $studentTuition->sortBy('created_at');

                                            if ($studentTuition->isEmpty()) {
                                                return new HtmlString(
                                                    '<div class="p-6 text-center bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                                        </svg>
                                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No tuition records found</h3>
                                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This student does not have any tuition records matching your filters.</p>
                                                    </div>'
                                                );
                                            }

                                            $html = '<div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                                        <tr>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">School Year</th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Semester</th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Tuition</th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                                            foreach ($studentTuition as $tuition) {
                                                $html .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">'.
                                                        $tuition->school_year.
                                                    '</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">'.
                                                        $tuition->formatted_semester.
                                                    '</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">'.
                                                        $tuition->formatted_overall_tuition.
                                                    '</td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex flex-col space-y-1">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">'.
                                                                $tuition->formatted_total_balance.
                                                            '</div>
                                                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                                                <div class="bg-primary-600 h-2.5 rounded-full" style="width: '.$tuition->payment_progress.'%"></div>
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">'.
                                                                $tuition->payment_progress.'% paid'.
                                                            '</div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full '.$tuition->status_class.'">
                                                            '.$tuition->payment_status.'
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <button type="button"
                                                                onclick="showTuitionDetails('.$tuition->id.')"
                                                                class="text-primary-600 hover:text-primary-900 dark:text-primary-500 dark:hover:text-primary-400 font-medium">
                                                            View Details
                                                        </button>
                                                    </td>
                                                </tr>';

                                                // Add hidden details row that will be shown/hidden with JavaScript
                                                $html .= '<tr id="tuition-details-'.$tuition->id.'" class="hidden bg-gray-50 dark:bg-gray-800/50">
                                                    <td colspan="6" class="px-6 py-4">
                                                        <div class="grid grid-cols-2 gap-4">
                                                            <div>
                                                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Tuition Breakdown</h4>
                                                                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                                                    <dt class="text-gray-500 dark:text-gray-400">Lecture Fee:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_total_lectures.'</dd>

                                                                    <dt class="text-gray-500 dark:text-gray-400">Discount:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_discount.'</dd>

                                                                    <dt class="text-gray-500 dark:text-gray-400">Laboratory Fee:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_total_laboratory.'</dd>

                                                                    <dt class="text-gray-500 dark:text-gray-400">Miscellaneous:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_total_miscelaneous_fees.'</dd>
                                                                </dl>
                                                            </div>
                                                            <div>
                                                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Payment Information</h4>
                                                                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                                                    <dt class="text-gray-500 dark:text-gray-400">Total Tuition:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_total_tuition.'</dd>

                                                                    <dt class="text-gray-500 dark:text-gray-400">Overall Total:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_overall_tuition.'</dd>

                                                                    <dt class="text-gray-500 dark:text-gray-400">Downpayment:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100">'.$tuition->formatted_downpayment.'</dd>

                                                                    <dt class="text-gray-500 dark:text-gray-400">Remaining Balance:</dt>
                                                                    <dd class="text-gray-900 dark:text-gray-100 font-medium">'.$tuition->formatted_total_balance.'</dd>
                                                                </dl>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>';
                                            }

                                            $html .= '</tbody></table></div>';

                                            // Add JavaScript for toggling details
                                            $html .= '<script>
                                                function showTuitionDetails(id) {
                                                    const detailsRow = document.getElementById(`tuition-details-${id}`);
                                                    if (detailsRow.classList.contains("hidden")) {
                                                        detailsRow.classList.remove("hidden");
                                                    } else {
                                                        detailsRow.classList.add("hidden");
                                                    }
                                                }
                                            </script>';

                                            return new HtmlString($html);
                                        }),
                                ])
                                ->columnSpanFull(),
                        ]),
                        Tabs\Tab::make('Transactions')->schema([
                            Section::make()
                                ->schema([
                                    Fieldset::make('Transaction Filters')
                                        ->schema([
                                            Select::make('sort_field')
                                                ->label('Sort By')
                                                ->options([
                                                    'created_at' => 'Date',
                                                    'invoicenumber' => 'OR Number',
                                                    'description' => 'Description',
                                                    'status' => 'Status',
                                                ])
                                                ->default('created_at')
                                                ->reactive(),
                                            Select::make('sort_direction')
                                                ->label('Order')
                                                ->options([
                                                    'desc' => 'Descending',
                                                    'asc' => 'Ascending',
                                                ])
                                                ->default('desc')
                                                ->reactive(),
                                            Select::make('status_filter')
                                                ->label('Status')
                                                ->options([
                                                    '' => 'All',
                                                    'Paid' => 'Paid',
                                                    'Pending' => 'Pending',
                                                    'Cancelled' => 'Cancelled',
                                                ])
                                                ->default('')
                                                ->reactive(),
                                        ])
                                        ->columns(3),
                                    Placeholder::make('transactions_table')
                                        ->columnSpanFull()
                                        ->content(function ($record, $get): HtmlString {
                                            $student = $get('selectedStudent');

                                            if (! $student) {
                                                return new HtmlString(
                                                    '<div class="p-4 text-center text-gray-500">Please select a student to view transactions</div>'
                                                );
                                            }

                                            // Get sort parameters
                                            $sortField = $get('sort_field') ?? 'created_at';
                                            $sortDirection = $get('sort_direction') ?? 'desc';
                                            $statusFilter = $get('status_filter') ?? '';

                                            // Get transactions with sorting and filtering
                                            $transactionIds = $student->studentTransactions()
                                                ->pluck('transaction_id');

                                            $query = Transaction::whereIn('id', $transactionIds);

                                            // Apply status filter if selected
                                            if (! empty($statusFilter)) {
                                                $query->where('status', $statusFilter);
                                            }

                                            // Apply sorting
                                            $query->sort($sortField, $sortDirection);

                                            // Get transactions
                                            $transactions = $query->take(15)->get();

                                            if ($transactions->isEmpty()) {
                                                return new HtmlString(
                                                    '<div class="p-6 text-center bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                                        </svg>
                                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No transactions found</h3>
                                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This student does not have any transactions yet.</p>
                                                    </div>'
                                                );
                                            }

                                            $html = '<div class="w-full overflow-x-auto shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                                                <div class="inline-block min-w-full align-middle">
                                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                                            <tr>
                                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">Date</th>
                                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">OR No.</th>
                                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">Description</th>
                                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">Amount</th>
                                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">Status</th>
                                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                                            foreach ($transactions as $transaction) {
                                                $statusClass = match ($transaction->status) {
                                                    'Paid' => 'bg-green-100 text-green-800 dark:bg-green-200 dark:text-green-900',
                                                    'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-200 dark:text-yellow-900',
                                                    'Cancelled' => 'bg-red-100 text-red-800 dark:bg-red-200 dark:text-red-900',
                                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-200 dark:text-gray-900'
                                                };

                                                $html .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">'.
                                                        $transaction->created_at->format('M d, Y h:i A').
                                                    '</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">'.
                                                        $transaction->invoicenumber.
                                                    '</td>
                                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" title="'.$transaction->description.'">'.
                                                        $transaction->description.
                                                    '</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">₱ '.
                                                        number_format((float) ($transaction->total_amount ?? 0), 2).
                                                    '</td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full '.$statusClass.'">
                                                            '.$transaction->status.'
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <div class="flex space-x-2">
                                                            <a href="/transactions/'.$transaction->transaction_number.'"
                                                               class="text-primary-600 hover:text-primary-900 dark:text-primary-500 dark:hover:text-primary-400 font-medium text-xs"
                                                               target="_blank">
                                                                View
                                                            </a>
                                                            <a href="/transactions/'.$transaction->transaction_number.'/print"
                                                               class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 font-medium text-xs"
                                                               target="_blank">
                                                                Print
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>';
                                            }

                                            $html .= '</tbody></table></div></div>';

                                            // Add pagination info
                                            $html .= '<div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                                Showing up to 15 most recent transactions.
                                                <a href="/admin/transactions?student_id='.$student->id.'" class="text-primary-600 hover:underline">
                                                    View all transactions
                                                </a>
                                            </div>';

                                            return new HtmlString($html);
                                        }),
                                ])
                                ->columnSpanFull(),
                        ]),
                    ]),
                Fieldset::make('Add Transaction')
                    ->visible(fn ($get): bool => $get('selectedStudent') !== null)
                    ->schema(function ($record): array {
                        $formComponents = [
                            TextInput::make('invoicenumber')
                                ->label('Invoice Number')
                                ->numeric()
                                ->helperText(
                                    'Enter the invoice number for the transaction.'
                                )
                                ->required(),
                            KeyValue::make('settlements')
                                ->label('Settlements')
                                ->columnSpanFull()
                                ->helperText(
                                    'Enter the settlements for the following.'
                                )
                                ->default([
                                    'registration_fee' => 0,
                                    'tuition_fee' => 0,
                                    'miscelanous_fee' => 0,
                                    'diploma_or_certificate' => 0,
                                    'transcript_of_records' => 0,
                                    'certification' => 0,
                                    'special_exam' => 0,
                                    'others' => 0,
                                ])
                                ->reorderable()
                                ->editableKeys(false)
                                ->keyLabel('Particulars')
                                ->deletable(false)
                                ->addable(false)
                                ->valueLabel('Ammounts')
                                ->required(),
                            Textarea::make('description')
                                ->label('Description')
                                ->helperText(
                                    'Enter a description for the transaction.'
                                )
                                ->required()
                                ->columnSpanFull(),
                        ];

                        return $formComponents;
                    }),
            ])

            ->columns(3)
            ->statePath('data');
    }

    public function getFormActions(): array
    {
        return [Action::make('create')->label('Create')->action('create')];
    }

    public function create(): void
    {
        // this is slow i think but too lazy to optimize it
        $student = $this->data['selectedStudent'];
        // $amount = $this->data['amount'];
        $description = $this->data['description'];
        $schoolYear = $this->data['selectedSchoolYear'];
        $semester = $this->data['selectedSemester'];
        // $transactionType = $this->data['transaction_type'];
        $settlements = $this->data['settlements'];
        $invoicenumber = $this->data['invoicenumber'];
        if (
            isset($settlements['tuition_fee']) &&
            $settlements['tuition_fee'] > 0
        ) {
            $studentTuition = $student->StudentTuition
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->first();
            $studentTuition->total_balance -= $settlements['tuition_fee'];
            $studentTuition->status = 'Paid';
            $studentTuition->save();
        }

        // create the transaction
        $transaction = Transaction::create([
            'description' => $description,
            'settlements' => $settlements,
            'status' => 'Paid',
            'invoicenumber' => $invoicenumber,
            'signature' => GeneralSetting::first()->enable_signatures === false
                    ? null
                    : $this->data['signature'],
        ]);
        $student->studentTransactions()->create([
            'transaction_id' => $transaction->id,
            'status' => 'Paid',
        ]);

        // Create admin transaction record
        \App\Models\AdminTransaction::create([
            'admin_id' => Auth::id(),
            'transaction_id' => $transaction->id,
            'status' => 'Paid',
        ]);

        Notification::make('success')
            ->title('Transaction Created')
            ->body('Transaction has been created successfully')
            ->sendToDatabase(Auth::user())
            ->send();

        // NotificationFacade::route("mail", $student->email)->notify(
        //     new InvoiceTransact($transaction, $student)
        // );
    }

    public function saveStudentTuition(): void
    {
        if ($this->studentTuitionData === []) {
            Notification::make()
                ->title('No tuition data to save.')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->studentTuitionData as $tuitionData) {
            $tuition = \App\Models\StudentTuition::find($tuitionData['id']);
            if ($tuition) {
                // Recalculate totals before updating
                $totalTuition = $tuitionData['total_lectures'] - ($tuitionData['total_lectures'] * ($tuitionData['discount'] / 100));
                $overallTuition = $totalTuition + $tuitionData['total_laboratory'] + $tuitionData['total_miscelaneous_fees'];
                $totalBalance = $overallTuition - $tuitionData['downpayment'];

                $tuition->update([
                    'total_lectures' => $tuitionData['total_lectures'],
                    'discount' => $tuitionData['discount'],
                    'total_tuition' => $totalTuition,
                    'total_laboratory' => $tuitionData['total_laboratory'],
                    'total_miscelaneous_fees' => $tuitionData['total_miscelaneous_fees'],
                    'overall_tuition' => $overallTuition,
                    'downpayment' => $tuitionData['downpayment'],
                    'total_balance' => $totalBalance,
                ]);
            }
        }

        Notification::make()
            ->title('Tuition details saved successfully')
            ->success()
            ->send();

        // Refresh the data in the form
        $this->updateStudentTuitionData();
    }

    private function updateStudentTuitionData(): void
    {
        $state = $this->form->getState();
        $schoolYear = $state['selectedSchoolYear'] ?? GeneralSetting::first()->getSchoolYear();
        $semester = $state['selectedSemester'] ?? GeneralSetting::first()->semester;

        if ($this->selectedStudent instanceof \App\Models\Student) {
            $this->studentTuitionData = $this->selectedStudent->StudentTuition()
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->get()
                ->toArray();
        } else {
            $this->studentTuitionData = [];
        }

        // This ensures the form's data is updated with the new values
        $this->form->fill([
            'studentTuitionData' => $this->studentTuitionData,
        ]);
    }

    /**
     * Calculate total tuition based on fees and discount
     */
    private function calculateTotalTuition($set, $get): void
    {
        $lectures = (float) ($get('total_lectures') ?? 0);
        $laboratory = (float) ($get('total_laboratory') ?? 0);
        $miscellaneous = (float) ($get('total_miscelaneous_fees') ?? 0);
        $discount = (float) ($get('discount') ?? 0);

        $subtotal = $lectures + $laboratory + $miscellaneous;
        $discountAmount = $subtotal * ($discount / 100);
        $totalTuition = $subtotal - $discountAmount;

        $set('calculated_total_tuition', $totalTuition);

        // Also calculate balance if downpayment exists
        $this->calculateTotalBalance($set, $get);
    }

    /**
     * Calculate total balance based on tuition and downpayment
     */
    private function calculateTotalBalance($set, $get): void
    {
        $lectures = (float) ($get('total_lectures') ?? 0);
        $laboratory = (float) ($get('total_laboratory') ?? 0);
        $miscellaneous = (float) ($get('total_miscelaneous_fees') ?? 0);
        $discount = (float) ($get('discount') ?? 0);
        $downpayment = (float) ($get('downpayment') ?? 0);

        $subtotal = $lectures + $laboratory + $miscellaneous;
        $discountAmount = $subtotal * ($discount / 100);
        $totalTuition = $subtotal - $discountAmount;
        $totalBalance = $totalTuition - $downpayment;

        $set('calculated_total_balance', $totalBalance);
    }
}
