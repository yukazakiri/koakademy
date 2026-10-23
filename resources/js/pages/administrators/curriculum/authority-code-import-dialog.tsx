import { IconTile } from "@/components/reui/icon-tile";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Check,
    CheckCircle2,
    ChevronDown,
    FileSpreadsheet,
    FileUp,
    Info,
    Loader2,
    Plus,
    ShieldCheck,
    Upload,
    X,
    XCircle,
} from "lucide-react";
import { AnimatePresence, motion, MotionConfig } from "motion/react";
import { useMemo, useRef, useState, type DragEvent } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

export type AuthoritySummary = {
    id: number;
    key: string;
    name: string;
    country_code: string | null;
    codes_count: number;
};

type FieldProposal = {
    key: string;
    label: string;
    source_header_aliases: string[];
    populated_rows: number;
};

type ImportRow = {
    id: string;
    source_row: number;
    code: string | null;
    title: string | null;
    status: "ready" | "invalid" | "applied" | "skipped";
    action: string | null;
    errors: string[];
    warnings: string[];
};

type AuthorityImportPreview = {
    id: string;
    authority_id: number;
    status: "review" | "completed";
    filename: string;
    summary: {
        ready_rows: number;
        invalid_rows: number;
        applied_rows: number;
        skipped_rows: number;
    };
    field_proposals: FieldProposal[];
    rows: ImportRow[];
};

type PreviewFilter = "all" | "ready" | "invalid";

const acceptedFileTypes = ".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv";

const errorMessage = (error: unknown): string => {
    if (!axios.isAxiosError(error)) return "The file could not be processed.";
    const response = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
    const validationMessage = response?.errors ? Object.values(response.errors).flat()[0] : null;
    return validationMessage ?? response?.message ?? "The file could not be processed.";
};

function StatusBadge({ status }: { status: ImportRow["status"] }) {
    switch (status) {
        case "ready":
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-sky-500/25 bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:text-sky-300"
                >
                    <span className="size-1.5 rounded-full bg-sky-500" />
                    Ready
                </Badge>
            );
        case "invalid":
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-rose-500/25 bg-rose-500/10 px-2 py-0.5 text-[11px] font-medium text-rose-700 dark:text-rose-300"
                >
                    <XCircle className="size-3" />
                    Needs attention
                </Badge>
            );
        case "applied":
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300"
                >
                    <Check className="size-3" />
                    Imported
                </Badge>
            );
        case "skipped":
            return (
                <Badge variant="outline" className="border-border/60 bg-muted/50 text-muted-foreground px-2 py-0.5 text-[11px] font-medium">
                    Skipped
                </Badge>
            );
    }
}

export function AuthorityCodeImportDialog({ authorities, isChedAccredited }: { authorities: AuthoritySummary[]; isChedAccredited: boolean }) {
    const [open, setOpen] = useState(false);
    const [authorityId, setAuthorityId] = useState<string>(authorities[0] ? String(authorities[0].id) : "");
    const [showNewAuthority, setShowNewAuthority] = useState(false);
    const [authorityName, setAuthorityName] = useState("");
    const [authorityKey, setAuthorityKey] = useState("");
    const [creating, setCreating] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<AuthorityImportPreview | null>(null);
    const [selectedRowIds, setSelectedRowIds] = useState<string[]>([]);
    const [selectedColumnKeys, setSelectedColumnKeys] = useState<string[]>([]);
    const [filter, setFilter] = useState<PreviewFilter>("all");
    const [rowSearch, setRowSearch] = useState("");
    const [expandedRowId, setExpandedRowId] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const shownRows = useMemo(() => {
        if (!preview) return [];
        let rows = preview.rows;
        if (filter !== "all") {
            rows = rows.filter((row) => row.status === filter);
        }
        if (rowSearch.trim()) {
            const q = rowSearch.toLowerCase().trim();
            rows = rows.filter(
                (row) =>
                    (row.code && row.code.toLowerCase().includes(q)) ||
                    (row.title && row.title.toLowerCase().includes(q)) ||
                    `row ${row.source_row}`.includes(q),
            );
        }
        return rows;
    }, [filter, preview, rowSearch]);

    const visibleReadyIds = shownRows.filter((row) => row.status === "ready").map((row) => row.id);
    const allVisibleSelected = visibleReadyIds.length > 0 && visibleReadyIds.every((id) => selectedRowIds.includes(id));
    const isBusy = uploading || confirming || creating;

    const reset = () => {
        setFile(null);
        setPreview(null);
        setSelectedRowIds([]);
        setSelectedColumnKeys([]);
        setFilter("all");
        setRowSearch("");
        setExpandedRowId(null);
        setError(null);
        setShowNewAuthority(false);
    };

    const changeOpen = (nextOpen: boolean) => {
        if (isBusy) return;
        if (nextOpen && !open) reset();
        setOpen(nextOpen);
    };

    const chooseFile = (nextFile: File | null) => {
        if (!nextFile) return;
        const extension = nextFile.name.split(".").pop()?.toLowerCase();
        if (!extension || !["xlsx", "xls", "csv"].includes(extension)) {
            setError("Choose a valid spreadsheet file (.xlsx, .xls, or .csv).");
            return;
        }
        setFile(nextFile);
        setError(null);
    };

    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        chooseFile(event.dataTransfer.files.item(0));
    };

    const createAuthority = async () => {
        if (!authorityName.trim() || !authorityKey.trim()) {
            setError("Give the new authority a name and key identifier.");
            return;
        }
        setCreating(true);
        setError(null);
        try {
            const response = await axios.post<{ authority: AuthoritySummary }>(
                route("administrators.curriculum.code-authorities.store"),
                {
                    name: authorityName.trim(),
                    key: authorityKey.trim(),
                    country_code: isChedAccredited ? "PH" : null,
                },
                { headers: { Accept: "application/json" } },
            );
            setAuthorityId(String(response.data.authority.id));
            setShowNewAuthority(false);
            setAuthorityName("");
            setAuthorityKey("");
            toast.success(`Authority “${response.data.authority.name}” registered.`);
            router.reload({ only: ["authority_codes"] });
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setCreating(false);
        }
    };

    const stageImport = async () => {
        if (!file) {
            setError("Choose a spreadsheet file first.");
            return;
        }
        if (!authorityId) {
            setError("Choose the regulator authority first.");
            return;
        }
        const data = new FormData();
        data.append("file", file);
        data.append("code_authority_id", authorityId);
        setUploading(true);
        setError(null);
        try {
            const response = await axios.post<{ import: AuthorityImportPreview }>(
                route("administrators.curriculum.code-authority-imports.store"),
                data,
                { headers: { Accept: "application/json" } },
            );
            const staged = response.data.import;
            setPreview(staged);
            setSelectedRowIds(staged.rows.filter((row) => row.status === "ready").map((row) => row.id));
            setSelectedColumnKeys(staged.field_proposals.map((field) => field.key));
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setUploading(false);
        }
    };

    const confirmImport = async () => {
        if (!preview || selectedRowIds.length === 0) return;
        setConfirming(true);
        setError(null);
        try {
            const response = await axios.post<{ import: AuthorityImportPreview }>(
                route("administrators.curriculum.code-authority-imports.confirm", preview.id),
                { row_ids: selectedRowIds, adopt_column_keys: selectedColumnKeys },
                { headers: { Accept: "application/json" } },
            );
            setPreview(response.data.import);
            toast.success(`${response.data.import.summary.applied_rows} official codes imported.`);
            router.reload({ only: ["authority_codes"] });
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setConfirming(false);
        }
    };

    const toggleRow = (rowId: string, checked: boolean) => {
        setSelectedRowIds((current) => (checked ? [...new Set([...current, rowId])] : current.filter((id) => id !== rowId)));
    };

    const toggleVisible = (checked: boolean) => {
        setSelectedRowIds((current) =>
            checked ? [...new Set([...current, ...visibleReadyIds])] : current.filter((id) => !visibleReadyIds.includes(id)),
        );
    };

    const toggleColumn = (key: string, checked: boolean) => {
        setSelectedColumnKeys((current) => (checked ? [...new Set([...current, key])] : current.filter((k) => k !== key)));
    };

    return (
        <MotionConfig reducedMotion="user">
            <Dialog open={open} onOpenChange={changeOpen}>
                <DialogTrigger asChild>
                    <Button variant="outline" className="hover:bg-muted/70 gap-2 rounded-xl font-medium shadow-2xs transition-colors">
                        <FileUp className="size-4 text-sky-600 dark:text-sky-400" />
                        <span>Import official codes</span>
                    </Button>
                </DialogTrigger>
                <DialogContent className="border-border/80 bg-background flex h-[min(92dvh,54rem)] max-h-[92dvh] max-w-[calc(100%-1.5rem)] flex-col gap-0 overflow-hidden rounded-3xl p-0 shadow-2xl sm:max-w-4xl">
                    {/* Header with Step Indicator */}
                    <DialogHeader className="border-border/70 bg-card/60 shrink-0 border-b px-6 py-5 backdrop-blur-md">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <IconTile variant="soft" size="default" className="text-sky-600 dark:text-sky-400">
                                    <FileSpreadsheet />
                                </IconTile>
                                <div>
                                    <DialogTitle className="text-xl font-bold tracking-tight">
                                        {preview?.status === "completed"
                                            ? "Official codes imported"
                                            : preview
                                              ? "Review & Confirm Spreadsheet"
                                              : "Import Official Regulator Codes"}
                                    </DialogTitle>
                                    <DialogDescription className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                                        {isChedAccredited
                                            ? "CHED-accredited registry import. Spreadsheet data is stored locally in your database."
                                            : "Import regulator course codes from official spreadsheets to link with curriculum programs."}
                                    </DialogDescription>
                                </div>
                            </div>

                            {/* Step chips */}
                            <div className="flex items-center gap-1.5 self-start sm:self-center">
                                <span
                                    className={cn(
                                        "flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-colors",
                                        !preview ? "bg-primary text-primary-foreground shadow-xs" : "bg-muted text-muted-foreground",
                                    )}
                                >
                                    <span className="bg-background/20 flex size-4 items-center justify-center rounded-full text-[10px]">1</span>
                                    Upload
                                </span>
                                <span className="text-muted-foreground/40 text-xs">/</span>
                                <span
                                    className={cn(
                                        "flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-colors",
                                        preview && preview.status === "review"
                                            ? "bg-primary text-primary-foreground shadow-xs"
                                            : preview && preview.status === "completed"
                                              ? "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                                              : "bg-muted text-muted-foreground",
                                    )}
                                >
                                    <span className="bg-background/20 flex size-4 items-center justify-center rounded-full text-[10px]">2</span>
                                    Review & Map
                                </span>
                            </div>
                        </div>
                    </DialogHeader>

                    <ScrollArea className="min-h-0 flex-1">
                        <div className="p-6">
                            <AnimatePresence initial={false}>
                                {error && (
                                    <motion.div
                                        initial={{ opacity: 0, y: -6 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0, y: -6 }}
                                        className="mb-5"
                                    >
                                        <Alert variant="destructive" className="border-destructive/30 bg-destructive/5 text-destructive rounded-2xl">
                                            <AlertCircle className="size-4" />
                                            <AlertTitle className="font-semibold">Import Issue</AlertTitle>
                                            <AlertDescription className="text-xs leading-5">{error}</AlertDescription>
                                        </Alert>
                                    </motion.div>
                                )}

                                {!preview ? (
                                    <motion.div
                                        key="step-upload"
                                        initial={{ opacity: 0, x: -10 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        exit={{ opacity: 0, x: 10 }}
                                        className="grid gap-6"
                                    >
                                        {/* Authority Selector Section */}
                                        <div className="border-border/70 bg-card/60 rounded-2xl border p-5 shadow-xs">
                                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <Label htmlFor="authority-select" className="text-foreground text-sm font-semibold">
                                                        Target Authority / Regulatory Body
                                                    </Label>
                                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                                        Official code directory to update (e.g. CHED, TESDA, DepEd)
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-primary hover:text-primary h-8 gap-1.5 self-start rounded-lg text-xs sm:self-auto"
                                                    onClick={() => setShowNewAuthority(!showNewAuthority)}
                                                    disabled={isBusy}
                                                >
                                                    <Plus className="size-3.5" />
                                                    {showNewAuthority ? "Select existing" : "New authority"}
                                                </Button>
                                            </div>

                                            {!showNewAuthority ? (
                                                <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                                                    <Select value={authorityId} onValueChange={setAuthorityId} disabled={isBusy}>
                                                        <SelectTrigger
                                                            id="authority-select"
                                                            className="bg-background border-border/80 h-10 rounded-xl"
                                                        >
                                                            <SelectValue placeholder="Choose an authority..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {authorities.map((authority) => (
                                                                <SelectItem key={authority.id} value={String(authority.id)}>
                                                                    <span className="font-semibold">{authority.name}</span>
                                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                                        ({authority.codes_count} codes indexed)
                                                                    </span>
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>

                                                    {authorityId && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            className="h-10 gap-2 rounded-xl text-xs"
                                                            disabled={isBusy}
                                                            onClick={() => {
                                                                window.open(
                                                                    route("administrators.curriculum.code-authorities.template", Number(authorityId)),
                                                                    "_blank",
                                                                    "noopener",
                                                                );
                                                            }}
                                                        >
                                                            <FileSpreadsheet className="size-3.5 text-emerald-600" />
                                                            <span>Download Template</span>
                                                        </Button>
                                                    )}
                                                </div>
                                            ) : (
                                                <motion.div
                                                    initial={{ opacity: 0, height: 0 }}
                                                    animate={{ opacity: 1, height: "auto" }}
                                                    exit={{ opacity: 0, height: 0 }}
                                                    className="border-primary/20 bg-primary/[0.03] mt-4 rounded-xl border p-4"
                                                >
                                                    <p className="text-primary mb-3 text-xs font-semibold">Register New Authority Record</p>
                                                    <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                                        <Input
                                                            aria-label="Authority full name"
                                                            placeholder="Full name (e.g. Commission on Higher Education)"
                                                            value={authorityName}
                                                            onChange={(e) => setAuthorityName(e.target.value)}
                                                            disabled={isBusy}
                                                            className="bg-background h-9 rounded-lg text-xs"
                                                        />
                                                        <Input
                                                            aria-label="Authority code key"
                                                            placeholder="Code key (e.g. ched)"
                                                            value={authorityKey}
                                                            onChange={(e) => setAuthorityKey(e.target.value)}
                                                            disabled={isBusy}
                                                            className="bg-background h-9 rounded-lg text-xs lowercase"
                                                        />
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="h-9 rounded-lg px-4 text-xs"
                                                            onClick={createAuthority}
                                                            disabled={isBusy || !authorityName.trim() || !authorityKey.trim()}
                                                        >
                                                            {creating ? <Loader2 className="size-3.5 animate-spin" /> : "Save"}
                                                        </Button>
                                                    </div>
                                                </motion.div>
                                            )}
                                        </div>

                                        {/* Upload Dropzone */}
                                        <div
                                            role="button"
                                            tabIndex={0}
                                            onDrop={handleDrop}
                                            onDragOver={(e) => {
                                                e.preventDefault();
                                                setIsDragging(true);
                                            }}
                                            onDragLeave={() => setIsDragging(false)}
                                            onClick={() => fileInput.current?.click()}
                                            onKeyDown={(e) => {
                                                if (e.key === "Enter" || e.key === " ") {
                                                    e.preventDefault();
                                                    fileInput.current?.click();
                                                }
                                            }}
                                            className={cn(
                                                "focus-visible:ring-ring/50 relative flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed p-8 text-center transition-[background-color,border-color,box-shadow] outline-none focus-visible:ring-2",
                                                isDragging
                                                    ? "border-primary bg-primary/5 scale-[0.99]"
                                                    : file
                                                      ? "border-emerald-500/40 bg-emerald-500/[0.03]"
                                                      : "border-border/80 bg-muted/20 hover:border-primary/50 hover:bg-muted/40",
                                            )}
                                        >
                                            <div
                                                className={cn(
                                                    "flex size-14 items-center justify-center rounded-2xl transition-transform",
                                                    file ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400" : "bg-primary/10 text-primary",
                                                )}
                                            >
                                                {file ? <FileSpreadsheet className="size-7" /> : <Upload className="size-7" />}
                                            </div>

                                            <div className="space-y-1">
                                                {file ? (
                                                    <>
                                                        <p className="text-foreground flex items-center justify-center gap-2 text-sm font-semibold">
                                                            <span>{file.name}</span>
                                                            <span className="text-muted-foreground text-xs font-normal">
                                                                ({(file.size / 1024).toFixed(1)} KB)
                                                            </span>
                                                        </p>
                                                        <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                            Spreadsheet ready for validation · Click to change file
                                                        </p>
                                                    </>
                                                ) : (
                                                    <>
                                                        <p className="text-foreground text-sm font-semibold">
                                                            Drag and drop your spreadsheet here, or{" "}
                                                            <span className="text-primary underline">browse</span>
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">
                                                            Accepts .xlsx, .xls, or .csv formats · Must contain columns for Code and Title
                                                        </p>
                                                    </>
                                                )}
                                            </div>

                                            {file && (
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setFile(null);
                                                    }}
                                                    className="text-muted-foreground hover:text-destructive mt-1 flex items-center gap-1 text-xs transition-colors"
                                                >
                                                    <X className="size-3.5" /> Remove file
                                                </button>
                                            )}

                                            <input
                                                ref={fileInput}
                                                type="file"
                                                accept={acceptedFileTypes}
                                                className="hidden"
                                                onChange={(e) => chooseFile(e.target.files?.item(0) ?? null)}
                                            />
                                        </div>

                                        {/* Security & Scope Notice */}
                                        <div className="text-muted-foreground flex items-start gap-3 rounded-xl border border-sky-500/20 bg-sky-500/[0.04] p-4 text-xs leading-relaxed">
                                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                                            <span>
                                                Imported authority codes are strictly scoped to your school installation. They assist in automatic
                                                code lookups and title suggestions when administrators add curriculum programs.
                                            </span>
                                        </div>
                                    </motion.div>
                                ) : (
                                    <motion.div
                                        key="step-review"
                                        initial={{ opacity: 0, x: 10 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        exit={{ opacity: 0, x: -10 }}
                                        className="grid gap-5"
                                    >
                                        {/* Summary KPI Strip */}
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            <div className="rounded-2xl border border-sky-500/20 bg-sky-500/[0.04] p-3.5">
                                                <p className="text-[11px] font-semibold tracking-wider text-sky-700 uppercase dark:text-sky-300">
                                                    Ready to Import
                                                </p>
                                                <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums">{preview.summary.ready_rows}</p>
                                            </div>
                                            <div className="rounded-2xl border border-rose-500/20 bg-rose-500/[0.04] p-3.5">
                                                <p className="text-[11px] font-semibold tracking-wider text-rose-700 uppercase dark:text-rose-300">
                                                    Needs Attention
                                                </p>
                                                <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums">{preview.summary.invalid_rows}</p>
                                            </div>
                                            <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/[0.04] p-3.5">
                                                <p className="text-[11px] font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-300">
                                                    Imported
                                                </p>
                                                <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums">{preview.summary.applied_rows}</p>
                                            </div>
                                            <div className="border-border/70 bg-muted/40 rounded-2xl border p-3.5">
                                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Skipped</p>
                                                <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums">{preview.summary.skipped_rows}</p>
                                            </div>
                                        </div>

                                        {/* Field Proposals (Discovered Columns) */}
                                        {preview.field_proposals.length > 0 && preview.status === "review" && (
                                            <div className="rounded-2xl border border-amber-500/25 bg-amber-500/[0.04] p-4">
                                                <div className="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                                                    <Info className="size-4 shrink-0" />
                                                    <p className="text-xs font-semibold">New Columns Detected in Spreadsheet</p>
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Select columns you want to capture as custom attributes for this authority:
                                                </p>
                                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                    {preview.field_proposals.map((field) => (
                                                        <label
                                                            key={field.key}
                                                            className={cn(
                                                                "flex cursor-pointer items-start gap-2.5 rounded-xl border p-2.5 transition-colors",
                                                                selectedColumnKeys.includes(field.key)
                                                                    ? "border-amber-500/40 bg-amber-500/10"
                                                                    : "border-border/60 bg-background hover:bg-muted/30",
                                                            )}
                                                        >
                                                            <Checkbox
                                                                checked={selectedColumnKeys.includes(field.key)}
                                                                onCheckedChange={(checked) => toggleColumn(field.key, checked === true)}
                                                                className="mt-0.5"
                                                            />
                                                            <div className="text-xs">
                                                                <p className="text-foreground font-semibold">{field.label}</p>
                                                                <p className="text-muted-foreground text-[11px]">
                                                                    {field.populated_rows} rows · matched from{" "}
                                                                    <span className="font-mono">{field.source_header_aliases.join(", ")}</span>
                                                                </p>
                                                            </div>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Row Filter Bar */}
                                        <div className="border-border/70 flex flex-col gap-3 border-b pb-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="bg-muted/50 flex items-center gap-1.5 rounded-xl p-1">
                                                {(["all", "ready", "invalid"] as const).map((opt) => (
                                                    <button
                                                        key={opt}
                                                        type="button"
                                                        onClick={() => setFilter(opt)}
                                                        className={cn(
                                                            "rounded-lg px-3 py-1 text-xs font-semibold transition-[background-color,border-color,box-shadow]",
                                                            filter === opt
                                                                ? "bg-background text-foreground shadow-2xs"
                                                                : "text-muted-foreground hover:text-foreground",
                                                        )}
                                                    >
                                                        {opt === "all"
                                                            ? `All (${preview.rows.length})`
                                                            : opt === "ready"
                                                              ? `Ready (${preview.summary.ready_rows})`
                                                              : `Attention (${preview.summary.invalid_rows})`}
                                                    </button>
                                                ))}
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <Input
                                                    aria-label="Search import rows"
                                                    placeholder="Search rows..."
                                                    value={rowSearch}
                                                    onChange={(e) => setRowSearch(e.target.value)}
                                                    className="bg-background h-8 w-44 rounded-lg text-xs"
                                                />
                                                {preview.status === "review" && visibleReadyIds.length > 0 && (
                                                    <label className="text-muted-foreground flex cursor-pointer items-center gap-2 text-xs font-medium select-none">
                                                        <Checkbox checked={allVisibleSelected} onCheckedChange={(c) => toggleVisible(c === true)} />
                                                        <span>Select ready</span>
                                                    </label>
                                                )}
                                            </div>
                                        </div>

                                        {/* Preview Rows List */}
                                        <div className="space-y-2">
                                            {shownRows.map((row) => {
                                                const isExpanded = expandedRowId === row.id;
                                                const hasIssues = row.errors.length > 0 || row.warnings.length > 0;

                                                return (
                                                    <div
                                                        key={row.id}
                                                        className={cn(
                                                            "rounded-xl border transition-[background-color,border-color,box-shadow]",
                                                            row.status === "invalid"
                                                                ? "border-rose-500/25 bg-rose-500/[0.02]"
                                                                : row.status === "ready"
                                                                  ? "border-border/70 bg-card/60 hover:border-border"
                                                                  : "border-border/50 bg-muted/20",
                                                        )}
                                                    >
                                                        <div
                                                            className="flex cursor-pointer items-center gap-3 p-3 select-none"
                                                            role="button"
                                                            tabIndex={0}
                                                            aria-expanded={isExpanded}
                                                            onKeyDown={(event) => {
                                                                if (
                                                                    event.target === event.currentTarget &&
                                                                    (event.key === "Enter" || event.key === " ")
                                                                ) {
                                                                    event.preventDefault();
                                                                    setExpandedRowId(isExpanded ? null : row.id);
                                                                }
                                                            }}
                                                            onClick={() => setExpandedRowId(isExpanded ? null : row.id)}
                                                        >
                                                            {preview.status === "review" && row.status === "ready" && (
                                                                <Checkbox
                                                                    checked={selectedRowIds.includes(row.id)}
                                                                    onCheckedChange={(c) => toggleRow(row.id, c === true)}
                                                                    onClick={(e) => e.stopPropagation()}
                                                                />
                                                            )}
                                                            <StatusBadge status={row.status} />
                                                            <span className="bg-muted rounded px-2 py-0.5 font-mono text-xs font-bold">
                                                                {row.code ?? "—"}
                                                            </span>
                                                            <span className="text-foreground max-w-sm truncate text-xs font-medium">
                                                                {row.title ?? `Row ${row.source_row}`}
                                                            </span>
                                                            <span className="text-muted-foreground ml-auto hidden text-[11px] sm:inline">
                                                                Row #{row.source_row}
                                                            </span>

                                                            {hasIssues && (
                                                                <span className="flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                                                                    <AlertCircle className="size-3.5" />
                                                                    <span className="hidden sm:inline">Issues</span>
                                                                </span>
                                                            )}

                                                            <ChevronDown
                                                                className={cn(
                                                                    "text-muted-foreground size-4 transition-transform",
                                                                    isExpanded && "rotate-180",
                                                                )}
                                                            />
                                                        </div>

                                                        <AnimatePresence>
                                                            {isExpanded && (
                                                                <motion.div
                                                                    initial={{ height: 0, opacity: 0 }}
                                                                    animate={{ height: "auto", opacity: 1 }}
                                                                    exit={{ height: 0, opacity: 0 }}
                                                                    className="border-border/60 bg-muted/30 border-t px-4 py-3 text-xs"
                                                                >
                                                                    {row.errors.map((msg) => (
                                                                        <div
                                                                            key={msg}
                                                                            className="flex items-center gap-2 py-0.5 text-rose-600 dark:text-rose-400"
                                                                        >
                                                                            <X className="size-3 shrink-0" />
                                                                            <span>{msg}</span>
                                                                        </div>
                                                                    ))}
                                                                    {row.warnings.map((msg) => (
                                                                        <div
                                                                            key={msg}
                                                                            className="flex items-center gap-2 py-0.5 text-amber-600 dark:text-amber-400"
                                                                        >
                                                                            <AlertTriangle className="size-3 shrink-0" />
                                                                            <span>{msg}</span>
                                                                        </div>
                                                                    ))}
                                                                    {!hasIssues && (
                                                                        <p className="text-muted-foreground">
                                                                            Code and title validated without discrepancies.
                                                                        </p>
                                                                    )}
                                                                </motion.div>
                                                            )}
                                                        </AnimatePresence>
                                                    </div>
                                                );
                                            })}

                                            {shownRows.length === 0 && (
                                                <div className="text-muted-foreground py-12 text-center text-xs">
                                                    No rows match the active filter or search query.
                                                </div>
                                            )}
                                        </div>
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        </div>
                    </ScrollArea>

                    {/* Footer Controls */}
                    <DialogFooter className="border-border/70 bg-card/60 shrink-0 flex-row items-center justify-between border-t px-6 py-4 sm:justify-between">
                        <div>
                            {preview && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-muted-foreground hover:text-foreground gap-1.5 rounded-xl text-xs"
                                    onClick={() => setPreview(null)}
                                    disabled={isBusy}
                                >
                                    <ArrowLeft className="size-3.5" />
                                    <span>Back to upload</span>
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-xl text-xs"
                                onClick={() => changeOpen(false)}
                                disabled={isBusy}
                            >
                                {preview?.status === "completed" ? "Done" : "Cancel"}
                            </Button>

                            {!preview && (
                                <Button
                                    type="button"
                                    onClick={stageImport}
                                    disabled={!file || !authorityId || isBusy}
                                    className="gap-2 rounded-xl px-4 text-xs font-semibold shadow-sm"
                                >
                                    {uploading ? <Loader2 className="size-3.5 animate-spin" /> : <Upload className="size-3.5" />}
                                    <span>Review spreadsheet</span>
                                </Button>
                            )}

                            {preview?.status === "review" && (
                                <Button
                                    type="button"
                                    onClick={confirmImport}
                                    disabled={selectedRowIds.length === 0 || isBusy}
                                    className="gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                                >
                                    {confirming ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                    <span>
                                        Import {selectedRowIds.length} code{selectedRowIds.length === 1 ? "" : "s"}
                                    </span>
                                </Button>
                            )}
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MotionConfig>
    );
}
