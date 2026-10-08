import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { BeamSearch } from "@/components/spectrumui/beam-search";
import { KbdKey } from "@/components/spectrumui/kbd-key";
import { Button, buttonVariants } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { isAdministratorPortalRole } from "@/lib/portal-role";
import { cn } from "@/lib/utils";
import { index as adminLibraryIndex } from "@/routes/administrators/library";
import { index as libraryIndex } from "@/routes/library";
import { favorite as favoriteBook, show as showBook, unfavorite as unfavoriteBook } from "@/routes/library/books";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowUpDown,
    BookMarked,
    BookOpen,
    Bookmark,
    ChevronLeft,
    ChevronRight,
    Clock,
    Compass,
    Heart,
    LayoutGrid,
    LibraryBig,
    List,
    RotateCcw,
    Search,
    ShieldCheck,
    Sparkles,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { BookCard, type LibraryBookCardData } from "./components/book-card";
import { BookCover } from "./components/book-cover";

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Filters {
    search: string;
    category_id: number | null;
    year: number | null;
    availability: string;
    collection: string;
    sort: string;
}

interface Props {
    auth: { user: User };
    books: Pagination<LibraryBookCardData>;
    filters: Filters;
    options: {
        categories: { id: number; name: string }[];
        years: number[];
    };
    stats: {
        catalog_books: number;
        available_online: number;
        favorites: number;
    };
}

const numberFormatter = new Intl.NumberFormat();

export default function DigitalLibraryIndex({ auth, books, filters, options, stats }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [viewMode, setViewMode] = useState<"grid" | "list">("grid");
    const searchInputRef = useRef<HTMLInputElement>(null);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const isLibrarianOrAdmin = isAdministratorPortalRole(auth.user.role);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        return () => {
            if (searchTimer.current) clearTimeout(searchTimer.current);
        };
    }, []);

    // Global keyboard shortcut to focus BeamSearch with "/" or "Cmd+K"
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const activeElement = document.activeElement;
            const isTyping =
                activeElement instanceof HTMLInputElement ||
                activeElement instanceof HTMLTextAreaElement ||
                (activeElement instanceof HTMLElement && activeElement.isContentEditable);

            if ((event.key === "/" && !isTyping) || ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k")) {
                event.preventDefault();
                searchInputRef.current?.focus();
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, []);

    const visitWith = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        router.get(
            libraryIndex.url({
                query: {
                    search: next.search || undefined,
                    category_id: next.category_id || undefined,
                    year: next.year || undefined,
                    availability: next.availability === "all" ? undefined : next.availability,
                    collection: next.collection === "all" ? undefined : next.collection,
                    sort: next.sort === "title" ? undefined : next.sort,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["books", "filters", "stats"],
            },
        );
    };

    const handleSearchChange = (value: string) => {
        setSearch(value);
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => {
            visitWith({ search: value });
        }, 300);
    };

    const handleSearchClear = () => {
        setSearch("");
        if (searchTimer.current) clearTimeout(searchTimer.current);
        visitWith({ search: "" });
    };

    const clearAllFilters = () => {
        setSearch("");
        router.get(libraryIndex.url(), {}, { preserveState: true, replace: true });
    };

    const hasFilters =
        filters.search !== "" ||
        filters.category_id !== null ||
        filters.year !== null ||
        filters.availability !== "all" ||
        filters.collection !== "all" ||
        filters.sort !== "title";

    // Quick shelf tab presets
    const quickShelves = [
        {
            id: "all",
            label: "All Catalog",
            count: stats.catalog_books,
            icon: LibraryBig,
            isActive: filters.collection === "all" && filters.availability === "all",
            apply: () => visitWith({ collection: "all", availability: "all" }),
        },
        {
            id: "online",
            label: "Digital Editions",
            count: stats.available_online,
            icon: BookOpen,
            isActive: filters.availability === "online",
            apply: () => visitWith({ availability: "online" }),
        },
        {
            id: "favorites",
            label: "My Saved Shelf",
            count: stats.favorites,
            icon: Heart,
            isActive: filters.collection === "favorites",
            apply: () => visitWith({ collection: "favorites" }),
        },
        {
            id: "recent",
            label: "Recently Read",
            count: null,
            icon: Clock,
            isActive: filters.collection === "recent",
            apply: () => visitWith({ collection: "recent" }),
        },
        {
            id: "catalog_only",
            label: "Physical Copies",
            count: Math.max(0, stats.catalog_books - stats.available_online),
            icon: BookMarked,
            isActive: filters.availability === "catalog",
            apply: () => visitWith({ availability: "catalog" }),
        },
    ];

    const currentCategory = options.categories.find((c) => c.id === filters.category_id);

    return (
        <PortalLayout user={auth.user}>
            <Head title="Digital Library • KoAkademy" />

            <div className="flex flex-col gap-6">
                {/* ── Scholarly Hero Banner & Quick Stats ── */}
                <Frame variant="default" spacing="default" className="border-border/80 bg-card/60 overflow-hidden shadow-xs">
                    <FramePanel className="via-background relative overflow-hidden bg-linear-to-br from-amber-500/10 to-sky-500/10 p-6 md:p-8">
                        <div className="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                            <div className="max-w-3xl space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="primary-light" radius="full" size="sm" className="font-semibold tracking-wider uppercase">
                                        <Sparkles className="text-primary mr-1 size-3" />
                                        KoAkademy Digital Library
                                    </Badge>
                                    <Badge variant="outline" radius="full" size="sm" className="text-muted-foreground">
                                        <ShieldCheck className="mr-1 size-3 text-emerald-600 dark:text-emerald-400" />
                                        Authenticated Campus Access
                                    </Badge>
                                    {isLibrarianOrAdmin && (
                                        <Link
                                            href={adminLibraryIndex.url()}
                                            className={cn(
                                                buttonVariants({ variant: "outline", size: "xs" }),
                                                "border-primary/30 text-primary hover:bg-primary/10 gap-1 rounded-full",
                                            )}
                                        >
                                            <Compass className="size-3" />
                                            Librarian Operations
                                        </Link>
                                    )}
                                </div>

                                <div>
                                    <h1 className="text-foreground font-serif text-3xl font-semibold tracking-tight md:text-5xl">
                                        Academic Repository & Digital Editions
                                    </h1>
                                    <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed md:text-base">
                                        Welcome, <span className="text-foreground font-medium">{auth.user.name}</span>. Access physical catalog
                                        records, instant-read digital editions, and personal research bookmarks.
                                    </p>
                                </div>
                            </div>

                            {/* ── Quick Stats Grid ── */}
                            <div className="grid grid-cols-3 gap-2.5 sm:gap-3 lg:grid-cols-3">
                                <div className="border-border/60 bg-background/80 flex flex-col gap-1 rounded-xl border p-3 shadow-2xs backdrop-blur-xs">
                                    <div className="flex items-center justify-between gap-2">
                                        <IconTile variant="soft" size="sm" className="text-primary">
                                            <LibraryBig />
                                        </IconTile>
                                        <span className="text-foreground font-serif text-xl font-bold tabular-nums md:text-2xl">
                                            {numberFormatter.format(stats.catalog_books)}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Catalog Titles</span>
                                </div>

                                <div className="border-border/60 bg-background/80 flex flex-col gap-1 rounded-xl border p-3 shadow-2xs backdrop-blur-xs">
                                    <div className="flex items-center justify-between gap-2">
                                        <IconTile variant="soft" size="sm" className="text-emerald-600 dark:text-emerald-400">
                                            <BookOpen />
                                        </IconTile>
                                        <span className="text-foreground font-serif text-xl font-bold tabular-nums md:text-2xl">
                                            {numberFormatter.format(stats.available_online)}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Digital eBooks</span>
                                </div>

                                <div className="border-border/60 bg-background/80 flex flex-col gap-1 rounded-xl border p-3 shadow-2xs backdrop-blur-xs">
                                    <div className="flex items-center justify-between gap-2">
                                        <IconTile variant="soft" size="sm" className="text-rose-500">
                                            <Heart />
                                        </IconTile>
                                        <span className="text-foreground font-serif text-xl font-bold tabular-nums md:text-2xl">
                                            {numberFormatter.format(stats.favorites)}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Saved Shelf</span>
                                </div>
                            </div>
                        </div>
                    </FramePanel>
                </Frame>

                {/* ── Spectrum UI Fast Search & Filtering Deck ── */}
                <Frame variant="default" spacing="sm" className="border-border/80 bg-card/70 shadow-xs">
                    <FramePanel className="space-y-4 p-4 md:p-5">
                        {/* ── Shelf Navigator Tabs ── */}
                        <div className="flex scrollbar-none items-center gap-1.5 overflow-x-auto pb-1">
                            {quickShelves.map((shelf) => {
                                const Icon = shelf.icon;
                                return (
                                    <button
                                        key={shelf.id}
                                        type="button"
                                        onClick={shelf.apply}
                                        className={cn(
                                            "inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all select-none",
                                            shelf.isActive
                                                ? "bg-primary text-primary-foreground font-semibold shadow-xs"
                                                : "bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground",
                                        )}
                                    >
                                        <Icon className="size-3.5" />
                                        <span>{shelf.label}</span>
                                        {shelf.count !== null && (
                                            <span
                                                className={cn(
                                                    "py-0.2 rounded-full px-1.5 text-[10px] font-semibold tabular-nums",
                                                    shelf.isActive
                                                        ? "bg-primary-foreground/20 text-primary-foreground"
                                                        : "bg-background/80 text-foreground",
                                                )}
                                            >
                                                {numberFormatter.format(shelf.count)}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {/* ── Main Search & Filter Row ── */}
                        <div className="grid gap-3 lg:grid-cols-[1.8fr_repeat(3,minmax(8rem,1fr))_auto] lg:items-center">
                            {/* Spectrum UI BeamSearch Input */}
                            <div className="min-w-0">
                                <BeamSearch
                                    ref={searchInputRef}
                                    value={search}
                                    onChange={handleSearchChange}
                                    onClear={handleSearchClear}
                                    placeholder="Search by title, author, call number, or ISBN..."
                                    size="default"
                                    className="bg-background/90"
                                    trailing={
                                        <div className="flex items-center gap-1">
                                            <KbdKey size="sm">/</KbdKey>
                                        </div>
                                    }
                                />
                            </div>

                            {/* Category Filter */}
                            <div>
                                <Select
                                    value={filters.category_id ? String(filters.category_id) : "all"}
                                    onValueChange={(val) => visitWith({ category_id: val === "all" ? null : Number(val) })}
                                >
                                    <SelectTrigger className="bg-background/90 h-10 w-full rounded-lg text-xs font-medium">
                                        <SelectValue placeholder="All Categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        {options.categories.map((cat) => (
                                            <SelectItem key={cat.id} value={String(cat.id)}>
                                                {cat.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Publication Year Filter */}
                            <div>
                                <Select
                                    value={filters.year ? String(filters.year) : "all"}
                                    onValueChange={(val) => visitWith({ year: val === "all" ? null : Number(val) })}
                                >
                                    <SelectTrigger className="bg-background/90 h-10 w-full rounded-lg text-xs font-medium">
                                        <SelectValue placeholder="All Years" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Years</SelectItem>
                                        {options.years.map((year) => (
                                            <SelectItem key={year} value={String(year)}>
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Sort Filter */}
                            <div>
                                <Select value={filters.sort} onValueChange={(val) => visitWith({ sort: val })}>
                                    <SelectTrigger className="bg-background/90 h-10 w-full rounded-lg text-xs font-medium">
                                        <div className="flex items-center gap-1.5 truncate">
                                            <ArrowUpDown className="text-muted-foreground size-3 shrink-0" />
                                            <SelectValue />
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="title">Title (A–Z)</SelectItem>
                                        <SelectItem value="year_newest">Newest Publication</SelectItem>
                                        <SelectItem value="year_oldest">Oldest Publication</SelectItem>
                                        <SelectItem value="recently_added">Recently Catalogued</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Layout Toggle (Grid vs List) & Reset */}
                            <div className="flex items-center gap-1.5">
                                <div className="border-border/80 bg-muted/40 inline-flex rounded-lg border p-0.5">
                                    <button
                                        type="button"
                                        onClick={() => setViewMode("grid")}
                                        className={cn(
                                            "flex size-9 items-center justify-center rounded-md transition-colors",
                                            viewMode === "grid"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                        aria-label="Grid view"
                                        title="Grid view"
                                    >
                                        <LayoutGrid className="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setViewMode("list")}
                                        className={cn(
                                            "flex size-9 items-center justify-center rounded-md transition-colors",
                                            viewMode === "list"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                        aria-label="List view"
                                        title="Academic list view"
                                    >
                                        <List className="size-4" />
                                    </button>
                                </div>

                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={clearAllFilters}
                                        className="text-muted-foreground hover:text-foreground h-10 w-10"
                                        aria-label="Reset all filters"
                                        title="Reset all filters"
                                    >
                                        <RotateCcw className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* ── Active Filter Pills ── */}
                        {hasFilters && (
                            <div className="flex flex-wrap items-center gap-1.5 pt-1 text-xs">
                                <span className="text-muted-foreground mr-1 text-[11px] font-semibold tracking-wider uppercase">Active Filters:</span>
                                {filters.search && (
                                    <Badge variant="outline" radius="full" size="sm" className="bg-background gap-1">
                                        <span>Query: "{filters.search}"</span>
                                        <button
                                            type="button"
                                            onClick={() => handleSearchClear()}
                                            className="hover:text-destructive text-muted-foreground"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                )}
                                {currentCategory && (
                                    <Badge variant="outline" radius="full" size="sm" className="bg-background gap-1">
                                        <span>Category: {currentCategory.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => visitWith({ category_id: null })}
                                            className="hover:text-destructive text-muted-foreground"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                )}
                                {filters.year && (
                                    <Badge variant="outline" radius="full" size="sm" className="bg-background gap-1">
                                        <span>Year: {filters.year}</span>
                                        <button
                                            type="button"
                                            onClick={() => visitWith({ year: null })}
                                            className="hover:text-destructive text-muted-foreground"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                )}
                                {filters.availability !== "all" && (
                                    <Badge variant="outline" radius="full" size="sm" className="bg-background gap-1">
                                        <span>Format: {filters.availability === "online" ? "Digital Only" : "Physical Only"}</span>
                                        <button
                                            type="button"
                                            onClick={() => visitWith({ availability: "all" })}
                                            className="hover:text-destructive text-muted-foreground"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                )}
                                {filters.collection !== "all" && (
                                    <Badge variant="outline" radius="full" size="sm" className="bg-background gap-1">
                                        <span>Shelf: {filters.collection === "favorites" ? "My Favorites" : "Recently Read"}</span>
                                        <button
                                            type="button"
                                            onClick={() => visitWith({ collection: "all" })}
                                            className="hover:text-destructive text-muted-foreground"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                )}
                                <button
                                    type="button"
                                    onClick={clearAllFilters}
                                    className="text-primary hover:text-primary/80 ml-1 text-[11px] font-semibold underline underline-offset-2"
                                >
                                    Clear all
                                </button>
                            </div>
                        )}
                    </FramePanel>
                </Frame>

                {/* ── Catalog Results Header & Content ── */}
                <section aria-labelledby="catalog-results-heading" className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3 px-1">
                        <div>
                            <h2 id="catalog-results-heading" className="text-foreground font-serif text-xl font-semibold tracking-tight md:text-2xl">
                                {filters.collection === "favorites"
                                    ? "Your Personal Saved Shelf"
                                    : filters.collection === "recent"
                                      ? "Recently Opened Texts"
                                      : filters.availability === "online"
                                        ? "Direct-Read Digital Editions"
                                        : "Campus Library Catalog"}
                            </h2>
                            <p className="text-muted-foreground text-xs md:text-sm">
                                {books.total === 0
                                    ? "No matching catalog entries"
                                    : `Showing ${numberFormatter.format(books.from ?? 0)}–${numberFormatter.format(books.to ?? 0)} of ${numberFormatter.format(books.total)} titles`}
                            </p>
                        </div>
                    </div>

                    {/* ── Grid View ── */}
                    {books.data.length > 0 && viewMode === "grid" && (
                        <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                            {books.data.map((book, index) => (
                                <BookCard key={book.id} book={book} priority={index < 6} />
                            ))}
                        </div>
                    )}

                    {/* ── Academic List View ── */}
                    {books.data.length > 0 && viewMode === "list" && (
                        <Frame variant="default" spacing="xs" className="border-border/80 bg-card/60 overflow-hidden shadow-xs">
                            <div className="divide-border/60 divide-y">
                                {books.data.map((book) => (
                                    <div
                                        key={book.id}
                                        className="group hover:bg-muted/40 flex flex-col gap-3 p-3.5 transition-colors sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 items-center gap-3.5">
                                            <div className="h-16 w-12 shrink-0 overflow-hidden rounded-md border shadow-2xs">
                                                <BookCover
                                                    title={book.title}
                                                    author={book.author}
                                                    coverUrl={book.cover_image_url}
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Link
                                                        href={showBook.url(book.id)}
                                                        prefetch
                                                        className="text-foreground hover:text-primary line-clamp-1 font-serif text-sm font-semibold transition-colors"
                                                    >
                                                        {book.title}
                                                    </Link>
                                                    {book.available_online ? (
                                                        <Badge variant="success-light" size="xs" radius="full">
                                                            Online eBook
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="invert-light" size="xs" radius="full">
                                                            Physical Copy
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {book.author || "Unknown author"}
                                                    {book.category && <span> • {book.category}</span>}
                                                    {book.publication_year && <span> • {book.publication_year}</span>}
                                                </p>
                                                <p className="text-muted-foreground/80 line-clamp-1 text-xs">
                                                    {book.description || "No catalog synopsis."}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2 self-end sm:self-center">
                                            <Link
                                                href={showBook.url(book.id)}
                                                prefetch
                                                className={cn(buttonVariants({ variant: "outline", size: "sm" }), "gap-1.5 rounded-lg text-xs")}
                                            >
                                                {book.available_online ? <BookOpen className="size-3.5" /> : <Bookmark className="size-3.5" />}
                                                {book.available_online ? "Read eBook" : "Details"}
                                            </Link>
                                            <Button
                                                type="button"
                                                size="icon-sm"
                                                variant="ghost"
                                                className="rounded-lg"
                                                onClick={() => {
                                                    const next = !book.is_favorite;
                                                    const routeDefinition = next ? favoriteBook(book.id) : unfavoriteBook(book.id);
                                                    router.visit(routeDefinition.url, {
                                                        method: routeDefinition.method,
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                        only: ["books", "stats"],
                                                    });
                                                }}
                                                aria-label={book.is_favorite ? "Remove from shelf" : "Save to shelf"}
                                            >
                                                <Heart className={cn("size-3.5", book.is_favorite && "fill-rose-500 text-rose-500")} />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Frame>
                    )}

                    {/* ── Empty State ── */}
                    {books.data.length === 0 && (
                        <Frame variant="default" spacing="default" className="border-border/80 bg-card/60">
                            <FramePanel className="flex min-h-72 flex-col items-center justify-center gap-4 rounded-xl p-8 text-center">
                                <IconTile variant="soft" size="lg" className="text-primary">
                                    <Search />
                                </IconTile>
                                <div className="max-w-md space-y-1">
                                    <h3 className="text-foreground font-serif text-lg font-semibold">No volumes match your criteria</h3>
                                    <p className="text-muted-foreground text-xs leading-relaxed">
                                        Try adjusting your search terms, selecting a different classification category, or resetting active filters.
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center justify-center gap-2 pt-2">
                                    <Button type="button" variant="outline" size="sm" onClick={clearAllFilters} className="rounded-lg">
                                        <RotateCcw className="mr-1.5 size-3.5" />
                                        Clear all filters
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => visitWith({ availability: "online" })}
                                        className="rounded-lg"
                                    >
                                        <BookOpen className="mr-1.5 size-3.5" />
                                        Browse Digital Editions
                                    </Button>
                                </div>
                            </FramePanel>
                        </Frame>
                    )}

                    {/* ── Pagination ── */}
                    {books.last_page > 1 && (
                        <Frame variant="ghost" spacing="xs" className="pt-2">
                            <FramePanel className="border-border/60 bg-card/50 flex flex-col gap-3 rounded-xl border p-3 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-muted-foreground text-center text-xs tabular-nums sm:text-left">
                                    Page <span className="text-foreground font-medium">{books.current_page}</span> of{" "}
                                    <span className="text-foreground font-medium">{books.last_page}</span> ({numberFormatter.format(books.total)}{" "}
                                    total works)
                                </p>
                                <nav className="flex items-center justify-center gap-2" aria-label="Catalog pagination">
                                    {books.prev_page_url ? (
                                        <Link
                                            href={books.prev_page_url}
                                            preserveScroll
                                            className={cn(buttonVariants({ variant: "outline", size: "sm" }), "gap-1 rounded-lg text-xs")}
                                        >
                                            <ChevronLeft className="size-3.5" />
                                            Previous
                                        </Link>
                                    ) : (
                                        <Button variant="outline" size="sm" disabled className="gap-1 rounded-lg text-xs">
                                            <ChevronLeft className="size-3.5" />
                                            Previous
                                        </Button>
                                    )}

                                    <div className="flex items-center gap-1 px-2 text-xs font-medium">
                                        <span className="bg-primary/10 text-primary rounded-md px-2 py-1 font-semibold tabular-nums">
                                            {books.current_page}
                                        </span>
                                    </div>

                                    {books.next_page_url ? (
                                        <Link
                                            href={books.next_page_url}
                                            preserveScroll
                                            className={cn(buttonVariants({ variant: "outline", size: "sm" }), "gap-1 rounded-lg text-xs")}
                                        >
                                            Next
                                            <ChevronRight className="size-3.5" />
                                        </Link>
                                    ) : (
                                        <Button variant="outline" size="sm" disabled className="gap-1 rounded-lg text-xs">
                                            Next
                                            <ChevronRight className="size-3.5" />
                                        </Button>
                                    )}
                                </nav>
                            </FramePanel>
                        </Frame>
                    )}
                </section>
            </div>
        </PortalLayout>
    );
}
