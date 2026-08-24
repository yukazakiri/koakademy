import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { router } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, CheckCircle2, ChevronDown, FileSpreadsheet, FileUp, Loader2, ShieldCheck, UserRoundCheck, XCircle } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

type ImportField = {
    label: string;
    value: string | null;
    masked?: boolean;
};

type ImportRow = {
    id: string;
    source_row: number;
    faculty_id_number: string | null;
    name: string | null;
    status: "ready" | "invalid" | "applied" | "skipped";
    action: "create" | "update" | null;
    errors: string[];
    warnings: string[];
    fields: ImportField[];
};

type FacultyImportPreview = {
    id: string;
    status: "review" | "completed";
    filename: string;
    summary: {
        ready_rows: number;
        invalid_rows: number;
        applied_rows: number;
        skipped_rows: number;
    };
    rows: ImportRow[];
};

type PreviewFilter = "all" | "ready" | "invalid";

const acceptedFileTypes =
    ".xlsx,.xls,.mdb,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/x-msaccess";

const errorMessage = (error: unknown): string => {
    if (!axios.isAxiosError(error)) return "The file could not be processed.";

    const response = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
    const validationMessage = response?.errors ? Object.values(response.errors).flat()[0] : null;

    return validationMessage ?? response?.message ?? "The file could not be processed.";
};

function StatusBadge({ status }: { status: ImportRow["status"] }) {
    const styles = {
        ready: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300",
        invalid: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300",
        applied: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        skipped: "border-muted-foreground/20 bg-muted text-muted-foreground",
    };

    const labels = {
        ready: "Ready",
        invalid: "Needs attention",
        applied: "Imported",
        skipped: "Skipped",
    };

    return (
        <Badge variant="outline" className={styles[status]}>
            {labels[status]}
        </Badge>
    );
}

export function FacultyImportDialog() {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<FacultyImportPreview | null>(null);
    const [selectedRowIds, setSelectedRowIds] = useState<string[]>([]);
    const [filter, setFilter] = useState<PreviewFilter>("all");
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const shownRows = useMemo(() => {
        if (!preview) return [];
        if (filter === "all") return preview.rows;

        return preview.rows.filter((row) => row.status === filter);
    }, [filter, preview]);

    const visibleReadyIds = shownRows.filter((row) => row.status === "ready").map((row) => row.id);
    const allVisibleSelected = visibleReadyIds.length > 0 && visibleReadyIds.every((id) => selectedRowIds.includes(id));
    const isBusy = uploading || confirming;

    const reset = () => {
        setFile(null);
        setPreview(null);
        setSelectedRowIds([]);
        setFilter("all");
        setError(null);
    };

    const changeOpen = (nextOpen: boolean) => {
        if (isBusy) return;
        if (nextOpen && !open) reset();
        setOpen(nextOpen);
    };

    const stageImport = async () => {
        if (!file) {
            setError("Choose an Excel or Access file first.");
            return;
        }

        const data = new FormData();
        data.append("file", file);
        setUploading(true);
        setError(null);

        try {
            const response = await axios.post<{ import: FacultyImportPreview }>(route("administrators.faculties.imports.store"), data, {
                headers: { Accept: "application/json" },
            });
            const stagedImport = response.data.import;
            setPreview(stagedImport);
            setSelectedRowIds(stagedImport.rows.filter((row) => row.status === "ready").map((row) => row.id));
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
            const response = await axios.post<{ import: FacultyImportPreview }>(
                route("administrators.faculties.imports.confirm", preview.id),
                { row_ids: selectedRowIds },
                { headers: { Accept: "application/json" } },
            );
            setPreview(response.data.import);
            toast.success(`${response.data.import.summary.applied_rows} faculty record(s) imported.`);
            router.reload({ only: ["faculties", "stats"] });
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

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <Button variant="outline" onClick={() => changeOpen(true)}>
                <FileUp className="size-4" /> Import faculty
            </Button>
            <DialogContent className="flex max-h-[92dvh] max-w-[calc(100%-1rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-6xl">
                <DialogHeader className="border-border/70 border-b px-6 py-5 pr-12">
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-600">
                            <FileSpreadsheet className="size-4" />
                        </span>
                        <div>
                            <DialogTitle>{preview?.status === "completed" ? "Faculty imported" : "Import faculty records"}</DialogTitle>
                            <DialogDescription className="mt-1">
                                {preview
                                    ? `${preview.filename} · review the valid records before confirming.`
                                    : "Upload an Excel workbook or Access database, review the mapping, then import only the ready records."}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                {!preview ? (
                    <div className="grid min-h-96 place-items-center p-6 sm:p-10">
                        <div className="w-full max-w-xl space-y-5">
                            <label className="border-border bg-muted/20 hover:bg-muted/35 flex cursor-pointer flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-10 text-center transition-colors">
                                <span className="bg-background flex size-12 items-center justify-center rounded-xl border shadow-xs">
                                    <FileUp className="text-muted-foreground size-5" />
                                </span>
                                <span className="font-medium">Choose a faculty import file</span>
                                <span className="text-muted-foreground max-w-md text-xs leading-5">
                                    Supported formats: Excel (.xlsx or .xls) and Microsoft Access (.mdb). Download the template for the current
                                    configurable faculty fields.
                                </span>
                                <Input
                                    type="file"
                                    accept={acceptedFileTypes}
                                    className="max-w-sm cursor-pointer"
                                    onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                                />
                            </label>
                            {file ? <p className="text-muted-foreground text-center text-xs">Selected: {file.name}</p> : null}
                            <Alert className="border-sky-500/20 bg-sky-500/5">
                                <ShieldCheck />
                                <AlertTitle>Private field values stay protected</AlertTitle>
                                <AlertDescription>
                                    Configurable faculty fields are shown only as protected values during review. Importing a row never creates a
                                    portal account.
                                </AlertDescription>
                            </Alert>
                            {error ? (
                                <Alert variant="destructive">
                                    <AlertTriangle />
                                    <AlertTitle>File not accepted</AlertTitle>
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            ) : null}
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="border-border/70 grid grid-cols-2 border-b sm:grid-cols-4">
                            {[
                                [
                                    preview.status === "completed" ? "Imported" : "Ready",
                                    preview.status === "completed" ? preview.summary.applied_rows : preview.summary.ready_rows,
                                    "text-emerald-600",
                                ],
                                ["Needs attention", preview.summary.invalid_rows, "text-rose-600"],
                                ["Skipped", preview.summary.skipped_rows, "text-muted-foreground"],
                                ["Rows", preview.rows.length, "text-sky-600"],
                            ].map(([title, value, tone]) => (
                                <div
                                    key={String(title)}
                                    className="border-border/70 border-r px-5 py-4 last:border-r-0 even:border-r-0 sm:last:border-r-0 sm:even:border-r"
                                >
                                    <p className="text-muted-foreground text-[11px] font-medium">{title}</p>
                                    <p className={`mt-1 text-2xl font-semibold tracking-tight tabular-nums ${tone}`}>{value}</p>
                                </div>
                            ))}
                        </div>

                        <div className="border-border/70 flex flex-wrap items-center gap-2 border-b px-5 py-3">
                            {(
                                [
                                    ["all", "All"],
                                    ["ready", "Ready"],
                                    ["invalid", "Needs attention"],
                                ] as const
                            ).map(([value, label]) => (
                                <Button key={value} variant={filter === value ? "secondary" : "ghost"} size="xs" onClick={() => setFilter(value)}>
                                    {label}
                                </Button>
                            ))}
                        </div>

                        <ScrollArea className="min-h-0 flex-1">
                            <div className="min-w-[800px]">
                                <div className="bg-muted/45 text-muted-foreground sticky top-0 z-10 grid grid-cols-[44px_1.4fr_0.8fr_0.7fr_44px] items-center border-b px-4 py-2.5 text-[10px] font-semibold tracking-wide uppercase">
                                    <Checkbox
                                        aria-label="Select all visible ready faculty rows"
                                        checked={allVisibleSelected}
                                        disabled={preview.status === "completed" || visibleReadyIds.length === 0}
                                        onCheckedChange={(checked) => toggleVisible(checked === true)}
                                    />
                                    <span>Faculty</span>
                                    <span>Action</span>
                                    <span>Status</span>
                                    <span />
                                </div>
                                {shownRows.map((row) => (
                                    <details key={row.id} className="group border-border/70 border-b last:border-b-0">
                                        <summary className="hover:bg-muted/25 grid cursor-pointer list-none grid-cols-[44px_1.4fr_0.8fr_0.7fr_44px] items-center px-4 py-3.5 transition-colors">
                                            <span onClick={(event) => event.preventDefault()}>
                                                <Checkbox
                                                    aria-label={`Select ${row.name ?? `row ${row.source_row}`}`}
                                                    checked={selectedRowIds.includes(row.id)}
                                                    disabled={preview.status === "completed" || row.status !== "ready"}
                                                    onCheckedChange={(checked) => toggleRow(row.id, checked === true)}
                                                />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium">{row.name ?? "Unnamed faculty"}</span>
                                                <span className="text-muted-foreground mt-0.5 block font-mono text-[10px]">
                                                    {row.faculty_id_number ?? "No faculty ID"} · Source row {row.source_row}
                                                </span>
                                            </span>
                                            <span>
                                                <Badge variant="outline" className="capitalize">
                                                    {row.action ?? "No change"}
                                                </Badge>
                                            </span>
                                            <span>
                                                <StatusBadge status={row.status} />
                                            </span>
                                            <ChevronDown className="text-muted-foreground size-4 transition-transform group-open:rotate-180" />
                                        </summary>
                                        <div className="bg-muted/15 border-border/70 border-t px-16 py-4">
                                            {row.errors.length > 0 ? (
                                                <div className="mb-3 space-y-1 text-xs text-rose-600">
                                                    {row.errors.map((message) => (
                                                        <p key={message} className="flex gap-2">
                                                            <XCircle className="mt-0.5 size-3.5 shrink-0" /> {message}
                                                        </p>
                                                    ))}
                                                </div>
                                            ) : null}
                                            {row.warnings.map((message) => (
                                                <p key={message} className="mb-3 flex gap-2 text-xs text-amber-600">
                                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" /> {message}
                                                </p>
                                            ))}
                                            {row.fields.length > 0 ? (
                                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                    {row.fields.map((field) => (
                                                        <div key={field.label} className="bg-background rounded-lg border p-3">
                                                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                                                {field.label}
                                                            </p>
                                                            <p className="mt-1 truncate text-xs font-medium">{field.value ?? "Empty"}</p>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground text-xs">
                                                    No configurable field values were supplied for this row.
                                                </p>
                                            )}
                                        </div>
                                    </details>
                                ))}
                                {shownRows.length === 0 ? (
                                    <div className="text-muted-foreground grid min-h-52 place-items-center text-sm">No rows match this filter.</div>
                                ) : null}
                            </div>
                        </ScrollArea>

                        {error ? (
                            <Alert variant="destructive" className="rounded-none border-x-0 border-b-0">
                                <AlertTriangle />
                                <AlertTitle>Import not completed</AlertTitle>
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        ) : null}
                    </>
                )}

                <DialogFooter className="border-border/70 border-t px-6 py-4">
                    {preview?.status === "review" && preview.summary.ready_rows > selectedRowIds.length ? (
                        <span className="text-muted-foreground mr-auto self-center text-xs">
                            {preview.summary.ready_rows - selectedRowIds.length} ready row(s) will be skipped.
                        </span>
                    ) : null}
                    <Button variant="ghost" disabled={isBusy} onClick={() => changeOpen(false)}>
                        {preview?.status === "completed" ? "Done" : "Cancel"}
                    </Button>
                    {!preview ? (
                        <Button disabled={!file || uploading} onClick={stageImport}>
                            {uploading ? <Loader2 className="size-4 animate-spin" /> : <ShieldCheck className="size-4" />}
                            Review import
                        </Button>
                    ) : preview.status === "review" ? (
                        <Button disabled={confirming || selectedRowIds.length === 0} onClick={confirmImport}>
                            {confirming ? <Loader2 className="size-4 animate-spin" /> : <UserRoundCheck className="size-4" />}
                            Confirm {selectedRowIds.length} row{selectedRowIds.length === 1 ? "" : "s"}
                        </Button>
                    ) : (
                        <span className="flex items-center gap-2 self-center text-sm font-medium text-emerald-600">
                            <CheckCircle2 className="size-4" /> Confirmation complete
                        </span>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
