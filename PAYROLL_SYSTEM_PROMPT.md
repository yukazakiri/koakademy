# Payroll Management System Implementation Prompt

## Overview
You are tasked with implementing a comprehensive **Payroll Management System** for administrators in the KoAkademy Laravel application. This system should be built as a **module** using the existing Coolsam\Modules architecture, and it **DEPENDS ON** the HR Management System being implemented first.

**PREREQUISITE**: The HR Management System must be implemented BEFORE this Payroll system, as Payroll extends and builds upon the core HR models.

**Architecture Decision**:
- Core HR Models (Employee, Department, Position, etc.) → `app/Models/` (shared foundation)
- HR Features (Leave, Attendance, Performance) → `Modules/HR/`
- Payroll Features → `Modules/Payroll/` (extends HR models)

This ensures clean separation, reusability, and proper dependency flow.

---

## Integration with HR Management System

### Before Starting Payroll Implementation
Ensure the HR Management System has been fully implemented with:
- ✅ Employee model created in `app/Models/Employee.php`
- ✅ Position model created in `app/Models/Position.php`
- ✅ Department model updated with HR fields
- ✅ User and Faculty models updated with employee_id columns
- ✅ All HR migrations applied
- ✅ Modules/HR/ fully functional

### How Payroll Extends HR
1. **Employee Data Source**: Payroll uses `Employee` from `app/Models` as the single source of truth for employee information
2. **Salary Grades**: Both HR and Payroll reference `SalaryGrade` model (in Payroll module)
3. **Approval Workflows**: Payroll leverages Employee hierarchy (manager_id) for approval chain
4. **Reporting**: Payroll uses Department and Position data from HR for analytics
5. **Leave Integration**: Payroll can factor leave balances from `Modules/HR/app/Models/LeaveRequest` into calculations
6. **Events**: HR events (EmployeeTerminated, EmployeeHired) can trigger Payroll updates

### Import Pattern
In Payroll module classes:
```php
use App\Models\Employee;      // From core app/Models
use App\Models\Position;      // From core app/Models
use App\Models\Department;    // From core app/Models
use Modules\Payroll\Models\Payroll;        // Payroll-specific
use Modules\HR\Models\LeaveRequest;        // Can reference HR module models for integration
```

---

## Key Requirements

### 1. Architecture & Structure
- **Module Location**: Create the module at `/Modules/Payroll`
- **Module Structure**: Follow existing module patterns (Cashier, Inventory, etc.)
  - `Modules/Payroll/app/Filament/` - Filament admin resources and pages
  - `Modules/Payroll/app/Http/Controllers/` - Controllers for routes
  - `Modules/Payroll/app/Models/` - New payroll-specific models ONLY
  - `Modules/Payroll/app/Providers/` - Service providers
  - `Modules/Payroll/database/migrations/` - Migration files
  - `Modules/Payroll/database/seeders/` - Seeder files
  - `Modules/Payroll/routes/` - Module routes
  - `Modules/Payroll/resources/views/` - Blade templates if needed
- **Module Configuration**: Create `Modules/Payroll/module.json` with proper metadata

### 2. Existing Models Usage (CRITICAL)
**DO NOT extend models unnecessarily.** Only update existing models if critical functionality requires it.

#### Models to Utilize:
- **`Employee` model** (from HR System) - Central employee record with all HR data
  - Contains: employee_number, hire_date, employment_status, position_id, department_id, manager_id
  - Use this as the main record for payroll processing
  - Located in `app/Models/Employee.php`

- **`User` model** - System user account
  - Use for admin/HR personnel managing payroll
  - Link to Employee for additional context
  - Already has `role`, `department_id`, `school_id`

- **`Faculty` model** - Faculty-specific records
  - Link to Employee if needed
  - Support faculty-specific payroll if different from regular employees

- **`Department` model** (updated by HR System) - Department information
  - Use for filtering and reporting payroll by department
  - Links to employees

- **`Position` model** (from HR System) - Job positions
  - Contains position name, level, department
  - Can link to salary grades
  - Located in `app/Models/Position.php`

- **`Transaction` model** - Existing transaction system
  - Link payroll transactions to existing Transaction system

- **`Account` model** - Existing account system
  - Consider linking bank account info for salary transfers if needed

#### Columns to ADD to Existing Tables (NOT new models):
**NOTE**: The following columns will be added by the HR Management System. The Payroll module should NOT duplicate these migrations. Just reference them.

- **`users` table** (added by HR System): 
  - `employee_id` (foreign key to Employee)
  - `salary_grade_id` (foreign key, nullable)
  - `designation` (string, nullable)
  - `employee_number` (string, unique, nullable)
  
- **`faculty` table** (added by HR System):
  - `employee_id` (foreign key to Employee)
  - `salary_grade_id` (foreign key)
  - `designation` (string)
  - `employee_number` (string, unique)
  - `bank_account_number` (string, nullable)
  - `tax_identification_number` (string, nullable)

**Payroll-Specific Migration** (only if needed):
- May add to `payroll` table: `overtime_multiplier`, `advance_deduction` fields if not covered by PayrollItems

### 3. New Models to Create (Payroll Module Only)
Create these NEW models ONLY in `Modules/Payroll/app/Models/`:

- **`Payroll`** - Main payroll record
  - Properties: id, employee_id (foreign key to Employee from app/Models), payroll_period_id, base_salary, gross_salary, net_salary, payment_date, status (draft/submitted/approved/paid/archived), notes, created_at, updated_at, deleted_at
  - Relationships: 
    - `belongsTo(Employee)` - from app/Models (NOT Modules/HR)
    - `belongsToMany(User)` - for multi-level approvals
    - `hasMany(PayrollItem)` - allowances and earnings
    - `hasMany(PayrollDeduction)` - deductions applied
    - `hasOne(PayrollApproval)` - latest approval status
    - `hasOne(PayrollTransaction)` - link to transaction

- **`PayrollPeriod`** - Payroll cycle definition
  - Properties: id, period_name, start_date, end_date, is_active, is_locked, created_at, updated_at
  - Relationships: hasMany Payroll, hasMany PayrollItems

- **`SalaryGrade`** - Salary classification system
  - Properties: id, grade_code, grade_name, base_salary, description, created_at, updated_at
  - Relationships: hasMany Users/Faculty (via salary_grade_id)

- **`DeductionType`** - Types of deductions (taxes, insurance, loans, etc.)
  - Properties: id, name, code, description, is_mandatory, percentage, fixed_amount, applies_to_role, created_at, updated_at
  - Relationships: hasMany Deductions, hasMany PayrollDeductions

- **`PayrollItem`** - Individual payroll line items (allowances, earnings)
  - Properties: id, payroll_id, item_type, description, amount, created_at, updated_at
  - Relationships: belongsTo Payroll

- **`PayrollDeduction`** - Deductions for a payroll
  - Properties: id, payroll_id, deduction_type_id, amount, notes, created_at, updated_at
  - Relationships: belongsTo Payroll, belongsTo DeductionType

- **`PayrollApproval`** - Approval workflow
  - Properties: id, payroll_id, approved_by_user_id, status (pending/approved/rejected), notes, approved_at, created_at, updated_at
  - Relationships: belongsTo Payroll, belongsTo User (approved_by)

- **`PayrollTransaction`** - Link to Transaction system
  - Properties: id, payroll_id, transaction_id, payment_method, reference_number, created_at, updated_at
  - Relationships: belongsTo Payroll, belongsTo Transaction

### 4. User/Faculty Updates (Minimal Changes)

#### Update Existing Users:
- **Backfill Migration**: Create a migration to add `salary_grade_id`, `designation`, `employee_number` to `users` table
- **No Model Code Changes**: Do NOT modify `User.php` unless absolutely necessary
- **Accessors/Relationships**: Consider adding payroll-related accessors if needed, but keep minimal

#### Update Existing Faculty:
- **Backfill Migration**: Create a migration to add payroll columns to `faculty` table
- **No Model Code Changes**: Do NOT modify `Faculty.php` unless absolutely necessary
- **Existing Relationships**: Faculty already has relationships, just use them

### 5. Filament Admin Resources
Create Filament v5 resources in `Modules/Payroll/app/Filament/Resources/`:

- **`PayrollResource`** - Main payroll CRUD
  - List view with filters (by employee, status, period, date range)
  - Create/Edit forms with validations
  - Actions: Calculate, Approve, Reject, View PDF, Mark as Paid
  - Infolist for viewing details
  
- **`SalaryGradeResource`** - Manage salary grades
  - CRUD operations
  - Set base salaries
  
- **`DeductionTypeResource`** - Configure deductions
  - CRUD operations
  - Set percentages/fixed amounts
  
- **`PayrollPeriodResource`** - Create and manage payroll periods
  - CRUD operations
  - Lock/Unlock periods
  - Activate/Deactivate
  
- **`PayrollApprovalResource`** - Approval workflow dashboard
  - List pending approvals
  - Approve/Reject actions
  - View history

- **`PayrollReportResource`** - Analytics and reports
  - Summary statistics
  - Charts showing payroll trends
  - Export functionality (Excel/PDF)

### 6. Business Logic & Features

#### Payroll Calculation:
- **Automatic Calculation**: Gross = Base Salary + Allowances
- **Deductions**: Apply mandatory and optional deductions based on DeductionType
- **Net Salary**: Gross - Total Deductions
- **Tax Calculation**: Integrate with salary grade and tax rules (implement basic tax slab system)
- **Overtime Handling**: Support overtime multipliers if applicable

#### Payroll Approval Workflow:
- Payroll must go through approval stages before payment
- Support multi-level approval (HR Manager → Finance Manager → System Admin)
- Track approval history with timestamps and notes
- Status: Draft → Submitted → Approved → Paid → Archived

#### Integration with Transactions:
- When payroll is marked as "Paid", automatically create corresponding `Transaction` records
- Link `PayrollTransaction` model to track transaction-payroll relationship
- Support different payment methods (bank transfer, cash, check)

#### Data Validations:
- Employee must have a salary grade assigned
- Payroll period must not overlap with existing payroll records
- Only process payroll for active employees in the period
- Validate deduction amounts don't exceed gross salary
- Require approval before marking as paid

#### Authorization (Filament Gate/Policy):
- Only HR/Finance/Admin roles can create/edit payroll
- Only designated approvers can approve payroll
- Staff can only view their own payroll records
- Use Filament Shield or custom policies for permission management

### 7. Database Migrations
Create migration files in `Modules/Payroll/database/migrations/`:

- `xxxx_xx_xx_create_salary_grades_table.php`
- `xxxx_xx_xx_create_payroll_periods_table.php`
- `xxxx_xx_xx_create_deduction_types_table.php`
- `xxxx_xx_xx_add_payroll_columns_to_users_table.php` (salary_grade_id, designation, employee_number)
- `xxxx_xx_xx_add_payroll_columns_to_faculty_table.php` (salary_grade_id, designation, employee_number, bank_account_number, tax_id)
- `xxxx_xx_xx_create_payroll_table.php`
- `xxxx_xx_xx_create_payroll_items_table.php`
- `xxxx_xx_xx_create_payroll_deductions_table.php`
- `xxxx_xx_xx_create_payroll_approvals_table.php`
- `xxxx_xx_xx_create_payroll_transactions_table.php`

### 8. Seeders & Testing Data
Create in `Modules/Payroll/database/seeders/`:

- `SalaryGradeSeeder` - Create sample salary grades (Junior, Senior, Manager, etc.)
- `DeductionTypeSeeder` - Create common deductions (Income Tax, Health Insurance, Pension, etc.)
- `PayrollPeriodSeeder` - Create current/past payroll periods
- Bonus: Create factory files for testing

### 9. Routes
Define in `Modules/Payroll/routes/web.php`:

- Resource routes for Filament resources (auto-registered by Filament)
- API routes if needed for fetching payroll data
- Export route for PDF/Excel generation

### 10. Service Classes (Business Logic)
Create service classes in `Modules/Payroll/app/Services/`:

- **`PayrollCalculationService`** - Handle all payroll math
  - calculateGrossSalary()
  - calculateDeductions()
  - calculateNetSalary()
  - calculateTax()

- **`PayrollApprovalService`** - Handle approval workflow
  - submitForApproval()
  - approve()
  - reject()
  - getApprovalChain()

- **`PayrollExportService`** - Handle Excel/PDF exports
  - exportToExcel()
  - exportToPdf()
  - generateReport()

### 11. Notifications & Events
Optional but recommended:

- **Events**: PayrollCreated, PayrollApproved, PayrollRejected, PayrollPaid
- **Notifications**: Notify employees when payroll is processed, approved, or ready for payment
- **Email**: Send payroll slips via email when marked as paid

### 12. Testing
Create tests in `Modules/Payroll/tests/`:

- Test payroll calculation accuracy
- Test approval workflow
- Test transaction integration
- Test authorization/policies
- Test export functionality
- Test validations

### 13. Documentation
Include in module:

- `README.md` - Setup and usage instructions
- PHPDoc blocks on all classes and methods
- Inline comments for complex business logic

## Implementation Checklist

- [ ] Create `/Modules/Payroll/` directory structure
- [ ] Create `module.json` with PayrollServiceProvider
- [ ] Create migrations for new tables AND columns for existing tables
- [ ] Create models (Payroll, PayrollPeriod, SalaryGrade, DeductionType, etc.)
- [ ] Backfill columns to Users and Faculty tables
- [ ] Create Filament Resources for admin interface
- [ ] Implement PayrollCalculationService
- [ ] Implement PayrollApprovalService
- [ ] Create seeders with sample data
- [ ] Implement authorization checks
- [ ] Add route definitions
- [ ] Create tests
- [ ] Run migrations and seeders
- [ ] Test the complete payroll workflow end-to-end
- [ ] Verify existing Users and Faculty data is preserved

## Important Constraints

1. **NO UNNECESSARY MODEL EXTENSIONS**: Only add columns to User/Faculty tables, do NOT extend their classes
2. **USE EXISTING TRANSACTION SYSTEM**: Link payroll to existing Transaction model
3. **MINIMAL CHANGES TO CORE**: Keep changes to `app/Models/User.php` and `app/Models/Faculty.php` to a minimum
4. **FOLLOW EXISTING PATTERNS**: Mirror the structure and conventions used in other modules (Cashier, Inventory)
5. **LARAVEL 12 BEST PRACTICES**: Use latest Laravel 12, Filament v5, Pest v4 features
6. **TYPE SAFETY**: Use strict typing, PHP 8.4 features, explicit type hints
7. **AUTHORIZATION**: Implement proper authorization using Filament policies or Laravel Gates
8. **VALIDATION**: Use Form Requests and Filament validation rules consistently
9. **TESTING**: Write Pest tests to verify functionality

## Success Criteria

✅ Payroll module is created and functional
✅ Existing User and Faculty data is NOT corrupted
✅ Payroll can be calculated, approved, and marked as paid
✅ Transactions are automatically created when payroll is paid
✅ Admins can view comprehensive payroll reports
✅ All tests pass
✅ System follows Laravel 12 and Filament v5 best practices
✅ Authorization and validation are properly implemented
✅ Code is clean, well-documented, and follows existing conventions

---

## Implementation Order & Dependency

**THIS MUST BE IMPLEMENTED AFTER HR SYSTEM**

### Sequence:
1. **First**: Implement HR Management System (`HR_MANAGEMENT_SYSTEM_PROMPT.md`)
   - Creates core Employee, Position, Department models in `app/Models/`
   - Creates HR module at `Modules/HR/`
   - Implements leave, attendance, performance features

2. **Second**: Implement Payroll Management System (this document)
   - Extends Employee model via relationships (no extension needed)
   - Creates Payroll module at `Modules/Payroll/`
   - Links to HR data for employee information
   - Processes payroll calculations and approvals

### Dependency Graph
```
app/Models/
├── Employee         ← Created by HR System
├── Position         ← Created by HR System
├── Department       ← Updated by HR System
├── User             ← Updated by HR System
└── Faculty          ← Updated by HR System

Modules/HR/
├── app/Models/LeaveRequest
├── app/Models/EmployeeAttendance
└── app/Models/PerformanceReview
    ↓ (depends on)
    app/Models/Employee

Modules/Payroll/
├── app/Models/Payroll
├── app/Models/SalaryGrade
└── app/Models/PayrollApproval
    ↓ (depends on)
    app/Models/Employee, Department, Position
```

**Flow**: Payroll → HR → Core Models (clean dependency flow)
