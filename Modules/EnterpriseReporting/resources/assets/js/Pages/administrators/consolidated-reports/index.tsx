import { exportMethod, index } from "@/actions/Modules/EnterpriseReporting/Http/Controllers/ConsolidatedReportController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { index as marketplace } from "@/routes/administrators/module-marketplace";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { type FormEvent } from "react";

interface Filters {
    school_ids: number[];
    school_year: string;
    semester: number;
}
interface Props {
    user: User;
    schools: { id: number; name: string; code: string }[];
    filters: Filters;
    report: {
        rows: { school_id: number; name: string; code: string; enrollments: number; students: number; courses: number }[];
        totals: { schools: number; enrollments: number; students: number };
    };
}

export default function ConsolidatedReports({ user, schools, filters, report }: Props) {
    const form = useForm<Filters>({ ...filters });
    const number = new Intl.NumberFormat();

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.get(index.url(), { preserveScroll: true, preserveState: true });
    }

    return (
        <AdminLayout user={user} title="Consolidated reports">
            <Head title="Consolidated school reports" />
            <div className="mx-auto w-full max-w-7xl space-y-6 pb-8">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm">Enterprise reporting</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Consolidated school reports</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            Compare enrollment across your licensed schools using one academic period.
                        </p>
                    </div>
                    <Button variant="outline" nativeButton={false} render={<Link href={marketplace.url()} />}>
                        Manage licenses
                    </Button>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>Report scope</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={applyFilters} className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="report-school-year">School year</Label>
                                    <Input
                                        id="report-school-year"
                                        required
                                        value={form.data.school_year}
                                        onChange={(event) => form.setData("school_year", event.target.value)}
                                        aria-invalid={Boolean(form.errors.school_year)}
                                    />
                                    {form.errors.school_year && (
                                        <p role="alert" className="text-destructive text-sm">
                                            {form.errors.school_year}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="report-semester">Semester</Label>
                                    <Input
                                        id="report-semester"
                                        type="number"
                                        min={1}
                                        max={3}
                                        required
                                        value={form.data.semester}
                                        onChange={(event) => form.setData("semester", Number(event.target.value))}
                                        aria-invalid={Boolean(form.errors.semester)}
                                    />
                                    {form.errors.semester && (
                                        <p role="alert" className="text-destructive text-sm">
                                            {form.errors.semester}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <fieldset className="space-y-4">
                                <legend className="text-sm font-medium">Schools</legend>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {schools.map((school) => (
                                        <label key={school.id} className="flex min-h-11 items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={form.data.school_ids.includes(school.id)}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        "school_ids",
                                                        checked === true
                                                            ? [...form.data.school_ids, school.id]
                                                            : form.data.school_ids.filter((id) => id !== school.id),
                                                    )
                                                }
                                            />
                                            <span>
                                                {school.name} <span className="text-muted-foreground">({school.code})</span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                {form.errors.school_ids && (
                                    <p role="alert" className="text-destructive text-sm">
                                        {form.errors.school_ids}
                                    </p>
                                )}
                            </fieldset>
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={form.processing || form.data.school_ids.length === 0}>
                                    {form.processing ? "Updating report…" : "Apply filters"}
                                </Button>
                                <Button variant="outline" nativeButton={false} render={<a href={exportMethod.url({ query: { ...filters } })} />}>
                                    Export CSV
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <div className="grid gap-4 sm:grid-cols-3" aria-label="Report totals">
                    {(
                        [
                            ["Schools", report.totals.schools],
                            ["Enrollments", report.totals.enrollments],
                            ["Student records", report.totals.students],
                        ] as const
                    ).map(([label, value]) => (
                        <Card key={label}>
                            <CardHeader>
                                <CardTitle className="text-muted-foreground text-sm font-medium">{label}</CardTitle>
                            </CardHeader>
                            <CardContent className="text-3xl font-semibold tabular-nums">{number.format(value)}</CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Enrollment by school</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            {filters.school_year} · Semester {filters.semester}. Student records are counted separately within each school.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <caption className="sr-only">Enrollment comparison for the selected schools and academic period</caption>
                                <thead>
                                    <tr className="border-b">
                                        <th scope="col" className="px-4 py-4 text-left font-medium">
                                            School
                                        </th>
                                        <th scope="col" className="px-4 py-4 text-right font-medium">
                                            Enrollments
                                        </th>
                                        <th scope="col" className="px-4 py-4 text-right font-medium">
                                            Student records
                                        </th>
                                        <th scope="col" className="px-4 py-4 text-right font-medium">
                                            Courses
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.rows.map((row) => (
                                        <tr key={row.school_id} className="border-b last:border-0">
                                            <th scope="row" className="px-4 py-4 text-left font-medium">
                                                {row.name}
                                                <span className="text-muted-foreground block text-xs">{row.code}</span>
                                            </th>
                                            <td className="px-4 py-4 text-right tabular-nums">{number.format(row.enrollments)}</td>
                                            <td className="px-4 py-4 text-right tabular-nums">{number.format(row.students)}</td>
                                            <td className="px-4 py-4 text-right tabular-nums">{number.format(row.courses)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {report.totals.enrollments === 0 && (
                            <p className="text-muted-foreground py-6 text-center text-sm">
                                No enrollment records match this academic period. Choose another school year or semester.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
