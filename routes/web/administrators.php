<?php

declare(strict_types=1);

use App\Http\Controllers\AdministratorAuditLogController;
use App\Http\Controllers\AdministratorClassManagementController;
use App\Http\Controllers\AdministratorCurriculumManagementController;
use App\Http\Controllers\AdministratorEnrollmentManagementController;
use App\Http\Controllers\AdministratorFacultyManagementController;
use App\Http\Controllers\AdministratorFinanceController;
use App\Http\Controllers\AdministratorGlobalSearchController;
use App\Http\Controllers\AdministratorInventoryBorrowingController;
use App\Http\Controllers\AdministratorInventoryController;
use App\Http\Controllers\AdministratorInventoryProductController;
use App\Http\Controllers\AdministratorLibraryAuthorController;
use App\Http\Controllers\AdministratorLibraryBookController;
use App\Http\Controllers\AdministratorLibraryBorrowRecordController;
use App\Http\Controllers\AdministratorLibraryCategoryController;
use App\Http\Controllers\AdministratorLibraryController;
use App\Http\Controllers\AdministratorLibraryResearchPaperController;
use App\Http\Controllers\AdministratorRolesController;
use App\Http\Controllers\AdministratorSanityContentController;
use App\Http\Controllers\AdministratorSchedulingAnalyticsController;
use App\Http\Controllers\AdministratorStudentDocumentController;
use App\Http\Controllers\AdministratorStudentManagementController;
use App\Http\Controllers\AdministratorUserManagementController;
use App\Http\Controllers\UserSettingController;
use App\Models\User;
use App\Support\AdministratorPortalData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Allow any authenticated user to stop impersonating (since they might be impersonating a non-admin)
Route::post('/administrators/users/stop-impersonating', [AdministratorUserManagementController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('administrators.users.stop-impersonating');

Route::middleware(['auth', 'administrators.only'])
    ->prefix('administrators')
    ->name('administrators.')
    ->group(function (): void {
        Route::redirect('/', '/administrators/dashboard')->name('home');

        Route::get('/dashboard', function () {
            $user = Auth::user();

            if (! $user instanceof User) {
                return redirect('/login');
            }

            $portalData = AdministratorPortalData::build($user);

            $stats = $portalData['stats'];
            $analytics = $portalData['analytics'];
            $recentActivity = $portalData['recent_activity'];

            $quickActions = [
                [
                    'title' => 'Review pending approvals',
                    'description' => 'Approve or reject the latest requests.',
                    'href' => '/administrators/approvals',
                    'disabled' => true,
                    'disabledTooltip' => 'Approvals workflow coming soon',
                ],
                [
                    'title' => 'View faculty directory',
                    'description' => 'Find faculty details quickly.',
                    'href' => '/administrators/faculties',
                    'disabled' => false,
                ],
                [
                    'title' => 'Create announcement',
                    'description' => 'Draft and publish an announcement.',
                    'href' => '/administrators/announcements',
                    'disabled' => false,
                ],
            ];

            $beginnerTips = [
                [
                    'title' => 'Start with the Faculty Directory',
                    'content' => 'Use it to confirm who is assigned to which department and spot missing records.',
                ],
                [
                    'title' => 'Use search often',
                    'content' => 'Most screens will support search so you don\'t need to scroll.',
                ],
                [
                    'title' => 'Look for “Coming soon” labels',
                    'content' => 'Some tools are still being rolled out. You\'ll see clear hints when a feature is not ready yet.',
                ],
            ];

            return Inertia::render('administrators/dashboard', [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url ?? null,
                    'role' => $user->role?->getLabel() ?? 'Administrator',
                ],
                'admin_data' => [
                    'stats' => $stats,
                    'quick_actions' => $quickActions,
                    'recent_activity' => $recentActivity,
                    'beginner_tips' => $beginnerTips,
                    'analytics' => $analytics,
                ],
                'flash' => session('flash'),
            ]);
        })->name('dashboard');

        Route::get('/settings', [App\Http\Controllers\ProfileController::class, 'index'])->name('settings.index');
        Route::put('/settings', [App\Http\Controllers\ProfileController::class, 'updateUser'])->name('settings.update');
        Route::put('/settings/faculty', [App\Http\Controllers\ProfileController::class, 'updateFaculty'])->name('settings.faculty.update');
        Route::put('/settings/password', [App\Http\Controllers\ProfileController::class, 'changePassword'])->name('settings.password.update');
        Route::post('/settings/two-factor-authentication/enable', [App\Http\Controllers\ProfileController::class, 'enableTwoFactor'])->name('settings.two-factor.enable');
        Route::post('/settings/two-factor-authentication/confirm', [App\Http\Controllers\ProfileController::class, 'confirmTwoFactor'])->name('settings.two-factor.confirm');
        Route::delete('/settings/two-factor-authentication', [App\Http\Controllers\ProfileController::class, 'disableTwoFactor'])->name('settings.two-factor.disable');
        Route::post('/settings/email-authentication', [App\Http\Controllers\ProfileController::class, 'toggleEmailAuthentication'])->name('settings.email-auth.toggle');
        Route::post('/settings/two-factor-authentication/recovery-codes', [App\Http\Controllers\ProfileController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes');
        Route::delete('/settings/other-browser-sessions', [App\Http\Controllers\ProfileController::class, 'logoutOtherBrowserSessions'])->name('settings.browser-sessions.logout');

        Route::post('/settings/passkeys/options', [App\Http\Controllers\PasskeyController::class, 'generateRegistrationOptions'])->name('settings.passkeys.options');
        Route::post('/settings/passkeys', [App\Http\Controllers\PasskeyController::class, 'store'])->name('settings.passkeys.store');
        Route::delete('/settings/passkeys/{id}', [App\Http\Controllers\PasskeyController::class, 'destroy'])->name('settings.passkeys.destroy');
        Route::get('/settings/passkeys', [App\Http\Controllers\PasskeyController::class, 'index'])->name('settings.passkeys.index');

        Route::get('/enrollments', [AdministratorEnrollmentManagementController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/applicants', [AdministratorEnrollmentManagementController::class, 'applicants'])->name('enrollments.applicants');
        Route::get('/enrollments/create', [AdministratorEnrollmentManagementController::class, 'create'])->name('enrollments.create');
        Route::post('/enrollments', [AdministratorEnrollmentManagementController::class, 'store'])->name('enrollments.store');

        // Enrollment Form Data API endpoints
        Route::get('/enrollments/api/students', [AdministratorEnrollmentManagementController::class, 'searchStudents'])->name('enrollments.api.students');
        Route::get('/enrollments/api/subjects', [AdministratorEnrollmentManagementController::class, 'searchSubjects'])->name('enrollments.api.subjects');
        Route::get('/enrollments/api/sections', [AdministratorEnrollmentManagementController::class, 'getSubjectSections'])->name('enrollments.api.sections');
        Route::get('/enrollments/api/student-details', [AdministratorEnrollmentManagementController::class, 'getStudentDetails'])->name('enrollments.api.student-details');
        Route::get('/enrollments/api/calculate-fees', [AdministratorEnrollmentManagementController::class, 'calculateSubjectFees'])->name('enrollments.api.calculate-fees');
        Route::get('/enrollments/api/year-level-by-department', [AdministratorEnrollmentManagementController::class, 'yearLevelByDepartment'])->name('enrollments.api.year-level-by-department');
        Route::get('/enrollments/api/department-by-year-level', [AdministratorEnrollmentManagementController::class, 'departmentByYearLevel'])->name('enrollments.api.department-by-year-level');

        Route::patch('/enrollments/{student}', [AdministratorEnrollmentManagementController::class, 'update'])->name('enrollments.scholarship.update');
        Route::get('/enrollments/{enrollment}', [AdministratorEnrollmentManagementController::class, 'show'])->name('enrollments.show');

        // Enrollment Actions
        Route::post('/enrollments/{enrollment}/verify-head-dept', [AdministratorEnrollmentManagementController::class, 'verifyHeadDept'])->name('enrollments.verify-head-dept');
        Route::post('/enrollments/{enrollment}/verify-cashier', [AdministratorEnrollmentManagementController::class, 'verifyCashier'])->name('enrollments.verify-cashier');
        Route::post('/enrollments/{enrollment}/verify-cashier-no-receipt', [AdministratorEnrollmentManagementController::class, 'verifyCashierNoReceipt'])->name('enrollments.verify-cashier-no-receipt');
        Route::post('/enrollments/{enrollment}/undo-cashier', [AdministratorEnrollmentManagementController::class, 'undoCashierVerification'])->name('enrollments.undo-cashier');
        Route::post('/enrollments/{enrollment}/undo-head-dept', [AdministratorEnrollmentManagementController::class, 'undoHeadDeptVerification'])->name('enrollments.undo-head-dept');
        Route::post('/enrollments/{enrollment}/advance-pipeline-step', [AdministratorEnrollmentManagementController::class, 'advancePipelineStep'])->name('enrollments.advance-pipeline-step');
        Route::post('/enrollments/{enrollment}/enroll-class', [AdministratorEnrollmentManagementController::class, 'enrollInClass'])->name('enrollments.enroll-class');
        Route::post('/enrollments/{enrollment}/retry-enrollment', [AdministratorEnrollmentManagementController::class, 'retryEnrollment'])->name('enrollments.retry-enrollment');
        Route::post('/enrollments/{enrollment}/resend-assessment', [AdministratorEnrollmentManagementController::class, 'resendAssessment'])->name('enrollments.resend-assessment');
        Route::post('/enrollments/{enrollment}/create-assessment-pdf', [AdministratorEnrollmentManagementController::class, 'createAssessmentPdf'])->name('enrollments.create-assessment-pdf');
        Route::get('/enrollments/{enrollment}/assessment-preview-data', [AdministratorEnrollmentManagementController::class, 'assessmentPreviewData'])->name('enrollments.assessment-preview-data');
        Route::get('/enrollments/{enrollment}/assessment-preview', [AdministratorEnrollmentManagementController::class, 'assessmentPreview'])->name('enrollments.assessment-preview');
        Route::get('/enrollments/{enrollment}/edit', [AdministratorEnrollmentManagementController::class, 'edit'])->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [AdministratorEnrollmentManagementController::class, 'updateEnrollment'])->name('enrollments.update');
        Route::post('/enrollments/{enrollment}/quick-enroll', [AdministratorEnrollmentManagementController::class, 'quickEnroll'])->name('enrollments.quick-enroll');
        Route::patch('/enrollments/{enrollment}/transactions/{transaction}', [AdministratorEnrollmentManagementController::class, 'updateTransaction'])->name('enrollments.transactions.update');
        Route::patch('/enrollments/{enrollment}/tuition', [AdministratorEnrollmentManagementController::class, 'updateTuition'])->name('enrollments.tuition.update');
        Route::delete('/enrollments/{enrollment}', [AdministratorEnrollmentManagementController::class, 'destroy'])->name('enrollments.destroy');
        Route::delete('/enrollments/{enrollment}/force', [AdministratorEnrollmentManagementController::class, 'forceDestroy'])->name('enrollments.force-destroy');
        Route::post('/enrollments/{enrollment}/restore', [AdministratorEnrollmentManagementController::class, 'restore'])->name('enrollments.restore');
        Route::get('/enrollments/{enrollment}/activity-log', [AdministratorEnrollmentManagementController::class, 'activityLog'])->name('enrollments.activity-log');
        Route::post('/enrollments/{enrollment}/restore-subjects', [AdministratorEnrollmentManagementController::class, 'restoreSubjects'])->name('enrollments.restore-subjects');

        // Enrollment Reports
        Route::post('/enrollments/reports/bulk-assessments', [AdministratorEnrollmentManagementController::class, 'generateBulkAssessments'])->name('enrollments.reports.bulk-assessments');
        Route::get('/enrollments/reports/data', [AdministratorEnrollmentManagementController::class, 'enrollmentReportData'])->name('enrollments.reports.data');
        Route::get('/enrollments/reports/preview-pdf', [AdministratorEnrollmentManagementController::class, 'enrollmentReportPreviewPdf'])->name('enrollments.reports.preview-pdf');
        Route::get('/enrollments/reports/export', [AdministratorEnrollmentManagementController::class, 'enrollmentReportExport'])->name('enrollments.reports.export');
        Route::get('/enrollments/reports/subject-options', [AdministratorEnrollmentManagementController::class, 'reportSubjectOptions'])->name('enrollments.reports.subject-options');
        Route::get('/enrollments/reports/course-options', [AdministratorEnrollmentManagementController::class, 'reportCourseOptions'])->name('enrollments.reports.course-options');

        // Global Search (Administrator)
        Route::get('/search', AdministratorGlobalSearchController::class)->name('search');

        // Semester / School Year preferences (Administrator)
        Route::put('/settings/semester', [UserSettingController::class, 'updateSemester'])->name('settings.semester.update');
        Route::put('/settings/school-year', [UserSettingController::class, 'updateSchoolYear'])->name('settings.school-year.update');
        Route::put('/settings/active-school', [UserSettingController::class, 'updateActiveSchool'])->name('settings.active-school.update');

        // Finance and Billing
        Route::get('/finance', [AdministratorFinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance/invoices', [AdministratorFinanceController::class, 'invoices'])->name('finance.invoices');
        Route::get('/finance/payments', [AdministratorFinanceController::class, 'payments'])->name('finance.payments');
        Route::get('/finance/payments/create', [AdministratorFinanceController::class, 'create'])->name('finance.payments.create');
        Route::post('/finance/payments', [AdministratorFinanceController::class, 'store'])->name('finance.payments.store');
        Route::get('/finance/payments/{transaction}', [AdministratorFinanceController::class, 'show'])->name('finance.payments.show');
        Route::get('/finance/api/student-details', [AdministratorFinanceController::class, 'getStudentDetails'])->name('finance.api.student-details');
        Route::get('/finance/reports', [AdministratorFinanceController::class, 'reports'])->name('finance.reports');

        // Finance Report API Endpoints
        Route::get('/finance/reports/daily-collection', [AdministratorFinanceController::class, 'dailyCollectionReport'])->name('finance.reports.daily-collection');
        Route::get('/finance/reports/collection', [AdministratorFinanceController::class, 'collectionReport'])->name('finance.reports.collection');
        Route::get('/finance/reports/outstanding-balances', [AdministratorFinanceController::class, 'outstandingBalancesReport'])->name('finance.reports.outstanding-balances');
        Route::get('/finance/reports/scholarship', [AdministratorFinanceController::class, 'scholarshipReport'])->name('finance.reports.scholarship');
        Route::get('/finance/reports/revenue-breakdown', [AdministratorFinanceController::class, 'revenueBreakdownReport'])->name('finance.reports.revenue-breakdown');
        Route::get('/finance/reports/fully-paid', [AdministratorFinanceController::class, 'fullyPaidReport'])->name('finance.reports.fully-paid');
        Route::get('/finance/reports/cashier-performance', [AdministratorFinanceController::class, 'cashierPerformanceReport'])->name('finance.reports.cashier-performance');

        // Students Management
        Route::get('/students', [AdministratorStudentManagementController::class, 'index'])->name('students.index');
        Route::get('/students/create', [AdministratorStudentManagementController::class, 'create'])->name('students.create');
        Route::get('/students/generate-id', [AdministratorStudentManagementController::class, 'generateId'])->name('students.generate-id');
        Route::post('/students', [AdministratorStudentManagementController::class, 'store'])->name('students.store');
        Route::patch('/students/bulk/update-status', [AdministratorStudentManagementController::class, 'bulkUpdateStatus'])->name('students.bulk-update-status');
        Route::post('/students/bulk/manage-clearance', [AdministratorStudentManagementController::class, 'bulkManageClearance'])->name('students.bulk-manage-clearance');
        Route::post('/students/bulk/email', [AdministratorStudentManagementController::class, 'bulkSendEmail'])->name('students.bulk-email');
        Route::delete('/students/bulk', [AdministratorStudentManagementController::class, 'bulkDestroy'])->name('students.bulk-destroy');
        Route::get('/students/documents', [AdministratorStudentDocumentController::class, 'listAll'])->name('students.documents.list');
        Route::get('/students/{student}', [AdministratorStudentManagementController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/tuition/soa', [AdministratorStudentManagementController::class, 'printSoa'])->name('students.tuition.soa');
        Route::get('/students/{student}/documents', [AdministratorStudentDocumentController::class, 'index'])->name('students.documents.index');
        Route::post('/students/{student}/documents/fixed', [AdministratorStudentDocumentController::class, 'updateFixed'])->name('students.documents.fixed.update');
        Route::post('/students/{student}/documents/dynamic', [AdministratorStudentDocumentController::class, 'storeDynamic'])->name('students.documents.dynamic.store');
        Route::delete('/students/{student}/documents/dynamic/{resource}', [AdministratorStudentDocumentController::class, 'destroyDynamic'])->name('students.documents.dynamic.destroy');
        Route::get('/students/{student}/edit', [AdministratorStudentManagementController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [AdministratorStudentManagementController::class, 'update'])->name('students.update');
        Route::post('/students/{student}/subjects', [AdministratorStudentManagementController::class, 'addSubject'])->name('students.subjects.add');
        Route::patch('/students/{student}/subjects/{subject}', [AdministratorStudentManagementController::class, 'updateSubjectGrade'])->name('students.subjects.update-grade');
        Route::delete('/students/{student}/subjects/{subjectEnrollment}', [AdministratorStudentManagementController::class, 'removeSubject'])->name('students.subjects.remove');

        // Student Actions
        Route::post('/students/{student}/link-account', [AdministratorStudentManagementController::class, 'linkAccount'])->name('students.link-account');
        Route::patch('/students/{student}/update-id', [AdministratorStudentManagementController::class, 'updateStudentId'])->name('students.update-id');
        Route::post('/students/{student}/undo-id-change', [AdministratorStudentManagementController::class, 'undoStudentIdChange'])->name('students.undo-id-change');
        Route::patch('/students/{student}/change-course', [AdministratorStudentManagementController::class, 'changeCourse'])->name('students.change-course');
        Route::get('/students/courses/{course}/subjects', [AdministratorStudentManagementController::class, 'getCourseSubjects'])->name('students.courses.subjects');
        Route::post('/students/{student}/retry-enrollment', [AdministratorStudentManagementController::class, 'retryClassEnrollment'])->name('students.retry-enrollment');
        Route::patch('/students/{student}/update-tuition', [AdministratorStudentManagementController::class, 'updateTuition'])->name('students.update-tuition');
        Route::post('/students/{student}/signature', [AdministratorStudentManagementController::class, 'updateSignature'])->name('students.signature.update');
        Route::post('/students/{student}/manage-clearance', [AdministratorStudentManagementController::class, 'manageClearance'])->name('students.manage-clearance');
        Route::patch('/students/{student}/update-status', [AdministratorStudentManagementController::class, 'updateStatus'])->name('students.update-status');
        Route::delete('/students/{student}', [AdministratorStudentManagementController::class, 'destroy'])->name('students.destroy');
        Route::delete('/students/{student}/force', [AdministratorStudentManagementController::class, 'forceDestroy'])->name('students.force-destroy');

        // Library Management
        Route::get('/library', [AdministratorLibraryController::class, 'index'])->name('library.index');

        Route::get('/library/books', [AdministratorLibraryBookController::class, 'index'])->name('library.books.index');
        Route::get('/library/books/create', [AdministratorLibraryBookController::class, 'create'])->name('library.books.create');
        Route::post('/library/books', [AdministratorLibraryBookController::class, 'store'])->name('library.books.store');
        Route::get('/library/books/{book}/edit', [AdministratorLibraryBookController::class, 'edit'])->name('library.books.edit');
        Route::put('/library/books/{book}', [AdministratorLibraryBookController::class, 'update'])->name('library.books.update');
        Route::delete('/library/books/{book}', [AdministratorLibraryBookController::class, 'destroy'])->name('library.books.destroy');

        Route::get('/library/authors', [AdministratorLibraryAuthorController::class, 'index'])->name('library.authors.index');
        Route::get('/library/authors/create', [AdministratorLibraryAuthorController::class, 'create'])->name('library.authors.create');
        Route::post('/library/authors', [AdministratorLibraryAuthorController::class, 'store'])->name('library.authors.store');
        Route::get('/library/authors/{author}/edit', [AdministratorLibraryAuthorController::class, 'edit'])->name('library.authors.edit');
        Route::put('/library/authors/{author}', [AdministratorLibraryAuthorController::class, 'update'])->name('library.authors.update');
        Route::delete('/library/authors/{author}', [AdministratorLibraryAuthorController::class, 'destroy'])->name('library.authors.destroy');

        Route::get('/library/categories', [AdministratorLibraryCategoryController::class, 'index'])->name('library.categories.index');
        Route::get('/library/categories/create', [AdministratorLibraryCategoryController::class, 'create'])->name('library.categories.create');
        Route::post('/library/categories', [AdministratorLibraryCategoryController::class, 'store'])->name('library.categories.store');
        Route::get('/library/categories/{category}/edit', [AdministratorLibraryCategoryController::class, 'edit'])->name('library.categories.edit');
        Route::put('/library/categories/{category}', [AdministratorLibraryCategoryController::class, 'update'])->name('library.categories.update');
        Route::delete('/library/categories/{category}', [AdministratorLibraryCategoryController::class, 'destroy'])->name('library.categories.destroy');

        Route::get('/library/borrow-records', [AdministratorLibraryBorrowRecordController::class, 'index'])->name('library.borrow-records.index');
        Route::get('/library/borrow-records/create', [AdministratorLibraryBorrowRecordController::class, 'create'])->name('library.borrow-records.create');
        Route::post('/library/borrow-records', [AdministratorLibraryBorrowRecordController::class, 'store'])->name('library.borrow-records.store');
        Route::get('/library/borrow-records/{borrowRecord}/edit', [AdministratorLibraryBorrowRecordController::class, 'edit'])->name('library.borrow-records.edit');
        Route::put('/library/borrow-records/{borrowRecord}', [AdministratorLibraryBorrowRecordController::class, 'update'])->name('library.borrow-records.update');
        Route::delete('/library/borrow-records/{borrowRecord}', [AdministratorLibraryBorrowRecordController::class, 'destroy'])->name('library.borrow-records.destroy');

        Route::get('/library/research-papers', [AdministratorLibraryResearchPaperController::class, 'index'])->name('library.research-papers.index');
        Route::get('/library/research-papers/create', [AdministratorLibraryResearchPaperController::class, 'create'])->name('library.research-papers.create');
        Route::post('/library/research-papers', [AdministratorLibraryResearchPaperController::class, 'store'])->name('library.research-papers.store');
        Route::get('/library/research-papers/{researchPaper}/edit', [AdministratorLibraryResearchPaperController::class, 'edit'])->name('library.research-papers.edit');
        Route::put('/library/research-papers/{researchPaper}', [AdministratorLibraryResearchPaperController::class, 'update'])->name('library.research-papers.update');
        Route::delete('/library/research-papers/{researchPaper}', [AdministratorLibraryResearchPaperController::class, 'destroy'])->name('library.research-papers.destroy');

        // Inventory Management
        Route::get('/inventory', [AdministratorInventoryController::class, 'index'])->name('inventory.index');

        Route::get('/inventory/items', [AdministratorInventoryProductController::class, 'index'])->name('inventory.items.index');
        Route::get('/inventory/items/create', [AdministratorInventoryProductController::class, 'create'])->name('inventory.items.create');
        Route::post('/inventory/items', [AdministratorInventoryProductController::class, 'store'])->name('inventory.items.store');
        Route::get('/inventory/items/{inventoryProduct}/edit', [AdministratorInventoryProductController::class, 'edit'])->name('inventory.items.edit');
        Route::put('/inventory/items/{inventoryProduct}', [AdministratorInventoryProductController::class, 'update'])->name('inventory.items.update');
        Route::delete('/inventory/items/{inventoryProduct}', [AdministratorInventoryProductController::class, 'destroy'])->name('inventory.items.destroy');

        Route::get('/inventory/borrowings', [AdministratorInventoryBorrowingController::class, 'index'])->name('inventory.borrowings.index');
        Route::get('/inventory/borrowings/create', [AdministratorInventoryBorrowingController::class, 'create'])->name('inventory.borrowings.create');
        Route::post('/inventory/borrowings', [AdministratorInventoryBorrowingController::class, 'store'])->name('inventory.borrowings.store');
        Route::get('/inventory/borrowings/{inventoryBorrowing}/edit', [AdministratorInventoryBorrowingController::class, 'edit'])->name('inventory.borrowings.edit');
        Route::put('/inventory/borrowings/{inventoryBorrowing}', [AdministratorInventoryBorrowingController::class, 'update'])->name('inventory.borrowings.update');
        Route::delete('/inventory/borrowings/{inventoryBorrowing}', [AdministratorInventoryBorrowingController::class, 'destroy'])->name('inventory.borrowings.destroy');

        // Classes Management
        Route::get('/classes', [AdministratorClassManagementController::class, 'index'])->name('classes.index');
        Route::post('/classes', [AdministratorClassManagementController::class, 'store'])->name('classes.store');
        Route::patch('/classes/{class}', [AdministratorClassManagementController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{class}', [AdministratorClassManagementController::class, 'destroy'])->name('classes.destroy');
        Route::post('/classes/{class}/copy', [AdministratorClassManagementController::class, 'copy'])->name('classes.copy');

        Route::get('/classes/{class}', [AdministratorClassManagementController::class, 'show'])->name('classes.show');
        Route::get('/classes/{class}/export-student-list', [AdministratorClassManagementController::class, 'exportStudentList'])->name('classes.export-student-list');
        Route::post('/classes/{class}/move-student', [AdministratorClassManagementController::class, 'moveStudent'])->name('classes.move-student');

        Route::get('/classes/options/subjects', [AdministratorClassManagementController::class, 'subjectOptions'])->name('classes.options.subjects');
        Route::get('/classes/options/shs-strands', [AdministratorClassManagementController::class, 'shsStrandOptions'])->name('classes.options.shs-strands');
        Route::get('/classes/options/shs-subjects', [AdministratorClassManagementController::class, 'shsSubjectOptions'])->name('classes.options.shs-subjects');

        // Curriculum & Program Management
        Route::get('/curriculum', [AdministratorCurriculumManagementController::class, 'index'])->name('curriculum.index');
        Route::get('/curriculum/programs', [AdministratorCurriculumManagementController::class, 'programs'])->name('curriculum.programs.index');
        Route::post('/curriculum/programs', [AdministratorCurriculumManagementController::class, 'storeProgram'])->name('curriculum.programs.store');
        Route::get('/curriculum/programs/{course}', [AdministratorCurriculumManagementController::class, 'showProgram'])->name('curriculum.programs.show');
        Route::put('/curriculum/programs/{course}', [AdministratorCurriculumManagementController::class, 'updateProgram'])->name('curriculum.programs.update');
        Route::put('/curriculum/programs/{course}/toggle-status', [AdministratorCurriculumManagementController::class, 'toggleProgramStatus'])->name('curriculum.programs.toggle-status');
        Route::post('/curriculum/programs/{course}/subjects', [AdministratorCurriculumManagementController::class, 'storeSubject'])->name('curriculum.programs.subjects.store');
        Route::put('/curriculum/programs/{course}/subjects/{subject}', [AdministratorCurriculumManagementController::class, 'updateSubject'])->name('curriculum.programs.subjects.update');
        Route::delete('/curriculum/programs/{course}/subjects/{subject}', [AdministratorCurriculumManagementController::class, 'destroySubject'])->name('curriculum.programs.subjects.destroy');

        // Scheduling Analytics
        Route::get('/scheduling-analytics', [AdministratorSchedulingAnalyticsController::class, 'index'])->name('scheduling-analytics.index');
        Route::get('/scheduling-analytics/students/search', [AdministratorSchedulingAnalyticsController::class, 'searchStudents'])->name('scheduling-analytics.students.search');
        Route::get('/scheduling-analytics/students/{studentId}/schedule', [AdministratorSchedulingAnalyticsController::class, 'getStudentSchedule'])->name('scheduling-analytics.students.schedule');
        Route::patch('/scheduling-analytics/schedules/{schedule}', [AdministratorSchedulingAnalyticsController::class, 'updateSchedule'])->name('scheduling-analytics.schedules.update');
        Route::post('/scheduling-analytics/classes', [AdministratorSchedulingAnalyticsController::class, 'storeClass'])->name('scheduling-analytics.classes.store');

        // Faculty Management
        Route::get('/faculties', [AdministratorFacultyManagementController::class, 'index'])->name('faculties.index');
        Route::get('/faculties/create', [AdministratorFacultyManagementController::class, 'create'])->name('faculties.create');
        Route::post('/faculties', [AdministratorFacultyManagementController::class, 'store'])->name('faculties.store');
        Route::get('/faculties/{faculty}', [AdministratorFacultyManagementController::class, 'show'])->name('faculties.show');
        Route::get('/faculties/{faculty}/edit', [AdministratorFacultyManagementController::class, 'edit'])->name('faculties.edit');
        Route::put('/faculties/{faculty}', [AdministratorFacultyManagementController::class, 'update'])->name('faculties.update');
        Route::delete('/faculties/{faculty}', [AdministratorFacultyManagementController::class, 'destroy'])->name('faculties.destroy');
        Route::post('/faculties/{faculty}/assign-classes', [AdministratorFacultyManagementController::class, 'assignClasses'])->name('faculties.assign-classes');
        Route::delete('/faculties/{faculty}/classes/{class}', [AdministratorFacultyManagementController::class, 'unassignClass'])->name('faculties.classes.unassign');
        Route::put('/faculties/{faculty}/faculty-id-number', [AdministratorFacultyManagementController::class, 'updateFacultyIdNumber'])->name('faculties.update-id-number');

        // Departments Management
        Route::get('/departments', [App\Http\Controllers\AdministratorDepartmentManagementController::class, 'index'])->name('departments.index');
        Route::get('/departments/create', [App\Http\Controllers\AdministratorDepartmentManagementController::class, 'create'])->name('departments.create');
        Route::post('/departments', [App\Http\Controllers\AdministratorDepartmentManagementController::class, 'store'])->name('departments.store');
        Route::get('/departments/{department}/edit', [App\Http\Controllers\AdministratorDepartmentManagementController::class, 'edit'])->name('departments.edit');
        Route::put('/departments/{department}', [App\Http\Controllers\AdministratorDepartmentManagementController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [App\Http\Controllers\AdministratorDepartmentManagementController::class, 'destroy'])->name('departments.destroy');

        // Audit Logs
        Route::get('/audit-logs', [AdministratorAuditLogController::class, 'index'])->name('audit-logs.index');

        // User Management
        Route::get('/users', [AdministratorUserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdministratorUserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [AdministratorUserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdministratorUserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdministratorUserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdministratorUserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/impersonate', [AdministratorUserManagementController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/users/{user}/reset-password', [AdministratorUserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::put('/users/{user}/verify-email', [AdministratorUserManagementController::class, 'verifyEmail'])->name('users.verify-email');

        // Roles & Permissions Management
        Route::get('/roles', [AdministratorRolesController::class, 'index'])->name('roles.index');
        Route::get('/roles/{role}/edit', [AdministratorRolesController::class, 'edit'])->name('roles.edit');
        Route::post('/roles', [AdministratorRolesController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [AdministratorRolesController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [AdministratorRolesController::class, 'destroy'])->name('roles.destroy');
        Route::post('/roles/assign', [AdministratorRolesController::class, 'assignRole'])->name('roles.assign');
        Route::post('/permissions', [AdministratorRolesController::class, 'createPermission'])->name('permissions.store');
        Route::delete('/permissions/{permission}', [AdministratorRolesController::class, 'destroyPermission'])->name('permissions.destroy');

        // Sanity Content Management
        Route::get('/sanity-content', [AdministratorSanityContentController::class, 'index'])->name('sanity-content.index');
        Route::get('/sanity-content/create', [AdministratorSanityContentController::class, 'create'])->name('sanity-content.create');
        Route::post('/sanity-content', [AdministratorSanityContentController::class, 'store'])->name('sanity-content.store');
        Route::get('/sanity-content/{sanityContent}', [AdministratorSanityContentController::class, 'show'])->name('sanity-content.show');
        Route::get('/sanity-content/{sanityContent}/edit', [AdministratorSanityContentController::class, 'edit'])->name('sanity-content.edit');
        Route::put('/sanity-content/{sanityContent}', [AdministratorSanityContentController::class, 'update'])->name('sanity-content.update');
        Route::delete('/sanity-content/{sanityContent}', [AdministratorSanityContentController::class, 'destroy'])->name('sanity-content.destroy');

        // Sanity Sync Actions
        Route::post('/sanity-content/sync/from-sanity', [AdministratorSanityContentController::class, 'syncFromSanity'])->name('sanity-content.sync-from-sanity');
        Route::post('/sanity-content/{sanityContent}/sync/to-sanity', [AdministratorSanityContentController::class, 'syncToSanity'])->name('sanity-content.sync-to-sanity');
        Route::post('/sanity-content/sync/bulk-to-sanity', [AdministratorSanityContentController::class, 'bulkSyncToSanity'])->name('sanity-content.bulk-sync-to-sanity');
        Route::post('/sanity-content/upload-image', [AdministratorSanityContentController::class, 'uploadImage'])->name('sanity-content.upload-image');

        // Onboarding Features Management
        Route::get('/onboarding-features', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'index'])->name('onboarding-features.index');
        Route::get('/onboarding-features/create', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'create'])->name('onboarding-features.create');
        Route::post('/onboarding-features', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'store'])->name('onboarding-features.store');
        Route::get('/onboarding-features/{onboardingFeature}/edit', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'edit'])->name('onboarding-features.edit');
        Route::put('/onboarding-features/{onboardingFeature}', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'update'])->name('onboarding-features.update');
        Route::post('/onboarding-features/{onboardingFeature}/toggle', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'toggle'])->name('onboarding-features.toggle');
        Route::delete('/onboarding-features/{onboardingFeature}', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'destroy'])->name('onboarding-features.destroy');
        Route::post('/onboarding-features/upload-image', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'uploadImage'])->name('onboarding-features.upload-image');
        Route::post('/onboarding-features/{onboardingFeature}/activate-for-user', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'activateForUser'])->name('onboarding-features.activate-for-user');
        Route::post('/onboarding-features/{onboardingFeature}/deactivate-for-user', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'deactivateForUser'])->name('onboarding-features.deactivate-for-user');
        Route::post('/onboarding-features/{onboardingFeature}/purge-overrides', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'purgeOverrides'])->name('onboarding-features.purge-overrides');
        Route::get('/onboarding-features/{onboardingFeature}/overridden-users', [App\Http\Controllers\AdministratorOnboardingFeatureController::class, 'overriddenUsers'])->name('onboarding-features.overridden-users');

        // System Management
        Route::get('/system-management', [App\Http\Controllers\AdministratorSystemManagementController::class, 'index'])->name('system-management.index');
        Route::get('/system-management/school', [App\Http\Controllers\AdministratorSystemManagementController::class, 'school'])->name('system-management.school.index');
        Route::get('/system-management/enrollment-pipeline', [App\Http\Controllers\AdministratorSystemManagementController::class, 'enrollmentPipeline'])->name('system-management.enrollment-pipeline.index');
        Route::get('/system-management/seo', [App\Http\Controllers\AdministratorSystemManagementController::class, 'seo'])->name('system-management.seo.index');
        Route::get('/system-management/analytics', [App\Http\Controllers\AdministratorSystemManagementController::class, 'analytics'])->name('system-management.analytics.index');
        Route::get('/system-management/brand', [App\Http\Controllers\AdministratorSystemManagementController::class, 'brand'])->name('system-management.brand.index');
        Route::get('/system-management/sanity', [App\Http\Controllers\AdministratorSystemManagementController::class, 'sanity'])->name('system-management.sanity.index');
        Route::get('/system-management/socialite', [App\Http\Controllers\AdministratorSystemManagementController::class, 'socialite'])->name('system-management.socialite.index');
        Route::get('/system-management/mail', [App\Http\Controllers\AdministratorSystemManagementController::class, 'mail'])->name('system-management.mail.index');
        Route::get('/system-management/api', [App\Http\Controllers\AdministratorSystemManagementController::class, 'api'])->name('system-management.api.index');
        Route::get('/system-management/pulse', [App\Http\Controllers\AdministratorSystemManagementController::class, 'pulse'])->name('system-management.pulse.index');
        Route::post('/system-management/school', [App\Http\Controllers\AdministratorSystemManagementController::class, 'storeSchool'])->name('system-management.school.store');
        Route::put('/system-management/school', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchool'])->name('system-management.school.update');
        Route::put('/system-management/school-details', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchoolDetails'])->name('system-management.school-details.update');
        Route::put('/system-management/academic-calendar', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateAcademicCalendar'])->name('system-management.academic-calendar.update');
        Route::put('/system-management/schools/{school}', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateManagedSchool'])->name('system-management.schools.update');
        Route::patch('/system-management/schools/{school}/status', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchoolStatus'])->name('system-management.schools.status.update');
        Route::delete('/system-management/schools/{school}', [App\Http\Controllers\AdministratorSystemManagementController::class, 'destroySchool'])->name('system-management.schools.destroy');
        Route::delete('/system-management/schools/{school}/force', [App\Http\Controllers\AdministratorSystemManagementController::class, 'forceDestroySchool'])->name('system-management.schools.force-destroy');
        Route::put('/system-management/seo', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSeo'])->name('system-management.seo.update');
        Route::put('/system-management/analytics', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateAnalytics'])->name('system-management.analytics.update');
        Route::put('/system-management/brand', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateBrand'])->name('system-management.brand.update');
        Route::put('/system-management/sanity', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSanity'])->name('system-management.sanity.update');
        Route::put('/system-management/socialite', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSocialite'])->name('system-management.socialite.update');
        Route::put('/system-management/mail', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateMail'])->name('system-management.mail.update');
        Route::put('/system-management/api', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateApiManagement'])->name('system-management.api.update');
        Route::put('/system-management/enrollment-pipeline', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateEnrollmentPipeline'])->name('system-management.enrollment-pipeline.update');
        Route::post('/system-management/mail/test', [App\Http\Controllers\AdministratorSystemManagementController::class, 'sendTestEmail'])->name('system-management.mail.test');
        Route::get('/system-management/notifications', [App\Http\Controllers\AdministratorSystemManagementController::class, 'notifications'])->name('system-management.notifications.index');
        Route::put('/system-management/notifications', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateNotificationChannels'])->name('system-management.notifications.update');
        Route::get('/system-management/grading', [App\Http\Controllers\AdministratorSystemManagementController::class, 'grading'])->name('system-management.grading.index');
        Route::put('/system-management/grading', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateGrading'])->name('system-management.grading.update');

        // Help Tickets
        Route::get('/help-tickets', [App\Http\Controllers\AdministratorHelpTicketController::class, 'index'])->name('help-tickets.index');
        Route::get('/help-tickets/{helpTicket}', [App\Http\Controllers\AdministratorHelpTicketController::class, 'show'])->name('help-tickets.show');
        Route::post('/help-tickets/{helpTicket}/reply', [App\Http\Controllers\AdministratorHelpTicketController::class, 'reply'])->name('help-tickets.reply');
        Route::put('/help-tickets/{helpTicket}', [App\Http\Controllers\AdministratorHelpTicketController::class, 'update'])->name('help-tickets.update');
        Route::delete('/help-tickets/{helpTicket}', [App\Http\Controllers\AdministratorHelpTicketController::class, 'destroy'])->name('help-tickets.destroy');

        // Notifications
        Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::delete('/notifications/{id}', [App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');
    });
