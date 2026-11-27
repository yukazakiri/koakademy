import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Loader2,
    Sparkles,
    UploadCloud,
    XCircle,
} from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";
import type { IdentifierPolicy, SchoolProfile, StudentConfiguration } from "../shared-types";

interface ValidatedBatchRow {
    row_number: number;
    valid: boolean;
    errors: string[];
    warnings: string[];
    normalized: {
        student_type: string;
        student_id: string | null;
        lrn: string | null;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        gender: string;
        birth_date: string | null;
        email: string | null;
        phone: string | null;
        course_id: number | null;
        shs_strand_id: number | null;
        program_label: string;
        academic_year: number;
        status: string;
    };
}

interface StudentBatchIntakeProps {
    configuration: StudentConfiguration;
    identifierPolicy?: IdentifierPolicy;
    schoolProfile?: SchoolProfile;
    sampleProgramCode?: string;
}

interface ParsedDelimitedResult {
    rows: Record<string, string>[];
    parseErrors: Array<{ row_number: number; message: string }>;
}

function escapeCsvCell(value: string): string {
    if (/[",\r\n]/.test(value)) {
        return `"${value.replace(/"/g, '""')}"`;
    }
    return value;
}

function detectDelimiter(text: string): "\t" | "," {
    let inQuotes = false;
    let sawHeaderContent = false;
    let sawTabOnLine = false;

    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (inQuotes) {
            if (ch === '"') {
                if (text[i + 1] === '"') {
                    i += 1;
                } else {
                    inQuotes = false;
                }
            }
            continue;
        }
        if (ch === '"') {
            inQuotes = true;
            sawHeaderContent = true;
        } else if (ch === "\t") {
            sawTabOnLine = true;
        } else if (ch === "\n") {
            if (sawHeaderContent) {
                return sawTabOnLine ? "\t" : ",";
            }
            sawTabOnLine = false;
        } else if (ch !== " " && ch !== "\r") {
            sawHeaderContent = true;
        }
    }

    return sawHeaderContent && sawTabOnLine ? "\t" : ",";
}

function parseDelimitedText(input: string): ParsedDelimitedResult {
    const normalized = input.replace(/^\uFEFF/, "").replace(/\r\n/g, "\n").replace(/\r/g, "\n");
    if (normalized.trim().length === 0) {
        return { rows: [], parseErrors: [] };
    }

    const delimiter = detectDelimiter(normalized);
    const records: Array<{ values: string[]; malformedReason: string | null }> = [];

    let inQuotes = false;
    let justClosedQuote = false;
    let currentField = "";
    let currentRow: string[] = [];
    let malformedReason: string | null = null;

    const pushRecord = () => {
        currentRow.push(currentField);
        const isBlank = malformedReason === null && currentRow.every((cell) => cell.trim() === "");
        if (!isBlank) {
            records.push({ values: currentRow, malformedReason });
        }
        currentField = "";
        currentRow = [];
        justClosedQuote = false;
        malformedReason = null;
    };

    for (let i = 0; i < normalized.length; i++) {
        const ch = normalized[i];

        if (inQuotes) {
            if (ch === '"') {
                if (normalized[i + 1] === '"') {
                    currentField += '"';
                    i += 1;
                } else {
                    inQuotes = false;
                    justClosedQuote = true;
                }
            } else {
                currentField += ch;
            }
            continue;
        }

        if (justClosedQuote) {
            if (ch === delimiter) {
                currentRow.push(currentField);
                currentField = "";
                justClosedQuote = false;
            } else if (ch === "\n") {
                pushRecord();
            } else if (ch === " " || (ch === "\t" && delimiter !== "\t")) {
                // Ignore trailing spaces after a closing quote before the delimiter/newline
            } else {
                malformedReason ??= "Unexpected characters after a closing quote.";
                currentField += ch;
                justClosedQuote = false;
            }
            continue;
        }

        if (ch === '"') {
            if (currentField.trim().length === 0) {
                inQuotes = true;
                currentField = "";
            } else {
                malformedReason ??= "Unexpected quote inside an unquoted field.";
                currentField += ch;
            }
        } else if (ch === delimiter) {
            currentRow.push(currentField);
            currentField = "";
        } else if (ch === "\n") {
            pushRecord();
        } else {
            currentField += ch;
        }
    }

    if (inQuotes) {
        malformedReason ??= "Unclosed quoted field.";
        pushRecord();
    } else if (currentRow.length > 0 || currentField.length > 0 || justClosedQuote) {
        pushRecord();
    }

    if (records.length === 0) {
        return { rows: [], parseErrors: [] };
    }

    const headerRecord = records[0];
    if (headerRecord.malformedReason) {
        return {
            rows: [],
            parseErrors: [{ row_number: 0, message: `Header row: ${headerRecord.malformedReason}` }],
        };
    }

    const headers = headerRecord.values.map((h) =>
        h.trim().toLowerCase().replace(/\s+/g, "_").replace(/[^a-z0-9_]/g, ""),
    );

    if (records.length < 2) {
        return { rows: [], parseErrors: [] };
    }

    const rows: Record<string, string>[] = [];
    const parseErrors: Array<{ row_number: number; message: string }> = [];

    records.slice(1).forEach((record, index) => {
        const rowNumber = index + 1;
        if (record.malformedReason) {
            parseErrors.push({ row_number: rowNumber, message: record.malformedReason });
            return;
        }
        if (record.values.length !== headers.length) {
            parseErrors.push({
                row_number: rowNumber,
                message: `Expected ${headers.length} column(s), found ${record.values.length}. Wrap values containing commas in double quotes.`,
            });
            return;
        }

        const row: Record<string, string> = {};
        headers.forEach((header, idx) => {
            if (header) {
                row[header] = record.values[idx].trim();
            }
        });
        rows.push(row);
    });

    return { rows, parseErrors };
}

export function StudentBatchIntake({
    configuration,
    identifierPolicy,
    schoolProfile,
    sampleProgramCode = "BSCS",
}: StudentBatchIntakeProps) {
    const [rawText, setRawText] = useState("");
    const [inputRevision, setInputRevision] = useState(0);
    const [readingFile, setReadingFile] = useState(false);
    const [validating, setValidating] = useState(false);
    const [committing, setCommitting] = useState(false);
    const [skipDuplicates, setSkipDuplicates] = useState(true);
    const [provisionPortals, setProvisionPortals] = useState(false);
    const [parseErrors, setParseErrors] = useState<Array<{ row_number: number; message: string }>>([]);
    const [committedRowNumbers, setCommittedRowNumbers] = useState<Set<number>>(() => new Set());
    const [preview, setPreview] = useState<{
        revision: number;
        summary: { total: number; valid: number; errors: number; duplicates: number };
        rows: ValidatedBatchRow[];
    } | null>(null);
    const [commitResult, setCommitResult] = useState<{
        created_count: number;
        failed_count: number;
        created: Array<{ id: number; student_id: string; full_name: string }>;
        failed: Array<{ row_number: number; name: string; error: string }>;
    } | null>(null);

    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const inputRevisionRef = useRef(0);
    const validationRequestIdRef = useRef(0);
    const fileReadRequestIdRef = useRef(0);
    const readingFileRef = useRef(false);

    const updateRawText = (nextText: string): number => {
        const nextRevision = inputRevisionRef.current + 1;
        inputRevisionRef.current = nextRevision;
        validationRequestIdRef.current += 1;
        fileReadRequestIdRef.current += 1;
        readingFileRef.current = false;
        setReadingFile(false);
        setInputRevision(nextRevision);
        setRawText(nextText);
        setValidating(false);
        setParseErrors([]);
        setPreview(null);
        setCommitResult(null);
        setCommittedRowNumbers(new Set());
        return nextRevision;
    };

    const defaultType = configuration.default_type || "college";
    const defaultLevel = configuration.year_levels?.[defaultType]?.[0]?.value ?? (defaultType === "shs" ? 11 : 1);
    const externalEnabled =
        configuration.external_identifier.enabled &&
        configuration.external_identifier.student_types.includes(defaultType);
    const manualIdMode = identifierPolicy?.assignment_mode === "manual";

    const buildTemplateCsv = () => {
        const headers = [
            "first_name",
            "middle_name",
            "last_name",
            "gender",
            "birth_date",
            "email",
            "phone",
            "program",
            "year_level",
            "status",
            "student_type",
        ];
        if (externalEnabled) headers.push("lrn");
        if (manualIdMode) headers.push("student_id");

        const row1 = [
            "Ana",
            "M.",
            "Dela Cruz",
            "female",
            "2006-04-15",
            "ana.delacruz@example.edu",
            "09171234567",
            sampleProgramCode,
            String(defaultLevel),
            "enrolled",
            defaultType,
        ];
        const row2 = [
            "Marco",
            "R.",
            "Santos",
            "male",
            "2005-09-21",
            "marco.santos@example.edu",
            "09189876543",
            sampleProgramCode,
            String(defaultLevel),
            "applicant",
            defaultType,
        ];

        if (externalEnabled) {
            row1.push("109876543210");
            row2.push("109876543211");
        }
        if (manualIdMode) {
            row1.push("260001");
            row2.push("260002");
        }

        return [
            headers.map(escapeCsvCell).join(","),
            row1.map(escapeCsvCell).join(","),
            row2.map(escapeCsvCell).join(","),
        ].join("\n");
    };

    const handleDownloadTemplate = () => {
        const csv = buildTemplateCsv();
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `student-batch-template-${defaultType}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    const handleValidate = async (sourceText?: string, targetRevision?: number) => {
        if (committing || readingFileRef.current) {
            toast.warning("Wait for the current operation to finish before re-validating.");
            return;
        }

        const text = sourceText ?? rawText;
        const revision = targetRevision ?? inputRevisionRef.current;
        const parsed = parseDelimitedText(text);

        if (parsed.parseErrors.length > 0) {
            setParseErrors(parsed.parseErrors);
            setPreview(null);
            setCommitResult(null);
            toast.error(
                `Found ${parsed.parseErrors.length} malformed CSV row(s). Fix quoting or column counts before validating.`,
            );
            return;
        }

        if (parsed.rows.length === 0) {
            setParseErrors([]);
            toast.error("Paste or upload at least a header row plus one student row.");
            return;
        }

        const requestId = ++validationRequestIdRef.current;
        setParseErrors([]);
        setValidating(true);
        setCommitResult(null);
        try {
            const response = await axios.post(
                "/administrators/students/batch-import/validate",
                { rows: parsed.rows },
                { headers: { Accept: "application/json" } },
            );
            if (revision !== inputRevisionRef.current || requestId !== validationRequestIdRef.current) {
                return;
            }
            setPreview({
                revision,
                summary: response.data.summary,
                rows: response.data.rows,
            });
            toast.success(
                `Validated ${response.data.summary.total} row(s): ${response.data.summary.valid} ready, ${response.data.summary.errors} with errors.`,
            );
        } catch (error: unknown) {
            if (revision !== inputRevisionRef.current || requestId !== validationRequestIdRef.current) {
                return;
            }
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to validate batch rows.");
        } finally {
            if (requestId === validationRequestIdRef.current) {
                setValidating(false);
            }
        }
    };

    const handleFileUpload = async (file: File) => {
        if (committing || readingFileRef.current) {
            toast.warning("Wait for the current operation to finish before loading another file.");
            return;
        }

        const startRevision = inputRevisionRef.current;
        const readRequestId = ++fileReadRequestIdRef.current;
        readingFileRef.current = true;
        setReadingFile(true);

        let content: string;
        try {
            content = await file.text();
        } catch {
            if (readRequestId === fileReadRequestIdRef.current && inputRevisionRef.current === startRevision) {
                toast.error(`Could not read "${file.name}". Your current input was left unchanged.`);
            }
            return;
        } finally {
            if (readRequestId === fileReadRequestIdRef.current) {
                readingFileRef.current = false;
                setReadingFile(false);
            }
        }

        if (inputRevisionRef.current !== startRevision || readRequestId !== fileReadRequestIdRef.current) {
            return;
        }

        const nextRevision = updateRawText(content);
        await handleValidate(content, nextRevision);
    };

    const currentPreview = preview && preview.revision === inputRevision ? preview : null;

    const rowsToCommit = (currentPreview?.rows ?? []).filter(
        (r) => r.valid && (!skipDuplicates || r.warnings.length === 0) && !committedRowNumbers.has(r.row_number),
    );

    const mapCommitFailures = (
        failedList: Array<{ row_number: number; name: string; error: string }>,
        submittedRows: ValidatedBatchRow[],
    ) =>
        failedList.map((entry) => {
            const matchedRow = submittedRows[entry.row_number - 1];
            return {
                ...entry,
                row_number: matchedRow ? matchedRow.row_number : entry.row_number,
                name:
                    entry.name ||
                    (matchedRow
                        ? `${matchedRow.normalized.first_name} ${matchedRow.normalized.last_name}`.trim()
                        : "Unknown student"),
            };
        });

    const handleCommit = async () => {
        if (!currentPreview || rowsToCommit.length === 0 || committing || validating || readingFile || readingFileRef.current) {
            return;
        }
        const commitRevision = currentPreview.revision;
        const submittedRows = [...rowsToCommit];
        setCommitting(true);
        try {
            const response = await axios.post(
                "/administrators/students/batch-import/commit",
                {
                    rows: submittedRows.map((r) => r.normalized),
                    provision_portal_accounts: provisionPortals,
                    allow_duplicates: !skipDuplicates,
                },
                { headers: { Accept: "application/json" } },
            );
            if (commitRevision !== inputRevisionRef.current) {
                return;
            }
            const mappedFailed = mapCommitFailures(response.data?.failed ?? [], submittedRows);
            const failedRowNumbers = new Set(mappedFailed.map((f) => f.row_number));
            setCommittedRowNumbers((prev) => {
                const next = new Set(prev);
                for (const row of submittedRows) {
                    if (!failedRowNumbers.has(row.row_number)) {
                        next.add(row.row_number);
                    }
                }
                return next;
            });
            setCommitResult({
                created_count: Number(response.data?.created_count ?? 0),
                failed_count: Number(response.data?.failed_count ?? mappedFailed.length),
                created: response.data?.created ?? [],
                failed: mappedFailed,
            });
            toast.success(response.data?.message || "Batch import complete.");
        } catch (error: unknown) {
            if (commitRevision !== inputRevisionRef.current) {
                return;
            }
            const err = error as {
                response?: {
                    data?: {
                        message?: string;
                        created_count?: number;
                        failed_count?: number;
                        created?: Array<{ id: number; student_id: string; full_name: string }>;
                        failed?: Array<{ row_number: number; name: string; error: string }>;
                    };
                };
            };
            const responseData = err.response?.data;
            if (responseData && Array.isArray(responseData.failed)) {
                const mappedFailed = mapCommitFailures(responseData.failed, submittedRows);
                setCommitResult({
                    created_count: Number(responseData.created_count ?? 0),
                    failed_count: Number(responseData.failed_count ?? mappedFailed.length),
                    created: responseData.created ?? [],
                    failed: mappedFailed,
                });
            }
            toast.error(responseData?.message || "Failed to commit batch import.");
        } finally {
            setCommitting(false);
        }
    };

    return (
        <div className="space-y-5">
            <Card className="border-border/70">
                <CardHeader className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileSpreadsheet className="text-primary size-4" />
                            Spreadsheet Batch Intake
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Paste rows directly from Excel / Google Sheets or upload a CSV file. Every row is validated live against your{" "}
                            <span className="font-medium">{schoolProfile?.school_level_label || "school"}</span> configuration before saving.
                        </CardDescription>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={committing || readingFile}
                            className="h-8 gap-1.5 text-xs"
                            onClick={() => updateRawText(buildTemplateCsv())}
                        >
                            <Sparkles className="size-3.5" />
                            Load Sample
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            onClick={handleDownloadTemplate}
                        >
                            <Download className="size-3.5" />
                            Download CSV Template
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,.tsv,.txt"
                            className="hidden"
                            disabled={committing || readingFile}
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) void handleFileUpload(file);
                                e.currentTarget.value = "";
                            }}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={committing || readingFile}
                            className="h-8 gap-1.5 text-xs"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            {readingFile ? <Loader2 className="size-3.5 animate-spin" /> : <UploadCloud className="size-3.5" />}
                            {readingFile ? "Reading CSV..." : "Upload CSV"}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="batch-csv-input" className="text-xs">
                            Paste spreadsheet cells (CSV or Tab-separated)
                        </Label>
                        <Textarea
                            id="batch-csv-input"
                            value={rawText}
                            disabled={committing || readingFile}
                            onChange={(e) => updateRawText(e.target.value)}
                            placeholder="first_name,middle_name,last_name,gender,birth_date,email,phone,program,year_level,status"
                            rows={7}
                            className="font-mono text-xs"
                        />
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-muted-foreground text-[11px]">
                            Max 250 students per batch. Duplicate checks run automatically against name + birth date, email, and LRN.
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            disabled={validating || committing || readingFile || rawText.trim() === ""}
                            onClick={() => void handleValidate()}
                            className="gap-1.5 text-xs"
                        >
                            {validating ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                            Validate Rows
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {parseErrors.length > 0 && (
                <Alert variant="destructive">
                    <XCircle className="size-4" />
                    <AlertTitle className="text-sm font-semibold">
                        Malformed CSV / TSV Input ({parseErrors.length} issue{parseErrors.length === 1 ? "" : "s"})
                    </AlertTitle>
                    <AlertDescription className="mt-2 space-y-1 text-xs">
                        {parseErrors.map((err, idx) => (
                            <p key={`${err.row_number}-${idx}`}>
                                <span className="font-mono font-semibold">
                                    {err.row_number === 0 ? "Header" : `Row ${err.row_number}`}:
                                </span>{" "}
                                {err.message}
                            </p>
                        ))}
                    </AlertDescription>
                </Alert>
            )}

            {currentPreview && (
                <Card className="border-border/70">
                    <CardHeader className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle className="text-sm font-semibold">Validation Preview</CardTitle>
                            <Badge variant="secondary" className="font-mono text-xs tabular-nums">
                                {currentPreview.summary.total} Total
                            </Badge>
                            <Badge
                                variant="outline"
                                className="border-emerald-500/35 bg-emerald-500/10 font-mono text-xs text-emerald-700 tabular-nums dark:text-emerald-300"
                            >
                                {currentPreview.summary.valid} Valid
                            </Badge>
                            {currentPreview.summary.errors > 0 && (
                                <Badge
                                    variant="outline"
                                    className="border-rose-500/35 bg-rose-500/10 font-mono text-xs text-rose-700 tabular-nums dark:text-rose-300"
                                >
                                    {currentPreview.summary.errors} Errors
                                </Badge>
                            )}
                            {currentPreview.summary.duplicates > 0 && (
                                <Badge
                                    variant="outline"
                                    className="border-amber-500/35 bg-amber-500/10 font-mono text-xs text-amber-700 tabular-nums dark:text-amber-300"
                                >
                                    {currentPreview.summary.duplicates} Possible Duplicates
                                </Badge>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-1.5 text-xs">
                                <Checkbox
                                    checked={skipDuplicates}
                                    disabled={committing || readingFile}
                                    onCheckedChange={(checked) => setSkipDuplicates(Boolean(checked))}
                                />
                                <span>Skip potential duplicates</span>
                            </label>
                            <label className="flex items-center gap-1.5 text-xs">
                                <Checkbox
                                    checked={provisionPortals}
                                    disabled={committing || readingFile}
                                    onCheckedChange={(checked) => setProvisionPortals(Boolean(checked))}
                                />
                                <span>Provision portal accounts</span>
                            </label>
                            <Button
                                type="button"
                                size="sm"
                                disabled={committing || validating || readingFile || rowsToCommit.length === 0}
                                onClick={() => void handleCommit()}
                                className="gap-1.5 text-xs"
                            >
                                {committing ? <Loader2 className="size-3.5 animate-spin" /> : <UploadCloud className="size-3.5" />}
                                Import {rowsToCommit.length} Student{rowsToCommit.length === 1 ? "" : "s"}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/40">
                                        <TableHead className="w-12 text-xs">#</TableHead>
                                        <TableHead className="text-xs">Student</TableHead>
                                        <TableHead className="text-xs">Program &amp; Level</TableHead>
                                        <TableHead className="text-xs">Contact</TableHead>
                                        <TableHead className="text-xs">Validation Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {currentPreview.rows.map((row) => {
                                        const isImported = committedRowNumbers.has(row.row_number);
                                        return (
                                            <TableRow key={row.row_number}>
                                                <TableCell className="font-mono text-xs">{row.row_number}</TableCell>
                                                <TableCell className="text-xs">
                                                    <p className="font-medium">
                                                        {row.normalized.first_name} {row.normalized.last_name}
                                                    </p>
                                                    <p className="text-muted-foreground text-[11px]">
                                                        {row.normalized.gender} · {row.normalized.birth_date || "No DOB"}
                                                        {row.normalized.lrn ? ` · LRN ${row.normalized.lrn}` : ""}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    <span className="font-medium">{row.normalized.program_label || "Default"}</span> · Level{" "}
                                                    {row.normalized.academic_year}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-xs">
                                                    {row.normalized.email || row.normalized.phone || "—"}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {isImported ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 gap-1 text-[10px]"
                                                        >
                                                            <CheckCircle2 className="size-3" />
                                                            Imported
                                                        </Badge>
                                                    ) : row.valid ? (
                                                        <div className="space-y-1">
                                                            <Badge
                                                                variant="outline"
                                                                className="border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 gap-1 text-[10px]"
                                                            >
                                                                <CheckCircle2 className="size-3" />
                                                                Ready
                                                            </Badge>
                                                            {row.warnings.map((w, idx) => (
                                                                <p
                                                                    key={idx}
                                                                    className="flex items-center gap-1 text-[11px] text-amber-700 dark:text-amber-300"
                                                                >
                                                                    <AlertTriangle className="size-3 shrink-0" />
                                                                    {w}
                                                                </p>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <div className="space-y-1">
                                                            {row.errors.map((e, idx) => (
                                                                <p key={idx} className="text-destructive flex items-center gap-1 text-[11px]">
                                                                    <XCircle className="size-3 shrink-0" />
                                                                    {e}
                                                                </p>
                                                            ))}
                                                        </div>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {commitResult && (
                <Alert
                    className={
                        commitResult.created_count === 0 && commitResult.failed_count > 0
                            ? "border-destructive/40 bg-destructive/10"
                            : commitResult.failed_count > 0
                              ? "border-amber-500/40 bg-amber-500/10"
                              : "border-emerald-500/35 bg-emerald-500/10"
                    }
                >
                    {commitResult.created_count === 0 && commitResult.failed_count > 0 ? (
                        <XCircle className="text-destructive size-4" />
                    ) : commitResult.failed_count > 0 ? (
                        <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400" />
                    ) : (
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                    )}
                    <AlertTitle className="text-sm font-semibold">
                        Batch Import Completed — {commitResult.created_count} Created
                        {commitResult.failed_count > 0 ? `, ${commitResult.failed_count} Failed` : ""}
                    </AlertTitle>
                    <AlertDescription className="mt-2 space-y-2.5 text-xs">
                        {commitResult.created.length > 0 && (
                            <div className="flex flex-wrap gap-1.5">
                                {commitResult.created.slice(0, 12).map((s) => (
                                    <Link
                                        key={s.id}
                                        href={`/administrators/students/${s.id}`}
                                        className="border-border bg-background hover:border-primary rounded-md border px-2 py-1 font-mono text-[11px]"
                                    >
                                        {s.student_id} · {s.full_name}
                                    </Link>
                                ))}
                            </div>
                        )}
                        {commitResult.failed.length > 0 && (
                            <div className="border-destructive/30 bg-background/80 space-y-1.5 rounded-lg border p-2.5">
                                <p className="text-destructive text-[11px] font-semibold">
                                    Failed Rows ({commitResult.failed.length})
                                </p>
                                <ul className="space-y-1">
                                    {commitResult.failed.map((failure, idx) => (
                                        <li
                                            key={`${failure.row_number}-${idx}`}
                                            className="flex flex-wrap items-baseline gap-x-1.5 text-[11px]"
                                        >
                                            <span className="font-mono font-semibold">Row #{failure.row_number}</span>
                                            <span className="font-medium">{failure.name || "Unnamed student"}</span>
                                            <span className="text-destructive">— {failure.error}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="pt-1">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs"
                                onClick={() => router.visit("/administrators/students")}
                            >
                                Return to Student Directory
                            </Button>
                        </div>
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}
