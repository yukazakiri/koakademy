# Implementation Roadmap: HR & Payroll System

## Overview

This document provides a unified view of the **HR Management System** and **Payroll Management System** implementations, showing how they work together as an integrated solution.

**Total Phases**: 2 systems across 3 layers

---

## Architecture Layers

### Layer 1: Core Models (app/Models/)
**Purpose**: Shared foundation that both modules depend on
**Location**: Application root `app/Models/`
**Status**: Created by HR System

Models:
- `Employee.php` - Central employee record (NEW)
- `Position.php` - Job positions (NEW)
- `Department.php` - Updated with HR fields
- `User.php` - Updated with employee link
- `Faculty.php` - Updated with employee link

**Characteristics**:
- ✅ Shared across all modules
- ✅ No module-specific logic
- ✅ Pure data models with relationships
- ✅ Importable by any module

---

### Layer 2: HR Module (Modules/HR/)
**Purpose**: HR-specific features and workflows
**Location**: `Modules/HR/`
**Depends On**: Layer 1 (Core Models)

Models (HR-specific):
- `LeaveRequest` - Leave management
- `LeaveType` - Leave classification
- `EmployeeAttendance` - Attendance tracking
- `PerformanceReview` - Performance management
- `Training` - Training assignments
- `EmployeeDocument` - Document storage

Features:
- ✅ Employee CRUD with hierarchy
- ✅ Leave request workflow with approvals
- ✅ Attendance tracking
- ✅ Performance reviews
- ✅ Training management
- ✅ Two admin interfaces (Filament + Inertia)
- ✅ Authorization and policies
- ✅ Events and notifications

---

### Layer 3: Payroll Module (Modules/Payroll/)
**Purpose**: Payroll processing and management
**Location**: `Modules/Payroll/`
**Depends On**: Layer 1 (Core Models) + Layer 2 (HR Module data)

Models (Payroll-specific):
- `Payroll` - Payroll records
- `SalaryGrade` - Salary classifications
- `PayrollPeriod` - Payroll cycles
- `DeductionType` - Deduction definitions
- `PayrollItem` - Line items (allowances)
- `PayrollDeduction` - Applied deductions
- `PayrollApproval` - Approval workflow
- `PayrollTransaction` - Transaction links

Features:
- ✅ Payroll calculation (gross, net, taxes)
- ✅ Multi-level approval workflow
- ✅ Integration with Transaction system
- ✅ Leave balance factoring
- ✅ PDF/Excel reporting
- ✅ Salary slip generation

---

## Implementation Schedule

### Phase 1: HR Management System (Week 1-2)
**Deliverables**: Complete HR module with both admin interfaces

#### Step 1: Core Models (Days 1-2)
- Create Employee model in `app/Models/Employee.php`
- Create Position model in `app/Models/Position.php`
- Update Department model with HR fields
- Update User and Faculty models with employee links
- Create all necessary migrations

#### Step 2: HR Module Structure (Days 2-3)
- Create `Modules/HR/` directory structure
- Setup `module.json` and `HRServiceProvider`
- Create HR-specific models (LeaveType, LeaveRequest, etc.)
- Create all HR module migrations

#### Step 3: Services & Business Logic (Days 3-4)
- Implement `EmployeeService`
- Implement `LeaveService`
- Implement `AttendanceService`
- Implement `PerformanceService`
- Create policies and authorization

#### Step 4: Filament Admin Interface (Days 4-5)
- Create EmployeeResource
- Create DepartmentResource
- Create PositionResource
- Create LeaveRequestResource with workflow
- Create PerformanceReviewResource
- Create TrainingResource
- Setup authorization gates

#### Step 5: Inertia Admin Interface (Days 5-6)
- Create React components for Employees page
- Create components for Departments page
- Create components for Leave Requests page
- Create components for Reports/Analytics
- Setup routes and controllers
- Implement form handling and validation

#### Step 6: Testing & Polish (Days 6-7)
- Write comprehensive Pest tests
- Setup browser tests for UI
- Create seeders with sample data
- Documentation and README
- Performance optimization

**Validation Checklist**:
- [ ] All Employee CRUD operations working
- [ ] Leave request workflow functioning
- [ ] Both admin interfaces accessible and functional
- [ ] Authorization checks working
- [ ] All tests passing
- [ ] Sample data loaded via seeders

---

### Phase 2: Payroll Management System (Week 3-4)
**Deliverables**: Complete Payroll module integrated with HR

#### Step 1: Setup & Models (Days 8-9)
- Create `Modules/Payroll/` directory structure
- Create all Payroll models
- Create all Payroll migrations
- Setup relationships to Employee model

#### Step 2: Payroll Calculation Logic (Days 9-10)
- Implement `PayrollCalculationService`
  - Gross salary calculation
  - Deduction application
  - Net salary calculation
  - Tax calculation based on salary grades
- Setup SalaryGrade and DeductionType seeders

#### Step 3: Approval Workflow (Days 10-11)
- Implement `PayrollApprovalService`
- Create approval status tracking
- Implement multi-level approvals
- Setup approval notifications

#### Step 4: Admin Interfaces (Days 11-12)
- Create PayrollResource in Filament
- Create SalaryGradeResource
- Create DeductionTypeResource
- Create PayrollPeriodResource
- Create PayrollApprovalResource
- Create reporting resources
- Setup authorization for payroll operations

#### Step 5: Integration (Days 12-13)
- Link Payroll to Transaction system
- Create `PayrollTransactionService`
- Implement payroll-to-transaction conversion
- Setup event listeners for HR events
- Implement leave balance factoring

#### Step 6: Reporting & Export (Days 13-14)
- Implement `PayrollExportService`
- Create PDF payroll slips
- Create Excel payroll reports
- Create dashboard with analytics
- Implement batch processing for payroll runs

#### Step 7: Testing & Deployment (Days 14)
- Write comprehensive tests
- Test end-to-end payroll workflow
- Performance testing for large datasets
- Documentation
- Deployment checklist

**Validation Checklist**:
- [ ] Payroll calculations accurate
- [ ] Approval workflow functional
- [ ] Integration with transactions working
- [ ] PDF/Excel exports generating correctly
- [ ] All tests passing
- [ ] Authorization checks in place
- [ ] Existing employee data preserved

---

## Data Flow Examples

### Example 1: Creating a New Employee
```
1. Admin logs into Filament (/admin) or Inertia (admin domain)
2. Navigates to Employees → Create
3. Fills form with employee details
4. System creates Employee record in app/Models/Employee
5. Updates User record if linked
6. Triggers EmployeeCreated event
7. HR module captures event for notifications
8. Payroll module can immediately process this employee for next payroll run
```

### Example 2: Processing Payroll
```
1. Admin creates PayrollPeriod (Jan 1-31)
2. Opens PayrollResource in Filament
3. Clicks "Calculate" to auto-generate payroll for all active employees
   - Fetches Employee records with positions and salary grades
   - Calculates base salary from SalaryGrade
   - Applies SalaryGrade → DeductionType mappings
   - Fetches leave balance from HR module
   - Calculates net = gross - deductions - leave deductions
4. Payroll moves to "Submitted" status
5. Manager approves via LeaveRequestResource
6. Moves to "Approved" status
7. Finance marks as "Paid"
8. System creates Transaction records for each payroll
9. PayrollTransaction links them together
```

### Example 3: Leave Request & Payroll Impact
```
1. Employee requests 5 days leave (via HR module)
2. Manager approves
3. Leave balance updates in LeaveRequest records
4. Payroll cycle starts
5. PayrollCalculationService checks leave balance
6. Applies leave deduction (unpaid leave) to payroll
7. Payroll calculated with leave deduction factored in
```

---

## Database Relationships Map

```
app/Models/ (Shared)
├── User (one-to-one or one-to-many)→ Employee
├── Faculty (one-to-one or one-to-many)→ Employee
├── Department (one-to-many)→ Employee
├── Department (one-to-one)→ Employee (as head)
├── Position (one-to-many)→ Employee
└── Employee (self-referencing)→ Employee (as manager/reports_to)

Modules/HR/
├── Employee ← LeaveRequest (one-to-many)
├── Employee ← EmployeeAttendance (one-to-many)
├── Employee ← PerformanceReview (one-to-many)
├── Employee ← Training (many-to-many via pivot)
├── LeaveType ← LeaveRequest (one-to-many)
└── User ← PerformanceReview (one-to-many, reviewed_by)

Modules/Payroll/
├── Employee ← Payroll (one-to-many)
├── PayrollPeriod ← Payroll (one-to-many)
├── SalaryGrade ← Payroll (one-to-many)
├── Payroll ← PayrollItem (one-to-many)
├── Payroll ← PayrollDeduction (one-to-many)
├── DeductionType ← PayrollDeduction (one-to-many)
├── Payroll ← PayrollApproval (one-to-many)
├── Payroll ← PayrollTransaction (one-to-one)
├── Transaction ← PayrollTransaction (one-to-one)
└── User ← PayrollApproval (one-to-many, approved_by)
```

---

## Admin Interface Access

### Filament Admin (`/admin` path)
- ✅ HR Resources (Employees, Departments, Leave Requests, etc.)
- ✅ Payroll Resources (Payroll, Salary Grades, Deductions, etc.)
- ✅ Authorization via Filament policies
- ✅ Bulk actions and exports
- ✅ Real-time validation

### Inertia Admin (Admin domain)
- ✅ HR Dashboard with employee list, leave requests, reports
- ✅ React-based modern UI
- ✅ RESTful API endpoints for AJAX calls
- ✅ Form handling with validation
- ✅ Analytics and charts

### Two-Panel Design
- **Filament**: Traditional admin panel for power users, bulk operations
- **Inertia**: Modern dashboard for quick decisions, reporting, mobile-friendly

---

## Key Design Decisions

### 1. Core Models in app/Models/
**Why**: Shared foundation ensures all modules reference same employee data
**Benefit**: No duplication, single source of truth

### 2. HR-Specific Models in Modules/HR/
**Why**: Keeps HR-specific concerns (leaves, attendance) separate
**Benefit**: Clean architecture, easier to maintain

### 3. Payroll-Specific Models in Modules/Payroll/
**Why**: Payroll is a specialized domain with unique business logic
**Benefit**: Decoupled from HR, can be extended independently

### 4. Two Admin Interfaces
**Why**: Filament for traditional operations, Inertia for modern workflows
**Benefit**: Flexibility, better UX for different user types

### 5. Minimal Model Extensions
**Why**: Avoids tight coupling and complex inheritance
**Benefit**: Easy to understand, less fragile, easier to test

---

## Success Metrics

After both systems are implemented:

- ✅ Employee records can be created, updated, and managed
- ✅ Leave workflows function end-to-end
- ✅ Payroll calculates accurately for all employee types
- ✅ Approvals work at multiple levels
- ✅ Both admin interfaces are responsive and functional
- ✅ All tests pass (Unit, Feature, Browser)
- ✅ Authorization is properly enforced
- ✅ Data integrity is maintained
- ✅ Performance is acceptable for 1000+ employees
- ✅ System is maintainable and follows Laravel best practices

---

## Team Coordination

### Who Does What

#### Phase 1 (HR System)
- Developer 1: Core models + migrations (Days 1-2)
- Developer 2: HR module + services (Days 3-4)
- Developer 3: Filament interface (Days 4-5)
- Developer 4: Inertia interface (Days 5-6)
- Developer 5: Testing + Polish (Days 6-7)

#### Phase 2 (Payroll System)
- Developer 1: Models + migrations (Days 8-9)
- Developer 2: Calculation services (Days 9-10)
- Developer 3: Approval workflow (Days 10-11)
- Developer 4: Admin resources (Days 11-12)
- Developer 5: Integration + Reporting (Days 12-14)

### Communication Points
- Daily standup on progress
- Code review checkpoints at each step
- Integration testing after each phase
- Final UAT before deployment

---

## Deployment Checklist

### Pre-Deployment
- [ ] All tests passing
- [ ] Code reviewed and approved
- [ ] Database migrations tested on staging
- [ ] Backup of existing data
- [ ] User documentation ready
- [ ] Admin trained on new interfaces

### Deployment Steps
1. Backup production database
2. Run migrations in order (HR first, Payroll second)
3. Seed initial data (salary grades, deductions, leave types)
4. Deploy code
5. Clear caches
6. Verify both admin interfaces load
7. Run smoke tests
8. Monitor for errors

### Post-Deployment
- [ ] Monitor application logs
- [ ] Verify data integrity
- [ ] Get user feedback
- [ ] Plan for iterations
- [ ] Document any issues

---

## Next Steps

1. **Review both prompts** carefully (HR_MANAGEMENT_SYSTEM_PROMPT.md + PAYROLL_SYSTEM_PROMPT.md)
2. **Clarify requirements** with stakeholders
3. **Plan resource allocation** for both phases
4. **Setup development environment** with all dependencies
5. **Begin Phase 1** with HR System implementation
6. **Iterate based on feedback** after Phase 1 completion
7. **Begin Phase 2** with Payroll System implementation
8. **Conduct UAT** with end users
9. **Deploy to production**

---

## References

- [HR Management System Prompt](HR_MANAGEMENT_SYSTEM_PROMPT.md)
- [Payroll Management System Prompt](PAYROLL_SYSTEM_PROMPT.md)
- Laravel 12 Documentation
- Filament v5 Documentation
- Inertia.js Documentation
