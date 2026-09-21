<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Faculty;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search faculty members in the active school by name, department, or faculty ID number.')]
#[IsReadOnly]
final class SearchFacultyTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?GeneralSettingsService $settings = null)
    {
        $this->settings ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'ViewAny:Faculty', 'You are not permitted to search faculty records.');

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $query = mb_trim((string) $validated['query']);
        $limit = (int) ($validated['limit'] ?? 10);
        $schoolYear = $this->settings->getCurrentSchoolYearString();
        $semester = $this->settings->getCurrentSemester();

        $faculty = Faculty::query()
            ->withCount([
                'classes' => fn ($q) => $q->forAcademicPeriod($schoolYear, $semester),
            ])
            ->where(function (Builder $builder) use ($query): void {
                $term = '%'.addcslashes($query, '%_\\').'%';

                $builder->where('faculty_id_number', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('department', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) LIKE ?", [$term]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get(['id', 'faculty_id_number', 'first_name', 'middle_name', 'last_name', 'email', 'department', 'position', 'office_hours'])
            ->map(fn (Faculty $f): array => [
                'id' => $f->id,
                'faculty_id_number' => $f->faculty_id_number,
                'name' => $f->full_name,
                'email' => $f->email,
                'department' => $f->department,
                'position' => $f->position,
                'office_hours' => $f->office_hours,
                'active_classes_count' => $f->classes_count,
            ])
            ->values()
            ->all();

        return Response::structured([
            'query' => $query,
            'count' => count($faculty),
            'faculty' => $faculty,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(100)->required()->description('At least two characters from a faculty name, department, or faculty ID number.'),
            'limit' => $schema->integer()->min(1)->max(25)->description('Maximum results to return. Defaults to 10.'),
        ];
    }
}
