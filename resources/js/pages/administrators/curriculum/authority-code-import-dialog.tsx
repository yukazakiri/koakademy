import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, CheckCircle2, FileSpreadsheet, FileUp, Loader2, Upload, X, XCircle } from "lucide-react";
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
    category_code: string | null;
    category_name: string | null;
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

export function AuthorityCodeImportDialog({ authorities, isChedAccredited }: { authorities: AuthoritySummary[]; isChedAccredited: boolean }) {
    const [open, setOpen] = useState(false);
    const [authorityId, setAuthorityId] = useState<string>(authorities[0] ? String(authorities[0].id) : "");
    const [authorityName, setAuthorityName] = useState("");
    const [authorityKey, setAuthorityKey] = useState("");
    const [creating, setCreating] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<AuthorityImportPreview | null>(null);
    const [selectedRowIds, setSelectedRowIds] = useState<string[]>([]);
    const [selectedColumnKeys, setSelectedColumnKeys] = useState<string[]>([]);
    const [filter, setFilter] = useState<PreviewFilter>("all");
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const shownRows = useMemo(() => {
        if (!preview) return [];
        if (filter === "all") return preview.rows;
        return preview.rows.filter((row) => row.status === filter);
    }, [filter, preview]);

    const visibleReadyIds = shownRows.filter((row) => row.status === "ready").map((row) => row.id);
    const allVisibleSelected = visibleReadyIds.length > 0 && visibleReadyIds.every((id) => selectedRowIds.includes(id));
    const isBusy = uploading || confirming || creating;

    const reset = () => {
        setFile(null);
        setPreview(null);
        setSelectedRowIds([]);
        setSelectedColumnKeys([]);
        setFilter("all");
        setError(null);
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
            setError("Choose a spreadsheet file (.xlsx, .xls, or .csv).");
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
            setError("Give the new authority a name and a key first.");
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
            toast.success(`Authority “${response.data.authority.name}” created.`);
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
            setError("Choose the authority this list belongs to first.");
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
            toast.success(`${response.data.import.summary.applied_rows} official code(s) imported.`);
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
        <Dialog open={open} onOpenChange={changeOpen}>
            <Button variant="outline" onClick={() => changeOpen(true)}>
                <FileUp className="size-4" /> Import official codes
            </Button>
            <DialogContent className="flex h-[min(92dvh,52rem)] max-h-[92dvh] max-w-[calc(100%-1rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-6xl">
                <DialogHeader className="border-border/70 shrink-0 border-b px-6 py-5 pr-12">
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-600">
                            <FileSpreadsheet className="size-4" />
                        </span>
                        <div>
                            <DialogTitle>{preview?.status === "completed" ? "Official codes imported" : "Import official course codes"}</DialogTitle>
                            <DialogDescription className="mt-1">
                                {isChedAccredited
                                    ? "Your school is CHED-accredited, so you can import the CHED list your institution follows. Nothing ships with the app — the file stays yours."
                                    : "Import the regulator list your institution follows. The file stays in your school and is never shared."}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <ScrollArea className="min-h-0 flex-1">
                    <div className="grid gap-5 px-6 py-5">
                        {error && (
                            <Alert variant="destructive">
                                <AlertTriangle className="size-4" />
                                <AlertTitle>Import needs attention</AlertTitle>
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        {!preview && (
                            <>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="authority-select">Authority list</Label>
                                        <Select value={authorityId} onValueChange={setAuthorityId} disabled={isBusy}>
                                            <SelectTrigger id="authority-select" className="rounded-xl">
                                                <SelectValue placeholder="Choose an authority" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {authorities.map((authority) => (
                                                    <SelectItem key={authority.id} value={String(authority.id)}>
                                                        {authority.name} · {authority.codes_count} codes
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {authorities.length === 0 && (
                                            <p className="text-muted-foreground text-xs">
                                                No authority yet — create one below, then import its spreadsheet.
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Spreadsheet template</Label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="justify-start rounded-xl"
                                            disabled={!authorityId || isBusy}
                                            onClick={() => {
                                                if (!authorityId) return;
                                                window.open(
                                                    route("administrators.curriculum.code-authorities.template", Number(authorityId)),
                                                    "_blank",
                                                    "noopener",
                                                );
                                            }}
                                        >
                                            <FileSpreadsheet className="size-4" />
                                            Download headers-only template
                                        </Button>
                                        <p className="text-muted-foreground text-xs">Headers only — official data always comes from your own file.</p>
                                    </div>
                                </div>

                                <div className="rounded-2xl border border-dashed p-4">
                                    <p className="text-sm font-semibold">Or register a new authority</p>
                                    <div className="mt-3 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                                        <Input
                                            placeholder="Authority name (e.g. CHED)"
                                            value={authorityName}
                                            onChange={(e) => setAuthorityName(e.target.value)}
                                            disabled={isBusy}
                                        />
                                        <Input
                                            placeholder="Key (e.g. ched)"
                                            value={authorityKey}
                                            onChange={(e) => setAuthorityKey(e.target.value)}
                                            disabled={isBusy}
                                        />
                                        <Button type="button" variant="secondary" onClick={createAuthority} disabled={isBusy}>
                                            {creating ? <Loader2 className="size-4 animate-spin" /> : null}
                                            Create
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    role="button"
                                    tabIndex={0}
                                    onDrop={handleDrop}
                                    onDragOver={(event) => {
                                        event.preventDefault();
                                        setIsDragging(true);
                                    }}
                                    onDragLeave={() => setIsDragging(false)}
                                    onClick={() => fileInput.current?.click()}
                                    onKeyDown={(event) => {
                                        if (event.key === "Enter") fileInput.current?.click();
                                    }}
                                    className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed px-6 py-10 text-center transition ${
                                        isDragging ? "border-sky-500 bg-sky-500/5" : "border-border/70 hover:border-sky-500/50"
                                    }`}
                                >
                                    <Upload className="text-muted-foreground size-6" />
                                    <p className="text-sm font-medium">{file ? file.name : "Drop the regulator spreadsheet here"}</p>
                                    <p className="text-muted-foreground text-xs">.xlsx, .xls or .csv · needs a code column and a title column</p>
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        accept={acceptedFileTypes}
                                        className="hidden"
                                        onChange={(event) => chooseFile(event.target.files?.item(0) ?? null)}
                                    />
                                </div>
                            </>
                        )}

                        {preview && (
                            <>
                                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                    <div className="rounded-xl border p-3">
                                        <p className="text-muted-foreground text-xs">Ready</p>
                                        <p className="text-xl font-semibold">{preview.summary.ready_rows}</p>
                                    </div>
                                    <div className="rounded-xl border p-3">
                                        <p className="text-muted-foreground text-xs">Needs attention</p>
                                        <p className="text-xl font-semibold">{preview.summary.invalid_rows}</p>
                                    </div>
                                    <div className="rounded-xl border p-3">
                                        <p className="text-muted-foreground text-xs">Imported</p>
                                        <p className="text-xl font-semibold">{preview.summary.applied_rows}</p>
                                    </div>
                                    <div className="rounded-xl border p-3">
                                        <p className="text-muted-foreground text-xs">Skipped</p>
                                        <p className="text-xl font-semibold">{preview.summary.skipped_rows}</p>
                                    </div>
                                </div>

                                {preview.field_proposals.length > 0 && preview.status === "review" && (
                                    <div className="rounded-2xl border border-amber-500/30 bg-amber-500/[0.05] p-4">
                                        <p className="text-sm font-semibold">New columns found in your file</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Tick the ones to keep as authority fields. Unticked columns are ignored.
                                        </p>
                                        <div className="mt-3 grid gap-2">
                                            {preview.field_proposals.map((field) => (
                                                <label
                                                    key={field.key}
                                                    className="flex cursor-pointer items-start gap-2 rounded-xl border border-transparent p-2 hover:border-amber-500/30"
                                                >
                                                    <Checkbox
                                                        checked={selectedColumnKeys.includes(field.key)}
                                                        onCheckedChange={(checked) => toggleColumn(field.key, checked === true)}
                                                    />
                                                    <span className="text-xs">
                                                        <span className="font-semibold">{field.label}</span>{" "}
                                                        <span className="text-muted-foreground">
                                                            ({field.populated_rows} rows · from {field.source_header_aliases.join(", ")})
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center gap-2">
                                    {(["all", "ready", "invalid"] as const).map((option) => (
                                        <Button
                                            key={option}
                                            type="button"
                                            size="sm"
                                            variant={filter === option ? "default" : "ghost"}
                                            className="rounded-full"
                                            onClick={() => setFilter(option)}
                                        >
                                            {option === "all" ? "All rows" : option === "ready" ? "Ready" : "Needs attention"}
                                        </Button>
                                    ))}
                                    {preview.status === "review" && (
                                        <label className="text-muted-foreground ml-auto flex cursor-pointer items-center gap-2 text-xs">
                                            <Checkbox checked={allVisibleSelected} onCheckedChange={(checked) => toggleVisible(checked === true)} />
                                            Select visible ready rows
                                        </label>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    {shownRows.map((row) => (
                                        <details key={row.id} className="rounded-xl border px-3 py-2">
                                            <summary className="flex cursor-pointer items-center gap-2 text-sm">
                                                {preview.status === "review" && row.status === "ready" && (
                                                    <Checkbox
                                                        checked={selectedRowIds.includes(row.id)}
                                                        onCheckedChange={(checked) => toggleRow(row.id, checked === true)}
                                                        onClick={(event) => event.stopPropagation()}
                                                    />
                                                )}
                                                <StatusBadge status={row.status} />
                                                <span className="font-mono font-semibold">{row.code ?? "—"}</span>
                                                <span className="text-muted-foreground truncate">
                                                    {row.title ?? `Spreadsheet row ${row.source_row}`}
                                                </span>
                                                {row.category_name && (
                                                    <span className="text-muted-foreground hidden max-w-[200px] truncate text-xs sm:inline">
                                                        ({row.category_code ? `${row.category_code} · ` : ""}
                                                        {row.category_name})
                                                    </span>
                                                )}
                                                {row.errors.length > 0 && <XCircle className="text-destructive ml-auto size-4 shrink-0" />}
                                                {row.errors.length === 0 && row.warnings.length > 0 && (
                                                    <AlertTriangle className="ml-auto size-4 shrink-0 text-amber-500" />
                                                )}
                                                {row.status === "applied" && <CheckCircle2 className="ml-auto size-4 shrink-0 text-emerald-500" />}
                                            </summary>
                                            {(row.errors.length > 0 || row.warnings.length > 0) && (
                                                <div className="mt-2 grid gap-1 pb-1 text-xs">
                                                    {row.errors.map((message) => (
                                                        <p key={message} className="text-destructive flex items-center gap-1.5">
                                                            <X className="size-3" /> {message}
                                                        </p>
                                                    ))}
                                                    {row.warnings.map((message) => (
                                                        <p key={message} className="flex items-center gap-1.5 text-amber-600">
                                                            <AlertTriangle className="size-3" /> {message}
                                                        </p>
                                                    ))}
                                                </div>
                                            )}
                                        </details>
                                    ))}
                                    {shownRows.length === 0 && (
                                        <p className="text-muted-foreground py-6 text-center text-sm">No rows match this filter.</p>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                </ScrollArea>

                <DialogFooter className="border-border/70 shrink-0 gap-2 border-t px-6 py-4">
                    {!preview && (
                        <Button type="button" onClick={stageImport} disabled={!file || !authorityId || isBusy} className="rounded-full">
                            {uploading ? <Loader2 className="size-4 animate-spin" /> : <Upload className="size-4" />}
                            Review spreadsheet
                        </Button>
                    )}
                    {preview?.status === "review" && (
                        <Button type="button" onClick={confirmImport} disabled={selectedRowIds.length === 0 || isBusy} className="rounded-full">
                            {confirming ? <Loader2 className="size-4 animate-spin" /> : <CheckCircle2 className="size-4" />}
                            Import {selectedRowIds.length} code
                            {selectedRowIds.length === 1 ? "" : "s"}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
