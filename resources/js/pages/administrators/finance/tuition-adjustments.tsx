import { resolve, storeBatch } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowRight,
    Check,
    CheckCircle2,
    ClipboardPaste,
    Columns3,
    FileSpreadsheet,
    LayoutList,
    Loader2,
    Mail,
    RefreshCw,
    Search,
    Send,
    Settings2,
    UserRound,
} from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

type Installment = { term: "prelim" | "midterm" | "finals"; amount: number; percentage?: number; source?: string };
type TuitionRow = {
    enrollment_id: number;
    tuition_id: number;
    student_id: number;
    student_number: string;
    student_name: string;
    student_type: string;
    course: string;
    school_year: string;
    semester: number;
    academic_year: number;
    lecture: number;
    laboratory: number;
    miscellaneous: number;
    modular_or_other: number;
    additional_fees: number;
    assessment_adjustment: number;
    discount: number;
    required_downpayment: number;
    total_fees: number;
    paid: number;
    signed_balance: number;
    balance_due: number;
    credit: number;
    installments: Installment[];
    state_hash: string;
};

type DraftRow = TuitionRow & {
    clientRowId: string;
    original: TuitionRow;
    status: "unchanged" | "changed" | "recorded" | "duplicate" | "rejected";
    message?: string;
};

type ScheduleProfile = {
    enabled: boolean;
    percentages: { prelim: number; midterm: number; finals: number };
    rounding_increment: number;
    rounding_mode: "nearest" | "down" | "up";
};

type Props = {
    user: User;
    rows: TuitionRow[];
    filters: { school_year: string; semester: number };
    school_years: Record<string, string> | string[];
    semesters: Record<string, string>;
    courses: Array<{ id: number; code: string; title: string }>;
    student_types: Array<{ value: string; label: string }>;
    schedule_settings: { profiles: Record<string, ScheduleProfile> };
    workspace_layout: "inspector" | "staged";
    can_manage: boolean;
};

const money = (value: number) => new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(value) || 0);
const centsEqual = (left: number, right: number) => Math.abs(Number(left) - Number(right)) < 0.009;
const termAmount = (row: DraftRow, term: Installment["term"]) => Number(row.installments.find((item) => item.term === term)?.amount ?? 0);

function toDraft(row: TuitionRow): DraftRow {
    return { ...row, clientRowId: crypto.randomUUID(), original: structuredClone(row), status: "unchanged" };
}

function roundTo(value: number, increment: number, mode: ScheduleProfile["rounding_mode"]) {
    const scaled = value / Math.max(0.01, increment);
    const rounded = mode === "down" ? Math.floor(scaled) : mode === "up" ? Math.ceil(scaled) : Math.round(scaled);
    return rounded * increment;
}

function generateSchedule(balance: number, profile: ScheduleProfile): Installment[] {
    const due = Math.max(0, Number(balance));
    if (!profile.enabled || due <= 0) {
        return [
            { term: "prelim", amount: 0, percentage: profile.percentages.prelim, source: "generated" },
            { term: "midterm", amount: 0, percentage: profile.percentages.midterm, source: "generated" },
            { term: "finals", amount: due, percentage: profile.percentages.finals, source: "generated" },
        ];
    }
    const prelim = roundTo((due * profile.percentages.prelim) / 100, profile.rounding_increment, profile.rounding_mode);
    const midterm = Math.min(
        roundTo((due * profile.percentages.midterm) / 100, profile.rounding_increment, profile.rounding_mode),
        Math.max(0, due - prelim),
    );
    return [
        { term: "prelim", amount: prelim, percentage: profile.percentages.prelim, source: "generated" },
        { term: "midterm", amount: midterm, percentage: profile.percentages.midterm, source: "generated" },
        { term: "finals", amount: Number((due - prelim - midterm).toFixed(2)), percentage: profile.percentages.finals, source: "generated" },
    ];
}

export default function TuitionAdjustmentsPage(props: Props) {
    const [drafts, setDrafts] = useState<DraftRow[]>(() => props.rows.map(toDraft));
    const [selectedId, setSelectedId] = useState<number | null>(props.rows[0]?.enrollment_id ?? null);
    const [layout, setLayout] = useState<"inspector" | "staged">(props.workspace_layout === "staged" ? "staged" : "inspector");
    const [step, setStep] = useState<1 | 2 | 3>(props.rows.length > 0 ? 2 : 1);
    const [search, setSearch] = useState("");
    const [course, setCourse] = useState("all");
    const [studentType, setStudentType] = useState("all");
    const [statusFilter, setStatusFilter] = useState("all");
    const [reason, setReason] = useState("");
    const [pasteOpen, setPasteOpen] = useState(false);
    const [pasteText, setPasteText] = useState("");
    const [resolving, setResolving] = useState(false);
    const [applying, setApplying] = useState(false);

    const selected = drafts.find((row) => row.enrollment_id === selectedId) ?? drafts[0] ?? null;
    const filtered = useMemo(
        () =>
            drafts.filter((row) => {
                const query = search.toLowerCase();
                const rowStatus = validationStatus(row);
                return (
                    (!query || row.student_name.toLowerCase().includes(query) || row.student_number.toLowerCase().includes(query)) &&
                    (course === "all" || row.course === course) &&
                    (studentType === "all" || row.student_type === studentType) &&
                    (statusFilter === "all" || rowStatus === statusFilter)
                );
            }),
        [drafts, search, course, studentType, statusFilter],
    );
    const changed = drafts.filter((row) => row.status === "changed");
    const ready = changed.filter((row) => validationStatus(row) === "ready");
    const needsReview = changed.length - ready.length;

    function validationStatus(row: DraftRow): "ready" | "review" | "recorded" | "unchanged" {
        if (row.status === "recorded" || row.status === "duplicate") return "recorded";
        if (row.status === "unchanged") return "unchanged";
        const scheduleTotal = termAmount(row, "prelim") + termAmount(row, "midterm") + termAmount(row, "finals");
        const expectedBalance = Number((row.total_fees - row.paid).toFixed(2));
        return centsEqual(expectedBalance, row.signed_balance) && centsEqual(scheduleTotal, Math.max(0, row.signed_balance)) ? "ready" : "review";
    }

    function updateRow(enrollmentId: number, changes: Partial<DraftRow>, regenerate = false) {
        setDrafts((current) =>
            current.map((row) => {
                if (row.enrollment_id !== enrollmentId) return row;
                const next = { ...row, ...changes, status: "changed" as const, message: undefined };
                if ("total_fees" in changes || "paid" in changes) {
                    next.signed_balance = Number((next.total_fees - next.paid).toFixed(2));
                    next.balance_due = Math.max(0, next.signed_balance);
                    next.credit = Math.max(0, -next.signed_balance);
                    if (regenerate) next.installments = generateSchedule(next.balance_due, props.schedule_settings.profiles[next.student_type]);
                }
                return next;
            }),
        );
    }

    function updateInstallment(enrollmentId: number, term: Installment["term"], amount: number) {
        const row = drafts.find((item) => item.enrollment_id === enrollmentId);
        if (!row) return;
        updateRow(enrollmentId, {
            installments: row.installments.map((item) => (item.term === term ? { ...item, amount, source: "manual" } : item)),
        });
    }

    function changePeriod(field: "school_year" | "semester", value: string) {
        router.get(
            "/administrators/finance/tuition-adjustments",
            { ...props.filters, [field]: field === "semester" ? Number(value) : value },
            { preserveState: false, replace: true },
        );
    }

    async function resolvePaste() {
        const parsed = pasteText
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean)
            .map((line) => line.split(line.includes("\t") ? "\t" : ",").map((cell) => cell.trim()))
            .filter((cells) => cells.length >= 4)
            .map((cells) => ({
                client_row_id: crypto.randomUUID(),
                identifier: cells[0],
                total_fees: Number(cells[1].replace(/[₱,()]/g, "")) * (cells[1].includes("(") ? -1 : 1),
                opening_paid: Number(cells[2].replace(/[₱,()]/g, "")) * (cells[2].includes("(") ? -1 : 1),
                balance: Number(cells[3].replace(/[₱,()]/g, "")) * (cells[3].includes("(") ? -1 : 1),
                prelim: cells[4] ? Number(cells[4].replace(/[₱,()]/g, "")) : undefined,
                midterm: cells[5] ? Number(cells[5].replace(/[₱,()]/g, "")) : undefined,
                finals: cells[6] ? Number(cells[6].replace(/[₱,()]/g, "")) : undefined,
            }));
        if (parsed.length === 0) return toast.error("Paste at least one row with Student, Total, Paid/DP, and Balance.");

        setResolving(true);
        try {
            const response = await axios.post(resolve.url(), {
                school_year: props.filters.school_year,
                semester: props.filters.semester,
                rows: parsed,
            });
            const resolvedRows = response.data.rows.filter((row: { status: string }) => row.status === "resolved");
            const rejectedRows = response.data.rows.filter((row: { status: string }) => row.status === "rejected");
            setDrafts((current) => {
                const map = new Map(current.map((row) => [row.enrollment_id, row]));
                resolvedRows.forEach((result: { client_row_id: string; canonical: TuitionRow }) => {
                    const incoming = result.canonical;
                    const original = map.get(incoming.enrollment_id)?.original ?? structuredClone(incoming);
                    map.set(incoming.enrollment_id, { ...incoming, clientRowId: result.client_row_id, original, status: "changed" });
                });
                return Array.from(map.values());
            });
            setPasteOpen(false);
            setPasteText("");
            setStep(2);
            toast.success(`${resolvedRows.length} row${resolvedRows.length === 1 ? "" : "s"} matched.`, {
                description: rejectedRows.length > 0 ? `${rejectedRows.length} row(s) could not be matched.` : undefined,
            });
        } catch {
            toast.error("The pasted rows could not be resolved.");
        } finally {
            setResolving(false);
        }
    }

    async function applyRows() {
        if (!reason.trim()) return toast.error("Enter an adjustment reason before applying rows.");
        if (ready.length === 0) return toast.error("No reconciled changed rows are ready to apply.");
        setApplying(true);
        try {
            const response = await axios.post(storeBatch.url(), {
                batch_key: crypto.randomUUID(),
                source: "workspace",
                reason,
                rows: ready.map((row) => ({
                    client_row_id: row.clientRowId,
                    enrollment_id: row.enrollment_id,
                    tuition_id: row.tuition_id,
                    state_hash: row.state_hash,
                    total_fees: row.total_fees,
                    opening_paid: row.paid,
                    balance: row.signed_balance,
                    lecture: row.lecture,
                    laboratory: row.laboratory,
                    miscellaneous: row.miscellaneous,
                    discount: row.discount,
                    required_downpayment: row.required_downpayment,
                    installments: {
                        prelim: termAmount(row, "prelim"),
                        midterm: termAmount(row, "midterm"),
                        finals: termAmount(row, "finals"),
                    },
                })),
            });
            const results = new Map(response.data.rows.map((row: { client_row_id: string }) => [row.client_row_id, row]));
            setDrafts((current) =>
                current.map((row) => {
                    const result = results.get(row.clientRowId) as
                        { status: DraftRow["status"]; canonical?: TuitionRow; message?: string } | undefined;
                    if (!result) return row;
                    return result.canonical
                        ? {
                              ...result.canonical,
                              clientRowId: row.clientRowId,
                              original: structuredClone(result.canonical),
                              status: result.status,
                              message: result.message,
                          }
                        : { ...row, status: result.status, message: result.message };
                }),
            );
            const recorded = response.data.rows.filter((row: { status: string }) => row.status === "recorded").length;
            const rejected = response.data.rows.filter((row: { status: string }) => row.status === "rejected").length;
            toast.success(`${recorded} tuition adjustment${recorded === 1 ? "" : "s"} applied.`, {
                description: rejected > 0 ? `${rejected} row(s) still need attention.` : "Student notifications were queued.",
            });
        } catch {
            toast.error("The adjustment batch could not be submitted.");
        } finally {
            setApplying(false);
        }
    }

    return (
        <AdminLayout user={props.user} title="Tuition Adjustments">
            <Head title="Finance · Tuition Adjustments" />
            <div className="mx-auto w-full max-w-[1800px] space-y-4 antialiased">
                <header className="border-border/70 flex flex-col gap-4 border-b pb-4 xl:flex-row xl:items-end xl:justify-between">
                    <div className="space-y-1">
                        <p className="text-primary text-xs font-semibold tracking-[0.18em] uppercase">Finance reconciliation desk</p>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Tuition Adjustments</h1>
                        <p className="text-muted-foreground text-sm">
                            Import printed-ledger values, reconcile each enrollment, then notify students.
                        </p>
                    </div>
                    <StageTracker step={step} onChange={setStep} />
                </header>

                <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div className="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5">
                        <div className="relative sm:col-span-2">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search student or ID"
                                className="pl-9"
                            />
                        </div>
                        <Select value={props.filters.school_year} onValueChange={(value) => changePeriod("school_year", value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.values(props.school_years).map((year) => (
                                    <SelectItem key={year} value={year}>
                                        {year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={String(props.filters.semester)} onValueChange={(value) => changePeriod("semester", value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(props.semesters).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" onClick={() => setPasteOpen(true)}>
                            <ClipboardPaste className="size-4" /> Paste from Excel
                        </Button>
                    </div>
                    <div className="flex gap-2">
                        <div className="bg-muted/50 flex rounded-lg border p-1">
                            <Button size="sm" variant={layout === "inspector" ? "secondary" : "ghost"} onClick={() => setLayout("inspector")}>
                                <Columns3 className="size-4" /> Inspector
                            </Button>
                            <Button size="sm" variant={layout === "staged" ? "secondary" : "ghost"} onClick={() => setLayout("staged")}>
                                <LayoutList className="size-4" /> Staged table
                            </Button>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href="/administrators/settings#personalization">
                                <Settings2 className="size-4" />
                                <span className="sr-only">Workspace settings</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                {step === 1 ? (
                    <ImportStage onPaste={() => setPasteOpen(true)} onContinue={() => setStep(2)} count={drafts.length} />
                ) : step === 3 ? (
                    <NotifyStage
                        ready={ready}
                        reason={reason}
                        onReasonChange={setReason}
                        onBack={() => setStep(2)}
                        onApply={applyRows}
                        applying={applying}
                    />
                ) : (
                    <div className={cn("grid min-h-[620px] gap-4", layout === "inspector" && "2xl:grid-cols-[minmax(0,1fr)_380px]")}>
                        <Card className="min-w-0 overflow-hidden">
                            <CardHeader className="border-b py-3">
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <CardTitle className="text-base">Enrollment ledger</CardTitle>
                                        <CardDescription>
                                            Use Tab or click into a currency cell. All three paper totals must reconcile.
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Select value={course} onValueChange={setCourse}>
                                            <SelectTrigger className="w-36">
                                                <SelectValue placeholder="Course" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All courses</SelectItem>
                                                {Array.from(new Set(drafts.map((row) => row.course))).map((value) => (
                                                    <SelectItem key={value} value={value}>
                                                        {value}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select value={studentType} onValueChange={setStudentType}>
                                            <SelectTrigger className="w-36">
                                                <SelectValue placeholder="Type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All types</SelectItem>
                                                {props.student_types.map((type) => (
                                                    <SelectItem key={type.value} value={type.value}>
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                                            <SelectTrigger className="w-36">
                                                <SelectValue placeholder="Status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All statuses</SelectItem>
                                                <SelectItem value="ready">Ready</SelectItem>
                                                <SelectItem value="review">Needs review</SelectItem>
                                                <SelectItem value="recorded">Recorded</SelectItem>
                                                <SelectItem value="unchanged">No change</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="overflow-auto p-0">
                                <TuitionGrid
                                    rows={filtered}
                                    selectedId={selected?.enrollment_id ?? null}
                                    onSelect={setSelectedId}
                                    updateRow={updateRow}
                                    updateInstallment={updateInstallment}
                                    validationStatus={validationStatus}
                                />
                            </CardContent>
                        </Card>
                        {layout === "inspector" && selected && (
                            <EnrollmentInspector
                                row={selected}
                                profile={props.schedule_settings.profiles[selected.student_type]}
                                updateRow={updateRow}
                                updateInstallment={updateInstallment}
                            />
                        )}
                    </div>
                )}

                {step === 2 && (
                    <div className="bg-card sticky bottom-3 z-10 flex flex-col gap-3 rounded-xl border p-3 shadow-xl sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap gap-5 text-sm">
                            <Summary label="Ready" value={ready.length} tone="success" />
                            <Summary label="Needs review" value={needsReview} tone="warning" />
                            <Summary label="Changed" value={changed.length} />
                            <Summary label="Total rows" value={drafts.length} />
                        </div>
                        <Button onClick={() => setStep(3)} disabled={ready.length === 0 || !props.can_manage}>
                            Review notifications ({ready.length}) <ArrowRight className="size-4" />
                        </Button>
                    </div>
                )}
            </div>

            <Dialog open={pasteOpen} onOpenChange={setPasteOpen}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Paste tuition ledger rows</DialogTitle>
                        <DialogDescription>
                            Columns: Student ID or Name, Total Fees, Paid/DP, Balance, Prelim, Midterm, Finals. Tab-separated Excel rows work best.
                        </DialogDescription>
                    </DialogHeader>
                    <Textarea
                        value={pasteText}
                        onChange={(event) => setPasteText(event.target.value)}
                        rows={12}
                        className="font-mono text-xs"
                        placeholder={"LAMBINO, JUSTIN JADE S.\t19470\t20000\t-530\t0\t0\t-530"}
                    />
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPasteOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={resolvePaste} disabled={resolving}>
                            {resolving ? <Loader2 className="size-4 animate-spin" /> : <FileSpreadsheet className="size-4" />} Resolve rows
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

function StageTracker({ step, onChange }: { step: 1 | 2 | 3; onChange: (step: 1 | 2 | 3) => void }) {
    const steps = [
        { id: 1 as const, label: "Import" },
        { id: 2 as const, label: "Review" },
        { id: 3 as const, label: "Notify" },
    ];
    return (
        <div className="flex items-center gap-2">
            {steps.map((item, index) => (
                <div key={item.id} className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onChange(item.id)}
                        className={cn(
                            "flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors",
                            step === item.id ? "bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted",
                        )}
                    >
                        <span
                            className={cn(
                                "flex size-6 items-center justify-center rounded-full border text-xs font-semibold",
                                step === item.id && "border-primary bg-primary text-primary-foreground",
                            )}
                        >
                            {item.id}
                        </span>
                        {item.label}
                    </button>
                    {index < 2 && <span className="bg-border hidden h-px w-8 sm:block" />}
                </div>
            ))}
        </div>
    );
}

function TuitionGrid({
    rows,
    selectedId,
    onSelect,
    updateRow,
    updateInstallment,
    validationStatus,
}: {
    rows: DraftRow[];
    selectedId: number | null;
    onSelect: (id: number) => void;
    updateRow: (id: number, changes: Partial<DraftRow>, regenerate?: boolean) => void;
    updateInstallment: (id: number, term: Installment["term"], amount: number) => void;
    validationStatus: (row: DraftRow) => string;
}) {
    return (
        <Table className="min-w-[1180px]">
            <TableHeader className="bg-muted/40 sticky top-0 z-10">
                <TableRow>
                    <TableHead className="w-10">#</TableHead>
                    <TableHead className="min-w-56">Student</TableHead>
                    <TableHead>Course</TableHead>
                    {["Total fees", "Paid / DP", "Balance", "Prelim", "Midterm", "Finals"].map((label) => (
                        <TableHead key={label} className="min-w-32 text-right">
                            {label}
                        </TableHead>
                    ))}
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => {
                    const status = validationStatus(row);
                    return (
                        <TableRow
                            key={row.enrollment_id}
                            onClick={() => onSelect(row.enrollment_id)}
                            className={cn(
                                "cursor-pointer",
                                selectedId === row.enrollment_id && "bg-primary/5 outline-primary/40 outline -outline-offset-1",
                            )}
                        >
                            <TableCell className="text-muted-foreground">{index + 1}</TableCell>
                            <TableCell>
                                <p className="font-medium">{row.student_name}</p>
                                <p className="text-muted-foreground text-xs">{row.student_number}</p>
                            </TableCell>
                            <TableCell>{row.course}</TableCell>
                            <MoneyCell value={row.total_fees} onChange={(value) => updateRow(row.enrollment_id, { total_fees: value }, true)} />
                            <MoneyCell value={row.paid} onChange={(value) => updateRow(row.enrollment_id, { paid: value }, true)} />
                            <MoneyCell
                                value={row.signed_balance}
                                onChange={(value) =>
                                    updateRow(row.enrollment_id, {
                                        signed_balance: value,
                                        balance_due: Math.max(0, value),
                                        credit: Math.max(0, -value),
                                    })
                                }
                            />
                            <MoneyCell
                                value={termAmount(row, "prelim")}
                                onChange={(value) => updateInstallment(row.enrollment_id, "prelim", value)}
                            />
                            <MoneyCell
                                value={termAmount(row, "midterm")}
                                onChange={(value) => updateInstallment(row.enrollment_id, "midterm", value)}
                            />
                            <MoneyCell
                                value={termAmount(row, "finals")}
                                onChange={(value) => updateInstallment(row.enrollment_id, "finals", value)}
                            />
                            <TableCell>
                                <StatusBadge status={status} message={row.message} />
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}

function MoneyCell({ value, onChange }: { value: number; onChange: (value: number) => void }) {
    return (
        <TableCell className="p-1.5">
            <Input
                type="number"
                step="0.01"
                value={Number(value)}
                onClick={(event) => event.stopPropagation()}
                onChange={(event) => onChange(Number(event.target.value))}
                className="hover:border-border focus-visible:border-primary h-9 border-transparent bg-transparent text-right tabular-nums focus-visible:ring-2"
            />
        </TableCell>
    );
}

function StatusBadge({ status, message }: { status: string; message?: string }) {
    if (status === "ready")
        return (
            <Badge className="bg-emerald-600/15 text-emerald-700 hover:bg-emerald-600/15 dark:text-emerald-400">
                <Check className="size-3" /> Ready
            </Badge>
        );
    if (status === "review")
        return (
            <Badge className="bg-amber-500/15 text-amber-700 hover:bg-amber-500/15 dark:text-amber-400" title={message}>
                <AlertTriangle className="size-3" /> Needs review
            </Badge>
        );
    if (status === "recorded")
        return (
            <Badge variant="secondary">
                <CheckCircle2 className="size-3" /> Recorded
            </Badge>
        );
    return <Badge variant="outline">No change</Badge>;
}

function EnrollmentInspector({
    row,
    profile,
    updateRow,
    updateInstallment,
}: {
    row: DraftRow;
    profile: ScheduleProfile;
    updateRow: (id: number, changes: Partial<DraftRow>, regenerate?: boolean) => void;
    updateInstallment: (id: number, term: Installment["term"], amount: number) => void;
}) {
    return (
        <Card className="h-fit overflow-hidden 2xl:sticky 2xl:top-4">
            <CardHeader className="border-b">
                <div className="flex items-start gap-3">
                    <div className="bg-primary text-primary-foreground flex size-10 items-center justify-center rounded-full font-semibold">
                        {row.student_name
                            .split(/\s+/)
                            .slice(0, 2)
                            .map((part) => part[0])
                            .join("")}
                    </div>
                    <div>
                        <CardTitle className="text-base">{row.student_name}</CardTitle>
                        <CardDescription>
                            {row.student_number} · {row.course} · Year {row.academic_year}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-5 p-4">
                <section>
                    <p className="text-muted-foreground mb-2 text-xs font-semibold tracking-wider uppercase">Before → current draft</p>
                    <div className="grid grid-cols-[1fr_auto_auto] gap-x-3 gap-y-1.5 text-sm">
                        <span>Total fees</span>
                        <span className="text-muted-foreground tabular-nums">{money(row.original.total_fees)}</span>
                        <strong className="tabular-nums">{money(row.total_fees)}</strong>
                        <span>Paid / opening</span>
                        <span className="text-muted-foreground tabular-nums">{money(row.original.paid)}</span>
                        <strong className="tabular-nums">{money(row.paid)}</strong>
                        <span>{row.credit > 0 ? "Credit" : "Balance"}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {money(row.original.credit > 0 ? row.original.credit : row.original.balance_due)}
                        </span>
                        <strong className={cn("tabular-nums", row.credit > 0 && "text-emerald-600")}>
                            {money(row.credit > 0 ? row.credit : row.balance_due)}
                        </strong>
                    </div>
                </section>
                <section className="space-y-3 border-t pt-4">
                    <p className="text-sm font-semibold">Paper ledger values</p>
                    {(
                        [
                            ["total_fees", "Total fees"],
                            ["paid", "Paid / opening DP"],
                            ["signed_balance", "Balance / credit"],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid grid-cols-[1fr_140px] items-center gap-3">
                            <Label className="text-sm font-normal">{label}</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={row[field]}
                                onChange={(event) => {
                                    const value = Number(event.target.value);
                                    if (field === "signed_balance") {
                                        updateRow(row.enrollment_id, {
                                            signed_balance: value,
                                            balance_due: Math.max(0, value),
                                            credit: Math.max(0, -value),
                                        });
                                    } else {
                                        updateRow(row.enrollment_id, { [field]: value }, true);
                                    }
                                }}
                                className="text-right tabular-nums"
                            />
                        </div>
                    ))}
                </section>
                <section className="space-y-3 border-t pt-4">
                    <p className="text-sm font-semibold">Fee breakdown</p>
                    {(
                        [
                            ["lecture", "Lecture"],
                            ["laboratory", "Laboratory"],
                            ["miscellaneous", "Miscellaneous"],
                            ["discount", "Discount (%)"],
                            ["required_downpayment", "Required downpayment"],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid grid-cols-[1fr_140px] items-center gap-3">
                            <Label className="text-sm font-normal">{label}</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={row[field]}
                                onChange={(event) => updateRow(row.enrollment_id, { [field]: Number(event.target.value) })}
                                className="text-right tabular-nums"
                            />
                        </div>
                    ))}
                    <div className="text-muted-foreground flex justify-between text-xs">
                        <span>Modular / other tuition</span>
                        <span>{money(row.modular_or_other)}</span>
                    </div>
                    <div className="text-muted-foreground flex justify-between text-xs">
                        <span>Enrollment additional fees</span>
                        <span>{money(row.additional_fees)}</span>
                    </div>
                    <div className="flex justify-between border-t pt-2 text-sm">
                        <span>Assessment adjustment</span>
                        <span className="font-medium tabular-nums">
                            {money(
                                row.total_fees -
                                    (row.lecture + row.laboratory + row.modular_or_other + row.miscellaneous + row.additional_fees) *
                                        (1 - row.discount / 100),
                            )}
                        </span>
                    </div>
                </section>
                <section className="space-y-3 border-t pt-4">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-semibold">Installment schedule</p>
                            <p className="text-muted-foreground text-xs">
                                {profile.percentages.prelim}% / {profile.percentages.midterm}% / remainder · rounded to{" "}
                                {money(profile.rounding_increment)}
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => updateRow(row.enrollment_id, { installments: generateSchedule(row.balance_due, profile) })}
                        >
                            <RefreshCw className="size-3.5" /> Regenerate
                        </Button>
                    </div>
                    {(["prelim", "midterm", "finals"] as const).map((term) => (
                        <div key={term} className="grid grid-cols-[1fr_140px] items-center gap-3">
                            <Label className="text-sm font-normal capitalize">{term}</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={termAmount(row, term)}
                                onChange={(event) => updateInstallment(row.enrollment_id, term, Number(event.target.value))}
                                className="text-right tabular-nums"
                            />
                        </div>
                    ))}
                </section>
                <Alert>
                    <Send className="size-4" />
                    <AlertTitle>Notification preview</AlertTitle>
                    <AlertDescription>
                        After saving, the student receives one in-app notification and one email with the before/after totals and schedule.
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
    );
}

function ImportStage({ onPaste, onContinue, count }: { onPaste: () => void; onContinue: () => void; count: number }) {
    return (
        <Card className="border-dashed">
            <CardContent className="flex min-h-[420px] flex-col items-center justify-center gap-5 text-center">
                <div className="bg-primary/10 text-primary rounded-2xl p-5">
                    <FileSpreadsheet className="size-10" />
                </div>
                <div>
                    <h2 className="text-xl font-semibold">Bring in the printed ledger</h2>
                    <p className="text-muted-foreground mt-2 max-w-xl text-sm">
                        Paste tabular rows from Excel, or continue with the enrollments already loaded for this academic period.
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-2">
                    <Button onClick={onPaste}>
                        <ClipboardPaste className="size-4" /> Paste rows
                    </Button>
                    <Button variant="outline" onClick={onContinue} disabled={count === 0}>
                        Review {count} loaded rows
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function NotifyStage({
    ready,
    reason,
    onReasonChange,
    onBack,
    onApply,
    applying,
}: {
    ready: DraftRow[];
    reason: string;
    onReasonChange: (value: string) => void;
    onBack: () => void;
    onApply: () => void;
    applying: boolean;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
            <Card>
                <CardHeader>
                    <CardTitle>Notification review</CardTitle>
                    <CardDescription>One message is prepared for each reconciled enrollment.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {ready.map((row) => (
                        <div key={row.enrollment_id} className="flex items-center justify-between gap-4 rounded-lg border p-3">
                            <div>
                                <p className="font-medium">{row.student_name}</p>
                                <p className="text-muted-foreground text-xs">
                                    {row.school_year} · Semester {row.semester} · {row.course}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="font-semibold tabular-nums">{money(row.total_fees)}</p>
                                <div className="text-muted-foreground mt-1 flex items-center justify-end gap-2 text-xs">
                                    <Badge variant="outline">
                                        <UserRound className="size-3" /> In-app
                                    </Badge>
                                    <Badge variant="outline">
                                        <Mail className="size-3" /> Email
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
            <Card className="h-fit lg:sticky lg:top-4">
                <CardHeader>
                    <CardTitle className="text-base">Confirm adjustment</CardTitle>
                    <CardDescription>The reason appears in the audit trail and student message.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="adjustment-reason">Adjustment reason</Label>
                        <Textarea
                            id="adjustment-reason"
                            value={reason}
                            onChange={(event) => onReasonChange(event.target.value)}
                            rows={5}
                            placeholder="Example: Reconciled against the signed 2026–2027 tuition ledger."
                        />
                    </div>
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>
                            {ready.length} row{ready.length === 1 ? "" : "s"} ready
                        </AlertTitle>
                        <AlertDescription>Only these reconciled rows will be applied. Other rows remain unchanged.</AlertDescription>
                    </Alert>
                    <div className="grid grid-cols-2 gap-2">
                        <Button variant="outline" onClick={onBack}>
                            Back to review
                        </Button>
                        <Button onClick={onApply} disabled={applying || !reason.trim()}>
                            {applying ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />} Apply & notify
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function Summary({ label, value, tone }: { label: string; value: number; tone?: "success" | "warning" }) {
    return (
        <div className="flex items-center gap-2">
            <span
                className={cn(
                    "bg-muted-foreground size-2 rounded-full",
                    tone === "success" && "bg-emerald-500",
                    tone === "warning" && "bg-amber-500",
                )}
            />
            <strong className="tabular-nums">{value}</strong>
            <span className="text-muted-foreground">{label}</span>
        </div>
    );
}
