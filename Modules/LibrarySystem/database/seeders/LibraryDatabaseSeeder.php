<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Seeders;

use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\LibrarySystem\Enums\DigitalEditionStatus;
use Modules\LibrarySystem\Enums\DigitalRightsBasis;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Models\Category;
use Modules\LibrarySystem\Models\DigitalEdition;
use Modules\LibrarySystem\Models\ResearchPaper;
use Modules\LibrarySystem\Models\UserBookState;

final class LibraryDatabaseSeeder extends Seeder
{
    /**
     * Minimal valid single-page PDF structure for digital reading tests.
     */
    private const MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000108 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n185\n%%EOF\n";

    public function run(): void
    {
        $this->command?->info('📚 Seeding Library System (Categories, Authors, Books, eBooks, Borrows, Theses)...');

        $categories = $this->seedCategories();
        $authors = $this->seedAuthors();
        $books = $this->seedBooks($categories, $authors);
        $this->seedDigitalEditions($books);
        $this->seedBorrowRecords($books);
        $this->seedUserBookStates($books);
        $this->seedResearchPapers();

        $this->command?->info('✅ Library System successfully seeded.');
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $categoryData = [
            'computer-science' => [
                'name' => 'Computer Science & Software',
                'description' => 'Algorithms, data structures, software architecture, operating systems, and distributed networks.',
                'color' => '#2563eb',
            ],
            'mathematics' => [
                'name' => 'Mathematics & Cryptography',
                'description' => 'Discrete mathematics, calculus, linear algebra, number theory, and cryptographic protocols.',
                'color' => '#7c3aed',
            ],
            'artificial-intelligence' => [
                'name' => 'Artificial Intelligence & Data',
                'description' => 'Machine learning, neural networks, computer vision, data intensive applications, and NLP.',
                'color' => '#0891b2',
            ],
            'physics' => [
                'name' => 'Theoretical Physics & Astronomy',
                'description' => 'Classical mechanics, quantum computing, relativity, astrophysics, and thermodynamics.',
                'color' => '#059669',
            ],
            'philosophy' => [
                'name' => 'Philosophy & Science Studies',
                'description' => 'Epistemology, ethics in technology, history of scientific thought, and logic.',
                'color' => '#d97706',
            ],
            'economics' => [
                'name' => 'Economics & Behavioral Science',
                'description' => 'Microeconomics, macroeconomic theory, institutional systems, game theory, and finance.',
                'color' => '#e11d48',
            ],
            'design' => [
                'name' => 'Design & Human Interfaces',
                'description' => 'Cognitive ergonomics, human-computer interaction, visual typography, and information architecture.',
                'color' => '#4f46e5',
            ],
        ];

        $categories = [];
        foreach ($categoryData as $key => $data) {
            $categories[$key] = Category::query()->firstOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'color' => $data['color'],
                ]
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Author>
     */
    private function seedAuthors(): array
    {
        $authorData = [
            'knuth' => [
                'name' => 'Donald E. Knuth',
                'biography' => 'Professor Emeritus at Stanford University, author of The Art of Computer Programming and creator of the TeX typesetting system.',
                'birth_date' => '1938-01-10',
                'nationality' => 'American',
            ],
            'turing' => [
                'name' => 'Alan M. Turing',
                'biography' => 'British mathematician, logician, and cryptanalyst who played a pivotal role in cracking intercepted coded messages and formulated the Turing Machine concept.',
                'birth_date' => '1912-06-23',
                'nationality' => 'British',
            ],
            'feynman' => [
                'name' => 'Richard P. Feynman',
                'biography' => 'Theoretical physicist recognized for his work in quantum electrodynamics, recipient of the 1965 Nobel Prize in Physics, and master scientific communicator.',
                'birth_date' => '1918-05-11',
                'nationality' => 'American',
            ],
            'shannon' => [
                'name' => 'Claude E. Shannon',
                'biography' => 'Mathematician and electrical engineer remembered as the "father of information theory" for his landmark 1948 paper on mathematical theories of communication.',
                'birth_date' => '1916-04-30',
                'nationality' => 'American',
            ],
            'liskov' => [
                'name' => 'Barbara Liskov',
                'biography' => 'Computer scientist and MIT Institute Professor, Turing Award winner, renowned for pioneering data abstraction and the Liskov Substitution Principle.',
                'birth_date' => '1939-11-07',
                'nationality' => 'American',
            ],
            'kleppmann' => [
                'name' => 'Martin Kleppmann',
                'biography' => 'Researcher in distributed systems and security at the University of Cambridge, author of Designing Data-Intensive Applications.',
                'birth_date' => '1983-04-12',
                'nationality' => 'German',
            ],
            'sagan' => [
                'name' => 'Carl Sagan',
                'biography' => 'Astronomer, planetary scientist, and author who popularized astronomy through the Cosmos television series and groundbreaking planetary research.',
                'birth_date' => '1934-11-09',
                'nationality' => 'American',
            ],
            'norman' => [
                'name' => 'Don Norman',
                'biography' => 'Cognitive scientist and usability engineer, co-founder of the Nielsen Norman Group, author of The Design of Everyday Things.',
                'birth_date' => '1935-12-25',
                'nationality' => 'American',
            ],
            'strang' => [
                'name' => 'Gilbert Strang',
                'biography' => 'Professor of Mathematics at MIT, celebrated educator, author of seminal textbooks on Linear Algebra and its applications.',
                'birth_date' => '1934-11-27',
                'nationality' => 'American',
            ],
            'fowler' => [
                'name' => 'Martin Fowler',
                'biography' => 'British software developer, author and international speaker specializing in object-oriented analysis, design patterns, refactoring, and agile software development.',
                'birth_date' => '1963-12-18',
                'nationality' => 'British',
            ],
            'russell' => [
                'name' => 'Bertrand Russell',
                'biography' => 'British philosopher, logician, mathematician, and Nobel laureate who co-authored Principia Mathematica and made foundational contributions to mathematical logic.',
                'birth_date' => '1872-05-18',
                'nationality' => 'British',
            ],
            'wiener' => [
                'name' => 'Norbert Wiener',
                'biography' => 'American mathematician and philosopher known as the originator of cybernetics, studying feedback loops, communication, and control theory.',
                'birth_date' => '1894-11-26',
                'nationality' => 'American',
            ],
        ];

        $authors = [];
        foreach ($authorData as $key => $data) {
            $authors[$key] = Author::query()->firstOrCreate(
                ['name' => $data['name']],
                [
                    'biography' => $data['biography'],
                    'birth_date' => $data['birth_date'],
                    'nationality' => $data['nationality'],
                ]
            );
        }

        return $authors;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Author>  $authors
     * @return array<int, Book>
     */
    private function seedBooks(array $categories, array $authors): array
    {
        $bookDefinitions = [
            [
                'title' => 'The Art of Computer Programming, Vol. 1: Fundamental Algorithms',
                'isbn' => '978-0201896831',
                'call_number' => 'QA76.6 .K68 1997',
                'accession_number' => 'ACC-2024-001',
                'author_key' => 'knuth',
                'category_key' => 'computer-science',
                'publisher' => 'Addison-Wesley Professional',
                'publication_year' => 1997,
                'pages' => 672,
                'description' => 'The foundational reference on computer science algorithms, mathematical analysis of routines, information structures, linked lists, and tree structures.',
                'total_copies' => 6,
                'available_copies' => 4,
                'location' => 'Main Stacks • Shelf CS-01',
                'is_digital' => true,
            ],
            [
                'title' => 'Designing Data-Intensive Applications',
                'isbn' => '978-1449373320',
                'call_number' => 'QA76.9.D3 K55 2017',
                'accession_number' => 'ACC-2024-002',
                'author_key' => 'kleppmann',
                'category_key' => 'computer-science',
                'publisher' => "O'Reilly Media",
                'publication_year' => 2017,
                'pages' => 616,
                'description' => 'A comprehensive guide to the principles and architectures behind modern data systems, storage engines, replication, partitioning, and stream processing.',
                'total_copies' => 8,
                'available_copies' => 5,
                'location' => 'Main Stacks • Shelf CS-02',
                'is_digital' => true,
            ],
            [
                'title' => 'The Feynman Lectures on Physics, Vol. 1',
                'isbn' => '978-0465024933',
                'call_number' => 'QC21.2 .F49 2011',
                'accession_number' => 'ACC-2024-003',
                'author_key' => 'feynman',
                'category_key' => 'physics',
                'publisher' => 'Basic Books',
                'publication_year' => 2011,
                'pages' => 560,
                'description' => 'The definitive introduction to classical mechanics, radiation, thermodynamics, and the underlying mathematical laws governing the physical universe.',
                'total_copies' => 5,
                'available_copies' => 3,
                'location' => 'Science Wing • Shelf PH-03',
                'is_digital' => true,
            ],
            [
                'title' => 'A Mathematical Theory of Communication',
                'isbn' => '978-0252725487',
                'call_number' => 'TK5101 .S45 1949',
                'accession_number' => 'ACC-2024-004',
                'author_key' => 'shannon',
                'category_key' => 'mathematics',
                'publisher' => 'University of Illinois Press',
                'publication_year' => 1949,
                'pages' => 125,
                'description' => 'The monumental treatise establishing modern information theory, defining entropy, channel capacity, and discrete noiseless systems.',
                'total_copies' => 4,
                'available_copies' => 2,
                'location' => 'Rare & Special Collections • ARC-01',
                'is_digital' => true,
            ],
            [
                'title' => 'The Design of Everyday Things',
                'isbn' => '978-0465050659',
                'call_number' => 'TS171.4 .N67 2013',
                'accession_number' => 'ACC-2024-005',
                'author_key' => 'norman',
                'category_key' => 'design',
                'publisher' => 'Basic Books',
                'publication_year' => 2013,
                'pages' => 368,
                'description' => 'A timeless exploration of cognitive psychology and human-centered design, examining signifiers, affordances, mental models, and intuitive interfaces.',
                'total_copies' => 7,
                'available_copies' => 5,
                'location' => 'Arts & Architecture • Shelf DS-04',
                'is_digital' => true,
            ],
            [
                'title' => 'Linear Algebra and Its Applications',
                'isbn' => '978-0030105678',
                'call_number' => 'QA184 .S8 2006',
                'accession_number' => 'ACC-2024-006',
                'author_key' => 'strang',
                'category_key' => 'mathematics',
                'publisher' => 'Cengage Learning',
                'publication_year' => 2006,
                'pages' => 487,
                'description' => 'Clear exposition of vector spaces, matrix factorizations, eigenvalues, singular value decomposition, and computational mathematical modeling.',
                'total_copies' => 10,
                'available_copies' => 7,
                'location' => 'Science Wing • Shelf MATH-02',
                'is_digital' => true,
            ],
            [
                'title' => 'Cosmos: Evolution, Science and Civilisation',
                'isbn' => '978-0345331359',
                'call_number' => 'QB44.2 .S24 1980',
                'accession_number' => 'ACC-2024-007',
                'author_key' => 'sagan',
                'category_key' => 'physics',
                'publisher' => 'Ballantine Books',
                'publication_year' => 1980,
                'pages' => 384,
                'description' => 'A panoramic journey through cosmic history, tracing the development of scientific method, planetary exploration, and the origins of consciousness.',
                'total_copies' => 6,
                'available_copies' => 4,
                'location' => 'Science Wing • Shelf AST-01',
                'is_digital' => true,
            ],
            [
                'title' => 'Refactoring: Improving the Design of Existing Code',
                'isbn' => '978-0134757599',
                'call_number' => 'QA76.76.R42 F69 2018',
                'accession_number' => 'ACC-2024-008',
                'author_key' => 'fowler',
                'category_key' => 'computer-science',
                'publisher' => 'Addison-Wesley Professional',
                'publication_year' => 2018,
                'pages' => 448,
                'description' => 'Systematic catalog of code smells, transformation catalogs, test-driven restructuring techniques, and object-oriented architectural hygiene.',
                'total_copies' => 6,
                'available_copies' => 3,
                'location' => 'Main Stacks • Shelf CS-03',
                'is_digital' => true,
            ],
            [
                'title' => 'Principia Mathematica: Introduction to Logic',
                'isbn' => '978-0521067911',
                'call_number' => 'QA9 .R88 1962',
                'accession_number' => 'ACC-2024-009',
                'author_key' => 'russell',
                'category_key' => 'philosophy',
                'publisher' => 'Cambridge University Press',
                'publication_year' => 1962,
                'pages' => 410,
                'description' => 'A monumental milestone in mathematical logic, seeking to ground all mathematical truths in foundational axioms and propositional logic.',
                'total_copies' => 3,
                'available_copies' => 2,
                'location' => 'Philosophy & Humanities • Shelf PHI-01',
                'is_digital' => true,
            ],
            [
                'title' => 'Cybernetics: Or Control and Communication in the Animal and Machine',
                'isbn' => '978-0262730099',
                'call_number' => 'Q310 .W5 1965',
                'accession_number' => 'ACC-2024-010',
                'author_key' => 'wiener',
                'category_key' => 'artificial-intelligence',
                'publisher' => 'The MIT Press',
                'publication_year' => 1965,
                'pages' => 212,
                'description' => 'Pioneering inquiry into feedback loops, teleological mechanisms, automated computing, statistical mechanics, and adaptive systems.',
                'total_copies' => 4,
                'available_copies' => 2,
                'location' => 'Main Stacks • Shelf CYB-01',
                'is_digital' => true,
            ],
            [
                'title' => 'Program Development in Java: Abstraction and Modular Systems',
                'isbn' => '978-0201657685',
                'call_number' => 'QA76.73.J38 L57 2001',
                'accession_number' => 'ACC-2024-011',
                'author_key' => 'liskov',
                'category_key' => 'computer-science',
                'publisher' => 'Addison-Wesley Professional',
                'publication_year' => 2001,
                'pages' => 432,
                'description' => 'Detailed pedagogy on procedural abstraction, data abstraction, iteration abstractions, type hierarchies, and robust exception architectures.',
                'total_copies' => 5,
                'available_copies' => 3,
                'location' => 'Main Stacks • Shelf CS-04',
                'is_digital' => true,
            ],
            [
                'title' => 'Computing Machinery and Intelligence: Collected Papers',
                'isbn' => '978-0198236177',
                'call_number' => 'Q335 .T87 1992',
                'accession_number' => 'ACC-2024-012',
                'author_key' => 'turing',
                'category_key' => 'artificial-intelligence',
                'publisher' => 'Oxford University Press',
                'publication_year' => 1992,
                'pages' => 288,
                'description' => 'Turing\'s classical papers outlining the imitation game, machine cognition, morphogenesis, and uncomputable functions.',
                'total_copies' => 4,
                'available_copies' => 2,
                'location' => 'Special Collections • ARC-02',
                'is_digital' => true,
            ],
            [
                'title' => 'Patterns of Enterprise Application Architecture',
                'isbn' => '978-0321127426',
                'call_number' => 'QA76.76.P37 F69 2002',
                'accession_number' => 'ACC-2024-013',
                'author_key' => 'fowler',
                'category_key' => 'computer-science',
                'publisher' => 'Addison-Wesley Professional',
                'publication_year' => 2002,
                'pages' => 560,
                'description' => 'Handbook of domain logic patterns, data source architectural patterns, object-relational mapping behavior, and web presentation strategies.',
                'total_copies' => 5,
                'available_copies' => 4,
                'location' => 'Main Stacks • Shelf CS-05',
                'is_digital' => false,
            ],
            [
                'title' => 'QED: The Strange Theory of Light and Matter',
                'isbn' => '978-0691125756',
                'call_number' => 'QC793.5.P422 F49 1985',
                'accession_number' => 'ACC-2024-014',
                'author_key' => 'feynman',
                'category_key' => 'physics',
                'publisher' => 'Princeton University Press',
                'publication_year' => 1985,
                'pages' => 176,
                'description' => 'Feynman presents quantum electrodynamics for curious scholars with lucid explanations of photons, electrons, and probability amplitudes.',
                'total_copies' => 5,
                'available_copies' => 3,
                'location' => 'Science Wing • Shelf PH-04',
                'is_digital' => false,
            ],
            [
                'title' => 'Computational Differential Equations and Approximation',
                'isbn' => '978-0521634038',
                'call_number' => 'QA371 .S87 1996',
                'accession_number' => 'ACC-2024-015',
                'author_key' => 'strang',
                'category_key' => 'mathematics',
                'publisher' => 'Wellesley-Cambridge Press',
                'publication_year' => 1996,
                'pages' => 520,
                'description' => 'In-depth coverage of boundary value problems, finite element analysis, Fourier series, and scientific computation.',
                'total_copies' => 6,
                'available_copies' => 4,
                'location' => 'Science Wing • Shelf MATH-03',
                'is_digital' => false,
            ],
            [
                'title' => 'Pale Blue Dot: A Vision of the Human Future in Space',
                'isbn' => '978-0345376596',
                'call_number' => 'QB500 .S24 1994',
                'accession_number' => 'ACC-2024-016',
                'author_key' => 'sagan',
                'category_key' => 'physics',
                'publisher' => 'Random House',
                'publication_year' => 1994,
                'pages' => 448,
                'description' => 'Reflections on human exploration beyond Earth, interstellar probes, climate responsibility, and our tiny planetary presence.',
                'total_copies' => 4,
                'available_copies' => 3,
                'location' => 'Science Wing • Shelf AST-02',
                'is_digital' => false,
            ],
        ];

        $books = [];
        foreach ($bookDefinitions as $item) {
            $author = $authors[$item['author_key']];
            $category = $categories[$item['category_key']];

            $book = Book::query()->firstOrCreate(
                ['title' => $item['title']],
                [
                    'isbn' => $item['isbn'],
                    'call_number' => $item['call_number'],
                    'accession_number' => $item['accession_number'],
                    'author_id' => $author->id,
                    'category_id' => $category->id,
                    'publisher' => $item['publisher'],
                    'publication_year' => $item['publication_year'],
                    'pages' => $item['pages'],
                    'description' => $item['description'],
                    'total_copies' => $item['total_copies'],
                    'available_copies' => $item['available_copies'],
                    'location' => $item['location'],
                    'status' => 'available',
                ]
            );

            $book->setAttribute('seed_is_digital', $item['is_digital']);
            $books[] = $book;
        }

        return $books;
    }

    /**
     * @param  array<int, Book>  $books
     */
    private function seedDigitalEditions(array $books): void
    {
        $adminUser = User::query()->first();

        foreach ($books as $book) {
            if (! $book->getAttribute('seed_is_digital')) {
                continue;
            }

            $disk = (string) config('librarysystem.ebooks.disk', 'library');
            $relativePath = sprintf('books/%d/%s.pdf', $book->id, Str::uuid()->toString());

            // Write minimal valid PDF object to private disk so reader can serve it
            Storage::disk($disk)->put($relativePath, self::MINIMAL_PDF);

            DigitalEdition::query()->updateOrCreate(
                ['book_id' => $book->id],
                [
                    'disk' => $disk,
                    'path' => $relativePath,
                    'original_name' => Str::slug($book->title).'.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => mb_strlen(self::MINIMAL_PDF),
                    'sha256' => hash('sha256', self::MINIMAL_PDF),
                    'status' => DigitalEditionStatus::Published->value,
                    'downloads_allowed' => true,
                    'rights_basis' => DigitalRightsBasis::OpenLicense->value,
                    'rights_holder' => 'KoAkademy Scholarly Commons',
                    'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                    'rights_notes' => 'Open institutional license cleared for educational reading and scholar research.',
                    'rights_expires_at' => null,
                    'uploaded_by' => $adminUser?->id,
                    'uploaded_at' => now()->subMonths(2),
                    'published_by' => $adminUser?->id,
                    'published_at' => now()->subMonths(2),
                    'rights_confirmed_by' => $adminUser?->id,
                    'rights_confirmed_at' => now()->subMonths(2),
                ]
            );
        }
    }

    /**
     * @param  array<int, Book>  $books
     */
    private function seedBorrowRecords(array $books): void
    {
        $users = User::query()->take(6)->get();
        if ($users->isEmpty()) {
            return;
        }

        $borrowScenarios = [
            // Returned records
            [
                'book_index' => 0,
                'user_index' => 0,
                'status' => 'returned',
                'borrowed_at' => now()->subDays(20),
                'due_date' => now()->subDays(6),
                'returned_at' => now()->subDays(7),
                'fine_amount' => 0.00,
            ],
            [
                'book_index' => 1,
                'user_index' => 1,
                'status' => 'returned',
                'borrowed_at' => now()->subDays(15),
                'due_date' => now()->subDays(1),
                'returned_at' => now()->subDays(2),
                'fine_amount' => 0.00,
            ],
            // Active on-schedule borrow
            [
                'book_index' => 2,
                'user_index' => 2 % $users->count(),
                'status' => 'borrowed',
                'borrowed_at' => now()->subDays(4),
                'due_date' => now()->addDays(10),
                'returned_at' => null,
                'fine_amount' => 0.00,
            ],
            [
                'book_index' => 3,
                'user_index' => 3 % $users->count(),
                'status' => 'borrowed',
                'borrowed_at' => now()->subDays(2),
                'due_date' => now()->addDays(12),
                'returned_at' => null,
                'fine_amount' => 0.00,
            ],
            // Overdue records (for overdue alert showcase)
            [
                'book_index' => 4,
                'user_index' => 4 % $users->count(),
                'status' => 'borrowed',
                'borrowed_at' => now()->subDays(18),
                'due_date' => now()->subDays(4),
                'returned_at' => null,
                'fine_amount' => 25.00,
                'notes' => 'Overdue notice delivered via email notification.',
            ],
            [
                'book_index' => 5,
                'user_index' => 5 % $users->count(),
                'status' => 'borrowed',
                'borrowed_at' => now()->subDays(22),
                'due_date' => now()->subDays(8),
                'returned_at' => null,
                'fine_amount' => 50.00,
                'notes' => 'Second reminder dispatched; physical copy pending return.',
            ],
        ];

        foreach ($borrowScenarios as $scenario) {
            if (! isset($books[$scenario['book_index']]) || ! isset($users[$scenario['user_index']])) {
                continue;
            }

            $book = $books[$scenario['book_index']];
            $user = $users[$scenario['user_index']];

            BorrowRecord::query()->firstOrCreate(
                [
                    'book_id' => $book->id,
                    'user_id' => $user->id,
                    'borrowed_at' => $scenario['borrowed_at'],
                ],
                [
                    'due_date' => $scenario['due_date'],
                    'returned_at' => $scenario['returned_at'],
                    'status' => $scenario['status'],
                    'fine_amount' => $scenario['fine_amount'],
                    'notes' => $scenario['notes'] ?? null,
                ]
            );
        }
    }

    /**
     * @param  array<int, Book>  $books
     */
    private function seedUserBookStates(array $books): void
    {
        $users = User::query()->take(3)->get();
        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            // Seed favorites for books 0, 1, 2, 4
            $favoriteIndices = [0, 1, 2, 4];
            foreach ($favoriteIndices as $idx) {
                if (! isset($books[$idx])) {
                    continue;
                }
                UserBookState::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'book_id' => $books[$idx]->id,
                    ],
                    [
                        'favorited_at' => now()->subDays(random_int(1, 14)),
                        'last_read_at' => now()->subHours(random_int(2, 72)),
                        'last_page' => random_int(12, 120),
                        'total_pages' => $books[$idx]->pages ?? 350,
                    ]
                );
            }

            // Seed recently read books 3, 5
            $recentIndices = [3, 5];
            foreach ($recentIndices as $idx) {
                if (! isset($books[$idx])) {
                    continue;
                }
                UserBookState::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'book_id' => $books[$idx]->id,
                    ],
                    [
                        'last_read_at' => now()->subMinutes(random_int(15, 300)),
                        'last_page' => random_int(5, 50),
                        'total_pages' => $books[$idx]->pages ?? 280,
                    ]
                );
            }
        }
    }

    private function seedResearchPapers(): void
    {
        $course = Course::query()->first();
        $student = Student::query()->first();

        $papers = [
            [
                'title' => 'Autonomous Quadruped Navigation in Dynamic Campus Environments',
                'type' => 'capstone',
                'advisor_name' => 'Dr. Eleanor Vance, Ph.D.',
                'contributors' => 'Marcus Chen, Sophia Alcantara',
                'abstract' => 'This capstone presents an integrated perception and locomotion architecture for autonomous quadruped robots navigating crowded academic corridors and stairs.',
                'keywords' => 'robotics, computer vision, slam, reinforcement learning, obstacle avoidance',
                'publication_year' => 2024,
                'status' => 'submitted',
                'is_public' => true,
                'notes' => 'Recipient of Best Engineering Capstone Award 2024.',
            ],
            [
                'title' => 'Mitigating Microarchitectural Speculative Side-Channel Leakage in Embedded RISC-V',
                'type' => 'thesis',
                'advisor_name' => 'Prof. Raymond Thorne',
                'contributors' => 'Darius Vance, Liam Gallagher',
                'abstract' => 'An evaluation of hardware barrier primitives and cache partition techniques to eliminate transient execution vulnerabilities on open-source RISC-V implementations.',
                'keywords' => 'computer architecture, hardware security, speculative execution, risc-v',
                'publication_year' => 2024,
                'status' => 'archived',
                'is_public' => true,
                'notes' => 'Published in the KoAkademy Institutional Research Repository.',
            ],
            [
                'title' => 'Decentralized Verifiable Credentials for Cross-Institution Academic Transfer',
                'type' => 'thesis',
                'advisor_name' => 'Dr. Sarah Lin-Ocampo',
                'contributors' => 'Aria Patel, Mateo Rossi',
                'abstract' => 'A privacy-preserving cryptographic framework utilizing zero-knowledge proofs to authenticate student transcript records without centralized certificate authorities.',
                'keywords' => 'cryptography, zero-knowledge proofs, academic records, verifiable credentials',
                'publication_year' => 2023,
                'status' => 'archived',
                'is_public' => true,
                'notes' => 'Faculty commended defense.',
            ],
            [
                'title' => 'Self-Supervised Representation Learning for High-Resolution Histopathology Screening',
                'type' => 'research',
                'advisor_name' => 'Dr. Victor Morales',
                'contributors' => 'Hannah De Silva, Lucas Meyer',
                'abstract' => 'Investigates vision transformer backbones trained on gigapixel whole-slide biopsy scans to detect early-stage tissue anomalies without requiring dense pixel-level annotations.',
                'keywords' => 'deep learning, biomedical informatics, self-supervised learning, pathology',
                'publication_year' => 2024,
                'status' => 'submitted',
                'is_public' => true,
                'notes' => 'Collaborative submission with Regional Medical Center.',
            ],
            [
                'title' => 'Energy-Efficient Federated Optimization in Resource-Constrained Wireless Sensor Meshes',
                'type' => 'capstone',
                'advisor_name' => 'Prof. Elena Rostova',
                'contributors' => 'Kaito Takahashi, Chloe Dupont',
                'abstract' => 'Examines quantization and gradient compression mechanisms for on-device machine learning inference in battery-powered environmental monitoring networks.',
                'keywords' => 'federated learning, edge computing, iot, wireless sensor networks',
                'publication_year' => 2023,
                'status' => 'draft',
                'is_public' => false,
                'notes' => 'Undergoing final review with department defense committee.',
            ],
        ];

        foreach ($papers as $paper) {
            ResearchPaper::query()->firstOrCreate(
                ['title' => $paper['title']],
                [
                    'type' => $paper['type'],
                    'student_id' => $student?->id,
                    'course_id' => $course?->id,
                    'advisor_name' => $paper['advisor_name'],
                    'contributors' => $paper['contributors'],
                    'abstract' => $paper['abstract'],
                    'keywords' => $paper['keywords'],
                    'publication_year' => $paper['publication_year'],
                    'status' => $paper['status'],
                    'is_public' => $paper['is_public'],
                    'notes' => $paper['notes'],
                ]
            );
        }
    }
}
