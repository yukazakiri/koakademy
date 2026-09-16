<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Ai\Agents\BursarFinanceAgent;
use App\Ai\Agents\CampusSupportAgent;
use App\Ai\Agents\RegistrarAuditAgent;
use App\Services\Ai\AiSettingsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Override;
use Throwable;
use UnitEnum;

final class AiAssistant extends Page
{
    public string $selectedAgent = 'registrar_auditor';

    public string $prompt = '';

    public ?string $response = null;

    public bool $isProcessing = false;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'System Tools';

    #[Override]
    protected static ?int $navigationSort = 15;

    #[Override]
    protected string $view = 'filament.pages.ai-assistant';

    #[Override]
    protected static ?string $title = 'AI Assistant Workspace';

    public function askAgent(): void
    {
        $trimmed = mb_trim($this->prompt);

        if ($trimmed === '') {
            Notification::make()
                ->warning()
                ->title('Prompt Required')
                ->body('Please enter a prompt or instruction for the AI agent.')
                ->send();

            return;
        }

        $this->isProcessing = true;

        try {
            $user = Auth::user();

            $agent = match ($this->selectedAgent) {
                'registrar_auditor' => new RegistrarAuditAgent,
                'bursar_finance' => new BursarFinanceAgent,
                'campus_support' => new CampusSupportAgent,
                default => new CampusSupportAgent,
            };

            $agentInstance = $user ? $agent->forUser($user) : $agent;
            $result = $agentInstance->prompt($trimmed);

            $this->response = (string) $result;

            Notification::make()
                ->success()
                ->title('Agent Response Completed')
                ->send();
        } catch (Throwable $e) {
            $this->response = "Error: {$e->getMessage()}";

            Notification::make()
                ->danger()
                ->title('AI Execution Error')
                ->body($e->getMessage())
                ->send();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function clearWorkspace(): void
    {
        $this->prompt = '';
        $this->response = null;
    }

    public function getActiveProviderSummary(): array
    {
        $settings = app(AiSettingsService::class)->get();
        $primary = (string) ($settings['primary_provider'] ?? 'anthropic');
        $supported = AiSettingsService::supportedProviders();

        return [
            'provider' => $supported[$primary]['label'] ?? ucfirst($primary),
            'chat_model' => $settings['providers'][$primary]['default_chat_model'] ?? 'Standard Default',
            'failover' => ($settings['failover_enabled'] ?? false) ? ($settings['fallback_provider'] ?? 'None') : 'Disabled',
        ];
    }
}
