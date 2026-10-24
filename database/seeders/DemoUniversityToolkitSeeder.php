<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoUniversityToolkitSeeder extends Seeder
{
    private const SCHOOL_ID = 8;

    public function run(): void
    {
        $this->command?->info('Seeding faculty toolkit demo data...');

        $now = now();
        $faculty = DB::table('faculty')->where('email', 'j.adams@koakademy.edu')->first();
        if (! $faculty) {
            $this->command?->warn('j.adams faculty record not found; skipping toolkit seeder.');

            return;
        }
        $facultyId = $faculty->id;

        $classRows = DB::table('classes')
            ->where('faculty_id', $facultyId)
            ->whereNull('deleted_at')
            ->where('school_year', '2026 - 2027')
            ->where('semester', '1')
            ->orderBy('id')
            ->get(['id', 'subject_code', 'section']);

        if ($classRows->isEmpty()) {
            $this->command?->warn('No current classes for j.adams; skipping toolkit seeder.');

            return;
        }

        $classIds = $classRows->pluck('id')->all();

        // Clean previous demo data
        DB::table('assessment_rubrics')->where('faculty_id', $facultyId)->delete();
        DB::table('alert_actions')->where('faculty_id', $facultyId)->delete();
        DB::table('inbox_templates')->where('faculty_id', $facultyId)->delete();
        $threadIds = DB::table('inbox_threads')->where('faculty_id', $facultyId)->pluck('id');
        if ($threadIds->isNotEmpty()) {
            DB::table('inbox_messages')->whereIn('thread_id', $threadIds)->delete();
            DB::table('inbox_threads')->where('faculty_id', $facultyId)->delete();
        }
        $slotIds = DB::table('office_hour_slots')->where('faculty_id', $facultyId)->pluck('id');
        if ($slotIds->isNotEmpty()) {
            DB::table('office_hour_bookings')->whereIn('slot_id', $slotIds)->delete();
            DB::table('office_hour_slots')->where('faculty_id', $facultyId)->delete();
        }
        DB::table('faculty_requests')->where('faculty_id', $facultyId)->delete();
        DB::table('class_resources')->where('faculty_id', $facultyId)->whereIn('class_id', $classIds)->delete();

        // --- Rubric library ---
        $rubrics = [
            [
                'title' => 'Essay Grading Rubric',
                'description' => 'Standard rubric for written essays and reaction papers.',
                'criteria' => [
                    ['title' => 'Content', 'description' => 'Depth and accuracy of ideas.', 'points' => 40, 'levels' => []],
                    ['title' => 'Organization', 'description' => 'Structure and flow.', 'points' => 30, 'levels' => []],
                    ['title' => 'Mechanics', 'description' => 'Grammar and formatting.', 'points' => 30, 'levels' => []],
                ],
            ],
            [
                'title' => 'Lab Report Rubric',
                'description' => 'For laboratory experiment write-ups.',
                'criteria' => [
                    ['title' => 'Methodology', 'description' => null, 'points' => 30, 'levels' => []],
                    ['title' => 'Data & Analysis', 'description' => null, 'points' => 40, 'levels' => []],
                    ['title' => 'Conclusion', 'description' => null, 'points' => 30, 'levels' => []],
                ],
            ],
            [
                'title' => 'Recitation / Participation',
                'description' => 'Weekly participation scoring.',
                'criteria' => [
                    ['title' => 'Engagement', 'description' => null, 'points' => 50, 'levels' => []],
                    ['title' => 'Accuracy', 'description' => null, 'points' => 50, 'levels' => []],
                ],
            ],
        ];
        foreach ($rubrics as $rubric) {
            DB::table('assessment_rubrics')->insert([
                'faculty_id' => $facultyId,
                'title' => $rubric['title'],
                'description' => $rubric['description'],
                'criteria' => json_encode($rubric['criteria']),
                'is_shared' => false,
                'school_id' => self::SCHOOL_ID,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Alert actions (outreach log) ---
        $atRiskStudents = DB::table('class_enrollments')
            ->whereIn('class_id', $classIds)
            ->whereNotNull('total_average')
            ->where('total_average', '<', 78)
            ->orderBy('total_average')
            ->limit(6)
            ->get(['student_id', 'class_id']);

        $actions = ['contacted', 'meeting', 'counseling'];
        $notes = [
            'Discussed missed requirements and set a catch-up plan.',
            'Scheduled a one-on-one consultation next week.',
            'Referred to guidance office for follow-up.',
            'Advised student on study strategies; will monitor next quiz.',
        ];
        foreach ($atRiskStudents as $i => $row) {
            DB::table('alert_actions')->insert([
                'class_id' => $row->class_id,
                'student_id' => $row->student_id,
                'faculty_id' => $facultyId,
                'action' => $actions[$i % count($actions)],
                'note' => $notes[$i % count($notes)],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Inbox ---
        $students = DB::table('class_enrollments')
            ->whereIn('class_id', $classIds)
            ->join('students', 'students.id', '=', 'class_enrollments.student_id')
            ->whereIn('class_enrollments.id', function ($q) use ($classIds) {
                $q->selectRaw('MIN(ce.id)')->from('class_enrollments as ce')->whereIn('ce.class_id', $classIds)->groupBy('ce.student_id');
            })
            ->get(['students.id', 'students.first_name', 'students.last_name'])
            ->take(5);

        $threads = [
            ['subject' => 'Question about last quiz', 'body' => 'Good day! I would like to ask about my score in the last quiz.', 'reply' => 'Hi! I checked your paper — you got 38/50. Let\'s talk after class on Wednesday.'],
            ['subject' => 'Missed assignment submission', 'body' => 'I was sick last week and missed the deadline. May I still submit?', 'reply' => 'Yes, submit within 3 days and I will count it with a small deduction.'],
            ['subject' => 'Grade clarification request', 'body' => 'Can you walk me through how my project was scored?', 'reply' => 'Sure, I used the class rubric. Come to office hours and we will go over it.'],
        ];
        $templates = [
            ['name' => 'Deadline reminder', 'subject' => 'Reminder: {assignment} due soon', 'body' => 'Dear class, please remember that the assignment is due soon. Late submissions will receive deductions per class policy.'],
            ['name' => 'Grade concern follow-up', 'subject' => 'Follow-up on your grade', 'body' => 'Hi, I noticed your recent scores are below expectations. Please see me during office hours so we can plan how to improve.'],
            ['name' => 'Excused absence confirmation', 'subject' => 'Excused absence — make-up plan', 'body' => 'Your absence has been noted as excused. Please coordinate with me for the make-up requirements within one week.'],
        ];

        foreach ($students as $i => $student) {
            if (! isset($threads[$i])) {
                break;
            }
            $threadId = DB::table('inbox_threads')->insertGetId([
                'faculty_id' => $facultyId,
                'class_id' => $classIds[$i % count($classIds)],
                'student_id' => $student->id,
                'subject' => $threads[$i]['subject'],
                'status' => 'open',
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('inbox_messages')->insert([
                'thread_id' => $threadId,
                'sender_type' => 'student',
                'sender_id' => $student->id,
                'body' => $threads[$i]['body'],
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('inbox_messages')->insert([
                'thread_id' => $threadId,
                'sender_type' => 'faculty',
                'sender_id' => $facultyId,
                'body' => $threads[$i]['reply'],
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach ($templates as $template) {
            DB::table('inbox_templates')->insert([
                'faculty_id' => $facultyId,
                'name' => $template['name'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Office hours ---
        $slots = [
            ['day' => 'Monday', 'start' => '13:00:00', 'end' => '15:00:00', 'mode' => 'in_person', 'location' => 'Faculty Room 204', 'capacity' => 2],
            ['day' => 'Wednesday', 'start' => '10:00:00', 'end' => '12:00:00', 'mode' => 'online', 'location' => 'https://meet.google.com/demo-office-hours', 'capacity' => 3],
            ['day' => 'Friday', 'start' => '14:00:00', 'end' => '16:00:00', 'mode' => 'in_person', 'location' => 'Faculty Room 204', 'capacity' => 2],
        ];
        $slotIds = [];
        foreach ($slots as $slot) {
            $slotIds[] = DB::table('office_hour_slots')->insertGetId([
                'faculty_id' => $facultyId,
                'day_of_week' => $slot['day'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'mode' => $slot['mode'],
                'location' => $slot['location'],
                'capacity' => $slot['capacity'],
                'is_active' => 1,
                'school_id' => self::SCHOOL_ID,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $bookingTopics = ['Grade clarification', 'Project consultation', 'Thesis advising question', 'Catch-up plan'];
        foreach (array_slice($students->all(), 0, 3) as $i => $student) {
            DB::table('office_hour_bookings')->insert([
                'slot_id' => $slotIds[$i % count($slotIds)],
                'student_id' => $student->id,
                'class_id' => $classIds[$i % count($classIds)],
                'topic' => $bookingTopics[$i % count($bookingTopics)],
                'status' => 'booked',
                'booked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Requests & approvals ---
        $requests = [
            ['type' => 'leave', 'title' => 'Personal leave — Oct 30', 'details' => 'Family matter; class coverage arranged with co-faculty.', 'status' => 'pending', 'steps' => [['name' => 'Department Head', 'status' => 'pending'], ['name' => 'Dean', 'status' => 'pending']], 'current' => 0],
            ['type' => 'make_up_exam', 'title' => 'Make-up exam for IT121 section A', 'details' => 'Three students missed the midterm due to school-sanctioned activity.', 'status' => 'approved', 'steps' => [['name' => 'Department Head', 'status' => 'approved']], 'current' => 1],
            ['type' => 'grade_change', 'title' => 'Grade correction — encoding error', 'details' => 'Encoded 86 instead of 68; requesting correction before posting.', 'status' => 'pending', 'steps' => [['name' => 'Department Head', 'status' => 'approved'], ['name' => 'Registrar', 'status' => 'pending']], 'current' => 1],
            ['type' => 'supply', 'title' => 'Laboratory supplies requisition', 'details' => 'Markers, bond paper, and USB cables for the lab room.', 'status' => 'rejected', 'steps' => [['name' => 'Department Head', 'status' => 'approved'], ['name' => 'Admin', 'status' => 'rejected']], 'current' => 2],
        ];
        foreach ($requests as $request) {
            DB::table('faculty_requests')->insert([
                'faculty_id' => $facultyId,
                'type' => $request['type'],
                'title' => $request['title'],
                'details' => $request['details'],
                'steps' => json_encode($request['steps']),
                'current_step' => $request['current'],
                'status' => $request['status'],
                'decision_note' => $request['status'] === 'rejected' ? 'Budget cycle closed; resubmit next semester.' : null,
                'decided_at' => $request['status'] === 'pending' ? null : $now,
                'school_id' => self::SCHOOL_ID,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Class resources ---
        $resources = [
            ['type' => 'link', 'title' => 'Course syllabus (PDF)', 'description' => 'Full syllabus with grading breakdown.', 'url' => 'https://example.edu/syllabus/'.($classRows[0]->subject_code ?? 'course'), 'visibility' => 'students'],
            ['type' => 'link', 'title' => 'Programming practice set', 'description' => 'Extra exercises with solutions.', 'url' => 'https://example.edu/practice', 'visibility' => 'students'],
            ['type' => 'link', 'title' => 'Department grading policy', 'description' => 'Internal reference for faculty.', 'url' => 'https://example.edu/policy', 'visibility' => 'faculty_only'],
            ['type' => 'file', 'title' => 'Week 1 lecture slides', 'description' => 'Introductory slides.', 'url' => 'class-resources/'.($classIds[0]).'/week-1-lecture-slides.pdf', 'visibility' => 'students'],
        ];
        foreach ($resources as $i => $resource) {
            DB::table('class_resources')->insert([
                'class_id' => $classIds[$i % count($classIds)],
                'faculty_id' => $facultyId,
                'title' => $resource['title'],
                'description' => $resource['description'],
                'type' => $resource['type'],
                'url' => $resource['url'],
                'visibility' => $resource['visibility'],
                'downloads' => random_int(3, 40),
                'school_id' => self::SCHOOL_ID,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info('Toolkit demo data seeded: rubrics, outreach log, inbox, office hours, requests, resources.');
    }
}
