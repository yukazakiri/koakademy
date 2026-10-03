import {
    Bar,
    BarChart,
    BarXAxis,
    BarYAxis,
    ChartTooltip,
    Grid,
    Legend,
    LegendItem,
    LegendLabel,
    LegendMarker,
    LegendProgress,
    LegendValue,
    Ring,
    RingCenter,
    RingChart,
    chartCssVars,
} from "@/components/charts";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { ArrowUpRight, Calendar, CheckCircle2, GraduationCap, Info, Layers, PieChart, Search, Sparkles, UserCheck, Users, X } from "lucide-react";
import { useMemo, useState } from "react";
import type { DashboardViewProps, RecentStudent, StudentTypeItem, TopCourse } from "./types";

type RingSlice = {
    label: string;
    value: number;
    maxValue: number;
    color: string;
};

const chartPalette = ["var(--chart-1)", "var(--chart-2)", "var(--chart-3)", "var(--chart-4)", "var(--chart-5)"] as const;

function formatNumber(value: number): string {
    return new Intl.NumberFormat("en-US").format(value);
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat("en-US", { maximumFractionDigits: 1 }).format(value)}%`;
}

function formatDateTime(value?: string | null): string {
    if (!value) return "Recently";
    try {
        return new Intl.DateTimeFormat("en-US", {
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit",
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function getInitials(name: string): string {
    if (!name) return "ST";
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

export default function StudentDemographicsView({ user, admin_data, currency }: DashboardViewProps) {
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedClassification, setSelectedClassification] = useState<string>("all");
    const [hoveredRingIndex, setHoveredRingIndex] = useState<number | null>(null);

    // Current Academic Period
    const currentPeriod = admin_data.current_period ?? {
        school_year: "2026-2027",
        semester: 1,
        label: "Active Academic Term",
    };

    // Demographic total headcount. A genuine zero is preserved so an empty institution reads as
    // empty rather than being backfilled with a plausible-looking number.
    const totalStudents = useMemo(() => {
        if (typeof admin_data.student_demographics?.total === "number") {
            return admin_data.student_demographics.total;
        }
        if (typeof admin_data.enrollment_health?.enrolled === "number") {
            return admin_data.enrollment_health.enrolled;
        }
        return 0;
    }, [admin_data.student_demographics?.total, admin_data.enrollment_health?.enrolled]);

    // Student classifications / types. Never synthesised: an empty breakdown renders an empty state.
    const studentTypes: StudentTypeItem[] = useMemo(() => {
        const types = admin_data.student_demographics?.by_type?.length
            ? admin_data.student_demographics.by_type
            : admin_data.analytics?.student_types?.length
              ? admin_data.analytics.student_types
              : [];

        return types;
    }, [admin_data.student_demographics?.by_type, admin_data.analytics?.student_types]);

    // RingChart data format
    const ringData: RingSlice[] = useMemo(() => {
        return studentTypes.map((item, index) => ({
            label: item.label,
            value: item.count,
            maxValue: Math.max(totalStudents, 1),
            color: chartPalette[index % chartPalette.length],
        }));
    }, [studentTypes, totalStudents]);

    // Top Degree Programs. Never synthesised, so the ranking only ever reports real enrolment.
    const topCourses: TopCourse[] = useMemo(() => {
        return admin_data.student_demographics?.top_courses?.length
            ? admin_data.student_demographics.top_courses
            : admin_data.analytics?.top_courses?.length
              ? admin_data.analytics.top_courses
              : [];
    }, [admin_data.student_demographics?.top_courses, admin_data.analytics?.top_courses]);

    // Top Degree Programs Horizontal Bar Chart data
    const topCoursesChartData = useMemo(() => {
        return topCourses.slice(0, 5).map((course) => ({
            code: course.code,
            students: Number(course.student_count) || 0,
            title: course.title,
        }));
    }, [topCourses]);

    // Year-Level distribution. Never synthesised.
    const yearLevels = useMemo(() => {
        return admin_data.student_demographics?.by_year_level?.length
            ? admin_data.student_demographics.by_year_level
            : admin_data.analytics?.year_level_distribution?.length
              ? admin_data.analytics.year_level_distribution
              : [];
    }, [admin_data.student_demographics?.by_year_level, admin_data.analytics?.year_level_distribution]);

    // Year-Level BarChart data
    const yearLevelChartData = useMemo(() => {
        return yearLevels.map((lvl) => ({
            year_level: lvl.year_level,
            students: Number(lvl.count) || 0,
        }));
    }, [yearLevels]);

    // Gender breakdown. Never synthesised.
    const genderBreakdown = useMemo(() => {
        const categories = admin_data.student_demographics?.by_gender?.length
            ? admin_data.student_demographics.by_gender
            : admin_data.analytics?.gender_distribution?.length
              ? admin_data.analytics.gender_distribution
              : [];

        const sumCount = categories.reduce((sum, item) => sum + (Number(item.count) || 0), 0) || 1;

        return categories.map((item) => ({
            gender: item.gender,
            count: Number(item.count) || 0,
            percentage: ((Number(item.count) || 0) / sumCount) * 100,
        }));
    }, [admin_data.student_demographics?.by_gender, admin_data.analytics?.gender_distribution]);

    // Gender parity ratio text
    const genderRatioText = useMemo(() => {
        if (genderBreakdown.length >= 2) {
            const first = genderBreakdown[0];
            const second = genderBreakdown[1];
            if (second.count > 0) {
                const ratio = (first.count / second.count).toFixed(2);
                return `${ratio}:1 (${first.gender} to ${second.gender})`;
            }
        }
        return "Demographic Parity";
    }, [genderBreakdown]);

    // Recent Students Roster. Never synthesised: the profile links below carry real record ids, so an
    // invented roster would point administrators at students that do not exist.
    const recentStudents: RecentStudent[] = useMemo(() => {
        return admin_data.recent_records?.students?.length
            ? admin_data.recent_records.students
            : admin_data.analytics?.recent_students?.length
              ? admin_data.analytics.recent_students
              : [];
    }, [admin_data.recent_records?.students, admin_data.analytics?.recent_students]);

    // Unique classification list for filter pills
    const classificationOptions = useMemo(() => {
        const set = new Set<string>();
        for (const s of recentStudents) {
            if (s.type) {
                set.add(s.type);
            }
        }
        return Array.from(set);
    }, [recentStudents]);

    // Filtered Students
    const filteredStudents = useMemo(() => {
        return recentStudents.filter((student) => {
            const matchesClassification =
                selectedClassification === "all" || (student.type && student.type.toLowerCase() === selectedClassification.toLowerCase());

            if (!matchesClassification) {
                return false;
            }

            if (!searchQuery.trim()) {
                return true;
            }

            const query = searchQuery.toLowerCase();
            return (
                student.name.toLowerCase().includes(query) ||
                (student.student_id && student.student_id.toLowerCase().includes(query)) ||
                (student.course && student.course.toLowerCase().includes(query)) ||
                (student.type && student.type.toLowerCase().includes(query)) ||
                (student.status && student.status.toLowerCase().includes(query))
            );
        });
    }, [recentStudents, searchQuery, selectedClassification]);

    return (
        <div className="space-y-6">
            {/* =========================================================
                1. HERO HEADER: Student Population Telemetry Bar
                ========================================================= */}
            <Frame variant="default" className="border-border/80 from-card via-card to-muted/20 bg-gradient-to-r shadow-xs">
                <FramePanel className="bg-transparent p-5 sm:p-6">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <Badge
                                    variant="outline"
                                    size="sm"
                                    radius="full"
                                    className="border-primary/30 text-foreground bg-primary/5 gap-1.5 font-medium"
                                >
                                    <Calendar className="text-primary size-3" />
                                    <span>{currentPeriod.label || `SY ${currentPeriod.school_year} • Semester ${currentPeriod.semester}`}</span>
                                </Badge>
                                <Badge variant="secondary" size="sm" radius="full" className="gap-1.5 font-semibold tabular-nums">
                                    <Users className="text-primary size-3" />
                                    <span>{formatNumber(totalStudents)} Total Headcount</span>
                                </Badge>
                                <span className="text-muted-foreground hidden text-xs sm:inline">&bull;</span>
                                <span className="text-muted-foreground hidden font-mono text-xs tracking-wider uppercase sm:inline">
                                    Demographic Intelligence Matrix
                                </span>
                            </div>

                            <div className="space-y-1">
                                <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">
                                    Student Demographics & Diversity Matrix
                                </h1>
                                <p className="text-muted-foreground max-w-2xl text-xs sm:text-sm">
                                    Real-time population structure, academic cohort classification, degree program density, and enrollment roster.
                                </p>
                            </div>
                        </div>

                        {/* Telemetry Actions */}
                        <div className="flex flex-wrap items-center gap-2.5 self-start lg:self-center">
                            <div className="border-border/70 bg-card flex items-center gap-2.5 rounded-lg border px-3 py-2 shadow-2xs">
                                <IconTile variant="soft" size="xs" className="text-primary">
                                    <Sparkles className="size-3.5" />
                                </IconTile>
                                <div className="leading-tight">
                                    <span className="text-muted-foreground block text-[10px] font-medium tracking-wider uppercase">
                                        Program Diversity
                                    </span>
                                    <span className="text-foreground text-sm font-bold tabular-nums">{topCourses.length} Active Tracks</span>
                                </div>
                            </div>

                            <Link
                                href="/administrators/students"
                                className={cn(buttonVariants({ variant: "default", size: "sm" }), "gap-1.5 text-xs font-semibold shadow-xs")}
                            >
                                <Users className="size-3.5" />
                                <span>Student Directory</span>
                                <ArrowUpRight className="size-3" />
                            </Link>
                        </div>
                    </div>
                </FramePanel>
            </Frame>

            {/* Informational Synchronization Alert */}
            {totalStudents > 0 ? (
                <Alert variant="info" className="border-info/20 bg-info/5 text-info-foreground">
                    <Info className="text-info size-4" />
                    <AlertTitle>Demographics Synchronized</AlertTitle>
                    <AlertDescription>
                        Population telemetry reflects active term registrations for {currentPeriod.label || `SY ${currentPeriod.school_year}`}. All
                        cohort percentages are calculated based on {formatNumber(totalStudents)} verified records.
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert variant="warning" className="border-warning/20 bg-warning/5 text-warning-foreground">
                    <Info className="text-warning size-4" />
                    <AlertTitle>No student records yet</AlertTitle>
                    <AlertDescription>
                        No student profiles exist for {currentPeriod.label || `SY ${currentPeriod.school_year}`}. Distributions below stay empty until
                        students are registered.
                    </AlertDescription>
                </Alert>
            )}

            {/* =========================================================
                2. COMPACT 2x2 DEMOGRAPHIC KPI GRID (card-40 template)
                ========================================================= */}
            <Frame variant="default" className="w-full shadow-2xs">
                <FramePanel className="overflow-hidden p-0">
                    <FrameHeader className="border-border/60 flex flex-row items-center justify-between border-b p-4 sm:p-5">
                        <div className="flex items-center gap-2.5">
                            <IconTile variant="soft" size="xs" className="text-primary">
                                <Users className="size-3.5" />
                            </IconTile>
                            <div>
                                <FrameTitle className="text-base font-semibold">Demographic Concentrations & Cohort Ratios</FrameTitle>
                                <FrameDescription className="text-xs">
                                    Key academic profile distributions across degree programs and classification tracks
                                </FrameDescription>
                            </div>
                        </div>
                        <Badge variant="outline" size="sm" radius="full" className="gap-1 font-mono text-xs tabular-nums">
                            <CheckCircle2 className="size-3 text-emerald-500" />
                            <span>Live Census</span>
                        </Badge>
                    </FrameHeader>

                    {/* 4-cell metric panel with border dividers */}
                    <div className="divide-border/60 bg-card/60 grid grid-cols-1 divide-y sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4 lg:divide-y-0">
                        {/* Cell 1: Total Headcount */}
                        <div className="hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5">
                            <div className="flex items-baseline gap-1.5">
                                <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                    {formatNumber(totalStudents)}
                                </span>
                                <span className="text-muted-foreground text-xs font-semibold uppercase">students</span>
                            </div>
                            <div className="mt-2.5">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase sm:text-[11px]">
                                    Total Headcount
                                </p>
                                <p className="text-muted-foreground/80 mt-0.5 text-xs">Verified student profiles in directory catalog</p>
                            </div>
                        </div>

                        {/* Cell 2: Year-Level Distribution */}
                        <div className="hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5">
                            <div className="flex items-baseline gap-1.5">
                                <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                    {yearLevels.length}
                                </span>
                                <span className="text-muted-foreground text-xs font-semibold uppercase">cohorts</span>
                            </div>
                            <div className="mt-2.5">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase sm:text-[11px]">
                                    Year-Level Distribution
                                </p>
                                <p className="text-muted-foreground/80 mt-0.5 text-xs">Across 1st Year to 4th Year standing</p>
                            </div>
                        </div>

                        {/* Cell 3: Gender Balance Ratio */}
                        <div className="hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5">
                            <div className="flex items-baseline gap-1.5">
                                <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                    {genderBreakdown.length}
                                </span>
                                <span className="text-muted-foreground text-xs font-semibold uppercase">categories</span>
                            </div>
                            <div className="mt-2.5">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase sm:text-[11px]">
                                    Gender Balance Ratio
                                </p>
                                <p className="text-muted-foreground/80 mt-0.5 text-xs">{genderRatioText}</p>
                            </div>
                        </div>

                        {/* Cell 4: Degree Programs */}
                        <div className="hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5">
                            <div className="flex items-baseline gap-1.5">
                                <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                    {topCourses.length}
                                </span>
                                <span className="text-muted-foreground text-xs font-semibold uppercase">courses</span>
                            </div>
                            <div className="mt-2.5">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase sm:text-[11px]">
                                    Degree Programs
                                </p>
                                <p className="text-muted-foreground/80 mt-0.5 text-xs">Active curriculum tracks with active rosters</p>
                            </div>
                        </div>
                    </div>
                </FramePanel>
            </Frame>

            {/* =========================================================
                3. DISTRIBUTION & COMPOSITION SPLIT GRID
                ========================================================= */}
            <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                {/* Left Side: Student Mix Ring Chart */}
                <Frame variant="default" className="border-border/80 shadow-2xs lg:col-span-5">
                    <FramePanel className="flex flex-col justify-between p-4 sm:p-5">
                        <FrameHeader className="border-border/60 border-b p-0 pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <IconTile variant="soft" size="xs" className="text-primary">
                                        <PieChart className="size-3.5" />
                                    </IconTile>
                                    <div>
                                        <FrameTitle className="text-base font-semibold">Student Classification Mix</FrameTitle>
                                        <FrameDescription className="text-xs">
                                            Proportional composition across student admission categories
                                        </FrameDescription>
                                    </div>
                                </div>
                                <Badge variant="outline" size="sm" radius="full" className="font-mono text-xs tabular-nums">
                                    {studentTypes.length} Tiers
                                </Badge>
                            </div>
                        </FrameHeader>

                        {studentTypes.length > 0 ? (
                            <>
                                {/* Ring Chart Center & Visual Canvas */}
                                <div className="pt-4">
                                    <div className="mx-auto flex w-full max-w-[260px] items-center justify-center">
                                        <RingChart
                                            data={ringData}
                                            size={250}
                                            strokeWidth={14}
                                            ringGap={6}
                                            baseInnerRadius={55}
                                            hoveredIndex={hoveredRingIndex}
                                            onHoverChange={setHoveredRingIndex}
                                        >
                                            {ringData.map((item, index) => (
                                                <Ring key={item.label} index={index} />
                                            ))}
                                            <RingCenter defaultLabel="Total Students" />
                                        </RingChart>
                                    </div>

                                    {/* Interactive Legend with Progress Bars */}
                                    <div className="mt-5">
                                        <Legend
                                            items={ringData}
                                            hoveredIndex={hoveredRingIndex}
                                            onHoverChange={setHoveredRingIndex}
                                            className="flex flex-col gap-2"
                                        >
                                            <LegendItem className="hover:bg-muted/30 flex flex-col gap-1.5 rounded-lg p-2 transition-colors">
                                                <div className="flex items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <LegendMarker className="size-2.5" />
                                                        <LegendLabel className="text-xs font-medium" />
                                                    </div>
                                                    <LegendValue showPercentage className="text-xs tabular-nums" />
                                                </div>
                                                <LegendProgress height="h-1.5" />
                                            </LegendItem>
                                        </Legend>
                                    </div>
                                </div>

                                {/* Micro-insight Footer */}
                                <div className="border-border/60 text-muted-foreground mt-4 flex items-center justify-between border-t pt-3 text-xs">
                                    <span className="font-medium">Primary Cohort Focus</span>
                                    <span className="text-foreground font-semibold">
                                        {studentTypes[0]?.label ?? "—"} ({studentTypes[0]?.percentage ?? 0}%)
                                    </span>
                                </div>
                            </>
                        ) : (
                            <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
                                <IconTile variant="soft" size="lg" className="text-muted-foreground mb-3">
                                    <PieChart className="size-5" />
                                </IconTile>
                                <p className="text-foreground text-sm font-semibold">No classification data</p>
                                <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                                    Student profiles have not been classified into admission categories yet, so the mix cannot be drawn.
                                </p>
                            </div>
                        )}
                    </FramePanel>
                </Frame>

                {/* Right Side: Charts Stack (Top Programs, Year Level, Gender Breakdown) */}
                <div className="space-y-6 lg:col-span-7">
                    {/* Top Degree Programs Horizontal Bar Chart */}
                    <Frame variant="default" className="border-border/80 shadow-2xs">
                        <FramePanel className="flex flex-col justify-between p-4 sm:p-5">
                            <FrameHeader className="border-border/60 border-b p-0 pb-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <GraduationCap className="size-3.5" />
                                        </IconTile>
                                        <div>
                                            <FrameTitle className="text-base font-semibold">Top Degree Programs</FrameTitle>
                                            <FrameDescription className="text-xs">
                                                Enrollment distribution ranked by major academic pathways
                                            </FrameDescription>
                                        </div>
                                    </div>
                                    <Badge variant="outline" size="sm" className="font-mono text-xs tabular-nums">
                                        Top {topCoursesChartData.length} Majors
                                    </Badge>
                                </div>
                            </FrameHeader>

                            {topCoursesChartData.length > 0 ? (
                                <div className="w-full pt-3">
                                    <BarChart
                                        data={topCoursesChartData}
                                        xDataKey="code"
                                        orientation="horizontal"
                                        className="h-[220px] w-full"
                                        aspectRatio="16 / 8"
                                        margin={{ left: 65, right: 24, top: 12, bottom: 20 }}
                                    >
                                        <Grid vertical strokeDasharray="3 3" />
                                        <Bar dataKey="students" fill={chartCssVars.linePrimary} lineCap="round" />
                                        <BarYAxis />
                                        <ChartTooltip showDatePill={false} />
                                    </BarChart>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center px-6 py-10 text-center">
                                    <IconTile variant="soft" size="lg" className="text-muted-foreground mb-3">
                                        <GraduationCap className="size-5" />
                                    </IconTile>
                                    <p className="text-foreground text-sm font-semibold">No major enrolment yet</p>
                                    <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                                        No student has been mapped to a degree program for {currentPeriod.label}, so there is nothing to rank.
                                    </p>
                                </div>
                            )}
                        </FramePanel>
                    </Frame>

                    {/* Sub-grid: Academic Year Level (Vertical) + Gender Distribution Breakdown */}
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {/* Academic Year Level Distribution */}
                        <Frame variant="default" className="border-border/80 h-full shadow-2xs">
                            <FramePanel className="flex h-full flex-col justify-between p-4 sm:p-5">
                                <div>
                                    <FrameHeader className="border-border/60 border-b p-0 pb-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <IconTile variant="soft" size="xs" className="text-primary">
                                                    <Layers className="size-3.5" />
                                                </IconTile>
                                                <div>
                                                    <FrameTitle className="text-sm font-semibold">Year-Level Standing</FrameTitle>
                                                    <FrameDescription className="text-xs">Academic cohort progression</FrameDescription>
                                                </div>
                                            </div>
                                        </div>
                                    </FrameHeader>

                                    {yearLevelChartData.length > 0 ? (
                                        <div className="w-full pt-2">
                                            <BarChart
                                                data={yearLevelChartData}
                                                xDataKey="year_level"
                                                orientation="vertical"
                                                className="h-[180px] w-full"
                                                aspectRatio="16 / 9"
                                                margin={{ left: 40, right: 16, top: 16, bottom: 28 }}
                                            >
                                                <Grid horizontal strokeDasharray="3 3" />
                                                <Bar dataKey="students" fill="var(--chart-2)" lineCap="round" />
                                                <BarXAxis />
                                                <ChartTooltip showDatePill={false} />
                                            </BarChart>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center px-4 py-8 text-center">
                                            <p className="text-foreground text-xs font-semibold">No year-level data</p>
                                            <p className="text-muted-foreground mt-1 text-[11px]">
                                                Academic standing has not been recorded for this term.
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {yearLevels.length > 0 ? (
                                    <div className="border-border/60 text-muted-foreground mt-3 flex items-center justify-between border-t pt-2.5 text-xs">
                                        <span className="font-medium">Largest Standing</span>
                                        <span className="text-foreground font-semibold">
                                            {yearLevels[0]?.year_level} ({formatNumber(Number(yearLevels[0]?.count) || 0)})
                                        </span>
                                    </div>
                                ) : null}
                            </FramePanel>
                        </Frame>

                        {/* Gender Distribution Breakdown Bars */}
                        <Frame variant="default" className="border-border/80 h-full shadow-2xs">
                            <FramePanel className="flex h-full flex-col justify-between p-4 sm:p-5">
                                <div>
                                    <FrameHeader className="border-border/60 border-b p-0 pb-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <IconTile variant="soft" size="xs" className="text-primary">
                                                    <UserCheck className="size-3.5" />
                                                </IconTile>
                                                <div>
                                                    <FrameTitle className="text-sm font-semibold">Gender Balance</FrameTitle>
                                                    <FrameDescription className="text-xs">Demographic category breakdown</FrameDescription>
                                                </div>
                                            </div>
                                            <Badge variant="outline" size="sm" className="font-mono text-xs tabular-nums">
                                                {genderBreakdown.length} Categories
                                            </Badge>
                                        </div>
                                    </FrameHeader>

                                    {genderBreakdown.length > 0 ? (
                                        <div className="space-y-4 pt-4">
                                            {genderBreakdown.map((item, idx) => (
                                                <div key={item.gender} className="space-y-1.5">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <div className="text-foreground flex items-center gap-2 font-medium">
                                                            <span
                                                                className="size-2.5 rounded-full"
                                                                style={{
                                                                    backgroundColor: chartPalette[(idx + 2) % chartPalette.length],
                                                                }}
                                                            />
                                                            <span>{item.gender}</span>
                                                        </div>
                                                        <div className="text-muted-foreground flex items-center gap-1.5 font-mono tabular-nums">
                                                            <span className="text-foreground font-semibold">{formatNumber(item.count)}</span>
                                                            <span>({formatPercent(item.percentage)})</span>
                                                        </div>
                                                    </div>
                                                    <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                                        <div
                                                            className="h-full rounded-full transition-all duration-500"
                                                            style={{
                                                                width: `${Math.min(100, Math.max(0, item.percentage))}%`,
                                                                backgroundColor: chartPalette[(idx + 2) % chartPalette.length],
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center px-4 py-8 text-center">
                                            <p className="text-foreground text-xs font-semibold">No gender data</p>
                                            <p className="text-muted-foreground mt-1 text-[11px]">
                                                Gender has not been captured on student profiles for this term.
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {genderBreakdown.length > 0 ? (
                                    <div className="border-border/60 text-muted-foreground mt-4 flex items-center justify-between border-t pt-2.5 text-xs">
                                        <span className="font-medium">Parity Index</span>
                                        <span className="text-foreground font-mono font-semibold tabular-nums">{genderRatioText}</span>
                                    </div>
                                ) : null}
                            </FramePanel>
                        </Frame>
                    </div>
                </div>
            </div>

            {/* =========================================================
                4. RECENT REGISTRATIONS DIRECTORY TABLE (solution-users-1)
                ========================================================= */}
            <Frame variant="default" className="w-full shadow-2xs">
                <FramePanel className="overflow-hidden p-0">
                    <FrameHeader className="border-border/60 flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div className="flex items-center gap-2.5">
                            <IconTile variant="soft" size="xs" className="text-primary">
                                <Users className="size-3.5" />
                            </IconTile>
                            <div>
                                <FrameTitle className="text-base font-semibold">Recent Registrations Directory</FrameTitle>
                                <FrameDescription className="text-xs">
                                    Verified workspace directory with matriculation IDs, program paths, and status pills
                                </FrameDescription>
                            </div>
                        </div>

                        {/* Search and Classification Filters */}
                        <div className="flex flex-wrap items-center gap-2.5">
                            <div className="relative w-full sm:w-64">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                <Input
                                    placeholder="Search name, ID, or course..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="h-8 pr-8 pl-8 text-xs"
                                />
                                {searchQuery && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchQuery("")}
                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </div>

                            {/* Classification Filter Pills */}
                            <div className="border-border/60 bg-muted/40 hidden items-center gap-1 rounded-lg border p-1 text-xs md:flex">
                                <button
                                    type="button"
                                    onClick={() => setSelectedClassification("all")}
                                    className={cn(
                                        "rounded-md px-2.5 py-1 text-xs font-medium transition-all",
                                        selectedClassification === "all"
                                            ? "bg-background text-foreground font-semibold shadow-2xs"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    All Types
                                </button>
                                {classificationOptions.map((opt) => (
                                    <button
                                        key={opt}
                                        type="button"
                                        onClick={() => setSelectedClassification(opt)}
                                        className={cn(
                                            "rounded-md px-2.5 py-1 text-xs font-medium capitalize transition-all",
                                            selectedClassification.toLowerCase() === opt.toLowerCase()
                                                ? "bg-background text-foreground font-semibold shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        {opt}
                                    </button>
                                ))}
                            </div>

                            <Badge variant="outline" size="sm" className="font-mono text-xs tabular-nums">
                                {filteredStudents.length} of {recentStudents.length} records
                            </Badge>
                        </div>
                    </FrameHeader>

                    {filteredStudents.length === 0 ? (
                        <div className="p-8 text-center">
                            <div className="bg-muted mx-auto flex size-10 items-center justify-center rounded-full">
                                <Search className="text-muted-foreground size-5" />
                            </div>
                            <p className="text-foreground mt-3 text-sm font-medium">No matching student records</p>
                            <p className="text-muted-foreground mt-1 text-xs">Adjust your search term or clear the classification filter.</p>
                            <Button
                                variant="outline"
                                size="xs"
                                className="mt-4"
                                onClick={() => {
                                    setSearchQuery("");
                                    setSelectedClassification("all");
                                }}
                            >
                                Reset Filters
                            </Button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-border/70 bg-muted/40 hover:bg-muted/40 border-b">
                                        <TableHead className="text-muted-foreground py-3 pl-4 text-[11px] font-semibold tracking-wider uppercase">
                                            Student ID
                                        </TableHead>
                                        <TableHead className="text-muted-foreground py-3 text-[11px] font-semibold tracking-wider uppercase">
                                            Student Name
                                        </TableHead>
                                        <TableHead className="text-muted-foreground py-3 text-[11px] font-semibold tracking-wider uppercase">
                                            Classification
                                        </TableHead>
                                        <TableHead className="text-muted-foreground py-3 text-[11px] font-semibold tracking-wider uppercase">
                                            Degree Program
                                        </TableHead>
                                        <TableHead className="text-muted-foreground py-3 text-[11px] font-semibold tracking-wider uppercase">
                                            Status
                                        </TableHead>
                                        <TableHead className="text-muted-foreground py-3 text-[11px] font-semibold tracking-wider uppercase">
                                            Registration Date
                                        </TableHead>
                                        <TableHead className="text-muted-foreground py-3 pr-4 text-right text-[11px] font-semibold tracking-wider uppercase">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredStudents.map((student) => {
                                        const statusStr = (student.status || "enrolled").toLowerCase();
                                        const isEnrolled =
                                            statusStr.includes("enrolled") || statusStr.includes("active") || statusStr.includes("matriculated");
                                        const isApplicant =
                                            statusStr.includes("applicant") || statusStr.includes("pending") || statusStr.includes("review");
                                        const isGraduated = statusStr.includes("graduated") || statusStr.includes("alumni");

                                        return (
                                            <TableRow key={student.id} className="border-border/40 hover:bg-muted/30 border-b transition-colors">
                                                {/* Monospace Student ID Pill Badge */}
                                                <TableCell className="py-3 pl-4">
                                                    <span className="border-border/60 bg-muted/60 text-foreground/90 inline-block rounded border px-2 py-0.5 font-mono text-xs font-semibold tabular-nums">
                                                        {student.student_id || "PENDING"}
                                                    </span>
                                                </TableCell>

                                                {/* Student Name with Avatar Initials IconTile */}
                                                <TableCell className="py-3">
                                                    <div className="flex items-center gap-2.5">
                                                        <IconTile variant="soft" size="sm" className="text-primary font-mono text-xs font-semibold">
                                                            {getInitials(student.name)}
                                                        </IconTile>
                                                        <div className="min-w-0">
                                                            <div className="text-foreground truncate text-sm font-medium">{student.name}</div>
                                                            <div className="text-muted-foreground font-mono text-xs">
                                                                {student.student_id
                                                                    ? `${student.student_id.toLowerCase()}@student.edu`
                                                                    : "Awaiting ID assignment"}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                {/* Student Classification Type Pill */}
                                                <TableCell className="py-3">
                                                    <Badge variant="outline" size="sm" className="font-medium capitalize">
                                                        {student.type || "Freshman"}
                                                    </Badge>
                                                </TableCell>

                                                {/* Program / Course Label */}
                                                <TableCell className="py-3">
                                                    <span className="text-foreground text-xs font-medium">
                                                        {student.course || "General Education"}
                                                    </span>
                                                </TableCell>

                                                {/* Semantic Status Badge with Dot */}
                                                <TableCell className="py-3">
                                                    {isEnrolled && (
                                                        <Badge
                                                            variant="success-light"
                                                            size="sm"
                                                            radius="full"
                                                            className="gap-1.5 font-medium capitalize"
                                                        >
                                                            <span className="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                                                            <span>{student.status || "Enrolled"}</span>
                                                        </Badge>
                                                    )}
                                                    {isApplicant && (
                                                        <Badge
                                                            variant="warning-light"
                                                            size="sm"
                                                            radius="full"
                                                            className="gap-1.5 font-medium capitalize"
                                                        >
                                                            <span className="size-1.5 rounded-full bg-amber-500" />
                                                            <span>{student.status || "Applicant"}</span>
                                                        </Badge>
                                                    )}
                                                    {isGraduated && (
                                                        <Badge
                                                            variant="info-light"
                                                            size="sm"
                                                            radius="full"
                                                            className="gap-1.5 font-medium capitalize"
                                                        >
                                                            <span className="size-1.5 rounded-full bg-sky-500" />
                                                            <span>{student.status || "Graduated"}</span>
                                                        </Badge>
                                                    )}
                                                    {!isEnrolled && !isApplicant && !isGraduated && (
                                                        <Badge variant="secondary" size="sm" radius="full" className="gap-1.5 font-medium capitalize">
                                                            <span className="bg-muted-foreground size-1.5 rounded-full" />
                                                            <span>{student.status || "Inactive"}</span>
                                                        </Badge>
                                                    )}
                                                </TableCell>

                                                {/* Registration Timestamp */}
                                                <TableCell className="text-muted-foreground py-3 font-mono text-xs tabular-nums">
                                                    {formatDateTime(student.registered_at)}
                                                </TableCell>

                                                {/* View Profile Button */}
                                                <TableCell className="py-3 pr-4 text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="xs"
                                                        render={
                                                            <Link
                                                                href={
                                                                    student.id ? `/administrators/students/${student.id}` : "/administrators/students"
                                                                }
                                                            />
                                                        }
                                                    >
                                                        <span>View Profile</span>
                                                        <ArrowUpRight className="size-3" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </FramePanel>
            </Frame>
        </div>
    );
}
