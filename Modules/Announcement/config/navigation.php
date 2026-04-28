<?php

declare(strict_types=1);

return [
    'admin' => [
        [
            'id' => 'admin-announcements',
            'title' => 'Announcements',
            'link' => '/administrators/announcements',
            'inertiaPage' => 'Announcement/Index',
            'section' => 'core',
            'icon' => 'news',
            'requiredPermission' => [
                'ViewAny:Announcement',
                'View:Announcement',
                'view_announcements',
                'manage_announcements',
            ],
        ],
    ],
];
