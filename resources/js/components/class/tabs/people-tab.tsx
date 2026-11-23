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
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { StudentEntry, TeacherEntry } from "@/types/class-detail-types";
import { router } from "@inertiajs/react";
import {
    IconDotsVertical,
    IconDownload,
    IconExchange,
    IconFileInfo,
    IconFileSpreadsheet,
    IconFileTypePdf,
    IconMail,
    IconSearch,
    IconTrash,
    IconUser,
    IconUserCheck,
    IconX,
} from "@tabler/icons-react";
import { useMemo, useState } from "react";
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
    onManageGrades?: (student: StudentEntry) => void;
}

export function PeopleTab({ classData, teacher, students, onViewProfile, onTrackAttendance, onManageGrades }: PeopleTabProps) {
    const [moveStudentOpen, setMoveStudentOpen] = useState(false);
    const [selectedStudentForMove, setSelectedStudentForMove] = useState<{ id: number | string; name: string } | null>(null);
    const [studentPendingRemoval, setStudentPendingRemoval] = useState<{ id: number | string; name: string } | null>(null);
    const [searchQuery, setSearchQuery] = useState("");

    const filteredStudents = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return students;
        return students.filter((student) => {
            const name = student.name.toLowerCase();
            const studentId = String(student.student_id ?? "").toLowerCase();
            const email = (student.email ?? "").toLowerCase();
            return name.includes(query) || studentId.includes(query) || email.includes(query);
        });
    }, [students, searchQuery]);

    return (
        <div className="space-y-6">
            <Card className="border-border/70 bg-card/90 rounded-lg shadow-sm">
                <CardHeader className="px-4 sm:px-6">
                    <CardTitle>People</CardTitle>
                    <CardDescription>Teacher and enrolled students</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6 px-4 sm:px-6">
                    {/* Teacher Section */}
                    <div className="space-y-3">
                        <p className="text-muted-foreground text-xs font-semibold tracking-[0.3em] uppercase">Teacher</p>
                        <div className="border-border/60 bg-background/60 flex min-w-0 items-center gap-3 rounded-lg border p-3 sm:gap-4 sm:p-4">
                            <div className="bg-primary/10 text-primary flex size-14 items-center justify-center overflow-hidden rounded-full">
                                {teacher.photo_url ? (
                                    <img src={teacher.photo_url} alt={teacher.name} className="h-full w-full object-cover" />
                                ) : (
                                    <span className="text-lg font-semibold">{teacher.name.charAt(0).toUpperCase()}</span>
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-foreground truncate text-base font-semibold">{teacher.name}</p>
                                {teacher.email && <p className="text-muted-foreground truncate text-sm">{teacher.email}</p>}
                                {teacher.department && <p className="text-muted-foreground text-xs">{teacher.department}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Students Section */}
                    <div className="space-y-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-muted-foreground text-xs font-semibold tracking-[0.3em] uppercase">Students</p>
                            <div className="flex flex-wrap items-center gap-2">
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
                                                        throw new Error(
                                                            payload.error || payload.message || "Failed to queue student list PDF export.",
                                                        );
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
                        {students.length > 0 && (
                            <div className="relative">
                                <IconSearch className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    type="search"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Filter roster by name, student ID, or email..."
                                    aria-label="Filter enrolled students"
                                    className="pr-9 pl-9"
                                />
                                {searchQuery && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setSearchQuery("")}
                                        aria-label="Clear student filter"
                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-1 size-7 -translate-y-1/2"
                                    >
                                        <IconX className="size-3.5" />
                                    </Button>
                                )}
                            </div>
                        )}
                        {students.length === 0 ? (
                            <div className="border-border/60 text-muted-foreground rounded-lg border border-dashed p-6 text-center">
                                No students enrolled yet.
                            </div>
                        ) : filteredStudents.length === 0 ? (
                            <div className="border-border/60 text-muted-foreground flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center">
                                <p>No students match &ldquo;{searchQuery}&rdquo;.</p>
                                <Button type="button" variant="outline" size="sm" onClick={() => setSearchQuery("")}>
                                    Clear filter
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {filteredStudents.map((student) => (
                                    <div
                                        key={student.id}
                                        className="border-border/60 bg-background/60 flex min-w-0 items-start gap-3 rounded-lg border p-3 sm:items-center"
                                    >
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-full">
                                                <span className="text-sm font-semibold">{student.name.charAt(0).toUpperCase()}</span>
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex min-w-0 flex-col sm:flex-row sm:items-center sm:gap-2">
                                                    <p className="text-foreground truncate text-sm font-semibold">{student.name}</p>
                                                    <p className="text-muted-foreground truncate font-mono text-xs">ID: {student.student_id}</p>
                                                </div>
                                                <p className="text-muted-foreground truncate text-xs">{student.email ?? "No email available"}</p>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            <Badge
                                                className={cn(
                                                    "hidden rounded-full px-2 py-0.5 text-[10px] uppercase sm:inline-flex",
                                                    student.status === "Active"
                                                        ? "bg-emerald-500/15 text-emerald-600"
                                                        : "bg-amber-500/15 text-amber-600",
                                                )}
                                            >
                                                {student.status}
                                            </Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 rounded-full"
                                                        aria-label={`Actions for ${student.name}`}
                                                    >
                                                        <IconDotsVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-56">
                                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                    <DropdownMenuItem onSelect={() => onViewProfile(student)}>
                                                        <IconUser className="mr-2 h-4 w-4" />
                                                        View Profile
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => window.open(`mailto:${student.email}`, "_blank")}
                                                        disabled={!student.email}
                                                    >
                                                        <IconMail className="mr-2 h-4 w-4" />
                                                        Email Student
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    {onManageGrades && (
                                                        <DropdownMenuItem onSelect={() => onManageGrades(student)}>
                                                            <IconFileInfo className="mr-2 h-4 w-4" />
                                                            Manage Grades
                                                        </DropdownMenuItem>
                                                    )}
                                                    <DropdownMenuItem onSelect={() => onTrackAttendance(student.id as number)}>
                                                        <IconUserCheck className="mr-2 h-4 w-4" />
                                                        Track Attendance
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
