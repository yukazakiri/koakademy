import AdministratorCourseSchedulePdfController from "@/actions/App/Http/Controllers/AdministratorCourseSchedulePdfController";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ExternalLink, FileText } from "lucide-react";
import * as React from "react";

type CourseOption = {
    id: number;
    code: string;
    title: string;
};

type ScheduleExportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    courses: CourseOption[];
    initialCourseId: number | null;
};

export default function ScheduleExportDialog({ open, onOpenChange, courses, initialCourseId }: ScheduleExportDialogProps) {
    const [courseId, setCourseId] = React.useState<string>();

    React.useEffect(() => {
        if (!open) return;

        const initialCourse = courses.find((course) => course.id === initialCourseId);
        setCourseId(initialCourse ? String(initialCourse.id) : undefined);
    }, [courses, initialCourseId, open]);

    const selectedCourse = courses.find((course) => String(course.id) === courseId);

    const openPdf = () => {
        if (!selectedCourse) return;

        window.open(AdministratorCourseSchedulePdfController.url({ course: selectedCourse.id }), "_blank", "noopener,noreferrer");
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="bg-primary/10 text-primary mb-1 flex h-10 w-10 items-center justify-center rounded-lg">
                        <FileText className="h-5 w-5" />
                    </div>
                    <DialogTitle>Export program schedule</DialogTitle>
                    <DialogDescription>Generate a formal, print-ready schedule for one program in the current academic period.</DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-2">
                    <div className="space-y-2">
                        <Label htmlFor="schedule-export-course">Program</Label>
                        <Select value={courseId} onValueChange={setCourseId}>
                            <SelectTrigger id="schedule-export-course" className="w-full">
                                <SelectValue placeholder="Select a program" />
                            </SelectTrigger>
                            <SelectContent>
                                {courses.map((course) => (
                                    <SelectItem key={course.id} value={String(course.id)}>
                                        {course.code} — {course.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="bg-muted/60 text-muted-foreground rounded-lg border px-3 py-2.5 text-xs leading-relaxed">
                        A4 portrait · all year levels · one row per section. The PDF opens in a new tab with your browser&apos;s Print and Download
                        controls.
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" disabled={!selectedCourse} onClick={openPdf}>
                        Open PDF
                        <ExternalLink className="ml-1.5 h-3.5 w-3.5" />
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
