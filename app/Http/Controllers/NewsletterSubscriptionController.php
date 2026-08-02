<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NewsletterSubscriptionStatus;
use App\Enums\SequenzySubscribeResult;
use App\Models\NewsletterSubscription;
use App\Models\User;
use App\Services\SequenzySubscriberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request, SequenzySubscriberService $sequenzy): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->isStudentRole() || $user->isFaculty()), 403);

        $result = $sequenzy->subscribe($user);

        if (! $result->succeeded()) {
            $message = $result === SequenzySubscribeResult::NotConfigured
                ? 'The newsletter service is not available yet. Please try again later.'
                : 'We could not subscribe you right now. Please try again in a moment.';

            return back()->with('newsletter_feedback', [
                'type' => 'error',
                'message' => $message,
            ]);
        }

        NewsletterSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'email' => (string) $user->email,
                'status' => NewsletterSubscriptionStatus::Subscribed,
                'subscribed_at' => now(),
                'declined_at' => null,
            ],
        );

        $message = $result === SequenzySubscribeResult::AlreadySubscribed
            ? "You're already on our newsletter list — no further action needed."
            : "You're subscribed! School news and announcements will be sent to your inbox.";

        return back()->with('newsletter_feedback', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function decline(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->isStudentRole() || $user->isFaculty()), 403);

        NewsletterSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'email' => (string) $user->email,
                'status' => NewsletterSubscriptionStatus::Declined,
                'declined_at' => now(),
            ],
        );

        return back();
    }
}
