<?php

declare(strict_types=1);

namespace App\Filament\Resources\SanityContents\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class SanityContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Content')
                    ->tabs([
                        Tabs\Tab::make('Content')
                            ->schema([
                                Section::make('Basic Information')
                                    ->schema([
                                        TextInput::make('title')
                                            ->required()
                                            ->maxLength(140)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (callable $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                                        TextInput::make('slug')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Auto-generated from title'),

                                        Textarea::make('excerpt')
                                            ->required()
                                            ->maxLength(320)
                                            ->rows(3)
                                            ->helperText('Short teaser used for previews and SEO descriptions')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Post Type & Category')
                                    ->schema([
                                        Select::make('post_kind')
                                            ->label('Post Category')
                                            ->options([
                                                'news' => 'News',
                                                'story' => 'Feature Story',
                                                'announcement' => 'Announcement',
                                                'alert' => 'Alert',
                                            ])
                                            ->required()
                                            ->default('news')
                                            ->live(),

                                        Select::make('content_focus')
                                            ->label('Content Focus')
                                            ->options([
                                                'news' => 'University News',
                                                'research' => 'Research Highlight',
                                                'student-life' => 'Student Life',
                                                'athletics' => 'Athletics',
                                                'press' => 'Press Release',
                                            ])
                                            ->hidden(fn (Get $get): bool => in_array($get('post_kind'), ['announcement', 'alert'])),
                                    ])
                                    ->columns(2),

                                Section::make('Main Content')
                                    ->schema([
                                        RichEditor::make('content')
                                            ->label('Body Content')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'link',
                                                'h2',
                                                'h3',
                                                'bulletList',
                                                'orderedList',
                                                'blockquote',
                                                'codeBlock',
                                            ])
                                            ->columnSpanFull()
                                            ->helperText('Required for news or story posts'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Alert Settings')
                            ->schema([
                                Section::make('Alert Configuration')
                                    ->description('These settings only apply to announcements and alerts')
                                    ->schema([
                                        Select::make('priority')
                                            ->options([
                                                'normal' => 'Normal',
                                                'high' => 'High',
                                                'critical' => 'Critical',
                                            ])
                                            ->default('normal')
                                            ->required(),

                                        Fieldset::make('Visibility Window')
                                            ->schema([
                                                DateTimePicker::make('activation_window.start')
                                                    ->label('Start Time'),
                                                DateTimePicker::make('activation_window.end')
                                                    ->label('End Time'),
                                            ])
                                            ->columns(2),

                                        Select::make('channels')
                                            ->label('Delivery Channels')
                                            ->multiple()
                                            ->options([
                                                'website' => 'Website',
                                                'email' => 'Email',
                                                'sms' => 'SMS',
                                                'mobile' => 'Mobile App Push',
                                                'signage' => 'Digital Signage',
                                            ]),

                                        Fieldset::make('Call to Action')
                                            ->schema([
                                                TextInput::make('cta.label')
                                                    ->label('CTA Label'),
                                                TextInput::make('cta.url')
                                                    ->label('CTA URL')
                                                    ->url(),
                                            ])
                                            ->columns(2),
                                    ]),
                            ])
                            ->hidden(fn (Get $get): bool => ! in_array($get('post_kind'), ['announcement', 'alert'])),

                        Tabs\Tab::make('Relationships')
                            ->schema([
                                Section::make('Categorization & Tags')
                                    ->schema([
                                        TextInput::make('primary_category_id')
                                            ->label('Primary Category')
                                            ->helperText('Sanity reference ID'),

                                        TagsInput::make('tags')
                                            ->placeholder('Add tags')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Legacy Fields')
                                    ->schema([
                                        TextInput::make('category')
                                            ->label('Category (Legacy)')
                                            ->helperText('Free-text category for imported posts'),

                                        TextInput::make('author')
                                            ->label('Author (Legacy)')
                                            ->helperText('Free-text author for imported posts'),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),

                                Section::make('Audiences')
                                    ->schema([
                                        Select::make('audiences')
                                            ->label('Target Audiences')
                                            ->multiple()
                                            ->options([
                                                'prospective' => 'Prospective Students',
                                                'current' => 'Current Students',
                                                'faculty' => 'Faculty & Staff',
                                                'alumni' => 'Alumni',
                                                'parents' => 'Parents & Families',
                                                'community' => 'Community & Partners',
                                            ]),
                                    ]),

                                Section::make('Related Content')
                                    ->schema([
                                        TagsInput::make('department_ids')
                                            ->label('Related Departments')
                                            ->placeholder('Department IDs from Sanity'),

                                        TagsInput::make('program_ids')
                                            ->label('Related Programs')
                                            ->placeholder('Program IDs from Sanity'),

                                        TagsInput::make('author_ids')
                                            ->label('Author Profiles')
                                            ->placeholder('Person IDs from Sanity')
                                            ->helperText('Required for news or story posts'),

                                        TagsInput::make('related_post_ids')
                                            ->label('Suggested Posts')
                                            ->placeholder('Related post IDs from Sanity'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Metadata')
                            ->schema([
                                Section::make('Publishing')
                                    ->schema([
                                        Select::make('status')
                                            ->label('Publishing Status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'scheduled' => 'Scheduled',
                                                'published' => 'Published',
                                                'archived' => 'Archived',
                                            ])
                                            ->default('draft')
                                            ->required(),

                                        DateTimePicker::make('published_at')
                                            ->label('Publish Date')
                                            ->required()
                                            ->default(now()),

                                        Toggle::make('featured')
                                            ->label('Feature on Landing Pages')
                                            ->default(false),

                                        TextInput::make('sanity_id')
                                            ->label('Sanity Document ID')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Auto-generated when synced with Sanity'),
                                    ])
                                    ->columns(2),

                                Section::make('SEO & Sharing')
                                    ->schema([
                                        TextInput::make('seo.metaTitle')
                                            ->label('Meta Title')
                                            ->maxLength(70),

                                        Textarea::make('seo.metaDescription')
                                            ->label('Meta Description')
                                            ->maxLength(160)
                                            ->rows(3),

                                        TextInput::make('seo.canonicalUrl')
                                            ->label('Canonical URL')
                                            ->url(),

                                        TextInput::make('seo.metaImage.alt')
                                            ->label('Social Share Image Alt Text'),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
