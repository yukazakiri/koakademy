import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { NavListCard } from "@/components/spectrumui/nav-list-card";
import { buttonVariants } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { index as adminAuthorsIndex } from "@/routes/administrators/library/authors";
import { index as adminBooksIndex, create as createBook } from "@/routes/administrators/library/books";
import { index as adminBorrowRecordsIndex } from "@/routes/administrators/library/borrow-records";
import { index as adminCategoriesIndex } from "@/routes/administrators/library/categories";
import { index as adminResearchPapersIndex, create as createResearchPaper } from "@/routes/administrators/library/research-papers";
import { index as publicLibraryIndex } from "@/routes/library";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowUpRight,
    BookOpen,
    BookText,
    ClipboardList,
    ExternalLink,
    FolderOpen,
    GraduationCap,
    Plus,
    Sparkles,
    Tag,
    Users,
} from "lucide-react";

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

const statusBadgeVariant = (status: string): "success-light" | "warning-light" | "destructive-light" | "info-light" | "invert-light" => {
    switch (status.toLowerCase()) {
        case "available":
        case "returned":
            return "success-light";
        case "borrowed":
            return "warning-light";
        case "maintenance":
        case "lost":
            return "destructive-light";
        case "submitted":
            return "info-light";
        case "draft":
        case "archived":
        default:
            return "invert-light";
    }
};

const numberFormatter = new Intl.NumberFormat();

const formatDate = (value?: string | null) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
};

export default function LibraryIndex({ user, stats, recent }: Props) {
    return (
        <AdminLayout user={user} title="Library Operations Center">
            <Head title="Administrators • Library Operations Center" />

            <div className="flex flex-col gap-6">
                {/* ── Operational Hero & Actions ── */}
                <Frame variant="default" spacing="default" className="border-border/80 bg-card/60 overflow-hidden shadow-xs">
                    <FramePanel className="via-background relative overflow-hidden bg-linear-to-br from-emerald-500/10 to-sky-500/10 p-6 md:p-8">
                        <div className="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                            <div className="max-w-2xl space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="primary-light" radius="full" size="sm" className="font-semibold tracking-wider uppercase">
                                        <Sparkles className="text-primary mr-1 size-3" />
                                        Administrative Control
                                    </Badge>
                                    <Badge variant="outline" radius="full" size="sm" className="text-muted-foreground">
                                        KoAkademy Library Operations
                                    </Badge>
                                </div>
                                <div>
                                    <h1 className="text-foreground font-serif text-3xl font-semibold tracking-tight md:text-4xl">
                                        Library Control Center
                                    </h1>
                                    <p className="text-muted-foreground mt-2 text-sm leading-relaxed md:text-base">
                                        Monitor circulation activity, manage collection acquisitions, oversee student research archiving, and maintain
                                        institutional metadata.
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2.5">
                                <Link
                                    href={publicLibraryIndex.url()}
                                    className={cn(
                                        buttonVariants({ variant: "outline", size: "default" }),
                                        "gap-2 rounded-lg text-xs font-semibold shadow-2xs",
                                    )}
                                >
                                    <ExternalLink className="size-3.5" />
                                    Open Public Library Portal
                                </Link>
                                <Link
                                    href={createBook.url()}
                                    className={cn(buttonVariants({ size: "default" }), "gap-2 rounded-lg text-xs font-semibold shadow-xs")}
                                >
                                    <Plus className="size-3.5" />
                                    Add Book
                                </Link>
                                <Link
                                    href={createResearchPaper.url()}
                                    className={cn(
                                        buttonVariants({ variant: "secondary", size: "default" }),
                                        "gap-2 rounded-lg text-xs font-semibold",
                                    )}
                                >
                                    <GraduationCap className="size-3.5" />
                                    Add Research
                                </Link>
                            </div>
                        </div>
                    </FramePanel>
                </Frame>

                {/* ── Key Operational Metrics ── */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Frame variant="default" spacing="xs" className="border-border/80 bg-card/60 shadow-xs">
                        <FramePanel className="flex items-center justify-between gap-4 p-5">
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Catalog Collection</p>
                                <p className="text-foreground font-serif text-3xl font-bold tabular-nums">
                                    {numberFormatter.format(stats.total_books)}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                        {numberFormatter.format(stats.available_copies)}
                                    </span>{" "}
                                    copies ready for circulation
                                </p>
                            </div>
                            <IconTile variant="soft" size="lg" className="text-emerald-600 dark:text-emerald-400">
                                <BookOpen />
                            </IconTile>
                        </FramePanel>
                    </Frame>

                    <Frame variant="default" spacing="xs" className="border-border/80 bg-card/60 shadow-xs">
                        <FramePanel className="flex items-center justify-between gap-4 p-5">
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Authors & Taxonomy</p>
                                <p className="text-foreground font-serif text-3xl font-bold tabular-nums">
                                    {numberFormatter.format(stats.authors + stats.categories)}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {stats.authors} authors • {stats.categories} categories
                                </p>
                            </div>
                            <IconTile variant="soft" size="lg" className="text-sky-600 dark:text-sky-400">
                                <BookText />
                            </IconTile>
                        </FramePanel>
                    </Frame>

                    <Frame variant="default" spacing="xs" className="border-border/80 bg-card/60 shadow-xs">
                        <FramePanel className="flex items-center justify-between gap-4 p-5">
                            <div className="space-y-1">
                                <div className="flex items-center gap-1.5">
                                    <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Circulation Desk</p>
                                    {stats.overdue_records > 0 && (
                                        <Badge variant="destructive-light" size="xs" radius="full">
                                            {stats.overdue_records} Overdue
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-foreground font-serif text-3xl font-bold tabular-nums">
                                    {numberFormatter.format(stats.borrow_records)}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {stats.overdue_records > 0 ? (
                                        <span className="font-medium text-rose-600 dark:text-rose-400">
                                            {stats.overdue_records} overdue returns pending
                                        </span>
                                    ) : (
                                        "All borrowed items on schedule"
                                    )}
                                </p>
                            </div>
                            <IconTile
                                variant="soft"
                                size="lg"
                                className={stats.overdue_records > 0 ? "text-rose-500" : "text-amber-600 dark:text-amber-400"}
                            >
                                {stats.overdue_records > 0 ? <AlertTriangle /> : <ClipboardList />}
                            </IconTile>
                        </FramePanel>
                    </Frame>

                    <Frame variant="default" spacing="xs" className="border-border/80 bg-card/60 shadow-xs">
                        <FramePanel className="flex items-center justify-between gap-4 p-5">
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Academic Research</p>
                                <p className="text-foreground font-serif text-3xl font-bold tabular-nums">
                                    {numberFormatter.format(stats.research_papers)}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    <span className="font-medium text-indigo-600 dark:text-indigo-400">{stats.public_research_papers}</span> theses
                                    public-accessible
                                </p>
                            </div>
                            <IconTile variant="soft" size="lg" className="text-indigo-600 dark:text-indigo-400">
                                <GraduationCap />
                            </IconTile>
                        </FramePanel>
                    </Frame>
                </div>

                {/* ── Main Operations Grid ── */}
                <div className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                    {/* ── Left Column: Recent Catalog Updates ── */}
                    <div className="flex flex-col gap-6">
                        <Frame variant="default" spacing="sm" className="border-border/80 bg-card/60 shadow-xs">
                            <FramePanel className="space-y-4 p-5">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="text-foreground font-serif text-lg font-semibold">Recent Catalog Updates</h2>
                                        <p className="text-muted-foreground text-xs">Latest book acquisitions and inventory modifications.</p>
                                    </div>
                                    <Link
                                        href={adminBooksIndex.url()}
                                        className={cn(
                                            buttonVariants({ variant: "outline", size: "sm" }),
                                            "gap-1 self-start rounded-lg text-xs sm:self-auto",
                                        )}
                                    >
                                        View All ({numberFormatter.format(stats.total_books)})
                                        <ArrowUpRight className="size-3.5" />
                                    </Link>
                                </div>

                                <div className="border-border/70 overflow-hidden rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="bg-muted/30">
                                                <TableHead className="text-xs font-semibold">Title & Category</TableHead>
                                                <TableHead className="text-xs font-semibold">Author</TableHead>
                                                <TableHead className="text-xs font-semibold">Status</TableHead>
                                                <TableHead className="text-right text-xs font-semibold">Updated</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {recent.books.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={4} className="text-muted-foreground h-28 text-center text-xs">
                                                        No catalog entries registered yet.
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                recent.books.map((book) => (
                                                    <TableRow key={book.id} className="hover:bg-muted/30 transition-colors">
                                                        <TableCell>
                                                            <div className="space-y-0.5">
                                                                <p className="text-foreground text-xs leading-snug font-medium">{book.title}</p>
                                                                <p className="text-muted-foreground text-[11px]">
                                                                    {book.category ?? "General Collection"}
                                                                </p>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground text-xs">{book.author ?? "Unknown"}</TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant={statusBadgeVariant(book.status)}
                                                                radius="full"
                                                                size="xs"
                                                                className="capitalize"
                                                            >
                                                                {book.status}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground text-right text-xs tabular-nums">
                                                            {formatDate(book.updated_at)}
                                                        </TableCell>
                                                    </TableRow>
                                                ))
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </FramePanel>
                        </Frame>
                    </div>

                    {/* ── Right Column: Spectrum UI NavCard & Activity Rails ── */}
                    <div className="flex flex-col gap-6">
                        {/* Spectrum UI NavListCard Quick Directory */}
                        <NavListCard
                            title="Library Management Modules"
                            className="bg-card/70 border-border/80 shadow-xs"
                            items={[
                                {
                                    icon: <BookOpen className="text-emerald-600 dark:text-emerald-400" />,
                                    label: "Books & Catalogues",
                                    href: adminBooksIndex.url(),
                                    badge: (
                                        <Badge variant="outline" size="xs" radius="full">
                                            {numberFormatter.format(stats.total_books)}
                                        </Badge>
                                    ),
                                },
                                {
                                    icon: <ClipboardList className="text-amber-600 dark:text-amber-400" />,
                                    label: "Borrow & Circulation Records",
                                    href: adminBorrowRecordsIndex.url(),
                                    badge: (
                                        <Badge variant={stats.overdue_records > 0 ? "destructive-light" : "outline"} size="xs" radius="full">
                                            {stats.overdue_records > 0
                                                ? `${stats.overdue_records} Overdue`
                                                : numberFormatter.format(stats.borrow_records)}
                                        </Badge>
                                    ),
                                },
                                {
                                    icon: <GraduationCap className="text-indigo-600 dark:text-indigo-400" />,
                                    label: "Theses & Research Papers",
                                    href: adminResearchPapersIndex.url(),
                                    badge: (
                                        <Badge variant="outline" size="xs" radius="full">
                                            {numberFormatter.format(stats.research_papers)}
                                        </Badge>
                                    ),
                                },
                                {
                                    icon: <Users className="text-sky-600 dark:text-sky-400" />,
                                    label: "Authors Directory",
                                    href: adminAuthorsIndex.url(),
                                    badge: (
                                        <Badge variant="outline" size="xs" radius="full">
                                            {numberFormatter.format(stats.authors)}
                                        </Badge>
                                    ),
                                },
                                {
                                    icon: <Tag className="text-purple-600 dark:text-purple-400" />,
                                    label: "Classification Categories",
                                    href: adminCategoriesIndex.url(),
                                    badge: (
                                        <Badge variant="outline" size="xs" radius="full">
                                            {numberFormatter.format(stats.categories)}
                                        </Badge>
                                    ),
                                },
                            ]}
                        />

                        {/* Circulation Watch */}
                        <Frame variant="default" spacing="sm" className="border-border/80 bg-card/60 shadow-xs">
                            <FramePanel className="space-y-3 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h2 className="text-foreground font-serif text-sm font-semibold">Circulation Watch</h2>
                                        <p className="text-muted-foreground text-[11px]">Recent borrowing and return statuses.</p>
                                    </div>
                                    <Link
                                        href={adminBorrowRecordsIndex.url()}
                                        className={cn(buttonVariants({ variant: "ghost", size: "xs" }), "gap-1 text-xs")}
                                    >
                                        Log
                                        <ArrowUpRight className="size-3" />
                                    </Link>
                                </div>

                                <div className="space-y-2">
                                    {recent.borrows.length === 0 ? (
                                        <p className="text-muted-foreground py-6 text-center text-xs">No recent borrow records logged.</p>
                                    ) : (
                                        recent.borrows.map((record) => (
                                            <div
                                                key={record.id}
                                                className="border-border/70 bg-background/60 flex flex-col gap-1.5 rounded-lg border p-3 text-xs"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="text-foreground truncate font-medium">
                                                            {record.book?.title ?? "Unknown Title"}
                                                        </p>
                                                        <p className="text-muted-foreground truncate text-[11px]">
                                                            Borrower: {record.borrower?.name ?? record.borrower?.email ?? "Unknown"}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant={record.is_overdue ? "destructive-light" : statusBadgeVariant(record.status)}
                                                        size="xs"
                                                        radius="full"
                                                        className="shrink-0 capitalize"
                                                    >
                                                        {record.is_overdue ? "Overdue" : record.status}
                                                    </Badge>
                                                </div>
                                                <div className="text-muted-foreground border-border/40 flex items-center justify-between border-t pt-1 text-[11px]">
                                                    <span>Out: {formatDate(record.borrowed_at)}</span>
                                                    <span className={record.is_overdue ? "font-semibold text-rose-600" : ""}>
                                                        Due: {formatDate(record.due_date)}
                                                    </span>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </FramePanel>
                        </Frame>

                        {/* Research Highlights */}
                        <Frame variant="default" spacing="sm" className="border-border/80 bg-card/60 shadow-xs">
                            <FramePanel className="space-y-3 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h2 className="text-foreground font-serif text-sm font-semibold">Research Archive</h2>
                                        <p className="text-muted-foreground text-[11px]">Recent capstone and thesis deposits.</p>
                                    </div>
                                    <Link
                                        href={adminResearchPapersIndex.url()}
                                        className={cn(buttonVariants({ variant: "ghost", size: "xs" }), "gap-1 text-xs")}
                                    >
                                        Archive
                                        <ArrowUpRight className="size-3" />
                                    </Link>
                                </div>

                                <div className="space-y-2">
                                    {recent.research_papers.length === 0 ? (
                                        <p className="text-muted-foreground py-6 text-center text-xs">No research papers registered yet.</p>
                                    ) : (
                                        recent.research_papers.map((paper) => (
                                            <div
                                                key={paper.id}
                                                className="border-border/70 bg-background/60 flex flex-col gap-1.5 rounded-lg border p-3 text-xs"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="text-foreground line-clamp-1 font-medium">{paper.title}</p>
                                                    <Badge
                                                        variant={statusBadgeVariant(paper.status)}
                                                        size="xs"
                                                        radius="full"
                                                        className="shrink-0 capitalize"
                                                    >
                                                        {paper.status}
                                                    </Badge>
                                                </div>
                                                <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-[11px]">
                                                    <span className="flex items-center gap-1">
                                                        <FolderOpen className="size-3" />
                                                        {paper.type}
                                                    </span>
                                                    {paper.course && (
                                                        <span className="flex items-center gap-1">
                                                            <GraduationCap className="size-3" />
                                                            {paper.course}
                                                        </span>
                                                    )}
                                                    {paper.publication_year && <span className="tabular-nums">• {paper.publication_year}</span>}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </FramePanel>
                        </Frame>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
