<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ClassPostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreMobileClassPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFaculty() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'instruction' => ['nullable', 'string'],
            'type' => ['required', new Enum(ClassPostType::class)],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_points' => ['nullable', 'integer', 'min:0'],
            'audience_mode' => ['nullable', 'string', 'max:50'],
            'assigned_student_ids' => ['nullable', 'array'],
            'assigned_student_ids.*' => ['integer'],
            'rubric' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.(int) config('api.max_upload_kb', 51200), 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar'],
        ];
    }
}
