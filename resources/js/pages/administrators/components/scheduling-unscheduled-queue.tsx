import * as React from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { CalendarPlus, CheckCircle2, Clock, Inbox, Loader2, Sparkles } from "lucide-react";

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;

type RoomOption = {
    id: number;
    name: string;
    class_code: string | null;
    capacity?: number;
    type?: string;
};

type ScheduleEntry = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    room: string | null;
    room_id?: number | null;
    component_type?: string;
    pattern_group_id?: string | null;
    status?: string;
};

type QueueClassItem = {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    grade_level: string | null;
    faculty_id?: string | null;
    faculty_name: string | null;
    room_id?: number | null;
    room_name: string | null;
    maximum_slots?: number;
    courses: string;
    lecture_hours?: number;
    lab_hours?: number;
    required_hours?: number;
    scheduled_hours?: number;
    is_unscheduled?: boolean;
    is_complete?: boolean;
    schedules: ScheduleEntry[];
};

type PlacePatternPayload = {
    classId: number;
    days: string[];
    start_time: string;
    end_time: string;
    room_id: number | null;
    component_type: "lecture" | "lab";
    status: "draft" | "published";
};

type SchedulingUnscheduledQueueProps = {
    classes: QueueClassItem[];
    allClasses: QueueClassItem[];
    rooms: RoomOption[];
    onPlacePattern: (payload: PlacePatternPayload) => Promise<void>;
    onGenerateCohort?: () => void;
    onSelectClass?: (cls: QueueClassItem) => void;
};

function parseMinutes(timeStr: string): number {
    const clean = timeStr.trim();
    const match12 = clean.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (match12) {
        let h = parseInt(match12[1], 10);
        const m = parseInt(match12[2], 10);
        const ap = match12[3].toUpperCase();
        if (ap === "PM" && h < 12) h += 12;
        if (ap === "AM" && h === 12) h = 0;
        return h * 60 + m;
    }
    const match24 = clean.match(/^(\d{1,2}):(\d{2})/);
    if (match24) {
        return parseInt(match24[1], 10) * 60 + parseInt(match24[2], 10);
    }
    return 480;
}

export default function SchedulingUnscheduledQueue({
    classes,
    allClasses,
    rooms,
    onPlacePattern,
    onGenerateCohort,
    onSelectClass,
}: SchedulingUnscheduledQueueProps) {
    const [filterTab, setFilterTab] = React.useState<"needs_schedule" | "all">("needs_schedule");
    const [targetClass, setTargetClass] = React.useState<QueueClassItem | null>(null);
    const [preset, setPreset] = React.useState<"mwf" | "tth" | "single" | "custom">("mwf");
    const [selectedDays, setSelectedDays] = React.useState<string[]>(["Monday", "Wednesday", "Friday"]);
    const [startTime, setStartTime] = React.useState("08:00");
    const [endTime, setEndTime] = React.useState("09:00");
    const [roomId, setRoomId] = React.useState<string>("none");
    const [componentType, setComponentType] = React.useState<"lecture" | "lab">("lecture");
    const [isSaving, setIsSaving] = React.useState(false);

    const needsScheduleList = React.useMemo(
        () =>
            classes.filter(
                (c) =>
                    c.is_unscheduled ||
                    c.schedules.length === 0 ||
                    (c.scheduled_hours ?? 0) < (c.required_hours ?? 3),
            ),
        [classes],
    );

    const displayedList = filterTab === "needs_schedule" ? needsScheduleList : classes;

    const openQuickPlacer = (cls: QueueClassItem) => {
        setTargetClass(cls);
        setPreset("mwf");
        setSelectedDays(["Monday", "Wednesday", "Friday"]);
        setStartTime("08:00");
        setEndTime("09:00");
        setRoomId(cls.room_id ? String(cls.room_id) : (rooms[0] ? String(rooms[0].id) : "none"));
        setComponentType((cls.lab_hours ?? 0) > 0 && (cls.schedules.length > 0) ? "lab" : "lecture");
    };

    const applyPreset = (nextPreset: "mwf" | "tth" | "single" | "custom") => {
        setPreset(nextPreset);
        if (nextPreset === "mwf") {
            setSelectedDays(["Monday", "Wednesday", "Friday"]);
            setStartTime("08:00");
            setEndTime("09:00");
        } else if (nextPreset === "tth") {
            setSelectedDays(["Tuesday", "Thursday"]);
            setStartTime("08:00");
            setEndTime("09:30");
        } else if (nextPreset === "single") {
            setSelectedDays(["Monday"]);
            setStartTime("08:00");
            setEndTime("11:00");
        }
    };

    const toggleCustomDay = (day: string) => {
        setPreset("custom");
        setSelectedDays((prev) => (prev.includes(day) ? prev.filter((d) => d !== day) : [...prev, day]));
    };

    // Compute room availability for selectedDays + startTime..endTime
    const roomAvailability = React.useMemo(() => {
        const startMin = parseMinutes(startTime);
        const endMin = parseMinutes(endTime);
        const map = new Map<number, { busy: boolean; conflictWith?: string }>();

        for (const room of rooms) {
            let conflictWith: string | undefined;
            for (const cls of allClasses) {
                for (const sched of cls.schedules) {
                    if (sched.room_id !== room.id) continue;
                    if (!selectedDays.includes(sched.day_of_week)) continue;
                    const sMin = parseMinutes(sched.start_time);
                    const eMin = parseMinutes(sched.end_time);
                    if (startMin < eMin && endMin > sMin) {
                        conflictWith = `${cls.subject_code} (${cls.section}) on ${sched.day_of_week.slice(0, 3)}`;
                        break;
                    }
                }
                if (conflictWith) break;
            }
            map.set(room.id, { busy: Boolean(conflictWith), conflictWith });
        }

        return map;
    }, [rooms, allClasses, selectedDays, startTime, endTime]);

    // Check if the class's assigned faculty is busy during selectedDays + startTime..endTime
    const facultyBusyWarning = React.useMemo(() => {
        if (!targetClass?.faculty_id) return null;
        const startMin = parseMinutes(startTime);
        const endMin = parseMinutes(endTime);

        for (const cls of allClasses) {
            if (cls.faculty_id !== targetClass.faculty_id) continue;
            for (const sched of cls.schedules) {
                if (!selectedDays.includes(sched.day_of_week)) continue;
                const sMin = parseMinutes(sched.start_time);
                const eMin = parseMinutes(sched.end_time);
                if (startMin < eMin && endMin > sMin) {
                    return `${targetClass.faculty_name} is already teaching ${cls.subject_code} (${cls.section}) on ${sched.day_of_week} (${sched.time_range}).`;
                }
            }
        }

        return null;
    }, [targetClass, allClasses, selectedDays, startTime, endTime]);

    const handleConfirmPlace = async () => {
        if (!targetClass || selectedDays.length === 0) return;
        setIsSaving(true);
        try {
            await onPlacePattern({
                classId: targetClass.id,
                days: selectedDays,
                start_time: startTime,
                end_time: endTime,
                room_id: roomId !== "none" ? parseInt(roomId, 10) : null,
                component_type: componentType,
                status: "published",
            });
            setTargetClass(null);
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <>
            <div className="w-full shrink-0 lg:w-[270px]">
                <Card className="h-full">
                    <CardHeader className="space-y-2 pb-2.5">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-1.5 text-xs font-semibold">
                                <Inbox className="h-3.5 w-3.5 text-amber-600" />
                                Planning Queue
                            </CardTitle>
                            <Badge variant={needsScheduleList.length > 0 ? "default" : "secondary"} className="text-[10px]">
                                {needsScheduleList.length} Need Hours
                            </Badge>
                        </div>
                        <CardDescription className="text-[11px]">
                            Classes missing schedule blocks or required curriculum hours.
                        </CardDescription>
                        <div className="bg-muted grid grid-cols-2 gap-1 rounded-lg p-0.5">
                            <button
                                type="button"
                                onClick={() => setFilterTab("needs_schedule")}
                                className={`rounded-md py-1 text-[11px] font-medium transition-colors ${
                                    filterTab === "needs_schedule"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                Needs Schedule ({needsScheduleList.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterTab("all")}
                                className={`rounded-md py-1 text-[11px] font-medium transition-colors ${
                                    filterTab === "all"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                All Cohort ({classes.length})
                            </button>
                        </div>
                    </CardHeader>

                    <CardContent className="pt-0">
                        <ScrollArea className="h-[580px]">
                            <div className="space-y-2 pr-2">
                                {displayedList.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-5 text-center">
                                        <CheckCircle2 className="mb-2 h-7 w-7 text-emerald-500" />
                                        <p className="text-xs font-semibold">
                                            {classes.length === 0 ? "No classes in this term/filter" : "All classes scheduled!"}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-[11px]">
                                            {classes.length === 0
                                                ? "Generate cohort offerings from curriculum or create a class to start planning."
                                                : "Every class in the current view meets its required weekly hours."}
                                        </p>
                                        {classes.length === 0 && onGenerateCohort && (
                                            <Button size="sm" variant="secondary" className="mt-3 h-7 text-xs" onClick={onGenerateCohort}>
                                                <Sparkles className="mr-1 h-3 w-3" /> Generate Cohort
                                            </Button>
                                        )}
                                    </div>
                                ) : (
                                    displayedList.map((cls) => {
                                        const reqH = cls.required_hours ?? 3;
                                        const schedH = cls.scheduled_hours ?? 0;
                                        const isComplete = schedH >= reqH;
                                        const isUnscheduled = schedH === 0;

                                        return (
                                            <div
                                                key={cls.id}
                                                className="hover:border-primary/40 space-y-2 rounded-lg border p-2.5 transition-colors"
                                            >
                                                <div className="flex items-start justify-between gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => onSelectClass?.(cls)}
                                                        className="min-w-0 text-left"
                                                    >
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="truncate text-xs font-bold">{cls.subject_code}</span>
                                                            <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
                                                                Sec {cls.section}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-muted-foreground truncate text-[11px]">
                                                            {cls.subject_title}
                                                        </div>
                                                    </button>

                                                    <Badge
                                                        variant="outline"
                                                        className={`shrink-0 text-[10px] ${
                                                            isComplete
                                                                ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                : isUnscheduled
                                                                  ? "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                                  : "border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300"
                                                        }`}
                                                    >
                                                        <Clock className="mr-0.5 h-2.5 w-2.5" />
                                                        {schedH}/{reqH}h
                                                    </Badge>
                                                </div>

                                                <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-1 text-[10px]">
                                                    <span className="truncate">
                                                        {cls.courses || cls.grade_level || "General"} · {cls.faculty_name || "Faculty TBA"}
                                                    </span>
                                                    {(cls.lab_hours ?? 0) > 0 && (
                                                        <span>
                                                            {cls.lecture_hours ?? 0}h Lec + {cls.lab_hours}h Lab
                                                        </span>
                                                    )}
                                                </div>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={isUnscheduled ? "default" : "outline"}
                                                    className="h-7 w-full text-xs"
                                                    onClick={() => openQuickPlacer(cls)}
                                                >
                                                    <CalendarPlus className="mr-1.5 h-3 w-3" />
                                                    {isUnscheduled ? "Place Schedule" : "Add / Adjust Block"}
                                                </Button>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={Boolean(targetClass)} onOpenChange={(open) => !open && setTargetClass(null)}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>
                            Place Schedule — {targetClass?.subject_code} (Section {targetClass?.section})
                        </DialogTitle>
                        <DialogDescription>
                            {targetClass?.subject_title} · Target: {targetClass?.scheduled_hours ?? 0} / {targetClass?.required_hours ?? 3}h weekly
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">1. Meeting Pattern Preset</Label>
                            <div className="grid grid-cols-4 gap-1.5">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={preset === "mwf" ? "default" : "outline"}
                                    className="text-xs"
                                    onClick={() => applyPreset("mwf")}
                                >
                                    MWF (1h × 3)
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={preset === "tth" ? "default" : "outline"}
                                    className="text-xs"
                                    onClick={() => applyPreset("tth")}
                                >
                                    TTh (1.5h × 2)
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={preset === "single" ? "default" : "outline"}
                                    className="text-xs"
                                    onClick={() => applyPreset("single")}
                                >
                                    Single (3h)
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={preset === "custom" ? "default" : "outline"}
                                    className="text-xs"
                                    onClick={() => setPreset("custom")}
                                >
                                    Custom
                                </Button>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">2. Active Days</Label>
                            <div className="flex flex-wrap gap-1.5">
                                {DAYS.map((day) => {
                                    const active = selectedDays.includes(day);
                                    return (
                                        <Button
                                            key={day}
                                            type="button"
                                            size="sm"
                                            variant={active ? "default" : "outline"}
                                            className="h-7 px-2.5 text-xs"
                                            onClick={() => toggleCustomDay(day)}
                                        >
                                            {day.slice(0, 3)}
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs">Start Time</Label>
                                <Input type="time" step={900} value={startTime} onChange={(e) => setStartTime(e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">End Time</Label>
                                <Input type="time" step={900} value={endTime} onChange={(e) => setEndTime(e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Component</Label>
                                <Select value={componentType} onValueChange={(v) => setComponentType(v as "lecture" | "lab")}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="lecture">Lecture</SelectItem>
                                        <SelectItem value="lab">Laboratory</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">3. Room Assignment (Live Availability)</Label>
                            <Select value={roomId} onValueChange={setRoomId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select room..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No Room (TBA)</SelectItem>
                                    {rooms.map((room) => {
                                        const status = roomAvailability.get(room.id);
                                        return (
                                            <SelectItem key={room.id} value={String(room.id)}>
                                                {status?.busy ? "⚠ " : "✓ "}
                                                {room.name}
                                                {room.capacity ? ` (${room.capacity} seats)` : ""}
                                                {status?.busy ? ` — Occupied: ${status.conflictWith}` : " — Free"}
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        </div>

                        {facultyBusyWarning && (
                            <div className="rounded-lg border border-red-300 bg-red-50/70 p-2.5 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                                <strong>Faculty Conflict Warning:</strong> {facultyBusyWarning}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setTargetClass(null)} disabled={isSaving}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={handleConfirmPlace}
                            disabled={isSaving || selectedDays.length === 0}
                        >
                            {isSaving ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <CalendarPlus className="mr-1.5 h-3.5 w-3.5" />}
                            Place {selectedDays.length} Block{selectedDays.length === 1 ? "" : "s"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
