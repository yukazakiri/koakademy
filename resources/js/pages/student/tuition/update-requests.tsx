import { index as tuitionIndex } from "@/actions/App/Http/Controllers/StudentTuitionController";
import {
    store as storeTuitionUpdateRequest,
    index as tuitionUpdateRequestsIndex,
} from "@/actions/App/Http/Controllers/StudentTuitionUpdateRequestController";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { AlertCircle, ArrowLeft, CheckCircle2, Clock3, FileQuestion, Loader2, ReceiptText, Send, ShieldCheck } from "lucide-react";

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
    auth: { user: { name: string; email: string; avatar?: string; role: string } };
    requests: TuitionRequest[];
    periods: Period[];
    selected_period: { school_year: string; semester: number };
    concerns: Concern[];
    error: string | null;
};

const statusStyle: Record<TuitionRequest["status"], string> = {
    pending: "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300",
    in_review: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300",
    resolved: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    rejected: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300",
};

export default function TuitionUpdateRequests({ auth, requests, periods, selected_period, concerns, error }: Props) {
    const form = useForm({
        school_year: selected_period.school_year,
        semester: selected_period.semester,
        concern_type: "missing_payment",
        receipt_number: "",
        details: "",
    });
    const isPaymentConcern = form.data.concern_type === "missing_payment";

    function changePeriod(value: string) {
        const [schoolYear, semester] = value.split("|");
        router.get(tuitionUpdateRequestsIndex.url({ query: { school_year: schoolYear, semester: Number(semester) } }));
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(storeTuitionUpdateRequest.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset("receipt_number", "details"),
        });
    }

    return (
        <StudentLayout user={auth.user}>
            <Head title="Tuition Update Requests" />
            <main className="mx-auto w-full max-w-5xl space-y-6 p-4 pb-24 md:p-6">
                <header className="border-primary/15 bg-card relative overflow-hidden rounded-2xl border p-5 shadow-sm md:p-7">
                    <div className="bg-primary/10 pointer-events-none absolute -top-20 -right-20 size-56 rounded-full blur-3xl" />
                    <div className="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <div className="text-primary mb-2 flex items-center gap-2 text-xs font-semibold tracking-[0.16em] uppercase">
                                <ReceiptText className="size-4" /> Finance support
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Keep your tuition record accurate</h1>
                            <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
                                Submit a focused request when a payment, discount, or enrolled subject has not yet appeared correctly on your
                                assessment. Finance will verify the official record before making any change.
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={tuitionIndex.url()}>
                                <ArrowLeft className="mr-2 size-4" />
                                Back to tuition
                            </Link>
                        </Button>
                    </div>
                </header>

                {error ? (
                    <Card className="border-destructive/30">
                        <CardContent className="text-destructive flex gap-3 p-5 text-sm">
                            <AlertCircle className="size-5 shrink-0" />
                            {error}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.8fr)]">
                            <Card className="shadow-sm">
                                <CardHeader>
                                    <CardTitle>Submit a tuition update request</CardTitle>
                                    <CardDescription>Choose the academic period and tell Finance exactly what needs review.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form className="space-y-5" onSubmit={submit}>
                                        <div className="space-y-2">
                                            <Label htmlFor="tuition-request-period">Academic period</Label>
                                            <Select value={`${form.data.school_year}|${form.data.semester}`} onValueChange={changePeriod}>
                                                <SelectTrigger id="tuition-request-period">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {periods.map((period) => (
                                                        <SelectItem
                                                            key={`${period.school_year}-${period.semester}`}
                                                            value={`${period.school_year}|${period.semester}`}
                                                        >
                                                            {period.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {form.errors.school_year && <p className="text-destructive text-sm">{form.errors.school_year}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label>What needs to be updated?</Label>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {concerns.map((concern) => (
                                                    <button
                                                        key={concern.value}
                                                        type="button"
                                                        onClick={() => form.setData("concern_type", concern.value)}
                                                        className={cn(
                                                            "rounded-xl border p-3 text-left transition-colors",
                                                            form.data.concern_type === concern.value
                                                                ? "border-primary bg-primary/5 ring-primary/20 ring-1"
                                                                : "hover:border-primary/40",
                                                        )}
                                                    >
                                                        <p className="text-sm font-semibold">{concern.label}</p>
                                                        <p className="text-muted-foreground mt-1 text-xs leading-5">{concern.description}</p>
                                                    </button>
                                                ))}
                                            </div>
                                            {form.errors.concern_type && <p className="text-destructive text-sm">{form.errors.concern_type}</p>}
                                        </div>
                                        {isPaymentConcern && (
                                            <div className="space-y-2">
                                                <Label htmlFor="receipt-number">Official receipt / OR number</Label>
                                                <Input
                                                    id="receipt-number"
                                                    value={form.data.receipt_number}
                                                    onChange={(event) => form.setData("receipt_number", event.target.value)}
                                                    placeholder="Enter the number on your payment receipt"
                                                    autoComplete="off"
                                                />{" "}
                                                <p className="text-muted-foreground text-xs">
                                                    Finance will match this with the official payment ledger.
                                                </p>
                                                {form.errors.receipt_number && (
                                                    <p className="text-destructive text-sm">{form.errors.receipt_number}</p>
                                                )}
                                            </div>
                                        )}
                                        <div className="space-y-2">
                                            <Label htmlFor="tuition-request-details">Details</Label>
                                            <Textarea
                                                id="tuition-request-details"
                                                value={form.data.details}
                                                onChange={(event) => form.setData("details", event.target.value)}
                                                className="min-h-32 resize-y"
                                                placeholder={
                                                    isPaymentConcern
                                                        ? "Include the payment date, amount, and any helpful context."
                                                        : "Describe what is incorrect and the change you expect to see."
                                                }
                                            />
                                            {form.errors.details && <p className="text-destructive text-sm">{form.errors.details}</p>}
                                        </div>
                                        <div className="bg-muted/55 text-muted-foreground flex items-start gap-2 rounded-lg p-3 text-xs leading-5">
                                            <ShieldCheck className="text-primary mt-0.5 size-4 shrink-0" />
                                            Your request does not change your balance automatically. Finance verifies the ledger or assessment before
                                            updating it.
                                        </div>
                                        <Button type="submit" disabled={form.processing || periods.length === 0} className="w-full sm:w-auto">
                                            {form.processing ? (
                                                <>
                                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                                    Submitting…
                                                </>
                                            ) : (
                                                <>
                                                    <Send className="mr-2 size-4" />
                                                    Submit to Finance
                                                </>
                                            )}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                            <Card className="border-primary/15 bg-primary/[0.025] h-fit shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-base">What happens next</CardTitle>
                                </CardHeader>
                                <CardContent className="text-muted-foreground space-y-4 text-sm">
                                    <div className="flex gap-3">
                                        <span className="bg-primary text-primary-foreground flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                            1
                                        </span>
                                        <p>Finance reviews your request and checks the correct academic period.</p>
                                    </div>
                                    <div className="flex gap-3">
                                        <span className="bg-primary text-primary-foreground flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                            2
                                        </span>
                                        <p>
                                            Payments are verified against the official receipt number; assessment issues are checked against your
                                            enrollment.
                                        </p>
                                    </div>
                                    <div className="flex gap-3">
                                        <span className="bg-primary text-primary-foreground flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                            3
                                        </span>
                                        <p>You receive a portal notification with the result and Finance’s note.</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <section className="space-y-3">
                            <div>
                                <h2 className="text-lg font-semibold">My request history</h2>
                                <p className="text-muted-foreground text-sm">Follow each request from submission to Finance’s final review.</p>
                            </div>
                            {requests.length === 0 ? (
                                <Card>
                                    <CardContent className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                        <FileQuestion className="text-muted-foreground size-7" />
                                        <p className="font-medium">No tuition update requests yet</p>
                                        <p className="text-muted-foreground max-w-sm text-sm">
                                            Use the form above if your payment or assessment needs a Finance review.
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                <div className="space-y-3">
                                    {requests.map((request) => (
                                        <Card key={request.id}>
                                            <CardContent className="p-4 md:p-5">
                                                <div className="flex flex-col justify-between gap-3 sm:flex-row">
                                                    <div className="space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="font-semibold">
                                                                {concerns.find((item) => item.value === request.concern_type)?.label ??
                                                                    "Tuition concern"}
                                                            </p>
                                                            <Badge variant="outline" className={statusStyle[request.status]}>
                                                                {request.status.replace("_", " ")}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground text-sm">
                                                            {request.school_year} · {request.semester === 1 ? "1st" : "2nd"} Semester
                                                            {request.receipt_number ? ` · OR ${request.receipt_number}` : ""}
                                                        </p>
                                                        <p className="text-sm leading-6">{request.details}</p>
                                                    </div>
                                                    <p className="text-muted-foreground flex shrink-0 items-center gap-1 text-xs">
                                                        <Clock3 className="size-3.5" />
                                                        {request.submitted_at ? new Date(request.submitted_at).toLocaleDateString() : "Submitted"}
                                                    </p>
                                                </div>
                                                {request.resolution_note && (
                                                    <div
                                                        className={cn(
                                                            "mt-4 rounded-lg border p-3 text-sm",
                                                            request.status === "resolved"
                                                                ? "border-emerald-500/20 bg-emerald-500/5"
                                                                : "border-rose-500/20 bg-rose-500/5",
                                                        )}
                                                    >
                                                        <div className="mb-1 flex items-center gap-2 font-medium">
                                                            {request.status === "resolved" ? (
                                                                <CheckCircle2 className="size-4 text-emerald-600" />
                                                            ) : (
                                                                <AlertCircle className="size-4 text-rose-600" />
                                                            )}
                                                            Finance review{request.reviewer_name ? ` · ${request.reviewer_name}` : ""}
                                                        </div>
                                                        <p className="text-muted-foreground">{request.resolution_note}</p>
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </section>
                    </>
                )}
            </main>
        </StudentLayout>
    );
}
