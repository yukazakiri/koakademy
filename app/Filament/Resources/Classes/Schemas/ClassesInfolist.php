<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Schemas;

use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ClassesInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Class Information')
                    ->schema([
                        TextEntry::make('subjects')
                            ->label('Subjects')
                            ->formatStateUsing(function ($record) {
                                $subjects = $record->subjects;
                                if ($subjects->isEmpty()) {
                                    // Fallback to single subject for backward compatibility
                                    return $record->subject?->title ?? 'N/A';
                                }

                                return $subjects->pluck('title')->join(', ');
                            })
                            ->badge()
                            ->separator(', ')
                            ->columnSpanFull(),
                        TextEntry::make('formatted_course_codes')
                            ->label('Associated Courses')
                            ->badge()
                            ->separator(', ')
                            ->columnSpanFull(),
                        TextEntry::make('section')->label('Section'),
                        TextEntry::make('academic_year')
                            ->label('Year Level')
                            ->formatStateUsing(
                                fn (string $state): string => match ($state) {
                                    '1' => '1st Year',
                                    '2' => '2nd Year',
                                    '3' => '3rd Year',
                                    '4' => '4th Year',
                                    default => $state,
                                }
                            ),
                        TextEntry::make('semester')
                            ->label('Semester')
                            ->formatStateUsing(
                                fn (string $state): string => match ($state) {
                                    '1st' => '1st Semester',
                                    '2nd' => '2nd Semester',
                                    'summer' => 'Summer',
                                    default => $state,
                                }
                            ),
                        TextEntry::make('school_year')->label('School Year'),
                        TextEntry::make('student_count')
                            ->label('Enrolled Students')
                            ->formatStateUsing(
                                fn (
                                    $state,
                                    $record
                                ): string => sprintf('%s / %s', $record->class_enrollments_count, $record->maximum_slots)
                            )
                            ->color(
                                fn (
                                    string $state,
                                    $record
                                ): string => $record->class_enrollments_count <
                                    $record->maximum_slots
                                    ? 'success'
                                    : 'danger'
                            )
                            ->icon(
                                fn (
                                    string $state,
                                    $record
                                ): string => $record->class_enrollments_count <
                                    $record->maximum_slots
                                    ? 'heroicon-o-check-circle'
                                    : 'heroicon-o-exclamation-triangle'
                            ),
                    ])
                    ->columns(2),

                Section::make('Faculty Information')
                    ->schema([
                        ImageEntry::make('Faculty.avatar_url')
                            ->label('')
                            ->circular()
                            ->grow(false)
                            ->imageWidth(50)
                            ->imageHeight(50),
                        TextEntry::make('faculty.full_name')
                            ->label('Faculty')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Class Schedule')
                    ->schema([
                        TextEntry::make('formatted_weekly_schedule')
                            ->label('')
                            ->view('filament.infolists.class-schedule')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Class Settings')
                    ->description('Class customization and feature settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Fieldset::make('Visual Customization')
                            ->schema([
                                ColorEntry::make('settings.background_color')
                                    ->label('Background Color')
                                    ->copyable(),

                                ColorEntry::make('settings.accent_color')
                                    ->label('Accent Color')
                                    ->copyable(),

                                TextEntry::make('settings.theme')
                                    ->label('Theme')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'default')),

                                ImageEntry::make('settings.banner_image')
                                    ->label('Banner Image')
                                    ->height(100)
                                    ->visible(fn ($record): bool => filled($record->getSetting('banner_image'))),
                            ])
                            ->columns(3),

                        Fieldset::make('Feature Settings')
                            ->schema([
                                IconEntry::make('settings.enable_announcements')
                                    ->label('Announcements')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),

                                IconEntry::make('settings.enable_grade_visibility')
                                    ->label('Grade Visibility')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),

                                IconEntry::make('settings.enable_attendance_tracking')
                                    ->label('Attendance Tracking')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),

                                IconEntry::make('settings.allow_late_submissions')
                                    ->label('Late Submissions')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),

                                IconEntry::make('settings.enable_discussion_board')
                                    ->label('Discussion Board')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),
                            ])
                            ->columns(5),

                        Fieldset::make('Custom Preferences')
                            ->schema([
                                KeyValueEntry::make('settings.custom')
                                    ->label('Additional Settings')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn ($record): bool => filled($record->getSetting('custom'))),
                    ])
                    ->collapsible()
                    ->collapsed(),

            ]);
    }
}
