import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { exportMethod, index as analyticsRoute } from "@/routes/administrators/registrar/analytics";
import { Head, router } from "@inertiajs/react";
import { AlertTriangle, FileSpreadsheet, Filter, RefreshCw, RotateCcw, Users } from "lucide-react";
import { type ReactNode, useEffect, useState } from "react";
import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, XAxis, YAxis } from "recharts";

type Item = Record<string, string | number | null | undefined> & { count?: number };
type Option = { value: string | number; label: string; department_id?: number | null };
type FilterValues = Record<string, string | number | null>;
type MatrixRow = Record<string, string | number | null | undefined>;
type Props = {
    user: { name: string; email: string; avatar: string | null; role: string };
    analytics: {
        current_semester_count: number; current_school_year_count: number; previous_semester_count: number;
        active_count: number; trashed_count: number; by_department: Item[]; by_program: Item[];
        by_year_level: Item[]; by_student_type: Item[]; by_gender: Item[]; by_status: Item[];
        daily_trend: Item[]; by_origin: Item[]; by_scholarship: Item[]; by_income_bracket: Item[];
        by_attrition: Item[]; by_equity_group: Item[]; form_bc_matrix: MatrixRow[]; program_year_matrix: MatrixRow[]; annual_graduates: Item[];
    };
    quality: Record<string, number>;
    report: { label: string; max_year_level: number; values: FilterValues; options: Record<string, Option[]>; context: { filters: { label: string; value: string }[]; status_rule: string; form_bc_rule: string } };
    generatedAt: string;
};

const COLORS = ["hsl(212 96% 53%)", "hsl(142 76% 36%)", "hsl(45 93% 48%)", "hsl(326 100% 60%)", "hsl(271 81% 56%)", "hsl(0 84% 60%)"];
const chartConfig: ChartConfig = { count: { label: "Students", color: COLORS[0] } };
const label = (value: unknown) => String(value ?? "Unspecified").replaceAll("_", " ").replace(/\b\w/g, (c) => c.toUpperCase());

function ChartCard({ title, description, data, children }: { title: string; description: string; data: { name: string; count: number }[]; children: (shown: { name: string; count: number }[]) => ReactNode }) {
    const [limit, setLimit] = useState<"5" | "10" | "all">("10");
    const shown = limit === "all" ? data : data.slice(0, Number(limit));
    return <Card className="min-w-0">
        <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0">
            <div><CardTitle className="text-base">{title}</CardTitle><CardDescription>{description}</CardDescription></div>
            <label className="text-muted-foreground grid gap-1 text-right text-[10px] font-semibold tracking-wide uppercase">Chart view
                <select aria-label={`${title} chart view`} value={limit} onChange={(event) => setLimit(event.target.value as "5" | "10" | "all")} className="border-input bg-background h-8 rounded-md border px-2 text-xs font-normal normal-case">
                    <option value="5">Top 5</option><option value="10">Top 10</option><option value="all">All</option>
                </select>
            </label>
        </CardHeader>
        <CardContent>{shown.length === 0 ? <p className="text-muted-foreground py-12 text-center text-sm">No records match the shared report filters.</p> : children(shown)}</CardContent>
    </Card>;
}

function BarView({ data }: { data: { name: string; count: number }[] }) {
    return <ChartContainer config={chartConfig} className="h-[255px] w-full"><BarChart data={data} layout="vertical" margin={{ left: 4, right: 12 }}><CartesianGrid horizontal={false} /><XAxis type="number" allowDecimals={false} /><YAxis dataKey="name" type="category" width={95} tickLine={false} axisLine={false} /><ChartTooltip content={<ChartTooltipContent />} /><Bar dataKey="count" fill="var(--color-count)" radius={4} /></BarChart></ChartContainer>;
}

function PieView({ data }: { data: { name: string; count: number }[] }) {
    return <ChartContainer config={chartConfig} className="h-[255px] w-full"><PieChart><ChartTooltip content={<ChartTooltipContent nameKey="name" />} /><Pie data={data} dataKey="count" nameKey="name" outerRadius={92}>{data.map((item, index) => <Cell key={item.name} fill={COLORS[index % COLORS.length]} />)}</Pie></PieChart></ChartContainer>;
}

export default function RegistrarAnalytics({ user, analytics, quality, report, generatedAt }: Props) {
    const [values, setValues] = useState<FilterValues>(report.values);
    useEffect(() => setValues(report.values), [report.values]);
    const date = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date(generatedAt));
    const yearLevelLabel = (year: number) => year === 0 ? "Unclassified or other year level" : `Year ${year}`;
    const chartData = (items: Item[], key: string) => items.map((item) => ({ name: key === "year_level" ? yearLevelLabel(Number(item[key] ?? 0)) : label(item[key]), count: Number(item.count ?? 0) })).sort((a, b) => b.count - a.count);
    const periodTotal = analytics.current_semester_count ?? 0;
    const delta = periodTotal - (analytics.previous_semester_count ?? 0);
    const query = (next: FilterValues) => Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== ""));
    const apply = (next: FilterValues) => { setValues(next); router.get(analyticsRoute.url({ query: query(next) }), {}, { preserveScroll: true, preserveState: true, replace: true }); };
    const programOptions = (report.options.programs ?? []).filter((option) => !values.department_id || String(option.department_id) === String(values.department_id));
    const qualityItems = [
        ["Missing program metadata", quality.missing_program_metadata_count], ["Unclassified first-year intake", quality.unclassified_first_year_intake_count],
        ["Missing reporting confirmation", quality.reporting_confirmation_missing_count], ["Missing graduation period", quality.missing_graduation_period_count],
        ["Missing gender", quality.without_gender_count], ["Missing course", quality.missing_course_count],
    ] as const;
    const yearLevels = Array.from({ length: report.max_year_level }, (_, index) => index + 1);
    const matrixColumns = ["new_freshman_male", "new_freshman_female", "continuing_first_year_male", "continuing_first_year_female", ...yearLevels.slice(1).flatMap((year) => [`year_${year}_male`, `year_${year}_female`])];

    return <AdminLayout user={user} title="Registrar Analytics">
        <Head title="Registrar Analytics" />
        <div className="space-y-6 pb-12">
            <header className="flex flex-col gap-4 border-b pb-6 xl:flex-row xl:items-end xl:justify-between">
                <div><p className="text-muted-foreground text-[11px] font-semibold tracking-[.16em] uppercase">Commission on Higher Education Form B/C reporting</p><h1 className="mt-1 text-2xl font-semibold tracking-tight">Current-semester registrar analytics</h1><p className="text-muted-foreground mt-1 max-w-3xl text-sm">Aggregate reporting population for {report.label}. Shared filters apply to every KPI, chart, matrix, and restricted workbook detail sheet.</p></div>
                <div className="flex items-center gap-2"><span className="text-muted-foreground hidden items-center gap-1 text-xs lg:flex"><RefreshCw className="size-3" />{date}</span><Button asChild size="sm"><a href={exportMethod.url({ query: query(values) })}><FileSpreadsheet className="mr-1.5 size-4" />Export Excel</a></Button></div>
            </header>

            <Card className="border-primary/20 bg-primary/[.025]"><CardHeader className="pb-3"><CardTitle className="flex items-center gap-2 text-base"><Filter className="size-4" />Report filters</CardTitle><CardDescription>Changing these URL-backed filters recalculates the authoritative reporting population; chart views below only change what is displayed.</CardDescription></CardHeader><CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                {([['school_year', 'Academic year', report.options.school_years], ['semester', 'Term', report.options.semesters], ['department_id', 'Department', report.options.departments], ['course_id', 'Program', programOptions], ['academic_year', 'Year level', report.options.year_levels], ['gender', 'Sex', report.options.genders], ['student_type', 'Student type', report.options.student_types], ['intake_category', 'First-year intake', report.options.intake_categories], ['status', 'Enrollment status', report.options.statuses]] as const).map(([key, title, options]) => <label key={key} className="grid gap-1 text-xs font-medium">{title}<select value={values[key] ?? ""} onChange={(event) => apply({ ...values, [key]: event.target.value || null, ...(key === 'department_id' ? { course_id: null } : {}) })} className="border-input bg-background h-9 rounded-md border px-2 text-sm"><option value="">All</option>{options.map((option) => <option key={String(option.value)} value={String(option.value)}>{option.label}</option>)}</select></label>)}
                <div className="flex items-end"><Button variant="outline" className="w-full" onClick={() => apply({})}><RotateCcw className="mr-1.5 size-4" />Reset to current term</Button></div>
            </CardContent></Card>

            <Card><CardHeader className="pb-3"><CardTitle className="text-base">Report context</CardTitle><CardDescription>These notes explain exactly which enrollment records the dashboard and workbook include.</CardDescription></CardHeader><CardContent className="space-y-3"><div className="flex flex-wrap gap-2">{report.context.filters.map((filter) => <Badge key={filter.label} variant="secondary">{filter.label}: {filter.value}</Badge>)}</div><div className="grid gap-2 text-sm text-muted-foreground"><p>{report.context.status_rule}</p><p>{report.context.form_bc_rule}</p></div></CardContent></Card>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[["Selected reporting population", periodTotal], ["School-year total", analytics.current_school_year_count], ["Prior-term comparison", analytics.previous_semester_count], ["Active records", analytics.active_count]].map(([title, value], index) => <Card key={String(title)}><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">{title}</CardTitle><Users className="text-muted-foreground size-4" /></CardHeader><CardContent><div className="text-2xl font-bold">{Number(value ?? 0).toLocaleString()}</div>{index === 2 && <p className="text-muted-foreground mt-1 text-xs">{delta >= 0 ? '+' : ''}{delta.toLocaleString()} vs prior period</p>}{index === 3 && <p className="text-muted-foreground mt-1 text-xs">{analytics.trashed_count ?? 0} deleted records retained for audit</p>}</CardContent></Card>)}
            </div>

            <Card><CardHeader><CardTitle className="text-base">Commission on Higher Education Form B/C enrollment matrix</CardTitle><CardDescription>Program rows with male and female counts. First-year counts are separated into new and continuing students; Years 2 through {report.max_year_level} are shown by year level.</CardDescription></CardHeader><CardContent className="overflow-x-auto"><table className="w-full min-w-[900px] text-xs"><thead className="bg-muted/60 text-left"><tr><th className="p-2">Department</th><th className="p-2">Program</th><th className="p-2">New first-year students, male / female</th><th className="p-2">Continuing first-year students, male / female</th>{yearLevels.slice(1).map((year) => <th key={year} className="p-2">Year {year} students, male / female</th>)}<th className="p-2">Eligible Form B/C total</th></tr></thead><tbody>{analytics.form_bc_matrix.length === 0 ? <tr><td colSpan={yearLevels.length + 4} className="text-muted-foreground p-6 text-center">No programs match the report filters.</td></tr> : analytics.form_bc_matrix.map((row, rowIndex) => <tr key={`${row.program_code}-${rowIndex}`} className="border-b"><td className="p-2">{row.department ?? 'Unassigned'}</td><td className="p-2 font-medium">{row.program_code ?? 'Unassigned'} <span className="text-muted-foreground font-normal">{row.program_title ?? ''}</span></td>{matrixColumns.slice(0, 4).filter((_, index) => index % 2 === 0).map((column) => <td key={column} className="p-2">{Number(row[column] ?? 0)} / {Number(row[column.replace('_male', '_female')] ?? 0)}</td>)}{yearLevels.slice(1).map((year) => <td key={year} className="p-2">{Number(row[`year_${year}_male`] ?? 0)} / {Number(row[`year_${year}_female`] ?? 0)}</td>)}<td className="p-2 font-semibold">{Number(row.total ?? 0)}</td></tr>)}</tbody></table></CardContent></Card>

            <Card><CardHeader><CardTitle className="text-base">Enrollment by program and year level</CardTitle><CardDescription>Every program is shown with its selected reporting-population count from Year 1 through Year {report.max_year_level}. Unclassified or other year-level values are shown separately rather than inferred.</CardDescription></CardHeader><CardContent className="overflow-x-auto"><table className="w-full min-w-[900px] text-xs"><thead className="bg-muted/60 text-left"><tr><th className="p-2">Department</th><th className="p-2">Program code</th><th className="p-2">Program title</th>{yearLevels.map((year) => <th key={year} className="p-2">Year {year}</th>)}<th className="p-2">Unclassified or other year level</th><th className="p-2">Total</th></tr></thead><tbody>{analytics.program_year_matrix.length === 0 ? <tr><td colSpan={yearLevels.length + 5} className="text-muted-foreground p-6 text-center">No programs match the report filters.</td></tr> : analytics.program_year_matrix.map((row, rowIndex) => <tr key={`${row.program_code}-${rowIndex}`} className="border-b"><td className="p-2">{row.department ?? 'Unassigned'}</td><td className="p-2 font-medium">{row.program_code ?? 'Unassigned'}</td><td className="p-2">{row.program_title ?? 'Unassigned program'}</td>{yearLevels.map((year) => <td key={year} className="p-2">{Number(row[`year_${year}`] ?? 0)}</td>)}<td className="p-2">{Number(row.unclassified_year_level ?? 0)}</td><td className="p-2 font-semibold">{Number(row.total ?? 0)}</td></tr>)}</tbody></table></CardContent></Card>

            <div className="grid gap-4 xl:grid-cols-2">
                <ChartCard title="Program distribution" description="Reported enrollments by program." data={chartData(analytics.by_program, "program")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Department distribution" description="Reported enrollments by department." data={chartData(analytics.by_department, "department")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Sex breakdown" description="Sex values as recorded on student records." data={chartData(analytics.by_gender, "gender")}>{(shown) => <PieView data={shown} />}</ChartCard>
                <ChartCard title="Year-level breakdown" description="Enrollment-period year level." data={chartData(analytics.by_year_level, "year_level")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Enrollment pipeline" description="All statuses in the shared filtered population." data={chartData(analytics.by_status, "status")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Daily enrollment trend" description="Enrollment records created in the selected term." data={analytics.daily_trend.map((item) => ({ name: String(item.date ?? ""), count: Number(item.count ?? 0) }))}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Regional origin" description="Recorded student region of origin." data={chartData(analytics.by_origin, "origin")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Scholarship" description="Recorded scholarship category." data={chartData(analytics.by_scholarship, "scholarship")}>{(shown) => <PieView data={shown} />}</ChartCard>
                <ChartCard title="Income bracket" description="Recorded family income bracket." data={chartData(analytics.by_income_bracket, "income_bracket")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Equity and support groups" description="Students with recorded equity indicators; categories may overlap." data={chartData(analytics.by_equity_group, "group")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Attrition category" description="Recorded withdrawal or dropout category." data={chartData(analytics.by_attrition, "attrition")}>{(shown) => <BarView data={shown} />}</ChartCard>
                <ChartCard title="Annual graduates" description="Graduates with a confirmed graduation academic year." data={analytics.annual_graduates.map((item) => ({ name: `${label(item.program)} · ${label(item.gender)}`, count: Number(item.count ?? 0) }))}>{(shown) => <BarView data={shown} />}</ChartCard>
            </div>

            <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><AlertTriangle className="size-4 text-amber-600" />Reporting-quality queue</CardTitle><CardDescription>Unknown values are surfaced for registrar review rather than being inferred. Dashboard charts remain aggregate-only; student detail is limited to the authorized Excel workbook.</CardDescription></CardHeader><CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{qualityItems.map(([title, count]) => <div key={title} className="border-border flex items-center justify-between rounded-lg border p-3"><span className="text-sm">{title}</span><Badge variant={count > 0 ? "destructive" : "secondary"}>{count ?? 0}</Badge></div>)}</CardContent></Card>
        </div>
    </AdminLayout>;
}
