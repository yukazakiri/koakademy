import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { index as enrollmentRecords } from "@/routes/administrators/enrollments";
import { Link } from "@inertiajs/react";
import { Activity, AlertTriangle, ArrowRight, ChartNoAxesCombined, CircleCheckBig, DatabaseZap, GraduationCap, Users } from "lucide-react";
import { useMemo } from "react";
import type { EnrollmentManagementProps } from "./types";

type EnrollmentAnalyticsSectionProps = {
    analytics: EnrollmentManagementProps["analytics"];
    applicantsCount: number;
    quality: {
        missing_department_count: number;
        missing_course_count: number;
    };
};

function percentage(value: number, total: number): number {
    return total > 0 ? Math.round((value / total) * 100) : 0;
}

export function EnrollmentAnalyticsSection({ analytics, applicantsCount, quality }: EnrollmentAnalyticsSectionProps) {
    const currentCount = analytics?.current_semester_count ?? 0;
    const previousCount = analytics?.previous_semester_count ?? 0;
    const activeCount = analytics?.active_count ?? 0;
    const trashedCount = analytics?.trashed_count ?? 0;
    const semesterDelta = currentCount - previousCount;
    const semesterGrowth = previousCount > 0 ? Math.round((semesterDelta / previousCount) * 100) : currentCount > 0 ? 100 : 0;

    const statusData = useMemo(
        () =>
            (analytics?.by_status ?? [])
                .map((item) => ({ name: item.status || "Unspecified", value: Number(item.count) }))
                .sort((left, right) => right.value - left.value),
        [analytics?.by_status],
    );

    const pendingCount = useMemo(
        () => statusData.filter((item) => /pending|review|processing|applicant/i.test(item.name)).reduce((sum, item) => sum + item.value, 0),
        [statusData],
    );
    const exceptionCount = pendingCount + trashedCount;

    const departmentData = useMemo(() => {
        const entries = (analytics?.by_department ?? []).map((item) => ({
            name: item.department?.trim() || "Unassigned",
            value: Number(item.count),
        }));
        const total = entries.reduce((sum, item) => sum + item.value, 0);

        return entries.sort((left, right) => right.value - left.value).map((item) => ({ ...item, percentage: percentage(item.value, total) }));
    }, [analytics?.by_department]);

    const yearLevelData = useMemo(() => {
        const entries = (analytics?.by_year_level ?? []).map((item) => ({
            name: item.year_level ? `Year ${item.year_level}` : "Unassigned",
            value: Number(item.count),
        }));
        const total = entries.reduce((sum, item) => sum + item.value, 0);

        return entries.sort((left, right) => right.value - left.value).map((item) => ({ ...item, percentage: percentage(item.value, total) }));
    }, [analytics?.by_year_level]);

    const recordsWithoutDepartment = quality.missing_department_count;
    const recordsWithoutCourse = quality.missing_course_count;
    const confirmedEnrollmentShare = percentage(currentCount, currentCount + applicantsCount);
    const funnelMax = Math.max(applicantsCount, currentCount, activeCount, 1);
    const comparisonMax = Math.max(currentCount, previousCount, 1);

    const qualityChecks = [
        {
            label: "Workflow exceptions",
            description: "Pending, review, processing, and deleted enrollment records.",
            value: exceptionCount,
            tone: exceptionCount > 0 ? "warning" : "success",
        },
        {
            label: "Missing department assignment",
            description: "Current-semester records without a resolved department.",
            value: recordsWithoutDepartment,
            tone: recordsWithoutDepartment > 0 ? "warning" : "success",
        },
        {
            label: "Missing course assignment",
            description: "Current-semester records without a resolved course.",
            value: recordsWithoutCourse,
            tone: recordsWithoutCourse > 0 ? "warning" : "success",
        },
    ] as const;

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    {
                        label: "Total enrolled",
                        value: currentCount,
                        detail: "Current semester",
                        icon: GraduationCap,
                        tone: "text-blue-600 dark:text-blue-400",
                    },
                    {
                        label: "Active enrollments",
                        value: activeCount,
                        detail: `${percentage(activeCount, Math.max(activeCount + trashedCount, 1))}% of records`,
                        icon: Activity,
                        tone: "text-emerald-600 dark:text-emerald-400",
                    },
                    {
                        label: "Pending / exceptions",
                        value: exceptionCount,
                        detail: exceptionCount > 0 ? "Requires registrar review" : "No exceptions detected",
                        icon: AlertTriangle,
                        tone: "text-amber-600 dark:text-amber-400",
                    },
                    {
                        label: "Semester growth",
                        value: `${semesterGrowth >= 0 ? "+" : ""}${semesterGrowth}%`,
                        detail: `${Math.abs(semesterDelta)} student${Math.abs(semesterDelta) === 1 ? "" : "s"} vs prior term`,
                        icon: ChartNoAxesCombined,
                        tone: semesterGrowth >= 0 ? "text-emerald-600 dark:text-emerald-400" : "text-destructive",
                    },
                ].map((metric) => (
                    <Card key={metric.label} size="sm" className="gap-0 py-0">
                        <CardContent className="flex items-center justify-between gap-4 p-4">
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">{metric.label}</p>
                                <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">{metric.value}</p>
                                <p className="text-muted-foreground mt-1 truncate text-xs">{metric.detail}</p>
                            </div>
                            <div className="bg-muted/60 flex size-10 shrink-0 items-center justify-center rounded-lg">
                                <metric.icon className={`size-5 ${metric.tone}`} aria-hidden="true" />
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Status flow & conversion</CardTitle>
                        <CardDescription>Application handoff through confirmed current-semester enrollment.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div
                            className="space-y-3"
                            role="img"
                            aria-label={`${applicantsCount} applicants, ${currentCount} enrolled, and ${activeCount} active enrollments`}
                        >
                            {[
                                {
                                    label: "Applicant records",
                                    value: applicantsCount,
                                    width: Math.max(percentage(applicantsCount, funnelMax), 4),
                                    tone: "bg-blue-500",
                                },
                                {
                                    label: "Confirmed enrollments",
                                    value: currentCount,
                                    width: Math.max(percentage(currentCount, funnelMax), 4),
                                    tone: "bg-primary",
                                },
                                {
                                    label: "Active records",
                                    value: activeCount,
                                    width: Math.max(percentage(activeCount, funnelMax), 4),
                                    tone: "bg-emerald-500",
                                },
                            ].map((item) => (
                                <div key={item.label} className="space-y-1.5">
                                    <div className="flex items-center justify-between gap-4 text-xs">
                                        <span className="font-medium">{item.label}</span>
                                        <span className="font-mono font-semibold tabular-nums">{item.value.toLocaleString()}</span>
                                    </div>
                                    <div className="bg-muted h-7 overflow-hidden rounded-md">
                                        <div className={`h-full rounded-md ${item.tone}`} style={{ width: `${Math.min(item.width, 100)}%` }} />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="bg-muted/35 flex items-center justify-between gap-4 rounded-lg border px-4 py-3">
                            <div>
                                <p className="text-sm font-semibold">Confirmed enrollment share</p>
                                <p className="text-muted-foreground text-xs">
                                    Share of enrolled records across the current enrollment and applicant scope.
                                </p>
                            </div>
                            <p className="text-xl font-semibold tabular-nums">{confirmedEnrollmentShare}%</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Semester comparison</CardTitle>
                        <CardDescription>Confirmed enrollment volume for the selected and preceding terms.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {[
                            { label: "Current semester", value: currentCount, tone: "bg-primary" },
                            { label: "Previous semester", value: previousCount, tone: "bg-muted-foreground/50" },
                        ].map((item) => (
                            <div key={item.label} className="space-y-2">
                                <div className="flex items-end justify-between gap-4">
                                    <span className="text-sm font-medium">{item.label}</span>
                                    <span className="font-mono text-lg font-semibold tabular-nums">{item.value.toLocaleString()}</span>
                                </div>
                                <div className="bg-muted h-3 overflow-hidden rounded-full">
                                    <div
                                        className={`h-full rounded-full transition-[width] ${item.tone}`}
                                        style={{ width: `${(item.value / comparisonMax) * 100}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                        <div className="grid grid-cols-2 gap-3 border-t pt-4">
                            <div>
                                <p className="text-muted-foreground text-xs">Net change</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    {semesterDelta >= 0 ? "+" : ""}
                                    {semesterDelta}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs">Academic-year total</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    {(analytics?.current_school_year_count ?? 0).toLocaleString()}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Department distribution</CardTitle>
                        <CardDescription>Confirmed enrollment by academic department for this semester.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {departmentData.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center text-sm">No department data is available for this period.</p>
                        ) : (
                            departmentData.map((department) => (
                                <div key={department.name} className="space-y-1.5">
                                    <div className="flex items-center justify-between gap-4 text-xs">
                                        <span className="font-medium">{department.name}</span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {department.value.toLocaleString()} · {department.percentage}%
                                        </span>
                                    </div>
                                    <Progress value={department.percentage} className="h-2" />
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Year-level composition</CardTitle>
                        <CardDescription>Current-semester enrollment by academic stage.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {yearLevelData.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center text-sm">No year-level data is available for this period.</p>
                        ) : (
                            yearLevelData.map((year) => (
                                <div key={year.name} className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                                    <div className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-md">
                                        <Users className="size-4" aria-hidden="true" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">{year.name}</p>
                                        <Progress value={year.percentage} className="mt-1.5 h-1.5" />
                                    </div>
                                    <div className="text-right">
                                        <p className="font-mono text-sm font-semibold tabular-nums">{year.value.toLocaleString()}</p>
                                        <p className="text-muted-foreground text-[11px]">{year.percentage}%</p>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader className="flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <DatabaseZap className="size-4" aria-hidden="true" /> Data quality & workflow review
                        </CardTitle>
                        <CardDescription>Measured exceptions that may require registrar follow-up.</CardDescription>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={enrollmentRecords.url()}>
                            View enrollment records
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent className="grid gap-3 lg:grid-cols-3">
                    {qualityChecks.map((check) => (
                        <div key={check.label} className="flex items-start gap-3 rounded-lg border p-3">
                            {check.tone === "success" ? (
                                <CircleCheckBig className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                            ) : (
                                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-semibold">{check.label}</p>
                                    <Badge variant={check.value > 0 ? "secondary" : "outline"}>{check.value}</Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">{check.description}</p>
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}
