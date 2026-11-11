<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClassPostType;
use App\Models\ClassPost;

final class ClassPostPayloadService
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(ClassPost $post, bool $includeAudienceIds = true): array
    {
        $postType = $post->type instanceof ClassPostType ? $post->type->value : (string) $post->type;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content,
            'type' => $postType,
            'status' => $post->status ?? 'backlog',
            'priority' => $post->priority ?? 'medium',
            'start_date' => $post->start_date?->toDateString(),
            'due_date' => $post->due_date?->toDateString(),
            'progress_percent' => $post->progress_percent ?? 0,
            'total_points' => $post->total_points,
            'assigned_faculty_id' => $post->assigned_faculty_id,
            'attachments' => collect($post->attachments ?? [])
                ->map(function ($attachment) use ($post): array {
                    $name = $attachment['name'] ?? basename((string) ($attachment['url'] ?? 'Attachment'));
                    $kind = $attachment['kind'] ?? 'link';
                    $url = $attachment['url'] ?? '';

                    if ($kind === 'file' && $post->class_id) {
                        $filename = basename((string) ($attachment['path'] ?? parse_url($url, PHP_URL_PATH) ?? ''));
                        if ($filename !== '') {
                            $url = route('classes.posts.attachments.download', [
                                'class' => $post->class_id,
                                'file' => $filename,
                            ]);
                        }
                    }

                    return [
                        'name' => $name,
                        'url' => $url,
                        'kind' => $kind,
                    ];
                })
                ->values(),
            'assignment' => $postType === ClassPostType::Assignment->value ? [
                'instruction' => $post->instruction,
                'audience_mode' => $post->audience_mode ?? 'all_students',
                'assigned_student_ids' => $includeAudienceIds ? ($post->assigned_student_ids ?? []) : [],
                'rubric' => $this->normalizeRubric($post->rubric ?? []),
            ] : null,
            'created_at' => format_timestamp($post->created_at),
        ];
    }

    /**
     * @param  array<int, mixed>  $rubric
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRubric(array $rubric): array
    {
        return collect($rubric)
            ->map(fn (array $criterion): array => [
                'title' => (string) ($criterion['title'] ?? ''),
                'description' => isset($criterion['description']) ? (string) $criterion['description'] : null,
                'points' => (int) ($criterion['points'] ?? 0),
                'levels' => collect($criterion['levels'] ?? [])
                    ->map(fn ($level): array => [
                        'title' => (string) ($level['title'] ?? ''),
                        'description' => isset($level['description']) ? (string) $level['description'] : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
