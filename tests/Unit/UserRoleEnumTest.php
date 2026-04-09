<?php

declare(strict_types=1);

use App\Enums\UserRole;

describe('UserRole Enum', function () {
    it('has labels for all cases', function () {
        foreach (UserRole::cases() as $role) {
            expect($role->getLabel())->not->toBeNull();
            expect($role->getLabel())->toBeString();
        }
    });

    it('has colors for all cases', function () {
        foreach (UserRole::cases() as $role) {
            expect($role->getColor())->not->toBeNull();
        }
    });

    it('has hierarchy levels for all cases', function () {
        foreach (UserRole::cases() as $role) {
            expect($role->getHierarchyLevel())->toBeInt();
            expect($role->getHierarchyLevel())->toBeGreaterThan(0);
        }
    });

    it('has manageable roles defined for all administrative cases', function () {
        foreach (UserRole::cases() as $role) {
            if ($role->isAdministrative() || in_array($role, [
                UserRole::Registrar,
                UserRole::AssociateDean,
                UserRole::HRManager,
                UserRole::DepartmentHead,
            ])) {
                expect($role->getManageableRoles())->toBeArray();
            }
        }
    });

    it('correctly identifies administrative roles', function () {
        expect(UserRole::SuperAdmin->isAdministrative())->toBeTrue();
        expect(UserRole::Developer->isAdministrative())->toBeTrue();
        expect(UserRole::Admin->isAdministrative())->toBeTrue();
        expect(UserRole::President->isAdministrative())->toBeTrue();

        expect(UserRole::Student->isAdministrative())->toBeFalse();
        expect(UserRole::Cashier->isAdministrative())->toBeFalse();
    });

    it('correctly identifies student roles', function () {
        expect(UserRole::Student->isStudent())->toBeTrue();
        expect(UserRole::GraduateStudent->isStudent())->toBeTrue();

        expect(UserRole::Admin->isStudent())->toBeFalse();
        expect(UserRole::Professor->isStudent())->toBeFalse();
    });

    it('correctly identifies faculty roles', function () {
        expect(UserRole::Professor->isFaculty())->toBeTrue();
        expect(UserRole::AssociateProfessor->isFaculty())->toBeTrue();
        expect(UserRole::AssistantProfessor->isFaculty())->toBeTrue();
        expect(UserRole::Instructor->isFaculty())->toBeTrue();

        expect(UserRole::Admin->isFaculty())->toBeFalse();
        expect(UserRole::Student->isFaculty())->toBeFalse();
    });
});
