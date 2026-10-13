import {
    show as showTuitionUpdateRequest,
    index as tuitionUpdateRequestsIndex,
} from "@/actions/App/Http/Controllers/AdministratorTuitionUpdateRequestController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowRight, ClipboardCheck, FileSpreadsheet, RotateCcw, Search, UserRound } from "lucide-react";
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

const concernLabels: Record<string, string> = {
    missing_payment: "Payment not reflected",
    discount: "Discount / Scholarship",
    subject_change: "Subject enrollment mismatch",
    other: "Other assessment inquiry",
};

const statusMeta: Record<string, { label: string; variant: "warning-light" | "info-light" | "success-light" | "destructive-light" }> = {
    pending: { label: "Pending", variant: "warning-light" },
    in_review: { label: "In Review", variant: "info-light" },
    resolved: { label: "Resolved", variant: "success-light" },
    rejected: { label: "Rejected", variant: "destructive-light" },
};

export default function TuitionUpdateRequestsIndex({ user, requests, filters, statuses, concerns, can_manage }: Props) {
    const [search, setSearch] = useState(String(filters.search ?? ""));

    const counts = useMemo(() => {
        const pending = requests.data.filter((r) => r.status === "pending").length;
        const inReview = requests.data.filter((r) => r.status === "in_review").length;
        const resolved = requests.data.filter((r) => r.status === "resolved").length;
        return { pending, inReview, resolved, totalOnPage: requests.data.length };
    }, [requests.data]);

    function updateFilter(key: string, value: string) {
        const next = { ...filters, [key]: value === "all" ? undefined : value, search: search || undefined };
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

    function resetFilters() {
        setSearch("");
        router.get(tuitionUpdateRequestsIndex.url(), {}, { preserveState: true, replace: true });
    }

    const hasActiveFilters = Boolean(filters.search || filters.status || filters.concern_type);

    return (
        <AdminLayout user={user} title="Tuition Update Requests">
            <Head title="Finance · Tuition Update Requests" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                {/* Header */}
                <header className="flex flex-col justify-between gap-4 border-b pb-5 md:flex-row md:items-end">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <Badge variant="primary-light" className="gap-1.5 tracking-wider uppercase">
                                <ClipboardCheck className="size-3.5" />
                                Finance Queue
                            </Badge>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Tuition Update Requests</h1>
                        <p className="text-muted-foreground max-w-3xl text-sm">
                            Verify and reconcile student-reported tuition discrepancies. Link official receipt payments or tuition adjustments to
                            resolve requests.
                        </p>
                    </div>
                </header>

                {/* KPI Metrics Row */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-xs font-medium">Pending Review</span>
                            <Badge variant="warning-light">Action Needed</Badge>
                        </div>
                        <p className="mt-2 text-2xl font-bold tracking-tight tabular-nums">{counts.pending}</p>
                        <p className="text-muted-foreground mt-0.5 text-xs">On current page</p>
                    </Card>

                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-xs font-medium">In Review</span>
                            <Badge variant="info-light">Active</Badge>
                        </div>
                        <p className="mt-2 text-2xl font-bold tracking-tight tabular-nums">{counts.inReview}</p>
                        <p className="text-muted-foreground mt-0.5 text-xs">Claimed by reviewers</p>
                    </Card>

                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-xs font-medium">Resolved</span>
                            <Badge variant="success-light">Completed</Badge>
                        </div>
                        <p className="mt-2 text-2xl font-bold tracking-tight tabular-nums">{counts.resolved}</p>
                        <p className="text-muted-foreground mt-0.5 text-xs">Reconciled & notified</p>
                    </Card>

                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-xs font-medium">Total Requests</span>
                            <Badge variant="outline">All records</Badge>
                        </div>
                        <p className="mt-2 text-2xl font-bold tracking-tight tabular-nums">{requests.total}</p>
                        <p className="text-muted-foreground mt-0.5 text-xs">Across all pages</p>
                    </Card>
                </div>

                {/* Filter Toolbar Card */}
                <Card className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <form onSubmit={submitSearch} className="flex flex-1 items-center gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by student name, ID, or OR number..."
                                    className="pl-8"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>

                        <div className="flex flex-wrap items-center gap-2">
                            <div className="w-44">
                                <Select value={String(filters.status ?? "all")} onValueChange={(val) => updateFilter("status", val)}>
                                    <SelectTrigger size="sm" className="w-full">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All statuses</SelectItem>
                                        {statuses.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {statusMeta[s]?.label ?? s.replace("_", " ")}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-52">
                                <Select value={String(filters.concern_type ?? "all")} onValueChange={(val) => updateFilter("concern_type", val)}>
                                    <SelectTrigger size="sm" className="w-full">
                                        <SelectValue placeholder="All concerns" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All concerns</SelectItem>
                                        {concerns.map((c) => (
                                            <SelectItem key={c} value={c}>
                                                {concernLabels[c] ?? c}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {hasActiveFilters && (
                                <Button variant="ghost" size="sm" onClick={resetFilters} className="text-muted-foreground">
                                    <RotateCcw className="mr-1.5 size-3.5" />
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Queue List in ReUI Frame */}
                <Frame>
                    <FramePanel className="p-0">
                        <div className="divide-border divide-y">
                            {requests.data.length === 0 ? (
                                <div className="py-12">
                                    <Empty>
                                        <EmptyMedia variant="icon">
                                            <FileSpreadsheet aria-hidden="true" />
                                        </EmptyMedia>
                                        <EmptyHeader>
                                            <EmptyTitle>No tuition update requests found</EmptyTitle>
                                            <EmptyDescription>
                                                {hasActiveFilters
                                                    ? "No requests match the selected search terms or filters. Try adjusting your query."
                                                    : "There are currently no tuition update requests awaiting review."}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                        {hasActiveFilters && (
                                            <Button variant="outline" size="sm" onClick={resetFilters}>
                                                Clear filters
                                            </Button>
                                        )}
                                    </Empty>
                                </div>
                            ) : (
                                requests.data.map((req) => {
                                    const meta = statusMeta[req.status] ?? {
                                        label: req.status,
                                        variant: "outline" as const,
                                    };
                                    const canClaimAndReview = can_manage && req.status === "pending";

                                    return (
                                        <article
                                            key={req.id}
                                            className="hover:bg-muted/30 flex flex-col justify-between gap-4 p-4 transition-colors md:flex-row md:items-center"
                                        >
                                            <div className="flex items-start gap-3.5">
                                                <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-sm">
                                                    <UserRound className="size-4" />
                                                </div>

                                                <div className="flex flex-col gap-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="text-foreground font-semibold">{req.student.name || "Student Record"}</span>
                                                        <span className="text-muted-foreground font-mono text-xs">#{req.student.student_number}</span>
                                                        <Badge variant={meta.variant}>{meta.label}</Badge>
                                                    </div>

                                                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                                        <span className="text-foreground font-medium">
                                                            {concernLabels[req.concern_type] ?? req.concern_type}
                                                        </span>
                                                        <span>·</span>
                                                        <span>
                                                            {req.school_year} ({req.semester === 1 ? "1st Sem" : "2nd Sem"})
                                                        </span>
                                                        {req.receipt_number && (
                                                            <>
                                                                <span>·</span>
                                                                <span className="text-foreground font-mono">OR #{req.receipt_number}</span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between gap-4 md:justify-end">
                                                <div className="text-muted-foreground hidden text-right text-xs lg:block">
                                                    <div>{req.reviewer_name ? `Reviewer: ${req.reviewer_name}` : "Unassigned"}</div>
                                                    <div>
                                                        {req.submitted_at
                                                            ? new Date(req.submitted_at).toLocaleDateString(undefined, {
                                                                  month: "short",
                                                                  day: "numeric",
                                                                  year: "numeric",
                                                              })
                                                            : ""}
                                                    </div>
                                                </div>

                                                <Button
                                                    size="sm"
                                                    variant={canClaimAndReview ? "default" : "outline"}
                                                    nativeButton={false}
                                                    render={<Link href={showTuitionUpdateRequest.url(req.id)} />}
                                                >
                                                    {canClaimAndReview ? "Review" : "View Details"}
                                                    <ArrowRight className="ml-1.5 size-3.5" />
                                                </Button>
                                            </div>
                                        </article>
                                    );
                                })
                            )}
                        </div>
                    </FramePanel>
                </Frame>

                {/* Pagination */}
                {requests.links.length > 3 && (
                    <nav className="flex flex-wrap justify-center gap-1" aria-label="Pagination">
                        {requests.links.map((link, idx) => (
                            <Button
                                key={`${link.label}-${idx}`}
                                variant={link.active ? "default" : "outline"}
                                size="sm"
                                disabled={!link.url}
                                nativeButton={false}
                                render={
                                    link.url ? (
                                        <Link href={link.url}>{link.label.replace(/&laquo;|&raquo;/g, "").trim()}</Link>
                                    ) : (
                                        <span>{link.label.replace(/&laquo;|&raquo;/g, "").trim()}</span>
                                    )
                                }
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AdminLayout>
    );
}
