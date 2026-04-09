<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DocumentLocation;
use App\Models\StudentContact;
use App\Models\StudentEducationInfo;
use App\Models\StudentParentsInfo;
use App\Models\StudentsPersonalInfo;
use Illuminate\Database\Seeder;

final class StudentRelatedTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Document Locations
        $documentLocations = [
            [
                'picture_1x1' => 'documents/students/1/1x1.jpg',
                'picture_2x2' => 'documents/students/1/2x2.jpg',
                'birth_certificate' => 'documents/students/1/birth_cert.pdf',
                'transcript_records' => 'documents/students/1/transcript.pdf',
                'good_moral' => 'documents/students/1/good_moral.pdf',
            ],
            [
                'picture_1x1' => 'documents/students/2/1x1.jpg',
                'picture_2x2' => 'documents/students/2/2x2.jpg',
                'birth_certificate' => 'documents/students/2/birth_cert.pdf',
                'transcript_records' => 'documents/students/2/transcript.pdf',
                'good_moral' => 'documents/students/2/good_moral.pdf',
            ],
            [
                'picture_1x1' => 'documents/students/3/1x1.jpg',
                'picture_2x2' => 'documents/students/3/2x2.jpg',
                'birth_certificate' => 'documents/students/3/birth_cert.pdf',
                'transcript_records' => 'documents/students/3/transcript.pdf',
                'good_moral' => 'documents/students/3/good_moral.pdf',
            ],
        ];

        foreach ($documentLocations as $documentLocation) {
            DocumentLocation::query()->create($documentLocation);
        }

        // Student Contacts
        $studentContacts = [
            [
                'personal_contact' => '09171234567',
                'facebook' => 'john.doe.student',
                'emergency_contact_name' => 'Jane Doe',
                'emergency_contact_phone' => '09181234567',
                'emergency_contact_relationship' => 'Mother',
            ],
            [
                'personal_contact' => '09181234568',
                'facebook' => 'maria.santos.student',
                'emergency_contact_name' => 'Pedro Santos',
                'emergency_contact_phone' => '09191234568',
                'emergency_contact_relationship' => 'Father',
            ],
            [
                'personal_contact' => '09191234569',
                'facebook' => 'carlos.garcia.student',
                'emergency_contact_name' => 'Ana Garcia',
                'emergency_contact_phone' => '09201234569',
                'emergency_contact_relationship' => 'Mother',
            ],
        ];

        foreach ($studentContacts as $studentContact) {
            StudentContact::query()->create($studentContact);
        }

        // Students Personal Info
        $personalInfos = [
            [
                'place_of_birth' => 'Manila, Philippines',
                'citizenship' => 'Filipino',
                'blood_type' => 'O+',
                'height' => 170.50,
                'weight' => 65.00,
                'father_occupation' => 'Engineer',
                'mother_occupation' => 'Teacher',
                'hobbies' => 'Reading, Gaming, Programming',
                'special_skills' => 'Web Development, Graphic Design',
            ],
            [
                'place_of_birth' => 'Quezon City, Philippines',
                'citizenship' => 'Filipino',
                'blood_type' => 'A+',
                'height' => 155.00,
                'weight' => 50.00,
                'father_occupation' => 'Businessman',
                'mother_occupation' => 'Nurse',
                'hobbies' => 'Dancing, Singing, Cooking',
                'special_skills' => 'Event Planning, Public Speaking',
            ],
            [
                'place_of_birth' => 'Makati City, Philippines',
                'citizenship' => 'Filipino',
                'blood_type' => 'B+',
                'height' => 175.00,
                'weight' => 70.00,
                'father_occupation' => 'Doctor',
                'mother_occupation' => 'Lawyer',
                'hobbies' => 'Basketball, Music, Photography',
                'special_skills' => 'Leadership, Problem Solving',
            ],
        ];

        foreach ($personalInfos as $personalInfo) {
            StudentsPersonalInfo::query()->create($personalInfo);
        }

        // Student Parents Info
        $parentsInfos = [
            [
                'father_name' => 'Robert Doe',
                'father_occupation' => 'Civil Engineer',
                'father_contact' => '09161234567',
                'father_email' => 'robert.doe@email.com',
                'mother_name' => 'Jane Doe',
                'mother_occupation' => 'Elementary Teacher',
                'mother_contact' => '09181234567',
                'mother_email' => 'jane.doe@email.com',
                'family_address' => '123 Main Street, Quezon City, Metro Manila',
            ],
            [
                'father_name' => 'Pedro Santos',
                'father_occupation' => 'Business Owner',
                'father_contact' => '09171234568',
                'father_email' => 'pedro.santos@email.com',
                'mother_name' => 'Carmen Santos',
                'mother_occupation' => 'Registered Nurse',
                'mother_contact' => '09191234568',
                'mother_email' => 'carmen.santos@email.com',
                'family_address' => '456 Oak Avenue, Makati City, Metro Manila',
            ],
            [
                'father_name' => 'Dr. Miguel Garcia',
                'father_occupation' => 'Medical Doctor',
                'father_contact' => '09181234569',
                'father_email' => 'miguel.garcia@email.com',
                'mother_name' => 'Atty. Ana Garcia',
                'mother_occupation' => 'Lawyer',
                'mother_contact' => '09201234569',
                'mother_email' => 'ana.garcia@email.com',
                'family_address' => '789 Pine Street, Pasig City, Metro Manila',
            ],
        ];

        foreach ($parentsInfos as $parentInfo) {
            StudentParentsInfo::query()->create($parentInfo);
        }

        // Student Education Info
        $educationInfos = [
            [
                'elementary_school' => 'Manila Elementary School',
                'elementary_year_graduated' => '2015',
                'high_school' => 'Quezon City High School',
                'high_school_year_graduated' => '2019',
                'senior_high_school' => 'KoAkademy Senior High School',
                'senior_high_year_graduated' => '2021',
            ],
            [
                'elementary_school' => 'Makati Elementary School',
                'elementary_year_graduated' => '2014',
                'high_school' => 'Makati Science High School',
                'high_school_year_graduated' => '2018',
                'senior_high_school' => 'KoAkademy Senior High School',
                'senior_high_year_graduated' => '2020',
            ],
            [
                'elementary_school' => 'Pasig Elementary School',
                'elementary_year_graduated' => '2016',
                'high_school' => 'Pasig Catholic High School',
                'high_school_year_graduated' => '2020',
                'senior_high_school' => 'KoAkademy Senior High School',
                'senior_high_year_graduated' => '2022',
            ],
        ];

        foreach ($educationInfos as $educationInfo) {
            StudentEducationInfo::query()->create($educationInfo);
        }

        $this->command->info('Student related tables seeded successfully!');
    }
}
