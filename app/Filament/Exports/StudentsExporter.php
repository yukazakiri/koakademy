<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Students;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class StudentsExporter extends Exporter
{
    protected static ?string $model = Students::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('first_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('middle_name'),
            ExportColumn::make('gender'),
            ExportColumn::make('birth_date'),
            ExportColumn::make('age'),
            ExportColumn::make('address'),
            ExportColumn::make('contacts'),
            ExportColumn::make('course.code'),
            ExportColumn::make('academic_year')
                ->label('Academic Year')
                ->formatStateUsing(fn ($state): string => match ($state) {
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                    '5' => 'Graduate',
                }),
            ExportColumn::make('email'),

            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),

            ExportColumn::make('student_id'),

        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your students export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
