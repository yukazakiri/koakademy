import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { VisualRadioButton } from "@/Components/ui/visual-radio-button";
import { useDraggable, useDroppable, DndContext, MouseSensor, TouchSensor, useSensor, useSensors, type DragEndEvent } from "@dnd-kit/core";
import { useForm } from "@inertiajs/react";
import axios from "axios";
import {
    BookOpen,
    Building2,
    Calendar,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    GraduationCap,
    GripVertical,
    MapPin,
    Plus,
    RefreshCw,
    Settings2,
    User as UserIcon,
    Users,
    Wand2,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
const DAYS_SHORT = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as const;

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

function DraggableScheduleCard({ id, schedule, index, pal }: { id: string; schedule: { day_of_week: string; start_time: string; end_time: string; room_id: number | null }; index: number; pal: ReturnType<typeof getPalette> }) {
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
                <div className="text-[10px] font-medium text-foreground bg-background/60 px-1.5 py-0.5 rounded">R{schedule.room_id}</div>
            )}
        </div>
    );
}

function DroppableRoomTile({ room, children, isOver }: { room: { id: number; name: string }; children: React.ReactNode; isOver?: boolean }) {
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

export default function CreateClassDialog({ open, onOpenChange, options, defaults, onClassCreated }: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: {
        rooms: Array<{ id: number; name: string; class_code: string | null }>;
        faculty: Array<{ id: string; name: string; department: string | null }>;
        courses: Array<{ id: number; code: string; title: string; curriculum_year: string | null }>;
        shs_tracks: Array<{ id: number; track_name: string }>;
        shs_strands: Array<{ id: number; strand_name: string; track_id: number; track_name: string | null }>;
        sections: string[];
        semesters: Array<{ value: string; label: string }>;
    };
    defaults: { semester: string; school_year: string };
    onClassCreated: (classItem: any) => void;
}) {
    const [activeTab, setActiveTab] = React.useState("details");
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [scheduleRooms, setScheduleRooms] = React.useState<Record<string, number | null>>({});
    const [dndOverRoom, setDndOverRoom] = React.useState<number | null>(null);

    const [collegeSubjectOptions, setCollegeSubjectOptions] = React.useState<Array<{ id: number; label: string; code: string; title: string }>>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = React.useState(false);
    const [shsStrandOptions, setShsStrandOptions] = React.useState<Array<{ id: string | number; label: string }>>([]);
    const [shsStrandsLoading, setShsStrandsLoading] = React.useState(false);
    const [shsSubjectOptions, setShsSubjectOptions] = React.useState<Array<{ code: string; label: string; title: string }>>([]);
    const [shsSubjectsLoading, setShsSubjectsLoading] = React.useState(false);
    const [subjectCodeTouched, setSubjectCodeTouched] = React.useState(false);

    const form = useForm({
        classification: "college" as "college" | "shs",
        course_codes: [] as number[],
        subject_ids: [] as number[],
        subject_code: "",
        academic_year: 1,
        shs_track_id: null as number | null,
        shs_strand_id: null as number | null,
        subject_code_shs: "",
        grade_level: "Grade 11",
        faculty_id: null as string | null,
        semester: defaults.semester,
        school_year: defaults.school_year,
        section: "A",
        room_id: options.rooms[0]?.id ?? 0,
        maximum_slots: 40,
        schedules: [] as Array<{ day_of_week: string; start_time: string; end_time: string; room_id: number | null }>,
    });

    React.useEffect(() => {
        if (!open) return;
        form.setData({
            classification: "college",
            course_codes: [],
            subject_ids: [],
            subject_code: "",
            academic_year: 1,
            shs_track_id: null,
            shs_strand_id: null,
            subject_code_shs: "",
            grade_level: "Grade 11",
            faculty_id: null,
            semester: defaults.semester,
            school_year: defaults.school_year,
            section: "A",
            room_id: options.rooms[0]?.id ?? 0,
            maximum_slots: 40,
            schedules: [],
        });
        setScheduleRooms({});
        setCollegeSubjectOptions([]);
        setShsStrandOptions([]);
        setShsSubjectOptions([]);
        setSubjectCodeTouched(false);
        setActiveTab("details");
    }, [open]);

    const loadCollegeSubjects = async (courseIds: number[]) => {
        if (courseIds.length === 0) {
            setCollegeSubjectOptions([]);
            return;
        }
        setCollegeSubjectsLoading(true);
        try {
            const response = await fetch(route("administrators.classes.options.subjects", { course_ids: courseIds }));
            const data = await response.json() as { data: Array<{ id: number; label: string; code: string; title: string }> };
            setCollegeSubjectOptions(data.data);
            if (!subjectCodeTouched) {
                const codes = data.data
                    .filter((s) => form.data.subject_ids.includes(s.id))
                    .map((s) => s.code)
                    .filter(Boolean);
                const computed = Array.from(new Set(codes)).join(", ");
                if (computed) form.setData("subject_code", computed);
            }
        } finally {
            setCollegeSubjectsLoading(false);
        }
    };

    const loadShsStrands = async (trackId: number | null) => {
        if (!trackId) {
            setShsStrandOptions([]);
            form.setData("shs_strand_id", null);
            return;
        }
        setShsStrandsLoading(true);
        try {
            const response = await fetch(route("administrators.classes.options.shs-strands", { track_id: trackId }));
            const data = await response.json() as { data: Array<{ id: string | number; label: string }> };
            setShsStrandOptions(data.data);
        } finally {
            setShsStrandsLoading(false);
        }
    };

    const loadShsSubjects = async (strandId: number | null) => {
        if (!strandId) {
            setShsSubjectOptions([]);
            return;
        }
        setShsSubjectsLoading(true);
        try {
            const response = await fetch(route("administrators.classes.options.shs-subjects", { strand_id: strandId }));
            const data = await response.json() as { data: Array<{ code: string; label: string; title: string }> };
            setShsSubjectOptions(data.data);
        } finally {
            setShsSubjectsLoading(false);
        }
    };

    const generateSchedules = (recurrence: "mwf" | "tth" | "daily" | "custom", customDays: string[], startTime: string, endTime: string) => {
        let days: string[] = [];
        switch (recurrence) {
            case "mwf": days = ["Monday", "Wednesday", "Friday"]; break;
            case "tth": days = ["Tuesday", "Thursday"]; break;
            case "daily": days = [...DAYS]; break;
            case "custom": days = customDays; break;
        }
        if (!startTime || !endTime || days.length === 0) return [];
        return days.map((day) => ({
            day_of_week: day,
            start_time: startTime,
            end_time: endTime,
            room_id: scheduleRooms[day] ?? null,
        }));
    };

    const [recurrence, setRecurrence] = React.useState<"mwf" | "tth" | "daily" | "custom">("mwf");
    const [customDays, setCustomDays] = React.useState<string[]>(["Monday", "Wednesday", "Friday"]);
    const [startTime, setStartTime] = React.useState("08:00");
    const [endTime, setEndTime] = React.useState("10:00");

    React.useEffect(() => {
        const schedules = generateSchedules(recurrence, customDays, startTime, endTime);
        form.setData("schedules", schedules);
    }, [recurrence, customDays, startTime, endTime]);

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
        const day = form.data.schedules[index]?.day_of_week;
        if (!day) return;
        setScheduleRooms((prev) => ({ ...prev, [day]: roomId }));
        form.setData("schedules", form.data.schedules.map((s, i) => (i === index ? { ...s, room_id: roomId } : s)));
    };

    const handleDragOver = (event: DragEndEvent) => {
        const roomId = (event.over?.data.current as { roomId?: number })?.roomId;
        setDndOverRoom(roomId ?? null);
    };

    const autoAssignRooms = () => {
        const rooms = options.rooms;
        if (rooms.length === 0 || form.data.schedules.length === 0) return;
        const newMap: Record<string, number | null> = {};
        const newSchedules = form.data.schedules.map((s, i) => {
            const room = rooms[i % rooms.length];
            newMap[s.day_of_week] = room.id;
            return { ...s, room_id: room.id };
        });
        setScheduleRooms(newMap);
        form.setData("schedules", newSchedules);
        toast.success("Rooms auto-assigned");
    };

    const canSubmit = () => {
        return form.data.schedules.length > 0 && form.data.schedules.every((s) => s.room_id !== null);
    };

    const handleSubmit = async () => {
        if (!canSubmit()) {
            toast.error("Please assign a room to every schedule entry.");
            return;
        }
        setIsSubmitting(true);

        const payload = { ...form.data };
        if (payload.classification === "college") {
            payload.subject_id = payload.subject_ids[0] ?? null;
        }

        try {
            const res = await axios.post(route("administrators.scheduling-analytics.classes.store"), payload);
            toast.success("Class created successfully!");
            onClassCreated(res.data.class);
            onOpenChange(false);
            if (res.data.conflicts?.length > 0) {
                toast.warning(`${res.data.conflicts.length} schedule conflict${res.data.conflicts.length > 1 ? "s" : ""} detected.`);
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

    const firstError = Object.values(form.errors).find((e) => typeof e === "string" && e.length > 0) ?? null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-xl md:max-w-2xl lg:max-w-3xl flex flex-col gap-0 p-0">
                <SheetHeader className="bg-muted/20 border-b p-6 pb-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 rounded-lg p-2.5">
                            <Plus className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <SheetTitle className="text-xl font-bold">Create New Class</SheetTitle>
                            <SheetDescription className="text-sm">Set up a new class and assign its schedule across rooms.</SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col flex-1 overflow-hidden">
                    <div className="px-6 pt-4">
                        <TabsList className="h-12 w-full justify-start gap-6 rounded-none border-b bg-transparent p-0">
                            <TabsTrigger value="details" className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                                <BookOpen className="mr-2 h-4 w-4" /> Details
                            </TabsTrigger>
                            <TabsTrigger value="schedule" className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                                <Clock className="mr-2 h-4 w-4" /> Schedule
                            </TabsTrigger>
                            <TabsTrigger value="rooms" className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                                <MapPin className="mr-2 h-4 w-4" /> Rooms
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <div className="flex-1 overflow-y-auto p-6">
                        {firstError ? (
                            <div className="border-destructive/40 bg-destructive/5 text-destructive mb-6 rounded-lg border px-4 py-3 text-sm">{firstError}</div>
                        ) : null}

                        <TabsContent value="details" className="m-0 space-y-6 outline-none">
                            <div className="grid gap-6">
                                <Card className="border-border/60 shadow-sm">
                                    <CardHeader className="bg-muted/20 border-b pb-4">
                                        <CardTitle className="text-base font-semibold">Academic Profile</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-6">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-3 sm:col-span-2">
                                                <Label>Class type</Label>
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <VisualRadioButton
                                                        title="College"
                                                        description="Higher education degrees and courses."
                                                        checked={form.data.classification === "college"}
                                                        onSelect={() => {
                                                            form.setData("classification", "college");
                                                            form.setData("course_codes", []);
                                                            form.setData("subject_ids", []);
                                                            form.setData("subject_code", "");
                                                            form.setData("shs_track_id", null);
                                                            form.setData("shs_strand_id", null);
                                                            form.setData("subject_code_shs", "");
                                                            setCollegeSubjectOptions([]);
                                                            setShsStrandOptions([]);
                                                            setShsSubjectOptions([]);
                                                            setSubjectCodeTouched(false);
                                                        }}
                                                    />
                                                    <VisualRadioButton
                                                        title="Senior High School"
                                                        description="K-12 pathway and strands."
                                                        checked={form.data.classification === "shs"}
                                                        onSelect={() => {
                                                            form.setData("classification", "shs");
                                                            form.setData("course_codes", []);
                                                            form.setData("subject_ids", []);
                                                            form.setData("subject_code", "");
                                                            form.setData("shs_track_id", null);
                                                            form.setData("shs_strand_id", null);
                                                            form.setData("subject_code_shs", "");
                                                            setCollegeSubjectOptions([]);
                                                            setShsStrandOptions([]);
                                                            setShsSubjectOptions([]);
                                                            setSubjectCodeTouched(false);
                                                        }}
                                                    />
                                                </div>
                                            </div>

                                            {form.data.classification === "college" ? (
                                                <>
                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>Associated courses</Label>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {options.courses.map((c) => {
                                                                const selected = form.data.course_codes.includes(c.id);
                                                                return (
                                                                    <button
                                                                        key={c.id}
                                                                        type="button"
                                                                        onClick={() => {
                                                                            const next = selected
                                                                                ? form.data.course_codes.filter((id) => id !== c.id)
                                                                                : [...form.data.course_codes, c.id];
                                                                            form.setData("course_codes", next);
                                                                            form.setData("subject_ids", []);
                                                                            if (!subjectCodeTouched) form.setData("subject_code", "");
                                                                            void loadCollegeSubjects(next);
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
                                                        {form.errors.course_codes ? <p className="text-destructive text-xs">{form.errors.course_codes}</p> : null}
                                                    </div>

                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>Subjects</Label>
                                                        {collegeSubjectsLoading ? (
                                                            <div className="text-muted-foreground text-xs py-2">Loading subjects...</div>
                                                        ) : collegeSubjectOptions.length === 0 ? (
                                                            <div className="text-muted-foreground text-xs py-2">Select a course to see subjects.</div>
                                                        ) : (
                                                            <div className="flex flex-wrap gap-1.5">
                                                                {collegeSubjectOptions.map((s) => {
                                                                    const selected = form.data.subject_ids.includes(s.id);
                                                                    return (
                                                                        <button
                                                                            key={s.id}
                                                                            type="button"
                                                                            onClick={() => {
                                                                                const next = selected
                                                                                    ? form.data.subject_ids.filter((id) => id !== s.id)
                                                                                    : [...form.data.subject_ids, s.id];
                                                                                form.setData("subject_ids", next);
                                                                                if (!subjectCodeTouched) {
                                                                                    const codes = collegeSubjectOptions
                                                                                        .filter((sub) => next.includes(sub.id))
                                                                                        .map((sub) => sub.code)
                                                                                        .filter(Boolean);
                                                                                    form.setData("subject_code", Array.from(new Set(codes)).join(", "));
                                                                                }
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
                                                        {form.errors.subject_ids ? <p className="text-destructive text-xs">{form.errors.subject_ids}</p> : null}
                                                    </div>

                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>Class name / subject code</Label>
                                                        <Input
                                                            value={form.data.subject_code}
                                                            placeholder="Auto-generated from subjects..."
                                                            onChange={(e) => {
                                                                setSubjectCodeTouched(true);
                                                                form.setData("subject_code", e.target.value);
                                                            }}
                                                        />
                                                        <p className="text-muted-foreground text-xs">Auto-generated from selected subjects. You can customize it.</p>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>Year level</Label>
                                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                            {[1, 2, 3, 4].map((year) => (
                                                                <button
                                                                    key={year}
                                                                    type="button"
                                                                    onClick={() => form.setData("academic_year", year)}
                                                                    className={`rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${
                                                                        form.data.academic_year === year
                                                                            ? "border-primary bg-primary text-primary-foreground"
                                                                            : "border-border bg-background text-muted-foreground hover:bg-muted"
                                                                    }`}
                                                                >
                                                                    {year}{year === 1 ? "st" : year === 2 ? "nd" : year === 3 ? "rd" : "th"} Year
                                                                </button>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <div className="space-y-2">
                                                        <Label>SHS track</Label>
                                                        <Select
                                                            value={form.data.shs_track_id ? String(form.data.shs_track_id) : ""}
                                                            onValueChange={(val) => {
                                                                const trackId = Number(val);
                                                                form.setData("shs_track_id", trackId);
                                                                form.setData("shs_strand_id", null);
                                                                form.setData("subject_code_shs", "");
                                                                void loadShsStrands(trackId);
                                                            }}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder={shsStrandsLoading ? "Loading..." : "Select track"} />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {options.shs_tracks.map((track) => (
                                                                    <SelectItem key={track.id} value={String(track.id)}>{track.track_name}</SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>SHS strand</Label>
                                                        <Select
                                                            value={form.data.shs_strand_id ? String(form.data.shs_strand_id) : ""}
                                                            onValueChange={(val) => {
                                                                const strandId = Number(val);
                                                                form.setData("shs_strand_id", strandId);
                                                                form.setData("subject_code_shs", "");
                                                                void loadShsSubjects(strandId);
                                                            }}
                                                            disabled={!form.data.shs_track_id || shsStrandsLoading}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder={shsStrandsLoading ? "Loading..." : "Select strand"} />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {shsStrandOptions.map((strand) => (
                                                                    <SelectItem key={strand.id} value={String(strand.id)}>{strand.label}</SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>SHS subject</Label>
                                                        <Select
                                                            value={form.data.subject_code_shs}
                                                            onValueChange={(val) => form.setData("subject_code_shs", val)}
                                                            disabled={!form.data.shs_strand_id || shsSubjectsLoading}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder={shsSubjectsLoading ? "Loading..." : "Select subject"} />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {shsSubjectOptions.map((subject) => (
                                                                    <SelectItem key={subject.code} value={subject.code}>{subject.label}</SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>Grade level</Label>
                                                        <Select value={form.data.grade_level} onValueChange={(val) => form.setData("grade_level", val)}>
                                                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="Grade 11">Grade 11</SelectItem>
                                                                <SelectItem value="Grade 12">Grade 12</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-border/60 h-fit shadow-sm">
                                    <CardHeader className="bg-muted/20 border-b pb-4">
                                        <CardTitle className="text-base font-semibold">Logistics & Organization</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-6">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2 sm:col-span-2">
                                                <Label>Faculty</Label>
                                                <Select
                                                    value={form.data.faculty_id ? String(form.data.faculty_id) : ""}
                                                    onValueChange={(val) => form.setData("faculty_id", val)}
                                                >
                                                    <SelectTrigger className="w-full"><SelectValue placeholder="Select faculty" /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="">TBA</SelectItem>
                                                        {options.faculty.map((f) => (
                                                            <SelectItem key={f.id} value={String(f.id)}>{f.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Section</Label>
                                                <div className="grid grid-cols-4 gap-2">
                                                    {options.sections.map((s) => (
                                                        <button
                                                            key={s}
                                                            type="button"
                                                            onClick={() => form.setData("section", s)}
                                                            className={`rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${
                                                                form.data.section === s
                                                                    ? "border-primary bg-primary text-primary-foreground"
                                                                    : "border-border bg-background text-muted-foreground hover:bg-muted"
                                                            }`}
                                                        >
                                                            {s}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Max slots</Label>
                                                <Input type="number" min={1} value={form.data.maximum_slots} onChange={(e) => form.setData("maximum_slots", Number(e.target.value))} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Semester</Label>
                                                <Select value={form.data.semester} onValueChange={(val) => form.setData("semester", val)}>
                                                    <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {options.semesters.map((s) => (
                                                            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>School Year</Label>
                                                <Input value={form.data.school_year} onChange={(e) => form.setData("school_year", e.target.value)} />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="flex justify-end pt-2">
                                <Button type="button" size="sm" onClick={() => setActiveTab("schedule")}>
                                    Next <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </TabsContent>

                        <TabsContent value="schedule" className="m-0 space-y-6 outline-none">
                            <Card className="border-border/60 shadow-sm">
                                <CardHeader className="bg-muted/20 border-b pb-4">
                                    <CardTitle className="text-base font-semibold">Schedule Builder</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-6">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-2">
                                            <Label>Start Time</Label>
                                            <Input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>End Time</Label>
                                            <Input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Recurrence Pattern</Label>
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
                                        <div className="space-y-2">
                                            <Label>Select Days</Label>
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

                                    {form.data.schedules.length > 0 && (
                                        <div className="space-y-2">
                                            <Label>Generated Schedules ({form.data.schedules.length})</Label>
                                            <div className="grid gap-1.5">
                                                {form.data.schedules.map((s, i) => {
                                                    const p = getPalette(s.day_of_week);
                                                    return (
                                                        <div key={i} className={`flex items-center justify-between rounded-md border-l-[3px] ${p.accent} ${p.bg} ${p.border} p-2`}>
                                                            <div className="flex items-center gap-2">
                                                                <Calendar className={`h-3.5 w-3.5 ${p.text}`} />
                                                                <span className={`text-xs font-medium ${p.text}`}>{s.day_of_week}</span>
                                                                <span className="text-muted-foreground text-xs">{fmtTime(s.start_time)} - {fmtTime(s.end_time)}</span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    const day = s.day_of_week;
                                                                    setScheduleRooms((prev) => {
                                                                        const next = { ...prev };
                                                                        delete next[day];
                                                                        return next;
                                                                    });
                                                                    form.setData("schedules", form.data.schedules.filter((_, idx) => idx !== i));
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
                                </CardContent>
                            </Card>

                            <div className="flex justify-between pt-2">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setActiveTab("details")}>
                                    <ChevronLeft className="mr-1 h-3.5 w-3.5" /> Back
                                </Button>
                                <Button type="button" size="sm" onClick={() => setActiveTab("rooms")} disabled={form.data.schedules.length === 0}>
                                    Next <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </TabsContent>

                        <TabsContent value="rooms" className="m-0 space-y-6 outline-none">
                            <div className="flex items-center justify-between">
                                <Label className="text-sm font-medium">Drag schedules onto rooms</Label>
                                <Button type="button" variant="outline" size="sm" onClick={autoAssignRooms} className="h-7 text-xs gap-1">
                                    <Wand2 className="h-3 w-3" /> Auto-assign
                                </Button>
                            </div>

                            <DndContext sensors={sensors} onDragEnd={handleDragEnd} onDragOver={handleDragOver}>
                                <div className="flex gap-3">
                                    <div className="w-[200px] shrink-0">
                                        <ScrollArea className="h-[520px]">
                                            <div className="space-y-2 pr-2">
                                                <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-1">Schedules</div>
                                                {form.data.schedules.map((s, i) => (
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

                                    <div className="flex-1">
                                        <ScrollArea className="h-[520px]">
                                            <div className="grid grid-cols-2 gap-2 pr-2">
                                                {options.rooms.map((room) => {
                                                    const assigned = form.data.schedules.filter((s) => scheduleRooms[s.day_of_week] === room.id);
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
                                                                {assigned.length === 0 && <div className="text-[10px] text-muted-foreground/60 italic">Drop here</div>}
                                                            </div>
                                                        </DroppableRoomTile>
                                                    );
                                                })}
                                            </div>
                                        </ScrollArea>
                                    </div>
                                </div>
                            </DndContext>

                            <div className="flex justify-between pt-3 border-t">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setActiveTab("schedule")}>
                                    <ChevronLeft className="mr-1 h-3.5 w-3.5" /> Back
                                </Button>
                                <Button type="button" size="sm" onClick={handleSubmit} disabled={!canSubmit() || isSubmitting}>
                                    {isSubmitting ? <RefreshCw className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Plus className="mr-1.5 h-3.5 w-3.5" />}
                                    {isSubmitting ? "Creating..." : "Create Class"}
                                </Button>
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}
