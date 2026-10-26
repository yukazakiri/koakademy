<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LibrarySystem\Database\Seeders\LibraryDatabaseSeeder;
use Modules\NotificationCenter\Database\Seeders\NotificationTemplateSeeder;
use Modules\StudentMedicalRecords\Database\Seeders\StudentMedicalRecordsDatabaseSeeder;

final class DemoUniversityModulesSeeder extends Seeder
{

    private const SCHOOL_ID = 8;
    private string $now = '';

    private int $adminId = 1;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<object> */
    private array $students = [];

    public function run(): void
    {
        $this->now = now()->toDateTimeString();
        $this->adminId = (int) (DB::table('users')->whereIn('role', ['super_admin', 'admin'])->value('id') ?? 1);
        $this->userIds = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->students = DB::table('students')->whereNull('deleted_at')->get()->all();

        $this->cleanup();

        $this->call([
            LibraryDatabaseSeeder::class,
            StudentMedicalRecordsDatabaseSeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        $this->augmentLibrary();
        $this->augmentMedical();
        $this->seedInventory();
        $this->addNotificationTemplates();

        $this->command?->info('Module data ready: '.DB::table('library_books')->count().' books, '.DB::table('medical_records')->count().' medical records, '.DB::table('inventory_products')->count().' inventory products.');
    }

    private function cleanup(): void
    {
        foreach ([
            'library_borrow_records',
            'library_bookmarks',
            'library_user_book_states',
            'library_research_paper_student',
            'library_research_papers',
            'library_book_digital_editions',
            'library_books',
            'library_authors',
            'library_categories',
            'medical_records',
            'inventory_borrowings',
            'inventory_stock_movements',
            'inventory_product_histories',
            'inventory_products',
            'inventory_suppliers',
            'inventory_categories',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    private function augmentLibrary(): void
    {
        $this->command?->info('Expanding library catalog and circulation...');

        $categoryNames = [
            'Accountancy', 'Architecture', 'Biology', 'Chemistry', 'Communication',
            'Criminology', 'Education', 'Engineering', 'Entrepreneurship', 'Environmental Science',
            'Filipino', 'History', 'Hospitality', 'Law', 'Literature',
            'Management', 'Nursing', 'Philosophy', 'Psychology', 'Tourism',
        ];
        $categoryIds = DB::table('library_categories')->pluck('id')->all();
        foreach ($categoryNames as $index => $name) {
            if (DB::table('library_categories')->where('name', $name)->exists()) {
                continue;
            }
            $categoryIds[] = DB::table('library_categories')->insertGetId([
                'name' => $name,
                'description' => 'Curated resources for '.$name.' students and faculty.',
                'color' => ['#2563eb', '#7c3aed', '#0891b2', '#059669', '#d97706', '#e11d48', '#4f46e5'][$index % 7],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $authorFirst = ['Maria', 'Jose', 'Ana', 'Ramon', 'Liza', 'Carlos', 'Elena', 'Miguel', 'Sofia', 'Andres', 'Patricia', 'Daniel'];
        $authorLast = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Villanueva', 'Aquino', 'Navarro'];
        $authorIds = DB::table('library_authors')->pluck('id')->all();
        for ($i = 0; $i < 50; $i++) {
            $authorIds[] = DB::table('library_authors')->insertGetId([
                'name' => $authorFirst[$i % count($authorFirst)].' '.$authorLast[($i * 3) % count($authorLast)],
                'biography' => 'Filipino author and educator contributing to higher education reference materials.',
                'birth_date' => Carbon::create(1950 + ($i % 40), 1 + ($i % 12), 1 + ($i % 27))->toDateString(),
                'nationality' => $i % 4 === 0 ? 'Filipino' : 'International',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $subjects = [
            'Foundations of Information Technology', 'Advanced Business Management', 'Hospitality Operations',
            'Principles of Teaching', 'Engineering Mathematics', 'Financial Accounting',
            'Human Anatomy and Physiology', 'Philippine Literature', 'Introduction to Psychology',
            'Tourism and Culture', 'Research Methods', 'Professional Communication',
            'Data Analytics for Business', 'Environmental Science', 'Introduction to Law',
        ];
        $bookIds = [];
        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $total = 1 + ($i % 10);
            $rows[] = [
                'title' => $subjects[$i % count($subjects)].' Volume '.($i + 1),
                'isbn' => '978-971-'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT).'-'.($i % 10),
                'author_id' => $authorIds[$i % count($authorIds)],
                'category_id' => $categoryIds[$i % count($categoryIds)],
                'publisher' => ['KoAkademy Press', 'Rex Book Store', 'C&E Publishing', 'Anvil Publishing', 'Oxford University Press'][$i % 5],
                'publication_year' => 2005 + ($i % 20),
                'pages' => 120 + (($i * 13) % 500),
                'description' => 'A comprehensive reference for '.$subjects[$i % count($subjects)].' with practical exercises and local case studies.',
                'cover_image' => null,
                'total_copies' => $total,
                'available_copies' => max(0, $total - ($i % 4)),
                'location' => 'Main Library • Shelf '.str_pad((string) (($i % 30) + 1), 2, '0', STR_PAD_LEFT),
                'status' => $i % 7 === 0 ? 'borrowed' : 'available',
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'cover_image_path' => null,
                'call_number' => 'Z'.(100 + $i).' .A1 '.$i,
                'accession_number' => 'ACC-2026-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('library_books')->insert($chunk);
        }
        $bookIds = DB::table('library_books')->pluck('id')->all();

        $editions = [];
        $editionBookIds = array_values(array_diff(
            $bookIds,
            DB::table('library_book_digital_editions')->pluck('book_id')->map(fn ($id) => (int) $id)->all(),
        ));
        foreach (array_slice($editionBookIds, 0, 80) as $index => $bookId) {
            $editions[] = [
                'book_id' => $bookId,
                'disk' => 'local',
                'path' => 'library/digital/book-'.$bookId.'.pdf',
                'original_name' => 'book-'.$bookId.'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 180000,
                'sha256' => hash('sha256', 'library-book-'.$bookId),
                'status' => 'published',
                'downloads_allowed' => 1,
                'rights_basis' => 'koakademy_owned',
                'rights_holder' => 'KoAkademy',
                'license_url' => null,
                'rights_notes' => 'Digitized for the KoAkademy learning community.',
                'rights_expires_at' => null,
                'uploaded_by' => $this->adminId,
                'uploaded_at' => $this->now,
                'published_by' => $this->adminId,
                'published_at' => $this->now,
                'rights_confirmed_by' => $this->adminId,
                'rights_confirmed_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('library_book_digital_editions')->insert($editions);

        $borrows = [];
        for ($i = 0; $i < 300; $i++) {
            $borrowed = Carbon::create(2026, 8, 1)->addDays($i % 90);
            $returned = $i % 3 !== 0;
            $status = $returned ? 'returned' : ($i % 13 === 0 ? 'lost' : 'borrowed');
            $borrows[] = [
                'book_id' => $bookIds[$i % count($bookIds)],
                'user_id' => $this->userIds[$i % count($this->userIds)],
                'borrowed_at' => $borrowed->toDateTimeString(),
                'due_date' => $borrowed->copy()->addDays(14)->toDateTimeString(),
                'returned_at' => $returned ? $borrowed->copy()->addDays(7)->toDateTimeString() : null,
                'status' => $status,
                'fine_amount' => ! $returned && $borrowed->copy()->addDays(14)->isPast() ? 25 * (($i % 4) + 1) : 0,
                'notes' => $returned ? 'Returned in good condition.' : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('library_borrow_records')->insert($borrows);

        $states = [];
        $usedStates = [];
        foreach (DB::table('library_user_book_states')->get(['user_id', 'book_id']) as $existing) {
            $usedStates[$existing->user_id.'|'.$existing->book_id] = true;
        }
        for ($i = 0; $i < 300; $i++) {
            $userId = $this->userIds[$i % count($this->userIds)];
            $bookId = $bookIds[($i * 7) % count($bookIds)];
            $key = $userId.'|'.$bookId;
            if (isset($usedStates[$key])) {
                continue;
            }
            $usedStates[$key] = true;
            $states[] = [
                'user_id' => $userId,
                'book_id' => $bookId,
                'last_page' => 10 + ($i % 120),
                'total_pages' => 300,
                'favorited_at' => $i % 4 === 0 ? $this->now : null,
                'last_read_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('library_user_book_states')->insert($states);

        $bookmarks = [];
        $usedBookmarks = [];
        foreach (DB::table('library_bookmarks')->get(['user_id', 'book_id', 'page']) as $existing) {
            $usedBookmarks[$existing->user_id.'|'.$existing->book_id.'|'.$existing->page] = true;
        }
        for ($i = 0; $i < 200; $i++) {
            $userId = $this->userIds[$i % count($this->userIds)];
            $bookId = $bookIds[($i * 3) % count($bookIds)];
            $page = 10 + ($i % 200);
            $key = $userId.'|'.$bookId.'|'.$page;
            if (isset($usedBookmarks[$key])) {
                continue;
            }
            $usedBookmarks[$key] = true;
            $bookmarks[] = [
                'user_id' => $userId,
                'book_id' => $bookId,
                'page' => $page,
                'label' => 'Important section '.($i + 1),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('library_bookmarks')->insert($bookmarks);

        $courseIds = DB::table('courses')->pluck('id')->all();
        $paperIds = [];
        $papers = [];
        $paperTypes = ['thesis', 'capstone', 'research'];
        $paperStatuses = ['submitted', 'archived', 'draft'];
        for ($i = 0; $i < 120; $i++) {
            $student = $this->students[$i % count($this->students)];
            $papers[] = [
                'title' => 'Institutional Research Paper '.($i + 1).': '.$subjects[$i % count($subjects)],
                'type' => $paperTypes[$i % count($paperTypes)],
                'student_id' => $student->id,
                'course_id' => $courseIds[$i % count($courseIds)],
                'advisor_name' => 'Dr. '.$authorFirst[$i % count($authorFirst)].' '.$authorLast[$i % count($authorLast)],
                'contributors' => $authorFirst[($i + 2) % count($authorFirst)].' '.$authorLast[($i + 5) % count($authorLast)],
                'abstract' => 'This study examines current practices and proposes evidence-based improvements for the institution.',
                'keywords' => 'research, education, innovation, assessment',
                'publication_year' => 2022 + ($i % 5),
                'document_url' => null,
                'status' => $paperStatuses[$i % count($paperStatuses)],
                'is_public' => $i % 3 === 0 ? 1 : 0,
                'notes' => 'Completed under the institutional research program.',
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'document_path' => 'library/research/paper-'.($i + 1).'.pdf',
                'cover_image_path' => null,
                'school_id' => self::SCHOOL_ID,
            ];
        }
        foreach (array_chunk($papers, 200) as $chunk) {
            DB::table('library_research_papers')->insert($chunk);
        }
        $paperIds = DB::table('library_research_papers')->pluck('id')->all();

        $pivots = [];
        $usedPivots = [];
        foreach (DB::table('library_research_paper_student')->get(['research_paper_id', 'student_id']) as $existing) {
            $usedPivots[$existing->research_paper_id.'|'.$existing->student_id] = true;
        }
        foreach ($paperIds as $index => $paperId) {
            foreach ([0, 1] as $offset) {
                $student = $this->students[($index + $offset * 17) % count($this->students)];
                $key = $paperId.'|'.$student->id;
                if (isset($usedPivots[$key])) {
                    continue;
                }
                $usedPivots[$key] = true;
                $pivots[] = [
                    'research_paper_id' => $paperId,
                    'student_id' => $student->id,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }
        foreach (array_chunk($pivots, 300) as $chunk) {
            DB::table('library_research_paper_student')->insert($chunk);
        }
    }

    private function augmentMedical(): void
    {
        $this->command?->info('Expanding medical records...');

        $types = ['checkup', 'vaccination', 'allergy', 'medication', 'emergency', 'dental', 'vision', 'mental_health', 'laboratory', 'surgery', 'follow_up'];
        $statuses = ['active', 'resolved', 'ongoing'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $rows = [];
        for ($i = 0; $i < 400; $i++) {
            $student = $this->students[$i % count($this->students)];
            $height = 150 + ($i % 40);
            $weight = 45 + ($i % 45);
            $rows[] = [
                'student_id' => $student->id,
                'record_type' => $types[$i % count($types)],
                'title' => ['Annual checkup', 'Flu vaccination', 'Allergy consultation', 'Dental cleaning', 'Vision screening'][$i % 5],
                'description' => 'Routine medical consultation and assessment.',
                'diagnosis' => $i % 3 === 0 ? 'Mild seasonal allergy' : null,
                'treatment' => $i % 3 === 0 ? 'Antihistamine and hydration' : null,
                'prescription' => $i % 4 === 0 ? 'Cetirizine 10mg once daily' : null,
                'notes' => 'Follow up if symptoms persist.',
                'doctor_name' => 'Dr. Sarah Johnson',
                'clinic_name' => 'KoAkademy Health Center',
                'clinic_address' => 'Main Campus, Quezon City',
                'doctor_contact' => '09170000002',
                'visit_date' => Carbon::create(2026, 1, 10)->addDays($i % 250)->toDateString(),
                'next_appointment' => $i % 5 === 0 ? Carbon::create(2026, 9, 1)->addDays($i % 30)->toDateString() : null,
                'follow_up_date' => null,
                'status' => $statuses[$i % count($statuses)],
                'priority' => $priorities[$i % count($priorities)],
                'is_confidential' => $i % 7 === 0 ? 1 : 0,
                'height' => $height,
                'weight' => $weight,
                'blood_pressure_systolic' => 110 + ($i % 20),
                'blood_pressure_diastolic' => 70 + ($i % 15),
                'temperature' => 36.2 + (($i % 10) / 10),
                'heart_rate' => 65 + ($i % 25),
                'bmi' => round($weight / (($height / 100) ** 2), 1),
                'vital_signs' => json_encode(['temperature' => 36.5, 'heart_rate' => 72]),
                'lab_results' => $i % 6 === 0 ? json_encode(['cbc' => 'normal']) : null,
                'attachments' => json_encode([]),
                'emergency_contact_notified' => 0,
                'emergency_notification_sent_at' => null,
                'created_by' => $this->adminId,
                'updated_by' => $this->adminId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('medical_records')->insert($chunk);
        }
    }

    private function seedInventory(): void
    {
        $this->command?->info('Creating inventory catalog and movements...');

        $categoryNames = ['Computers', 'Networking', 'Audio Visual', 'Laboratory', 'Office Supplies', 'Furniture', 'Sports', 'Maintenance', 'Security', 'Medical', 'Kitchen', 'Transport', 'Tools', 'Cabling', 'Accessories'];
        $categoryIds = [];
        foreach ($categoryNames as $index => $name) {
            $categoryIds[] = DB::table('inventory_categories')->insertGetId([
                'name' => $name,
                'description' => $name.' equipment and supplies.',
                'slug' => Str::slug($name),
                'is_active' => 1,
                'parent_id' => null,
                'sort_order' => $index + 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $supplierIds = [];
        $supplierNames = ['TechSource Philippines', 'Network Solutions Inc.', 'Campus Supply Co.', 'Metro Office Depot', 'LabWorks Trading', 'Furniture Hub', 'Sports Unlimited', 'Safety First Corp.', 'MediCare Supplies', 'Kitchen Essentials', 'Transit Motors', 'ToolMaster', 'CablePro', 'Accessory World', 'Green Facilities'];
        foreach ($supplierNames as $index => $name) {
            $supplierIds[] = DB::table('inventory_suppliers')->insertGetId([
                'name' => $name,
                'contact_person' => 'Contact '.($index + 1),
                'email' => 'supplier'.($index + 1).'@example.com',
                'phone' => '0918000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'address' => 'Supplier Address '.($index + 1),
                'city' => 'Quezon City',
                'state' => 'Metro Manila',
                'postal_code' => '1100',
                'country' => 'Philippines',
                'tax_number' => 'TAX-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'notes' => null,
                'is_active' => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $itemTypes = ['Tool', 'Router', 'NVR', 'CCTV'];
        $productIds = [];
        $products = [];
        for ($i = 0; $i < 200; $i++) {
            $type = $itemTypes[$i % count($itemTypes)];
            $stock = 5 + (($i * 7) % 90);
            $products[] = [
                'name' => ['Projector', 'Router', 'NVR Unit', 'CCTV Camera', 'Desktop Computer', 'Laptop', 'Printer', 'Switch', 'Access Point', 'Tool Kit'][$i % 10].' '.($i + 1),
                'sku' => 'INV-2026-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'description' => 'Institutional '.$type.' for academic and administrative use.',
                'category_id' => $categoryIds[$i % count($categoryIds)],
                'supplier_id' => $supplierIds[$i % count($supplierIds)],
                'price' => 1500 + ($i * 25),
                'cost' => 1000 + ($i * 20),
                'stock_quantity' => $stock,
                'min_stock_level' => 5,
                'max_stock_level' => 150,
                'unit' => 'pcs',
                'barcode' => 'BC-'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'track_stock' => 1,
                'is_active' => 1,
                'images' => json_encode([]),
                'notes' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'item_type' => $type,
                'is_borrowable' => 1,
                'location_building' => 'Main Building',
                'location_floor' => (string) (1 + ($i % 4)),
                'location_area' => ['Storage', 'Laboratory', 'Office', 'IT Room'][$i % 4],
                'ip_address' => in_array($type, ['Router', 'NVR', 'CCTV'], true) ? '192.168.1.'.(10 + $i % 200) : null,
                'wifi_ssid' => $type === 'Router' ? 'KoAkademy-'.($i + 1) : null,
                'wifi_password' => $type === 'Router' ? 'demo-password' : null,
                'login_username' => in_array($type, ['Router', 'NVR', 'CCTV'], true) ? 'admin' : null,
                'login_password' => in_array($type, ['Router', 'NVR', 'CCTV'], true) ? 'demo-password' : null,
                'defective_quantity' => $i % 17 === 0 ? 1 : 0,
                'is_consumable' => 0,
            ];
        }
        foreach (array_chunk($products, 200) as $chunk) {
            DB::table('inventory_products')->insert($chunk);
        }
        $productIds = DB::table('inventory_products')->pluck('id')->all();

        $borrowings = [];
        for ($i = 0; $i < 120; $i++) {
            $borrowed = Carbon::create(2026, 7, 1)->addDays($i % 100);
            $status = ['borrowed', 'returned', 'overdue', 'lost'][$i % 4];
            $returned = $status === 'returned';
            $borrowings[] = [
                'product_id' => $productIds[$i % count($productIds)],
                'quantity_borrowed' => 1 + ($i % 3),
                'borrower_name' => 'Borrower '.($i + 1),
                'borrower_email' => 'borrower'.($i + 1).'@koakademy.edu',
                'borrower_phone' => '0917000'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'department' => ['IT', 'Facilities', 'Academics', 'Library'][$i % 4],
                'purpose' => 'Official university activity.',
                'status' => $status,
                'borrowed_date' => $borrowed->toDateTimeString(),
                'expected_return_date' => $borrowed->copy()->addDays(7)->toDateTimeString(),
                'actual_return_date' => $returned ? $borrowed->copy()->addDays(5)->toDateTimeString() : null,
                'quantity_returned' => $returned ? 1 + ($i % 3) : 0,
                'return_notes' => $returned ? 'Returned in good condition.' : null,
                'issued_by' => $this->adminId,
                'returned_to' => $returned ? $this->adminId : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'quantity_returned_good' => $returned ? 1 + ($i % 3) : 0,
                'quantity_returned_defective' => 0,
            ];
        }
        DB::table('inventory_borrowings')->insert($borrowings);

        $movements = [];
        for ($i = 0; $i < 500; $i++) {
            $previous = 10 + ($i % 80);
            $type = ['in', 'out', 'return', 'adjustment'][$i % 4];
            $quantity = 1 + ($i % 10);
            $new = $type === 'in' || $type === 'return' ? $previous + $quantity : max(0, $previous - $quantity);
            $movements[] = [
                'product_id' => $productIds[$i % count($productIds)],
                'type' => $type,
                'quantity' => $quantity,
                'previous_stock' => $previous,
                'new_stock' => $new,
                'reference' => 'MOV-2026-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'reason' => 'Inventory '.$type.' transaction.',
                'user_id' => $this->adminId,
                'movement_date' => Carbon::create(2026, 1, 5)->addDays($i % 250)->toDateTimeString(),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('inventory_stock_movements')->insert($movements);

        $eventTypes = ['item_created', 'item_updated', 'location_moved', 'stock_rebalanced', 'borrowing_created', 'borrowing_updated', 'borrowing_reverted', 'borrowing_reassigned', 'borrowing_deleted'];
        $histories = [];
        for ($i = 0; $i < 300; $i++) {
            $histories[] = [
                'product_id' => $productIds[$i % count($productIds)],
                'event_type' => $eventTypes[$i % count($eventTypes)],
                'before' => json_encode(['stock_quantity' => 20]),
                'after' => json_encode(['stock_quantity' => 18]),
                'reference_type' => 'App\\Models\\User',
                'reference_id' => $this->adminId,
                'notes' => 'Inventory history entry generated for the demo dataset.',
                'recorded_by' => $this->adminId,
                'recorded_at' => Carbon::create(2026, 1, 5)->addDays($i % 250)->toDateTimeString(),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('inventory_product_histories')->insert($histories);
    }

    private function addNotificationTemplates(): void
    {
        $templates = [
            ['tuition-reminder', 'Tuition Reminder', 'Payment reminders for outstanding balances', 'finance'],
            ['library-due', 'Library Due Date', 'Reminders for borrowed library materials', 'library'],
            ['class-posted', 'Class Post Published', 'Notifications for new class announcements and assignments', 'academic'],
            ['ticket-update', 'Support Ticket Update', 'Updates on help desk ticket status', 'support'],
            ['medical-appointment', 'Medical Appointment', 'Clinic appointment reminders', 'health'],
        ];

        foreach ($templates as $template) {
            DB::table('notification_templates')->updateOrInsert(
                ['slug' => $template[0]],
                [
                    'name' => $template[1],
                    'description' => $template[2],
                    'category' => $template[3],
                    'mail_template' => 'notificationcenter::emails.templates.'.$template[0],
                    'default_channels' => json_encode(['mail', 'database']),
                    'variables' => json_encode(['title', 'content', 'action_url']),
                    'styles' => json_encode(['theme' => 'light', 'primary_color' => '#2563eb']),
                    'is_active' => 1,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ],
            );
        }
    }
}
