<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CurriculumFramework;

/**
 * Static, research-backed catalog of Philippine curriculum frameworks.
 *
 * Data was compiled against official issuances from CHED, DepEd, and TESDA
 * (research date: 2026-08-13). Every entry carries a `reference` (the governing
 * issuance), a `source_url`, and a `verified` flag: entries whose issuance
 * number could not be confirmed against the regulator's website during
 * research are marked `verified => false` and rendered with a "verify" hint
 * in the setup wizard.
 *
 * Update this file when regulators issue new curriculum orders (e.g., new
 * CHED CMOs, revised TESDA Training Regulations, or the finalized revised
 * SHS curriculum).
 */
final class PhilippineCurriculumCatalog
{
    /**
     * The date the catalog data was last researched.
     */
    public const string AS_OF = '2026-08-13';

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     department_code: string,
     *     department_name: string,
     *     programs: array<int, array<string, mixed>>
     * }>
     */
    public static function chedClusters(): array
    {
        return [
            [
                'key' => 'ict',
                'label' => 'IT & Computing',
                'department_code' => 'IT',
                'department_name' => 'Information Technology',
                'programs' => [
                    self::chedProgram('BSIT', 'Bachelor of Science in Information Technology', 'Software development, networking, and system administration.', 120, 4, 'bachelor', 'CMO 25 s. 2015 (ICT PSGs)', true),
                    self::chedProgram('BSCS', 'Bachelor of Science in Computer Science', 'Theoretical foundations of computing and software engineering.', 120, 4, 'bachelor', 'CMO 25 s. 2015 (ICT PSGs)', true),
                    self::chedProgram('BSIS', 'Bachelor of Science in Information Systems', 'Business-aligned design and management of information systems.', 120, 4, 'bachelor', 'CMO 25 s. 2015 (ICT PSGs)', true),
                    self::chedProgram('BSEMC', 'Bachelor of Science in Entertainment and Multimedia Computing', 'Game development, animation, and multimedia applications.', 120, 4, 'bachelor', 'CHED PSG for BS EMC', false),
                    self::chedProgram('ACT', 'Associate in Computer Technology', 'Two-year ladderized computing program (PQF Level 5).', 72, 2, 'associate', 'CHED PSG for Associate Programs', false),
                    self::chedProgram('DIT', 'Diploma in Information Technology', 'Practice-oriented computing diploma (PQF Level 5).', 90, 2, 'diploma', 'CHED Diploma Programs (PQF Level 5)', false),
                ],
            ],
            [
                'key' => 'business',
                'label' => 'Business, Accountancy & Management',
                'department_code' => 'MGT',
                'department_name' => 'Business Administration',
                'programs' => [
                    self::chedProgram('BSA', 'Bachelor of Science in Accountancy', 'Financial accounting, auditing, and taxation.', 160, 4, 'bachelor', 'CHED PSG for BS Accountancy', false, 'ACC', 'Accountancy'),
                    self::chedProgram('BSBA', 'Bachelor of Science in Business Administration', 'Majors in Marketing, Financial, Human Resource, and Operations Management.', 120, 4, 'bachelor', 'CMO 17 s. 2017 (BSBA PSG)', true),
                    self::chedProgram('BSEntrep', 'Bachelor of Science in Entrepreneurship', 'New venture creation and small business management.', 120, 4, 'bachelor', 'CHED PSG for BS Entrepreneurship', false),
                ],
            ],
            [
                'key' => 'engineering',
                'label' => 'Engineering & Technology',
                'department_code' => 'ENG',
                'department_name' => 'Engineering',
                'programs' => [
                    self::chedProgram('BSCE', 'Bachelor of Science in Civil Engineering', 'Structural, transportation, and geotechnical engineering.', 165, 4, 'bachelor', 'CHED PSG (Engineering Series, 2017)', false),
                    self::chedProgram('BSME', 'Bachelor of Science in Mechanical Engineering', 'Machinery, energy systems, and manufacturing.', 165, 4, 'bachelor', 'CHED PSG (Engineering Series, 2017)', false),
                    self::chedProgram('BSEE', 'Bachelor of Science in Electrical Engineering', 'Power systems, control, and electrical design.', 165, 4, 'bachelor', 'CHED PSG (Engineering Series, 2017)', false),
                    self::chedProgram('BSECE', 'Bachelor of Science in Electronics Engineering', 'Electronics, communications, and embedded systems.', 165, 4, 'bachelor', 'CHED PSG (Engineering Series, 2017)', false),
                    self::chedProgram('BSCpE', 'Bachelor of Science in Computer Engineering', 'Hardware-software integration and digital systems.', 165, 4, 'bachelor', 'CHED PSG (Engineering Series, 2017)', false),
                    self::chedProgram('BSIE', 'Bachelor of Science in Industrial Engineering', 'Process optimization, ergonomics, and operations research.', 165, 4, 'bachelor', 'CHED PSG (Engineering Series, 2017)', false),
                    self::chedProgram('BSArch', 'Bachelor of Science in Architecture', 'Architectural design, planning, and construction practice.', 160, 5, 'bachelor', 'CHED PSG for Architecture', false),
                ],
            ],
            [
                'key' => 'hospitality',
                'label' => 'Hospitality & Tourism',
                'department_code' => 'HM',
                'department_name' => 'Hospitality Management',
                'programs' => [
                    self::chedProgram('BSHRM', 'Bachelor of Science in Hotel and Restaurant Management', 'Hotel and restaurant operations and service management.', 120, 4, 'bachelor', 'CMO 62 s. 2017 (HRM/Tourism PSGs)', true),
                    self::chedProgram('BSHM', 'Bachelor of Science in Hospitality Management', 'Comprehensive hospitality operations and guest experience.', 120, 4, 'bachelor', 'CMO 62 s. 2017 (HRM/Tourism PSGs)', true),
                    self::chedProgram('BSTM', 'Bachelor of Science in Tourism Management', 'Tourism planning, operations, and destination management.', 120, 4, 'bachelor', 'CMO 62 s. 2017 (HRM/Tourism PSGs)', true),
                    self::chedProgram('BSCA', 'Bachelor of Science in Culinary Arts', 'Professional culinary arts and food service management.', 120, 4, 'bachelor', 'CHED PSG for Culinary Arts', false),
                ],
            ],
            [
                'key' => 'health',
                'label' => 'Health & Allied Sciences',
                'department_code' => 'HLTH',
                'department_name' => 'Health Sciences',
                'programs' => [
                    self::chedProgram('BSN', 'Bachelor of Science in Nursing', 'Professional nursing practice and community health.', 150, 4, 'bachelor', 'CMO 15 s. 2017 (BSN PSG)', true),
                    self::chedProgram('BSMLS', 'Bachelor of Science in Medical Laboratory Science', 'Clinical laboratory diagnostics and biosafety.', 150, 4, 'bachelor', 'CHED PSG for Medical Laboratory Science', false),
                    self::chedProgram('BSP', 'Bachelor of Science in Pharmacy', 'Pharmaceutical sciences and clinical pharmacy.', 150, 4, 'bachelor', 'CHED PSG for Pharmacy', false),
                    self::chedProgram('BSPT', 'Bachelor of Science in Physical Therapy', 'Rehabilitation science and therapeutic practice.', 150, 4, 'bachelor', 'CHED PSG for Physical Therapy', false),
                    self::chedProgram('BSND', 'Bachelor of Science in Nutrition and Dietetics', 'Clinical nutrition, food systems, and dietetic practice.', 150, 4, 'bachelor', 'CHED PSG for Nutrition and Dietetics', false),
                ],
            ],
            [
                'key' => 'education',
                'label' => 'Teacher Education',
                'department_code' => 'EDUC',
                'department_name' => 'Education',
                'programs' => [
                    self::chedProgram('BEEd', 'Bachelor of Elementary Education', 'Teaching learners in the elementary grades.', 120, 4, 'bachelor', 'CMO 74 s. 2017 (Teacher Ed PSGs)', true),
                    self::chedProgram('BSEd', 'Bachelor of Secondary Education', 'Teaching learners in the junior and senior high school levels.', 120, 4, 'bachelor', 'CMO 74 s. 2017 (Teacher Ed PSGs)', true),
                    self::chedProgram('BECEd', 'Bachelor of Early Childhood Education', 'Teaching learners in the early childhood years.', 120, 4, 'bachelor', 'CMO 76 s. 2017 (Teacher Ed PSGs)', false),
                    self::chedProgram('BPEd', 'Bachelor of Physical Education', 'Physical education, wellness, and sports pedagogy.', 120, 4, 'bachelor', 'CMO 80 s. 2017 (Teacher Ed PSGs)', false),
                    self::chedProgram('BTLEd', 'Bachelor of Technology and Livelihood Education', 'Technology and livelihood education instruction.', 120, 4, 'bachelor', 'CMO 78 s. 2017 (Teacher Ed PSGs)', false),
                ],
            ],
            [
                'key' => 'socsci',
                'label' => 'Social Sciences, Arts & Sciences',
                'department_code' => 'SOCS',
                'department_name' => 'Arts and Sciences',
                'programs' => [
                    self::chedProgram('BSPsy', 'Bachelor of Science in Psychology', 'Human behavior, assessment, and mental health.', 120, 4, 'bachelor', 'CHED PSG for Psychology', false),
                    self::chedProgram('ABComm', 'Bachelor of Arts in Communication', 'Media, public relations, and communication theory.', 120, 4, 'bachelor', 'CHED PSG for Communication', false),
                    self::chedProgram('ABPolSci', 'Bachelor of Arts in Political Science', 'Governance, policy, and international relations.', 120, 4, 'bachelor', 'CHED PSG for Political Science', false),
                    self::chedProgram('BSMath', 'Bachelor of Science in Mathematics', 'Pure and applied mathematics.', 120, 4, 'bachelor', 'CHED PSG for Mathematics', false),
                    self::chedProgram('BSBio', 'Bachelor of Science in Biology', 'Life sciences, ecology, and biotechnology.', 120, 4, 'bachelor', 'CHED PSG for Biology', false),
                    self::chedProgram('ABEng', 'Bachelor of Arts in English Language Studies', 'Language, linguistics, and literature.', 120, 4, 'bachelor', 'CHED PSG for English Language Studies', false),
                ],
            ],
            [
                'key' => 'crim',
                'label' => 'Criminal Justice & Public Safety',
                'department_code' => 'CRIM',
                'department_name' => 'Criminology',
                'programs' => [
                    self::chedProgram('BSCrim', 'Bachelor of Science in Criminology', 'Law enforcement, criminal justice, and forensic science.', 120, 4, 'bachelor', 'CHED PSG for Criminology', false),
                ],
            ],
            [
                'key' => 'maritime',
                'label' => 'Maritime Programs',
                'department_code' => 'MAR',
                'department_name' => 'Maritime Studies',
                'programs' => [
                    self::chedProgram('BSMT', 'Bachelor of Science in Marine Transportation', 'Shipboard operation, navigation, and cargo handling.', 150, 4, 'bachelor', 'CHED-MARINA PSGs (STCW-aligned)', false),
                    self::chedProgram('BSMarE', 'Bachelor of Science in Marine Engineering', 'Ship propulsion, auxiliaries, and marine systems.', 150, 4, 'bachelor', 'CHED-MARINA PSGs (STCW-aligned)', false),
                ],
            ],
        ];
    }

    /**
     * Legacy four-track Senior High School structure under the K to 12 program.
     *
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     strands: array<int, array{key: string, name: string, description: string}>
     * }>
     */
    public static function shsTracksLegacy(): array
    {
        return [
            [
                'key' => 'academic',
                'name' => 'Academic Track',
                'description' => 'Prepares learners for tertiary education through specialized academic strands.',
                'strands' => [
                    ['key' => 'stem', 'name' => 'STEM', 'description' => 'Science, Technology, Engineering, and Mathematics.'],
                    ['key' => 'abm', 'name' => 'ABM', 'description' => 'Accountancy, Business, and Management.'],
                    ['key' => 'humss', 'name' => 'HUMSS', 'description' => 'Humanities and Social Sciences.'],
                    ['key' => 'gas', 'name' => 'GAS', 'description' => 'General Academic Strand.'],
                ],
            ],
            [
                'key' => 'tvl',
                'name' => 'Technical-Vocational-Livelihood (TVL) Track',
                'description' => 'Competency-based training aligned with TESDA Training Regulations and NC levels.',
                'strands' => [
                    ['key' => 'agri-fishery', 'name' => 'Agri-Fishery Arts', 'description' => 'Agricultural crops, animal production, and aquaculture.'],
                    ['key' => 'home-economics', 'name' => 'Home Economics', 'description' => 'Cookery, bread and pastry production, caregiving, and tourism services.'],
                    ['key' => 'ict', 'name' => 'ICT', 'description' => 'Computer programming, servicing, and animation.'],
                    ['key' => 'industrial-arts', 'name' => 'Industrial Arts', 'description' => 'Automotive, electrical, welding, and construction trades.'],
                ],
            ],
            [
                'key' => 'sports',
                'name' => 'Sports Track',
                'description' => 'Sports science, coaching, and athletic development.',
                'strands' => [
                    ['key' => 'sports', 'name' => 'Sports', 'description' => 'Sports science, coaching, and officiating.'],
                ],
            ],
            [
                'key' => 'arts-design',
                'name' => 'Arts and Design Track',
                'description' => 'Creative industries: visual arts, media, performing arts, and design.',
                'strands' => [
                    ['key' => 'arts-design', 'name' => 'Arts and Design', 'description' => 'Visual arts, media arts, performing arts, and design.'],
                ],
            ],
        ];
    }

    /**
     * Revised two-track Senior High School structure piloted from SY 2025-2026
     * (841 pilot schools). Structure is provisional until DepEd finalizes it.
     *
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     strands: array<int, array{key: string, name: string, description: string}>
     * }>
     */
    public static function shsTracksRevised(): array
    {
        return [
            [
                'key' => 'academic',
                'name' => 'Academic Track (Revised)',
                'description' => 'College-preparatory strand cluster with electives and a 640-hour work immersion.',
                'strands' => [
                    ['key' => 'stem', 'name' => 'STEM', 'description' => 'Science, Technology, Engineering, and Mathematics.'],
                    ['key' => 'abm', 'name' => 'ABM', 'description' => 'Accountancy, Business, and Management.'],
                    ['key' => 'humss', 'name' => 'HUMSS', 'description' => 'Humanities and Social Sciences.'],
                    ['key' => 'gas', 'name' => 'GAS', 'description' => 'General Academic Strand.'],
                ],
            ],
            [
                'key' => 'technical-professional',
                'name' => 'Technical-Professional Track (Revised)',
                'description' => 'Work-oriented strand cluster under the revised SHS pilot.',
                'strands' => [
                    ['key' => 'ict', 'name' => 'ICT', 'description' => 'Computing and digital media skills.'],
                    ['key' => 'home-economics', 'name' => 'Home Economics', 'description' => 'Hospitality, food, and care services.'],
                    ['key' => 'agri-fishery', 'name' => 'Agri-Fishery Arts', 'description' => 'Agricultural and fishery production.'],
                    ['key' => 'industrial-arts', 'name' => 'Industrial Arts', 'description' => 'Engineering and construction trades.'],
                ],
            ],
        ];
    }

    /**
     * MATATAG phased implementation timeline (DepEd Order No. 010, s. 2024).
     *
     * @return array<int, array{sy: string, grades: array<int, string>, status: string}>
     */
    public static function matatagPhases(): array
    {
        return [
            ['sy' => '2024-2025', 'grades' => ['K', '1', '4', '7'], 'status' => 'Implemented'],
            ['sy' => '2025-2026', 'grades' => ['2', '3', '5', '8'], 'status' => 'Implemented'],
            ['sy' => '2026-2027', 'grades' => ['6', '9', '10'], 'status' => 'Current - full K-10 implementation'],
        ];
    }

    /**
     * MATATAG learning areas per grade band.
     *
     * @return array<string, array<int, string>>
     */
    public static function matatagLearningAreas(): array
    {
        return [
            'kindergarten' => ['Play-based learning (no formal subjects)'],
            'grade_1' => ['Language', 'Reading & Literacy', 'Mathematics', 'Makabansa', 'GMRC'],
            'grade_2' => ['Filipino', 'English', 'Mathematics', 'Makabansa', 'GMRC'],
            'grade_3' => ['Filipino', 'English', 'Mathematics', 'Science', 'Makabansa', 'GMRC'],
            'grades_4_6' => ['Filipino', 'English', 'Mathematics', 'Science', 'Araling Panlipunan', 'EPP/TLE', 'Music & Arts', 'PE & Health', 'GMRC'],
            'grades_7_10' => ['Filipino', 'English', 'Mathematics', 'Science', 'Araling Panlipunan', 'TLE', 'Music & Arts', 'PE & Health', 'Values Education'],
        ];
    }

    /**
     * Official Philippine school calendar presets.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function calendarPresets(): array
    {
        return [
            [
                'key' => 'sy2026-2027-3term',
                'label' => 'SY 2026-2027 · 3-Term Calendar (new)',
                'starts' => '2026-06-08',
                'ends' => '2027-05-30',
                'terms' => 3,
                'note' => 'Three-term calendar approved for SY 2026-2027; end date is indicative.',
                'source' => 'DepEd / Office of the President (2026)',
                'estimated' => true,
            ],
            [
                'key' => 'sy2025-2026',
                'label' => 'SY 2025-2026 · 2 Semesters',
                'starts' => '2025-06-16',
                'ends' => '2026-03-31',
                'terms' => 2,
                'note' => 'Per DepEd Order No. 012, s. 2025.',
                'source' => 'DepEd Order No. 012, s. 2025',
                'estimated' => false,
            ],
        ];
    }

    /**
     * TESDA training sectors with their popular qualifications.
     *
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     department_code: string,
     *     department_name: string,
     *     qualifications: array<int, array<string, mixed>>
     * }>
     */
    public static function tesdaSectors(): array
    {
        return [
            [
                'key' => 'ict',
                'label' => 'ICT',
                'department_code' => 'ICT',
                'department_name' => 'Information & Communications Technology',
                'qualifications' => [
                    self::tesdaQualification('CSS-NC2', 'Computer Systems Servicing NC II', 2, 2, false, false, 'Install, configure, and maintain computer systems and networks.'),
                    self::tesdaQualification('ANIM-NC2', 'Animation NC II', 2, 2, false, false, '2D and 3D digital animation production.'),
                    self::tesdaQualification('VGD-NC3', 'Visual Graphic Design NC III', 3, 3, false, false, 'Layout, imaging, and production for print and digital media.'),
                    self::tesdaQualification('PROG-NC4', 'Programming NC IV', 4, 4, false, false, 'Software development at the professional level.'),
                    self::tesdaQualification('CCS-NC2', 'Contact Center Services NC II', 2, 2, false, false, 'Customer service and telesales operations.'),
                    self::tesdaQualification('BIM-NC2', 'Broadband Installation (Fixed Wireless Systems) NC II', 2, 2, false, false, 'Fixed wireless broadband installation and maintenance.'),
                    self::tesdaQualification('DIPL-ICT', 'Diploma in Information Technology', 0, 5, true, false, 'Three-year TESDA diploma program (PQF Level 5).'),
                ],
            ],
            [
                'key' => 'tourism',
                'label' => 'Hospitality & Tourism',
                'department_code' => 'TOUR',
                'department_name' => 'Hospitality & Tourism',
                'qualifications' => [
                    self::tesdaQualification('COOK-NC2', 'Cookery NC II', 2, 2, false, false, 'Hot and cold kitchen production.'),
                    self::tesdaQualification('FBP-NC2', 'Food Production (Bread and Patisserie) NC II', 2, 2, false, false, 'Supersedes Bread & Pastry Production NC II.'),
                    self::tesdaQualification('FBS-NC2', 'Food and Beverage Services NC II', 2, 2, false, false, 'Restaurant service and table management.'),
                    self::tesdaQualification('BART-NC2', 'Bartending NC II', 2, 2, false, false, 'Bar operations and beverage service.'),
                    self::tesdaQualification('HKP-NC2', 'Housekeeping NC II', 2, 2, false, false, 'Guest room and public area servicing.'),
                    self::tesdaQualification('FOS-NC2', 'Front Office Services NC II', 2, 2, false, false, 'Reservations and front desk operations.'),
                    self::tesdaQualification('EMS-NC3', 'Events Management Services NC III', 3, 3, false, false, 'Event planning and execution.'),
                    self::tesdaQualification('TGS-NC2', 'Tour Guiding Services NC II', 2, 2, false, false, 'Local tour guiding and interpretation.'),
                ],
            ],
            [
                'key' => 'agri',
                'label' => 'Agriculture, Forestry & Fishery',
                'department_code' => 'AGRI',
                'department_name' => 'Agriculture',
                'qualifications' => [
                    self::tesdaQualification('OAP-NC2', 'Organic Agriculture Production NC II', 2, 2, false, false, 'Organic crop and livestock production.'),
                    self::tesdaQualification('ACP-NC2', 'Agricultural Crops Production NC II', 2, 2, false, false, 'Commercial crop production.'),
                    self::tesdaQualification('APP-NC2', 'Animal Production (Poultry-Chicken) NC II', 2, 2, false, false, 'Poultry raising and management.'),
                    self::tesdaQualification('AQUA-NC2', 'Aquaculture NC II', 2, 2, false, false, 'Fish and aquatic resource farming.'),
                ],
            ],
            [
                'key' => 'auto',
                'label' => 'Automotive & Land Transport',
                'department_code' => 'AUTO',
                'department_name' => 'Automotive & Land Transport',
                'qualifications' => [
                    self::tesdaQualification('ASV-NC1', 'Automotive Servicing NC I', 1, 1, false, false, 'Basic vehicle inspection and maintenance.'),
                    self::tesdaQualification('ASV-NC2', 'Automotive Servicing NC II', 2, 2, false, false, 'Engine, chassis, and electrical servicing.'),
                    self::tesdaQualification('DRV-NC2', 'Driving NC II', 2, 2, false, false, 'Light vehicle driving.'),
                    self::tesdaQualification('MSE-NC2', 'Motorcycle/Small Engine Servicing NC II', 2, 2, false, false, 'Motorcycle and small engine repair.'),
                ],
            ],
            [
                'key' => 'construction',
                'label' => 'Construction',
                'department_code' => 'CONS',
                'department_name' => 'Construction',
                'qualifications' => [
                    self::tesdaQualification('CARP-NC2', 'Carpentry NC II', 2, 2, false, false, 'Framing, forms, and finishing carpentry.'),
                    self::tesdaQualification('MAS-NC2', 'Masonry NC II', 2, 2, false, false, 'Concrete masonry and tile work.'),
                    self::tesdaQualification('PLUMB-NC2', 'Plumbing NC II', 2, 2, false, false, 'Water supply and drainage systems.'),
                    self::tesdaQualification('EIM-NC2', 'Electrical Installation and Maintenance NC II', 2, 2, false, false, 'Residential and commercial wiring.'),
                    self::tesdaQualification('SMAW-NC1', 'Shielded Metal Arc Welding NC I', 1, 1, true, false, 'Superseded by Manual Metal Arc Welding (MMAW).'),
                    self::tesdaQualification('SMAW-NC2', 'Shielded Metal Arc Welding NC II', 2, 2, true, false, 'Superseded by Manual Metal Arc Welding (MMAW).'),
                ],
            ],
            [
                'key' => 'metals',
                'label' => 'Metals & Engineering',
                'department_code' => 'METAL',
                'department_name' => 'Metals & Engineering',
                'qualifications' => [
                    self::tesdaQualification('MACH-NC2', 'Machining NC II', 2, 2, false, false, 'Lathe and milling machine operation.'),
                    self::tesdaQualification('MMAW-NC1', 'Manual Metal Arc Welding NC I', 1, 1, false, false, 'Current TR replacing SMAW NC I.'),
                    self::tesdaQualification('MMAW-NC2', 'Manual Metal Arc Welding NC II', 2, 2, false, false, 'Current TR replacing SMAW NC II.'),
                    self::tesdaQualification('GMAW-NC2', 'Gas Metal Arc Welding NC II', 2, 2, false, false, 'MIG welding processes.'),
                ],
            ],
            [
                'key' => 'garments',
                'label' => 'Garments & Fashion',
                'department_code' => 'GARM',
                'department_name' => 'Garments & Fashion',
                'qualifications' => [
                    self::tesdaQualification('DRES-NC2', 'Dressmaking NC II', 2, 2, false, false, 'Garment construction and finishing.'),
                    self::tesdaQualification('TAIL-NC2', 'Tailoring NC II', 2, 2, false, false, 'Men\u2019s wear construction and alterations.'),
                    self::tesdaQualification('FASD-NC3', 'Fashion Design (Apparel) NC III', 3, 3, false, false, 'Apparel design and production planning.'),
                ],
            ],
            [
                'key' => 'health',
                'label' => 'Health, Social & Community Services',
                'department_code' => 'HLTH',
                'department_name' => 'Health & Social Services',
                'qualifications' => [
                    self::tesdaQualification('CARE-NC2', 'Caregiving NC II', 2, 2, false, false, 'Personal care across lifespan settings.'),
                    self::tesdaQualification('HCS-NC2', 'Health Care Services NC II', 2, 2, false, false, 'Clinical support services in health facilities.'),
                    self::tesdaQualification('MASS-NC2', 'Massage Therapy NC II', 2, 2, false, false, 'Swedish, shiatsu, and wellness massage.'),
                    self::tesdaQualification('PHAR-NC3', 'Pharmacy Services NC III', 3, 3, false, false, 'Pharmacy dispensing support.'),
                ],
            ],
            [
                'key' => 'electronics',
                'label' => 'Electronics & Semiconductor',
                'department_code' => 'ELEC',
                'department_name' => 'Electronics',
                'qualifications' => [
                    self::tesdaQualification('EPAS-NC2', 'Electronic Products Assembly and Servicing NC II', 2, 2, false, false, 'Assembly, troubleshooting, and repair of electronic products.'),
                    self::tesdaQualification('MECH-NC3', 'Mechatronics Servicing NC III', 3, 3, false, false, 'Automated and robotic system servicing.'),
                ],
            ],
            [
                'key' => 'business',
                'label' => 'Business & Finance',
                'department_code' => 'BIZ',
                'department_name' => 'Business & Finance',
                'qualifications' => [
                    self::tesdaQualification('BK-NC3', 'Bookkeeping NC III', 3, 3, false, false, 'Financial records and accounting support.'),
                ],
            ],
            [
                'key' => 'beauty',
                'label' => 'Beauty & Wellness',
                'department_code' => 'BEAU',
                'department_name' => 'Beauty & Wellness',
                'qualifications' => [
                    self::tesdaQualification('BCS-NC2', 'Beauty Care Services NC II', 2, 2, false, false, 'Facials, manicure, pedicure, and makeup services.'),
                    self::tesdaQualification('HAIR-NC2', 'Hairdressing NC II', 2, 2, false, false, 'Hair cutting, coloring, and styling.'),
                ],
            ],
        ];
    }

    /**
     * The selectable program keys for a given framework. Used to validate the
     * setup wizard's `programs` payload against the catalog.
     *
     * @return list<string>
     */
    public static function validProgramCodes(?CurriculumFramework $framework): array
    {
        return match ($framework) {
            CurriculumFramework::ChedPsg => self::validChedProgramCodes(),
            CurriculumFramework::TesdaTr => self::validTesdaProgramCodes(),
            CurriculumFramework::DepedShsK12 => self::validShsProgramCodes(self::shsTracksLegacy()),
            CurriculumFramework::DepedShsRevised => self::validShsProgramCodes(self::shsTracksRevised()),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function chedProgram(
        string $code,
        string $title,
        string $description,
        int $units,
        int $yearLevel,
        string $type,
        string $reference,
        bool $verified,
        ?string $departmentCode = null,
        ?string $departmentName = null,
    ): array {
        return [
            'code' => $code,
            'title' => $title,
            'description' => $description,
            'units' => $units,
            'year_level' => $yearLevel,
            'type' => $type,
            'reference' => $reference,
            'verified' => $verified,
            'source_url' => 'https://ched.gov.ph/issuances/',
            'department_code' => $departmentCode,
            'department_name' => $departmentName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function tesdaQualification(
        string $code,
        string $title,
        int $ncLevel,
        int $pqfLevel,
        bool $diploma,
        bool $superseded,
        string $description,
    ): array {
        return [
            'code' => $code,
            'title' => $title,
            'nc_level' => $ncLevel,
            'pqf_level' => $pqfLevel,
            'diploma' => $diploma,
            'superseded' => $superseded,
            'description' => $description,
            'reference' => $diploma ? 'TESDA Diploma Program (PQF Level 5)' : 'TESDA Training Regulations (TR)',
            'source_url' => 'https://www.tesda.gov.ph/Download/Training_Regulations',
        ];
    }

    /**
     * @return list<string>
     */
    private static function validChedProgramCodes(): array
    {
        $codes = [];

        foreach (self::chedClusters() as $cluster) {
            foreach ($cluster['programs'] as $program) {
                $code = $program['code'] ?? null;

                if (is_string($code)) {
                    $codes[] = $code;
                }
            }
        }

        return $codes;
    }

    /**
     * @return list<string>
     */
    private static function validTesdaProgramCodes(): array
    {
        $codes = [];

        foreach (self::tesdaSectors() as $sector) {
            foreach ($sector['qualifications'] as $qualification) {
                $code = $qualification['code'] ?? null;

                if (is_string($code)) {
                    $codes[] = $code;
                }
            }
        }

        return $codes;
    }

    /**
     * @param  array<int, array{key: string, strands: array<int, array{key: string, name: string, description: string}>}>  $tracks
     * @return list<string>
     */
    private static function validShsProgramCodes(array $tracks): array
    {
        $codes = [];

        foreach ($tracks as $track) {
            foreach ($track['strands'] as $strand) {
                $codes[] = $track['key'].':'.$strand['key'];
            }
        }

        return $codes;
    }
}
