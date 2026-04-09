import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

interface StudentAttendanceTabProps {
    stats: {
        present: number;
        late: number;
        absent: number;
        excused: number;
    };
    history: Array<{
        id: number;
        date: string;
        status: string;
        remarks: string;
        topic: string;
    }>;
}

export function StudentAttendanceTab({ stats, history }: StudentAttendanceTabProps) {
    const totalSessions = stats.present + stats.late + stats.absent + stats.excused;
    const presentPercentage = totalSessions > 0 ? Math.round(((stats.present + stats.late) / totalSessions) * 100) : 100;

    return (
        <div className="grid gap-6 md:grid-cols-[300px_1fr]">
            <div className="space-y-6">
                <Card className="border-border/70 bg-card/90 shadow-sm">
                    <CardHeader>
                        <CardTitle>Attendance Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium">Attendance Rate</span>
                                <span className="text-muted-foreground">{presentPercentage}%</span>
                            </div>
                            <Progress value={presentPercentage} className="h-2" />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="border-border/60 bg-background/50 space-y-1 rounded-lg border p-3">
                                <p className="text-muted-foreground text-xs tracking-wider uppercase">Present</p>
                                <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{stats.present}</p>
                            </div>
                            <div className="border-border/60 bg-background/50 space-y-1 rounded-lg border p-3">
                                <p className="text-muted-foreground text-xs tracking-wider uppercase">Late</p>
                                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400">{stats.late}</p>
                            </div>
                            <div className="border-border/60 bg-background/50 space-y-1 rounded-lg border p-3">
                                <p className="text-muted-foreground text-xs tracking-wider uppercase">Absent</p>
                                <p className="text-2xl font-bold text-rose-600 dark:text-rose-400">{stats.absent}</p>
                            </div>
                            <div className="border-border/60 bg-background/50 space-y-1 rounded-lg border p-3">
                                <p className="text-muted-foreground text-xs tracking-wider uppercase">Excused</p>
                                <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{stats.excused}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card className="border-border/70 bg-card/90 h-fit shadow-sm">
                <CardHeader>
                    <CardTitle>Attendance History</CardTitle>
                </CardHeader>
                <CardContent>
                    {history.length === 0 ? (
                        <div className="text-muted-foreground py-8 text-center">No attendance records yet.</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Topic</TableHead>
                                    <TableHead>Remarks</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {history.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell>{new Date(record.date).toLocaleDateString()}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    record.status === "present"
                                                        ? "border-emerald-200 bg-emerald-500/10 text-emerald-600 dark:border-emerald-800"
                                                        : record.status === "late"
                                                          ? "border-amber-200 bg-amber-500/10 text-amber-600 dark:border-amber-800"
                                                          : record.status === "absent"
                                                            ? "border-rose-200 bg-rose-500/10 text-rose-600 dark:border-rose-800"
                                                            : "border-blue-200 bg-blue-500/10 text-blue-600 dark:border-blue-800"
                                                }
                                            >
                                                {record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="max-w-[200px] truncate" title={record.topic || "Regular Class"}>
                                            {record.topic || "Regular Class"}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">{record.remarks || "-"}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
