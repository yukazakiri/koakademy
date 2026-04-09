<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClassPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'type_name' => $this->type?->name,
            'attachments' => $this->attachments ?? [],

            // Relationships
            'class' => $this->when(
                $this->relationLoaded('class'),
                fn (): array => [
                    'id' => $this->class?->id,
                    'section' => $this->class?->section,
                    'subject_code' => $this->class?->subject_code,
                ]
            ),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Conditional fields
            'created_at_formatted' => $this->when(
                $this->created_at,
                fn () => $this->created_at->format('Y-m-d H:i:s')
            ),
            'updated_at_formatted' => $this->when(
                $this->updated_at,
                fn () => $this->updated_at->format('Y-m-d H:i:s')
            ),
        ];
    }
}
