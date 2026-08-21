import {
    show as showTuitionUpdateRequest,
    index as tuitionUpdateRequestsIndex,
} from "@/actions/App/Http/Controllers/AdministratorTuitionUpdateRequestController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowRight, ClipboardCheck, Clock3, Search, UserRound } from "lucide-react";
import { useMemo, useState } from "react";

type Row = {
    id: number;
    student: { student_number: string; name: string | null };
    school_year: string;
    semester: number;
    concern_type: string;
    receipt_number: string | null;
    status: "pending" | "in_review" | "resolved" | "rejected";
    reviewer_name: string | null;
    submitted_at: string | null;
};
type Props = {
    user: User;
    requests: { data: Row[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    filters: Record<string, string | number | undefined>;
    statuses: string[];
    concerns: string[];
    can_manage: boolean;
};

const concernLabel: Record<string, string> = {
    missing_payment: "Payment not reflected",
    discount: "Discount concern",
    subject_change: "Subject enrollment change",
    other: "Other concern",
};
const statusTone: Record<string, string> = {
    pending: "bg-amber-500/10 text-amber-700 border-amber-500/25",
    in_review: "bg-sky-500/10 text-sky-700 border-sky-500/25",
    resolved: "bg-emerald-500/10 text-emerald-700 border-emerald-500/25",
    rejected: "bg-rose-500/10 text-rose-700 border-rose-500/25",
};

export default function TuitionUpdateRequestsIndex({ user, requests, filters, statuses, concerns, can_manage }: Props) {
    const [search, setSearch] = useState(String(filters.search ?? ""));
    const activeCount = useMemo(
        () => requests.data.filter((item) => item.status === "pending" || item.status === "in_review").length,
        [requests.data],
    );

    function updateFilter(key: string, value: string) {
        const next = { ...filters, [key]: value === "all" ? undefined : value, search };
        router.get(tuitionUpdateRequestsIndex.url({ query: next }), {}, { preserveState: true, replace: true });
    }

    function submitSearch(event: React.FormEvent) {
        event.preventDefault();
        router.get(
            tuitionUpdateRequestsIndex.url({ query: { ...filters, search: search || undefined } }),
            {},
            { preserveState: true, replace: true },
        );
    }

    return (
        <AdminLayout user={user} title="Tuition Update Requests">
            <Head title="Finance · Tuition Update Requests" />
            <div className="mx-auto w-full max-w-7xl space-y-6">
                <header className="flex flex-col justify-between gap-4 border-b pb-5 md:flex-row md:items-end">
                    <div>
                        <div className="text-primary mb-2 flex items-center gap-2 text-xs font-semibold tracking-[0.16em] uppercase">
                            <ClipboardCheck className="size-4" /> Finance review queue
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Tuition Update Requests</h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm">
                            Review student-reported payment and assessment discrepancies, then link each result to the official payment or tuition
                            adjustment record.
                        </p>
                    </div>
                    <Badge variant="outline" className="w-fit gap-2 px-3 py-2 text-sm">
                        <Clock3 className="size-4" />
                        {activeCount} active on this page
                    </Badge>
                </header>
                <Card>
                    <CardContent className="grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_180px_220px]">
                        <form className="flex gap-2" onSubmit={submitSearch}>
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search student, ID, or OR number"
                            />
                            <Button type="submit" variant="secondary">
                                <Search className="mr-2 size-4" />
                                Search
                            </Button>
                        </form>
                        <Select value={String(filters.status ?? "all")} onValueChange={(value) => updateFilter("status", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status.replace("_", " ")}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={String(filters.concern_type ?? "all")} onValueChange={(value) => updateFilter("concern_type", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="All concerns" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All concerns</SelectItem>
                                {concerns.map((concern) => (
                                    <SelectItem key={concern} value={concern}>
                                        {concernLabel[concern]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Requests</CardTitle>
                        <CardDescription>
                            {requests.total} request{requests.total === 1 ? "" : "s"} match the current filters.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y">
                            {requests.data.length === 0 ? (
                                <div className="text-muted-foreground px-6 py-16 text-center text-sm">
                                    No tuition update requests match these filters.
                                </div>
                            ) : (
                                requests.data.map((request) => (
                                    <article
                                        key={request.id}
                                        className="hover:bg-muted/35 flex flex-col gap-4 px-5 py-4 transition-colors md:flex-row md:items-center"
                                    >
                                        <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full">
                                            <UserRound className="size-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold">{request.student.name || "Student record"}</p>
                                                <span className="text-muted-foreground text-xs">{request.student.student_number}</span>
                                                <Badge variant="outline" className={statusTone[request.status]}>
                                                    {request.status.replace("_", " ")}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {concernLabel[request.concern_type]} · {request.school_year} ·{" "}
                                                {request.semester === 1 ? "1st" : "2nd"} Semester
                                                {request.receipt_number ? ` · OR ${request.receipt_number}` : ""}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <p className="text-muted-foreground hidden text-right text-xs lg:block">
                                                {request.reviewer_name ? `Reviewer: ${request.reviewer_name}` : "Unassigned"}
                                                <br />
                                                {request.submitted_at ? new Date(request.submitted_at).toLocaleDateString() : ""}
                                            </p>
                                            <Button variant={can_manage && request.status === "pending" ? "default" : "outline"} asChild>
                                                <Link href={showTuitionUpdateRequest.url(request.id)}>
                                                    {can_manage && request.status === "pending" ? "Review" : "View"}
                                                    <ArrowRight className="ml-2 size-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </article>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
                {requests.links.length > 3 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {requests.links.map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                variant={link.active ? "default" : "outline"}
                                size="sm"
                                disabled={!link.url}
                                asChild={Boolean(link.url)}
                            >
                                {link.url ? (
                                    <Link href={link.url}>{link.label.replace(/&laquo;|&raquo;/g, "")}</Link>
                                ) : (
                                    <span>{link.label.replace(/&laquo;|&raquo;/g, "")}</span>
                                )}
                            </Button>
                        ))}
                    </nav>
                )}
            </div>
        </AdminLayout>
    );
}
