<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
// use Filament\Tables\Contracts\HasTable;
// use Filament\Actions\Contracts\HasActions;
// use Filament\Tables\Columns\Layout\Component;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PaymentTransactionTable extends Component implements HasActions, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    public StudentTuition $studentTuitionRecord;

    public function mount(StudentTuition $studentTuition): void
    {
        $this->studentTuitionRecord = $studentTuition;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StudentTransaction::query()->where('student_id', $this->studentTuitionRecord->student_id))
            ->columns([
                TextColumn::make('transaction.transaction_date')
                    ->label('Date')
                    ->date('M d, Y'),
                TextColumn::make('transaction.transaction_number')
                    ->label('Transaction No')
                    ->searchable(),
                TextColumn::make('transaction.description')
                    ->label('Description')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount Paid')
                    ->money('PHP'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Payment Date')
                    ->dateTime(),
                // ... other columns
            ])
            ->filters([
                // ... table filters
            ])
            ->recordActions([
                // ... table actions
            ])
            ->toolbarActions([
                // ... table bulk actions
            ]);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render(): View
    {
        return view('livewire.payment-transaction-table');
    }
}
