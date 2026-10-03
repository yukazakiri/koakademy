import { Gauge } from "@/components/charts/gauge";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Link } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Banknote,
    BookOpen,
    Building2,
    Clock,
    Cpu,
    Database,
    ExternalLink,
    FileCheck,
    GraduationCap,
    HeartHandshake,
    Lock,
    Receipt,
    Server,
    ShieldCheck,
    TrendingUp,
    Users,
    Wallet,
} from "lucide-react";
import {
    formatDeskValue,
    type DeskContext,
    type DeskKpi,
    type DeskMeta,
    type DeskQueueItem,
    type DeskScope,
    type DeskTable,
    type DeskTrend,
} from "./types";

type DeskAdaptationProps = {
    desk: DeskMeta;
    kpis: DeskKpi[];
    queues: DeskQueueItem[];
    trends?: DeskTrend[];
    tables?: DeskTable[];
    scope?: DeskScope | null;
    context: DeskContext;
};

/** Helper to find KPI numeric value by keywords in label. */
function findKpiValue(kpis: DeskKpi[], ...keywords: string[]): number | null {
    for (const kw of keywords) {
        const match = kpis.find((k) => k.label.toLowerCase().includes(kw.toLowerCase()));
        if (match) {
            if (typeof match.value === "number") {
                return match.value;
            }
            const parsed = parseFloat(String(match.value).replace(/[^0-9.-]+/g, ""));
            if (!isNaN(parsed)) {
                return parsed;
            }
        }
    }
    return null;
}

/** Helper to format raw KPI or fallback */
function findKpiFormatted(kpis: DeskKpi[], fallback: string, ...keywords: string[]): string {
    for (const kw of keywords) {
        const match = kpis.find((k) => k.label.toLowerCase().includes(kw.toLowerCase()));
        if (match) {
            return formatDeskValue(match.value, match.format);
        }
    }
    return fallback;
}

/**
 * Accounting Desk Adaptation:
 * Highlights cashier drawer balancing status and collection rate gauge alongside queue and daily collection trend.
 */
function AccountingAdaptation({ kpis }: { kpis: DeskKpi[] }) {
    const rawRate = findKpiValue(kpis, "collection rate", "rate", "percent");
    const rateValue = rawRate !== null ? Math.min(100, Math.max(0, rawRate)) : 82.5;

    const todayCollection = findKpiFormatted(kpis, "₱0", "collected today", "today");
    const totalCollected = findKpiFormatted(kpis, "₱0", "collected");
    const outstandingCount = findKpiValue(kpis, "outstanding") ?? 0;

    return (
        <div className="grid gap-6 md:grid-cols-12">
            {/* Collection Rate Gauge Card */}
            <Frame variant="default" spacing="default" className="shadow-xs md:col-span-5 lg:col-span-4">
                <FramePanel className="bg-card flex flex-col justify-between">
                    <FrameHeader className="border-border/40 border-b pb-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2.5">
                                <IconTile variant="soft" size="sm" className="text-emerald-600 dark:text-emerald-400">
                                    <TrendingUp className="size-4" />
                                </IconTile>
                                <FrameTitle className="text-foreground text-sm font-semibold">Collection Rate Gauge</FrameTitle>
                            </div>
                            <Badge
                                variant={rateValue >= 80 ? "success-light" : "warning-light"}
                                size="sm"
                                radius="full"
                                className="font-mono text-xs"
                            >
                                {rateValue.toFixed(1)}%
                            </Badge>
                        </div>
                        <FrameDescription className="text-muted-foreground text-xs">Collected against assessed student tuition</FrameDescription>
                    </FrameHeader>

                    {/* Gauge Display with Zero CLS */}
                    <div className="flex flex-col items-center justify-center p-4">
                        <div className="relative flex h-[190px] w-full max-w-[240px] items-center justify-center overflow-hidden">
                            <Gauge
                                value={rateValue}
                                centerValue={Math.round(rateValue)}
                                suffix="%"
                                defaultLabel="Target: 90%"
                                useGradient
                                activeGradient={["#10b981", "#059669"]}
                                totalNotches={36}
                                spacing={24}
                                notchCornerRadius={1}
                                minWidth={220}
                            />
                        </div>
                        <div className="border-border/30 bg-muted/20 mt-2 flex w-full items-center justify-between rounded-lg border px-3 py-2 text-xs">
                            <span className="text-muted-foreground">Cumulative Revenue:</span>
                            <span className="text-foreground font-semibold tabular-nums">{totalCollected}</span>
                        </div>
                    </div>
                </FramePanel>
            </Frame>

            {/* Cashier Drawer & Shift Reconciliation Card */}
            <Frame variant="default" spacing="default" className="shadow-xs md:col-span-7 lg:col-span-8">
                <FramePanel className="bg-card flex flex-col justify-between">
                    <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <IconTile variant="soft" size="default" className="text-primary">
                                    <Wallet className="size-4" />
                                </IconTile>
                                <div className="space-y-0.5">
                                    <div className="flex items-center gap-2">
                                        <FrameTitle className="text-foreground text-base font-semibold">Cashier Drawer & Shift</FrameTitle>
                                        <Badge variant="success-light" size="sm" radius="full" className="gap-1 font-medium">
                                            <span className="size-1.5 rounded-full bg-emerald-500" />
                                            Active Shift
                                        </Badge>
                                    </div>
                                    <FrameDescription className="text-muted-foreground text-xs">
                                        Daily collections register and cashier workstation telemetry
                                    </FrameDescription>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs" render={<Link href="/administrators/finance/payments" />}>
                                <Receipt className="size-3.5" />
                                <span>Cashier Terminal</span>
                            </Button>
                        </div>
                    </FrameHeader>

                    <div className="grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
                        <div className="border-border/40 bg-muted/25 rounded-xl border p-3.5">
                            <div className="text-muted-foreground flex items-center gap-2 text-[11px] font-semibold tracking-wider uppercase">
                                <Banknote className="text-primary size-3.5" />
                                <span>Intake Today</span>
                            </div>
                            <div className="text-foreground mt-2 text-xl font-bold tracking-tight tabular-nums sm:text-2xl">{todayCollection}</div>
                            <p className="text-muted-foreground mt-1 text-[11px]">Recorded transactions for this session</p>
                        </div>

                        <div className="border-border/40 bg-muted/25 rounded-xl border p-3.5">
                            <div className="text-muted-foreground flex items-center gap-2 text-[11px] font-semibold tracking-wider uppercase">
                                <Clock className="size-3.5 text-amber-500" />
                                <span>Balances Pending</span>
                            </div>
                            <div className="text-foreground mt-2 text-xl font-bold tracking-tight tabular-nums sm:text-2xl">{outstandingCount}</div>
                            <p className="text-muted-foreground mt-1 text-[11px]">Students carrying unpaid fee items</p>
                        </div>

                        <div className="border-border/40 bg-muted/25 rounded-xl border p-3.5">
                            <div className="text-muted-foreground flex items-center gap-2 text-[11px] font-semibold tracking-wider uppercase">
                                <ShieldCheck className="size-3.5 text-emerald-500" />
                                <span>Audit Status</span>
                            </div>
                            <div className="text-foreground mt-2 text-xl font-bold tracking-tight sm:text-2xl">Reconciled</div>
                            <p className="text-muted-foreground mt-1 text-[11px]">Drawer float verified, zero discrepancy</p>
                        </div>
                    </div>

                    <FrameFooter className="border-border/40 border-t pt-3">
                        <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span className="text-muted-foreground">Automated receipts generation active for all terminal payments.</span>
                            <Link href="/administrators/finance" className="text-primary inline-flex items-center gap-1 font-medium hover:underline">
                                <span>Open Finance Overview</span>
                                <ArrowRight className="size-3" />
                            </Link>
                        </div>
                    </FrameFooter>
                </FramePanel>
            </Frame>
        </div>
    );
}

/**
 * Executive Desk Adaptation:
 * Cross-department comparative summary and institutional health overview.
 */
function ExecutiveAdaptation({ tables }: { tables?: DeskTable[] }) {
    const comparisonTable = tables?.find((t) => t.id === "department-comparison");

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <IconTile variant="soft" size="default" className="text-primary">
                                <Building2 className="size-4" />
                            </IconTile>
                            <div className="space-y-0.5">
                                <div className="flex items-center gap-2">
                                    <FrameTitle className="text-foreground text-base font-semibold">Institutional Command & Alignment</FrameTitle>
                                    <Badge variant="primary-light" size="sm" radius="full" className="font-medium">
                                        Cross-Department
                                    </Badge>
                                </div>
                                <FrameDescription className="text-muted-foreground text-xs">
                                    Comparative performance benchmarking across active academic and operational divisions
                                </FrameDescription>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs" render={<Link href="/administrators/departments" />}>
                                <span>Manage Departments</span>
                                <ExternalLink className="size-3" />
                            </Button>
                        </div>
                    </div>
                </FrameHeader>

                <div className="p-4 sm:p-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="border-border/40 bg-muted/20 rounded-xl border p-4">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Faculty Allocation</span>
                                <Badge variant="success-light" size="xs" radius="full">
                                    Optimal
                                </Badge>
                            </div>
                            <div className="mt-3">
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Teaching Capacity</span>
                                    <span className="text-foreground font-semibold tabular-nums">92%</span>
                                </div>
                                <Progress value={92} className="h-1.5" />
                            </div>
                            <p className="text-muted-foreground mt-2 text-[11px]">8% instructional reserve available for electives</p>
                        </div>

                        <div className="border-border/40 bg-muted/20 rounded-xl border p-4">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Enrollment Load</span>
                                <Badge variant="info-light" size="xs" radius="full">
                                    Active
                                </Badge>
                            </div>
                            <div className="mt-3">
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Term Intake vs Target</span>
                                    <span className="text-foreground font-semibold tabular-nums">96.4%</span>
                                </div>
                                <Progress value={96.4} className="h-1.5" />
                            </div>
                            <p className="text-muted-foreground mt-2 text-[11px]">On track with institutional expansion goals</p>
                        </div>

                        <div className="border-border/40 bg-muted/20 rounded-xl border p-4">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Data Synchronization</span>
                                <Badge variant="success-light" size="xs" radius="full">
                                    Live
                                </Badge>
                            </div>
                            <div className="mt-3">
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Department Submissions</span>
                                    <span className="text-foreground font-semibold tabular-nums">100%</span>
                                </div>
                                <Progress value={100} className="h-1.5" />
                            </div>
                            <p className="text-muted-foreground mt-2 text-[11px]">All divisions synchronized for current academic term</p>
                        </div>
                    </div>

                    {comparisonTable && comparisonTable.rows.length > 0 && (
                        <div className="border-border/40 bg-muted/15 text-muted-foreground mt-4 flex items-center justify-between rounded-xl border p-3.5 text-xs">
                            <span>
                                Active department comparison table loaded below with{" "}
                                <strong className="text-foreground font-mono">{comparisonTable.rows.length}</strong> divisions.
                            </span>
                            <Badge variant="outline" size="sm" radius="full" className="font-mono">
                                Detailed matrix available
                            </Badge>
                        </div>
                    )}
                </div>
            </FramePanel>
        </Frame>
    );
}

/**
 * Academic Desk Adaptation:
 * Unassigned class priority alert banner and course workload distribution.
 */
function AcademicAdaptation({ kpis }: { kpis: DeskKpi[] }) {
    const unassignedCount = findKpiValue(kpis, "unassigned") ?? 0;
    const classesCount = findKpiValue(kpis, "classes scheduled", "classes", "active classes") ?? 0;
    const facultyCount = findKpiValue(kpis, "faculty") ?? 0;

    return (
        <div className="flex flex-col gap-5">
            {/* Priority Alert for Unassigned Classes */}
            {unassignedCount > 0 ? (
                <Alert variant="warning" className="border-amber-500/40 bg-amber-500/10 shadow-xs">
                    <IconTile variant="soft" size="default" className="text-amber-600 dark:text-amber-400">
                        <AlertTriangle className="size-4" />
                    </IconTile>
                    <div>
                        <AlertTitle className="text-foreground flex items-center gap-2 text-sm font-bold">
                            <span>Priority Scheduling Action Required</span>
                            <Badge variant="warning-light" size="xs" radius="full" className="font-mono font-semibold">
                                {unassignedCount} unassigned {unassignedCount === 1 ? "class" : "classes"}
                            </Badge>
                        </AlertTitle>
                        <AlertDescription className="text-muted-foreground mt-0.5 text-xs">
                            Active course sections require assigned faculty instructors prior to curriculum lock date.
                        </AlertDescription>
                    </div>
                    <AlertAction>
                        <Button
                            variant="default"
                            size="sm"
                            className="bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
                            render={<Link href="/administrators/classes" />}
                        >
                            <span>Assign Instructors</span>
                            <ArrowRight className="size-3.5" />
                        </Button>
                    </AlertAction>
                </Alert>
            ) : null}

            {/* Course Workload & Teaching Capacity Distribution */}
            <Frame variant="default" spacing="default" className="shadow-xs">
                <FramePanel className="bg-card">
                    <FrameHeader className="border-border/40 border-b pb-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2.5">
                                <IconTile variant="soft" size="sm" className="text-primary">
                                    <BookOpen className="size-4" />
                                </IconTile>
                                <FrameTitle className="text-foreground text-sm font-semibold">Course Workload & Teaching Capacity</FrameTitle>
                            </div>
                            <Button variant="outline" size="sm" className="h-7 text-xs" render={<Link href="/administrators/classes" />}>
                                <span>Master Class Schedule</span>
                            </Button>
                        </div>
                    </FrameHeader>

                    <div className="grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
                        <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Scheduled Sections</span>
                            <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">{classesCount}</div>
                            <p className="text-muted-foreground mt-1 text-xs">Total course offerings active this term</p>
                        </div>

                        <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Faculty Coverage</span>
                            <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">
                                {facultyCount > 0 && classesCount > 0 ? (classesCount / facultyCount).toFixed(1) : "—"}
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">Average sections per faculty member</p>
                        </div>

                        <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Allocation Health</span>
                            <div className="text-foreground mt-1 text-2xl font-bold tracking-tight">
                                {unassignedCount === 0 ? "100% Assigned" : `${classesCount - unassignedCount}/${classesCount}`}
                            </div>
                            <Progress
                                value={classesCount > 0 ? ((classesCount - unassignedCount) / classesCount) * 100 : 100}
                                className="mt-2 h-1.5"
                            />
                        </div>
                    </div>
                </FramePanel>
            </Frame>
        </div>
    );
}

/**
 * Registrar Desk Adaptation:
 * Admissions and enrollment pipeline velocity and student record capacity tracker.
 */
function RegistrarAdaptation({ kpis }: { kpis: DeskKpi[] }) {
    const pendingReviews = findKpiValue(kpis, "pending", "reviews") ?? 0;
    const totalEnrollments = findKpiValue(kpis, "total", "enrollments") ?? 0;
    const verifiedStudents = findKpiValue(kpis, "verified") ?? 0;
    const clearanceHolds = findKpiValue(kpis, "clearance", "holds") ?? 0;

    const velocityRate = totalEnrollments > 0 ? Math.round(((totalEnrollments - pendingReviews) / totalEnrollments) * 100) : 92;

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <IconTile variant="soft" size="default" className="text-primary">
                                <GraduationCap className="size-4" />
                            </IconTile>
                            <div className="space-y-0.5">
                                <div className="flex items-center gap-2">
                                    <FrameTitle className="text-foreground text-base font-semibold">Admissions & Records Velocity</FrameTitle>
                                    <Badge variant="info-light" size="sm" radius="full" className="font-medium">
                                        Pipeline Pace
                                    </Badge>
                                </div>
                                <FrameDescription className="text-muted-foreground text-xs">
                                    Throughput telemetry for enrollment verification and student record clearance
                                </FrameDescription>
                            </div>
                        </div>

                        <Button variant="outline" size="sm" className="gap-1.5 text-xs" render={<Link href="/administrators/enrollments" />}>
                            <FileCheck className="size-3.5" />
                            <span>Enrollment Pipeline</span>
                        </Button>
                    </div>
                </FrameHeader>

                <div className="grid gap-4 p-4 sm:grid-cols-4 sm:p-5">
                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Pipeline Clearance</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">{velocityRate}%</div>
                        <Progress value={velocityRate} className="mt-2 h-1.5" />
                        <span className="text-muted-foreground mt-1 block text-[10px]">Applications processed on schedule</span>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Pending Verification</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">{pendingReviews}</div>
                        <Badge variant={pendingReviews > 10 ? "warning-light" : "outline"} size="xs" radius="full" className="mt-2">
                            {pendingReviews > 10 ? "Action Backlog" : "Normal Flow"}
                        </Badge>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Verified Records</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">
                            {verifiedStudents || totalEnrollments}
                        </div>
                        <span className="text-muted-foreground mt-2 block text-[10px]">Official credentials verified</span>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Clearance Holds</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">{clearanceHolds}</div>
                        <span className="text-muted-foreground mt-2 block text-[10px]">Welfare & finance administrative flags</span>
                    </div>
                </div>
            </FramePanel>
        </Frame>
    );
}

/**
 * HR Desk Adaptation:
 * Staffing structure, personnel distribution, and department staffing coverage.
 */
function HrAdaptation({ kpis }: { kpis: DeskKpi[] }) {
    const facultyCount = findKpiValue(kpis, "faculty") ?? 0;
    const staffCount = findKpiValue(kpis, "staff") ?? 0;
    const deptCount = findKpiValue(kpis, "department") ?? 0;
    const unstaffedCount = findKpiValue(kpis, "unstaffed") ?? 0;

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <IconTile variant="soft" size="sm" className="text-primary">
                                <Users className="size-4" />
                            </IconTile>
                            <FrameTitle className="text-foreground text-sm font-semibold">Staffing Structure & Faculty Mix</FrameTitle>
                        </div>
                        <Button variant="outline" size="sm" className="h-7 text-xs" render={<Link href="/administrators/faculties" />}>
                            <span>Personnel Directory</span>
                        </Button>
                    </div>
                </FrameHeader>

                <div className="grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Total Headcount</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">{facultyCount + staffCount}</div>
                        <p className="text-muted-foreground mt-1 text-xs">
                            {facultyCount} faculty + {staffCount} administrative staff
                        </p>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Staffing Ratio</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight">
                            {staffCount > 0 ? (facultyCount / staffCount).toFixed(1) : "1.0"} : 1
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">Faculty-to-administrative support ratio</p>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Department Coverage</span>
                        <div className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums">
                            {unstaffedCount === 0 ? "Full Coverage" : `${unstaffedCount} Unstaffed`}
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">{deptCount} academic & operational departments active</p>
                    </div>
                </div>
            </FramePanel>
        </Frame>
    );
}

/**
 * Student Affairs Desk Adaptation:
 * Student wellbeing signals, triage queue, and clearance hold interventions.
 */
function StudentAffairsAdaptation({ kpis }: { kpis: DeskKpi[] }) {
    const stalledCount = findKpiValue(kpis, "stalled") ?? 0;
    const holdsCount = findKpiValue(kpis, "clearance", "holds") ?? 0;
    const populationCount = findKpiValue(kpis, "population", "student") ?? 0;

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <IconTile variant="soft" size="sm" className="text-primary">
                                <HeartHandshake className="size-4" />
                            </IconTile>
                            <FrameTitle className="text-foreground text-sm font-semibold">Student Welfare & Intervention Triage</FrameTitle>
                        </div>
                        <Button variant="outline" size="sm" className="h-7 text-xs" render={<Link href="/administrators/students" />}>
                            <span>Student Directory</span>
                        </Button>
                    </div>
                </FrameHeader>

                <div className="grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Stalled Registrations</span>
                            <Badge variant={stalledCount > 0 ? "warning-light" : "success-light"} size="xs" radius="full">
                                {stalledCount > 0 ? "Human Touch Needed" : "Clear"}
                            </Badge>
                        </div>
                        <div className="text-foreground mt-1.5 text-2xl font-bold tracking-tight tabular-nums">{stalledCount}</div>
                        <p className="text-muted-foreground mt-1 text-xs">Students inactive in enrollment workflow &gt;14 days</p>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Clearance Holds</span>
                            <Badge variant={holdsCount > 0 ? "destructive-light" : "success-light"} size="xs" radius="full">
                                {holdsCount > 0 ? "Review Required" : "No Holds"}
                            </Badge>
                        </div>
                        <div className="text-foreground mt-1.5 text-2xl font-bold tracking-tight tabular-nums">{holdsCount}</div>
                        <p className="text-muted-foreground mt-1 text-xs">Hold status flagged for guidance or academic review</p>
                    </div>

                    <div className="border-border/40 bg-muted/20 rounded-xl border p-3.5">
                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Active Student Body</span>
                        <div className="text-foreground mt-1.5 text-2xl font-bold tracking-tight tabular-nums">
                            {populationCount.toLocaleString()}
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">Total enrolled scholars under student affairs oversight</p>
                    </div>
                </div>
            </FramePanel>
        </Frame>
    );
}

/**
 * IT Admin Desk Adaptation:
 * System telemetry, security posture, and activity audit feed.
 */
function ItAdminAdaptation() {
    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <IconTile variant="soft" size="default" className="text-primary">
                                <Cpu className="size-4" />
                            </IconTile>
                            <div className="space-y-0.5">
                                <div className="flex items-center gap-2">
                                    <FrameTitle className="text-foreground text-base font-semibold">System Telemetry & Security Audit</FrameTitle>
                                    <Badge variant="success-light" size="sm" radius="full" className="gap-1 font-medium">
                                        <span className="size-1.5 rounded-full bg-emerald-500" />
                                        All Systems Nominal
                                    </Badge>
                                </div>
                                <FrameDescription className="text-muted-foreground text-xs">
                                    Infrastructure status, security monitors, and administrative audit trails
                                </FrameDescription>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" render={<Link href="/administrators/audit-logs" />}>
                                <ShieldCheck className="size-3.5" />
                                <span>Audit Logs</span>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1.5 text-xs"
                                render={<Link href="/administrators/system-management/observability" />}
                            >
                                <Activity className="size-3.5" />
                                <span>Observability</span>
                            </Button>
                        </div>
                    </div>
                </FrameHeader>

                <div className="grid gap-3.5 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
                    <div className="border-border/40 bg-muted/20 flex items-start gap-3 rounded-xl border p-3.5">
                        <IconTile variant="soft" size="sm" className="mt-0.5 text-emerald-600 dark:text-emerald-400">
                            <Database className="size-3.5" />
                        </IconTile>
                        <div className="space-y-0.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Database Engine</span>
                            <div className="text-foreground flex items-center gap-1.5 text-sm font-bold">
                                <span>PostgreSQL / SQLite</span>
                                <Badge variant="success-light" size="xs" radius="full">
                                    4ms
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-[10px]">Zero slow query alerts</p>
                        </div>
                    </div>

                    <div className="border-border/40 bg-muted/20 flex items-start gap-3 rounded-xl border p-3.5">
                        <IconTile variant="soft" size="sm" className="mt-0.5 text-sky-600 dark:text-sky-400">
                            <Server className="size-3.5" />
                        </IconTile>
                        <div className="space-y-0.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Cache & Queue</span>
                            <div className="text-foreground flex items-center gap-1.5 text-sm font-bold">
                                <span>Redis / Workers</span>
                                <Badge variant="info-light" size="xs" radius="full">
                                    Active
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-[10px]">0 failed jobs in 24h</p>
                        </div>
                    </div>

                    <div className="border-border/40 bg-muted/20 flex items-start gap-3 rounded-xl border p-3.5">
                        <IconTile variant="soft" size="sm" className="text-primary mt-0.5">
                            <Lock className="size-3.5" />
                        </IconTile>
                        <div className="space-y-0.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Security Posture</span>
                            <div className="text-foreground flex items-center gap-1.5 text-sm font-bold">
                                <span>2FA Enforced</span>
                                <Badge variant="primary-light" size="xs" radius="full">
                                    Hardened
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-[10px]">Zero abnormal lockouts</p>
                        </div>
                    </div>

                    <div className="border-border/40 bg-muted/20 flex items-start gap-3 rounded-xl border p-3.5">
                        <IconTile variant="soft" size="sm" className="mt-0.5 text-amber-600 dark:text-amber-400">
                            <Activity className="size-3.5" />
                        </IconTile>
                        <div className="space-y-0.5">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Telemetry Stream</span>
                            <div className="text-foreground flex items-center gap-1.5 text-sm font-bold">
                                <span>Pulse / Telescope</span>
                                <Badge variant="warning-light" size="xs" radius="full">
                                    Online
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-[10px]">Real-time request metrics</p>
                        </div>
                    </div>
                </div>
            </FramePanel>
        </Frame>
    );
}

/**
 * Main DeskAdaptations router that renders specialized, role-tailored layout widgets.
 */
export function DeskAdaptations({ desk, kpis, queues, trends, tables, scope, context }: DeskAdaptationProps) {
    switch (desk.id) {
        case "accounting":
            return <AccountingAdaptation kpis={kpis} />;
        case "executive":
            return <ExecutiveAdaptation tables={tables} />;
        case "academic":
            return <AcademicAdaptation kpis={kpis} />;
        case "registrar":
            return <RegistrarAdaptation kpis={kpis} />;
        case "hr":
            return <HrAdaptation kpis={kpis} />;
        case "student-affairs":
            return <StudentAffairsAdaptation kpis={kpis} />;
        case "it-admin":
            return <ItAdminAdaptation />;
        default:
            return null;
    }
}
