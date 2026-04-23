import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { VisualRadioButton } from "@/Components/ui/visual-radio-button";
import { useDraggable, useDroppable, DndContext, MouseSensor, TouchSensor, useSensor, useSensors, type DragEndEvent, type DragMoveEvent } from "@dnd-kit/core";
import { useForm } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    BookOpen,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    GripVertical,
    MapPin,
    Plus,
    RefreshCw,
    Trash2,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
const DAYS_SHORT = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as const;
const HOUR_START = 7;
const HOUR_END = 19;
const CELL_H = 52;
const SNAP_MINUTES = 15;

const PALETTES = [
    { accent: "border-l-rose-500", bg: "bg-rose-500/10 dark:bg-rose-400/15", text: "text-rose-700 dark:text-rose-300", border: "border-rose-200 dark:border-rose-800", ghost: "bg-rose-500/5 dark:bg-rose-400/8" },
    { accent: "border-l-sky-500", bg: "bg-sky-500/10 dark:bg-sky-400/15", text: "text-sky-700 dark:text-sky-300", border: "border-sky-200 dark:border-sky-800", ghost: "bg-sky-500/5 dark:bg-sky-400/8" },
    { accent: "border-l-amber-500", bg: "bg-amber-500/10 dark:bg-amber-400/15", text: "text-amber-700 dark:text-amber-300", border: "border-amber-200 dark:border-amber-800", ghost: "bg-amber-500/5 dark:bg-amber-400/8" },
    { accent: "border-l-emerald-500", bg: "bg-emerald-500/10 dark:bg-emerald-400/15", text: "text-emerald-700 dark:text-emerald-300", border: "border-emerald-200 dark:border-emerald-800", ghost: "bg-emerald-500/5 dark:bg-emerald-400/8" },
    { accent: "border-l-violet-500", bg: "bg-violet-500/10 dark:bg-violet-400/15", text: "text-violet-700 dark:text-violet-300", border: "border-violet-200 dark:border-violet-800", ghost: "bg-violet-500/5 dark:bg-violet-400/8" },
    { accent: "border-l-orange-500", bg: "bg-orange-500/10 dark:bg-orange-400/15", text: "text-orange-700 dark:text-orange-300", border: "border-orange-200 dark:border-orange-800", ghost: "bg-orange-500/5 dark:bg-orange-400/8" },
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

function parseTimeToMinutes(value: string): number | null {
    const match = value.match(/^(\d{1,2}):(\d{2})$/);
    if (!match) return null;
    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    if (!Number.isFinite(hours) || !Number.isFinite(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
    return hours * 60 + minutes;
}

function minutesToTime(totalMinutes: number): string {
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

function schedulesOverlap(a: { start_time: string; end_time: string }, b: { start_time: string; end_time: string }): boolean {
    const aStart = parseTimeToMinutes(a.start_time);
    const aEnd = parseTimeToMinutes(a.end_time);
    const bStart = parseTimeToMinutes(b.start_time);
    const bEnd = parseTimeToMinutes(b.end_time);
    if (aStart === null || aEnd === null || bStart === null || bEnd === null) return false;
    return aStart < bEnd && aEnd > bStart;
}

export default function CreateClassDialog({ open, onOpenChange, options, defaults, existingSchedules, onClassCreated }: {
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
    existingSchedules: Array<{
        id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty_name: string | null;
        room_id: number | null;
        room: string | null;
        day_of_week: string;
        start_time: string;
        end_time: string;
        time_range: string;
    }>;
    onClassCreated: (classItem: any) => void;
}) {
    const [activeTab, setActiveTab] = React.useState("details");
    const [isSubmitting, setIsSubmitting] = React.useState(false);

    const [collegeSubjectOptions, setCollegeSubjectOptions] = React.useState<Array<{ id: number; label: string; code: string; title: string }>>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = React.useState(false);
    const [shsStrandOptions, setShsStrandOptions] = React.useState<Array<{ id: string | number; label: string }>>([]);
    const [shsStrandsLoading, setShsStrandsLoading] = React.useState(false);
    const [shsSubjectOptions, setShsSubjectOptions] = React.useState<Array<{ code: string; label: string; title: string }>>([]);
    const [shsSubjectsLoading, setShsSubjectsLoading] = React.useState(false);
    const [subjectCodeTouched, setSubjectCodeTouched] = React.useState(false);

    const [selectedBlockIndex, setSelectedBlockIndex] = React.useState<number | null>(null);
    const [hoveredBlockIndex, setHoveredBlockIndex] = React.useState<number | null>(null);

    const [resizing, setResizing] = React.useState<{
        blockId: string;
        edge: "top" | "bottom";
        initialY: number;
        originalStartMin: number;
        originalEndMin: number;
        currentStartMin?: number;
        currentEndMin?: number;
    } | null>(null);
    const resizeTooltipRef = React.useRef<HTMLDivElement>(null);

    const [dragPreview, setDragPreview] = React.useState<{
        dayIdx: number;
        startMin: number;
        duration: number;
        blockId: string;
    } | null>(null);

    const form = useForm({
        classification: "college" as "college" | "shs",
        course_id: null as number | null,
        subject_id: null as number | null,
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
        room_id: null as number | null,
        maximum_slots: 40,
        schedules: [] as Array<{ id: string; day_of_week: string; start_time: string; end_time: string; room_id: number | null }>,
    });

    React.useEffect(() => {
        if (!open) return;
        form.setData({
            classification: "college",
            course_id: null,
            subject_id: null,
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
            room_id: null,
            maximum_slots: 40,
            schedules: [],
        });
        setCollegeSubjectOptions([]);
        setShsStrandOptions([]);
        setShsSubjectOptions([]);
        setSubjectCodeTouched(false);
        setSelectedBlockIndex(null);
        setHoveredBlockIndex(null);
        setResizing(null);
        setDragPreview(null);
        setActiveTab("details");
    }, [open]);

    const selectedRoom = React.useMemo(() => {
        if (!form.data.room_id) return null;
        return options.rooms.find((r) => r.id === form.data.room_id) ?? null;
    }, [form.data.room_id, options.rooms]);

    const roomExistingSchedules = React.useMemo(() => {
        if (!form.data.room_id) return [];
        return existingSchedules.filter((s) => s.room_id === form.data.room_id);
    }, [existingSchedules, form.data.room_id]);

    const selectedFacultyName = React.useMemo(() => {
        if (!form.data.faculty_id) return null;
        return options.faculty.find((f) => String(f.id) === String(form.data.faculty_id))?.name ?? null;
    }, [form.data.faculty_id, options.faculty]);

    const conflicts = React.useMemo(() => {
        const result: Array<{ type: "room" | "faculty"; newBlock: typeof form.data.schedules[0]; existing: typeof existingSchedules[0] }> = [];
        for (const ns of form.data.schedules) {
            for (const es of existingSchedules) {
                if (es.day_of_week !== ns.day_of_week) continue;
                if (!schedulesOverlap(ns, es)) continue;
                if (ns.room_id !== null && es.room_id === ns.room_id) {
                    result.push({ type: "room", newBlock: ns, existing: es });
                }
                if (selectedFacultyName && es.faculty_name === selectedFacultyName) {
                    result.push({ type: "faculty", newBlock: ns, existing: es });
                }
            }
        }
        return result;
    }, [form.data.schedules, existingSchedules, selectedFacultyName]);

    const loadCollegeSubjects = async (courseId: number | null) => {
        if (!courseId) {
            setCollegeSubjectOptions([]);
            return;
        }
        setCollegeSubjectsLoading(true);
        try {
            const response = await fetch(route("administrators.classes.options.subjects", { course_ids: [courseId] }));
            const data = await response.json() as { data: Array<{ id: number; label: string; code: string; title: string }> };
            setCollegeSubjectOptions(data.data);
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

    const addScheduleBlock = (day?: string, start?: string, end?: string) => {
        const newBlock = {
            id: crypto.randomUUID(),
            day_of_week: day ?? "Monday",
            start_time: start ?? "08:00",
            end_time: end ?? "10:00",
            room_id: form.data.room_id ?? options.rooms[0]?.id ?? null,
        };
        form.setData("schedules", [...form.data.schedules, newBlock]);
        setSelectedBlockIndex(form.data.schedules.length);
    };

    const removeScheduleBlock = (index: number) => {
        const next = form.data.schedules.filter((_, i) => i !== index);
        form.setData("schedules", next);
        if (selectedBlockIndex === index) setSelectedBlockIndex(null);
        else if (selectedBlockIndex !== null && selectedBlockIndex > index) setSelectedBlockIndex(selectedBlockIndex - 1);
    };

    const updateBlock = (index: number, patch: Partial<typeof form.data.schedules[0]>) => {
        form.setData("schedules", form.data.schedules.map((s, i) => (i === index ? { ...s, ...patch } : s)));
    };

    const generateFromRecurrence = (recurrence: "mwf" | "tth" | "daily" | "custom", customDays: string[], startTime: string, endTime: string) => {
        let days: string[] = [];
        switch (recurrence) {
            case "mwf": days = ["Monday", "Wednesday", "Friday"]; break;
            case "tth": days = ["Tuesday", "Thursday"]; break;
            case "daily": days = [...DAYS]; break;
            case "custom": days = customDays; break;
        }
        if (!startTime || !endTime || days.length === 0) return;
        const newBlocks = days.map((day) => ({
            id: crypto.randomUUID(),
            day_of_week: day,
            start_time: startTime,
            end_time: endTime,
            room_id: form.data.room_id ?? options.rooms[0]?.id ?? null,
        }));
        form.setData("schedules", [...form.data.schedules, ...newBlocks]);
    };

    const autoAssignRooms = () => {
        if (options.rooms.length === 0 || form.data.schedules.length === 0) return;
        form.setData("schedules", form.data.schedules.map((s, i) => ({ ...s, room_id: options.rooms[i % options.rooms.length].id })));
        toast.success("Rooms auto-assigned");
    };

    const handleDragMove = (event: DragMoveEvent) => {
        const blockId = (event.active.data.current as { blockId?: string })?.blockId;
        if (!blockId) return;
        const overId = event.over?.id;
        if (!overId || typeof overId !== "string" || !overId.startsWith("day-")) {
            setDragPreview(null);
            return;
        }
        const dayIdx = Number(overId.replace("day-", ""));
        const block = form.data.schedules.find((s) => s.id === blockId);
        if (!block) return;
        const startMin = parseTimeToMinutes(block.start_time);
        const endMin = parseTimeToMinutes(block.end_time);
        if (startMin === null || endMin === null) return;
        setDragPreview({
            dayIdx,
            startMin,
            duration: endMin - startMin,
            blockId,
        });
    };

    const handleDragEnd = (event: DragEndEvent) => {
        setDragPreview(null);
        const { active, over } = event;
        if (!over || !active.data.current) return;
        const dayIdx = (over.data.current as { dayIdx?: number })?.dayIdx;
        const blockId = (active.data.current as { blockId?: string })?.blockId;
        if (dayIdx === undefined || !blockId) return;
        const day = DAYS[dayIdx];
        if (!day) return;
        const idx = form.data.schedules.findIndex((s) => s.id === blockId);
        if (idx === -1) return;
        updateBlock(idx, { day_of_week: day });
    };

    React.useEffect(() => {
        if (!resizing) return;

        const handlePointerMove = (e: PointerEvent) => {
            const deltaY = e.clientY - resizing.initialY;
            const pxPerMin = CELL_H / 60;
            const deltaMin = Math.round(deltaY / pxPerMin);

            let nStart = resizing.originalStartMin;
            let nEnd = resizing.originalEndMin;

            if (resizing.edge === "top") {
                nStart += deltaMin;
                nStart = Math.round(nStart / SNAP_MINUTES) * SNAP_MINUTES;
                nStart = Math.max(HOUR_START * 60, Math.min(nStart, nEnd - SNAP_MINUTES));
            } else {
                nEnd += deltaMin;
                nEnd = Math.round(nEnd / SNAP_MINUTES) * SNAP_MINUTES;
                nEnd = Math.max(nStart + SNAP_MINUTES, Math.min(nEnd, HOUR_END * 60));
            }

            if (nStart !== resizing.currentStartMin || nEnd !== resizing.currentEndMin) {
                resizing.currentStartMin = nStart;
                resizing.currentEndMin = nEnd;

                const idx = form.data.schedules.findIndex((s) => s.id === resizing.blockId);
                if (idx !== -1) {
                    form.setData("schedules", form.data.schedules.map((s, i) =>
                        i === idx ? { ...s, start_time: minutesToTime(nStart), end_time: minutesToTime(nEnd) } : s
                    ));
                }
            }

            if (resizeTooltipRef.current) {
                resizeTooltipRef.current.style.opacity = "1";
                resizeTooltipRef.current.style.transform = `translate(${e.clientX + 16}px, ${e.clientY - 16}px)`;
                resizeTooltipRef.current.innerText = `${minutesToTime(nStart)} – ${minutesToTime(nEnd)}`;
            }
        };

        const handlePointerUp = () => {
            if (resizeTooltipRef.current) resizeTooltipRef.current.style.opacity = "0";
            setResizing(null);
        };

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);

        return () => {
            window.removeEventListener("pointermove", handlePointerMove);
            window.removeEventListener("pointerup", handlePointerUp);
        };
    }, [resizing]);

    const handleResizeStart = (blockId: string, edge: "top" | "bottom", initialY: number, originalStartMin: number, originalEndMin: number) => {
        setResizing({ blockId, edge, initialY, originalStartMin, originalEndMin });
    };

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 5 } }),
    );

    const handleSubmit = async () => {
        if (form.data.schedules.length === 0) {
            toast.error("Please add at least one schedule block.");
            return;
        }
        if (form.data.schedules.some((s) => s.room_id === null)) {
            toast.error("Please assign a room to every schedule block.");
            return;
        }
        setIsSubmitting(true);

        const payload = { ...form.data, course_codes: form.data.course_id ? [form.data.course_id] : [], subject_ids: form.data.subject_id ? [form.data.subject_id] : [] };

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

    const hours = React.useMemo(() => Array.from({ length: HOUR_END - HOUR_START + 1 }, (_, i) => HOUR_START + i), []);

    const blockHasConflict = (block: typeof form.data.schedules[0]) => {
        return conflicts.some((c) => c.newBlock.id === block.id);
    };

    const getBlockConflicts = (block: typeof form.data.schedules[0]) => {
        return conflicts.filter((c) => c.newBlock.id === block.id);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-xl md:max-w-2xl lg:max-w-[1100px] flex flex-col gap-0 p-0">
                <div ref={resizeTooltipRef} className="fixed z-[100] pointer-events-none opacity-0 bg-foreground text-background text-xs font-medium px-2 py-1 rounded shadow-lg transition-opacity" />

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
                            <TabsTrigger value="review" className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                                <Check className="mr-2 h-4 w-4" /> Review
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
                                        <CardTitle className="text-base font-semibold">Primary Room</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-6">
                                        <div className="space-y-2">
                                            <Label>Room</Label>
                                            <Combobox
                                                label=""
                                                options={options.rooms.map((r) => ({ label: r.name, value: String(r.id), description: r.class_code || undefined }))}
                                                value={form.data.room_id ? String(form.data.room_id) : ""}
                                                onValueChange={(val) => {
                                                    const roomId = val ? Number(val) : null;
                                                    form.setData("room_id", roomId);
                                                    if (roomId) {
                                                        form.setData("schedules", form.data.schedules.map((s) => ({ ...s, room_id: roomId })));
                                                    }
                                                }}
                                                placeholder="Search and select a room..."
                                                emptyText="No rooms found."
                                                searchPlaceholder="Search rooms..."
                                            />
                                            {selectedRoom && roomExistingSchedules.length > 0 && (
                                                <p className="text-muted-foreground text-xs">
                                                    {roomExistingSchedules.length} existing schedule{roomExistingSchedules.length !== 1 ? "s" : ""} in {selectedRoom.name} will be shown in the timetable.
                                                </p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>

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
                                                            form.setData("course_id", null);
                                                            form.setData("subject_id", null);
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
                                                            form.setData("course_id", null);
                                                            form.setData("subject_id", null);
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
                                                        <Label>Associated course</Label>
                                                        <Combobox
                                                            label=""
                                                            options={options.courses.map((c) => ({ label: `${c.code} — ${c.title}`, value: String(c.id), description: c.curriculum_year ? `Curriculum ${c.curriculum_year}` : undefined }))}
                                                            value={form.data.course_id ? String(form.data.course_id) : ""}
                                                            onValueChange={(val) => {
                                                                const courseId = Number(val);
                                                                form.setData("course_id", courseId);
                                                                form.setData("subject_id", null);
                                                                form.setData("subject_code", "");
                                                                setSubjectCodeTouched(false);
                                                                void loadCollegeSubjects(courseId);
                                                            }}
                                                            placeholder="Search and select a course..."
                                                            emptyText="No courses found."
                                                            searchPlaceholder="Search courses..."
                                                        />
                                                        {form.errors.course_codes ? <p className="text-destructive text-xs">{form.errors.course_codes}</p> : null}
                                                    </div>

                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>Subject</Label>
                                                        {collegeSubjectsLoading ? (
                                                            <div className="text-muted-foreground text-xs py-2">Loading subjects...</div>
                                                        ) : collegeSubjectOptions.length === 0 ? (
                                                            <div className="text-muted-foreground text-xs py-2">Select a course to see subjects.</div>
                                                        ) : (
                                                            <Combobox
                                                                label=""
                                                                options={collegeSubjectOptions.map((s) => ({ label: `${s.code} — ${s.title}`, value: String(s.id), description: s.label }))}
                                                                value={form.data.subject_id ? String(form.data.subject_id) : ""}
                                                                onValueChange={(val) => {
                                                                    const subjectId = Number(val);
                                                                    form.setData("subject_id", subjectId);
                                                                    const selected = collegeSubjectOptions.find((s) => s.id === subjectId);
                                                                    if (selected && !subjectCodeTouched) {
                                                                        form.setData("subject_code", selected.code);
                                                                    }
                                                                }}
                                                                placeholder="Search and select a subject..."
                                                                emptyText="No subjects found for this course."
                                                                searchPlaceholder="Search subjects..."
                                                            />
                                                        )}
                                                        {form.errors.subject_ids ? <p className="text-destructive text-xs">{form.errors.subject_ids}</p> : null}
                                                    </div>

                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>Class name / subject code</Label>
                                                        <Input
                                                            value={form.data.subject_code}
                                                            placeholder="Auto-generated from subject..."
                                                            onChange={(e) => {
                                                                setSubjectCodeTouched(true);
                                                                form.setData("subject_code", e.target.value);
                                                            }}
                                                        />
                                                        <p className="text-muted-foreground text-xs">Auto-generated from selected subject. You can customize it.</p>
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
                                                            value={form.data.shs_track_id ? String(form.data.shs_track_id) : "__none__"}
                                                            onValueChange={(val) => {
                                                                const trackId = val === "__none__" ? null : Number(val);
                                                                form.setData("shs_track_id", trackId);
                                                                form.setData("shs_strand_id", null);
                                                                form.setData("subject_code_shs", "");
                                                                void loadShsStrands(trackId);
                                                            }}
                                                        >
                                                            <SelectTrigger className="w-full"><SelectValue placeholder="Select track" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="__none__">Select track</SelectItem>
                                                                {options.shs_tracks.map((track) => (
                                                                    <SelectItem key={track.id} value={String(track.id)}>{track.track_name}</SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>SHS strand</Label>
                                                        <Select
                                                            value={form.data.shs_strand_id ? String(form.data.shs_strand_id) : "__none__"}
                                                            onValueChange={(val) => {
                                                                const strandId = val === "__none__" ? null : Number(val);
                                                                form.setData("shs_strand_id", strandId);
                                                                form.setData("subject_code_shs", "");
                                                                void loadShsSubjects(strandId);
                                                            }}
                                                            disabled={!form.data.shs_track_id || shsStrandsLoading}
                                                        >
                                                            <SelectTrigger className="w-full"><SelectValue placeholder={shsStrandsLoading ? "Loading..." : "Select strand"} /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="__none__">Select strand</SelectItem>
                                                                {shsStrandOptions.map((strand) => (
                                                                    <SelectItem key={strand.id} value={String(strand.id)}>{strand.label}</SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>SHS subject</Label>
                                                        {shsSubjectsLoading ? (
                                                            <div className="text-muted-foreground text-xs py-2">Loading subjects...</div>
                                                        ) : shsSubjectOptions.length === 0 ? (
                                                            <div className="text-muted-foreground text-xs py-2">Select a strand to see subjects.</div>
                                                        ) : (
                                                            <Combobox
                                                                label=""
                                                                options={shsSubjectOptions.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.code, description: s.label }))}
                                                                value={form.data.subject_code_shs}
                                                                onValueChange={(val) => form.setData("subject_code_shs", val)}
                                                                placeholder="Search and select a subject..."
                                                                emptyText="No subjects found for this strand."
                                                                searchPlaceholder="Search subjects..."
                                                            />
                                                        )}
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
                                                <Combobox
                                                    label=""
                                                    options={[{ label: "TBA — To be assigned", value: "__none__" }, ...options.faculty.map((f) => ({ label: f.name, value: String(f.id), description: f.department || undefined }))]}
                                                    value={form.data.faculty_id ? String(form.data.faculty_id) : "__none__"}
                                                    onValueChange={(val) => form.setData("faculty_id", val === "__none__" ? null : val)}
                                                    placeholder="Search and select faculty..."
                                                    emptyText="No faculty found."
                                                    searchPlaceholder="Search faculty..."
                                                />
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
                            {conflicts.length > 0 && (
                                <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border px-4 py-3 text-sm">
                                    <div className="flex items-center gap-2 font-semibold mb-1">
                                        <AlertTriangle className="h-4 w-4" />
                                        {conflicts.length} potential conflict{conflicts.length !== 1 ? "s" : ""} detected
                                    </div>
                                    <ul className="space-y-1 text-xs">
                                        {conflicts.slice(0, 5).map((c, i) => (
                                            <li key={i}>
                                                {c.type === "room" ? "Room" : "Faculty"} conflict on {c.newBlock.day_of_week} {fmtTime(c.newBlock.start_time)}–{fmtTime(c.newBlock.end_time)} with {c.existing.subject_code} ({c.existing.section})
                                            </li>
                                        ))}
                                        {conflicts.length > 5 && <li>...and {conflicts.length - 5} more</li>}
                                    </ul>
                                </div>
                            )}

                            <Card className="border-border/60 shadow-sm">
                                <CardHeader className="bg-muted/20 border-b pb-4">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base font-semibold">Quick Generate</CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-6">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-2">
                                            <Label>Start Time</Label>
                                            <Input type="time" defaultValue="08:00" id="quick-start" />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>End Time</Label>
                                            <Input type="time" defaultValue="10:00" id="quick-end" />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Recurrence Pattern</Label>
                                        <div className="flex flex-wrap gap-1.5">
                                            {[
                                                { key: "mwf" as const, label: "MWF", desc: "Mon, Wed, Fri" },
                                                { key: "tth" as const, label: "TTh", desc: "Tue, Thu" },
                                                { key: "daily" as const, label: "Daily", desc: "Mon–Sat" },
                                            ].map((r) => (
                                                <button
                                                    key={r.key}
                                                    type="button"
                                                    onClick={() => {
                                                        const startEl = document.getElementById("quick-start") as HTMLInputElement | null;
                                                        const endEl = document.getElementById("quick-end") as HTMLInputElement | null;
                                                        generateFromRecurrence(r.key, [], startEl?.value ?? "08:00", endEl?.value ?? "10:00");
                                                    }}
                                                    className="flex flex-col items-start rounded-md border border-border bg-background px-3 py-2 text-left transition-colors hover:bg-muted min-w-[80px]"
                                                >
                                                    <span className="text-xs font-bold text-foreground">{r.label}</span>
                                                    <span className="text-[10px] text-muted-foreground">{r.desc}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => {
                                            const startEl = document.getElementById("quick-start") as HTMLInputElement | null;
                                            const endEl = document.getElementById("quick-end") as HTMLInputElement | null;
                                            addScheduleBlock("Monday", startEl?.value ?? "08:00", endEl?.value ?? "10:00");
                                        }}>
                                            <Plus className="mr-1 h-3.5 w-3.5" /> Add Single Block
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {form.data.schedules.length > 0 && (
                                <Card className="border-border/60 shadow-sm">
                                    <CardHeader className="bg-muted/20 border-b pb-4">
                                        <CardTitle className="text-base font-semibold">
                                            Visual Timetable
                                            {selectedRoom && <span className="text-muted-foreground text-xs font-normal ml-2">— showing existing classes in {selectedRoom.name}</span>}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-6">
                                        <DndContext sensors={sensors} onDragEnd={handleDragEnd} onDragMove={handleDragMove}>
                                            <div className="relative border rounded-lg overflow-hidden">
                                                <div className="grid grid-cols-[60px_repeat(6,1fr)] border-b bg-muted/30">
                                                    <div className="p-2 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider text-center border-r">Time</div>
                                                    {DAYS.map((day, i) => (
                                                        <div key={day} className="p-2 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider text-center border-r last:border-r-0">
                                                            {DAYS_SHORT[i]}
                                                        </div>
                                                    ))}
                                                </div>
                                                <div className="grid grid-cols-[60px_repeat(6,1fr)]" style={{ height: (HOUR_END - HOUR_START + 1) * CELL_H }}>
                                                    <div className="relative border-r bg-muted/10">
                                                        {hours.map((h) => (
                                                            <div key={h} className="absolute w-full text-[10px] text-muted-foreground text-center -translate-y-1/2" style={{ top: (h - HOUR_START) * CELL_H }}>
                                                                {h <= 12 ? `${h === 0 ? 12 : h} ${h < 12 ? "AM" : "PM"}` : `${h - 12} PM`}
                                                            </div>
                                                        ))}
                                                    </div>
                                                    {DAYS.map((day, dayIdx) => {
                                                        const dayBlocks = form.data.schedules.filter((s) => s.day_of_week === day);
                                                        const dayExisting = roomExistingSchedules.filter((s) => s.day_of_week === day);
                                                        return (
                                                            <DayColumn
                                                                key={day}
                                                                dayIdx={dayIdx}
                                                                dayBlocks={dayBlocks}
                                                                allBlocks={form.data.schedules}
                                                                existingBlocks={dayExisting}
                                                                onBlockClick={setSelectedBlockIndex}
                                                                onBlockHover={setHoveredBlockIndex}
                                                                blockHasConflict={blockHasConflict}
                                                                getBlockConflicts={getBlockConflicts}
                                                                onAddBlock={(time) => addScheduleBlock(day, time, minutesToTime(parseTimeToMinutes(time)! + 120))}
                                                                dragPreview={dragPreview?.dayIdx === dayIdx ? dragPreview : null}
                                                                onResizeStart={handleResizeStart}
                                                            />
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </DndContext>
                                    </CardContent>
                                </Card>
                            )}

                            {form.data.schedules.length > 0 && (
                                <Card className="border-border/60 shadow-sm">
                                    <CardHeader className="bg-muted/20 border-b pb-4">
                                        <CardTitle className="text-base font-semibold">Schedule Blocks ({form.data.schedules.length})</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3 pt-6">
                                        {form.data.schedules.map((block, i) => {
                                            const hasConflict = blockHasConflict(block);
                                            const blockConflicts = getBlockConflicts(block);
                                            const pal = getPalette(block.day_of_week);
                                            return (
                                                <div
                                                    key={block.id}
                                                    className={`rounded-xl border transition-colors ${
                                                        selectedBlockIndex === i
                                                            ? "border-primary bg-primary/5 shadow-sm"
                                                            : hasConflict
                                                                ? "border-destructive bg-destructive/5"
                                                                : "border-border bg-background"
                                                    }`}
                                                >
                                                    <button
                                                        type="button"
                                                        className="flex w-full flex-col gap-2 p-4 text-left"
                                                        onClick={() => setSelectedBlockIndex(i)}
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <div className="flex items-center gap-2">
                                                                <span className={`text-xs font-bold ${hasConflict ? "text-destructive" : pal.text}`}>{block.day_of_week}</span>
                                                                <span className="text-muted-foreground text-xs">{fmtTime(block.start_time)} – {fmtTime(block.end_time)}</span>
                                                                {hasConflict && <AlertTriangle className="h-3.5 w-3.5 text-destructive" />}
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={(e) => { e.stopPropagation(); removeScheduleBlock(i); }}
                                                                className="text-muted-foreground hover:text-destructive transition-colors"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                        {hasConflict && (
                                                            <div className="text-destructive text-[10px]">
                                                                Conflicts with: {blockConflicts.map((c) => `${c.existing.subject_code} (${c.existing.section})`).join(", ")}
                                                            </div>
                                                        )}
                                                    </button>

                                                    {selectedBlockIndex === i && (
                                                        <div className="grid gap-3 border-t px-4 py-3 sm:grid-cols-3">
                                                            <div className="space-y-1.5">
                                                                <Label className="text-[10px] uppercase tracking-wider text-muted-foreground">Day</Label>
                                                                <Select value={block.day_of_week} onValueChange={(val) => updateBlock(i, { day_of_week: val })}>
                                                                    <SelectTrigger className="h-8 text-xs"><SelectValue /></SelectTrigger>
                                                                    <SelectContent>
                                                                        {DAYS.map((d) => <SelectItem key={d} value={d}>{d}</SelectItem>)}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="space-y-1.5">
                                                                <Label className="text-[10px] uppercase tracking-wider text-muted-foreground">Start</Label>
                                                                <Input type="time" value={block.start_time} onChange={(e) => updateBlock(i, { start_time: e.target.value })} className="h-8 text-xs" />
                                                            </div>
                                                            <div className="space-y-1.5">
                                                                <Label className="text-[10px] uppercase tracking-wider text-muted-foreground">End</Label>
                                                                <Input type="time" value={block.end_time} onChange={(e) => updateBlock(i, { end_time: e.target.value })} className="h-8 text-xs" />
                                                            </div>
                                                            <div className="space-y-1.5 sm:col-span-3">
                                                                <Label className="text-[10px] uppercase tracking-wider text-muted-foreground">Room</Label>
                                                                <Select value={block.room_id ? String(block.room_id) : "__none__"} onValueChange={(val) => updateBlock(i, { room_id: val === "__none__" ? null : Number(val) })}>
                                                                    <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Select room" /></SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="__none__">No room</SelectItem>
                                                                        {options.rooms.map((r) => <SelectItem key={r.id} value={String(r.id)}>{r.name}</SelectItem>)}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </CardContent>
                                </Card>
                            )}

                            {form.data.schedules.length === 0 && (
                                <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
                                    <Clock className="text-muted-foreground h-8 w-8 mb-3" />
                                    <h3 className="text-sm font-semibold">No schedule blocks yet</h3>
                                    <p className="text-muted-foreground text-xs mt-1">Use Quick Generate or add blocks manually.</p>
                                </div>
                            )}

                            <div className="flex justify-between pt-2">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setActiveTab("details")}>
                                    <ChevronLeft className="mr-1 h-3.5 w-3.5" /> Back
                                </Button>
                                <Button type="button" size="sm" onClick={() => setActiveTab("review")} disabled={form.data.schedules.length === 0}>
                                    Next <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </TabsContent>

                        <TabsContent value="review" className="m-0 space-y-6 outline-none">
                            <Card className="border-border/60 shadow-sm">
                                <CardHeader className="bg-muted/20 border-b pb-4">
                                    <CardTitle className="text-base font-semibold">Review & Submit</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-6">
                                    <div className="grid gap-3 text-sm">
                                        <div className="flex justify-between border-b pb-2">
                                            <span className="text-muted-foreground">Classification</span>
                                            <span className="font-medium capitalize">{form.data.classification}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-2">
                                            <span className="text-muted-foreground">Subject</span>
                                            <span className="font-medium">{form.data.classification === "college" ? (form.data.subject_code || "—") : (form.data.subject_code_shs || "—")}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-2">
                                            <span className="text-muted-foreground">Faculty</span>
                                            <span className="font-medium">{options.faculty.find((f) => String(f.id) === String(form.data.faculty_id))?.name ?? "TBA"}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-2">
                                            <span className="text-muted-foreground">Section</span>
                                            <span className="font-medium">{form.data.section}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-2">
                                            <span className="text-muted-foreground">Semester</span>
                                            <span className="font-medium">{options.semesters.find((s) => s.value === form.data.semester)?.label ?? form.data.semester}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">School Year</span>
                                            <span className="font-medium">{form.data.school_year}</span>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium">Schedule Blocks ({form.data.schedules.length})</Label>
                                        <div className="space-y-1.5">
                                            {form.data.schedules.map((block, i) => {
                                                const hasConflict = blockHasConflict(block);
                                                const room = options.rooms.find((r) => r.id === block.room_id);
                                                return (
                                                    <div key={block.id} className={`flex items-center justify-between rounded-md border px-3 py-2 text-xs ${hasConflict ? "border-destructive bg-destructive/5" : "border-border bg-muted/20"}`}>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{block.day_of_week}</span>
                                                            <span className="text-muted-foreground">{fmtTime(block.start_time)} – {fmtTime(block.end_time)}</span>
                                                            {room && <span className="text-muted-foreground">• {room.name}</span>}
                                                        </div>
                                                        {hasConflict && <AlertTriangle className="h-3.5 w-3.5 text-destructive" />}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {conflicts.length > 0 && (
                                        <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border px-4 py-3 text-sm">
                                            <div className="flex items-center gap-2 font-semibold">
                                                <AlertTriangle className="h-4 w-4" />
                                                {conflicts.length} potential conflict{conflicts.length !== 1 ? "s" : ""} detected
                                            </div>
                                            <p className="text-xs mt-1">Review the schedule tab to resolve conflicts before submitting.</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex justify-between pt-2">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setActiveTab("schedule")}>
                                    <ChevronLeft className="mr-1 h-3.5 w-3.5" /> Back
                                </Button>
                                <Button type="button" size="sm" onClick={handleSubmit} disabled={isSubmitting || conflicts.length > 0}>
                                    {isSubmitting ? <RefreshCw className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Plus className="mr-1.5 h-3.5 w-3.5" />}
                                    {isSubmitting ? "Creating..." : conflicts.length > 0 ? "Resolve Conflicts First" : "Create Class"}
                                </Button>
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}

function DayColumn({ dayIdx, dayBlocks, allBlocks, existingBlocks, onBlockClick, onBlockHover, blockHasConflict, getBlockConflicts, onAddBlock, dragPreview, onResizeStart }: {
    dayIdx: number;
    dayBlocks: Array<{ id: string; day_of_week: string; start_time: string; end_time: string; room_id: number | null }>;
    allBlocks: Array<{ id: string; day_of_week: string; start_time: string; end_time: string; room_id: number | null }>;
    existingBlocks: Array<{ id: number; subject_code: string; section: string; start_time: string; end_time: string; room: string | null }>;
    onBlockClick: (index: number) => void;
    onBlockHover: (index: number | null) => void;
    blockHasConflict: (block: { id: string; day_of_week: string; start_time: string; end_time: string; room_id: number | null }) => boolean;
    getBlockConflicts: (block: { id: string; day_of_week: string; start_time: string; end_time: string; room_id: number | null }) => Array<{ type: "room" | "faculty"; existing: any }>;
    onAddBlock: (time: string) => void;
    dragPreview: { dayIdx: number; startMin: number; duration: number; blockId: string } | null;
    onResizeStart: (blockId: string, edge: "top" | "bottom", initialY: number, originalStartMin: number, originalEndMin: number) => void;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: `day-${dayIdx}`, data: { dayIdx } });
    const totalH = (HOUR_END - HOUR_START + 1) * CELL_H;

    return (
        <div
            ref={setNodeRef}
            className={`relative border-r last:border-r-0 transition-colors ${isOver ? "bg-primary/5" : ""}`}
            style={{ height: totalH }}
        >
            {Array.from({ length: HOUR_END - HOUR_START + 1 }, (_, i) => (
                <div key={i} className="absolute w-full border-b border-dashed border-border/40" style={{ top: i * CELL_H }} />
            ))}

            {Array.from({ length: HOUR_END - HOUR_START }, (_, i) => {
                const hour = HOUR_START + i;
                return (
                    <button
                        key={hour}
                        type="button"
                        className="absolute w-full hover:bg-primary/5 transition-colors cursor-cell"
                        style={{ top: i * CELL_H, height: CELL_H }}
                        onClick={() => onAddBlock(`${String(hour).padStart(2, "0")}:00`)}
                    />
                );
            })}

            {existingBlocks.map((block) => {
                const startMin = parseTimeToMinutes(block.start_time);
                const endMin = parseTimeToMinutes(block.end_time);
                if (startMin === null || endMin === null) return null;
                const top = ((startMin / 60) - HOUR_START) * CELL_H;
                const height = ((endMin - startMin) / 60) * CELL_H;
                const pal = getPalette(block.subject_code);
                return (
                    <div
                        key={`existing-${block.id}`}
                        className={`absolute overflow-hidden rounded-md border-l-[3px] px-1.5 py-1 pointer-events-none ${pal.accent} ${pal.ghost} opacity-40`}
                        style={{ top, height: Math.max(height - 2, 20), left: 2, right: 2 }}
                        title={`${block.subject_code} (${block.section}) — ${block.room || "No room"}`}
                    >
                        <div className={`truncate text-[9px] font-bold leading-tight ${pal.text} opacity-60`}>{block.subject_code}</div>
                        <div className="text-muted-foreground truncate text-[8px] leading-tight opacity-50">{block.section}</div>
                    </div>
                );
            })}

            {dragPreview && (
                <div
                    className="absolute left-[2px] right-[2px] rounded-md border-[2.5px] border-dashed border-primary/40 bg-primary/5 z-10 pointer-events-none flex items-start p-1.5 opacity-80"
                    style={{
                        top: ((dragPreview.startMin / 60) - HOUR_START) * CELL_H,
                        height: (dragPreview.duration / 60) * CELL_H,
                    }}
                >
                    <span className="text-[10px] font-bold tracking-tight uppercase opacity-70 text-primary">Move Here</span>
                </div>
            )}

            {dayBlocks.map((block) => {
                const globalIndex = allBlocks.findIndex((b) => b.id === block.id);
                const startMin = parseTimeToMinutes(block.start_time);
                const endMin = parseTimeToMinutes(block.end_time);
                if (startMin === null || endMin === null) return null;
                const top = ((startMin / 60) - HOUR_START) * CELL_H;
                const height = ((endMin - startMin) / 60) * CELL_H;
                const hasConflict = blockHasConflict(block);
                const pal = getPalette(block.day_of_week);

                return (
                    <DraggableScheduleBlock
                        key={block.id}
                        block={block}
                        globalIndex={globalIndex}
                        style={{ top, height: Math.max(height - 2, 24), left: 2, right: 2 }}
                        hasConflict={hasConflict}
                        palette={pal}
                        onClick={() => onBlockClick(globalIndex)}
                        onHover={(hovering) => onBlockHover(hovering ? globalIndex : null)}
                        onResizeStart={onResizeStart}
                    />
                );
            })}
        </div>
    );
}

function DraggableScheduleBlock({ block, globalIndex, style, hasConflict, palette, onClick, onHover, onResizeStart }: {
    block: { id: string; day_of_week: string; start_time: string; end_time: string; room_id: number | null };
    globalIndex: number;
    style: React.CSSProperties;
    hasConflict: boolean;
    palette: ReturnType<typeof getPalette>;
    onClick: () => void;
    onHover: (hovering: boolean) => void;
    onResizeStart: (blockId: string, edge: "top" | "bottom", initialY: number, originalStartMin: number, originalEndMin: number) => void;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: `block-${block.id}`,
        data: { blockId: block.id },
    });

    const startMin = parseTimeToMinutes(block.start_time);
    const endMin = parseTimeToMinutes(block.end_time);

    const handleResizePointerDown = (e: React.PointerEvent, edge: "top" | "bottom") => {
        e.stopPropagation();
        e.preventDefault();
        if (startMin === null || endMin === null) return;
        onResizeStart(block.id, edge, e.clientY, startMin, endMin);
    };

    return (
        <div
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            onClick={(e) => { e.stopPropagation(); onClick(); }}
            onMouseEnter={() => onHover(true)}
            onMouseLeave={() => onHover(false)}
            className={`absolute overflow-hidden rounded-md border-l-[3px] px-1.5 py-1 text-left cursor-grab active:cursor-grabbing transition-all z-10 ${
                hasConflict
                    ? "border-l-red-500 bg-red-500/12 dark:bg-red-400/15 ring-2 ring-red-400/40"
                    : `${palette.accent} ${palette.bg}`
            } ${isDragging ? "opacity-40" : "hover:shadow-md hover:z-20"}`}
            style={style}
        >
            <div
                className="absolute top-0 left-0 right-0 h-2 cursor-ns-resize z-30 opacity-0 hover:opacity-100 transition-opacity"
                onPointerDown={(e) => handleResizePointerDown(e, "top")}
            >
                <div className="mx-auto w-8 h-1 rounded-full bg-foreground/30 mt-0.5" />
            </div>

            <div className="flex items-center gap-1">
                <GripVertical className="text-muted-foreground h-3 w-3 shrink-0 opacity-60" />
                <div className={`truncate text-[10px] font-bold leading-tight ${hasConflict ? "text-red-700 dark:text-red-300" : palette.text}`}>
                    {fmtTime(block.start_time)}
                </div>
            </div>
            <div className="text-muted-foreground truncate text-[9px] leading-tight">{fmtTime(block.end_time)}</div>

            <div
                className="absolute bottom-0 left-0 right-0 h-2 cursor-ns-resize z-30 opacity-0 hover:opacity-100 transition-opacity"
                onPointerDown={(e) => handleResizePointerDown(e, "bottom")}
            >
                <div className="mx-auto w-8 h-1 rounded-full bg-foreground/30 mb-0.5" />
            </div>
        </div>
    );
}
