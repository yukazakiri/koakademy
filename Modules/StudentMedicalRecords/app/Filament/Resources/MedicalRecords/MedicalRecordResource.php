<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Pages\CreateMedicalRecord;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Pages\EditMedicalRecord;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Pages\ListMedicalRecords;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Schemas\MedicalRecordForm;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Tables\MedicalRecordsTable;
use Modules\StudentMedicalRecords\Models\MedicalRecord;
use UnitEnum;

final class MedicalRecordResource extends Resource
{
    protected static ?string $model = MedicalRecord::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Medical Records';

    protected static ?string $modelLabel = 'Medical Record';

    protected static ?string $pluralModelLabel = 'Medical Records';

    protected static UnitEnum|string|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return MedicalRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalRecords::route('/'),
            'create' => CreateMedicalRecord::route('/create'),
            'edit' => EditMedicalRecord::route('/{record}/edit'),
        ];
    }
}
