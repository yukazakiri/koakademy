import { GradeSheet } from "@/components/grade-sheet";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import axios from "axios";
import { useState } from "react";
import { toast } from "sonner";

interface ClassOption {
    id: number | string;
    subject_code: string;
    subject_title: string;
    section: string;
    classification?: string;
}

interface StudentRow {
    id: number;
    name: string;
    student_id: string;
    grades: {
        prelim: number | null;
        midterm: number | null;
        final: number | null;
        average: number | null;
    };
}

interface GradeStudentsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classes: ClassOption[];
}

export function GradeStudentsModal({ open, onOpenChange, classes }: GradeStudentsModalProps) {
    const [selectedClassId, setSelectedClassId] = useState<string>("");
    const [students, setStudents] = useState<StudentRow[]>([]);
    const [autoAverage, setAutoAverage] = useState(true);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedClass, setSelectedClass] = useState<ClassOption | null>(null);

    const handleClassChange = async (classId: string) => {
        setSelectedClassId(classId);
        setIsLoading(true);
        setStudents([]);

        const classItem = classes.find((c) => c.id.toString() === classId);
        setSelectedClass(classItem || null);

        try {
            const response = await axios.get(`/faculty/classes/${classId}/quick-action-data`);
            const data = response.data;

            // Transform students data for GradeSheet
            const transformedStudents: StudentRow[] = (data.students || []).map((student: any) => ({
                id: student.id,
                name: student.name,
                student_id: student.student_id,
                grades: {
                    prelim: student.grades?.prelim ?? null,
                    midterm: student.grades?.midterm ?? null,
                    final: student.grades?.final ?? null,
                    average: student.grades?.average ?? null,
                },
            }));

            setStudents(transformedStudents);
            setAutoAverage(data.auto_average ?? true);
        } catch (error) {
            console.error("Failed to fetch class data:", error);
            toast.error("Failed to load class data");
        } finally {
            setIsLoading(false);
        }
    };

    const handleClose = () => {
        setSelectedClassId("");
        setStudents([]);
        setSelectedClass(null);
        onOpenChange(false);
    };

    // Filter out SHS classes (grades managed in LIS)
    const gradeableClasses = classes.filter((c) => c.classification !== "shs");

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        Grade Students
                        {selectedClass && (
                            <Badge variant="secondary" className="ml-2">
                                {selectedClass.subject_code} - {selectedClass.section}
                            </Badge>
                        )}
                    </DialogTitle>
                    <DialogDescription>Select a class to view and edit student grades.</DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Class Selector */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Select Class</label>
                        <Select value={selectedClassId} onValueChange={handleClassChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a class to grade..." />
                            </SelectTrigger>
                            <SelectContent>
                                {gradeableClasses.length === 0 ? (
                                    <div className="text-muted-foreground p-4 text-center text-sm">No gradeable classes available</div>
                                ) : (
                                    gradeableClasses.map((classItem) => (
                                        <SelectItem key={classItem.id} value={classItem.id.toString()}>
                                            {classItem.subject_code} - {classItem.section} ({classItem.subject_title})
                                        </SelectItem>
                                    ))
                                )}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Loading State */}
                    {isLoading && (
                        <div className="space-y-4">
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-64 w-full" />
                        </div>
                    )}

                    {/* Grade Sheet */}
                    {!isLoading && selectedClassId && students.length > 0 && (
                        <GradeSheet
                            classId={parseInt(selectedClassId)}
                            students={students.map((s) => ({
                                id: s.id,
                                name: s.name,
                                studentId: s.student_id,
                                grades: s.grades,
                            }))}
                            autoAverageDefault={autoAverage}
                        />
                    )}

                    {/* Empty State */}
                    {!isLoading && selectedClassId && students.length === 0 && (
                        <div className="rounded-lg border border-dashed p-8 text-center">
                            <p className="text-muted-foreground">No students enrolled in this class yet.</p>
                        </div>
                    )}

                    {/* Initial State */}
                    {!selectedClassId && (
                        <div className="rounded-lg border border-dashed p-8 text-center">
                            <p className="text-muted-foreground">Select a class above to start grading students.</p>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
