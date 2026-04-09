<?php

declare(strict_types=1);

namespace App\Filament\Resources\SanityContents\Tables;

use App\Services\SanityService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class SanityContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->limit(50)
                    ->description(fn ($record) => Str::limit($record->excerpt, 60)),

                TextColumn::make('post_kind')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'news' => 'info',
                        'story' => 'success',
                        'announcement' => 'warning',
                        'alert' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'news' => 'News',
                        'story' => 'Story',
                        'announcement' => 'Announcement',
                        'alert' => 'Alert',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'warning',
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'normal' => 'gray',
                        'high' => 'warning',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->visible(fn ($record): bool => $record && in_array($record->post_kind, ['announcement', 'alert'])),

                TextColumn::make('featured')
                    ->label('Featured')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sanity_id')
                    ->label('Sanity ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->copyMessage('Sanity ID copied')
                    ->copyMessageDuration(1500),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('post_kind')
                    ->label('Post Type')
                    ->options([
                        'news' => 'News',
                        'story' => 'Feature Story',
                        'announcement' => 'Announcement',
                        'alert' => 'Alert',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                SelectFilter::make('priority')
                    ->options([
                        'normal' => 'Normal',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('featured')
                    ->label('Featured')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('sync_to_sanity')
                    ->label('Push to Sanity')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $sanityService = app(SanityService::class);

                        $documentData = [
                            '_type' => 'post',
                            'title' => $record->title,
                            'slug' => ['_type' => 'slug', 'current' => $record->slug],
                            'excerpt' => $record->excerpt,
                            'postKind' => $record->post_kind,
                            'content' => $record->content,
                            'status' => $record->status,
                            'publishedAt' => format_timestamp($record->published_at),
                            'priority' => $record->priority,
                            'featured' => $record->featured,
                        ];

                        // Add optional fields
                        if ($record->content_focus) {
                            $documentData['contentFocus'] = $record->content_focus;
                        }
                        if ($record->tags) {
                            $documentData['tags'] = $record->tags;
                        }
                        if ($record->audiences) {
                            $documentData['audiences'] = $record->audiences;
                        }

                        if ($record->sanity_id) {
                            $sanityService->updateDocument($record->sanity_id, $documentData);
                        } else {
                            $result = $sanityService->createDocument($documentData);
                            if ($result) {
                                $record->update(['sanity_id' => $result['_id']]);
                            }
                        }
                    })
                    ->successNotificationTitle('Content synced to Sanity successfully')
                    ->visible(fn ($record) => $record->exists),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('sync_from_sanity')
                    ->label('Sync from Sanity')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->requiresConfirmation()
                    ->action(function () {
                        $sanityService = app(SanityService::class);

                        return $sanityService->syncToDatabase('post');
                    })
                    ->successNotificationTitle(fn ($data): string => "Synced {$data} posts from Sanity")
                    ->color('success'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
