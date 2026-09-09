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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    BookmarkCheck,
    Building2,
    CheckCircle2,
    ChevronLeft,
    Edit,
    FileSpreadsheet,
    Layers,
    MoreHorizontal,
    Plus,
    Search,
    ShieldCheck,
    Sparkles,
    Trash2,
    X,
} from "lucide-react";
import { useCallback, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { AuthorityCodeImportDialog, type AuthoritySummary } from "../authority-code-import-dialog";

export interface CodeAuthorityItem {
    id: number;
    key: string;
    name: string;
    country_code: string | null;
    curriculum_framework: string | null;
    description: string | null;
    is_active: boolean;
    codes_count: number;
    active_codes_count: number;
    is_relevant: boolean;
}

export interface IndustryCodeItem {
    id: number;
    school_id: number;
    code_authority_id: number;
    code: string;
    title: string;
    category_code: string | null;
    category_name: string | null;
    attributes: Record<string, string | null> | null;
    source: string;
    is_active: boolean;
    courses_count: number;
    authority: {
        id: number;
        key: string;
        name: string;
    } | null;
}

export interface CategoryOption {
    code: string | null;
    name: string;
    count: number;
    label: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCodes {
    data: IndustryCodeItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface AuthorityCodesIndexProps {
    user: User;
    stats: {
        total_codes: number;
        active_codes: number;
        total_categories: number;
        total_authorities: number;
        linked_programs_count: number;
    };
    is_ched_accredited: boolean;
    authorities: CodeAuthorityItem[];
    categories: CategoryOption[];
    codes: PaginatedCodes;
    filters: {
        search?: string;
        authority_id?: string;
        category_code?: string;
        status?: string;
    };
}

const FieldError = ({ message }: { message?: string }) => (message ? <p className="text-destructive mt-1 text-xs font-medium">{message}</p> : null);

export default function AuthorityCodesIndex({ user, stats, is_ched_accredited, authorities, categories, codes, filters }: AuthorityCodesIndexProps) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [selectedAuthority, setSelectedAuthority] = useState<string>(filters.authority_id ?? "all");
    const [selectedCategory, setSelectedCategory] = useState<string>(filters.category_code ?? "all");
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status ?? "all");

    // Modal state for Code CRUD
    const [codeModalOpen, setCodeModalOpen] = useState(false);
    const [editingCode, setEditingCode] = useState<IndustryCodeItem | null>(null);
    const [deleteCodeTarget, setDeleteCodeTarget] = useState<IndustryCodeItem | null>(null);

    // Modal state for Authority CRUD
    const [authorityModalOpen, setAuthorityModalOpen] = useState(false);
    const [editingAuthority, setEditingAuthority] = useState<CodeAuthorityItem | null>(null);
    const [deleteAuthorityTarget, setDeleteAuthorityTarget] = useState<CodeAuthorityItem | null>(null);

    const codeForm = useForm({
        code_authority_id: authorities[0] ? String(authorities[0].id) : "",
        code: "",
        title: "",
        category_code: "",
        category_name: "",
        is_active: true,
    });

    const authorityForm = useForm({
        name: "",
        key: "",
        country_code: is_ched_accredited ? "PH" : "",
        curriculum_framework: is_ched_accredited ? "ched_psg" : "",
        description: "",
        is_active: true,
    });

    const applyFilters = useCallback(
        (overrides: Partial<typeof filters> = {}) => {
            const params: Record<string, string> = {};
            const nextSearch = overrides.search !== undefined ? overrides.search : search;
            const nextAuth = overrides.authority_id !== undefined ? overrides.authority_id : selectedAuthority;
            const nextCat = overrides.category_code !== undefined ? overrides.category_code : selectedCategory;
            const nextStatus = overrides.status !== undefined ? overrides.status : selectedStatus;

            if (nextSearch.trim()) params.search = nextSearch.trim();
            if (nextAuth && nextAuth !== "all") params.authority_id = nextAuth;
            if (nextCat && nextCat !== "all") params.category_code = nextCat;
            if (nextStatus && nextStatus !== "all") params.status = nextStatus;

            router.get(route("administrators.curriculum.authority-codes.index"), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [search, selectedAuthority, selectedCategory, selectedStatus, filters],
    );

    const handleSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const handleClearFilters = () => {
        setSearch("");
        setSelectedAuthority("all");
        setSelectedCategory("all");
        setSelectedStatus("all");
        router.get(
            route("administrators.curriculum.authority-codes.index"),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const openCreateCode = () => {
        setEditingCode(null);
        codeForm.reset();
        codeForm.clearErrors();
        if (authorities.length > 0) {
            codeForm.setData("code_authority_id", String(authorities[0].id));
        }
        setCodeModalOpen(true);
    };

    const openEditCode = (code: IndustryCodeItem) => {
        setEditingCode(code);
        codeForm.clearErrors();
        codeForm.setData({
            code_authority_id: String(code.code_authority_id),
            code: code.code,
            title: code.title,
            category_code: code.category_code ?? "",
            category_name: code.category_name ?? "",
            is_active: code.is_active,
        });
        setCodeModalOpen(true);
    };

    const handleSaveCode = (e: FormEvent) => {
        e.preventDefault();
        if (editingCode) {
            codeForm.put(route("administrators.curriculum.authority-codes.update", editingCode.id), {
                preserveScroll: true,
                onSuccess: () => {
                    setCodeModalOpen(false);
                    toast.success("Authority code updated successfully.");
                },
            });
        } else {
            codeForm.post(route("administrators.curriculum.authority-codes.store"), {
                preserveScroll: true,
                onSuccess: () => {
                    setCodeModalOpen(false);
                    codeForm.reset();
                    toast.success("Authority code registered successfully.");
                },
            });
        }
    };

    const handleDeleteCode = () => {
        if (!deleteCodeTarget) return;
        router.delete(route("administrators.curriculum.authority-codes.destroy", deleteCodeTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteCodeTarget(null);
                toast.success("Authority code deleted successfully.");
            },
        });
    };

    const openCreateAuthority = () => {
        setEditingAuthority(null);
        authorityForm.reset();
        authorityForm.clearErrors();
        if (is_ched_accredited) {
            authorityForm.setData({
                name: "CHED",
                key: "ched",
                country_code: "PH",
                curriculum_framework: "ched_psg",
                description: "Commission on Higher Education (CHED) - Philippines Standard Classification of Education",
                is_active: true,
            });
        }
        setAuthorityModalOpen(true);
    };

    const openEditAuthority = (authority: CodeAuthorityItem) => {
        setEditingAuthority(authority);
        authorityForm.clearErrors();
        authorityForm.setData({
            name: authority.name,
            key: authority.key,
            country_code: authority.country_code ?? "",
            curriculum_framework: authority.curriculum_framework ?? "",
            description: authority.description ?? "",
            is_active: authority.is_active,
        });
        setAuthorityModalOpen(true);
    };

    const handleSaveAuthority = (e: FormEvent) => {
        e.preventDefault();
        if (editingAuthority) {
            authorityForm.put(route("administrators.curriculum.code-authorities.update", editingAuthority.id), {
                preserveScroll: true,
                onSuccess: () => {
                    setAuthorityModalOpen(false);
                    toast.success("Authority updated successfully.");
                },
            });
        } else {
            authorityForm.post(route("administrators.curriculum.code-authorities.store"), {
                preserveScroll: true,
                onSuccess: () => {
                    setAuthorityModalOpen(false);
                    authorityForm.reset();
                    toast.success("Authority registered successfully.");
                },
            });
        }
    };

    const handleDeleteAuthority = () => {
        if (!deleteAuthorityTarget) return;
        router.delete(route("administrators.curriculum.code-authorities.destroy", deleteAuthorityTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteAuthorityTarget(null);
                toast.success("Authority deleted successfully.");
            },
        });
    };

    const authoritySummaries: AuthoritySummary[] = authorities.map((a) => ({
        id: a.id,
        key: a.key,
        name: a.name,
        country_code: a.country_code,
        codes_count: a.codes_count,
    }));

    return (
        <AdminLayout user={user} title="Authority Course Codes">
            <Head title="Authority Course Codes · Curriculum" />

            <div className="relative isolate flex flex-col gap-6 pb-4">
                {/* Header */}
                <header className="border-border/70 relative flex flex-col gap-4 border-b pb-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0 space-y-3">
                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs font-medium">
                            <Link href={route("administrators.curriculum.programs.index")} className="hover:text-foreground transition-colors">
                                Curriculum
                            </Link>
                            <span aria-hidden="true">/</span>
                            <span className="text-foreground font-semibold">Authority Course Codes</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="bg-primary/8 text-primary inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em] uppercase">
                                <Sparkles className="size-3.5" /> Registry
                            </span>
                            {is_ched_accredited && (
                                <Badge variant="outline" className="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300">
                                    <ShieldCheck className="mr-1 size-3" /> CHED Accredited
                                </Badge>
                            )}
                        </div>
                        <h1 className="text-3xl font-semibold tracking-[-0.04em]">Official Course Codes & Disciplines</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-6">
                            Manage regulatory program codes, discipline groups, and categories (e.g. CHED PSCED). Codes can be imported in bulk or
                            managed manually.
                        </p>
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Button asChild variant="ghost" className="rounded-lg">
                            <Link href={route("administrators.curriculum.programs.index")}>
                                <ChevronLeft className="size-4" /> Program catalog
                            </Link>
                        </Button>
                        <AuthorityCodeImportDialog authorities={authoritySummaries} isChedAccredited={is_ched_accredited} />
                        <Button variant="outline" className="rounded-lg shadow-sm" onClick={openCreateAuthority}>
                            <Building2 className="size-4" /> New authority
                        </Button>
                        <Button className="rounded-lg px-4 shadow-sm" onClick={openCreateCode}>
                            <Plus className="size-4" /> Add code
                        </Button>
                    </div>
                </header>

                {/* Sub-nav tabs */}
                <div className="border-border/70 flex items-center gap-2 border-b pb-2">
                    <Link
                        href={route("administrators.curriculum.programs.index")}
                        className="text-muted-foreground hover:bg-muted/60 hover:text-foreground rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    >
                        Academic Programs
                    </Link>
                    <Link
                        href={route("administrators.curriculum.authority-codes.index")}
                        className="bg-primary/10 text-primary rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                    >
                        Authority Codes & Disciplines
                    </Link>
                </div>

                {/* Stats Cards */}
                <section className="grid grid-cols-2 gap-4 sm:grid-cols-4" aria-label="Registry Statistics">
                    <Card className="border-border/80 rounded-2xl border shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Codes</CardTitle>
                            <BookmarkCheck className="text-primary size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_codes.toLocaleString()}</div>
                            <p className="text-muted-foreground mt-1 text-xs">{stats.active_codes} active in picker</p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 rounded-2xl border shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Categories / Disciplines
                            </CardTitle>
                            <Layers className="size-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_categories.toLocaleString()}</div>
                            <p className="text-muted-foreground mt-1 text-xs">Distinct discipline groups</p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 rounded-2xl border shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Authorities</CardTitle>
                            <Building2 className="size-4 text-sky-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_authorities}</div>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {authorities.map((a) => (
                                    <button
                                        key={a.id}
                                        type="button"
                                        onClick={() => openEditAuthority(a)}
                                        className="focus:ring-ring rounded hover:opacity-80 focus:ring-1 focus:outline-none"
                                        title={`Edit ${a.name}`}
                                    >
                                        <Badge variant="secondary" className="cursor-pointer text-[10px]">
                                            {a.name}
                                        </Badge>
                                    </button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 rounded-2xl border shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Linked Programs</CardTitle>
                            <CheckCircle2 className="size-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.linked_programs_count}</div>
                            <p className="text-muted-foreground mt-1 text-xs">Courses using official codes</p>
                        </CardContent>
                    </Card>
                </section>

                {/* Filter Toolbar */}
                <section className="border-border/80 bg-card flex flex-col gap-3 rounded-2xl border p-4 shadow-sm lg:flex-row lg:items-center">
                    <form onSubmit={handleSearchSubmit} className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Search by code, title, or discipline…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="rounded-xl pl-9"
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Authority Select */}
                        <Select
                            value={selectedAuthority}
                            onValueChange={(val) => {
                                setSelectedAuthority(val);
                                applyFilters({ authority_id: val });
                            }}
                        >
                            <SelectTrigger className="w-[180px] rounded-xl">
                                <SelectValue placeholder="All Authorities" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Authorities</SelectItem>
                                {authorities.map((auth) => (
                                    <SelectItem key={auth.id} value={String(auth.id)}>
                                        {auth.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {/* Category Select */}
                        <Select
                            value={selectedCategory}
                            onValueChange={(val) => {
                                setSelectedCategory(val);
                                applyFilters({ category_code: val });
                            }}
                        >
                            <SelectTrigger className="w-[220px] rounded-xl">
                                <SelectValue placeholder="All Disciplines" />
                            </SelectTrigger>
                            <SelectContent className="max-h-72">
                                <SelectItem value="all">All Disciplines</SelectItem>
                                {categories.map((cat, idx) => (
                                    <SelectItem key={cat.code ?? `cat-${idx}`} value={cat.code ?? cat.name}>
                                        {cat.label} ({cat.count})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {/* Status Select */}
                        <Select
                            value={selectedStatus}
                            onValueChange={(val) => {
                                setSelectedStatus(val);
                                applyFilters({ status: val });
                            }}
                        >
                            <SelectTrigger className="w-[140px] rounded-xl">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>

                        {(search || selectedAuthority !== "all" || selectedCategory !== "all" || selectedStatus !== "all") && (
                            <Button variant="ghost" size="sm" onClick={handleClearFilters} className="rounded-xl">
                                <X className="size-4" /> Reset
                            </Button>
                        )}
                    </div>
                </section>

                {/* Table of codes */}
                <section className="border-border/80 bg-card overflow-hidden rounded-2xl border shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/40">
                                <TableHead className="w-[140px] font-semibold">Code</TableHead>
                                <TableHead className="font-semibold">Official Course Title</TableHead>
                                <TableHead className="font-semibold">Category / Discipline Group</TableHead>
                                <TableHead className="w-[130px] font-semibold">Authority</TableHead>
                                <TableHead className="w-[100px] text-center font-semibold">Programs</TableHead>
                                <TableHead className="w-[90px] font-semibold">Source</TableHead>
                                <TableHead className="w-[90px] font-semibold">Status</TableHead>
                                <TableHead className="w-[70px] text-right font-semibold">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {codes.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-muted-foreground py-12 text-center">
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <FileSpreadsheet className="text-muted-foreground/60 size-8" />
                                            <p className="font-medium">No official course codes found</p>
                                            <p className="text-xs">Import the official spreadsheet or register codes manually above.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                codes.data.map((code) => (
                                    <TableRow key={code.id} className="hover:bg-muted/30">
                                        <TableCell className="text-foreground font-mono font-bold">
                                            <span className="bg-muted rounded px-2 py-0.5">{code.code}</span>
                                        </TableCell>
                                        <TableCell className="font-medium">{code.title}</TableCell>
                                        <TableCell>
                                            {code.category_name ? (
                                                <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                                    {code.category_code && (
                                                        <Badge variant="outline" className="font-mono text-[11px]">
                                                            {code.category_code}
                                                        </Badge>
                                                    )}
                                                    <span className="text-muted-foreground">{code.category_name}</span>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" className="text-xs">
                                                {code.authority?.name ?? "—"}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant={code.courses_count > 0 ? "default" : "outline"} className="text-xs">
                                                {code.courses_count}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-muted-foreground text-xs capitalize">{code.source}</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={code.is_active ? "outline" : "secondary"}
                                                className={
                                                    code.is_active
                                                        ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                        : "text-muted-foreground"
                                                }
                                            >
                                                {code.is_active ? "Active" : "Inactive"}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8">
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => openEditCode(code)}>
                                                        <Edit className="mr-2 size-4" /> Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        className="text-destructive focus:text-destructive"
                                                        onClick={() => setDeleteCodeTarget(code)}
                                                    >
                                                        <Trash2 className="mr-2 size-4" /> Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    {codes.total > codes.per_page && (
                        <div className="border-border/70 text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
                            <div>
                                Showing <span className="text-foreground font-medium">{codes.from}</span> to{" "}
                                <span className="text-foreground font-medium">{codes.to}</span> of{" "}
                                <span className="text-foreground font-medium">{codes.total}</span> codes
                            </div>
                            <div className="flex items-center gap-1">
                                {codes.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <Button
                                                key={i}
                                                variant="ghost"
                                                size="sm"
                                                disabled
                                                className="size-8 p-0 text-xs"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }
                                    return (
                                        <Button key={i} asChild variant={link.active ? "default" : "ghost"} size="sm" className="size-8 p-0 text-xs">
                                            <Link href={link.url} preserveScroll preserveState>
                                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                            </Link>
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </section>
            </div>

            {/* Create/Edit Code Dialog */}
            <Dialog open={codeModalOpen} onOpenChange={setCodeModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingCode ? "Edit authority course code" : "Register official course code"}</DialogTitle>
                        <DialogDescription>Enter official regulatory course details and its overarching category/discipline group.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSaveCode} className="grid gap-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="code-authority">Authority</Label>
                            <Select
                                value={codeForm.data.code_authority_id}
                                onValueChange={(val) => codeForm.setData("code_authority_id", val)}
                                disabled={Boolean(editingCode)}
                            >
                                <SelectTrigger id="code-authority">
                                    <SelectValue placeholder="Select regulatory authority" />
                                </SelectTrigger>
                                <SelectContent>
                                    {authorities.map((auth) => (
                                        <SelectItem key={auth.id} value={String(auth.id)}>
                                            {auth.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={codeForm.errors.code_authority_id} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="code-input">Official Code *</Label>
                                <Input
                                    id="code-input"
                                    placeholder="e.g. 140101 or 464108"
                                    value={codeForm.data.code}
                                    onChange={(e) => codeForm.setData("code", e.target.value)}
                                    required
                                />
                                <FieldError message={codeForm.errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category-code-input">Discipline / Group Code</Label>
                                <Input
                                    id="category-code-input"
                                    placeholder="e.g. 14 or 47"
                                    value={codeForm.data.category_code}
                                    onChange={(e) => codeForm.setData("category_code", e.target.value)}
                                />
                                <FieldError message={codeForm.errors.category_code} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="title-input">Official Course Title *</Label>
                            <Input
                                id="title-input"
                                placeholder="e.g. Elementary Education"
                                value={codeForm.data.title}
                                onChange={(e) => codeForm.setData("title", e.target.value)}
                                required
                            />
                            <FieldError message={codeForm.errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category-name-input">Category / Discipline Group Name</Label>
                            <Input
                                id="category-name-input"
                                placeholder="e.g. Education Science and Teacher Training"
                                value={codeForm.data.category_name}
                                onChange={(e) => codeForm.setData("category_name", e.target.value)}
                            />
                            <FieldError message={codeForm.errors.category_name} />
                        </div>

                        <div className="flex items-center gap-2 pt-1">
                            <Checkbox
                                id="is-active-check"
                                checked={codeForm.data.is_active}
                                onCheckedChange={(c) => codeForm.setData("is_active", c === true)}
                            />
                            <Label htmlFor="is-active-check" className="cursor-pointer font-normal">
                                Code is active and selectable in program forms
                            </Label>
                        </div>

                        <DialogFooter className="pt-3">
                            <Button type="button" variant="outline" onClick={() => setCodeModalOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={codeForm.processing}>
                                {editingCode ? "Update code" : "Register code"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Create/Edit Authority Dialog */}
            <Dialog open={authorityModalOpen} onOpenChange={setAuthorityModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingAuthority ? `Edit authority: ${editingAuthority.name}` : "Register regulatory authority"}</DialogTitle>
                        <DialogDescription>Define a regulatory body whose official program catalog this institution tracks.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSaveAuthority} className="grid gap-4 py-2">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="auth-name">Authority Name *</Label>
                                <Input
                                    id="auth-name"
                                    placeholder="e.g. CHED or DepEd"
                                    value={authorityForm.data.name}
                                    onChange={(e) => authorityForm.setData("name", e.target.value)}
                                    required
                                />
                                <FieldError message={authorityForm.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="auth-key">Key (Slug) *</Label>
                                <Input
                                    id="auth-key"
                                    placeholder="e.g. ched"
                                    value={authorityForm.data.key}
                                    onChange={(e) => authorityForm.setData("key", e.target.value)}
                                    disabled={Boolean(editingAuthority)}
                                    required
                                />
                                <FieldError message={authorityForm.errors.key} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="auth-country">Country Code</Label>
                                <Input
                                    id="auth-country"
                                    placeholder="e.g. PH"
                                    maxLength={2}
                                    value={authorityForm.data.country_code}
                                    onChange={(e) => authorityForm.setData("country_code", e.target.value.toUpperCase())}
                                />
                                <FieldError message={authorityForm.errors.country_code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="auth-framework">Framework Link</Label>
                                <Input
                                    id="auth-framework"
                                    placeholder="e.g. ched_psg"
                                    value={authorityForm.data.curriculum_framework}
                                    onChange={(e) => authorityForm.setData("curriculum_framework", e.target.value)}
                                />
                                <FieldError message={authorityForm.errors.curriculum_framework} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="auth-description">Description</Label>
                            <Textarea
                                id="auth-description"
                                placeholder="Notes about this regulatory framework, source issuances, etc."
                                value={authorityForm.data.description}
                                onChange={(e) => authorityForm.setData("description", e.target.value)}
                                rows={3}
                            />
                            <FieldError message={authorityForm.errors.description} />
                        </div>

                        <div className="flex items-center gap-2 pt-1">
                            <Checkbox
                                id="auth-active-check"
                                checked={authorityForm.data.is_active}
                                onCheckedChange={(c) => authorityForm.setData("is_active", c === true)}
                            />
                            <Label htmlFor="auth-active-check" className="cursor-pointer font-normal">
                                Authority is active
                            </Label>
                        </div>

                        <DialogFooter className="flex flex-row items-center justify-between gap-2 pt-3">
                            {editingAuthority ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        const target = editingAuthority;
                                        setAuthorityModalOpen(false);
                                        setDeleteAuthorityTarget(target);
                                    }}
                                >
                                    <Trash2 className="mr-1 size-4" /> Delete authority
                                </Button>
                            ) : (
                                <div />
                            )}
                            <div className="flex items-center gap-2">
                                <Button type="button" variant="outline" onClick={() => setAuthorityModalOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={authorityForm.processing}>
                                    {editingAuthority ? "Update authority" : "Create authority"}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Code Confirmation Dialog */}
            <AlertDialog open={deleteCodeTarget !== null} onOpenChange={(open) => !open && setDeleteCodeTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete authority course code?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete code <strong>{deleteCodeTarget?.code}</strong> ({deleteCodeTarget?.title})? Any programs
                            linked to this code will retain their local information, but their official code reference will be unlinked.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeleteCode} className="bg-destructive hover:bg-destructive/90 text-destructive-foreground">
                            Delete code
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Delete Authority Confirmation Dialog */}
            <AlertDialog open={deleteAuthorityTarget !== null} onOpenChange={(open) => !open && setDeleteAuthorityTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete regulatory authority?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete authority <strong>{deleteAuthorityTarget?.name}</strong>? This will cascade delete all
                            official codes and imports registered under this authority.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDeleteAuthority}
                            className="bg-destructive hover:bg-destructive/90 text-destructive-foreground"
                        >
                            Delete authority
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
