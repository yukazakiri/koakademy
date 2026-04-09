import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Combobox } from "@/components/ui/combobox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { router, useForm } from "@inertiajs/react";
import axios from "axios";
import { ArrowRightLeft, BookOpen, CheckCircle, ChevronRight, ShieldCheck } from "lucide-react";
import { type FormEvent, useMemo, useState } from "react";
import { toast } from "sonner";
import type { StudentDetail, StudentOptions } from "../types";

declare const route: any;

type DialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    student: StudentDetail;
};

type DialogWithOptionsProps = DialogProps & {
    options: StudentOptions;
};

export function UpdateStatusDialog({ open, onOpenChange, student, options }: DialogWithOptionsProps) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        status: student.status || "enrolled",
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(route("administrators.students.update-status", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                toast.success("Student status updated");
                reset();
            },
            onError: () => toast.error("Failed to update status"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Update Student Status</DialogTitle>
                    <DialogDescription>Change the current status of the student.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select value={data.status} onValueChange={(value) => setData("status", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select status..." />
                            </SelectTrigger>
                            <SelectContent>
                                {options.statuses.map((status) => (
                                    <SelectItem key={status.value} value={status.value}>
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.status && <span className="text-destructive text-xs">{errors.status}</span>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Update Status
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function UpdateIdDialog({ open, onOpenChange, student }: DialogProps) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        new_student_id: "",
        confirm_operation: false,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(route("administrators.students.update-id", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                toast.success("Student ID updated");
                reset();
                router.reload();
            },
            onError: () => toast.error("Failed to update ID"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Update Student ID</DialogTitle>
                    <DialogDescription>This will update the student ID and all related records. This action cannot be undone.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Current ID</Label>
                        <Input value={student.student_id || "Not set"} disabled />
                    </div>
                    <div className="space-y-2">
                        <Label>New Student ID</Label>
                        <Input
                            value={data.new_student_id}
                            onChange={(event) => setData("new_student_id", event.target.value)}
                            type="number"
                            placeholder="Enter 6-digit ID"
                        />
                        {errors.new_student_id && <span className="text-destructive text-xs">{errors.new_student_id}</span>}
                    </div>
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="confirm_op"
                            checked={data.confirm_operation}
                            onCheckedChange={(checked) => setData("confirm_operation", Boolean(checked))}
                        />
                        <Label htmlFor="confirm_op">I confirm this operation</Label>
                    </div>
                    {errors.confirm_operation && <span className="text-destructive text-xs">{errors.confirm_operation}</span>}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} variant="destructive">
                            Update ID
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function UndoIdDialog({ open, onOpenChange, student, options }: DialogWithOptionsProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        change_log_id: "",
        confirm_undo: false,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route("administrators.students.undo-id-change", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                toast.success("ID change undone");
                reset();
                router.reload();
            },
            onError: () => toast.error("Failed to undo ID change"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Undo Student ID Change</DialogTitle>
                    <DialogDescription>Select a change log to revert.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Select Change to Undo</Label>
                        <Select value={data.change_log_id} onValueChange={(value) => setData("change_log_id", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select change log..." />
                            </SelectTrigger>
                            <SelectContent>
                                {options.id_changes.map((change) => (
                                    <SelectItem key={change.value} value={String(change.value)}>
                                        {change.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.change_log_id && <span className="text-destructive text-xs">{errors.change_log_id}</span>}
                    </div>
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="confirm_undo"
                            checked={data.confirm_undo}
                            onCheckedChange={(checked) => setData("confirm_undo", Boolean(checked))}
                        />
                        <Label htmlFor="confirm_undo">I confirm this undo operation</Label>
                    </div>
                    {errors.confirm_undo && <span className="text-destructive text-xs">{errors.confirm_undo}</span>}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} variant="destructive">
                            Undo Change
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ChangeCourseDialog({ open, onOpenChange, student, options }: DialogWithOptionsProps) {
    const [targetCurriculum, setTargetCurriculum] = useState<any[]>([]);
    const [loadingCurriculum, setLoadingCurriculum] = useState(false);
    const [credits, setCredits] = useState<Record<number, number>>({});

    const { data, setData, reset } = useForm({
        course_id: student.course?.id ? String(student.course.id) : "",
        credits: [] as any[],
    });

    const passedSubjects = useMemo(() => {
        const subjects: any[] = [];
        student.checklist.forEach((year: any) => {
            year.semesters.forEach((semester: any) => {
                semester.subjects.forEach((subject: any) => {
                    if ((subject.grade && subject.grade !== "-" && subject.grade !== null) || subject.status === "Completed") {
                        subjects.push(subject);
                    }
                });
            });
        });

        return subjects;
    }, [student.checklist]);

    const passedSubjectOptions = useMemo(
        () =>
            passedSubjects.map((subject) => ({
                value: String(subject.id),
                label: `${subject.code} - ${subject.title} (${subject.grade})`,
            })),
        [passedSubjects],
    );

    const handleCourseChange = async (courseId: string) => {
        setData("course_id", courseId);
        if (!courseId) {
            setTargetCurriculum([]);
            return;
        }

        setLoadingCurriculum(true);
        try {
            const response = await axios.get(route("administrators.students.courses.subjects", courseId));
            setTargetCurriculum(response.data);

            const newCredits: Record<number, number> = {};
            const flatTargets: any[] = [];
            response.data.forEach((year: any) => year.semesters.forEach((semester: any) => flatTargets.push(...semester.subjects)));

            flatTargets.forEach((targetSubject) => {
                const match = passedSubjects.find((subject) => subject.code === targetSubject.code);
                if (match) {
                    newCredits[targetSubject.id] = match.id;
                }
            });

            setCredits(newCredits);
        } catch {
            toast.error("Failed to load curriculum");
        } finally {
            setLoadingCurriculum(false);
        }
    };

    const stats = useMemo(() => {
        let totalUnits = 0;
        let creditedUnits = 0;
        let totalSubjects = 0;
        let creditedSubjectsCount = 0;

        targetCurriculum.forEach((year) =>
            year.semesters.forEach((semester: any) =>
                semester.subjects.forEach((subject: any) => {
                    totalUnits += Number(subject.units) || 0;
                    totalSubjects++;
                    if (credits[subject.id]) {
                        creditedUnits += Number(subject.units) || 0;
                        creditedSubjectsCount++;
                    }
                }),
            ),
        );

        return {
            totalUnits,
            creditedUnits,
            totalSubjects,
            creditedSubjectsCount,
            percent: totalUnits > 0 ? Math.round((creditedUnits / totalUnits) * 100) : 0,
            remainingUnits: totalUnits - creditedUnits,
        };
    }, [credits, targetCurriculum]);

    const handleSubmit = () => {
        const creditsArray = Object.entries(credits).map(([targetId, sourceId]) => ({
            target_subject_id: Number.parseInt(targetId, 10),
            source_subject_id: sourceId,
        }));

        router.patch(
            route("administrators.students.change-course", student.id),
            {
                course_id: data.course_id,
                credits: creditsArray,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    toast.success("Course changed and subjects credited");
                    reset();
                    router.reload();
                },
                onError: () => toast.error("Failed to change course"),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex h-[95vh] min-w-[95vw] flex-col gap-0 overflow-hidden p-0">
                <div className="bg-muted/20 flex flex-shrink-0 items-center justify-between border-b px-6 py-4">
                    <div>
                        <DialogTitle className="text-xl">Shift Course & Credit Subjects</DialogTitle>
                        <DialogDescription>Select a new course and map existing subjects to the new curriculum.</DialogDescription>
                    </div>
                    <div className="w-1/3 min-w-[300px]">
                        <Select value={data.course_id} onValueChange={handleCourseChange}>
                            <SelectTrigger className="bg-background border-primary/20 focus:ring-primary/20 h-10 shadow-sm">
                                <SelectValue placeholder="Select New Course..." />
                            </SelectTrigger>
                            <SelectContent className="max-h-[400px]">
                                {options.courses.map((course) => (
                                    <SelectItem key={course.value} value={String(course.value)}>
                                        {course.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="flex flex-1 overflow-hidden">
                    <div className="bg-muted/10 flex w-80 flex-shrink-0 flex-col space-y-8 overflow-y-auto border-r p-6">
                        <div className="space-y-3">
                            <h4 className="text-muted-foreground text-xs font-bold tracking-wider uppercase">Impact Summary</h4>
                            <Card className="bg-background/50 border-none shadow-sm">
                                <CardContent className="space-y-5 p-5">
                                    <div>
                                        <div className="mb-2 flex justify-between text-sm">
                                            <span className="text-muted-foreground font-medium">Credited Progress</span>
                                            <span className="font-bold">{stats.percent}%</span>
                                        </div>
                                        <Progress value={stats.percent} className="h-2.5" />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3 text-center">
                                        <div className="bg-primary/10 border-primary/20 rounded-lg border p-3">
                                            <div className="text-primary text-2xl font-bold">{stats.creditedUnits}</div>
                                            <div className="text-primary/80 text-[10px] font-bold tracking-wide uppercase">Credited Units</div>
                                        </div>
                                        <div className="bg-secondary border-border rounded-lg border p-3">
                                            <div className="text-secondary-foreground text-2xl font-bold">{stats.remainingUnits}</div>
                                            <div className="text-muted-foreground text-[10px] font-bold tracking-wide uppercase">Remaining</div>
                                        </div>
                                    </div>
                                    <div className="text-muted-foreground border-t pt-2 text-center text-xs">
                                        {stats.creditedSubjectsCount} of {stats.totalSubjects} subjects matched
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="flex min-h-0 flex-1 flex-col space-y-3">
                            <h4 className="text-muted-foreground flex items-center justify-between text-xs font-bold tracking-wider uppercase">
                                <span>Passed Subjects (Source)</span>
                                <Badge variant="secondary">{passedSubjects.length}</Badge>
                            </h4>
                            <ScrollArea className="bg-background flex-1 rounded-lg border">
                                <div className="space-y-2 p-2">
                                    {passedSubjects.map((subject) => (
                                        <div
                                            key={subject.id}
                                            className="border-border bg-card hover:bg-accent hover:text-accent-foreground flex flex-col rounded border p-2.5 text-xs transition-colors"
                                        >
                                            <div className="flex items-center justify-between font-mono font-bold">
                                                <span className="text-primary">{subject.code}</span>
                                                <Badge variant="outline" className="h-4 px-1 text-[10px]">
                                                    {subject.grade}
                                                </Badge>
                                            </div>
                                            <div className="text-muted-foreground mt-1 line-clamp-2 text-[10px]">{subject.title}</div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        </div>
                    </div>

                    <div className="bg-background relative flex flex-1 flex-col overflow-hidden">
                        {loadingCurriculum ? (
                            <div className="bg-background/80 text-muted-foreground absolute inset-0 z-50 flex flex-col items-center justify-center gap-4 backdrop-blur-sm">
                                <div className="border-primary h-10 w-10 animate-spin rounded-full border-b-2"></div>
                                <span className="text-sm font-medium">Fetching new curriculum...</span>
                            </div>
                        ) : targetCurriculum.length === 0 ? (
                            <div className="text-muted-foreground flex flex-1 flex-col items-center justify-center p-12 text-center opacity-60">
                                <BookOpen className="mb-6 h-24 w-24 stroke-1" />
                                <p className="text-xl font-medium">No Curriculum Loaded</p>
                                <p className="mt-2 text-sm">Select a target course from the dropdown above to begin mapping subjects.</p>
                            </div>
                        ) : (
                            <div className="flex-1 overflow-auto">
                                <div className="min-w-[800px] space-y-10 p-8 pb-20">
                                    {targetCurriculum.map((yearGroup: any) => (
                                        <div key={yearGroup.year} className="space-y-6">
                                            <div className="sticky left-0 flex items-center gap-4">
                                                <div className="bg-primary text-primary-foreground flex h-10 w-10 items-center justify-center rounded-full text-lg font-bold shadow-sm">
                                                    {yearGroup.year}
                                                </div>
                                                <h3 className="text-foreground text-2xl font-bold">
                                                    {yearGroup.year === 1
                                                        ? "First Year"
                                                        : yearGroup.year === 2
                                                          ? "Second Year"
                                                          : yearGroup.year === 3
                                                            ? "Third Year"
                                                            : yearGroup.year === 4
                                                              ? "Fourth Year"
                                                              : `${yearGroup.year}th Year`}
                                                </h3>
                                            </div>

                                            <div className="grid grid-cols-1 gap-8 xl:grid-cols-2">
                                                {yearGroup.semesters.map((semesterGroup: any) => (
                                                    <Card key={semesterGroup.semester} className="overflow-hidden border shadow-sm">
                                                        <div className="bg-muted/30 flex items-center justify-between border-b px-6 py-3">
                                                            <h4 className="text-foreground text-sm font-bold tracking-wider uppercase">
                                                                {semesterGroup.semester === 1
                                                                    ? "1st Semester"
                                                                    : semesterGroup.semester === 2
                                                                      ? "2nd Semester"
                                                                      : "Summer"}
                                                            </h4>
                                                            <Badge variant="outline" className="bg-background">
                                                                {semesterGroup.subjects.reduce(
                                                                    (accumulator: number, subject: any) => accumulator + (Number(subject.units) || 0),
                                                                    0,
                                                                )}{" "}
                                                                Units
                                                            </Badge>
                                                        </div>
                                                        <Table>
                                                            <TableHeader>
                                                                <TableRow className="hover:bg-transparent">
                                                                    <TableHead className="w-[45%] pl-6">New Curriculum (Target)</TableHead>
                                                                    <TableHead className="w-[10%] text-center">Status</TableHead>
                                                                    <TableHead className="w-[45%] pr-6">Credited Subject (Source)</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody>
                                                                {semesterGroup.subjects.map((targetSubject: any) => {
                                                                    const creditSourceId = credits[targetSubject.id];
                                                                    const creditSource = passedSubjects.find(
                                                                        (subject) => subject.id === creditSourceId,
                                                                    );
                                                                    const isCredited = Boolean(creditSource);

                                                                    return (
                                                                        <TableRow
                                                                            key={targetSubject.id}
                                                                            className={cn(
                                                                                "transition-colors",
                                                                                isCredited ? "bg-primary/5 hover:bg-primary/10" : "hover:bg-muted/30",
                                                                            )}
                                                                        >
                                                                            <TableCell className="py-3 pl-6 align-top">
                                                                                <div className="flex flex-col gap-1">
                                                                                    <div className="flex items-center gap-2">
                                                                                        <span
                                                                                            className={cn(
                                                                                                "font-mono text-sm font-bold",
                                                                                                isCredited ? "text-primary" : "",
                                                                                            )}
                                                                                        >
                                                                                            {targetSubject.code}
                                                                                        </span>
                                                                                        <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                                                                                            {targetSubject.units}u
                                                                                        </Badge>
                                                                                    </div>
                                                                                    <span
                                                                                        className="text-muted-foreground line-clamp-2 text-xs font-medium"
                                                                                        title={targetSubject.title}
                                                                                    >
                                                                                        {targetSubject.title}
                                                                                    </span>
                                                                                </div>
                                                                            </TableCell>

                                                                            <TableCell className="py-3 text-center align-middle">
                                                                                {isCredited ? (
                                                                                    <div className="flex justify-center">
                                                                                        <div className="bg-primary/20 text-primary flex h-8 w-8 items-center justify-center rounded-full shadow-sm">
                                                                                            <CheckCircle className="h-5 w-5" />
                                                                                        </div>
                                                                                    </div>
                                                                                ) : (
                                                                                    <div className="flex justify-center opacity-20">
                                                                                        <ArrowRightLeft className="h-5 w-5" />
                                                                                    </div>
                                                                                )}
                                                                            </TableCell>

                                                                            <TableCell className="py-3 pr-6 align-top">
                                                                                <Combobox
                                                                                    options={passedSubjectOptions}
                                                                                    value={creditSourceId ? String(creditSourceId) : ""}
                                                                                    onValueChange={(value) => {
                                                                                        const sourceId = value
                                                                                            ? Number.parseInt(value, 10)
                                                                                            : undefined;
                                                                                        setCredits((previous) => {
                                                                                            const next = { ...previous };
                                                                                            if (sourceId) {
                                                                                                next[targetSubject.id] = sourceId;
                                                                                            } else {
                                                                                                delete next[targetSubject.id];
                                                                                            }
                                                                                            return next;
                                                                                        });
                                                                                    }}
                                                                                    placeholder="Select subject to credit..."
                                                                                    emptyText="No matching subject found."
                                                                                    searchPlaceholder="Search passed..."
                                                                                    className={cn(
                                                                                        "h-9 text-xs",
                                                                                        isCredited
                                                                                            ? "border-primary/50 ring-primary/20 bg-background"
                                                                                            : "bg-muted/20",
                                                                                    )}
                                                                                />
                                                                                {isCredited && (
                                                                                    <div className="bg-background border-primary/20 mt-2 flex items-center justify-between rounded border p-1.5 text-xs shadow-sm">
                                                                                        <div className="flex flex-col gap-0.5 leading-none">
                                                                                            <span className="text-primary text-[10px] font-bold uppercase">
                                                                                                Source
                                                                                            </span>
                                                                                            <span className="text-foreground font-mono font-medium">
                                                                                                {creditSource.code}
                                                                                            </span>
                                                                                        </div>
                                                                                        <div className="flex flex-col gap-0.5 text-right leading-none">
                                                                                            <span className="text-primary text-[10px] font-bold uppercase">
                                                                                                Grade
                                                                                            </span>
                                                                                            <span className="text-foreground bg-primary/10 rounded px-1.5 py-0.5 font-bold">
                                                                                                {creditSource.grade}
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                )}
                                                                            </TableCell>
                                                                        </TableRow>
                                                                    );
                                                                })}
                                                            </TableBody>
                                                        </Table>
                                                    </Card>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter className="bg-background z-20 flex flex-shrink-0 items-center justify-between border-t p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <ShieldCheck className="h-4 w-4" />
                        <span>
                            {stats.creditedSubjectsCount > 0
                                ? `${stats.creditedSubjectsCount} subjects will be transferred.`
                                : "No subjects will be transferred. Student will start fresh."}
                        </span>
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={handleSubmit} disabled={!data.course_id || loadingCurriculum} className="gap-2 px-6">
                            Confirm Shift Course <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function RetryEnrollmentDialog({ open, onOpenChange, student, options }: DialogWithOptionsProps) {
    const { data, setData, post, processing, reset } = useForm({
        enrollment_id: "all",
        force_enrollment: true,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route("administrators.students.retry-enrollment", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                toast.success("Enrollment retry completed");
                reset();
            },
            onError: () => toast.error("Failed to retry enrollment"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retry Class Enrollment</DialogTitle>
                    <DialogDescription>Attempt to re-enroll student in classes.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="flex items-center justify-between space-x-2 rounded-md border p-4">
                        <div className="space-y-0.5">
                            <Label htmlFor="force">Force Enrollment</Label>
                            <div className="text-muted-foreground text-xs">Override class size limits</div>
                        </div>
                        <Switch id="force" checked={data.force_enrollment} onCheckedChange={(checked) => setData("force_enrollment", checked)} />
                    </div>

                    <div className="space-y-2">
                        <Label>Enrollment Scope</Label>
                        <Select value={data.enrollment_id} onValueChange={(value) => setData("enrollment_id", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="All Subjects (Default)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Subjects</SelectItem>
                                {options.enrollment_ids.map((enrollmentId) => (
                                    <SelectItem key={enrollmentId.value} value={String(enrollmentId.value)}>
                                        {enrollmentId.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">Select a specific enrollment ID or keep “All Subjects”.</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Retry Enrollment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function UpdateTuitionDialog({ open, onOpenChange, student }: DialogProps) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        total_lectures: student.tuition?.total_lectures.replace(/[^0-9.]/g, "") || 0,
        total_laboratory: student.tuition?.total_laboratory.replace(/[^0-9.]/g, "") || 0,
        total_miscelaneous_fees: student.tuition?.total_miscelaneous_fees.replace(/[^0-9.]/g, "") || 3500,
        downpayment: student.tuition?.downpayment.replace(/[^0-9.]/g, "") || 0,
        discount: student.tuition?.discount.replace(/[^0-9.]/g, "") || 0,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(route("administrators.students.update-tuition", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                toast.success("Tuition updated");
                reset();
            },
            onError: () => toast.error("Failed to update tuition"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Manage Tuition</DialogTitle>
                    <DialogDescription>Update fees for current semester.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Lecture Fees</Label>
                            <Input type="number" value={data.total_lectures} onChange={(event) => setData("total_lectures", event.target.value)} />
                            {errors.total_lectures && <span className="text-destructive text-xs">{errors.total_lectures}</span>}
                        </div>
                        <div className="space-y-2">
                            <Label>Lab Fees</Label>
                            <Input
                                type="number"
                                value={data.total_laboratory}
                                onChange={(event) => setData("total_laboratory", event.target.value)}
                            />
                            {errors.total_laboratory && <span className="text-destructive text-xs">{errors.total_laboratory}</span>}
                        </div>
                        <div className="space-y-2">
                            <Label>Misc Fees</Label>
                            <Input
                                type="number"
                                value={data.total_miscelaneous_fees}
                                onChange={(event) => setData("total_miscelaneous_fees", event.target.value)}
                            />
                            {errors.total_miscelaneous_fees && <span className="text-destructive text-xs">{errors.total_miscelaneous_fees}</span>}
                        </div>
                    </div>
                    <Separator />
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Downpayment</Label>
                            <Input type="number" value={data.downpayment} onChange={(event) => setData("downpayment", event.target.value)} />
                            {errors.downpayment && <span className="text-destructive text-xs">{errors.downpayment}</span>}
                        </div>
                        <div className="space-y-2">
                            <Label>Discount (%)</Label>
                            <Input type="number" value={data.discount} onChange={(event) => setData("discount", event.target.value)} />
                            {errors.discount && <span className="text-destructive text-xs">{errors.discount}</span>}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save Tuition
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ManageClearanceDialog({ open, onOpenChange, student }: DialogProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        is_cleared: student.current_clearance?.is_cleared || false,
        remarks: student.current_clearance?.remarks || "",
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route("administrators.students.manage-clearance", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                toast.success("Clearance updated");
                reset();
            },
            onError: () => toast.error("Failed to update clearance"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Manage Clearance</DialogTitle>
                    <DialogDescription>Update clearance status for current semester.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="flex items-center justify-between space-x-2 rounded-md border p-4">
                        <div className="space-y-0.5">
                            <Label>Is Cleared?</Label>
                            <div className="text-muted-foreground text-xs">Mark student as cleared</div>
                        </div>
                        <Switch checked={data.is_cleared} onCheckedChange={(checked) => setData("is_cleared", checked)} />
                    </div>
                    <div className="space-y-2">
                        <Label>Remarks</Label>
                        <Textarea value={data.remarks} onChange={(event) => setData("remarks", event.target.value)} placeholder="Optional remarks" />
                        {errors.remarks && <span className="text-destructive text-xs">{errors.remarks}</span>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Update Clearance
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
