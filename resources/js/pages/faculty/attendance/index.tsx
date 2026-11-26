import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { CalendarCheck2, ClipboardCheck, Clock } from "lucide-react";

interface AttendanceClassRow {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    classification: string;
    students_count: number;
    sessions_count?: number;
    today_logged?: boolean;
    room: string;
    schedule_summary: string[];
    manage_attendance_url: string;
}

interface FacultyAttendancePageProps {
    user: User;
    faculty_data: {
        classes: AttendanceClassRow[];
    };
    current_semester: string;
    current_school_year: string;
}

export default function FacultyAttendancePage({ user, faculty_data, current_semester, current_school_year }: FacultyAttendancePageProps) {
    const classes = faculty_data.classes ?? [];
    const periodLabel = `Semester ${current_semester} • ${current_school_year}`;

    const totalSessions = classes.reduce((acc, row) => acc + (row.sessions_count ?? 0), 0);
    const loggedTodayCount = classes.filter((row) => Boolean(row.today_logged)).length;

    return (
        <FacultyLayout user={user}>
            <Head title="Attendance" />

            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight">Attendance</h1>
                    <p className="text-muted-foreground text-sm">Manage attendance per class using each class attendance workspace.</p>
                    <p className="text-muted-foreground text-sm">{periodLabel}</p>
                </header>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Card className="py-0">
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">Active Classes</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">{classes.length}</p>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">Total Sessions Logged</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">{totalSessions}</p>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">Logged Today</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                {loggedTodayCount} / {classes.length}
                            </p>
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
                                        <TableHead>Students</TableHead>
                                        <TableHead>Sessions</TableHead>
                                        <TableHead>Today</TableHead>
                                        <TableHead className="hidden md:table-cell">Schedule</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {classes.map((classRow) => {
                                        const sessionsCount = classRow.sessions_count ?? 0;
                                        const todayLogged = Boolean(classRow.today_logged);

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
                                                <TableCell className="tabular-nums">{classRow.students_count}</TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary" className="tabular-nums">
                                                        {sessionsCount} {sessionsCount === 1 ? "session" : "sessions"}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {todayLogged ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <CalendarCheck2 className="size-3" />
                                                            Logged Today
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="text-muted-foreground gap-1">
                                                            <Clock className="size-3" />
                                                            Pending
                                                        </Badge>
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
                                                        render={<Link href={classRow.manage_attendance_url} />}
                                                        nativeButton={false}
                                                        className="gap-1.5"
                                                    >
                                                        <ClipboardCheck className="size-3.5" />
                                                        Take Attendance
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
