<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClassPostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClassPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(ClassPostType::class)],
            'status' => ['nullable', 'string', Rule::in(['backlog', 'in_progress', 'review', 'done', 'blocked'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'total_points' => [
                Rule::requiredIf(in_array($this->input('type'), ['quiz', 'assignment', 'activity'], true)),
                'nullable',
                'integer',
                'min:0',
            ],
            'assigned_faculty_id' => ['nullable', 'string', 'exists:faculty,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['required_with:attachments', 'url'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:51200'], // 50MB to match PHP upload_max_filesize
        ];
    }
}
