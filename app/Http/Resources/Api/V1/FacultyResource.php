<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FacultyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'faculty_id_number' => $this->faculty_id_number,
            'name' => $this->full_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'department' => $this->department,
            'position' => $this->position,
            'office_hours' => $this->office_hours,
            'biography' => $this->biography,
            'education' => $this->education,
            'courses_taught' => $this->courses_taught,
            'photo_url' => $this->photo_url,
            'status' => $this->status,
        ];
    }
}
