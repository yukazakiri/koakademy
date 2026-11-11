<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HelpTicketAttachmentController extends Controller
{
    public function download(Request $request, HelpTicket $ticket, string $filename): StreamedResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        if ($ticket->user_id !== $user->id && ! $user->canAccessAdminPortal()) {
            abort(403);
        }

        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(400, 'Invalid filename.');
        }

        $ticket->loadMissing('replies');

        $allAttachments = collect($ticket->attachments ?? []);
        foreach ($ticket->replies as $reply) {
            $allAttachments = $allAttachments->merge($reply->attachments ?? []);
        }

        $matching = $allAttachments->first(function ($att) use ($filename): bool {
            if (! is_array($att)) {
                return false;
            }

            $attFilename = basename((string) ($att['path'] ?? parse_url($att['url'] ?? '', PHP_URL_PATH) ?? ''));

            return $attFilename === $filename || ($att['name'] ?? '') === $filename;
        });

        if (! $matching) {
            abort(404, 'Attachment not found.');
        }

        $path = $matching['path'] ?? "help-tickets/{$ticket->user_id}/{$ticket->id}/{$filename}";
        $originalName = $matching['name'] ?? $filename;

        $disk = 'private';
        if (! Storage::disk('private')->exists($path)) {
            if (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            } elseif (Storage::disk(config('filesystems.default'))->exists($path)) {
                $disk = config('filesystems.default');
            } else {
                abort(404, 'File not found on storage.');
            }
        }

        return Storage::disk($disk)->download($path, $originalName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
