<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateStudent extends CreateRecord
{
    #[Override]
    protected static string $resource = StudentResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data): \App\Models\Student {
            $type = \App\Enums\StudentType::from($data['student_type']);
            $configuration = app(\App\Services\StudentConfigurationService::class);
            $rules = [
                'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
                'nationality' => ['nullable', 'string', 'max:50'],
            ];
            app(\App\Services\IdentifierGenerator::class)->sequenceFor('student', lock: true);
            $configuration->forget();
            $configuration->assertRequiredFieldsSupported(['email', 'phone', 'nationality']);
            \Illuminate\Support\Facades\Validator::make($data, $configuration->rules($rules, $type->value))->validate();
            $mode = $data['identifier_assignment'] ?? (app(\App\Services\IdentifierGenerator::class)->sequenceFor('student')->assignment_mode === 'manual' ? 'manual' : 'automatic');
            $data['student_id'] = $configuration->usesExternal($type->value)
                ? app(\App\Services\IdentifierGenerator::class)->claimExternalIdentifier($type, $data['lrn'])
                : app(\App\Services\IdentifierGenerator::class)->assign($type, isset($data['student_id']) ? (string) $data['student_id'] : null, $mode);
            unset($data['identifier_assignment']);
            foreach ($configuration->get()['fields'] as $field => $policy) {
                if (! $policy['visible']) {
                    unset($data[$field]);
                }
            }
            if (! $configuration->externalFor($type->value)['enabled']) {
                unset($data['lrn']);
            }

            return \App\Models\Student::query()->create($data);
        });
    }
}
