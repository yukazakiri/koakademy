<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\LibraryResearchPaperRequest;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Models\ResearchPaper;
use Spatie\Tags\Tag;

final class AdministratorLibraryResearchPaperController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $type = $request->input('type');
        $status = $request->input('status');
        $visibility = $request->input('visibility');

        $papers = ResearchPaper::query()
            ->with(['students', 'student', 'course'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'ilike', "%{$term}%")
                        ->orWhere('advisor_name', 'ilike', "%{$term}%")
                        ->orWhere('contributors', 'ilike', "%{$term}%")
                        ->orWhereHas('student', fn ($studentQuery) => $studentQuery
                            ->where('first_name', 'ilike', "%{$term}%")
                            ->orWhere('last_name', 'ilike', "%{$term}%"))
                        ->orWhereHas('students', fn ($studentQuery) => $studentQuery
                            ->where('first_name', 'ilike', "%{$term}%")
                            ->orWhere('last_name', 'ilike', "%{$term}%"))
                        ->orWhereHas('course', fn ($courseQuery) => $courseQuery
                            ->where('code', 'ilike', "%{$term}%")
                            ->orWhere('title', 'ilike', "%{$term}%"));
                });
            })
            ->when(is_string($type) && $type !== '' && $type !== 'all', fn ($query) => $query->where('type', $type))
            ->when(is_string($status) && $status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(is_string($visibility) && $visibility !== '' && $visibility !== 'all', function ($query) use ($visibility): void {
                $query->where('is_public', $visibility === 'public');
            })
            ->orderByDesc('publication_year')
            ->orderBy('title')
            ->limit(50)
            ->get()
            ->map(fn (ResearchPaper $paper): array => [
                'id' => $paper->id,
                'title' => $paper->title,
                'type' => $paper->type,
                'status' => $paper->status,
                'publication_year' => $paper->publication_year,
                'advisor_name' => $paper->advisor_name,
                'contributors' => $paper->contributors,
                'keywords' => $paper->keywords,
                'is_public' => $paper->is_public,
                'students' => $this->resolveStudentNames($paper),
                'course' => $paper->course?->code,
                'cover_image_url' => $this->resolveCoverImageUrl($paper),
                'document_url' => $this->resolveDocumentUrl($paper),
            ]);

        $stats = [
            'total' => ResearchPaper::count(),
            'capstone' => ResearchPaper::query()->where('type', 'capstone')->count(),
            'thesis' => ResearchPaper::query()->where('type', 'thesis')->count(),
            'research' => ResearchPaper::query()->where('type', 'research')->count(),
            'public' => ResearchPaper::query()->where('is_public', true)->count(),
        ];

        return Inertia::render('administrators/library/research-papers/index', [
            'user' => $this->getUserProps(),
            'papers' => $papers,
            'stats' => $stats,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'type' => is_string($type) ? $type : null,
                'status' => is_string($status) ? $status : null,
                'visibility' => is_string($visibility) ? $visibility : null,
            ],
            'options' => [
                'types' => [
                    ['value' => 'all', 'label' => 'All types'],
                    ['value' => 'capstone', 'label' => 'Capstone'],
                    ['value' => 'thesis', 'label' => 'Thesis'],
                    ['value' => 'research', 'label' => 'Research'],
                ],
                'statuses' => [
                    ['value' => 'all', 'label' => 'All statuses'],
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'submitted', 'label' => 'Submitted'],
                    ['value' => 'archived', 'label' => 'Archived'],
                ],
                'visibility' => [
                    ['value' => 'all', 'label' => 'All visibility'],
                    ['value' => 'public', 'label' => 'Public'],
                    ['value' => 'private', 'label' => 'Private'],
                ],
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/library/research-papers/edit', [
            'user' => $this->getUserProps(),
            'paper' => null,
            'options' => $this->getResearchOptions(),
        ]);
    }

    public function store(LibraryResearchPaperRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $this->normalizeStudentIds($validated);
        $validated['student_id'] = $studentIds[0] ?? $validated['student_id'] ?? null;

        $coverImage = $request->file('cover_image_upload');
        if ($coverImage instanceof UploadedFile) {
            $validated['cover_image_path'] = $this->storeCoverImage($coverImage);
        }

        $document = $request->file('document_upload');
        if ($document instanceof UploadedFile) {
            $validated['document_path'] = $this->storeDocument($document);
        }

        unset($validated['student_ids'], $validated['cover_image_upload'], $validated['document_upload']);

        $paper = ResearchPaper::create($validated);
        if ($studentIds !== []) {
            $paper->students()->sync($studentIds);
        }

        $tags = $this->normalizeTags($request);
        if ($tags !== null) {
            $paper->syncTags($tags);
        }

        return redirect()
            ->route('administrators.library.research-papers.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Research paper saved successfully.',
            ]);
    }

    public function edit(ResearchPaper $researchPaper): Response
    {
        return Inertia::render('administrators/library/research-papers/edit', [
            'user' => $this->getUserProps(),
            'paper' => [
                'id' => $researchPaper->id,
                'title' => $researchPaper->title,
                'type' => $researchPaper->type,
                'student_ids' => $this->resolveStudentIds($researchPaper),
                'course_id' => $researchPaper->course_id,
                'advisor_name' => $researchPaper->advisor_name,
                'contributors' => $researchPaper->contributors,
                'abstract' => $researchPaper->abstract,
                'keywords' => $researchPaper->keywords,
                'tags' => $researchPaper->tags->pluck('name')->all(),
                'publication_year' => $researchPaper->publication_year,
                'document_url' => $researchPaper->document_url,
                'document_download_url' => $this->resolveDocumentUrl($researchPaper),
                'status' => $researchPaper->status,
                'is_public' => $researchPaper->is_public,
                'notes' => $researchPaper->notes,
                'cover_image_url' => $this->resolveCoverImageUrl($researchPaper),
            ],
            'options' => $this->getResearchOptions(),
        ]);
    }

    public function update(LibraryResearchPaperRequest $request, ResearchPaper $researchPaper): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $this->normalizeStudentIds($validated);
        $validated['student_id'] = $studentIds[0] ?? $validated['student_id'] ?? null;

        $coverImage = $request->file('cover_image_upload');
        if ($coverImage instanceof UploadedFile) {
            $validated['cover_image_path'] = $this->storeCoverImage($coverImage, $researchPaper->cover_image_path);
        }

        $document = $request->file('document_upload');
        if ($document instanceof UploadedFile) {
            $validated['document_path'] = $this->storeDocument($document, $researchPaper->document_path);
        }

        unset($validated['student_ids'], $validated['cover_image_upload'], $validated['document_upload']);

        $researchPaper->update($validated);
        $researchPaper->students()->sync($studentIds);

        $tags = $this->normalizeTags($request);
        if ($tags !== null) {
            $researchPaper->syncTags($tags);
        }

        return redirect()
            ->route('administrators.library.research-papers.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Research paper updated.',
            ]);
    }

    public function destroy(ResearchPaper $researchPaper): RedirectResponse
    {
        if (is_string($researchPaper->cover_image_path) && $researchPaper->cover_image_path !== '') {
            Storage::disk('public')->delete($researchPaper->cover_image_path);
        }

        if (is_string($researchPaper->document_path) && $researchPaper->document_path !== '') {
            Storage::disk('public')->delete($researchPaper->document_path);
        }

        $researchPaper->delete();

        return redirect()
            ->route('administrators.library.research-papers.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Research paper deleted.',
            ]);
    }

    private function getResearchOptions(): array
    {
        return [
            'students' => Student::query()
                ->orderBy('last_name')
                ->limit(100)
                ->get()
                ->map(fn (Student $student): array => [
                    'value' => (string) $student->id,
                    'label' => $student->full_name,
                ])
                ->values()
                ->all(),
            'courses' => Course::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Course $course): array => [
                    'value' => $course->id,
                    'label' => $course->code.' - '.$course->title,
                ])
                ->values()
                ->all(),
            'types' => [
                ['value' => 'capstone', 'label' => 'Capstone'],
                ['value' => 'thesis', 'label' => 'Thesis'],
                ['value' => 'research', 'label' => 'Research'],
            ],
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'submitted', 'label' => 'Submitted'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'tags' => Tag::all()->map(fn (Tag $tag): array => [
                'value' => $tag->name,
                'label' => $tag->name,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    private function normalizeStudentIds(array $validated): array
    {
        $studentIds = Arr::wrap($validated['student_ids'] ?? []);

        if ($studentIds === [] && isset($validated['student_id'])) {
            $studentIds = Arr::wrap($validated['student_id']);
        }

        return collect($studentIds)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();
    }

    private function resolveStudentIds(ResearchPaper $paper): array
    {
        $studentIds = $paper->students
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($studentIds === [] && $paper->student_id) {
            return [(string) $paper->student_id];
        }

        return $studentIds;
    }

    private function resolveStudentNames(ResearchPaper $paper): array
    {
        $students = $paper->students
            ->pluck('full_name')
            ->filter()
            ->values()
            ->all();

        if ($students === [] && $paper->student) {
            return [$paper->student->full_name];
        }

        return $students;
    }

    private function normalizeTags(Request $request): ?array
    {
        if (! $request->has('tags')) {
            return null;
        }

        $tags = Arr::wrap($request->input('tags', []));

        return collect($tags)
            ->map(function ($tag): ?string {
                if (is_array($tag)) {
                    return $tag['value'] ?? $tag['label'] ?? null;
                }

                return is_string($tag) ? $tag : null;
            })
            ->filter(fn (?string $tag): bool => is_string($tag) && mb_trim($tag) !== '')
            ->map(fn (string $tag): string => mb_trim($tag))
            ->unique()
            ->values()
            ->all();
    }

    private function storeCoverImage(UploadedFile $file, ?string $currentPath = null): string
    {
        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        return $file->storePublicly('library/research/covers', 'public');
    }

    private function storeDocument(UploadedFile $file, ?string $currentPath = null): string
    {
        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        return $file->storePublicly('library/research/documents', 'public');
    }

    private function resolveCoverImageUrl(ResearchPaper $paper): ?string
    {
        if (is_string($paper->cover_image_path) && $paper->cover_image_path !== '') {
            return Storage::disk('public')->url($paper->cover_image_path);
        }

        return null;
    }

    private function resolveDocumentUrl(ResearchPaper $paper): ?string
    {
        if (is_string($paper->document_path) && $paper->document_path !== '') {
            return Storage::disk('public')->url($paper->document_path);
        }

        if (is_string($paper->document_url) && $paper->document_url !== '') {
            return $paper->document_url;
        }

        return null;
    }

    private function getUserProps(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }
}
