import AdminLayout from "@/components/administrators/admin-layout";
import { RegistrarStudentProfileImportDialog } from "@/components/administrators/registrar-student-profile-import-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { index as analyticsRoute, exportMethod } from "@/routes/administrators/registrar/analytics";
import { Head, router } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    CalendarRange,
    ChartNoAxesCombined,
    CircleGauge,
    FileSpreadsheet,
    Filter,
    GraduationCap,
    RefreshCw,
    RotateCcw,
    Sparkles,
    Users,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, XAxis, YAxis } from "recharts";

type Item = Record<string, string | number | null | undefined> & { count?: number };
type Option = { value: string | number; label: string; department_id?: number | null };
type FilterValues = Record<string, string | number | null>;
type MatrixRow = Record<string, string | number | null | undefined>;
type ChartDatum = { name: string; count: number };
type CourseTone = { badge: string; dot: string; stripe: string };
type Props = {
    user: { name: string; email: string; avatar: string | null; role: string };
    canImportStudentProfiles: boolean;
    analytics: {
        current_semester_count: number;
        current_school_year_count: number;
        previous_semester_count: number;
        active_count: number;
        trashed_count: number;
        by_department: Item[];
        by_program: Item[];
        by_year_level: Item[];
        by_student_type: Item[];
        by_gender: Item[];
        by_status: Item[];
        daily_trend: Item[];
        by_origin: Item[];
        by_scholarship: Item[];
        by_income_bracket: Item[];
        by_attrition: Item[];
        by_equity_group: Item[];
        form_bc_matrix: MatrixRow[];
        program_year_matrix: MatrixRow[];
        annual_graduates: Item[];
    };
    quality: Record<string, number>;
    report: {
        label: string;
        max_year_level: number;
        values: FilterValues;
        options: Record<string, Option[]>;
        context: { filters: { label: string; value: string }[]; status_rule: string; form_bc_rule: string };
    };
    generatedAt: string;
};

const COLORS = ["hsl(172 66% 42%)", "hsl(212 94% 62%)", "hsl(38 92% 58%)", "hsl(326 75% 58%)", "hsl(266 74% 64%)", "hsl(8 76% 58%)"];
const chartConfig: ChartConfig = { count: { label: "Students", color: COLORS[0] } };
const COURSE_TONES: CourseTone[] = [
    { badge: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300", dot: "bg-sky-500", stripe: "border-l-sky-500" },
    { badge: "border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300", dot: "bg-violet-500", stripe: "border-l-violet-500" },
    {
        badge: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        dot: "bg-emerald-500",
        stripe: "border-l-emerald-500",
    },
    { badge: "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300", dot: "bg-amber-500", stripe: "border-l-amber-500" },
    { badge: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300", dot: "bg-rose-500", stripe: "border-l-rose-500" },
    { badge: "border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300", dot: "bg-cyan-500", stripe: "border-l-cyan-500" },
    { badge: "border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300", dot: "bg-indigo-500", stripe: "border-l-indigo-500" },
    { badge: "border-orange-500/30 bg-orange-500/10 text-orange-700 dark:text-orange-300", dot: "bg-orange-500", stripe: "border-l-orange-500" },
];
const label = (value: unknown) =>
    String(value ?? "Unspecified")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (character) => character.toUpperCase());

function courseTone(value: unknown): CourseTone {
    const code = String(value ?? "Unassigned");
    const hash = [...code].reduce((total, character) => total + character.charCodeAt(0), 0);

    return COURSE_TONES[hash % COURSE_TONES.length];
}

function CourseBadge({ code, tone = courseTone(code) }: { code: unknown; tone?: CourseTone }) {
    return (
        <Badge variant="outline" className={`gap-1.5 font-mono text-[10px] ${tone.badge}`}>
            <span className={`size-1.5 rounded-full ${tone.dot}`} />
            {code ?? "—"}
        </Badge>
    );
}

function GenderSplit({ male, female, tone }: { male: number; female: number; tone: CourseTone }) {
    return (
        <span className="inline-flex items-center gap-1 font-mono text-[11px] tabular-nums">
            <span className={`rounded-md border px-1.5 py-0.5 ${tone.badge}`}>M {male}</span>
            <span className="rounded-md border border-violet-500/30 bg-violet-500/10 px-1.5 py-0.5 text-violet-700 dark:text-violet-300">
                F {female}
            </span>
        </span>
    );
}

function CourseCount({ count, tone }: { count: number; tone: CourseTone }) {
    return (
        <span
            className={`inline-flex min-w-8 justify-center rounded-md border px-2 py-1 font-mono text-[11px] font-medium tabular-nums ${count > 0 ? tone.badge : "border-border/70 bg-muted/50 text-muted-foreground"}`}
        >
            {count.toLocaleString()}
        </span>
    );
}

function MetricCard({ title, value, detail, change, icon }: { title: string; value: number; detail: string; change?: number; icon: ReactNode }) {
    const isPositive = (change ?? 0) >= 0;

    return (
        <Card size="sm" className="bg-card/80 relative min-h-36 shadow-none">
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div className="grid gap-1.5">
                    <CardDescription className="text-xs font-medium">{title}</CardDescription>
                    <CardTitle className="text-3xl font-semibold tracking-[-0.04em] tabular-nums">{value.toLocaleString()}</CardTitle>
                </div>
                <div className="border-border/70 bg-muted/50 text-muted-foreground flex size-9 items-center justify-center rounded-lg border">
                    {icon}
                </div>
            </CardHeader>
            <CardContent className="mt-auto flex items-end justify-between gap-3">
                <p className="text-muted-foreground max-w-48 text-xs leading-5">{detail}</p>
                {change !== undefined ? (
                    <Badge
                        variant="outline"
                        className={
                            isPositive
                                ? "border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                : "border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-400"
                        }
                    >
                        {isPositive ? <ArrowUpRight className="size-3" /> : <ArrowDownRight className="size-3" />}
                        {Math.abs(change).toLocaleString()}
                    </Badge>
                ) : null}
            </CardContent>
        </Card>
    );
}

function ChartCard({
    title,
    description,
    data,
    children,
    className = "",
}: {
    title: string;
    description: string;
    data: ChartDatum[];
    children: (shown: ChartDatum[]) => ReactNode;
    className?: string;
}) {
    const [limit, setLimit] = useState<"5" | "10" | "all">("10");
    const shown = limit === "all" ? data : data.slice(0, Number(limit));

    return (
        <Card className={`bg-card/80 min-w-0 shadow-none ${className}`}>
            <CardHeader className="flex-row items-start justify-between gap-3">
                <div className="grid gap-1">
                    <CardTitle className="text-sm font-semibold">{title}</CardTitle>
                    <CardDescription className="text-xs">{description}</CardDescription>
                </div>
                <select
                    aria-label={`${title} chart view`}
                    value={limit}
                    onChange={(event) => setLimit(event.target.value as "5" | "10" | "all")}
                    className="border-input bg-background text-foreground focus-visible:border-ring focus-visible:ring-ring/30 h-8 rounded-md border px-2 text-xs outline-none focus-visible:ring-2"
                >
                    <option value="5">Top 5</option>
                    <option value="10">Top 10</option>
                    <option value="all">All</option>
                </select>
            </CardHeader>
            <CardContent>
                {shown.length === 0 ? (
                    <div className="text-muted-foreground flex h-[230px] items-center justify-center text-sm">
                        No records match the shared report filters.
                    </div>
                ) : (
                    children(shown)
                )}
            </CardContent>
        </Card>
    );
}

function BarView({ data }: { data: ChartDatum[] }) {
    return (
        <ChartContainer config={chartConfig} className="h-[230px] w-full">
            <BarChart data={data} layout="vertical" margin={{ left: 2, right: 16 }}>
                <CartesianGrid horizontal={false} strokeDasharray="3 3" vertical />
                <XAxis type="number" allowDecimals={false} axisLine={false} tickLine={false} />
                <YAxis dataKey="name" type="category" width={94} tickLine={false} axisLine={false} tick={{ fontSize: 11 }} />
                <ChartTooltip cursor={{ fill: "var(--muted)", opacity: 0.45 }} content={<ChartTooltipContent />} />
                <Bar dataKey="count" fill="var(--color-count)" radius={[0, 5, 5, 0]} maxBarSize={22} />
            </BarChart>
        </ChartContainer>
    );
}

function TrendView({ data }: { data: ChartDatum[] }) {
    return (
        <ChartContainer config={chartConfig} className="h-[230px] w-full">
            <AreaChart data={data} margin={{ left: 0, right: 8, top: 10 }}>
                <CartesianGrid vertical={false} strokeDasharray="3 3" />
                <XAxis dataKey="name" tickLine={false} axisLine={false} minTickGap={24} tick={{ fontSize: 11 }} />
                <YAxis tickLine={false} axisLine={false} width={32} allowDecimals={false} tick={{ fontSize: 11 }} />
                <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                <Area dataKey="count" type="monotone" fill="var(--color-count)" fillOpacity={0.16} stroke="var(--color-count)" strokeWidth={2.25} />
            </AreaChart>
        </ChartContainer>
    );
}

function PieView({ data }: { data: ChartDatum[] }) {
    const total = data.reduce((sum, item) => sum + item.count, 0);

    return (
        <div className="grid h-[230px] grid-cols-[minmax(150px,1fr)_minmax(120px,.8fr)] items-center gap-3">
            <ChartContainer config={chartConfig} className="h-[210px] w-full">
                <PieChart>
                    <ChartTooltip content={<ChartTooltipContent nameKey="name" />} />
                    <Pie data={data} dataKey="count" nameKey="name" innerRadius={54} outerRadius={82} paddingAngle={2} strokeWidth={0}>
                        {data.map((item, index) => (
                            <Cell key={item.name} fill={COLORS[index % COLORS.length]} />
                        ))}
                    </Pie>
                </PieChart>
            </ChartContainer>
            <div className="grid content-center gap-2">
                <div className="border-border/70 border-b pb-2">
                    <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Total</p>
                    <p className="text-xl font-semibold tabular-nums">{total.toLocaleString()}</p>
                </div>
                {data.slice(0, 4).map((item, index) => (
                    <div key={item.name} className="flex items-center justify-between gap-2 text-[11px]">
                        <span className="flex min-w-0 items-center gap-1.5">
                            <span className="size-2 shrink-0 rounded-full" style={{ backgroundColor: COLORS[index % COLORS.length] }} />
                            <span className="text-muted-foreground truncate">{item.name}</span>
                        </span>
                        <span className="font-medium tabular-nums">{item.count.toLocaleString()}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function TableSection({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <Card className="bg-card/80 gap-0 py-0 shadow-none">
            <CardHeader className="border-border/70 border-b py-4">
                <CardTitle className="text-sm font-semibold">{title}</CardTitle>
                <CardDescription className="max-w-5xl text-xs leading-5">{description}</CardDescription>
            </CardHeader>
            <CardContent className="px-0">{children}</CardContent>
        </Card>
    );
}

export default function RegistrarAnalytics({ user, analytics, quality, report, generatedAt, canImportStudentProfiles }: Props) {
    const [values, setValues] = useState<FilterValues>(report.values);

    useEffect(() => setValues(report.values), [report.values]);

    const date = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date(generatedAt));
    const yearLevelLabel = (year: number) => (year === 0 ? "Unclassified or other year level" : `Year ${year}`);
    const chartData = (items: Item[], key: string) =>
        items
            .map((item) => ({
                name: key === "year_level" ? yearLevelLabel(Number(item[key] ?? 0)) : label(item[key]),
                count: Number(item.count ?? 0),
            }))
            .sort((first, second) => second.count - first.count);
    const periodTotal = analytics.current_semester_count ?? 0;
    const delta = periodTotal - (analytics.previous_semester_count ?? 0);
    const query = (next: FilterValues) => Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== ""));
    const apply = (next: FilterValues) => {
        setValues(next);
        router.get(analyticsRoute.url({ query: query(next) }), {}, { preserveScroll: true, preserveState: true, replace: true });
    };
    const programOptions = (report.options.programs ?? []).filter(
        (option) => !values.department_id || String(option.department_id) === String(values.department_id),
    );
    const qualityItems = [
        ["Missing program metadata", quality.missing_program_metadata_count],
        ["Unclassified first-year intake", quality.unclassified_first_year_intake_count],
        ["Missing reporting confirmation", quality.reporting_confirmation_missing_count],
        ["Missing graduation period", quality.missing_graduation_period_count],
        ["Missing gender", quality.without_gender_count],
        ["Missing course", quality.missing_course_count],
    ] as const;
    const yearLevels = Array.from({ length: report.max_year_level }, (_, index) => index + 1);
    const matrixColumns = ["new_freshman_male", "continuing_first_year_male"];
    const dailyTrend = analytics.daily_trend.map((item) => ({ name: String(item.date ?? ""), count: Number(item.count ?? 0) }));
    const secondaryCharts = [
        ["Program distribution", "Reported enrollments by program.", chartData(analytics.by_program, "program"), "bar"],
        ["Department distribution", "Reported enrollments by department.", chartData(analytics.by_department, "department"), "bar"],
        ["Year-level breakdown", "Enrollment-period year level.", chartData(analytics.by_year_level, "year_level"), "bar"],
        ["Regional origin", "Recorded student region of origin.", chartData(analytics.by_origin, "origin"), "bar"],
        ["Scholarship", "Recorded scholarship category.", chartData(analytics.by_scholarship, "scholarship"), "pie"],
        ["Income bracket", "Recorded family income bracket.", chartData(analytics.by_income_bracket, "income_bracket"), "bar"],
        ["Student type", "Reported enrollment population by student type.", chartData(analytics.by_student_type, "student_type"), "pie"],
        ["Equity and support groups", "Recorded equity indicators; categories may overlap.", chartData(analytics.by_equity_group, "group"), "bar"],
        ["Attrition category", "Recorded withdrawal or dropout category.", chartData(analytics.by_attrition, "attrition"), "bar"],
        [
            "Annual graduates",
            "Graduates with a confirmed graduation academic year.",
            analytics.annual_graduates.map((item) => ({ name: `${label(item.program)} · ${label(item.gender)}`, count: Number(item.count ?? 0) })),
            "bar",
        ],
    ] as const;

    return (
        <AdminLayout user={user} title="Registrar Analytics">
            <Head title="Registrar Analytics" />
            <div className="space-y-5 pb-12">
                <header className="flex flex-col gap-4 border-b pb-5 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <div className="text-muted-foreground mb-2 flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase">
                            <span className="bg-primary size-1.5 rounded-full" /> Commission on Higher Education Form B/C
                        </div>
                        <h1 className="text-2xl font-semibold tracking-[-0.035em]">Registrar analytics</h1>
                        <p className="text-muted-foreground mt-1 max-w-3xl text-sm leading-6">
                            A live view of the reporting population for {report.label}, from enrollment health to compliance-ready program matrices.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground mr-1 flex items-center gap-1.5 text-xs">
                            <RefreshCw className="size-3.5" /> Updated {date}
                        </span>
                        {canImportStudentProfiles ? <RegistrarStudentProfileImportDialog /> : null}
                        <Button asChild size="sm">
                            <a href={exportMethod.url({ query: query(values) })}>
                                <FileSpreadsheet className="size-4" /> Export Excel
                            </a>
                        </Button>
                    </div>
                </header>

                <Card className="bg-card/80 gap-0 py-0 shadow-none">
                    <CardHeader className="border-border/70 flex-row items-center justify-between border-b py-3">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <Filter className="size-4" /> Report filters
                            </CardTitle>
                            <CardDescription className="mt-0.5 text-xs">
                                Every selection recalculates all metrics, charts, and tables.
                            </CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" onClick={() => apply({})}>
                            <RotateCcw className="size-3.5" /> Reset
                        </Button>
                    </CardHeader>
                    <CardContent className="grid gap-3 py-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                        {(
                            [
                                ["school_year", "Academic year", report.options.school_years],
                                ["semester", "Term", report.options.semesters],
                                ["department_id", "Department", report.options.departments],
                                ["course_id", "Program", programOptions],
                                ["academic_year", "Year level", report.options.year_levels],
                                ["gender", "Sex", report.options.genders],
                                ["student_type", "Student type", report.options.student_types],
                                ["intake_category", "First-year intake", report.options.intake_categories],
                                ["status", "Enrollment status", report.options.statuses],
                            ] as const
                        ).map(([key, title, options]) => (
                            <label key={key} className="grid gap-1.5 text-[11px] font-medium">
                                <span className="text-muted-foreground">{title}</span>
                                <select
                                    value={values[key] ?? ""}
                                    onChange={(event) =>
                                        apply({
                                            ...values,
                                            [key]: event.target.value || null,
                                            ...(key === "department_id" ? { course_id: null } : {}),
                                        })
                                    }
                                    className="border-input bg-background text-foreground focus-visible:border-ring focus-visible:ring-ring/30 h-9 min-w-0 rounded-md border px-2.5 text-xs outline-none focus-visible:ring-2"
                                >
                                    <option value="">All</option>
                                    {options.map((option) => (
                                        <option key={String(option.value)} value={String(option.value)}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                    <MetricCard
                        title="Selected population"
                        value={periodTotal}
                        detail="Students in the current reporting view"
                        change={delta}
                        icon={<Users className="size-4" />}
                    />
                    <MetricCard
                        title="School-year total"
                        value={analytics.current_school_year_count}
                        detail={`All reported enrollments in ${report.label}`}
                        icon={<CalendarRange className="size-4" />}
                    />
                    <MetricCard
                        title="Prior-term total"
                        value={analytics.previous_semester_count}
                        detail="Baseline used for term-over-term change"
                        icon={<ChartNoAxesCombined className="size-4" />}
                    />
                    <MetricCard
                        title="Active records"
                        value={analytics.active_count}
                        detail={`${analytics.trashed_count ?? 0} deleted records retained for audit`}
                        icon={<Activity className="size-4" />}
                    />
                </div>

                <div className="grid gap-3 2xl:grid-cols-3">
                    <ChartCard
                        title="Enrollment activity"
                        description="Daily records created in the selected term."
                        data={dailyTrend}
                        className="2xl:col-span-2"
                    >
                        {(shown) => <TrendView data={shown} />}
                    </ChartCard>
                    <ChartCard
                        title="Sex breakdown"
                        description="Sex values recorded on student records."
                        data={chartData(analytics.by_gender, "gender")}
                    >
                        {(shown) => <PieView data={shown} />}
                    </ChartCard>
                    <ChartCard
                        title="Enrollment pipeline"
                        description="Current status across the filtered population."
                        data={chartData(analytics.by_status, "status")}
                        className="2xl:col-span-2"
                    >
                        {(shown) => <BarView data={shown} />}
                    </ChartCard>
                    <Card className="bg-card/80 shadow-none">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <CircleGauge className="size-4" /> Report context
                            </CardTitle>
                            <CardDescription className="text-xs">The active rules behind this reporting view.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <div className="flex flex-wrap gap-1.5">
                                {report.context.filters.map((filter) => (
                                    <Badge key={filter.label} variant="secondary" className="font-normal">
                                        {filter.label}: {filter.value}
                                    </Badge>
                                ))}
                            </div>
                            <div className="border-border/70 text-muted-foreground grid gap-2 border-t pt-3 text-xs leading-5">
                                <p>{report.context.status_rule}</p>
                                <p>{report.context.form_bc_rule}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <TableSection
                    title="CHED Form B/C enrollment matrix"
                    description={`Program rows with male and female counts. First-year counts separate new and continuing students; Years 2 through ${report.max_year_level} follow the selected enrollment period.`}
                >
                    <Table className="min-w-[1100px] text-xs">
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="border-border/60 h-11 border-r px-4">Department</TableHead>
                                <TableHead className="border-border/60 h-11 min-w-64 border-r px-4">Program</TableHead>
                                <TableHead className="border-border/60 h-11 min-w-40 border-r px-4">New Year 1</TableHead>
                                <TableHead className="border-border/60 h-11 min-w-44 border-r px-4">Continuing Year 1</TableHead>
                                {yearLevels.slice(1).map((year) => (
                                    <TableHead key={year} className="border-border/60 h-11 border-r px-4 text-center">
                                        Year {year}
                                    </TableHead>
                                ))}
                                <TableHead className="h-11 px-4 text-right">Eligible total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {analytics.form_bc_matrix.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={yearLevels.length + 4} className="text-muted-foreground h-28 text-center">
                                        No programs match the report filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                analytics.form_bc_matrix.map((row, rowIndex) => {
                                    const tone = courseTone(row.program_code);

                                    return (
                                        <TableRow key={`${row.program_code}-${rowIndex}`} className={`group border-l-2 ${tone.stripe}`}>
                                            <TableCell className="border-border/60 border-r px-4 py-3">
                                                <span className="flex items-center gap-2 font-medium">
                                                    <span className={`flex size-7 items-center justify-center rounded-md ${tone.badge}`}>
                                                        <Building2 className="size-3.5" />
                                                    </span>
                                                    {row.department ?? "Unassigned"}
                                                </span>
                                            </TableCell>
                                            <TableCell className="border-border/60 border-r px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <CourseBadge code={row.program_code} tone={tone} />
                                                    <span className="text-muted-foreground max-w-56 truncate">
                                                        {row.program_title ?? "Unassigned program"}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            {matrixColumns.map((column) => (
                                                <TableCell key={column} className="border-border/60 border-r px-4 py-3">
                                                    <GenderSplit
                                                        male={Number(row[column] ?? 0)}
                                                        female={Number(row[column.replace("_male", "_female")] ?? 0)}
                                                        tone={tone}
                                                    />
                                                </TableCell>
                                            ))}
                                            {yearLevels.slice(1).map((year) => (
                                                <TableCell key={year} className="border-border/60 border-r px-4 py-3 text-center">
                                                    <GenderSplit
                                                        male={Number(row[`year_${year}_male`] ?? 0)}
                                                        female={Number(row[`year_${year}_female`] ?? 0)}
                                                        tone={tone}
                                                    />
                                                </TableCell>
                                            ))}
                                            <TableCell className="px-4 py-3 text-right">
                                                <Badge variant="outline" className={`font-mono tabular-nums ${tone.badge}`}>
                                                    {Number(row.total ?? 0).toLocaleString()} students
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableSection>

                <TableSection
                    title="Enrollment by program and year level"
                    description={`Selected reporting-population counts from Year 1 through Year ${report.max_year_level}. Unclassified values stay separate and are never inferred.`}
                >
                    <Table className="min-w-[1050px] text-xs">
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="border-border/60 h-11 min-w-48 border-r px-4">Department</TableHead>
                                <TableHead className="border-border/60 h-11 border-r px-4">Code</TableHead>
                                <TableHead className="border-border/60 h-11 min-w-64 border-r px-4">Program title</TableHead>
                                {yearLevels.map((year) => (
                                    <TableHead key={year} className="border-border/60 h-11 border-r px-4 text-center">
                                        Year {year}
                                    </TableHead>
                                ))}
                                <TableHead className="border-border/60 h-11 border-r px-4 text-center">Unclassified</TableHead>
                                <TableHead className="h-11 px-4 text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {analytics.program_year_matrix.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={yearLevels.length + 5} className="text-muted-foreground h-28 text-center">
                                        No programs match the report filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                analytics.program_year_matrix.map((row, rowIndex) => {
                                    const tone = courseTone(row.program_code);

                                    return (
                                        <TableRow key={`${row.program_code}-${rowIndex}`} className={`border-l-2 ${tone.stripe}`}>
                                            <TableCell className="border-border/60 border-r px-4 py-3 font-medium">
                                                <span className="flex items-center gap-2">
                                                    <span className={`size-2 rounded-full ${tone.dot}`} />
                                                    {row.department ?? "Unassigned"}
                                                </span>
                                            </TableCell>
                                            <TableCell className="border-border/60 border-r px-4 py-3">
                                                <CourseBadge code={row.program_code} tone={tone} />
                                            </TableCell>
                                            <TableCell className="border-border/60 text-muted-foreground border-r px-4 py-3">
                                                {row.program_title ?? "Unassigned program"}
                                            </TableCell>
                                            {yearLevels.map((year) => (
                                                <TableCell key={year} className="border-border/60 border-r px-4 py-3 text-center">
                                                    <CourseCount count={Number(row[`year_${year}`] ?? 0)} tone={tone} />
                                                </TableCell>
                                            ))}
                                            <TableCell className="border-border/60 border-r px-4 py-3 text-center">
                                                <CourseCount count={Number(row.unclassified_year_level ?? 0)} tone={tone} />
                                            </TableCell>
                                            <TableCell className="px-4 py-3 text-right">
                                                <Badge variant="outline" className={`font-mono tabular-nums ${tone.badge}`}>
                                                    {Number(row.total ?? 0).toLocaleString()} students
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableSection>

                <section className="space-y-3">
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <h2 className="text-base font-semibold tracking-tight">Population intelligence</h2>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                Deeper distribution and outcome views using the same active filters.
                            </p>
                        </div>
                        <Badge variant="outline" className="hidden sm:flex">
                            <Sparkles className="size-3" /> Live aggregates
                        </Badge>
                    </div>
                    <div className="grid gap-3 xl:grid-cols-2 2xl:grid-cols-3">
                        {secondaryCharts.map(([title, description, data, type]) => (
                            <ChartCard key={title} title={title} description={description} data={[...data]}>
                                {(shown) => (type === "pie" ? <PieView data={shown} /> : <BarView data={shown} />)}
                            </ChartCard>
                        ))}
                    </div>
                </section>

                <Card className="bg-card/80 gap-0 py-0 shadow-none">
                    <CardHeader className="border-border/70 border-b py-4">
                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                            <AlertTriangle className="size-4 text-amber-600" /> Reporting-quality queue
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Unknown values stay visible for registrar review. Student-level detail remains restricted to the authorized Excel
                            workbook.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="bg-border/70 grid gap-px p-0 sm:grid-cols-2 xl:grid-cols-3">
                        {qualityItems.map(([title, count]) => (
                            <div key={title} className="bg-card flex min-h-16 items-center justify-between gap-4 px-4 py-3">
                                <span className="flex items-center gap-2 text-xs font-medium">
                                    <GraduationCap className="text-muted-foreground size-4" /> {title}
                                </span>
                                <Badge variant={count > 0 ? "destructive" : "secondary"} className="min-w-8 justify-center tabular-nums">
                                    {count ?? 0}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
