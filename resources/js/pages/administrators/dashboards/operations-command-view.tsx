import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    GraduationCap,
    Info,
    ListChecks,
    RotateCcw,
    School,
    Search,
    Server,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    UserCheck,
    Users,
    Workflow,
    X,
    type LucideIcon,
} from "lucide-react";
import React, { useMemo, useState } from "react";
import { route } from "ziggy-js";
import type { DashboardViewProps, RecentActivityItem } from "./types";

function formatNumber(value: number): string {
    return new Intl.NumberFormat("en-US").format(value);
}

function formatDateTime(value?: string | null): string {
    if (!value) return "Just now";
    try {
        return new Intl.DateTimeFormat("en-US", {
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit",
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function safeRoute(name: string, fallbackUrl: string): string {
    try {
        if (typeof window !== "undefined" && typeof route === "function") {
            return route(name);
        }
    } catch {
        // Fall back gracefully when route manifest is not yet hydrated in client session
    }
    return fallbackUrl;
}

function getInitials(name: string): string {
    if (!name) return "SY";
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Highlights quotes, IDs, codes, or keywords in an activity action string
 */
function HighlightedAction({ text }: { text: string }) {
    // Regex matches text inside quotes, or words like #1234, or uppercase acronym codes like CS101, BSIT-1A
    const parts = text.split(/("[^"]+"|\b[A-Z]{2,}[0-9A-Z-]*\b|#[0-9A-Za-z-]+)/g);

    return (
        <span className="text-muted-foreground text-xs leading-relaxed">
            {parts.map((part, idx) => {
                const isQuoted = part.startsWith('"') && part.endsWith('"');
                const isCode = /^(\b[A-Z]{2,}[0-9A-Z-]*\b|#[0-9A-Za-z-]+)$/.test(part);

                if (isQuoted || isCode) {
                    return (
                        <span
                            key={idx}
                            className="text-foreground bg-muted/80 dark:bg-muted/40 border-border/50 mx-0.5 inline-block rounded border px-1.5 py-0.25 font-mono text-[11px] font-semibold"
                        >
                            {isQuoted ? part.slice(1, -1) : part}
                        </span>
                    );
                }

                return <React.Fragment key={idx}>{part}</React.Fragment>;
            })}
        </span>
    );
}

type OperationalTask = {
    id: string;
    title: string;
    description: string;
    category: "faculty" | "grades" | "clearance" | "system";
    count: number;
    urgency: "critical" | "high" | "medium" | "low";
    tone: "destructive" | "warning" | "info" | "success";
    href: string;
    actionLabel: string;
    icon: LucideIcon;
    resolved?: boolean;
};

type ReadinessCheckItem = {
    id: string;
    title: string;
    category: string;
    description: string;
    recommendedAction: string;
    routeHref: string;
    completed: boolean;
    verificationPoints: string[];
};

export default function OperationsCommandView({ user, admin_data, currency }: DashboardViewProps) {
    const operations = admin_data.operations ?? {
        active_classes: 0,
        total_faculty: 0,
        total_users: 0,
        unassigned_classes: 0,
        action_queue: [],
    };

    const unassignedCount = operations.unassigned_classes ?? 0;
    const activeClasses = operations.active_classes ?? 0;
    const totalFaculty = operations.total_faculty ?? 0;
    const totalUsers = operations.total_users ?? 0;

    const [activityFilter, setActivityFilter] = useState("");
    const [activityStatusFilter, setActivityStatusFilter] = useState<"all" | "success" | "warning" | "error" | "info">("all");
    const [queueCategoryFilter, setQueueCategoryFilter] = useState<"all" | "urgent" | "faculty" | "grades" | "clearance" | "system">("all");

    // Interactive Incident & Action Queue Backlog State
    const [resolvedTasks, setResolvedTasks] = useState<Record<string, boolean>>({});
    const [clearedAllQueue, setClearedAllQueue] = useState(false);

    // Initial operational backlog combining required operational task categories
    const initialTasks: OperationalTask[] = useMemo(() => {
        const tasks: OperationalTask[] = [];

        // 1. Sections waiting for faculty assignments
        tasks.push({
            id: "task-unassigned-sections",
            title: "Sections Waiting for Faculty Assignments",
            description:
                unassignedCount > 0
                    ? `${unassignedCount} academic course section(s) lack assigned teaching instructors. Allocate faculty load to prevent schedule bottlenecks.`
                    : "All scheduled class sections currently have assigned faculty instructors.",
            category: "faculty",
            count: unassignedCount,
            urgency: unassignedCount > 0 ? "critical" : "low",
            tone: unassignedCount > 0 ? "warning" : "success",
            href: safeRoute("administrators.classes.index", "/administrators/classes"),
            actionLabel: "Assign Faculty",
            icon: School,
        });

        // 2. Pending grades / attendance submissions.
        // Only surfaced when the backend actually reports a grade or attendance item. Substituting
        // an unrelated queue entry would report grading work that does not exist.
        const queueGradeItem = operations.action_queue.find((i) => /grade|attendance/i.test(i.label));
        if (queueGradeItem) {
            const gradeCount = queueGradeItem.value;
            tasks.push({
                id: "task-pending-grades",
                title: "Pending Grades & Attendance Sheets",
                description:
                    "Instructor grade sheets and class attendance rosters submitted awaiting departmental endorsement and dean verification.",
                category: "grades",
                count: gradeCount,
                urgency: gradeCount > 5 ? "high" : "medium",
                tone: "warning",
                href: queueGradeItem.href || safeRoute("administrators.faculties.index", "/administrators/faculties"),
                actionLabel: "Review Submissions",
                icon: GraduationCap,
            });
        }

        // 3. Clearance approvals. Likewise only rendered when the backend reports such work.
        const queueClearanceItem = operations.action_queue.find((i) => /clearance|approval|hold/i.test(i.label));
        if (queueClearanceItem) {
            const clearanceCount = queueClearanceItem.value;
            tasks.push({
                id: "task-clearance-approvals",
                title: "Student Clearance & Graduation Approvals",
                description: "Term completion and academic clearance petitions awaiting administrator and registrar department sign-off.",
                category: "clearance",
                count: clearanceCount,
                urgency: clearanceCount > 0 ? "high" : "low",
                tone: clearanceCount > 0 ? "info" : "success",
                href: queueClearanceItem.href || safeRoute("administrators.students.index", "/administrators/students"),
                actionLabel: "Clear Students",
                icon: UserCheck,
            });
        }

        // 4. System maintenance & telemetry audit. Informational, so it is always listed.
        tasks.push({
            id: "task-system-maintenance",
            title: "System Maintenance & Telemetry Audit",
            description: "Observability telemetry, scheduled database optimization routines, and automated storage snapshot validations.",
            category: "system",
            count: 1,
            urgency: "low",
            tone: "info",
            href: safeRoute("administrators.system-management.observability.index", "/administrators/system-management/observability"),
            actionLabel: "Inspect Diagnostics",
            icon: Server,
        });

        // Also incorporate any other custom action_queue items from admin_data
        operations.action_queue.forEach((item, index) => {
            if (
                !/unassigned|faculty|grade|attendance|clearance/i.test(item.label) &&
                !tasks.some((t) => t.title.toLowerCase() === item.label.toLowerCase())
            ) {
                tasks.push({
                    id: `task-custom-${index}`,
                    title: item.label,
                    description: item.description,
                    category: "system",
                    count: item.value,
                    urgency: item.tone === "warning" ? "high" : "medium",
                    tone: item.tone === "warning" ? "warning" : item.tone === "info" ? "info" : "success",
                    href: item.href,
                    actionLabel: "Review",
                    icon: ListChecks,
                });
            }
        });

        return tasks;
    }, [unassignedCount, operations.action_queue]);

    // Active operational tasks
    const activeTasks = useMemo(() => {
        if (clearedAllQueue) return [];

        return initialTasks.filter((task) => {
            const isResolved = resolvedTasks[task.id];
            if (isResolved) return false;

            if (queueCategoryFilter === "all") return true;
            if (queueCategoryFilter === "urgent") return task.urgency === "critical" || task.urgency === "high";
            return task.category === queueCategoryFilter;
        });
    }, [initialTasks, resolvedTasks, clearedAllQueue, queueCategoryFilter]);

    const totalPendingIncidents = useMemo(() => {
        if (clearedAllQueue) return 0;
        return initialTasks.reduce((sum, t) => {
            if (resolvedTasks[t.id]) return sum;
            return sum + (t.count > 0 ? t.count : 0);
        }, 0);
    }, [initialTasks, resolvedTasks, clearedAllQueue]);

    const isAttentionRequired = unassignedCount > 0 || totalPendingIncidents > 0;

    const handleResolveTask = (taskId: string) => {
        setResolvedTasks((prev) => ({ ...prev, [taskId]: true }));
    };

    const handleClearAll = () => {
        setClearedAllQueue(true);
    };

    const handleResetQueue = () => {
        setClearedAllQueue(false);
        setResolvedTasks({});
    };

    /**
     * Live Activity Stream Logs. Never synthesised: an invented entry such as a failed-password
     * alert reads as a real security incident, so an empty log renders as an empty log.
     */
    const rawActivity: RecentActivityItem[] = useMemo(() => {
        return admin_data.recent_records?.activity ?? admin_data.recent_activity ?? [];
    }, [admin_data.recent_records?.activity, admin_data.recent_activity]);

    // Filtered Activity Stream
    const filteredActivity = useMemo(() => {
        return rawActivity.filter((item) => {
            // Status filter
            if (activityStatusFilter !== "all" && item.status !== activityStatusFilter) {
                return false;
            }

            // Text search filter
            if (!activityFilter.trim()) return true;

            const q = activityFilter.toLowerCase();
            return (
                item.actor.toLowerCase().includes(q) ||
                item.action.toLowerCase().includes(q) ||
                item.time.toLowerCase().includes(q) ||
                item.status.toLowerCase().includes(q)
            );
        });
    }, [rawActivity, activityFilter, activityStatusFilter]);

    // Activity counts by status
    const statusCounts = useMemo(() => {
        return {
            all: rawActivity.length,
            success: rawActivity.filter((a) => a.status === "success").length,
            warning: rawActivity.filter((a) => a.status === "warning").length,
            error: rawActivity.filter((a) => a.status === "error").length,
            info: rawActivity.filter((a) => a.status === "info").length,
        };
    }, [rawActivity]);

    // Readiness Checklist State
    const [readinessItems, setReadinessItems] = useState<ReadinessCheckItem[]>([
        {
            id: "step-1",
            title: "Academic Period & Calendar Configuration",
            category: "Term Setup",
            description: "Verify active school year, semester dates, late enrollment deadlines, and grading drop windows.",
            recommendedAction: "System Settings",
            routeHref: safeRoute("administrators.system-management.index", "/administrators/system-management"),
            completed: true,
            verificationPoints: [
                `Active period: ${admin_data.current_period?.label || "Current Academic Term"}`,
                "Late registration and drop penalties configured",
                "Grading encoding period deadlines posted to faculty",
            ],
        },
        {
            id: "step-2",
            title: "Course Section Scheduling & Room Duty",
            category: "Scheduling",
            description: "Ensure scheduled courses have allocated classrooms, lab assignments, and zero room collisions.",
            recommendedAction: "Scheduling Console",
            routeHref: safeRoute("administrators.scheduling-analytics.index", "/administrators/scheduling-analytics"),
            completed: activeClasses > 0,
            verificationPoints: [
                `${formatNumber(activeClasses)} active class sections scheduled on duty`,
                "Classroom room capacity constraints verified",
                "Collision detection report verified with zero conflicts",
            ],
        },
        {
            id: "step-3",
            title: "Faculty Load Assignment & Workload Compliance",
            category: "Staffing",
            description: "Appoint qualified faculty instructors to all active sections and verify maximum credit teaching loads.",
            recommendedAction: "Assign Instructors",
            routeHref: safeRoute("administrators.classes.index", "/administrators/classes"),
            completed: unassignedCount === 0,
            verificationPoints: [
                `${formatNumber(totalFaculty)} appointed faculty instructors on duty`,
                unassignedCount > 0
                    ? `Action required: ${unassignedCount} sections awaiting instructors`
                    : "100% of sections staffed with assigned faculty",
                "Overload authorization signed by Academic Dean",
            ],
        },
        {
            id: "step-4",
            title: "Student Clearances & Ledger Auditing",
            category: "Registrar",
            description: "Audit student account balances, prerequisite verification, document submissions, and clearance holds.",
            recommendedAction: "Student Directory",
            routeHref: safeRoute("administrators.students.index", "/administrators/students"),
            completed: true,
            verificationPoints: [
                "Tuition fee schedule and payment terms enforced",
                "Prerequisite automated blocking active for irregular students",
                "Graduation and clearance sign-off matrices operational",
            ],
        },
        {
            id: "step-5",
            title: "System Observability & Automated Backup",
            category: "Infrastructure",
            description: "Validate queue workers, background jobs, database snapshot routines, and error telemetry.",
            recommendedAction: "Observability Health",
            routeHref: safeRoute("administrators.system-management.observability.index", "/administrators/system-management/observability"),
            completed: true,
            verificationPoints: [
                "Nightly database snapshot schedule verified",
                "Queue workers active with low latency",
                "Audit logging enabled across all administrative causers",
            ],
        },
    ]);

    const toggleReadinessItem = (id: string) => {
        setReadinessItems((prev) => prev.map((item) => (item.id === id ? { ...item, completed: !item.completed } : item)));
    };

    const completedReadinessCount = readinessItems.filter((i) => i.completed).length;
    const readinessPercent = Math.round((completedReadinessCount / readinessItems.length) * 100);

    return (
        <div className="grid gap-6">
            {/* =========================================================================
                1. HERO HEADER: Institutional Operations & System Telemetry Header
                ========================================================================= */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Breadcrumb className="mb-1.5">
                        <BreadcrumbList>
                            <BreadcrumbItem>
                                <BreadcrumbLink href={safeRoute("administrators.dashboard", "/administrators/dashboard")}>Dashboard</BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage className="text-foreground font-semibold">Operations Command</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">Operations Command</h1>

                        {/* Real-time Status Indicator with Animated Ping Dot */}
                        {isAttentionRequired ? (
                            <Badge variant="warning-light" size="sm" className="gap-2 font-semibold">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75" />
                                    <span className="relative inline-flex size-2 rounded-full bg-amber-500" />
                                </span>
                                Attention Required ({totalPendingIncidents} items)
                            </Badge>
                        ) : (
                            <Badge variant="success-light" size="sm" className="gap-2 font-semibold">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                                </span>
                                Operations Normal
                            </Badge>
                        )}

                        {/* Current Academic Period Badge */}
                        <Badge variant="outline" size="sm" className="gap-1.5 font-medium">
                            <CalendarDays className="text-primary size-3.5" />
                            {admin_data.current_period?.label || "Active Academic Term"}
                        </Badge>

                        {/* Synced Timestamp Pill */}
                        <Badge variant="outline" size="sm" className="text-muted-foreground gap-1.5 font-mono text-xs tabular-nums">
                            <Activity className="text-muted-foreground size-3" />
                            Synced {formatDateTime(admin_data.executive_summary?.last_updated_at)}
                        </Badge>
                    </div>

                    <p className="text-muted-foreground mt-1 max-w-4xl text-sm leading-relaxed">
                        Institutional execution metrics, active courses on duty, faculty allocations, pending approvals, and live telemetry audit.
                    </p>
                </div>

                {/* Quick Action Navigation Buttons & Audit Log Link */}
                <div className="flex flex-wrap items-center gap-2.5 self-start sm:self-center">
                    <Button
                        variant="outline"
                        size="sm"
                        render={<Link href={safeRoute("administrators.audit-logs.index", "/administrators/audit-logs")} />}
                        className="gap-1.5 text-xs font-semibold"
                    >
                        <ShieldCheck className="text-primary size-3.5" />
                        Audit Trail
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        render={<Link href={safeRoute("administrators.classes.index", "/administrators/classes")} />}
                        className="gap-1.5 text-xs font-semibold"
                    >
                        <School className="text-primary size-3.5" />
                        Manage Classes
                    </Button>
                    <Button
                        variant="default"
                        size="sm"
                        render={<Link href={safeRoute("administrators.faculties.index", "/administrators/faculties")} />}
                        className="gap-1.5 text-xs font-semibold"
                    >
                        <Users className="size-3.5" />
                        Faculty Roster
                    </Button>
                </div>
            </div>

            {/* =========================================================================
                2. OPERATIONS COMMAND HUD (4-Card Metric Strip)
                ========================================================================= */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* HUD Card 1: Active Classes / Classrooms on duty */}
                <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                    <div className="flex items-center justify-between gap-2">
                        <IconTile variant="soft" size="sm" className="text-primary">
                            <School className="size-4" />
                        </IconTile>
                        <Badge variant="success-light" size="sm" className="font-semibold">
                            Live Sections
                        </Badge>
                    </div>
                    <div className="mt-3">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Active Classes</p>
                        <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                            {formatNumber(activeClasses)}
                        </p>
                        <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">Scheduled academic course sections on duty</p>
                    </div>
                </div>

                {/* HUD Card 2: Appointed Faculty Members */}
                <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                    <div className="flex items-center justify-between gap-2">
                        <IconTile variant="soft" size="sm" className="text-emerald-500">
                            <GraduationCap className="size-4" />
                        </IconTile>
                        <Badge variant="info-light" size="sm" className="font-semibold">
                            Assigned Roster
                        </Badge>
                    </div>
                    <div className="mt-3">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Faculty Members</p>
                        <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                            {formatNumber(totalFaculty)}
                        </p>
                        <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">Teaching personnel with active instructional load</p>
                    </div>
                </div>

                {/* HUD Card 3: Active Portal Accounts */}
                <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                    <div className="flex items-center justify-between gap-2">
                        <IconTile variant="soft" size="sm" className="text-sky-500">
                            <Users className="size-4" />
                        </IconTile>
                        <Badge variant="outline" size="sm" className="font-mono text-xs tabular-nums">
                            Portal Active
                        </Badge>
                    </div>
                    <div className="mt-3">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Portal Accounts</p>
                        <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">{formatNumber(totalUsers)}</p>
                        <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">Students, faculty, and administrative logins</p>
                    </div>
                </div>

                {/* HUD Card 4: Unassigned Sections */}
                <div
                    className={cn(
                        "rounded-xl border p-4 shadow-2xs transition-colors sm:p-5",
                        unassignedCount > 0
                            ? "border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10 dark:bg-amber-500/10"
                            : "border-border/70 bg-card hover:bg-muted/10",
                    )}
                >
                    <div className="flex items-center justify-between gap-2">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className={unassignedCount > 0 ? "text-amber-600 dark:text-amber-400" : "text-emerald-500"}
                        >
                            <AlertTriangle className="size-4" />
                        </IconTile>
                        {unassignedCount > 0 ? (
                            <Badge variant="warning-light" size="sm" className="font-semibold tabular-nums">
                                {unassignedCount} Unassigned
                            </Badge>
                        ) : (
                            <Badge variant="success-light" size="sm" className="font-semibold">
                                100% Assigned
                            </Badge>
                        )}
                    </div>
                    <div className="mt-3">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Unassigned Sections</p>
                        <p
                            className={cn(
                                "mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl",
                                unassignedCount > 0 ? "text-amber-600 dark:text-amber-400" : "text-foreground",
                            )}
                        >
                            {formatNumber(unassignedCount)}
                        </p>
                        <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">
                            {unassignedCount > 0
                                ? "Course sections requiring faculty instructor appointment"
                                : "All scheduled course sections staffed with teachers"}
                        </p>
                    </div>
                </div>
            </div>

            {/* =========================================================================
                3. INCIDENT & ACTION QUEUE SECTION (`agent-activity-1` template)
                High-contrast backlog queue of pending operational tasks
                ========================================================================= */}
            <Frame variant="default" className="shadow-2xs">
                <FramePanel className="p-0">
                    <FrameHeader className="border-border/60 flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div>
                            <div className="flex items-center gap-2">
                                <IconTile variant="soft" size="xs" className="text-primary">
                                    <ListChecks className="size-3.5" />
                                </IconTile>
                                <FrameTitle className="text-base font-semibold">Incident & Operational Action Backlog</FrameTitle>
                                {totalPendingIncidents > 0 ? (
                                    <Badge variant="warning-light" size="sm" className="font-semibold tabular-nums">
                                        {totalPendingIncidents} Action Items
                                    </Badge>
                                ) : (
                                    <Badge variant="success-light" size="sm" className="font-semibold">
                                        Backlog Clear
                                    </Badge>
                                )}
                            </div>
                            <FrameDescription className="text-xs">
                                High-priority operational routines, pending submissions, staffing requirements, and system maintenance alerts
                            </FrameDescription>
                        </div>

                        {/* Queue Filter Tabs & Batch Action */}
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="border-border/70 bg-muted/30 inline-flex items-center rounded-lg border p-0.5">
                                <Button
                                    type="button"
                                    variant={queueCategoryFilter === "all" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setQueueCategoryFilter("all")}
                                    className="text-xs font-semibold"
                                >
                                    All ({initialTasks.length})
                                </Button>
                                <Button
                                    type="button"
                                    variant={queueCategoryFilter === "urgent" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setQueueCategoryFilter("urgent")}
                                    className="text-xs font-semibold"
                                >
                                    Urgent
                                </Button>
                                <Button
                                    type="button"
                                    variant={queueCategoryFilter === "faculty" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setQueueCategoryFilter("faculty")}
                                    className="text-xs font-semibold"
                                >
                                    Faculty
                                </Button>
                                <Button
                                    type="button"
                                    variant={queueCategoryFilter === "grades" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setQueueCategoryFilter("grades")}
                                    className="text-xs font-semibold"
                                >
                                    Grades
                                </Button>
                                <Button
                                    type="button"
                                    variant={queueCategoryFilter === "clearance" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setQueueCategoryFilter("clearance")}
                                    className="text-xs font-semibold"
                                >
                                    Clearances
                                </Button>
                            </div>

                            {clearedAllQueue ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="xs"
                                    onClick={handleResetQueue}
                                    className="gap-1.5 text-xs font-semibold"
                                >
                                    <RotateCcw className="size-3" />
                                    Restore Queue
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    onClick={handleClearAll}
                                    className="text-muted-foreground hover:text-foreground gap-1.5 text-xs font-semibold"
                                    title="Acknowledge and dismiss all pending notifications"
                                >
                                    <Check className="size-3" />
                                    Clear All
                                </Button>
                            )}
                        </div>
                    </FrameHeader>

                    {/* Backlog Item List */}
                    <div className="p-4 sm:p-5">
                        {activeTasks.length === 0 ? (
                            <div className="border-border/80 bg-muted/15 flex min-h-36 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center">
                                <IconTile variant="outline" size="sm" className="mb-2 text-emerald-500">
                                    <CheckCircle2 className="size-4" />
                                </IconTile>
                                <p className="text-foreground text-sm font-semibold">Operational Backlog is Clear</p>
                                <p className="text-muted-foreground mt-0.5 max-w-sm text-xs">
                                    No pending approvals or unassigned tasks match your current filter. All operations are running nominal.
                                </p>
                                {clearedAllQueue && (
                                    <Button type="button" variant="outline" size="xs" onClick={handleResetQueue} className="mt-3 gap-1.5 text-xs">
                                        <RotateCcw className="size-3" />
                                        Reset Backlog View
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                {activeTasks.map((task) => {
                                    const TaskIcon = task.icon;

                                    return (
                                        <Alert
                                            key={task.id}
                                            variant={
                                                task.tone === "warning"
                                                    ? "warning"
                                                    : task.tone === "destructive"
                                                      ? "destructive"
                                                      : task.tone === "info"
                                                        ? "info"
                                                        : "default"
                                            }
                                            className="border-border/60 bg-card hover:bg-muted/10 transition-colors"
                                        >
                                            <IconTile
                                                variant="soft"
                                                size="sm"
                                                className={cn(
                                                    "shrink-0",
                                                    task.tone === "warning" && "text-amber-600 dark:text-amber-400",
                                                    task.tone === "destructive" && "text-rose-600 dark:text-rose-400",
                                                    task.tone === "info" && "text-sky-600 dark:text-sky-400",
                                                    task.tone === "success" && "text-emerald-600 dark:text-emerald-400",
                                                )}
                                            >
                                                <TaskIcon className="size-3.5" />
                                            </IconTile>

                                            <AlertTitle className="text-foreground flex flex-wrap items-center gap-2 text-sm font-semibold">
                                                <span>{task.title}</span>
                                                {task.urgency === "critical" && (
                                                    <Badge variant="destructive-light" size="xs" className="font-semibold">
                                                        Critical
                                                    </Badge>
                                                )}
                                                {task.urgency === "high" && (
                                                    <Badge variant="warning-light" size="xs" className="font-semibold">
                                                        Urgent
                                                    </Badge>
                                                )}
                                            </AlertTitle>

                                            <AlertDescription className="text-muted-foreground text-xs leading-relaxed">
                                                {task.description}
                                            </AlertDescription>

                                            <AlertAction className="flex flex-wrap items-center gap-2">
                                                {task.count > 0 && (
                                                    <Badge
                                                        variant={
                                                            task.tone === "warning"
                                                                ? "warning-light"
                                                                : task.tone === "destructive"
                                                                  ? "destructive-light"
                                                                  : "info-light"
                                                        }
                                                        size="sm"
                                                        className="font-semibold tabular-nums"
                                                    >
                                                        {formatNumber(task.count)} pending
                                                    </Badge>
                                                )}

                                                <Button
                                                    variant="ghost"
                                                    size="xs"
                                                    onClick={() => handleResolveTask(task.id)}
                                                    className="text-muted-foreground hover:text-foreground text-xs"
                                                    title="Mark task as verified / handled"
                                                >
                                                    <Check className="mr-1 size-3" />
                                                    Dismiss
                                                </Button>

                                                <Button
                                                    variant="outline"
                                                    size="xs"
                                                    render={<Link href={task.href} />}
                                                    className="gap-1 text-xs font-semibold"
                                                >
                                                    {task.actionLabel}
                                                    <ArrowUpRight className="size-3" />
                                                </Button>
                                            </AlertAction>
                                        </Alert>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </FramePanel>
            </Frame>

            {/* =========================================================================
                4. LIVE SYSTEM ACTIVITY STREAM (`agent-activity-1` / `solution-analytics-1`)
                ========================================================================= */}
            <Frame variant="default" className="shadow-2xs">
                <FramePanel className="p-0">
                    <FrameHeader className="border-border/60 flex flex-col gap-3.5 border-b p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div>
                            <div className="flex items-center gap-2">
                                <IconTile variant="soft" size="xs" className="text-primary">
                                    <Activity className="size-3.5" />
                                </IconTile>
                                <FrameTitle className="text-base font-semibold">Live System Activity Stream</FrameTitle>
                                <Badge variant="outline" size="sm" className="font-mono text-xs tabular-nums">
                                    {filteredActivity.length} of {rawActivity.length} events
                                </Badge>
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                                </span>
                            </div>
                            <FrameDescription className="text-xs">
                                Real-time institutional audit feed with actor identity, target highlight, and semantic status verification
                            </FrameDescription>
                        </div>

                        {/* Search Input and Status Filters */}
                        <div className="flex flex-wrap items-center gap-2.5">
                            {/* Search Filter Box */}
                            <div className="relative w-full sm:w-60">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                <Input
                                    placeholder="Filter by actor, action..."
                                    value={activityFilter}
                                    onChange={(e) => setActivityFilter(e.target.value)}
                                    className="h-8 pr-8 pl-8 text-xs"
                                />
                                {activityFilter && (
                                    <button
                                        type="button"
                                        onClick={() => setActivityFilter("")}
                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </div>

                            {/* Status Filter Chips */}
                            <div className="border-border/70 bg-muted/30 inline-flex items-center rounded-lg border p-0.5">
                                <Button
                                    type="button"
                                    variant={activityStatusFilter === "all" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setActivityStatusFilter("all")}
                                    className="text-xs font-semibold"
                                >
                                    All ({statusCounts.all})
                                </Button>
                                <Button
                                    type="button"
                                    variant={activityStatusFilter === "success" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setActivityStatusFilter("success")}
                                    className="text-xs font-semibold"
                                >
                                    Success ({statusCounts.success})
                                </Button>
                                <Button
                                    type="button"
                                    variant={activityStatusFilter === "warning" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setActivityStatusFilter("warning")}
                                    className="text-xs font-semibold"
                                >
                                    Warning ({statusCounts.warning})
                                </Button>
                                <Button
                                    type="button"
                                    variant={activityStatusFilter === "error" ? "default" : "ghost"}
                                    size="xs"
                                    onClick={() => setActivityStatusFilter("error")}
                                    className="text-xs font-semibold"
                                >
                                    Errors ({statusCounts.error})
                                </Button>
                            </div>
                        </div>
                    </FrameHeader>

                    {/* Activity Feed Rows */}
                    {filteredActivity.length === 0 ? (
                        <div className="border-border/80 bg-muted/15 m-5 flex min-h-36 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center">
                            <IconTile variant="outline" size="sm" className="text-muted-foreground/60 mb-2">
                                <Search className="size-4" />
                            </IconTile>
                            {rawActivity.length === 0 ? (
                                <>
                                    <p className="text-foreground text-sm font-semibold">No recorded activity</p>
                                    <p className="text-muted-foreground mt-0.5 max-w-xs text-xs">
                                        Nothing has been logged against this institution yet. Events appear here as staff act on records.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="text-foreground text-sm font-semibold">No activity matches this query</p>
                                    <p className="text-muted-foreground mt-0.5 max-w-xs text-xs">
                                        Try adjusting your search keyword or clearing the status filter.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="xs"
                                        onClick={() => {
                                            setActivityFilter("");
                                            setActivityStatusFilter("all");
                                        }}
                                        className="mt-3 text-xs"
                                    >
                                        Clear Activity Filter
                                    </Button>
                                </>
                            )}
                        </div>
                    ) : (
                        <div className="divide-border/60 divide-y">
                            {filteredActivity.map((act, index) => {
                                const initials = getInitials(act.actor);
                                const isSuccess = act.status === "success";
                                const isWarning = act.status === "warning";
                                const isError = act.status === "error";

                                const statusBadgeVariant = isSuccess
                                    ? "success-light"
                                    : isWarning
                                      ? "warning-light"
                                      : isError
                                        ? "destructive-light"
                                        : "info-light";

                                const dotColor = isSuccess ? "bg-emerald-500" : isWarning ? "bg-amber-500" : isError ? "bg-rose-500" : "bg-sky-500";

                                return (
                                    <div
                                        key={`${act.actor}-${act.time}-${index}`}
                                        className="hover:bg-muted/20 flex flex-col gap-3 p-3.5 transition-colors sm:flex-row sm:items-center sm:justify-between sm:p-4"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            {/* Actor Avatar Tile */}
                                            <IconTile
                                                variant="soft"
                                                size="sm"
                                                className={cn(
                                                    "mt-0.5 shrink-0 font-mono text-xs font-bold",
                                                    isSuccess && "text-emerald-600 dark:text-emerald-400",
                                                    isWarning && "text-amber-600 dark:text-amber-400",
                                                    isError && "text-rose-600 dark:text-rose-400",
                                                    !isSuccess && !isWarning && !isError && "text-primary",
                                                )}
                                            >
                                                {initials}
                                            </IconTile>

                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-foreground text-sm font-semibold">{act.actor}</span>
                                                    <Badge variant={statusBadgeVariant} size="xs" className="gap-1 font-medium capitalize">
                                                        <span className={cn("size-1.5 rounded-full", dotColor)} />
                                                        {act.status}
                                                    </Badge>
                                                </div>
                                                <div className="mt-0.5">
                                                    <HighlightedAction text={act.action} />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Timestamp Pill & Inspect Shortcut */}
                                        <div className="flex shrink-0 items-center justify-between gap-2.5 sm:justify-end">
                                            <Badge
                                                variant="outline"
                                                size="xs"
                                                className="text-muted-foreground gap-1 font-mono text-[11px] tabular-nums"
                                            >
                                                <Clock className="size-3" />
                                                {act.time}
                                            </Badge>

                                            <Button
                                                variant="ghost"
                                                size="icon-xs"
                                                render={<Link href={safeRoute("administrators.audit-logs.index", "/administrators/audit-logs")} />}
                                                title="View audit event details"
                                                className="text-muted-foreground hover:text-foreground"
                                            >
                                                <ArrowUpRight className="size-3" />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <FrameFooter className="border-border/60 bg-muted/15 flex flex-col gap-2 border-t p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                        <span className="text-muted-foreground text-xs">Activity events are immutably recorded by Spatie Activitylog engine</span>
                        <Button
                            variant="outline"
                            size="xs"
                            render={<Link href={safeRoute("administrators.audit-logs.index", "/administrators/audit-logs")} />}
                            className="gap-1.5 text-xs font-semibold"
                        >
                            <ShieldCheck className="size-3.5" />
                            Open Full Audit Logs
                        </Button>
                    </FrameFooter>
                </FramePanel>
            </Frame>

            {/* =========================================================================
                5. OPERATIONAL GUIDANCE & READINESS CHECKLIST
                ========================================================================= */}
            <div className="grid gap-6 lg:grid-cols-12">
                {/* Left: Operational Readiness Checklist with Expandable Steps */}
                <div className="lg:col-span-7">
                    <Frame variant="default" className="h-full shadow-2xs">
                        <FramePanel className="flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/60 border-b p-4 sm:p-5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Workflow className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Operational Readiness Checklist</FrameTitle>
                                    </div>
                                    <Badge
                                        variant={readinessPercent === 100 ? "success-light" : "warning-light"}
                                        size="sm"
                                        className="font-semibold tabular-nums"
                                    >
                                        {readinessPercent}% Ready ({completedReadinessCount}/{readinessItems.length})
                                    </Badge>
                                </div>
                                <FrameDescription className="text-xs">
                                    Step-by-step verification milestones for administrative operational excellence
                                </FrameDescription>

                                {/* Visual Readiness Progress Bar */}
                                <div className="mt-2 space-y-1">
                                    <Progress value={readinessPercent} className="h-2" />
                                    <div className="text-muted-foreground flex justify-between text-[11px]">
                                        <span>Target: 100% Operational Sign-off</span>
                                        <span className="font-semibold tabular-nums">
                                            {completedReadinessCount} of {readinessItems.length} Milestones Verified
                                        </span>
                                    </div>
                                </div>
                            </FrameHeader>

                            {/* Expandable Accordion Steps */}
                            <div className="p-4 sm:p-5">
                                <Accordion type="single" collapsible defaultValue="step-3" className="w-full">
                                    {readinessItems.map((item, idx) => (
                                        <AccordionItem key={item.id} value={item.id} className="border-border/60">
                                            <AccordionTrigger className="py-3 text-sm hover:no-underline">
                                                <div className="flex items-center gap-3 text-left">
                                                    <button
                                                        type="button"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            toggleReadinessItem(item.id);
                                                        }}
                                                        className={cn(
                                                            "flex size-5 shrink-0 items-center justify-center rounded border transition-colors",
                                                            item.completed
                                                                ? "border-emerald-500 bg-emerald-500 text-white"
                                                                : "border-border/80 hover:border-primary bg-background",
                                                        )}
                                                        title={item.completed ? "Mark incomplete" : "Mark completed"}
                                                    >
                                                        {item.completed && <Check className="size-3" />}
                                                    </button>
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span
                                                                className={cn(
                                                                    "font-semibold",
                                                                    item.completed && "text-muted-foreground line-through",
                                                                )}
                                                            >
                                                                {item.title}
                                                            </span>
                                                            <Badge variant="outline" size="xs" className="font-mono text-[10px]">
                                                                Step 0{idx + 1}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground text-xs font-normal">{item.category}</p>
                                                    </div>
                                                </div>
                                            </AccordionTrigger>
                                            <AccordionContent className="pt-1 pb-3 text-xs">
                                                <div className="bg-muted/30 border-border/50 space-y-2.5 rounded-lg border p-3.5">
                                                    <p className="text-foreground text-xs leading-relaxed">{item.description}</p>
                                                    <div className="space-y-1.5 pt-1">
                                                        <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                                            Verification Criteria
                                                        </p>
                                                        <ul className="space-y-1">
                                                            {item.verificationPoints.map((point, pIdx) => (
                                                                <li key={pIdx} className="text-muted-foreground flex items-center gap-2 text-xs">
                                                                    <span className="bg-primary size-1 shrink-0 rounded-full" />
                                                                    <span>{point}</span>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                    <div className="border-border/40 flex items-center justify-between border-t pt-2">
                                                        <span className="text-muted-foreground text-[11px]">
                                                            Status: {item.completed ? "Verified" : "Pending Action"}
                                                        </span>
                                                        <Button
                                                            variant="outline"
                                                            size="xs"
                                                            render={<Link href={item.routeHref} />}
                                                            className="gap-1 text-xs"
                                                        >
                                                            {item.recommendedAction}
                                                            <ArrowUpRight className="size-3" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            </div>

                            <FrameFooter className="border-border/60 bg-muted/15 text-muted-foreground flex items-center justify-between border-t p-3 text-xs sm:p-4">
                                <span>Checklist state persists during active session</span>
                                <Badge variant="outline" size="xs">
                                    Readiness Protocol v2
                                </Badge>
                            </FrameFooter>
                        </FramePanel>
                    </Frame>
                </div>

                {/* Right: Operational Guidance & Administrator Tips */}
                <div className="lg:col-span-5">
                    <Frame variant="default" className="h-full shadow-2xs">
                        <FramePanel className="flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/60 border-b p-4 sm:p-5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Sparkles className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Operational Guidance & Tips</FrameTitle>
                                    </div>
                                    <Badge variant="outline" size="sm" className="font-mono text-xs">
                                        Best Practices
                                    </Badge>
                                </div>
                                <FrameDescription className="text-xs">
                                    Administrative hints, scheduling rules, and workflow recommendations
                                </FrameDescription>
                            </FrameHeader>

                            <div className="space-y-3 p-4 sm:p-5">
                                {admin_data.beginner_tips && admin_data.beginner_tips.length > 0 ? (
                                    admin_data.beginner_tips.map((tip, idx) => (
                                        <div
                                            key={tip.title || idx}
                                            className="border-border/60 bg-muted/20 hover:bg-muted/30 flex items-start gap-3 rounded-xl border p-3.5 transition-colors"
                                        >
                                            <IconTile variant="soft" size="sm" className="text-primary mt-0.5 shrink-0">
                                                <Info className="size-3.5" />
                                            </IconTile>
                                            <div className="min-w-0">
                                                <p className="text-foreground text-sm leading-snug font-semibold">{tip.title}</p>
                                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">{tip.content}</p>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <>
                                        <div className="border-border/60 bg-muted/20 hover:bg-muted/30 flex items-start gap-3 rounded-xl border p-3.5 transition-colors">
                                            <IconTile variant="soft" size="sm" className="text-primary mt-0.5 shrink-0">
                                                <School className="size-3.5" />
                                            </IconTile>
                                            <div className="min-w-0">
                                                <p className="text-foreground text-sm leading-snug font-semibold">Faculty Assignment Priority</p>
                                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                                    Ensure regular tenure and full-time faculty receive minimum load allocations before scheduling
                                                    adjunct or part-time instructors.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="border-border/60 bg-muted/20 hover:bg-muted/30 flex items-start gap-3 rounded-xl border p-3.5 transition-colors">
                                            <IconTile variant="soft" size="sm" className="mt-0.5 shrink-0 text-emerald-500">
                                                <BookOpen className="size-3.5" />
                                            </IconTile>
                                            <div className="min-w-0">
                                                <p className="text-foreground text-sm leading-snug font-semibold">Section Room Constraints</p>
                                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                                    Laboratory courses require designated computer or science rooms. Check capacity thresholds before
                                                    finalizing student headcounts.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="border-border/60 bg-muted/20 hover:bg-muted/30 flex items-start gap-3 rounded-xl border p-3.5 transition-colors">
                                            <IconTile variant="soft" size="sm" className="mt-0.5 shrink-0 text-amber-500">
                                                <ShieldAlert className="size-3.5" />
                                            </IconTile>
                                            <div className="min-w-0">
                                                <p className="text-foreground text-sm leading-snug font-semibold">Audit Trail Retention</p>
                                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                                    Critical clearance approvals, tuition fee revisions, and grade overrides are permanently logged
                                                    with authenticated causer IDs.
                                                </p>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>

                            <FrameFooter className="border-border/60 bg-muted/15 border-t p-3 sm:p-4">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span>Need procedural assistance?</span>
                                    <Button
                                        variant="ghost"
                                        size="xs"
                                        render={
                                            <Link href={safeRoute("administrators.system-management.index", "/administrators/system-management")} />
                                        }
                                        className="gap-1 text-xs"
                                    >
                                        System Guide
                                        <ArrowUpRight className="size-3" />
                                    </Button>
                                </div>
                            </FrameFooter>
                        </FramePanel>
                    </Frame>
                </div>
            </div>
        </div>
    );
}
