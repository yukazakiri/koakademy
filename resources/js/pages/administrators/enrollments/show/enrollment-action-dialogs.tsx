import {
    destroy as destroyEnrollment,
    enrollInClass,
    forceDestroy as forceDestroyEnrollment,
    quickEnroll,
    restore as restoreEnrollment,
    restoreSubjects,
    undoCancellation,
    updateTransaction,
    verifyCashier,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import {
    cancel as cancelEnrollmentPolicy,
    reopen as reopenEnrollmentPolicy,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentPolicyController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from "@/components/reui/alert-dialog";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { router, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    Pencil,
    Printer,
    RotateCcw,
    ShieldAlert,
    Trash2,
    Undo2,
} from "lucide-react";
import React, { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { formatMoney as formatCurrency } from "../enrollment-utils";
import type { Branding, EnrollmentData, RecentDeletion } from "./types";

type FlashPayload = {
    error?: string;
    success?: string;
    warning?: string;
    info?: string;
};

function readFlash(page: { props: unknown }): FlashPayload | undefined {
    const props = page.props as { flash?: FlashPayload | null } | undefined;
    return props?.flash ?? undefined;
}

function createIdempotencyKey(prefix: string): string {
    if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
        return crypto.randomUUID();
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function parseNonNegativeInput(raw: string): number {
    if (raw.trim() === "") {
        return 0;
    }

    const parsed = Number(raw);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }

    return parsed;
}

function extractAxiosErrorMessage(error: unknown, fallback: string): string {
    if (!axios.isAxiosError(error)) {
        return fallback;
    }

    const data = error.response?.data as
        | { message?: string; errors?: Record<string, string[] | string> }
        | undefined;

    if (data?.message) {
        return data.message;
    }

    const firstError = Object.values(data?.errors ?? {})[0];
    if (Array.isArray(firstError) && firstError[0]) {
        return firstError[0];
    }

    if (typeof firstError === "string" && firstError.length > 0) {
        return firstError;
    }

    return fallback;
}

export interface EditTransactionDialogProps {
    enrollmentId: number;
    transaction: EnrollmentData["transactions"][number];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    hideTrigger?: boolean;
}

export function EditTransactionDialog({
    enrollmentId,
    transaction,
    open: controlledOpen,
    onOpenChange,
    hideTrigger = false,
}: EditTransactionDialogProps) {
    const initialAmount = transaction.amount ?? transaction.total_amount ?? 0;
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isControlled = controlledOpen !== undefined;
    const isOpen = isControlled ? controlledOpen : uncontrolledOpen;
    const setIsOpen = (next: boolean) => {
        if (!isControlled) {
            setUncontrolledOpen(next);
        }
        onOpenChange?.(next);
    };

    const [amountInput, setAmountInput] = useState<string>(String(initialAmount));
    const [localAmountError, setLocalAmountError] = useState<string | null>(null);

    const { data, setData, patch, processing, errors, clearErrors } = useForm({
        amount: initialAmount,
        invoicenumber: transaction.invoicenumber ?? "",
    });

    useEffect(() => {
        const syncedAmount = transaction.amount ?? transaction.total_amount ?? 0;
        setAmountInput(String(syncedAmount));
        setLocalAmountError(null);
        setData({
            amount: syncedAmount,
            invoicenumber: transaction.invoicenumber ?? "",
        });
        clearErrors();
    }, [
        transaction.id,
        transaction.amount,
        transaction.total_amount,
        transaction.invoicenumber,
        isOpen,
    ]);

    const handleAmountChange = (raw: string) => {
        setAmountInput(raw);
        if (raw.trim() === "") {
            setLocalAmountError("Amount is required.");
            return;
        }
        const parsed = Number(raw);
        if (!Number.isFinite(parsed) || parsed < 0) {
            setLocalAmountError("Enter a valid non-negative amount.");
            return;
        }
        setLocalAmountError(null);
        setData("amount", parsed);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const parsed = Number(amountInput);
        if (amountInput.trim() === "" || !Number.isFinite(parsed) || parsed < 0) {
            setLocalAmountError("Enter a valid non-negative amount.");
            return;
        }

        setData("amount", parsed);
        patch(updateTransaction.url({ enrollment: enrollmentId, transaction: transaction.id }), {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = readFlash(page);
                if (!flash?.error) {
                    setIsOpen(false);
                }
            },
            onError: () => {
                toast.error("Failed to update transaction.");
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {!hideTrigger && (
                <DialogTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-foreground size-7"
                        aria-label={`Edit transaction ${transaction.invoicenumber ?? transaction.id}`}
                    >
                        <Pencil className="size-3.5" />
                    </Button>
                </DialogTrigger>
            )}
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Transaction</DialogTitle>
                    <DialogDescription>
                        Modify the recorded amount and invoice reference. Changes recalculate the tuition ledger balance.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`tx-invoice-${transaction.id}`}>Invoice / OR Number</Label>
                        <Input
                            id={`tx-invoice-${transaction.id}`}
                            value={data.invoicenumber}
                            onChange={(e) => setData("invoicenumber", e.target.value)}
                            required
                        />
                        {errors.invoicenumber && (
                            <p className="text-destructive text-xs">{errors.invoicenumber}</p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`tx-amount-${transaction.id}`}>Amount</Label>
                        <Input
                            id={`tx-amount-${transaction.id}`}
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0"
                            className="tabular-nums"
                            value={amountInput}
                            onChange={(e) => handleAmountChange(e.target.value)}
                            required
                        />
                        {(localAmountError || errors.amount) && (
                            <p className="text-destructive text-xs">{localAmountError ?? errors.amount}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setIsOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || Boolean(localAmountError)}>
                            {processing ? "Saving…" : "Save Changes"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type SeparateFeeTransactionField = `separate_fee_${number}_transaction`;
type SettlementKey = "registration_fee" | "tuition_fee" | "miscelanous_fee" | "others";

type CashierPaymentForm = {
    transition_key: string | null;
    invoicenumber: string;
    payment_method: string;
    reason: string;
    settlements: Record<SettlementKey, number>;
} & Partial<Record<SeparateFeeTransactionField, string>>;

export interface VerifyCashierDialogProps {
    enrollmentId: number;
    tuition: EnrollmentData["tuition"];
    additionalFees: EnrollmentData["additional_fees"];
    label?: string;
    transitionKey?: string;
    requiresReason?: boolean;
    receiptMode?: "required" | "optional" | "none";
    allowedPaymentMethods?: string[];
    assessmentPreviewUrl?: string;
    buttonClassName?: string;
    buttonSize?: "default" | "sm" | "lg";
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    hideTrigger?: boolean;
}

export function VerifyCashierDialog({
    enrollmentId,
    tuition,
    additionalFees,
    label = "Verify Payment & Enroll",
    transitionKey,
    requiresReason = false,
    receiptMode = "required",
    allowedPaymentMethods = [],
    assessmentPreviewUrl,
    buttonClassName,
    buttonSize = "default",
    open: controlledOpen,
    onOpenChange,
    hideTrigger = false,
}: VerifyCashierDialogProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const currencySymbol = currency === "USD" ? "$" : "₱";

    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isControlled = controlledOpen !== undefined;
    const isOpen = isControlled ? controlledOpen : uncontrolledOpen;
    const setIsOpen = (next: boolean) => {
        if (!isControlled) {
            setUncontrolledOpen(next);
        }
        onOpenChange?.(next);
    };

    const [printAfterVerify, setPrintAfterVerify] = useState(false);

    const paymentMethods = useMemo(
        () =>
            allowedPaymentMethods.length > 0
                ? allowedPaymentMethods
                : ["Cash", "Check", "Bank Transfer", "Credit Card", "Debit Card", "Online Payment"],
        [allowedPaymentMethods],
    );

    const outstandingBalance = Number(tuition?.balance_due ?? tuition?.total_balance ?? 0);
    const requiredPayment = Number(tuition?.required_downpayment ?? tuition?.downpayment ?? 0);

    const separateTransactionFees = useMemo(
        () => additionalFees.filter((fee) => fee.is_separate_transaction),
        [additionalFees],
    );
    const separateFeesTotal = useMemo(
        () => separateTransactionFees.reduce((total, fee) => total + Number(fee.amount ?? 0), 0),
        [separateTransactionFees],
    );

    const mainRequiredPayment = Math.max(0, requiredPayment - separateFeesTotal);
    const mainOutstandingBalance = Math.max(0, outstandingBalance - separateFeesTotal);
    const defaultTuitionPayment =
        receiptMode === "none" ? 0 : Math.min(mainRequiredPayment, mainOutstandingBalance);

    const buildInitialForm = (): CashierPaymentForm => {
        const separateFields = Object.fromEntries(
            separateTransactionFees.map((fee) => [
                `separate_fee_${fee.id}_transaction`,
                fee.transaction_number ?? "",
            ]),
        ) as Partial<Record<SeparateFeeTransactionField, string>>;

        return {
            transition_key: transitionKey ?? null,
            invoicenumber: "",
            payment_method: paymentMethods[0] ?? "Cash",
            reason: "",
            settlements: {
                registration_fee: 0,
                tuition_fee: defaultTuitionPayment,
                miscelanous_fee: 0,
                others: 0,
            },
            ...separateFields,
        };
    };

    const { data, setData, post, processing, errors, clearErrors } = useForm<CashierPaymentForm>(
        buildInitialForm(),
    );

    const [settlementInputs, setSettlementInputs] = useState<Record<SettlementKey, string>>({
        registration_fee: "0",
        tuition_fee: String(defaultTuitionPayment),
        miscelanous_fee: "0",
        others: "0",
    });

    const separateFeesSignature = separateTransactionFees
        .map((fee) => `${fee.id}:${fee.transaction_number ?? ""}`)
        .join("|");

    useEffect(() => {
        if (!isOpen) return;

        const nextForm = buildInitialForm();
        setData(nextForm);
        setSettlementInputs({
            registration_fee: "0",
            tuition_fee: String(defaultTuitionPayment),
            miscelanous_fee: "0",
            others: "0",
        });
        clearErrors();
    }, [
        isOpen,
        enrollmentId,
        transitionKey,
        receiptMode,
        defaultTuitionPayment,
        paymentMethods[0],
        separateFeesSignature,
    ]);

    const handleSettlementChange = (key: SettlementKey, raw: string) => {
        setSettlementInputs((prev) => ({ ...prev, [key]: raw }));
        const numeric = parseNonNegativeInput(raw);
        setData("settlements", {
            ...data.settlements,
            [key]: numeric,
        });
    };

    const totalSettlementAmount =
        Number(data.settlements.registration_fee || 0) +
        Number(data.settlements.tuition_fee || 0) +
        Number(data.settlements.miscelanous_fee || 0) +
        Number(data.settlements.others || 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(verifyCashier.url(enrollmentId), {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = readFlash(page);
                if (!flash?.error) {
                    setIsOpen(false);
                    if (printAfterVerify && assessmentPreviewUrl) {
                        window.open(assessmentPreviewUrl, "_blank", "noopener,noreferrer");
                    }
                }
            },
            onError: () => {
                toast.error("Could not verify cashier payment. Please review the highlighted fields.");
            },
        });
    };

    const errorMap = errors as Record<string, string | undefined>;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {!hideTrigger && (
                <DialogTrigger asChild>
                    <Button
                        type="button"
                        size={buttonSize}
                        className={cn(
                            "bg-emerald-600 text-white shadow-sm hover:bg-emerald-700",
                            buttonClassName,
                        )}
                    >
                        <CheckCircle2 className="mr-1.5 size-4 shrink-0" />
                        <span className="truncate">{label}</span>
                    </Button>
                </DialogTrigger>
            )}
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[540px]">
                <DialogHeader>
                    <DialogTitle>Cashier Payment Verification</DialogTitle>
                    <DialogDescription>
                        Confirm payment details and settlement allocations to advance enrollment.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-1">
                    {receiptMode === "none" ? (
                        <Alert variant="info">
                            <CheckCircle2 className="size-4" />
                            <AlertTitle>No receipt required</AlertTitle>
                            <AlertDescription>
                                This policy transition completes payment verification without an official receipt or invoice number.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="cashier-invoice">Invoice / OR Number</Label>
                                {receiptMode === "optional" && (
                                    <Badge variant="secondary" size="xs">
                                        Optional
                                    </Badge>
                                )}
                            </div>
                            <Input
                                id="cashier-invoice"
                                required={receiptMode === "required"}
                                placeholder="Enter official receipt or invoice number"
                                value={data.invoicenumber}
                                onChange={(e) => setData("invoicenumber", e.target.value)}
                            />
                            {errors.invoicenumber && (
                                <p className="text-destructive text-xs">{errors.invoicenumber}</p>
                            )}
                        </div>
                    )}

                    {receiptMode !== "none" && separateTransactionFees.length > 0 && (
                        <div className="bg-muted/30 space-y-3 rounded-lg border p-3">
                            <div>
                                <p className="text-sm font-medium">Separate fee receipts</p>
                                <p className="text-muted-foreground text-xs">
                                    Each fee below is recorded as its own cashier ledger transaction.
                                </p>
                            </div>
                            {separateTransactionFees.map((fee) => {
                                const field = `separate_fee_${fee.id}_transaction` as SeparateFeeTransactionField;
                                return (
                                    <div key={fee.id} className="grid gap-1.5">
                                        <Label
                                            htmlFor={field}
                                            className="flex items-center justify-between gap-3 text-xs"
                                        >
                                            <span className="truncate">{fee.fee_name}</span>
                                            <span className="text-muted-foreground shrink-0 font-normal tabular-nums">
                                                {formatCurrency(fee.amount, currency)}
                                            </span>
                                        </Label>
                                        <Input
                                            id={field}
                                            required={receiptMode === "required"}
                                            placeholder={`Receipt for ${fee.fee_name}`}
                                            value={data[field] ?? ""}
                                            onChange={(event) => setData(field, event.target.value)}
                                        />
                                        {errorMap[field] && (
                                            <p className="text-destructive text-xs">{errorMap[field]}</p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="cashier-payment-method">Payment Method</Label>
                        <Select
                            value={data.payment_method}
                            onValueChange={(val) => setData("payment_method", val)}
                        >
                            <SelectTrigger id="cashier-payment-method">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {paymentMethods.map((method) => (
                                    <SelectItem key={method} value={method}>
                                        {method}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.payment_method && (
                            <p className="text-destructive text-xs">{errors.payment_method}</p>
                        )}
                    </div>

                    <div className="bg-muted/40 space-y-1.5 rounded-lg border p-3 text-xs">
                        <div className="flex items-center justify-between gap-4">
                            <span className="text-muted-foreground">Required Downpayment</span>
                            <span className="font-medium tabular-nums">
                                {formatCurrency(mainRequiredPayment, currency)}
                            </span>
                        </div>
                        {separateFeesTotal > 0 && (
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">Separate Transaction Fees</span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(separateFeesTotal, currency)}
                                </span>
                            </div>
                        )}
                        <div className="flex items-center justify-between gap-4">
                            <span className="text-muted-foreground">Current Balance Due</span>
                            <span className="font-semibold tabular-nums">
                                {formatCurrency(outstandingBalance, currency)}
                            </span>
                        </div>
                    </div>

                    <div className="space-y-2.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label className="text-xs font-semibold tracking-wide uppercase">
                                Settlement Allocation
                            </Label>
                            <span className="text-xs font-semibold tabular-nums">
                                Total: {formatCurrency(totalSettlementAmount, currency)}
                            </span>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {(
                                [
                                    { key: "tuition_fee", label: "Tuition Fee" },
                                    { key: "registration_fee", label: "Registration Fee" },
                                    { key: "miscelanous_fee", label: "Miscellaneous Fee" },
                                    { key: "others", label: "Other Fees" },
                                ] as const
                            ).map((field) => {
                                const errorKey = `settlements.${field.key}`;
                                return (
                                    <div key={field.key} className="grid gap-1.5">
                                        <Label
                                            htmlFor={`settlement-${field.key}`}
                                            className="text-muted-foreground text-xs"
                                        >
                                            {field.label}
                                        </Label>
                                        <div className="relative">
                                            <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs">
                                                {currencySymbol}
                                            </span>
                                            <Input
                                                id={`settlement-${field.key}`}
                                                type="number"
                                                inputMode="decimal"
                                                step="0.01"
                                                min="0"
                                                className="pl-7 tabular-nums"
                                                value={settlementInputs[field.key]}
                                                onChange={(e) =>
                                                    handleSettlementChange(field.key, e.target.value)
                                                }
                                            />
                                        </div>
                                        {errorMap[errorKey] && (
                                            <p className="text-destructive text-xs">
                                                {errorMap[errorKey]}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                        {errorMap.settlements && (
                            <p className="text-destructive text-xs">{errorMap.settlements}</p>
                        )}
                    </div>

                    {(requiresReason || Boolean(errors.reason)) && (
                        <div className="grid gap-2">
                            <Label htmlFor="payment-transition-reason">
                                Audited Reason {requiresReason ? "(required)" : ""}
                            </Label>
                            <Textarea
                                id="payment-transition-reason"
                                required={requiresReason}
                                rows={2}
                                value={data.reason}
                                onChange={(event) => setData("reason", event.target.value)}
                                placeholder="Explain why this payment verification is being completed"
                            />
                            {errors.reason && (
                                <p className="text-destructive text-xs">{errors.reason}</p>
                            )}
                        </div>
                    )}

                    {assessmentPreviewUrl && (
                        <div className="flex items-center gap-2 rounded-lg border px-3 py-2">
                            <Checkbox
                                id="print-after-verify"
                                checked={printAfterVerify}
                                onCheckedChange={(checked) => setPrintAfterVerify(checked === true)}
                            />
                            <Label
                                htmlFor="print-after-verify"
                                className="flex cursor-pointer items-center gap-1.5 text-xs font-normal"
                            >
                                <Printer className="text-muted-foreground size-3.5" />
                                Open printable assessment form in a new tab after verification
                            </Label>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setIsOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            {processing ? "Verifying…" : "Confirm Verification"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export interface EnrollClassButtonProps {
    enrollmentId: number;
    classId: number;
    isFull: boolean;
    subjectCode: string;
}

export function EnrollClassButton({
    enrollmentId,
    classId,
    isFull,
    subjectCode,
}: EnrollClassButtonProps) {
    const [processing, setProcessing] = useState(false);
    const [confirmForceOpen, setConfirmForceOpen] = useState(false);

    const submitEnroll = () => {
        setConfirmForceOpen(false);
        router.post(
            enrollInClass.url(enrollmentId),
            {
                class_id: classId,
                force_enrollment: isFull,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: () => toast.error(`Failed to enroll in ${subjectCode}.`),
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
            <Button
                type="button"
                size="sm"
                variant={isFull ? "destructive" : "secondary"}
                onClick={handleEnroll}
                disabled={processing}
            >
                {processing ? "Enrolling…" : isFull ? "Force Enroll" : "Enroll"}
            </Button>

            <AlertDialog open={confirmForceOpen} onOpenChange={setConfirmForceOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia className="bg-destructive/10 text-destructive">
                            <AlertTriangle className="size-5" />
                        </AlertDialogMedia>
                        <AlertDialogTitle>Force enroll in full class?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This section for{" "}
                            <span className="text-foreground font-medium">{subjectCode}</span> has
                            already reached its maximum slot capacity. Force enrolling will exceed
                            the configured limit.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={submitEnroll}>
                            Force Enroll
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export interface QuickEnrollDialogProps {
    enrollmentId: number;
    studentName?: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function QuickEnrollDialog({
    enrollmentId,
    studentName,
    open,
    onOpenChange,
}: QuickEnrollDialogProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        remarks: "",
        confirm_emergency: false,
        confirm_payment: false,
    });

    useEffect(() => {
        if (!open) {
            reset();
            clearErrors();
        }
    }, [open, enrollmentId]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(quickEnroll.url(enrollmentId), {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = readFlash(page);
                if (!flash?.error) {
                    onOpenChange(false);
                    reset();
                }
            },
            onError: () => toast.error("Emergency quick enrollment failed."),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-destructive/30 sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="text-destructive flex items-center gap-2">
                        <ShieldAlert className="size-5 shrink-0" />
                        Emergency Quick Enroll
                    </DialogTitle>
                    <DialogDescription>
                        Bypass intermediate verification steps{studentName ? ` for ${studentName}` : ""}.
                        Use only in authorized exceptional circumstances.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-1">
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Super Admin Audit Override</AlertTitle>
                        <AlertDescription>
                            This action is logged permanently with your user account and remarks.
                        </AlertDescription>
                    </Alert>

                    <div className="grid gap-2">
                        <Label htmlFor="quick-enroll-remarks">Justification / Remarks</Label>
                        <Textarea
                            id="quick-enroll-remarks"
                            required
                            rows={3}
                            placeholder="Explain why standard verification steps are being bypassed…"
                            value={data.remarks}
                            onChange={(e) => setData("remarks", e.target.value)}
                        />
                        {errors.remarks && (
                            <p className="text-destructive text-xs">{errors.remarks}</p>
                        )}
                    </div>

                    <div className="space-y-2.5 rounded-lg border p-3">
                        <div className="flex items-start gap-2.5">
                            <Checkbox
                                id="confirm-emergency"
                                checked={data.confirm_emergency}
                                onCheckedChange={(checked) =>
                                    setData("confirm_emergency", checked === true)
                                }
                            />
                            <Label
                                htmlFor="confirm-emergency"
                                className="cursor-pointer text-xs leading-snug font-normal"
                            >
                                I confirm this is an authorized emergency enrollment override.
                            </Label>
                        </div>
                        <div className="flex items-start gap-2.5">
                            <Checkbox
                                id="confirm-payment"
                                checked={data.confirm_payment}
                                onCheckedChange={(checked) =>
                                    setData("confirm_payment", checked === true)
                                }
                            />
                            <Label
                                htmlFor="confirm-payment"
                                className="cursor-pointer text-xs leading-snug font-normal"
                            >
                                I confirm payment or financial clearance has been verified manually.
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={
                                processing ||
                                !data.remarks.trim() ||
                                !data.confirm_emergency ||
                                !data.confirm_payment
                            }
                        >
                            {processing ? "Processing…" : "Confirm Quick Enroll"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export interface CancelEnrollmentDialogProps {
    enrollmentId: number;
    studentName: string;
    outstandingPayments: { transactions: number; amount: number };
    currency?: string;
    formatMoney?: (amount: number | null | undefined) => string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function CancelEnrollmentDialog({
    enrollmentId,
    studentName,
    outstandingPayments,
    currency = "PHP",
    formatMoney,
    open,
    onOpenChange,
}: CancelEnrollmentDialogProps) {
    const [reason, setReason] = useState("");
    const [acknowledgePayments, setAcknowledgePayments] = useState(false);
    const [processing, setProcessing] = useState(false);

    const hasPayments = outstandingPayments.transactions > 0;
    const renderMoney = (amount: number | null | undefined) =>
        formatMoney ? formatMoney(amount) : formatCurrency(amount, currency);

    useEffect(() => {
        if (!open) {
            setReason("");
            setAcknowledgePayments(false);
        }
    }, [open, enrollmentId]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reason.trim()) return;

        setProcessing(true);
        axios
            .post(cancelEnrollmentPolicy.url(enrollmentId), {
                reason: reason.trim(),
                idempotency_key: createIdempotencyKey("cancel"),
                acknowledge_outstanding_payments: acknowledgePayments,
            })
            .then(() => {
                onOpenChange(false);
                setReason("");
                setAcknowledgePayments(false);
                toast.success("Enrollment cancelled.");
                router.reload({ only: ["enrollment", "enrollment_pipeline"] });
            })
            .catch((error: unknown) => {
                toast.error(
                    extractAxiosErrorMessage(error, "The enrollment could not be cancelled."),
                );
            })
            .finally(() => setProcessing(false));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-destructive/30 sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle className="text-destructive flex items-center gap-2">
                        <Ban className="size-5 shrink-0" />
                        Cancel Enrollment
                    </DialogTitle>
                    <DialogDescription>
                        Cancelling stops this enrollment for {studentName}. Reserved class seats
                        are released while subjects and tuition remain preserved as audit history.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-1">
                    <div className="grid gap-2">
                        <Label htmlFor="cancel-reason">Cancellation Reason (required)</Label>
                        <Textarea
                            id="cancel-reason"
                            required
                            rows={3}
                            placeholder="Why is this enrollment being cancelled? e.g. applicant withdrew, duplicate record."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">
                            Finance handles any refund or balance adjustment separately. Released
                            class seats must be reassigned if the enrollment is later reopened.
                        </p>
                    </div>

                    {hasPayments && (
                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Recorded payments detected</AlertTitle>
                            <AlertDescription className="space-y-2.5">
                                <p>
                                    {renderMoney(outstandingPayments.amount)} across{" "}
                                    {outstandingPayments.transactions} payment reference
                                    {outstandingPayments.transactions === 1 ? "" : "s"} is linked to
                                    this enrollment. Cancelling does not automatically refund it.
                                </p>
                                <div className="flex items-start gap-2">
                                    <Checkbox
                                        id="cancel-acknowledge-payments"
                                        checked={acknowledgePayments}
                                        onCheckedChange={(checked) =>
                                            setAcknowledgePayments(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="cancel-acknowledge-payments"
                                        className="text-foreground cursor-pointer text-xs leading-snug font-normal"
                                    >
                                        I will handle any refund or ledger correction in Finance.
                                    </Label>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Keep Enrollment
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={
                                processing ||
                                !reason.trim() ||
                                (hasPayments && !acknowledgePayments)
                            }
                        >
                            {processing ? "Cancelling…" : "Cancel Enrollment"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export interface ReopenEnrollmentDialogProps {
    enrollment: EnrollmentData;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function ReopenEnrollmentDialog({
    enrollment,
    open,
    onOpenChange,
}: ReopenEnrollmentDialogProps) {
    const isPolicyRuntime = enrollment.workflow_runtime === "policy_v1";
    const availableSteps = enrollment.workflow.available_reopen_steps ?? [];
    const [targetStepKey, setTargetStepKey] = useState<string>(availableSteps[0]?.key ?? "");
    const [reason, setReason] = useState("");
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setTargetStepKey(availableSteps[0]?.key ?? "");
            setReason("");
        }
    }, [open, enrollment.id, availableSteps.length]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reason.trim()) return;

        if (isPolicyRuntime) {
            setProcessing(true);
            axios
                .post(reopenEnrollmentPolicy.url(enrollment.id), {
                    target_step_key: targetStepKey || null,
                    reason: reason.trim(),
                    idempotency_key: createIdempotencyKey("reopen"),
                })
                .then(() => {
                    onOpenChange(false);
                    setReason("");
                    toast.success("Enrollment reopened.");
                    router.reload({ only: ["enrollment", "enrollment_pipeline"] });
                })
                .catch((error: unknown) => {
                    toast.error(
                        extractAxiosErrorMessage(error, "The enrollment could not be reopened."),
                    );
                })
                .finally(() => setProcessing(false));
            return;
        }

        router.post(
            undoCancellation.url(enrollment.id),
            { reason: reason.trim() },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    const flash = readFlash(page);
                    if (!flash?.error) {
                        onOpenChange(false);
                        setReason("");
                    }
                },
                onError: () => toast.error("The enrollment could not be reopened."),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <RotateCcw className="size-4 shrink-0" />
                        {isPolicyRuntime ? "Reopen Enrollment" : "Undo Cancellation"}
                    </DialogTitle>
                    <DialogDescription>
                        Return {enrollment.student.full_name}&rsquo;s enrollment to an active
                        workflow stage with an audited justification.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-1">
                    {enrollment.workflow.terminal_outcome === "cancelled" && (
                        <Alert variant="warning">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Released seats are not auto-restored</AlertTitle>
                            <AlertDescription>
                                Previously released class sections must be checked and reassigned
                                after reopening.
                            </AlertDescription>
                        </Alert>
                    )}

                    {isPolicyRuntime && availableSteps.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="reopen-target-step">Target Workflow Step</Label>
                            <Select value={targetStepKey} onValueChange={setTargetStepKey}>
                                <SelectTrigger id="reopen-target-step">
                                    <SelectValue placeholder="Choose step to reopen into" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableSteps.map((step) => (
                                        <SelectItem key={step.key} value={step.key}>
                                            {step.label} ({step.status})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="reopen-reason">Audited Reason (required)</Label>
                        <Textarea
                            id="reopen-reason"
                            required
                            rows={3}
                            placeholder="Explain why this enrollment is being reopened…"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                !reason.trim() ||
                                (isPolicyRuntime && availableSteps.length > 0 && !targetStepKey)
                            }
                        >
                            {processing ? "Reopening…" : "Reopen Enrollment"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export interface RestoreSubjectsDialogProps {
    enrollmentId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    recentDeletions: RecentDeletion[];
}

export function RestoreSubjectsDialog({
    enrollmentId,
    open,
    onOpenChange,
    recentDeletions,
}: RestoreSubjectsDialogProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [restoring, setRestoring] = useState(false);

    useEffect(() => {
        if (!open) {
            setSelectedIds([]);
        }
    }, [open, enrollmentId]);

    const toggleSelection = (id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id],
        );
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === recentDeletions.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(recentDeletions.map((item) => item.id));
        }
    };

    const handleRestore = () => {
        if (selectedIds.length === 0) return;

        router.post(
            restoreSubjects.url(enrollmentId),
            { activity_ids: selectedIds },
            {
                preserveScroll: true,
                onStart: () => setRestoring(true),
                onFinish: () => setRestoring(false),
                onSuccess: (page) => {
                    const flash = readFlash(page);
                    if (!flash?.error) {
                        onOpenChange(false);
                        setSelectedIds([]);
                    }
                },
                onError: () => toast.error("Failed to restore deleted subjects."),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[550px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Undo2 className="size-4 shrink-0" />
                        Restore Deleted Subjects
                    </DialogTitle>
                    <DialogDescription>
                        Select removed subjects from the audit log to recreate their subject
                        enrollments and attempt to re-enroll in their original sections.
                    </DialogDescription>
                </DialogHeader>

                {recentDeletions.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm">
                        No recently deleted subjects are available to restore for this enrollment.
                    </div>
                ) : (
                    <div className="max-h-[320px] space-y-2 overflow-y-auto py-2">
                        <div className="flex items-center gap-2 pb-1">
                            <Checkbox
                                id="restore-select-all"
                                checked={
                                    selectedIds.length === recentDeletions.length &&
                                    recentDeletions.length > 0
                                }
                                onCheckedChange={toggleSelectAll}
                            />
                            <Label
                                htmlFor="restore-select-all"
                                className="cursor-pointer text-xs font-medium"
                            >
                                Select all ({recentDeletions.length} item
                                {recentDeletions.length === 1 ? "" : "s"})
                            </Label>
                        </div>
                        <Separator />
                        {recentDeletions.map((deletion) => (
                            <div
                                key={deletion.id}
                                className="hover:bg-muted/50 flex items-start gap-3 rounded-lg border p-3 transition-colors"
                            >
                                <Checkbox
                                    id={`deletion-${deletion.id}`}
                                    checked={selectedIds.includes(deletion.id)}
                                    onCheckedChange={() => toggleSelection(deletion.id)}
                                    className="mt-0.5"
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <Label
                                            htmlFor={`deletion-${deletion.id}`}
                                            className="cursor-pointer truncate text-sm font-medium"
                                        >
                                            {deletion.subject_code}
                                        </Label>
                                        <span className="text-muted-foreground shrink-0 text-xs">
                                            {deletion.created_at_human}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {deletion.subject_title}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                        Deleted by: {deletion.causer}
                                        {deletion.class_id ? ` · Class #${deletion.class_id}` : ""}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleRestore}
                        disabled={restoring || selectedIds.length === 0}
                    >
                        <Undo2 className="mr-1.5 size-4" />
                        {restoring
                            ? "Restoring…"
                            : `Restore ${selectedIds.length} Subject${selectedIds.length === 1 ? "" : "s"}`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export type DeleteRestoreDialogMode = "soft_delete" | "restore" | "force_delete";

export interface DeleteRestoreDialogProps {
    enrollmentId: number;
    studentName: string;
    mode: DeleteRestoreDialogMode | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function DeleteRestoreDialog({
    enrollmentId,
    studentName,
    mode,
    open,
    onOpenChange,
}: DeleteRestoreDialogProps) {
    const [processing, setProcessing] = useState(false);

    const activeMode = mode ?? "soft_delete";

    const config = {
        soft_delete: {
            title: "Move enrollment to trash?",
            description: `Soft-deleting archives ${studentName}'s enrollment (#${enrollmentId}) out of active lists. You can restore it later from trash.`,
            actionLabel: "Move to Trash",
            processingLabel: "Archiving…",
            variant: "destructive" as const,
            mediaClass: "bg-destructive/10 text-destructive",
            icon: Trash2,
        },
        restore: {
            title: "Restore trashed enrollment?",
            description: `Restoring brings ${studentName}'s enrollment (#${enrollmentId}) back into active registrar views with its preserved history.`,
            actionLabel: "Restore Enrollment",
            processingLabel: "Restoring…",
            variant: "default" as const,
            mediaClass: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
            icon: RotateCcw,
        },
        force_delete: {
            title: "Permanently delete enrollment?",
            description: `This irreversibly removes ${studentName}'s enrollment (#${enrollmentId}) and its non-financial records. Enrollments with tuition ledgers, fees, or transactions cannot be permanently deleted.`,
            actionLabel: "Delete Permanently",
            processingLabel: "Deleting…",
            variant: "destructive" as const,
            mediaClass: "bg-destructive/10 text-destructive",
            icon: AlertTriangle,
        },
    }[activeMode];

    const Icon = config.icon;

    const handleConfirm = () => {
        const callbacks = {
            preserveScroll: activeMode !== "force_delete",
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: (page: { props: unknown }) => {
                const flash = readFlash(page);
                if (!flash?.error) {
                    onOpenChange(false);
                }
            },
            onError: () => {
                toast.error("Action could not be completed.");
            },
        };

        if (activeMode === "soft_delete") {
            router.delete(destroyEnrollment.url(enrollmentId), callbacks);
        } else if (activeMode === "restore") {
            router.post(restoreEnrollment.url(enrollmentId), {}, callbacks);
        } else {
            router.delete(forceDestroyEnrollment.url(enrollmentId), callbacks);
        }
    };

    return (
        <AlertDialog open={open && mode !== null} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia className={config.mediaClass}>
                        <Icon className="size-5" />
                    </AlertDialogMedia>
                    <AlertDialogTitle>{config.title}</AlertDialogTitle>
                    <AlertDialogDescription>{config.description}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        variant={config.variant}
                        disabled={processing}
                        onClick={handleConfirm}
                    >
                        {processing ? config.processingLabel : config.actionLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
