<?php

declare(strict_types=1);

namespace Modules\Announcement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Announcement\Models\Announcement;

final class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = Auth::user();

        $announcements = Announcement::query()
            ->with('creator:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('Announcement/Index', [
            'user' => $user,
            'announcements' => $announcements,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:info,warning,danger,success,maintenance,enrollment,update',
            'priority' => 'nullable|string|in:urgent,high,medium,low',
            'display_mode' => 'nullable|string|in:banner,toast,modal',
            'requires_acknowledgment' => 'boolean',
            'link' => 'nullable|string|url',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['priority'] = $validated['priority'] ?? 'medium';
        $validated['display_mode'] = $validated['display_mode'] ?? 'banner';
        $validated['requires_acknowledgment'] = $validated['requires_acknowledgment'] ?? false;

        Announcement::create($validated);

        return redirect()->back()->with('success', 'Announcement created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:info,warning,danger,success,maintenance,enrollment,update',
            'priority' => 'nullable|string|in:urgent,high,medium,low',
            'display_mode' => 'nullable|string|in:banner,toast,modal',
            'requires_acknowledgment' => 'boolean',
            'link' => 'nullable|string|url',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['priority'] = $validated['priority'] ?? 'medium';
        $validated['display_mode'] = $validated['display_mode'] ?? 'banner';
        $validated['requires_acknowledgment'] = $validated['requires_acknowledgment'] ?? false;

        $announcement->update($validated);

        return redirect()->back()->with('success', 'Announcement updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->back()->with('success', 'Announcement deleted successfully.');
    }
}
