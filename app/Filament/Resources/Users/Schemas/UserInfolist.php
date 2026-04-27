<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Exception;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class UserInfolist
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('avatar_url')
                            ->label('')
                            ->circular()
                            ->defaultImageUrl(fn (User $record): string => 'https://www.gravatar.com/avatar/'.md5(mb_strtolower(mb_trim($record->email))).'?d=mp&r=g&s=250')
                            ->size(100)
                            ->columnSpan(1),
                        TextEntry::make('name')
                            ->label('Full Name')
                            ->weight('bold')
                            ->size('lg')
                            ->columnSpan(2),
                        TextEntry::make('email')
                            ->label('Email Address')
                            ->copyable()
                            ->icon('heroicon-m-envelope')
                            ->columnSpan(2),
                    ]),

                Section::make('Organizational Assignment')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('school.name')
                            ->label('School')
                            ->icon('heroicon-o-academic-cap')
                            ->badge()
                            ->color('info')
                            ->placeholder('No school assigned'),
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->icon('heroicon-o-building-office')
                            ->badge()
                            ->color('primary')
                            ->placeholder('No department assigned'),
                        TextEntry::make('faculty_id_number')
                            ->label('Faculty ID')
                            ->icon('heroicon-o-identification')
                            ->placeholder('Not assigned')
                            ->copyable(),
                        TextEntry::make('record_id')
                            ->label('External Record ID')
                            ->icon('heroicon-o-link')
                            ->placeholder('Not linked')
                            ->copyable(),
                    ]),

                Section::make('Access & Role')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('role')
                            ->badge()
                            ->label('Primary Role'),
                        TextEntry::make('roles.name')
                            ->label('Permission Roles')
                            ->badge()
                            ->color('gray')
                            ->separator(', ')
                            ->placeholder('No additional permissions'),
                        TextEntry::make('view_title_course')
                            ->label('Access Level')
                            ->icon('heroicon-m-key'),
                        TextEntry::make('role')
                            ->label('Authority Level')
                            ->formatStateUsing(fn (User $record): string => 'Level '.$record->role->getHierarchyLevel())
                            ->icon('heroicon-m-shield-check'),
                    ]),

                Section::make('Account Status')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('email_verified')
                            ->label('Email Verified')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-badge')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger')
                            ->getStateUsing(fn (User $record): bool => $record->email_verified_at !== null),
                        TextEntry::make('email_verified_at')
                            ->label('Verified At')
                            ->dateTime()
                            ->placeholder('Not verified')
                            ->icon('heroicon-m-check-circle'),
                        ColorEntry::make('theme_color')
                            ->label('Theme Preference')
                            ->default('#000000'),
                    ]),

                Section::make('Timestamps')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Account Created')
                            ->dateTime()
                            ->icon('heroicon-m-calendar'),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->icon('heroicon-m-clock'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime()
                            ->placeholder('Active')
                            ->icon('heroicon-m-trash')
                            ->color('danger'),
                    ]),
            ]);
    }
}
