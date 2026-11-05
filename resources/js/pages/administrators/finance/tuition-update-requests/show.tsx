import { index as tuitionAdjustmentsIndex } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import {
    adjustAndResolvePayment,
    adjustPayment,
    claim,
    recordAndResolvePayment,
    reject,
    resendEmail,
    resolve,
    resolveAdjustment,
    resolvePayment,
    index as tuitionUpdateRequestsIndex,
} from "@/actions/App/Http/Controllers/AdministratorTuitionUpdateRequestController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    CheckCircle2,
    CircleDollarSign,
    ClipboardCheck,
    CreditCard,
    ExternalLink,
    FileText,
    History,
    ImageIcon,
    Info,
    Link2,
    Loader2,
    Mail,
    PlusCircle,
    Receipt,
    ReceiptText,
    ShieldAlert,
    SlidersHorizontal,
    XCircle,
    ZoomIn,
} from "lucide-react";
import { useMemo, useState } from "react";

type ReceiptItem = {
    id: number;
    receipt_number: string;
    amount: number | null;
    image_url: string | null;
    file_name: string | null;
};

type ReviewRequest = {
    id: number;
    enrollment_id: number | null;
    student: { id: number; student_number: string; name: string | null; email: string | null };
    school_year: string;
    semester: number;
    concern_type: string;
    amount: number | null;
    receipt_number: string | null;
    details: string;
    status: "pending" | "in_review" | "resolved" | "rejected";
    resolution_note: string | null;
    reviewer_name: string | null;
    submitted_at: string | null;
    resolved_at: string | null;
    receipts?: ReceiptItem[];
    tuition: { overall_tuition: number; balance: number; discount: number; paid?: number } | null;
    resolved_transaction: { id: number; receipt_number: string | null } | null;
    tuition_adjustment: { id: number; reason: string } | null;
    events: Array<{
        id: number;
        event: string;
        from_status: string | null;
        to_status: string | null;
        note: string | null;
        actor_name: string | null;
        created_at: string | null;
    }>;
};

type TransactionRow = {
    id: number;
    transaction_number: string | null;
    receipt_number: string | null;
    status: string;
    payment_method?: string;
    amount: number;
    date: string | null;
};

type Props = {
    user: User;
    request: ReviewRequest;
    student_transactions?: TransactionRow[];
    matching_transactions: TransactionRow[];
    other_transactions?: TransactionRow[];
    matching_adjustments: Array<{ id: number; reason: string; created_at: string | null }>;
    payment_methods?: Array<{ value: string; label: string }>;
    can_manage: boolean;
    is_current_reviewer: boolean;
};

const concernLabels: Record<string, string> = {
    missing_payment: "Payment not reflected",
    discount: "Discount / Scholarship",
    subject_change: "Subject enrollment mismatch",
    other: "General assessment inquiry",
};

const statusMeta: Record<string, { label: string; variant: "warning-light" | "info-light" | "success-light" | "destructive-light" }> = {
    pending: { label: "Pending", variant: "warning-light" },
    in_review: { label: "In Review", variant: "info-light" },
    resolved: { label: "Resolved", variant: "success-light" },
    rejected: { label: "Rejected", variant: "destructive-light" },
};

export default function TuitionUpdateRequestShow({
    user,
    request,
    student_transactions = [],
    matching_transactions = [],
    other_transactions = [],
    matching_adjustments = [],
    payment_methods = [],
    can_manage,
    is_current_reviewer,
}: Props) {
    const isPayment = request.concern_type === "missing_payment";
    const reviewing = request.status === "in_review" && is_current_reviewer;
    const allAvailableTransactions = useMemo(() => {
        if (student_transactions.length > 0) return student_transactions;
        return [...matching_transactions, ...other_transactions];
    }, [student_transactions, matching_transactions, other_transactions]);

    // Form 1: Adjust Existing Transaction(s)
    const adjustForm = useForm({
        transaction_id: matching_transactions[0]?.id
            ? String(matching_transactions[0].id)
            : allAvailableTransactions[0]?.id
              ? String(allAvailableTransactions[0].id)
              : "",
        amount: request.amount
            ? String(request.amount)
            : matching_transactions[0]?.amount
              ? String(matching_transactions[0].amount)
              : "",
        reference_number: request.receipt_number ?? (matching_transactions[0]?.receipt_number ?? ""),
        note: request.amount
            ? `Adjusted payment transaction amount to ₱${Number(request.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}.`
            : "Adjusted payment transaction amount.",
        resolution_note: request.amount
            ? `Adjusted payment transaction to reflect verified downpayment/tuition payment of ₱${Number(request.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}.`
            : "Adjusted transaction to reflect verified amount.",
        adjust_multiple: Boolean(request.receipts && request.receipts.length > 1),
        adjustments: (request.receipts && request.receipts.length > 0
            ? request.receipts
            : [{ id: 1, receipt_number: request.receipt_number ?? "", amount: request.amount, image_url: null, file_name: null }]
        ).map((r, i) => ({
            transaction_id: allAvailableTransactions[i]?.id ? String(allAvailableTransactions[i].id) : "",
            amount: r.amount ? String(r.amount) : "",
            reference_number: r.receipt_number ?? "",
        })),
    });

    // Form 2: Finalize & Resolve Request (Once all adjustments are completed)
    const resolveForm = useForm({
        resolution_note: request.resolution_note || (
            request.amount
                ? `Your tuition update request has been reviewed. Transactions and tuition balance have been adjusted to reflect ₱${Number(request.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}.`
                : "Your tuition update request has been reviewed and verified. All payment and tuition fee adjustments have been applied."
        ),
        transaction_id: request.resolved_transaction?.id ? String(request.resolved_transaction.id) : "",
        tuition_adjustment_id: request.tuition_adjustment?.id ? String(request.tuition_adjustment.id) : "",
    });

    // Form 3: Link Existing Transaction As-Is
    const paymentForm = useForm({
        transaction_id: matching_transactions[0]?.id ? String(matching_transactions[0].id) : "",
        allow_mismatch: false,
        resolution_note: "",
    });

    // Form 4: Record & Resolve Missing Payment (Brand new transaction)
    const recordPaymentForm = useForm({
        amount: request.amount
            ? String(request.amount)
            : request.tuition?.balance
              ? String(request.tuition.balance)
              : "",
        payment_method: payment_methods[0]?.value ?? "Cash",
        reference_number: request.receipt_number ?? "",
        resolution_note: request.receipt_number
            ? `Verified payment with official receipt #${request.receipt_number} and recorded to student tuition account.`
            : "Verified payment and recorded to student tuition account.",
    });

    // Form 5: Resolve via Tuition Adjustment
    const adjustmentForm = useForm({
        tuition_adjustment_id: matching_adjustments[0]?.id ? String(matching_adjustments[0].id) : "",
        resolution_note: "",
    });

    // Form 6: Reject Request
    const rejectionForm = useForm({ resolution_note: "" });

    // Form 7: Resend Email Form
    const resendForm = useForm({});

    // Active resolution tab: default to "adjust" if transactions exist, else "record"
    const [paymentResolveMode, setPaymentResolveMode] = useState<"adjust" | "record" | "link">(
        allAvailableTransactions.length > 0 ? "adjust" : "record",
    );

    // Lightbox / Image Preview state
    const [previewImage, setPreviewImage] = useState<{ url: string; title: string } | null>(null);

    function claimRequest() {
        router.post(claim.url(request.id), {}, { preserveScroll: true });
    }

    function handleAdjustPaymentOnly(event: React.FormEvent) {
        event.preventDefault();
        adjustForm.transform((data) => {
            if (data.adjust_multiple) {
                return {
                    adjustments: data.adjustments.map((a) => ({
                        transaction_id: Number(a.transaction_id),
                        amount: Number(a.amount),
                        reference_number: a.reference_number || undefined,
                    })),
                    note: data.note || undefined,
                };
            }
            return {
                transaction_id: Number(data.transaction_id),
                amount: Number(data.amount),
                reference_number: data.reference_number || undefined,
                note: data.note || undefined,
            };
        });
        adjustForm.post(adjustPayment.url(request.id), { preserveScroll: true });
    }

    function handleAdjustAndResolvePayment(event: React.FormEvent) {
        event.preventDefault();
        adjustForm.transform((data) => {
            if (data.adjust_multiple) {
                return {
                    adjustments: data.adjustments.map((a) => ({
                        transaction_id: Number(a.transaction_id),
                        amount: Number(a.amount),
                        reference_number: a.reference_number || undefined,
                    })),
                    resolution_note: data.resolution_note,
                };
            }
            return {
                transaction_id: Number(data.transaction_id),
                amount: Number(data.amount),
                reference_number: data.reference_number || undefined,
                resolution_note: data.resolution_note,
            };
        });
        adjustForm.post(adjustAndResolvePayment.url(request.id), { preserveScroll: true });
    }

    function handleResolveRequest(event: React.FormEvent) {
        event.preventDefault();
        resolveForm.transform((data) => ({
            resolution_note: data.resolution_note,
            transaction_id: data.transaction_id ? Number(data.transaction_id) : undefined,
            tuition_adjustment_id: data.tuition_adjustment_id ? Number(data.tuition_adjustment_id) : undefined,
        }));
        resolveForm.post(resolve.url(request.id), { preserveScroll: true });
    }

    function handleResolvePayment(event: React.FormEvent) {
        event.preventDefault();
        paymentForm.post(resolvePayment.url(request.id), { preserveScroll: true });
    }

    function handleRecordAndResolvePayment(event: React.FormEvent) {
        event.preventDefault();
        recordPaymentForm.post(recordAndResolvePayment.url(request.id), { preserveScroll: true });
    }

    function handleResolveAdjustment(event: React.FormEvent) {
        event.preventDefault();
        adjustmentForm.post(resolveAdjustment.url(request.id), { preserveScroll: true });
    }

    function handleReject(event: React.FormEvent) {
        event.preventDefault();
        rejectionForm.post(reject.url(request.id), { preserveScroll: true });
    }

    function handleResendEmail() {
        resendForm.post(resendEmail.url(request.id), { preserveScroll: true });
    }

    function quickSelectTransactionForAdjust(tx: TransactionRow) {
        setPaymentResolveMode("adjust");
        adjustForm.setData((prev) => ({
            ...prev,
            transaction_id: String(tx.id),
            reference_number: tx.receipt_number ?? prev.reference_number,
        }));
    }

    const currentStatus = statusMeta[request.status] ?? { label: request.status, variant: "outline" as const };
    const receiptsList = request.receipts ?? [];

    return (
        <AdminLayout user={user} title="Tuition Update Request Details">
            <Head title={`Finance · Request #${request.id}`} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                {/* Header Bar */}
                <header className="flex flex-col justify-between gap-4 border-b pb-5 md:flex-row md:items-end">
                    <div className="flex flex-col gap-2">
                        <div>
                            <Button variant="ghost" size="sm" nativeButton={false} render={<Link href={tuitionUpdateRequestsIndex.url()} />}>
                                <ArrowLeft className="mr-1.5 size-4" />
                                All review requests
                            </Button>
                        </div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-bold tracking-tight">Request #{request.id}</h1>
                            <Badge variant={currentStatus.variant}>{currentStatus.label}</Badge>
                            <Badge variant="outline">{concernLabels[request.concern_type] ?? request.concern_type}</Badge>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            Submitted {request.submitted_at ? new Date(request.submitted_at).toLocaleString() : "—"} · {request.school_year} (
                            {request.semester === 1 ? "1st" : "2nd"} Semester)
                        </p>
                    </div>

                    {can_manage && request.status === "pending" && (
                        <Button onClick={claimRequest}>
                            <ClipboardCheck className="mr-2 size-4" />
                            Claim for review
                        </Button>
                    )}

                    {can_manage && ["resolved", "rejected"].includes(request.status) && (
                        <Button variant="outline" size="sm" disabled={resendForm.processing} onClick={handleResendEmail}>
                            {resendForm.processing ? <Loader2 className="mr-1.5 size-3.5 animate-spin" /> : <Mail className="mr-1.5 size-3.5" />}
                            Resend Email Notification
                        </Button>
                    )}
                </header>

                {/* Main 2-Column Grid */}
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                    {/* Left Column: Context & Actions */}
                    <div className="flex flex-col gap-6">
                        {/* Student Report Panel */}
                        <Frame>
                            <FramePanel>
                                <FrameHeader>
                                    <FrameTitle>Student Report</FrameTitle>
                                    <FrameDescription>Student's submitted explanation, claimed amounts, and official receipt documents.</FrameDescription>
                                </FrameHeader>

                                <div className="border-border bg-muted/30 mt-4 grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                                    <div>
                                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Student Details</span>
                                        <p className="text-foreground mt-1 font-semibold">{request.student.name || "Student Record"}</p>
                                        <p className="text-muted-foreground font-mono text-xs">ID: {request.student.student_number}</p>
                                        {request.student.email && <p className="text-muted-foreground text-xs">{request.student.email}</p>}
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Submitted Receipt / OR Number
                                        </span>
                                        <p className="text-foreground mt-1 font-mono font-semibold">{request.receipt_number || "None provided"}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {request.receipt_number ? "Reported by student on submission" : "No receipt number was entered"}
                                        </p>
                                    </div>
                                </div>

                                {/* Claimed / Requested Amount Display */}
                                {request.amount !== null && (
                                    <div className="mt-4 flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3.5">
                                        <div className="flex items-center gap-2.5">
                                            <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                                                <CircleDollarSign className="size-4" />
                                            </div>
                                            <div>
                                                <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                                    Claimed / Target Payment Amount
                                                </span>
                                                <p className="text-base font-bold text-foreground tabular-nums">
                                                    ₱{request.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant="primary-light">Student Specified</Badge>
                                    </div>
                                )}

                                {/* Submitted Receipt Attachments (1 or multiple receipts supported) */}
                                {receiptsList.length > 0 && (
                                    <div className="mt-4 flex flex-col gap-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                                Receipt Documents & Photos ({receiptsList.length})
                                            </span>
                                            <span className="text-xs text-muted-foreground">Click image to inspect</span>
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {receiptsList.map((rec, idx) => (
                                                <div key={rec.id || idx} className="flex flex-col gap-2 rounded-md border border-border bg-card p-3 shadow-sm">
                                                    <div className="flex items-start justify-between">
                                                        <div>
                                                            <span className="text-[10px] font-semibold text-muted-foreground uppercase">
                                                                Receipt #{idx + 1}
                                                            </span>
                                                            <p className="font-mono text-sm font-bold text-foreground">
                                                                {rec.receipt_number || "No OR Number"}
                                                            </p>
                                                        </div>
                                                        {rec.amount !== null && (
                                                            <Badge variant="outline" className="font-mono">
                                                                ₱{rec.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    {rec.image_url ? (
                                                        <div
                                                            onClick={() => setPreviewImage({ url: rec.image_url!, title: `Receipt ${rec.receipt_number || idx + 1}` })}
                                                            className="group relative h-36 w-full cursor-pointer overflow-hidden rounded-md border border-border bg-muted"
                                                        >
                                                            <img
                                                                src={rec.image_url}
                                                                alt={`Receipt ${rec.receipt_number}`}
                                                                className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                            />
                                                            <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                                                <span className="inline-flex items-center gap-1 rounded bg-black/75 px-2 py-1 text-xs text-white">
                                                                    <ZoomIn className="size-3.5" />
                                                                    View receipt
                                                                </span>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="flex h-20 items-center justify-center rounded-md border border-dashed border-border bg-muted/20 text-xs text-muted-foreground">
                                                            <FileText className="mr-1.5 size-4" />
                                                            No image attached
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="mt-4 flex flex-col gap-1.5">
                                    <span className="text-foreground text-xs font-semibold">Statement of Discrepancy:</span>
                                    <p className="border-border bg-card text-muted-foreground rounded-md border p-3.5 text-sm leading-relaxed whitespace-pre-wrap">
                                        {request.details}
                                    </p>
                                </div>

                                {/* Tuition Assessment Snapshot */}
                                {request.tuition && (
                                    <div className="border-border bg-muted/20 mt-4 grid grid-cols-4 gap-2 rounded-md border p-3.5 text-center">
                                        <div>
                                            <span className="text-muted-foreground text-xs">Assessed Tuition</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums text-sm sm:text-base">
                                                ₱{request.tuition.overall_tuition.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-xs">Total Paid</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums text-sm sm:text-base">
                                                ₱{(request.tuition.paid ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-xs">Current Balance</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums text-sm sm:text-base">
                                                ₱{request.tuition.balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-xs">Discount</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums text-sm sm:text-base">
                                                {request.tuition.discount}%
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </FramePanel>
                        </Frame>

                        {/* RESOLUTION WORKSPACE */}
                        {can_manage && reviewing && (
                            <Frame className="border-primary/30">
                                <FramePanel>
                                    {isPayment ? (
                                        <div className="flex flex-col gap-4">
                                            <FrameHeader>
                                                <div className="flex items-center gap-2">
                                                    <CircleDollarSign className="text-primary size-5" />
                                                    <FrameTitle>Resolve Payment Request</FrameTitle>
                                                </div>
                                                <FrameDescription>
                                                    Adjust an existing recorded payment transaction (e.g. overpayment/underpayment correction), record a brand new payment, or link an existing transaction.
                                                </FrameDescription>
                                            </FrameHeader>

                                            <Tabs value={paymentResolveMode} onValueChange={(val) => setPaymentResolveMode(val as "adjust" | "record" | "link")}>
                                                <TabsList className="grid w-full grid-cols-3">
                                                    <TabsTab value="adjust" className="gap-1.5 text-xs sm:text-sm">
                                                        <SlidersHorizontal className="size-3.5" />
                                                        <span>Adjust Transaction</span>
                                                    </TabsTab>
                                                    <TabsTab value="record" className="gap-1.5 text-xs sm:text-sm">
                                                        <PlusCircle className="size-3.5" />
                                                        <span>Record New</span>
                                                    </TabsTab>
                                                    <TabsTab value="link" className="gap-1.5 text-xs sm:text-sm">
                                                        <Link2 className="size-3.5" />
                                                        <span>Link As-Is</span>
                                                    </TabsTab>
                                                </TabsList>

                                                {/* MODE 1: ADJUST EXISTING TRANSACTION (Reconciles Over/Underpayment e.g. 10450 -> 10000) */}
                                                <TabsPanel value="adjust" className="mt-4 flex flex-col gap-4">
                                                    <Alert variant="info">
                                                        <Info aria-hidden="true" />
                                                        <AlertTitle>Adjust posted transaction amount & reconcile ledger</AlertTitle>
                                                        <AlertDescription>
                                                            Updates the transaction amount, adjusts settlements, corrects student payments, and recalculates the balance due automatically without creating artificial duplicate charges.
                                                        </AlertDescription>
                                                    </Alert>

                                                    {allAvailableTransactions.length === 0 ? (
                                                        <Empty>
                                                            <EmptyMedia variant="icon">
                                                                <CreditCard aria-hidden="true" />
                                                            </EmptyMedia>
                                                            <EmptyHeader>
                                                                <EmptyTitle>No existing transactions found to adjust</EmptyTitle>
                                                                <EmptyDescription>
                                                                    This student has no posted transactions. Use the "Record New" tab to create one.
                                                                </EmptyDescription>
                                                            </EmptyHeader>
                                                            <Button variant="outline" size="sm" onClick={() => setPaymentResolveMode("record")}>
                                                                Switch to Record Payment
                                                            </Button>
                                                        </Empty>
                                                    ) : (
                                                        <form onSubmit={handleAdjustAndResolvePayment} className="flex flex-col gap-4">
                                                            {/* Multiple Adjustments Toggle for Dual Receipts */}
                                                            {receiptsList.length > 1 && (
                                                                <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 p-3">
                                                                    <div>
                                                                        <span className="text-xs font-semibold text-foreground">Dual / Multiple Receipt Adjustment</span>
                                                                        <p className="text-xs text-muted-foreground">Adjust multiple separate transactions for each receipt.</p>
                                                                    </div>
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={adjustForm.data.adjust_multiple}
                                                                        onChange={(e) => adjustForm.setData("adjust_multiple", e.target.checked)}
                                                                        className="accent-primary size-4"
                                                                    />
                                                                </div>
                                                            )}

                                                            {!adjustForm.data.adjust_multiple ? (
                                                                <>
                                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                                        <div className="flex flex-col gap-1.5">
                                                                            <Label htmlFor="adjust-tx-select">Select Transaction to Adjust</Label>
                                                                            <Select
                                                                                value={adjustForm.data.transaction_id}
                                                                                onValueChange={(val) => {
                                                                                    const found = allAvailableTransactions.find((t) => String(t.id) === val);
                                                                                    adjustForm.setData((prev) => ({
                                                                                        ...prev,
                                                                                        transaction_id: val,
                                                                                        reference_number: found?.receipt_number ?? prev.reference_number,
                                                                                    }));
                                                                                }}
                                                                            >
                                                                                <SelectTrigger id="adjust-tx-select" className="w-full">
                                                                                    <SelectValue placeholder="Choose transaction" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {allAvailableTransactions.map((tx) => (
                                                                                        <SelectItem key={tx.id} value={String(tx.id)}>
                                                                                            Tx #{tx.transaction_number || tx.id} (OR: {tx.receipt_number || "None"}) — ₱{tx.amount.toLocaleString()}
                                                                                        </SelectItem>
                                                                                    ))}
                                                                                </SelectContent>
                                                                            </Select>
                                                                            {adjustForm.errors.transaction_id && (
                                                                                <p className="text-destructive text-xs">{adjustForm.errors.transaction_id}</p>
                                                                            )}
                                                                        </div>

                                                                        <div className="flex flex-col gap-1.5">
                                                                            <div className="flex items-center justify-between">
                                                                                <Label htmlFor="adjust-amount">Corrected Amount (₱)</Label>
                                                                                {request.amount !== null && (
                                                                                    <button
                                                                                        type="button"
                                                                                        onClick={() => adjustForm.setData("amount", String(request.amount))}
                                                                                        className="text-xs text-primary hover:underline"
                                                                                    >
                                                                                        Use student target (₱{request.amount.toLocaleString()})
                                                                                    </button>
                                                                                )}
                                                                            </div>
                                                                            <Input
                                                                                id="adjust-amount"
                                                                                type="number"
                                                                                step="0.01"
                                                                                min="0.01"
                                                                                value={adjustForm.data.amount}
                                                                                onChange={(e) => adjustForm.setData("amount", e.target.value)}
                                                                                placeholder="e.g. 10000.00"
                                                                                required
                                                                            />
                                                                            {adjustForm.errors.amount && (
                                                                                <p className="text-destructive text-xs">{adjustForm.errors.amount}</p>
                                                                            )}
                                                                        </div>
                                                                    </div>

                                                                    <div className="flex flex-col gap-1.5">
                                                                        <Label htmlFor="adjust-reference">Official Receipt / OR Reference Number</Label>
                                                                        <Input
                                                                            id="adjust-reference"
                                                                            value={adjustForm.data.reference_number}
                                                                            onChange={(e) => adjustForm.setData("reference_number", e.target.value)}
                                                                            placeholder="e.g. 226432"
                                                                        />
                                                                        {adjustForm.errors.reference_number && (
                                                                            <p className="text-destructive text-xs">{adjustForm.errors.reference_number}</p>
                                                                        )}
                                                                    </div>
                                                                </>
                                                            ) : (
                                                                /* MULTI-RECEIPT ADJUSTMENT SECTION */
                                                                <div className="flex flex-col gap-3">
                                                                    <span className="text-xs font-semibold text-foreground">Adjust Each Receipt Transaction:</span>
                                                                    {adjustForm.data.adjustments.map((item, idx) => (
                                                                        <div key={idx} className="rounded-md border border-border p-3 flex flex-col gap-3 bg-muted/20">
                                                                            <div className="flex items-center justify-between">
                                                                                <span className="text-xs font-bold text-foreground">Receipt #{idx + 1} Adjustment</span>
                                                                                <Badge variant="outline">Item {idx + 1}</Badge>
                                                                            </div>
                                                                            <div className="grid gap-3 sm:grid-cols-3">
                                                                                <div className="flex flex-col gap-1">
                                                                                    <Label className="text-xs">Transaction</Label>
                                                                                    <Select
                                                                                        value={item.transaction_id}
                                                                                        onValueChange={(val) => {
                                                                                            const next = [...adjustForm.data.adjustments];
                                                                                            next[idx].transaction_id = val;
                                                                                            adjustForm.setData("adjustments", next);
                                                                                        }}
                                                                                    >
                                                                                        <SelectTrigger className="w-full text-xs">
                                                                                            <SelectValue placeholder="Select Tx" />
                                                                                        </SelectTrigger>
                                                                                        <SelectContent>
                                                                                            {allAvailableTransactions.map((tx) => (
                                                                                                <SelectItem key={tx.id} value={String(tx.id)}>
                                                                                                    #{tx.transaction_number || tx.id} (₱{tx.amount.toLocaleString()})
                                                                                                </SelectItem>
                                                                                            ))}
                                                                                        </SelectContent>
                                                                                    </Select>
                                                                                </div>
                                                                                <div className="flex flex-col gap-1">
                                                                                    <Label className="text-xs">Corrected Amount (₱)</Label>
                                                                                    <Input
                                                                                        type="number"
                                                                                        step="0.01"
                                                                                        min="0.01"
                                                                                        value={item.amount}
                                                                                        onChange={(e) => {
                                                                                            const next = [...adjustForm.data.adjustments];
                                                                                            next[idx].amount = e.target.value;
                                                                                            adjustForm.setData("adjustments", next);
                                                                                        }}
                                                                                        placeholder="5000.00"
                                                                                        className="text-xs"
                                                                                        required
                                                                                    />
                                                                                </div>
                                                                                <div className="flex flex-col gap-1">
                                                                                    <Label className="text-xs">OR / Ref Number</Label>
                                                                                    <Input
                                                                                        value={item.reference_number}
                                                                                        onChange={(e) => {
                                                                                            const next = [...adjustForm.data.adjustments];
                                                                                            next[idx].reference_number = e.target.value;
                                                                                            adjustForm.setData("adjustments", next);
                                                                                        }}
                                                                                        placeholder="OR-226432"
                                                                                        className="text-xs"
                                                                                    />
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            )}

                                                            <div className="flex flex-col gap-1.5">
                                                                <Label htmlFor="adjust-note">Adjustment Note / Memo (Optional)</Label>
                                                                <Input
                                                                    id="adjust-note"
                                                                    value={adjustForm.data.note}
                                                                    onChange={(e) => adjustForm.setData("note", e.target.value)}
                                                                    placeholder="e.g. Adjusted downpayment transaction amount..."
                                                                    className="text-xs"
                                                                />
                                                                <p className="text-[11px] text-muted-foreground">
                                                                    Recorded in the review audit timeline. You can adjust multiple transactions while keeping this request in review.
                                                                </p>
                                                                {adjustForm.errors.note && (
                                                                    <p className="text-destructive text-xs">{adjustForm.errors.note}</p>
                                                                )}
                                                            </div>

                                                            <div className="flex flex-col gap-2 pt-1 sm:flex-row sm:items-center sm:justify-between">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <Button
                                                                        type="button"
                                                                        disabled={adjustForm.processing}
                                                                        onClick={handleAdjustPaymentOnly}
                                                                        className="w-full sm:w-auto"
                                                                    >
                                                                        {adjustForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                                                        <SlidersHorizontal className="mr-1.5 size-4" />
                                                                        Adjust Transaction (Keep In Review)
                                                                    </Button>

                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        disabled={adjustForm.processing}
                                                                        onClick={handleAdjustAndResolvePayment}
                                                                        className="w-full sm:w-auto"
                                                                    >
                                                                        <CheckCircle2 className="mr-1.5 size-4" />
                                                                        Adjust & Resolve In One Step
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    )}
                                                </TabsPanel>

                                                {/* MODE 2: RECORD NEW VERIFIED PAYMENT */}
                                                <TabsPanel value="record" className="mt-4 flex flex-col gap-4">
                                                    <Alert variant="info">
                                                        <Info aria-hidden="true" />
                                                        <AlertTitle>Record missing cashier or bank payment</AlertTitle>
                                                        <AlertDescription>
                                                            This records the official transaction, updates the student's tuition ledger and balance, and resolves this request in one atomic operation.
                                                        </AlertDescription>
                                                    </Alert>

                                                    <form onSubmit={handleRecordAndResolvePayment} className="flex flex-col gap-4">
                                                        <div className="grid gap-4 sm:grid-cols-2">
                                                            <div className="flex flex-col gap-1.5">
                                                                <div className="flex items-center justify-between">
                                                                    <Label htmlFor="payment-amount">Verified Amount (₱)</Label>
                                                                    {request.tuition && request.tuition.balance > 0 && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                recordPaymentForm.setData("amount", String(request.tuition?.balance ?? ""))
                                                                            }
                                                                            className="text-xs text-primary hover:underline"
                                                                        >
                                                                            Use balance (₱{request.tuition.balance.toLocaleString()})
                                                                        </button>
                                                                    )}
                                                                </div>
                                                                <Input
                                                                    id="payment-amount"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0.01"
                                                                    value={recordPaymentForm.data.amount}
                                                                    onChange={(e) => recordPaymentForm.setData("amount", e.target.value)}
                                                                    placeholder="e.g. 5000.00"
                                                                    required
                                                                />
                                                                {recordPaymentForm.errors.amount && (
                                                                    <p className="text-destructive text-xs">{recordPaymentForm.errors.amount}</p>
                                                                )}
                                                            </div>

                                                            <div className="flex flex-col gap-1.5">
                                                                <Label htmlFor="payment-method">Payment Method</Label>
                                                                <Select
                                                                    value={recordPaymentForm.data.payment_method}
                                                                    onValueChange={(val) => recordPaymentForm.setData("payment_method", val)}
                                                                >
                                                                    <SelectTrigger id="payment-method" className="w-full">
                                                                        <SelectValue placeholder="Select method" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {payment_methods.map((pm) => (
                                                                            <SelectItem key={pm.value} value={pm.value}>
                                                                                {pm.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                                {recordPaymentForm.errors.payment_method && (
                                                                    <p className="text-destructive text-xs">{recordPaymentForm.errors.payment_method}</p>
                                                                )}
                                                            </div>
                                                        </div>

                                                        <div className="flex flex-col gap-1.5">
                                                            <Label htmlFor="payment-reference">Official Receipt / Reference No.</Label>
                                                            <Input
                                                                id="payment-reference"
                                                                value={recordPaymentForm.data.reference_number}
                                                                onChange={(e) => recordPaymentForm.setData("reference_number", e.target.value)}
                                                                placeholder="e.g. OR-2026-00124"
                                                                required
                                                            />
                                                            {recordPaymentForm.errors.reference_number && (
                                                                <p className="text-destructive text-xs">{recordPaymentForm.errors.reference_number}</p>
                                                            )}
                                                        </div>

                                                        <div className="flex flex-col gap-1.5">
                                                            <Label htmlFor="record-note">Resolution Note to Student</Label>
                                                            <Textarea
                                                                id="record-note"
                                                                value={recordPaymentForm.data.resolution_note}
                                                                onChange={(e) => recordPaymentForm.setData("resolution_note", e.target.value)}
                                                                placeholder="Explain the payment verification and update to the student..."
                                                                className="min-h-24 resize-y"
                                                                required
                                                            />
                                                            {recordPaymentForm.errors.resolution_note && (
                                                                <p className="text-destructive text-xs">{recordPaymentForm.errors.resolution_note}</p>
                                                            )}
                                                        </div>

                                                        <Button type="submit" disabled={recordPaymentForm.processing} className="w-full sm:w-auto">
                                                            {recordPaymentForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                                            Record Payment & Resolve Request
                                                        </Button>
                                                    </form>
                                                </TabsPanel>

                                                {/* MODE 3: LINK EXISTING TRANSACTION */}
                                                <TabsPanel value="link" className="mt-4 flex flex-col gap-4">
                                                    {allAvailableTransactions.length === 0 ? (
                                                        <Empty>
                                                            <EmptyMedia variant="icon">
                                                                <CreditCard aria-hidden="true" />
                                                            </EmptyMedia>
                                                            <EmptyHeader>
                                                                <EmptyTitle>No existing transactions found</EmptyTitle>
                                                                <EmptyDescription>
                                                                    There are no recorded transactions matching this student's account yet.
                                                                </EmptyDescription>
                                                            </EmptyHeader>
                                                        </Empty>
                                                    ) : (
                                                        <form onSubmit={handleResolvePayment} className="flex flex-col gap-4">
                                                            <div className="flex flex-col gap-1.5">
                                                                <Label htmlFor="transaction-select">Select Verified Transaction</Label>
                                                                <Select
                                                                    value={paymentForm.data.transaction_id}
                                                                    onValueChange={(val) => paymentForm.setData("transaction_id", val)}
                                                                >
                                                                    <SelectTrigger id="transaction-select" className="w-full">
                                                                        <SelectValue placeholder="Choose a transaction" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {allAvailableTransactions.map((tx) => (
                                                                            <SelectItem key={tx.id} value={String(tx.id)}>
                                                                                Tx #{tx.transaction_number || tx.id} (OR: {tx.receipt_number || "None"}) — ₱{tx.amount.toLocaleString()}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                                {paymentForm.errors.transaction_id && (
                                                                    <p className="text-destructive text-xs">{paymentForm.errors.transaction_id}</p>
                                                                )}
                                                            </div>

                                                            <div className="border-border bg-muted/30 flex items-start gap-2.5 rounded-md border p-3">
                                                                <input
                                                                    type="checkbox"
                                                                    id="allow-mismatch"
                                                                    checked={paymentForm.data.allow_mismatch}
                                                                    onChange={(e) => paymentForm.setData("allow_mismatch", e.target.checked)}
                                                                    className="accent-primary mt-0.5 size-4"
                                                                />
                                                                <div className="flex flex-col">
                                                                    <label htmlFor="allow-mismatch" className="text-foreground cursor-pointer text-xs font-semibold">
                                                                        Allow receipt / OR number reconciliation
                                                                    </label>
                                                                    <span className="text-muted-foreground text-xs">
                                                                        Check this if the student made a typo in their OR number. The request will link and update to the verified OR number.
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div className="flex flex-col gap-1.5">
                                                                <Label htmlFor="link-note">Resolution Note to Student</Label>
                                                                <Textarea
                                                                    id="link-note"
                                                                    value={paymentForm.data.resolution_note}
                                                                    onChange={(e) => paymentForm.setData("resolution_note", e.target.value)}
                                                                    placeholder="Explain what payment was verified and linked to their account..."
                                                                    className="min-h-24 resize-y"
                                                                    required
                                                                />
                                                                {paymentForm.errors.resolution_note && (
                                                                    <p className="text-destructive text-xs">{paymentForm.errors.resolution_note}</p>
                                                                )}
                                                            </div>

                                                            <Button
                                                                type="submit"
                                                                disabled={paymentForm.processing || !paymentForm.data.transaction_id}
                                                                className="w-full sm:w-auto"
                                                            >
                                                                {paymentForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                                                Link Transaction & Resolve Request
                                                            </Button>
                                                        </form>
                                                    )}
                                                </TabsPanel>
                                            </Tabs>
                                        </div>
                                    ) : (
                                        /* TUITION ADJUSTMENT RESOLVER */
                                        <div className="flex flex-col gap-4">
                                            <FrameHeader>
                                                <div className="flex items-center gap-2">
                                                    <ReceiptText className="text-primary size-5" />
                                                    <FrameTitle>Resolve with Tuition Adjustment</FrameTitle>
                                                </div>
                                                <FrameDescription>
                                                    Apply the approved scholarship, discount, or enrollment correction in the Tuition Adjustments ledger, then link the audit record below.
                                                </FrameDescription>
                                            </FrameHeader>

                                            <div>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    nativeButton={false}
                                                    render={
                                                        <Link
                                                            href={tuitionAdjustmentsIndex.url({
                                                                query: {
                                                                    enrollment: request.enrollment_id ?? undefined,
                                                                    school_year: request.school_year,
                                                                    semester: request.semester,
                                                                },
                                                            })}
                                                        />
                                                    }
                                                >
                                                    <ExternalLink className="mr-1.5 size-3.5" />
                                                    Open Tuition Adjustments Ledger
                                                </Button>
                                            </div>

                                            {matching_adjustments.length === 0 ? (
                                                <Alert variant="info">
                                                    <Info aria-hidden="true" />
                                                    <AlertTitle>No recorded adjustments found</AlertTitle>
                                                    <AlertDescription>
                                                        Complete the assessment adjustment in the canonical Tuition Adjustments module first, then return here to link its record and notify the student.
                                                    </AlertDescription>
                                                </Alert>
                                            ) : (
                                                <form onSubmit={handleResolveAdjustment} className="flex flex-col gap-4">
                                                    <div className="flex flex-col gap-1.5">
                                                        <Label htmlFor="adjustment-select">Completed Adjustment Record</Label>
                                                        <Select
                                                            value={adjustmentForm.data.tuition_adjustment_id}
                                                            onValueChange={(val) => adjustmentForm.setData("tuition_adjustment_id", val)}
                                                        >
                                                            <SelectTrigger id="adjustment-select" className="w-full">
                                                                <SelectValue placeholder="Select adjustment" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {matching_adjustments.map((adj) => (
                                                                    <SelectItem key={adj.id} value={String(adj.id)}>
                                                                        #{adj.id} · {adj.reason}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                        {adjustmentForm.errors.tuition_adjustment_id && (
                                                            <p className="text-destructive text-xs">{adjustmentForm.errors.tuition_adjustment_id}</p>
                                                        )}
                                                    </div>

                                                    <div className="flex flex-col gap-1.5">
                                                        <Label htmlFor="adj-note">Resolution Note to Student</Label>
                                                        <Textarea
                                                            id="adj-note"
                                                            value={adjustmentForm.data.resolution_note}
                                                            onChange={(e) => adjustmentForm.setData("resolution_note", e.target.value)}
                                                            placeholder="Explain what adjustment was applied..."
                                                            className="min-h-24 resize-y"
                                                            required
                                                        />
                                                        {adjustmentForm.errors.resolution_note && (
                                                            <p className="text-destructive text-xs">{adjustmentForm.errors.resolution_note}</p>
                                                        )}
                                                    </div>

                                                    <Button
                                                        type="submit"
                                                        disabled={adjustmentForm.processing || !adjustmentForm.data.tuition_adjustment_id}
                                                        className="w-full sm:w-auto"
                                                    >
                                                        {adjustmentForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                                        Resolve with Adjustment & Notify Student
                                                    </Button>
                                                </form>
                                            )}
                                        </div>
                                    )}
                                </FramePanel>
                            </Frame>
                        )}

                        {/* FINALIZE & RESOLVE REQUEST (Dedicated action when all adjustments are complete) */}
                        {can_manage && reviewing && (
                            <Frame className="border-emerald-500/30 bg-emerald-500/5">
                                <FramePanel>
                                    <form onSubmit={handleResolveRequest} className="flex flex-col gap-4">
                                        <FrameHeader>
                                            <div className="flex items-center gap-2">
                                                <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />
                                                <FrameTitle>Finalize & Resolve Request</FrameTitle>
                                            </div>
                                            <FrameDescription>
                                                Finished with all transaction adjustments and tuition reviews? Enter the official resolution note and mark this request as resolved.
                                            </FrameDescription>
                                        </FrameHeader>

                                        {/* Assessment Snapshot Confirmation */}
                                        {request.tuition && (
                                            <div className="rounded-md border border-border bg-card p-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                                                <div>
                                                    <span className="text-muted-foreground">Assessed Tuition:</span>
                                                    <span className="ml-1.5 font-mono font-bold text-foreground">
                                                        ₱{request.tuition.overall_tuition.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">Total Paid:</span>
                                                    <span className="ml-1.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                                        ₱{(request.tuition.paid ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">Balance Due:</span>
                                                    <span className="ml-1.5 font-mono font-bold text-foreground">
                                                        ₱{request.tuition.balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </span>
                                                </div>
                                            </div>
                                        )}

                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="resolve-note">Final Resolution Note to Student</Label>
                                            <Textarea
                                                id="resolve-note"
                                                value={resolveForm.data.resolution_note}
                                                onChange={(e) => resolveForm.setData("resolution_note", e.target.value)}
                                                placeholder="Provide the complete resolution explanation to the student..."
                                                className="min-h-24 resize-y bg-background"
                                                required
                                            />
                                            {resolveForm.errors.resolution_note && (
                                                <p className="text-destructive text-xs">{resolveForm.errors.resolution_note}</p>
                                            )}
                                        </div>

                                        <div className="flex justify-end">
                                            <Button type="submit" disabled={resolveForm.processing} className="w-full sm:w-auto">
                                                {resolveForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                                <CheckCircle2 className="mr-2 size-4" />
                                                Mark as Resolved & Notify Student
                                            </Button>
                                        </div>
                                    </form>
                                </FramePanel>
                            </Frame>
                        )}

                        {/* RESOLVED STATUS CARD */}
                        {request.status === "resolved" && (
                            <Frame className="border-success/30">
                                <FramePanel>
                                    <div className="flex flex-col gap-3">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="text-foreground flex items-center gap-2 font-semibold">
                                                <CheckCircle2 className="text-success size-5" />
                                                <span>Request Successfully Resolved</span>
                                            </div>
                                            {can_manage && (
                                                <Button variant="outline" size="sm" disabled={resendForm.processing} onClick={handleResendEmail}>
                                                    {resendForm.processing ? (
                                                        <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                                                    ) : (
                                                        <Mail className="mr-1.5 size-3.5" />
                                                    )}
                                                    Resend Email Notification
                                                </Button>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground text-sm whitespace-pre-wrap">{request.resolution_note}</p>
                                        <div className="text-muted-foreground flex flex-wrap gap-4 border-t pt-3 text-xs">
                                            {request.resolved_transaction && (
                                                <div className="flex items-center gap-1.5">
                                                    <Receipt className="size-3.5" />
                                                    <span>Linked Transaction:</span>
                                                    <code className="bg-muted text-foreground rounded px-1.5 py-0.5 font-mono">
                                                        #{request.resolved_transaction.id}
                                                        {request.resolved_transaction.receipt_number
                                                            ? ` (OR: ${request.resolved_transaction.receipt_number})`
                                                            : ""}
                                                    </code>
                                                </div>
                                            )}
                                            {request.tuition_adjustment && (
                                                <div className="flex items-center gap-1.5">
                                                    <ReceiptText className="size-3.5" />
                                                    <span>Linked Adjustment:</span>
                                                    <code className="bg-muted text-foreground rounded px-1.5 py-0.5 font-mono">
                                                        #{request.tuition_adjustment.id}
                                                    </code>
                                                </div>
                                            )}
                                            {request.resolved_at && <div>Resolved on {new Date(request.resolved_at).toLocaleString()}</div>}
                                        </div>
                                    </div>
                                </FramePanel>
                            </Frame>
                        )}
                    </div>

                    {/* Right Column: Transactions Sidebar, Timeline & Reject */}
                    <div className="flex flex-col gap-6">
                        {/* SIDEBAR CARD: STUDENT TRANSACTIONS */}
                        <Frame>
                            <FramePanel>
                                <FrameHeader>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <CreditCard className="text-primary size-4" />
                                            <FrameTitle>Student Transactions</FrameTitle>
                                        </div>
                                        <Badge variant="outline" className="text-xs tabular-nums">
                                            {allAvailableTransactions.length}
                                        </Badge>
                                    </div>
                                    <FrameDescription>All posted cashier & bank payments for this student.</FrameDescription>
                                </FrameHeader>

                                <div className="mt-4 flex flex-col divide-y divide-border">
                                    {allAvailableTransactions.length === 0 ? (
                                        <div className="py-6 text-center text-xs text-muted-foreground">
                                            No recorded transactions found for this student.
                                        </div>
                                    ) : (
                                        allAvailableTransactions.map((tx) => (
                                            <div key={tx.id} className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-mono text-xs font-semibold text-foreground">
                                                        {tx.receipt_number ? `OR #${tx.receipt_number}` : `Tx #${tx.transaction_number || tx.id}`}
                                                    </span>
                                                    <span className="font-mono text-sm font-bold text-foreground tabular-nums">
                                                        ₱{tx.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                                    <span>{tx.date ? new Date(tx.date).toLocaleDateString() : "—"} · {tx.payment_method ?? "Cash"}</span>
                                                    <Badge
                                                        variant={["paid", "completed"].includes(tx.status.toLowerCase()) ? "success-light" : "outline"}
                                                        className="px-1.5 py-0 text-[10px]"
                                                    >
                                                        {tx.status}
                                                    </Badge>
                                                </div>
                                                {can_manage && reviewing && (
                                                    <div className="mt-1 flex justify-end">
                                                        <button
                                                            type="button"
                                                            onClick={() => quickSelectTransactionForAdjust(tx)}
                                                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline"
                                                        >
                                                            <SlidersHorizontal className="size-3" />
                                                            Adjust this payment
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </div>
                            </FramePanel>
                        </Frame>

                        {/* Review Timeline */}
                        <Frame>
                            <FramePanel>
                                <FrameHeader>
                                    <div className="flex items-center gap-2">
                                        <History className="text-muted-foreground size-4" />
                                        <FrameTitle>Review Timeline</FrameTitle>
                                    </div>
                                </FrameHeader>

                                <div className="mt-4 flex flex-col gap-4">
                                    {request.events.map((event) => (
                                        <div key={event.id} className="border-border relative flex gap-3 border-l pl-4">
                                            <span className="border-background bg-primary absolute top-1 -left-1.5 size-3 rounded-full border" />
                                            <div className="flex flex-col gap-0.5">
                                                <span className="text-foreground text-xs font-semibold tracking-wider uppercase">
                                                    {event.event.replaceAll("_", " ")}
                                                </span>
                                                <span className="text-muted-foreground text-xs">
                                                    {event.actor_name || "System"} ·{" "}
                                                    {event.created_at ? new Date(event.created_at).toLocaleDateString() : ""}
                                                </span>
                                                {event.note && <p className="text-muted-foreground mt-1 text-xs whitespace-pre-wrap">{event.note}</p>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </FramePanel>
                        </Frame>

                        {/* Reject Request Panel */}
                        {can_manage && reviewing && (
                            <Frame className="border-destructive/30">
                                <FramePanel>
                                    <FrameHeader>
                                        <div className="flex items-center gap-2">
                                            <ShieldAlert className="text-destructive size-4" />
                                            <FrameTitle>Reject Request</FrameTitle>
                                        </div>
                                        <FrameDescription>
                                            Use when Finance cannot validate the payment or requested assessment change.
                                        </FrameDescription>
                                    </FrameHeader>

                                    <form onSubmit={handleReject} className="mt-4 flex flex-col gap-3">
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="reject-note">Rejection Explanation</Label>
                                            <Textarea
                                                id="reject-note"
                                                value={rejectionForm.data.resolution_note}
                                                onChange={(e) => rejectionForm.setData("resolution_note", e.target.value)}
                                                placeholder="Explain clearly to the student why the request could not be validated..."
                                                className="min-h-24 resize-y"
                                                required
                                            />
                                            {rejectionForm.errors.resolution_note && (
                                                <p className="text-destructive text-xs">{rejectionForm.errors.resolution_note}</p>
                                            )}
                                        </div>

                                        <Button type="submit" variant="destructive" size="sm" disabled={rejectionForm.processing} className="w-full">
                                            {rejectionForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                            <XCircle className="mr-1.5 size-4" />
                                            Reject & Notify Student
                                        </Button>
                                    </form>
                                </FramePanel>
                            </Frame>
                        )}
                    </div>
                </div>
            </div>

            {/* Lightbox Dialog for Receipt Image Inspection */}
            {previewImage && (
                <Dialog open={Boolean(previewImage)} onOpenChange={(open) => !open && setPreviewImage(null)}>
                    <DialogContent className="max-w-3xl p-4">
                        <DialogHeader>
                            <DialogTitle className="text-sm font-semibold">{previewImage.title}</DialogTitle>
                        </DialogHeader>
                        <div className="mt-2 flex max-h-[75vh] items-center justify-center overflow-auto rounded-md bg-muted/40 p-2">
                            <img src={previewImage.url} alt="Receipt preview" className="max-h-[70vh] w-auto rounded object-contain" />
                        </div>
                        <div className="mt-2 flex justify-end gap-2">
                            <Button variant="outline" size="sm" onClick={() => window.open(previewImage.url, "_blank")}>
                                <ExternalLink className="mr-1.5 size-3.5" />
                                Open Full Size
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AdminLayout>
    );
}
