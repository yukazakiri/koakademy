import { index as tuitionIndex } from "@/actions/App/Http/Controllers/StudentTuitionController";
import { store as storeTuitionUpdateRequest } from "@/actions/App/Http/Controllers/StudentTuitionUpdateRequestController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import StudentLayout from "@/components/student/student-layout";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowLeft,
    Camera,
    CheckCircle2,
    Clock,
    CreditCard,
    ExternalLink,
    FileQuestion,
    HelpCircle,
    History,
    ImageIcon,
    Loader2,
    Plus,
    PlusCircle,
    Receipt,
    ReceiptText,
    Sparkles,
    Trash2,
    Upload,
    UserCheck,
    ZoomIn,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";

type Period = { school_year: string; semester: number; label: string };
type Concern = { value: string; label: string; description: string };
type ReceiptItem = {
    id: number;
    receipt_number: string;
    amount: number | null;
    image_url: string | null;
    file_name: string | null;
};
type TuitionRequest = {
    id: number;
    school_year: string;
    semester: number;
    concern_type: string;
    amount?: number | null;
    receipt_number: string | null;
    details: string;
    status: "pending" | "in_review" | "resolved" | "rejected";
    resolution_note: string | null;
    reviewer_name: string | null;
    submitted_at: string | null;
    resolved_at: string | null;
    receipts?: ReceiptItem[];
};
type TuitionSummary = {
    overall_tuition: number;
    total_balance: number;
    paid: number;
    discount: number;
    status: string;
};
type Props = {
    auth: { user: User };
    requests: TuitionRequest[];
    periods: Period[];
    selected_period: { school_year: string; semester: number };
    current_tuition?: TuitionSummary | null;
    concerns: Concern[];
    error: string | null;
    submitted_request_id?: number | null;
};

const statusConfig: Record<string, { label: string; variant: "warning-light" | "info-light" | "success-light" | "destructive-light" }> = {
    pending: { label: "Awaiting review", variant: "warning-light" },
    in_review: { label: "In review", variant: "info-light" },
    resolved: { label: "Resolved", variant: "success-light" },
    rejected: { label: "Not approved", variant: "destructive-light" },
};

const concernMeta: Record<string, { label: string; hint: string; icon: typeof Receipt }> = {
    missing_payment: {
        label: "Payment not reflected",
        hint: "Include payment date, amount, method (Cash, Bank, GCash), and official receipt / reference number. Example: 'Paid ₱5,000 at cashier counter on Aug 25, receipt OR-226432.'",
        icon: CreditCard,
    },
    discount: {
        label: "Discount / scholarship concern",
        hint: "Specify scholarship or discount name (e.g. Academic Excellence, Sibling Discount) and what percentage or amount is missing.",
        icon: Sparkles,
    },
    subject_change: {
        label: "Subject enrollment mismatch",
        hint: "List dropped or added subjects, course codes, and unit counts that differ from your tuition fee assessment.",
        icon: ReceiptText,
    },
    other: {
        label: "General assessment inquiry",
        hint: "Explain what looks incorrect on your tuition record and what you expected to see.",
        icon: HelpCircle,
    },
};

function formatPeriod(period: { school_year: string; semester: number }) {
    return `${period.school_year} · ${period.semester === 1 ? "1st" : "2nd"} Semester`;
}

type LocalReceiptEntry = {
    receipt_number: string;
    amount: string;
    image: File | null;
    preview: string | null;
};

export default function TuitionUpdateRequests({
    auth,
    requests,
    periods,
    selected_period,
    current_tuition,
    concerns,
    error,
    submitted_request_id,
}: Props) {
    const initialPeriod =
        periods.find((period) => period.school_year === selected_period.school_year && period.semester === selected_period.semester) ?? periods[0];

    const form = useForm({
        school_year: initialPeriod?.school_year ?? "",
        semester: initialPeriod?.semester ?? 1,
        concern_type: concerns[0]?.value ?? "missing_payment",
        amount: "",
        receipt_number: "",
        details: "",
        receipts: [
            { receipt_number: "", amount: "", image: null as File | null, preview: null as string | null },
        ] as LocalReceiptEntry[],
    });

    const [activeTab, setActiveTab] = useState<string>(submitted_request_id && requests.length > 0 ? "history" : "new");
    const [submissionError, setSubmissionError] = useState<string | null>(null);
    const [previewImage, setPreviewImage] = useState<{ url: string; title: string } | null>(null);
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const confirmationRef = useRef<HTMLDivElement>(null);

    const isPaymentConcern = form.data.concern_type === "missing_payment";
    const existingRequest = requests.find(
        (request) =>
            request.school_year === form.data.school_year &&
            request.semester === form.data.semester &&
            request.concern_type === form.data.concern_type &&
            ["pending", "in_review"].includes(request.status),
    );
    const submittedRequest = requests.find((request) => request.id === submitted_request_id);
    const errors = Object.entries(form.errors);

    useEffect(() => {
        if (form.hasErrors || submissionError) {
            errorSummaryRef.current?.focus();
        }
    }, [form.errors, form.hasErrors, submissionError]);

    useEffect(() => {
        if (submitted_request_id) {
            setActiveTab("history");
            confirmationRef.current?.focus();
        }
    }, [submitted_request_id]);

    function addReceiptSlot() {
        form.setData("receipts", [
            ...form.data.receipts,
            { receipt_number: "", amount: "", image: null, preview: null },
        ]);
    }

    function removeReceiptSlot(index: number) {
        if (form.data.receipts.length <= 1) return;
        const next = [...form.data.receipts];
        next.splice(index, 1);
        form.setData("receipts", next);
    }

    function handleReceiptImageChange(index: number, file: File | null) {
        const next = [...form.data.receipts];
        if (file) {
            const previewUrl = URL.createObjectURL(file);
            next[index].image = file;
            next[index].preview = previewUrl;
        } else {
            next[index].image = null;
            next[index].preview = null;
        }
        form.setData("receipts", next);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        if (form.processing || existingRequest || !periods.length || !concerns.length) return;
        setSubmissionError(null);

        // Auto-sum amount from receipts if empty
        let totalAmount = form.data.amount.trim();
        if (isPaymentConcern && !totalAmount && form.data.receipts.length > 0) {
            const sum = form.data.receipts
                .map((r) => parseFloat(r.amount) || 0)
                .reduce((acc, curr) => acc + curr, 0);
            if (sum > 0) {
                totalAmount = String(sum);
            }
        }

        // Primary receipt number
        const primaryReceiptNum = form.data.receipt_number.trim() ||
            form.data.receipts.map((r) => r.receipt_number.trim()).filter(Boolean).join(", ");

        form.transform((data) => ({
            ...data,
            amount: totalAmount ? Number(totalAmount) : null,
            receipt_number: isPaymentConcern ? primaryReceiptNum : "",
            details: data.details.trim(),
            receipts: isPaymentConcern
                ? data.receipts
                      .filter((r) => r.receipt_number.trim() || r.amount || r.image)
                      .map((r) => ({
                          receipt_number: r.receipt_number.trim() || undefined,
                          amount: r.amount ? Number(r.amount) : undefined,
                          image: r.image,
                      }))
                : [],
        }));

        form.post(storeTuitionUpdateRequest.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset("amount", "receipt_number", "details", "receipts");
                setActiveTab("history");
            },
            onNetworkError: () => {
                setSubmissionError(
                    "We couldn’t confirm your submission. Your details are preserved. Check connection and My requests before retrying.",
                );
                return false;
            },
        });
    }

    const currentPeriodValue = `${form.data.school_year}|${form.data.semester}`;

    return (
        <StudentLayout user={auth.user}>
            <Head title="Report a tuition issue" />
            <main className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 pb-24 md:p-8">
                {/* Header Section */}
                <header className="flex flex-col gap-3">
                    <div>
                        <Button variant="ghost" size="sm" nativeButton={false} render={<Link href={tuitionIndex.url()} />}>
                            <ArrowLeft aria-hidden="true" className="mr-1.5 size-4" />
                            Back to tuition ledger
                        </Button>
                    </div>
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight">Tuition Update Requests</h1>
                                <Badge variant="primary-light">Finance Support</Badge>
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Report discrepancies in payments, scholarships, or subject enrollments for finance review.
                            </p>
                        </div>
                    </div>
                </header>

                {/* Submitted Confirmation Banner */}
                {submittedRequest && (
                    <div ref={confirmationRef} tabIndex={-1} outline-none="true">
                        <Alert variant="success">
                            <CheckCircle2 aria-hidden="true" />
                            <AlertTitle>Your request was submitted to Finance</AlertTitle>
                            <AlertDescription>
                                Request #{submittedRequest.id} for {formatPeriod(submittedRequest)} is currently{" "}
                                <span className="text-foreground font-medium">
                                    {statusConfig[submittedRequest.status]?.label ?? submittedRequest.status}
                                </span>
                                . You will be notified once reviewed.
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {/* System Error or Missing Records Gate */}
                {error || !periods.length || !concerns.length ? (
                    <Frame>
                        <FramePanel>
                            <Empty>
                                <EmptyMedia variant="icon">
                                    <FileQuestion aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyHeader>
                                    <EmptyTitle>Requests currently unavailable</EmptyTitle>
                                    <EmptyDescription>
                                        {error ??
                                            (!periods.length
                                                ? "No tuition or enrollment record exists for your account. Please consult the registrar to check academic enrollment."
                                                : "Tuition concern options are temporarily unavailable. Please contact the Finance office directly.")}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        </FramePanel>
                    </Frame>
                ) : (
                    <Tabs value={activeTab} onValueChange={(val) => setActiveTab(val)}>
                        <TabsList className="grid w-full grid-cols-2 sm:w-80">
                            <TabsTab value="new" className="gap-2">
                                <PlusCircle className="size-4" />
                                <span>New Request</span>
                            </TabsTab>
                            <TabsTab value="history" className="gap-2">
                                <History className="size-4" />
                                <span>My Requests</span>
                                {requests.length > 0 && (
                                    <Badge variant="outline" className="ml-1 px-1.5 py-0 text-xs">
                                        {requests.length}
                                    </Badge>
                                )}
                            </TabsTab>
                        </TabsList>

                        {/* TAB 1: NEW REQUEST FORM */}
                        <TabsPanel value="new" className="mt-4">
                            <form onSubmit={submit} noValidate encType="multipart/form-data">
                                <Frame>
                                    {/* Error Summary */}
                                    {(errors.length > 0 || submissionError) && (
                                        <FramePanel className="bg-destructive/5 border-destructive/25">
                                            <div ref={errorSummaryRef} tabIndex={-1} role="alert" className="flex flex-col gap-2">
                                                <div className="text-destructive flex items-center gap-2 font-semibold">
                                                    <AlertCircle className="size-4" />
                                                    <span>Please correct the errors before sending</span>
                                                </div>
                                                {submissionError && <p className="text-destructive text-sm">{submissionError}</p>}
                                                <ul className="text-destructive list-inside list-disc text-sm">
                                                    {errors.map(([field, message]) => (
                                                        <li key={field}>{message}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </FramePanel>
                                    )}

                                    {/* Step 1: Period Selection & Current Assessment Display */}
                                    <FramePanel>
                                        <FrameHeader>
                                            <FrameTitle>1. Select Academic Period</FrameTitle>
                                            <FrameDescription>Choose the school year and semester where the discrepancy appears.</FrameDescription>
                                        </FrameHeader>
                                        <div className="mt-3 flex flex-col gap-4">
                                            <div className="flex flex-col gap-2">
                                                <Label htmlFor="period-selector">Academic period</Label>
                                                <Select
                                                    value={currentPeriodValue}
                                                    onValueChange={(val) => {
                                                        const [sy, sem] = val.split("|");
                                                        form.setData((prev) => ({
                                                            ...prev,
                                                            school_year: sy,
                                                            semester: Number(sem),
                                                        }));
                                                        form.clearErrors("school_year", "semester", "concern_type");
                                                    }}
                                                >
                                                    <SelectTrigger id="period-selector" className="w-full">
                                                        <SelectValue placeholder="Select academic period" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {periods.map((p) => (
                                                            <SelectItem key={`${p.school_year}|${p.semester}`} value={`${p.school_year}|${p.semester}`}>
                                                                {p.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {(form.errors.school_year || form.errors.semester) && (
                                                    <p className="text-destructive text-xs">{form.errors.school_year || form.errors.semester}</p>
                                                )}
                                            </div>

                                            {/* Assessment Context Card */}
                                            {current_tuition && (
                                                <div className="rounded-md border border-border bg-muted/20 p-3.5">
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                                        Current Stored Ledger ({form.data.school_year} · Sem {form.data.semester})
                                                    </span>
                                                    <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                        <div>
                                                            <span className="text-[11px] text-muted-foreground">Assessed Tuition</span>
                                                            <p className="font-mono text-sm font-bold text-foreground tabular-nums">
                                                                ₱{current_tuition.overall_tuition.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span className="text-[11px] text-muted-foreground">Total Paid</span>
                                                            <p className="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                                                ₱{current_tuition.paid.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span className="text-[11px] text-muted-foreground">Current Balance</span>
                                                            <p className="font-mono text-sm font-bold text-foreground tabular-nums">
                                                                ₱{current_tuition.total_balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span className="text-[11px] text-muted-foreground">Discount Applied</span>
                                                            <p className="font-mono text-sm font-bold text-foreground tabular-nums">
                                                                {current_tuition.discount}%
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </FramePanel>

                                    {/* Step 2: Concern Type Cards */}
                                    <FramePanel>
                                        <FrameHeader>
                                            <FrameTitle>2. What is your concern?</FrameTitle>
                                            <FrameDescription>Select the category that best describes your tuition fee update.</FrameDescription>
                                        </FrameHeader>
                                        <div className="mt-3 grid gap-2.5 sm:grid-cols-2">
                                            {concerns.map((concern) => {
                                                const meta = concernMeta[concern.value] ?? {
                                                    label: concern.label,
                                                    hint: concern.description,
                                                    icon: HelpCircle,
                                                };
                                                const Icon = meta.icon;
                                                const isSelected = form.data.concern_type === concern.value;

                                                return (
                                                    <button
                                                        key={concern.value}
                                                        type="button"
                                                        onClick={() => {
                                                            form.setData((prev) => ({
                                                                ...prev,
                                                                concern_type: concern.value,
                                                                receipt_number: "",
                                                            }));
                                                            form.clearErrors("concern_type", "receipt_number");
                                                        }}
                                                        className={cn(
                                                            "flex flex-col items-start gap-2 border p-3.5 text-left transition-colors",
                                                            isSelected
                                                                ? "border-primary bg-primary/5 text-foreground ring-primary ring-1"
                                                                : "border-border bg-card hover:bg-muted/50 text-muted-foreground",
                                                        )}
                                                    >
                                                        <div className="flex w-full items-center justify-between">
                                                            <div className="flex items-center gap-2">
                                                                <div
                                                                    className={cn(
                                                                        "flex size-7 items-center justify-center rounded-sm",
                                                                        isSelected
                                                                            ? "bg-primary text-primary-foreground"
                                                                            : "bg-muted text-foreground",
                                                                    )}
                                                                >
                                                                    <Icon className="size-4" />
                                                                </div>
                                                                <span className={cn("text-sm font-semibold", isSelected && "text-foreground")}>
                                                                    {meta.label}
                                                                </span>
                                                            </div>
                                                            {isSelected && <Badge variant="primary-light">Selected</Badge>}
                                                        </div>
                                                        <p className="text-muted-foreground text-xs leading-relaxed">{concern.description}</p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        {form.errors.concern_type && <p className="text-destructive mt-2 text-xs">{form.errors.concern_type}</p>}

                                        {existingRequest && (
                                            <Alert variant="info" className="mt-4">
                                                <Clock aria-hidden="true" />
                                                <AlertTitle>Active request already pending</AlertTitle>
                                                <AlertDescription>
                                                    Finance is currently reviewing request #{existingRequest.id} for this period and category. You can check its status in the "My Requests" tab.
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                    </FramePanel>

                                    {/* Step 3: Details, Amount & Multi-Receipt Photos */}
                                    <FramePanel>
                                        <FrameHeader>
                                            <FrameTitle>3. Provide Details, Amount & Receipts</FrameTitle>
                                            <FrameDescription>
                                                Specify the amount that needs adjustment, attach photos of your receipt(s), and explain the issue.
                                            </FrameDescription>
                                        </FrameHeader>

                                        <div className="mt-3 flex flex-col gap-5">
                                            {/* Amount Field */}
                                            <div className="flex flex-col gap-1.5">
                                                <div className="flex items-center justify-between">
                                                    <Label htmlFor="amount-input">Target / Adjustment Amount (₱)</Label>
                                                    <span className="text-xs text-muted-foreground">What amount should be reflected or credited?</span>
                                                </div>
                                                <Input
                                                    id="amount-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={form.data.amount}
                                                    onChange={(e) => form.setData("amount", e.target.value)}
                                                    placeholder="e.g. 10000.00"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Enter the exact amount you paid or need adjusted on your tuition (e.g. ₱10,000.00 for total downpayment).
                                                </p>
                                                {form.errors.amount && <p className="text-destructive text-xs">{form.errors.amount}</p>}
                                            </div>

                                            {/* Receipts & Photo Upload Section */}
                                            {isPaymentConcern && (
                                                <div className="flex flex-col gap-3 rounded-md border border-border bg-muted/15 p-4">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <span className="text-sm font-semibold text-foreground">Official Receipts / Proof of Payment</span>
                                                            <p className="text-xs text-muted-foreground">
                                                                Upload pictures or take photos of your receipts (supports 2 or more receipts).
                                                            </p>
                                                        </div>
                                                        <Button type="button" variant="outline" size="sm" onClick={addReceiptSlot} className="gap-1.5">
                                                            <Plus className="size-3.5" />
                                                            Add another receipt
                                                        </Button>
                                                    </div>

                                                    <div className="flex flex-col gap-3">
                                                        {form.data.receipts.map((slot, index) => (
                                                            <div key={index} className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                                                                <div className="flex items-center justify-between">
                                                                    <span className="text-xs font-semibold text-foreground">
                                                                        Receipt #{index + 1}
                                                                    </span>
                                                                    {form.data.receipts.length > 1 && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => removeReceiptSlot(index)}
                                                                            className="text-xs text-destructive hover:underline inline-flex items-center gap-1"
                                                                        >
                                                                            <Trash2 className="size-3" />
                                                                            Remove
                                                                        </button>
                                                                    )}
                                                                </div>

                                                                <div className="grid gap-3 sm:grid-cols-2">
                                                                    <div className="flex flex-col gap-1">
                                                                        <Label className="text-xs" htmlFor={`receipt-num-${index}`}>
                                                                            Official Receipt (OR) / Ref Number
                                                                        </Label>
                                                                        <Input
                                                                            id={`receipt-num-${index}`}
                                                                            value={slot.receipt_number}
                                                                            onChange={(e) => {
                                                                                const next = [...form.data.receipts];
                                                                                next[index].receipt_number = e.target.value;
                                                                                form.setData("receipts", next);
                                                                            }}
                                                                            placeholder="e.g. 226432 or OR-2026-001"
                                                                            className="text-xs"
                                                                            required
                                                                        />
                                                                    </div>

                                                                    <div className="flex flex-col gap-1">
                                                                        <Label className="text-xs" htmlFor={`receipt-amt-${index}`}>
                                                                            Amount on this receipt (₱)
                                                                        </Label>
                                                                        <Input
                                                                            id={`receipt-amt-${index}`}
                                                                            type="number"
                                                                            step="0.01"
                                                                            min="0.01"
                                                                            value={slot.amount}
                                                                            onChange={(e) => {
                                                                                const next = [...form.data.receipts];
                                                                                next[index].amount = e.target.value;
                                                                                form.setData("receipts", next);
                                                                            }}
                                                                            placeholder="e.g. 5000.00"
                                                                            className="text-xs"
                                                                        />
                                                                    </div>
                                                                </div>

                                                                {/* Photo / Camera Upload */}
                                                                <div className="flex flex-col gap-1.5">
                                                                    <Label className="text-xs">Receipt Photo / Scan</Label>
                                                                    <div className="flex flex-wrap items-center gap-3">
                                                                        <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium shadow-xs hover:bg-muted/50">
                                                                            <Camera className="size-3.5 text-primary" />
                                                                            <span>Take Photo / Upload Image</span>
                                                                            <input
                                                                                type="file"
                                                                                accept="image/*"
                                                                                capture="environment"
                                                                                onChange={(e) => handleReceiptImageChange(index, e.target.files?.[0] ?? null)}
                                                                                className="sr-only"
                                                                            />
                                                                        </label>

                                                                        {slot.image && (
                                                                            <span className="text-xs text-muted-foreground truncate max-w-xs">
                                                                                {slot.image.name}
                                                                            </span>
                                                                        )}
                                                                    </div>

                                                                    {slot.preview && (
                                                                        <div className="relative mt-1 h-28 w-28 overflow-hidden rounded-md border border-border bg-muted">
                                                                            <img
                                                                                src={slot.preview}
                                                                                alt="Receipt Preview"
                                                                                className="h-full w-full object-cover"
                                                                            />
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => handleReceiptImageChange(index, null)}
                                                                                className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                                                                                title="Remove picture"
                                                                            >
                                                                                <Trash2 className="size-3" />
                                                                            </button>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Details Textarea */}
                                            <div className="flex flex-col gap-1.5">
                                                <Label htmlFor="details-input">Explanation of discrepancy</Label>
                                                <p className="text-muted-foreground text-xs">
                                                    {concernMeta[form.data.concern_type]?.hint ?? concernMeta.other.hint}
                                                </p>
                                                <Textarea
                                                    id="details-input"
                                                    value={form.data.details}
                                                    onChange={(e) => form.setData("details", e.target.value)}
                                                    placeholder="Provide clear details regarding what you paid or what needs adjustment..."
                                                    className="min-h-32 resize-y"
                                                    minLength={10}
                                                    maxLength={2000}
                                                    required
                                                />
                                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                                    <span>Minimum 10 characters</span>
                                                    <span className="tabular-nums">{form.data.details.length} / 2,000</span>
                                                </div>
                                                {form.errors.details && <p className="text-destructive text-xs">{form.errors.details}</p>}
                                            </div>
                                        </div>
                                    </FramePanel>

                                    {/* Footer & Submit */}
                                    <FrameFooter className="bg-muted/30 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                                        <p className="text-muted-foreground text-xs">
                                            Finance will inspect the cashier ledger or assessment records before adjusting your balance.
                                        </p>
                                        <Button type="submit" disabled={form.processing || Boolean(existingRequest)} className="w-full sm:w-auto">
                                            {form.processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                            {form.processing ? "Submitting..." : "Send Request to Finance"}
                                        </Button>
                                    </FrameFooter>
                                </Frame>
                            </form>
                        </TabsPanel>

                        {/* TAB 2: MY REQUESTS HISTORY */}
                        <TabsPanel value="history" className="mt-4">
                            {requests.length === 0 ? (
                                <Frame>
                                    <FramePanel>
                                        <Empty>
                                            <EmptyMedia variant="icon">
                                                <Clock aria-hidden="true" />
                                            </EmptyMedia>
                                            <EmptyHeader>
                                                <EmptyTitle>No tuition requests yet</EmptyTitle>
                                                <EmptyDescription>
                                                    Once you submit a request, its review status, assigned reviewer, and Finance’s official response will be tracked here.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                            <Button variant="outline" size="sm" onClick={() => setActiveTab("new")}>
                                                <PlusCircle className="mr-1.5 size-4" />
                                                Submit a request
                                            </Button>
                                        </Empty>
                                    </FramePanel>
                                </Frame>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {requests.map((req) => {
                                        const config = statusConfig[req.status] ?? {
                                            label: req.status,
                                            variant: "outline" as const,
                                        };
                                        const isTarget = req.id === submitted_request_id;
                                        const reqReceipts = req.receipts ?? [];

                                        return (
                                            <Frame key={req.id} className={cn(isTarget && "ring-primary ring-2")}>
                                                <FramePanel>
                                                    <div className="flex flex-col gap-3">
                                                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2.5">
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                                    Request #{req.id}
                                                                </span>
                                                                <span className="text-muted-foreground">·</span>
                                                                <span className="text-muted-foreground text-xs font-medium">{formatPeriod(req)}</span>
                                                            </div>
                                                            <Badge variant={config.variant}>{config.label}</Badge>
                                                        </div>

                                                        <div className="flex flex-col gap-1.5">
                                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                                <h3 className="text-foreground font-semibold">
                                                                    {concernMeta[req.concern_type]?.label ?? req.concern_type}
                                                                </h3>
                                                                {req.amount !== null && req.amount !== undefined && (
                                                                    <Badge variant="outline" className="font-mono text-xs">
                                                                        Target: ₱{Number(req.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                                    </Badge>
                                                                )}
                                                            </div>

                                                            {req.receipt_number && (
                                                                <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                                                    <Receipt className="size-3.5" />
                                                                    <span>Receipt / Reference:</span>
                                                                    <code className="bg-muted text-foreground rounded px-1.5 py-0.5 font-mono">
                                                                        {req.receipt_number}
                                                                    </code>
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Attached Receipts & Photos */}
                                                        {reqReceipts.length > 0 && (
                                                            <div className="flex flex-wrap gap-2 pt-1">
                                                                {reqReceipts.map((r, i) => (
                                                                    <div key={r.id || i} className="flex items-center gap-2 rounded-md border border-border bg-muted/30 p-2 text-xs">
                                                                        {r.image_url ? (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => setPreviewImage({ url: r.image_url!, title: `Receipt ${r.receipt_number}` })}
                                                                                className="relative h-10 w-10 shrink-0 overflow-hidden rounded border border-border"
                                                                            >
                                                                                <img src={r.image_url} alt="Receipt thumbnail" className="h-full w-full object-cover" />
                                                                            </button>
                                                                        ) : (
                                                                            <Receipt className="size-4 text-muted-foreground" />
                                                                        )}
                                                                        <div>
                                                                            <span className="font-mono font-bold text-foreground">{r.receipt_number}</span>
                                                                            {r.amount !== null && (
                                                                                <span className="text-muted-foreground ml-1.5">
                                                                                    (₱{Number(r.amount).toLocaleString()})
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}

                                                        <p className="text-muted-foreground text-sm leading-relaxed whitespace-pre-wrap">
                                                            {req.details}
                                                        </p>

                                                        <div className="text-muted-foreground text-xs">
                                                            Submitted:{" "}
                                                            {req.submitted_at
                                                                ? new Date(req.submitted_at).toLocaleDateString(undefined, {
                                                                      year: "numeric",
                                                                      month: "short",
                                                                      day: "numeric",
                                                                      hour: "2-digit",
                                                                      minute: "2-digit",
                                                                  })
                                                                : "—"}
                                                        </div>

                                                        {/* Resolution Note or Pending State */}
                                                        {req.resolution_note ? (
                                                            <div className="border-border bg-muted/40 flex flex-col gap-2 rounded-md border p-3.5">
                                                                <div className="text-foreground flex items-center justify-between text-xs font-medium">
                                                                    <span className="flex items-center gap-1.5">
                                                                        <UserCheck className="text-primary size-3.5" />
                                                                        Finance Response
                                                                        {req.reviewer_name ? ` (${req.reviewer_name})` : ""}
                                                                    </span>
                                                                    {req.resolved_at && (
                                                                        <span className="text-muted-foreground">
                                                                            {new Date(req.resolved_at).toLocaleDateString()}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <p className="text-foreground text-sm leading-relaxed whitespace-pre-wrap">
                                                                    {req.resolution_note}
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <div className="text-muted-foreground flex items-center gap-2 border-t pt-2 text-xs">
                                                                <Clock className="size-3.5" />
                                                                <span>Under review by Finance team.</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </FramePanel>
                                            </Frame>
                                        );
                                    })}
                                </div>
                            )}
                        </TabsPanel>
                    </Tabs>
                )}
            </main>

            {/* Lightbox / Preview Dialog */}
            {previewImage && (
                <Dialog open={Boolean(previewImage)} onOpenChange={(open) => !open && setPreviewImage(null)}>
                    <DialogContent className="max-w-2xl p-4">
                        <DialogHeader>
                            <DialogTitle className="text-sm font-semibold">{previewImage.title}</DialogTitle>
                        </DialogHeader>
                        <div className="mt-2 flex max-h-[70vh] items-center justify-center overflow-auto rounded bg-muted/30 p-2">
                            <img src={previewImage.url} alt="Receipt preview" className="max-h-[65vh] w-auto rounded object-contain" />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </StudentLayout>
    );
}
