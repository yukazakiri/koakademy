<?php

declare(strict_types=1);

use App\Filament\Clusters\SeniorHighSchool\SeniorHighSchoolCluster;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Pages\Backups;
use App\Filament\Pages\ManageStudentClearances;
use App\Filament\Pages\Timetable;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Classes\ClassesResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Faculties\FacultyResource;
use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Schools\SchoolResource;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Users\UserResource;
use Modules\Announcement\Filament\Resources\Announcements\AnnouncementResource;
use Modules\Cashier\Filament\Pages\Cashier;
use Modules\Inventory\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;
use Modules\Inventory\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;
use Modules\Inventory\Filament\Resources\InventoryCategories\InventoryCategoryResource;
use Modules\Inventory\Filament\Resources\InventoryProducts\InventoryProductResource;
use Modules\Inventory\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;
use Modules\Inventory\Filament\Resources\InventorySuppliers\InventorySupplierResource;
use Modules\LibrarySystem\Filament\Resources\Authors\AuthorResource;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\BorrowRecordResource;
use Modules\LibrarySystem\Filament\Resources\Categories\CategoryResource;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\MedicalRecordResource;

function navigationStaticProperty(string $class, string $property): mixed
{
    $reflection = new ReflectionClass($class);
    $navigationProperty = $reflection->getProperty($property);
    $navigationProperty->setAccessible(true);

    return $navigationProperty->getValue();
}

it('keeps admin navigation groups, labels, and plugin config aligned', function (): void {
    $expectedGroups = [
        CourseResource::class => 'Academics',
        ClassesResource::class => 'Academics',
        StudentEnrollmentResource::class => 'Academics',
        ManageStudentClearances::class => 'Academics',
        Timetable::class => 'Academics',
        SeniorHighSchoolCluster::class => 'Academics',
        StudentResource::class => 'People',
        FacultyResource::class => 'People',
        UserResource::class => 'People',
        MedicalRecordResource::class => 'People',
        SchoolResource::class => 'Campus',
        DepartmentResource::class => 'Campus',
        RoomResource::class => 'Campus',
        Cashier::class => 'Finance',
        InventoryCategoryResource::class => 'Inventory',
        InventorySupplierResource::class => 'Inventory',
        InventoryProductResource::class => 'Inventory',
        InventoryStockMovementResource::class => 'Inventory',
        InventoryBorrowingResource::class => 'Inventory',
        InventoryAmendmentResource::class => 'Inventory',
        BookResource::class => 'Library',
        AuthorResource::class => 'Library',
        CategoryResource::class => 'Library',
        BorrowRecordResource::class => 'Library',
        EventResource::class => 'Operations',
        AnnouncementResource::class => 'Communications',
        OnboardingFeatureResource::class => 'Content',
        SettingsCluster::class => 'Administration',
        AccountResource::class => 'Administration',
        Backups::class => 'System Tools',
    ];

    foreach ($expectedGroups as $class => $group) {
        expect(navigationStaticProperty($class, 'navigationGroup'))->toBe($group);
    }

    expect(Backups::getNavigationGroup())->toBe('System Tools');

    $expectedLabels = [
        StudentEnrollmentResource::class => 'Enrollments',
        ManageStudentClearances::class => 'Clearances',
        FacultyResource::class => 'Faculty',
        AccountResource::class => 'Portal Accounts',
        OnboardingFeatureResource::class => 'Onboarding',
        InventoryStockMovementResource::class => 'Stock Movements',
        InventoryAmendmentResource::class => 'Stock Adjustments',
        CategoryResource::class => 'Book Categories',
        MedicalRecordResource::class => 'Medical Records',
    ];

    foreach ($expectedLabels as $class => $label) {
        expect(navigationStaticProperty($class, 'navigationLabel'))->toBe($label);
    }

    $mailsConfig = require base_path('config/mails.php');
    $filamentMailsConfig = require base_path('config/filament-mails.php');
    $apiServiceConfig = require base_path('config/api-service.php');
    $activityLogConfig = require base_path('config/filament-activity-log.php');

    expect($mailsConfig['navigation']['group'])->toBe('Communications')
        ->and($filamentMailsConfig['navigation']['group'])->toBe('Communications')
        ->and($apiServiceConfig['navigation']['token']['group'])->toBe('Administration')
        ->and($activityLogConfig['resource']['group'])->toBe('System Tools')
        ->and($activityLogConfig['pages']['user_activities']['navigation_group'])->toBe('System Tools');
});
