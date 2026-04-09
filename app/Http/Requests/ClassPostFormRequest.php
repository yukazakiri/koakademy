<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClassPostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class ClassPostFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('id') !== null;

        return [
            'class_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'exists:classes,id',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'content' => [
                'nullable',
                'string',
            ],
            'type' => [
                $isUpdate ? 'sometimes' : 'required',
                new Enum(ClassPostType::class),
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:10',
            ],
            'attachments.*' => [
                'nullable',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'class_id.required' => 'The class ID is required.',
            'class_id.exists' => 'The selected class is invalid.',
            'title.required' => 'The title is required.',
            'title.max' => 'The title must not be greater than 255 characters.',
            'type.required' => 'The post type is required.',
            'type.Enum' => 'The selected post type is invalid.',
            'attachments.array' => 'Attachments must be an array.',
            'attachments.max' => 'You can upload a maximum of 10 attachments.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each attachment must not be greater than 10MB.',
            'attachments.*.mimes' => 'Each attachment must be a file of type: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, rtf, jpg, jpeg, png, gif, zip, or rar.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'class_id' => 'class ID',
            'attachments.*' => 'attachment',
        ];
    }
}
