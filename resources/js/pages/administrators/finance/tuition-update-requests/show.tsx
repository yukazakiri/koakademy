import { index as tuitionAdjustmentsIndex } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import {
    claim,
    reject,
    resolveAdjustment,
    resolvePayment,
    index as tuitionUpdateRequestsIndex,
} from "@/actions/App/Http/Controllers/AdministratorTuitionUpdateRequestController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { ArrowLeft, CircleDollarSign, ClipboardCheck, ExternalLink, Loader2, ReceiptText, ShieldAlert, XCircle } from "lucide-react";

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
    tuition: { overall_tuition: number; balance: number; discount: number } | null;
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
type Props = {
    user: User;
    request: ReviewRequest;
    matching_transactions: Array<{
        id: number;
        transaction_number: string | null;
        receipt_number: string | null;
        status: string;
        amount: number;
        date: string | null;
    }>;
    matching_adjustments: Array<{ id: number; reason: string; created_at: string | null }>;
    can_manage: boolean;
    is_current_reviewer: boolean;
};
const labels: Record<string, string> = {
    missing_payment: "Payment not reflected",
    discount: "Discount concern",
    subject_change: "Subject enrollment change",
    other: "Other concern",
};

export default function TuitionUpdateRequestShow({
    user,
    request,
    matching_transactions,
    matching_adjustments,
    can_manage,
    is_current_reviewer,
}: Props) {
    const paymentForm = useForm({ transaction_id: "", resolution_note: "" });
    const adjustmentForm = useForm({ tuition_adjustment_id: "", resolution_note: "" });
    const rejectionForm = useForm({ resolution_note: "" });
    const reviewing = request.status === "in_review" && is_current_reviewer;
    const isPayment = request.concern_type === "missing_payment";

    function claimRequest() {
        router.post(claim.url(request.id), {}, { preserveScroll: true });
    }
    function resolveWithPayment(event: React.FormEvent) {
        event.preventDefault();
        paymentForm.post(resolvePayment.url(request.id), { preserveScroll: true });
    }
    function resolveWithAdjustment(event: React.FormEvent) {
        event.preventDefault();
        adjustmentForm.post(resolveAdjustment.url(request.id), { preserveScroll: true });
    }
    function rejectRequest(event: React.FormEvent) {
        event.preventDefault();
        rejectionForm.post(reject.url(request.id), { preserveScroll: true });
    }

    return (
        <AdminLayout user={user} title="Tuition Update Request">
            <Head title="Finance · Tuition Update Request" />
            <div className="mx-auto w-full max-w-6xl space-y-6">
                <header className="flex flex-col justify-between gap-4 border-b pb-5 sm:flex-row sm:items-end">
                    <div>
                        <Button variant="ghost" size="sm" asChild className="mb-2 -ml-3">
                            <Link href={tuitionUpdateRequestsIndex.url()}>
                                <ArrowLeft className="mr-2 size-4" />
                                All requests
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">{labels[request.concern_type]}</h1>
                            <Badge variant="outline">{request.status.replace("_", " ")}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Submitted {request.submitted_at ? new Date(request.submitted_at).toLocaleString() : ""} · {request.school_year} ·{" "}
                            {request.semester === 1 ? "1st" : "2nd"} Semester
                        </p>
                    </div>
                    {can_manage && request.status === "pending" && (
                        <Button onClick={claimRequest}>
                            <ClipboardCheck className="mr-2 size-4" />
                            Claim for review
                        </Button>
                    )}
                </header>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Student report</CardTitle>
                                <CardDescription>
                                    Use this report as context; confirm all financial changes against the authoritative ledger or adjustment record.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-muted/50 grid gap-3 rounded-xl p-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Student</p>
                                        <p className="mt-1 font-medium">{request.student.name}</p>
                                        <p className="text-muted-foreground text-sm">{request.student.student_number}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Official receipt / OR</p>
                                        <p className="mt-1 font-medium">{request.receipt_number || "Not applicable"}</p>
                                    </div>
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-semibold">Details</p>
                                    <p className="text-muted-foreground text-sm leading-6 whitespace-pre-wrap">{request.details}</p>
                                </div>
                                {request.tuition && (
                                    <div className="grid grid-cols-3 gap-3 rounded-xl border p-4 text-sm">
                                        <div>
                                            <p className="text-muted-foreground">Assessed</p>
                                            <p className="mt-1 font-semibold">
                                                ₱{request.tuition.overall_tuition.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">Balance</p>
                                            <p className="mt-1 font-semibold">
                                                ₱{request.tuition.balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">Discount</p>
                                            <p className="mt-1 font-semibold">{request.tuition.discount}%</p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        {can_manage &&
                            reviewing &&
                            (isPayment ? (
                                <Card className="border-emerald-500/20">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <CircleDollarSign className="size-5 text-emerald-600" />
                                            Resolve with verified payment
                                        </CardTitle>
                                        <CardDescription>
                                            Only a paid transaction for this student and academic period with the matching OR number can resolve this
                                            request.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <form className="space-y-4" onSubmit={resolveWithPayment}>
                                            <div className="space-y-2">
                                                <Label>Matching payment transaction</Label>
                                                <Select
                                                    value={paymentForm.data.transaction_id}
                                                    onValueChange={(value) => paymentForm.setData("transaction_id", value)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue
                                                            placeholder={
                                                                matching_transactions.length
                                                                    ? "Select a verified transaction"
                                                                    : "No matching payment found"
                                                            }
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {matching_transactions.map((transaction) => (
                                                            <SelectItem key={transaction.id} value={String(transaction.id)}>
                                                                #{transaction.transaction_number || transaction.id} ·{" "}
                                                                {transaction.receipt_number || "No OR"} · ₱{transaction.amount.toLocaleString()}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {paymentForm.errors.transaction_id && (
                                                    <p className="text-destructive text-sm">{paymentForm.errors.transaction_id}</p>
                                                )}
                                            </div>
                                            <ResolutionNote form={paymentForm} />
                                            <Button disabled={paymentForm.processing || matching_transactions.length === 0}>
                                                {paymentForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}Resolve and notify student
                                            </Button>
                                        </form>
                                        {matching_transactions.length === 0 && (
                                            <p className="mt-4 rounded-lg bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">
                                                Record the verified payment through Payments & Collections first, then return here to link it. Finance
                                                access rules for payment recording still apply.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            ) : (
                                <Card className="border-primary/20">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <ReceiptText className="text-primary size-5" />
                                            Resolve with tuition adjustment
                                        </CardTitle>
                                        <CardDescription>
                                            Complete the approved assessment change in the canonical Tuition Adjustments workspace, then link its
                                            audit record here.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Button variant="outline" asChild className="mb-4">
                                            <Link
                                                href={tuitionAdjustmentsIndex.url({
                                                    query: {
                                                        enrollment: request.enrollment_id,
                                                        school_year: request.school_year,
                                                        semester: request.semester,
                                                    },
                                                })}
                                            >
                                                <ExternalLink className="mr-2 size-4" />
                                                Open Tuition Adjustments
                                            </Link>
                                        </Button>
                                        <form className="space-y-4" onSubmit={resolveWithAdjustment}>
                                            <div className="space-y-2">
                                                <Label>Completed adjustment</Label>
                                                <Select
                                                    value={adjustmentForm.data.tuition_adjustment_id}
                                                    onValueChange={(value) => adjustmentForm.setData("tuition_adjustment_id", value)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select the completed adjustment" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {matching_adjustments.map((adjustment) => (
                                                            <SelectItem key={adjustment.id} value={String(adjustment.id)}>
                                                                #{adjustment.id} · {adjustment.reason}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {adjustmentForm.errors.tuition_adjustment_id && (
                                                    <p className="text-destructive text-sm">{adjustmentForm.errors.tuition_adjustment_id}</p>
                                                )}
                                            </div>
                                            <ResolutionNote form={adjustmentForm} />
                                            <Button disabled={adjustmentForm.processing || matching_adjustments.length === 0}>
                                                {adjustmentForm.processing && <Loader2 className="mr-2 size-4 animate-spin" />}Resolve and notify
                                                student
                                            </Button>
                                        </form>
                                    </CardContent>
                                </Card>
                            ))}
                    </div>
                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Review timeline</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {request.events.map((event) => (
                                    <div key={event.id} className="relative border-l pl-4 text-sm">
                                        <span className="bg-primary absolute top-1 -left-1.5 size-3 rounded-full" />
                                        <p className="font-medium">{event.event.replaceAll("_", " ")}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {event.actor_name || "System"} · {event.created_at ? new Date(event.created_at).toLocaleString() : ""}
                                        </p>
                                        {event.note && <p className="text-muted-foreground mt-1">{event.note}</p>}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        {can_manage && reviewing && (
                            <Card className="border-rose-500/20">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <ShieldAlert className="size-4 text-rose-600" />
                                        Reject request
                                    </CardTitle>
                                    <CardDescription>Use this when Finance cannot validate or apply the requested change.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form className="space-y-3" onSubmit={rejectRequest}>
                                        <Textarea
                                            value={rejectionForm.data.resolution_note}
                                            onChange={(event) => rejectionForm.setData("resolution_note", event.target.value)}
                                            placeholder="Explain what the student should do next."
                                            className="min-h-28"
                                        />
                                        {rejectionForm.errors.resolution_note && (
                                            <p className="text-destructive text-sm">{rejectionForm.errors.resolution_note}</p>
                                        )}
                                        <Button variant="destructive" disabled={rejectionForm.processing} className="w-full">
                                            <XCircle className="mr-2 size-4" />
                                            Reject and notify
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </aside>
                </div>
            </div>
        </AdminLayout>
    );
}

function ResolutionNote({ form }: { form: ReturnType<typeof useForm<{ resolution_note: string }>> }) {
    return (
        <div className="space-y-2">
            <Label>Resolution note for the student</Label>
            <Textarea
                value={form.data.resolution_note}
                onChange={(event) => form.setData("resolution_note", event.target.value)}
                placeholder="Explain what was updated and any next step."
                className="min-h-24"
            />
            {form.errors.resolution_note && <p className="text-destructive text-sm">{form.errors.resolution_note}</p>}
        </div>
    );
}
