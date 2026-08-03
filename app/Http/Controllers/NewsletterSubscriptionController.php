<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NewsletterSubscribeResult;
use App\Models\User;
use App\Services\Newsletter\NewsletterSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request, NewsletterSubscriptionService $newsletter): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->isStudentRole() || $user->isFaculty()), 403);

        $result = $newsletter->subscribe($user);

        if (! $result->succeeded()) {
            $message = $result === NewsletterSubscribeResult::NotConfigured
                ? 'The newsletter service is not available yet. Please try again later.'
                : 'We could not subscribe you right now. Please try again in a moment.';

            return back()->with('newsletter_feedback', [
                'type' => 'error',
                'message' => $message,
            ]);
        }

        $message = $result === NewsletterSubscribeResult::AlreadySubscribed
            ? "You're already on our newsletter list — no further action needed."
            : "You're subscribed! School news and announcements will be sent to your inbox.";

        return back()->with('newsletter_feedback', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function decline(Request $request, NewsletterSubscriptionService $newsletter): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->isStudentRole() || $user->isFaculty()), 403);

        $newsletter->decline($user);

        return back();
    }
}
