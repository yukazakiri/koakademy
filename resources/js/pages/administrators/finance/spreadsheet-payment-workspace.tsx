import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import axios from "axios";
import { CheckCircle2, ClipboardPaste, Loader2, Plus, ReceiptText, RotateCcw, Trash2, TriangleAlert } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { formatCurrency, makeClientRowId, type FeeOption, type InventoryItem, type PaymentMethodOption } from "./payment-workspace-types";

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

type ChargeType = "tuition" | "fee" | "item";

type LedgerRow = {
    client_row_id: string;
    student_identifier: string;
    student: LedgerStudent | null;
    candidates: LedgerStudent[];
    charge_type: ChargeType;
    charge_id: string;
    amount: string;
    quantity: string;
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
        amount?: number | null;
        errors?: string[] | Record<string, string[]>;
    }>;
};

type SpreadsheetPaymentWorkspaceProps = {
    batchUrl: string;
    currency: string;
    defaultPaymentMethod: string;
    feeOptions: FeeOption[];
    inventoryItems: InventoryItem[];
    paymentMethods: PaymentMethodOption[];
    resolveUrl: string;
};

const createBatchId = (): string => makeClientRowId();

const createRow = (paymentMethod: string): LedgerRow => ({
    client_row_id: makeClientRowId(),
    student_identifier: "",
    student: null,
    candidates: [],
    charge_type: "tuition",
    charge_id: "",
    amount: "",
    quantity: "1",
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

const parseCharge = (value: string): Pick<LedgerRow, "charge_type" | "charge_id"> => {
    const [type, identifier] = value.trim().toLocaleLowerCase().split(":", 2);
    if (type === "fee" && identifier) return { charge_type: "fee", charge_id: identifier };
    if (type === "item" && identifier) return { charge_type: "item", charge_id: identifier };

    return { charge_type: "tuition", charge_id: "" };
};

export function SpreadsheetPaymentWorkspace({
    batchUrl,
    currency,
    defaultPaymentMethod,
    feeOptions,
    inventoryItems,
    paymentMethods,
    resolveUrl,
}: SpreadsheetPaymentWorkspaceProps) {
    const [batchId, setBatchId] = useState(createBatchId);
    const [rows, setRows] = useState<LedgerRow[]>(() => Array.from({ length: 8 }, () => createRow(defaultPaymentMethod)));
    const [resolving, setResolving] = useState(false);
    const [recording, setRecording] = useState(false);
    const [results, setResults] = useState<LedgerResult[]>([]);
    const lookupTimers = useRef(new Map<string, ReturnType<typeof setTimeout>>());

    useEffect(() => () => lookupTimers.current.forEach((timer) => clearTimeout(timer)), []);

    const selectedTuition = (row: LedgerRow) => row.student?.open_tuitions.find((tuition) => String(tuition.id) === row.charge_id) ?? null;
    const selectedFee = (row: LedgerRow) => feeOptions.find((fee) => fee.key === row.charge_id) ?? null;
    const selectedItem = (row: LedgerRow) => inventoryItems.find((item) => String(item.id) === row.charge_id) ?? null;
    const rowAmount = (row: LedgerRow): number => {
        if (row.charge_type === "item") {
            const quantity = Number.parseInt(row.quantity, 10) || 0;
            return Number(selectedItem(row)?.price ?? 0) * quantity;
        }

        return Number.parseFloat(row.amount) || 0;
    };

    const resolveRows = async (sourceRows: LedgerRow[] = rows, announce = true) => {
        const identifiers = [...new Set(sourceRows.map((row) => row.student_identifier.trim()).filter(Boolean))];
        if (identifiers.length === 0) {
            if (announce) toast.message("Enter a student ID first.");
            return;
        }

        setResolving(true);
        try {
            const { data } = await axios.post<ResolveResponse>(resolveUrl, { student_identifiers: identifiers });
            const matches = new Map(data.matches.map((match) => [match.identifier.trim().toLocaleLowerCase(), match.students]));
            setRows((current) =>
                current.map((row) => {
                    const key = row.student_identifier.trim().toLocaleLowerCase();
                    if (!key || !matches.has(key)) return row;

                    const candidates = matches.get(key) ?? [];
                    if (candidates.length === 0)
                        return {
                            ...row,
                            student: null,
                            candidates: [],
                            charge_id: row.charge_type === "tuition" ? "" : row.charge_id,
                            error: "No student matched this ID.",
                        };
                    if (candidates.length > 1)
                        return {
                            ...row,
                            student: null,
                            candidates,
                            charge_id: row.charge_type === "tuition" ? "" : row.charge_id,
                            error: "Choose the matching student.",
                        };

                    const student = candidates[0];
                    const onlyTuition = student.open_tuitions.length === 1 ? student.open_tuitions[0] : null;
                    const tuitionNeedsChoice = row.charge_type === "tuition" && !row.charge_id && !onlyTuition;
                    return {
                        ...row,
                        student,
                        candidates,
                        charge_id: row.charge_type === "tuition" && onlyTuition ? String(onlyTuition.id) : row.charge_id,
                        error: tuitionNeedsChoice ? "Choose which open balance to pay." : null,
                    };
                }),
            );
            if (announce) toast.success("Student IDs resolved.");
        } catch {
            if (announce) toast.error("Student lookup failed. Check your connection and retry.");
        } finally {
            setResolving(false);
        }
    };

    const queueStudentResolution = (sourceRows: LedgerRow[], row: LedgerRow) => {
        const value = row.student_identifier.trim();
        const existing = lookupTimers.current.get(row.client_row_id);
        if (existing) clearTimeout(existing);
        if (!/^\d{3,}$/.test(value)) return;

        lookupTimers.current.set(
            row.client_row_id,
            setTimeout(() => {
                lookupTimers.current.delete(row.client_row_id);
                void resolveRows(sourceRows, false);
            }, 350),
        );
    };

    const changeStudentIdentifier = (clientRowId: string, value: string) => {
        const next = rows.map((row) =>
            row.client_row_id === clientRowId
                ? {
                      ...row,
                      student_identifier: value,
                      student: null,
                      candidates: [],
                      charge_id: row.charge_type === "tuition" ? "" : row.charge_id,
                      error: null,
                  }
                : row,
        );
        setRows(next);
        const changed = next.find((row) => row.client_row_id === clientRowId);
        if (changed) queueStudentResolution(next, changed);
    };

    const updateRow = (clientRowId: string, change: Partial<LedgerRow>) => {
        setRows((current) => current.map((row) => (row.client_row_id === clientRowId ? { ...row, ...change } : row)));
    };

    const chooseStudent = (row: LedgerRow, studentId: string) => {
        const student = row.candidates.find((candidate) => String(candidate.id) === studentId) ?? null;
        const onlyTuition = student?.open_tuitions.length === 1 ? student.open_tuitions[0] : null;
        updateRow(row.client_row_id, {
            student,
            charge_id: row.charge_type === "tuition" && onlyTuition ? String(onlyTuition.id) : row.charge_id,
            error: !student
                ? "Choose the matching student."
                : row.charge_type === "tuition" && student.open_tuitions.length > 1
                  ? "Choose which open balance to pay."
                  : null,
        });
    };

    const changeChargeType = (row: LedgerRow, chargeType: ChargeType) => {
        const onlyTuition = row.student?.open_tuitions.length === 1 ? row.student.open_tuitions[0] : null;
        updateRow(row.client_row_id, {
            charge_type: chargeType,
            charge_id: chargeType === "tuition" && onlyTuition ? String(onlyTuition.id) : "",
            amount: "",
            quantity: "1",
            error: chargeType === "tuition" && row.student && row.student.open_tuitions.length > 1 ? "Choose which open balance to pay." : null,
        });
    };

    const changeCharge = (row: LedgerRow, chargeId: string) => {
        const item = row.charge_type === "item" ? inventoryItems.find((candidate) => String(candidate.id) === chargeId) : null;
        updateRow(row.client_row_id, { charge_id: chargeId, amount: item ? item.price.toFixed(2) : row.amount, error: null });
    };

    const parsePaste = (clipboardText: string): LedgerRow[] =>
        clipboardText
            .replace(/\r/g, "")
            .split("\n")
            .filter((line) => line.trim().length > 0)
            .map((line) => {
                const [studentIdentifier = "", charge = "", amount = "", paymentMethod = defaultPaymentMethod, referenceNumber = "", remarks = ""] =
                    line.split("\t");
                return {
                    ...createRow(paymentMethod.trim() || defaultPaymentMethod),
                    ...parseCharge(charge),
                    student_identifier: studentIdentifier.trim(),
                    amount: amount.trim(),
                    reference_number: referenceNumber.trim(),
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

    const validationMessage = (row: LedgerRow): string | null => {
        if (row.error) return row.error;
        if (!row.student) return "Type a student ID to resolve the account.";

        if (row.charge_type === "tuition") {
            const tuition = selectedTuition(row);
            if (!tuition) return "Choose an open tuition balance.";
            if (rowAmount(row) > tuition.balance) return "Amount exceeds the current balance.";
        }
        if (row.charge_type === "fee" && !selectedFee(row)) return "Choose a fee.";
        if (row.charge_type === "item" && !selectedItem(row)) return "Choose an inventory item.";
        if (row.charge_type === "item" && (!Number.isInteger(Number(row.quantity)) || Number(row.quantity) < 1)) return "Enter a valid quantity.";
        if (rowAmount(row) <= 0) return "Enter a positive amount.";

        return null;
    };

    const readyRows = rows.filter((row) => row.student_identifier.trim().length > 0 && !validationMessage(row));
    const failedRows = rows.filter((row) => row.student_identifier.trim().length > 0 && Boolean(validationMessage(row))).length;
    const readyTotal = readyRows.reduce((total, row) => total + rowAmount(row), 0);
    const recordedTotal = results.reduce((total, result) => total + result.amount, 0);

    const recordValidRows = async () => {
        const candidates = rows.filter((row) => row.student_identifier.trim().length > 0);
        const nextRows = rows.map((row) => ({ ...row, error: row.student_identifier.trim().length > 0 ? validationMessage(row) : null }));
        setRows(nextRows);
        const valid = nextRows.filter((row) => row.student_identifier.trim().length > 0 && !row.error && row.student);
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
                    items: [
                        row.charge_type === "tuition"
                            ? { type: "tuition", tuition_id: Number(row.charge_id), amount: rowAmount(row) }
                            : row.charge_type === "fee"
                              ? { type: "fee", fee_key: row.charge_id, amount: rowAmount(row) }
                              : { type: "item", id: Number(row.charge_id), quantity: Number(row.quantity), amount: rowAmount(row) },
                    ],
                })),
            });
            const submitted = new Map(valid.map((row) => [row.client_row_id, row]));
            const completed = data.results.filter((result) => result.status === "recorded" || result.status === "duplicate");
            const rejected = new Map(
                data.results.filter((result) => result.status === "rejected").map((result) => [result.client_row_id, errorText(result.errors)]),
            );

            setResults((current) => [
                ...completed.map((result) => {
                    const row = submitted.get(result.client_row_id)!;
                    return {
                        client_row_id: result.client_row_id,
                        status: result.status as "recorded" | "duplicate",
                        transaction_id: result.transaction_id,
                        receipt_url: result.receipt_url,
                        amount: result.amount ?? rowAmount(row),
                        student_name: row.student?.full_name || row.student_identifier,
                    };
                }),
                ...current,
            ]);
            setRows((current) =>
                current
                    .filter((row) => !completed.some((result) => result.client_row_id === row.client_row_id))
                    .map((row) => (rejected.has(row.client_row_id) ? { ...row, error: rejected.get(row.client_row_id)! } : row)),
            );
            toast.success(
                `${completed.length} ${completed.length === 1 ? "payment" : "payments"} recorded.${rejected.size > 0 ? ` ${rejected.size} row(s) need attention.` : ""}`,
            );
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422)
                toast.error("The batch format is invalid. Correct the highlighted rows and retry.");
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

    const chargeControl = (row: LedgerRow, index: number) => {
        const tuition = selectedTuition(row);
        const item = selectedItem(row);

        return (
            <div className="space-y-2">
                <Select value={row.charge_type} onValueChange={(value) => changeChargeType(row, value as ChargeType)}>
                    <SelectTrigger aria-label={`Charge type row ${index + 1}`} className="h-9">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="tuition">Tuition balance</SelectItem>
                        <SelectItem value="fee">School fee</SelectItem>
                        <SelectItem value="item">Inventory purchase</SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    value={row.charge_id}
                    onValueChange={(value) => changeCharge(row, value)}
                    disabled={row.charge_type === "tuition" && !row.student}
                >
                    <SelectTrigger aria-label={`Charge selection row ${index + 1}`} className="h-9">
                        <SelectValue
                            placeholder={row.charge_type === "tuition" ? "Choose balance" : row.charge_type === "fee" ? "Choose fee" : "Choose item"}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {row.charge_type === "tuition" &&
                            row.student?.open_tuitions.map((openTuition) => (
                                <SelectItem key={openTuition.id} value={String(openTuition.id)}>
                                    {tuitionLabel(openTuition)} · {formatCurrency(openTuition.balance, currency)}
                                </SelectItem>
                            ))}
                        {row.charge_type === "fee" &&
                            feeOptions.map((fee) => (
                                <SelectItem key={fee.key} value={fee.key}>
                                    {fee.label}
                                </SelectItem>
                            ))}
                        {row.charge_type === "item" &&
                            inventoryItems.map((inventoryItem) => (
                                <SelectItem key={inventoryItem.id} value={String(inventoryItem.id)}>
                                    {inventoryItem.name} · {formatCurrency(inventoryItem.price, currency)}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
                {tuition && <p className="text-muted-foreground text-xs">Available {formatCurrency(tuition.balance, currency)}</p>}
                {item && (
                    <p className="text-muted-foreground text-xs">
                        {item.sku} · {item.category}
                    </p>
                )}
            </div>
        );
    };

    const amountControl = (row: LedgerRow, index: number) => {
        const item = selectedItem(row);

        return (
            <div className="space-y-2">
                <Input
                    aria-label={`Amount row ${index + 1}`}
                    type="number"
                    min="0"
                    max={selectedTuition(row)?.balance}
                    step="0.01"
                    inputMode="decimal"
                    value={row.charge_type === "item" ? rowAmount(row).toFixed(2) : row.amount}
                    onChange={(event) => updateRow(row.client_row_id, { amount: event.target.value, error: null })}
                    placeholder="0.00"
                    readOnly={row.charge_type === "item"}
                    className="h-9 text-right font-medium tabular-nums"
                />
                {row.charge_type === "item" && (
                    <div className="flex items-center gap-2">
                        <Label className="text-muted-foreground shrink-0 text-xs" htmlFor={`quantity-${row.client_row_id}`}>
                            Qty
                        </Label>
                        <Input
                            id={`quantity-${row.client_row_id}`}
                            type="number"
                            min="1"
                            max="100"
                            inputMode="numeric"
                            value={row.quantity}
                            onChange={(event) => updateRow(row.client_row_id, { quantity: event.target.value, error: null })}
                            className="h-8 text-right text-xs tabular-nums"
                            disabled={!item}
                        />
                    </div>
                )}
            </div>
        );
    };

    const studentControl = (row: LedgerRow, index: number) => (
        <div className="space-y-2">
            <Input
                aria-label={`Student ID row ${index + 1}`}
                value={row.student_identifier}
                onPaste={(event) => handlePaste(event, row.client_row_id)}
                onChange={(event) => changeStudentIdentifier(row.client_row_id, event.target.value)}
                placeholder="Student ID"
                className="h-9"
            />
            {resolving && row.student_identifier.trim().length > 0 && !row.student && (
                <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                    <Loader2 className="size-3 animate-spin" />
                    Looking up student…
                </p>
            )}
            {row.candidates.length > 1 && !row.student && (
                <Select onValueChange={(value) => chooseStudent(row, value)}>
                    <SelectTrigger className="h-8 text-xs">
                        <SelectValue placeholder="Choose student" />
                    </SelectTrigger>
                    <SelectContent>
                        {row.candidates.map((candidate) => (
                            <SelectItem key={candidate.id} value={String(candidate.id)}>
                                {candidate.student_id} · {candidate.full_name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
            {row.student && (
                <p className="text-muted-foreground truncate text-xs">
                    {row.student.student_id} · {row.student.full_name}
                </p>
            )}
        </div>
    );

    const rowStatus = (row: LedgerRow) => {
        const message = validationMessage(row);
        if (row.student_identifier.trim().length === 0) return <span className="text-muted-foreground text-xs">Ready for data</span>;
        if (message)
            return (
                <span className="text-destructive flex items-start gap-1.5 text-xs leading-5">
                    <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                    {message}
                </span>
            );

        return (
            <span className="flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                <CheckCircle2 className="size-3.5" />
                Ready to record
            </span>
        );
    };

    return (
        <div className="min-w-0 space-y-4 pb-10">
            <Card className="min-w-0 overflow-hidden rounded-xl shadow-xs">
                <CardHeader className="bg-muted/25 border-b">
                    <div className="flex min-w-0 flex-col gap-3 @lg/main:flex-row @lg/main:items-start @lg/main:justify-between">
                        <div className="min-w-0">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <ClipboardPaste className="text-primary size-5" />
                                Payment ledger
                            </CardTitle>
                            <CardDescription>
                                Type a student ID or paste Excel rows. Each valid row becomes its own receipt; fees and inventory purchases are
                                supported alongside tuition.
                            </CardDescription>
                        </div>
                        <div className="grid grid-cols-1 gap-2 @lg/main:flex">
                            <Button type="button" variant="outline" onClick={() => void resolveRows()} disabled={resolving}>
                                {resolving ? <Loader2 className="mr-2 size-4 animate-spin" /> : <ClipboardPaste className="mr-2 size-4" />}Refresh
                                matches
                            </Button>
                            <Button type="button" variant="outline" onClick={clearSession}>
                                <RotateCcw className="mr-2 size-4" />
                                New ledger
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4 p-4 sm:p-5">
                    <div className="bg-muted/20 rounded-xl border border-dashed p-4">
                        <p className="font-medium">Excel paste format</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Student ID, charge (`tuition`, `fee:certification`, or `item:123`), amount, method, reference, remarks. Student IDs
                            resolve automatically after you pause typing.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <Badge variant="secondary">{readyRows.length} ready</Badge>
                        <Badge variant={failedRows > 0 ? "destructive" : "secondary"}>{failedRows} need attention</Badge>
                        <span className="text-muted-foreground">Ready total {formatCurrency(readyTotal, currency)}</span>
                    </div>
                </CardContent>
            </Card>

            <div className="space-y-3 @lg/main:hidden">
                {rows.map((row, index) => (
                    <Card
                        key={row.client_row_id}
                        className={cn(
                            "min-w-0 overflow-hidden rounded-xl shadow-xs",
                            validationMessage(row) && row.student_identifier.trim() ? "border-destructive/30" : "",
                        )}
                    >
                        <CardContent className="space-y-4 p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Student</Label>
                                    <div className="mt-2">{studentControl(row, index)}</div>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove row ${index + 1}`}
                                    onClick={() => setRows((current) => current.filter((item) => item.client_row_id !== row.client_row_id))}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Charge</Label>
                                    <div className="mt-2">{chargeControl(row, index)}</div>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Amount</Label>
                                    <div className="mt-2">{amountControl(row, index)}</div>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Method</Label>
                                    <Select
                                        value={row.payment_method}
                                        onValueChange={(value) => updateRow(row.client_row_id, { payment_method: value, error: null })}
                                    >
                                        <SelectTrigger className="mt-2 h-9">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {paymentMethods.map((method) => (
                                                <SelectItem key={method.value} value={method.value}>
                                                    {method.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Reference</Label>
                                    <Input
                                        value={row.reference_number}
                                        onChange={(event) => updateRow(row.client_row_id, { reference_number: event.target.value, error: null })}
                                        placeholder="Optional"
                                        className="mt-2 h-9"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Remarks</Label>
                                <Input
                                    value={row.remarks}
                                    onChange={(event) => updateRow(row.client_row_id, { remarks: event.target.value, error: null })}
                                    placeholder="Optional"
                                    className="mt-2 h-9"
                                />
                            </div>
                            <div aria-live="polite" className="border-t pt-3">
                                {rowStatus(row)}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card className="hidden min-w-0 overflow-hidden rounded-xl shadow-xs @lg/main:block">
                <CardContent className="max-w-full overflow-x-auto overscroll-x-contain p-0">
                    <table className="w-full min-w-[1120px] border-collapse text-sm">
                        <thead className="bg-muted/35 text-muted-foreground text-left text-xs font-semibold tracking-wide uppercase">
                            <tr>
                                <th className="w-12 px-3 py-3">#</th>
                                <th className="min-w-44 px-3 py-3">Student</th>
                                <th className="min-w-64 px-3 py-3">Allocation / charge</th>
                                <th className="w-36 px-3 py-3">Amount</th>
                                <th className="min-w-36 px-3 py-3">Method</th>
                                <th className="min-w-40 px-3 py-3">Reference</th>
                                <th className="min-w-48 px-3 py-3">Remarks</th>
                                <th className="min-w-48 px-3 py-3">Status</th>
                                <th className="w-12 px-3 py-3">
                                    <span className="sr-only">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={row.client_row_id}
                                    className={cn(
                                        "workspace-density-row border-t align-top",
                                        validationMessage(row) && row.student_identifier.trim() ? "bg-destructive/[0.03]" : "",
                                    )}
                                >
                                    <td className="text-muted-foreground px-3 py-3 tabular-nums">{index + 1}</td>
                                    <td className="px-3 py-2">{studentControl(row, index)}</td>
                                    <td className="px-3 py-2">{chargeControl(row, index)}</td>
                                    <td className="px-3 py-2">{amountControl(row, index)}</td>
                                    <td className="px-3 py-2">
                                        <Select
                                            value={row.payment_method}
                                            onValueChange={(value) => updateRow(row.client_row_id, { payment_method: value, error: null })}
                                        >
                                            <SelectTrigger className="h-9">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {paymentMethods.map((method) => (
                                                    <SelectItem key={method.value} value={method.value}>
                                                        {method.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Reference row ${index + 1}`}
                                            value={row.reference_number}
                                            onChange={(event) => updateRow(row.client_row_id, { reference_number: event.target.value, error: null })}
                                            placeholder="Optional"
                                            className="h-9"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Remarks row ${index + 1}`}
                                            value={row.remarks}
                                            onChange={(event) => updateRow(row.client_row_id, { remarks: event.target.value, error: null })}
                                            placeholder="Optional"
                                            className="h-9"
                                        />
                                    </td>
                                    <td className="px-3 py-2" aria-live="polite">
                                        {rowStatus(row)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove row ${index + 1}`}
                                            onClick={() => setRows((current) => current.filter((item) => item.client_row_id !== row.client_row_id))}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div className="flex flex-col gap-2 sm:flex-row sm:justify-between">
                <Button type="button" variant="outline" onClick={() => addRows(1)}>
                    <Plus className="mr-2 size-4" />
                    Add row
                </Button>
                <Button type="button" variant="ghost" onClick={() => addRows(8)}>
                    Add 8 rows
                </Button>
            </div>

            <Card className="border-primary/20 bg-card min-w-0 overflow-hidden rounded-xl shadow-lg">
                <CardContent className="flex flex-col gap-4 p-4 sm:p-5 @lg/main:flex-row @lg/main:items-center @lg/main:justify-between">
                    <div>
                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Record valid rows</p>
                        <p className="mt-1 text-sm">
                            {readyRows.length} ready · {failedRows} stay editable · {formatCurrency(readyTotal, currency)} ready total
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="lg"
                        className="w-full @lg/main:w-auto"
                        disabled={recording || readyRows.length === 0}
                        onClick={() => void recordValidRows()}
                    >
                        {recording ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Recording…
                            </>
                        ) : (
                            <>
                                <ReceiptText className="mr-2 size-4" />
                                Record {readyRows.length || ""} valid rows
                            </>
                        )}
                    </Button>
                </CardContent>
            </Card>

            {results.length > 0 && (
                <Card className="min-w-0 rounded-xl shadow-xs">
                    <CardHeader className="border-b bg-emerald-500/5">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <CheckCircle2 className="size-5 text-emerald-600" />
                            Recorded receipts
                        </CardTitle>
                        <CardDescription>
                            {results.length} completed · {formatCurrency(recordedTotal, currency)} recorded this session. Duplicate rows were safely
                            recognized instead of posted again.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y p-0">
                        {results.map((result) => (
                            <div key={result.client_row_id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">{result.student_name}</p>
                                        <Badge variant={result.status === "duplicate" ? "secondary" : "default"}>
                                            {result.status === "duplicate" ? "Already recorded" : "Recorded"}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {formatCurrency(result.amount, currency)}
                                        {result.transaction_id ? ` · Transaction #${result.transaction_id}` : ""}
                                    </p>
                                </div>
                                {result.receipt_url && (
                                    <Button variant="outline" asChild>
                                        <a href={result.receipt_url}>Open receipt</a>
                                    </Button>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
