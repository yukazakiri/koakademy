<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class AiHelpDeskAssistant implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'ai-help-desk-assistant';
    }

    public function name(): string
    {
        return 'AI Campus Support Assistant';
    }

    public function summary(): string
    {
        return '24/7 semantic search across campus manuals, handbooks, and institutional policies with ticket escalation.';
    }

    public function audience(): string
    {
        return 'all';
    }

    public function badge(): string
    {
        return 'Campus Support';
    }

    public function accent(): string
    {
        return 'text-teal-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Support';
    }

    public function ctaUrl(): string
    {
        return '/help';
    }

    public function steps(): array
    {
        return [
            [
                'title' => '24/7 Campus Support',
                'summary' => 'Conversational answers from campus handbooks, fee tables, and registration calendars.',
                'highlights' => ['Knowledge base retrieval', 'Ticket status lookup', 'Automatic support ticket creation'],
                'stats' => [
                    ['label' => 'Coverage', 'value' => 'All Authenticated Users'],
                    ['label' => 'RAG Engine', 'value' => 'Vector Similarity'],
                ],
                'badge' => 'Campus Support',
                'accent' => 'text-teal-500',
                'icon' => 'help-circle',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Artificial Intelligence';
    }
}
