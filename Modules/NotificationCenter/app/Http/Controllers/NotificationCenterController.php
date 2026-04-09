<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NotificationCenter\Http\Requests\SendNotificationRequest;
use Modules\NotificationCenter\Jobs\SendBulkNotificationJob;
use Modules\NotificationCenter\Models\NotificationTemplate;

final class NotificationCenterController extends Controller
{
    public function index(): Response
    {
        $templates = NotificationTemplate::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn ($template) => [
                'slug' => $template->slug,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'variables' => $template->variables,
                'default_channels' => $template->default_channels,
                'styles' => $template->styles,
            ])
            ->values()
            ->all();

        $templatesByCategory = collect($templates)
            ->groupBy('category')
            ->map(fn ($group) => $group->values()->all())
            ->all();

        return Inertia::render('NotificationCenter/Index', [
            'templates' => $templates,
            'templatesByCategory' => $templatesByCategory,
        ]);
    }

    public function store(SendNotificationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SendBulkNotificationJob::dispatch(
            $validated['target_audience'],
            $validated['channels'],
            $validated['title'],
            $validated['content'],
            $validated['type'] ?? 'info',
            $validated['icon'] ?? 'bell',
            $validated['actions'] ?? [],
            $validated['template_slug'] ?? null,
            $validated['template_data'] ?? []
        );

        return redirect()->back()->with('success', 'Notifications are being dispatched in the background.');
    }

    public function preview(SendNotificationRequest $request, \Modules\NotificationCenter\Services\NotificationTemplateService $templateService): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $title = $validated['title'];
        $content = $validated['content'];
        $type = $validated['type'] ?? 'info';
        $icon = $validated['icon'] ?? 'bell';
        $actions = $validated['actions'] ?? [];
        $templateSlug = $validated['template_slug'] ?? null;
        $templateData = $validated['template_data'] ?? [];
        $channels = $validated['channels'] ?? ['mail', 'database'];

        $dummyUser = new \App\Models\User([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);
        $dummyUser->id = 1;

        if ($templateSlug && $templateService->templateExists($templateSlug)) {
            $notificationData = array_merge([
                'title' => $title,
                'content' => $content,
            ], $templateData);

            $notification = $templateService->createNotification($templateSlug, $notificationData, $channels);
        } else {
            $notification = new \Modules\NotificationCenter\Notifications\SendAdminNotification(
                $channels,
                $title,
                $content,
                $type,
                $icon,
                $actions
            );
        }

        $emailHtml = null;
        if (in_array('mail', $channels)) {
            try {
                $mailMessage = $notification->toMail($dummyUser);
                $emailHtml = $mailMessage->render();
            } catch (Exception $e) {
                // Ignore mail render errors for preview
            }
        }

        $databasePayload = null;
        if (in_array('database', $channels)) {
            $databasePayload = $notification->toDatabase($dummyUser);
        }

        return response()->json([
            'email' => $emailHtml,
            'database' => $databasePayload,
        ]);
    }
}
