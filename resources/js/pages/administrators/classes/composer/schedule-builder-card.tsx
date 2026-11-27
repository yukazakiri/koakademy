import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock,
    FlaskConical,
    Monitor,
    Pencil,
    Plus,
    Presentation,
    Radio,
    School,
    Trash2,
    UserCheck,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import type { CrudFacultyOption, CrudRoomOption, PreflightConflict, PreflightRoomCheck } from "../crud/types";
import {
    canonicalDeliveryMode,
    deliveryModeShortLabel,
    isOnlineDeliveryMode,
    type ClassSchedule,
    type DeliveryMode,
} from "../lib/class-defaults";

const BASE_DAYS = [
    { value: "Monday", short: "Mon" },
    { value: "Tuesday", short: "Tue" },
    { value: "Wednesday", short: "Wed" },
    { value: "Thursday", short: "Thu" },
    { value: "Friday", short: "Fri" },
    { value: "Saturday", short: "Sat" },
] as const;

const SUNDAY_DAY = { value: "Sunday", short: "Sun" } as const;

const DELIVERY_CHOICES: Array<{ value: DeliveryMode; label: string; short: string; icon: ReactNode }> = [
    { value: "face_to_face", label: "Face to face", short: "Face to face", icon: <School className="size-3.5" aria-hidden="true" /> },
    { value: "online_synchronous", label: "Online (Sync)", short: "Online (Sync)", icon: <Radio className="size-3.5" aria-hidden="true" /> },
    { value: "online", label: "Online (Async)", short: "Online (Async)", icon: <Monitor className="size-3.5" aria-hidden="true" /> },
];

const QUICK_DAY_PRESETS = [
    { label: "MWF", days: ["Monday", "Wednesday", "Friday"] },
    { label: "TTh", days: ["Tuesday", "Thursday"] },
];

const nativeSelectClass =
    "h-9 w-full min-w-0 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50";

function parseMinutes(time?: string | null): number | null {
    if (!time) return null;
    const [hStr, mStr] = time.trim().split(":");
    const h = Number(hStr);
    const m = Number(mStr ?? 0);
    if (!Number.isFinite(h) || !Number.isFinite(m)) return null;
    return h * 60 + m;
}

function durationMinutes(start: string, end: string): number {
    const s = parseMinutes(start);
    const e = parseMinutes(end);
    if (s === null || e === null || e <= s) return 0;
    return e - s;
}

function formatDuration(start: string, end: string): string {
    const diff = durationMinutes(start, end);
    if (diff <= 0) return "";
    const hrs = Math.floor(diff / 60);
    const mins = diff % 60;
    if (hrs > 0 && mins > 0) return `${hrs}h ${mins}m`;
    if (hrs > 0) return `${hrs}h`;
    return `${mins}m`;
}

function formatHours(hours: number): string {
    return `${Number(hours.toFixed(1))}h`;
}

function formatRoomOptionLabel(room: CrudRoomOption): string {
    const parts = [room.label];
    if (typeof room.capacity === "number" && room.capacity > 0) {
        parts.push(`${room.capacity} seats`);
    }
    if (room.type) {
        const prettyType = room.type.charAt(0).toUpperCase() + room.type.slice(1);
        parts.push(prettyType);
    }
    return parts.join(" · ");
}

function formatFacultyOptionLabel(faculty: CrudFacultyOption): string {
    if (typeof faculty.load_hours !== "number") {
        return faculty.label;
    }
    const current = Number(faculty.load_hours.toFixed(1));
    if (typeof faculty.max_load_hours === "number" && faculty.max_load_hours > 0) {
        return `${faculty.label} · ${current}h/${faculty.max_load_hours}h`;
    }
    return `${faculty.label} · ${current}h`;
}

export type ScheduleBuilderCardProps = {
    facultyId: string | null;
    deliveryMode: DeliveryMode;
    roomId: number | null;
    maximumSlots: number;
    schedules: ClassSchedule[];
    faculties: CrudFacultyOption[];
    rooms: CrudRoomOption[];
    allowSunday: boolean;
    conflicts: PreflightConflict[];
    roomChecks: PreflightRoomCheck[];
    hours: { scheduled: number; required: number | null; lecture: number; lab: number } | null;
    requiredFallbackHours: { lecture: number; lab: number; total: number } | null;
    editingIndex: number | null;
    onEditingIndexChange: (index: number | null) => void;
    onFacultyChange: (facultyId: string | null) => void;
    onDeliveryModeChange: (mode: DeliveryMode) => void;
    onRoomChange: (roomId: number | null) => void;
    onSchedulesChange: (schedules: ClassSchedule[]) => void;
    errors?: Record<string, string>;
};

export function ScheduleBuilderCard({
    facultyId,
    deliveryMode,
    roomId,
    maximumSlots,
    schedules,
    faculties,
    rooms,
    allowSunday,
    conflicts,
    roomChecks,
    hours,
    requiredFallbackHours,
    editingIndex,
    onEditingIndexChange,
    onFacultyChange,
    onDeliveryModeChange,
    onRoomChange,
    onSchedulesChange,
    errors = {},
}: ScheduleBuilderCardProps): ReactNode {
    const builderRef = useRef<HTMLDivElement>(null);
    const [selectedDays, setSelectedDays] = useState<string[]>(["Monday", "Wednesday", "Friday"]);
    const [startTime, setStartTime] = useState("08:00");
    const [endTime, setEndTime] = useState("09:00");
    const [componentType, setComponentType] = useState<"lecture" | "lab">("lecture");
    const [blockDeliveryMode, setBlockDeliveryMode] = useState<DeliveryMode>(deliveryMode);
    const [blockRoomId, setBlockRoomId] = useState<number | null>(roomId);
    const [builderError, setBuilderError] = useState<string | null>(null);

    const roomsById = useMemo(() => {
        const map = new Map<number, CrudRoomOption>();
        for (const room of rooms) {
            const num = Number(room.id);
            if (Number.isFinite(num)) {
                map.set(num, room);
            }
        }
        return map;
    }, [rooms]);

    const selectedFaculty = useMemo(
        () => faculties.find((f) => String(f.id) === String(facultyId ?? "")) ?? null,
        [faculties, facultyId],
    );

    const facultyOptions = useMemo<ComboboxOption[]>(
        () =>
            faculties.map((f) => ({
                value: String(f.id),
                label: formatFacultyOptionLabel(f),
                searchText: f.label,
            })),
        [faculties],
    );

    const roomComboboxOptions = useMemo<ComboboxOption[]>(
        () => [
            { value: "0", label: "Assign room later", searchText: "unassigned later none" },
            ...rooms.map((r) => ({
                value: String(r.id),
                label: formatRoomOptionLabel(r),
                searchText: `${r.label} ${r.type ?? ""} ${r.capacity ?? ""}`,
            })),
        ],
        [rooms],
    );

    const availableDays = useMemo(() => {
        const editingSunday =
            editingIndex !== null && schedules[editingIndex]?.day_of_week?.toLowerCase() === "sunday";
        return allowSunday || editingSunday ? [...BASE_DAYS, SUNDAY_DAY] : [...BASE_DAYS];
    }, [allowSunday, editingIndex, schedules]);

    // Sync builder defaults when default delivery or room changes (only when not actively editing a block)
    useEffect(() => {
        if (editingIndex !== null) return;
        setBlockDeliveryMode(deliveryMode);
    }, [deliveryMode, editingIndex]);

    useEffect(() => {
        if (editingIndex !== null) return;
        setBlockRoomId(isOnlineDeliveryMode(blockDeliveryMode) ? null : roomId);
    }, [roomId, blockDeliveryMode, editingIndex]);

    // Load block into builder when editingIndex changes
    useEffect(() => {
        if (editingIndex === null) return;
        const target = schedules[editingIndex];
        if (!target) {
            onEditingIndexChange(null);
            return;
        }
        const targetMode = canonicalDeliveryMode(target.delivery_mode ?? deliveryMode);
        setSelectedDays([target.day_of_week]);
        setStartTime(target.start_time.slice(0, 5));
        setEndTime(target.end_time.slice(0, 5));
        setComponentType(target.component_type === "lab" ? "lab" : "lecture");
        setBlockDeliveryMode(targetMode);
        setBlockRoomId(isOnlineDeliveryMode(targetMode) ? null : (target.room_id ?? null));
        setBuilderError(null);
        builderRef.current?.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }, [editingIndex, schedules, deliveryMode, onEditingIndexChange]);

    const resetBuilder = (): void => {
        setSelectedDays(["Monday"]);
        setStartTime("08:00");
        setEndTime("09:00");
        setComponentType("lecture");
        setBlockDeliveryMode(deliveryMode);
        setBlockRoomId(isOnlineDeliveryMode(deliveryMode) ? null : roomId);
        setBuilderError(null);
        onEditingIndexChange(null);
    };

    const toggleDay = (dayValue: string): void => {
        setBuilderError(null);
        if (editingIndex !== null) {
            setSelectedDays([dayValue]);
            return;
        }
        setSelectedDays((prev) => (prev.includes(dayValue) ? prev.filter((d) => d !== dayValue) : [...prev, dayValue]));
    };

    const handleCommitBlock = (): void => {
        if (selectedDays.length === 0) {
            setBuilderError("Select at least one day of the week.");
            return;
        }
        if (!startTime || !endTime || endTime <= startTime) {
            setBuilderError("End time must be after start time.");
            return;
        }

        const effectiveMode = canonicalDeliveryMode(blockDeliveryMode);
        const effectiveRoomId = isOnlineDeliveryMode(effectiveMode) ? null : blockRoomId || null;
        const effectiveRoomName = effectiveRoomId ? (roomsById.get(effectiveRoomId)?.label ?? null) : null;

        if (editingIndex !== null && schedules[editingIndex]) {
            const existing = schedules[editingIndex];
            const updated: ClassSchedule = {
                ...existing,
                day_of_week: selectedDays[0],
                start_time: startTime,
                end_time: endTime,
                component_type: componentType,
                delivery_mode: effectiveMode,
                room_id: effectiveRoomId,
                room_name: effectiveRoomName,
            };
            onSchedulesChange(schedules.map((item, idx) => (idx === editingIndex ? updated : item)));
            resetBuilder();
            return;
        }

        const dayOrder = availableDays.map((d) => d.value);
        const orderedDays = dayOrder.filter((d) => selectedDays.includes(d));
        const patternGroupId = orderedDays.length > 1 && typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : null;

        const additions: ClassSchedule[] = orderedDays.map((day) => ({
            day_of_week: day,
            start_time: startTime,
            end_time: endTime,
            component_type: componentType,
            delivery_mode: effectiveMode,
            room_id: effectiveRoomId,
            room_name: effectiveRoomName,
            pattern_group_id: patternGroupId,
            status: "draft",
        }));

        onSchedulesChange([...schedules, ...additions]);
        resetBuilder();
    };

    const handleRemoveBlock = (index: number): void => {
        if (editingIndex === index) {
            resetBuilder();
        } else if (editingIndex !== null && editingIndex > index) {
            onEditingIndexChange(editingIndex - 1);
        }
        onSchedulesChange(schedules.filter((_, idx) => idx !== index));
    };

    // Computed local hours fallback so the progress bar works immediately before preflight responds
    const computedHours = useMemo(() => {
        let lectureMins = 0;
        let labMins = 0;
        for (const block of schedules) {
            const mins = durationMinutes(block.start_time, block.end_time);
            if (block.component_type === "lab") {
                labMins += mins;
            } else {
                lectureMins += mins;
            }
        }
        return {
            lecture: lectureMins / 60,
            lab: labMins / 60,
            scheduled: (lectureMins + labMins) / 60,
        };
    }, [schedules]);

    const effectiveHours = {
        scheduled: hours ? hours.scheduled : computedHours.scheduled,
        required: hours?.required ?? (requiredFallbackHours && requiredFallbackHours.total > 0 ? requiredFallbackHours.total : null),
        lecture: hours ? hours.lecture : computedHours.lecture,
        lab: hours ? hours.lab : computedHours.lab,
    };

    const hoursPct =
        effectiveHours.required && effectiveHours.required > 0
            ? Math.min(100, Math.round((effectiveHours.scheduled / effectiveHours.required) * 100))
            : 0;
    const hoursMet = effectiveHours.required !== null && effectiveHours.scheduled >= effectiveHours.required;
    const hoursOver = effectiveHours.required !== null && effectiveHours.scheduled > effectiveHours.required + 0.01;

    const blockingConflictsCount = conflicts.filter((c) => c.severity === "blocking").length;

    const builderRoom = blockRoomId ? roomsById.get(blockRoomId) : undefined;
    const builderRoomTooSmall =
        !isOnlineDeliveryMode(blockDeliveryMode) &&
        builderRoom &&
        typeof builderRoom.capacity === "number" &&
        builderRoom.capacity > 0 &&
        builderRoom.capacity < maximumSlots;

    const scheduleError =
        errors.schedules ??
        Object.entries(errors).find(([key]) => key.startsWith("schedules."))?.[1] ??
        null;

    return (
        <Card id="composer-schedule" className="scroll-mt-24 rounded-2xl shadow-2xs">
            <CardHeader className="border-b pb-3.5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2.5">
                        <span className="bg-muted text-muted-foreground flex size-6 shrink-0 items-center justify-center rounded-md border text-xs font-bold">
                            2
                        </span>
                        <CardTitle className="text-sm font-bold tracking-tight">Teaching Assignment &amp; Lec/Lab Schedule</CardTitle>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge
                            variant="outline"
                            className={cn(
                                "font-mono text-[11px] font-semibold tabular-nums",
                                hoursMet && !hoursOver && "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                                hoursOver && "border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300",
                            )}
                        >
                            {effectiveHours.required !== null
                                ? `${formatHours(effectiveHours.scheduled)} / ${formatHours(effectiveHours.required)}`
                                : `${formatHours(effectiveHours.scheduled)} scheduled`}
                        </Badge>
                        {blockingConflictsCount > 0 ? (
                            <Badge variant="destructive" className="gap-1 text-[11px] font-semibold">
                                <AlertTriangle className="size-3" aria-hidden="true" />
                                {blockingConflictsCount} {blockingConflictsCount === 1 ? "conflict" : "conflicts"}
                            </Badge>
                        ) : null}
                    </div>
                </div>
            </CardHeader>

            <CardContent className="space-y-5 pt-4">
                {/* Instructor, Delivery Mode, Default Room */}
                <div className="grid gap-4 md:grid-cols-2">
                    {/* Lead Instructor */}
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label className="text-foreground flex items-center gap-1.5 text-xs font-semibold">
                                <UserCheck className="text-primary size-3.5" aria-hidden="true" />
                                Lead instructor
                            </Label>
                            {selectedFaculty && typeof selectedFaculty.load_hours === "number" ? (
                                <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                                    {Number(selectedFaculty.load_hours.toFixed(1))}h
                                    {selectedFaculty.max_load_hours ? `/${selectedFaculty.max_load_hours}h` : ""} load
                                </span>
                            ) : (
                                <span className="text-muted-foreground text-[11px]">Optional</span>
                            )}
                        </div>
                        <div className="flex items-center gap-1.5">
                            <Combobox
                                className="min-w-0 flex-1 space-y-0"
                                options={facultyOptions}
                                value={facultyId ?? undefined}
                                onValueChange={(val) => onFacultyChange(val || null)}
                                placeholder="Assign instructor…"
                                searchPlaceholder="Search faculty by name…"
                                emptyText="No faculty member found."
                            />
                            {facultyId ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-destructive size-9 shrink-0"
                                    aria-label="Clear instructor"
                                    onClick={() => onFacultyChange(null)}
                                >
                                    <X className="size-4" />
                                </Button>
                            ) : null}
                        </div>
                        {errors.faculty_id ? <p className="text-destructive text-xs">{errors.faculty_id}</p> : null}
                    </div>

                    {/* Default Room */}
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label className="text-foreground flex items-center gap-1.5 text-xs font-semibold">
                                <Building2 className="text-primary size-3.5" aria-hidden="true" />
                                Default room
                            </Label>
                            <span className="text-muted-foreground text-[11px]">
                                {isOnlineDeliveryMode(deliveryMode) ? "Not needed online" : "Used for new blocks"}
                            </span>
                        </div>
                        {isOnlineDeliveryMode(deliveryMode) ? (
                            <div className="bg-muted/30 text-muted-foreground flex h-11 items-center gap-2 rounded-lg border border-dashed px-3 text-xs">
                                <Monitor className="size-3.5 shrink-0 text-indigo-500" aria-hidden="true" />
                                <span>Virtual delivery — no default room reserved.</span>
                            </div>
                        ) : (
                            <Combobox
                                className="space-y-0"
                                options={roomComboboxOptions}
                                value={String(roomId ?? 0)}
                                onValueChange={(val) => onRoomChange(Number(val) || null)}
                                placeholder="Choose default room…"
                                searchPlaceholder="Search rooms or labs…"
                                emptyText="No room found."
                            />
                        )}
                        {errors.room_id ? <p className="text-destructive text-xs">{errors.room_id}</p> : null}
                    </div>
                </div>

                {/* Default Delivery Mode Segmented Control */}
                <div className="space-y-1.5">
                    <Label className="text-foreground text-xs font-semibold">Default delivery mode</Label>
                    <div
                        className="bg-muted/50 grid grid-cols-3 gap-1 rounded-xl border p-1"
                        role="radiogroup"
                        aria-label="Default delivery mode"
                    >
                        {DELIVERY_CHOICES.map((choice) => {
                            const active = deliveryMode === choice.value;
                            return (
                                <button
                                    key={choice.value}
                                    type="button"
                                    role="radio"
                                    aria-checked={active}
                                    onClick={() => onDeliveryModeChange(choice.value)}
                                    className={cn(
                                        "flex h-9 items-center justify-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold transition-all",
                                        active
                                            ? "bg-background text-foreground ring-border/70 shadow-2xs ring-1"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    {choice.icon}
                                    <span className="truncate">{choice.short}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Contact Hours Bar */}
                <div className="bg-muted/25 space-y-2 rounded-xl border px-3.5 py-2.5">
                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span className="text-foreground font-medium">
                            Scheduled <strong className="font-mono tabular-nums">{formatHours(effectiveHours.scheduled)}</strong>
                            {effectiveHours.required !== null ? (
                                <>
                                    {" "}
                                    / Required <strong className="font-mono tabular-nums">{formatHours(effectiveHours.required)}</strong>
                                </>
                            ) : null}
                        </span>
                        <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                            {formatHours(effectiveHours.lecture)} Lec · {formatHours(effectiveHours.lab)} Lab
                        </span>
                    </div>
                    {effectiveHours.required !== null ? (
                        <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                            <div
                                className={cn(
                                    "h-full rounded-full transition-all duration-300",
                                    hoursOver ? "bg-amber-500" : hoursMet ? "bg-emerald-600" : "bg-primary",
                                )}
                                style={{ width: `${hoursPct}%` }}
                            />
                        </div>
                    ) : null}
                </div>

                {/* Schedule Block Composer */}
                <div
                    ref={builderRef}
                    className={cn(
                        "space-y-3.5 rounded-xl border p-4 transition-colors",
                        editingIndex !== null
                            ? "border-primary/50 bg-primary/[0.03] ring-primary/15 ring-1"
                            : "bg-muted/20",
                    )}
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <Clock className="text-primary size-4" aria-hidden="true" />
                            <span className="text-foreground text-xs font-semibold">
                                {editingIndex !== null
                                    ? `Editing block #${editingIndex + 1}`
                                    : "Add weekly schedule block"}
                            </span>
                        </div>

                        <div className="flex items-center gap-1.5">
                            {editingIndex === null ? (
                                QUICK_DAY_PRESETS.map((preset) => (
                                    <Button
                                        key={preset.label}
                                        type="button"
                                        variant="outline"
                                        size="xs"
                                        className="h-6 rounded-md px-2 text-[11px]"
                                        onClick={() => setSelectedDays(preset.days)}
                                    >
                                        {preset.label}
                                    </Button>
                                ))
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    className="h-6 text-[11px]"
                                    onClick={resetBuilder}
                                >
                                    Cancel edit
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Row 1: Component toggle + Day chips */}
                    <div className="flex flex-wrap items-center gap-3">
                        <div
                            className="bg-muted inline-flex rounded-lg border p-0.5"
                            role="group"
                            aria-label="Session component type"
                        >
                            <button
                                type="button"
                                aria-pressed={componentType === "lecture"}
                                onClick={() => setComponentType("lecture")}
                                className={cn(
                                    "inline-flex h-7 items-center gap-1 rounded-md px-2.5 text-xs font-semibold transition-colors",
                                    componentType === "lecture"
                                        ? "bg-primary text-primary-foreground shadow-2xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <Presentation className="size-3" aria-hidden="true" />
                                Lecture
                            </button>
                            <button
                                type="button"
                                aria-pressed={componentType === "lab"}
                                onClick={() => setComponentType("lab")}
                                className={cn(
                                    "inline-flex h-7 items-center gap-1 rounded-md px-2.5 text-xs font-semibold transition-colors",
                                    componentType === "lab"
                                        ? "bg-emerald-600 text-white shadow-2xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <FlaskConical className="size-3" aria-hidden="true" />
                                Lab
                            </button>
                        </div>

                        <div className="flex flex-wrap items-center gap-1" role="group" aria-label="Days of week">
                            {availableDays.map((day) => {
                                const active = selectedDays.includes(day.value);
                                return (
                                    <button
                                        key={day.value}
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() => toggleDay(day.value)}
                                        className={cn(
                                            "h-8 min-w-10 rounded-lg border px-2 text-xs font-semibold transition-colors",
                                            active
                                                ? componentType === "lab"
                                                    ? "border-emerald-600 bg-emerald-600 text-white"
                                                    : "border-primary bg-primary text-primary-foreground"
                                                : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
                                        )}
                                    >
                                        {day.short}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Row 2: Start, End, Delivery override, Room, Commit CTA */}
                    <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-12">
                        <div className="space-y-1 lg:col-span-2">
                            <Label htmlFor="composer-block-start" className="text-muted-foreground text-[11px] font-medium">
                                Start
                            </Label>
                            <Input
                                id="composer-block-start"
                                type="time"
                                value={startTime}
                                onChange={(e) => {
                                    setStartTime(e.target.value);
                                    setBuilderError(null);
                                }}
                                className="h-9 rounded-lg font-mono text-xs"
                            />
                        </div>

                        <div className="space-y-1 lg:col-span-2">
                            <Label htmlFor="composer-block-end" className="text-muted-foreground text-[11px] font-medium">
                                End {formatDuration(startTime, endTime) ? `(${formatDuration(startTime, endTime)})` : ""}
                            </Label>
                            <Input
                                id="composer-block-end"
                                type="time"
                                value={endTime}
                                onChange={(e) => {
                                    setEndTime(e.target.value);
                                    setBuilderError(null);
                                }}
                                className="h-9 rounded-lg font-mono text-xs"
                            />
                        </div>

                        <div className="space-y-1 lg:col-span-3">
                            <Label htmlFor="composer-block-delivery" className="text-muted-foreground text-[11px] font-medium">
                                Session delivery
                            </Label>
                            <select
                                id="composer-block-delivery"
                                className={nativeSelectClass}
                                value={blockDeliveryMode}
                                onChange={(e) => {
                                    const nextMode = e.target.value as DeliveryMode;
                                    setBlockDeliveryMode(nextMode);
                                    if (isOnlineDeliveryMode(nextMode)) {
                                        setBlockRoomId(null);
                                    } else if (blockRoomId === null && roomId !== null) {
                                        setBlockRoomId(roomId);
                                    }
                                }}
                            >
                                <option value="face_to_face">Face to face</option>
                                <option value="online_synchronous">Online (Sync)</option>
                                <option value="online">Online (Async)</option>
                            </select>
                        </div>

                        <div className="space-y-1 lg:col-span-3">
                            <Label htmlFor="composer-block-room" className="text-muted-foreground text-[11px] font-medium">
                                Room
                            </Label>
                            {isOnlineDeliveryMode(blockDeliveryMode) ? (
                                <div className="bg-muted/40 text-muted-foreground flex h-9 items-center rounded-lg border border-dashed px-2.5 text-[11px]">
                                    No physical room
                                </div>
                            ) : (
                                <select
                                    id="composer-block-room"
                                    className={cn(nativeSelectClass, builderRoomTooSmall && "border-destructive text-destructive")}
                                    value={blockRoomId ?? 0}
                                    onChange={(e) => setBlockRoomId(Number(e.target.value) || null)}
                                >
                                    <option value={0}>Assign room later</option>
                                    {blockRoomId && !roomsById.has(blockRoomId) ? (
                                        <option value={blockRoomId}>Room #{blockRoomId} (inactive)</option>
                                    ) : null}
                                    {rooms.map((room) => (
                                        <option key={room.id} value={room.id}>
                                            {formatRoomOptionLabel(room)}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        <div className="flex items-end lg:col-span-2">
                            <Button
                                type="button"
                                variant={editingIndex !== null ? "default" : "secondary"}
                                className="h-9 w-full gap-1.5 rounded-lg text-xs font-semibold"
                                onClick={handleCommitBlock}
                            >
                                {editingIndex !== null ? (
                                    <>
                                        <CheckCircle2 className="size-3.5" aria-hidden="true" />
                                        Update Block
                                    </>
                                ) : (
                                    <>
                                        <Plus className="size-3.5" aria-hidden="true" />
                                        Add Block
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>

                    {builderRoomTooSmall && builderRoom ? (
                        <p className="text-destructive flex items-center gap-1.5 text-xs font-medium">
                            <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
                            <span>
                                {builderRoom.label} holds {builderRoom.capacity} seats, below class capacity ({maximumSlots}).
                            </span>
                        </p>
                    ) : null}

                    {builderError ? (
                        <p role="alert" className="text-destructive text-xs font-medium">
                            {builderError}
                        </p>
                    ) : null}
                </div>

                {scheduleError ? (
                    <p role="alert" className="text-destructive text-xs font-medium">
                        {scheduleError}
                    </p>
                ) : null}

                {/* Added Schedule Blocks List */}
                {schedules.length === 0 ? (
                    <div className="text-muted-foreground flex items-center gap-3 rounded-xl border border-dashed px-4 py-3.5">
                        <CalendarDays className="size-4 shrink-0" aria-hidden="true" />
                        <p className="text-xs">
                            No meeting blocks added yet. Add lecture or lab sessions above, or save this offering as an unscheduled draft.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-foreground text-xs font-semibold">
                                Scheduled blocks ({schedules.length})
                            </span>
                            <span className="text-muted-foreground text-[11px]">Click edit or any block on the weekly grid to adjust</span>
                        </div>

                        <div className="divide-y overflow-hidden rounded-xl border">
                            {schedules.map((block, index) => {
                                const mode = canonicalDeliveryMode(block.delivery_mode ?? deliveryMode);
                                const isOnline = isOnlineDeliveryMode(mode);
                                const room = block.room_id ? roomsById.get(Number(block.room_id)) : undefined;
                                const roomLabel = room?.label ?? block.room_name ?? (block.room_id ? `Room #${block.room_id}` : "Unassigned room");
                                const roomCheck = roomChecks.find((rc) => rc.schedule_index === index);
                                const roomCapacity = roomCheck?.capacity ?? room?.capacity ?? null;
                                const capacityWarning =
                                    !isOnline &&
                                    ((roomCheck && roomCheck.fits === false) ||
                                        (typeof roomCapacity === "number" && roomCapacity > 0 && roomCapacity < maximumSlots));
                                const blockConflicts = conflicts.filter((c) => c.schedule_index === index);
                                const hasBlocking = blockConflicts.some((c) => c.severity === "blocking");
                                const isLab = block.component_type === "lab";
                                const isEditingThis = editingIndex === index;
                                const statusLabel = block.status === "published" ? "Published" : "Draft";

                                return (
                                    <div
                                        key={block.id ?? `block-${index}-${block.day_of_week}-${block.start_time}`}
                                        className={cn(
                                            "flex flex-col gap-2 px-3.5 py-2.5 transition-colors sm:flex-row sm:items-center sm:justify-between",
                                            isEditingThis && "bg-primary/[0.06]",
                                            !isEditingThis && hasBlocking && "bg-destructive/[0.04]",
                                        )}
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        "px-1.5 py-0 text-[10px] font-bold uppercase",
                                                        isLab
                                                            ? "border-emerald-600/40 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300"
                                                            : "border-primary/40 bg-primary/10 text-primary",
                                                    )}
                                                >
                                                    {isLab ? "Lab" : "Lec"}
                                                </Badge>

                                                <span className="text-foreground text-xs font-semibold">
                                                    {block.day_of_week.slice(0, 3)}
                                                </span>
                                                <span className="text-foreground font-mono text-xs tabular-nums">
                                                    {block.start_time.slice(0, 5)}–{block.end_time.slice(0, 5)}
                                                </span>

                                                <span className="text-muted-foreground text-xs">·</span>

                                                {isOnline ? (
                                                    <Badge variant="secondary" className="text-[11px] font-medium">
                                                        {deliveryModeShortLabel(mode)}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">
                                                        <strong className="text-foreground font-medium">{roomLabel}</strong>
                                                        {typeof roomCapacity === "number" && roomCapacity > 0 ? ` (${roomCapacity} seats)` : ""}
                                                    </span>
                                                )}

                                                {capacityWarning && typeof roomCapacity === "number" ? (
                                                    <Badge variant="destructive" className="gap-1 px-1.5 py-0 text-[10px] font-semibold">
                                                        <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                        Fits {roomCapacity} &lt; {maximumSlots}
                                                    </Badge>
                                                ) : null}

                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        "px-1.5 py-0 text-[10px] font-medium",
                                                        statusLabel === "Published"
                                                            ? "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                            : "text-muted-foreground",
                                                    )}
                                                >
                                                    {statusLabel}
                                                </Badge>
                                            </div>

                                            {blockConflicts.length > 0 ? (
                                                <div className="space-y-0.5">
                                                    {blockConflicts.map((conflict, cIdx) => (
                                                        <p
                                                            key={`${conflict.kind}-${cIdx}`}
                                                            className={cn(
                                                                "flex items-center gap-1.5 text-[11px] font-medium",
                                                                conflict.severity === "blocking"
                                                                    ? "text-destructive"
                                                                    : "text-amber-700 dark:text-amber-400",
                                                            )}
                                                        >
                                                            <AlertTriangle className="size-3 shrink-0" aria-hidden="true" />
                                                            <span>{conflict.message}</span>
                                                        </p>
                                                    ))}
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1 self-end sm:self-center">
                                            <Button
                                                type="button"
                                                variant={isEditingThis ? "secondary" : "ghost"}
                                                size="icon-sm"
                                                aria-label={`Edit ${block.day_of_week} ${block.start_time} block`}
                                                onClick={() => onEditingIndexChange(isEditingThis ? null : index)}
                                            >
                                                <Pencil className="size-3.5" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                className="text-muted-foreground hover:text-destructive"
                                                aria-label={`Remove ${block.day_of_week} ${block.start_time} block`}
                                                onClick={() => handleRemoveBlock(index)}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
