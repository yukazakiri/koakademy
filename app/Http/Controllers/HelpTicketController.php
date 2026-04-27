<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpTicket;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

final class HelpTicketController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tickets = HelpTicket::where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('Help/Index', [
            'tickets' => $tickets,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'User',
            ],
            'submit_url' => route('help.store'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:support,issue,recommendation'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $ticket = HelpTicket::create([
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'],
        ]);

        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs(
                    "help-tickets/{$ticket->user_id}/{$ticket->id}",
                    $filename
                );

                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => Storage::url($path),
                    'type' => $file->getClientMimeType(),
                ];
            }
            $ticket->update(['attachments' => $attachments]);
        }

        // Notify admins about the new ticket
        $admins = User::whereIn('role', ['admin', 'super_admin', 'developer', 'student_affairs_officer', 'it_support'])->get();

        Notification::make()
            ->title('New Help Ticket')
            ->body('A new ticket has been submitted by "'.Auth::user()->name."\": \"{$ticket->subject}\"")
            ->icon('heroicon-o-ticket')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->button()
                    ->url(route('administrators.help-tickets.show', $ticket->id)),
            ])
            ->sendToDatabase($admins);

        return redirect()->back()->with('success', 'Ticket submitted successfully.');
    }

    public function show(HelpTicket $helpTicket)
    {
        $user = Auth::user();

        // Check if user is owner OR has an admin role
        $isAdmin = $user->canAccessAdminPortal();

        if ($helpTicket->user_id !== $user->id && ! $isAdmin) {
            abort(403);
        }

        $helpTicket->load(['user', 'replies.user']);

        return Inertia::render('Help/Show', [
            'ticket' => $helpTicket,
            'user' => $user,
        ]);
    }

    public function reply(Request $request, HelpTicket $helpTicket)
    {
        $user = Auth::user();

        // Check if user is owner OR has an admin role
        $isAdmin = $user->canAccessAdminPortal();

        if ($helpTicket->user_id !== $user->id && ! $isAdmin) {
            abort(403);
        }

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
                    $filename
                );

                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => Storage::url($path),
                    'type' => $file->getClientMimeType(),
                ];
            }
        }

        $helpTicket->replies()->create([
            'user_id' => $user->id,
            'message' => $validated['message'],
            'attachments' => $attachments,
        ]);

        if (in_array($helpTicket->status, ['resolved', 'closed'])) {
            $helpTicket->update(['status' => 'open']);
        }

        // Notify admins if a user replied
        if (! $isAdmin) {
            $admins = User::whereIn('role', ['admin', 'super_admin', 'developer', 'student_affairs_officer', 'it_support'])->get();

            Notification::make()
                ->title('New Help Ticket Reply')
                ->body("User \"{$user->name}\" replied to ticket #{$helpTicket->id}: \"{$helpTicket->subject}\"")
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('info')
                ->actions([
                    Action::make('view')
                        ->button()
                        ->url(route('administrators.help-tickets.show', $helpTicket->id)),
                ])
                ->sendToDatabase($admins);
        }

        return back()->with('success', 'Reply sent successfully.');
    }
}
