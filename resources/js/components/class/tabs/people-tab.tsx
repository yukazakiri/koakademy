import { AddStudentDialog } from "@/components/class/add-student-dialog";
import { MoveStudentDialog } from "@/components/class/move-student-dialog";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import { StudentEntry, TeacherEntry } from "@/types/class-detail-types";
import { router } from "@inertiajs/react";
import {
    IconBell,
    IconDotsVertical,
    IconDownload,
    IconExchange,
    IconEye,
    IconFileInfo,
    IconFileSpreadsheet,
    IconFileTypePdf,
    IconMail,
    IconTrash,
    IconUser,
    IconUserCheck,
} from "@tabler/icons-react";
import { useState } from "react";
import { toast } from "sonner";

interface PeopleTabProps {
    classData: {
        id: number;
        classification?: string;
    };
    teacher: TeacherEntry;
    students: StudentEntry[];
    onViewProfile: (student: StudentEntry) => void;
    onTrackAttendance: (studentId: number) => void;
    onViewPublicInfo: (student: StudentEntry) => void;
}

export function PeopleTab({ classData, teacher, students, onViewProfile, onTrackAttendance, onViewPublicInfo }: PeopleTabProps) {
    const [moveStudentOpen, setMoveStudentOpen] = useState(false);
    const [selectedStudentForMove, setSelectedStudentForMove] = useState<{ id: number | string; name: string } | null>(null);
    const [studentPendingRemoval, setStudentPendingRemoval] = useState<{ id: number | string; name: string } | null>(null);

    return (
        <div className="space-y-6">
            <Card className="border-border/70 bg-card/90 shadow-sm">
                <CardHeader>
                    <CardTitle>People</CardTitle>
                    <CardDescription>Teacher and enrolled students</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Teacher Section */}
                    <div className="space-y-3">
                        <p className="text-muted-foreground text-xs font-semibold tracking-[0.3em] uppercase">Teacher</p>
                        <div className="border-border/60 bg-background/60 flex items-center gap-4 rounded-xl border p-4">
                            <div className="bg-primary/10 text-primary flex size-14 items-center justify-center overflow-hidden rounded-full">
                                {teacher.photo_url ? (
                                    <img src={teacher.photo_url} alt={teacher.name} className="h-full w-full object-cover" />
                                ) : (
                                    <span className="text-lg font-semibold">{teacher.name.charAt(0).toUpperCase()}</span>
                                )}
                            </div>
                            <div className="flex-1">
                                <p className="text-foreground text-base font-semibold">{teacher.name}</p>
                                {teacher.email && <p className="text-muted-foreground text-sm">{teacher.email}</p>}
                                {teacher.department && <p className="text-muted-foreground text-xs">{teacher.department}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Students Section */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs font-semibold tracking-[0.3em] uppercase">Students</p>
                            <div className="flex items-center gap-2">
                                <AddStudentDialog classId={classData.id} classification={classData.classification} />
                                <Badge variant="outline" className="rounded-full px-3 py-0.5 text-xs">
                                    {students.length} enrolled
                                </Badge>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" size="sm" className="h-8 gap-2">
                                            <IconDownload className="h-3.5 w-3.5" />
                                            <span className="hidden sm:inline">Export</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuLabel>Export Student List</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onClick={() => window.open(`/faculty/classes/${classData.id}/students/export?format=excel`, "_blank")}
                                        >
                                            <IconFileSpreadsheet className="mr-2 h-4 w-4" />
                                            Export to Excel (CSV)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={async () => {
                                                const toastId = "export-student-list-pdf";
                                                toast.loading("Queueing PDF export...", { id: toastId });

                                                try {
                                                    const response = await fetch(`/faculty/classes/${classData.id}/students/export/pdf`, {
                                                        method: "GET",
                                                        headers: {
                                                            Accept: "application/json",
                                                        },
                                                    });

                                                    const payload = (await response.json().catch(() => ({}))) as { message?: string; error?: string };

                                                    if (!response.ok) {
                                                        throw new Error(payload.error || payload.message || "Failed to queue student list PDF export.");
                                                    }

                                                    toast.success("PDF export queued", {
                                                        id: toastId,
                                                        description: payload.message || "You'll get a notification once your PDF is ready.",
                                                    });
                                                } catch (error: unknown) {
                                                    const message = error instanceof Error ? error.message : "An unexpected error occurred.";
                                                    toast.error("Export failed", {
                                                        id: toastId,
                                                        description: message,
                                                    });
                                                }
                                            }}
                                        >
                                            <IconFileTypePdf className="mr-2 h-4 w-4" />
                                            Export to PDF
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                        {students.length === 0 ? (
                            <div className="border-border/60 text-muted-foreground rounded-lg border border-dashed p-6 text-center">
                                No students enrolled yet.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {students.map((student) => (
                                    <div
                                        key={student.id}
                                        className="border-border/60 bg-background/60 flex items-center justify-between rounded-xl border p-3"
                                    >
                                        <div className="flex flex-1 items-center gap-3">
                                            <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-full">
                                                <span className="text-sm font-semibold">{student.name.charAt(0).toUpperCase()}</span>
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="text-foreground text-sm font-semibold">{student.name}</p>
                                                    <span className="text-muted-foreground text-xs">•</span>
                                                    <p className="text-muted-foreground font-mono text-xs">ID: {student.student_id}</p>
                                                </div>
                                                <p className="text-muted-foreground text-xs">{student.email ?? "No email available"}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                className={cn(
                                                    "rounded-full px-3 py-0.5 text-[10px] uppercase",
                                                    student.status === "Active"
                                                        ? "bg-emerald-500/15 text-emerald-600"
                                                        : "bg-amber-500/15 text-amber-600",
                                                )}
                                            >
                                                {student.status}
                                            </Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full">
                                                        <IconDotsVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-56">
                                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                    <DropdownMenuItem onSelect={() => onViewProfile(student)}>
                                                        <IconUser className="mr-2 h-4 w-4" />
                                                        View Profile
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onSelect={() => {
                                                            setSelectedStudentForMove({
                                                                id: student.student_db_id || student.id,
                                                                name: student.name,
                                                            });
                                                            setMoveStudentOpen(true);
                                                        }}
                                                    >
                                                        <IconExchange className="mr-2 h-4 w-4" />
                                                        Request Transfer
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        className="text-destructive focus:text-destructive"
                                                        onSelect={(e) => {
                                                            e.preventDefault();
                                                            setStudentPendingRemoval({ id: student.student_db_id || student.id, name: student.name });
                                                        }}
                                                    >
                                                        <IconTrash className="mr-2 h-4 w-4" />
                                                        Remove from Class
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => window.open(`mailto:${student.email}`, "_blank")}
                                                        disabled={!student.email}
                                                    >
                                                        <IconMail className="mr-2 h-4 w-4" />
                                                        Email Student
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => {
                                                            toast.info(`Notification feature for ${student.name} coming soon`);
                                                        }}
                                                    >
                                                        <IconBell className="mr-2 h-4 w-4" />
                                                        Notify Student
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => onViewPublicInfo(student)}>
                                                        <IconEye className="mr-2 h-4 w-4" />
                                                        View Public Info
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => {
                                                            toast.info(`Grade management for ${student.name} coming soon`);
                                                        }}
                                                    >
                                                        <IconFileInfo className="mr-2 h-4 w-4" />
                                                        Manage Grades
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => onTrackAttendance(student.id as number)}>
                                                        <IconUserCheck className="mr-2 h-4 w-4" />
                                                        Track Attendance
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => {
                                                            toast.info(`Additional actions menu coming soon`);
                                                        }}
                                                        className="text-muted-foreground"
                                                    >
                                                        More Actions...
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            <MoveStudentDialog
                open={moveStudentOpen}
                onOpenChange={setMoveStudentOpen}
                classId={classData.id}
                studentId={selectedStudentForMove?.id || null}
                studentName={selectedStudentForMove?.name}
            />

            <AlertDialog open={!!studentPendingRemoval} onOpenChange={(open) => !open && setStudentPendingRemoval(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove student from class?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to remove <span className="text-foreground font-medium">{studentPendingRemoval?.name}</span>? This
                            action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => {
                                if (studentPendingRemoval) {
                                    // Use route helper if available or raw string
                                    // Assuming route() helper is available or we use raw url
                                    router.delete(`/faculty/classes/${classData.id}/students/${studentPendingRemoval.id}`, {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            toast.success("Student removed successfully");
                                            setStudentPendingRemoval(null);
                                        },
                                        onError: () => toast.error("Failed to remove student"),
                                    });
                                }
                            }}
                        >
                            Remove Student
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
