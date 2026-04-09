<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departments\Schemas;

use Exception;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DepartmentInfolist
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Department Name')
                            ->weight('semibold')
                            ->size('lg'),

                        TextEntry::make('code')
                            ->label('Department Code')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('school.name')
                            ->label('School')
                            ->weight('semibold')
                            ->icon('heroicon-o-academic-cap')
                            ->color('info'),

                        TextEntry::make('school.code')
                            ->label('School Code')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Section::make('Administrative Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('head_name')
                            ->label('Department Head')
                            ->icon('heroicon-o-user')
                            ->placeholder('No head assigned'),

                        TextEntry::make('head_email')
                            ->label('Head Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->placeholder('No email provided'),
                    ]),

                Section::make('Contact Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('location')
                            ->label('Location')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('No location specified'),

                        TextEntry::make('phone')
                            ->label('Phone Number')
                            ->icon('heroicon-o-phone')
                            ->copyable()
                            ->placeholder('No phone number provided'),

                        TextEntry::make('email')
                            ->label('Department Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->placeholder('No email provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Statistics')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

                        TextEntry::make('users_count')
                            ->label('Users')
                            ->getStateUsing(fn ($record) => $record->users()->count())
                            ->icon('heroicon-o-users')
                            ->suffix(' users'),

                        TextEntry::make('faculty_count')
                            ->label('Faculty')
                            ->getStateUsing(fn ($record) => $record->getFacultyCount())
                            ->icon('heroicon-o-user-group')
                            ->suffix(' faculty'),

                        TextEntry::make('courses_count')
                            ->label('Courses')
                            ->getStateUsing(fn ($record) => $record->getCoursesCount())
                            ->icon('heroicon-o-academic-cap')
                            ->suffix(' courses'),
                    ]),

                Section::make('Additional Information')
                    ->columns(1)
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('Metadata')
                            ->placeholder('No additional information')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->icon('heroicon-o-clock')
                            ->since(),
                    ])
                    ->collapsed(),
            ]);
    }
}
