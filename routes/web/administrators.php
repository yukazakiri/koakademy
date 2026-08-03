<?php

declare(strict_types=1);

use App\Http\Controllers\AdministratorAuditLogController;
use App\Http\Controllers\AdministratorClassManagementController;
use App\Http\Controllers\AdministratorCurriculumManagementController;
use App\Http\Controllers\AdministratorEnrollmentDiscountController;
use App\Http\Controllers\AdministratorEnrollmentManagementController;
use App\Http\Controllers\AdministratorEnrollmentPolicyController;
use App\Http\Controllers\AdministratorFacultyManagementController;
use App\Http\Controllers\AdministratorFinanceController;
use App\Http\Controllers\AdministratorGlobalSearchController;
use App\Http\Controllers\AdministratorRolesController;
use App\Http\Controllers\AdministratorSchedulingAnalyticsController;
use App\Http\Controllers\AdministratorStudentDocumentController;
use App\Http\Controllers\AdministratorStudentManagementController;
use App\Http\Controllers\AdministratorUserManagementController;
use App\Http\Controllers\ApiKeyController;
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
                    ...$portalData,
                    'quick_actions' => $quickActions,
                    'beginner_tips' => $beginnerTips,
                ],
                'flash' => session('flash'),
            ]);
        })->name('dashboard');

        Route::get('/settings', [App\Http\Controllers\ProfileController::class, 'index'])->name('settings.index');
        Route::get('/settings/newsletter', [App\Http\Controllers\AdministratorSystemManagementController::class, 'newsletter'])->name('settings.newsletter.index');
        Route::put('/settings', [App\Http\Controllers\ProfileController::class, 'updateUser'])->name('settings.update');
        Route::put('/settings/faculty', [App\Http\Controllers\ProfileController::class, 'updateFaculty'])->name('settings.faculty.update');
        Route::put('/settings/password', [App\Http\Controllers\ProfileController::class, 'changePassword'])->name('settings.password.update');
        Route::post('/settings/two-factor-authentication/enable', [App\Http\Controllers\ProfileController::class, 'enableTwoFactor'])->name('settings.two-factor.enable');
        Route::post('/settings/two-factor-authentication/confirm', [App\Http\Controllers\ProfileController::class, 'confirmTwoFactor'])->name('settings.two-factor.confirm');
        Route::delete('/settings/two-factor-authentication', [App\Http\Controllers\ProfileController::class, 'disableTwoFactor'])->name('settings.two-factor.disable');
        Route::post('/settings/two-factor-authentication/login-challenges', [App\Http\Controllers\ProfileController::class, 'toggleSecurityTwoFactor'])->name('settings.two-factor.login-challenges');
        Route::post('/settings/email-authentication', [App\Http\Controllers\ProfileController::class, 'toggleEmailAuthentication'])->name('settings.email-auth.toggle');
        Route::post('/settings/two-factor-authentication/recovery-codes', [App\Http\Controllers\ProfileController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes');
        Route::delete('/settings/other-browser-sessions', [App\Http\Controllers\ProfileController::class, 'logoutOtherBrowserSessions'])->name('settings.browser-sessions.logout');

        Route::post('/settings/passkeys/options', [App\Http\Controllers\PasskeyController::class, 'generateRegistrationOptions'])->name('settings.passkeys.options');
        Route::post('/settings/passkeys', [App\Http\Controllers\PasskeyController::class, 'store'])->name('settings.passkeys.store');
        Route::delete('/settings/passkeys/{id}', [App\Http\Controllers\PasskeyController::class, 'destroy'])->name('settings.passkeys.destroy');
        Route::get('/settings/passkeys', [App\Http\Controllers\PasskeyController::class, 'index'])->name('settings.passkeys.index');

        Route::get('/settings/api-keys', [ApiKeyController::class, 'index'])->name('settings.api-keys.index');
        Route::post('/settings/api-keys', [ApiKeyController::class, 'store'])->name('settings.api-keys.store');
        Route::delete('/settings/api-keys/{id}', [ApiKeyController::class, 'destroy'])->name('settings.api-keys.destroy');
        Route::get('/settings/api-keys/developer-mode', [ApiKeyController::class, 'checkDeveloperMode'])->name('settings.api-keys.developer-mode');

        Route::post('/settings/experimental-features', [App\Http\Controllers\ProfileController::class, 'toggleExperimentalFeatures'])->name('settings.experimental-features');

        Route::get('/enrollments', [AdministratorEnrollmentManagementController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/applicants', [AdministratorEnrollmentManagementController::class, 'applicants'])->name('enrollments.applicants');
        Route::get('/enrollments/create', [AdministratorEnrollmentManagementController::class, 'create'])->name('enrollments.create');
        Route::post('/enrollments', [AdministratorEnrollmentManagementController::class, 'store'])->name('enrollments.store');
        Route::post('/enrollments/discounts', [AdministratorEnrollmentDiscountController::class, 'store'])->name('enrollments.discounts.store');

        // Enrollment Form Data API endpoints
        Route::get('/enrollments/api/students', [AdministratorEnrollmentManagementController::class, 'searchStudents'])->name('enrollments.api.students');
        Route::get('/enrollments/api/subjects', [AdministratorEnrollmentManagementController::class, 'searchSubjects'])->name('enrollments.api.subjects');
        Route::get('/enrollments/api/sections', [AdministratorEnrollmentManagementController::class, 'getSubjectSections'])->name('enrollments.api.sections');
        Route::get('/enrollments/api/student-details', [AdministratorEnrollmentManagementController::class, 'getStudentDetails'])->name('enrollments.api.student-details');
        Route::get('/enrollments/api/calculate-fees', [AdministratorEnrollmentManagementController::class, 'calculateSubjectFees'])->name('enrollments.api.calculate-fees');
        Route::get('/enrollments/api/year-level-by-department', [AdministratorEnrollmentManagementController::class, 'yearLevelByDepartment'])->name('enrollments.api.year-level-by-department');
        Route::get('/enrollments/api/department-by-year-level', [AdministratorEnrollmentManagementController::class, 'departmentByYearLevel'])->name('enrollments.api.department-by-year-level');

        Route::patch('/enrollments/{student}', [AdministratorEnrollmentManagementController::class, 'update'])->whereNumber('student')->name('enrollments.scholarship.update');
        Route::post('/enrollments/applicants/{student}/notify-approval', [AdministratorEnrollmentManagementController::class, 'notifyApplicantApproval'])->whereNumber('student')->name('enrollments.applicants.notify-approval');
        Route::get('/enrollments/{enrollment}', [AdministratorEnrollmentManagementController::class, 'show'])->whereNumber('enrollment')->name('enrollments.show');

        // Enrollment Actions
        Route::post('/enrollments/{enrollment}/verify-head-dept', [AdministratorEnrollmentManagementController::class, 'verifyHeadDept'])->whereNumber('enrollment')->name('enrollments.verify-head-dept');
        Route::post('/enrollments/{enrollment}/verify-cashier', [AdministratorEnrollmentManagementController::class, 'verifyCashier'])->whereNumber('enrollment')->name('enrollments.verify-cashier');
        Route::post('/enrollments/{enrollment}/verify-cashier-no-receipt', [AdministratorEnrollmentManagementController::class, 'verifyCashierNoReceipt'])->whereNumber('enrollment')->name('enrollments.verify-cashier-no-receipt');
        Route::post('/enrollments/{enrollment}/undo-cashier', [AdministratorEnrollmentManagementController::class, 'undoCashierVerification'])->whereNumber('enrollment')->name('enrollments.undo-cashier');
        Route::post('/enrollments/{enrollment}/undo-head-dept', [AdministratorEnrollmentManagementController::class, 'undoHeadDeptVerification'])->whereNumber('enrollment')->name('enrollments.undo-head-dept');
        Route::post('/enrollments/{enrollment}/advance-pipeline-step', [AdministratorEnrollmentManagementController::class, 'advancePipelineStep'])->whereNumber('enrollment')->name('enrollments.advance-pipeline-step');
        Route::post('/enrollments/{enrollment}/transitions', [AdministratorEnrollmentPolicyController::class, 'transition'])->whereNumber('enrollment')->name('enrollments.transitions.store');
        Route::post('/enrollments/{enrollment}/reopen', [AdministratorEnrollmentPolicyController::class, 'reopen'])->whereNumber('enrollment')->name('enrollments.reopen');
        Route::post('/enrollments/{enrollment}/requirements/{requirement}/review', [AdministratorEnrollmentPolicyController::class, 'reviewRequirement'])
            ->whereNumber(['enrollment', 'requirement'])
            ->scopeBindings()
            ->name('enrollments.requirements.review');
        Route::post('/enrollments/{enrollment}/enroll-class', [AdministratorEnrollmentManagementController::class, 'enrollInClass'])->whereNumber('enrollment')->name('enrollments.enroll-class');
        Route::post('/enrollments/{enrollment}/retry-enrollment', [AdministratorEnrollmentManagementController::class, 'retryEnrollment'])->whereNumber('enrollment')->name('enrollments.retry-enrollment');
        Route::post('/enrollments/{enrollment}/resend-assessment', [AdministratorEnrollmentManagementController::class, 'resendAssessment'])->whereNumber('enrollment')->name('enrollments.resend-assessment');
        Route::post('/enrollments/{enrollment}/create-assessment-pdf', [AdministratorEnrollmentManagementController::class, 'createAssessmentPdf'])->whereNumber('enrollment')->name('enrollments.create-assessment-pdf');
        Route::get('/enrollments/{enrollment}/class-schedule-changes/preview', [AdministratorEnrollmentManagementController::class, 'classScheduleChangesPreview'])->whereNumber('enrollment')->name('enrollments.class-schedule-changes.preview');
        Route::post('/enrollments/{enrollment}/class-schedule-changes/notify', [AdministratorEnrollmentManagementController::class, 'notifyClassScheduleChanges'])->whereNumber('enrollment')->name('enrollments.class-schedule-changes.notify');
        Route::get('/enrollments/{enrollment}/assessment-preview-data', [AdministratorEnrollmentManagementController::class, 'assessmentPreviewData'])->whereNumber('enrollment')->name('enrollments.assessment-preview-data');
        Route::get('/enrollments/{enrollment}/assessment-preview', [AdministratorEnrollmentManagementController::class, 'assessmentPreview'])->whereNumber('enrollment')->name('enrollments.assessment-preview');
        Route::get('/enrollments/{enrollment}/edit', [AdministratorEnrollmentManagementController::class, 'edit'])->whereNumber('enrollment')->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [AdministratorEnrollmentManagementController::class, 'updateEnrollment'])->whereNumber('enrollment')->name('enrollments.update');
        Route::post('/enrollments/{enrollment}/quick-enroll', [AdministratorEnrollmentManagementController::class, 'quickEnroll'])->whereNumber('enrollment')->name('enrollments.quick-enroll');
        Route::patch('/enrollments/{enrollment}/transactions/{transaction}', [AdministratorEnrollmentManagementController::class, 'updateTransaction'])->whereNumber('enrollment')->whereNumber('transaction')->name('enrollments.transactions.update');
        Route::patch('/enrollments/{enrollment}/tuition', [AdministratorEnrollmentManagementController::class, 'updateTuition'])->whereNumber('enrollment')->name('enrollments.tuition.update');
        Route::delete('/enrollments/{enrollment}', [AdministratorEnrollmentManagementController::class, 'destroy'])->whereNumber('enrollment')->name('enrollments.destroy');
        Route::delete('/enrollments/{enrollment}/force', [AdministratorEnrollmentManagementController::class, 'forceDestroy'])->whereNumber('enrollment')->name('enrollments.force-destroy');
        Route::post('/enrollments/{enrollment}/restore', [AdministratorEnrollmentManagementController::class, 'restore'])->whereNumber('enrollment')->name('enrollments.restore');
        Route::get('/enrollments/{enrollment}/activity-log', [AdministratorEnrollmentManagementController::class, 'activityLog'])->whereNumber('enrollment')->name('enrollments.activity-log');
        Route::post('/enrollments/{enrollment}/restore-subjects', [AdministratorEnrollmentManagementController::class, 'restoreSubjects'])->whereNumber('enrollment')->name('enrollments.restore-subjects');

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
        Route::post('/finance/invoices/{enrollment}/send', [AdministratorFinanceController::class, 'sendInvoice'])->whereNumber('enrollment')->middleware('throttle:6,1')->name('finance.invoices.send');
        Route::get('/finance/payments', [AdministratorFinanceController::class, 'payments'])->name('finance.payments');
        Route::get('/finance/payments/create', [AdministratorFinanceController::class, 'create'])->name('finance.payments.create');
        Route::post('/finance/payments', [AdministratorFinanceController::class, 'store'])->name('finance.payments.store');
        Route::post('/finance/payments/{transaction}/resend-receipt', [AdministratorFinanceController::class, 'resendReceipt'])->whereNumber('transaction')->middleware('throttle:6,1')->name('finance.payments.resend-receipt');
        Route::get('/finance/documents/{issuance}/download', [AdministratorFinanceController::class, 'downloadFinancialDocument'])->name('finance.documents.download');
        Route::post('/finance/documents/{issuance}/resend', [AdministratorFinanceController::class, 'resendFinancialDocument'])->middleware('throttle:6,1')->name('finance.documents.resend');
        Route::get('/finance/payments/{transaction}', [AdministratorFinanceController::class, 'show'])->name('finance.payments.show');
        Route::get('/finance/api/student-details', [AdministratorFinanceController::class, 'getStudentDetails'])->name('finance.api.student-details');
        Route::get('/finance/api/students/{student}/transactions', [AdministratorFinanceController::class, 'studentTransactions'])->whereNumber('student')->name('finance.api.student-transactions');
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
        Route::delete('/students/bulk/force', [AdministratorStudentManagementController::class, 'bulkForceDestroy'])->name('students.bulk-force-destroy');
        Route::get('/students/documents', [AdministratorStudentDocumentController::class, 'listAll'])->name('students.documents.list');
        Route::get('/students/field-values', [AdministratorStudentManagementController::class, 'fieldValues'])->name('students.field-values');
        Route::get('/students/education-school-options', [AdministratorStudentManagementController::class, 'educationSchoolOptions'])->name('students.education-school-options');
        Route::get('/students/{student}', [AdministratorStudentManagementController::class, 'show'])->name('students.show')->withTrashed();
        Route::get('/students/{student}/tuition/soa', [AdministratorStudentManagementController::class, 'printSoa'])->name('students.tuition.soa');
        Route::get('/students/{student}/documents', [AdministratorStudentDocumentController::class, 'index'])->name('students.documents.index');
        Route::post('/students/{student}/documents/fixed', [AdministratorStudentDocumentController::class, 'updateFixed'])->name('students.documents.fixed.update');
        Route::post('/students/{student}/documents/dynamic', [AdministratorStudentDocumentController::class, 'storeDynamic'])->name('students.documents.dynamic.store');
        Route::delete('/students/{student}/documents/dynamic/{resource}', [AdministratorStudentDocumentController::class, 'destroyDynamic'])->name('students.documents.dynamic.destroy');
        Route::get('/students/{student}/edit', [AdministratorStudentManagementController::class, 'edit'])->name('students.edit')->withTrashed();
        Route::put('/students/{student}', [AdministratorStudentManagementController::class, 'update'])->name('students.update')->withTrashed();
        Route::post('/students/{student}/subjects', [AdministratorStudentManagementController::class, 'addSubject'])->name('students.subjects.add')->withTrashed();
        Route::patch('/students/{student}/subjects/{subject}', [AdministratorStudentManagementController::class, 'updateSubjectGrade'])->name('students.subjects.update-grade')->withTrashed();
        Route::delete('/students/{student}/subjects/{subjectEnrollment}', [AdministratorStudentManagementController::class, 'removeSubject'])->name('students.subjects.remove')->withTrashed();

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
        Route::post('/students/{student}/restore', [AdministratorStudentManagementController::class, 'restore'])->name('students.restore');
        Route::delete('/students/{student}', [AdministratorStudentManagementController::class, 'destroy'])->name('students.destroy')->withTrashed();
        Route::delete('/students/{student}/force', [AdministratorStudentManagementController::class, 'forceDestroy'])->name('students.force-destroy');

        // Classes Management
        Route::get('/classes', [AdministratorClassManagementController::class, 'index'])->name('classes.index');
        Route::get('/classes/create', [AdministratorClassManagementController::class, 'create'])->name('classes.create');
        Route::post('/classes', [AdministratorClassManagementController::class, 'store'])->name('classes.store');

        // Static routes — must come before /classes/{class} to avoid route parameter conflicts
        Route::get('/classes/compare', [AdministratorClassManagementController::class, 'compare'])->name('classes.compare');
        Route::get('/classes/options/subjects', [AdministratorClassManagementController::class, 'subjectOptions'])->name('classes.options.subjects');
        Route::get('/classes/options/shs-strands', [AdministratorClassManagementController::class, 'shsStrandOptions'])->name('classes.options.shs-strands');
        Route::get('/classes/options/shs-subjects', [AdministratorClassManagementController::class, 'shsSubjectOptions'])->name('classes.options.shs-subjects');

        // Parameterized routes
        Route::get('/classes/{class}/edit', [AdministratorClassManagementController::class, 'edit'])->name('classes.edit');
        Route::patch('/classes/{class}', [AdministratorClassManagementController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{class}', [AdministratorClassManagementController::class, 'destroy'])->name('classes.destroy');
        Route::post('/classes/{class}/copy', [AdministratorClassManagementController::class, 'copy'])->name('classes.copy');

        Route::get('/classes/{class}', [AdministratorClassManagementController::class, 'show'])->name('classes.show');
        Route::get('/classes/{class}/export-student-list', [AdministratorClassManagementController::class, 'exportStudentList'])->name('classes.export-student-list');
        Route::post('/classes/{class}/move-student', [AdministratorClassManagementController::class, 'moveStudent'])->name('classes.move-student');

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
        Route::delete('/scheduling-analytics/schedules/{schedule}', [AdministratorSchedulingAnalyticsController::class, 'destroySchedule'])->name('scheduling-analytics.schedules.destroy');
        Route::post('/scheduling-analytics/classes', [AdministratorSchedulingAnalyticsController::class, 'storeClass'])->name('scheduling-analytics.classes.store');

        // Faculty Management
        Route::get('/faculties', [AdministratorFacultyManagementController::class, 'index'])->name('faculties.index');
        Route::get('/faculties/create', [AdministratorFacultyManagementController::class, 'create'])->name('faculties.create');
        Route::post('/faculties', [AdministratorFacultyManagementController::class, 'store'])->name('faculties.store');
        Route::patch('/faculties/bulk/status', [AdministratorFacultyManagementController::class, 'bulkUpdateStatus'])->name('faculties.bulk.status');
        Route::get('/faculties/{faculty}', [AdministratorFacultyManagementController::class, 'show'])->name('faculties.show');
        Route::get('/faculties/{faculty}/edit', [AdministratorFacultyManagementController::class, 'edit'])->name('faculties.edit');
        Route::put('/faculties/{faculty}', [AdministratorFacultyManagementController::class, 'update'])->name('faculties.update');
        Route::delete('/faculties/{faculty}', [AdministratorFacultyManagementController::class, 'destroy'])->name('faculties.destroy');
        Route::post('/faculties/{faculty}/assign-classes', [AdministratorFacultyManagementController::class, 'assignClasses'])->name('faculties.assign-classes');
        Route::post('/faculties/{faculty}/portal-account', [AdministratorFacultyManagementController::class, 'managePortalAccount'])->name('faculties.portal-account');
        Route::post('/faculties/{faculty}/notice', [AdministratorFacultyManagementController::class, 'sendNotice'])->name('faculties.notice');
        Route::post('/faculties/{faculty}/deadlines', [AdministratorFacultyManagementController::class, 'storeDeadline'])->name('faculties.deadlines.store');
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

        // Feature Toggles Management
        Route::get('/feature-toggles', [App\Http\Controllers\AdministratorFeatureToggleController::class, 'index'])->name('feature-toggles.index');
        Route::post('/feature-toggles/{featureKey}/toggle', [App\Http\Controllers\AdministratorFeatureToggleController::class, 'toggle'])->name('feature-toggles.toggle');
        Route::post('/feature-toggles/{featureKey}/activate-for-user', [App\Http\Controllers\AdministratorFeatureToggleController::class, 'activateForUser'])->name('feature-toggles.activate-for-user');
        Route::post('/feature-toggles/{featureKey}/deactivate-for-user', [App\Http\Controllers\AdministratorFeatureToggleController::class, 'deactivateForUser'])->name('feature-toggles.deactivate-for-user');
        Route::post('/feature-toggles/{featureKey}/purge-overrides', [App\Http\Controllers\AdministratorFeatureToggleController::class, 'purgeOverrides'])->name('feature-toggles.purge-overrides');
        Route::get('/feature-toggles/{featureKey}/overridden-users', [App\Http\Controllers\AdministratorFeatureToggleController::class, 'overriddenUsers'])->name('feature-toggles.overridden-users');

        // System Management
        Route::get('/system-management', [App\Http\Controllers\AdministratorSystemManagementController::class, 'index'])->name('system-management.index');
        Route::get('/system-management/school', [App\Http\Controllers\AdministratorSystemManagementController::class, 'school'])->name('system-management.school.index');
        Route::get('/system-management/enrollment-pipeline', [App\Http\Controllers\AdministratorSystemManagementController::class, 'enrollmentPipeline'])->name('system-management.enrollment-pipeline.index');
        Route::get('/system-management/seo', [App\Http\Controllers\AdministratorSystemManagementController::class, 'seo'])->name('system-management.seo.index');
        Route::get('/system-management/analytics', [App\Http\Controllers\AdministratorSystemManagementController::class, 'analytics'])->name('system-management.analytics.index');
        Route::get('/system-management/brand', [App\Http\Controllers\AdministratorSystemManagementController::class, 'brand'])->name('system-management.brand.index');
        Route::get('/system-management/brand/appearance', [App\Http\Controllers\AdministratorSystemManagementController::class, 'brand'])->name('system-management.brand.appearance.index');
        Route::get('/system-management/socialite', [App\Http\Controllers\AdministratorSystemManagementController::class, 'socialite'])->name('system-management.socialite.index');
        Route::get('/system-management/mail', [App\Http\Controllers\AdministratorSystemManagementController::class, 'mail'])->name('system-management.mail.index');
        Route::get('/system-management/newsletter', [App\Http\Controllers\AdministratorSystemManagementController::class, 'newsletter'])->name('system-management.newsletter.index');
        Route::get('/system-management/api', [App\Http\Controllers\AdministratorSystemManagementController::class, 'api'])->name('system-management.api.index');
        Route::get('/system-management/pulse', [App\Http\Controllers\AdministratorSystemManagementController::class, 'pulse'])->name('system-management.pulse.index');
        Route::get('/system-management/identifiers', [App\Http\Controllers\AdministratorSystemManagementController::class, 'identifiers'])->name('system-management.identifiers.index');
        Route::post('/system-management/school', [App\Http\Controllers\AdministratorSystemManagementController::class, 'storeSchool'])->name('system-management.school.store');
        Route::put('/system-management/school', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchool'])->name('system-management.school.update');
        Route::put('/system-management/school-details', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchoolDetails'])->name('system-management.school-details.update');
        Route::put('/system-management/school-level', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchoolLevel'])->name('system-management.school-level.update');
        Route::put('/system-management/academic-calendar', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateAcademicCalendar'])->name('system-management.academic-calendar.update');
        Route::put('/system-management/schools/{school}', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateManagedSchool'])->name('system-management.schools.update');
        Route::patch('/system-management/schools/{school}/status', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSchoolStatus'])->name('system-management.schools.status.update');
        Route::delete('/system-management/schools/{school}', [App\Http\Controllers\AdministratorSystemManagementController::class, 'destroySchool'])->name('system-management.schools.destroy');
        Route::delete('/system-management/schools/{school}/force', [App\Http\Controllers\AdministratorSystemManagementController::class, 'forceDestroySchool'])->name('system-management.schools.force-destroy');
        Route::put('/system-management/seo', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSeo'])->name('system-management.seo.update');
        Route::put('/system-management/analytics', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateAnalytics'])->name('system-management.analytics.update');
        Route::put('/system-management/brand', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateBrand'])->name('system-management.brand.update');
        Route::put('/system-management/socialite', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateSocialite'])->name('system-management.socialite.update');
        Route::put('/system-management/mail', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateMail'])->name('system-management.mail.update');
        Route::put('/system-management/newsletter', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateNewsletter'])->name('system-management.newsletter.update');
        Route::put('/system-management/api', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateApiManagement'])->name('system-management.api.update');
        Route::put('/system-management/enrollment-pipeline', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateEnrollmentPipeline'])->name('system-management.enrollment-pipeline.update');
        Route::post('/system-management/enrollment-policies', [AdministratorEnrollmentPolicyController::class, 'store'])->name('system-management.enrollment-policies.store');
        Route::get('/system-management/enrollment-policies/compatibility', [AdministratorEnrollmentPolicyController::class, 'compatibility'])->name('system-management.enrollment-policies.compatibility');
        Route::get('/system-management/enrollment-policies/{policy}/inheritance', [AdministratorEnrollmentPolicyController::class, 'inheritance'])->whereNumber('policy')->name('system-management.enrollment-policies.inheritance');
        Route::post('/system-management/enrollment-policies/activate', [AdministratorEnrollmentPolicyController::class, 'activate'])->name('system-management.enrollment-policies.activate');
        Route::post('/system-management/enrollment-policies/deactivate', [AdministratorEnrollmentPolicyController::class, 'deactivate'])->name('system-management.enrollment-policies.deactivate');
        Route::post('/system-management/enrollment-policies/import', [AdministratorEnrollmentPolicyController::class, 'import'])->name('system-management.enrollment-policies.import');
        Route::post('/system-management/enrollment-policies/{policy}/clone', [AdministratorEnrollmentPolicyController::class, 'clonePolicy'])->whereNumber('policy')->name('system-management.enrollment-policies.clone');
        Route::put('/system-management/enrollment-policies/{policy}/draft', [AdministratorEnrollmentPolicyController::class, 'updateDraft'])->whereNumber('policy')->name('system-management.enrollment-policies.draft.update');
        Route::post('/system-management/enrollment-policies/{policy}/versions/{version}/simulate', [AdministratorEnrollmentPolicyController::class, 'simulate'])->whereNumber(['policy', 'version'])->name('system-management.enrollment-policies.versions.simulate');
        Route::post('/system-management/enrollment-policies/{policy}/versions/{version}/publish', [AdministratorEnrollmentPolicyController::class, 'publish'])->whereNumber(['policy', 'version'])->name('system-management.enrollment-policies.versions.publish');
        Route::post('/system-management/enrollment-policies/{policy}/versions/{version}/rollback', [AdministratorEnrollmentPolicyController::class, 'rollback'])->whereNumber(['policy', 'version'])->name('system-management.enrollment-policies.versions.rollback');
        Route::get('/system-management/enrollment-policies/{policy}/versions/{version}/export', [AdministratorEnrollmentPolicyController::class, 'export'])->whereNumber(['policy', 'version'])->name('system-management.enrollment-policies.versions.export');
        Route::post('/system-management/mail/test', [App\Http\Controllers\AdministratorSystemManagementController::class, 'sendTestEmail'])->name('system-management.mail.test');
        Route::post('/system-management/newsletter/test', [App\Http\Controllers\AdministratorSystemManagementController::class, 'testNewsletterConnection'])->name('system-management.newsletter.test');
        Route::get('/system-management/notifications', [App\Http\Controllers\AdministratorSystemManagementController::class, 'notifications'])->name('system-management.notifications.index');
        Route::put('/system-management/notifications', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateNotificationChannels'])->name('system-management.notifications.update');
        Route::get('/system-management/finance-documents', [App\Http\Controllers\AdministratorSystemManagementController::class, 'financeDocuments'])->name('system-management.finance_documents.index');
        Route::put('/system-management/finance-documents', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateFinanceDocuments'])->name('system-management.finance_documents.update');
        Route::get('/system-management/grading', [App\Http\Controllers\AdministratorSystemManagementController::class, 'grading'])->name('system-management.grading.index');
        Route::put('/system-management/grading', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateGrading'])->name('system-management.grading.update');
        Route::put('/system-management/identifiers', [App\Http\Controllers\AdministratorSystemManagementController::class, 'updateIdentifiers'])->name('system-management.identifiers.update');

        // Help Tickets
        Route::get('/help-tickets', [App\Http\Controllers\AdministratorHelpTicketController::class, 'index'])->name('help-tickets.index');
        Route::get('/help-tickets/{helpTicket}', [App\Http\Controllers\AdministratorHelpTicketController::class, 'show'])->name('help-tickets.show');
        Route::post('/help-tickets/{helpTicket}/reply', [App\Http\Controllers\AdministratorHelpTicketController::class, 'reply'])->name('help-tickets.reply');
        Route::put('/help-tickets/{helpTicket}', [App\Http\Controllers\AdministratorHelpTicketController::class, 'update'])->name('help-tickets.update');
        Route::delete('/help-tickets/{helpTicket}', [App\Http\Controllers\AdministratorHelpTicketController::class, 'destroy'])->name('help-tickets.destroy');

        // Notifications
        Route::get('/notifications/inbox', [App\Http\Controllers\NotificationController::class, 'inbox'])->name('notifications.inbox');
        Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::delete('/notifications/{id}', [App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');
    });
