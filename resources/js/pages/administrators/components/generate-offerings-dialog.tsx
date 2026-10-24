import * as React from "react";

import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Loader2, Sparkles } from "lucide-react";

type CourseOption = { id: number; code: string; title: string; curriculum_year?: string | null };
type RoomOption = { id: number; name: string; class_code: string | null };
type ShsStrandOption = { id: number; strand_name: string; track_id: number; track_name: string | null };

export type GenerateOfferingsPayload = {
    classification: "college" | "shs";
    school_year: string;
    semester: string;
    sections: string[];
    maximum_slots: number;
    room_id: number | null;
    delivery_mode: "face_to_face" | "online";
    course_id?: number;
    academic_year?: number;
    shs_strand_id?: number;
    shs_track_id?: number;
    grade_level?: "Grade 11" | "Grade 12";
};

type GenerateOfferingsDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    schoolYear: string;
    semester: string;
    courses: CourseOption[];
    shsStrands: ShsStrandOption[];
    rooms: RoomOption[];
    onGenerate: (payload: GenerateOfferingsPayload) => Promise<void>;
};

const ALL_SECTIONS = ["A", "B", "C", "D"] as const;

export default function GenerateOfferingsDialog({
    open,
    onOpenChange,
    schoolYear,
    semester,
    courses,
    shsStrands,
    rooms,
    onGenerate,
}: GenerateOfferingsDialogProps) {
    const [classification, setClassification] = React.useState<"college" | "shs">("college");
    const [courseId, setCourseId] = React.useState<string>(() => (courses[0] ? String(courses[0].id) : ""));
    const [academicYear, setAcademicYear] = React.useState<string>("1");
    const [strandId, setStrandId] = React.useState<string>(() => (shsStrands[0] ? String(shsStrands[0].id) : ""));
    const [gradeLevel, setGradeLevel] = React.useState<"Grade 11" | "Grade 12">("Grade 11");
    const [sections, setSections] = React.useState<string[]>(["A"]);
    const [maximumSlots, setMaximumSlots] = React.useState<number>(40);
    const [defaultRoomId, setDefaultRoomId] = React.useState<string>("none");
    const [deliveryMode, setDeliveryMode] = React.useState<"face_to_face" | "online">("face_to_face");
    const [isGenerating, setIsGenerating] = React.useState(false);

    React.useEffect(() => {
        if (!courseId && courses[0]) {
            setCourseId(String(courses[0].id));
        }
        if (!strandId && shsStrands[0]) {
            setStrandId(String(shsStrands[0].id));
        }
    }, [courses, shsStrands, courseId, strandId]);

    const toggleSection = (sec: string) => {
        setSections((prev) => (prev.includes(sec) ? prev.filter((s) => s !== sec) : [...prev, sec]));
    };

    const handleSubmit = async () => {
        if (sections.length === 0) return;
        setIsGenerating(true);
        try {
            const selectedStrand = shsStrands.find((s) => String(s.id) === strandId);
            await onGenerate({
                classification,
                school_year: schoolYear,
                semester,
                sections,
                maximum_slots: maximumSlots,
                room_id: deliveryMode === "online" || defaultRoomId === "none" ? null : parseInt(defaultRoomId, 10),
                delivery_mode: deliveryMode,
                ...(classification === "college"
                    ? {
                          course_id: parseInt(courseId, 10),
                          academic_year: parseInt(academicYear, 10),
                      }
                    : {
                          shs_strand_id: parseInt(strandId, 10),
                          shs_track_id: selectedStrand?.track_id,
                          grade_level: gradeLevel,
                      }),
            });
            onOpenChange(false);
        } finally {
            setIsGenerating(false);
        }
    };

    const canSubmit = sections.length > 0 && (classification === "college" ? Boolean(courseId && academicYear) : Boolean(strandId && gradeLevel));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="text-primary h-5 w-5" />
                        Add classes from curriculum
                    </DialogTitle>
                    <DialogDescription>
                        Add subjects from the curriculum for {schoolYear}, semester {semester}. You can assign their class times next.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold">Education Level</Label>
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={classification === "college" ? "default" : "outline"}
                                onClick={() => setClassification("college")}
                            >
                                College Program
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={classification === "shs" ? "default" : "outline"}
                                onClick={() => setClassification("shs")}
                            >
                                Senior High School (SHS)
                            </Button>
                        </div>
                    </div>

                    {classification === "college" ? (
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs">Program / Course</Label>
                                <Select value={courseId} onValueChange={setCourseId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select course..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {courses.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.code} — {c.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Curriculum Year Level</Label>
                                <Select value={academicYear} onValueChange={setAcademicYear}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">1st Year</SelectItem>
                                        <SelectItem value="2">2nd Year</SelectItem>
                                        <SelectItem value="3">3rd Year</SelectItem>
                                        <SelectItem value="4">4th Year</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs">SHS Strand</Label>
                                <Select value={strandId} onValueChange={setStrandId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select strand..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {shsStrands.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.strand_name} {s.track_name ? `(${s.track_name})` : ""}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Grade Level</Label>
                                <Select value={gradeLevel} onValueChange={(v) => setGradeLevel(v as "Grade 11" | "Grade 12")}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Grade 11">Grade 11</SelectItem>
                                        <SelectItem value="Grade 12">Grade 12</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold">Sections</Label>
                        <div className="flex flex-wrap gap-2">
                            {ALL_SECTIONS.map((sec) => {
                                const active = sections.includes(sec);
                                return (
                                    <Button
                                        key={sec}
                                        type="button"
                                        size="sm"
                                        variant={active ? "default" : "outline"}
                                        aria-pressed={active}
                                        className="h-8 px-3 text-xs"
                                        onClick={() => toggleSection(sec)}
                                    >
                                        Section {sec}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="offerings-delivery-mode">How are these classes taught?</Label>
                        <Select value={deliveryMode} onValueChange={(value) => setDeliveryMode(value as "face_to_face" | "online")}>
                            <SelectTrigger id="offerings-delivery-mode" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="face_to_face">Face to face</SelectItem>
                                <SelectItem value="online">Online</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Students per class</Label>
                            <Input
                                type="number"
                                min={1}
                                max={500}
                                value={maximumSlots}
                                onChange={(e) => setMaximumSlots(Math.max(1, parseInt(e.target.value || "40", 10)))}
                            />
                        </div>
                        {deliveryMode === "online" ? (
                            <p className="text-muted-foreground self-end py-2 text-sm">No room reserved for online classes.</p>
                        ) : (
                            <div className="space-y-1.5">
                                <Label className="text-xs">Default Room (Optional)</Label>
                                <Select value={defaultRoomId} onValueChange={setDefaultRoomId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="TBA / Assign later" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">TBA (Assign during scheduling)</SelectItem>
                                        {rooms.map((r) => (
                                            <SelectItem key={r.id} value={String(r.id)}>
                                                {r.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isGenerating}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={handleSubmit} disabled={!canSubmit || isGenerating}>
                        {isGenerating ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Sparkles className="mr-1.5 h-3.5 w-3.5" />}
                        Add classes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
