import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import axios from "axios";
import { CheckCircle2, ClipboardPaste, Loader2, Plus, ReceiptText, RotateCcw, Trash2, TriangleAlert } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { formatCurrency, makeClientRowId, type PaymentMethodOption } from "./payment-workspace-types";

type OpenTuition = {
    id: number;
    enrollment_id: number;
    school_year: string;
    semester: number;
    balance: number;
};

type LedgerStudent = {
    id: number;
    student_id: number | string;
    full_name: string;
    open_tuitions: OpenTuition[];
};

type LedgerRow = {
    client_row_id: string;
    student_identifier: string;
    student: LedgerStudent | null;
    candidates: LedgerStudent[];
    tuition_id: string;
    amount: string;
    payment_method: string;
    reference_number: string;
    remarks: string;
    error: string | null;
};

type LedgerResult = {
    client_row_id: string;
    status: "recorded" | "duplicate";
    transaction_id?: number;
    receipt_url?: string;
    amount: number;
    student_name: string;
};

type ResolveResponse = {
    matches: Array<{ identifier: string; students: LedgerStudent[] }>;
};

type BatchResponse = {
    results: Array<{
        client_row_id: string;
        status: "recorded" | "duplicate" | "rejected";
        transaction_id?: number;
        receipt_url?: string;
        errors?: string[] | Record<string, string[]>;
    }>;
};

type SpreadsheetPaymentWorkspaceProps = {
    batchUrl: string;
    currency: string;
    defaultPaymentMethod: string;
    paymentMethods: PaymentMethodOption[];
    resolveUrl: string;
};

const createBatchId = (): string => makeClientRowId();

const createRow = (paymentMethod: string): LedgerRow => ({
    client_row_id: makeClientRowId(),
    student_identifier: "",
    student: null,
    candidates: [],
    tuition_id: "",
    amount: "",
    payment_method: paymentMethod,
    reference_number: "",
    remarks: "",
    error: null,
});

const errorText = (errors?: string[] | Record<string, string[]>): string => {
    if (!errors) return "This payment could not be recorded.";
    if (Array.isArray(errors)) return errors.join(" ");

    return Object.values(errors).flat().join(" ");
};

const tuitionLabel = (tuition: OpenTuition) => `SY ${tuition.school_year} · ${tuition.semester === 1 ? "1st" : "2nd"} semester`;

export function SpreadsheetPaymentWorkspace({ batchUrl, currency, defaultPaymentMethod, paymentMethods, resolveUrl }: SpreadsheetPaymentWorkspaceProps) {
    const [batchId, setBatchId] = useState(createBatchId);
    const [rows, setRows] = useState<LedgerRow[]>(() => Array.from({ length: 8 }, () => createRow(defaultPaymentMethod)));
    const [resolving, setResolving] = useState(false);
    const [recording, setRecording] = useState(false);
    const [results, setResults] = useState<LedgerResult[]>([]);

    const updateRow = (clientRowId: string, change: Partial<LedgerRow>) => {
        setRows((current) => current.map((row) => row.client_row_id === clientRowId ? { ...row, ...change } : row));
    };

    const resolveRows = async (sourceRows = rows) => {
        const identifiers = [...new Set(sourceRows.map((row) => row.student_identifier.trim()).filter(Boolean))];
        if (identifiers.length === 0) {
            toast.message("Paste or enter at least one student ID first.");
            return;
        }

        setResolving(true);
        try {
            const { data } = await axios.post<ResolveResponse>(resolveUrl, { student_identifiers: identifiers });
            const matches = new Map(data.matches.map((match) => [match.identifier.trim().toLocaleLowerCase(), match.students]));
            setRows(sourceRows.map((row) => {
                const key = row.student_identifier.trim().toLocaleLowerCase();
                if (!key) return row;
                const candidates = matches.get(key) ?? [];
                if (candidates.length === 0) return { ...row, student: null, candidates: [], tuition_id: "", error: "No student matched this identifier." };
                if (candidates.length > 1) return { ...row, student: null, candidates, tuition_id: "", error: "Choose the matching student." };

                const student = candidates[0];
                const onlyTuition = student.open_tuitions.length === 1 ? student.open_tuitions[0] : null;
                return {
                    ...row,
                    student,
                    candidates,
                    tuition_id: onlyTuition ? String(onlyTuition.id) : "",
                    error: student.open_tuitions.length === 0 ? "This student has no open tuition balance." : onlyTuition ? null : "Choose which open balance to pay.",
                };
            }));
            toast.success("Student identifiers resolved.");
        } catch {
            toast.error("Student lookup failed. Check your connection and retry.");
        } finally {
            setResolving(false);
        }
    };

    const chooseStudent = (row: LedgerRow, studentId: string) => {
        const student = row.candidates.find((candidate) => String(candidate.id) === studentId) ?? null;
        const onlyTuition = student?.open_tuitions.length === 1 ? student.open_tuitions[0] : null;
        updateRow(row.client_row_id, {
            student,
            tuition_id: onlyTuition ? String(onlyTuition.id) : "",
            error: !student ? "Choose the matching student." : student.open_tuitions.length === 0 ? "This student has no open tuition balance." : onlyTuition ? null : "Choose which open balance to pay.",
        });
    };

    const parsePaste = (clipboardText: string): LedgerRow[] => clipboardText
        .replace(/\r/g, "")
        .split("\n")
        .filter((line) => line.trim().length > 0)
        .map((line) => {
            const [student_identifier = "", , amount = "", payment_method = defaultPaymentMethod, reference_number = "", remarks = ""] = line.split("\t");
            return {
                ...createRow(payment_method.trim() || defaultPaymentMethod),
                student_identifier: student_identifier.trim(),
                amount: amount.trim(),
                reference_number: reference_number.trim(),
                remarks: remarks.trim(),
            };
        });

    const handlePaste = (event: React.ClipboardEvent<HTMLInputElement>, clientRowId: string) => {
        const pastedRows = parsePaste(event.clipboardData.getData("text/plain"));
        if (pastedRows.length <= 1) return;
        event.preventDefault();
        const index = rows.findIndex((row) => row.client_row_id === clientRowId);
        const next = [...rows.slice(0, index), ...pastedRows, ...rows.slice(index + 1)];
        setRows(next);
        void resolveRows(next);
    };

    const selectedTuition = (row: LedgerRow) => row.student?.open_tuitions.find((tuition) => String(tuition.id) === row.tuition_id) ?? null;
    const validationMessage = (row: LedgerRow): string | null => {
        if (row.error) return row.error;
        if (!row.student) return row.error || "Resolve and choose a student.";
        const tuition = selectedTuition(row);
        if (!tuition) return row.error || "Choose an open tuition balance.";
        const amount = Number.parseFloat(row.amount);
        if (!Number.isFinite(amount) || amount <= 0) return "Enter a positive amount.";
        if (amount > tuition.balance) return "Amount exceeds the current balance.";
        return null;
    };

    const readyRows = rows.filter((row) => row.student_identifier.trim().length > 0 && !validationMessage(row));
    const failedRows = rows.filter((row) => row.student_identifier.trim().length > 0 && Boolean(validationMessage(row))).length;
    const readyTotal = readyRows.reduce((total, row) => total + (Number.parseFloat(row.amount) || 0), 0);
    const recordedTotal = results.reduce((total, result) => total + result.amount, 0);

    const recordValidRows = async () => {
        const candidates = rows.filter((row) => row.student_identifier.trim().length > 0);
        const nextRows = rows.map((row) => ({ ...row, error: row.student_identifier.trim().length > 0 ? validationMessage(row) : null }));
        setRows(nextRows);
        const valid = nextRows.filter((row) => row.student_identifier.trim().length > 0 && !row.error && row.student && selectedTuition(row));
        if (valid.length === 0) {
            toast.error(candidates.length === 0 ? "Add a payment row first." : "Resolve the highlighted rows before recording.");
            return;
        }

        setRecording(true);
        try {
            const { data } = await axios.post<BatchResponse>(batchUrl, {
                batch_id: batchId,
                rows: valid.map((row) => ({
                    client_row_id: row.client_row_id,
                    student_id: row.student?.id,
                    payment_method: row.payment_method,
                    reference_number: row.reference_number || null,
                    remarks: row.remarks || null,
                    items: [{ type: "tuition", tuition_id: Number(row.tuition_id), amount: Number.parseFloat(row.amount) }],
                })),
            });
            const submitted = new Map(valid.map((row) => [row.client_row_id, row]));
            const completed = data.results.filter((result) => result.status === "recorded" || result.status === "duplicate");
            const rejected = new Map(data.results.filter((result) => result.status === "rejected").map((result) => [result.client_row_id, errorText(result.errors)]));

            setResults((current) => [
                ...completed.map((result) => {
                    const row = submitted.get(result.client_row_id)!;
                    return { client_row_id: result.client_row_id, status: result.status as "recorded" | "duplicate", transaction_id: result.transaction_id, receipt_url: result.receipt_url, amount: Number.parseFloat(row.amount) || 0, student_name: row.student?.full_name || row.student_identifier };
                }),
                ...current,
            ]);
            setRows((current) => current
                .filter((row) => !completed.some((result) => result.client_row_id === row.client_row_id))
                .map((row) => rejected.has(row.client_row_id) ? { ...row, error: rejected.get(row.client_row_id)! } : row));
            toast.success(`${completed.length} ${completed.length === 1 ? "payment" : "payments"} recorded. ${rejected.size > 0 ? `${rejected.size} row(s) need attention.` : ""}`);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) toast.error("The batch format is invalid. Correct the highlighted rows and retry.");
            else toast.error("Payments could not be recorded. No new receipt was assumed recorded.");
        } finally {
            setRecording(false);
        }
    };

    const addRows = (count = 1) => setRows((current) => [...current, ...Array.from({ length: count }, () => createRow(defaultPaymentMethod))]);
    const clearSession = () => {
        setBatchId(createBatchId());
        setRows(Array.from({ length: 8 }, () => createRow(defaultPaymentMethod)));
        setResults([]);
    };

    return (
        <div className="space-y-4 pb-10">
            <Card className="overflow-hidden rounded-xl shadow-xs">
                <CardHeader className="border-b bg-muted/25"><div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"><div><CardTitle className="flex items-center gap-2 text-lg"><ClipboardPaste className="size-5 text-primary" />Paste-friendly payment ledger</CardTitle><CardDescription>Paste tab-separated rows from Excel. Each row is a separate receipt; safe rows record while rejected rows remain here to fix.</CardDescription></div><div className="flex flex-wrap gap-2"><Button type="button" variant="outline" onClick={() => void resolveRows()} disabled={resolving}>{resolving ? <Loader2 className="mr-2 size-4 animate-spin" /> : <ClipboardPaste className="mr-2 size-4" />}Resolve students</Button><Button type="button" variant="outline" onClick={clearSession}><RotateCcw className="mr-2 size-4" />New ledger</Button></div></div></CardHeader>
                <CardContent className="space-y-4 p-5"><div className="rounded-xl border border-dashed bg-muted/20 p-4"><p className="font-medium">Excel paste format</p><p className="text-muted-foreground mt-1 text-sm">Student ID, allocation (ignored until you choose a balance), amount, method, reference, remarks. Paste directly into the first Student cell; use Tab and Enter to keep moving through the grid.</p></div><div className="flex flex-wrap items-center gap-2 text-sm"><Badge variant="secondary">{readyRows.length} ready</Badge><Badge variant={failedRows > 0 ? "destructive" : "secondary"}>{failedRows} need attention</Badge><span className="text-muted-foreground">Ready total {formatCurrency(readyTotal, currency)}</span></div></CardContent>
            </Card>

            <Card className="overflow-hidden rounded-xl shadow-xs"><CardContent className="overflow-x-auto p-0"><table className="w-full min-w-[1180px] border-collapse text-sm"><thead className="bg-muted/35 text-muted-foreground text-left text-xs font-semibold tracking-wide uppercase"><tr><th className="w-12 px-3 py-3">#</th><th className="min-w-48 px-3 py-3">Student</th><th className="min-w-64 px-3 py-3">Allocation / charge</th><th className="w-36 px-3 py-3">Amount</th><th className="min-w-36 px-3 py-3">Method</th><th className="min-w-44 px-3 py-3">Reference</th><th className="min-w-52 px-3 py-3">Remarks</th><th className="min-w-52 px-3 py-3">Status</th><th className="w-12 px-3 py-3"><span className="sr-only">Remove</span></th></tr></thead><tbody>{rows.map((row, index) => {
                const tuition = selectedTuition(row);
                const message = validationMessage(row);
                return <tr key={row.client_row_id} className={cn("workspace-density-row border-t align-top", message && row.student_identifier.trim() ? "bg-destructive/[0.03]" : "") }><td className="px-3 py-3 text-muted-foreground tabular-nums">{index + 1}</td><td className="px-3 py-2"><div className="space-y-2"><Input aria-label={`Student identifier row ${index + 1}`} value={row.student_identifier} onPaste={(event) => handlePaste(event, row.client_row_id)} onChange={(event) => updateRow(row.client_row_id, { student_identifier: event.target.value, student: null, candidates: [], tuition_id: "", error: null })} placeholder="Student ID" className="h-9" />{row.candidates.length > 1 && !row.student && <Select onValueChange={(value) => chooseStudent(row, value)}><SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Choose student" /></SelectTrigger><SelectContent>{row.candidates.map((candidate) => <SelectItem key={candidate.id} value={String(candidate.id)}>{candidate.student_id} · {candidate.full_name}</SelectItem>)}</SelectContent></Select>}{row.student && <p className="text-muted-foreground truncate text-xs">{row.student.student_id} · {row.student.full_name}</p>}</div></td><td className="px-3 py-2"><Select value={row.tuition_id} onValueChange={(value) => updateRow(row.client_row_id, { tuition_id: value, error: null })} disabled={!row.student || row.student.open_tuitions.length === 0}><SelectTrigger className="h-9"><SelectValue placeholder={row.student ? "Choose balance" : "Resolve student first"} /></SelectTrigger><SelectContent>{row.student?.open_tuitions.map((openTuition) => <SelectItem key={openTuition.id} value={String(openTuition.id)}>{tuitionLabel(openTuition)} · {formatCurrency(openTuition.balance, currency)}</SelectItem>)}</SelectContent></Select>{tuition && <p className="text-muted-foreground mt-1 text-xs">Available {formatCurrency(tuition.balance, currency)}</p>}</td><td className="px-3 py-2"><Input aria-label={`Amount row ${index + 1}`} type="number" min="0" max={tuition?.balance} step="0.01" inputMode="decimal" value={row.amount} onChange={(event) => updateRow(row.client_row_id, { amount: event.target.value, error: null })} placeholder="0.00" className="h-9 text-right font-medium tabular-nums" /></td><td className="px-3 py-2"><Select value={row.payment_method} onValueChange={(value) => updateRow(row.client_row_id, { payment_method: value, error: null })}><SelectTrigger className="h-9"><SelectValue /></SelectTrigger><SelectContent>{paymentMethods.map((method) => <SelectItem key={method.value} value={method.value}>{method.label}</SelectItem>)}</SelectContent></Select></td><td className="px-3 py-2"><Input aria-label={`Reference row ${index + 1}`} value={row.reference_number} onChange={(event) => updateRow(row.client_row_id, { reference_number: event.target.value, error: null })} placeholder="Optional" className="h-9" /></td><td className="px-3 py-2"><Input aria-label={`Remarks row ${index + 1}`} value={row.remarks} onChange={(event) => updateRow(row.client_row_id, { remarks: event.target.value, error: null })} placeholder="Optional" className="h-9" /></td><td className="px-3 py-2">{row.student_identifier.trim().length === 0 ? <span className="text-muted-foreground text-xs">Ready for data</span> : message ? <span className="text-destructive flex items-start gap-1.5 text-xs leading-5"><TriangleAlert className="mt-0.5 size-3.5 shrink-0" />{message}</span> : <span className="flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300"><CheckCircle2 className="size-3.5" />Ready to record</span>}</td><td className="px-3 py-2"><Button type="button" variant="ghost" size="icon" aria-label={`Remove row ${index + 1}`} onClick={() => setRows((current) => current.filter((item) => item.client_row_id !== row.client_row_id))}><Trash2 className="size-4" /></Button></td></tr>;
            })}</tbody></table></CardContent><div className="flex justify-between gap-3 border-t p-3"><Button type="button" variant="outline" onClick={() => addRows(1)}><Plus className="mr-2 size-4" />Add row</Button><Button type="button" variant="ghost" onClick={() => addRows(8)}>Add 8 rows</Button></div></Card>

            <Card className="sticky bottom-3 z-10 overflow-hidden rounded-xl border-primary/20 bg-card shadow-lg"><CardContent className="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between"><div><p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Record valid rows</p><p className="mt-1 text-sm">{readyRows.length} ready · {failedRows} stay editable · {formatCurrency(readyTotal, currency)} ready total</p></div><Button type="button" size="lg" disabled={recording || readyRows.length === 0} onClick={() => void recordValidRows()}>{recording ? <><Loader2 className="mr-2 size-4 animate-spin" />Recording…</> : <><ReceiptText className="mr-2 size-4" />Record {readyRows.length || ""} valid rows</>}</Button></CardContent></Card>

            {results.length > 0 && <Card className="rounded-xl shadow-xs"><CardHeader className="border-b bg-emerald-500/5"><CardTitle className="flex items-center gap-2 text-lg"><CheckCircle2 className="size-5 text-emerald-600" />Recorded receipts</CardTitle><CardDescription>{results.length} completed · {formatCurrency(recordedTotal, currency)} recorded this session. Duplicate rows were safely recognized instead of posted again.</CardDescription></CardHeader><CardContent className="divide-y p-0">{results.map((result) => <div key={result.client_row_id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><div className="flex items-center gap-2"><p className="font-medium">{result.student_name}</p><Badge variant={result.status === "duplicate" ? "secondary" : "default"}>{result.status === "duplicate" ? "Already recorded" : "Recorded"}</Badge></div><p className="text-muted-foreground mt-1 text-sm">{formatCurrency(result.amount, currency)}{result.transaction_id ? ` · Transaction #${result.transaction_id}` : ""}</p></div>{result.receipt_url && <Button variant="outline" asChild><a href={result.receipt_url}>Open receipt</a></Button>}</div>)}</CardContent></Card>}
        </div>
    );
}
