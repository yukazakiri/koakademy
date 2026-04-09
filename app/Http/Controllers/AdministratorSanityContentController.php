<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SanityContent;
use App\Services\HtmlToPortableText;
use App\Services\SanityService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorSanityContentController extends Controller
{
    public function __construct(
        private readonly SanityService $sanityService,
        private readonly HtmlToPortableText $htmlToPortableText
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $postKind = $request->input('post_kind');
        $status = $request->input('status');

        $contents = SanityContent::query()
            ->when(is_string($search) && mb_trim($search) !== '', function ($builder) use ($search): void {
                $query = mb_trim($search);
                $builder->where(function ($nested) use ($query): void {
                    $nested->where('title', 'ilike', "%{$query}%")
                        ->orWhere('excerpt', 'ilike', "%{$query}%")
                        ->orWhere('slug', 'ilike', "%{$query}%");
                });
            })
            ->when(is_string($postKind) && $postKind !== '', fn ($q) => $q->where('post_kind', $postKind))
            ->when(is_string($status) && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('administrators/sanity-content/index', [
            'contents' => $contents,
            'filters' => [
                'search' => $search,
                'post_kind' => $postKind,
                'status' => $status,
            ],
            'sanityConfigured' => $this->sanityService->isConfigured(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/sanity-content/create', [
            'sanityConfigured' => $this->sanityService->isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $inputs = $this->preprocessArrayFields($request->all());
        $request->merge($inputs);

        $validated = $this->validateContentRequest($request);
        $validated = $this->processValidatedData($validated);

        // Generate a temporary sanity_id if not present (required by DB)
        if (! isset($validated['sanity_id'])) {
            $validated['sanity_id'] = 'draft_'.Str::uuid();
        }

        $content = SanityContent::create($validated);

        // Automatically sync to Sanity CMS when configured
        if ($this->sanityService->isConfigured()) {
            $result = $this->sanityService->syncPostToSanity($content);
            if ($result) {
                return redirect()
                    ->route('administrators.sanity-content.index')
                    ->with('flash', ['success' => 'Content created and synced to Sanity successfully']);
            }

            // Sync failed but content was saved locally
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', [
                    'warning' => 'Content created locally but failed to sync to Sanity. You can try syncing again later.',
                ]);
        }

        return redirect()
            ->route('administrators.sanity-content.index')
            ->with('flash', ['success' => 'Content created successfully (Sanity not configured)']);
    }

    public function show(SanityContent $sanityContent): Response
    {
        return Inertia::render('administrators/sanity-content/show', [
            'content' => $sanityContent,
            'sanityConfig' => [
                'projectId' => $this->sanityService->getProjectId(),
                'dataset' => $this->sanityService->getDataset(),
            ],
        ]);
    }

    public function edit(SanityContent $sanityContent): Response
    {
        return Inertia::render('administrators/sanity-content/edit', [
            'content' => $sanityContent,
            'sanityConfig' => [
                'projectId' => $this->sanityService->getProjectId(),
                'dataset' => $this->sanityService->getDataset(),
            ],
            'sanityConfigured' => $this->sanityService->isConfigured(),
        ]);
    }

    public function update(Request $request, SanityContent $sanityContent): RedirectResponse
    {
        $inputs = $this->preprocessArrayFields($request->all());
        $request->merge($inputs);

        $validated = $this->validateContentRequest($request);
        $validated = $this->processValidatedData($validated);

        $sanityContent->update($validated);

        // Automatically sync to Sanity CMS when configured
        if ($this->sanityService->isConfigured()) {
            $result = $this->sanityService->syncPostToSanity($sanityContent);
            if ($result) {
                return redirect()
                    ->route('administrators.sanity-content.index')
                    ->with('flash', ['success' => 'Content updated and synced to Sanity successfully']);
            }

            // Sync failed but content was saved locally
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', [
                    'warning' => 'Content updated locally but failed to sync to Sanity. You can try syncing again later.',
                ]);
        }

        return redirect()
            ->route('administrators.sanity-content.index')
            ->with('flash', ['success' => 'Content updated successfully (Sanity not configured)']);
    }

    public function destroy(SanityContent $sanityContent): RedirectResponse
    {
        // Optionally delete from Sanity as well
        if ($sanityContent->sanity_id && ! str_starts_with($sanityContent->sanity_id, 'draft_')) {
            $this->sanityService->deleteDocument($sanityContent->sanity_id);
        }

        $sanityContent->delete();

        return redirect()
            ->route('administrators.sanity-content.index')
            ->with('flash', ['success' => 'Content deleted successfully']);
    }

    public function syncFromSanity(): RedirectResponse
    {
        if (! $this->sanityService->isConfigured()) {
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', ['error' => 'Sanity is not configured. Please add credentials to your .env file.']);
        }

        $result = $this->sanityService->syncToDatabase('post');

        if (! $result['success']) {
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', ['error' => $result['message']]);
        }

        return redirect()
            ->route('administrators.sanity-content.index')
            ->with('flash', ['success' => $result['message']]);
    }

    public function syncToSanity(SanityContent $sanityContent): RedirectResponse
    {
        if (! $this->sanityService->isConfigured()) {
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', ['error' => 'Sanity is not configured. Please add credentials to your .env file.']);
        }

        $result = $this->sanityService->syncPostToSanity($sanityContent);

        if (! $result) {
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', ['error' => 'Failed to sync to Sanity. Please check your Sanity credentials.']);
        }

        return redirect()
            ->route('administrators.sanity-content.index')
            ->with('flash', ['success' => 'Content synced to Sanity successfully']);
    }

    public function uploadImage(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'], // 10MB max
        ]);

        if (! $this->sanityService->isConfigured()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Sanity is not configured'], 500);
            }

            return redirect()
                ->back()
                ->with('flash', ['error' => 'Sanity is not configured']);
        }

        try {
            $result = $this->sanityService->uploadImage($validated['image']);

            if (! $result) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Failed to upload image to Sanity'], 500);
                }

                return redirect()
                    ->back()
                    ->with('flash', ['error' => 'Failed to upload image to Sanity']);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            }

            return redirect()
                ->back()
                ->with('flash', ['imageData' => $result]);
        } catch (Exception $e) {
            Log::error('Image upload failed: '.$e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to upload image: '.$e->getMessage()], 500);
            }

            return redirect()
                ->back()
                ->withErrors(['image' => 'Failed to upload image: '.$e->getMessage()]);
        }
    }

    /**
     * Bulk sync selected content to Sanity
     */
    public function bulkSyncToSanity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:sanity_contents,id'],
        ]);

        if (! $this->sanityService->isConfigured()) {
            return redirect()
                ->route('administrators.sanity-content.index')
                ->with('flash', ['error' => 'Sanity is not configured.']);
        }

        $contents = SanityContent::whereIn('id', $validated['ids'])->get();
        $result = $this->sanityService->bulkSyncToSanity($contents);

        return redirect()
            ->route('administrators.sanity-content.index')
            ->with('flash', [$result['success'] ? 'success' : 'error' => $result['message']]);
    }

    /**
     * Preprocess comma-separated fields to arrays
     */
    private function preprocessArrayFields(array $inputs): array
    {
        foreach (['tags', 'audiences', 'channels'] as $field) {
            if (isset($inputs[$field]) && is_string($inputs[$field])) {
                $inputs[$field] = array_values(array_filter(array_map(trim(...), explode(',', $inputs[$field]))));
            }
        }

        return $inputs;
    }

    /**
     * Validate content request
     */
    private function validateContentRequest(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'slug' => ['required', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'post_kind' => ['required', 'string', Rule::in(['news', 'story', 'announcement', 'alert'])],
            'content' => ['nullable'],
            'content_focus' => ['nullable', 'string', Rule::in(['news', 'research', 'student-life', 'athletics', 'press'])],
            'status' => ['required', 'string', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
            'published_at' => ['nullable', 'date'],
            'featured' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'audiences' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'cta_text' => ['nullable', 'string'],
            'cta_url' => ['nullable', 'string'],
            'activation_start' => ['nullable', 'date'],
            'activation_end' => ['nullable', 'date'],
            'featured_image' => ['nullable', 'array'],
            'featured_image.assetId' => ['nullable', 'string'],
            'featured_image.url' => ['nullable', 'string'],
            'featured_image.alt' => ['nullable', 'string'],
            'featured_image.caption' => ['nullable', 'string'],
            'featured_image.credit' => ['nullable', 'string'],
            'featured_image.filename' => ['nullable', 'string'],
        ]);
    }

    /**
     * Process validated data for storage
     */
    private function processValidatedData(array $validated): array
    {
        // Construct CTA array
        $cta = null;
        if (! empty($validated['cta_text']) || ! empty($validated['cta_url'])) {
            $cta = [
                'text' => $validated['cta_text'] ?? '',
                'url' => $validated['cta_url'] ?? '',
            ];
        }

        // Construct Activation Window array
        $activationWindow = null;
        if (! empty($validated['activation_start']) || ! empty($validated['activation_end'])) {
            $activationWindow = [
                'start' => $validated['activation_start'] ?? null,
                'end' => $validated['activation_end'] ?? null,
            ];
        }

        $validated['cta'] = $cta;
        $validated['activation_window'] = $activationWindow;

        // Handle featured image - normalize and clean the data
        if (isset($validated['featured_image'])) {
            $featuredImage = $validated['featured_image'];
            // Only keep if there's actually an asset ID or URL
            if (! empty($featuredImage['assetId']) || ! empty($featuredImage['url'])) {
                $validated['featured_image'] = [
                    'assetId' => $featuredImage['assetId'] ?? null,
                    'url' => $featuredImage['url'] ?? null,
                    'alt' => $featuredImage['alt'] ?? null,
                    'caption' => $featuredImage['caption'] ?? null,
                    'credit' => $featuredImage['credit'] ?? null,
                    'filename' => $featuredImage['filename'] ?? null,
                ];
            } else {
                $validated['featured_image'] = null;
            }
        }

        // Convert HTML content to Portable Text if it's a string
        if (isset($validated['content']) && is_string($validated['content'])) {
            $validated['content'] = $this->htmlToPortableText->convert($validated['content']);
        }

        // Unset fields that don't exist in the database model
        unset(
            $validated['cta_text'],
            $validated['cta_url'],
            $validated['activation_start'],
            $validated['activation_end']
        );

        return $validated;
    }
}
