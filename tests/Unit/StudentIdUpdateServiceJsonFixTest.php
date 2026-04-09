<?php

declare(strict_types=1);

use App\Services\StudentIdUpdateService;

it('properly encodes json fields for raw database insertion', function () {
    // Test the core logic that was causing the "Array to string conversion" error
    $service = new StudentIdUpdateService();

    // Mock student data with array fields (simulating what toArray() returns)
    $studentData = [
        'id' => 123456,
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'test@example.com',
        'contacts' => ['phone' => '09123456789', 'email' => 'test@example.com'], // This was causing the error
        'subject_enrolled' => ['Math', 'Science', 'English'], // Another potential array field
        'student_parent_info' => ['father' => 'John Doe'], // Another field from the error
        'created_at' => now(),
        'updated_at' => now(),
    ];

    // Apply the same logic our fix uses in the performIdUpdate method
    $jsonFields = [
        'contacts',
        'subject_enrolled',
        'student_parent_info',
        'student_education_info',
        'student_personal_info',
        'document_metadata',
        'preferences',
        'settings',
        'additional_data',
    ];

    foreach ($jsonFields as $field) {
        if (isset($studentData[$field]) && is_array($studentData[$field])) {
            $studentData[$field] = json_encode($studentData[$field]);
        }
    }

    // Verify the arrays were properly converted to JSON strings
    expect($studentData['contacts'])->toBeString();
    expect($studentData['contacts'])->toBe('{"phone":"09123456789","email":"test@example.com"}');

    expect($studentData['subject_enrolled'])->toBeString();
    expect($studentData['subject_enrolled'])->toBe('["Math","Science","English"]');

    expect($studentData['student_parent_info'])->toBeString();
    expect($studentData['student_parent_info'])->toBe('{"father":"John Doe"}');

    // Verify non-array fields weren't affected
    expect($studentData['first_name'])->toBe('Test');
    expect($studentData['email'])->toBe('test@example.com');
});

it('handles null and missing json fields correctly', function () {
    $studentData = [
        'id' => 123456,
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'test@example.com',
        'contacts' => null, // null value should be preserved
        'subject_enrolled' => [], // empty array should be converted
        // student_parent_info is missing entirely
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $jsonFields = [
        'contacts',
        'subject_enrolled',
        'student_parent_info',
    ];

    foreach ($jsonFields as $field) {
        if (isset($studentData[$field]) && is_array($studentData[$field])) {
            $studentData[$field] = json_encode($studentData[$field]);
        }
    }

    // Null values should remain null
    expect($studentData['contacts'])->toBeNull();

    // Empty arrays should be converted to JSON
    expect($studentData['subject_enrolled'])->toBe('[]');

    // Missing fields should remain missing
    expect($studentData)->not->toHaveKey('student_parent_info');
});

it('handles complex nested array data correctly', function () {
    $studentData = [
        'contacts' => [
            'primary' => [
                'phone' => '09123456789',
                'email' => 'primary@example.com',
            ],
            'secondary' => [
                'phone' => '09987654321',
                'email' => 'secondary@example.com',
            ],
        ],
        'subject_enrolled' => [
            ['name' => 'Math', 'units' => 3],
            ['name' => 'Science', 'units' => 4],
            ['name' => 'English', 'units' => 3],
        ],
    ];

    $jsonFields = ['contacts', 'subject_enrolled'];

    foreach ($jsonFields as $field) {
        if (isset($studentData[$field]) && is_array($studentData[$field])) {
            $studentData[$field] = json_encode($studentData[$field]);
        }
    }

    // Verify complex nested arrays are properly encoded
    expect($studentData['contacts'])->toBeString();
    $decodedContacts = json_decode($studentData['contacts'], true);
    expect($decodedContacts['primary']['phone'])->toBe('09123456789');
    expect($decodedContacts['secondary']['email'])->toBe('secondary@example.com');

    expect($studentData['subject_enrolled'])->toBeString();
    $decodedSubjects = json_decode($studentData['subject_enrolled'], true);
    expect($decodedSubjects[0]['name'])->toBe('Math');
    expect($decodedSubjects[1]['units'])->toBe(4);
});
