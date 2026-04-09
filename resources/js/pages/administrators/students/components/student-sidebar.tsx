import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User as UserIcon } from "lucide-react";
import React from "react";
import type { StudentDetail } from "../types";
import { TextEntry } from "./text-entry";

export function StudentSidebar({ student }: { student: StudentDetail }) {
    return (
        <div className="space-y-6">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Student Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <TextEntry label="Student ID" value={student.student_id} icon={<UserIcon className="h-4 w-4" />} copyable />
                                <TextEntry label="Created At" value={new Date(student.created_at || "").toLocaleDateString()} />
                                <TextEntry label="Updated At" value={new Date(student.updated_at || "").toLocaleDateString()} />
                                <TextEntry label="Course" value={student.course.code} />
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
