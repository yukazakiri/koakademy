import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Combobox } from "@/components/ui/combobox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { IconCalendar, IconCheck, IconChevronLeft, IconChevronRight, IconClipboard, IconSchool, IconUsers } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

interface CreateClassDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    shs_strands: any[];
    rooms: { id: number; name: string }[];
    current_semester: string;
    current_school_year: string;
    current_faculty: { id: string; name: string } | null;
    onSuccess?: () => void;
}

export function CreateClassDialog({
    open,
    onOpenChange,
    shs_strands,
    rooms,
    current_semester,
    current_school_year,
    current_faculty,
    onSuccess,
}: CreateClassDialogProps) {
    const [formStep, setFormStep] = useState(1);
    const [strandSubjects, setStrandSubjects] = useState<any[]>([]);

    const [formData, setFormData] = useState({
        classification: "shs",
        course_codes: [] as string[],
        subject_ids: [] as string[],
        strand_id: "",
        subject_id: "",
        subject_code: "",
        faculty_id: current_faculty?.id || "",
        academic_year: "1",
        grade_level: "Grade 11",
        semester: current_semester || "1",
        school_year: current_school_year || "2024-2025",
        section: "A",
        room_id: "",
        maximum_slots: "40",
    });

    useEffect(() => {
        if (open) {
            setFormStep(1);
            setFormData((prev) => ({
                ...prev,
                semester: current_semester || prev.semester,
                school_year: current_school_year || prev.school_year,
                faculty_id: current_faculty?.id || prev.faculty_id,
            }));
        }
    }, [open, current_semester, current_school_year, current_faculty]);

    function handleInputChange(field: string, value: unknown) {
        setFormData((prev) => ({ ...prev, [field]: value }));
    }

    function handleStrandChange(strandId: string) {
        setFormData((prev) => ({ ...prev, strand_id: strandId, subject_id: "", subject_code: "" }));
        setStrandSubjects([]);

        if (!strandId) return;

        fetch(`/classes/strand-subjects?strand_id=${strandId}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        })
            .then((response) => response.json())
            .then((data) => {
                setStrandSubjects(data.strand_subjects || []);
            })
            .catch((errors) => {
                console.error("Error fetching strand subjects:", errors);
            });
    }

    function handleSubmit() {
        router.post("/classes/create", formData, {
            onSuccess: () => {
                onOpenChange(false);
                setFormStep(1);
                toast.success("Class created successfully");
                onSuccess?.();
                // Reset form data for next use
                setFormData({
                    classification: "shs",
                    course_codes: [],
                    subject_ids: [],
                    strand_id: "",
                    subject_id: "",
                    subject_code: "",
                    faculty_id: current_faculty?.id || "",
                    academic_year: "1",
                    grade_level: "Grade 11",
                    semester: current_semester || "1",
                    school_year: current_school_year || "2024-2025",
                    section: "A",
                    room_id: "",
                    maximum_slots: "40",
                });
                setStrandSubjects([]);
            },
            onError: (errors) => {
                console.error("Error creating class:", errors);
                toast.error("Failed to create class", { description: "Check your inputs." });
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col overflow-hidden p-0 sm:max-w-[900px]">
                <div className="border-border bg-background border-b px-6 py-4">
                    <DialogHeader>
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <DialogTitle className="text-foreground flex items-center gap-2 text-2xl">
                                    <div className="bg-primary/10 rounded-lg p-2">
                                        <IconSchool className="text-primary h-5 w-5" />
                                    </div>
                                    Create New Class
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-sm">
                                    Set up a new SHS class for the current academic year
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="mt-4">
                        <div className="flex items-center justify-between">
                            <div className="flex flex-1 items-center gap-3">
                                <div className={cn("flex flex-1 items-center gap-3", formStep >= 1 ? "text-foreground" : "text-muted-foreground")}>
                                    <div
                                        className={cn(
                                            "flex h-9 w-9 items-center justify-center rounded-full border-2 transition-all",
                                            formStep > 1
                                                ? "bg-primary border-primary text-primary-foreground"
                                                : formStep === 1
                                                  ? "border-primary text-primary"
                                                  : "border-muted-foreground/30",
                                        )}
                                    >
                                        {formStep > 1 ? <IconCheck className="h-5 w-5" /> : "1"}
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-foreground text-sm font-medium">Basic Information</span>
                                        <span className="text-muted-foreground text-xs">Strand & Subject</span>
                                    </div>
                                </div>
                                <IconChevronRight className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                            </div>
                            <div className="flex flex-1 items-center gap-3">
                                <div className={cn("flex flex-1 items-center gap-3", formStep >= 2 ? "text-foreground" : "text-muted-foreground")}>
                                    <div
                                        className={cn(
                                            "flex h-9 w-9 items-center justify-center rounded-full border-2 transition-all",
                                            formStep === 2 ? "border-primary text-primary" : "border-muted-foreground/30",
                                        )}
                                    >
                                        2
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-foreground text-sm font-medium">Schedule & Details</span>
                                        <span className="text-muted-foreground text-xs">Room, Faculty & Capacity</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="bg-muted mt-3 h-1.5 w-full rounded-full">
                            <div
                                className="bg-primary h-full rounded-full transition-all duration-300"
                                style={{ width: formStep === 1 ? "50%" : "100%" }}
                            />
                        </div>
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto">
                    <div className="space-y-6 p-6">
                        <div className="rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4 dark:border-amber-800/30 dark:from-amber-950/30 dark:to-orange-950/30">
                            <div className="flex items-start gap-3">
                                <div className="rounded-full bg-amber-100 p-2 dark:bg-amber-900/50">
                                    <IconClipboard className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div className="flex-1">
                                    <p className="text-sm font-semibold text-amber-900 dark:text-amber-300">SHS Class Creation</p>
                                    <p className="mt-1 text-xs text-amber-800/80 dark:text-amber-400/80">
                                        You're creating a Senior High School class. College class creation will be available soon.
                                    </p>
                                </div>
                                <Badge
                                    variant="outline"
                                    className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-400"
                                >
                                    SHS Only
                                </Badge>
                            </div>
                        </div>

                        {formStep === 1 && (
                            <div className="animate-in fade-in-50 space-y-6 duration-300">
                                <div className="border-primary/20 bg-primary/5 rounded-xl border-2 p-6">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 rounded-lg p-2.5">
                                                <IconSchool className="text-primary h-5 w-5" />
                                            </div>
                                            <div>
                                                <h3 className="text-foreground text-base font-semibold">Class Type</h3>
                                                <p className="text-muted-foreground text-xs">Current available option</p>
                                            </div>
                                        </div>
                                        <Badge className="bg-primary text-primary-foreground px-3 py-1 font-medium">Senior High School</Badge>
                                    </div>
                                    <p className="text-muted-foreground/80 pl-12 text-xs">
                                        SHS classes follow the K-12 curriculum with specialized tracks and strands
                                    </p>
                                </div>

                                <div className="border-border bg-background rounded-xl border shadow-sm">
                                    <div className="border-border border-b p-5">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 rounded-lg p-2">
                                                <IconClipboard className="text-primary h-4 w-4" />
                                            </div>
                                            <h3 className="text-foreground text-base font-semibold">Class Configuration</h3>
                                        </div>
                                    </div>
                                    <div className="space-y-5 p-5">
                                        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Combobox
                                                    label="SHS Strand"
                                                    options={(shs_strands ?? []).map((strand) => ({
                                                        label: strand.strand_name,
                                                        value: strand.id,
                                                        description: strand.track_name || undefined,
                                                    }))}
                                                    value={formData.strand_id}
                                                    onValueChange={handleStrandChange}
                                                    placeholder="Search and select a strand..."
                                                    emptyText="No strands found."
                                                    searchPlaceholder="Search strands..."
                                                    required
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Combobox
                                                    label="Subject"
                                                    options={strandSubjects.map((subject) => ({
                                                        label: `${subject.code} - ${subject.title}`,
                                                        value: subject.id,
                                                        description: subject.description || undefined,
                                                        searchText: subject.title,
                                                    }))}
                                                    value={formData.subject_id}
                                                    onValueChange={(value) => {
                                                        const selectedSubject = strandSubjects.find((s) => s.id === value);
                                                        handleInputChange("subject_id", value);
                                                        if (selectedSubject) {
                                                            handleInputChange("subject_code", selectedSubject.code);
                                                        }
                                                    }}
                                                    placeholder="Search by subject title..."
                                                    emptyText="No subjects found for this strand."
                                                    searchPlaceholder="Search by subject title..."
                                                    disabled={!formData.strand_id}
                                                    required
                                                />
                                            </div>

                                            <div className="space-y-2 md:col-span-2">
                                                <Label htmlFor="subject_code" className="text-foreground flex items-center gap-2 text-sm font-medium">
                                                    <span>Subject Code</span>
                                                </Label>
                                                <Input
                                                    id="subject_code"
                                                    value={formData.subject_code}
                                                    onChange={(e) => handleInputChange("subject_code", e.target.value)}
                                                    placeholder="Auto-populated from subject selection"
                                                    className="bg-background text-foreground h-11 font-mono text-sm"
                                                    readOnly
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="grade_level" className="text-foreground flex items-center gap-2 text-sm font-medium">
                                                    <span>Grade Level</span>
                                                    <span className="text-destructive">*</span>
                                                </Label>
                                                <Select
                                                    value={formData.grade_level}
                                                    onValueChange={(value) => handleInputChange("grade_level", value)}
                                                >
                                                    <SelectTrigger className="bg-background text-foreground h-11">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="Grade 11">
                                                            Grade 11 <span className="text-muted-foreground text-xs">(1st Year)</span>
                                                        </SelectItem>
                                                        <SelectItem value="Grade 12">
                                                            Grade 12 <span className="text-muted-foreground text-xs">(2nd Year)</span>
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="section" className="text-foreground flex items-center gap-2 text-sm font-medium">
                                                    <span>Section</span>
                                                    <span className="text-destructive">*</span>
                                                </Label>
                                                <Select value={formData.section} onValueChange={(value) => handleInputChange("section", value)}>
                                                    <SelectTrigger className="bg-background text-foreground h-11">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {["A", "B", "C", "D", "E", "F"].map((s) => (
                                                            <SelectItem key={s} value={s}>
                                                                Section {s}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {formStep === 2 && (
                            <div className="animate-in fade-in-50 space-y-6 duration-300">
                                <div className="border-border bg-background rounded-xl border shadow-sm">
                                    <div className="border-border border-b p-5">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 rounded-lg p-2">
                                                <IconCalendar className="text-primary h-4 w-4" />
                                            </div>
                                            <h3 className="text-foreground text-base font-semibold">Schedule & Academic Details</h3>
                                        </div>
                                    </div>
                                    <div className="space-y-5 p-5">
                                        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="semester" className="text-foreground flex items-center gap-2 text-sm font-medium">
                                                    <span>Semester</span>
                                                    <span className="text-destructive">*</span>
                                                </Label>
                                                <Select
                                                    value={formData.semester}
                                                    onValueChange={(value) => handleInputChange("semester", value)}
                                                    disabled
                                                >
                                                    <SelectTrigger className="bg-muted text-muted-foreground h-11 opacity-100">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="1">1st Semester</SelectItem>
                                                        <SelectItem value="2">2nd Semester</SelectItem>
                                                        <SelectItem value="summer">Summer Term</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-muted-foreground text-xs">Automatically set based on system settings</p>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="school_year" className="text-foreground flex items-center gap-2 text-sm font-medium">
                                                    <span>School Year</span>
                                                    <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    id="school_year"
                                                    value={formData.school_year}
                                                    onChange={(e) => handleInputChange("school_year", e.target.value)}
                                                    placeholder="e.g., 2024-2025"
                                                    className="bg-muted text-muted-foreground h-11 opacity-100"
                                                    readOnly
                                                />
                                                <p className="text-muted-foreground text-xs">Automatically set based on system settings</p>
                                            </div>

                                            <div className="space-y-2">
                                                <Combobox
                                                    label="Room Assignment"
                                                    options={(rooms ?? []).map((room) => ({ label: room.name, value: String(room.id) }))}
                                                    value={String(formData.room_id)}
                                                    onValueChange={(value) => handleInputChange("room_id", value)}
                                                    placeholder="Search and select a room..."
                                                    emptyText="No rooms found."
                                                    searchPlaceholder="Search rooms..."
                                                    required
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                {/* Faculty selection logic remains same */}
                                                <Label htmlFor="faculty_id" className="text-foreground flex items-center gap-2 text-sm font-medium">
                                                    <span>Assigned Faculty</span>
                                                    <span className="text-destructive">*</span>
                                                </Label>
                                                {current_faculty ? (
                                                    <div className="bg-muted border-input text-muted-foreground flex h-11 items-center rounded-md border px-3 text-sm">
                                                        {current_faculty.name}
                                                    </div>
                                                ) : (
                                                    <Select
                                                        value={formData.faculty_id}
                                                        onValueChange={(value) => handleInputChange("faculty_id", value)}
                                                    >
                                                        <SelectTrigger className="bg-background text-foreground h-11">
                                                            <SelectValue placeholder="Select faculty..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="1">Faculty 1</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                )}
                                            </div>

                                            <div className="space-y-2 md:col-span-2">
                                                <Label
                                                    htmlFor="maximum_slots"
                                                    className="text-foreground flex items-center gap-2 text-sm font-medium"
                                                >
                                                    <IconUsers className="h-4 w-4" />
                                                    <span>Maximum Class Size</span>
                                                    <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    id="maximum_slots"
                                                    type="number"
                                                    value={formData.maximum_slots}
                                                    onChange={(e) => handleInputChange("maximum_slots", e.target.value)}
                                                    placeholder="40"
                                                    min="1"
                                                    max="60"
                                                    className="bg-background text-foreground h-11"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="border-primary/20 from-primary/5 to-primary/10 rounded-xl border bg-gradient-to-br p-5">
                                    <h4 className="text-foreground mb-3 flex items-center gap-2 text-sm font-semibold">
                                        <IconCheck className="text-primary h-4 w-4" />
                                        Class Summary
                                    </h4>
                                    <div className="grid grid-cols-2 gap-3 text-xs">
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground">Strand</p>
                                            <p className="text-foreground font-medium">
                                                {(shs_strands ?? []).find((s) => s.id === formData.strand_id)?.strand_name || "-"}
                                            </p>
                                        </div>
                                        {/* Additional Summary items can go here */}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <div className="border-border bg-background border-t px-6 py-4">
                    <DialogFooter className="flex gap-3">
                        {formStep > 1 ? (
                            <Button variant="outline" onClick={() => setFormStep(1)} className="h-11 flex-1 sm:flex-none">
                                <IconChevronLeft className="mr-2 h-4 w-4" /> Back
                            </Button>
                        ) : (
                            <Button variant="ghost" onClick={() => onOpenChange(false)} className="h-11 flex-1 sm:flex-none">
                                Cancel
                            </Button>
                        )}

                        {formStep === 1 ? (
                            <Button onClick={() => setFormStep(2)} className="h-11 flex-1" disabled={!formData.subject_id || !formData.strand_id}>
                                Continue <IconChevronRight className="ml-2 h-4 w-4" />
                            </Button>
                        ) : (
                            <Button onClick={handleSubmit} className="bg-primary hover:bg-primary/90 h-11 flex-1">
                                <IconSchool className="mr-2 h-4 w-4" /> Create Class
                            </Button>
                        )}
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
