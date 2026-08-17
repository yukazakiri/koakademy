import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { SemesterSelector, type SemesterSelectorProps } from "@/components/semester-selector";
import { Head } from "@inertiajs/react";
import {
    Activity, AlertTriangle, ArrowDown, ArrowUp, Calendar, ChartNoAxesCombined,
    CircleCheckBig, DatabaseZap, FileSpreadsheet, GraduationCap, RefreshCw,
    School, TrendingUp, Users, UserCheck, UsersRound,
} from "lucide-react";
import { useMemo } from "react";

type AnalyticsItem = { [key: string]: unknown; count?: number };
type TrendItem = { date: string; count: number };
type CourseItem = { course_code: string; course_title: string; count: number };

type RegistrarAnalyticsProps = {
    user: { name: string; email: string; avatar: string | null; role: string };
    analytics: {
        current_semester_count: number; current_school_year_count: number;
        previous_semester_count: number; total_all_time_enrollments: number;
        by_department: AnalyticsItem[]; by_year_level: AnalyticsItem[];
        by_student_type: AnalyticsItem[]; by_gender: AnalyticsItem[];
        by_course: CourseItem[]; by_status: AnalyticsItem[];
        by_submission_channel: AnalyticsItem[]; trashed_count: number;
        active_count: number; daily_trend: TrendItem[];
    };
    applicantsCount: number; total_students: number;
    total_college_students: number; total_shs_students: number;
    quality: {
        missing_department_count: number; missing_course_count: number;
        missing_student_record_count: number; without_gender_count: number;
    };
    filters: SemesterSelectorProps; generatedAt: string;
};

function pct(value: number, total: number): number {
    return total > 0 ? Math.round((value / total) * 100) : 0;
}

function formatDelta(n: number): string {
    if (n > 0) return `+${n.toLocaleString()}`;
    return n.toLocaleString();
}

export default function RegistrarAnalytics({
    user, analytics, applicantsCount, total_students,
    total_college_students, total_shs_students, quality, filters, generatedAt,
}: RegistrarAnalyticsProps) {
    const generatedLabel = new Intl.DateTimeFormat(undefined, {
        dateStyle: "medium", timeStyle: "short",
    }).format(new Date(generatedAt));

    const currentCount = analytics?.current_semester_count ?? 0;
    const previousCount = analytics?.previous_semester_count ?? 0;
    const activeCount = analytics?.active_count ?? 0;
    const trashedCount = analytics?.trashed_count ?? 0;
    const schoolYearCount = analytics?.current_school_year_count ?? 0;
    const allTimeCount = analytics?.total_all_time_enrollments ?? 0;
    const semesterDelta = currentCount - previousCount;
    const semesterGrowthPct = previousCount > 0
        ? Math.round((semesterDelta / previousCount) * 100)
        : currentCount > 0 ? 100 : 0;

    const statusData = useMemo(() =>
        (analytics?.by_status ?? [])
            .map((item) => ({
                name: String(item.status ?? "Unspecified"),
                value: Number(item.count ?? 0),
            }))
            .sort((a, b) => b.value - a.value),
    [analytics?.by_status]);

    const departmentData = useMemo(() => {
        const e = (analytics?.by_department ?? []).map((item) => ({
            name: String(item.department ?? "").trim() || "Unassigned",
            value: Number(item.count ?? 0),
        }));
        const t = e.reduce((s, i) => s + i.value, 0);
        return e.sort((a, b) => b.value - a.value).map((item) => ({
            ...item, percentage: pct(item.value, t),
        }));
    }, [analytics?.by_department]);

    const yearLevelData = useMemo(() => {
        const e = (analytics?.by_year_level ?? []).map((item) => ({
            name: item.year_level ? `Year ${item.year_level}` : "Unassigned",
            value: Number(item.count ?? 0),
        }));
        const t = e.reduce((s, i) => s + i.value, 0);
        return e.sort((a, b) => a.value - b.value).map((item) => ({
            ...item, percentage: pct(item.value, t),
        }));
    }, [analytics?.by_year_level]);

    const genderData = useMemo(() => {
        const e = (analytics?.by_gender ?? []).map((item) => ({
            name: String(item.gender ?? "").trim() || "Unspecified",
            value: Number(item.count ?? 0),
        }));
        const t = e.reduce((s, i) => s + i.value, 0);
        return e.sort((a, b) => b.value - a.value).map((item) => ({
            ...item, percentage: pct(item.value, t),
        }));
    }, [analytics?.by_gender]);

    const typeData = useMemo(() => {
        const e = (analytics?.by_student_type ?? []).map((item) => {
            const r = String(item.student_type ?? "unknown");
            const l = r === "college" ? "College" : r === "shs" ? "SHS"
                : r === "tesda" ? "TESDA" : r === "dhrt" ? "DHRT" : r;
            return { name: l, value: Number(item.count ?? 0) };
        });
        const t = e.reduce((s, i) => s + i.value, 0);
        return e.sort((a, b) => b.value - a.value).map((item) => ({
            ...item, percentage: pct(item.value, t),
        }));
    }, [analytics?.by_student_type]);

    const topCourses = useMemo(() =>
        (analytics?.by_course ?? []).slice(0, 10).map((item, i) => ({
            rank: i + 1, code: item.course_code ?? "N/A",
            title: item.course_title ?? "", value: Number(item.count ?? 0),
        })),
    [analytics?.by_course]);

    const dailyTrendMax = useMemo(
        () => Math.max(1, ...(analytics?.daily_trend ?? []).map((d) => Number(d.count ?? 0))),
        [analytics?.daily_trend]);

    const statusTotal = statusData.reduce((s, i) => s + i.value, 0);
    const qualityIssues = [
        { label: "Missing Department", value: quality.missing_department_count,
          tone: quality.missing_department_count > 0 ? ("warn" as const) : ("ok" as const) },
        { label: "Missing Course", value: quality.missing_course_count,
          tone: quality.missing_course_count > 0 ? ("warn" as const) : ("ok" as const) },
        { label: "Missing Student Record", value: quality.missing_student_record_count,
          tone: quality.missing_student_record_count > 0 ? ("warn" as const) : ("ok" as const) },
        { label: "Without Gender Data", value: quality.without_gender_count,
          tone: quality.without_gender_count > 0 ? ("warn" as const) : ("ok" as const) },
    ];

    return (
        <AdminLayout user={user} title="Registrar Analytics">
            <Head title="Administrators • Registrar Analytics" />
            <div className="space-y-6 pb-12">
                {/* Header */}
                <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Registrar Insights</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Analytics &amp; Reporting</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                            Comprehensive enrollment metrics, demographics, and data-quality signals for administrative planning and reporting.
                        </p>
                    </div>
                    <div className="flex flex-col items-start gap-2 sm:items-end">
                        <SemesterSelector {...filters} />
                        <div className="flex items-center gap-3">
                            <p className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                                <RefreshCw className="size-3" aria-hidden="true" />
                                Generated {generatedLabel}
                            </p>
                            <Button size="sm" asChild>
                                <a href="/administrators/registrar/analytics/export" target="_blank" rel="noopener">
                                    <FileSpreadsheet className="size-4" /> Export Excel
                                </a>
                            </Button>
                        </div>
                    </div>
                </header>

                {/* KPI Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Current Semester</CardTitle>
                            <UsersRound className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{currentCount.toLocaleString()}</div>
                            <p className="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                                {semesterDelta >= 0 ? (
                                    <ArrowUp className="size-3 text-emerald-600" />
                                ) : (
                                    <ArrowDown className="size-3 text-red-500" />
                                )}
                                <span className={semesterDelta >= 0 ? "text-emerald-600" : "text-red-500"}>
                                    {formatDelta(semesterDelta)} ({semesterGrowthPct}%)
                                </span>
                                <span className="ml-1">vs prev semester</span>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">School Year Total</CardTitle>
                            <School className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{schoolYearCount.toLocaleString()}</div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Active {activeCount.toLocaleString()} · Trashed {trashedCount.toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Applicants</CardTitle>
                            <UserCheck className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{applicantsCount.toLocaleString()}</div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {pct(currentCount, currentCount + applicantsCount)}% enrolled
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">All-Time Records</CardTitle>
                            <DatabaseZap className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{allTimeCount.toLocaleString()}</div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {total_students.toLocaleString()} students · {total_college_students} COL · {total_shs_students} SHS
                            </p>
                        </CardContent>
                    </Card>
                </div>
                {/* Dept + Year Level */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <School className="size-4" /> Enrollment by Department
                            </CardTitle>
                            <CardDescription>Distribution across academic departments.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {departmentData.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No department data available.</p>
                            ) : (
                                <div className="space-y-3">
                                    {departmentData.map((dept) => (
                                        <div key={dept.name} className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                                            <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-md text-xs font-bold">
                                                {dept.name.slice(0, 3)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-medium truncate">{dept.name}</p>
                                                    <span className="font-mono text-sm font-semibold tabular-nums shrink-0">
                                                        {dept.value.toLocaleString()}
                                                    </span>
                                                </div>
                                                <Progress value={dept.percentage} className="mt-1.5 h-1.5" />
                                                <p className="text-muted-foreground text-xs">{dept.percentage}%</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <GraduationCap className="size-4" /> Enrollment by Year Level
                            </CardTitle>
                            <CardDescription>Distribution across academic years.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {yearLevelData.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No year-level data available.</p>
                            ) : (
                                <div className="space-y-3">
                                    {yearLevelData.map((year) => (
                                        <div key={year.name} className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                                            <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-md">
                                                <GraduationCap className="size-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-medium">{year.name}</p>
                                                    <span className="font-mono text-sm font-semibold tabular-nums shrink-0">
                                                        {year.value.toLocaleString()}
                                                    </span>
                                                </div>
                                                <Progress value={year.percentage} className="mt-1.5 h-1.5" />
                                                <p className="text-muted-foreground text-xs">{year.percentage}%</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
                {/* Gender + Student Type */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-4" /> Gender Distribution
                            </CardTitle>
                            <CardDescription>Enrollment breakdown by gender.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {genderData.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No gender data available.</p>
                            ) : (
                                <div className="space-y-3">
                                    {genderData.map((g) => (
                                        <div key={g.name} className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                                            <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-md text-xs font-bold uppercase">
                                                {g.name.slice(0, 1)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-medium">{g.name}</p>
                                                    <span className="font-mono text-sm font-semibold tabular-nums shrink-0">
                                                        {g.value.toLocaleString()}
                                                    </span>
                                                </div>
                                                <Progress value={g.percentage} className="mt-1.5 h-1.5" />
                                                <p className="text-muted-foreground text-xs">{g.percentage}%</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ChartNoAxesCombined className="size-4" /> Student Type
                            </CardTitle>
                            <CardDescription>College, SHS, TESDA, and DHRT breakdown.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {typeData.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No type data available.</p>
                            ) : (
                                <div className="space-y-3">
                                    {typeData.map((t) => (
                                        <div key={t.name} className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                                            <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-md text-xs font-bold">
                                                {t.name.slice(0, 3)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-medium">{t.name}</p>
                                                    <span className="font-mono text-sm font-semibold tabular-nums shrink-0">
                                                        {t.value.toLocaleString()}
                                                    </span>
                                                </div>
                                                <Progress value={t.percentage} className="mt-1.5 h-1.5" />
                                                <p className="text-muted-foreground text-xs">{t.percentage}%</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
                {/* Status + Top Courses */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Activity className="size-4" /> Enrollment by Status
                            </CardTitle>
                            <CardDescription>Workflow status breakdown for the current semester.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {statusData.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No status data available.</p>
                            ) : (
                                <div className="space-y-3">
                                    {statusData.map((s) => {
                                        const sp = pct(s.value, statusTotal);
                                        return (
                                            <div key={s.name} className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                                                <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-md text-[10px] font-bold uppercase">
                                                    {s.name.slice(0, 3)}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="text-sm font-medium">{s.name}</p>
                                                        <span className="font-mono text-sm font-semibold tabular-nums shrink-0">
                                                            {s.value.toLocaleString()}
                                                        </span>
                                                    </div>
                                                    <Progress value={sp} className="mt-1.5 h-1.5" />
                                                    <p className="text-muted-foreground text-xs">{sp}%</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="size-4" /> Top Courses
                            </CardTitle>
                            <CardDescription>Most popular courses by enrollment count.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {topCourses.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No course data available.</p>
                            ) : (
                                <div className="space-y-2">
                                    {topCourses.map((c) => (
                                        <div key={c.code} className="flex items-center gap-3 rounded-lg border px-3 py-2">
                                            <Badge variant="secondary" className="shrink-0 font-mono text-xs">
                                                #{c.rank}
                                            </Badge>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium truncate">{c.code}</p>
                                                <p className="text-muted-foreground truncate text-[11px]">{c.title}</p>
                                            </div>
                                            <span className="font-mono text-sm font-semibold tabular-nums shrink-0">
                                                {c.value.toLocaleString()}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
                {/* Daily Trend */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="size-4" /> Daily Enrollment Trend
                        </CardTitle>
                        <CardDescription>New enrollments per day for the current semester (last 30 days).</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {(analytics?.daily_trend ?? []).length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center text-sm">No daily trend data available.</p>
                        ) : (
                            <div className="max-h-72 space-y-1.5 overflow-y-auto">
                                {(analytics?.daily_trend ?? []).slice(-30).map((day) => (
                                    <div key={day.date} className="flex items-center gap-3">
                                        <span className="text-muted-foreground w-24 shrink-0 text-xs tabular-nums">
                                            {day.date}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <Progress value={pct(Number(day.count ?? 0), dailyTrendMax)} className="h-2" />
                                        </div>
                                        <span className="font-mono text-xs font-semibold tabular-nums w-12 shrink-0 text-right">
                                            {Number(day.count ?? 0).toLocaleString()}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Data Quality */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <DatabaseZap className="size-4" /> Data Quality &amp; Record Integrity
                        </CardTitle>
                        <CardDescription>Exceptions requiring registrar review and cleanup.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {qualityIssues.map((check) => (
                            <div key={check.label} className="flex items-start gap-3 rounded-lg border p-3">
                                {check.tone === "ok" ? (
                                    <CircleCheckBig className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                ) : (
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                )}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">{check.label}</p>
                                        <Badge variant={check.value > 0 ? "secondary" : "outline"}>{check.value}</Badge>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        {check.value > 0 ? "Needs attention" : "No issues found"}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
