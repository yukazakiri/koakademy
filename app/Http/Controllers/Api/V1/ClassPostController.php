<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassPostFormRequest;
use App\Http\Resources\ClassPostResource;
use App\Models\ClassPost;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        $query = ClassPost::with(['class'])->orderBy('created_at', 'desc');

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
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%");
            });
        }

        // Paginate results
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

        $posts = $query->paginate($perPage);

        return ClassPostResource::collection($posts);
    }

    /**
     * Store a newly created class post.
     *
     *
     * @throws ValidationException
     */
    public function store(ClassPostFormRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Handle file uploads
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('class-post-attachments', 'public');
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
                        Storage::disk('public')->put("class-post-attachments/{$filename}", $decoded);
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

        return response()->json([
            'message' => 'Class post retrieved successfully',
            'data' => new ClassPostResource($post),
        ]);
    }

    /**
     * Update the specified class post.
     *
     *
     * @throws ValidationException
     */
    public function update(ClassPostFormRequest $request, int $id): JsonResponse
    {
        try {
            $post = ClassPost::findOrFail($id);
            $validated = $request->validated();

            // Handle new file uploads
            if ($request->hasFile('attachments')) {
                // Remove old attachments
                if ($post->attachments) {
                    foreach ($post->attachments as $attachment) {
                        Storage::disk('public')->delete($attachment);
                    }
                }

                // Upload new attachments
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('class-post-attachments', 'public');
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
        try {
            $post = ClassPost::findOrFail($id);

            // Delete associated attachment files
            if ($post->attachments) {
                foreach ($post->attachments as $attachment) {
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
        try {
            $post = ClassPost::onlyTrashed()->findOrFail($id);
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
        try {
            $post = ClassPost::withTrashed()->findOrFail($id);

            // Delete associated attachment files
            if ($post->attachments) {
                foreach ($post->attachments as $attachment) {
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
        $query = ClassPost::where('class_id', $classId)
            ->with(['class'])
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Paginate results
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $posts = $query->paginate($perPage);

        return ClassPostResource::collection($posts);
    }

    /**
     * Upload attachment for a specific post.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar',
        ]);

        try {
            $post = ClassPost::findOrFail($id);
            $existingAttachments = $post->attachments ?? [];

            $newAttachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('class-post-attachments', 'public');
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
        try {
            $post = ClassPost::findOrFail($id);

            if (! $post->attachments || ! isset($post->attachments[$attachmentIndex])) {
                return response()->json([
                    'message' => 'Attachment not found',
                    'data' => null,
                ], 404);
            }

            // Delete the file from storage
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
}
