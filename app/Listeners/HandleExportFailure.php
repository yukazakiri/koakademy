<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Filament\Handlers\ExportFailureHandler;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Queue\Events\JobFailed;

final readonly class HandleExportFailure
{
    public function __construct(
        private ExportFailureHandler $failureHandler
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $job = $event->job;
        $exception = $event->exception;

        // Check if this is an export-related job
        $jobClass = $job->resolveName();

        if (! str_contains($jobClass, 'Filament\\Actions\\Exports')) {
            return;
        }

        // Extract export identifiers without instantiating arbitrary serialized classes.
        $payload = $job->payload();
        $exportMeta = $this->extractExportMetadata($payload['data']['command'] ?? null);
        $export = $exportMeta['export_id'] !== null
            ? Export::query()->find($exportMeta['export_id'])
            : null;

        // Check if the exception is related to missing Export model
        if (str_contains($exception->getMessage(), 'No query results for model [Filament\\Actions\\Exports\\Models\\Export]')) {
            if ($exportMeta['user_id'] !== null) {
                $this->failureHandler->handleMissingExport(
                    $exportMeta['exporter'] ?? 'Unknown',
                    $exportMeta['user_id'],
                    $exportMeta['export_id'],
                );
            }
        } elseif ($export instanceof Export) {
            $this->failureHandler->handleExportFailure(
                $export,
                $exception->getMessage(),
            );
        }
    }

    /**
     * Safely inspect a serialized job command string with `allowed_classes => false`
     * so no gadget classes are ever instantiated or woken up.
     *
     * @return array{export_id: int|null, user_id: int|null, exporter: string|null}
     */
    private function extractExportMetadata(mixed $serializedCommand): array
    {
        $result = [
            'export_id' => null,
            'user_id' => null,
            'exporter' => null,
        ];

        if (! is_string($serializedCommand) || $serializedCommand === '') {
            return $result;
        }

        $decoded = @unserialize($serializedCommand, ['allowed_classes' => false]);
        if (! is_object($decoded)) {
            return $result;
        }

        $this->collectExportFields((array) $decoded, $result, 0);

        return $result;
    }

    /**
     * @param  array<mixed>  $values
     * @param  array{export_id: int|null, user_id: int|null, exporter: string|null}  $result
     */
    private function collectExportFields(array $values, array &$result, int $depth): void
    {
        if ($depth > 4) {
            return;
        }

        $className = $values['__PHP_Incomplete_Class_Name'] ?? null;
        if ($className === Export::class) {
            foreach ($values as $key => $val) {
                $cleanKey = is_string($key) ? mb_ltrim(mb_substr($key, mb_strrpos($key, "\0") === false ? 0 : mb_strrpos($key, "\0") + 1)) : '';
                if ($cleanKey === 'attributes' && is_array($val)) {
                    if (isset($val['id']) && is_numeric($val['id'])) {
                        $result['export_id'] ??= (int) $val['id'];
                    }
                    if (isset($val['user_id']) && is_numeric($val['user_id'])) {
                        $result['user_id'] ??= (int) $val['user_id'];
                    }
                    if (isset($val['exporter']) && is_string($val['exporter'])) {
                        $result['exporter'] ??= $val['exporter'];
                    }
                }
            }
        }

        if ($className === \Illuminate\Contracts\Database\ModelIdentifier::class) {
            $modelClass = null;
            $modelId = null;
            foreach ($values as $key => $val) {
                $cleanKey = is_string($key) ? mb_ltrim(mb_substr($key, mb_strrpos($key, "\0") === false ? 0 : mb_strrpos($key, "\0") + 1)) : '';
                if ($cleanKey === 'class' && is_string($val)) {
                    $modelClass = $val;
                } elseif ($cleanKey === 'id' && is_numeric($val)) {
                    $modelId = (int) $val;
                }
            }
            if ($modelClass === Export::class && $modelId !== null) {
                $result['export_id'] ??= $modelId;
            }
        }

        foreach ($values as $val) {
            if (is_object($val)) {
                $this->collectExportFields((array) $val, $result, $depth + 1);
            } elseif (is_array($val)) {
                $this->collectExportFields($val, $result, $depth + 1);
            }
        }
    }
}
