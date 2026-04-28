<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Models\Category;
use Modules\LibrarySystem\Models\ResearchPaper;

final class AdministratorLibraryController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_books' => Book::count(),
            'available_copies' => (int) Book::sum('available_copies'),
            'authors' => Author::count(),
            'categories' => Category::count(),
            'borrow_records' => BorrowRecord::count(),
            'overdue_records' => BorrowRecord::query()
                ->where('status', 'borrowed')
                ->where('due_date', '<', now())
                ->count(),
            'research_papers' => ResearchPaper::count(),
            'public_research_papers' => ResearchPaper::query()->where('is_public', true)->count(),
        ];

        $recentBooks = Book::query()
            ->with(['author', 'category'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Book $book): array => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author?->name,
                'category' => $book->category?->name,
                'status' => $book->status,
                'available_copies' => $book->available_copies,
                'updated_at' => format_timestamp($book->updated_at),
            ]);

        $recentBorrows = BorrowRecord::query()
            ->with(['book', 'user'])
            ->orderByDesc('borrowed_at')
            ->take(5)
            ->get()
            ->map(fn (BorrowRecord $record): array => [
                'id' => $record->id,
                'book' => [
                    'id' => $record->book?->id,
                    'title' => $record->book?->title,
                ],
                'borrower' => [
                    'name' => $record->user?->name,
                    'email' => $record->user?->email,
                ],
                'borrowed_at' => format_timestamp($record->borrowed_at),
                'due_date' => format_timestamp($record->due_date),
                'status' => $record->status,
                'is_overdue' => $record->isOverdue(),
            ]);

        $recentResearch = ResearchPaper::query()
            ->with(['students', 'student', 'course'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (ResearchPaper $paper): array => [
                'id' => $paper->id,
                'title' => $paper->title,
                'type' => $paper->type,
                'publication_year' => $paper->publication_year,
                'status' => $paper->status,
                'students' => $this->resolveResearchStudents($paper),
                'course' => $paper->course?->code,
            ]);

        return Inertia::render('administrators/library/index', [
            'user' => $this->getUserProps(),
            'stats' => $stats,
            'recent' => [
                'books' => $recentBooks,
                'borrows' => $recentBorrows,
                'research_papers' => $recentResearch,
            ],
            'flash' => session('flash'),
        ]);
    }

    private function getUserProps(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }

    private function resolveResearchStudents(ResearchPaper $paper): array
    {
        $students = $paper->students
            ->pluck('full_name')
            ->filter()
            ->values()
            ->all();

        if ($students === [] && $paper->student) {
            return [$paper->student->full_name];
        }

        return $students;
    }
}
