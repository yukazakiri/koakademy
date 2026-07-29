<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFinanceDocumentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateFinanceDocuments', GeneralSetting::class) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'automatic_receipts_enabled' => ['required', 'boolean'],
            'require_paper_or_reference' => ['required', 'boolean'],
            'manual_invoices_enabled' => ['required', 'boolean'],
        ];
    }
}
