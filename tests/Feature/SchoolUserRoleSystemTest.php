<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    // Create test schools
    $this->schoolIT = School::create([
        'name' => 'Test School of Information Technology',
        'code' => 'TSIT',
        'dean_name' => 'Dr. John Smith',
        'dean_email' => 'john.smith@university.edu',
        'is_active' => true,
    ]);

    $this->schoolBusiness = School::create([
        'name' => 'Test School of Business Administration',
        'code' => 'TSBA',
        'dean_name' => 'Dr. Jane Doe',
        'dean_email' => 'jane.doe@university.edu',
        'is_active' => true,
    ]);

    // Create test departments
    $this->deptCS = Department::create([
        'school_id' => $this->schoolIT->id,
        'name' => 'Test Computer Science',
        'code' => 'TCS',
        'is_active' => true,
    ]);

    $this->deptIS = Department::create([
        'school_id' => $this->schoolIT->id,
        'name' => 'Test Information Systems',
        'code' => 'TIS',
        'is_active' => true,
    ]);

    $this->deptMGT = Department::create([
        'school_id' => $this->schoolBusiness->id,
        'name' => 'Test Management',
        'code' => 'TMGT',
        'is_active' => true,
    ]);

    // Create test users with different roles
    $this->developer = User::factory()->create([
        'name' => 'System Developer',
        'email' => 'developer@university.edu',
        'role' => UserRole::Developer,
    ]);

    $this->admin = User::factory()->create([
        'name' => 'System Admin',
        'email' => 'admin@university.edu',
        'role' => UserRole::Admin,
    ]);

    $this->president = User::factory()->create([
        'name' => 'University President',
        'email' => 'president@university.edu',
        'role' => UserRole::President,
    ]);

    $this->deanIT = User::factory()->create([
        'name' => 'IT Dean',
        'email' => 'dean.it@university.edu',
        'role' => UserRole::Dean,
        'school_id' => $this->schoolIT->id,
    ]);

    $this->deptHeadCS = User::factory()->create([
        'name' => 'CS Department Head',
        'email' => 'head.cs@university.edu',
        'role' => UserRole::DepartmentHead,
        'school_id' => $this->schoolIT->id,
        'department_id' => $this->deptCS->id,
    ]);

    $this->professor = User::factory()->create([
        'name' => 'Professor Smith',
        'email' => 'prof.smith@university.edu',
        'role' => UserRole::Professor,
        'school_id' => $this->schoolIT->id,
        'department_id' => $this->deptCS->id,
    ]);

    $this->registrar = User::factory()->create([
        'name' => 'University Registrar',
        'email' => 'registrar@university.edu',
        'role' => UserRole::Registrar,
    ]);

    $this->student = User::factory()->create([
        'name' => 'John Student',
        'email' => 'student@university.edu',
        'role' => UserRole::Student,
        'school_id' => $this->schoolIT->id,
        'department_id' => $this->deptCS->id,
    ]);
});

describe('School Model', function () {
    it('can create a school with proper attributes', function () {
        $school = School::factory()->create([
            'name' => 'Test School',
            'code' => 'TS',
            'description' => 'A test school',
            'dean_name' => 'Dr. Test',
            'dean_email' => 'test@university.edu',
        ]);

        expect($school->name)->toBe('Test School');
        expect($school->code)->toBe('TS');
        expect($school->is_active)->toBe(true);
    });

    it('has proper relationships', function () {
        expect($this->schoolIT->departments)->toHaveCount(2);
        expect($this->schoolIT->users)->toHaveCount(4); // dean, dept head, professor, student
        expect($this->schoolIT->departments->first()->name)->toBe('Test Computer Science');
    });

    it('can get departments count', function () {
        expect($this->schoolIT->getDepartmentsCount())->toBe(2);
        expect($this->schoolIT->getActiveDepartmentsCount())->toBe(2);
    });

    it('can get users count', function () {
        expect($this->schoolIT->getUsersCount())->toBe(4);
    });

    it('can get full name attribute', function () {
        expect($this->schoolIT->getFullNameAttribute())->toBe('Test School of Information Technology (TSIT)');
    });

    it('ensures code is uppercase', function () {
        $school = School::factory()->create(['code' => 'sit']);
        expect($school->code)->toBe('SIT');
    });
});

describe('Department Model', function () {
    it('can create a department with proper attributes', function () {
        $dept = Department::factory()->create([
            'school_id' => $this->schoolIT->id,
            'name' => 'Test Department',
            'code' => 'TD',
        ]);

        expect($dept->name)->toBe('Test Department');
        expect($dept->code)->toBe('TD');
        expect($dept->school_id)->toBe($this->schoolIT->id);
    });

    it('has proper relationships', function () {
        expect($this->deptCS->school->name)->toBe('Test School of Information Technology');
        expect($this->deptCS->users)->toHaveCount(3); // dept head, professor, student
    });

    it('can get full name attribute', function () {
        expect($this->deptCS->getFullNameAttribute())->toBe('Test Computer Science (TCS) - Test School of Information Technology');
    });

    it('can get name with code attribute', function () {
        expect($this->deptCS->getNameWithCodeAttribute())->toBe('Test Computer Science (TCS)');
    });

    it('ensures code is uppercase', function () {
        $dept = Department::factory()->create([
            'school_id' => $this->schoolIT->id,
            'code' => 'cs',
        ]);
        expect($dept->code)->toBe('CS');
    });
});

describe('User Model - Organizational Relationships', function () {
    it('has proper school and department relationships', function () {
        expect($this->deanIT->school->name)->toBe('Test School of Information Technology');
        expect($this->deptHeadCS->department->name)->toBe('Test Computer Science');
    });

    it('can check if user belongs to specific school', function () {
        expect($this->deanIT->belongsToSchool($this->schoolIT))->toBe(true);
        expect($this->deanIT->belongsToSchool($this->schoolBusiness))->toBe(false);
    });

    it('can check if user belongs to specific department', function () {
        expect($this->deptHeadCS->belongsToDepartment($this->deptCS))->toBe(true);
        expect($this->deptHeadCS->belongsToDepartment($this->deptIS))->toBe(false);
    });

    it('gets organizational context string', function () {
        expect($this->deptHeadCS->getOrganizationalContextAttribute())
            ->toBe('Test School of Information Technology > Test Computer Science');

        expect($this->deanIT->getOrganizationalContextAttribute())
            ->toBe('Test School of Information Technology');

        expect($this->admin->getOrganizationalContextAttribute())
            ->toBe('No organizational assignment');
    });
});

describe('User Role - Organizational Management', function () {
    it('allows system admins to manage anyone in organization', function () {
        expect($this->developer->canManageInOrganization($this->deanIT))->toBe(true);
        expect($this->developer->canManageInOrganization($this->student))->toBe(true);
        expect($this->admin->canManageInOrganization($this->professor))->toBe(true);
    });

    it('allows presidents to manage university-wide', function () {
        expect($this->president->canManageInOrganization($this->deanIT))->toBe(true);
        expect($this->president->canManageInOrganization($this->deptHeadCS))->toBe(true);
        expect($this->president->canManageInOrganization($this->professor))->toBe(true);
    });

    it('restricts deans to their school', function () {
        // Dean can manage within their school
        expect($this->deanIT->canManageInOrganization($this->deptHeadCS))->toBe(true);
        expect($this->deanIT->canManageInOrganization($this->professor))->toBe(true);

        // Dean cannot manage outside their school
        $businessProf = User::factory()->create([
            'role' => UserRole::Professor,
            'school_id' => $this->schoolBusiness->id,
        ]);
        expect($this->deanIT->canManageInOrganization($businessProf))->toBe(false);
    });

    it('restricts department heads to their department', function () {
        // Department head can manage within their department
        expect($this->deptHeadCS->canManageInOrganization($this->professor))->toBe(true);

        // Department head cannot manage outside their department
        $isProfessor = User::factory()->create([
            'role' => UserRole::Professor,
            'school_id' => $this->schoolIT->id,
            'department_id' => $this->deptIS->id,
        ]);
        expect($this->deptHeadCS->canManageInOrganization($isProfessor))->toBe(false);
    });
});

describe('School Policies', function () {
    it('allows system admins to view any school', function () {
        expect($this->developer->can('viewAny', School::class))->toBe(true);
        expect($this->developer->can('view', $this->schoolIT))->toBe(true);
        expect($this->developer->can('view', $this->schoolBusiness))->toBe(true);
    });

    it('allows deans to view schools', function () {
        expect($this->deanIT->can('viewAny', School::class))->toBe(true);
        expect($this->deanIT->can('view', $this->schoolIT))->toBe(true);
        expect($this->deanIT->can('view', $this->schoolBusiness))->toBe(true);
    });

    it('allows registrar to view schools', function () {
        expect($this->registrar->can('viewAny', School::class))->toBe(true);
        expect($this->registrar->can('view', $this->schoolIT))->toBe(true);
    });

    it('restricts students from viewing schools', function () {
        expect($this->student->can('viewAny', School::class))->toBe(false);
    });

    it('allows only high-level roles to create schools', function () {
        expect($this->developer->can('create', School::class))->toBe(true);
        expect($this->admin->can('create', School::class))->toBe(true);
        expect($this->president->can('create', School::class))->toBe(true);
        expect($this->deanIT->can('create', School::class))->toBe(false);
        expect($this->deptHeadCS->can('create', School::class))->toBe(false);
    });

    it('allows appropriate roles to update schools', function () {
        expect($this->developer->can('update', $this->schoolIT))->toBe(true);
        expect($this->president->can('update', $this->schoolIT))->toBe(true);
        expect($this->deanIT->can('update', $this->schoolIT))->toBe(true);
        expect($this->deptHeadCS->can('update', $this->schoolIT))->toBe(false);
    });

    it('prevents deletion of schools with users', function () {
        expect($this->developer->can('delete', $this->schoolIT))->toBe(false); // has users

        $emptySchool = School::factory()->create();
        expect($this->developer->can('delete', $emptySchool))->toBe(true);
    });
});

describe('Department Policies', function () {
    it('allows system admins to view any department', function () {
        expect($this->developer->can('viewAny', Department::class))->toBe(true);
        expect($this->developer->can('view', $this->deptCS))->toBe(true);
        expect($this->developer->can('view', $this->deptMGT))->toBe(true);
    });

    it('allows users to view departments in their school', function () {
        expect($this->deanIT->can('view', $this->deptCS))->toBe(true);
        expect($this->deanIT->can('view', $this->deptIS))->toBe(true);
        expect($this->deptHeadCS->can('view', $this->deptCS))->toBe(true);
        expect($this->professor->can('view', $this->deptCS))->toBe(true);
    });

    it('allows appropriate roles to create departments', function () {
        expect($this->developer->can('create', Department::class))->toBe(true);
        expect($this->president->can('create', Department::class))->toBe(true);
        expect($this->deanIT->can('create', Department::class))->toBe(true);
        expect($this->deptHeadCS->can('create', Department::class))->toBe(false);
        expect($this->professor->can('create', Department::class))->toBe(false);
    });

    it('allows appropriate roles to update departments', function () {
        expect($this->developer->can('update', $this->deptCS))->toBe(true);
        expect($this->president->can('update', $this->deptCS))->toBe(true);
        expect($this->deanIT->can('update', $this->deptCS))->toBe(true);
        expect($this->deptHeadCS->can('update', $this->deptCS))->toBe(true);

        // Cannot update department in different school
        $businessUser = User::factory()->create([
            'role' => UserRole::Dean,
            'school_id' => $this->schoolBusiness->id,
        ]);
        expect($businessUser->can('update', $this->deptCS))->toBe(false);
    });

    it('prevents deletion of departments with users', function () {
        expect($this->developer->can('delete', $this->deptCS))->toBe(false); // has users

        $emptyDept = Department::factory()->create([
            'school_id' => $this->schoolIT->id,
        ]);
        expect($this->developer->can('delete', $emptyDept))->toBe(true);
    });
});

describe('Integration Tests', function () {
    it('maintains data integrity when deleting schools', function () {
        $school = School::factory()->create();
        $dept = Department::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'department_id' => $dept->id,
            'role' => UserRole::Professor,
        ]);

        $school->delete();

        $user->refresh();
        expect($user->school_id)->toBeNull();
        expect($user->department_id)->toBeNull();
    });

    it('maintains data integrity when deleting departments', function () {
        $user = User::factory()->create([
            'school_id' => $this->schoolIT->id,
            'department_id' => $this->deptCS->id,
            'role' => UserRole::Professor,
        ]);

        $this->deptCS->delete();

        $user->refresh();
        expect($user->department_id)->toBeNull();
        expect($user->school_id)->toBe($this->schoolIT->id); // School remains
    });

    it('can seed and retrieve organizational data', function () {
        expect(School::count())->toBeGreaterThanOrEqual(2);
        expect(Department::count())->toBeGreaterThanOrEqual(3);

        $sitSchool = School::where('code', 'TSIT')->first();
        expect($sitSchool)->not->toBeNull();
        expect($sitSchool->departments)->toHaveCount(2);
    });

    it('handles hierarchical permissions correctly', function () {
        // Create a complex scenario
        $vicePresident = User::factory()->create([
            'role' => UserRole::VicePresident,
        ]);

        $associateDean = User::factory()->create([
            'role' => UserRole::AssociateDean,
            'school_id' => $this->schoolIT->id,
        ]);

        $programChair = User::factory()->create([
            'role' => UserRole::ProgramChair,
            'school_id' => $this->schoolIT->id,
            'department_id' => $this->deptCS->id,
        ]);

        // Vice President can manage Associate Dean
        expect($vicePresident->canManageInOrganization($associateDean))->toBe(true);

        // Associate Dean can manage Program Chair in same school
        expect($associateDean->canManageInOrganization($programChair))->toBe(true);

        // Program Chair cannot manage Associate Dean (lower hierarchy)
        expect($programChair->canManageInOrganization($associateDean))->toBe(false);
    });
});
