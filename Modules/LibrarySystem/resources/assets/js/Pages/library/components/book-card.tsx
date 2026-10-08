import { Badge } from "@/components/reui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { favorite, show, unfavorite } from "@/routes/library/books";
import { Link, router } from "@inertiajs/react";
import { BookOpen, Bookmark, Heart, LibraryBig, Sparkles } from "lucide-react";
import { useEffect, useState } from "react";
import { BookCover } from "./book-cover";

export interface LibraryBookCardData {
    id: number;
    title: string;
    author: string | null;
    category: string | null;
    category_color: string | null;
    publication_year: number | null;
    description: string;
    cover_image_url: string | null;
    available_online: boolean;
    is_favorite: boolean;
}

export function BookCard({ book, priority = false }: { book: LibraryBookCardData; priority?: boolean }) {
    const [isFavorite, setIsFavorite] = useState(book.is_favorite);
    const [favoritePending, setFavoritePending] = useState(false);

    useEffect(() => setIsFavorite(book.is_favorite), [book.is_favorite]);

    const toggleFavorite = () => {
        const next = !isFavorite;
        setIsFavorite(next);
        setFavoritePending(true);

        const routeDefinition = next ? favorite(book.id) : unfavorite(book.id);
        router.visit(routeDefinition.url, {
            method: routeDefinition.method,
            preserveScroll: true,
            preserveState: true,
            only: ["books", "stats", "state"],
            onError: () => setIsFavorite(!next),
            onFinish: () => setFavoritePending(false),
        });
    };

    return (
        <article className="group border-border/70 bg-card/90 hover:border-primary/40 relative flex min-h-full flex-col overflow-hidden rounded-2xl border shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
            <Link href={show.url(book.id)} prefetch className="relative block aspect-3/4 overflow-hidden">
                <BookCover title={book.title} author={book.author} coverUrl={book.cover_image_url} priority={priority} />
                <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 bg-linear-to-t from-black/85 via-black/30 to-transparent p-3 pt-12">
                    {book.available_online ? (
                        <Badge
                            variant="success-light"
                            radius="full"
                            size="sm"
                            className="border border-emerald-400/30 bg-emerald-950/80 font-semibold tracking-wide text-emerald-200 backdrop-blur-md"
                        >
                            <Sparkles className="mr-1 size-3 text-emerald-400" />
                            Available Online
                        </Badge>
                    ) : (
                        <Badge
                            variant="invert-light"
                            radius="full"
                            size="sm"
                            className="border border-white/20 bg-black/60 font-medium tracking-wide text-white/90 backdrop-blur-md"
                        >
                            <LibraryBig className="mr-1 size-3 text-white/70" />
                            Physical Catalog
                        </Badge>
                    )}
                </div>
            </Link>

            <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="space-y-1.5">
                    <div className="flex flex-wrap items-center gap-1.5 text-xs">
                        {book.category && (
                            <Badge
                                variant="outline"
                                size="xs"
                                radius="full"
                                className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase"
                            >
                                {book.category}
                            </Badge>
                        )}
                        {book.publication_year && (
                            <span className="text-muted-foreground/80 text-[11px] font-medium tabular-nums">• {book.publication_year}</span>
                        )}
                    </div>
                    <div>
                        <Link href={show.url(book.id)} prefetch className="hover:text-primary transition-colors">
                            <h3 className="line-clamp-2 font-serif text-base leading-snug font-semibold text-balance">{book.title}</h3>
                        </Link>
                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">{book.author || "Unknown author"}</p>
                    </div>
                    <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed">
                        {book.description || "No description available in catalogue records."}
                    </p>
                </div>

                <div className="mt-auto flex items-center gap-2 pt-2">
                    <Link
                        href={show.url(book.id)}
                        prefetch
                        className={cn(
                            buttonVariants({ size: "sm" }),
                            "flex-1 gap-1.5 rounded-lg text-xs font-semibold shadow-xs transition-transform active:scale-95",
                        )}
                    >
                        {book.available_online ? <BookOpen className="size-3.5" /> : <Bookmark className="size-3.5" />}
                        {book.available_online ? "Read eBook" : "View Details"}
                    </Link>
                    <Button
                        type="button"
                        size="icon-sm"
                        variant="outline"
                        className="shrink-0 rounded-lg transition-colors"
                        onClick={toggleFavorite}
                        disabled={favoritePending}
                        aria-label={isFavorite ? `Remove ${book.title} from favorites` : `Add ${book.title} to favorites`}
                        aria-pressed={isFavorite}
                    >
                        <Heart className={cn("size-3.5", isFavorite && "fill-rose-500 text-rose-500")} />
                    </Button>
                </div>
            </div>
        </article>
    );
}
