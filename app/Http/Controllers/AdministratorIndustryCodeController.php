<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\CodeAuthorityImportTemplateExport;
use App\Http\Requests\Administrators\ConfirmCodeAuthorityImportRequest;
use App\Http\Requests\Administrators\StoreCodeAuthorityImportRequest;
use App\Http\Requests\Administrators\StoreCodeAuthorityRequest;
use App\Http\Requests\Administrators\StoreIndustryCourseCodeRequest;
use App\Http\Requests\Administrators\UpdateCodeAuthorityRequest;
use App\Http\Requests\Administrators\UpdateIndustryCourseCodeRequest;
use App\Models\CodeAuthority;
use App\Models\CodeAuthorityImport;
use App\Models\Course;
use App\Models\IndustryCourseCode;
use App\Models\School;
use App\Models\User;
use App\Services\CodeAuthorityImportService;
use App\Services\CurriculumCapabilityResolver;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Registrar-managed registry of per-school regulatory authority code
 * lists (e.g. CHED PSCED). Authorities are created at runtime and
 * populated by spreadsheet import — no country dataset ships with the
 * repository.
 */
final class AdministratorIndustryCodeController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CurriculumCapabilityResolver $capabilities,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        Gate::authorize('viewAny', IndustryCourseCode::class);

        $school = $this->currentSchool();
        abort_if($school === null, 422, 'Choose an active school before managing authority codes.');

        $authorityQuery = CodeAuthority::query()
            ->where('school_id', $school->id)
            ->withCount(['codes', 'codes as active_codes_count' => fn (Builder $q) => $q->where('is_active', true)])
            ->orderBy('name');

        $authorities = $authorityQuery->get();

        $search = mb_trim((string) $request->query('search', ''));
        $selectedAuthorityId = $request->query('authority_id');
        $selectedCategory = $request->query('category_code');
        $status = $request->query('status', 'all');

        $codesQuery = IndustryCourseCode::query()
            ->where('school_id', $school->id)
            ->with(['authority:id,name,key', 'school:id,name'])
            ->withCount('courses')
            ->orderBy('code');

        if (is_numeric($selectedAuthorityId)) {
            $codesQuery->where('code_authority_id', (int) $selectedAuthorityId);
        }

        if (is_string($selectedCategory) && $selectedCategory !== '' && $selectedCategory !== 'all') {
            $codesQuery->where('category_code', $selectedCategory);
        }

        if ($status === 'active') {
            $codesQuery->where('is_active', true);
        } elseif ($status === 'inactive') {
            $codesQuery->where('is_active', false);
        }

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $codesQuery->where(function (Builder $inner) use ($like): void {
                $inner->whereRaw('lower(code) like ?', [$like])
                    ->orWhereRaw('lower(title) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(category_code, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(category_name, \'\')) like ?', [$like]);
            });
        }

        $allCodes = IndustryCourseCode::query()->where('school_id', $school->id);

        $distinctCategories = (clone $allCodes)
            ->whereNotNull('category_name')
            ->whereRaw("TRIM(COALESCE(category_name, '')) != ''")
            ->selectRaw('category_code, category_name, count(*) as count')
            ->groupBy('category_code', 'category_name')
            ->orderBy('category_name')
            ->get()
            ->map(fn ($row): array => [
                'code' => $row->category_code,
                'name' => $row->category_name,
                'count' => (int) $row->count,
                'label' => $row->category_code ? "{$row->category_code} · {$row->category_name}" : (string) $row->category_name,
            ])
            ->values();

        $stats = [
            'total_codes' => (clone $allCodes)->count(),
            'active_codes' => (clone $allCodes)->where('is_active', true)->count(),
            'total_categories' => $distinctCategories->count(),
            'total_authorities' => $authorities->count(),
            'linked_programs_count' => Course::query()->where('school_id', $school->id)->whereNotNull('industry_course_code_id')->count(),
        ];

        return Inertia::render('administrators/curriculum/authority-codes/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'stats' => $stats,
            'is_ched_accredited' => $this->isChedAccredited($school),
            'authorities' => $authorities->map(fn (CodeAuthority $auth): array => $this->authorityPayload($auth, $school))->values(),
            'categories' => $distinctCategories,
            'codes' => $codesQuery->paginate(30)->withQueryString(),
            'filters' => [
                'search' => $search,
                'authority_id' => $selectedAuthorityId,
                'category_code' => $selectedCategory,
                'status' => $status,
            ],
        ]);
    }

    public function authorities(): JsonResponse
    {
        Gate::authorize('viewAny', IndustryCourseCode::class);

        $school = $this->currentSchool();
        abort_if($school === null, 422, 'Choose an active school before managing authority codes.');

        $authorities = CodeAuthority::query()
            ->withCount(['codes', 'codes as active_codes_count' => fn (Builder $query): Builder => $query->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (CodeAuthority $authority): array => $this->authorityPayload($authority, $school))
            ->values();

        return response()->json([
            'is_ched_accredited' => $this->isChedAccredited($school),
            'authorities' => $authorities,
        ]);
    }

    public function storeAuthority(StoreCodeAuthorityRequest $request): JsonResponse|RedirectResponse
    {
        $schoolId = $this->tenantContext->getCurrentSchoolId();
        abort_if($schoolId === null, 422, 'Choose an active school before creating an authority.');

        $validated = $request->validated();

        $authority = CodeAuthority::query()->create([
            ...$validated,
            'school_id' => $schoolId,
            'schema' => CodeAuthority::defaultSchema(),
            'is_active' => true,
        ]);

        if (! $request->wantsJson()) {
            return redirect()->back()->with('success', "Authority “{$authority->name}” created successfully.");
        }

        return response()->json([
            'authority' => $this->authorityPayload($authority->refresh(), $authority->school),
        ], 201);
    }

    public function updateAuthority(UpdateCodeAuthorityRequest $request, CodeAuthority $codeAuthority): JsonResponse|RedirectResponse
    {
        $this->ensureSameSchool($codeAuthority->school_id);

        $codeAuthority->update($request->validated());

        if (! $request->wantsJson()) {
            return redirect()->back()->with('success', "Authority “{$codeAuthority->name}” updated successfully.");
        }

        return response()->json([
            'authority' => $this->authorityPayload($codeAuthority->refresh(), $codeAuthority->school),
        ]);
    }

    public function destroyAuthority(CodeAuthority $codeAuthority): JsonResponse|RedirectResponse
    {
        $this->ensureSameSchool($codeAuthority->school_id);
        Gate::authorize('delete', $codeAuthority);

        $name = $codeAuthority->name;
        $codeAuthority->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', "Authority “{$name}” deleted successfully.");
    }

    public function searchCodes(): JsonResponse
    {
        Gate::authorize('viewAny', IndustryCourseCode::class);

        $school = $this->currentSchool();
        abort_if($school === null, 422, 'Choose an active school before searching authority codes.');
        $schoolId = $school->id;

        $eligibleAuthorities = CodeAuthority::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (CodeAuthority $authority): bool => $authority->matchesSchool($school));

        $eligibleAuthorityIds = $eligibleAuthorities->pluck('id')->all();

        if ($eligibleAuthorityIds === []) {
            return response()->json(['codes' => []]);
        }

        $authorityId = request()->query('code_authority_id');
        if (is_numeric($authorityId)) {
            $parsedId = (int) $authorityId;
            if (! in_array($parsedId, $eligibleAuthorityIds, true)) {
                return response()->json(['codes' => []]);
            }
            $targetAuthorityIds = [$parsedId];
        } else {
            $targetAuthorityIds = $eligibleAuthorityIds;
        }

        $query = IndustryCourseCode::query()
            ->with('authority:id,name,key')
            ->where('is_active', true)
            ->whereIn('code_authority_id', $targetAuthorityIds)
            ->orderBy('code')
            ->limit(50);

        $search = mb_trim((string) request()->query('q', ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->whereRaw('lower(code) like ?', [$like])
                    ->orWhereRaw('lower(title) like ?', [$like]);
            });
        }

        return response()->json([
            'codes' => $query->get()->map(fn (IndustryCourseCode $code): array => [
                'id' => $code->id,
                'code' => $code->code,
                'title' => $code->title,
                'category_code' => $code->category_code,
                'category_name' => $code->category_name,
                'label' => $code->displayLabel(),
                'authority_id' => $code->code_authority_id,
                'authority_name' => $code->authority?->name,
                'attributes' => $code->attributes ?? [],
            ])->values(),
        ]);
    }

    public function storeCode(StoreIndustryCourseCodeRequest $request): JsonResponse|RedirectResponse
    {
        $schoolId = $this->tenantContext->getCurrentSchoolId();
        abort_if($schoolId === null, 422, 'Choose an active school before registering a code.');

        $validated = $request->validated();
        $authority = CodeAuthority::query()->find($validated['code_authority_id']);
        abort_unless($authority instanceof CodeAuthority, 404);
        $this->ensureSameSchool($authority->school_id);

        $candidateCode = mb_strtolower((string) $validated['code']);

        abort_if(IndustryCourseCode::query()
            ->where('school_id', $schoolId)
            ->where('code_authority_id', $authority->id)
            ->whereRaw('lower(code) = ?', [$candidateCode])
            ->exists(), 422, 'This code is already registered for the selected authority.');

        $code = IndustryCourseCode::query()->create([
            ...$validated,
            'school_id' => $schoolId,
            'source' => 'manual',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! $request->wantsJson()) {
            return redirect()->back()->with('success', "Authority course code “{$code->code}” created successfully.");
        }

        return response()->json([
            'code' => [
                'id' => $code->id,
                'code' => $code->code,
                'title' => $code->title,
                'category_code' => $code->category_code,
                'category_name' => $code->category_name,
                'label' => $code->displayLabel(),
                'authority_id' => $code->code_authority_id,
                'attributes' => $code->attributes ?? [],
            ],
        ], 201);
    }

    public function updateCode(UpdateIndustryCourseCodeRequest $request, IndustryCourseCode $industryCourseCode): JsonResponse|RedirectResponse
    {
        $this->ensureSameSchool($industryCourseCode->school_id);

        $validated = $request->validated();

        if (isset($validated['code'])) {
            $duplicate = IndustryCourseCode::query()
                ->where('school_id', $industryCourseCode->school_id)
                ->where('code_authority_id', $industryCourseCode->code_authority_id)
                ->whereKeyNot($industryCourseCode->id)
                ->whereRaw('lower(code) = ?', [mb_strtolower((string) $validated['code'])])
                ->exists();

            abort_if($duplicate, 422, 'Another code with this value already exists for the selected authority.');
        }

        $industryCourseCode->update($validated);

        if (! $request->wantsJson()) {
            return redirect()->back()->with('success', "Authority course code “{$industryCourseCode->code}” updated successfully.");
        }

        return response()->json([
            'code' => [
                'id' => $industryCourseCode->id,
                'code' => $industryCourseCode->code,
                'title' => $industryCourseCode->title,
                'category_code' => $industryCourseCode->category_code,
                'category_name' => $industryCourseCode->category_name,
                'label' => $industryCourseCode->displayLabel(),
                'authority_id' => $industryCourseCode->code_authority_id,
                'attributes' => $industryCourseCode->attributes ?? [],
            ],
        ]);
    }

    public function destroyCode(IndustryCourseCode $industryCourseCode): JsonResponse|RedirectResponse
    {
        $this->ensureSameSchool($industryCourseCode->school_id);
        Gate::authorize('delete', $industryCourseCode);

        $codeStr = $industryCourseCode->code;
        $industryCourseCode->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', "Authority course code “{$codeStr}” deleted successfully.");
    }

    public function downloadTemplate(CodeAuthority $codeAuthority): BinaryFileResponse
    {
        Gate::authorize('viewAny', IndustryCourseCode::class);
        $this->ensureSameSchool($codeAuthority->school_id);

        $slug = mb_strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $codeAuthority->key));

        return Excel::download(
            new CodeAuthorityImportTemplateExport($codeAuthority),
            "authority-codes-{$slug}-template.xlsx",
        );
    }

    public function storeImport(StoreCodeAuthorityImportRequest $request, CodeAuthorityImportService $imports): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');
        abort_unless($user instanceof User && $file !== null, 422);

        /** @var int $authorityId */
        $authorityId = $request->validated('code_authority_id');
        $authority = CodeAuthority::query()->find($authorityId);
        abort_unless($authority instanceof CodeAuthority, 404);
        $this->ensureSameSchool($authority->school_id);

        $import = $imports->stage($user, $authority, $file);

        return response()->json(['import' => $imports->serialize($import)], 201);
    }

    public function confirmImport(
        ConfirmCodeAuthorityImportRequest $request,
        CodeAuthorityImport $codeAuthorityImport,
        CodeAuthorityImportService $imports,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var list<int> $rowIds */
        $rowIds = $request->validated('row_ids');
        /** @var list<string> $adoptKeys */
        $adoptKeys = $request->validated('adopt_column_keys', []);
        $import = $imports->confirm($codeAuthorityImport, $user, $rowIds, $adoptKeys);

        return response()->json(['import' => $imports->serialize($import)]);
    }

    private function currentSchool(): ?School
    {
        $user = auth()->user();

        return $this->tenantContext->getCurrentSchool()
            ?? ($user instanceof User ? $user->school : null)
            ?? School::query()->orderBy('id')->first();
    }

    private function ensureSameSchool(?int $schoolId): void
    {
        $current = $this->tenantContext->getCurrentSchoolId();

        if ($current !== null) {
            abort_unless($schoolId === $current, 404);
        }
    }

    private function isChedAccredited(School $school): bool
    {
        return $this->capabilities->forSchool($school)
            ->contains(fn (array $capability): bool => ($capability['curriculum_framework'] ?? null) === 'ched_psg');
    }

    /** @return array<string, mixed> */
    private function authorityPayload(CodeAuthority $authority, ?School $school): array
    {
        return [
            'id' => $authority->id,
            'key' => $authority->key,
            'name' => $authority->name,
            'country_code' => $authority->country_code,
            'curriculum_framework' => $authority->curriculum_framework,
            'description' => $authority->description,
            'columns' => $authority->columnDefinitions(),
            'is_active' => $authority->is_active,
            'codes_count' => $authority->codes_count ?? $authority->codes()->count(),
            'active_codes_count' => $authority->active_codes_count ?? $authority->codes()->where('is_active', true)->count(),
            'is_relevant' => $school instanceof School ? $authority->matchesSchool($school) : true,
        ];
    }
}
