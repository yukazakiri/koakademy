<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Services\ModuleAdminNavigationService;
use App\Services\TenantContext;
use App\Support\SystemManagementPermissions;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * @return array<int, array{0: string, 1: string}>
 */
function administratorInertiaPageCatalog(): array
{
    return [
        ['administrators.dashboard', 'administrators/dashboard'],
        ['administrators.audit-logs.index', 'administrators/audit-logs/index'],
        ['administrators.classes.index', 'administrators/classes/index'],
        ['administrators.classes.create', 'administrators/classes/create'],
        ['administrators.curriculum.index', 'administrators/curriculum/programs'],
        ['administrators.curriculum.programs.index', 'administrators/curriculum/programs'],
        ['administrators.departments.index', 'administrators/departments/index'],
        ['administrators.departments.create', 'administrators/departments/edit'],
        ['administrators.enrollments.index', 'administrators/enrollments/index'],
        ['administrators.enrollments.applicants', 'administrators/enrollments/applicants'],
        ['administrators.enrollments.create', 'administrators/enrollments/create'],
        ['administrators.faculties.index', 'administrators/faculties/index'],
        ['administrators.faculties.create', 'administrators/faculties/create'],
        ['administrators.feature-toggles.index', 'administrators/feature-toggles/index'],
        ['administrators.finance.index', 'administrators/finance/dashboard'],
        ['administrators.finance.invoices', 'administrators/finance/invoices'],
        ['administrators.finance.payments', 'administrators/finance/payments'],
        ['administrators.finance.payments.create', 'administrators/finance/create-payment'],
        ['administrators.finance.reports', 'administrators/finance/reports'],
        ['administrators.finance.tuition-adjustments.index', 'administrators/finance/tuition-adjustments'],
        ['administrators.finance.tuition-update-requests.index', 'administrators/finance/tuition-update-requests/index'],
        ['administrators.help-tickets.index', 'administrators/help/index'],
        ['administrators.inventory.index', 'administrators/inventory/index'],
        ['administrators.inventory.items.index', 'administrators/inventory/items/index'],
        ['administrators.inventory.items.create', 'administrators/inventory/items/edit'],
        ['administrators.inventory.borrowings.index', 'administrators/inventory/borrowings/index'],
        ['administrators.inventory.borrowings.create', 'administrators/inventory/borrowings/edit'],
        ['administrators.inventory.ledger.index', 'administrators/inventory/ledger/index'],
        ['administrators.library.index', 'administrators/library/index'],
        ['administrators.library.authors.index', 'administrators/library/authors/index'],
        ['administrators.library.authors.create', 'administrators/library/authors/edit'],
        ['administrators.library.books.index', 'administrators/library/books/index'],
        ['administrators.library.books.create', 'administrators/library/books/edit'],
        ['administrators.library.borrow-records.index', 'administrators/library/borrow-records/index'],
        ['administrators.library.borrow-records.create', 'administrators/library/borrow-records/edit'],
        ['administrators.library.categories.index', 'administrators/library/categories/index'],
        ['administrators.library.categories.create', 'administrators/library/categories/edit'],
        ['administrators.library.research-papers.index', 'administrators/library/research-papers/index'],
        ['administrators.library.research-papers.create', 'administrators/library/research-papers/edit'],
        ['administrators.module-marketplace.index', 'administrators/module-marketplace/index'],
        ['administrators.notifications.index', 'NotificationCenter/Index'],
        ['administrators.notifications.inbox', 'notifications/index'],
        ['administrators.registrar.analytics.index', 'administrators/registrar/analytics'],
        ['administrators.registrar.reports.index', 'administrators/registrar/reports'],
        ['administrators.roles.index', 'administrators/roles/index'],
        ['administrators.scheduling-analytics.index', 'administrators/scheduling-analytics'],
        ['administrators.settings.index', 'profile'],
        ['administrators.settings.newsletter.index', 'administrators/system-management/newsletter'],
        ['administrators.students.index', 'administrators/students/index'],
        ['administrators.students.create', 'administrators/students/create'],
        ['administrators.students.documents.list', 'administrators/students/documents/list'],
        ['administrators.system-management.index', 'administrators/system-management/index'],
        ['administrators.users.index', 'administrators/users/index'],
        ['administrators.users.create', 'administrators/users/create'],
        ['administrators.announcements.index', 'Announcement/Index'],
        ['administrators.system-management.school.index', 'administrators/system-management/school'],
        ['administrators.system-management.enrollment-pipeline.index', 'administrators/system-management/enrollment-pipeline'],
        ['administrators.system-management.seo.index', 'administrators/system-management/seo'],
        ['administrators.system-management.analytics.index', 'administrators/system-management/analytics'],
        ['administrators.system-management.brand.index', 'administrators/system-management/brand'],
        ['administrators.system-management.brand.appearance.index', 'administrators/system-management/brand'],
        ['administrators.system-management.socialite.index', 'administrators/system-management/socialite'],
        ['administrators.system-management.mail.index', 'administrators/system-management/mail'],
        ['administrators.system-management.newsletter.index', 'administrators/system-management/newsletter'],
        ['administrators.system-management.api.index', 'administrators/system-management/api'],
        ['administrators.system-management.notifications.index', 'administrators/system-management/notifications'],
        ['administrators.system-management.finance_documents.index', 'administrators/system-management/finance-documents'],
        ['administrators.system-management.tuition_payment_schedule.index', 'administrators/system-management/tuition-payment-schedule'],
        ['administrators.system-management.grading.index', 'administrators/system-management/grading'],
        ['administrators.system-management.identifiers.index', 'administrators/system-management/identifiers'],
        ['administrators.system-management.faculty-fields.index', 'administrators/system-management/faculty-fields'],
        ['administrators.system-management.pulse.index', 'administrators/system-management/pulse'],
    ];
}

/**
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function administratorInertiaParameterizedPageCatalog(): array
{
    return [
        ['administrators.classes.edit', 'administrators/classes/create', 'class'],
        ['administrators.classes.show', 'administrators/classes/show', 'class'],
        ['administrators.curriculum.programs.show', 'administrators/curriculum/programs/show', 'course'],
        ['administrators.departments.edit', 'administrators/departments/edit', 'department'],
        ['administrators.enrollments.show', 'administrators/enrollments/show', 'enrollment'],
        ['administrators.enrollments.edit', 'administrators/enrollments/edit', 'enrollment'],
        ['administrators.enrollments.assessment-preview', 'administrators/enrollments/assessment-preview', 'enrollment'],
        ['administrators.faculties.show', 'administrators/faculties/show', 'faculty'],
        ['administrators.faculties.edit', 'administrators/faculties/edit', 'faculty'],
        ['administrators.finance.payments.show', 'administrators/finance/receipt', 'transaction'],
        ['administrators.finance.tuition-adjustments.imports.show', 'administrators/finance/tuition-adjustment-spreadsheet-import', 'spreadsheet-import'],
        ['administrators.finance.tuition-update-requests.show', 'administrators/finance/tuition-update-requests/show', 'tuition-update-request'],
        ['administrators.help-tickets.show', 'administrators/help/show', 'help-ticket'],
        ['administrators.inventory.items.edit', 'administrators/inventory/items/edit', 'inventory-product'],
        ['administrators.inventory.borrowings.edit', 'administrators/inventory/borrowings/edit', 'inventory-borrowing'],
        ['administrators.library.authors.edit', 'administrators/library/authors/edit', 'author'],
        ['administrators.library.books.edit', 'administrators/library/books/edit', 'book'],
        ['administrators.library.borrow-records.edit', 'administrators/library/borrow-records/edit', 'borrow-record'],
        ['administrators.library.categories.edit', 'administrators/library/categories/edit', 'category'],
        ['administrators.library.research-papers.edit', 'administrators/library/research-papers/edit', 'research-paper'],
        ['administrators.roles.edit', 'administrators/roles/edit', 'role'],
        ['administrators.students.show', 'administrators/students/show', 'student'],
        ['administrators.students.edit', 'administrators/students/edit', 'student'],
        ['administrators.students.documents.index', 'administrators/students/documents/index', 'student'],
        ['administrators.users.edit', 'administrators/users/edit', 'user'],
    ];
}

function administratorInertiaAuditUser(): User
{
    $permissionNames = [
        'ViewAny:Announcement',
        'View:Announcement',
        'Create:Announcement',
        'Update:Announcement',
        'Delete:Announcement',
        'View:Cashier',
        'view_tuition_fees',
        'manage_tuition_fees',
        'ViewAny:User',
        'ViewAny:Student',
        'ViewAny:Faculty',
        'ViewAny:Department',
        'ViewAny:StudentEnrollment',
    ];

    foreach (SystemManagementPermissions::sectionKeys() as $section) {
        $permissionNames[] = SystemManagementPermissions::viewPermission($section);

        $updatePermission = SystemManagementPermissions::updatePermission($section);
        if ($updatePermission !== null) {
            $permissionNames[] = $updatePermission;
        }
    }

    $permissions = collect($permissionNames)->filter()->unique()->map(function (string $permissionName): Permission {
        return Permission::findOrCreate($permissionName, 'web');
    });

    $school = School::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $school->id,
    ]);
    $user->givePermissionTo($permissions);
    $user->assignRole(Role::findOrCreate(UserRole::Admin->value, 'web'));

    app(TenantContext::class)->setCurrentSchool($school);
    config([
        'modules-marketplace.enabled' => true,
        'modules-marketplace.registry_url' => null,
    ]);

    return $user;
}

function administratorInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');
    $version = config('app.asset_url')
        ? hash('xxh128', (string) config('app.asset_url'))
        : (file_exists($manifest) ? hash_file('xxh128', $manifest) : '');

    return [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

it('keeps a complete administrator page catalog mapped to existing React components', function (): void {
    foreach ([...administratorInertiaPageCatalog(), ...administratorInertiaParameterizedPageCatalog()] as $page) {
        $routeName = $page[0];
        $component = $page[1];
        $componentPath = base_path('resources/js/pages/'.str_replace('/', DIRECTORY_SEPARATOR, $component).'.tsx');
        $moduleComponentPath = collect(glob(base_path('Modules/*/resources/assets/js/Pages/'.str_replace('/', DIRECTORY_SEPARATOR, $component).'.tsx')) ?: [])
            ->first(fn (string $path): bool => is_file($path));

        expect(
            is_file($componentPath) || is_string($moduleComponentPath),
            "{$routeName} must resolve to {$component}"
        )->toBeTrue();
    }
});

function administratorInertiaParameterizedUrl(string $routeName, string $fixture): string
{
    $school = app(TenantContext::class)->getCurrentSchool();

    return match ($fixture) {
        'class' => route($routeName, App\Models\Classes::factory()->create()),
        'course' => route($routeName, App\Models\Course::factory()->create()),
        'department' => route($routeName, App\Models\Department::factory()->forSchool($school)->create()),
        'enrollment' => route($routeName, App\Models\StudentEnrollment::factory()->create(['school_id' => $school?->id])),
        'faculty' => route($routeName, App\Models\Faculty::factory()->create()),
        'transaction' => route($routeName, administratorInertiaTransaction()),
        'spreadsheet-import' => route($routeName, App\Models\TuitionAdjustmentSpreadsheetImport::create([
            'public_id' => (string) Illuminate\Support\Str::uuid(),
            'uploaded_by_user_id' => auth()->id(),
            'original_filename' => 'audit.csv',
            'stored_path' => 'testing/audit.csv',
            'checksum' => hash('sha256', 'navigation-audit-spreadsheet'),
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'status' => 'ready',
            'ready_count' => 0,
            'invalid_count' => 0,
            'applied_count' => 0,
            'rejected_count' => 0,
        ])),
        'tuition-update-request' => route($routeName, administratorInertiaTuitionUpdateRequest()),
        'help-ticket' => route($routeName, App\Models\HelpTicket::create([
            'user_id' => auth()->id(),
            'type' => 'general',
            'subject' => 'Navigation audit',
            'message' => 'Navigation audit fixture',
            'status' => 'open',
            'priority' => 'normal',
        ])),
        'inventory-product' => route($routeName, Modules\Inventory\Models\InventoryProduct::factory()->create()),
        'inventory-borrowing' => route($routeName, Modules\Inventory\Models\InventoryBorrowing::create([
            'product_id' => Modules\Inventory\Models\InventoryProduct::factory()->create()->id,
            'quantity_borrowed' => 1,
            'borrower_name' => 'Navigation Audit Borrower',
            'status' => 'borrowed',
            'borrowed_date' => now()->subDay(),
            'expected_return_date' => now()->addDays(7),
            'issued_by' => auth()->id(),
        ])),
        'author' => route($routeName, Modules\LibrarySystem\Models\Author::factory()->create()),
        'book' => route($routeName, Modules\LibrarySystem\Models\Book::factory()->create()),
        'borrow-record' => route($routeName, administratorInertiaBorrowRecord()),
        'category' => route($routeName, Modules\LibrarySystem\Models\Category::factory()->create()),
        'research-paper' => route($routeName, Modules\LibrarySystem\Models\ResearchPaper::factory()->create()),
        'role' => route($routeName, Role::findOrCreate('navigation-audit-'.uniqid(), 'web')),
        'student' => route($routeName, App\Models\Student::factory()->create(['school_id' => $school?->id, 'institution_id' => $school?->id])),
        'user' => route($routeName, User::factory()->create(['role' => UserRole::Student])),
        default => throw new InvalidArgumentException("Unknown administrator page fixture [{$fixture}]."),
    };
}

function administratorInertiaTransaction(): App\Models\Transaction
{
    $student = App\Models\Student::factory()->create();

    $transaction = App\Models\Transaction::create([
        'description' => 'Navigation audit payment',
        'payment_method' => 'cash',
        'status' => 'Paid',
        'transaction_date' => now(),
        'settlements' => [100],
        'invoicenumber' => 'AUDIT-'.uniqid(),
        'user_id' => auth()->id(),
    ]);

    $transaction->studentTransactions()->create([
        'student_id' => $student->id,
        'amount' => 100,
        'status' => 'Paid',
    ]);

    return $transaction;
}

function administratorInertiaTuitionUpdateRequest(): App\Models\StudentTuitionUpdateRequest
{
    $enrollment = App\Models\StudentEnrollment::factory()->create();

    return App\Models\StudentTuitionUpdateRequest::create([
        'submitted_by_user_id' => auth()->id(),
        'student_id' => $enrollment->student_id,
        'student_enrollment_id' => $enrollment->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'concern_type' => App\Models\StudentTuitionUpdateRequest::ConcernOther,
        'details' => 'Navigation audit fixture',
        'status' => App\Models\StudentTuitionUpdateRequest::StatusPending,
    ]);
}

function administratorInertiaBorrowRecord(): Modules\LibrarySystem\Models\BorrowRecord
{
    $book = Modules\LibrarySystem\Models\Book::factory()->create();

    return Modules\LibrarySystem\Models\BorrowRecord::create([
        'book_id' => $book->id,
        'user_id' => auth()->id(),
        'borrowed_at' => now()->subDay(),
        'due_date' => now()->addDays(7),
        'status' => 'borrowed',
        'fine_amount' => 0,
    ]);
}

it('returns every static administrator destination as an Inertia response', function (string $routeName, string $component): void {
    $user = administratorInertiaAuditUser();

    actingAs($user)
        ->get(route($routeName), administratorInertiaHeaders())
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', $component)
        ->assertJsonStructure(['component', 'props', 'url', 'version']);
})->with(administratorInertiaPageCatalog());

it('returns every parameterized administrator destination as an Inertia response', function (string $routeName, string $component, string $fixture): void {
    $user = administratorInertiaAuditUser();
    actingAs($user);

    $url = administratorInertiaParameterizedUrl($routeName, $fixture);

    $this->get($url, administratorInertiaHeaders())
        ->assertSuccessful()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', $component)
        ->assertJsonStructure(['component', 'props', 'url', 'version']);
})->with(administratorInertiaParameterizedPageCatalog());

it('advertises the admin shell deferred props on initial Inertia visits', function (): void {
    $user = administratorInertiaAuditUser();

    $response = actingAs($user)
        ->get(route('administrators.dashboard'), administratorInertiaHeaders())
        ->assertOk()
        ->assertHeader('X-Inertia', 'true');

    expect($response->json('deferredProps.admin-shell'))
        ->toEqualCanonicalizing([
            'onboarding',
            'notifications',
            'unreadNotificationsCount',
            'unresolvedHelpTicketsCount',
            'adminSidebarCounts',
            'institutionOnboarding',
            'announcements',
        ]);
});

it('defers the student records dataset during Inertia navigation', function (): void {
    $user = administratorInertiaAuditUser();

    $response = actingAs($user)
        ->get(route('administrators.students.index'), administratorInertiaHeaders())
        ->assertOk()
        ->assertHeader('X-Inertia', 'true');

    expect($response->json('deferredProps.student-directory'))
        ->toContain('students')
        ->and($response->json('props'))->not->toHaveKey('students');
});

it('caches module navigation discovery and supports explicit invalidation', function (): void {
    Cache::forget('admin-navigation-routes:v1');

    $service = app(ModuleAdminNavigationService::class);
    $routes = $service->getRoutes();

    expect($routes)->toBeArray()->not->toBeEmpty();
    expect(Cache::has('admin-navigation-routes:v1'))->toBeTrue();

    $service->forgetCache();

    expect(Cache::has('admin-navigation-routes:v1'))->toBeFalse();
});
