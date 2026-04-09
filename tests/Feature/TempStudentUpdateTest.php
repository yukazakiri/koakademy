<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\User;

it('tests updating student edge case', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::find(205495) ?? Student::factory()->create(['id' => 205495]);

    $response = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true'])
        ->put(route('administrators.students.update', $student->id), [
            'student_type' => 'college',
            'student_id' => '',
            'lrn' => '',
            'first_name' => 'Loreano Dee Louis',
            'last_name' => 'Lukkanit',
            'middle_name' => '',
            'gender' => 'male',
            'birth_date' => '2005-01-01',
            'age' => 20,
            'email' => '',
            'course_id' => '',
            'academic_year' => '1',
            'shs_strand_id' => '',
            'remarks' => '',
            'personal_contact' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'emergency_contact_address' => '',
            'fathers_name' => '',
            'mothers_name' => '',
            'elementary_school' => '',
            'elementary_graduate_year' => '',
            'elementary_school_address' => '',
            'junior_high_school_name' => '',
            'junior_high_graduation_year' => '',
            'junior_high_school_address' => '',
            'senior_high_name' => '',
            'senior_high_graduate_year' => '',
            'senior_high_address' => '',
            'current_address' => '',
            'permanent_address' => '',
            'birthplace' => '',
            'civil_status' => '',
            'citizenship' => '',
            'religion' => '',
            'weight' => '',
            'height' => '',
            'ethnicity' => '',
            'city_of_origin' => '',
            'province_of_origin' => '',
            'region_of_origin' => '',
            'is_indigenous_person' => false,
            'indigenous_group' => '',
            'status' => 'enrolled',
            'withdrawal_date' => '',
            'withdrawal_reason' => '',
            'attrition_category' => '',
            'dropout_date' => '',
            'scholarship_type' => 'none',
            'scholarship_details' => '',
            'employment_status' => 'not_applicable',
            'employer_name' => '',
            'job_position' => '',
            'employment_date' => '',
            'employed_by_institution' => false,
        ]);

    $response->dumpSession();
    $response->dump();
});
