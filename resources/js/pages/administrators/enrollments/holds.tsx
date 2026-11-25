import { searchStudents } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import {
    index as holdsIndexRoute,
    override as overrideHoldRoute,
    release as releaseHoldRoute,
    store as storeHoldRoute,
} from "@/actions/App/Http/Controllers/AdministratorStudentHoldController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Loader2,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    ShieldOff,
    Unlock,
} from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { formatShortDate } from "./enrollment-utils";
import type { StudentHoldItem } from "./types";

interface HoldsPageProps {
    user: User;
    holds: {
        data: StudentHoldItem[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    offices: Array<{ value: string; label: string }>;
    stats: {
        active: number;
        blocking_enrollment: number;
        overridden: number;
        released: number;
        by_office: Record<string, number>;
    };
    filters: {
        status: string;
        office: string;
        search: string;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

interface StudentSearchResult {
    id: number;
    student_id: string | number | null;
    full_name: string;
    course_code: string | null;
}

export default function AdministratorStudentHoldsPage({ user, holds, offices, stats, filters, flash }: HoldsPageProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [placeOpen, setPlaceOpen] = useState(false);
    const [releaseTarget, setReleaseTarget] = useState<StudentHoldItem | null>(null);
    const [releaseNotes, setReleaseNotes] = useState("");
    const [overrideTarget, setOverrideTarget] = useState<StudentHoldItem | null>(null);
    const [overrideReason, setOverrideReason] = useState("");
    const [busy, setBusy] = useState(false);

    // Place Hold form state
    const [studentQuery, setStudentQuery] = useState("");
    const [studentResults, setStudentResults] = useState<StudentSearchResult[]>([]);
    const [searchingStudents, setSearchingStudents] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentSearchResult | null>(null);
    const [office, setOffice] = useState("registrar");
    const [severity, setSeverity] = useState<"blocking" | "warning">("blocking");
    const [title, setTitle] = useState("");
    const [reason, setReason] = useState("");
    const [blocksEnrollment, setBlocksEnrollment] = useState(true);
    const [blocksAddDrop, setBlocksAddDrop] = useState(false);
    const [blocksGradeRelease, setBlocksGradeRelease] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    useEffect(() => {
        if (!placeOpen || studentQuery.trim().length < 2) {
            setStudentResults([]);
            return;
        }
        const timer = setTimeout(async () => {
            setSearchingStudents(true);
            try {
                const response = await axios.get<StudentSearchResult[]>(searchStudents.url(), {
                    params: { search: studentQuery.trim() },
                });
                setStudentResults(Array.isArray(response.data) ? response.data : []);
            } catch {
                setStudentResults([]);
            } finally {
                setSearchingStudents(false);
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [placeOpen, studentQuery]);

    const applyFilter = (overrides: Partial<HoldsPageProps["filters"]>) => {
        router.get(
            holdsIndexRoute.url(),
            {
                status: overrides.status ?? filters.status,
                office: overrides.office ?? filters.office,
                search: overrides.search !== undefined ? overrides.search : search,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handlePlaceHold = (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedStudent) {
            toast.error("Select a student first.");
            return;
        }
        if (!title.trim() || !reason.trim()) {
            toast.error("Enter both a hold title and reason.");
            return;
        }

        setBusy(true);
        router.post(
            storeHoldRoute.url(),
            {
                student_id: selectedStudent.id,
                office,
                severity,
                title: title.trim(),
                reason: reason.trim(),
                blocks_enrollment: blocksEnrollment,
                blocks_add_drop: blocksAddDrop,
                blocks_grade_release: blocksGradeRelease,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPlaceOpen(false);
                    setSelectedStudent(null);
                    setStudentQuery("");
                    setTitle("");
                    setReason("");
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    toast.error(typeof first === "string" ? first : "Failed to place hold.");
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const handleReleaseHold = (event: React.FormEvent) => {
        event.preventDefault();
        if (!releaseTarget) return;
        setBusy(true);
        router.post(
            releaseHoldRoute.url(releaseTarget.id),
            { release_notes: releaseNotes.trim() || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReleaseTarget(null);
                    setReleaseNotes("");
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const handleOverrideHold = (event: React.FormEvent) => {
        event.preventDefault();
        if (!overrideTarget || overrideReason.trim().length < 5) {
            toast.error("Provide an audit reason (at least 5 characters).");
            return;
        }
        setBusy(true);
        router.post(
            overrideHoldRoute.url(overrideTarget.id),
            { override_reason: overrideReason.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOverrideTarget(null);
                    setOverrideReason("");
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    toast.error(typeof first === "string" ? first : "Failed to override hold.");
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <AdminLayout user={user} title="Holds & Clearances">
            <Head title="Administrators • Holds & Clearances" />

            <div className="space-y-4 pb-10">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route("administrators.enrollments.index")}
                            className={buttonVariants({ variant: "ghost", size: "icon-sm", className: "size-8" })}
                            title="Back to Enrollments"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-[-0.025em]">Holds &amp; Clearances</h1>
                            <p className="text-muted-foreground text-xs">
                                Cross-office student holds with enrollment gating and audited administrator overrides.
                            </p>
                        </div>
                    </div>

                    <Button size="sm" className="h-8 gap-1.5" onClick={() => setPlaceOpen(true)}>
                        <Plus className="size-3.5" />
                        Place hold
                    </Button>
                </header>

                {/* KPI Strip */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[
                        { label: "Active holds", value: stats.active, icon: ShieldAlert, tone: "text-amber-600 dark:text-amber-400" },
                        { label: "Blocking enrollment", value: stats.blocking_enrollment, icon: AlertTriangle, tone: "text-destructive" },
                        { label: "Audited overrides", value: stats.overridden, icon: ShieldOff, tone: "text-blue-600 dark:text-blue-400" },
                        { label: "Released clearances", value: stats.released, icon: ShieldCheck, tone: "text-emerald-600 dark:text-emerald-400" },
                    ].map((stat) => (
                        <Frame key={stat.label} spacing="xs">
                            <FramePanel className="flex items-center justify-between p-3.5">
                                <div>
                                    <p className="text-muted-foreground text-[11px] font-medium">{stat.label}</p>
                                    <p className="mt-0.5 text-xl font-semibold tabular-nums">{stat.value}</p>
                                </div>
                                <stat.icon className={cn("size-5 opacity-80", stat.tone)} />
                            </FramePanel>
                        </Frame>
                    ))}
                </div>

                {/* Filter Bar */}
                <Frame spacing="xs">
                    <FramePanel className="space-y-3 p-3">
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-0.5">
                            {[
                                { value: "all", label: "All offices", count: stats.active },
                                ...offices.map((o) => ({ value: o.value, label: o.label, count: stats.by_office[o.value] ?? 0 })),
                            ].map((chip) => {
                                const active = filters.office === chip.value;
                                return (
                                    <button
                                        key={chip.value}
                                        type="button"
                                        onClick={() => applyFilter({ office: chip.value })}
                                        className={cn(
                                            "inline-flex shrink-0 items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors",
                                            active
                                                ? "border-foreground/20 bg-foreground text-background"
                                                : "border-border/70 bg-background/60 text-muted-foreground hover:text-foreground hover:bg-muted/60",
                                        )}
                                    >
                                        <span>{chip.label}</span>
                                        <span className={cn("text-[10.5px] tabular-nums", active ? "text-background/75" : "text-muted-foreground")}>
                                            {chip.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <form
                                className="relative flex-1"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    applyFilter({ search });
                                }}
                            >
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search student name, ID, hold title, or reason..."
                                    className="h-8 pl-8 text-xs"
                                />
                            </form>

                            <div className="border-border/80 bg-muted/50 inline-flex items-center rounded-lg border p-0.5">
                                {(["active", "overridden", "released", "all"] as const).map((statusKey) => (
                                    <button
                                        key={statusKey}
                                        type="button"
                                        onClick={() => applyFilter({ status: statusKey })}
                                        className={cn(
                                            "rounded-md px-2.5 py-1 text-xs font-medium capitalize transition-colors",
                                            filters.status === statusKey
                                                ? "bg-background text-foreground shadow-xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        {statusKey}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </FramePanel>
                </Frame>

                {/* Holds Table */}
                <Frame spacing="xs">
                    <FramePanel className="divide-y p-0">
                        {holds.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                <CheckCircle2 className="text-muted-foreground/60 size-8" />
                                <p className="text-sm font-medium">No matching holds</p>
                                <p className="text-muted-foreground text-xs">
                                    No student holds match the selected office and status filters.
                                </p>
                            </div>
                        ) : (
                            holds.data.map((hold) => (
                                <div key={hold.id} className="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="text-sm font-semibold">{hold.student_name ?? `Student #${hold.student_id}`}</span>
                                            {hold.student_number && (
                                                <span className="text-muted-foreground font-mono text-xs">ID {hold.student_number}</span>
                                            )}
                                            {hold.course_code && (
                                                <Badge variant="outline" size="xs" radius="full">
                                                    {hold.course_code}
                                                </Badge>
                                            )}
                                            <Badge variant="secondary" size="xs" radius="full">
                                                {hold.office_label}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    hold.status === "released"
                                                        ? "success-light"
                                                        : hold.status === "overridden"
                                                          ? "info-light"
                                                          : hold.severity === "blocking"
                                                            ? "destructive-light"
                                                            : "warning-light"
                                                }
                                                size="xs"
                                                radius="full"
                                                className="capitalize"
                                            >
                                                {hold.status === "active" ? hold.severity : hold.status}
                                            </Badge>
                                        </div>

                                        <div>
                                            <p className="text-xs font-medium">{hold.title}</p>
                                            <p className="text-muted-foreground text-xs">{hold.reason}</p>
                                        </div>

                                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                            <span>
                                                Placed by {hold.placed_by} · {formatShortDate(hold.placed_at)}
                                            </span>
                                            {hold.blocks_enrollment && <span className="text-destructive font-medium">Blocks enrollment</span>}
                                            {hold.blocks_add_drop && <span className="text-amber-600 font-medium dark:text-amber-400">Blocks add/drop</span>}
                                            {hold.blocks_grade_release && <span>Blocks grade release</span>}
                                        </div>

                                        {hold.status === "overridden" && hold.override_reason && (
                                            <p className="bg-muted/50 rounded-md px-2.5 py-1.5 text-[11px]">
                                                <span className="font-semibold">Audited override by {hold.overridden_by}:</span> {hold.override_reason}
                                            </p>
                                        )}
                                        {hold.status === "released" && (
                                            <p className="text-muted-foreground text-[11px]">
                                                Released by {hold.released_by} ({formatShortDate(hold.released_at)})
                                                {hold.release_notes ? ` — ${hold.release_notes}` : ""}
                                            </p>
                                        )}
                                    </div>

                                    {hold.status === "active" && (
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="xs"
                                                className="h-7 gap-1 text-xs"
                                                onClick={() => setReleaseTarget(hold)}
                                            >
                                                <Unlock className="size-3" />
                                                Release
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="xs"
                                                className="text-muted-foreground hover:text-foreground h-7 gap-1 text-xs"
                                                onClick={() => setOverrideTarget(hold)}
                                            >
                                                <ShieldOff className="size-3" />
                                                Override
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </FramePanel>
                </Frame>
            </div>

            {/* Place Hold Dialog */}
            <Dialog open={placeOpen} onOpenChange={setPlaceOpen}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={handlePlaceHold} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Place Student Hold</DialogTitle>
                            <DialogDescription>
                                Record a cross-office clearance hold that surfaces on enrollment rows, creation forms, and the student dossier.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-1.5">
                            <Label className="text-xs">Student</Label>
                            {selectedStudent ? (
                                <div className="flex items-center justify-between rounded-md border px-3 py-2 text-xs">
                                    <div>
                                        <p className="font-semibold">{selectedStudent.full_name}</p>
                                        <p className="text-muted-foreground">
                                            ID {selectedStudent.student_id ?? selectedStudent.id}
                                            {selectedStudent.course_code ? ` · ${selectedStudent.course_code}` : ""}
                                        </p>
                                    </div>
                                    <Button type="button" size="xs" variant="ghost" onClick={() => setSelectedStudent(null)}>
                                        Change
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-1.5">
                                    <Input
                                        value={studentQuery}
                                        onChange={(e) => setStudentQuery(e.target.value)}
                                        placeholder="Type student name or ID..."
                                        className="h-8 text-xs"
                                    />
                                    {searchingStudents && <p className="text-muted-foreground text-[11px]">Searching students...</p>}
                                    {studentResults.length > 0 && (
                                        <div className="max-h-40 divide-y overflow-y-auto rounded-md border">
                                            {studentResults.map((s) => (
                                                <button
                                                    key={s.id}
                                                    type="button"
                                                    onClick={() => setSelectedStudent(s)}
                                                    className="hover:bg-muted/60 flex w-full items-center justify-between px-3 py-2 text-left text-xs"
                                                >
                                                    <span className="font-medium">{s.full_name}</span>
                                                    <span className="text-muted-foreground font-mono text-[11px]">
                                                        {s.student_id ?? s.id} {s.course_code ? `· ${s.course_code}` : ""}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs">Issuing office</Label>
                                <Select value={office} onValueChange={setOffice}>
                                    <SelectTrigger className="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {offices.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Severity</Label>
                                <Select value={severity} onValueChange={(v) => setSeverity(v as "blocking" | "warning")}>
                                    <SelectTrigger className="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="blocking">Blocking hold</SelectItem>
                                        <SelectItem value="warning">Advisory warning</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="hold-title" className="text-xs">
                                Hold title
                            </Label>
                            <Input
                                id="hold-title"
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="e.g. Unpaid prior-term balance, Missing Form 137, Overdue library book"
                                className="h-8 text-xs"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="hold-reason" className="text-xs">
                                Details &amp; clearance instructions
                            </Label>
                            <Textarea
                                id="hold-reason"
                                rows={3}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Describe what the student must settle or submit before clearance."
                                className="text-xs"
                            />
                        </div>

                        <div className="flex flex-wrap gap-4 pt-1">
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox checked={blocksEnrollment} onCheckedChange={(v) => setBlocksEnrollment(Boolean(v))} />
                                Blocks enrollment
                            </label>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox checked={blocksAddDrop} onCheckedChange={(v) => setBlocksAddDrop(Boolean(v))} />
                                Blocks add/drop
                            </label>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox checked={blocksGradeRelease} onCheckedChange={(v) => setBlocksGradeRelease(Boolean(v))} />
                                Blocks grade release
                            </label>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" size="sm" onClick={() => setPlaceOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm" disabled={busy}>
                                {busy && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                                Place hold
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Release Hold Dialog */}
            <Dialog open={releaseTarget !== null} onOpenChange={(open) => !open && setReleaseTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleReleaseHold} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Release Hold</DialogTitle>
                            <DialogDescription>
                                Mark &ldquo;{releaseTarget?.title}&rdquo; for {releaseTarget?.student_name} as cleared.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-1.5">
                            <Label htmlFor="release-notes" className="text-xs">
                                Clearance remarks (optional)
                            </Label>
                            <Textarea
                                id="release-notes"
                                rows={2}
                                value={releaseNotes}
                                onChange={(e) => setReleaseNotes(e.target.value)}
                                placeholder="Receipt reference, returned item confirmation, or registrar note..."
                                className="text-xs"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" size="sm" onClick={() => setReleaseTarget(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm" disabled={busy}>
                                {busy && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                                Confirm release
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Audited Override Hold Dialog */}
            <Dialog open={overrideTarget !== null} onOpenChange={(open) => !open && setOverrideTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleOverrideHold} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Audited Hold Override</DialogTitle>
                            <DialogDescription>
                                Override &ldquo;{overrideTarget?.title}&rdquo; ({overrideTarget?.office_label}). Your user identity and reason will
                                be permanently logged.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-1.5">
                            <Label htmlFor="override-reason" className="text-xs">
                                Audit justification (required)
                            </Label>
                            <Textarea
                                id="override-reason"
                                rows={3}
                                value={overrideReason}
                                onChange={(e) => setOverrideReason(e.target.value)}
                                placeholder="e.g. Approved promissory note signed by VP Finance #PN-2026-041"
                                className="text-xs"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" size="sm" onClick={() => setOverrideTarget(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm" variant="destructive" disabled={busy}>
                                {busy && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                                Record override
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
