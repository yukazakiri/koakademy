<?php

declare(strict_types=1);

return [
    'accounts' => [
        'student' => [
            'role' => 'student',
            'label' => 'Student',
            'description' => 'Explore schedules, classes, tuition, and student services.',
            'email' => 'john.student@student.koakademy.edu',
        ],
        'faculty' => [
            'role' => 'faculty',
            'label' => 'Faculty',
            'description' => 'Review classes, students, assignments, and faculty tools.',
            'email' => 'j.adams@koakademy.edu',
        ],
        'admin' => [
            'role' => 'admin',
            'label' => 'Admin',
            'description' => 'Preview administrative dashboards and management workflows.',
            'email' => 'admin@koakademy.edu',
        ],
    ],
];
