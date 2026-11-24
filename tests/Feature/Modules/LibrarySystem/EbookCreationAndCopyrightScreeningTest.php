<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LibrarySystem\Ai\Agents\LibraryDocumentCatalogAgent;
use Modules\LibrarySystem\Enums\BookFormat;
use Modules\LibrarySystem\Enums\DigitalEditionStatus;
use Modules\LibrarySystem\Enums\DigitalEditionVisibility;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;
use Modules\LibrarySystem\Models\DigitalEdition;

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
    Storage::fake('library-test');

    GeneralSetting::factory()->create([
        'library_module_enabled' => true,
        'is_setup' => true,
    ]);
});

it('creates an ebook-only book without physical copies and excludes it from physical borrow options', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $author = Author::factory()->create(['name' => 'Ada Lovelace']);
    $category = Category::factory()->create(['name' => 'Computer Science']);

    actingAs($admin)
        ->get(route('administrators.library.books.create', ['format' => 'ebook']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administrators/library/books/edit', false)
            ->where('initial_format', 'ebook')
            ->where('can_manage_digital_edition', true));

    actingAs($admin)
        ->post(route('administrators.library.books.store'), [
            'format' => 'ebook',
            'title' => 'Pure Digital Compendium',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'publication_year' => 2024,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('administrators.library.books.index'));

    $ebook = Book::query()->where('title', 'Pure Digital Compendium')->firstOrFail();

    expect($ebook->format)->toBe(BookFormat::Ebook)
        ->and($ebook->total_copies)->toBe(0)
        ->and($ebook->available_copies)->toBe(0)
        ->and($ebook->status)->toBe('available')
        ->and($ebook->isEbookOnly())->toBeTrue();

    // Filterable via ebook_only in admin catalog
    actingAs($admin)
        ->get(route('administrators.library.books.index', ['digital' => 'ebook_only']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.id', $ebook->id)
            ->where('books.data.0.format', 'ebook'));

    // Excluded from physical borrow dropdown
    $physical = Book::factory()->create([
        'title' => 'Shelf Copy Book',
        'format' => BookFormat::Physical,
        'total_copies' => 2,
        'available_copies' => 2,
        'status' => 'available',
    ]);

    actingAs($admin)
        ->get(route('administrators.library.borrow-records.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('options.books', 1)
            ->where('options.books.0.value', $physical->id));
});

it('creates a book and draft digital edition in one step and redirects to the digital edition review panel', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $author = Author::factory()->create(['name' => 'Grace Hopper']);
    $category = Category::factory()->create(['name' => 'Engineering']);

    $pdfBytes = buildValidTestPdf(
        'OpenStax Calculus Volume 1. Licensed under Creative Commons CC BY 4.0 https://creativecommons.org/licenses/by/4.0/',
        [
            'Title' => 'Open Calculus',
            'Author' => 'Grace Hopper',
            'Subject' => 'Open textbook for undergraduate calculus.',
        ]
    );

    $response = actingAs($librarian)
        ->post(route('administrators.library.books.store'), [
            'format' => 'ebook',
            'title' => 'Open Calculus',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'publication_year' => 2024,
            'digital_pdf' => UploadedFile::fake()->createWithContent('open-calculus.pdf', $pdfBytes),
            'digital_rights_basis' => 'open_license',
            'digital_license_url' => 'https://creativecommons.org/licenses/by/4.0/',
        ]);

    $book = Book::query()->where('title', 'Open Calculus')->firstOrFail();

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('administrators.library.books.edit', $book).'#digital-edition');

    $edition = $book->digitalEdition()->firstOrFail();

    expect($edition->status)->toBe(DigitalEditionStatus::Draft)
        ->and($edition->downloads_allowed)->toBeFalse()
        ->and($edition->rights_confirmed_at)->toBeNull()
        ->and($edition->rights_risk_level)->toBe('open')
        ->and($edition->isPiracyBlocked())->toBeFalse()
        ->and($edition->rights_scan)->toBeArray()
        ->and($edition->rights_scan['risk_level'])->toBe('open');

    Storage::disk('library-test')->assertExists($edition->path);
});

it('inspects uploaded PDFs for metadata, missing fields, and open-license signals', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $author = Author::factory()->create(['name' => 'Maria Clara']);

    $richPdf = buildValidTestPdf(
        "Published by OpenStax in 2023. ISBN 978-1-947172-13-5.\nCreative Commons CC BY 4.0 https://creativecommons.org/licenses/by/4.0/",
        [
            'Title' => 'Principles of Open Education',
            'Author' => 'Maria Clara',
            'Subject' => 'An open access textbook on modern pedagogy.',
        ]
    );

    actingAs($librarian)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('principles.pdf', $richPdf),
        ])
        ->assertOk()
        ->assertJsonPath('metadata.title', 'Principles of Open Education')
        ->assertJsonPath('metadata.matched_author_id', $author->id)
        ->assertJsonPath('metadata.isbn', '9781947172135')
        ->assertJsonPath('metadata.publication_year', 2023)
        ->assertJsonPath('metadata.pages', 1)
        ->assertJsonPath('needs_metadata_review', false)
        ->assertJsonPath('rights_scan.risk_level', 'open')
        ->assertJsonPath('rights_scan.piracy_blocked', false)
        ->assertJsonPath('rights_scan.suggested_rights.rights_basis', 'open_license')
        ->assertJsonPath('rights_scan.suggested_rights.license_url', 'https://creativecommons.org/licenses/by/4.0/');

    // Minimal PDF without embedded metadata triggers needs_metadata_review and image_only_scan
    $blankPdf = buildValidTestPdf('', []);

    actingAs($librarian)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('scan_001.pdf', $blankPdf),
        ])
        ->assertOk()
        ->assertJsonPath('needs_metadata_review', true)
        ->assertJsonPath('rights_scan.risk_level', 'review')
        ->assertJsonPath('rights_scan.image_only_scan', true);

    // Non-PDF rejected
    actingAs($librarian)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('fake.pdf', 'not a valid pdf'),
        ])
        ->assertUnprocessable();

    // Unauthorized role forbidden
    $student = User::factory()->create(['role' => UserRole::Student]);
    actingAs($student)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('principles.pdf', $richPdf),
        ])
        ->assertForbidden();
});

it('hard-blocks publishing and public access when a piracy-site watermark is detected', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $author = Author::factory()->create();
    $category = Category::factory()->create();

    $piratedPdf = buildValidTestPdf(
        'OceanofPDF.com - Downloaded from Z-Library. All rights reserved.',
        [
            'Title' => 'Pirated Textbook',
        ]
    );

    actingAs($librarian)
        ->post(route('administrators.library.books.store'), [
            'format' => 'ebook',
            'title' => 'Pirated Textbook',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'digital_pdf' => UploadedFile::fake()->createWithContent('pirated.pdf', $piratedPdf),
        ])
        ->assertRedirect();

    $book = Book::query()->where('title', 'Pirated Textbook')->firstOrFail();
    $edition = $book->digitalEdition()->firstOrFail();

    expect($edition->status)->toBe(DigitalEditionStatus::Draft)
        ->and($edition->rights_risk_level)->toBe('critical')
        ->and($edition->isPiracyBlocked())->toBeTrue()
        ->and($edition->rights_scan['piracy_sources'])->toContain('oceanofpdf', 'z-library');

    // Attempt to publish MUST be hard-blocked even with full attestation and notes
    actingAs($librarian)
        ->put(route('administrators.library.books.digital-edition.update', $book), [
            'status' => 'published',
            'downloads_allowed' => false,
            'rights_basis' => 'written_permission',
            'rights_holder' => 'Someone',
            'rights_notes' => 'Trying to override piracy block.',
            'rights_confirmed' => true,
        ])
        ->assertSessionHasErrors('status');

    expect($edition->refresh()->status)->toBe(DigitalEditionStatus::Draft);

    // Defense-in-depth: even if DB status were forced to published, public endpoints refuse access
    $edition->forceFill([
        'status' => DigitalEditionStatus::Published,
        'rights_confirmed_at' => now(),
        'published_at' => now(),
    ])->save();

    expect($edition->refresh()->isAccessible())->toBeFalse();
    $this->get(route('library.books.read', $book))->assertNotFound();
    $this->get(route('library.books.content', $book))->assertNotFound();
});

it('requires documented permission notes before publishing PDFs flagged with copyright notices or public-domain year conflicts', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $author = Author::factory()->create();
    $category = Category::factory()->create();

    $copyrightedPdf = buildValidTestPdf(
        'Copyright 2022 Pearson Education. All rights reserved. No part of this publication may be reproduced without prior written permission.',
        [
            'Title' => 'Commercial Physics Textbook',
        ]
    );

    actingAs($librarian)
        ->post(route('administrators.library.books.store'), [
            'format' => 'ebook',
            'title' => 'Commercial Physics Textbook',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'publication_year' => 2022,
            'digital_pdf' => UploadedFile::fake()->createWithContent('commercial.pdf', $copyrightedPdf),
        ])
        ->assertRedirect();

    $book = Book::query()->where('title', 'Commercial Physics Textbook')->firstOrFail();
    $edition = $book->digitalEdition()->firstOrFail();

    expect($edition->rights_risk_level)->toBe('high')
        ->and($edition->isPiracyBlocked())->toBeFalse()
        ->and($edition->hasHighCopyrightRisk())->toBeTrue();

    // Publishing without rights_notes or license_url is blocked
    actingAs($librarian)
        ->put(route('administrators.library.books.digital-edition.update', $book), [
            'status' => 'published',
            'downloads_allowed' => false,
            'rights_basis' => 'written_permission',
            'rights_holder' => 'Pearson Education',
            'rights_notes' => '',
            'rights_confirmed' => true,
        ])
        ->assertSessionHasErrors('rights_notes');

    // Public domain on a 2022 book without rights_notes is also blocked
    actingAs($librarian)
        ->put(route('administrators.library.books.digital-edition.update', $book), [
            'status' => 'published',
            'downloads_allowed' => false,
            'rights_basis' => 'public_domain',
            'rights_notes' => '',
            'rights_confirmed' => true,
        ])
        ->assertSessionHasErrors('rights_notes');

    // Publishing WITH documented written permission succeeds
    actingAs($librarian)
        ->put(route('administrators.library.books.digital-edition.update', $book), [
            'status' => 'published',
            'downloads_allowed' => false,
            'rights_basis' => 'written_permission',
            'rights_holder' => 'Pearson Education',
            'rights_notes' => 'Institutional digital campus license #KOA-2026-09 signed by publisher.',
            'rights_confirmed' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($edition->refresh()->status)->toBe(DigitalEditionStatus::Published)
        ->and($edition->isAccessible())->toBeTrue();
});

it('extracts author, category, journal publisher, abstract, and excludes References pages from commercial publisher false positives', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $businessCategory = Category::factory()->create(['name' => 'Business Administration']);

    $academicPdf = buildMultiPageTestPdf([
        [
            'From the editor',
            'Advancing Small Business Strategy in Turbulent Times',
            'Timothy R. Mcilveene',
            '1a',
            '1 Business Administration, University of West Florida',
            'Keywords: small business strategy, organizational resilience, artificial intelligence (AI)',
            'https://doi.org/10.53703/001c.157794',
            'Journal of Small Business Strategy',
            'Vol. 36, Issue 2, 2026',
            'Small and medium-sized enterprises are central to innovation, employment, and community development across turbulent global markets.',
            'Introduction',
            'Small businesses have long been recognized as vital engines of innovation.',
        ],
        [
            'Published: March 31, 2026 CDT.',
            'This is an open-access article distributed under the terms of the Creative Commons Attribution 4.0 International License (CCBY-4.0).',
            'View this license legal deed at http://creativecommons.org/licenses/by/4.0',
        ],
        [
            'References',
            'Mark, M., & Pearson, C. S. (2001). The Hero and the Outlaw. McGraw-Hill.',
            'Lazarus, R. S. (1991). Emotion and adaptation. Oxford University Press.',
            'Freeman, R. E. (1984). Strategic management. Cambridge University Press.',
        ],
    ], [
        'Title' => 'Advancing Small Business Strategy in Turbulent Times',
        'Producer' => 'Prince 15.1 (www.princexml.com)',
    ]);

    $response = actingAs($librarian)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('small-business-strategy.pdf', $academicPdf),
            'use_ai' => false,
        ])
        ->assertOk();

    $response->assertJsonPath('metadata.author', 'Timothy R. Mcilveene')
        ->assertJsonPath('metadata.category', 'Business Administration')
        ->assertJsonPath('metadata.matched_category_id', $businessCategory->id)
        ->assertJsonPath('metadata.publisher', 'Journal of Small Business Strategy')
        ->assertJsonPath('metadata.publication_year', 2026)
        ->assertJsonPath('rights_scan.risk_level', 'open')
        ->assertJsonPath('rights_scan.piracy_blocked', false)
        ->assertJsonPath('rights_scan.suggested_rights.rights_basis', 'open_license')
        ->assertJsonPath('rights_scan.suggested_rights.license_url', 'http://creativecommons.org/licenses/by/4.0')
        ->assertJsonPath('ai_decision.status', 'skipped_by_user');

    expect($response->json('metadata.description'))->toContain('Small and medium-sized enterprises are central to innovation')
        ->and($response->json('rights_scan.reference_pages_excluded'))->toContain(3);
});

it('utilizes Laravel AI structured output to match author and category and surface copyright decision reasoning', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $author = Author::factory()->create(['name' => 'Timothy R. Mcilveene']);
    $category = Category::factory()->create(['name' => 'Business & Management']);

    LibraryDocumentCatalogAgent::fake([
        [
            'title' => 'Advancing Small Business Strategy in Turbulent Times',
            'author_name' => 'Timothy R. Mcilveene',
            'matched_author_id' => $author->id,
            'category_name' => 'Business & Management',
            'matched_category_id' => $category->id,
            'publisher' => 'Journal of Small Business Strategy',
            'publication_year' => 2026,
            'isbn' => null,
            'description' => 'An editorial overview examining resilience, ethical AI adoption, customer retention, and trade policy uncertainty in SMEs.',
            'rights_risk_level' => 'open',
            'recommended_rights_basis' => 'open_license',
            'rights_holder' => 'Journal of Small Business Strategy',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0',
            'confidence' => 'high',
            'decision_summary' => 'Open-access editorial published under CC BY 4.0; matched existing catalog author and Business & Management category.',
            'decision_reasons' => [
                'Explicit CC BY 4.0 open-access license statement on document colophon.',
                'Topic and keywords align with Business & Management.',
            ],
        ],
    ]);

    $minimalPdf = buildValidTestPdf(
        'An editorial overview of SME resilience and ethical AI. Licensed under Creative Commons Attribution 4.0 https://creativecommons.org/licenses/by/4.0',
        [
            'Title' => 'Advancing Small Business Strategy in Turbulent Times',
        ]
    );

    $response = actingAs($librarian)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('editorial.pdf', $minimalPdf),
            'use_ai' => true,
        ])
        ->assertOk();

    LibraryDocumentCatalogAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Business & Management'));

    $response->assertJsonPath('ai_decision.used', true)
        ->assertJsonPath('ai_decision.status', 'applied')
        ->assertJsonPath('ai_decision.confidence', 'high')
        ->assertJsonPath('metadata.matched_author_id', $author->id)
        ->assertJsonPath('metadata.matched_category_id', $category->id)
        ->assertJsonPath('metadata.publisher', 'Journal of Small Business Strategy')
        ->assertJsonPath('metadata.publication_year', 2026)
        ->assertJsonPath('rights_scan.risk_level', 'open')
        ->assertJsonPath('rights_scan.ai_decision.used', true)
        ->assertJsonPath('needs_metadata_review', false);
});

it('never allows Laravel AI to override deterministic piracy hard-blocks', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);

    LibraryDocumentCatalogAgent::fake([
        [
            'title' => 'Supposedly Open Book',
            'author_name' => 'Someone',
            'matched_author_id' => null,
            'category_name' => 'General',
            'matched_category_id' => null,
            'publisher' => null,
            'publication_year' => 2024,
            'isbn' => null,
            'description' => 'Should never be called on piracy files.',
            'rights_risk_level' => 'open',
            'recommended_rights_basis' => 'open_license',
            'rights_holder' => null,
            'license_url' => null,
            'confidence' => 'high',
            'decision_summary' => 'Should never run.',
            'decision_reasons' => [],
        ],
    ]);

    $piratedPdf = buildValidTestPdf(
        'Downloaded from OceanOfPDF.com — Creative Commons Attribution 4.0',
        ['Title' => 'Pirated Sample']
    );

    actingAs($librarian)
        ->postJson(route('administrators.library.books.inspect-pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('pirated.pdf', $piratedPdf),
            'use_ai' => true,
        ])
        ->assertOk()
        ->assertJsonPath('rights_scan.piracy_blocked', true)
        ->assertJsonPath('rights_scan.risk_level', 'critical')
        ->assertJsonPath('ai_decision.used', false)
        ->assertJsonPath('ai_decision.status', 'skipped_piracy_blocked');

    LibraryDocumentCatalogAgent::assertNeverPrompted();
});

it('allows administrators to configure digital edition visibility and filter account-required ebooks', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $author = Author::factory()->create(['name' => 'Katherine Johnson']);
    $category = Category::factory()->create(['name' => 'Mathematics']);

    $openPdf = buildValidTestPdf(
        'Open Textbook. Creative Commons CC BY 4.0 https://creativecommons.org/licenses/by/4.0/',
        ['Title' => 'Orbital Mechanics Notes']
    );

    actingAs($librarian)
        ->post(route('administrators.library.books.store'), [
            'format' => 'ebook',
            'title' => 'Orbital Mechanics Notes',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'publication_year' => 2025,
            'digital_pdf' => UploadedFile::fake()->createWithContent('orbital.pdf', $openPdf),
            'digital_visibility' => 'authenticated',
            'digital_rights_basis' => 'open_license',
            'digital_license_url' => 'https://creativecommons.org/licenses/by/4.0/',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $gatedBook = Book::query()->where('title', 'Orbital Mechanics Notes')->firstOrFail();
    $gatedEdition = $gatedBook->digitalEdition()->firstOrFail();

    expect($gatedEdition->status)->toBe(DigitalEditionStatus::Draft)
        ->and($gatedEdition->visibility)->toBe(DigitalEditionVisibility::Authenticated);

    // Edit page exposes visibility and options
    actingAs($librarian)
        ->get(route('administrators.library.books.edit', $gatedBook))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('book.digital_edition.visibility', 'authenticated')
            ->has('options.digital_visibilities', 3));

    // Update visibility to members_only and publish
    actingAs($librarian)
        ->put(route('administrators.library.books.digital-edition.update', $gatedBook), [
            'status' => 'published',
            'visibility' => 'members_only',
            'downloads_allowed' => false,
            'rights_basis' => 'open_license',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
            'rights_confirmed' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($gatedEdition->refresh()->status)->toBe(DigitalEditionStatus::Published)
        ->and($gatedEdition->visibility)->toBe(DigitalEditionVisibility::MembersOnly);

    // Create a second public eBook to confirm the account_required filter excludes it
    $publicBook = Book::factory()->create([
        'title' => 'Open Public Guide',
        'format' => BookFormat::Ebook,
        'author_id' => $author->id,
        'category_id' => $category->id,
        'total_copies' => 0,
        'available_copies' => 0,
    ]);
    $publicPath = "library/ebooks/{$publicBook->id}/public.pdf";
    Storage::disk('library-test')->put($publicPath, $openPdf);

    DigitalEdition::query()->create([
        'book_id' => $publicBook->id,
        'disk' => 'library-test',
        'path' => $publicPath,
        'original_name' => 'public.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => mb_strlen($openPdf),
        'sha256' => hash('sha256', $openPdf),
        'status' => DigitalEditionStatus::Published,
        'visibility' => DigitalEditionVisibility::Public,
        'downloads_allowed' => false,
        'rights_basis' => 'open_license',
        'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
        'uploaded_by' => $librarian->id,
        'uploaded_at' => now(),
        'published_by' => $librarian->id,
        'published_at' => now(),
        'rights_confirmed_by' => $librarian->id,
        'rights_confirmed_at' => now(),
    ]);

    actingAs($librarian)
        ->get(route('administrators.library.books.index', ['digital' => 'account_required']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.id', $gatedBook->id)
            ->where('books.data.0.digital_edition.visibility', 'members_only'));
});

it('enforces public, authenticated, and members_only visibility across catalog, show, reader, and PDF stream', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);
    $author = Author::factory()->create(['name' => 'Marie Curie']);
    $category = Category::factory()->create(['name' => 'Physics']);

    $pdfBytes = buildValidTestPdf('Sample published digital edition content.', ['Title' => 'Physics Monograph']);

    $makePublishedEbook = function (
        string $title,
        DigitalEditionVisibility $visibility,
        BookFormat $format = BookFormat::Ebook,
    ) use ($author, $category, $pdfBytes): Book {
        $book = Book::factory()->create([
            'title' => $title,
            'format' => $format,
            'author_id' => $author->id,
            'category_id' => $category->id,
            'total_copies' => $format === BookFormat::Ebook ? 0 : 2,
            'available_copies' => $format === BookFormat::Ebook ? 0 : 2,
            'status' => 'available',
        ]);

        $path = "library/ebooks/{$book->id}/edition.pdf";
        Storage::disk('library-test')->put($path, $pdfBytes);

        DigitalEdition::query()->create([
            'book_id' => $book->id,
            'disk' => 'library-test',
            'path' => $path,
            'original_name' => 'edition.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => mb_strlen($pdfBytes),
            'sha256' => hash('sha256', $pdfBytes),
            'status' => DigitalEditionStatus::Published,
            'visibility' => $visibility,
            'downloads_allowed' => false,
            'rights_basis' => 'open_license',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
            'uploaded_at' => now(),
            'published_at' => now(),
            'rights_confirmed_at' => now(),
        ]);

        return $book;
    };

    $publicBook = $makePublishedEbook('Public Open Reader Book', DigitalEditionVisibility::Public);
    $authBook = $makePublishedEbook('Account Gated Reader Book', DigitalEditionVisibility::Authenticated);
    $membersEbook = $makePublishedEbook('Members Only Hidden eBook', DigitalEditionVisibility::MembersOnly);
    $membersHybrid = $makePublishedEbook('Members Only Hybrid Book', DigitalEditionVisibility::MembersOnly, BookFormat::Hybrid);

    // 1. Guest catalog: sees public, authenticated, and hybrid (physical only), but NOT members_only ebook-only
    $this->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.catalog_books', 3)
            ->where('stats.available_online', 2)
            ->has('books.data', 3));

    // 2. Public visibility: guest can view show, reader, and PDF content
    $this->get(route('library.books.show', $publicBook))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('book.visibility', 'public')
            ->where('book.requires_account_to_read', false)
            ->where('access.can_read', true)
            ->where('access.read_requires_auth', false));

    $this->get(route('library.books.read', $publicBook))->assertOk();
    $this->get(route('library.books.content', $publicBook))->assertOk();

    // 3. Authenticated visibility: guest sees detail with sign-in gate, blocked on /read & /content
    $this->get(route('library.books.show', $authBook))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('book.visibility', 'authenticated')
            ->where('book.requires_account_to_read', true)
            ->where('access.can_read', false)
            ->where('access.read_requires_auth', true));

    $this->get(route('library.books.read', $authBook))->assertRedirect(route('login'));
    $this->getJson(route('library.books.read', $authBook))->assertUnauthorized();
    $this->get(route('library.books.content', $authBook))->assertUnauthorized();

    // 4. Members-only eBook-only: guest redirected on show & read, 401 on content
    $this->get(route('library.books.show', $membersEbook))->assertRedirect(route('login'));
    $this->get(route('library.books.read', $membersEbook))->assertRedirect(route('login'));
    $this->get(route('library.books.content', $membersEbook))->assertUnauthorized();

    // 5. Members-only hybrid: guest can still see the physical book record, but digital edition is hidden
    $this->get(route('library.books.show', $membersHybrid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('book.available_online', false)
            ->where('book.visibility', null)
            ->where('access.can_read', false)
            ->where('access.read_requires_auth', false));

    $this->get(route('library.books.read', $membersHybrid))->assertRedirect(route('login'));
    $this->get(route('library.books.content', $membersHybrid))->assertUnauthorized();

    // 6. Signed-in student: sees all 4 books & all 4 digital editions, can open & stream every one
    actingAs($student)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.catalog_books', 4)
            ->where('stats.available_online', 4)
            ->has('books.data', 4));

    actingAs($student)
        ->get(route('library.books.authenticate', ['book' => $authBook, 'from' => 'read', 'action' => 'read']))
        ->assertRedirect(route('library.books.read', $authBook));

    foreach ([$authBook, $membersEbook, $membersHybrid] as $restrictedBook) {
        actingAs($student)
            ->get(route('library.books.show', $restrictedBook))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('book.available_online', true)
                ->where('access.can_read', true)
                ->where('access.read_requires_auth', false));

        actingAs($student)->get(route('library.books.read', $restrictedBook))->assertOk();
        actingAs($student)->get(route('library.books.content', $restrictedBook))->assertOk();
    }
});

/**
 * Build a minimal standards-compliant PDF 1.4 document with selectable text and Info dictionary
 * so smalot/pdfparser can parse metadata and page text deterministically in tests.
 *
 * @param  array<string, string>  $info
 */
function buildValidTestPdf(string $pageText, array $info = []): string
{
    return buildMultiPageTestPdf([$pageText !== '' ? [$pageText] : []], $info);
}

/**
 * Build a multi-page standards-compliant PDF 1.4 document with line-separated selectable text.
 *
 * @param  list<list<string>>  $pagesLines
 * @param  array<string, string>  $info
 */
function buildMultiPageTestPdf(array $pagesLines, array $info = []): string
{
    $escapePdf = static fn (string $text): string => str_replace(
        ['\\', '(', ')', "\r", "\n"],
        ['\\\\', '\\(', '\\)', '', ' '],
        $text
    );

    $infoEntries = [];
    foreach ($info as $key => $value) {
        $infoEntries[] = "/{$key} (".$escapePdf($value).')';
    }

    $pageCount = count($pagesLines);
    $fontObjectId = 3;
    $infoObjectId = 4;
    $nextObjectId = 5;

    $pageObjectIds = [];
    $dynamicObjects = [];

    foreach ($pagesLines as $lines) {
        $pageObjectId = $nextObjectId++;
        $contentObjectId = $nextObjectId++;
        $pageObjectIds[] = $pageObjectId;

        $streamParts = [];
        if ($lines !== []) {
            $streamParts[] = 'BT';
            $streamParts[] = '/F1 11 Tf';
            $streamParts[] = '14 TL';
            $streamParts[] = '72 740 Td';
            foreach ($lines as $idx => $line) {
                $prefix = $idx === 0 ? '' : 'T* ';
                $streamParts[] = $prefix.'('.$escapePdf($line).') Tj';
            }
            $streamParts[] = 'ET';
        }
        $stream = implode("\n", $streamParts);

        $dynamicObjects[$pageObjectId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /Contents {$contentObjectId} 0 R >>";
        $dynamicObjects[$contentObjectId] = '<< /Length '.mb_strlen($stream, '8bit')." >>\nstream\n{$stream}\nendstream";
    }

    $kidsRefs = implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $pageObjectIds));

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => "<< /Type /Pages /Kids [{$kidsRefs}] /Count {$pageCount} >>",
        $fontObjectId => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        $infoObjectId => '<< '.implode(' ', $infoEntries).' >>',
        ...$dynamicObjects,
    ];
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];

    foreach ($objects as $id => $body) {
        $offsets[$id] = mb_strlen($pdf, '8bit');
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = mb_strlen($pdf, '8bit');
    $count = count($objects) + 1;
    $pdf .= "xref\n0 {$count}\n";
    $pdf .= "0000000000 65535 f \n";

    foreach (array_keys($objects) as $id) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }

    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R /Info {$infoObjectId} 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}
