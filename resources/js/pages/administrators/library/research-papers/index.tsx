import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { BookOpen, Calendar, Edit, FileText, Filter, GraduationCap, MoreVertical, Plus, Search, Trash2, Users } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface ResearchPaperItem {
    id: number;
    title: string;
    type: string;
    status: string;
    publication_year?: number | null;
    advisor_name?: string | null;
    contributors?: string | null;
    tags?: string[];
    is_public: boolean;
    students?: string[];
    course?: string | null;
    cover_image_url?: string | null;
    document_url?: string | null;
}

interface Props {
    user: User;
    papers: ResearchPaperItem[];
    stats: {
        total: number;
        capstone: number;
        thesis: number;
        research: number;
        public: number;
    };
    filters: {
        search?: string | null;
        type?: string | null;
        status?: string | null;
        visibility?: string | null;
    };
    options: {
        types: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
        visibility: { value: string; label: string }[];
    };
    flash?: {
        type: string;
        message: string;
    };
}

const statusStyles: Record<string, string> = {
    draft: "bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-200",
    submitted: "bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-200",
    archived: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200",
};

export default function ResearchPapersIndex({ user, papers, stats, filters, options, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [deleteTarget, setDeleteTarget] = useState<ResearchPaperItem | null>(null);

    useEffect(() => {
        if (!flash?.message) return;
        if (flash.type === "success") {
            toast.success(flash.message);
        } else if (flash.type === "error") {
            toast.error(flash.message);
        } else {
            toast.message(flash.message);
        }
    }, [flash]);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(
            route("administrators.library.research-papers.index"),
            { ...filters, search: term || null },
            { preserveState: true, replace: true },
        );
    }, 300);

    const handleFilterChange = (key: string, value: string) => {
        router.get(
            route("administrators.library.research-papers.index"),
            { ...filters, [key]: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.library.research-papers.destroy", deleteTarget.id), {
            preserveState: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout user={user} title="Research Papers">
            <Head title="Administrators • Research Papers" />

            <div className="flex flex-col gap-8">
                {/* Header Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-indigo-100 bg-gradient-to-br from-indigo-500/10 to-transparent shadow-sm dark:border-indigo-900/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-indigo-600">Total Research</CardTitle>
                            <BookOpen className="h-4 w-4 text-indigo-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-muted-foreground text-xs">Archived papers</p>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Capstones</CardTitle>
                            <GraduationCap className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.capstone}</div>
                            <p className="text-muted-foreground text-xs">Student projects</p>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Theses</CardTitle>
                            <FileText className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.thesis}</div>
                            <p className="text-muted-foreground text-xs">Academic papers</p>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Public Ready</CardTitle>
                            <Users className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.public}</div>
                            <p className="text-muted-foreground text-xs">Visible to all</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters & Actions */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="relative flex-1 md:max-w-md">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            placeholder="Search by title, author, or keyword..."
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                handleSearch(event.target.value);
                            }}
                            className="bg-background/50 pl-9"
                        />
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-2">
                            <Select value={filters.type ?? "all"} onValueChange={(v) => handleFilterChange("type", v)}>
                                <SelectTrigger className="bg-background/50 w-[130px]">
                                    <div className="flex items-center gap-2">
                                        <Filter className="text-muted-foreground h-3.5 w-3.5" />
                                        <span className="truncate">
                                            {options.types.find((t) => t.value === (filters.type ?? "all"))?.label ?? "Type"}
                                        </span>
                                    </div>
                                </SelectTrigger>
                                <SelectContent>
                                    {options.types.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.status ?? "all"} onValueChange={(v) => handleFilterChange("status", v)}>
                                <SelectTrigger className="bg-background/50 w-[130px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.statuses.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button asChild className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                            <Link href={route("administrators.library.research-papers.create")}>
                                <Plus className="h-4 w-4" />
                                Add New
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Grid Content */}
                {papers.length === 0 ? (
                    <div className="bg-muted/40 animate-in fade-in-50 flex min-h-[400px] flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
                        <div className="bg-muted flex h-20 w-20 items-center justify-center rounded-full">
                            <BookOpen className="text-muted-foreground h-10 w-10" />
                        </div>
                        <h3 className="mt-4 text-lg font-semibold">No research papers found</h3>
                        <p className="text-muted-foreground mx-auto mb-4 max-w-sm text-sm">
                            {search
                                ? "Try adjusting your search terms or filters."
                                : "Get started by adding your first research paper to the archive."}
                        </p>
                        {!search && (
                            <Button asChild>
                                <Link href={route("administrators.library.research-papers.create")}>Add Research Paper</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {papers.map((paper) => (
                            <Card key={paper.id} className="group border-muted/60 flex flex-col overflow-hidden transition-all hover:shadow-md">
                                <div className="bg-muted relative aspect-[4/3] w-full overflow-hidden">
                                    {paper.cover_image_url ? (
                                        <img
                                            src={paper.cover_image_url}
                                            alt={paper.title}
                                            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div className="from-muted to-muted/50 text-muted-foreground flex h-full w-full items-center justify-center bg-gradient-to-br">
                                            <GraduationCap className="h-12 w-12 opacity-20" />
                                        </div>
                                    )}
                                    <div className="absolute top-2 right-2 flex gap-1">
                                        <Badge variant="secondary" className={cn("shadow-sm backdrop-blur-md", statusStyles[paper.status])}>
                                            {paper.status}
                                        </Badge>
                                    </div>
                                </div>
                                <CardHeader className="p-4 pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium tracking-wider text-indigo-600 uppercase">{paper.type}</p>
                                            <h3 className="line-clamp-2 leading-tight font-semibold transition-colors group-hover:text-indigo-600">
                                                <Link href={route("administrators.library.research-papers.edit", paper.id)}>{paper.title}</Link>
                                            </h3>
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="text-muted-foreground -mr-2 h-8 w-8">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={route("administrators.library.research-papers.edit", paper.id)}
                                                        className="flex cursor-pointer items-center gap-2"
                                                    >
                                                        <Edit className="h-4 w-4" /> Edit Details
                                                    </Link>
                                                </DropdownMenuItem>
                                                {paper.document_url && (
                                                    <DropdownMenuItem asChild>
                                                        <a
                                                            href={paper.document_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="flex cursor-pointer items-center gap-2"
                                                        >
                                                            <FileText className="h-4 w-4" /> View PDF
                                                        </a>
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive flex cursor-pointer items-center gap-2"
                                                    onClick={() => setDeleteTarget(paper)}
                                                >
                                                    <Trash2 className="h-4 w-4" /> Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-1 p-4 pt-0">
                                    <div className="text-muted-foreground mt-2 space-y-2 text-sm">
                                        <div className="flex items-center gap-2">
                                            <Users className="h-3.5 w-3.5 shrink-0" />
                                            <span className="line-clamp-1">
                                                {paper.students && paper.students.length > 0 ? paper.students.join(", ") : "No authors"}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-3.5 w-3.5 shrink-0" />
                                            <span>{paper.publication_year ?? "Unknown Year"}</span>
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="bg-muted/20 text-muted-foreground flex items-center justify-between border-t p-4 text-xs">
                                    <div className="flex items-center gap-1.5">
                                        <div className={cn("h-2 w-2 rounded-full", paper.is_public ? "bg-emerald-500" : "bg-amber-500")} />
                                        {paper.is_public ? "Public" : "Private"}
                                    </div>
                                    {paper.course && (
                                        <span className="max-w-[120px] truncate" title={paper.course}>
                                            {paper.course}
                                        </span>
                                    )}
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Delete Dialog */}
                <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete research record?</AlertDialogTitle>
                            <AlertDialogDescription>
                                This will permanently remove "{deleteTarget?.title}" and its files from the archive.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmDelete} className="bg-destructive hover:bg-destructive/90">
                                Delete Record
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AdminLayout>
    );
}
