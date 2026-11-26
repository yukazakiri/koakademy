import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    BookOpen,
    CalendarX2,
    CheckCircle2,
    ClipboardCheck,
    ExternalLink,
    GraduationCap,
    ShieldAlert,
    TrendingDown,
} from "lucide-react";

interface AtRiskAlert {
    id: number;
    class_id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    class_label: string;
    student_db_id: number | null;
    student_id: string;
    student_name: string;
    student_email: string | null;
    risk_level: "high" | "medium";
    risk_categories: Array<"attendance" | "academic">;
    absences_count: number;
    lates_count: number;
    grades: {
        prelim: number | null;
        midterm: number | null;
        final: number | null;
        average: number | null;
        outcome: string | null;
    };
    signals: string[];
    signal_count: number;
    links: {
        open_class: string;
        view_grades: string;
        take_attendance: string;
    };
}

interface ClassOption {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    label: string;
}

interface FacultyAtRiskAlertsProps {
    user: User;
    alerts: AtRiskAlert[];
    classes: ClassOption[];
    summary: {
        high_risk: number;
        medium_risk: number;
        attendance_risk: number;
        academic_risk: number;
        classes_monitored: number;
        total_alerts: number;
    };
    filters: {
        class_id: string;
        risk_level: string;
    };
    current_semester: string;
    current_school_year: string;
}

export default function FacultyAtRiskAlertsPage({
    user,
    alerts,
    classes,
    summary,
    filters,
    current_semester,
    current_school_year,
}: FacultyAtRiskAlertsProps) {
    const periodLabel = `Semester ${current_semester} • ${current_school_year}`;

    const applyFilter = (key: "class_id" | "risk_level", value: string) => {
        const nextFilters = {
            ...filters,
            [key]: value,
        };
        router.get(
            "/faculty/at-risk-alerts",
            {
                ...(nextFilters.class_id !== "all" ? { class_id: nextFilters.class_id } : {}),
                ...(nextFilters.risk_level !== "all" ? { risk_level: nextFilters.risk_level } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <FacultyLayout user={user}>
            <Head title="At-Risk Alerts" />

            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Early Warning System</p>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">At-Risk Student Alerts</h1>
                        <p className="text-muted-foreground text-sm">
                            Spot attendance drops and failing grades early across your active classes for {periodLabel}.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={filters.class_id} onValueChange={(value) => applyFilter("class_id", value ?? "all")}>
                            <SelectTrigger className="w-[210px]">
                                <SelectValue placeholder="All classes" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Monitored Classes</SelectItem>
                                {classes.map((cls) => (
                                    <SelectItem key={cls.id} value={String(cls.id)}>
                                        {cls.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={filters.risk_level} onValueChange={(value) => applyFilter("risk_level", value ?? "all")}>
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="All severities" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Risk Levels</SelectItem>
                                <SelectItem value="high">High Risk</SelectItem>
                                <SelectItem value="medium">Medium Risk</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </header>

                {/* Summary KPI Cards */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">High Risk</p>
                                <p className="mt-1 text-2xl font-bold text-rose-600 tabular-nums dark:text-rose-400">{summary.high_risk}</p>
                                <p className="text-muted-foreground text-xs">Immediate outreach</p>
                            </div>
                            <div className="flex size-10 items-center justify-center rounded-lg bg-rose-500/10">
                                <ShieldAlert className="size-5 text-rose-600 dark:text-rose-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">Attendance Risk</p>
                                <p className="mt-1 text-2xl font-bold text-amber-600 tabular-nums dark:text-amber-400">
                                    {summary.attendance_risk}
                                </p>
                                <p className="text-muted-foreground text-xs">3+ absences or frequent lates</p>
                            </div>
                            <div className="flex size-10 items-center justify-center rounded-lg bg-amber-500/10">
                                <CalendarX2 className="size-5 text-amber-600 dark:text-amber-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">Academic Risk</p>
                                <p className="mt-1 text-2xl font-bold text-orange-600 tabular-nums dark:text-orange-400">
                                    {summary.academic_risk}
                                </p>
                                <p className="text-muted-foreground text-xs">Below passing threshold</p>
                            </div>
                            <div className="flex size-10 items-center justify-center rounded-lg bg-orange-500/10">
                                <TrendingDown className="size-5 text-orange-600 dark:text-orange-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">Classes Monitored</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">{summary.classes_monitored}</p>
                                <p className="text-muted-foreground text-xs">Current term load</p>
                            </div>
                            <div className="bg-primary/10 flex size-10 items-center justify-center rounded-lg">
                                <BookOpen className="text-primary size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Alert Feed */}
                {alerts.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-14 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-emerald-500/10">
                                <CheckCircle2 className="size-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">
                                    {summary.total_alerts === 0 ? "All students are currently on track" : "No alerts match the selected filters"}
                                </h2>
                                <p className="text-muted-foreground max-w-md text-sm">
                                    {summary.total_alerts === 0
                                        ? "None of your enrolled students currently meet early-warning thresholds for absences or failing grades."
                                        : "Try switching back to all monitored classes or all risk levels."}
                                </p>
                            </div>
                            {(filters.class_id !== "all" || filters.risk_level !== "all") && (
                                <Button variant="outline" size="sm" onClick={() => router.get("/faculty/at-risk-alerts")}>
                                    Reset Filters
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {alerts.map((alert) => {
                            const isHigh = alert.risk_level === "high";
                            return (
                                <Card
                                    key={`${alert.class_id}-${alert.id}`}
                                    className={isHigh ? "border-rose-500/40" : "border-amber-500/40"}
                                >
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            isHigh
                                                                ? "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                                                : "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                        }
                                                    >
                                                        <AlertTriangle className="mr-1 size-3" />
                                                        {isHigh ? "High Risk" : "Medium Risk"}
                                                    </Badge>
                                                    <Badge variant="secondary">{alert.class_label}</Badge>
                                                </div>
                                                <CardTitle className="truncate text-base">{alert.student_name}</CardTitle>
                                                <CardDescription className="truncate text-xs">
                                                    {alert.student_id}
                                                    {alert.student_email ? ` · ${alert.student_email}` : ""} · {alert.subject_title}
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex flex-wrap gap-1.5">
                                            {alert.signals.map((signal, idx) => (
                                                <Badge key={idx} variant="outline" className="text-xs font-normal">
                                                    {signal}
                                                </Badge>
                                            ))}
                                        </div>

                                        <div className="bg-muted/30 grid grid-cols-4 gap-2 rounded-lg border p-2.5 text-center text-xs">
                                            <div>
                                                <div className="text-muted-foreground">Absences</div>
                                                <div className="font-mono font-semibold">{alert.absences_count}</div>
                                            </div>
                                            <div>
                                                <div className="text-muted-foreground">Lates</div>
                                                <div className="font-mono font-semibold">{alert.lates_count}</div>
                                            </div>
                                            <div>
                                                <div className="text-muted-foreground">Midterm</div>
                                                <div className="font-mono font-semibold">
                                                    {alert.grades.midterm !== null ? alert.grades.midterm.toFixed(2) : "—"}
                                                </div>
                                            </div>
                                            <div>
                                                <div className="text-muted-foreground">Average</div>
                                                <div className="font-mono font-semibold">
                                                    {alert.grades.average !== null ? alert.grades.average.toFixed(2) : "—"}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2 pt-1">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                render={<Link href={alert.links.open_class} />}
                                                nativeButton={false}
                                                className="gap-1.5"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Open Class
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                render={<Link href={alert.links.view_grades} />}
                                                nativeButton={false}
                                                className="gap-1.5"
                                            >
                                                <GraduationCap className="size-3.5" />
                                                View Grades
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                render={<Link href={alert.links.take_attendance} />}
                                                nativeButton={false}
                                                className="gap-1.5"
                                            >
                                                <ClipboardCheck className="size-3.5" />
                                                Take Attendance
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </main>
        </FacultyLayout>
    );
}
