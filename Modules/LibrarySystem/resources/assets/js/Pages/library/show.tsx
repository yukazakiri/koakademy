import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button, buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { index as libraryIndex } from "@/routes/library";
import { download, favorite, read, unfavorite } from "@/routes/library/books";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowLeft,
    BookOpen,
    Building2,
    Calendar,
    ChevronRight,
    Download,
    FileText,
    Heart,
    LibraryBig,
    Mail,
    MapPin,
    Scale,
    ShieldCheck,
    Sparkles,
    UserRound,
} from "lucide-react";
import React, { useState } from "react";
import { BookCard, type LibraryBookCardData } from "./components/book-card";
import { BookCover } from "./components/book-cover";

interface BookDetail {
    id: number;
    title: string;
    isbn: string | null;
    call_number: string | null;
    accession_number: string | null;
    author: string | null;
    author_biography: string | null;
    category: string | null;
    category_color: string | null;
    publisher: string | null;
    publication_year: number | null;
    pages: number | null;
    description: string | null;
    location: string | null;
    cover_image_url: string | null;
    available_online: boolean;
    downloads_allowed: boolean;
    rights_basis: string | null;
    rights_holder: string | null;
    license_url: string | null;
    rights_expires_at: string | null;
}

interface Props {
    auth: { user: User };
    book: BookDetail;
    state: {
        is_favorite: boolean;
        last_page: number | null;
        total_pages: number | null;
        last_read_at: string | null;
    };
    related: LibraryBookCardData[];
    takedown_email: string | null;
}

const RIGHTS_LABELS: Record<string, string> = {
    koakademy_owned: "KoAkademy-owned work",
    written_permission: "Written permission",
    licensed: "Licensed distribution",
    open_license: "Open educational license",
    public_domain: "Public domain",
};

export default function DigitalLibraryShow({ auth, book, state, related, takedown_email }: Props) {
    const [isFavorite, setIsFavorite] = useState(state.is_favorite);
    const [favoritePending, setFavoritePending] = useState(false);

    const progress = state.last_page && state.total_pages ? Math.min(100, Math.round((state.last_page / state.total_pages) * 100)) : null;

    const toggleFavorite = () => {
        const next = !isFavorite;
        setIsFavorite(next);
        setFavoritePending(true);

        const routeDefinition = next ? favorite(book.id) : unfavorite(book.id);
        router.visit(routeDefinition.url, {
            method: routeDefinition.method,
            preserveScroll: true,
            preserveState: true,
            only: ["state"],
            onError: () => setIsFavorite(!next),
            onFinish: () => setFavoritePending(false),
        });
    };

    return (
        <PortalLayout user={auth.user}>
            <Head title={`${book.title} • Digital Library`} />

            <div className="flex flex-col gap-6">
                {/* ── Breadcrumb & Back Navigation ── */}
                <div className="flex flex-wrap items-center justify-between gap-3 text-xs">
                    <nav aria-label="Breadcrumb" className="text-muted-foreground flex items-center gap-1.5">
                        <Link
                            href={libraryIndex.url()}
                            className="hover:text-foreground inline-flex items-center gap-1 font-medium transition-colors"
                        >
                            <ArrowLeft className="size-3.5" />
                            Digital Library
                        </Link>
                        <ChevronRight className="text-muted-foreground/50 size-3" />
                        {book.category && (
                            <>
                                <span className="hover:text-foreground transition-colors">{book.category}</span>
                                <ChevronRight className="text-muted-foreground/50 size-3" />
                            </>
                        )}
                        <span className="text-foreground line-clamp-1 max-w-3xs font-semibold">{book.title}</span>
                    </nav>

                    <Link
                        href={libraryIndex.url()}
                        className={cn(buttonVariants({ variant: "outline", size: "xs" }), "gap-1.5 rounded-lg font-medium")}
                    >
                        <ArrowLeft className="size-3" />
                        Back to Catalog
                    </Link>
                </div>

                {/* ── Hero Book Showcase (ReUI Frame & FramePanel) ── */}
                <Frame variant="default" spacing="default" className="border-border/80 bg-card/60 overflow-hidden shadow-xs">
                    <FramePanel className="via-background relative overflow-hidden bg-linear-to-br from-amber-500/10 to-sky-500/10 p-6 md:p-8">
                        <div className="relative z-10 grid gap-8 lg:grid-cols-[minmax(14rem,18rem)_1fr] lg:items-center">
                            {/* Book Cover Container with Depth */}
                            <div className="mx-auto aspect-3/4 w-full max-w-xs overflow-hidden rounded-xl border border-black/15 shadow-xl transition-transform duration-300 hover:scale-[1.02]">
                                <BookCover title={book.title} author={book.author} coverUrl={book.cover_image_url} priority />
                            </div>

                            {/* Book Metadata & Primary Call-to-Actions */}
                            <div className="flex flex-col justify-center gap-5">
                                <div className="space-y-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        {book.category && (
                                            <Badge
                                                variant="outline"
                                                radius="full"
                                                size="sm"
                                                className="text-muted-foreground bg-background/80 font-semibold tracking-wider uppercase"
                                            >
                                                {book.category}
                                            </Badge>
                                        )}
                                        {book.available_online ? (
                                            <Badge variant="success-light" radius="full" size="sm" className="font-semibold tracking-wide">
                                                <Sparkles className="mr-1 size-3 text-emerald-600 dark:text-emerald-400" />
                                                Direct-Read Digital Edition
                                            </Badge>
                                        ) : (
                                            <Badge variant="invert-light" radius="full" size="sm" className="font-medium tracking-wide">
                                                <LibraryBig className="text-muted-foreground mr-1 size-3" />
                                                In-Library Physical Copy
                                            </Badge>
                                        )}
                                        {book.publication_year && (
                                            <Badge
                                                variant="outline"
                                                radius="full"
                                                size="sm"
                                                className="text-muted-foreground font-medium tabular-nums"
                                            >
                                                Published {book.publication_year}
                                            </Badge>
                                        )}
                                    </div>

                                    <div>
                                        <h1 className="text-foreground font-serif text-3xl leading-tight font-semibold tracking-tight md:text-5xl">
                                            {book.title}
                                        </h1>
                                        <p className="text-muted-foreground mt-2 text-base md:text-lg">
                                            {book.author ? `Authored by ${book.author}` : "Author unrecorded in catalog"}
                                        </p>
                                    </div>

                                    <p className="text-muted-foreground max-w-3xl text-sm leading-relaxed">
                                        {book.description || "No catalog synopsis or abstract is recorded for this volume."}
                                    </p>
                                </div>

                                {/* Reading Progress Meter */}
                                {progress !== null && book.available_online && (
                                    <div className="border-border/60 bg-background/70 max-w-md space-y-2 rounded-xl border p-3 shadow-2xs">
                                        <div className="text-muted-foreground flex justify-between text-xs font-semibold tracking-wider uppercase">
                                            <span>Your Reading Progress</span>
                                            <span className="text-foreground font-mono tabular-nums">
                                                {progress}% (Page {state.last_page ?? 1} of {state.total_pages})
                                            </span>
                                        </div>
                                        <div className="bg-muted h-2 overflow-hidden rounded-full">
                                            <div
                                                className="bg-primary h-full rounded-full transition-all duration-500"
                                                style={{ width: `${progress}%` }}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Action Buttons Deck */}
                                <div className="flex flex-wrap items-center gap-3 pt-2">
                                    {book.available_online ? (
                                        <Link
                                            href={read.url(book.id)}
                                            className={cn(
                                                buttonVariants({ size: "default" }),
                                                "gap-2 rounded-xl font-semibold shadow-xs transition-transform active:scale-95",
                                            )}
                                        >
                                            <BookOpen className="size-4" />
                                            {state.last_page && state.last_page > 1
                                                ? `Continue Reading (Page ${state.last_page})`
                                                : "Read Online eBook"}
                                        </Link>
                                    ) : (
                                        <Button size="default" disabled className="gap-2 rounded-xl">
                                            <LibraryBig className="size-4" />
                                            Physical Archive Only
                                        </Button>
                                    )}

                                    {book.available_online && book.downloads_allowed && (
                                        <a
                                            href={download.url(book.id)}
                                            className={cn(
                                                buttonVariants({ variant: "outline", size: "default" }),
                                                "gap-2 rounded-xl font-semibold shadow-2xs transition-transform active:scale-95",
                                            )}
                                        >
                                            <Download className="size-4" />
                                            Download PDF
                                        </a>
                                    )}

                                    <Button
                                        size="default"
                                        variant="outline"
                                        className="gap-2 rounded-xl shadow-2xs transition-colors"
                                        onClick={toggleFavorite}
                                        disabled={favoritePending}
                                        aria-label={isFavorite ? "Remove from saved shelf" : "Save to my shelf"}
                                        aria-pressed={isFavorite}
                                    >
                                        <Heart className={cn("size-4", isFavorite && "fill-rose-500 text-rose-500")} />
                                        <span>{isFavorite ? "Saved on Shelf" : "Save for Later"}</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </FramePanel>
                </Frame>

                {/* ── Technical Catalog Details & Rights Grid ── */}
                <div className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                    <div className="space-y-6">
                        {/* Catalog Metadata Details */}
                        <Frame variant="default" spacing="sm" className="border-border/80 bg-card/60 shadow-xs">
                            <FramePanel className="space-y-4 p-5">
                                <div className="flex items-center gap-2">
                                    <IconTile variant="soft" size="sm" className="text-primary">
                                        <LibraryBig />
                                    </IconTile>
                                    <div>
                                        <h2 className="text-foreground font-serif text-lg font-semibold">Archival Catalog Details</h2>
                                        <p className="text-muted-foreground text-xs">Bibliographic records and physical repository parameters.</p>
                                    </div>
                                </div>

                                <div className="grid gap-4 pt-2 sm:grid-cols-2 xl:grid-cols-3">
                                    <DetailTile icon={UserRound} label="Author" value={book.author} />
                                    <DetailTile icon={Building2} label="Publisher" value={book.publisher} />
                                    <DetailTile icon={Calendar} label="Publication Year" value={book.publication_year?.toString()} />
                                    <DetailTile icon={FileText} label="Length" value={book.pages ? `${book.pages} pages` : null} />
                                    <DetailTile icon={MapPin} label="Stacks Location" value={book.location} />
                                    <DetailTile icon={LibraryBig} label="Call Number" value={book.call_number} />
                                    <DetailTile icon={ShieldCheck} label="Accession No." value={book.accession_number} />
                                    <DetailTile icon={BookOpen} label="ISBN-13" value={book.isbn} />
                                </div>
                            </FramePanel>
                        </Frame>

                        {/* Author Biography */}
                        {book.author_biography && (
                            <Frame variant="default" spacing="sm" className="border-border/80 bg-card/60 shadow-xs">
                                <FramePanel className="space-y-3 p-5">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="sm" className="text-sky-600 dark:text-sky-400">
                                            <UserRound />
                                        </IconTile>
                                        <h2 className="text-foreground font-serif text-lg font-semibold">About {book.author}</h2>
                                    </div>
                                    <p className="text-muted-foreground pl-1 text-sm leading-relaxed">{book.author_biography}</p>
                                </FramePanel>
                            </Frame>
                        )}
                    </div>

                    {/* Rights & Licensing Rail */}
                    <div className="space-y-6">
                        <Frame variant="default" spacing="sm" className="border-border/80 bg-card/60 shadow-xs">
                            <FramePanel className="space-y-4 p-5">
                                <div className="flex items-center gap-2.5">
                                    <IconTile variant="soft" size="sm" className="text-amber-600 dark:text-amber-400">
                                        <Scale />
                                    </IconTile>
                                    <div>
                                        <h2 className="text-foreground font-serif text-base font-semibold">Digital Rights & Licensing</h2>
                                        <p className="text-muted-foreground text-[11px]">Institutional clearance status</p>
                                    </div>
                                </div>

                                <div className="text-muted-foreground space-y-3 text-xs leading-relaxed">
                                    {book.available_online ? (
                                        <>
                                            <div className="border-border/70 bg-background/60 space-y-2 rounded-lg border p-3">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-foreground font-semibold">Licensing Basis:</span>
                                                    <Badge variant="primary-light" size="xs" radius="full">
                                                        {book.rights_basis
                                                            ? (RIGHTS_LABELS[book.rights_basis] ?? book.rights_basis)
                                                            : "Documented permission"}
                                                    </Badge>
                                                </div>
                                                {book.rights_holder && (
                                                    <p>
                                                        <span className="text-foreground font-medium">Rights Holder:</span> {book.rights_holder}
                                                    </p>
                                                )}
                                                {book.license_url && (
                                                    <a
                                                        href={book.license_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-primary hover:text-primary/80 inline-block underline underline-offset-4"
                                                    >
                                                        Review license terms & conditions
                                                    </a>
                                                )}
                                            </div>

                                            <p className="text-muted-foreground/90 text-[11px]">
                                                {book.downloads_allowed
                                                    ? "Digital downloading is authorized for personal scholarly study. Systematic redistribution or commercial reuse is prohibited."
                                                    : "In-browser reading is cleared for campus scholars; local downloading and mass export remain restricted."}
                                            </p>
                                        </>
                                    ) : (
                                        <div className="border-border/70 bg-muted/30 rounded-lg border p-3 text-center">
                                            <p>No digital distribution license is currently filed for this title.</p>
                                            <p className="text-muted-foreground mt-1 text-[11px]">
                                                Please visit the physical library circulation desk to borrow this volume.
                                            </p>
                                        </div>
                                    )}

                                    {takedown_email && (
                                        <div className="border-border/60 text-muted-foreground border-t pt-3 text-[11px]">
                                            <span className="flex items-center gap-1.5">
                                                <Mail className="text-muted-foreground size-3" />
                                                <span>Rights inquiries or takedown requests:</span>
                                            </span>
                                            <a
                                                href={`mailto:${takedown_email}`}
                                                className="text-primary hover:text-primary/80 mt-0.5 ml-4 inline-block font-medium underline underline-offset-2"
                                            >
                                                {takedown_email}
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </FramePanel>
                        </Frame>
                    </div>
                </div>

                {/* ── Related Shelf Recommendations ── */}
                {related.length > 0 && (
                    <section aria-labelledby="related-heading" className="border-border/60 space-y-4 border-t pt-4">
                        <div className="space-y-1">
                            <Badge variant="primary-light" size="xs" radius="full" className="font-semibold tracking-wider uppercase">
                                Same Academic Discipline
                            </Badge>
                            <h2 id="related-heading" className="text-foreground font-serif text-2xl font-semibold">
                                Related Works & Shelf Neighbors
                            </h2>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {related.map((relatedBook) => (
                                <BookCard key={relatedBook.id} book={relatedBook} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PortalLayout>
    );
}

function DetailTile({ icon: Icon, label, value }: { icon: React.ComponentType<{ className?: string }>; label: string; value?: string | null }) {
    return (
        <div className="border-border/60 bg-background/60 flex items-start gap-3 rounded-lg border p-2.5">
            <IconTile variant="soft" size="xs" className="text-muted-foreground mt-0.5 shrink-0">
                <Icon className="size-3.5" />
            </IconTile>
            <div className="min-w-0">
                <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">{label}</p>
                <p className="text-foreground mt-0.5 text-xs font-medium wrap-break-word">{value || "—"}</p>
            </div>
        </div>
    );
}
