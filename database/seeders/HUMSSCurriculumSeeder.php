<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use Illuminate\Database\Seeder;

final class HUMSSCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update HUMSS track
        $humssTrack = ShsTrack::updateOrCreate(
            ['track_name' => 'HUMSS'],
            ['description' => 'Humanities and Social Sciences Track']
        );

        // Create or update HUMSS strand
        $humssStrand = ShsStrand::updateOrCreate(
            [
                'strand_name' => 'HUMSS',
                'track_id' => $humssTrack->id,
            ],
            ['description' => 'Humanities and Social Sciences Strand']
        );

        // Define all HUMSS subjects
        $subjects = [
            // Grade 11 - First Semester
            ['title' => 'Oral Communication', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Komunikasyon at Pananaliksik sa Wika at Kulturang Filipino', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'General Mathematics', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Earth and Life Science', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => '21st Century Literature from the Philippines and the World', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 1', 'grade_year' => 11, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Disciplines and Ideas in the Social Sciences', 'grade_year' => 11, 'semester' => 1, 'category' => 'SPECIALIZED'],
            ['title' => 'Introduction to World Religions and Belief Systems', 'grade_year' => 11, 'semester' => 1, 'category' => 'SPECIALIZED'],

            // Grade 11 - Second Semester
            ['title' => 'Reading & Writing', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => "Pagbasa at Pagsulat ng Iba't Ibang Teksto Tungo sa Pananaliksik", 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Statistics & Probability', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Understanding Culture, Society and Politics', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 2', 'grade_year' => 11, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Empowerment Technologies', 'grade_year' => 11, 'semester' => 2, 'category' => 'APPLIED'],
            ['title' => 'Practical Research 1', 'grade_year' => 11, 'semester' => 2, 'category' => 'APPLIED'],
            ['title' => 'Creative Writing', 'grade_year' => 11, 'semester' => 2, 'category' => 'SPECIALIZED'],
            ['title' => 'Disciplines and Ideas in the Applied Social Sciences', 'grade_year' => 11, 'semester' => 2, 'category' => 'SPECIALIZED'],

            // Grade 12 - First Semester
            ['title' => 'Contemporary Philippine Arts and from the Regions', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Personal Development', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Physical Science', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 3', 'grade_year' => 12, 'semester' => 1, 'category' => 'CORE'],
            ['title' => 'Filipino sa Piling Larang', 'grade_year' => 12, 'semester' => 1, 'category' => 'APPLIED'],
            ['title' => 'Practical Research 2', 'grade_year' => 12, 'semester' => 1, 'category' => 'APPLIED'],
            ['title' => 'Trends, Networks and Critical Thinking in the 21st Century', 'grade_year' => 12, 'semester' => 1, 'category' => 'SPECIALIZED'],
            ['title' => 'The Philippine Politics and Governance', 'grade_year' => 12, 'semester' => 1, 'category' => 'SPECIALIZED'],
            ['title' => 'Creative Nonfiction: The Literary Essay', 'grade_year' => 12, 'semester' => 1, 'category' => 'SPECIALIZED'],

            // Grade 12 - Second Semester
            ['title' => 'Media & Information Literacy', 'grade_year' => 12, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Introduction to the Philosophy of the Human Person', 'grade_year' => 12, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Physical Education and Health 4', 'grade_year' => 12, 'semester' => 2, 'category' => 'CORE'],
            ['title' => 'Inquiries, Investigation and Immersion', 'grade_year' => 12, 'semester' => 2, 'category' => 'APPLIED'],
            ['title' => 'Indigenous Studies', 'grade_year' => 12, 'semester' => 2, 'category' => 'SPECIALIZED'],
            ['title' => 'Social Science 1 (Sociology, Anthropology, Psychology)', 'grade_year' => 12, 'semester' => 2, 'category' => 'SPECIALIZED'],
        ];

        // Insert all subjects (code will be auto-generated by the model)
        foreach ($subjects as $subject) {
            $category = $subject['category'];

            $subjectData = [
                'title' => $subject['title'],
                'description' => $category.' Subject',
                'grade_year' => $subject['grade_year'],
                'semester' => $subject['semester'],
                'strand_id' => $humssStrand->id,
            ];

            StrandSubject::create($subjectData);
        }

        $this->command->info('HUMSS curriculum seeded successfully!');
        $this->command->info("- Track: {$humssTrack->track_name} (ID: {$humssTrack->id})");
        $this->command->info("- Strand: {$humssStrand->strand_name} (ID: {$humssStrand->id})");
        $this->command->info('- Subjects created: '.count($subjects));
        $this->command->info('  * CORE subjects: '.count(array_filter($subjects, fn (array $s): bool => ($s['category'] ?? '') === 'CORE')));
        $this->command->info('  * APPLIED subjects: '.count(array_filter($subjects, fn (array $s): bool => ($s['category'] ?? '') === 'APPLIED')));
        $this->command->info('  * SPECIALIZED subjects: '.count(array_filter($subjects, fn (array $s): bool => ($s['category'] ?? '') === 'SPECIALIZED')));
    }
}
