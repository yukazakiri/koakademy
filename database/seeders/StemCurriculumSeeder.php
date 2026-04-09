<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use Illuminate\Database\Seeder;

final class StemCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update STEM track
        $stemTrack = ShsTrack::updateOrCreate(
            ['track_name' => 'STEM'],
            ['description' => 'Science, Technology, Engineering, and Mathematics Track']
        );

        // Create or update STEM strand
        $stemStrand = ShsStrand::updateOrCreate(
            [
                'strand_name' => 'STEM',
                'track_id' => $stemTrack->id,
            ],
            ['description' => 'Science, Technology, Engineering, and Mathematics Strand']
        );

        // Define all STEM subjects
        $subjects = [
            // Grade 11 - First Semester
            ['title' => 'Oral Communication', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Komunikasyon at Pananaliksik sa Wika at Kulturang Filipino', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'General Mathematics', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Personal Development', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Earth Science', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 1', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Entrepreneurship', 'grade_year' => 11, 'semester' => 1, 'category' => 'APPLIED'],
            ['title' => 'Pre-Calculus', 'grade_year' => 11, 'semester' => 1, 'category' => 'SPECIALIZED'],
            ['title' => 'General Biology 1', 'grade_year' => 11, 'semester' => 1, 'category' => 'SPECIALIZED'],

            // Grade 11 - Second Semester
            ['title' => 'Reading & Writing', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Pagbasa at Pagsulat ng Iba\'t Ibang Teksto Tungo sa Pananaliksik', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => '21st Century Literature from the Philippines and the World', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Statistics & Probability', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Disaster Readiness and Risk Reduction', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 2', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Practical Research 1', 'grade_year' => 11, 'semester' => 2, 'category' => 'APPLIED'],
            ['title' => 'Basic Calculus', 'grade_year' => 11, 'semester' => 2, 'category' => 'SPECIALIZED'],
            ['title' => 'General Biology 2', 'grade_year' => 11, 'semester' => 2, 'category' => 'SPECIALIZED'],

            // Grade 12 - First Semester
            ['title' => 'Media and Information Literacy', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Introduction to the Philosophy of the Human Person', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Understanding Culture, Society and Politics', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 3', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Filipino sa Piling Larang', 'grade_year' => 12, 'semester' => 1, 'category' => 'APPLIED'],
            ['title' => 'Practical Research 2', 'grade_year' => 12, 'semester' => 1, 'category' => 'APPLIED'],
            ['title' => 'English for Academic and Professional Purposes', 'grade_year' => 12, 'semester' => 1, 'category' => 'APPLIED'],
            ['title' => 'General Physics 1', 'grade_year' => 12, 'semester' => 1, 'category' => 'SPECIALIZED'],
            ['title' => 'General Chemistry 1', 'grade_year' => 12, 'semester' => 1, 'category' => 'SPECIALIZED'],

            // Grade 12 - Second Semester
            ['title' => 'Contemporary Philippine Arts and from the Regions', 'grade_year' => 12, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 4', 'grade_year' => 12, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Empowerment Technologies', 'grade_year' => 12, 'semester' => 2, 'category' => 'APPLIED'],
            ['title' => 'Inquiries, Investigation and Immersion', 'grade_year' => 12, 'semester' => 2, 'category' => 'APPLIED'],
            ['title' => 'Capstone Project', 'grade_year' => 12, 'semester' => 2, 'category' => 'SPECIALIZED'],
            ['title' => 'General Physics 2', 'grade_year' => 12, 'semester' => 2, 'category' => 'SPECIALIZED'],
            ['title' => 'General Chemistry 2', 'grade_year' => 12, 'semester' => 2, 'category' => 'SPECIALIZED'],
        ];

        // Insert all subjects (code will be auto-generated by the model)
        foreach ($subjects as $subject) {
            $category = $subject['category'];

            $subjectData = [
                'title' => $subject['title'],
                'description' => $category.' Subject',
                'grade_year' => $subject['grade_year'],
                'semester' => $subject['semester'],
                'strand_id' => $stemStrand->id,
            ];

            StrandSubject::create($subjectData);
        }

        $this->command->info('STEM curriculum seeded successfully!');
        $this->command->info("- Track: {$stemTrack->track_name} (ID: {$stemTrack->id})");
        $this->command->info("- Strand: {$stemStrand->strand_name} (ID: {$stemStrand->id})");
        $this->command->info('- Subjects created: '.count($subjects));
        $this->command->info('  * CORE subjects: '.count(array_filter($subjects, fn (array $s): bool => $s['category'] === 'CORE')));
        $this->command->info('  * APPLIED subjects: '.count(array_filter($subjects, fn (array $s): bool => $s['category'] === 'APPLIED')));
        $this->command->info('  * SPECIALIZED subjects: '.count(array_filter($subjects, fn (array $s): bool => $s['category'] === 'SPECIALIZED')));
    }
}
