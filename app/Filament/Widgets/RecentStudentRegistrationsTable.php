<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class RecentStudentRegistrationsTable extends BaseWidget
{
    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Student Registrations';

    private static ?string $description = 'Latest student registrations across all types';

    private static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Student::query()
                    ->with(['Course'])
                    ->latest('created_at')
                    ->limit(20)
            )
            ->columns([
                Tables\Columns\TextColumn::make('student_id')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name'])
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('student_type')
                    ->label('Type')
                    ->badge()
                    ->color(function (StudentType|string $state): string {
                        $enum = $state instanceof StudentType ? $state : StudentType::tryFrom($state);

                        return match ($enum) {
                            StudentType::College => 'primary',
                            StudentType::SeniorHighSchool => 'success',
                            StudentType::TESDA => 'warning',
                            StudentType::DHRT => 'purple',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function (StudentType|string $state): string {
                        $enum = $state instanceof StudentType ? $state : StudentType::tryFrom($state);

                        return match ($enum) {
                            StudentType::College => 'College',
                            StudentType::SeniorHighSchool => 'SHS',
                            StudentType::TESDA => 'TESDA',
                            StudentType::DHRT => 'DHRT',
                            default => is_string($state) ? $state : 'Unknown',
                        };
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('Course.code')
                    ->label('Course')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('academic_year')
                    ->label('Year Level')
                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                        1 => '1st Year',
                        2 => '2nd Year',
                        3 => '3rd Year',
                        4 => '4th Year',
                        5 => 'Graduate',
                        default => 'Unknown',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M j, Y g:i A')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Student $record): string => route('filament.admin.resources.students.view', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No recent registrations')
            ->emptyStateDescription('Student registrations will appear here once they start enrolling.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
