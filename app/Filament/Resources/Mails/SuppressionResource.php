<?php

declare(strict_types=1);

namespace App\Filament\Resources\Mails;

use App\Filament\Resources\Mails\Pages\ListSuppressions;
use Backstage\Mails\Laravel\Enums\EventType;
use Backstage\Mails\Resources\SuppressionResource as BaseSuppressionResource;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class SuppressionResource extends BaseSuppressionResource
{
    public static function getEloquentQuery(): Builder
    {
        $mailTable = (string) config('mails.database.tables.mails', 'mails');
        $eventTable = (string) config('mails.database.tables.events', 'mail_events');
        $connection = DB::connection();
        $recipientExpression = self::recipientExpression($connection, $mailTable, 'to');
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $eventModel */
        $eventModel = self::getModel();

        return $eventModel::query()
            ->join($mailTable, "{$eventTable}.mail_id", '=', "{$mailTable}.id")
            ->where(function ($query) use ($eventTable): void {
                $query
                    ->where("{$eventTable}.type", EventType::HARD_BOUNCED)
                    ->orWhere("{$eventTable}.type", EventType::COMPLAINED);
            })
            ->whereNull("{$eventTable}.unsuppressed_at")
            ->whereIn(DB::raw($recipientExpression), function ($query) use ($connection, $eventTable, $mailTable): void {
                $query
                    ->from("{$eventTable} as filtered_events")
                    ->join("{$mailTable} as filtered_mails", 'filtered_events.mail_id', '=', 'filtered_mails.id')
                    ->selectRaw('distinct '.self::recipientExpression($connection, 'filtered_mails', 'to'))
                    ->where('filtered_events.type', EventType::HARD_BOUNCED)
                    ->whereNull('filtered_events.unsuppressed_at');
            })
            ->select("{$eventTable}.*", "{$mailTable}.to")
            ->latest("{$eventTable}.occurred_at");
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppressions::route('/'),
        ];
    }

    private static function recipientExpression(Connection $connection, string $table, string $column): string
    {
        $wrappedColumn = $connection->getQueryGrammar()->wrap("{$table}.{$column}");

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => "cast({$wrappedColumn} as char)",
            'pgsql', 'sqlite' => "cast({$wrappedColumn} as text)",
            'sqlsrv' => "cast({$wrappedColumn} as nvarchar(max))",
            default => "cast({$wrappedColumn} as text)",
        };
    }
}
