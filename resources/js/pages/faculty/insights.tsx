import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { User } from "@/types/user";
import { Head } from "@inertiajs/react";

interface ClassInfo { id: number; code: string; section: string; students: number }
interface PerClass {
    id: number; code: string; section: string; students: number; avg: number | null;
    at_risk: number; distribution: Record<string, number>;
    submission_rate: number | null; attendance_rate: number | null;
    assignment_count: number; session_count: number;
}
interface Overall { students: number; avg: number | null; at_risk: number; submission_rate: number | null; attendance_rate: number | null }
interface Props { user: User; classes: ClassInfo[]; per_class: PerClass[]; overall: Overall }

function StatCard({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <Card>
            <CardHeader className="pb-1">
                <CardTitle className="text-muted-foreground text-xs font-medium uppercase tracking-wide">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-bold">{value}</p>
                {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            </CardContent>
        </Card>
    );
}

export default function InsightsPage({ user, per_class, overall }: Props) {
    return (
        <FacultyLayout user={user}>
            <Head title="Insights" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Insights</h1>
                    <p className="text-muted-foreground text-sm">Grade distribution, submission, and attendance trends across your classes.</p>
                </header>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard label="Students" value={String(overall.students)} />
                    <StatCard label="Average grade" value={overall.avg != null ? `${overall.avg}%` : "—"} />
                    <StatCard label="At risk (<75)" value={String(overall.at_risk)} />
                    <StatCard label="Submission rate" value={overall.submission_rate != null ? `${overall.submission_rate}%` : "—"} />
                    <StatCard label="Attendance rate" value={overall.attendance_rate != null ? `${overall.attendance_rate}%` : "—"} />
                </div>

                <div className="space-y-4">
                    {per_class.map((c) => {
                        const dist = c.distribution ?? {};
                        const total = Object.values(dist).reduce((a, b) => a + b, 0) || 1;
                        const buckets = [
                            { key: "90", label: "90-100", color: "bg-emerald-500" },
                            { key: "80", label: "80-89", color: "bg-sky-500" },
                            { key: "70", label: "70-79", color: "bg-amber-500" },
                            { key: "60", label: "60-69", color: "bg-orange-500" },
                            { key: "0", label: "<60", color: "bg-red-500" },
                        ];
                        return (
                            <Card key={c.id}>
                                <CardContent className="space-y-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="font-semibold">{c.code} · Section {c.section}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {c.students} students · {c.assignment_count} assignments · {c.session_count} sessions
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <Badge variant="outline" className="font-normal">avg {c.avg != null ? `${c.avg}%` : "—"}</Badge>
                                            {c.at_risk > 0 && <Badge variant="outline" className="border-red-200 bg-red-500/10 font-normal text-red-600">{c.at_risk} at risk</Badge>}
                                            {c.submission_rate != null && <Badge variant="outline" className="font-normal">{c.submission_rate}% submissions</Badge>}
                                            {c.attendance_rate != null && <Badge variant="outline" className="font-normal">{c.attendance_rate}% attendance</Badge>}
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        {buckets.map((b) => {
                                            const count = dist[b.key] ?? 0;
                                            return (
                                                <div key={b.key} className="flex items-center gap-2 text-xs">
                                                    <span className="text-muted-foreground w-14 shrink-0">{b.label}</span>
                                                    <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                        <div className={`h-full rounded-full ${b.color}`} style={{ width: `${(count / total) * 100}%` }} />
                                                    </div>
                                                    <span className="text-muted-foreground w-6 text-right">{count}</span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                    {per_class.length === 0 && (
                        <Card><CardContent className="text-muted-foreground p-8 text-center text-sm">No classes this term.</CardContent></Card>
                    )}
                </div>
            </main>
        </FacultyLayout>
    );
}
