import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { useGradingConfig } from "@/hooks/use-grading-config";
import { formatGwa, gradeScaleLabel, gwaToneClass, isPassingGrade, type GradingConfig, type GwaResult } from "@/lib/gwa";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { motion, useReducedMotion } from "framer-motion";
import { ArrowRight, BookOpen, CheckCircle2, CircleDashed, Clock, GraduationCap, TrendingUp, Trophy } from "lucide-react";
import { useMemo } from "react";
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

export interface GradeInfo {
    prelim: number | null;
    midterm: number | null;
    finals: number | null;
    average: number | null;
}

export interface GradesPanelClass {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    faculty_name: string;
    units?: number;
    grades: GradeInfo;
}

type SubjectStatus = "awaiting" | "partial" | "complete";

const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

const classAccents = ["bg-blue-500", "bg-emerald-500", "bg-amber-500", "bg-rose-500", "bg-violet-500", "bg-cyan-500"];

const PERIODS = [
    { key: "prelim" as const, label: "Prelim" },
    { key: "midterm" as const, label: "Midterm" },
    { key: "finals" as const, label: "Final" },
];

function formatGradeValue(grade: number | null | undefined, config: GradingConfig): string {
    if (grade === null || grade === undefined) {
        return "—";
    }

    return grade.toFixed(Math.min(config.decimal_places, 2));
}

function getSubjectStatus(grades: GradeInfo): SubjectStatus {
    const periods = [grades.prelim, grades.midterm, grades.finals];
    const postedPeriods = periods.filter((value) => value !== null && value !== undefined).length;

    if (grades.average !== null && grades.average !== undefined) {
        return "complete";
    }

    if (postedPeriods === 3) {
        return "complete";
    }

    if (postedPeriods > 0) {
        return "partial";
    }

    return "awaiting";
}

function hasAnyPostedGrade(grades: GradeInfo): boolean {
    return grades.average !== null || grades.prelim !== null || grades.midterm !== null || grades.finals !== null;
}

function gradeToneClass(grade: number | null, config: GradingConfig): string {
    if (grade === null || grade === undefined) {
        return "text-muted-foreground";
    }

    return isPassingGrade(grade, config) ? "text-emerald-500" : "text-rose-500";
}

function gradeRemark(grade: number | null, config: GradingConfig): string | null {
    if (grade === null || grade === undefined) {
        return null;
    }

    if (!isPassingGrade(grade, config)) {
        return "Failing";
    }

    return "Passing";
}

function StatusBadge({ status }: { status: SubjectStatus }) {
    if (status === "complete") {
        return (
            <Badge variant="outline" className="gap-1 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <CheckCircle2 className="h-3 w-3" />
                Complete
            </Badge>
        );
    }

    if (status === "partial") {
        return (
            <Badge variant="outline" className="gap-1 border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <Clock className="h-3 w-3" />
                Partial
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="text-muted-foreground border-border/60 bg-muted/40 gap-1">
            <CircleDashed className="h-3 w-3" />
            Awaiting
        </Badge>
    );
}

function PeriodTracker({ grades, config }: { grades: GradeInfo; config: GradingConfig }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            {PERIODS.map((period) => {
                const value = grades[period.key];
                const posted = value !== null && value !== undefined;

                return (
                    <div
                        key={period.key}
                        className={cn(
                            "rounded-md border px-2 py-2 text-center transition-colors",
                            posted ? "border-primary/20 bg-primary/5" : "border-border/50 bg-muted/35 border-dashed",
                        )}
                    >
                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">{period.label}</p>
                        <p
                            className={cn(
                                "mt-0.5 font-mono text-sm font-semibold tabular-nums",
                                posted ? gradeToneClass(value, config) : "text-muted-foreground/70",
                            )}
                        >
                            {formatGradeValue(value, config)}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}

function SubjectGradeCard({
    classItem,
    index,
    config,
    reduceMotion,
}: {
    classItem: GradesPanelClass;
    index: number;
    config: GradingConfig;
    reduceMotion: boolean;
}) {
    const status = getSubjectStatus(classItem.grades);
    const average = classItem.grades.average;
    const remark = gradeRemark(average, config);
    const accent = classAccents[index % classAccents.length];

    return (
        <motion.div
            initial={reduceMotion ? false : { opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.22, delay: reduceMotion ? 0 : Math.min(index * 0.045, 0.28), ease: [0.23, 1, 0.32, 1] }}
        >
            <Card className={cn(dashboardCardClass, "overflow-hidden")}>
                <CardContent className="space-y-3 p-4">
                    <div className="flex gap-3">
                        <div className={cn(accent, "mt-1 h-11 w-1 shrink-0 rounded-full")} />
                        <div className="min-w-0 flex-1">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline" className="font-mono text-[11px]">
                                            {classItem.subject_code}
                                        </Badge>
                                        <span className="text-muted-foreground text-xs">Section {classItem.section}</span>
                                        {classItem.units !== undefined && classItem.units > 0 && (
                                            <span className="text-muted-foreground text-xs">{classItem.units}u</span>
                                        )}
                                    </div>
                                    <h3 className="text-foreground text-sm leading-snug font-semibold text-balance">{classItem.subject_title}</h3>
                                    <p className="text-muted-foreground truncate text-xs">{classItem.faculty_name}</p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1.5">
                                    <StatusBadge status={status} />
                                    <div className="text-right">
                                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Average</p>
                                        <p className={cn("font-mono text-xl font-bold tracking-tight tabular-nums", gradeToneClass(average, config))}>
                                            {formatGradeValue(average, config)}
                                        </p>
                                        {remark && <p className="text-muted-foreground mt-0.5 text-[10px] font-medium">{remark}</p>}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <PeriodTracker grades={classItem.grades} config={config} />
                </CardContent>
            </Card>
        </motion.div>
    );
}

function GradeComparisonChart({ classes, config }: { classes: GradesPanelClass[]; config: GradingConfig }) {
    const chartData = useMemo(() => {
        return classes
            .filter((classItem) => classItem.grades.average !== null)
            .map((classItem) => {
                const grade = classItem.grades.average as number;
                const performance = config.direction === "lower_is_better" ? Math.max(0, config.numeric_max - grade) : grade;

                return {
                    code: classItem.subject_code,
                    title: classItem.subject_title,
                    grade,
                    performance,
                    passing: isPassingGrade(grade, config),
                };
            });
    }, [classes, config]);

    if (chartData.length === 0) {
        return null;
    }

    const maxPerformance = config.direction === "lower_is_better" ? config.numeric_max - config.numeric_min : config.numeric_max;

    return (
        <Card className={dashboardPanelClass}>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <TrendingUp className="text-primary h-4 w-4" />
                    Grade comparison
                </CardTitle>
                <p className="text-muted-foreground text-xs">
                    {config.direction === "lower_is_better" ? "Longer bars mean stronger grades." : "Longer bars mean higher scores."}
                </p>
            </CardHeader>
            <CardContent>
                <div style={{ height: Math.max(160, chartData.length * 44) }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chartData} layout="vertical" margin={{ top: 4, right: 16, left: 4, bottom: 4 }}>
                            <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="hsl(var(--border))" />
                            <XAxis
                                type="number"
                                domain={[0, maxPerformance]}
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 11 }}
                                hide={false}
                            />
                            <YAxis
                                type="category"
                                dataKey="code"
                                width={72}
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 11 }}
                            />
                            <Tooltip
                                cursor={{ fill: "hsl(var(--muted) / 0.4)" }}
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) {
                                        return null;
                                    }

                                    const data = payload[0].payload as (typeof chartData)[number];

                                    return (
                                        <div className="bg-popover rounded-lg border p-3 text-sm shadow-lg">
                                            <p className="font-semibold">{data.title}</p>
                                            <p className="text-muted-foreground mt-0.5 font-mono text-xs">{data.code}</p>
                                            <p className="mt-2 text-xs">
                                                Average:{" "}
                                                <span
                                                    className={cn(
                                                        "font-mono font-semibold tabular-nums",
                                                        data.passing ? "text-emerald-500" : "text-rose-500",
                                                    )}
                                                >
                                                    {formatGradeValue(data.grade, config)}
                                                </span>
                                            </p>
                                        </div>
                                    );
                                }}
                            />
                            <Bar dataKey="performance" radius={[0, 6, 6, 0]} barSize={18}>
                                {chartData.map((entry) => (
                                    <Cell key={entry.code} fill={entry.passing ? "hsl(160 60% 40%)" : "hsl(0 70% 50%)"} fillOpacity={0.85} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}

function TermSnapshot({ gwaResult, classes, config }: { gwaResult: GwaResult; classes: GradesPanelClass[]; config: GradingConfig }) {
    const total = classes.length;
    const withAnyGrade = classes.filter((classItem) => hasAnyPostedGrade(classItem.grades)).length;
    const withAverage = classes.filter((classItem) => classItem.grades.average !== null).length;
    const progress = total > 0 ? (withAnyGrade / total) * 100 : 0;
    const gwaLabel = formatGwa(gwaResult, config);
    const scaleHint = gradeScaleLabel(gwaResult.scale, config);
    const allAwaiting = total > 0 && withAnyGrade === 0;

    let headline: string;
    let detail: string;

    if (total === 0) {
        headline = "No subjects this term";
        detail = "Once enrollment is finalized, your grades will show up here.";
    } else if (allAwaiting) {
        headline = "Awaiting grades";
        detail = "Faculty have not posted any grades yet. Check back after prelims.";
    } else if (withAverage < total) {
        headline = "Grades in progress";
        detail = `${withAnyGrade} of ${total} subjects have grades posted. GWA updates as more averages arrive.`;
    } else {
        headline = "Term grades posted";
        detail = `All ${total} subjects have an average. Review each subject below for period breakdowns.`;
    }

    return (
        <Card className={cn(dashboardPanelClass, "overflow-hidden")}>
            <CardContent className="p-4 sm:p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Term snapshot</p>
                        <h2 className="text-foreground text-lg font-semibold tracking-tight text-balance">{headline}</h2>
                        <p className="text-muted-foreground max-w-md text-sm leading-relaxed">{detail}</p>
                    </div>

                    <div className="border-border/60 bg-background/45 flex min-w-[140px] shrink-0 flex-col items-start gap-1 rounded-xl border px-4 py-3 sm:items-end sm:text-right">
                        <div className="flex items-center gap-1.5">
                            <Trophy className="h-3.5 w-3.5 text-amber-500" />
                            <span className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">GWA</span>
                        </div>
                        <p className={cn("font-mono text-3xl font-bold tracking-tight tabular-nums", gwaToneClass(gwaResult, config))}>{gwaLabel}</p>
                        <p className="text-muted-foreground text-[11px]">
                            {withAverage > 0 ? `${withAverage}/${total} averages${scaleHint ? ` · ${scaleHint}` : ""}` : "No averages yet"}
                        </p>
                    </div>
                </div>

                <div className="mt-4 space-y-1.5">
                    <div className="flex items-center justify-between text-xs">
                        <span className="text-muted-foreground">Posted progress</span>
                        <span className="font-medium tabular-nums">
                            {withAnyGrade}/{total} subjects
                        </span>
                    </div>
                    <Progress value={progress} className="h-2" />
                </div>

                {allAwaiting && (
                    <div className="border-border/50 bg-muted/30 mt-4 flex items-start gap-3 rounded-lg border border-dashed px-3 py-3">
                        <div className="bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <GraduationCap className="h-4 w-4" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium">Nothing to worry about yet</p>
                            <p className="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                                Your subjects are enrolled. Grades appear here as soon as faculty post them for prelim, midterm, or finals.
                            </p>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ScaleLegend({ scale }: { scale: GradeScale | null }) {
    if (scale === "percent") {
        return (
            <p className="text-muted-foreground text-center text-[11px] leading-relaxed">
                Percent scale · higher is better · <span className="text-foreground/80 font-medium">75+</span> is typically passing
            </p>
        );
    }

    // Default point-scale tip (most PH schools)
    return (
        <p className="text-muted-foreground text-center text-[11px] leading-relaxed">
            <span className="text-foreground/80 font-medium">1.0</span> excellent · <span className="text-foreground/80 font-medium">3.0</span>{" "}
            passing · <span className="text-foreground/80 font-medium">5.0</span> failing
        </p>
    );
}

export function GradesPanel({ classes, gwaResult }: { classes: GradesPanelClass[]; gwaResult: GwaResult }) {
    const config = useGradingConfig();
    const reduceMotion = useReducedMotion() ?? false;
    const hasChartData = classes.some((classItem) => classItem.grades.average !== null);

    if (classes.length === 0) {
        return (
            <Card className={cn(dashboardPanelClass, "border-dashed")}>
                <CardContent className="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div className="bg-muted/60 flex h-12 w-12 items-center justify-center rounded-xl">
                        <BookOpen className="text-muted-foreground h-6 w-6" />
                    </div>
                    <h3 className="mt-4 text-base font-semibold text-balance">No enrolled subjects</h3>
                    <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-relaxed">
                        Once enrollment is finalized, your grades will appear here by subject and period.
                    </p>
                    <Button asChild variant="secondary" size="sm" className="mt-5 active:scale-[0.96]">
                        <Link href="/student/classes">
                            View academic record
                            <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            <TermSnapshot gwaResult={gwaResult} classes={classes} config={config} />

            <div className="flex items-end justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Your subjects</p>
                    <h2 className="mt-0.5 text-lg font-semibold">Period breakdown</h2>
                </div>
                <Button asChild variant="ghost" size="sm" className="text-muted-foreground h-8 gap-1 px-2 active:scale-[0.96]">
                    <Link href="/student/classes">
                        Full record
                        <ArrowRight className="h-3.5 w-3.5" />
                    </Link>
                </Button>
            </div>

            <div className="grid gap-3">
                {classes.map((classItem, index) => (
                    <SubjectGradeCard key={classItem.id} classItem={classItem} index={index} config={config} reduceMotion={reduceMotion} />
                ))}
            </div>

            {hasChartData && <GradeComparisonChart classes={classes} config={config} />}

            <ScaleLegend scale={gwaResult.scale} />
        </div>
    );
}
