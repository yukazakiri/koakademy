<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

final class JobProgressModal extends Component
{
    public bool $isOpen = false;

    public string $jobId = '';

    public array $progress = [];

    public string $jobType = 'Assessment Resend';

    public function mount(): void
    {
        $this->progress = [
            'percentage' => 0,
            'message' => 'Initializing...',
            'failed' => false,
            'started_at' => format_timestamp_now(),
            'updated_at' => format_timestamp_now(),
        ];
    }

    #[On('open-progress-modal')]
    public function openModal(string $jobId): void
    {
        $this->jobId = $jobId;
        $this->isOpen = true;
        $this->loadProgress();

        Log::info('Progress modal opened', [
            'job_id' => $jobId,
            'component' => 'JobProgressModal',
        ]);
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->jobId = '';
        $this->resetProgress();
    }

    public function loadProgress(): void
    {
        if ($this->jobId === '' || $this->jobId === '0') {
            return;
        }

        $progressData = cache()->get('assessment_job_progress:'.$this->jobId);

        if ($progressData) {
            $this->progress = array_merge($this->progress, $progressData);
            // If job is completed (100%) or failed, we can show final status
            if ($this->progress['percentage'] >= 100) {
                // Stop polling if completed
                $this->dispatch('job-completed', [
                    'success' => ! $this->progress['failed'],
                    'message' => $this->progress['message'],
                ]);
            }
        } elseif ($this->progress['percentage'] === 0) {
            // If no progress data found, it might be an old job or not started yet
            $this->progress['message'] = 'Job not found or expired. Please try again.';
            $this->progress['failed'] = true;
            $this->progress['percentage'] = 100;
        }

        Log::debug('Progress loaded', [
            'job_id' => $this->jobId,
            'progress' => $this->progress,
        ]);
    }

    public function refreshProgress(): void
    {
        $this->loadProgress();
    }

    public function getProgressColorProperty(): string
    {
        if ($this->progress['failed']) {
            return 'danger';
        }

        if ($this->progress['percentage'] >= 100) {
            return 'success';
        }

        if ($this->progress['percentage'] >= 50) {
            return 'warning';
        }

        return 'primary';
    }

    public function getProgressIconProperty(): string
    {
        if ($this->progress['failed']) {
            return 'heroicon-o-x-circle';
        }

        if ($this->progress['percentage'] >= 100) {
            return 'heroicon-o-check-circle';
        }

        return 'heroicon-o-clock';
    }

    public function render(): View|Factory
    {
        return view('livewire.job-progress-modal');
    }

    private function resetProgress(): void
    {
        $this->progress = [
            'percentage' => 0,
            'message' => 'Initializing...',
            'failed' => false,
            'started_at' => format_timestamp_now(),
            'updated_at' => format_timestamp_now(),
        ];
    }
}
