<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Mansoor\FilamentVersionable\Page\RevisionsAction;
use Override;

final class EditStudent extends EditRecord
{
    #[Override]
    protected static string $resource = StudentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\Student $student */
        $student = $this->getRecord();
        $configuration = app(\App\Services\StudentConfigurationService::class);
        $type = $data['student_type'] ?? $student->student_type->value;
        $usesExternal = $configuration->usesExternal($type, $student->school_id)
            && (string) $student->student_id === (string) $student->lrn;
        $rules = $configuration->rules([], $type, $student);
        if (! $usesExternal) {
            $rules['student_id'] = app(\App\Services\IdentifierGenerator::class)->getStudentIdValidationRules(\App\Enums\StudentType::from($type), $student->id, schoolId: $student->school_id);
        }
        $data['academic_year'] ??= $student->academic_year;
        \Illuminate\Support\Facades\Validator::make($data, $rules)->validate();
        if ($usesExternal && isset($data['lrn'])) {
            $data['student_id'] = $data['lrn'];
        }
        foreach ($configuration->get($student->school_id)['fields'] as $field => $policy) {
            if (! $policy['visible']) {
                unset($data[$field]);
            }
        }
        if (! $configuration->externalFor($type, $student->school_id)['enabled']) {
            unset($data['lrn']);
        }
        unset($data['identifier_assignment']);

        return $data;
    }

    #[Override]
    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            RevisionsAction::make(),
        ];
    }
}
