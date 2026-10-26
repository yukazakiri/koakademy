<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Koakademy\SchoolNotes\Models\SchoolNote;

final class StoreSchoolNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('create', SchoolNote::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
