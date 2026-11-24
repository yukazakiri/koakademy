<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LibrarySystem\Enums\DigitalEditionStatus;
use Modules\LibrarySystem\Enums\DigitalRightsBasis;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;
use Modules\LibrarySystem\Models\DigitalEdition;
use Modules\LibrarySystem\Models\LibraryBookmark;
use Modules\LibrarySystem\Models\UserBookState;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config([
        'activitylog.enabled' => false,
        'inertia.testing.ensure_pages_exist' => false,
        'librarysystem.ebooks.disk' => 'library-test',
        'filesystems.disks.library-test' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/library-test'),
            'throw' => true,
        ],
    ]);

    GeneralSetting::factory()->create([
        'library_module_enabled' => true,
        'is_setup' => true,
    ]);
});

dataset('digital library authenticated roles', collect(UserRole::cases())
    ->mapWithKeys(fn (UserRole $role): array => [$role->value => [$role]])
    ->all());

it('allows guests to browse the catalog and read published eBooks online without an account', function (): void {
    Storage::fake('library-test');
    $book = createDigitalLibraryBook(['title' => 'Open Access Handbook']);
    $edition = createPublishedDigitalEdition($book, downloadsAllowed: true);
    $pdfContent = "%PDF-1.4\n%KoAkademy open reader fixture content for byte range streaming";
    Storage::disk('library-test')->put($edition->path, $pdfContent);

    $this->get(route('library.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('library/index', false)
            ->where('books.total', 1)
            ->where('access.is_authenticated', false)
            ->where('books.data.0.title', 'Open Access Handbook')
            ->where('books.data.0.available_online', true)
            ->where('books.data.0.downloads_allowed', true)
            ->missing('books.data.0.path')
            ->missing('books.data.0.disk')
            ->missing('books.data.0.sha256'));

    $this->get(route('library.books.show', $book))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('library/show', false)
            ->where('access.is_authenticated', false)
            ->where('access.can_download', false)
            ->where('access.download_requires_auth', true));

    $this->get(route('library.books.read', $book))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('library/read', false)
            ->where('reader.is_guest', true)
            ->where('reader.sync_enabled', false)
            ->where('reader.can_download', false)
            ->where('reader.download_requires_auth', true)
            ->where('reader.download_url', null));

    $contentResponse = $this->get(route('library.books.content', $book))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('x-robots-tag', 'noindex, nofollow');

    expect((string) $contentResponse->headers->get('content-disposition'))->toStartWith('inline;');

    $rangeResponse = $this->withHeaders(['Range' => 'bytes=0-15'])
        ->get(route('library.books.content', $book));

    $rangeResponse->assertStatus(206);
    expect((string) $rangeResponse->headers->get('content-range'))->toStartWith('bytes 0-15/');
});

it('requires an authenticated account for PDF downloads, authenticate bounce, favorites, progress, and bookmarks', function (): void {
    Storage::fake('library-test');
    $book = createDigitalLibraryBook();
    $edition = createPublishedDigitalEdition($book, downloadsAllowed: true);
    Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nProtected download fixture");

    $this->get(route('library.books.download', $book))->assertRedirect('/login');
    $this->get(route('library.books.authenticate', $book))->assertRedirect('/login');
    $this->post(route('library.books.favorite', $book))->assertRedirect('/login');
    $this->delete(route('library.books.unfavorite', $book))->assertRedirect('/login');

    $this->putJson(route('library.books.progress', $book), [
        'last_page' => 2,
        'total_pages' => 10,
    ])->assertUnauthorized();

    $this->postJson(route('library.books.bookmarks.store', $book), [
        'page' => 2,
        'label' => 'Page 2',
    ])->assertUnauthorized();
});

it('bounces authenticated users from the authenticate endpoint back to the book or reader ready to download', function (): void {
    Storage::fake('library-test');
    $user = User::factory()->create(['role' => UserRole::Student]);
    $book = createDigitalLibraryBook();
    $edition = createPublishedDigitalEdition($book, downloadsAllowed: true);
    Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nBounce fixture");

    actingAs($user)
        ->get(route('library.books.authenticate', $book))
        ->assertRedirect(route('library.books.show', ['book' => $book, 'action' => 'download']));

    actingAs($user)
        ->get(route('library.books.authenticate', ['book' => $book, 'from' => 'read']))
        ->assertRedirect(route('library.books.read', ['book' => $book, 'action' => 'download']));
});

it('allows every authenticated role to browse the digital library', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)
        ->get(route('library.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('library/index', false)
            ->where('books.total', 0));
})->with('digital library authenticated roles');

it('returns forbidden and hides access when the library module is disabled for both guests and authenticated users', function (): void {
    GeneralSetting::query()->firstOrFail()->update(['library_module_enabled' => false]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $book = createDigitalLibraryBook();
    createPublishedDigitalEdition($book);

    $this->get(route('library.index'))->assertForbidden();
    $this->get(route('library.books.read', $book))->assertForbidden();
    $this->get(route('library.books.content', $book))->assertForbidden();

    actingAs($user)
        ->get(route('library.index'))
        ->assertForbidden();

    actingAs($user)
        ->get(route('library.books.content', $book))
        ->assertForbidden();
});

it('shows the full catalog while distinguishing published digital editions', function (): void {
    Storage::fake('library-test');
    $user = User::factory()->create(['role' => UserRole::Student]);
    $catalogOnly = createDigitalLibraryBook(['title' => 'Catalog Only Book']);
    $online = createDigitalLibraryBook(['title' => 'Online Book']);
    createPublishedDigitalEdition($online);

    actingAs($user)
        ->get(route('library.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('library/index', false)
            ->where('books.total', 2)
            ->where('stats.catalog_books', 2)
            ->where('stats.available_online', 1)
            ->has('books.data', 2)
            ->where('books.data.0.title', $catalogOnly->title)
            ->where('books.data.0.available_online', false)
            ->where('books.data.1.title', $online->title)
            ->where('books.data.1.available_online', true)
            ->missing('books.data.1.path')
            ->missing('books.data.1.disk')
            ->missing('books.data.1.sha256'));
});

it('filters the catalog by search category and digital availability', function (): void {
    $user = User::factory()->create(['role' => UserRole::Professor]);
    $online = createDigitalLibraryBook(['title' => 'Modern Physics Handbook']);
    createPublishedDigitalEdition($online);
    createDigitalLibraryBook(['title' => 'World Literature Reader']);

    actingAs($user)
        ->get(route('library.index', [
            'search' => 'Physics',
            'category_id' => $online->category_id,
            'availability' => 'online',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('books.total', 1)
            ->where('books.data.0.id', $online->id)
            ->where('filters.availability', 'online'));
});

it('lets users favorite books and keeps each users state private', function (): void {
    $book = createDigitalLibraryBook();
    $student = User::factory()->create(['role' => UserRole::Student]);
    $otherStudent = User::factory()->create(['role' => UserRole::Student]);

    actingAs($student)
        ->post(route('library.books.favorite', $book))
        ->assertRedirect();

    expect(UserBookState::query()
        ->where('user_id', $student->id)
        ->where('book_id', $book->id)
        ->whereNotNull('favorited_at')
        ->exists())->toBeTrue();

    actingAs($otherStudent)
        ->get(route('library.index', ['collection' => 'favorites']))
        ->assertInertia(fn (Assert $page) => $page->where('books.total', 0));
});

it('saves reading progress and protects bookmarks from IDOR', function (): void {
    $book = createDigitalLibraryBook();
    createPublishedDigitalEdition($book);
    $student = User::factory()->create(['role' => UserRole::Student]);
    $otherStudent = User::factory()->create(['role' => UserRole::Student]);

    actingAs($student)
        ->putJson(route('library.books.progress', $book), [
            'last_page' => 12,
            'total_pages' => 120,
        ])
        ->assertSuccessful()
        ->assertJsonPath('last_page', 12);

    $bookmarkResponse = actingAs($student)
        ->postJson(route('library.books.bookmarks.store', $book), [
            'page' => 12,
            'label' => 'Key argument',
        ])
        ->assertSuccessful();

    $bookmark = LibraryBookmark::query()->findOrFail($bookmarkResponse->json('id'));

    actingAs($otherStudent)
        ->deleteJson(route('library.books.bookmarks.destroy', [$book, $bookmark]))
        ->assertNotFound();

    expect($bookmark->fresh())->not->toBeNull();
});

it('serves published local PDFs inline and blocks downloads by default', function (): void {
    Storage::fake('library-test');
    $book = createDigitalLibraryBook();
    $edition = createPublishedDigitalEdition($book, downloadsAllowed: false);
    Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nDigital library fixture");
    $student = User::factory()->create(['role' => UserRole::Student]);

    actingAs($student)
        ->get(route('library.books.content', $book))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    actingAs($student)
        ->get(route('library.books.download', $book))
        ->assertForbidden();
});

it('redirects authorized content and downloads to short-lived private storage URLs', function (): void {
    Storage::fake('library-test');
    config(['filesystems.disks.library-test.driver' => 's3']);

    $book = createDigitalLibraryBook(['title' => 'Licensed Download']);
    $edition = createPublishedDigitalEdition($book, downloadsAllowed: true);
    Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nPrivate URL fixture");
    $student = User::factory()->create(['role' => UserRole::Student]);
    $requests = [];

    Storage::disk('library-test')->buildTemporaryUrlsUsing(
        function (string $path, DateTimeInterface $expiresAt, array $options) use (&$requests): string {
            $requests[] = compact('path', 'expiresAt', 'options');

            return 'https://private-library.example.test/'.count($requests);
        }
    );

    actingAs($student)
        ->get(route('library.books.content', $book))
        ->assertRedirect('https://private-library.example.test/1');

    actingAs($student)
        ->get(route('library.books.download', $book))
        ->assertRedirect('https://private-library.example.test/2');

    expect($requests)->toHaveCount(2)
        ->and($requests[0]['path'])->toBe($edition->path)
        ->and($requests[0]['options']['ResponseContentDisposition'])->toStartWith('inline;')
        ->and($requests[1]['options']['ResponseContentDisposition'])->toStartWith('attachment;')
        ->and($requests[0]['expiresAt']->getTimestamp())->toBeGreaterThan(now()->addMinutes(59)->getTimestamp())
        ->and($requests[1]['expiresAt']->getTimestamp())->toBeLessThanOrEqual(now()->addMinutes(5)->getTimestamp());
});

it('never signs a private URL beyond the recorded rights expiration', function (): void {
    Storage::fake('library-test');
    config(['filesystems.disks.library-test.driver' => 's3']);

    $book = createDigitalLibraryBook();
    $edition = createPublishedDigitalEdition($book);
    $rightsExpireAt = now()->addMinutes(2);
    $edition->update(['rights_expires_at' => $rightsExpireAt]);
    Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nExpiring rights fixture");
    $student = User::factory()->create(['role' => UserRole::Student]);
    $signedUntil = null;

    Storage::disk('library-test')->buildTemporaryUrlsUsing(
        function (string $path, DateTimeInterface $expiresAt) use (&$signedUntil): string {
            $signedUntil = $expiresAt;

            return 'https://private-library.example.test/expiring';
        }
    );

    actingAs($student)
        ->get(route('library.books.content', $book))
        ->assertRedirect('https://private-library.example.test/expiring');

    expect($signedUntil)->not->toBeNull()
        ->and($signedUntil->getTimestamp())->toBeLessThanOrEqual($rightsExpireAt->getTimestamp());
});

it('returns not found for draft expired and missing digital editions', function (string $state): void {
    Storage::fake('library-test');
    $book = createDigitalLibraryBook();
    $edition = createPublishedDigitalEdition($book);
    $student = User::factory()->create(['role' => UserRole::Student]);

    if ($state === 'draft') {
        $edition->update(['status' => DigitalEditionStatus::Draft]);
        Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nDraft");
    } elseif ($state === 'expired') {
        $edition->update(['rights_expires_at' => now()->subMinute()]);
        Storage::disk('library-test')->put($edition->path, "%PDF-1.4\nExpired");
    }

    $this->get(route('library.books.read', $book))->assertNotFound();
    $this->get(route('library.books.content', $book))->assertNotFound();

    actingAs($student)
        ->get(route('library.books.content', $book))
        ->assertNotFound();
})->with(['draft', 'expired', 'missing']);

function createDigitalLibraryBook(array $attributes = []): Book
{
    $author = Author::query()->create([
        'name' => 'Author '.fake()->unique()->numerify('###'),
    ]);
    $category = Category::query()->create([
        'name' => 'Category '.fake()->unique()->numerify('###'),
        'color' => '#B45309',
    ]);

    return Book::query()->create(array_merge([
        'title' => 'Library Book '.fake()->unique()->numerify('####'),
        'author_id' => $author->id,
        'category_id' => $category->id,
        'description' => 'A catalog description for digital library testing.',
        'total_copies' => 1,
        'available_copies' => 1,
        'status' => 'available',
    ], $attributes));
}

function createPublishedDigitalEdition(Book $book, bool $downloadsAllowed = false): DigitalEdition
{
    return DigitalEdition::query()->create([
        'book_id' => $book->id,
        'disk' => 'library-test',
        'path' => "books/{$book->id}/fixture.pdf",
        'original_name' => 'fixture.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 32,
        'sha256' => str_repeat('a', 64),
        'status' => DigitalEditionStatus::Published,
        'downloads_allowed' => $downloadsAllowed,
        'rights_basis' => DigitalRightsBasis::KoakademyOwned,
        'rights_holder' => 'KoAkademy',
        'uploaded_at' => now(),
        'published_at' => now(),
        'rights_confirmed_at' => now(),
    ]);
}
