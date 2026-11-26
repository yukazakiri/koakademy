import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { CheckCircle2, GraduationCap, Lock } from "lucide-react";

interface GradeClassRow {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    classification: string;
    students_count: number;
    total_students?: number;
    graded_count?: number;
    finalized_count?: number;
    room: string;
    schedule_summary: string[];
    manage_grades_url: string;
}

interface FacultyGradesPageProps {
    user: User;
    faculty_data: {
        classes: GradeClassRow[];
    };
    current_semester: string;
    current_school_year: string;
}

export default function FacultyGradesPage({ user, faculty_data, current_semester, current_school_year }: FacultyGradesPageProps) {
    const classes = faculty_data.classes ?? [];
    const periodLabel = `Semester ${current_semester} • ${current_school_year}`;

    const totalStudentsAll = classes.reduce((acc, row) => acc + (row.total_students ?? row.students_count ?? 0), 0);
    const totalGradedAll = classes.reduce((acc, row) => acc + (row.graded_count ?? 0), 0);
    const totalFinalizedAll = classes.reduce((acc, row) => acc + (row.finalized_count ?? 0), 0);

    return (
        <FacultyLayout user={user}>
            <Head title="Grades & Reports" />

            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight">Grades &amp; Reports</h1>
                    <p className="text-muted-foreground text-sm">Manage student grades per class for the active academic period.</p>
                    <p className="text-muted-foreground text-sm">{periodLabel}</p>
                </header>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Card className="py-0">
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">Classes Assigned</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">{classes.length}</p>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">Grading Progress</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                {totalGradedAll} / {totalStudentsAll}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">Finalized Records</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">{totalFinalizedAll}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Your Classes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {classes.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No active classes found for the current term.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Subject</TableHead>
                                        <TableHead>Section</TableHead>
                                        <TableHead>Completion</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="hidden md:table-cell">Schedule</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {classes.map((classRow) => {
                                        const total = classRow.total_students ?? classRow.students_count ?? 0;
                                        const graded = classRow.graded_count ?? 0;
                                        const finalized = classRow.finalized_count ?? 0;
                                        const isFullyFinalized = total > 0 && finalized === total;
                                        const isPartiallyFinalized = finalized > 0 && finalized < total;
                                        const progressPct = total > 0 ? Math.min(100, Math.round((graded / total) * 100)) : 0;

                                        return (
                                            <TableRow key={classRow.id}>
                                                <TableCell>
                                                    <div className="font-medium">{classRow.subject_title}</div>
                                                    <div className="text-muted-foreground text-xs">{classRow.subject_code}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline">{classRow.section}</Badge>
                                                        <span className="text-muted-foreground hidden text-xs capitalize lg:inline">
                                                            {classRow.classification}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2 text-xs font-medium tabular-nums">
                                                            <span>
                                                                {graded} / {total} graded
                                                            </span>
                                                            <span className="text-muted-foreground">({progressPct}%)</span>
                                                        </div>
                                                        <div className="bg-muted h-1.5 w-28 overflow-hidden rounded-full">
                                                            <div
                                                                className="bg-primary h-full rounded-full transition-all"
                                                                style={{ width: `${progressPct}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {isFullyFinalized ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <Lock className="size-3" />
                                                            Finalized
                                                        </Badge>
                                                    ) : isPartiallyFinalized ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                        >
                                                            <Lock className="size-3" />
                                                            {finalized}/{total} Locked
                                                        </Badge>
                                                    ) : graded > 0 ? (
                                                        <Badge variant="secondary" className="gap-1">
                                                            <CheckCircle2 className="size-3" />
                                                            In Progress
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline">Not Started</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    <div className="text-muted-foreground text-xs">
                                                        {classRow.schedule_summary.length > 0 ? classRow.schedule_summary.join(" • ") : "TBA"}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">Room: {classRow.room}</div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        size="sm"
                                                        render={<Link href={classRow.manage_grades_url} />}
                                                        nativeButton={false}
                                                        className="gap-1.5"
                                                    >
                                                        <GraduationCap className="size-3.5" />
                                                        Open Gradebook
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </main>
        </FacultyLayout>
    );
}
