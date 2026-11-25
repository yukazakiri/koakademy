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
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { DropdownMenuItem } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { router, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, Ban, CheckCircle2, Pencil, ShieldAlert, Undo2 } from "lucide-react";
import React, { useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import type { Branding, EnrollmentData, RecentDeletion } from "./types";

export function EditTransactionDialog({
    enrollmentId,
    transaction,
}: {
    enrollmentId: number;
    transaction: EnrollmentData["transactions"][number];
}) {
    const { data, setData, patch, processing, errors } = useForm({
        amount: transaction.amount || transaction.total_amount || 0,
        invoicenumber: transaction.invoicenumber || "",
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route("administrators.enrollments.transactions.update", [enrollmentId, transaction.id]), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Transaction updated");
            },
            onError: () => toast.error("Failed to update transaction"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground h-6 w-6">
                    <Pencil className="h-3 w-3" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Transaction</DialogTitle>
                    <DialogDescription>Modify amount and invoice number. Use with caution.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="tx-invoice">Invoice Number</Label>
                        <Input id="tx-invoice" value={data.invoicenumber} onChange={(e) => setData("invoicenumber", e.target.value)} required />
                        {errors.invoicenumber && <p className="text-xs text-red-500">{errors.invoicenumber}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tx-amount">Amount</Label>
                        <Input
                            id="tx-amount"
                            type="number"
                            step="0.01"
                            value={data.amount}
                            onChange={(e) => setData("amount", parseFloat(e.target.value))}
                            required
                        />
                        {errors.amount && <p className="text-xs text-red-500">{errors.amount}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type SeparateFeeTransactionField = `separate_fee_${number}_transaction`;
type CashierPaymentForm = {
    transition_key: string | null;
    invoicenumber: string;
    payment_method: string;
    reason: string;
    settlements: {
        registration_fee: number;
        tuition_fee: number;
        miscelanous_fee: number;
        others: number;
    };
} & Partial<Record<SeparateFeeTransactionField, string>>;

export function VerifyCashierDialog({
    enrollmentId,
    tuition,
    additionalFees,
    label = "Verify & Enroll",
    transitionKey,
    requiresReason = false,
    receiptMode = "required",
    allowedPaymentMethods = [],
    buttonClassName,
}: {
    enrollmentId: number;
    tuition: EnrollmentData["tuition"];
    additionalFees: EnrollmentData["additional_fees"];
    label?: string;
    transitionKey?: string;
    requiresReason?: boolean;
    receiptMode?: "required" | "optional" | "none";
    allowedPaymentMethods?: string[];
    buttonClassName?: string;
}) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const outstandingBalance = Number(tuition?.balance_due ?? tuition?.total_balance ?? 0);
    const requiredPayment = Number(tuition?.required_downpayment ?? tuition?.downpayment ?? 5000);
    const separateTransactionFees = additionalFees.filter((fee) => fee.is_separate_transaction);
    const separateFeesTotal = separateTransactionFees.reduce((total, fee) => total + Number(fee.amount), 0);
    const mainRequiredPayment = Math.max(0, requiredPayment - separateFeesTotal);
    const mainOutstandingBalance = Math.max(0, outstandingBalance - separateFeesTotal);
    const defaultTuitionPayment = Math.min(mainRequiredPayment, mainOutstandingBalance);
    const separateFeeTransactionFields = Object.fromEntries(
        separateTransactionFees.map((fee) => [`separate_fee_${fee.id}_transaction`, fee.transaction_number ?? ""]),
    ) as Partial<Record<SeparateFeeTransactionField, string>>;
    const { data, setData, post, processing, errors, reset } = useForm<CashierPaymentForm>({
        transition_key: transitionKey ?? null,
        invoicenumber: "",
        payment_method: allowedPaymentMethods[0] ?? "Cash",
        reason: "",
        settlements: {
            registration_fee: 0,
            tuition_fee: defaultTuitionPayment,
            miscelanous_fee: 0,
            others: 0,
        },
        ...separateFeeTransactionFields,
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("administrators.enrollments.verify-cashier", enrollmentId), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Successfully enrolled student");
                reset();
            },
            onError: () => toast.error("Failed to process enrollment"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className={cn("h-10 w-full bg-emerald-600 shadow-sm hover:bg-emerald-700", buttonClassName)}>
                    <CheckCircle2 className="mr-2 h-4 w-4" />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Cashier Verification</DialogTitle>
                    <DialogDescription>Confirm the actual payment received to finalize enrollment.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="invoice">Invoice / OR Number</Label>
                        <Input
                            id="invoice"
                            required={receiptMode === "required"}
                            placeholder="Enter Invoice No."
                            value={data.invoicenumber}
                            onChange={(e) => setData("invoicenumber", e.target.value)}
                        />
                        {errors.invoicenumber && <p className="text-xs text-red-500">{errors.invoicenumber}</p>}
                    </div>

                    {separateTransactionFees.length > 0 && (
                        <div className="space-y-3 rounded-lg border p-3">
                            <div>
                                <p className="text-sm font-medium">Separate fee receipts</p>
                                <p className="text-muted-foreground text-xs">Each fee is recorded as its own cashier ledger transaction.</p>
                            </div>
                            {separateTransactionFees.map((fee) => {
                                const field = `separate_fee_${fee.id}_transaction` as SeparateFeeTransactionField;

                                return (
                                    <div key={fee.id} className="grid gap-2">
                                        <Label htmlFor={field} className="flex items-center justify-between gap-3">
                                            <span>{fee.fee_name} invoice / OR number</span>
                                            <span className="text-muted-foreground font-normal">
                                                {new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
                                                    style: "currency",
                                                    currency,
                                                }).format(Number(fee.amount))}
                                            </span>
                                        </Label>
                                        <Input
                                            id={field}
                                            required={receiptMode === "required"}
                                            placeholder={`Enter receipt for ${fee.fee_name}`}
                                            value={data[field] ?? ""}
                                            onChange={(event) => setData(field, event.target.value)}
                                        />
                                        {errors[field] && <p className="text-xs text-red-500">{errors[field]}</p>}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {requiresReason && (
                        <div className="grid gap-2">
                            <Label htmlFor="payment-transition-reason">Audited reason</Label>
                            <Textarea
                                id="payment-transition-reason"
                                required
                                value={data.reason}
                                onChange={(event) => setData("reason", event.target.value)}
                                placeholder="Explain why this transition is being completed"
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="payment_method">Payment Method</Label>
                        <Select value={data.payment_method} onValueChange={(val) => setData("payment_method", val)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(allowedPaymentMethods.length > 0
                                    ? allowedPaymentMethods
                                    : ["Cash", "Check", "Bank Transfer", "Credit Card", "Debit Card", "Online Payment"]
                                ).map((method) => (
                                    <SelectItem key={method} value={method}>
                                        {method}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="bg-muted/40 rounded-lg border p-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">Required Downpayment</span>
                            <span className="font-medium">
                                {new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", { style: "currency", currency }).format(
                                    mainRequiredPayment,
                                )}
                            </span>
                        </div>
                        {separateFeesTotal > 0 && (
                            <div className="mt-1 flex justify-between gap-4">
                                <span className="text-muted-foreground">Separate fees</span>
                                <span className="font-medium">
                                    {new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", { style: "currency", currency }).format(
                                        separateFeesTotal,
                                    )}
                                </span>
                            </div>
                        )}
                        <div className="mt-1 flex justify-between gap-4">
                            <span className="text-muted-foreground">Current Balance</span>
                            <span className="font-medium">
                                {new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", { style: "currency", currency }).format(
                                    outstandingBalance,
                                )}
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Payment Amount</Label>
                        <div className="relative">
                            <span className="text-muted-foreground absolute top-2.5 left-3">{currency === "USD" ? "$" : "₱"}</span>
                            <Input
                                type="number"
                                className="pl-7"
                                value={data.settlements.tuition_fee}
                                onChange={(e) => setData("settlements", { ...data.settlements, tuition_fee: parseFloat(e.target.value) || 0 })}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Confirm Enrollment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function EnrollClassButton({
    enrollmentId,
    classId,
    isFull,
    subjectCode,
}: {
    enrollmentId: number;
    classId: number;
    isFull: boolean;
    subjectCode: string;
}) {
    const [processing, setProcessing] = useState(false);
    const [confirmForceOpen, setConfirmForceOpen] = useState(false);

    const submitEnroll = () => {
        setConfirmForceOpen(false);
        router.post(
            route("administrators.enrollments.enroll-class", enrollmentId),
            {
                class_id: classId,
                force_enrollment: isFull,
            },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => toast.success(`Enrolled in ${subjectCode}`),
                onError: () => toast.error("Failed to enroll"),
            },
        );
    };

    const handleEnroll = () => {
        if (isFull) {
            setConfirmForceOpen(true);
            return;
        }
        submitEnroll();
    };

    return (
        <>
            <Button size="sm" variant={isFull ? "destructive" : "secondary"} onClick={handleEnroll} disabled={processing} className="shadow-sm">
                {isFull ? "Force Enroll" : "Enroll"}
            </Button>
            <AlertDialog open={confirmForceOpen} onOpenChange={setConfirmForceOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Force enroll in full class?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This section for <span className="text-foreground font-medium">{subjectCode}</span> has already reached its maximum
                            slot capacity. Force enrolling will exceed the configured limit.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={submitEnroll}
                        >
                            Force Enroll
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export function QuickEnrollDialog({ enrollmentId }: { enrollmentId: number }) {
    const { data, setData, post, processing, reset } = useForm({
        remarks: "",
        confirm_emergency: false,
        confirm_payment: false,
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("administrators.enrollments.quick-enroll", enrollmentId), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Quick enrollment successful");
                reset();
            },
            onError: () => toast.error("Quick enrollment failed"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                    <ShieldAlert className="mr-2 h-4 w-4" /> Quick Enroll (Super Admin)
                </DropdownMenuItem>
            </DialogTrigger>
            <DialogContent className="border-red-200 sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <ShieldAlert className="h-5 w-5" /> Emergency Quick Enroll
                    </DialogTitle>
                    <DialogDescription>Bypass all verification steps. Use only in exceptional circumstances.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="remarks">Justification / Remarks</Label>
                        <Textarea
                            id="remarks"
                            required
                            placeholder="Explain why verification is being bypassed..."
                            value={data.remarks}
                            onChange={(e) => setData("remarks", e.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="confirm_emergency"
                            className="rounded border-gray-300"
                            checked={data.confirm_emergency}
                            onChange={(e) => setData("confirm_emergency", e.target.checked)}
                            required
                        />
                        <Label htmlFor="confirm_emergency" className="text-xs">
                            I confirm this is an emergency enrollment
                        </Label>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="confirm_payment"
                            className="rounded border-gray-300"
                            checked={data.confirm_payment}
                            onChange={(e) => setData("confirm_payment", e.target.checked)}
                            required
                        />
                        <Label htmlFor="confirm_payment" className="text-xs">
                            I confirm payment has been verified manually
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            Quick Enroll
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function CancelEnrollmentDialog({
    enrollmentId,
    studentName,
    outstandingPayments,
    formatMoney,
}: {
    enrollmentId: number;
    studentName: string;
    outstandingPayments: { transactions: number; amount: number };
    formatMoney: (amount: number | null | undefined) => string;
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState("");
    const [acknowledgePayments, setAcknowledgePayments] = useState(false);
    const [processing, setProcessing] = useState(false);
    const hasPayments = outstandingPayments.transactions > 0;
    const idempotencyKey = () => (typeof crypto.randomUUID === "function" ? crypto.randomUUID() : `cancel-${Date.now()}-${Math.random()}`);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        axios
            .post(route("administrators.enrollments.cancel", enrollmentId), {
                reason,
                idempotency_key: idempotencyKey(),
                acknowledge_outstanding_payments: acknowledgePayments,
            })
            .then(() => {
                setOpen(false);
                setReason("");
                toast.success("Enrollment cancelled");
                router.reload({ only: ["enrollment"] });
            })
            .catch((error: unknown) => {
                const message = axios.isAxiosError(error)
                    ? ((error.response?.data?.message as string | undefined) ??
                      (Object.values(error.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0])
                    : null;
                toast.error(message ?? "The enrollment could not be cancelled.");
            })
            .finally(() => setProcessing(false));
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="text-red-600 focus:text-red-600">
                    <Ban className="mr-2 h-4 w-4" /> Cancel enrollment
                </DropdownMenuItem>
            </DialogTrigger>
            <DialogContent className="border-red-200 sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <Ban className="h-5 w-5" /> Cancel this enrollment
                    </DialogTitle>
                    <DialogDescription>
                        Cancelling stops this enrollment for {studentName}, including an enrollment already verified by cashier. The outcome is
                        recorded in the enrollment history and the student stops counting as enrolled for this term. Use &ldquo;Undo
                        cancellation&rdquo; for a recorded error. Released class seats must be reassigned separately.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="cancel-reason">Reason (required)</Label>
                        <Textarea
                            id="cancel-reason"
                            required
                            rows={3}
                            placeholder="Why is this enrollment being cancelled? e.g. applicant withdrew, duplicate application."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">
                            Reserved class seats held by this enrollment are released. Subjects, tuition, and the reporting status are kept as
                            history. Completed student/account status is not demoted; finance handles any refund or balance correction separately.
                        </p>
                    </div>

                    {hasPayments && (
                        <div className="space-y-2 rounded-lg border border-red-200 bg-red-50/60 p-3 dark:bg-red-950/30">
                            <div className="flex items-start gap-2 text-sm">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                                <span>
                                    {formatMoney(outstandingPayments.amount)} across {outstandingPayments.transactions} recorded payment reference(s)
                                    is still associated. Cancelling does not refund it.
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="cancel-acknowledge-payments"
                                    checked={acknowledgePayments}
                                    onCheckedChange={(checked) => setAcknowledgePayments(checked === true)}
                                />
                                <Label htmlFor="cancel-acknowledge-payments" className="text-xs font-normal">
                                    I will handle the refund separately in the finance module.
                                </Label>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Keep enrollment
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing || (hasPayments && !acknowledgePayments)}>
                            {processing ? "Cancelling…" : "Cancel enrollment"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function RestoreSubjectsDialog({
    open,
    onOpenChange,
    recentDeletions,
    selectedDeletions,
    onToggleSelection,
    onSelectAll,
    onRestore,
    restoring,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    recentDeletions: RecentDeletion[];
    selectedDeletions: number[];
    onToggleSelection: (id: number) => void;
    onSelectAll: () => void;
    onRestore: () => void;
    restoring: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[550px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Undo2 className="h-5 w-5" /> Restore Deleted Subjects
                    </DialogTitle>
                    <DialogDescription>
                        Select subjects to restore. This will recreate the subject enrollments and attempt to re-enroll in their original classes.
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-[300px] space-y-2 overflow-y-auto py-4">
                    <div className="flex items-center gap-2 pb-2">
                        <Checkbox
                            id="select-all"
                            checked={selectedDeletions.length === recentDeletions.length && recentDeletions.length > 0}
                            onCheckedChange={onSelectAll}
                        />
                        <label htmlFor="select-all" className="text-sm font-medium">
                            Select All ({recentDeletions.length} items)
                        </label>
                    </div>
                    <Separator />
                    {recentDeletions.map((deletion) => (
                        <div key={deletion.id} className="hover:bg-muted/50 flex items-start gap-3 rounded-lg border p-3 transition-colors">
                            <Checkbox
                                id={`deletion-${deletion.id}`}
                                checked={selectedDeletions.includes(deletion.id)}
                                onCheckedChange={() => onToggleSelection(deletion.id)}
                                className="mt-0.5"
                            />
                            <div className="flex-1">
                                <div className="flex items-center justify-between">
                                    <label htmlFor={`deletion-${deletion.id}`} className="cursor-pointer font-medium">
                                        {deletion.subject_code}
                                    </label>
                                    <span className="text-muted-foreground text-xs">{deletion.created_at_human}</span>
                                </div>
                                <p className="text-muted-foreground text-sm">{deletion.subject_title}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Deleted by: {deletion.causer}
                                    {deletion.class_id && ` | Class ID: ${deletion.class_id}`}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={onRestore} disabled={restoring || selectedDeletions.length === 0}>
                        <Undo2 className="mr-2 h-4 w-4" />
                        Restore {selectedDeletions.length} Subject{selectedDeletions.length !== 1 ? "s" : ""}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
