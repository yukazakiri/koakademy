<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\Course;
use App\Models\School;
use App\Models\SchoolCurriculumCapability;
use Illuminate\Support\Collection;

final class CurriculumCapabilityResolver
{
    public function kindForCourse(Course $course): string
    {
        $kind = (string) ($course->curriculum_kind ?? 'legacy');

        if ($kind !== '' && $kind !== 'legacy') {
            return $kind;
        }

        return mb_strtoupper(mb_trim((string) $course->department?->code)) === 'TESDA'
            ? 'tesda_qualification'
            : 'program';
    }

    public function isTesdaCourse(Course $course): bool
    {
        return $this->kindForCourse($course) === 'tesda_qualification';
    }

    public function isCollegeCourse(Course $course): bool
    {
        return $this->kindForCourse($course) === 'program';
    }

    /**
     * @return Collection<int, array{id: string, persisted_id: int|null, school_level: string, school_level_label: string, curriculum_framework: string, framework_label: string, reference: string, is_enabled: bool, is_derived: bool}>
     */
    public function forSchool(School $school): Collection
    {
        $capabilities = $school->curriculumCapabilities()
            ->where('is_enabled', true)
            ->orderBy('school_level')
            ->orderBy('curriculum_framework')
            ->get();

        if ($capabilities->isEmpty()) {
            return collect([$this->derivedCapability($school)]);
        }

        return $capabilities->map(fn (SchoolCurriculumCapability $capability): array => $this->serialize($capability));
    }

    /**
     * @return array{id: string, persisted_id: int|null, school_level: string, school_level_label: string, curriculum_framework: string, framework_label: string, reference: string, is_enabled: bool, is_derived: bool}
     */
    public function derivedCapability(School $school): array
    {
        $schoolLevel = $school->school_level ?? SchoolLevel::HigherEducation;
        $framework = $school->curriculum_framework ?? $this->defaultFramework($schoolLevel);

        return [
            'id' => 'derived:'.$schoolLevel->value.':'.$framework->value,
            'persisted_id' => null,
            'school_level' => $schoolLevel->value,
            'school_level_label' => (string) $schoolLevel->getLabel(),
            'curriculum_framework' => $framework->value,
            'framework_label' => $framework->getLabel(),
            'reference' => $school->curriculum_reference ?? $framework->getReference(),
            'is_enabled' => true,
            'is_derived' => true,
        ];
    }

    /** @return array{id: string, persisted_id: int|null, school_level: string, school_level_label: string, curriculum_framework: string, framework_label: string, reference: string, is_enabled: bool, is_derived: bool}|null */
    public function find(School $school, ?string $identifier): ?array
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return $this->forSchool($school)->first(fn (array $capability): bool => $capability['id'] === $identifier);
    }

    private function defaultFramework(SchoolLevel $schoolLevel): CurriculumFramework
    {
        return match ($schoolLevel) {
            SchoolLevel::HigherEducation => CurriculumFramework::ChedPsg,
            SchoolLevel::TechnicalVocational => CurriculumFramework::TesdaTr,
            SchoolLevel::SeniorHigh => CurriculumFramework::DepedShsK12,
            SchoolLevel::Elementary, SchoolLevel::JuniorHigh => CurriculumFramework::DepedMatatag,
        };
    }

    /**
     * @return array{id: string, persisted_id: int|null, school_level: string, school_level_label: string, curriculum_framework: string, framework_label: string, reference: string, is_enabled: bool, is_derived: bool}
     */
    private function serialize(SchoolCurriculumCapability $capability): array
    {
        $schoolLevel = $capability->school_level;
        $framework = $capability->curriculum_framework;

        return [
            'id' => (string) $capability->id,
            'persisted_id' => $capability->id,
            'school_level' => $schoolLevel->value,
            'school_level_label' => (string) $schoolLevel->getLabel(),
            'curriculum_framework' => $framework->value,
            'framework_label' => $framework->getLabel(),
            'reference' => $capability->curriculum_reference ?? $framework->getReference(),
            'is_enabled' => $capability->is_enabled,
            'is_derived' => false,
        ];
    }
}
