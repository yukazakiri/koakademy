<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
// use Filament\Infolists\Components\Grid;
// use Filament\Infolists\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class StatementOfAccountRelationManager extends RelationManager
{
    protected static string $relationship = 'StudentTuition';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('school_year')
            ->heading('Statement of Account')
            ->description('Detailed breakdown of tuition fees and payments')
            ->columns([
                TextColumn::make('school_year')
                    ->label('Academic Year')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('semester')
                    ->formatStateUsing(
                        fn ($state): string => match ($state) {
                            1 => '1st Semester',
                            2 => '2nd Semester',
                            default => 'Summer',
                        }
                    )
                    // ->badge()
                    ->color('gray'),

                TextColumn::make('overall_tuition')
                    ->label('Total Assessment')
                    ->money('PHP')
                    ->weight(FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Assessment Date')
                    ->date('M d, Y')
                    ->color('gray'),

                TextColumn::make('total_balance')
                    ->label('Remaining Balance')
                    ->money('PHP')
                    ->color(
                        fn ($record): string => $record->total_balance > 0
                            ? 'danger'
                            : 'success'
                    ),
            ])

            ->headerActions([
                CreateAction::make()
                    ->label('Create New Assessment')
                    ->modalHeading('Create New Tuition Assessment')
                    ->schema([
                        Select::make('semester')
                            ->options([
                                1 => '1st Semester',
                                2 => '2nd Semester',
                                3 => 'Summer',
                            ])
                            ->required(),

                        Select::make('school_year')
                            ->options(function (): array {
                                $years = [];
                                for (
                                    $i = date('Y') - 5;
                                    $i <= date('Y') + 5;
                                    $i++
                                ) {
                                    $years[$i.' - '.($i + 1)] =
                                        $i.' - '.($i + 1);
                                }

                                return $years;
                            })
                            ->required(),

                        TextInput::make('total_tuition')
                            ->numeric()
                            ->prefix('₱')
                            ->required(),

                        TextInput::make('total_lectures')
                            ->numeric()
                            ->prefix('₱')
                            ->required(),

                        TextInput::make('total_laboratory')
                            ->numeric()
                            ->prefix('₱')
                            ->required(),

                        TextInput::make(
                            'total_miscelaneous_fees'
                        )
                            ->label('Miscellaneous Fees')
                            ->numeric()
                            ->prefix('₱')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                Action::make('payments')
                    ->label('View Payments')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalHeading('Payment Transactions')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->schema([
                        Section::make('Payment History')->schema([
                            RepeatableEntry::make('studentTransactions')
                                ->label(false)
                                ->schema([
                                    Grid::make(4)->schema([
                                        TextEntry::make(
                                            'transaction.transaction_date'
                                        )
                                            ->label('Date')
                                            ->date('M d, Y'),

                                        TextEntry::make(
                                            'transaction.invoicenumber'
                                        )
                                            ->label('Invoice No.')
                                            ->copyable()
                                            ->badge()
                                            ->color('primary'),

                                        TextEntry::make(
                                            'transaction.total_amount'
                                        )
                                            ->label('Amount Paid (Settled)')
                                            ->money('PHP'),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(
                                                fn (
                                                    string $state
                                                ): string => match ($state) {
                                                    'Paid' => 'success',
                                                    'Pending' => 'warning',
                                                    'Failed' => 'danger',
                                                    default => 'gray',
                                                }
                                            ),
                                    ]),
                                    TextEntry::make('transaction.description')
                                        ->label('Description')
                                        ->columnSpanFull(),
                                ])
                                ->columns(1)
                                ->default(
                                    fn ($record) => $record->studentTransactions
                                ),
                        ]),
                    ]),

                EditAction::make()->schema([
                    TextInput::make('total_balance')
                        ->numeric()
                        ->prefix('₱')
                        ->required(),
                    TextInput::make('overall_tuition')
                        ->numeric()
                        ->prefix('₱')
                        ->required(),

                    Select::make('status')
                        ->options([
                            'paid' => 'Paid',
                            'partial' => 'Partial Payment',
                            'unpaid' => 'Unpaid',
                        ])
                        ->required(),
                ]),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No tuition assessments found')
            ->emptyStateDescription(
                'Click the button below to create a new assessment'
            )
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Create Assessment')
                    ->button(),
            ]);
    }

    public function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fee Breakdown')->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('total_lectures')
                            ->label('Lecture Fees')
                            ->money('PHP'),

                        TextEntry::make('total_laboratory')
                            ->label('Lab Fees')
                            ->money('PHP'),

                        TextEntry::make('total_miscelaneous_fees')
                            ->label('Miscellaneous')
                            ->money('PHP'),
                    ]),

                    Grid::make(2)->schema([
                        TextEntry::make('total_tuition')
                            ->label('Total Assessment')
                            ->money('PHP')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('total_balance')
                            ->label('Remaining Balance')
                            ->money('PHP')
                            ->color(
                                fn ($state): string => $state > 0
                                    ? 'danger'
                                    : 'success'
                            ),
                    ]),
                ]),

                Section::make('Payment History')
                    ->description(
                        'List of all transactions related to this assessment'
                    )
                    ->schema([
                        RepeatableEntry::make('studentTransactions')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('transaction_date')->date(
                                        'M d, Y'
                                    ),

                                    TextEntry::make('transaction_number')->label(
                                        'Transaction No'
                                    ),

                                    TextEntry::make('amount')->money('PHP'),

                                    TextEntry::make('status')->badge()->color(
                                        fn ($state): string => match ($state) {
                                            'paid' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger',
                                            default => 'gray',
                                        }
                                    ),

                                    TextEntry::make('remarks')->limit(30),
                                ]),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }
}
