# HR Management System Implementation Prompt

## Overview
You are tasked with implementing a comprehensive **HR Management System** for the KoAkademy Laravel application. This system is foundational and will be extended by other modules like Payroll.

**KEY ARCHITECTURE DECISION**: 
- **Core HR Models** → `app/Models/` (shared foundation for all modules)
- **HR Features & Admin Interfaces** → `Modules/HR/` (HR-specific functionality)
- **This ensures**: Other modules (Payroll, etc.) can extend and reuse core HR models

This system must support BOTH:
1. **Filament Admin Panel** - `/admin` path for traditional admin operations
2. **Inertia Admin Dashboard** - Admin domain for React-based modern admin interface

---

## Part 1: Core HR Models (app/Models/)

### Purpose
These are foundational models that represent the core HR business entities. They are placed in `app/Models/` so other modules (Payroll, Inventory, etc.) can import and extend them. Do NOT put these in the module folder.

### Core Models to Create/Update in `app/Models/`

#### 1. **Employee Model** (NEW - Core Foundation)
- **Location**: `app/Models/Employee.php`
- **Properties**:
  - id, user_id (foreign key to users), faculty_id (nullable, foreign key to faculty)
  - first_name, middle_name, last_name, employee_number (unique)
  - date_of_birth, gender, marital_status
  - personal_email, personal_phone
  - department_id, position_id, manager_id (self-referencing for hierarchy)
  - salary_grade_id (link to PayrollModule's SalaryGrade - nullable for now)
  - hire_date, contract_start_date, contract_end_date (nullable)
  - employment_status (active, on_leave, terminated, retired)
  - employment_type (full_time, part_time, contract, freelance)
  - bio, address, city, state, postal_code, country
  - emergency_contact_name, emergency_contact_phone, emergency_contact_relationship
  - is_active, deleted_at, created_at, updated_at
  
- **Relationships**:
  - `belongsTo(User)` - Link to main User model
  - `belongsToMany(Faculty)` - If employee can have multiple faculty records (nullable relation)
  - `belongsTo(Department)` - Department assignment
  - `belongsTo(Position)` - Job position
  - `belongsTo(Employee)` - Manager (self-referencing)
  - `hasMany(Employee)` - Subordinates (inverse of manager)
  - `hasMany(LeaveRequest)` - From HR module
  - `hasMany(EmployeeAttendance)` - From HR module
  - `hasMany(PerformanceReview)` - From HR module
  - `hasMany(Training)` - From HR module
  - `hasMany(Payroll)` - From Payroll module (when created)

- **Accessors/Methods**:
  - `getFullNameAttribute()` - first + middle + last
  - `getAgeAttribute()` - calculate from DOB
  - `getYearsOfServiceAttribute()` - calculate from hire_date
  - `isManager()` - check if has subordinates
  - `getReportingChain()` - get chain of command
  - `canApproveLeave()` - authorization check

#### 2. **Department Model** (Update Existing - Core Foundation)
- **Location**: `app/Models/Department.php`
- **Current Status**: Already exists in application
- **Updates Needed**:
  - Add: `head_id` (foreign key to Employee, nullable) - Department head
  - Add: `budget` (decimal, nullable) - Department budget
  - Add: `description` (text)
  - Add: `is_active` (boolean)
  - Relationships:
    - `belongsTo(Employee)` - Department head
    - `hasMany(Employee)` - Employees in department
    - `hasMany(Position)` - Positions in department

#### 3. **Position Model** (NEW - Core Foundation)
- **Location**: `app/Models/Position.php`
- **Properties**:
  - id, code (unique), name, description
  - department_id (foreign key)
  - salary_grade_id (nullable, links to Payroll module)
  - min_salary, max_salary (nullable, for reference)
  - level (junior, mid, senior, manager, executive)
  - reports_to_position_id (nullable, self-referencing)
  - is_active, created_at, updated_at

- **Relationships**:
  - `belongsTo(Department)`
  - `hasMany(Employee)` - Employees in this position
  - `belongsTo(Position)` - Reports to (self-referencing)
  - `hasMany(Position)` - Subordinate positions (inverse)

#### 4. **Update User Model** (app/Models/User.php)
- **Add Fields** (for payroll/HR):
  - `employee_id` (nullable, foreign key to Employee table)
  - `designation` (string, nullable) - Job title
  - `employee_number` (string, unique, nullable) - Employee ID
  - `department_id` (foreign key, nullable) - Direct department link
  - `salary_grade_id` (foreign key, nullable) - For payroll
  
- **Add Relationships**:
  - `hasOne(Employee)` or `belongsTo(Employee)`
  - `belongsTo(Department)`
  - Relationships for managing other users (if admin)

#### 5. **Update Faculty Model** (app/Models/Faculty.php)
- **Add Fields** (for payroll/HR):
  - `employee_id` (nullable, foreign key to Employee table)
  - `salary_grade_id` (foreign key, nullable)
  - `designation` (string) - Faculty rank/designation
  - `employee_number` (string, unique)
  - `bank_account_number` (string, nullable)
  - `tax_identification_number` (string, nullable)
  - `hire_date` (date, nullable)
  - `employment_status` (string)

- **Add Relationships**:
  - `hasOne(Employee)` or `belongsTo(Employee)`

---

## Part 2: HR Module (Modules/HR/)

### Purpose
This module contains HR-specific features, workflows, admin interfaces (both Filament and Inertia), and business logic that extends the core HR models.

### Module Structure

```
Modules/HR/
├── app/
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── EmployeeResource.php
│   │   │   ├── DepartmentResource.php
│   │   │   ├── PositionResource.php
│   │   │   ├── LeaveRequestResource.php
│   │   │   ├── EmployeeAttendanceResource.php
│   │   │   ├── PerformanceReviewResource.php
│   │   │   └── TrainingResource.php
│   │   └── Pages/
│   │       └── Dashboard/
│   │           └── HRDashboard.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── HR/
│   │   │   │   ├── EmployeeController.php
│   │   │   │   ├── DepartmentController.php
│   │   │   │   ├── PositionController.php
│   │   │   │   ├── LeaveRequestController.php
│   │   │   │   └── PerformanceReviewController.php
│   │   │   └── Api/
│   │   │       └── EmployeeController.php
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
│   ├── Providers/
│   │   └── HRServiceProvider.php
│   └── Events/
│       ├── EmployeeCreated.php
│       ├── EmployeeHired.php
│       ├── LeaveRequested.php
│       └── LeaveApproved.php
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
│   │       │   ├── Position/
│   │       │   └── LeaveRequest/
│   │       └── Components/
│   │           └── HR/
│   │               ├── EmployeeTable.jsx
│   │               ├── DepartmentForm.jsx
│   │               └── ...
│   └── js/
│       └── pages/ (auto-linked from resources/views/inertia/Pages)
├── routes/
│   ├── web.php
│   ├── api.php
│   └── inertia-admin.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── module.json
├── composer.json
├── package.json
└── README.md
```

### HR Module Models (Modules/HR/app/Models/)

#### 1. **LeaveType Model**
- Properties: id, name, code, total_days, is_paid, description, is_active, created_at, updated_at
- Relationships: `hasMany(LeaveRequest)`

#### 2. **LeaveRequest Model**
- Properties: id, employee_id, leave_type_id, start_date, end_date, reason, status (pending/approved/rejected/cancelled), approved_by_user_id, approval_date, notes, created_at, updated_at
- Relationships:
  - `belongsTo(Employee)` from app/Models
  - `belongsTo(LeaveType)`
  - `belongsTo(User, 'approved_by_user_id')` - Approver
  - `belongsTo(LeaveRequest)` - Parent leave request (if split)
  - `hasMany(LeaveRequest)` - Child leave requests

#### 3. **EmployeeAttendance Model**
- Properties: id, employee_id, check_in_time, check_out_time, date, hours_worked, status (present/absent/late/half_day), notes, device_info, location_info, created_at, updated_at
- Relationships: `belongsTo(Employee)`

#### 4. **PerformanceReview Model**
- Properties: id, employee_id, reviewed_by_user_id, review_date, rating (1-5), goals_met, strengths, areas_for_improvement, comments, next_review_date, created_at, updated_at
- Relationships:
  - `belongsTo(Employee)`
  - `belongsTo(User, 'reviewed_by_user_id')`

#### 5. **Training Model**
- Properties: id, title, description, start_date, end_date, location, provider, cost, max_participants, status (planned/in_progress/completed/cancelled), created_at, updated_at
- Relationships: `belongsToMany(Employee)` - Many-to-many training assignments

#### 6. **EmployeeDocument Model**
- Properties: id, employee_id, document_type, file_path, file_name, description, expiry_date (nullable), is_verified, uploaded_by_user_id, created_at, updated_at
- Relationships:
  - `belongsTo(Employee)`
  - `belongsTo(User, 'uploaded_by_user_id')`

---

## Part 3: Admin Interfaces

### 3.1 Filament Admin (`/admin` path)
- Use Filament v5 resources in `Modules/HR/app/Filament/Resources/`
- Follow existing Filament conventions in the application
- Create resources for:
  - EmployeeResource (full CRUD with advanced filters)
  - DepartmentResource
  - PositionResource
  - LeaveRequestResource (with approval workflow)
  - PerformanceReviewResource
  - TrainingResource
  - EmployeeAttendanceResource

**Features**:
- Bulk actions (export, delete, status change)
- Advanced filtering and search
- Custom actions (approve, reject, generate reports)
- Authorization using Filament policies
- Relations management (inline editing)

### 3.2 Inertia Admin (React-based dashboard on admin domain)
- Routes defined in `Modules/HR/routes/inertia-admin.php`
- Components in `Modules/HR/resources/js/pages/` (auto-registered)
- Views in `Modules/HR/resources/views/inertia/`
- Controllers in `Modules/HR/app/Http/Controllers/HR/`

**Architecture**:
```
Admin Domain Route Structure:
GET  /employees              - List employees (with React UI)
GET  /employees/:id          - View employee details
POST /employees              - Create employee
GET  /employees/:id/edit     - Edit employee
PUT  /employees/:id          - Update employee
DELETE /employees/:id        - Delete employee

GET  /departments            - List departments
GET  /departments/:id        - View department details
POST /departments            - Create department
...

GET  /leave-requests         - List leave requests
GET  /leave-requests/:id     - View leave request
POST /leave-requests/:id/approve - Approve leave
POST /leave-requests/:id/reject  - Reject leave
...

GET  /performance-reviews    - List reviews
GET  /attendance             - Attendance dashboard
GET  /reports                - HR reports
```

**Inertia React Components** (`Modules/HR/resources/js/pages/`):
- `Employees/Index.jsx` - Employee list with filtering, pagination
- `Employees/Show.jsx` - Employee profile view
- `Employees/Create.jsx` - Create employee form
- `Employees/Edit.jsx` - Edit employee form
- `Departments/Index.jsx` - Department management
- `LeaveRequests/Index.jsx` - Leave request dashboard
- `Reports/Dashboard.jsx` - HR analytics dashboard

**Controllers** (`Modules/HR/app/Http/Controllers/HR/`):
- Must return `Inertia::render()` responses
- Fetch data needed for React components
- Handle form submissions
- Return validation errors in expected format

**Example Controller Pattern**:
```php
public function index()
{
    return Inertia::render('Employees/Index', [
        'employees' => Employee::with('department', 'position', 'manager')
            ->paginate(15),
        'filters' => request()->only(['search', 'department_id', 'status']),
        'departments' => Department::pluck('name', 'id'),
    ]);
}
```

---

## Part 4: Routes

### 4.1 Filament Routes
- Auto-registered by Filament from resources
- No manual route definition needed (Filament handles this)
- Accessible at `/admin` path

### 4.2 Inertia Admin Routes (Admin Domain)
File: `Modules/HR/routes/inertia-admin.php`

```php
Route::domain(config('app.admin_host', 'admin.koakademy.test'))
    ->middleware(['auth', 'verified'])
    ->prefix('hr')
    ->group(function () {
        // Employee routes
        Route::resource('employees', EmployeeController::class);
        Route::post('employees/{employee}/approve', [EmployeeController::class, 'approve']);
        
        // Department routes
        Route::resource('departments', DepartmentController::class);
        
        // Leave request routes
        Route::resource('leave-requests', LeaveRequestController::class);
        Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
        
        // Performance review routes
        Route::resource('performance-reviews', PerformanceReviewController::class);
        
        // Reports
        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
```

### 4.3 Web Routes (Main application)
File: `Modules/HR/routes/web.php`

```php
// Public/authenticated employee routes
Route::middleware(['auth'])->group(function () {
    Route::get('/my-profile', [EmployeeController::class, 'profile'])->name('employee.profile');
    Route::get('/my-leave-requests', [LeaveRequestController::class, 'myRequests'])->name('leave.my-requests');
    Route::post('/my-leave-requests', [LeaveRequestController::class, 'store'])->name('leave.store');
});
```

### 4.4 API Routes (for React AJAX calls)
File: `Modules/HR/routes/api.php`

```php
Route::prefix('api/v1/hr')->middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('employees', Api\EmployeeController::class);
    Route::get('employees/{employee}/leave-balance', [Api\EmployeeController::class, 'leaveBalance']);
    Route::apiResource('leave-requests', Api\LeaveRequestController::class);
    Route::post('leave-requests/{leaveRequest}/approve', [Api\LeaveRequestController::class, 'approve']);
    Route::apiResource('departments', Api\DepartmentController::class);
});
```

---

## Part 5: Database Migrations

Create migrations in `Modules/HR/database/migrations/`:

1. **Create Employee Table** - Core HR foundation
   - All employee fields as defined in Employee model
   - Indexes on: user_id, faculty_id, employee_number, department_id, manager_id

2. **Create Position Table** - Job positions
   - All position fields
   - Indexes on: department_id, code

3. **Update Departments Table** - Add HR fields
   - Add: head_id, budget, description, is_active
   - Foreign key constraint to Employee for head_id

4. **Add HR columns to Users Table**
   - employee_id, designation, employee_number, department_id, salary_grade_id
   - Foreign keys with appropriate cascades

5. **Add HR columns to Faculty Table**
   - employee_id, salary_grade_id, designation, employee_number, bank_account_number, tax_id
   - hire_date, employment_status

6. **Create Leave Types Table**
7. **Create Leave Requests Table**
8. **Create Employee Attendance Table**
9. **Create Performance Reviews Table**
10. **Create Training Table**
11. **Create Training Employee Pivot Table** (for many-to-many)
12. **Create Employee Documents Table**

---

## Part 6: Service Classes

Create in `Modules/HR/app/Services/`:

- **`EmployeeService`** - Employee management
  - `createEmployee(array $data)`
  - `updateEmployee(Employee $employee, array $data)`
  - `terminateEmployee(Employee $employee, string $reason)`
  - `getEmployeeHierarchy(Employee $employee)`
  - `getDirectReports(Employee $employee)`

- **`LeaveService`** - Leave request handling
  - `requestLeave(Employee $employee, array $data)`
  - `approveLeave(LeaveRequest $request, User $approver, string $notes)`
  - `rejectLeave(LeaveRequest $request, User $reviewer, string $reason)`
  - `getLeaveBalance(Employee $employee, LeaveType $type)`
  - `calculateRemainingDays(Employee $employee, LeaveType $type)`

- **`AttendanceService`** - Attendance tracking
  - `checkIn(Employee $employee, array $data)`
  - `checkOut(Employee $employee, array $data)`
  - `getAttendanceReport(Employee $employee, $startDate, $endDate)`
  - `calculateHoursWorked(Attendance $attendance)`

- **`PerformanceService`** - Performance management
  - `createReview(Employee $employee, array $data)`
  - `getReviewHistory(Employee $employee)`
  - `generatePerformanceReport(Employee $employee, $period)`

- **`EmployeeDocumentService`** - Document management
  - `uploadDocument(Employee $employee, UploadedFile $file, string $type)`
  - `verifyDocument(EmployeeDocument $document)`
  - `checkExpiryDocuments()` - Notification system

---

## Part 7: Authorization & Policies

Create policies in `Modules/HR/app/Policies/`:

- **`EmployeePolicy`**
  - Only HR/Admin can view all employees
  - Employees can view their own profile
  - Managers can view their team members
  - Only Admin can create/update/delete

- **`LeaveRequestPolicy`**
  - Employees can only request their own leaves
  - Managers can approve leaves for their team
  - HR/Finance can see all leaves
  - Only approvers can approve/reject

- **`PerformanceReviewPolicy`**
  - Reviewers can create reviews
  - Only authorized roles can view reviews
  - HR/Admin can view all reviews

---

## Part 8: Events & Notifications

Create in `Modules/HR/app/Events/`:

- `EmployeeCreated` - When new employee added
- `EmployeeTerminated` - When employee terminated
- `LeaveRequested` - When leave request submitted
- `LeaveApproved` - When leave approved
- `LeaveRejected` - When leave rejected
- `PerformanceReviewCreated` - When review submitted

**Listeners/Notifications** (in Events folder or separate):
- Send emails to relevant parties
- Create activity log entries
- Update dashboards in real-time (via broadcasting if needed)

---

## Part 9: Module Configuration

File: `Modules/HR/module.json`

```json
{
    "name": "HR",
    "alias": "hr",
    "description": "Human Resources Management System with employee management, leave requests, attendance, performance reviews, and training.",
    "keywords": ["hr", "employees", "leave", "attendance", "performance"],
    "priority": 10,
    "providers": [
        "Modules\\HR\\Providers\\HRServiceProvider"
    ],
    "files": [],
    "dependencies": []
}
```

---

## Part 10: Integration Points

### With Payroll Module
- Payroll module imports `Employee` from `app/Models`
- Links `Payroll` records to employees via foreign keys
- Uses `SalaryGrade` assigned to employees
- Accesses employee hierarchy for approval workflows

### With Other Modules
- **Inventory Module**: Track equipment assigned to employees
- **StudentMedicalRecords Module**: Link faculty to medical records
- **Financial System**: Link employee expenses/transactions

---

## Part 11: Testing

Create tests in `Modules/HR/tests/`:

- **Feature Tests**:
  - Employee CRUD operations
  - Leave request workflow
  - Approval authorization
  - Filament resource operations
  - Inertia controller responses

- **Unit Tests**:
  - Service classes (EmployeeService, LeaveService, etc.)
  - Policy authorization
  - Attribute calculations

- **Browser Tests** (Pest v4):
  - Login and navigate Inertia admin
  - Create employee via form
  - Request leave
  - View employee profile

---

## Part 12: Implementation Sequence

1. **Phase 1**: Create core models in `app/Models/`
   - Employee model
   - Position model
   - Update Department model
   - Update User model
   - Update Faculty model
   - Create migrations

2. **Phase 2**: Setup HR Module structure
   - Create `Modules/HR/` directory
   - Create module.json and service provider
   - Create module-specific models (LeaveType, etc.)
   - Create migrations for HR tables

3. **Phase 3**: Implement Services & Business Logic
   - EmployeeService
   - LeaveService
   - AttendanceService
   - PerformanceService

4. **Phase 4**: Filament Admin Interface
   - Create all Filament resources
   - Implement authorization policies
   - Create custom actions
   - Test CRUD operations

5. **Phase 5**: Inertia Admin Interface
   - Create React components
   - Create controllers returning Inertia responses
   - Setup routes
   - Test navigation and forms

6. **Phase 6**: Events, Notifications, and Advanced Features
   - Setup events and listeners
   - Implement notifications
   - Add reporting/analytics

7. **Phase 7**: Testing & Documentation
   - Write comprehensive tests
   - Document APIs
   - Create user guides

---

## Important Constraints

1. **Model Separation**: Core HR models MUST be in `app/Models/`, NOT in the module
2. **Module Reusability**: HR module should only handle HR-specific features
3. **Two Admin Interfaces**: Both Filament (`/admin`) and Inertia (admin domain) must work
4. **Clean Dependencies**: Payroll and other modules import from `app/Models`, NOT from `Modules/HR/app/Models`
5. **Type Safety**: Use PHP 8.4 strict typing throughout
6. **No Model Extensions Needed**: Only extend models by adding relationships in service classes or separate concerns
7. **Authorization**: Implement proper Filament policies and Laravel gates
8. **Validation**: Use Form Requests for both Filament and Inertia forms
9. **Testing**: Pest v4 for all tests with browser testing for UI

---

## Success Criteria

✅ Core HR models created in `app/Models/`
✅ HR module created at `Modules/HR/` with complete structure
✅ Both Filament admin and Inertia admin fully functional
✅ Payroll module can import and use Employee model from `app/Models`
✅ Employee CRUD operations working in both admin interfaces
✅ Leave request workflow fully implemented with approvals
✅ Authorization checks working correctly
✅ All relationships properly established
✅ Tests passing for all features
✅ No existing User/Faculty data corrupted
✅ Documentation complete and comprehensive
✅ Follows Laravel 12 and Filament v5 best practices

---

## Implementation Order & Roadmap

**THIS MUST BE IMPLEMENTED FIRST**

### Sequence:
1. **First**: Implement HR Management System (this document)
   - Creates core Employee, Position, Department models in `app/Models/`
   - Creates HR module at `Modules/HR/`
   - Implements leave, attendance, performance features
   - Sets up both Filament and Inertia admin interfaces

2. **Then**: Implement Payroll Management System (`PAYROLL_SYSTEM_PROMPT.md`)
   - Extends Employee model via relationships (no extension needed)
   - Creates Payroll module at `Modules/Payroll/`
   - Links to HR data for employee information
   - Processes payroll calculations and approvals

### Module Dependency Map
```
app/Models/ (Core Foundation)
├── User         (Updated with employee_id)
├── Faculty      (Updated with employee_id)
├── Department   (Updated with head_id, budget)
├── Employee     (NEW - Central HR record) ← Foundation
└── Position     (NEW - Job positions)

        ↓ (extended by)

Modules/HR/ (HR Features)
├── Models/LeaveRequest
├── Models/EmployeeAttendance
├── Models/PerformanceReview
├── Models/Training
├── Models/LeaveType
└── Services (LeaveService, AttendanceService, etc.)

        ↓ (extended by)

Modules/Payroll/ (Payroll Management)
├── Models/Payroll
├── Models/SalaryGrade
├── Models/PayrollDeduction
├── Models/PayrollApproval
└── Services (PayrollCalculationService, etc.)
```

**Clean Dependency**: Core → HR → Payroll (unidirectional)
