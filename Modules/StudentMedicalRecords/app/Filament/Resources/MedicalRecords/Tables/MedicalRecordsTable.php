<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class MedicalRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('👤 Student Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => 'ID: '.($record->student->student_id ?? 'N/A')),

                TextColumn::make('record_type')
                    ->label('📝 Type')
                    ->badge()
                    ->color(function ($state): string {
                        $type = $state instanceof \Modules\StudentMedicalRecords\Enums\MedicalRecordType ? $state->value : $state;

                        return match ($type) {
                            'checkup' => 'primary',
                            'vaccination' => 'success',
                            'allergy' => 'warning',
                            'medication' => 'info',
                            'emergency' => 'danger',
                            'dental' => 'secondary',
                            'vision' => 'primary',
                            'mental_health' => 'purple',
                            'laboratory' => 'gray',
                            'surgery' => 'danger',
                            'follow_up' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state): string {
                        $type = $state instanceof \Modules\StudentMedicalRecords\Enums\MedicalRecordType ? $state->value : $state;

                        return match ($type) {
                            'checkup' => '🏥 Checkup',
                            'vaccination' => '💉 Vaccination',
                            'allergy' => '⚠️ Allergy',
                            'medication' => '💊 Medication',
                            'emergency' => '🚨 Emergency',
                            'dental' => '🦷 Dental',
                            'vision' => '👁️ Vision',
                            'mental_health' => '🧠 Mental Health',
                            'laboratory' => '🧪 Lab Test',
                            'surgery' => '🏥 Surgery',
                            'follow_up' => '🔄 Follow-up',
                            default => ucfirst($type),
                        };
                    }),

                TextColumn::make('title')
                    ->label('📋 Title')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return mb_strlen($state) > 40 ? $state : null;
                    }),

                TextColumn::make('visit_date')
                    ->label('📅 Visit Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->description(fn ($record) => $record->visit_date->diffForHumans()),

                TextColumn::make('status')
                    ->label('📊 Status')
                    ->badge()
                    ->color(function ($state): string {
                        $status = $state instanceof \Modules\StudentMedicalRecords\Enums\MedicalRecordStatus ? $state->value : $state;

                        return match ($status) {
                            'active' => 'success',
                            'resolved' => 'primary',
                            'ongoing' => 'warning',
                            'cancelled' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state): string {
                        $status = $state instanceof \Modules\StudentMedicalRecords\Enums\MedicalRecordStatus ? $state->value : $state;

                        return match ($status) {
                            'active' => '✅ Active',
                            'resolved' => '✅ Resolved',
                            'ongoing' => '🔄 Ongoing',
                            'cancelled' => '❌ Cancelled',
                            default => ucfirst($status),
                        };
                    }),

                TextColumn::make('priority')
                    ->label('⚠️ Priority')
                    ->badge()
                    ->color(function ($state): string {
                        $priority = $state instanceof \Modules\StudentMedicalRecords\Enums\MedicalRecordPriority ? $state->value : $state;

                        return match ($priority) {
                            'low' => 'gray',
                            'normal' => 'primary',
                            'high' => 'warning',
                            'urgent' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state): string {
                        $priority = $state instanceof \Modules\StudentMedicalRecords\Enums\MedicalRecordPriority ? $state->value : $state;

                        return match ($priority) {
                            'low' => '🟢 Low',
                            'normal' => '🟡 Normal',
                            'high' => '🟠 High',
                            'urgent' => '🔴 Urgent',
                            default => ucfirst($priority),
                        };
                    }),

                IconColumn::make('is_confidential')
                    ->label('🔒 Confidential')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('📝 Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('record_type')
                    ->label('📝 Record Type')
                    ->options([
                        'checkup' => '🏥 Regular Checkup',
                        'vaccination' => '💉 Vaccination',
                        'allergy' => '⚠️ Allergy/Allergic Reaction',
                        'medication' => '💊 Medication/Prescription',
                        'emergency' => '🚨 Emergency Visit',
                        'dental' => '🦷 Dental Care',
                        'vision' => '👁️ Vision/Eye Care',
                        'mental_health' => '🧠 Mental Health',
                        'laboratory' => '🧪 Lab Test Results',
                        'surgery' => '🏥 Surgery/Procedure',
                        'follow_up' => '🔄 Follow-up Visit',
                    ])
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('📊 Status')
                    ->options([
                        'active' => '✅ Active (ongoing treatment)',
                        'resolved' => '✅ Resolved (fully recovered)',
                        'ongoing' => '🔄 Ongoing (still being treated)',
                        'cancelled' => '❌ Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('priority')
                    ->label('⚠️ Priority Level')
                    ->options([
                        'low' => '🟢 Low (routine care)',
                        'normal' => '🟡 Normal (standard care)',
                        'high' => '🟠 High (needs attention)',
                        'urgent' => '🔴 Urgent (immediate attention)',
                    ])
                    ->multiple(),

                TernaryFilter::make('is_confidential')
                    ->label('🔒 Confidential Records')
                    ->placeholder('All records')
                    ->trueLabel('Confidential only')
                    ->falseLabel('Non-confidential only'),

                SelectFilter::make('student_id')
                    ->label('👤 Student')
                    ->relationship('student', 'first_name')
                    ->searchable()
                    ->preload(),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make()
                    ->label('👁️ View'),
                EditAction::make()
                    ->label('✏️ Edit'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('🗑️ Delete Selected'),
                ]),
            ])
            ->defaultSort('visit_date', 'desc')
            ->poll('30s')
            ->emptyStateHeading('No medical records found')
            ->emptyStateDescription('Start by creating a new medical record for a student.')
            ->emptyStateIcon('heroicon-o-heart');
    }
}
