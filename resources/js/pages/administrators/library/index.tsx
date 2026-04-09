import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { BookOpen, BookText, BookmarkCheck, ClipboardList, FolderOpen, GraduationCap, Library, Share2 } from "lucide-react";

declare const route: any;

interface LibraryStats {
    total_books: number;
    available_copies: number;
    authors: number;
    categories: number;
    borrow_records: number;
    overdue_records: number;
    research_papers: number;
    public_research_papers: number;
}

interface RecentBook {
    id: number;
    title: string;
    author?: string | null;
    category?: string | null;
    status: string;
    available_copies: number;
    updated_at?: string | null;
}

interface RecentBorrow {
    id: number;
    book: { id?: number | null; title?: string | null };
    borrower: { name?: string | null; email?: string | null };
    borrowed_at?: string | null;
    due_date?: string | null;
    status: string;
    is_overdue: boolean;
}

interface RecentResearchPaper {
    id: number;
    title: string;
    type: string;
    publication_year?: number | null;
    status: string;
    students?: string[];
    course?: string | null;
}

interface Props {
    user: User;
    stats: LibraryStats;
    recent: {
        books: RecentBook[];
        borrows: RecentBorrow[];
        research_papers: RecentResearchPaper[];
    };
}

const statusStyles: Record<string, string> = {
    available: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    maintenance: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
    returned: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    lost: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
    draft: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
    submitted: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
    archived: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
};

const formatDate = (value?: string | null) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString();
};

export default function LibraryIndex({ user, stats, recent }: Props) {
    return (
        <AdminLayout user={user} title="Library System">
            <Head title="Administrators • Library System" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-r from-emerald-500/10 to-sky-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                    <Library className="h-6 w-6" />
                                </div>
                                <div>
                                    <CardTitle className="text-2xl">Library Control Center</CardTitle>
                                    <CardDescription>Manage circulation, catalog, and research archives in one place.</CardDescription>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={route("administrators.library.books.create")}>Add Book</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.library.research-papers.create")}>Add Research</Link>
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Catalog Titles</p>
                                <p className="text-2xl font-semibold">{stats.total_books}</p>
                                <p className="text-muted-foreground text-xs">{stats.available_copies} copies available</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                <BookOpen className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Authors & Categories</p>
                                <p className="text-2xl font-semibold">{stats.authors + stats.categories}</p>
                                <p className="text-muted-foreground text-xs">
                                    {stats.authors} authors • {stats.categories} categories
                                </p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
                                <BookText className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Borrowed Items</p>
                                <p className="text-2xl font-semibold">{stats.borrow_records}</p>
                                <p className="text-muted-foreground text-xs">{stats.overdue_records} overdue records</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                                <ClipboardList className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Research Archive</p>
                                <p className="text-2xl font-semibold">{stats.research_papers}</p>
                                <p className="text-muted-foreground text-xs">{stats.public_research_papers} public-ready</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600">
                                <GraduationCap className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                    <Card className="border">
                        <CardHeader className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <CardTitle>Recent Catalog Updates</CardTitle>
                                <CardDescription>Latest books added or updated by the team.</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route("administrators.library.books.index")}>View catalog</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Author</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Updated</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent.books.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-24 text-center text-sm">
                                                No catalog updates yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        recent.books.map((book) => (
                                            <TableRow key={book.id}>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <p className="text-foreground font-medium">{book.title}</p>
                                                        <p className="text-muted-foreground text-xs">{book.category ?? "Uncategorized"}</p>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">{book.author ?? "Unknown"}</TableCell>
                                                <TableCell>
                                                    <Badge className={statusStyles[book.status] ?? "bg-muted text-muted-foreground"}>
                                                        {book.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-right text-xs">
                                                    {formatDate(book.updated_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6">
                        <Card className="border">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>Circulation Watch</CardTitle>
                                    <CardDescription>Latest borrow activity and due dates.</CardDescription>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route("administrators.library.borrow-records.index")}>Records</Link>
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {recent.borrows.length === 0 ? (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm">
                                        No recent borrow activity.
                                    </div>
                                ) : (
                                    recent.borrows.map((record) => (
                                        <div key={record.id} className="flex flex-col gap-2 rounded-lg border p-4">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="text-foreground text-sm font-semibold">{record.book?.title ?? "Unknown book"}</p>
                                                    <p className="text-muted-foreground text-xs">{record.borrower?.name ?? "Unknown borrower"}</p>
                                                </div>
                                                <Badge className={statusStyles[record.status] ?? "bg-muted text-muted-foreground"}>
                                                    {record.status}
                                                </Badge>
                                            </div>
                                            <div className="text-muted-foreground flex items-center justify-between text-xs">
                                                <span>Borrowed {formatDate(record.borrowed_at)}</span>
                                                <span className={record.is_overdue ? "text-rose-500" : ""}>Due {formatDate(record.due_date)}</span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>Research Highlights</CardTitle>
                                    <CardDescription>Capstones and theses ready for review.</CardDescription>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route("administrators.library.research-papers.index")}>Archive</Link>
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {recent.research_papers.length === 0 ? (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm">
                                        No research papers logged yet.
                                    </div>
                                ) : (
                                    recent.research_papers.map((paper) => (
                                        <div key={paper.id} className="flex flex-col gap-2 rounded-lg border p-4">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="text-foreground text-sm font-semibold">{paper.title}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {paper.students?.length ? paper.students.join(", ") : "Unassigned students"}
                                                    </p>
                                                </div>
                                                <Badge className={statusStyles[paper.status] ?? "bg-muted text-muted-foreground"}>
                                                    {paper.status}
                                                </Badge>
                                            </div>
                                            <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                                <span className="flex items-center gap-1">
                                                    <FolderOpen className="h-3 w-3" />
                                                    {paper.type}
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <BookmarkCheck className="h-3 w-3" />
                                                    {paper.publication_year ?? "Year TBD"}
                                                </span>
                                                {paper.course && (
                                                    <span className="flex items-center gap-1">
                                                        <GraduationCap className="h-3 w-3" />
                                                        {paper.course}
                                                    </span>
                                                )}
                                                <span className="flex items-center gap-1">
                                                    <Share2 className="h-3 w-3" />
                                                    {paper.status}
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
