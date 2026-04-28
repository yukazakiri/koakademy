<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class LibraryResearchPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:capstone,thesis,research'],
            'student_id' => ['nullable', 'exists:students,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'advisor_name' => ['nullable', 'string', 'max:255'],
            'contributors' => ['nullable', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'keywords' => ['nullable', 'string'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'document_url' => ['nullable', 'string', 'max:255'],
            'document_upload' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'cover_image_upload' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'in:draft,submitted,archived'],
            'is_public' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Research title is required.',
            'type.required' => 'Select a research type.',
            'type.in' => 'Select a valid research type.',
            'student_id.exists' => 'Selected student could not be found.',
            'student_ids.*.exists' => 'Selected student could not be found.',
            'course_id.exists' => 'Selected course could not be found.',
            'tags.*.max' => 'Each tag must be 255 characters or fewer.',
            'publication_year.min' => 'Publication year must be 1900 or later.',
            'publication_year.max' => 'Publication year must be 2100 or earlier.',
            'status.in' => 'Select a valid research status.',
            'document_upload.mimes' => 'Document must be a PDF file.',
            'document_upload.max' => 'Document must be 50MB or smaller.',
            'cover_image_upload.image' => 'Cover image must be a valid image file.',
            'cover_image_upload.max' => 'Cover image must be 4MB or smaller.',
        ];
    }
}
