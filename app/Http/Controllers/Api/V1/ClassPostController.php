<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassPostFormRequest;
use App\Http\Resources\ClassPostResource;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\User;
use App\Policies\ClassPostPolicy;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class Post API Controller
 *
 * Provides REST API endpoints for managing class posts and their attachments.
 * All endpoints are protected by Sanctum authentication middleware.
 */
final class ClassPostController extends Controller
{
    /**
     * Display a listing of class posts.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', ClassPost::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = ClassPost::with(['class'])->orderBy('created_at', 'desc');

        $isPrivileged = $user->can('ViewAny:ClassPost')
            || in_array($user->role, [
                UserRole::Developer,
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::Registrar,
                UserRole::AssistantRegistrar,
            ], true);

        if (! $isPrivileged) {
            $policy = app(ClassPostPolicy::class);
            if ($user->isFaculty()) {
                $faculty = $policy->resolveAuthenticatedFaculty($user);
                if ($faculty) {
                    $query->whereHas('class', fn ($q) => $q->where('faculty_id', $faculty->id));
                } else {
                    $query->whereRaw('0 = 1');
                }
            } elseif ($user->isStudentRole()) {
                $student = $policy->resolveAuthenticatedStudent($user);
                if ($student) {
                    $query->whereHas('class.class_enrollments', fn ($q) => $q->where('student_id', $student->id));
                } else {
                    $query->whereRaw('0 = 1');
                }
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        // Filter by class_id if provided
        if ($request->has('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Search by title or content
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->whereLike('title', "%{$search}%")
                    ->orWhereLike('content', "%{$search}%");
            });
        }

        // Paginate results
        $perPage = ApiPagination::perPage($request->input('per_page'));

        $posts = $query->paginate($perPage);

        return ClassPostResource::collection($posts);
    }

    /**
     * Store a newly created class post.
     *
     * @throws ValidationException
     */
    public function store(ClassPostFormRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $classId = (int) $request->input('class_id');
        $class = Classes::findOrFail($classId);
        $this->authorize('create', [ClassPost::class, $class]);

        try {
            $validated = $request->validated();

            // Handle file uploads
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('class-post-attachments', 'private');
                    $attachments[] = $path;
                }
            }

            // If attachments were uploaded as JSON array of base64
            if (isset($validated['attachments']) && is_array($validated['attachments'])) {
                $jsonAttachments = [];
                foreach ($validated['attachments'] as $attachment) {
                    if (is_string($attachment) && str_starts_with($attachment, 'data:')) {
                        // Handle base64 encoded file
                        $decoded = base64_decode(preg_split('@;@', $attachment)[1] ?? '', flags: PREG_SPLIT_NO_EMPTY);
                        $filename = 'attachment_'.time().'_'.uniqid().'.bin';
                        Storage::disk('private')->put("class-post-attachments/{$filename}", $decoded);
                        $jsonAttachments[] = "class-post-attachments/{$filename}";
                    }
                }
                $attachments = array_merge($attachments, $jsonAttachments);
                unset($validated['attachments']);
            }

            $postData = array_merge($validated, [
                'attachments' => $attachments,
            ]);

            $post = ClassPost::create($postData);
            $post->load(['class']);

            return response()->json([
                'message' => 'Class post created successfully',
                'data' => new ClassPostResource($post),
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to create class post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified class post.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = ClassPost::with(['class']);

        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $post = $query->find($id);

        if (! $post) {
            return response()->json([
                'message' => 'Class post not found',
                'data' => null,
            ], 404);
        }

        $this->authorize('view', $post);

        return response()->json([
            'message' => 'Class post retrieved successfully',
            'data' => new ClassPostResource($post),
        ]);
    }

    /**
     * Update the specified class post.
     *
     * @throws ValidationException
     */
    public function update(ClassPostFormRequest $request, int $id): JsonResponse
    {
        $post = ClassPost::with('class')->findOrFail($id);
        $this->authorize('update', $post);

        try {
            $validated = $request->validated();

            if (isset($validated['class_id']) && (int) $validated['class_id'] !== (int) $post->class_id) {
                $destClass = Classes::findOrFail((int) $validated['class_id']);
                $this->authorize('create', [ClassPost::class, $destClass]);
            }

            // Handle new file uploads
            if ($request->hasFile('attachments')) {
                // Remove old attachments
                if ($post->attachments) {
                    foreach ($post->attachments as $attachment) {
                        Storage::disk('private')->delete($attachment);
                        Storage::disk('public')->delete($attachment);
                    }
                }

                // Upload new attachments
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('class-post-attachments', 'private');
                    $attachments[] = $path;
                }
                $validated['attachments'] = $attachments;
            }

            $post->update($validated);
            $post->load(['class']);

            return response()->json([
                'message' => 'Class post updated successfully',
                'data' => new ClassPostResource($post->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update class post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified class post.
     */
    public function destroy(int $id): JsonResponse
    {
        $post = ClassPost::with('class')->findOrFail($id);
        $this->authorize('delete', $post);

        try {
            // Delete associated attachment files
            if ($post->attachments) {
                foreach ($post->attachments as $attachment) {
                    Storage::disk('private')->delete($attachment);
                    Storage::disk('public')->delete($attachment);
                }
            }

            $post->delete();

            return response()->json([
                'message' => 'Class post deleted successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete class post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted class post.
     */
    public function restore(int $id): JsonResponse
    {
        $post = ClassPost::onlyTrashed()->with('class')->findOrFail($id);
        $this->authorize('restore', $post);

        try {
            $post->restore();
            $post->load(['class']);

            return response()->json([
                'message' => 'Class post restored successfully',
                'data' => new ClassPostResource($post->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to restore class post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force delete a class post permanently.
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $post = ClassPost::withTrashed()->with('class')->findOrFail($id);
        $this->authorize('forceDelete', $post);

        try {
            // Delete associated attachment files
            if ($post->attachments) {
                foreach ($post->attachments as $attachment) {
                    Storage::disk('private')->delete($attachment);
                    Storage::disk('public')->delete($attachment);
                }
            }

            $post->forceDelete();

            return response()->json([
                'message' => 'Class post permanently deleted',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete class post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get posts by class.
     */
    public function byClass(int $classId, Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $class = Classes::findOrFail($classId);
        $policy = app(ClassPostPolicy::class);

        $isPrivileged = $user->can('ViewAny:ClassPost')
            || in_array($user->role, [
                UserRole::Developer,
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::Registrar,
                UserRole::AssistantRegistrar,
            ], true);

        abort_unless(
            $isPrivileged
            || ($user->isFaculty() && $policy->ownsClass($user, $class))
            || ($user->isStudentRole() && $policy->isEnrolledInClass($user, $classId)),
            403
        );

        $query = ClassPost::where('class_id', $classId)
            ->with(['class'])
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Paginate results
        $perPage = ApiPagination::perPage($request->input('per_page'));

        $posts = $query->paginate($perPage);

        return ClassPostResource::collection($posts);
    }

    /**
     * Upload attachment for a specific post.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $post = ClassPost::with('class')->findOrFail($id);
        $this->authorize('update', $post);

        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar',
        ]);

        try {
            $existingAttachments = $post->attachments ?? [];

            $newAttachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('class-post-attachments', 'private');
                $newAttachments[] = $path;
            }

            $post->attachments = array_merge($existingAttachments, $newAttachments);
            $post->save();

            return response()->json([
                'message' => 'Attachment(s) uploaded successfully',
                'data' => [
                    'post' => new ClassPostResource($post->fresh()),
                    'new_attachments' => $newAttachments,
                ],
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to upload attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a specific attachment from a post.
     */
    public function deleteAttachment(int $id, int $attachmentIndex): JsonResponse
    {
        $post = ClassPost::with('class')->findOrFail($id);
        $this->authorize('update', $post);

        try {
            if (! $post->attachments || ! isset($post->attachments[$attachmentIndex])) {
                return response()->json([
                    'message' => 'Attachment not found',
                    'data' => null,
                ], 404);
            }

            // Delete the file from storage
            Storage::disk('private')->delete($post->attachments[$attachmentIndex]);
            Storage::disk('public')->delete($post->attachments[$attachmentIndex]);

            // Remove from array
            array_splice($post->attachments, $attachmentIndex, 1);
            $post->save();

            return response()->json([
                'message' => 'Attachment deleted successfully',
                'data' => new ClassPostResource($post->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a specific attachment from a post.
     */
    public function downloadAttachment(Request $request, int $id, int $attachmentIndex): StreamedResponse
    {
        $post = ClassPost::with('class')->findOrFail($id);
        $this->authorize('view', $post);

        $attachments = $post->attachments ?? [];
        if (! isset($attachments[$attachmentIndex])) {
            abort(404, 'Attachment not found');
        }

        $path = $attachments[$attachmentIndex];
        if (is_array($path)) {
            $path = $path['path'] ?? $path['url'] ?? '';
        }

        if (! is_string($path) || $path === '' || str_contains($path, '..')) {
            abort(404, 'Invalid attachment');
        }

        $disk = 'private';
        if (! Storage::disk('private')->exists($path)) {
            if (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            } elseif (Storage::disk(config('filesystems.default'))->exists($path)) {
                $disk = config('filesystems.default');
            } else {
                abort(404, 'File not found on storage');
            }
        }

        return Storage::disk($disk)->download($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
