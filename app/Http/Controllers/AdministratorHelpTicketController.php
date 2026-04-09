<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpTicket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

final class AdministratorHelpTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = HelpTicket::query()->with('user');

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('message', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search): void {
                        $q->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', (string) $request->input('status')));
        }

        if ($request->filled('priority')) {
            $query->whereIn('priority', explode(',', (string) $request->input('priority')));
        }

        if ($request->filled('type')) {
            $query->whereIn('type', explode(',', (string) $request->input('type')));
        }

        // Sorting
        $sortColumn = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_order', 'desc');

        // Allowed sort columns
        if (in_array($sortColumn, ['created_at', 'updated_at', 'priority', 'status', 'type'])) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $tickets = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => HelpTicket::count(),
            'open' => HelpTicket::where('status', 'open')->count(),
            'resolved' => HelpTicket::where('status', 'resolved')->count(),
            'high_priority' => HelpTicket::where('status', 'open')->where('priority', 'high')->count(),
        ];

        return Inertia::render('administrators/help/index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'status', 'priority', 'type']),
            'stats' => $stats,
        ]);
    }

    public function show(HelpTicket $helpTicket)
    {
        $helpTicket->load(['user', 'replies.user']);

        return Inertia::render('administrators/help/show', [
            'ticket' => $helpTicket,
        ]);
    }

    public function reply(Request $request, HelpTicket $helpTicket)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs(
                    "help-tickets/{$helpTicket->user_id}/{$helpTicket->id}",
                    $filename,
                    'r2'
                );

                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => Storage::disk('r2')->url($path),
                    'type' => $file->getClientMimeType(),
                ];
            }
        }

        $helpTicket->replies()->create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'attachments' => $attachments,
        ]);

        // Auto update status to in_progress if it was open
        if ($helpTicket->status === 'open') {
            $helpTicket->update(['status' => 'in_progress']);
        }

        // Notify the user who created the ticket
        Notification::make()
            ->title('Support Ticket Update')
            ->body("An administrator has replied to your ticket: \"{$helpTicket->subject}\"")
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->button()
                    ->url(route('help.show', $helpTicket->id)),
            ])
            ->sendToDatabase($helpTicket->user);

        return back()->with('success', 'Reply sent successfully.');
    }

    public function update(Request $request, HelpTicket $helpTicket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $oldStatus = $helpTicket->status;
        $helpTicket->update($validated);

        // Notify user if status changed to resolved or closed
        if ($oldStatus !== $helpTicket->status && in_array($helpTicket->status, ['resolved', 'closed'])) {
            Notification::make()
                ->title('Ticket '.ucfirst($helpTicket->status))
                ->body("Your ticket \"{$helpTicket->subject}\" has been marked as {$helpTicket->status}.")
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->actions([
                    Action::make('view')
                        ->button()
                        ->url(route('help.show', $helpTicket->id)),
                ])
                ->sendToDatabase($helpTicket->user);
        }

        return back()->with('success', 'Ticket updated successfully.');
    }

    public function destroy(HelpTicket $helpTicket)
    {
        $helpTicket->delete();

        return redirect()->route('administrators.help-tickets.index')->with('success', 'Ticket deleted successfully.');
    }
}
