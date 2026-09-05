<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'student_id' => $this->student_id,
            'name' => $this->full_name ?? mb_trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]))),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix,
            'email' => $this->email,
            'phone' => $this->phone,
            'student_type' => $this->student_type,
            'status' => $this->status,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'civil_status' => $this->civil_status,
            'nationality' => $this->nationality,
            'address' => $this->address,
            'course' => $this->whenLoaded('Course', fn (): ?array => $this->Course ? [
                'id' => (int) $this->Course->id,
                'code' => $this->Course->code,
                'title' => $this->Course->title,
            ] : null),
        ];
    }
}
