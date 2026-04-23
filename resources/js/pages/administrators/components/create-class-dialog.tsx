import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useDraggable, useDroppable, DndContext, MouseSensor, TouchSensor, useSensor, useSensors, type DragEndEvent } from "@dnd-kit/core";
import axios from "axios";
import {
    BookOpen,
    Building2,
    Calendar,
    Check,
    ChevronRight,
    Clock,
    GraduationCap,
    GripVertical,
    MapPin,
    Plus,
    RefreshCw,
    User as UserIcon,
    Users,
    Wand2,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

// ── Types ──────────────────────────────────────────────────────────

type RoomOption = { id: number; name: string; class_code: string | null };
type FacultyOption = { id: string; name: string; department: string | null };
type CourseOption = { id: number; code: string; title: string; curriculum_year: string | null };
type ShsTrackOption = { id: number; track_name: string };
type ShsStrandOption = { id: number; strand_name: string; track_id: number; track_name: string | null };

type CreationOptions = {
    rooms: RoomOption[];
    faculty: FacultyOption[];
    courses: CourseOption[];
    shs_tracks: ShsTrackOption[];
    shs_strands: ShsStrandOption[];
    sections: string[];
    semesters: { value: string; label: string }[];
};

type ScheduleEntryInput = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
};

type ClassScheduleData = {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    grade_level: string | null;
    faculty_id?: string | null;
    faculty_name: string | null;
    room_name: string | null;
    courses: string;
    course_ids: number[];
    student_count: number;
    schedules: Array<{
        id?: number;
        day_of_week: string;
        start_time: string;
        end_time: string;
        time_range: string;
        room: string | null;
        room_id?: number | null;
    }>;
};

interface CreateClassDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: CreationOptions;
    defaults: { semester: string; school_year: string };
    onClassCreated: (classItem: ClassScheduleData) => void;
}

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
const DAYS_SHORT = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as const;
const HOUR_START = 7;
const HOUR_END = 19;

const PALETTES = [
    { accent: "border-l-rose-500", bg: "bg-rose-500/10 dark:bg-rose-400/15", text: "text-rose-700 dark:text-rose-300", badge: "bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300", border: "border-rose-200 dark:border-rose-800" },
    { accent: "border-l-sky-500", bg: "bg-sky-500/10 dark:bg-sky-400/15", text: "text-sky-700 dark:text-sky-300", badge: "bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300", border: "border-sky-200 dark:border-sky-800" },
    { accent: "border-l-amber-500", bg: "bg-amber-500/10 dark:bg-amber-400/15", text: "text-amber-700 dark:text-amber-300", badge: "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300", border: "border-amber-200 dark:border-amber-800" },
    { accent: "border-l-emerald-500", bg: "bg-emerald-500/10 dark:bg-emerald-400/15", text: "text-emerald-700 dark:text-emerald-300", badge: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300", border: "border-emerald-200 dark:border-emerald-800" },
    { accent: "border-l-violet-500", bg: "bg-violet-500/10 dark:bg-violet-400/15", text: "text-violet-700 dark:text-violet-300", badge: "bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300", border: "border-violet-200 dark:border-violet-800" },
    { accent: "border-l-orange-500", bg: "bg-orange-500/10 dark:bg-orange-400/15", text: "text-orange-700 dark:text-orange-300", badge: "bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300", border: "border-orange-200 dark:border-orange-800" },
];

function hashStr(s: string): number {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = s.charCodeAt(i) + ((h << 5) - h);
    return Math.abs(h);
}

const getPalette = (s: string) => PALETTES[hashStr(s) % PALETTES.length];

function fmtTime(t: string): string {
    if (!t) return "";
    const [h, m] = t.split(":").map(Number);
    const ap = h >= 12 ? "PM" : "AM";
    const h12 = h === 0 ? 12 : h > 12 ? h - 12 : h;
    return `${h12}:${String(m).padStart(2, "0")} ${ap}`;
}

// ── DraggableScheduleCard ─────────────────────────────────────────

function DraggableScheduleCard({ id, schedule, index, pal }: { id: string; schedule: ScheduleEntryInput; index: number; pal: ReturnType<typeof getPalette> }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id, data: { schedule, index } });
    const dayIdx = DAYS.indexOf(schedule.day_of_week as typeof DAYS[number]);
    return (
        <div
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            className={`flex items-center gap-2 rounded-md border-l-[3px] ${pal.accent} ${pal.bg} ${pal.border} p-2.5 transition-all ${isDragging ? "opacity-30" : "cursor-grab hover:shadow-md"}`}
        >
            <GripVertical className="text-muted-foreground h-3.5 w-3.5 shrink-0" />
            <div className="flex-1 min-w-0">
                <div className={`text-xs font-bold ${pal.text}`}>{DAYS_SHORT[dayIdx]}</div>
                <div className="text-muted-foreground text-[10px]">{fmtTime(schedule.start_time)} - {fmtTime(schedule.end_time)}</div>
            </div>
            {schedule.room_id && (
                <div className="text-[10px] font-medium text-foreground bg-background/60 px-1.5 py-0.5 rounded">
                    R{schedule.room_id}
                </div>
            )}
        </div>
    );
}

// ── DroppableRoomTile ─────────────────────────────────────────────

function DroppableRoomTile({ room, children, isOver }: { room: RoomOption; children: React.ReactNode; isOver?: boolean }) {
    const { setNodeRef, isOver: dropIsOver } = useDroppable({ id: `room-${room.id}`, data: { roomId: room.id } });
    const active = isOver ?? dropIsOver;
    return (
        <div
            ref={setNodeRef}
            className={`relative rounded-xl border p-3 transition-all duration-150 ${active ? "border-primary bg-primary/5 ring-1 ring-primary/20" : "bg-muted/30 border-border hover:border-border/80"}`}
        >
            {children}
        </div>
    );
}

// ── Component ─────────────────────────────────────────────────────

export default function CreateClassDialog({ open, onOpenChange, options, defaults, onClassCreated }: CreateClassDialogProps) {
    const [activeTab, setActiveTab] = React.useState("details");
    const [isSubmitting, setIsSubmitting] = React.useState(false);

    // ── Details tab state ──
    const [classification, setClassification] = React.useState<"college" | "shs">("college");
    const [courseCodes, setCourseCodes] = React.useState<number[]>([]);
    const [subjectIds, setSubjectIds] = React.useState<number[]>([]);
    const [subjectCode, setSubjectCode] = React.useState("");
    const [academicYear, setAcademicYear] = React.useState<string>("1");
    const [shsTrackId, setShsTrackId] = React.useState<string>("");
    const [shsStrandId, setShsStrandId] = React.useState<string>("");
    const [gradeLevel, setGradeLevel] = React.useState<string>("Grade 11");
    const [subjectCodeShs, setSubjectCodeShs] = React.useState("");
    const [facultyId, setFacultyId] = React.useState<string>("");
    const [semester, setSemester] = React.useState(defaults.semester);
    const [schoolYear, setSchoolYear] = React.useState(defaults.school_year);
    const [section, setSection] = React.useState("A");
    const [maximumSlots, setMaximumSlots] = React.useState<string>("30");

    // ── Schedule tab state ──
    const [startTime, setStartTime] = React.useState("08:00");
    const [endTime, setEndTime] = React.useState("10:00");
    const [recurrence, setRecurrence] = React.useState<"mwf" | "tth" | "daily" | "custom">("mwf");
    const [customDays, setCustomDays] = React.useState<string[]>(["Monday", "Wednesday", "Friday"]);
    const [generatedSchedules, setGeneratedSchedules] = React.useState<ScheduleEntryInput[]>([]);

    const [scheduleRooms, setScheduleRooms] = React.useState<Record<string, number | null>>({});
    const [dndOverRoom, setDndOverRoom] = React.useState<number | null>(null);

    // Derived: available subjects for selected courses
    const [availableSubjects, setAvailableSubjects] = React.useState<Array<{ id: number; code: string; title: string; course_id: number }>>([]);
    const [isLoadingSubjects, setIsLoadingSubjects] = React.useState(false);

    // Derived: strands for selected track
    const availableStrands = React.useMemo(() => {
        if (!shsTrackId) return [];
        return options.shs_strands.filter((s) => s.track_id === Number(shsTrackId));
    }, [shsTrackId, options.shs_strands]);

    // Fetch subjects when courses change
    React.useEffect(() => {
        if (classification !== "college" || courseCodes.length === 0) {
            setAvailableSubjects([]);
            return;
        }
        setIsLoadingSubjects(true);
        fetch(route("administrators.classes.options.subjects", { course_ids: courseCodes }))
            .then((r) => r.json())
            .then((data) => {
                setAvailableSubjects(data.data ?? []);
            })
            .catch(() => setAvailableSubjects([]))
            .finally(() => setIsLoadingSubjects(false));
    }, [classification, courseCodes]);

    // Generate schedules when parameters change
    React.useEffect(() => {
        let days: string[] = [];
        switch (recurrence) {
            case "mwf":
                days = ["Monday", "Wednesday", "Friday"];
                break;
            case "tth":
                days = ["Tuesday", "Thursday"];
                break;
            case "daily":
                days = [...DAYS];
                break;
            case "custom":
                days = customDays;
                break;
        }

        if (!startTime || !endTime || days.length === 0) {
            setGeneratedSchedules([]);
            return;
        }

        const newSchedules: ScheduleEntryInput[] = days.map((day) => ({
            day_of_week: day,
            start_time: startTime,
            end_time: endTime,
            room_id: scheduleRooms[day] ?? null,
        }));

        setGeneratedSchedules(newSchedules);
    }, [startTime, endTime, recurrence, customDays]);

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 5 } }),
    );

    const handleDragEnd = (event: DragEndEvent) => {
        setDndOverRoom(null);
        const { active, over } = event;
        if (!over || !active.data.current) return;
        const roomId = (over.data.current as { roomId?: number })?.roomId;
        const index = (active.data.current as { index?: number })?.index;
        if (roomId === undefined || index === undefined) return;
        const day = generatedSchedules[index]?.day_of_week;
        if (!day) return;
        setScheduleRooms((prev) => ({ ...prev, [day]: roomId }));
        setGeneratedSchedules((prev) =>
            prev.map((s, i) => (i === index ? { ...s, room_id: roomId } : s)),
        );
    };

    const handleDragOver = (event: DragEndEvent) => {
        const roomId = (event.over?.data.current as { roomId?: number })?.roomId;
        setDndOverRoom(roomId ?? null);
    };

    const autoAssignRooms = () => {
        const rooms = options.rooms;
        if (rooms.length === 0 || generatedSchedules.length === 0) return;

        const newMap: Record<string, number | null> = {};
        const newSchedules = generatedSchedules.map((s, i) => {
            const room = rooms[i % rooms.length];
            newMap[s.day_of_week] = room.id;
            return { ...s, room_id: room.id };
        });

        setScheduleRooms(newMap);
        setGeneratedSchedules(newSchedules);
        toast.success("Rooms auto-assigned");
    };

    const resetForm = () => {
        setClassification("college");
        setCourseCodes([]);
        setSubjectIds([]);
        setSubjectCode("");
        setAcademicYear("1");
        setShsTrackId("");
        setShsStrandId("");
        setGradeLevel("Grade 11");
        setSubjectCodeShs("");
        setFacultyId("");
        setSemester(defaults.semester);
        setSchoolYear(defaults.school_year);
        setSection("A");
        setMaximumSlots("30");
        setStartTime("08:00");
        setEndTime("10:00");
        setRecurrence("mwf");
        setCustomDays(["Monday", "Wednesday", "Friday"]);
        setGeneratedSchedules([]);
        setScheduleRooms({});
        setActiveTab("details");
    };

    const canProceedToSchedule = () => {
        if (classification === "college") {
            return courseCodes.length > 0 && subjectIds.length > 0 && section && maximumSlots;
        }
        return shsTrackId && shsStrandId && gradeLevel && subjectCodeShs && section && maximumSlots;
    };

    const canProceedToRooms = () => {
        return generatedSchedules.length > 0;
    };

    const canSubmit = () => {
        return generatedSchedules.length > 0 && generatedSchedules.every((s) => s.room_id !== null);
    };

    const handleSubmit = async () => {
        if (!canSubmit()) {
            toast.error("Please assign a room to every schedule entry.");
            return;
        }

        setIsSubmitting(true);

        const payload: Record<string, unknown> = {
            classification,
            faculty_id: facultyId || null,
            semester,
            school_year: schoolYear,
            section,
            room_id: generatedSchedules[0]?.room_id ?? options.rooms[0]?.id ?? null,
            maximum_slots: Number(maximumSlots),
            schedules: generatedSchedules.map((s) => ({
                day_of_week: s.day_of_week,
                start_time: s.start_time,
                end_time: s.end_time,
                room_id: s.room_id,
            })),
        };

        if (classification === "college") {
            payload.course_codes = courseCodes;
            payload.subject_ids = subjectIds;
            payload.subject_code = subjectCode || null;
            payload.subject_id = subjectIds[0] ?? null;
            payload.academic_year = Number(academicYear);
        } else {
            payload.shs_track_id = Number(shsTrackId);
            payload.shs_strand_id = Number(shsStrandId);
            payload.grade_level = gradeLevel;
            payload.subject_code_shs = subjectCodeShs;
        }

        try {
            const res = await axios.post(route("administrators.scheduling-analytics.classes.store"), payload);
            const data = res.data;

            toast.success("Class created successfully!");
            onClassCreated(data.class);
            onOpenChange(false);
            resetForm();

            if (data.conflicts?.length > 0) {
                toast.warning(`${data.conflicts.length} schedule conflict${data.conflicts.length > 1 ? "s" : ""} detected.`);
            }
        } catch (error: unknown) {
            let msg = "Failed to create class.";
            if (axios.isAxiosError(error) && error.response?.data?.message) {
                msg = error.response.data.message;
            } else if (axios.isAxiosError(error) && error.response?.data?.errors) {
                const errs = error.response.data.errors;
                const first = Object.values(errs).flat()[0];
                if (first) msg = String(first);
            }
            toast.error(msg);
        } finally {
            setIsSubmitting(false);
        }
    };

    const pal = getPalette(subjectCode || subjectCodeShs || "NEW");

    // ── Render ──
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[720px] max-h-[90vh] flex flex-col">
                <DialogHeader className="shrink-0">
                    <div className="flex items-center gap-3">
                        <div className={`rounded-xl p-2.5 ${pal.badge}`}>
                            <Plus className="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle>Create New Class</DialogTitle>
                            <DialogDescription className="text-xs">
                                Set up a new class and assign its schedule across rooms.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col flex-1 min-h-0">
                    <TabsList className="grid w-full grid-cols-3 shrink-0">
                        <TabsTrigger value="details" className="text-xs">
                            <BookOpen className="mr-1.5 h-3.5 w-3.5" /> Details
                        </TabsTrigger>
                        <TabsTrigger value="schedule" className="text-xs" disabled={!canProceedToSchedule()}>
                            <Clock className="mr-1.5 h-3.5 w-3.5" /> Schedule
                        </TabsTrigger>
                        <TabsTrigger value="rooms" className="text-xs" disabled={!canProceedToRooms()}>
                            <MapPin className="mr-1.5 h-3.5 w-3.5" /> Rooms
                        </TabsTrigger>
                    </TabsList>

                    <div className="flex-1 min-h-0 mt-3">
                        {/* ── Details Tab ── */}
                        <TabsContent value="details" className="h-full">
                            <ScrollArea className="h-full max-h-[420px] pr-3">
                                <div className="space-y-4">
                                    {/* Classification */}
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Classification</Label>
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant={classification === "college" ? "default" : "outline"}
                                                size="sm"
                                                onClick={() => setClassification("college")}
                                                className="flex-1 text-xs"
                                            >
                                                <GraduationCap className="mr-1.5 h-3.5 w-3.5" /> College
                                            </Button>
                                            <Button
                                                type="button"
                                                variant={classification === "shs" ? "default" : "outline"}
                                                size="sm"
                                                onClick={() => setClassification("shs")}
                                                className="flex-1 text-xs"
                                            >
                                                <Users className="mr-1.5 h-3.5 w-3.5" /> SHS
                                            </Button>
                                        </div>
                                    </div>

                                    {classification === "college" ? (
                                        <>
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">Courses</Label>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {options.courses.map((c) => {
                                                        const selected = courseCodes.includes(c.id);
                                                        return (
                                                            <button
                                                                key={c.id}
                                                                type="button"
                                                                onClick={() => {
                                                                    setCourseCodes((prev) =>
                                                                        selected ? prev.filter((id) => id !== c.id) : [...prev, c.id],
                                                                    );
                                                                    setSubjectIds([]);
                                                                }}
                                                                className={`inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                                    selected
                                                                        ? "border-primary bg-primary text-primary-foreground"
                                                                        : "border-border bg-background text-muted-foreground hover:bg-muted"
                                                                }`}
                                                            >
                                                                {selected && <Check className="h-3 w-3" />}
                                                                {c.code}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">Subjects</Label>
                                                {isLoadingSubjects ? (
                                                    <div className="text-muted-foreground text-xs py-2">Loading subjects...</div>
                                                ) : availableSubjects.length === 0 ? (
                                                    <div className="text-muted-foreground text-xs py-2">Select a course to see subjects.</div>
                                                ) : (
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {availableSubjects.map((s) => {
                                                            const selected = subjectIds.includes(s.id);
                                                            return (
                                                                <button
                                                                    key={s.id}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setSubjectIds((prev) =>
                                                                            selected ? prev.filter((id) => id !== s.id) : [...prev, s.id],
                                                                        );
                                                                    }}
                                                                    className={`inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                                        selected
                                                                            ? "border-primary bg-primary text-primary-foreground"
                                                                            : "border-border bg-background text-muted-foreground hover:bg-muted"
                                                                    }`}
                                                                >
                                                                    {selected && <Check className="h-3 w-3" />}
                                                                    {s.code}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium">Year Level</Label>
                                                    <Select value={academicYear} onValueChange={setAcademicYear}>
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {["1", "2", "3", "4"].map((y) => (
                                                                <SelectItem key={y} value={y} className="text-xs">
                                                                    {y}{y === "1" ? "st" : y === "2" ? "nd" : y === "3" ? "rd" : "th"} Year
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium">Section</Label>
                                                    <Select value={section} onValueChange={setSection}>
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.sections.map((s) => (
                                                                <SelectItem key={s} value={s} className="text-xs">{s}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium">Track</Label>
                                                    <Select value={shsTrackId} onValueChange={setShsTrackId}>
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue placeholder="Select track" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.shs_tracks.map((t) => (
                                                                <SelectItem key={t.id} value={String(t.id)} className="text-xs">{t.track_name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium">Strand</Label>
                                                    <Select value={shsStrandId} onValueChange={setShsStrandId}>
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue placeholder="Select strand" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {availableStrands.map((s) => (
                                                                <SelectItem key={s.id} value={String(s.id)} className="text-xs">{s.strand_name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium">Grade Level</Label>
                                                    <Select value={gradeLevel} onValueChange={setGradeLevel}>
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="Grade 11" className="text-xs">Grade 11</SelectItem>
                                                            <SelectItem value="Grade 12" className="text-xs">Grade 12</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium">Section</Label>
                                                    <Select value={section} onValueChange={setSection}>
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.sections.map((s) => (
                                                                <SelectItem key={s} value={s} className="text-xs">{s}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">Subject Code</Label>
                                                <Input
                                                    value={subjectCodeShs}
                                                    onChange={(e) => setSubjectCodeShs(e.target.value)}
                                                    placeholder="e.g. STEM-MATH11"
                                                    className="h-8 text-xs"
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Faculty</Label>
                                            <Select value={facultyId} onValueChange={setFacultyId}>
                                                <SelectTrigger className="h-8 text-xs">
                                                    <div className="flex items-center gap-1.5">
                                                        <UserIcon className="text-muted-foreground h-3 w-3" />
                                                        <SelectValue placeholder="TBA" />
                                                    </div>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none" className="text-xs">TBA</SelectItem>
                                                    {options.faculty.map((f) => (
                                                        <SelectItem key={f.id} value={f.id} className="text-xs">{f.name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Max Slots</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={maximumSlots}
                                                onChange={(e) => setMaximumSlots(e.target.value)}
                                                className="h-8 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Semester</Label>
                                            <Select value={semester} onValueChange={setSemester}>
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.semesters.map((s) => (
                                                        <SelectItem key={s.value} value={s.value} className="text-xs">{s.label}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">School Year</Label>
                                            <Input
                                                value={schoolYear}
                                                onChange={(e) => setSchoolYear(e.target.value)}
                                                className="h-8 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end pt-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => setActiveTab("schedule")}
                                            disabled={!canProceedToSchedule()}
                                            className="text-xs"
                                        >
                                            Next <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </ScrollArea>
                        </TabsContent>

                        {/* ── Schedule Tab ── */}
                        <TabsContent value="schedule" className="h-full">
                            <ScrollArea className="h-full max-h-[420px] pr-3">
                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Start Time</Label>
                                            <Input
                                                type="time"
                                                value={startTime}
                                                onChange={(e) => setStartTime(e.target.value)}
                                                className="h-8 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">End Time</Label>
                                            <Input
                                                type="time"
                                                value={endTime}
                                                onChange={(e) => setEndTime(e.target.value)}
                                                className="h-8 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Recurrence Pattern</Label>
                                        <div className="flex flex-wrap gap-1.5">
                                            {[
                                                { key: "mwf" as const, label: "MWF", desc: "Mon, Wed, Fri" },
                                                { key: "tth" as const, label: "TTh", desc: "Tue, Thu" },
                                                { key: "daily" as const, label: "Daily", desc: "Mon-Sat" },
                                                { key: "custom" as const, label: "Custom", desc: "Pick days" },
                                            ].map((r) => (
                                                <button
                                                    key={r.key}
                                                    type="button"
                                                    onClick={() => setRecurrence(r.key)}
                                                    className={`flex flex-col items-start rounded-md border px-3 py-2 text-left transition-colors min-w-[80px] ${
                                                        recurrence === r.key
                                                            ? "border-primary bg-primary/5 text-primary"
                                                            : "border-border bg-background text-muted-foreground hover:bg-muted"
                                                    }`}
                                                >
                                                    <span className="text-xs font-bold">{r.label}</span>
                                                    <span className="text-[10px] opacity-70">{r.desc}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    {recurrence === "custom" && (
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Select Days</Label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {DAYS.map((day) => {
                                                    const selected = customDays.includes(day);
                                                    return (
                                                        <button
                                                            key={day}
                                                            type="button"
                                                            onClick={() => {
                                                                setCustomDays((prev) =>
                                                                    selected ? prev.filter((d) => d !== day) : [...prev, day],
                                                                );
                                                            }}
                                                            className={`rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                                selected
                                                                    ? "border-primary bg-primary text-primary-foreground"
                                                                    : "border-border bg-background text-muted-foreground hover:bg-muted"
                                                            }`}
                                                        >
                                                            {DAYS_SHORT[DAYS.indexOf(day)]}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {generatedSchedules.length > 0 && (
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Generated Schedules ({generatedSchedules.length})</Label>
                                            <div className="grid gap-1.5">
                                                {generatedSchedules.map((s, i) => {
                                                    const p = getPalette(s.day_of_week);
                                                    return (
                                                        <div
                                                            key={i}
                                                            className={`flex items-center justify-between rounded-md border-l-[3px] ${p.accent} ${p.bg} ${p.border} p-2`}
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <Calendar className={`h-3.5 w-3.5 ${p.text}`} />
                                                                <span className={`text-xs font-medium ${p.text}`}>{s.day_of_week}</span>
                                                                <span className="text-muted-foreground text-xs">{fmtTime(s.start_time)} - {fmtTime(s.end_time)}</span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    const day = generatedSchedules[i]?.day_of_week;
                                                                    if (day) {
                                                                        setScheduleRooms((prev) => {
                                                                            const next = { ...prev };
                                                                            delete next[day];
                                                                            return next;
                                                                        });
                                                                    }
                                                                    setGeneratedSchedules((prev) => prev.filter((_, idx) => idx !== i));
                                                                }}
                                                                className="text-muted-foreground hover:text-destructive transition-colors"
                                                            >
                                                                <X className="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex justify-between pt-2">
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setActiveTab("details")} className="text-xs">
                                            Back
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => setActiveTab("rooms")}
                                            disabled={!canProceedToRooms()}
                                            className="text-xs"
                                        >
                                            Next <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </ScrollArea>
                        </TabsContent>

                        {/* ── Rooms Tab ── */}
                        <TabsContent value="rooms" className="h-full">
                            <div className="flex flex-col h-full max-h-[420px]">
                                <div className="flex items-center justify-between mb-3">
                                    <Label className="text-xs font-medium">Drag schedules onto rooms</Label>
                                    <Button type="button" variant="outline" size="sm" onClick={autoAssignRooms} className="h-7 text-xs gap-1">
                                        <Wand2 className="h-3 w-3" /> Auto-assign
                                    </Button>
                                </div>

                                <DndContext sensors={sensors} onDragEnd={handleDragEnd} onDragOver={handleDragOver}>
                                    <div className="flex gap-3 flex-1 min-h-0">
                                        {/* Schedule cards */}
                                        <div className="w-[200px] shrink-0">
                                            <ScrollArea className="h-full">
                                                <div className="space-y-2 pr-2">
                                                    <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-1">Schedules</div>
                                                    {generatedSchedules.map((s, i) => (
                                                        <DraggableScheduleCard
                                                            key={`${s.day_of_week}-${i}`}
                                                            id={`sched-card-${i}`}
                                                            schedule={s}
                                                            index={i}
                                                            pal={getPalette(s.day_of_week)}
                                                        />
                                                    ))}
                                                </div>
                                            </ScrollArea>
                                        </div>

                                        {/* Room grid */}
                                        <div className="flex-1 min-h-0">
                                            <ScrollArea className="h-full">
                                                <div className="grid grid-cols-2 gap-2 pr-2">
                                                    {options.rooms.map((room) => {
                                                        const assigned = generatedSchedules.filter((s) => scheduleRooms[s.day_of_week] === room.id);
                                                        const isOver = dndOverRoom === room.id;
                                                        return (
                                                            <DroppableRoomTile key={room.id} room={room} isOver={isOver}>
                                                                <div className="flex items-start justify-between mb-1.5">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Building2 className="text-muted-foreground h-3.5 w-3.5" />
                                                                        <span className="text-xs font-semibold">{room.name}</span>
                                                                    </div>
                                                                    <span className="text-[10px] text-muted-foreground bg-muted px-1 rounded">{assigned.length}</span>
                                                                </div>
                                                                <div className="space-y-1">
                                                                    {assigned.map((s) => {
                                                                        const dayIdx = DAYS.indexOf(s.day_of_week as typeof DAYS[number]);
                                                                        return (
                                                                            <div key={s.day_of_week} className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                                <Calendar className="h-2.5 w-2.5" />
                                                                                <span>{DAYS_SHORT[dayIdx]}</span>
                                                                                <span>{fmtTime(s.start_time)}</span>
                                                                            </div>
                                                                        );
                                                                    })}
                                                                    {assigned.length === 0 && (
                                                                        <div className="text-[10px] text-muted-foreground/60 italic">Drop here</div>
                                                                    )}
                                                                </div>
                                                            </DroppableRoomTile>
                                                        );
                                                    })}
                                                </div>
                                            </ScrollArea>
                                        </div>
                                    </div>
                                </DndContext>

                                <div className="flex justify-between pt-3 mt-3 border-t">
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setActiveTab("schedule")} className="text-xs">
                                        Back
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={handleSubmit}
                                        disabled={!canSubmit() || isSubmitting}
                                        className="text-xs"
                                    >
                                        {isSubmitting ? <RefreshCw className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Plus className="mr-1.5 h-3.5 w-3.5" />}
                                        {isSubmitting ? "Creating..." : "Create Class"}
                                    </Button>
                                </div>
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
