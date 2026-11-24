<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LibrarySystem\Enums\BookFormat;
use Modules\LibrarySystem\Enums\DigitalEditionStatus;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;

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

/**
 * Build a minimal standards-compliant PDF 1.4 document with selectable text and Info dictionary
 * so smalot/pdfparser can parse metadata and page text deterministically in tests.
 *
 * @param  array<string, string>  $info
 */
function buildValidTestPdf(string $pageText, array $info = []): string
{
    $escapePdf = static fn (string $text): string => str_replace(
        ['\\', '(', ')', "\r", "\n"],
        ['\\\\', '\\(', '\\)', '', ' '],
        $text
    );

    $stream = $pageText !== ''
        ? "BT\n/F1 12 Tf\n72 720 Td\n(".$escapePdf($pageText).") Tj\nET"
        : '';

    $infoEntries = [];
    foreach ($info as $key => $value) {
        $infoEntries[] = "/{$key} (".$escapePdf($value).')';
    }

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => '<< /Length '.mb_strlen($stream, '8bit')." >>\nstream\n{$stream}\nendstream",
        6 => '<< '.implode(' ', $infoEntries).' >>',
    ];

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

    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R /Info 6 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}
