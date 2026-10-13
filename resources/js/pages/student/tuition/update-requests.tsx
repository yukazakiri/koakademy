import { index as tuitionIndex } from "@/actions/App/Http/Controllers/StudentTuitionController";
import { store as storeTuitionUpdateRequest } from "@/actions/App/Http/Controllers/StudentTuitionUpdateRequestController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import StudentLayout from "@/components/student/student-layout";
import { Button } from "@/components/ui/button";
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
    CheckCircle2,
    Clock,
    CreditCard,
    FileQuestion,
    HelpCircle,
    History,
    Loader2,
    PlusCircle,
    Receipt,
    ReceiptText,
    Sparkles,
    UserCheck,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";

type Period = { school_year: string; semester: number; label: string };
type Concern = { value: string; label: string; description: string };
type TuitionRequest = {
    id: number;
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
};
type Props = {
    auth: { user: User };
    requests: TuitionRequest[];
    periods: Period[];
    selected_period: { school_year: string; semester: number };
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
        hint: "Include payment date, amount, method (Cash, Bank, GCash), and official receipt / reference number. Example: 'Paid ₱3,500 at cashier counter on Oct 10, receipt OR-12345.'",
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

export default function TuitionUpdateRequests({ auth, requests, periods, selected_period, concerns, error, submitted_request_id }: Props) {
    const initialPeriod =
        periods.find((period) => period.school_year === selected_period.school_year && period.semester === selected_period.semester) ?? periods[0];

    const form = useForm({
        school_year: initialPeriod?.school_year ?? "",
        semester: initialPeriod?.semester ?? 1,
        concern_type: concerns[0]?.value ?? "missing_payment",
        receipt_number: "",
        details: "",
    });

    const [activeTab, setActiveTab] = useState<string>(submitted_request_id && requests.length > 0 ? "history" : "new");
    const [submissionError, setSubmissionError] = useState<string | null>(null);
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

    function submit(event: React.FormEvent) {
        event.preventDefault();
        if (form.processing || existingRequest || !periods.length || !concerns.length) return;
        setSubmissionError(null);
        form.transform((data) => ({
            ...data,
            receipt_number: isPaymentConcern ? data.receipt_number.trim() : "",
            details: data.details.trim(),
        }));
        form.post(storeTuitionUpdateRequest.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset("receipt_number", "details");
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
                            <form onSubmit={submit} noValidate>
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

                                    {/* Step 1: Period Selection */}
                                    <FramePanel>
                                        <FrameHeader>
                                            <FrameTitle>1. Select Academic Period</FrameTitle>
                                            <FrameDescription>Choose the school year and semester where the discrepancy appears.</FrameDescription>
                                        </FrameHeader>
                                        <div className="mt-3 flex flex-col gap-2">
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
                                                    Finance is currently reviewing request #{existingRequest.id} for this period and category. You can
                                                    check its status in the "My Requests" tab.
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                    </FramePanel>

                                    {/* Step 3: Details & Receipt */}
                                    <FramePanel>
                                        <FrameHeader>
                                            <FrameTitle>3. Provide Details</FrameTitle>
                                            <FrameDescription>
                                                Share supporting information so Finance can verify your records quickly.
                                            </FrameDescription>
                                        </FrameHeader>
                                        <div className="mt-3 flex flex-col gap-4">
                                            {isPaymentConcern && (
                                                <div className="flex flex-col gap-1.5">
                                                    <Label htmlFor="receipt-input">Official Receipt / Reference No.</Label>
                                                    <Input
                                                        id="receipt-input"
                                                        value={form.data.receipt_number}
                                                        onChange={(e) => form.setData("receipt_number", e.target.value)}
                                                        placeholder="e.g. OR-2026-001, BDO-REF-45892, or Counter Receipt #"
                                                        maxLength={255}
                                                        required
                                                        autoComplete="off"
                                                    />
                                                    <p className="text-muted-foreground text-xs">
                                                        Enter the official receipt (OR) number or bank/cashier payment reference. If you don't have an
                                                        OR yet, write your payment transaction or deposit reference number.
                                                    </p>
                                                    {form.errors.receipt_number && (
                                                        <p className="text-destructive text-xs">{form.errors.receipt_number}</p>
                                                    )}
                                                </div>
                                            )}

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
                                                    Once you submit a request, its review status, assigned reviewer, and Finance’s official response
                                                    will be tracked here.
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

                                                        <div className="flex flex-col gap-1">
                                                            <h3 className="text-foreground font-semibold">
                                                                {concernMeta[req.concern_type]?.label ?? req.concern_type}
                                                            </h3>
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
                                                            ["pending", "in_review"].includes(req.status) && (
                                                                <div className="text-muted-foreground flex items-center gap-2 border-t pt-2.5 text-xs">
                                                                    <Clock className="size-3.5" />
                                                                    <span>
                                                                        {req.status === "pending"
                                                                            ? "Awaiting review by Finance officer."
                                                                            : "Currently under active verification by Finance."}
                                                                    </span>
                                                                </div>
                                                            )
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
        </StudentLayout>
    );
}
