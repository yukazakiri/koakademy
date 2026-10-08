import PortalLayout from "@/components/portal-layout";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { progress, show } from "@/routes/library/books";
import { destroy as destroyBookmark, store as storeBookmark } from "@/routes/library/books/bookmarks";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    Bookmark,
    ChevronLeft,
    ChevronRight,
    Download,
    ExternalLink,
    Fullscreen,
    LoaderCircle,
    Minus,
    PanelLeftClose,
    PanelLeftOpen,
    Plus,
    Search,
    X,
} from "lucide-react";
import { GlobalWorkerOptions, getDocument, type PDFDocumentProxy, type PDFPageProxy, type RenderTask } from "pdfjs-dist";
import pdfWorker from "pdfjs-dist/build/pdf.worker.min.mjs?url";
import { useEffect, useRef, useState } from "react";

GlobalWorkerOptions.workerSrc = pdfWorker;

interface ReaderBookmark {
    id: number;
    page: number;
    label: string | null;
}

interface Props {
    auth: { user: User };
    book: {
        id: number;
        title: string;
        author: string | null;
        downloads_allowed: boolean;
    };
    reader: {
        content_url: string;
        download_url: string | null;
        last_page: number;
        total_pages: number | null;
        bookmarks: ReaderBookmark[];
    };
}

export default function DigitalLibraryReader({ auth, book, reader }: Props) {
    const [document, setDocument] = useState<PDFDocumentProxy | null>(null);
    const [currentPage, setCurrentPage] = useState(Math.max(1, reader.last_page));
    const [pageCount, setPageCount] = useState(reader.total_pages ?? 0);
    const [zoom, setZoom] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [bookmarks, setBookmarks] = useState(reader.bookmarks);
    const [searchQuery, setSearchQuery] = useState("");
    const [searching, setSearching] = useState(false);
    const [searchResults, setSearchResults] = useState<number[]>([]);
    const readerShellRef = useRef<HTMLDivElement>(null);
    const progressTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const loadingTask = getDocument({
            url: reader.content_url,
        });
        let cancelled = false;

        loadingTask.promise
            .then((pdf) => {
                if (cancelled) {
                    void loadingTask.destroy();
                    return;
                }
                setDocument(pdf);
                setPageCount(pdf.numPages);
                setCurrentPage((page) => Math.min(Math.max(1, page), pdf.numPages));
                setLoading(false);
            })
            .catch(() => {
                if (!cancelled) {
                    setError("The PDF could not be loaded. The secure reading link may have expired or storage may be unavailable.");
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
            void loadingTask.destroy();
        };
    }, [reader.content_url]);

    useEffect(() => {
        if (pageCount < 1 || loading) return;
        if (progressTimer.current) clearTimeout(progressTimer.current);

        progressTimer.current = setTimeout(() => {
            void window.axios.put(progress.url(book.id), {
                last_page: currentPage,
                total_pages: pageCount,
            });
        }, 700);

        return () => {
            if (progressTimer.current) clearTimeout(progressTimer.current);
        };
    }, [book.id, currentPage, loading, pageCount]);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
            if (event.key === "ArrowLeft" || event.key === "PageUp") {
                setCurrentPage((page) => Math.max(1, page - 1));
            }
            if (event.key === "ArrowRight" || event.key === "PageDown") {
                setCurrentPage((page) => Math.min(pageCount, page + 1));
            }
            if (event.key === "+" || event.key === "=") {
                setZoom((value) => Math.min(2.5, Number((value + 0.1).toFixed(1))));
            }
            if (event.key === "-") {
                setZoom((value) => Math.max(0.6, Number((value - 0.1).toFixed(1))));
            }
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [pageCount]);

    const toggleBookmark = async () => {
        const existing = bookmarks.find((bookmark) => bookmark.page === currentPage);

        if (existing) {
            await window.axios.delete(destroyBookmark.url({ book: book.id, bookmark: existing.id }));
            setBookmarks((items) => items.filter((bookmark) => bookmark.id !== existing.id));
            return;
        }

        const response = await window.axios.post<ReaderBookmark>(storeBookmark.url(book.id), {
            page: currentPage,
            label: `Page ${currentPage}`,
        });
        setBookmarks((items) => [...items, response.data].sort((a, b) => a.page - b.page));
    };

    const searchDocument = async () => {
        const term = searchQuery.trim().toLocaleLowerCase();
        if (!document || term === "") {
            setSearchResults([]);
            return;
        }

        setSearching(true);
        const matches: number[] = [];

        try {
            for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber += 1) {
                const page = await document.getPage(pageNumber);
                const content = await page.getTextContent();
                const pageText = content.items
                    .map((item) => ("str" in item ? item.str : ""))
                    .join(" ")
                    .toLocaleLowerCase();

                if (pageText.includes(term)) matches.push(pageNumber);
            }
            setSearchResults(matches);
        } finally {
            setSearching(false);
        }
    };

    const toggleFullscreen = async () => {
        if (!readerShellRef.current) return;
        if (documentFullscreen()) {
            await window.document.exitFullscreen();
            return;
        }
        await readerShellRef.current.requestFullscreen();
    };

    return (
        <PortalLayout user={auth.user}>
            <Head title={`Read ${book.title}`} />

            <div
                ref={readerShellRef}
                className="bg-background border-border flex min-h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-4xl border shadow-xl"
            >
                <header className="border-border bg-card/95 flex flex-wrap items-center justify-between gap-3 border-b px-3 py-3 backdrop-blur md:px-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <Link
                            href={show.url(book.id)}
                            className={cn(buttonVariants({ variant: "ghost", size: "icon" }), "rounded-lg")}
                            aria-label="Back to book details"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                        <Button variant="ghost" size="icon" onClick={() => setSidebarOpen((open) => !open)} aria-label="Toggle reader sidebar">
                            {sidebarOpen ? <PanelLeftClose className="size-4" /> : <PanelLeftOpen className="size-4" />}
                        </Button>
                        <div className="min-w-0">
                            <h1 className="truncate font-serif text-sm font-semibold sm:text-base">{book.title}</h1>
                            <p className="text-muted-foreground hidden truncate text-xs sm:block">{book.author || "Unknown author"}</p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-1.5">
                        <div className="border-border bg-background flex items-center rounded-xl border">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 rounded-xl"
                                disabled={currentPage <= 1}
                                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                                aria-label="Previous page"
                            >
                                <ChevronLeft className="size-4" />
                            </Button>
                            <label className="flex items-center gap-1 text-xs tabular-nums">
                                <span className="sr-only">Current page</span>
                                <Input
                                    value={currentPage}
                                    type="number"
                                    min={1}
                                    max={pageCount || 1}
                                    onChange={(event) => setCurrentPage(Math.min(pageCount || 1, Math.max(1, Number(event.target.value) || 1)))}
                                    className="h-7 w-12 border-0 bg-transparent px-1 text-center text-xs shadow-none"
                                />
                                <span className="text-muted-foreground pr-2">/ {pageCount || "—"}</span>
                            </label>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 rounded-xl"
                                disabled={currentPage >= pageCount}
                                onClick={() => setCurrentPage((page) => Math.min(pageCount, page + 1))}
                                aria-label="Next page"
                            >
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>

                        <div className="border-border bg-background hidden items-center rounded-xl border sm:flex">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 rounded-xl"
                                onClick={() => setZoom((value) => Math.max(0.6, Number((value - 0.1).toFixed(1))))}
                                aria-label="Zoom out"
                            >
                                <Minus className="size-4" />
                            </Button>
                            <span className="text-muted-foreground w-12 text-center text-xs tabular-nums">{Math.round(zoom * 100)}%</span>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 rounded-xl"
                                onClick={() => setZoom((value) => Math.min(2.5, Number((value + 0.1).toFixed(1))))}
                                aria-label="Zoom in"
                            >
                                <Plus className="size-4" />
                            </Button>
                        </div>

                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => void toggleBookmark()}
                            aria-label={bookmarks.some((bookmark) => bookmark.page === currentPage) ? "Remove page bookmark" : "Bookmark page"}
                        >
                            <Bookmark
                                className={cn(
                                    "size-4",
                                    bookmarks.some((bookmark) => bookmark.page === currentPage) && "fill-amber-500 text-amber-600",
                                )}
                            />
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => void toggleFullscreen()} aria-label="Enter fullscreen">
                            <Fullscreen className="size-4" />
                        </Button>
                        <a
                            href={reader.content_url}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: "ghost", size: "icon" }), "rounded-lg")}
                            aria-label="Open PDF in a new tab"
                        >
                            <ExternalLink className="size-4" />
                        </a>
                        {reader.download_url && (
                            <a
                                href={reader.download_url}
                                className={cn(buttonVariants({ variant: "ghost", size: "icon" }), "rounded-lg")}
                                aria-label="Download PDF"
                            >
                                <Download className="size-4" />
                            </a>
                        )}
                    </div>
                </header>

                <div className="flex min-h-0 flex-1">
                    {sidebarOpen && (
                        <aside className="border-border bg-card hidden w-64 shrink-0 border-r md:block lg:w-72">
                            <Tabs defaultValue="pages" className="flex h-full flex-col">
                                <TabsList className="mx-3 mt-3 grid grid-cols-3">
                                    <TabsTrigger value="pages">Pages</TabsTrigger>
                                    <TabsTrigger value="bookmarks">Saved</TabsTrigger>
                                    <TabsTrigger value="search">Search</TabsTrigger>
                                </TabsList>
                                <TabsContent value="pages" className="min-h-0 flex-1">
                                    <ScrollArea className="h-[calc(100vh-15rem)] px-3">
                                        <div className="grid grid-cols-2 gap-3 pb-4">
                                            {document &&
                                                Array.from({ length: pageCount }, (_, index) => index + 1).map((pageNumber) => (
                                                    <PdfThumbnail
                                                        key={pageNumber}
                                                        document={document}
                                                        pageNumber={pageNumber}
                                                        active={pageNumber === currentPage}
                                                        onSelect={setCurrentPage}
                                                    />
                                                ))}
                                        </div>
                                    </ScrollArea>
                                </TabsContent>
                                <TabsContent value="bookmarks" className="min-h-0 flex-1">
                                    <ScrollArea className="h-[calc(100vh-15rem)] px-3">
                                        <div className="space-y-2 pb-4">
                                            {bookmarks.length > 0 ? (
                                                bookmarks.map((bookmark) => (
                                                    <button
                                                        key={bookmark.id}
                                                        type="button"
                                                        onClick={() => setCurrentPage(bookmark.page)}
                                                        className="hover:bg-muted flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors"
                                                    >
                                                        <Bookmark className="size-4 fill-amber-500 text-amber-600" />
                                                        <span className="text-sm font-medium">{bookmark.label || `Page ${bookmark.page}`}</span>
                                                    </button>
                                                ))
                                            ) : (
                                                <EmptySidebar message="Bookmark a page to find it quickly later." />
                                            )}
                                        </div>
                                    </ScrollArea>
                                </TabsContent>
                                <TabsContent value="search" className="min-h-0 flex-1 px-3">
                                    <form
                                        className="flex gap-2"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            void searchDocument();
                                        }}
                                    >
                                        <Input
                                            value={searchQuery}
                                            onChange={(event) => setSearchQuery(event.target.value)}
                                            placeholder="Search inside this book"
                                            aria-label="Search PDF text"
                                        />
                                        <Button size="icon" disabled={searching} aria-label="Search">
                                            {searching ? <LoaderCircle className="size-4 animate-spin" /> : <Search className="size-4" />}
                                        </Button>
                                    </form>
                                    <ScrollArea className="mt-3 h-[calc(100vh-19rem)]">
                                        <div className="space-y-2 pb-4">
                                            {searchResults.map((pageNumber) => (
                                                <button
                                                    key={pageNumber}
                                                    type="button"
                                                    onClick={() => setCurrentPage(pageNumber)}
                                                    className="hover:bg-muted flex w-full items-center gap-3 rounded-xl border p-3 text-left text-sm transition-colors"
                                                >
                                                    <Search className="text-muted-foreground size-4" />
                                                    Match on page {pageNumber}
                                                </button>
                                            ))}
                                            {!searching && searchQuery && searchResults.length === 0 && (
                                                <EmptySidebar message="No matching pages found. Search ignores formatting but requires exact words." />
                                            )}
                                        </div>
                                    </ScrollArea>
                                </TabsContent>
                            </Tabs>
                        </aside>
                    )}

                    <main className="bg-muted/35 relative flex min-w-0 flex-1 items-start justify-center overflow-auto p-3 md:p-6">
                        {loading && (
                            <div className="text-muted-foreground flex min-h-96 flex-col items-center justify-center gap-3">
                                <LoaderCircle className="size-8 animate-spin" />
                                <p className="text-sm">Opening the secure digital edition…</p>
                            </div>
                        )}
                        {error && (
                            <div className="bg-card my-16 max-w-lg rounded-2xl border p-8 text-center shadow-sm">
                                <X className="text-destructive mx-auto size-8" />
                                <h2 className="mt-4 font-serif text-xl font-semibold">Reader unavailable</h2>
                                <p className="text-muted-foreground mt-2 text-sm leading-6">{error}</p>
                                <Link href={show.url(book.id)} className={cn(buttonVariants({ variant: "outline" }), "mt-5 rounded-lg")}>
                                    Return to book details
                                </Link>
                            </div>
                        )}
                        {!loading && !error && document && <PdfPageCanvas document={document} pageNumber={currentPage} zoom={zoom} />}
                    </main>
                </div>
            </div>
        </PortalLayout>
    );
}

function PdfPageCanvas({ document, pageNumber, zoom }: { document: PDFDocumentProxy; pageNumber: number; zoom: number }) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const renderTaskRef = useRef<RenderTask | null>(null);
    const [width, setWidth] = useState(900);

    useEffect(() => {
        if (!containerRef.current) return;
        const observer = new ResizeObserver(([entry]) => setWidth(Math.max(320, entry.contentRect.width)));
        observer.observe(containerRef.current);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        let cancelled = false;

        void document.getPage(pageNumber).then(async (page) => {
            if (cancelled || !canvasRef.current) return;
            renderTaskRef.current?.cancel();

            const baseViewport = page.getViewport({ scale: 1 });
            const cssScale = Math.min(2, (width - 4) / baseViewport.width) * zoom;
            const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
            const viewport = page.getViewport({ scale: cssScale * pixelRatio });
            const canvas = canvasRef.current;
            const context = canvas.getContext("2d");
            if (!context) return;

            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = `${Math.floor(viewport.width / pixelRatio)}px`;
            canvas.style.height = `${Math.floor(viewport.height / pixelRatio)}px`;

            const task = page.render({ canvas, canvasContext: context, viewport });
            renderTaskRef.current = task;
            try {
                await task.promise;
            } catch (renderError) {
                if ((renderError as Error).name !== "RenderingCancelledException") throw renderError;
            }
        });

        return () => {
            cancelled = true;
            renderTaskRef.current?.cancel();
        };
    }, [document, pageNumber, width, zoom]);

    return (
        <div ref={containerRef} className="flex w-full min-w-xs justify-center">
            <canvas ref={canvasRef} className="bg-white shadow-2xl ring-1 ring-black/10" aria-label={`Page ${pageNumber}`} />
        </div>
    );
}

function PdfThumbnail({
    document,
    pageNumber,
    active,
    onSelect,
}: {
    document: PDFDocumentProxy;
    pageNumber: number;
    active: boolean;
    onSelect: (page: number) => void;
}) {
    const wrapperRef = useRef<HTMLButtonElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!wrapperRef.current) return;
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setVisible(true);
                observer.disconnect();
            }
        });
        observer.observe(wrapperRef.current);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!visible || !canvasRef.current) return;
        let task: RenderTask | null = null;
        let cancelled = false;

        void document.getPage(pageNumber).then(async (page: PDFPageProxy) => {
            if (cancelled || !canvasRef.current) return;
            const viewport = page.getViewport({ scale: 0.22 });
            const canvas = canvasRef.current;
            const context = canvas.getContext("2d");
            if (!context) return;
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            task = page.render({ canvas, canvasContext: context, viewport });
            try {
                await task.promise;
            } catch (renderError) {
                if ((renderError as Error).name !== "RenderingCancelledException") throw renderError;
            }
        });

        return () => {
            cancelled = true;
            task?.cancel();
        };
    }, [document, pageNumber, visible]);

    return (
        <button
            ref={wrapperRef}
            type="button"
            onClick={() => onSelect(pageNumber)}
            className={cn(
                "hover:border-primary/50 flex flex-col items-center gap-2 rounded-xl border p-2 transition",
                active && "border-primary bg-primary/5 ring-primary/20 ring-2",
            )}
            aria-label={`Go to page ${pageNumber}`}
            aria-current={active ? "page" : undefined}
        >
            <div className="bg-muted flex aspect-3/4 w-full items-center justify-center overflow-hidden rounded-md">
                {visible ? (
                    <canvas ref={canvasRef} className="max-h-full max-w-full bg-white" />
                ) : (
                    <LoaderCircle className="text-muted-foreground size-4 animate-spin" />
                )}
            </div>
            <span className="text-muted-foreground text-xs tabular-nums">{pageNumber}</span>
        </button>
    );
}

function EmptySidebar({ message }: { message: string }) {
    return <p className="text-muted-foreground rounded-xl border border-dashed p-4 text-center text-sm leading-6">{message}</p>;
}

function documentFullscreen(): boolean {
    return window.document.fullscreenElement !== null;
}
