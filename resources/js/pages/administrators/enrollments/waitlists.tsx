import {
    assign as assignClassRoute,
    autoAssign as autoAssignRoute,
    destroy as cancelWaitlistRoute,
    index as waitlistsIndexRoute,
    promote as promoteWaitlistRoute,
    store as addWaitlistRoute,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentWaitlistController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    ArrowUpCircle,
    CheckCircle2,
    Clock,
    Loader2,
    Search,
    Sparkles,
    Trash2,
    Users,
    Wand2,
} from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { formatShortDate } from "./enrollment-utils";

interface ClassOption {
    id: number;
    section: string;
    subject_code: string;
    faculty: string;
    enrolled_count: number;
    max_slots: number;
    available_slots: number | null;
    is_full: boolean;
}

interface UnassignedItem {
    subject_enrollment_id: number;
    enrollment_id: number;
    student_id: number;
    student_number: string | number;
    student_name: string;
    course_code: string | null;
    subject_id: number;
    subject_code: string;
    subject_title: string;
    has_open_seat: boolean;
    is_waitlisted: boolean;
    waitlist_id: number | null;
    waitlist_position: number | null;
    open_classes: ClassOption[];
    full_classes: ClassOption[];
}

interface WaitlistItem {
    id: number;
    student_enrollment_id: number;
    subject_enrollment_id: number;
    student_id: number;
    student_number: string | number;
    student_name: string;
    course_code: string | null;
    subject_id: number;
    subject_code: string;
    subject_title: string;
    position: number;
    status: "waiting" | "promoted" | "cancelled";
    notes: string | null;
    preferred_class_id: number | null;
    preferred_section: string | null;
    promoted_section: string | null;
    promoted_at: string | null;
    added_by: string;
    created_at: string | null;
    open_classes: ClassOption[];
}

interface WaitlistsPageProps {
    user: User;
    term: { school_year: string; semester: number };
    unassigned: UnassignedItem[];
    waitlists: WaitlistItem[];
    stats: {
        unassigned_count: number;
        auto_assignable_count: number;
        all_sections_full_count: number;
        waiting_count: number;
        promoted_count: number;
    };
    courses: Array<{ id: number; code: string; title: string | null }>;
    filters: {
        search: string;
        course_id: string;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function AdministratorEnrollmentWaitlistsPage({
    user,
    term,
    unassigned,
    waitlists,
    stats,
    courses,
    filters,
    flash,
}: WaitlistsPageProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [waitlistWhenFull, setWaitlistWhenFull] = useState(true);
    const [autoAssignBusy, setAutoAssignBusy] = useState(false);
    const [pickedClassByRow, setPickedClassByRow] = useState<Record<number, string>>({});
    const [pickedPromoteClass, setPickedPromoteClass] = useState<Record<number, string>>({});
    const [busyKey, setBusyKey] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const applyFilters = (overrides: Partial<WaitlistsPageProps["filters"]>) => {
        router.get(
            waitlistsIndexRoute.url(),
            {
                search: overrides.search !== undefined ? overrides.search : search,
                course_id: overrides.course_id ?? filters.course_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleBulkAutoAssign = () => {
        setAutoAssignBusy(true);
        router.post(
            autoAssignRoute.url(),
            { waitlist_when_full: waitlistWhenFull },
            {
                preserveScroll: true,
                onFinish: () => setAutoAssignBusy(false),
            },
        );
    };

    const handleManualAssign = (item: UnassignedItem) => {
        const chosenId = pickedClassByRow[item.subject_enrollment_id] ?? (item.open_classes[0] ? String(item.open_classes[0].id) : "");
        if (!chosenId) {
            toast.error("Select an open section first.");
            return;
        }
        setBusyKey(`assign:${item.subject_enrollment_id}`);
        router.post(
            assignClassRoute.url(item.subject_enrollment_id),
            { class_id: Number(chosenId) },
            {
                preserveScroll: true,
                onFinish: () => setBusyKey(null),
            },
        );
    };

    const handleAddToWaitlist = (item: UnassignedItem) => {
        setBusyKey(`waitlist:${item.subject_enrollment_id}`);
        router.post(
            addWaitlistRoute.url(),
            {
                subject_enrollment_id: item.subject_enrollment_id,
                preferred_class_id: item.full_classes[0]?.id ?? null,
            },
            {
                preserveScroll: true,
                onFinish: () => setBusyKey(null),
            },
        );
    };

    const handlePromote = (entry: WaitlistItem, forceOverload = false) => {
        const targetClassId =
            pickedPromoteClass[entry.id] ?? (entry.open_classes[0] ? String(entry.open_classes[0].id) : entry.preferred_class_id ? String(entry.preferred_class_id) : "");

        setBusyKey(`promote:${entry.id}`);
        router.post(
            promoteWaitlistRoute.url(entry.id),
            {
                class_id: targetClassId ? Number(targetClassId) : null,
                force_overload: forceOverload,
            },
            {
                preserveScroll: true,
                onFinish: () => setBusyKey(null),
            },
        );
    };

    const handleCancelWaitlist = (entry: WaitlistItem) => {
        setBusyKey(`cancel:${entry.id}`);
        router.delete(cancelWaitlistRoute.url(entry.id), {
            preserveScroll: true,
            onFinish: () => setBusyKey(null),
        });
    };

    return (
        <AdminLayout user={user} title="Class Waitlists & Auto-Assign">
            <Head title="Administrators • Class Waitlists & Auto-Assign" />

            <div className="space-y-4 pb-10">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <Link
                            href={route("administrators.enrollments.index")}
                            className={buttonVariants({ variant: "ghost", size: "icon-sm", className: "size-8" })}
                            title="Back to Enrollments"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-[-0.025em]">Unassigned &amp; Waitlist Queue</h1>
                                <Badge variant="outline" radius="full" size="lg" className="text-muted-foreground font-normal">
                                    {term.school_year} · Sem {term.semester}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Auto-assign open seats across unassigned subject enrollments and manage FIFO waitlists for full sections.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <label className="text-muted-foreground flex items-center gap-2 text-xs">
                            <Switch checked={waitlistWhenFull} onCheckedChange={setWaitlistWhenFull} />
                            Waitlist full overflows
                        </label>
                        <Button
                            size="sm"
                            className="h-8 gap-1.5"
                            disabled={autoAssignBusy || stats.unassigned_count === 0}
                            onClick={handleBulkAutoAssign}
                        >
                            {autoAssignBusy ? <Loader2 className="size-3.5 animate-spin" /> : <Wand2 className="size-3.5" />}
                            Auto-assign open seats ({stats.auto_assignable_count})
                        </Button>
                    </div>
                </header>

                {/* KPI Strip */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[
                        { label: "Unassigned seats", value: stats.unassigned_count, icon: Users, tone: "text-amber-600 dark:text-amber-400" },
                        { label: "Auto-assignable now", value: stats.auto_assignable_count, icon: Sparkles, tone: "text-emerald-600 dark:text-emerald-400" },
                        { label: "Full-section overflow", value: stats.all_sections_full_count, icon: AlertTriangle, tone: "text-destructive" },
                        { label: "Active waitlist", value: stats.waiting_count, icon: Clock, tone: "text-blue-600 dark:text-blue-400" },
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

                {/* Search & Program Filter */}
                <Frame spacing="xs">
                    <FramePanel className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
                        <form
                            className="relative flex-1"
                            onSubmit={(e) => {
                                e.preventDefault();
                                applyFilters({ search });
                            }}
                        >
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search student name or subject code..."
                                className="h-8 pl-8 text-xs"
                            />
                        </form>
                        <Select value={filters.course_id} onValueChange={(v) => applyFilters({ course_id: v })}>
                            <SelectTrigger className="h-8 w-full text-xs sm:w-56">
                                <SelectValue placeholder="All programs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All programs</SelectItem>
                                {courses.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.code} — {c.title ?? "Program"}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FramePanel>
                </Frame>

                {/* Tabbed Queue */}
                <Tabs defaultValue="unassigned" className="space-y-3">
                    <TabsList>
                        <TabsTrigger value="unassigned" className="text-xs">
                            Unassigned Students ({unassigned.length})
                        </TabsTrigger>
                        <TabsTrigger value="waitlist" className="text-xs">
                            Waitlist Queue ({waitlists.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="unassigned" className="mt-0">
                        <Frame spacing="xs">
                            <FramePanel className="divide-y p-0">
                                {unassigned.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                        <CheckCircle2 className="size-8 text-emerald-500" />
                                        <p className="text-sm font-medium">All term subjects have assigned sections</p>
                                        <p className="text-muted-foreground text-xs">No unassigned subject enrollments found for this term.</p>
                                    </div>
                                ) : (
                                    unassigned.map((item) => (
                                        <div
                                            key={item.subject_enrollment_id}
                                            className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="text-sm font-semibold">{item.student_name}</span>
                                                    <span className="text-muted-foreground font-mono text-xs">ID {item.student_number}</span>
                                                    {item.course_code && (
                                                        <Badge variant="outline" size="xs" radius="full">
                                                            {item.course_code}
                                                        </Badge>
                                                    )}
                                                    {item.is_waitlisted && (
                                                        <Badge variant="info-light" size="xs" radius="full">
                                                            Waitlisted #{item.waitlist_position}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs">
                                                    <span className="font-mono font-semibold">{item.subject_code}</span> — {item.subject_title}
                                                </p>
                                                <p className="text-muted-foreground text-[11px]">
                                                    {item.open_classes.length > 0
                                                        ? `${item.open_classes.length} open section(s) available`
                                                        : item.full_classes.length > 0
                                                          ? `All ${item.full_classes.length} offered section(s) are full`
                                                          : "No class section created yet for this subject"}
                                                </p>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                {item.open_classes.length > 0 ? (
                                                    <>
                                                        <Select
                                                            value={pickedClassByRow[item.subject_enrollment_id] ?? String(item.open_classes[0].id)}
                                                            onValueChange={(v) =>
                                                                setPickedClassByRow((prev) => ({
                                                                    ...prev,
                                                                    [item.subject_enrollment_id]: v,
                                                                }))
                                                            }
                                                        >
                                                            <SelectTrigger className="h-7 w-48 text-xs">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {item.open_classes.map((c) => (
                                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                                        Sec {c.section} ({c.enrolled_count}/{c.max_slots || "∞"})
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                        <Button
                                                            type="button"
                                                            size="xs"
                                                            variant="secondary"
                                                            className="h-7 text-xs"
                                                            disabled={busyKey === `assign:${item.subject_enrollment_id}`}
                                                            onClick={() => handleManualAssign(item)}
                                                        >
                                                            Assign
                                                        </Button>
                                                    </>
                                                ) : !item.is_waitlisted ? (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="outline"
                                                        className="h-7 gap-1 text-xs"
                                                        disabled={busyKey === `waitlist:${item.subject_enrollment_id}`}
                                                        onClick={() => handleAddToWaitlist(item)}
                                                    >
                                                        <Clock className="size-3" />
                                                        Add to waitlist
                                                    </Button>
                                                ) : null}
                                                <Link
                                                    href={route("administrators.enrollments.show", item.enrollment_id)}
                                                    className={buttonVariants({ variant: "ghost", size: "xs", className: "h-7 text-xs" })}
                                                >
                                                    Review
                                                </Link>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    <TabsContent value="waitlist" className="mt-0">
                        <Frame spacing="xs">
                            <FramePanel className="divide-y p-0">
                                {waitlists.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                        <Clock className="text-muted-foreground/50 size-8" />
                                        <p className="text-sm font-medium">Waitlist queue is empty</p>
                                        <p className="text-muted-foreground text-xs">
                                            Students overflowed from full sections will appear here in FIFO order.
                                        </p>
                                    </div>
                                ) : (
                                    waitlists.map((entry) => (
                                        <div
                                            key={entry.id}
                                            className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Badge variant="outline" size="xs" radius="full" className="font-mono">
                                                        #{entry.position}
                                                    </Badge>
                                                    <span className="text-sm font-semibold">{entry.student_name}</span>
                                                    <Badge
                                                        variant={
                                                            entry.status === "promoted"
                                                                ? "success-light"
                                                                : entry.status === "cancelled"
                                                                  ? "destructive-light"
                                                                  : "warning-light"
                                                        }
                                                        size="xs"
                                                        radius="full"
                                                        className="capitalize"
                                                    >
                                                        {entry.status}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs">
                                                    <span className="font-mono font-semibold">{entry.subject_code}</span> — {entry.subject_title}
                                                    {entry.preferred_section ? ` · Preferred: Sec ${entry.preferred_section}` : ""}
                                                    {entry.promoted_section ? ` · Assigned: Sec ${entry.promoted_section}` : ""}
                                                </p>
                                                <p className="text-muted-foreground text-[11px]">
                                                    Queued {formatShortDate(entry.created_at)} by {entry.added_by}
                                                    {entry.notes ? ` · ${entry.notes}` : ""}
                                                </p>
                                            </div>

                                            {entry.status === "waiting" && (
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {entry.open_classes.length > 0 && (
                                                        <Select
                                                            value={pickedPromoteClass[entry.id] ?? String(entry.open_classes[0].id)}
                                                            onValueChange={(v) =>
                                                                setPickedPromoteClass((prev) => ({
                                                                    ...prev,
                                                                    [entry.id]: v,
                                                                }))
                                                            }
                                                        >
                                                            <SelectTrigger className="h-7 w-44 text-xs">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {entry.open_classes.map((c) => (
                                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                                        Sec {c.section} ({c.enrolled_count}/{c.max_slots || "∞"})
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    )}
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant={entry.open_classes.length > 0 ? "secondary" : "outline"}
                                                        className="h-7 gap-1 text-xs"
                                                        disabled={busyKey === `promote:${entry.id}`}
                                                        onClick={() => handlePromote(entry, entry.open_classes.length === 0)}
                                                    >
                                                        <ArrowUpCircle className="size-3" />
                                                        {entry.open_classes.length > 0 ? "Promote" : "Force promote"}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon-xs"
                                                        variant="ghost"
                                                        className="text-muted-foreground hover:text-destructive h-7 w-7"
                                                        disabled={busyKey === `cancel:${entry.id}`}
                                                        onClick={() => handleCancelWaitlist(entry)}
                                                        title="Cancel waitlist entry"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    ))
                                )}
                            </FramePanel>
                        </Frame>
                    </TabsContent>
                </Tabs>
            </div>
        </AdminLayout>
    );
}
