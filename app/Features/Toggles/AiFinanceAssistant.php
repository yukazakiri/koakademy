<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class AiFinanceAssistant implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function resolve(User $scope): bool
    {
        if ($this->globalFeatureState() === false) {
            return false;
        }

        return $scope->canAccessAdminPortal() || $scope->isStudentRole();
    }

    public function key(): string
    {
        return 'ai-finance-assistant';
    }

    public function name(): string
    {
        return 'AI Bursar & Finance Assistant';
    }

    public function summary(): string
    {
        return 'Intelligent fee breakdown explanations, tuition adjustment spreadsheet validation, and scholarship simulation.';
    }

    public function audience(): string
    {
        return 'finance';
    }

    public function badge(): string
    {
        return 'AI Finance';
    }

    public function accent(): string
    {
        return 'text-amber-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Finance';
    }

    public function ctaUrl(): string
    {
        return '/administrators/system-management/finance-documents';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'AI Finance Intelligence',
                'summary' => 'Demystify tuition assessments, validate batch adjustment imports, and audit student ledgers.',
                'highlights' => ['Statement of Account explanations', 'Adjustment sheet screening', 'Scholarship simulations'],
                'stats' => [
                    ['label' => 'Domain', 'value' => 'Accounting & Bursar'],
                    ['label' => 'Ledger Safety', 'value' => 'Human Approval Required'],
                ],
                'badge' => 'AI Finance',
                'accent' => 'text-amber-500',
                'icon' => 'calculator',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Artificial Intelligence';
    }
}
