import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Link, router } from "@inertiajs/react";
import { ShieldAlert, User as UserIcon } from "lucide-react";
import type { StudentDetail } from "../types";
import { StudentPortalAccountCard } from "./student-portal-account-card";
import { TextEntry } from "./text-entry";

export function StudentSidebar({ student }: { student: StudentDetail }) {
    const activeHolds = student.active_holds ?? [];
    const completionPct = student.profile_completion?.percentage;

    return (
        <div className="space-y-6">
            <StudentPortalAccountCard
                studentId={student.id}
                studentEmail={student.email}
                portalAccount={student.portal_account}
                compact
                onUpdated={() => router.reload({ only: ["student"] })}
            />

            {activeHolds.length > 0 && (
                <Card className="border-rose-500/35 bg-rose-500/5">
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2 text-sm text-rose-700 dark:text-rose-300">
                            <ShieldAlert className="size-4" />
                            Active Holds ({activeHolds.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2.5 text-xs">
                        {activeHolds.map((hold) => (
                            <div key={hold.id} className="border-rose-500/20 bg-background/80 rounded-lg border p-2.5">
                                <div className="flex items-center justify-between gap-1">
                                    <span className="font-semibold">{hold.title}</span>
                                    <Badge variant="outline" className="text-[10px]">
                                        {hold.office_label}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-[11px]">{hold.reason}</p>
                            </div>
                        ))}
                        <Link
                            href="/administrators/enrollments/holds"
                            className="text-primary inline-block text-[11px] font-medium hover:underline"
                        >
                            Manage in Holds Console →
                        </Link>
                    </CardContent>
                </Card>
            )}

            {typeof completionPct === "number" && (
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-sm">Profile Readiness</CardTitle>
                            <span className="font-mono text-xs font-semibold tabular-nums">{completionPct}%</span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Progress value={completionPct} className="h-1.5" />
                        {(student.profile_completion?.missing_fields?.length ?? 0) > 0 && (
                            <p className="text-muted-foreground text-[11px]">
                                Missing: {student.profile_completion?.missing_fields?.slice(0, 4).join(", ")}
                            </p>
                        )}
                    </CardContent>
                </Card>
            )}

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Student Info</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <TextEntry label="Student ID" value={student.student_id} icon={<UserIcon className="h-4 w-4" />} copyable />
                        {student.lrn && <TextEntry label="External ID / LRN" value={student.lrn} copyable />}
                        <TextEntry label="Created At" value={new Date(student.created_at || "").toLocaleDateString()} />
                        <TextEntry label="Updated At" value={new Date(student.updated_at || "").toLocaleDateString()} />
                        <TextEntry label="Course / Strand" value={student.course.code} />
                    </CardContent>
                </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Address Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <TextEntry label="Current Address" value={student.personal_info?.current_adress} />
                                <TextEntry label="Permanent Address" value={student.personal_info?.permanent_address} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Non-Credited Subjects</CardTitle>
                                <CardDescription>External subjects with no curriculum equivalent in this school.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {student.non_credited_subjects.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>External Subject</TableHead>
                                                <TableHead>School / Term</TableHead>
                                                <TableHead className="text-right">Grade</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {student.non_credited_subjects.map((subject) => (
                                                <TableRow key={subject.id}>
                                                    <TableCell>
                                                        <div className="space-y-1">
                                                            <div className="font-medium">
                                                                {subject.external_subject_code || "No Code"} -{" "}
                                                                {subject.external_subject_title || "Untitled Subject"}
                                                            </div>
                                                            <div className="text-muted-foreground text-xs">
                                                                {subject.external_subject_units ?? 0} units
                                                                {subject.linked_subject
                                                                    ? ` | Previously linked to ${subject.linked_subject.code}`
                                                                    : ""}
                                                            </div>
                                                            {subject.remarks && (
                                                                <div className="text-muted-foreground text-xs">{subject.remarks}</div>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        <div>{subject.school_name || "Unknown School"}</div>
                                                        <div className="text-muted-foreground text-xs">
                                                            SY {subject.school_year || "N/A"} | Sem {subject.semester || "N/A"}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {subject.grade !== null ? (
                                                            <span className="font-mono font-semibold">{subject.grade}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">-</span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <div className="text-muted-foreground px-6 py-8 text-sm">
                                        No standalone non-credited subjects recorded for this student.
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
        </div>
    );
}
