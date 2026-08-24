<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FacultyCustomFieldDefinition;
use Illuminate\Database\Eloquent\Collection;

final class FacultyCustomFieldDefinitionService
{
    /** @var list<array{key: string, label: string, source_header_aliases: list<string>}> */
    private const DEFAULTS = [
        ['key' => 'sss_number', 'label' => 'SSS Number', 'source_header_aliases' => ['SSS_NO', 'SSS NUMBER']],
        ['key' => 'tin_number', 'label' => 'TIN Number', 'source_header_aliases' => ['TIN_NO', 'TIN NUMBER']],
        ['key' => 'philhealth_number', 'label' => 'PhilHealth Number', 'source_header_aliases' => ['PHILHEALTH_NO', 'PHILHEALTH NUMBER']],
        ['key' => 'pagibig_number', 'label' => 'Pag-IBIG Number', 'source_header_aliases' => ['PAG-IBIG_NO', 'PAGIBIG_NO', 'PAG-IBIG NUMBER']],
    ];

    /** @return Collection<int, FacultyCustomFieldDefinition> */
    public function activeForSchool(int $schoolId): Collection
    {
        $this->ensureDefaults($schoolId);

        return FacultyCustomFieldDefinition::query()
            ->where('school_id', $schoolId)
            ->active()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, FacultyCustomFieldDefinition> */
    public function allForSchool(int $schoolId): Collection
    {
        $this->ensureDefaults($schoolId);

        return FacultyCustomFieldDefinition::query()
            ->where('school_id', $schoolId)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    public function ensureDefaults(int $schoolId): void
    {
        foreach (self::DEFAULTS as $index => $definition) {
            FacultyCustomFieldDefinition::query()->createOrFirst(
                ['school_id' => $schoolId, 'key' => $definition['key']],
                [
                    'label' => $definition['label'],
                    'field_type' => 'text',
                    'source_header_aliases' => $definition['source_header_aliases'],
                    'is_required' => false,
                    'is_sensitive' => true,
                    'is_active' => true,
                    'display_order' => $index + 1,
                ],
            );
        }
    }
}
