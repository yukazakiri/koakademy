<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;
use Modules\LibrarySystem\Models\ResearchPaper;
use Spatie\Tags\Tag;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    actingAs($this->admin);
});

it('renders the library overview', function (): void {
    get(route('administrators.library.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/library/index')
            ->has('stats')
            ->has('recent')
        );
});

it('creates a library book', function (): void {
    Storage::fake('public');

    $author = Author::create([
        'name' => 'Jane Austen',
    ]);
    $category = Category::create([
        'name' => 'Literature',
        'color' => '#2563eb',
    ]);

    $payload = [
        'title' => 'Sense and Sensibility',
        'isbn' => '9780141439662',
        'author_id' => $author->id,
        'category_id' => $category->id,
        'publisher' => 'Penguin Classics',
        'publication_year' => 1811,
        'pages' => 384,
        'description' => 'Classic novel about the Dashwood sisters.',
        'cover_image' => 'https://example.com/cover.jpg',
        'cover_image_upload' => UploadedFile::fake()->image('cover.jpg'),
        'total_copies' => 3,
        'available_copies' => 3,
        'location' => 'Shelf A2',
        'status' => 'available',
    ];

    post(route('administrators.library.books.store'), $payload)
        ->assertRedirect(route('administrators.library.books.index'));

    assertDatabaseHas('library_books', [
        'title' => 'Sense and Sensibility',
        'isbn' => '9780141439662',
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);

    $book = Book::query()->firstOrFail();
    expect($book->cover_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($book->cover_image_path);
});

it('creates a borrow record and updates availability', function (): void {
    $author = Author::create([
        'name' => 'Harper Lee',
    ]);
    $category = Category::create([
        'name' => 'Fiction',
        'color' => '#16a34a',
    ]);
    $book = Book::create([
        'title' => 'To Kill a Mockingbird',
        'author_id' => $author->id,
        'category_id' => $category->id,
        'total_copies' => 2,
        'available_copies' => 2,
        'status' => 'available',
    ]);

    $payload = [
        'book_id' => $book->id,
        'user_id' => $this->admin->id,
        'borrowed_at' => now()->toDateTimeString(),
        'due_date' => now()->addDays(7)->toDateTimeString(),
        'status' => 'borrowed',
        'fine_amount' => 0,
        'notes' => 'First loan',
    ];

    post(route('administrators.library.borrow-records.store'), $payload)
        ->assertRedirect(route('administrators.library.borrow-records.index'));

    $book->refresh();
    expect($book->available_copies)->toBe(1);

    assertDatabaseHas('library_borrow_records', [
        'book_id' => $book->id,
        'user_id' => $this->admin->id,
        'status' => 'borrowed',
    ]);
});

it('creates a research paper record', function (): void {
    Storage::fake('public');
    activity()->disableLogging();

    $student = Student::factory()->create([
        'document_location_id' => null,
    ]);
    $coAuthor = Student::factory()->create([
        'document_location_id' => null,
    ]);

    $payload = [
        'title' => 'Predictive Analytics for Enrollment',
        'type' => 'capstone',
        'student_ids' => [$student->id, $coAuthor->id],
        'course_id' => $student->course_id,
        'advisor_name' => 'Prof. Rivera',
        'contributors' => 'Team Alpha',
        'abstract' => 'Research on enrollment forecasting using historical data.',
        'tags' => [' analytics ', 'forecasting'],
        'keywords' => 'analytics, enrollment, forecasting',
        'publication_year' => 2024,
        'document_url' => 'https://example.com/research.pdf',
        'document_upload' => UploadedFile::fake()->create('research.pdf', 1200, 'application/pdf'),
        'cover_image_upload' => UploadedFile::fake()->image('research-cover.jpg'),
        'status' => 'draft',
        'is_public' => true,
        'notes' => 'Needs librarian review.',
    ];

    post(route('administrators.library.research-papers.store'), $payload)
        ->assertRedirect(route('administrators.library.research-papers.index'));

    assertDatabaseHas('library_research_papers', [
        'title' => 'Predictive Analytics for Enrollment',
        'type' => 'capstone',
        'status' => 'draft',
        'is_public' => true,
    ]);

    $paper = ResearchPaper::query()->firstOrFail();
    $tagNames = $paper->tags->pluck('name')->all();

    assertDatabaseHas('library_research_paper_student', [
        'research_paper_id' => $paper->id,
        'student_id' => $student->id,
    ]);
    assertDatabaseHas('library_research_paper_student', [
        'research_paper_id' => $paper->id,
        'student_id' => $coAuthor->id,
    ]);

    expect($paper->student_id)->toBe($student->id);
    expect($paper->document_path)->not->toBeNull();
    expect($paper->cover_image_path)->not->toBeNull();
    expect(Tag::findFromString('analytics'))->not->toBeNull();
    expect(Tag::findFromString('forecasting'))->not->toBeNull();
    expect($tagNames)->toContain('analytics');
    expect($tagNames)->toContain('forecasting');
    Storage::disk('public')->assertExists($paper->document_path);
    Storage::disk('public')->assertExists($paper->cover_image_path);
});
