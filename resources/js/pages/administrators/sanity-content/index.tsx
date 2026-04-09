import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    FileText,
    Filter,
    Globe,
    Image as ImageIcon,
    LayoutGrid,
    LayoutList,
    Megaphone,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Star,
    Trash,
    Upload,
    X,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare let route: any;

interface SanityContent {
    id: number;
    title: string;
    slug: string;
    post_kind: string;
    status: string;
    excerpt: string | null;
    featured: boolean;
    published_at: string | null;
    created_at: string;
    updated_at: string;
    featured_image: {
        url: string;
        alt?: string;
        caption?: string;
        credit?: string;
        assetId?: string;
    } | null;
    // We might need these for the update payload to pass validation
    content?: any;
    content_focus?: string;
    tags?: string[];
    audiences?: string[];
    channels?: string[];
    cta?: any;
    activation_window?: any;
}

interface SanityContentIndexProps {
    auth: {
        user: User;
    };
    contents: {
        data: SanityContent[];
        total: number;
        from: number;
        to: number;
        current_page: number;
        last_page: number;
        per_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: {
        search?: string | null;
        post_kind?: string | null;
        status?: string | null;
        featured?: string | null;
    };
}

export default function AdministratorSanityContentIndex({ auth, contents, filters }: SanityContentIndexProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [syncing, setSyncing] = useState(false);
    const [previewItem, setPreviewItem] = useState<SanityContent | null>(null);
    const [viewMode, setViewMode] = useState<"table" | "grid">("table");

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.sanity-content.index"), { ...filters, search: term }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: string, value: string | boolean | null) => {
        router.get(
            route("administrators.sanity-content.index"),
            { ...filters, [key]: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get(route("administrators.sanity-content.index"), { search: filters.search }, { preserveState: true, replace: true });
    };

    const syncFromSanity = () => {
        setSyncing(true);
        router.post(
            route("administrators.sanity-content.sync-from-sanity"),
            {},
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success("Successfully synced content from Sanity!");
                    setSyncing(false);
                },
                onError: () => {
                    toast.error("Failed to sync from Sanity");
                    setSyncing(false);
                },
            },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this content?")) {
            router.delete(route("administrators.sanity-content.destroy", id), {
                preserveState: true,
                onSuccess: () => toast.success("Content deleted successfully"),
                onError: () => toast.error("Failed to delete content"),
            });
        }
    };

    const activeFilterCount = Object.keys(filters).filter((k) => k !== "search" && filters[k as keyof typeof filters]).length;

    const getStatusColor = (status: string) => {
        switch (status) {
            case "published":
                return "bg-emerald-500/15 text-emerald-600 border-emerald-500/20 hover:bg-emerald-500/25";
            case "draft":
                return "bg-slate-500/15 text-slate-600 border-slate-500/20 hover:bg-slate-500/25";
            case "scheduled":
                return "bg-blue-500/15 text-blue-600 border-blue-500/20 hover:bg-blue-500/25";
            case "archived":
                return "bg-amber-500/15 text-amber-600 border-amber-500/20 hover:bg-amber-500/25";
            default:
                return "bg-slate-500/15 text-slate-600 border-slate-500/20";
        }
    };

    const getPostKindIcon = (kind: string) => {
        switch (kind) {
            case "news":
                return <FileText className="h-3.5 w-3.5" />;
            case "story":
                return <FileText className="h-3.5 w-3.5" />;
            case "announcement":
                return <Megaphone className="h-3.5 w-3.5" />;
            case "alert":
                return <AlertCircle className="h-3.5 w-3.5" />;
            default:
                return <FileText className="h-3.5 w-3.5" />;
        }
    };

    const handleQuickStatusChange = (newStatus: string) => {
        if (!previewItem) return;
        handleStatusUpdate(previewItem.id, newStatus, previewItem);
    };

    const handleStatusUpdate = (id: number, newStatus: string, currentItem?: SanityContent) => {
        const updatedItem = currentItem ? { ...currentItem, status: newStatus } : { status: newStatus };

        // Automatically set published_at if publishing
        if (newStatus === "published" && (!currentItem || !currentItem.published_at)) {
            // We can't easily get the current item if not provided, but the backend might handle it or we assume now
            // Ideally we pass the full item from the list
        }

        router.put(
            route("administrators.sanity-content.update", id),
            {
                ...updatedItem,
                published_at: newStatus === "published" ? new Date().toISOString() : undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Status updated to ${newStatus}`);
                    if (currentItem && previewItem?.id === id) {
                        setPreviewItem({ ...previewItem, status: newStatus } as SanityContent);
                    }
                },
                onError: () => toast.error("Failed to update status"),
            },
        );
    };

    return (
        <AdminLayout user={auth.user} title="Sanity CMS">
            <Head title="Administrators • Sanity Content" />

            <div className="bg-background/50 flex h-[calc(100vh-65px)] flex-col">
                {/* Header */}
                <div className="bg-background/95 sticky top-0 z-20 border-b px-6 py-4 backdrop-blur-md">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Sanity Content</h1>
                            <p className="text-muted-foreground text-sm">Manage your headless CMS content</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={syncFromSanity} disabled={syncing} className="gap-2">
                                <RefreshCw className={cn("h-4 w-4", syncing && "animate-spin")} />
                                <span className="hidden sm:inline">Sync from Sanity</span>
                            </Button>
                            <Button asChild size="sm" className="gap-2 shadow-sm">
                                <Link href={route("administrators.sanity-content.create")}>
                                    <Plus className="h-4 w-4" />
                                    Create Post
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* Toolbar */}
                    <div className="mt-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div className="flex flex-1 items-center gap-2">
                            <div className="relative max-w-sm flex-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search posts..."
                                    className="bg-background h-9 pl-9"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        handleSearch(e.target.value);
                                    }}
                                />
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm" className="h-9 gap-2 border-dashed">
                                        <Filter className="h-3.5 w-3.5" />
                                        Filters
                                        {activeFilterCount > 0 && (
                                            <Badge variant="secondary" className="ml-1 h-5 rounded-sm px-1.5 text-[10px]">
                                                {activeFilterCount}
                                            </Badge>
                                        )}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-[200px]">
                                    <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {["published", "draft", "scheduled", "archived"].map((status) => (
                                        <DropdownMenuCheckboxItem
                                            key={status}
                                            checked={filters.status === status}
                                            onCheckedChange={(checked) => handleFilterChange("status", checked ? status : "all")}
                                            className="capitalize"
                                        >
                                            {status}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel>Filter by Type</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {["news", "story", "announcement", "alert"].map((kind) => (
                                        <DropdownMenuCheckboxItem
                                            key={kind}
                                            checked={filters.post_kind === kind}
                                            onCheckedChange={(checked) => handleFilterChange("post_kind", checked ? kind : "all")}
                                            className="capitalize"
                                        >
                                            {kind}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>

                            {activeFilterCount > 0 && (
                                <Button variant="ghost" size="sm" onClick={clearFilters} className="text-muted-foreground h-9 px-2">
                                    <X className="mr-1 h-3.5 w-3.5" />
                                    Reset
                                </Button>
                            )}
                        </div>

                        <div className="bg-background flex items-center rounded-md border shadow-sm">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setViewMode("table")}
                                            className={cn("h-8 rounded-r-none px-2.5", viewMode === "table" && "bg-muted")}
                                        >
                                            <LayoutList className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Table View</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <Separator orientation="vertical" className="h-4" />
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setViewMode("grid")}
                                            className={cn("h-8 rounded-l-none px-2.5", viewMode === "grid" && "bg-muted")}
                                        >
                                            <LayoutGrid className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Grid View</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                </div>

                {/* Content Area */}
                <div className="flex-1 overflow-hidden">
                    {contents.data.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center p-6 text-center">
                            <div className="bg-muted/20 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                                <FileText className="text-muted-foreground/50 h-8 w-8" />
                            </div>
                            <h3 className="text-lg font-medium">No posts found</h3>
                            <p className="text-muted-foreground mx-auto mt-1 max-w-xs">
                                {search || activeFilterCount > 0
                                    ? "Try adjusting your search or filters to find what you're looking for."
                                    : "Get started by creating your first post using the button above."}
                            </p>
                            {(search || activeFilterCount > 0) && (
                                <Button variant="link" onClick={clearFilters} className="mt-2">
                                    Clear all filters
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="scrollbar-thin h-full overflow-y-auto">
                            {viewMode === "table" ? (
                                <div className="inline-block min-w-full align-middle">
                                    <div className="border-b">
                                        <Table>
                                            <TableHeader className="bg-muted/50 sticky top-0 z-10 backdrop-blur-sm">
                                                <TableRow>
                                                    <TableHead className="w-[400px]">Content</TableHead>
                                                    <TableHead className="w-[120px]">Status</TableHead>
                                                    <TableHead>Type</TableHead>
                                                    <TableHead>Published</TableHead>
                                                    <TableHead className="text-right">Actions</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {contents.data.map((content) => (
                                                    <TableRow key={content.id} className="group hover:bg-muted/50">
                                                        <TableCell className="py-3">
                                                            <div className="flex items-start gap-3">
                                                                {/* Thumbnail */}
                                                                <div className="bg-muted relative h-12 w-16 shrink-0 overflow-hidden rounded-md border">
                                                                    {content.featured_image?.url ? (
                                                                        <img
                                                                            src={content.featured_image.url}
                                                                            alt=""
                                                                            className="h-full w-full object-cover"
                                                                        />
                                                                    ) : (
                                                                        <div className="flex h-full w-full items-center justify-center">
                                                                            <ImageIcon className="text-muted-foreground/30 h-4 w-4" />
                                                                        </div>
                                                                    )}
                                                                    {content.featured && (
                                                                        <div className="bg-background/80 absolute top-0.5 right-0.5 rounded-full p-0.5 text-amber-500 backdrop-blur-sm">
                                                                            <Star className="h-2 w-2 fill-current" />
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <div className="flex min-w-0 flex-col gap-1">
                                                                    <Link
                                                                        href={route("administrators.sanity-content.edit", content.id)}
                                                                        className="hover:text-primary block max-w-[300px] truncate leading-none font-medium"
                                                                    >
                                                                        {content.title}
                                                                    </Link>
                                                                    <p className="text-muted-foreground line-clamp-1 max-w-[300px] text-xs">
                                                                        {content.excerpt || content.slug}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild nativeButton={false}>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={cn(
                                                                            "cursor-pointer font-normal capitalize transition-opacity hover:opacity-80",
                                                                            getStatusColor(content.status),
                                                                        )}
                                                                    >
                                                                        {content.status}
                                                                        <ChevronDown className="ml-1 h-3 w-3 opacity-50" />
                                                                    </Badge>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="start">
                                                                    <DropdownMenuLabel>Set Status</DropdownMenuLabel>
                                                                    <DropdownMenuSeparator />
                                                                    {["draft", "published", "scheduled", "archived"].map((status) => (
                                                                        <DropdownMenuItem
                                                                            key={status}
                                                                            onClick={() => handleStatusUpdate(content.id, status, content)}
                                                                            className="gap-2 capitalize"
                                                                        >
                                                                            <div
                                                                                className={cn(
                                                                                    "h-2 w-2 rounded-full",
                                                                                    status === "published"
                                                                                        ? "bg-emerald-500"
                                                                                        : status === "draft"
                                                                                          ? "bg-slate-500"
                                                                                          : status === "scheduled"
                                                                                            ? "bg-blue-500"
                                                                                            : "bg-amber-500",
                                                                                )}
                                                                            />
                                                                            {status}
                                                                            {content.status === status && (
                                                                                <CheckCircle2 className="ml-auto h-3.5 w-3.5 opacity-50" />
                                                                            )}
                                                                        </DropdownMenuItem>
                                                                    ))}
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="text-muted-foreground flex items-center gap-1.5 text-sm capitalize">
                                                                {getPostKindIcon(content.post_kind)}
                                                                {content.post_kind}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-col text-sm">
                                                                <span className="font-medium">
                                                                    {content.published_at ? new Date(content.published_at).toLocaleDateString() : "—"}
                                                                </span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {content.published_at
                                                                        ? new Date(content.published_at).toLocaleTimeString([], {
                                                                              hour: "2-digit",
                                                                              minute: "2-digit",
                                                                          })
                                                                        : "Unpublished"}
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                                <TooltipProvider>
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                                                onClick={() => setPreviewItem(content)}
                                                                            >
                                                                                <Eye className="h-4 w-4" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Quick Preview</TooltipContent>
                                                                    </Tooltip>
                                                                </TooltipProvider>

                                                                <TooltipProvider>
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                                                asChild
                                                                            >
                                                                                <Link href={route("administrators.sanity-content.edit", content.id)}>
                                                                                    <Pencil className="h-4 w-4" />
                                                                                </Link>
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Edit</TooltipContent>
                                                                    </Tooltip>
                                                                </TooltipProvider>

                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger asChild>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                                        >
                                                                            <MoreHorizontal className="h-4 w-4" />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent align="end">
                                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                                        <DropdownMenuItem
                                                                            onClick={() => {
                                                                                router.post(
                                                                                    route("administrators.sanity-content.sync-to-sanity", content.id),
                                                                                    {},
                                                                                    {
                                                                                        preserveState: true,
                                                                                        onSuccess: () => toast.success("Synced to Sanity!"),
                                                                                    },
                                                                                );
                                                                            }}
                                                                        >
                                                                            <Upload className="mr-2 h-4 w-4" /> Sync to Sanity
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuSeparator />
                                                                        <DropdownMenuItem
                                                                            onClick={() => handleDelete(content.id)}
                                                                            className="text-destructive focus:text-destructive"
                                                                        >
                                                                            <Trash className="mr-2 h-4 w-4" /> Delete
                                                                        </DropdownMenuItem>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-6 p-6 pb-20 md:grid-cols-2 xl:grid-cols-3">
                                    {contents.data.map((content) => (
                                        <Card
                                            key={content.id}
                                            className="group hover:border-primary/20 flex flex-col overflow-hidden transition-all duration-300 hover:shadow-lg"
                                        >
                                            <div className="bg-muted relative h-48 overflow-hidden">
                                                {content.featured_image?.url ? (
                                                    <img
                                                        src={content.featured_image.url}
                                                        alt={content.featured_image.alt || content.title}
                                                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                    />
                                                ) : (
                                                    <div className="bg-muted/30 text-muted-foreground/40 flex h-full w-full flex-col items-center justify-center">
                                                        <ImageIcon className="mb-2 h-12 w-12" />
                                                        <span className="text-xs font-medium">No Cover Image</span>
                                                    </div>
                                                )}
                                                <div className="absolute top-3 left-3 flex gap-2">
                                                    <Badge className={cn("capitalize shadow-sm backdrop-blur-md", getStatusColor(content.status))}>
                                                        {content.status}
                                                    </Badge>
                                                </div>
                                                {content.featured && (
                                                    <div className="absolute top-3 right-3">
                                                        <div className="rounded-full bg-amber-500/90 p-1.5 text-white shadow-sm backdrop-blur-md">
                                                            <Star className="h-3.5 w-3.5 fill-current" />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            <CardContent className="flex flex-1 flex-col gap-4 p-5">
                                                <div className="flex-1 space-y-2">
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <span className="flex items-center gap-1 capitalize">
                                                            {getPostKindIcon(content.post_kind)}
                                                            {content.post_kind}
                                                        </span>
                                                        <span>•</span>
                                                        <span>{new Date(content.created_at).toLocaleDateString()}</span>
                                                    </div>

                                                    <Link
                                                        href={route("administrators.sanity-content.edit", content.id)}
                                                        className="group/title block"
                                                    >
                                                        <h3 className="group-hover/title:text-primary line-clamp-2 text-lg leading-tight font-semibold transition-colors">
                                                            {content.title}
                                                        </h3>
                                                    </Link>

                                                    <p className="text-muted-foreground line-clamp-2 text-sm">
                                                        {content.excerpt || "No excerpt provided."}
                                                    </p>
                                                </div>

                                                <div className="mt-auto flex items-center justify-between border-t pt-2">
                                                    <div className="flex gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                            onClick={() => setPreviewItem(content)}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                            asChild
                                                        >
                                                            <Link href={route("administrators.sanity-content.edit", content.id)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                            >
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    router.post(
                                                                        route("administrators.sanity-content.sync-to-sanity", content.id),
                                                                        {},
                                                                        { preserveState: true, onSuccess: () => toast.success("Synced to Sanity!") },
                                                                    );
                                                                }}
                                                            >
                                                                <Upload className="mr-2 h-4 w-4" /> Sync to Sanity
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem onClick={() => handleDelete(content.id)} className="text-destructive">
                                                                <Trash className="mr-2 h-4 w-4" /> Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Pagination Footer */}
                <div className="bg-background/80 sticky bottom-0 z-20 flex items-center justify-between border-t px-6 py-4 backdrop-blur-md">
                    <div className="text-muted-foreground text-sm">
                        Showing <span className="text-foreground font-medium">{contents.from}</span> to{" "}
                        <span className="text-foreground font-medium">{contents.to}</span> of{" "}
                        <span className="text-foreground font-medium">{contents.total}</span> posts
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild disabled={!contents.prev_page_url}>
                            {contents.prev_page_url ? (
                                <Link href={contents.prev_page_url} preserveState>
                                    <ChevronLeft className="mr-1 h-4 w-4" /> Previous
                                </Link>
                            ) : (
                                <span className="text-muted-foreground flex items-center">
                                    <ChevronLeft className="mr-1 h-4 w-4" /> Previous
                                </span>
                            )}
                        </Button>
                        <Button variant="outline" size="sm" asChild disabled={!contents.next_page_url}>
                            {contents.next_page_url ? (
                                <Link href={contents.next_page_url} preserveState>
                                    Next <ChevronRight className="ml-1 h-4 w-4" />
                                </Link>
                            ) : (
                                <span className="text-muted-foreground flex items-center">
                                    Next <ChevronRight className="ml-1 h-4 w-4" />
                                </span>
                            )}
                        </Button>
                    </div>
                </div>
            </div>

            {/* Quick Preview Sheet */}
            <Sheet open={!!previewItem} onOpenChange={(open) => !open && setPreviewItem(null)}>
                <SheetContent className="bg-background flex h-full w-full flex-col gap-0 border-l p-0 shadow-2xl sm:max-w-xl" side="right">
                    {previewItem && (
                        <>
                            {/* Immersive Toolbar */}
                            <div className="bg-background/80 z-10 flex shrink-0 items-center justify-between border-b px-4 py-3 backdrop-blur-md">
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className={cn("h-6 px-2 py-0.5 text-xs font-medium capitalize", getStatusColor(previewItem.status))}
                                    >
                                        {previewItem.status}
                                    </Badge>
                                    {previewItem.featured && (
                                        <Badge
                                            variant="secondary"
                                            className="h-6 gap-1 bg-amber-100 px-2 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400"
                                        >
                                            <Star className="h-3 w-3 fill-current" /> Featured
                                        </Badge>
                                    )}
                                </div>
                                <div className="flex items-center gap-1">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-foreground h-8 gap-1.5">
                                                <CheckCircle2 className="h-4 w-4" />
                                                Status
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Change Status</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            {["draft", "published", "scheduled", "archived"].map((status) => (
                                                <DropdownMenuItem
                                                    key={status}
                                                    onClick={() => handleQuickStatusChange(status)}
                                                    className="gap-2 capitalize"
                                                >
                                                    <div
                                                        className={cn(
                                                            "h-2 w-2 rounded-full",
                                                            status === "published"
                                                                ? "bg-emerald-500"
                                                                : status === "draft"
                                                                  ? "bg-slate-500"
                                                                  : status === "scheduled"
                                                                    ? "bg-blue-500"
                                                                    : "bg-amber-500",
                                                        )}
                                                    />
                                                    {status}
                                                    {previewItem.status === status && <CheckCircle2 className="ml-auto h-3.5 w-3.5 opacity-50" />}
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>

                                    <Separator orientation="vertical" className="mx-1 h-4" />

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                    onClick={() => {
                                                        router.post(route("administrators.sanity-content.sync-to-sanity", previewItem.id));
                                                    }}
                                                >
                                                    <Upload className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Push to Sanity</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <Button asChild variant="default" size="sm" className="ml-1 h-8 gap-2">
                                        <Link href={route("administrators.sanity-content.edit", previewItem.id)}>
                                            <Pencil className="h-3.5 w-3.5" />
                                            Edit
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            {/* Scrollable Content */}
                            <ScrollArea className="flex-1">
                                <div className="flex min-h-full flex-col">
                                    {/* Hero Image */}
                                    {previewItem.featured_image?.url ? (
                                        <div className="bg-muted group relative aspect-video w-full overflow-hidden">
                                            <img
                                                src={previewItem.featured_image.url}
                                                alt={previewItem.featured_image.alt || previewItem.title}
                                                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                            />
                                            <div className="from-background absolute inset-0 bg-gradient-to-t via-transparent to-transparent opacity-60" />
                                            <div className="absolute right-4 bottom-4 rounded-full bg-black/60 px-2 py-1 text-[10px] text-white opacity-0 backdrop-blur-md transition-opacity group-hover:opacity-100">
                                                {previewItem.featured_image.alt || "Featured Image"}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="from-muted/50 to-muted flex aspect-[3/1] w-full items-center justify-center border-b bg-gradient-to-br">
                                            <ImageIcon className="text-muted-foreground/20 h-10 w-10" />
                                        </div>
                                    )}

                                    {/* Document Header */}
                                    <div className="space-y-4 px-8 pt-8 pb-6">
                                        <div className="space-y-2">
                                            <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wider uppercase">
                                                <span className="text-primary flex items-center gap-1.5">
                                                    {getPostKindIcon(previewItem.post_kind)}
                                                    {previewItem.post_kind}
                                                </span>
                                                <span>•</span>
                                                <span>{new Date(previewItem.created_at).toLocaleDateString(undefined, { dateStyle: "long" })}</span>
                                            </div>
                                            <h1 className="text-foreground text-3xl leading-tight font-bold tracking-tight">{previewItem.title}</h1>
                                        </div>

                                        <div className="prose prose-sm dark:prose-invert text-muted-foreground max-w-none leading-relaxed">
                                            <p className="text-foreground/80 border-primary/20 border-l-4 pl-4 font-serif text-lg italic">
                                                {previewItem.excerpt || "No excerpt provided for this post."}
                                            </p>
                                        </div>
                                    </div>

                                    <Separator className="mx-8 w-auto" />

                                    {/* Metadata Grid */}
                                    <div className="grid grid-cols-2 gap-x-4 gap-y-6 px-8 py-6 text-sm">
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">ID</span>
                                            <div className="bg-muted/50 w-fit rounded px-2 py-1 font-mono text-xs select-all">{previewItem.id}</div>
                                        </div>
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Slug</span>
                                            <div className="text-muted-foreground hover:text-primary group flex cursor-pointer items-center gap-1 transition-colors">
                                                <Globe className="h-3.5 w-3.5" />
                                                <span className="max-w-[150px] truncate font-medium group-hover:underline">{previewItem.slug}</span>
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Published At</span>
                                            <div className="flex items-center gap-1.5">
                                                <Clock className="text-muted-foreground h-3.5 w-3.5" />
                                                <span>
                                                    {previewItem.published_at
                                                        ? new Date(previewItem.published_at).toLocaleString(undefined, {
                                                              dateStyle: "medium",
                                                              timeStyle: "short",
                                                          })
                                                        : "Not published"}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Last Updated</span>
                                            <span>{new Date(previewItem.updated_at).toLocaleDateString(undefined, { dateStyle: "medium" })}</span>
                                        </div>
                                    </div>

                                    {/* Footer Actions */}
                                    <div className="bg-muted/20 mt-auto flex items-center justify-between border-t p-4">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground hover:text-destructive text-xs"
                                            onClick={() => handleDelete(previewItem.id)}
                                        >
                                            <Trash className="mr-1.5 h-3.5 w-3.5" /> Delete Post
                                        </Button>
                                        <div className="text-muted-foreground/60 font-mono text-[10px]">Sanity ID: {previewItem.id}</div>
                                    </div>
                                </div>
                            </ScrollArea>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </AdminLayout>
    );
}
