<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        $table = config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $connection = $this->getConnection();

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $columns = Schema::connection($connection)->getColumnListing($table);

        if (in_array('steps', $columns, true) && in_array('status', $columns, true)) {
            return;
        }

        if (in_array('approval_state', $columns, true)) {
            $pending = $this->query($table)->whereNotNull('approval_state')->get()->contains(function (object $row): bool {
                $calls = $this->decoded($row->tool_calls ?? null);
                $state = $this->decoded($row->approval_state ?? null);

                return ! empty($state['pending']) || collect($calls)->contains(fn (array $call): bool => isset($call['approval_reason']) && ! array_key_exists('result', $call));
            });

            if ($pending) {
                throw new RuntimeException('Resolve or abandon pending AI tool approvals before running the Laravel AI SDK 1.0 conversation migration.');
            }
        }

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->longText('steps')->nullable();
            $blueprint->string('status', 25)->default(MessageStatus::Completed->value);
        });

        $this->query($table)->where('role', 'user')->update(['steps' => '[]']);

        $this->query($table)
            ->select('conversation_id')
            ->distinct()
            ->orderBy('conversation_id')
            ->chunk(100, function (Collection $conversations) use ($table): void {
                foreach ($conversations as $conversation) {
                    $this->backfill($table, (string) $conversation->conversation_id);
                }
            });

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->longText('steps')->nullable(false)->change();
            $toDrop = array_values(array_intersect(['tool_calls', 'tool_results', 'approval_state'], $columns));
            if ($toDrop !== []) {
                $blueprint->dropColumn($toDrop);
            }
        });

        if (Schema::connection($connection)->hasTable($table)
            && in_array('agent', Schema::connection($connection)->getColumnListing($table), true)) {
            $indexes = collect(Schema::connection($connection)->getIndexes($table));
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($indexes): void {
                if ($indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'participant_index')) {
                    $blueprint->dropIndex('participant_index');
                }
                $blueprint->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
            });
        }
    }

    public function down(): void
    {
        // The backfill rewrites conversation history and drops source columns; rollback is intentionally unsupported.
    }

    protected function backfill(string $table, string $conversationId): void
    {
        $rows = $this->query($table)
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('id')
            ->get();

        $results = $rows->flatMap(fn (object $row): array => $this->decoded($row->tool_results ?? null))->keyBy('id');

        foreach ($rows as $row) {
            $meta = $this->decoded($row->meta ?? null);
            $calls = collect($this->decoded($row->tool_calls ?? null))
                ->map(function (array $call) use ($results): array {
                    if (! $results->has($call['id'] ?? '')) {
                        return $call;
                    }

                    $result = $results[$call['id']];

                    return [
                        ...$call,
                        'result' => $result['result'] ?? null,
                        ...array_filter([
                            'denied' => $result['denied'] ?? false,
                            'failed' => $result['failed'] ?? false,
                        ]),
                    ];
                })
                ->values()
                ->all();

            $content = (string) $row->content;
            $steps = $calls !== [] && $content !== ''
                ? [$this->step('', $calls), $this->step($content, [], (string) ($meta['reasoning'] ?? ''))]
                : [$this->step($content, $calls, (string) ($meta['reasoning'] ?? ''))];

            unset($meta['provider_steps'], $meta['provider_content_blocks'], $meta['reasoning']);

            $this->query($table)->where('id', $row->id)->update([
                'steps' => json_encode($steps, JSON_THROW_ON_ERROR),
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'status' => MessageStatus::Completed->value,
            ]);
        }
    }

    /** @param list<array<string, mixed>> $calls @return array<string, mixed> */
    protected function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return [
            'content' => $content,
            'tool_calls' => $calls,
            'reasoning' => $reasoning,
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ];
    }

    /** @return array<string, mixed> */
    protected function decoded(?string $json): array
    {
        $decoded = json_decode($json ?? '', true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function query(string $table): Builder
    {
        return DB::connection($this->getConnection())->table($table);
    }
};
