<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMobileGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFaculty() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'prelim_grade' => ['nullable', 'numeric', 'between:0,100'],
            'midterm_grade' => ['nullable', 'numeric', 'between:0,100'],
            'finals_grade' => ['nullable', 'numeric', 'between:0,100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
