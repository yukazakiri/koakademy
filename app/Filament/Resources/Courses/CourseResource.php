<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses;

use App\Enums\UserRole;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Courses\Schemas\CourseForm;
use App\Filament\Resources\Courses\Tables\CoursesTable;
use App\Models\Course;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Allow access to administrative roles, academic staff, and student services
        if ($user?->role->isAdministrative()) {
            return true;
        }
        if ($user?->role->isFaculty()) {
            return true;
        }

        return (bool) $user?->role->isStudentServices();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        // Only administrative roles and academic leadership can create courses
        if ($user?->role->isAdministrative()) {
            return true;
        }

        return $user?->role === UserRole::Registrar;
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        // Course editing requires administrative or registrar privileges
        if ($user?->role->isAdministrative()) {
            return true;
        }

        return $user?->role === UserRole::Registrar;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        // Only high-level administrators can delete courses
        return $user?->role->isAdministrative();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->role->isAdministrative() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
