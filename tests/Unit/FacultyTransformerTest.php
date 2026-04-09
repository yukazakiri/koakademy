<?php

declare(strict_types=1);

use App\Filament\Resources\Faculties\Api\Transformers\FacultyTransformer;
use App\Models\Classes;
use App\Models\Faculty;

it('transforms faculty resource correctly', function () {
    $faculty = Faculty::factory()->create([
        'faculty_id_number' => '12345',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'middle_name' => 'Smith',
        'email' => 'john.doe@example.com',
        'phone_number' => '123-456-7890',
        'department' => 'Computer Science',
        'status' => 'active',
        'gender' => 'male',
        'age' => 35,
    ]);

    $transformer = new FacultyTransformer($faculty);
    $result = $transformer->toArray(request());

    expect($result)
        ->toHaveKey('id', $faculty->id)
        ->toHaveKey('faculty_id_number', '12345')
        ->toHaveKey('first_name', 'John')
        ->toHaveKey('last_name', 'Doe')
        ->toHaveKey('middle_name', 'Smith')
        ->toHaveKey('full_name', 'Doe, John Smith')
        ->toHaveKey('email', 'john.doe@example.com')
        ->toHaveKey('phone_number', '123-456-7890')
        ->toHaveKey('department', 'Computer Science')
        ->toHaveKey('status', 'active')
        ->toHaveKey('gender', 'male')
        ->toHaveKey('age', 35);
});

it('includes classes relationship when loaded', function () {
    $faculty = Faculty::factory()->create();
    $class1 = Classes::factory()->create([
        'subject_code' => 'CS101',
        'section' => 'A',
        'school_year' => '2023-2024',
        'semester' => 1,
    ]);
    $class2 = Classes::factory()->create([
        'subject_code' => 'CS102',
        'section' => 'B',
        'school_year' => '2023-2024',
        'semester' => 1,
    ]);

    // Manually load the relationship
    $faculty->setRelation('classes', collect([$class1, $class2]));

    $transformer = new FacultyTransformer($faculty);
    $result = $transformer->toArray(request());

    expect($result)
        ->toHaveKey('classes')
        ->and($result['classes'])
        ->toHaveCount(2)
        ->and($result['classes'][0])
        ->toHaveKey('subject_code', 'CS101')
        ->and($result['classes'][1])
        ->toHaveKey('subject_code', 'CS102');
});

it('excludes classes relationship when not loaded', function () {
    $faculty = Faculty::factory()->create();

    $transformer = new FacultyTransformer($faculty);
    $result = $transformer->toArray(request());

    expect($result)
        ->toHaveKey('classes');
});

it('includes classes count when classes are loaded', function () {
    $faculty = Faculty::factory()->create();
    $class1 = Classes::factory()->create();
    $class2 = Classes::factory()->create();

    $faculty->setRelation('classes', collect([$class1, $class2]));

    $transformer = new FacultyTransformer($faculty);
    $result = $transformer->toArray(request());

    expect($result)
        ->toHaveKey('classes_count', 2);
});

it('formats dates correctly', function () {
    $faculty = Faculty::factory()->create([
        'birth_date' => '1990-01-15',
        'created_at' => '2023-01-01 10:00:00',
        'updated_at' => '2023-01-02 15:30:00',
    ]);

    $transformer = new FacultyTransformer($faculty);
    $result = $transformer->toArray(request());

    expect($result)
        ->toHaveKey('birth_date', '1990-01-15')
        ->toHaveKey('created_at', '2023-01-01 10:00:00')
        ->toHaveKey('updated_at', '2023-01-02 15:30:00');
});

it('handles nullable fields correctly', function () {
    $faculty = Faculty::factory()->create([
        'middle_name' => null,
        'phone_number' => null,
        'department' => null,
        'birth_date' => null,
        'address_line1' => null,
        'biography' => null,
        'education' => null,
        'courses_taught' => null,
        'photo_url' => null,
        'gender' => null,
        'age' => null,
    ]);

    $transformer = new FacultyTransformer($faculty);
    $result = $transformer->toArray(request());

    expect($result)
        ->toHaveKey('middle_name', null)
        ->toHaveKey('phone_number', null)
        ->toHaveKey('department', null)
        ->toHaveKey('birth_date', null)
        ->toHaveKey('address_line1', null)
        ->toHaveKey('biography', null)
        ->toHaveKey('education', null)
        ->toHaveKey('courses_taught', null)
        ->toHaveKey('photo_url', null)
        ->toHaveKey('gender', null)
        ->toHaveKey('age', null);
});
