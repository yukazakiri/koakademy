<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OnboardingFeature;
use Illuminate\Database\Seeder;

final class OnboardingFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'feature_key' => 'onboarding-faculty-dashboard',
                'name' => 'Faculty Dashboard',
                'audience' => 'faculty',
                'summary' => 'Your command center for day-to-day teaching updates.',
                'badge' => 'Dashboard',
                'accent' => 'text-primary',
                'cta_label' => 'Open Dashboard',
                'cta_url' => '/faculty/dashboard',
                'steps' => [
                    [
                        'title' => 'Dashboard',
                        'summary' => 'Check today\'s highlights and stay on top of priorities.',
                        'highlights' => [
                            'Faculty dashboard overview',
                            'Daily priorities and alerts',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/dashboard'],
                            ['label' => 'Menu', 'value' => 'Dashboard'],
                        ],
                        'badge' => 'Dashboard',
                        'accent' => 'text-primary',
                        'icon' => 'sparkles',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-faculty-action-center',
                'name' => 'Action Center',
                'audience' => 'faculty',
                'summary' => 'Review follow-ups and action items in one place.',
                'badge' => 'Action Center',
                'accent' => 'text-emerald-500',
                'cta_label' => 'Open Action Center',
                'cta_url' => '/faculty/action-center',
                'steps' => [
                    [
                        'title' => 'Action Center',
                        'summary' => 'Prioritize tasks, alerts, and upcoming responsibilities.',
                        'highlights' => [
                            'Open tasks and deadlines',
                            'Quick access follow-ups',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/action-center'],
                            ['label' => 'Menu', 'value' => 'Action Center'],
                        ],
                        'badge' => 'Action Center',
                        'accent' => 'text-emerald-500',
                        'icon' => 'zap',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-faculty-classes',
                'name' => 'My Classes',
                'audience' => 'faculty',
                'summary' => 'Manage your classes and student lists.',
                'badge' => 'Classes',
                'accent' => 'text-sky-500',
                'cta_label' => 'Open Classes',
                'cta_url' => '/faculty/classes',
                'steps' => [
                    [
                        'title' => 'My Classes',
                        'summary' => 'Open class rosters, grades, and materials quickly.',
                        'highlights' => [
                            'Class roster management',
                            'Gradebook shortcuts',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/classes'],
                            ['label' => 'Menu', 'value' => 'My Classes'],
                        ],
                        'badge' => 'Classes',
                        'accent' => 'text-sky-500',
                        'icon' => 'clipboard-list',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-faculty-schedule',
                'name' => 'My Schedule',
                'audience' => 'faculty',
                'summary' => 'See your upcoming classes and room assignments.',
                'badge' => 'Schedule',
                'accent' => 'text-indigo-500',
                'cta_label' => 'Open Schedule',
                'cta_url' => '/faculty/schedule',
                'steps' => [
                    [
                        'title' => 'My Schedule',
                        'summary' => 'Plan your week with schedule and room details.',
                        'highlights' => [
                            'Weekly class lineup',
                            'Room and time details',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/schedule'],
                            ['label' => 'Menu', 'value' => 'My Schedule'],
                        ],
                        'badge' => 'Schedule',
                        'accent' => 'text-indigo-500',
                        'icon' => 'calendar-days',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-faculty-toolkit',
                'name' => 'Faculty Toolkit',
                'audience' => 'faculty',
                'summary' => 'Upcoming faculty tools are grouped here as they roll out.',
                'badge' => 'Toolkit',
                'accent' => 'text-amber-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Faculty Toolkit',
                        'summary' => 'Find new faculty tools as they become available.',
                        'highlights' => [
                            'At-risk alerts and insights',
                            'Requests and approvals',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Faculty Toolkit'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-amber-500',
                        'icon' => 'briefcase',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-at-risk-alerts',
                'name' => 'At-Risk Alerts',
                'audience' => 'faculty',
                'summary' => 'Early warning alerts for student performance.',
                'badge' => 'Toolkit',
                'accent' => 'text-rose-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'At-Risk Alerts',
                        'summary' => 'Monitor students who may need extra support.',
                        'highlights' => [
                            'Early warning signals',
                            'Actionable outreach',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'At-Risk Alerts'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-rose-500',
                        'icon' => 'trophy',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-assessments',
                'name' => 'Assessments',
                'audience' => 'faculty',
                'summary' => 'Create quizzes, rubrics, and grading queues.',
                'badge' => 'Toolkit',
                'accent' => 'text-emerald-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Assessments',
                        'summary' => 'Organize assessment workflows in one place.',
                        'highlights' => [
                            'Quizzes and rubrics',
                            'Grading queue overview',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Assessments'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-emerald-500',
                        'icon' => 'clipboard-list',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-inbox',
                'name' => 'Inbox',
                'audience' => 'faculty',
                'summary' => 'Messaging and templates for student communication.',
                'badge' => 'Toolkit',
                'accent' => 'text-sky-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Inbox',
                        'summary' => 'Message students quickly with saved templates.',
                        'highlights' => [
                            'Messaging workflows',
                            'Reusable templates',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Inbox'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-sky-500',
                        'icon' => 'messages-square',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-office-hours',
                'name' => 'Office Hours',
                'audience' => 'faculty',
                'summary' => 'Student appointment booking tools.',
                'badge' => 'Toolkit',
                'accent' => 'text-indigo-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Office Hours',
                        'summary' => 'Plan office hours and manage appointments.',
                        'highlights' => [
                            'Booking preferences',
                            'Appointment visibility',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Office Hours'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-indigo-500',
                        'icon' => 'calendar-days',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-requests-approvals',
                'name' => 'Requests & Approvals',
                'audience' => 'faculty',
                'summary' => 'Excusals and approvals in one workflow.',
                'badge' => 'Toolkit',
                'accent' => 'text-amber-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Requests & Approvals',
                        'summary' => 'Handle requests and approvals quickly.',
                        'highlights' => [
                            'Excusals and make-up requests',
                            'Approval tracking',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Requests & Approvals'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-amber-500',
                        'icon' => 'check-circle-2',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-insights',
                'name' => 'Insights',
                'audience' => 'faculty',
                'summary' => 'Class analytics and trends at a glance.',
                'badge' => 'Toolkit',
                'accent' => 'text-indigo-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Insights',
                        'summary' => 'Discover trends and performance summaries.',
                        'highlights' => [
                            'Class analytics',
                            'Progress trends',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Insights'],
                        ],
                        'badge' => 'Toolkit',
                        'accent' => 'text-indigo-500',
                        'icon' => 'trophy',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-grades',
                'name' => 'Grades & Reports',
                'audience' => 'faculty',
                'summary' => 'Grade management and report exports.',
                'badge' => 'Academic Tools',
                'accent' => 'text-emerald-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Grades & Reports',
                        'summary' => 'Manage grades and generate reports.',
                        'highlights' => [
                            'Grades workflow',
                            'Report exports',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Grades & Reports'],
                        ],
                        'badge' => 'Academic Tools',
                        'accent' => 'text-emerald-500',
                        'icon' => 'clipboard-list',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-attendance',
                'name' => 'Attendance',
                'audience' => 'faculty',
                'summary' => 'Attendance tracking for each class session.',
                'badge' => 'Academic Tools',
                'accent' => 'text-sky-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Attendance',
                        'summary' => 'Track attendance quickly per class session.',
                        'highlights' => [
                            'Session attendance',
                            'Instant updates',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Attendance'],
                        ],
                        'badge' => 'Academic Tools',
                        'accent' => 'text-sky-500',
                        'icon' => 'check-circle-2',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-announcements',
                'name' => 'Announcements',
                'audience' => 'faculty',
                'summary' => 'Post and read announcements quickly.',
                'badge' => 'Announcements',
                'accent' => 'text-amber-500',
                'cta_label' => 'Open Announcements',
                'cta_url' => '/faculty/announcements',
                'steps' => [
                    [
                        'title' => 'Announcements',
                        'summary' => 'Share updates and read new announcements.',
                        'highlights' => [
                            'Announcements feed',
                            'Share updates',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/announcements'],
                            ['label' => 'Menu', 'value' => 'Announcements'],
                        ],
                        'badge' => 'Announcements',
                        'accent' => 'text-amber-500',
                        'icon' => 'messages-square',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-faculty-resources',
                'name' => 'Resources',
                'audience' => 'faculty',
                'summary' => 'Library and teaching resources.',
                'badge' => 'Resources',
                'accent' => 'text-primary',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Resources',
                        'summary' => 'Access teaching resources and library materials.',
                        'highlights' => [
                            'Resource library',
                            'Teaching materials',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Resources'],
                        ],
                        'badge' => 'Resources',
                        'accent' => 'text-primary',
                        'icon' => 'book-open',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-forms',
                'name' => 'Faculty Forms',
                'audience' => 'faculty',
                'summary' => 'Request forms and approvals in one place.',
                'badge' => 'Forms',
                'accent' => 'text-rose-500',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Faculty Forms',
                        'summary' => 'Access leave requests and requisition forms.',
                        'highlights' => [
                            'Leave requests',
                            'Requisitions and forms',
                        ],
                        'stats' => [
                            ['label' => 'Status', 'value' => 'Coming soon'],
                            ['label' => 'Menu', 'value' => 'Faculty Forms'],
                        ],
                        'badge' => 'Forms',
                        'accent' => 'text-rose-500',
                        'icon' => 'clipboard-list',
                        'image' => null,
                    ],
                ],
                'is_active' => false,
            ],
            [
                'feature_key' => 'onboarding-faculty-settings',
                'name' => 'Settings',
                'audience' => 'faculty',
                'summary' => 'Update your profile and preferences.',
                'badge' => 'Settings',
                'accent' => 'text-indigo-500',
                'cta_label' => 'Open Settings',
                'cta_url' => '/faculty/profile',
                'steps' => [
                    [
                        'title' => 'Settings',
                        'summary' => 'Manage your profile and preferences.',
                        'highlights' => [
                            'Profile updates',
                            'Account settings',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/profile'],
                            ['label' => 'Menu', 'value' => 'Settings'],
                        ],
                        'badge' => 'Settings',
                        'accent' => 'text-indigo-500',
                        'icon' => 'check-circle-2',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-faculty-help',
                'name' => 'Help & Support',
                'audience' => 'faculty',
                'summary' => 'Get help or submit support tickets.',
                'badge' => 'Support',
                'accent' => 'text-emerald-500',
                'cta_label' => 'Open Help',
                'cta_url' => '/faculty/help',
                'steps' => [
                    [
                        'title' => 'Help & Support',
                        'summary' => 'Reach support when you need assistance.',
                        'highlights' => [
                            'Help center access',
                            'Ticket submissions',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/faculty/help'],
                            ['label' => 'Menu', 'value' => 'Help & Support'],
                        ],
                        'badge' => 'Support',
                        'accent' => 'text-emerald-500',
                        'icon' => 'users',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-dashboard',
                'name' => 'Student Dashboard',
                'audience' => 'student',
                'summary' => 'Your quick view of classes and account status.',
                'badge' => 'Dashboard',
                'accent' => 'text-primary',
                'cta_label' => 'Open Dashboard',
                'cta_url' => '/student/dashboard',
                'steps' => [
                    [
                        'title' => 'Dashboard',
                        'summary' => 'See your classes, balance, and alerts quickly.',
                        'highlights' => [
                            'Class overview',
                            'Account status',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/dashboard'],
                            ['label' => 'Menu', 'value' => 'Dashboard'],
                        ],
                        'badge' => 'Dashboard',
                        'accent' => 'text-primary',
                        'icon' => 'stars',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-classes',
                'name' => 'My Academics',
                'audience' => 'student',
                'summary' => 'Review your enrolled subjects and academics.',
                'badge' => 'Academics',
                'accent' => 'text-emerald-500',
                'cta_label' => 'Open My Academics',
                'cta_url' => '/student/classes',
                'steps' => [
                    [
                        'title' => 'My Academics',
                        'summary' => 'Track your subjects and class details.',
                        'highlights' => [
                            'Subject list',
                            'Class detail views',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/classes'],
                            ['label' => 'Menu', 'value' => 'My Academics'],
                        ],
                        'badge' => 'Academics',
                        'accent' => 'text-emerald-500',
                        'icon' => 'graduation-cap',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-tuition',
                'name' => 'Tuition & Fees',
                'audience' => 'student',
                'summary' => 'Keep an eye on balances and statements.',
                'badge' => 'Finances',
                'accent' => 'text-sky-500',
                'cta_label' => 'Open Tuition',
                'cta_url' => '/student/tuition',
                'steps' => [
                    [
                        'title' => 'Tuition & Fees',
                        'summary' => 'Review balances and statement details.',
                        'highlights' => [
                            'Balance snapshot',
                            'Statement updates',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/tuition'],
                            ['label' => 'Menu', 'value' => 'Tuition & Fees'],
                        ],
                        'badge' => 'Finances',
                        'accent' => 'text-sky-500',
                        'icon' => 'book-open',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-schedule',
                'name' => 'Class Schedule',
                'audience' => 'student',
                'summary' => 'View weekly schedules and daily timing.',
                'badge' => 'Schedule',
                'accent' => 'text-indigo-500',
                'cta_label' => 'Open Schedule',
                'cta_url' => '/student/schedule',
                'steps' => [
                    [
                        'title' => 'Class Schedule',
                        'summary' => 'Plan your week with class schedule details.',
                        'highlights' => [
                            'Weekly schedule',
                            'Room and instructor info',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/schedule'],
                            ['label' => 'Menu', 'value' => 'Class Schedule'],
                        ],
                        'badge' => 'Schedule',
                        'accent' => 'text-indigo-500',
                        'icon' => 'calendar-days',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-announcements',
                'name' => 'Announcements',
                'audience' => 'student',
                'summary' => 'Read important campus updates and notices.',
                'badge' => 'Announcements',
                'accent' => 'text-amber-500',
                'cta_label' => 'Open Announcements',
                'cta_url' => '/student/announcements',
                'steps' => [
                    [
                        'title' => 'Announcements',
                        'summary' => 'Stay up to date with school announcements.',
                        'highlights' => [
                            'Campus-wide updates',
                            'Event notices',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/announcements'],
                            ['label' => 'Menu', 'value' => 'Announcements'],
                        ],
                        'badge' => 'Announcements',
                        'accent' => 'text-amber-500',
                        'icon' => 'messages-square',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-settings',
                'name' => 'Settings',
                'audience' => 'student',
                'summary' => 'Update your profile and account preferences.',
                'badge' => 'Settings',
                'accent' => 'text-rose-500',
                'cta_label' => 'Open Settings',
                'cta_url' => '/student/profile',
                'steps' => [
                    [
                        'title' => 'Settings',
                        'summary' => 'Manage your profile and preferences.',
                        'highlights' => [
                            'Profile updates',
                            'Account preferences',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/profile'],
                            ['label' => 'Menu', 'value' => 'Settings'],
                        ],
                        'badge' => 'Settings',
                        'accent' => 'text-rose-500',
                        'icon' => 'check-circle-2',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'onboarding-student-help',
                'name' => 'Help & Support',
                'audience' => 'student',
                'summary' => 'Get help and submit support tickets.',
                'badge' => 'Support',
                'accent' => 'text-emerald-500',
                'cta_label' => 'Open Help',
                'cta_url' => '/student/help',
                'steps' => [
                    [
                        'title' => 'Help & Support',
                        'summary' => 'Reach support whenever you need help.',
                        'highlights' => [
                            'Help center access',
                            'Support requests',
                        ],
                        'stats' => [
                            ['label' => 'Route', 'value' => '/student/help'],
                            ['label' => 'Menu', 'value' => 'Help & Support'],
                        ],
                        'badge' => 'Support',
                        'accent' => 'text-emerald-500',
                        'icon' => 'users',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($features as $feature) {
            $featureKey = $feature['feature_key'];
            $attributes = $feature;
            unset($attributes['feature_key']);

            OnboardingFeature::query()->firstOrCreate(['feature_key' => $featureKey], $attributes);

            if ($feature['is_active']) {
                \Laravel\Pennant\Feature::activateForEveryone($featureKey);
            } else {
                \Laravel\Pennant\Feature::deactivateForEveryone($featureKey);
            }
        }
    }
}
