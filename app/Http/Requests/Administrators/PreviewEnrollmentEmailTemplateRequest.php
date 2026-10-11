<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;

final class PreviewEnrollmentEmailTemplateRequest extends EnrollmentEmailTemplateRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewEmailTemplates', GeneralSetting::class) ?? false;
    }
}
