import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowDown, ArrowLeft, CheckCircle2, Clock3, Loader2, ReceiptText } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { index as tuitionIndex } from "@/actions/App/Http/Controllers/StudentTuitionController";
import { store as storeTuitionUpdateRequest } from "@/actions/App/Http/Controllers/StudentTuitionUpdateRequestController";

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

const statuses = {
    pending: { label: "Awaiting review", style: "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300" },
    in_review: { label: "In review", style: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300" },
    resolved: { label: "Resolved", style: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" },
    rejected: { label: "Not approved", style: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300" },
};
const guidance: Record<string, { label: string; hint: string }> = {
    missing_payment: {
        label: "My payment is missing",
        hint: "Include the payment date, amount, and how you paid. For example: I paid ₱2,000 at the cashier on September 12, but my balance has not changed.",
    },
    discount: {
        label: "My discount is missing or incorrect",
        hint: "Tell us the discount or scholarship name and what you expected to see on your assessment.",
    },
    subject_change: {
        label: "My subjects or assessment are incorrect",
        hint: "List the subjects added or removed and explain which assessment amount looks incorrect.",
    },
    other: { label: "Something else", hint: "Explain what looks incorrect on your tuition record and what you expected to see." },
};
function periodLabel(period: { school_year: string; semester: number }) {
    return `${period.school_year} · ${period.semester === 1 ? "1st" : "2nd"} Semester`;
}

export default function TuitionUpdateRequests({ auth, requests, periods, selected_period, concerns, error, submitted_request_id }: Props) {
    const initialPeriod =
        periods.find((period) => period.school_year === selected_period.school_year && period.semester === selected_period.semester) ?? periods[0];
    const form = useForm({
        school_year: initialPeriod?.school_year ?? "",
        semester: initialPeriod?.semester ?? 1,
        concern_type: concerns[0]?.value ?? "",
        receipt_number: "",
        details: "",
    });
    const errorSummary = useRef<HTMLDivElement>(null);
    const [submissionError, setSubmissionError] = useState<string | null>(null);
    const confirmation = useRef<HTMLDivElement>(null);
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
            errorSummary.current?.focus();
        }
    }, [form.errors, form.hasErrors, submissionError]);
    useEffect(() => {
        if (submitted_request_id) confirmation.current?.focus();
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
            onSuccess: () => form.reset("receipt_number", "details"),
            onNetworkError: () => {
                setSubmissionError(
                    "We couldn’t confirm your submission. Your details are still here. Check your connection and My requests before trying again.",
                );
                return false;
            },
        });
    }

    return (
        <StudentLayout user={auth.user}>
            <Head title="Report a tuition issue" />
            <main className="mx-auto w-full max-w-3xl space-y-8 p-4 pb-24 md:p-8">
                <header className="space-y-4">
                    <Button variant="ghost" asChild className="-ml-3 min-h-11">
                        <Link href={tuitionIndex.url()}>
                            <ArrowLeft aria-hidden="true" className="mr-2 size-4" />
                            Back to tuition
                        </Link>
                    </Button>
                    <div className="space-y-2">
                        <p className="text-primary flex items-center gap-2 text-sm font-medium">
                            <ReceiptText aria-hidden="true" className="size-4" />
                            Finance support
                        </p>
                                <h1 className="text-balance text-3xl font-semibold tracking-tight">Report a tuition issue</h1>
                                <p className="text-muted-foreground text-pretty leading-6">
                            Tell Finance what looks wrong with your tuition record. You can follow their response here.
                        </p>
                    </div>
                    <a
                        href="#my-requests"
                        className="text-primary inline-flex min-h-11 items-center gap-2 text-sm font-medium underline-offset-4 hover:underline"
                    >
                        My requests ({requests.length})<ArrowDown aria-hidden="true" className="size-4" />
                    </a>
                </header>

                {submittedRequest && (
                    <div
                        ref={confirmation}
                        tabIndex={-1}
                        role="status"
                        className="scroll-mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-5 outline-offset-4"
                    >
                        <h2 className="flex items-center gap-2 font-semibold">
                            <CheckCircle2 aria-hidden="true" className="size-5" />
                            Your request was sent to Finance.
                        </h2>
                        <p className="mt-2 text-sm">
                            Request #{submittedRequest.id} · {periodLabel(submittedRequest)} · {statuses[submittedRequest.status].label}
                        </p>
                        <a
                            href={`#request-${submittedRequest.id}`}
                            className="mt-2 inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4"
                        >
                            View submitted request
                        </a>
                    </div>
                )}

                {error || !periods.length || !concerns.length ? (
                    <Card>
                        <CardContent className="space-y-2 p-6" role="status">
                            <h2 className="font-semibold">We can’t accept a request yet</h2>
                            <p className="text-muted-foreground text-sm leading-6">
                                {error ??
                                    (!periods.length
                                        ? "No enrollment or tuition record is available for your account. Please contact the registrar to check your academic period."
                                        : "Tuition concern options are unavailable. Please contact Finance for help.")}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="overflow-hidden py-0 shadow-sm">
                        <CardContent className="p-0">
                            <form onSubmit={submit} noValidate>
                                {(errors.length > 0 || submissionError) && (
                                    <div
                                        ref={errorSummary}
                                        tabIndex={-1}
                                        role="alert"
                                        aria-labelledby="request-errors-title"
                                        className="border-destructive/30 bg-destructive/5 m-6 rounded-lg border p-4 outline-offset-4"
                                    >
                                        <h2 id="request-errors-title" className="font-semibold">
                                            Check these details before sending
                                        </h2>
                                        {submissionError && <p className="mt-2 text-sm">{submissionError}</p>}
                                        <ul className="text-destructive mt-2 list-inside list-disc space-y-2 text-sm">
                                            {errors.map(([field, message]) => (
                                                <li key={field}>{message}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                <fieldset disabled={form.processing} className="min-w-0 divide-y disabled:opacity-70">
                                    <section className="space-y-4 p-5 sm:p-6" aria-labelledby="period-title">
                                        <h2 id="period-title" className="font-semibold">
                                            <span className="text-primary mr-3 text-sm">01</span>Choose your academic period
                                        </h2>
                                        <div className="space-y-2">
                                            <Label htmlFor="request-period">Academic period (required)</Label>
                                            <select
                                                id="request-period"
                                                value={`${form.data.school_year}|${form.data.semester}`}
                                                aria-invalid={Boolean(form.errors.school_year || form.errors.semester)}
                                                aria-describedby="period-help period-error"
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 min-h-11 w-full rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                                                onChange={(event) => {
                                                    const [schoolYear, semester] = event.target.value.split("|");
                                                    form.setData((data) => ({ ...data, school_year: schoolYear, semester: Number(semester) }));
                                                    form.clearErrors("school_year", "semester", "concern_type");
                                                }}
                                            >
                                                {periods.map((period) => (
                                                    <option
                                                        key={`${period.school_year}|${period.semester}`}
                                                        value={`${period.school_year}|${period.semester}`}
                                                    >
                                                        {period.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <p id="period-help" className="text-muted-foreground text-sm">
                                                Choose the period with the incorrect record. Changing it keeps your details below.
                                            </p>
                                            <p id="period-error" className="text-destructive text-sm">
                                                {form.errors.school_year || form.errors.semester}
                                            </p>
                                        </div>
                                    </section>
                                    <fieldset aria-describedby="concern-error" className="min-w-0 p-5 sm:p-6">
                                        <legend className="float-left mb-4 w-full font-semibold">
                                            <span className="text-primary mr-3 text-sm">02</span>What’s wrong?{" "}
                                            <span className="text-muted-foreground text-sm font-normal">(required)</span>
                                        </legend>
                                        <div className="clear-both space-y-2">
                                            {concerns.map((concern) => (
                                                <label
                                                    key={concern.value}
                                                    className={cn(
                                                        "border-input hover:border-primary/50 flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors",
                                                        form.data.concern_type === concern.value && "border-primary bg-primary/5",
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="concern_type"
                                                        value={concern.value}
                                                        checked={form.data.concern_type === concern.value}
                                                        aria-invalid={Boolean(form.errors.concern_type)}
                                                        aria-describedby="concern-error"
                                                        className="accent-primary mt-0.5 size-4 shrink-0"
                                                        onChange={() => {
                                                            form.setData((data) => ({ ...data, concern_type: concern.value, receipt_number: "" }));
                                                            form.clearErrors("concern_type", "receipt_number");
                                                        }}
                                                    />
                                                    <span>
                                                        <span className="block text-sm font-medium">
                                                            {guidance[concern.value]?.label ?? concern.label}
                                                        </span>
                                                        <span className="text-muted-foreground mt-1 block text-sm leading-5">
                                                            {concern.description}
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                        <p id="concern-error" className="text-destructive mt-2 text-sm">
                                            {form.errors.concern_type}
                                        </p>
                                        {existingRequest && (
                                            <div role="status" className="bg-muted mt-4 rounded-lg p-4 text-sm leading-6">
                                                <p id="duplicate-message" className="font-medium">
                                                    Finance already has a request for this issue.
                                                </p>
                                                <p>You don’t need to send it again. Check request #{existingRequest.id} for updates.</p>
                                                <a
                                                    className="text-primary inline-flex min-h-11 items-center font-medium underline underline-offset-4"
                                                    href={`#request-${existingRequest.id}`}
                                                >
                                                    View existing request
                                                </a>
                                            </div>
                                        )}
                                    </fieldset>
                                    <section className="space-y-5 p-5 sm:p-6" aria-labelledby="details-title">
                                        <h2 id="details-title" className="font-semibold">
                                            <span className="text-primary mr-3 text-sm">03</span>Tell us more
                                        </h2>
                                        {isPaymentConcern && (
                                            <div className="space-y-2">
                                                <Label htmlFor="receipt-number">Official receipt / OR number (required)</Label>
                                                <Input
                                                    id="receipt-number"
                                                    className="min-h-11"
                                                    value={form.data.receipt_number}
                                                    maxLength={255}
                                                    required
                                                    autoComplete="off"
                                                    aria-invalid={Boolean(form.errors.receipt_number)}
                                                    aria-describedby="receipt-help receipt-error"
                                                    onChange={(event) => form.setData("receipt_number", event.target.value)}
                                                />
                                                <p id="receipt-help" className="text-muted-foreground text-sm">
                                                    Look for “OR No.” or “Receipt No.” on your official payment receipt. If you can’t find it, ask the
                                                    cashier before submitting.
                                                </p>
                                                <p id="receipt-error" className="text-destructive text-sm">
                                                    {form.errors.receipt_number}
                                                </p>
                                            </div>
                                        )}
                                        <div className="space-y-2">
                                            <Label htmlFor="request-details">What should Finance check? (required)</Label>
                                            <p id="details-help" className="text-muted-foreground text-sm leading-6">
                                                {guidance[form.data.concern_type]?.hint ?? guidance.other.hint}
                                            </p>
                                            <Textarea
                                                id="request-details"
                                                className="min-h-36 resize-y"
                                                value={form.data.details}
                                                required
                                                minLength={10}
                                                maxLength={2000}
                                                aria-invalid={Boolean(form.errors.details)}
                                                aria-describedby="details-help details-count details-error"
                                                onChange={(event) => form.setData("details", event.target.value)}
                                            />
                                            <p id="details-count" className="text-muted-foreground flex justify-between gap-3 text-xs">
                                                <span>10–2,000 characters</span>
                                                <span className="tabular-nums">{form.data.details.length.toLocaleString()} / 2,000</span>
                                            </p>
                                            <p id="details-error" className="text-destructive text-sm">
                                                {form.errors.details}
                                            </p>
                                        </div>
                                    </section>
                                    <div className="bg-muted/30 space-y-4 p-5 sm:p-6">
                                        <p className="text-muted-foreground text-sm leading-6">
                                            Finance will verify your receipt or assessment before making changes. Sending a request does not
                                            automatically change your balance. You’ll receive a portal notification after review.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={form.processing || Boolean(existingRequest)}
                                            className="min-h-11 w-full sm:w-auto"
                                            aria-describedby={existingRequest ? "duplicate-message" : undefined}
                                        >
                                            {form.processing && (
                                                <Loader2 aria-hidden="true" className="mr-2 size-4 animate-spin motion-reduce:animate-none" />
                                            )}
                                            {form.processing ? "Sending request…" : "Send request to Finance"}
                                        </Button>
                                    </div>
                                </fieldset>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <section id="my-requests" tabIndex={-1} className="scroll-mt-6 space-y-4" aria-labelledby="history-title">
                    <div>
                        <h2 id="history-title" className="text-xl font-semibold tracking-tight">
                            My requests
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">All academic periods · Most recent first</p>
                    </div>
                    {requests.length === 0 ? (
                        <Card>
                            <CardContent className="space-y-2 p-6">
                                <Clock3 aria-hidden="true" className="text-muted-foreground size-5" />
                                <p className="font-medium">No requests yet</p>
                                <p className="text-muted-foreground text-sm">
                                    After you send a request, its progress and Finance’s response will appear here.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        requests.map((request) => (
                            <Card
                                key={request.id}
                                id={`request-${request.id}`}
                                tabIndex={-1}
                                className="target:ring-primary scroll-mt-6 target:ring-2"
                            >
                                <CardContent className="space-y-4 p-5 sm:p-6">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-muted-foreground text-xs font-medium">REQUEST #{request.id}</p>
                                        <Badge variant="outline" className={statuses[request.status].style}>
                                            {statuses[request.status].label}
                                        </Badge>
                                    </div>
                                    <div className="space-y-1">
                                        <h3 className="font-semibold">
                                            {guidance[request.concern_type]?.label ??
                                                concerns.find((concern) => concern.value === request.concern_type)?.label ??
                                                "Tuition concern"}
                                        </h3>
                                        <p className="text-muted-foreground text-sm">{periodLabel(request)}</p>
                                    </div>
                                    <p className="text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-wrap">{request.details}</p>
                                    {request.receipt_number && (
                                        <p className="text-muted-foreground text-sm [overflow-wrap:anywhere]">Receipt: {request.receipt_number}</p>
                                    )}
                                    <p className="text-muted-foreground text-xs">
                                        Submitted{" "}
                                        {request.submitted_at ? (
                                            <time dateTime={request.submitted_at}>{new Date(request.submitted_at).toLocaleDateString()}</time>
                                        ) : (
                                            "—"
                                        )}
                                    </p>
                                    {request.resolution_note ? (
                                        <div className="bg-muted/50 space-y-2 rounded-lg border p-4">
                                            <h4 className="text-sm font-medium">
                                                Finance’s response{request.reviewer_name ? ` · ${request.reviewer_name}` : ""}
                                            </h4>
                                            <p className="text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-wrap">
                                                {request.resolution_note}
                                            </p>
                                            {request.resolved_at && (
                                                <p className="text-muted-foreground text-xs">
                                                    Reviewed{" "}
                                                    <time dateTime={request.resolved_at}>{new Date(request.resolved_at).toLocaleDateString()}</time>
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        ["pending", "in_review"].includes(request.status) && (
                                            <p className="text-muted-foreground border-t pt-3 text-sm">
                                                {request.status === "pending"
                                                    ? "Your request is waiting for Finance to review."
                                                    : "Finance is checking your receipt or assessment."}
                                            </p>
                                        )
                                    )}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>
            </main>
        </StudentLayout>
    );
}
