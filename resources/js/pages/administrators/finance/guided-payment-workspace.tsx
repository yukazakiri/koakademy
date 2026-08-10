import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { students } from "@/routes/administrators/enrollments/api";
import { studentDetails, studentTransactions } from "@/routes/administrators/finance/api";
import { store } from "@/routes/administrators/finance/payments";
import { Link, router } from "@inertiajs/react";
import {
    ChevronsUpDown,
    CircleDollarSign,
    ExternalLink,
    History,
    Loader2,
    PackagePlus,
    PanelRightClose,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserRound,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import {
    FEE_TYPES,
    type FeeType,
    formatCurrency,
    type InventoryItem,
    type PaymentMethodOption,
    type PaymentWorkspacePreference,
    type StudentFinancialDetails,
    type StudentOption,
    type StudentTransactionHistoryResponse,
} from "./payment-workspace-types";

type ChargeItem = {
    id: string;
    type: "fee" | "item";
    label: string;
    amount: string;
    data: FeeType | InventoryItem;
};

type GuidedPaymentWorkspaceProps = {
    currency: string;
    inventoryItems: InventoryItem[];
    paymentMethods: PaymentMethodOption[];
    preference: PaymentWorkspacePreference;
};

const feeKey = (fee: FeeType | InventoryItem): fee is FeeType => "label" in fee;

export function GuidedPaymentWorkspace({ currency, inventoryItems, paymentMethods, preference }: GuidedPaymentWorkspaceProps) {
    const [studentOpen, setStudentOpen] = useState(false);
    const [studentSearch, setStudentSearch] = useState("");
    const [studentOptions, setStudentOptions] = useState<StudentOption[]>([]);
    const [studentLoading, setStudentLoading] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentFinancialDetails | null>(null);
    const [tuitionAmounts, setTuitionAmounts] = useState<Record<number, string>>({});
    const [charges, setCharges] = useState<ChargeItem[]>([]);
    const [addChargeOpen, setAddChargeOpen] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState(preference.default_payment_method);
    const [cashReceived, setCashReceived] = useState("");
    const [referenceNumber, setReferenceNumber] = useState("");
    const [remarks, setRemarks] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(preference.history_visibility === "open");
    const [historyLoading, setHistoryLoading] = useState(false);
    const [history, setHistory] = useState<StudentTransactionHistoryResponse>({ transactions: [], summary: { count: 0, total_paid: 0 } });
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const prefilledStudentRef = useRef(false);

    const tuitionTotal = Object.values(tuitionAmounts).reduce((total, amount) => total + (Number.parseFloat(amount) || 0), 0);
    const chargeTotal = charges.reduce((total, charge) => total + (Number.parseFloat(charge.amount) || 0), 0);
    const total = tuitionTotal + chargeTotal;
    const received = Number.parseFloat(cashReceived) || 0;
    const isCash = paymentMethod === "Cash";
    const isCashShort = isCash && total > 0 && received < total;
    const changeDue = isCash ? Math.max(received - total, 0) : 0;
    const chargeCount = Object.values(tuitionAmounts).filter((amount) => Number.parseFloat(amount) > 0).length + charges.filter((charge) => Number.parseFloat(charge.amount) > 0).length;
    const canSubmit = Boolean(selectedStudent && total > 0 && !isCashShort && !submitting);

    const searchStudents = useCallback(async (search: string): Promise<StudentOption[]> => {
        const response = await fetch(students.url({ query: { search } }));
        if (!response.ok) throw new Error("student-search-failed");

        return response.json();
    }, []);

    const loadHistory = useCallback(async (studentId: number) => {
        setHistoryLoading(true);
        try {
            const response = await fetch(studentTransactions.url(studentId));
            if (!response.ok) throw new Error("history-failed");
            const result: StudentTransactionHistoryResponse = await response.json();
            setHistory(result);
            if (preference.history_visibility === "auto") setHistoryOpen(result.summary.count > 0);
        } catch {
            toast.error("Payment history could not be loaded.");
        } finally {
            setHistoryLoading(false);
        }
    }, [preference.history_visibility]);

    const selectStudent = useCallback(async (student: StudentOption) => {
        setStudentLoading(true);
        setStudentOpen(false);
        setStudentSearch("");
        try {
            const response = await fetch(studentDetails.url({ query: { student_id: student.id } }));
            if (!response.ok) throw new Error("student-details-failed");
            const details: StudentFinancialDetails = await response.json();
            setSelectedStudent(details);
            setTuitionAmounts({});
            setCharges([]);
            setCashReceived("");
            setReferenceNumber("");
            setRemarks("");
            setPaymentMethod(preference.default_payment_method);
            setHistory({ transactions: [], summary: { count: 0, total_paid: 0 } });
            setHistoryOpen(preference.history_visibility === "open");
            void loadHistory(details.id);
            toast.success(`${details.full_name} is ready for collection.`);
        } catch {
            toast.error("Could not load the student’s balances.");
        } finally {
            setStudentLoading(false);
        }
    }, [loadHistory, preference.default_payment_method, preference.history_visibility]);

    useEffect(() => {
        if (prefilledStudentRef.current || selectedStudent || typeof window === "undefined") return;
        const studentQuery = new URLSearchParams(window.location.search).get("student");
        if (!studentQuery || studentQuery.length < 2) return;
        prefilledStudentRef.current = true;
        setStudentSearch(studentQuery);
        void searchStudents(studentQuery)
            .then((matches) => {
                setStudentOptions(matches);
                if (matches.length === 1) void selectStudent(matches[0]);
                else setStudentOpen(true);
            })
            .catch(() => toast.error("Could not prefill the student account."));
    }, [searchStudents, selectStudent, selectedStudent]);

    const updateStudentSearch = (value: string) => {
        setStudentSearch(value);
        if (searchTimeout.current) clearTimeout(searchTimeout.current);
        if (value.trim().length < 2) {
            setStudentOptions([]);
            return;
        }
        searchTimeout.current = setTimeout(() => {
            setStudentLoading(true);
            void searchStudents(value)
                .then(setStudentOptions)
                .catch(() => toast.error("Student search failed. Try again."))
                .finally(() => setStudentLoading(false));
        }, 250);
    };

    const addFee = (fee: FeeType) => {
        setCharges((current) => [...current, { id: `${Date.now()}-${fee.id}`, type: "fee", label: fee.label, amount: "", data: fee }]);
        setAddChargeOpen(false);
    };

    const addInventoryItem = (item: InventoryItem) => {
        setCharges((current) => [...current, { id: `${Date.now()}-${item.id}`, type: "item", label: item.name, amount: item.price.toFixed(2), data: item }]);
        setAddChargeOpen(false);
    };

    const submitPayment = (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedStudent || !canSubmit) return;

        const items = selectedStudent.unpaid_enrollments.flatMap((enrollment) => {
            const amount = Number.parseFloat(tuitionAmounts[enrollment.id] || "");
            return amount > 0 ? [{ type: "tuition", tuition_id: enrollment.id, amount }] : [];
        });

        charges.forEach((charge) => {
            const amount = Number.parseFloat(charge.amount);
            if (amount <= 0) return;
            if (charge.type === "fee" && feeKey(charge.data)) items.push({ type: "fee", fee_key: charge.data.id, amount });
            if (charge.type === "item" && !feeKey(charge.data)) items.push({ type: "item", id: charge.data.id, amount });
        });

        setSubmitting(true);
        router.post(
            store.url(),
            { student_id: selectedStudent.id, payment_method: paymentMethod, reference_number: referenceNumber, remarks, items },
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Payment recorded. The receipt is ready."),
                onError: () => toast.error("Payment could not be recorded. Review the amounts and try again."),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <div className={cn("grid items-start gap-4", historyOpen && selectedStudent ? "xl:grid-cols-[minmax(0,1fr)_360px]" : "xl:grid-cols-1")}>
            <form onSubmit={submitPayment} className="min-w-0 space-y-4 pb-10">
                <Card className="overflow-hidden rounded-xl shadow-xs">
                    <CardContent className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <div className="space-y-2">
                            <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Student account</Label>
                            <Popover open={studentOpen} onOpenChange={setStudentOpen}>
                                <PopoverTrigger asChild>
                                    <Button type="button" variant="outline" role="combobox" aria-expanded={studentOpen} className="h-auto min-h-12 w-full justify-between rounded-xl px-3.5 py-2.5 text-left">
                                        {selectedStudent ? (
                                            <span className="flex min-w-0 items-center gap-3">
                                                <span className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold">{selectedStudent.full_name.charAt(0)}</span>
                                                <span className="min-w-0"><span className="block truncate font-semibold">{selectedStudent.full_name}</span><span className="text-muted-foreground block text-xs font-normal">{selectedStudent.student_id} · {selectedStudent.course} · Year {selectedStudent.year_level}</span></span>
                                            </span>
                                        ) : <span className="text-muted-foreground flex items-center gap-3"><Search className="size-4" />Search by student name or ID</span>}
                                        <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-[min(92vw,520px)] rounded-xl p-0" align="start">
                                    <Command shouldFilter={false}>
                                        <CommandInput placeholder="Type at least 2 characters…" value={studentSearch} onValueChange={updateStudentSearch} />
                                        <CommandList>
                                            {studentLoading ? <div className="text-muted-foreground flex items-center justify-center gap-2 p-6 text-sm"><Loader2 className="size-4 animate-spin" />Searching students…</div> : studentSearch.length < 2 ? <CommandEmpty>Type a name or student ID.</CommandEmpty> : studentOptions.length === 0 ? <CommandEmpty>No matching students.</CommandEmpty> : <CommandGroup heading="Matching students">{studentOptions.map((student) => <CommandItem key={student.id} value={String(student.id)} onSelect={() => void selectStudent(student)} className="min-h-14"><UserRound className="mr-3 size-4" /><span><span className="block font-medium">{student.full_name}</span><span className="text-muted-foreground block text-xs">{student.email} · {student.course_code || "No course"}</span></span></CommandItem>)}</CommandGroup>}
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>
                        {selectedStudent && <Button type="button" variant="outline" onClick={() => setHistoryOpen((open) => !open)} className="min-h-10"><History className="mr-2 size-4" />{historyOpen ? "Hide history" : "View history"}</Button>}
                    </CardContent>
                </Card>

                <Card className="overflow-hidden rounded-xl shadow-xs">
                    <CardHeader className="border-b bg-muted/25"><CardTitle className="flex items-center gap-2 text-lg"><CircleDollarSign className="size-5 text-primary" />Open balances</CardTitle><CardDescription>Enter an amount against any outstanding tuition balance. One receipt can include several lines.</CardDescription></CardHeader>
                    <CardContent className="p-0">
                        {!selectedStudent ? <div className="text-muted-foreground px-5 py-12 text-center text-sm">Select a student to load their open tuition balances.</div> : selectedStudent.unpaid_enrollments.length === 0 ? <div className="text-muted-foreground px-5 py-12 text-center text-sm">This student has no open tuition balances.</div> : <div className="divide-y">{selectedStudent.unpaid_enrollments.map((enrollment) => {
                            const amount = tuitionAmounts[enrollment.id] ?? "";
                            return <div key={enrollment.id} className="workspace-density-row grid gap-3 px-5 py-4 md:grid-cols-[minmax(0,1fr)_150px_auto] md:items-center"><div><p className="font-medium">SY {enrollment.school_year} · {enrollment.semester === 1 ? "1st" : "2nd"} semester</p><p className="text-muted-foreground mt-1 text-sm">Balance {formatCurrency(enrollment.balance, currency)}</p></div><Input aria-label={`Amount for ${enrollment.school_year} semester ${enrollment.semester}`} type="number" min="0" max={enrollment.balance} step="0.01" inputMode="decimal" value={amount} onChange={(event) => setTuitionAmounts((current) => ({ ...current, [enrollment.id]: event.target.value }))} placeholder="0.00" className="h-10 text-right font-semibold tabular-nums" /><Button type="button" variant="ghost" size="sm" onClick={() => setTuitionAmounts((current) => ({ ...current, [enrollment.id]: enrollment.balance.toFixed(2) }))}>Pay balance</Button></div>;
                        })}</div>}
                    </CardContent>
                </Card>

                <Card className="overflow-visible rounded-xl shadow-xs">
                    <CardHeader className="flex-row items-start justify-between gap-4 border-b bg-muted/25"><div><CardTitle className="flex items-center gap-2 text-lg"><PackagePlus className="size-5 text-primary" />Other charges</CardTitle><CardDescription>Add catalog fees or stocked items to the same receipt.</CardDescription></div><Popover open={addChargeOpen} onOpenChange={setAddChargeOpen}><PopoverTrigger asChild><Button type="button" variant="outline"><Plus className="mr-2 size-4" />Add charge</Button></PopoverTrigger><PopoverContent className="w-80 p-2" align="end"><Command><CommandInput placeholder="Find a fee or item…" /><CommandList><CommandEmpty>No charge found.</CommandEmpty><CommandGroup heading="Fees">{FEE_TYPES.map((fee) => <CommandItem key={fee.id} onSelect={() => addFee(fee)}>{fee.label}</CommandItem>)}</CommandGroup><CommandSeparator /><CommandGroup heading="Inventory">{inventoryItems.map((item) => <CommandItem key={item.id} onSelect={() => addInventoryItem(item)}><span className="min-w-0 flex-1 truncate">{item.name}</span><span className="text-muted-foreground ml-3 text-xs">{formatCurrency(item.price, currency)}</span></CommandItem>)}</CommandGroup></CommandList></Command></PopoverContent></Popover></CardHeader>
                    <CardContent className="p-0">{charges.length === 0 ? <div className="text-muted-foreground px-5 py-9 text-center text-sm">No extra fees or items on this receipt.</div> : <div className="divide-y">{charges.map((charge) => <div key={charge.id} className="workspace-density-row grid gap-3 px-5 py-3 sm:grid-cols-[minmax(0,1fr)_150px_auto] sm:items-center"><div><p className="font-medium">{charge.label}</p><p className="text-muted-foreground text-xs capitalize">{charge.type}</p></div><Input aria-label={`${charge.label} amount`} type="number" min="0" step="0.01" inputMode="decimal" value={charge.amount} onChange={(event) => setCharges((current) => current.map((item) => item.id === charge.id ? { ...item, amount: event.target.value } : item))} className="h-10 text-right font-semibold tabular-nums" /><Button type="button" size="icon" variant="ghost" aria-label={`Remove ${charge.label}`} onClick={() => setCharges((current) => current.filter((item) => item.id !== charge.id))}><Trash2 className="size-4" /></Button></div>)}</div>}</CardContent>
                </Card>

                <Card className="rounded-xl shadow-xs"><CardHeader className="border-b bg-muted/25"><CardTitle className="text-lg">Collection details</CardTitle><CardDescription>Cash collection calculates change; references and remarks remain optional.</CardDescription></CardHeader><CardContent className="grid gap-4 p-5 sm:grid-cols-2"><div className="space-y-2"><Label htmlFor="guided-payment-method">Payment method</Label><Select value={paymentMethod} onValueChange={setPaymentMethod}><SelectTrigger id="guided-payment-method"><SelectValue /></SelectTrigger><SelectContent>{paymentMethods.map((method) => <SelectItem key={method.value} value={method.value}>{method.label}</SelectItem>)}</SelectContent></Select></div><div className="space-y-2"><Label htmlFor="guided-reference">Reference number <span className="text-muted-foreground font-normal">(optional)</span></Label><Input id="guided-reference" value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder={isCash ? "Official receipt or reference" : "Provider reference"} /></div><div className="space-y-2 sm:col-span-2"><Label htmlFor="guided-remarks">Remarks <span className="text-muted-foreground font-normal">(optional)</span></Label><Textarea id="guided-remarks" value={remarks} onChange={(event) => setRemarks(event.target.value)} placeholder="Add a short reconciliation note…" className="min-h-20 resize-none" /></div></CardContent></Card>

                <Card className="sticky bottom-3 z-10 overflow-hidden rounded-xl border-primary/20 bg-card shadow-lg"><CardContent className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(250px,0.8fr)_220px] lg:items-end"><div>{isCash && <div className="space-y-3"><div className="flex items-center justify-between"><Label htmlFor="guided-cash-received">Cash received</Label>{total > 0 && <Button type="button" variant="link" size="sm" onClick={() => setCashReceived(total.toFixed(2))}>Exact amount</Button>}</div><Input id="guided-cash-received" type="number" min="0" step="0.01" inputMode="decimal" value={cashReceived} onChange={(event) => setCashReceived(event.target.value)} placeholder="0.00" className="h-12 text-right text-xl font-bold tabular-nums" /><div className={cn("rounded-xl p-3", isCashShort ? "bg-destructive/10" : "bg-emerald-500/10")}><div className="flex justify-between gap-3"><span className={isCashShort ? "text-destructive text-sm font-medium" : "text-sm font-medium text-emerald-700 dark:text-emerald-300"}>{isCashShort ? "Still needed" : "Change due"}</span><span className={isCashShort ? "text-destructive font-bold tabular-nums" : "font-bold text-emerald-700 tabular-nums dark:text-emerald-300"}>{formatCurrency(isCashShort ? total - received : changeDue, currency)}</span></div></div></div>}</div><div className="space-y-2"><p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Receipt total</p><p className="text-3xl font-bold tracking-tight tabular-nums">{formatCurrency(total, currency)}</p><p className="text-muted-foreground text-sm">{chargeCount} payable {chargeCount === 1 ? "line" : "lines"} · {paymentMethod}</p></div><div><Button type="submit" size="lg" disabled={!canSubmit} className="h-11 w-full">{submitting ? <><Loader2 className="mr-2 size-4 animate-spin" />Recording…</> : <><ShieldCheck className="mr-2 size-4" />Record payment</>}</Button><p className="text-muted-foreground mt-2 text-center text-xs">Posts immediately and opens the receipt.</p></div></CardContent></Card>
            </form>

            {historyOpen && selectedStudent && <aside className="bg-background sticky top-4 flex max-h-[calc(100vh-2rem)] min-h-0 flex-col overflow-hidden rounded-xl border shadow-lg"><div className="border-b px-5 py-4"><div className="flex items-start justify-between gap-3"><div><div className="flex items-center gap-2"><History className="size-4 text-primary" /><h2 className="font-semibold">Payment history</h2></div><p className="text-muted-foreground mt-1 text-sm">{selectedStudent.full_name}</p></div><Button type="button" variant="ghost" size="icon" aria-label="Close payment history" onClick={() => setHistoryOpen(false)}><PanelRightClose className="size-4" /></Button></div></div><div className="grid grid-cols-2 border-b bg-muted/25"><div className="border-r px-5 py-3"><p className="text-muted-foreground text-xs">Transactions</p><p className="font-semibold tabular-nums">{history.summary.count}</p></div><div className="px-5 py-3"><p className="text-muted-foreground text-xs">Total paid</p><p className="font-semibold tabular-nums">{formatCurrency(history.summary.total_paid, currency)}</p></div></div><div className="min-h-0 flex-1 overflow-y-auto">{historyLoading ? <div className="text-muted-foreground flex h-40 items-center justify-center gap-2 text-sm"><Loader2 className="size-4 animate-spin" />Loading history…</div> : history.transactions.length === 0 ? <div className="text-muted-foreground px-6 py-12 text-center text-sm">No payment history yet.</div> : <div className="divide-y">{history.transactions.map((transaction) => <article key={transaction.id} className="space-y-2 px-5 py-4"><div className="flex justify-between gap-3"><div><p className="font-mono text-sm font-semibold">#{transaction.transaction_number || transaction.id}</p><p className="text-muted-foreground mt-1 text-xs">{transaction.date || "Date unavailable"} · {transaction.payment_method || "Unspecified"}</p></div><p className="font-bold tabular-nums">{formatCurrency(transaction.amount, currency)}</p></div><Button variant="ghost" size="sm" asChild className="px-0"><Link href={transaction.receipt_url}><ExternalLink className="mr-2 size-3.5" />Open receipt</Link></Button></article>)}</div>}</div></aside>}
        </div>
    );
}
