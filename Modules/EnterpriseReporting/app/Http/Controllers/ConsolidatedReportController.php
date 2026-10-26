<?php

declare(strict_types=1);

namespace Modules\EnterpriseReporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Inertia\Inertia;
use Inertia\Response;
use Modules\EnterpriseReporting\Http\Requests\ConsolidatedReportRequest;
use Modules\EnterpriseReporting\Services\ConsolidatedEnrollmentReport;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConsolidatedReportController extends Controller
{
    public function index(ConsolidatedReportRequest $request, ConsolidatedEnrollmentReport $reports, GeneralSettingsService $settings): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $schools = $reports->availableSchools($user);
        abort_if($schools->isEmpty(), 403, 'No accessible school has consolidated reporting licensed and enabled.');
        $filters = $this->filters($request, $schools->modelKeys(), $settings);

        return Inertia::render('administrators/consolidated-reports/index', [
            'user' => [
                ...$user->only(['id', 'name', 'email', 'role']),
                'avatar' => $user->avatar_url,
                'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            ],
            'schools' => $schools->map(fn (School $school): array => $school->only(['id', 'name', 'code']))->all(),
            'filters' => $filters,
            'report' => $reports->generate($user, $filters['school_ids'], $filters['school_year'], $filters['semester']),
        ]);
    }

    public function export(ConsolidatedReportRequest $request, ConsolidatedEnrollmentReport $reports, GeneralSettingsService $settings): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $filters = $this->filters($request, $reports->availableSchools($user)->modelKeys(), $settings);
        $report = $reports->generate($user, $filters['school_ids'], $filters['school_year'], $filters['semester']);

        return response()->streamDownload(function () use ($report, $filters): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new RuntimeException('The CSV output stream could not be opened.');
            }
            fputcsv($output, ['School code', 'School', 'School year', 'Semester', 'Enrollments', 'Student records', 'Courses'], escape: '');
            foreach ($report['rows'] as $row) {
                $values = [$row['code'], $row['name'], $filters['school_year'], $filters['semester'], $row['enrollments'], $row['students'], $row['courses']];
                $values = array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value, $values);
                fputcsv($output, $values, escape: '');
            }
            fclose($output);
        }, 'consolidated-enrollments.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * @param  array<int>  $defaultSchools
     * @return array{school_ids: list<int>, school_year: string, semester: int}
     */
    private function filters(ConsolidatedReportRequest $request, array $defaultSchools, GeneralSettingsService $settings): array
    {
        /** @var array{school_ids?: array<int|string, int|numeric-string>, school_year?: string, semester?: int|numeric-string} $validated */
        $validated = $request->validated();

        return [
            'school_ids' => array_values(array_map(static fn (int|string $id): int => (int) $id, $validated['school_ids'] ?? array_slice($defaultSchools, 0, 50))),
            'school_year' => GeneralSettingsService::normalizeSchoolYear($validated['school_year'] ?? $settings->getCurrentSchoolYearString()),
            'semester' => (int) ($validated['semester'] ?? $settings->getCurrentSemester()),
        ];
    }
}
