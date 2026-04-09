<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\SubjectEnrollment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class SubjectEnrollmentExporter extends Exporter
{
    protected static ?string $model = SubjectEnrollment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('student.id')
                ->label('Student ID'),
            ExportColumn::make('student.full_name')
                ->label('Student Name'),
            ExportColumn::make('subject.code')
                ->label('Subject Code'),
            ExportColumn::make('subject.title')
                ->label('Subject Title'),
            ExportColumn::make('subject.units')
                ->label('Units'),
            ExportColumn::make('classification')
                ->label('Classification'),
            ExportColumn::make('grade')
                ->label('Grade')
                ->formatStateUsing(fn ($state): string => $state ? number_format((float) $state, 2) : 'No Grade'),
            ExportColumn::make('instructor')
                ->label('Instructor'),
            ExportColumn::make('academic_year')
                ->label('Academic Year')
                ->formatStateUsing(fn ($state) => match ($state) {
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                    default => $state,
                }),
            ExportColumn::make('school_year')
                ->label('School Year'),
            ExportColumn::make('semester')
                ->label('Semester')
                ->formatStateUsing(fn ($state) => match ($state) {
                    1 => '1st Semester',
                    2 => '2nd Semester',
                    3 => 'Summer',
                    default => $state,
                }),
            ExportColumn::make('remarks')
                ->label('Remarks'),
            ExportColumn::make('school_name')
                ->label('School Name'),
            ExportColumn::make('created_at')
                ->label('Enrolled Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your subject enrollment export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
