<?php

declare(strict_types=1);

return [
    'admin' => [[
        'id' => 'school-notes',
        'title' => 'School notes',
        'link' => '/administrators/school-notes',
        'section' => 'core',
        'inertiaPage' => 'SchoolNotes/Index',
        'requiredPermission' => 'school-notes.view',
    ]],
];
