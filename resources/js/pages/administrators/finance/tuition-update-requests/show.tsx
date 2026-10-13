import { index as tuitionAdjustmentsIndex } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import {
    claim,
    recordAndResolvePayment,
    reject,
    resolveAdjustment,
    resolvePayment,
    index as tuitionUpdateRequestsIndex,
} from "@/actions/App/Http/Controllers/AdministratorTuitionUpdateRequestController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
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
    History,
    Info,
    Link2,
    Loader2,
    PlusCircle,
    Receipt,
    ReceiptText,
    ShieldAlert,
    XCircle,
} from "lucide-react";
import { useState } from "react";

type ReviewRequest = {
    id: number;
    enrollment_id: number | null;
    student: { id: number; student_number: string; name: string | null; email: string | null };
    school_year: string;
    semester: number;
    concern_type: string;
    receipt_number: string | null;
    details: string;
    status: "pending" | "in_review" | "resolved" | "rejected";
    resolution_note: string | null;
    reviewer_name: string | null;
    submitted_at: string | null;
    resolved_at: string | null;
    tuition: { overall_tuition: number; balance: number; discount: number } | null;
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
    amount: number;
    date: string | null;
};

type Props = {
    user: User;
    request: ReviewRequest;
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
    matching_transactions = [],
    other_transactions = [],
    matching_adjustments = [],
    payment_methods = [],
    can_manage,
    is_current_reviewer,
}: Props) {
    const isPayment = request.concern_type === "missing_payment";
    const reviewing = request.status === "in_review" && is_current_reviewer;

    // Form 1: Link Existing Transaction
    const paymentForm = useForm({
        transaction_id: matching_transactions[0]?.id ? String(matching_transactions[0].id) : "",
        allow_mismatch: false,
        resolution_note: "",
    });

    // Form 2: Record & Resolve Missing Payment (THE DIRECT FIX)
    const recordPaymentForm = useForm({
        amount: request.tuition?.balance ? String(request.tuition.balance) : "",
        payment_method: payment_methods[0]?.value ?? "Cash",
        reference_number: request.receipt_number ?? "",
        resolution_note: request.receipt_number
            ? `Verified payment with official receipt #${request.receipt_number} and recorded to student tuition account.`
            : "Verified payment and recorded to student tuition account.",
    });

    // Form 3: Resolve via Tuition Adjustment
    const adjustmentForm = useForm({
        tuition_adjustment_id: matching_adjustments[0]?.id ? String(matching_adjustments[0].id) : "",
        resolution_note: "",
    });

    // Form 4: Reject Request
    const rejectionForm = useForm({ resolution_note: "" });

    // Active resolution tab: default to "record" if no matching transaction, else "link"
    const [paymentResolveMode, setPaymentResolveMode] = useState<"link" | "record">(matching_transactions.length === 0 ? "record" : "link");

    function claimRequest() {
        router.post(claim.url(request.id), {}, { preserveScroll: true });
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

    const currentStatus = statusMeta[request.status] ?? { label: request.status, variant: "outline" as const };
    const allAvailableTransactions = [...matching_transactions, ...other_transactions];

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
                </header>

                {/* Main 2-Column Grid */}
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    {/* Left Column: Context & Actions */}
                    <div className="flex flex-col gap-6">
                        {/* Student Report Panel */}
                        <Frame>
                            <FramePanel>
                                <FrameHeader>
                                    <FrameTitle>Student Report</FrameTitle>
                                    <FrameDescription>Student's submitted explanation and official payment details.</FrameDescription>
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

                                <div className="mt-4 flex flex-col gap-1.5">
                                    <span className="text-foreground text-xs font-semibold">Statement of Discrepancy:</span>
                                    <p className="border-border bg-card text-muted-foreground rounded-md border p-3.5 text-sm leading-relaxed whitespace-pre-wrap">
                                        {request.details}
                                    </p>
                                </div>

                                {/* Tuition Assessment Snapshot */}
                                {request.tuition && (
                                    <div className="border-border bg-muted/20 mt-4 grid grid-cols-3 gap-3 rounded-md border p-3.5 text-center">
                                        <div>
                                            <span className="text-muted-foreground text-xs">Assessed Tuition</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums">
                                                ₱{request.tuition.overall_tuition.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-xs">Current Balance</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums">
                                                ₱{request.tuition.balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-xs">Discount Applied</span>
                                            <p className="text-foreground mt-0.5 font-semibold tabular-nums">{request.tuition.discount}%</p>
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
                                                    If the student already paid but no transaction exists yet, record it directly. Otherwise, link an
                                                    existing transaction.
                                                </FrameDescription>
                                            </FrameHeader>

                                            <Tabs value={paymentResolveMode} onValueChange={(val) => setPaymentResolveMode(val as "link" | "record")}>
                                                <TabsList className="grid w-full grid-cols-2">
                                                    <TabsTab value="record" className="gap-2">
                                                        <PlusCircle className="size-4" />
                                                        <span>Record & Verify Payment</span>
                                                    </TabsTab>
                                                    <TabsTab value="link" className="gap-2">
                                                        <Link2 className="size-4" />
                                                        <span>Link Existing Transaction ({allAvailableTransactions.length})</span>
                                                    </TabsTab>
                                                </TabsList>

                                                {/* MODE 1: RECORD NEW VERIFIED PAYMENT (Fixes missing payment transaction situation!) */}
                                                <TabsPanel value="record" className="mt-4 flex flex-col gap-4">
                                                    <Alert variant="info">
                                                        <Info aria-hidden="true" />
                                                        <AlertTitle>Record missing cashier or bank payment</AlertTitle>
                                                        <AlertDescription>
                                                            This records the official transaction, updates the student's tuition ledger and balance,
                                                            and resolves this request in one atomic operation.
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
                                                                                recordPaymentForm.setData(
                                                                                    "amount",
                                                                                    String(request.tuition?.balance ?? ""),
                                                                                )
                                                                            }
                                                                            className="text-primary text-xs hover:underline"
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
                                                                    placeholder="e.g. 3500.00"
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
                                                                    <p className="text-destructive text-xs">
                                                                        {recordPaymentForm.errors.payment_method}
                                                                    </p>
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
                                                            <p className="text-muted-foreground text-xs">
                                                                Recorded as the official receipt / invoice number on the ledger.
                                                            </p>
                                                            {recordPaymentForm.errors.reference_number && (
                                                                <p className="text-destructive text-xs">
                                                                    {recordPaymentForm.errors.reference_number}
                                                                </p>
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

                                                {/* MODE 2: LINK EXISTING TRANSACTION */}
                                                <TabsPanel value="link" className="mt-4 flex flex-col gap-4">
                                                    {allAvailableTransactions.length === 0 ? (
                                                        <div className="flex flex-col gap-3 py-4">
                                                            <Empty>
                                                                <EmptyMedia variant="icon">
                                                                    <CreditCard aria-hidden="true" />
                                                                </EmptyMedia>
                                                                <EmptyHeader>
                                                                    <EmptyTitle>No existing transactions found</EmptyTitle>
                                                                    <EmptyDescription>
                                                                        There are no recorded transactions matching this student's account yet. Use
                                                                        the "Record & Verify Payment" tab to record it now.
                                                                    </EmptyDescription>
                                                                </EmptyHeader>
                                                                <Button variant="outline" size="sm" onClick={() => setPaymentResolveMode("record")}>
                                                                    Switch to Record Payment
                                                                </Button>
                                                            </Empty>
                                                        </div>
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
                                                                        {matching_transactions.length > 0 && (
                                                                            <>
                                                                                {matching_transactions.map((tx) => (
                                                                                    <SelectItem key={tx.id} value={String(tx.id)}>
                                                                                        ★ Matching OR: #{tx.transaction_number || tx.id} (OR:{" "}
                                                                                        {tx.receipt_number || "None"}) — ₱{tx.amount.toLocaleString()}
                                                                                    </SelectItem>
                                                                                ))}
                                                                            </>
                                                                        )}
                                                                        {other_transactions.length > 0 && (
                                                                            <>
                                                                                {other_transactions.map((tx) => (
                                                                                    <SelectItem key={tx.id} value={String(tx.id)}>
                                                                                        Other tx #{tx.transaction_number || tx.id} (OR:{" "}
                                                                                        {tx.receipt_number || "None"}) — ₱{tx.amount.toLocaleString()}
                                                                                    </SelectItem>
                                                                                ))}
                                                                            </>
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                                {paymentForm.errors.transaction_id && (
                                                                    <p className="text-destructive text-xs">{paymentForm.errors.transaction_id}</p>
                                                                )}
                                                            </div>

                                                            {/* Option to allow OR mismatch */}
                                                            <div className="border-border bg-muted/30 flex items-start gap-2.5 rounded-md border p-3">
                                                                <input
                                                                    type="checkbox"
                                                                    id="allow-mismatch"
                                                                    checked={paymentForm.data.allow_mismatch}
                                                                    onChange={(e) => paymentForm.setData("allow_mismatch", e.target.checked)}
                                                                    className="accent-primary mt-0.5 size-4"
                                                                />
                                                                <div className="flex flex-col">
                                                                    <label
                                                                        htmlFor="allow-mismatch"
                                                                        className="text-foreground cursor-pointer text-xs font-semibold"
                                                                    >
                                                                        Allow receipt / OR number reconciliation
                                                                    </label>
                                                                    <span className="text-muted-foreground text-xs">
                                                                        Check this if the student made a typo in their OR number or the cashier
                                                                        entered a different format. The request will link and update to the verified
                                                                        OR number.
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
                                                    Apply the approved scholarship, discount, or enrollment correction in the Tuition Adjustments
                                                    ledger, then link the audit record below.
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
                                                        Complete the assessment adjustment in the canonical Tuition Adjustments module first, then
                                                        return here to link its record and notify the student.
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

                        {/* RESOLVED STATUS CARD */}
                        {request.status === "resolved" && (
                            <Frame className="border-success/30">
                                <FramePanel>
                                    <div className="flex flex-col gap-3">
                                        <div className="text-foreground flex items-center gap-2 font-semibold">
                                            <CheckCircle2 className="text-success size-5" />
                                            <span>Request Successfully Resolved</span>
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

                    {/* Right Column: Timeline & Reject */}
                    <div className="flex flex-col gap-6">
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
        </AdminLayout>
    );
}
