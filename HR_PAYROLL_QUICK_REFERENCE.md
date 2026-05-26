# Quick Reference: File Structure & Command Guide

## File Structure Overview

### After HR System Implementation

```
app/Models/
├── Employee.php (NEW)
├── Position.php (NEW)
├── Department.php (UPDATED)
├── User.php (UPDATED with employee_id, designation, etc.)
├── Faculty.php (UPDATED with employee_id, salary_grade_id, etc.)
├── [other existing models...]
└── ...

Modules/HR/
├── app/
│   ├── Filament/
│   │   └── Resources/
│   │       ├── EmployeeResource.php
│   │       ├── DepartmentResource.php
│   │       ├── PositionResource.php
│   │       ├── LeaveRequestResource.php
│   │       ├── EmployeeAttendanceResource.php
│   │       ├── PerformanceReviewResource.php
│   │       └── TrainingResource.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── HR/
│   │   │   │   ├── EmployeeController.php
│   │   │   │   ├── DepartmentController.php
│   │   │   │   ├── PositionController.php
│   │   │   │   ├── LeaveRequestController.php
│   │   │   │   └── PerformanceReviewController.php
│   │   │   └── Api/
│   │   │       ├── EmployeeController.php
│   │   │       ├── LeaveRequestController.php
│   │   │       └── DepartmentController.php
│   │   └── Requests/
│   │       ├── StoreEmployeeRequest.php
│   │       ├── UpdateEmployeeRequest.php
│   │       └── StoreLeaveRequestRequest.php
│   ├── Models/
│   │   ├── LeaveType.php
│   │   ├── LeaveRequest.php
│   │   ├── EmployeeAttendance.php
│   │   ├── PerformanceReview.php
│   │   ├── Training.php
│   │   └── EmployeeDocument.php
│   ├── Services/
│   │   ├── EmployeeService.php
│   │   ├── LeaveService.php
│   │   ├── AttendanceService.php
│   │   └── PerformanceService.php
│   ├── Policies/
│   │   ├── EmployeePolicy.php
│   │   ├── LeaveRequestPolicy.php
│   │   └── PerformanceReviewPolicy.php
│   ├── Events/
│   │   ├── EmployeeCreated.php
│   │   ├── EmployeeTerminated.php
│   │   ├── LeaveRequested.php
│   │   └── LeaveApproved.php
│   ├── Providers/
│   │   └── HRServiceProvider.php
│   └── [other app folders...]
├── database/
│   ├── migrations/
│   │   ├── xxxx_xx_xx_create_employees_table.php
│   │   ├── xxxx_xx_xx_create_positions_table.php
│   │   ├── xxxx_xx_xx_add_hr_columns_to_users_table.php
│   │   ├── xxxx_xx_xx_add_hr_columns_to_faculty_table.php
│   │   ├── xxxx_xx_xx_update_departments_table.php
│   │   ├── xxxx_xx_xx_create_leave_types_table.php
│   │   ├── xxxx_xx_xx_create_leave_requests_table.php
│   │   ├── xxxx_xx_xx_create_employee_attendance_table.php
│   │   ├── xxxx_xx_xx_create_performance_reviews_table.php
│   │   ├── xxxx_xx_xx_create_training_table.php
│   │   ├── xxxx_xx_xx_create_training_employee_pivot_table.php
│   │   └── xxxx_xx_xx_create_employee_documents_table.php
│   └── seeders/
│       ├── EmployeeSeeder.php
│       ├── DepartmentSeeder.php
│       ├── PositionSeeder.php
│       └── LeaveTypeSeeder.php
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   └── inertia-admin.blade.php
│   │   └── inertia/
│   │       ├── Pages/
│   │       │   ├── Employee/
│   │       │   │   ├── Index.jsx
│   │       │   │   ├── Show.jsx
│   │       │   │   ├── Create.jsx
│   │       │   │   └── Edit.jsx
│   │       │   ├── Department/
│   │       │   │   ├── Index.jsx
│   │       │   │   ├── Show.jsx
│   │       │   │   ├── Create.jsx
│   │       │   │   └── Edit.jsx
│   │       │   ├── LeaveRequest/
│   │       │   │   ├── Index.jsx
│   │       │   │   ├── Show.jsx
│   │       │   │   └── Create.jsx
│   │       │   ├── Position/
│   │       │   │   ├── Index.jsx
│   │       │   │   ├── Show.jsx
│   │       │   │   ├── Create.jsx
│   │       │   │   └── Edit.jsx
│   │       │   └── Reports/
│   │       │       └── Dashboard.jsx
│   │       └── Components/
│   │           └── HR/
│   │               ├── EmployeeTable.jsx
│   │               ├── DepartmentForm.jsx
│   │               └── LeaveRequestForm.jsx
│   └── js/
│       └── pages/ (auto-linked from resources/views/inertia/Pages)
├── routes/
│   ├── web.php
│   ├── api.php
│   └── inertia-admin.php
├── tests/
│   ├── Feature/
│   │   ├── EmployeeTest.php
│   │   ├── LeaveRequestTest.php
│   │   └── EmployeeControllerTest.php
│   └── Unit/
│       ├── EmployeeServiceTest.php
│       ├── LeaveServiceTest.php
│       └── AttendanceServiceTest.php
├── module.json
├── composer.json
├── package.json
└── README.md
```

### After Payroll System Implementation

```
Modules/Payroll/
├── app/
│   ├── Filament/
│   │   └── Resources/
│   │       ├── PayrollResource.php
│   │       ├── SalaryGradeResource.php
│   │       ├── DeductionTypeResource.php
│   │       ├── PayrollPeriodResource.php
│   │       ├── PayrollApprovalResource.php
│   │       └── PayrollReportResource.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── PayrollController.php
│   │   │   ├── SalaryGradeController.php
│   │   │   ├── DeductionTypeController.php
│   │   │   └── ReportController.php
│   │   └── Requests/
│   │       └── StorePayrollRequest.php
│   ├── Models/
│   │   ├── Payroll.php
│   │   ├── PayrollPeriod.php
│   │   ├── SalaryGrade.php
│   │   ├── DeductionType.php
│   │   ├── PayrollItem.php
│   │   ├── PayrollDeduction.php
│   │   ├── PayrollApproval.php
│   │   └── PayrollTransaction.php
│   ├── Services/
│   │   ├── PayrollCalculationService.php
│   │   ├── PayrollApprovalService.php
│   │   └── PayrollExportService.php
│   ├── Events/
│   │   ├── PayrollCreated.php
│   │   ├── PayrollApproved.php
│   │   ├── PayrollRejected.php
│   │   └── PayrollPaid.php
│   ├── Providers/
│   │   └── PayrollServiceProvider.php
│   └── [other app folders...]
├── database/
│   ├── migrations/
│   │   ├── xxxx_xx_xx_create_salary_grades_table.php
│   │   ├── xxxx_xx_xx_create_payroll_periods_table.php
│   │   ├── xxxx_xx_xx_create_deduction_types_table.php
│   │   ├── xxxx_xx_xx_create_payroll_table.php
│   │   ├── xxxx_xx_xx_create_payroll_items_table.php
│   │   ├── xxxx_xx_xx_create_payroll_deductions_table.php
│   │   ├── xxxx_xx_xx_create_payroll_approvals_table.php
│   │   └── xxxx_xx_xx_create_payroll_transactions_table.php
│   └── seeders/
│       ├── SalaryGradeSeeder.php
│       ├── DeductionTypeSeeder.php
│       └── PayrollPeriodSeeder.php
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
│   ├── Feature/
│   │   ├── PayrollCalculationTest.php
│   │   ├── PayrollApprovalTest.php
│   │   └── PayrollExportTest.php
│   └── Unit/
│       ├── PayrollCalculationServiceTest.php
│       └── PayrollApprovalServiceTest.php
├── module.json
├── composer.json
└── README.md
```

---

## Essential Artisan Commands

### HR System Setup

```bash
# 1. Create core models (app/Models)
vendor/bin/sail artisan make:model Employee -m
vendor/bin/sail artisan make:model Position -m
vendor/bin/sail artisan make:model -f Department  # Force (already exists)

# 2. Create HR module structure
vendor/bin/sail artisan module:make HR

# 3. Create HR module models
vendor/bin/sail artisan make:model Modules/HR/Models/LeaveType -m --no-interaction
vendor/bin/sail artisan make:model Modules/HR/Models/LeaveRequest -m --no-interaction
vendor/bin/sail artisan make:model Modules/HR/Models/EmployeeAttendance -m --no-interaction
vendor/bin/sail artisan make:model Modules/HR/Models/PerformanceReview -m --no-interaction
vendor/bin/sail artisan make:model Modules/HR/Models/Training -m --no-interaction
vendor/bin/sail artisan make:model Modules/HR/Models/EmployeeDocument -m --no-interaction

# 4. Create Filament resources
vendor/bin/sail artisan make:filament-resource Modules/HR/app/Filament/Resources/EmployeeResource --no-interaction
vendor/bin/sail artisan make:filament-resource Modules/HR/app/Filament/Resources/DepartmentResource --no-interaction
vendor/bin/sail artisan make:filament-resource Modules/HR/app/Filament/Resources/PositionResource --no-interaction
vendor/bin/sail artisan make:filament-resource Modules/HR/app/Filament/Resources/LeaveRequestResource --no-interaction
# ... create others

# 5. Create controllers for Inertia
vendor/bin/sail artisan make:controller Modules/HR/app/Http/Controllers/HR/EmployeeController --no-interaction
vendor/bin/sail artisan make:controller Modules/HR/app/Http/Controllers/HR/LeaveRequestController --no-interaction
# ... create others

# 6. Create Form Requests
vendor/bin/sail artisan make:request Modules/HR/app/Http/Requests/StoreEmployeeRequest --no-interaction
vendor/bin/sail artisan make:request Modules/HR/app/Http/Requests/UpdateEmployeeRequest --no-interaction
# ... create others

# 7. Create services
vendor/bin/sail artisan make:class Modules/HR/app/Services/EmployeeService --no-interaction
vendor/bin/sail artisan make:class Modules/HR/app/Services/LeaveService --no-interaction
# ... create others

# 8. Create policies
vendor/bin/sail artisan make:policy Modules/HR/app/Policies/EmployeePolicy --no-interaction
vendor/bin/sail artisan make:policy Modules/HR/app/Policies/LeaveRequestPolicy --no-interaction
# ... create others

# 9. Create events
vendor/bin/sail artisan make:event Modules/HR/app/Events/EmployeeCreated --no-interaction
vendor/bin/sail artisan make:event Modules/HR/app/Events/LeaveRequested --no-interaction
# ... create others

# 10. Create seeders
vendor/bin/sail artisan make:seeder Modules/HR/database/seeders/EmployeeSeeder --no-interaction
vendor/bin/sail artisan make:seeder Modules/HR/database/seeders/LeaveTypeSeeder --no-interaction
# ... create others

# 11. Create factories
vendor/bin/sail artisan make:factory Modules/HR/database/factories/EmployeeFactory --no-interaction
vendor/bin/sail artisan make:factory Modules/HR/database/factories/LeaveRequestFactory --no-interaction
# ... create others

# 12. Create tests
vendor/bin/sail artisan make:test Modules/HR/tests/Feature/EmployeeTest --pest --no-interaction
vendor/bin/sail artisan make:test Modules/HR/tests/Unit/EmployeeServiceTest --pest --unit --no-interaction
# ... create others

# 13. Run migrations
vendor/bin/sail artisan migrate --no-interaction

# 14. Seed data
vendor/bin/sail artisan db:seed --class=Modules\\HR\\database\\seeders\\EmployeeSeeder --no-interaction
vendor/bin/sail artisan db:seed --class=Modules\\HR\\database\\seeders\\LeaveTypeSeeder --no-interaction
```

### Payroll System Setup

```bash
# 1. Create Payroll module
vendor/bin/sail artisan module:make Payroll

# 2. Create Payroll module models
vendor/bin/sail artisan make:model Modules/Payroll/Models/Payroll -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/PayrollPeriod -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/SalaryGrade -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/DeductionType -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/PayrollItem -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/PayrollDeduction -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/PayrollApproval -m --no-interaction
vendor/bin/sail artisan make:model Modules/Payroll/Models/PayrollTransaction -m --no-interaction

# 3. Create Filament resources
vendor/bin/sail artisan make:filament-resource Modules/Payroll/app/Filament/Resources/PayrollResource --no-interaction
vendor/bin/sail artisan make:filament-resource Modules/Payroll/app/Filament/Resources/SalaryGradeResource --no-interaction
vendor/bin/sail artisan make:filament-resource Modules/Payroll/app/Filament/Resources/DeductionTypeResource --no-interaction
# ... create others

# 4. Create controllers
vendor/bin/sail artisan make:controller Modules/Payroll/app/Http/Controllers/PayrollController --no-interaction
vendor/bin/sail artisan make:controller Modules/Payroll/app/Http/Controllers/ReportController --no-interaction

# 5. Create services
vendor/bin/sail artisan make:class Modules/Payroll/app/Services/PayrollCalculationService --no-interaction
vendor/bin/sail artisan make:class Modules/Payroll/app/Services/PayrollApprovalService --no-interaction
vendor/bin/sail artisan make:class Modules/Payroll/app/Services/PayrollExportService --no-interaction

# 6. Create events
vendor/bin/sail artisan make:event Modules/Payroll/app/Events/PayrollCreated --no-interaction
vendor/bin/sail artisan make:event Modules/Payroll/app/Events/PayrollApproved --no-interaction

# 7. Create seeders
vendor/bin/sail artisan make:seeder Modules/Payroll/database/seeders/SalaryGradeSeeder --no-interaction
vendor/bin/sail artisan make:seeder Modules/Payroll/database/seeders/DeductionTypeSeeder --no-interaction

# 8. Create tests
vendor/bin/sail artisan make:test Modules/Payroll/tests/Feature/PayrollCalculationTest --pest --no-interaction
vendor/bin/sail artisan make:test Modules/Payroll/tests/Unit/PayrollCalculationServiceTest --pest --unit --no-interaction

# 9. Run migrations
vendor/bin/sail artisan migrate --no-interaction

# 10. Seed data
vendor/bin/sail artisan db:seed --class=Modules\\Payroll\\database\\seeders\\SalaryGradeSeeder --no-interaction
vendor/bin/sail artisan db:seed --class=Modules\\Payroll\\database\\seeders\\DeductionTypeSeeder --no-interaction
```

### Common Development Commands

```bash
# Start development environment
vendor/bin/sail up -d

# Stop development environment
vendor/bin/sail stop

# View logs
vendor/bin/sail logs

# Access database
vendor/bin/sail mysql

# Tinker (PHP REPL)
vendor/bin/sail artisan tinker

# Run tests
vendor/bin/sail artisan test --compact

# Run specific test file
vendor/bin/sail artisan test tests/Feature/EmployeeTest.php --compact

# Run with filter
vendor/bin/sail artisan test --compact --filter=testEmployeeCreation

# Format code
vendor/bin/sail bin pint --dirty

# Generate IDE helpers
vendor/bin/sail artisan ide-helper:generate
vendor/bin/sail artisan ide-helper:models --nowrite

# Clear caches
vendor/bin/sail artisan cache:clear
vendor/bin/sail artisan config:clear
vendor/bin/sail artisan view:clear

# Migrate fresh (reset database)
vendor/bin/sail artisan migrate:fresh --seed

# Open in browser
vendor/bin/sail open
```

---

## Key Files to Reference

### After HR System

```
app/Models/Employee.php          # ← Central employee record
app/Models/Position.php          # ← Job positions
Modules/HR/app/Services/EmployeeService.php    # ← Employee business logic
Modules/HR/app/Filament/Resources/EmployeeResource.php  # ← Filament admin
Modules/HR/app/Http/Controllers/HR/EmployeeController.php  # ← Inertia controller
routes/web/admin.php             # ← Admin domain routes reference
```

### After Payroll System

```
Modules/Payroll/app/Models/Payroll.php         # ← Main payroll model
Modules/Payroll/app/Services/PayrollCalculationService.php  # ← Calculations
Modules/Payroll/app/Filament/Resources/PayrollResource.php  # ← Filament admin
Modules/Payroll/database/migrations/            # ← Payroll tables
```

---

## Environment Variables (if needed)

```bash
# .env
APP_ADMIN_HOST=admin.koakademy.test

# Payroll settings
PAYROLL_CURRENCY=USD
PAYROLL_TAX_RATE=0.10  # 10% default tax
```

---

## Testing Commands

```bash
# Run all tests
vendor/bin/sail artisan test --compact

# Run HR tests only
vendor/bin/sail artisan test Modules/HR/tests --compact

# Run Payroll tests only
vendor/bin/sail artisan test Modules/Payroll/tests --compact

# Run with coverage
vendor/bin/sail artisan test --compact --coverage

# Browser testing (Pest v4)
vendor/bin/sail artisan test tests/Browser --compact
```

---

## Deployment Checklist Commands

```bash
# Before deployment
vendor/bin/sail artisan config:cache
vendor/bin/sail artisan route:cache
vendor/bin/sail artisan view:cache
vendor/bin/sail artisan event:cache

# Run migrations on production
vendor/bin/sail artisan migrate --force

# Seed initial data if needed
vendor/bin/sail artisan db:seed --class=Modules\\HR\\database\\seeders\\LeaveTypeSeeder --force
vendor/bin/sail artisan db:seed --class=Modules\\Payroll\\database\\seeders\\SalaryGradeSeeder --force

# After deployment
vendor/bin/sail artisan cache:clear
vendor/bin/sail artisan config:clear
vendor/bin/sail artisan view:clear
vendor/bin/sail artisan event:clear
```

---

## IDE Support

```bash
# Generate IDE helpers for better autocomplete
vendor/bin/sail artisan ide-helper:generate
vendor/bin/sail artisan ide-helper:models --nowrite
vendor/bin/sail artisan ide-helper:meta
```

---

## Import Map for Both Systems

### In HR Module Classes
```php
use App\Models\Employee;           // Core
use App\Models\Department;         // Core
use App\Models\Position;           // Core
use App\Models\User;               // Core
use Modules\HR\Models\LeaveRequest;     // HR-specific
use Modules\HR\Services\EmployeeService;   // HR services
```

### In Payroll Module Classes
```php
use App\Models\Employee;           // Core (MAIN DEPENDENCY)
use App\Models\Department;         // Core
use App\Models\Position;           // Core
use Modules\Payroll\Models\Payroll;        // Payroll-specific
use Modules\Payroll\Models\SalaryGrade;    // Payroll-specific
use Modules\HR\Models\LeaveRequest;        // HR integration
use Modules\Payroll\Services\PayrollCalculationService;  // Payroll services
```

---

## Troubleshooting

```bash
# If models not found
vendor/bin/sail composer dump-autoload

# If migrations fail
vendor/bin/sail artisan migrate:rollback
vendor/bin/sail artisan migrate:status

# If Filament resources don't show
vendor/bin/sail artisan cache:clear
vendor/bin/sail artisan config:clear

# If Inertia components not loading
vendor/bin/sail npm run build

# Check database state
vendor/bin/sail artisan tinker
# Then: Employee::count(), User::count(), etc.
```
