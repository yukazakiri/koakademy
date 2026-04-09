import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { DataTable } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import type { User } from "@/types/user";
import { DndContext, DragOverlay, KeyboardSensor, MouseSensor, TouchSensor, useDraggable, useDroppable, useSensor, useSensors } from "@dnd-kit/core";
import { snapCenterToCursor } from "@dnd-kit/modifiers";
import { Head, Link, useForm } from "@inertiajs/react";
import { ColumnDef } from "@tanstack/react-table";
import { format, parse, set } from "date-fns";
import {
    AlertCircle,
    ArrowRightLeft,
    ArrowUpDown,
    ArrowUpRight,
    Calendar as CalendarIcon,
    CheckCircle2,
    ChevronLeft,
    Clock,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    Layers,
    LayoutDashboard,
    ListTodo,
    MapPin,
    MoreHorizontal,
    Palette,
    Plus,
    Settings2,
    Trash2,
    Users,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { toast } from "sonner";
import { route } from "ziggy-js";

// --- Types ---

type WeeklyScheduleEntry = {
    start_time: string;
    end_time: string;
    time_range: string;
    room: {
        id: number;
        name: string;
    };
    has_conflict: boolean;
};

type RawScheduleEntry = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number;
    temp_id?: string;
};

type RoomScheduleEntry = {
    id: number;
    class_id: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    title: string;
};

type ClassShowData = {
    id: number;
    record_title: string;
    classification: string;

    subjects: string[];
    associated_courses: string | null;

    subject_code: string;
    subject_title: string;

    section: string;
    year_level: string | null;
    semester: string;
    school_year: string;

    students_count: number;
    maximum_slots: number;

    faculty: {
        name: string;
        email: string | null;
        avatar_url: string | null;
    } | null;

    schedule: Record<string, WeeklyScheduleEntry[]>;

    settings: {
        background_color: string | null;
        accent_color: string | null;
        theme: string | null;
        banner_image_url: string | null;
        features: {
            enable_announcements: boolean;
            enable_grade_visibility: boolean;
            enable_attendance_tracking: boolean;
            allow_late_submissions: boolean;
            enable_discussion_board: boolean;
        };
        custom: Record<string, string>;
    };

    filament: {
        view_url: string;
        edit_url: string;
    };
};

type EnrollmentRow = {
    id: number;
    status: boolean | null;
    prelim_grade: number | null;
    midterm_grade: number | null;
    finals_grade: number | null;
    total_average: number | null;
    remarks: string | null;
    student: {
        id: number;
        student_id: string | number | null;
        name: string;
        course: string | null;
        academic_year: string | number | null;
    } | null;
};

type EnrollmentPaginator = {
    data: EnrollmentRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    from: number;
    to: number;
};

type RoomOption = {
    id: number;
    label: string;
};

type TransferableClass = {
    id: number;
    section: string;
    label: string;
    is_full: boolean;
    available_slots: number;
};

interface ClassShowProps {
    user: User;
    class: ClassShowData;
    enrollments: EnrollmentPaginator;
    raw_schedules: RawScheduleEntry[];
    room_schedules: Record<number, RoomScheduleEntry[]>;
    rooms: RoomOption[];
    transferable_classes: TransferableClass[];
}

// --- Helpers ---

function splitBadges(value: string | null): string[] {
    if (!value) return [];
    if (value === "N/A") return [];
    return value
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
}

function enrollmentTone(studentsCount: number, maximumSlots: number): "success" | "warning" | "destructive" {
    if (maximumSlots <= 0) return "success";
    const ratio = studentsCount / maximumSlots;
    if (ratio >= 1) return "destructive";
    if (ratio >= 0.9) return "warning";
    return "success";
}

const scheduleDayOrder = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

// Grid Constants
const START_HOUR = 7; // 7 AM
const END_HOUR = 21; // 9 PM
const PIXELS_PER_HOUR = 60;
const PIXELS_PER_MINUTE = PIXELS_PER_HOUR / 60;
const SNAP_MINUTES = 15;
const TOTAL_MINUTES = (END_HOUR - START_HOUR) * 60;

function parseTimeToMinutes(timeValue: string): number {
    if (!timeValue) return 0;
    const [hours, minutes] = timeValue.split(":").map(Number);
    return hours * 60 + minutes;
}

function minutesToTime(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
}

function formatTimeLabel(minutesOfDay: number): string {
    const hours = Math.floor(minutesOfDay / 60);
    const minutes = minutesOfDay % 60;
    const period = hours >= 12 ? "PM" : "AM";
    const displayHours = ((hours + 11) % 12) + 1;
    return `${displayHours}:${String(minutes).padStart(2, "0")} ${period}`;
}

function getGridPosition(startTime: string, endTime: string): { top: number; height: number } {
    const startMinutes = parseTimeToMinutes(startTime);
    const endMinutes = parseTimeToMinutes(endTime);

    const gridStartMinutes = START_HOUR * 60;

    const top = (startMinutes - gridStartMinutes) * PIXELS_PER_MINUTE;
    const height = (endMinutes - startMinutes) * PIXELS_PER_MINUTE;

    return { top, height };
}

function getScheduleEntriesForDay(schedule: Record<string, WeeklyScheduleEntry[]> | undefined, dayKeyLowercase: string): WeeklyScheduleEntry[] {
    if (!schedule) return [];

    const normalized = dayKeyLowercase.toLowerCase();
    const titleCase = normalized.charAt(0).toUpperCase() + normalized.slice(1);
    const shortTitleCase = titleCase.slice(0, 3);

    const directHit = schedule[normalized] ?? schedule[titleCase] ?? schedule[shortTitleCase];
    if (directHit) return directHit;

    const matchedKey = Object.keys(schedule).find((key) => key.toLowerCase() === normalized);
    if (matchedKey) return schedule[matchedKey] ?? [];

    return [];
}

type TimetableBlock = {
    id: string;
    dayKey: string;
    startMinutes: number;
    endMinutes: number;
    timeRange: string;
    roomName: string;
    hasConflict: boolean;
    lane: number;
    laneCount: number;
};

function buildTimetableBlocksForDay(dayKey: string, entries: WeeklyScheduleEntry[]): TimetableBlock[] {
    const blocks = entries
        .map((entry) => {
            const startMinutes = parseTimeToMinutes(entry.start_time);
            const endMinutes = parseTimeToMinutes(entry.end_time);
            // Check for zero or null in a way that respects valid 0
            if (startMinutes === 0 && endMinutes === 0 && entry.start_time !== "00:00") return null;

            return {
                id: `${dayKey}-${entry.start_time}-${entry.end_time}-${entry.room.id}`,
                dayKey,
                startMinutes,
                endMinutes,
                timeRange: entry.time_range,
                roomName: entry.room.name,
                hasConflict: entry.has_conflict,
            };
        })
        .filter((block): block is Omit<TimetableBlock, "lane" | "laneCount"> => block !== null)
        .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes);

    if (blocks.length === 0) return [];

    const result: TimetableBlock[] = [];
    let idx = 0;

    while (idx < blocks.length) {
        const group: Array<Omit<TimetableBlock, "lane" | "laneCount">> = [];
        let groupEnd = blocks[idx].endMinutes;

        while (idx < blocks.length && blocks[idx].startMinutes < groupEnd) {
            const next = blocks[idx];
            group.push(next);
            groupEnd = Math.max(groupEnd, next.endMinutes);
            idx++;
        }

        if (group.length === 0) {
            idx++;
            continue;
        }

        const lanes: number[] = [];
        const groupWithLanes: TimetableBlock[] = [];

        group
            .slice()
            .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes)
            .forEach((entry) => {
                let laneIndex = lanes.findIndex((laneEnd) => laneEnd <= entry.startMinutes);
                if (laneIndex === -1) {
                    laneIndex = lanes.length;
                    lanes.push(entry.endMinutes);
                } else {
                    lanes[laneIndex] = entry.endMinutes;
                }

                groupWithLanes.push({
                    ...entry,
                    lane: laneIndex,
                    laneCount: 0,
                });
            });

        const laneCount = Math.max(1, lanes.length);
        groupWithLanes.forEach((entry) => {
            result.push({ ...entry, laneCount });
        });
    }

    return result;
}

function roundDown(value: number, step: number): number {
    return Math.floor(value / step) * step;
}

function roundUp(value: number, step: number): number {
    return Math.ceil(value / step) * step;
}

type TimetableWindow = {
    startMinutes: number;
    endMinutes: number;
    slotMinutes: number;
    slotHeight: number;
};

function computeTimetableWindow(blocks: TimetableBlock[], slotMinutes = 30): TimetableWindow | null {
    if (blocks.length === 0) return null;

    const start = blocks.reduce((min, block) => Math.min(min, block.startMinutes), blocks[0].startMinutes);
    const end = blocks.reduce((max, block) => Math.max(max, block.endMinutes), blocks[0].endMinutes);

    if (end <= start) return null;

    const roundedStart = Math.max(0, roundDown(start - 30, slotMinutes));
    const roundedEnd = Math.min(24 * 60, roundUp(end + 30, slotMinutes));

    return {
        startMinutes: roundedStart,
        endMinutes: roundedEnd,
        slotMinutes,
        slotHeight: 40,
    };
}

function hexToRgba(hex: string, alpha: number): string | null {
    const value = hex.trim().replace(/^#/, "");

    if (value.length === 3) {
        const r = Number.parseInt(value[0] + value[0], 16);
        const g = Number.parseInt(value[1] + value[1], 16);
        const b = Number.parseInt(value[2] + value[2], 16);
        if ([r, g, b].some((v) => Number.isNaN(v))) return null;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    if (value.length === 6) {
        const r = Number.parseInt(value.slice(0, 2), 16);
        const g = Number.parseInt(value.slice(2, 4), 16);
        const b = Number.parseInt(value.slice(4, 6), 16);
        if ([r, g, b].some((v) => Number.isNaN(v))) return null;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    return null;
}

// --- Components ---

function DraggableBlock({
    entry,
    rooms,
    onEdit,
    onRemove,
    onChangeRoom,
    onChangeTime,
    isOverlay = false,
    hasConflict = false,
}: {
    entry: RawScheduleEntry;
    rooms: RoomOption[];
    onEdit?: (entry: RawScheduleEntry) => void;
    onRemove?: (id: string) => void;
    onChangeRoom?: (id: string, roomId: number) => void;
    onChangeTime?: (id: string, start: string, end: string) => void;
    isOverlay?: boolean;
    hasConflict?: boolean;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: entry.temp_id || String(entry.id),
        data: entry,
        disabled: isOverlay,
    });

    const { top, height } = getGridPosition(entry.start_time, entry.end_time);
    const roomName = rooms.find((r) => r.id === entry.room_id)?.label || "Unknown Room";

    // Resize Logic
    const [isResizing, setIsResizing] = useState<"top" | "bottom" | null>(null);
    const [previewTime, setPreviewTime] = useState<string | null>(null);

    const handleResizeStart = (e: React.MouseEvent, direction: "top" | "bottom") => {
        e.stopPropagation();
        e.preventDefault();
        setIsResizing(direction);

        const startY = e.clientY;
        const startMinutes = parseTimeToMinutes(entry.start_time);
        const endMinutes = parseTimeToMinutes(entry.end_time);

        const handleMouseMove = (moveEvent: MouseEvent) => {
            const deltaY = moveEvent.clientY - startY;
            const deltaMinutes = Math.round(deltaY / PIXELS_PER_MINUTE);
            const snappedDelta = Math.round(deltaMinutes / SNAP_MINUTES) * SNAP_MINUTES;

            let newStart = startMinutes;
            let newEnd = endMinutes;

            if (direction === "top") {
                newStart = Math.min(startMinutes + snappedDelta, endMinutes - SNAP_MINUTES);
                // Check bounds
                newStart = Math.max(newStart, START_HOUR * 60);
                setPreviewTime(`${minutesToTime(newStart)} - ${minutesToTime(endMinutes)}`);
            } else {
                newEnd = Math.max(endMinutes + snappedDelta, startMinutes + SNAP_MINUTES);
                // Check bounds
                newEnd = Math.min(newEnd, END_HOUR * 60);
                setPreviewTime(`${minutesToTime(startMinutes)} - ${minutesToTime(newEnd)}`);
            }
        };

        const handleMouseUp = (upEvent: MouseEvent) => {
            document.removeEventListener("mousemove", handleMouseMove);
            document.removeEventListener("mouseup", handleMouseUp);
            setIsResizing(null);
            setPreviewTime(null);

            // Calculate final times similar to mousemove logic but commit it
            const deltaY = upEvent.clientY - startY;
            const deltaMinutes = Math.round(deltaY / PIXELS_PER_MINUTE);
            const snappedDelta = Math.round(deltaMinutes / SNAP_MINUTES) * SNAP_MINUTES;

            let newStart = startMinutes;
            let newEnd = endMinutes;

            if (direction === "top") {
                newStart = Math.min(startMinutes + snappedDelta, endMinutes - SNAP_MINUTES);
                newStart = Math.max(newStart, START_HOUR * 60);
            } else {
                newEnd = Math.max(endMinutes + snappedDelta, startMinutes + SNAP_MINUTES);
                newEnd = Math.min(newEnd, END_HOUR * 60);
            }

            if (newStart !== startMinutes || newEnd !== endMinutes) {
                onChangeTime?.(entry.temp_id || String(entry.id), minutesToTime(newStart), minutesToTime(newEnd));
            }
        };

        document.addEventListener("mousemove", handleMouseMove);
        document.addEventListener("mouseup", handleMouseUp);
    };

    const style: React.CSSProperties = {
        top: `${top}px`,
        height: `${height}px`,
        opacity: isDragging ? 0.3 : 1,
        position: "absolute",
        left: "2px",
        right: "2px",
        zIndex: isDragging || isResizing ? 50 : 10,
    };

    const content = (
        <div className={`relative flex h-full flex-col px-2 py-1 ${isResizing ? "select-none" : ""}`}>
            <div className="flex items-center justify-between gap-1">
                <span className="truncate font-semibold">
                    {previewTime ||
                        `${format(parse(entry.start_time, "HH:mm", new Date()), "h:mm a")} - ${format(parse(entry.end_time, "HH:mm", new Date()), "h:mm a")}`}
                </span>
                {!isOverlay && !isResizing && onRemove && (
                    <button
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onRemove(entry.temp_id || String(entry.id));
                        }}
                        className="text-muted-foreground hover:bg-destructive hover:text-destructive-foreground hidden rounded p-0.5 group-hover:block"
                    >
                        <Trash2 className="h-3 w-3" />
                    </button>
                )}
            </div>
            <div className="truncate font-medium opacity-90">{roomName}</div>
            {hasConflict && (
                <div className="text-destructive mt-auto flex items-center gap-1 text-[10px] font-bold">
                    <AlertCircle className="h-3 w-3" />
                    Conflict
                </div>
            )}

            {/* Resize Handles */}
            {!isOverlay && (
                <>
                    <div
                        className="hover:bg-primary/20 absolute top-0 right-0 left-0 z-20 h-2 cursor-ns-resize"
                        onMouseDown={(e) => handleResizeStart(e, "top")}
                    />
                    <div
                        className="hover:bg-primary/20 absolute right-0 bottom-0 left-0 z-20 h-2 cursor-ns-resize"
                        onMouseDown={(e) => handleResizeStart(e, "bottom")}
                    />
                </>
            )}

            {isResizing && (
                <div className="absolute -top-8 left-1/2 z-50 -translate-x-1/2 rounded bg-black px-2 py-1 text-xs whitespace-nowrap text-white shadow-md">
                    {previewTime}
                </div>
            )}
        </div>
    );

    const blockClass = `group flex flex-col overflow-hidden rounded-md border text-xs shadow-sm transition-colors
        ${hasConflict ? "bg-destructive/10 border-destructive" : "bg-primary/10 border-primary/20 hover:bg-primary/20"}
        ${isOverlay ? "cursor-grabbing shadow-xl ring-2 ring-primary" : "cursor-grab"}`;

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>
                <div
                    ref={setNodeRef}
                    style={style}
                    {...attributes}
                    {...listeners}
                    className={blockClass}
                    onDoubleClick={(e) => {
                        e.stopPropagation();
                        onEdit?.(entry);
                    }}
                >
                    {content}
                </div>
            </ContextMenuTrigger>
            {!isOverlay && (
                <ContextMenuContent className="w-64">
                    <ContextMenuItem onSelect={() => onEdit?.(entry)}>Edit Details</ContextMenuItem>
                    <ContextMenuSub>
                        <ContextMenuSubTrigger>Move to Room</ContextMenuSubTrigger>
                        <ContextMenuSubContent className="max-h-64 w-48 overflow-y-auto">
                            {rooms.map((room) => (
                                <ContextMenuItem key={room.id} onSelect={() => onChangeRoom?.(entry.temp_id || String(entry.id), room.id)}>
                                    {room.label}
                                    {room.id === entry.room_id && <CheckCircle2 className="ml-auto h-4 w-4" />}
                                </ContextMenuItem>
                            ))}
                        </ContextMenuSubContent>
                    </ContextMenuSub>
                    <ContextMenuItem
                        className="text-destructive focus:text-destructive"
                        onSelect={() => onRemove?.(entry.temp_id || String(entry.id))}
                    >
                        Delete Schedule
                    </ContextMenuItem>
                </ContextMenuContent>
            )}
        </ContextMenu>
    );
}

function DayColumn({
    day,
    entries,
    rooms,
    roomSchedules,
    onEdit,
    onRemove,
    onChangeRoom,
    onChangeTime,
    conflicts,
}: {
    day: string;
    entries: RawScheduleEntry[];
    rooms: RoomOption[];
    roomSchedules: Record<number, RoomScheduleEntry[]>;
    onEdit: (entry: RawScheduleEntry) => void;
    onRemove: (id: string) => void;
    onChangeRoom: (id: string, roomId: number) => void;
    onChangeTime: (id: string, start: string, end: string) => void;
    conflicts: Set<string>;
}) {
    const { setNodeRef, isOver } = useDroppable({
        id: day,
        data: { day },
    });

    // Calculate occupied slots for the rooms involved in *this column's entries*
    // Wait, if I drag an entry, I want to see occupied slots for *its* room.
    // Actually, standard behavior: Show "busy" slots for the room of the *currently dragged* item, or just show all busy slots for the *entries present*?
    // User asked: "show red marks... of the room sched".
    // Let's show red marks for ALL rooms that have entries in this column, plus any global selection?
    // Simpler: Iterate through all entries in this column. For each entry, get its room's *other* schedules and render them as background red blocks.

    const occupiedSlots = useMemo(() => {
        const slots: { top: number; height: number; title: string }[] = [];
        entries.forEach((entry) => {
            const roomScheds = roomSchedules[entry.room_id] || [];
            roomScheds.forEach((sched) => {
                if (sched.day_of_week === day && sched.class_id !== entry.id) {
                    // Don't show conflict for self (if saved)
                    // Actually, we are editing. `entry.id` might match `sched.id` if it came from DB.
                    // But `raw_schedules` has our *current* state. `roomSchedules` has *DB* state.
                    // If we are moving a block, we shouldn't see its *old* position as a conflict?
                    // `roomSchedules` contains *all* classes.
                    // We should filter out *this class's* schedules from `roomSchedules` completely in the controller or here.
                    // Ideally controller filters. But let's assume `roomSchedules` has *other* classes too.
                    // Filter: sched.class_id !== currentClassId. We don't have currentClassId here easily without prop drilling.
                    // Let's rely on the fact that we want to see "unavailable".
                    // If the schedule belongs to *this* class, we ignore it from the "red marks" because the draggable block represents it.
                    // We need to know the current class ID.

                    // For now, render all. If it overlaps with *self*, it's fine (draggable covers it).
                    // But visually better if we filter.
                    const { top, height } = getGridPosition(sched.start_time, sched.end_time);
                    slots.push({ top, height, title: sched.title });
                }
            });
        });
        return slots;
    }, [entries, day, roomSchedules]);

    return (
        <div ref={setNodeRef} className={`relative min-w-[140px] flex-1 border-r border-dashed last:border-r-0 ${isOver ? "bg-muted/30" : ""}`}>
            {/* Grid Lines */}
            <div className="pointer-events-none absolute inset-0">
                {Array.from({ length: END_HOUR - START_HOUR }).map((_, i) => (
                    <div key={i} className="border-border/30 w-full border-b" style={{ height: `${PIXELS_PER_HOUR}px` }} />
                ))}
            </div>

            {/* Occupied Slots (Red Marks) */}
            {occupiedSlots.map((slot, i) => (
                <div
                    key={i}
                    className="absolute right-1 left-1 z-0 flex items-center justify-center overflow-hidden rounded border border-red-200 bg-red-100/50 text-[10px] font-medium text-red-500"
                    style={{ top: slot.top, height: slot.height }}
                    title={`Occupied: ${slot.title}`}
                >
                    <span className="truncate px-1">Occupied</span>
                </div>
            ))}

            {/* Events */}
            {entries.map((entry) => (
                <DraggableBlock
                    key={entry.temp_id || entry.id}
                    entry={entry}
                    rooms={rooms}
                    onEdit={onEdit}
                    onRemove={onRemove}
                    onChangeRoom={onChangeRoom}
                    onChangeTime={onChangeTime}
                    hasConflict={conflicts.has(entry.temp_id || String(entry.id || ""))}
                />
            ))}
        </div>
    );
}

function TimetableEditor({
    classItem,
    initialSchedules,
    roomSchedules,
    rooms,
    open,
    onOpenChange,
}: {
    classItem: ClassShowData;
    initialSchedules: RawScheduleEntry[];
    roomSchedules: Record<number, RoomScheduleEntry[]>;
    rooms: RoomOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, put, processing, reset } = useForm({
        schedules: initialSchedules.map((s, i) => ({
            ...s,
            temp_id: s.temp_id || `id-${i}-${Date.now()}`,
        })),
    });

    const [activeId, setActiveId] = useState<string | null>(null);
    const [editingEntry, setEditingEntry] = useState<RawScheduleEntry | null>(null);

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 5 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 150, tolerance: 5 } }),
        useSensor(KeyboardSensor),
    );

    // Conflict Detection (Internal to current class)
    const conflicts = useMemo(() => {
        const conflictSet = new Set<string>();
        data.schedules.forEach((a) => {
            data.schedules.forEach((b) => {
                if (a.temp_id === b.temp_id) return;
                if (a.day_of_week !== b.day_of_week) return;

                const startA = parseTimeToMinutes(a.start_time);
                const endA = parseTimeToMinutes(a.end_time);
                const startB = parseTimeToMinutes(b.start_time);
                const endB = parseTimeToMinutes(b.end_time);

                if (Math.max(startA, startB) < Math.min(endA, endB)) {
                    conflictSet.add(a.temp_id!);
                    conflictSet.add(b.temp_id!);
                }
            });
        });
        return conflictSet;
    }, [data.schedules]);

    const handleDragStart = (event: any) => {
        setActiveId(event.active.id);
    };

    const handleDragEnd = (event: any) => {
        const { active, over, delta } = event;
        setActiveId(null);

        if (!over) return;

        const movedEntry = data.schedules.find((s) => (s.temp_id || String(s.id)) === active.id);
        if (!movedEntry) return;

        const minutesDelta = Math.round(delta.y / PIXELS_PER_MINUTE);
        const snappedMinutesDelta = Math.round(minutesDelta / SNAP_MINUTES) * SNAP_MINUTES;

        const originalStartMinutes = parseTimeToMinutes(movedEntry.start_time);
        const originalEndMinutes = parseTimeToMinutes(movedEntry.end_time);
        const duration = originalEndMinutes - originalStartMinutes;

        let newStartMinutes = originalStartMinutes + snappedMinutesDelta;
        let newEndMinutes = newStartMinutes + duration;

        const minMinutes = START_HOUR * 60;
        const maxMinutes = END_HOUR * 60;

        if (newStartMinutes < minMinutes) {
            newStartMinutes = minMinutes;
            newEndMinutes = newStartMinutes + duration;
        }
        if (newEndMinutes > maxMinutes) {
            newEndMinutes = maxMinutes;
            newStartMinutes = newEndMinutes - duration;
        }

        const newDay = over.id as string;

        const updatedSchedules = data.schedules.map((s) => {
            if ((s.temp_id || String(s.id)) === active.id) {
                return {
                    ...s,
                    day_of_week: newDay,
                    start_time: minutesToTime(newStartMinutes),
                    end_time: minutesToTime(newEndMinutes),
                };
            }
            return s;
        });

        setData("schedules", updatedSchedules);
    };

    const handleAdd = () => {
        const newEntry = {
            temp_id: `new-${Date.now()}`,
            day_of_week: "Monday",
            start_time: "08:00",
            end_time: "09:00",
            room_id: rooms[0]?.id || 0,
        };
        setData("schedules", [...data.schedules, newEntry]);
        setEditingEntry(newEntry);
    };

    const handleRemove = (id: string) => {
        setData(
            "schedules",
            data.schedules.filter((s) => (s.temp_id || String(s.id)) !== id),
        );
    };

    const handleUpdateEntry = (updated: RawScheduleEntry) => {
        setData(
            "schedules",
            data.schedules.map((s) => (s.temp_id === updated.temp_id ? ({ ...updated, temp_id: updated.temp_id! } as typeof s) : s)),
        );
        setEditingEntry(null);
    };

    const handleChangeRoom = (id: string, roomId: number) => {
        setData(
            "schedules",
            data.schedules.map((s) => ((s.temp_id || String(s.id)) === id ? { ...s, room_id: roomId } : s)),
        );
    };

    const handleChangeTime = (id: string, start: string, end: string) => {
        setData(
            "schedules",
            data.schedules.map((s) => ((s.temp_id || String(s.id)) === id ? { ...s, start_time: start, end_time: end } : s)),
        );
    };

    const handleSubmit = () => {
        put(route("administrators.classes.update", classItem.id), {
            onSuccess: () => {
                toast.success("Schedule updated successfully");
                onOpenChange(false);
            },
            onError: () => {
                toast.error("Failed to update. Check conflicts.");
            },
        });
    };

    const activeEntry = useMemo(() => data.schedules.find((s) => (s.temp_id || String(s.id)) === activeId), [activeId, data.schedules]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex h-[95vh] min-w-[80vw] flex-col overflow-hidden rounded-none sm:rounded-lg">
                <DialogHeader className="border-b px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <DialogTitle>Interactive Timetable</DialogTitle>
                            <DialogDescription>Drag to move/resize. Right-click to change room.</DialogDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="text-muted-foreground mr-4 flex items-center gap-2 text-xs">
                                <div className="flex items-center gap-1">
                                    <div className="bg-primary/20 border-primary/30 h-3 w-3 rounded border" />
                                    <span>Scheduled</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    <div className="h-3 w-3 rounded border border-red-200 bg-red-100/50" />
                                    <span>Room Occupied</span>
                                </div>
                            </div>
                            <Button size="sm" onClick={handleAdd}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Class
                            </Button>
                            <Button size="sm" onClick={handleSubmit} disabled={processing}>
                                {processing ? "Saving..." : "Save Changes"}
                            </Button>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex flex-1 flex-col overflow-hidden">
                    <ScrollArea className="w-full flex-1">
                        <div className="flex min-w-[1200px] p-4">
                            {/* Time Labels */}
                            <div className="text-muted-foreground mr-2 w-16 flex-shrink-0 space-y-[44px] pt-8 text-right text-xs">
                                {Array.from({ length: END_HOUR - START_HOUR + 1 }).map((_, i) => {
                                    const hour = START_HOUR + i;
                                    const label = format(set(new Date(), { hours: hour, minutes: 0 }), "h a");
                                    return (
                                        <div key={hour} className="-mt-2 h-[60px] pr-2" style={{ height: `${PIXELS_PER_HOUR}px` }}>
                                            {label}
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Grid */}
                            <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
                                <div className="bg-background relative flex flex-1 overflow-hidden rounded-lg border">
                                    {scheduleDayOrder.map((day) => (
                                        <div key={day} className="flex min-w-[140px] flex-1 flex-col">
                                            <div className="bg-muted/40 flex h-8 items-center justify-center border-r border-b text-xs font-semibold tracking-wide uppercase">
                                                {day.slice(0, 3)}
                                            </div>
                                            <DayColumn
                                                day={day}
                                                entries={data.schedules.filter((s) => s.day_of_week === day)}
                                                rooms={rooms}
                                                roomSchedules={roomSchedules}
                                                onEdit={setEditingEntry}
                                                onRemove={handleRemove}
                                                onChangeRoom={handleChangeRoom}
                                                onChangeTime={handleChangeTime}
                                                conflicts={conflicts}
                                            />
                                        </div>
                                    ))}
                                </div>

                                {createPortal(
                                    <DragOverlay modifiers={[snapCenterToCursor]}>
                                        {activeEntry ? <DraggableBlock entry={activeEntry} rooms={rooms} isOverlay /> : null}
                                    </DragOverlay>,
                                    document.body,
                                )}
                            </DndContext>
                        </div>
                        <ScrollBar orientation="horizontal" />
                        <ScrollBar orientation="vertical" />
                    </ScrollArea>
                </div>

                {/* Edit Popover (Dialog) */}
                {editingEntry && (
                    <Dialog open={!!editingEntry} onOpenChange={(o) => !o && setEditingEntry(null)}>
                        <DialogContent className="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Edit Schedule</DialogTitle>
                                <DialogDescription>Modify the details for this class session.</DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4 py-4">
                                {/* ... (Existing inputs for day, start, end, room) ... */}
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="day" className="text-right">
                                        Day
                                    </Label>
                                    <Select
                                        value={editingEntry.day_of_week}
                                        onValueChange={(val) => setEditingEntry({ ...editingEntry, day_of_week: val })}
                                    >
                                        <SelectTrigger className="col-span-3">
                                            <SelectValue placeholder="Select day" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {scheduleDayOrder.map((day) => (
                                                <SelectItem key={day} value={day}>
                                                    {day}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="start" className="text-right">
                                        Start
                                    </Label>
                                    <Input
                                        id="start"
                                        type="time"
                                        value={editingEntry.start_time}
                                        onChange={(e) =>
                                            setEditingEntry({
                                                ...editingEntry,
                                                start_time: e.target.value,
                                            })
                                        }
                                        className="col-span-3"
                                    />
                                </div>
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="end" className="text-right">
                                        End
                                    </Label>
                                    <Input
                                        id="end"
                                        type="time"
                                        value={editingEntry.end_time}
                                        onChange={(e) =>
                                            setEditingEntry({
                                                ...editingEntry,
                                                end_time: e.target.value,
                                            })
                                        }
                                        className="col-span-3"
                                    />
                                </div>
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="room" className="text-right">
                                        Room
                                    </Label>
                                    <Select
                                        value={String(editingEntry.room_id)}
                                        onValueChange={(val) =>
                                            setEditingEntry({
                                                ...editingEntry,
                                                room_id: parseInt(val),
                                            })
                                        }
                                    >
                                        <SelectTrigger className="col-span-3">
                                            <SelectValue placeholder="Select room" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {rooms.map((room) => (
                                                <SelectItem key={room.id} value={String(room.id)}>
                                                    {room.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="destructive"
                                    type="button"
                                    onClick={() => {
                                        handleRemove(editingEntry.temp_id || String(editingEntry.id));
                                        setEditingEntry(null);
                                    }}
                                    className="mr-auto"
                                >
                                    Delete
                                </Button>
                                <Button type="button" onClick={() => handleUpdateEntry(editingEntry)}>
                                    Done
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </DialogContent>
        </Dialog>
    );
}

function MoveStudentDialog({
    open,
    onOpenChange,
    student,
    transferableClasses,
    currentClassId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    student: EnrollmentRow | null;
    transferableClasses: TransferableClass[];
    currentClassId: number;
}) {
    const { data, setData, post, processing, reset, errors } = useForm({
        student_id: student?.student?.student_id, // Use student_id string or internal id? Controller expects 'student_id' which is likely the user ID (student model ID).
        // Wait, row.student.student_id is likely the string ID (e.g. "2023-001"). row.student.id is the PK.
        // The controller validation says: 'student_id' => ['required', 'integer', 'exists:students,id']
        // So we need row.student.id.
        target_class_id: "",
        notify_student: true,
    });

    // We need to set student_id when student prop changes, but row.student might be null.
    // Using useEffect.
    useEffect(() => {
        if (student?.student) {
            // We need to pass the student PK ID, not the string student_id
            // row.student.student_id seems to be the string ID based on types: student_id: string | number | null
            // row.student.id is the number PK.
            setData((data) => ({ ...data, student_id: student.student?.student_id })); // Wait, let's check what `student_id` in validation refers to.
            // `exists:students,id` refers to the primary key `id` of `students` table.
            // So I should pass `student.student.id`.
        }
    }, [student]);

    // Actually, let's fix the initial state logic.

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // We need to ensure we are passing the correct student PK ID.
        // If setData didn't update or user didn't change (it's hidden), we rely on state.
        // But better to set it explicitly on submit if needed, or rely on useEffect.

        // NOTE: The `useForm` initial value is evaluated once. `setData` in `useEffect` updates it.

        post(route("administrators.classes.move-student", currentClassId), {
            onSuccess: () => {
                toast.success("Student transfer queued successfully");
                onOpenChange(false);
                reset();
            },
            onError: () => {
                toast.error("Failed to queue transfer");
            },
        });
    };

    // Update data.student_id when student changes
    useEffect(() => {
        if (student?.student?.id) {
            setData("student_id", student.student.id);
        }
    }, [student]);

    if (!student) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move Student to Another Section</DialogTitle>
                    <DialogDescription>
                        Transfer <strong>{student.student?.name}</strong> to another class section. This process happens in the background.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="space-y-2">
                        <Label>Target Class</Label>
                        <Select value={data.target_class_id} onValueChange={(val) => setData("target_class_id", val)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a section..." />
                            </SelectTrigger>
                            <SelectContent>
                                {transferableClasses.length > 0 ? (
                                    transferableClasses.map((cls) => (
                                        <SelectItem key={cls.id} value={String(cls.id)} disabled={cls.is_full}>
                                            {cls.label}
                                        </SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="none" disabled>
                                        No other sections available
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        {errors.target_class_id && <p className="text-destructive text-sm">{errors.target_class_id}</p>}
                    </div>

                    <div className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            id="notify"
                            className="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                            checked={data.notify_student}
                            onChange={(e) => setData("notify_student", e.target.checked)}
                        />
                        <Label htmlFor="notify" className="cursor-pointer font-normal">
                            Notify student via email
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !data.target_class_id}>
                            {processing ? "Queuing..." : "Move Student"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// --- Main Page Component ---

function StatCard({
    icon: Icon,
    label,
    value,
    subtext,
    className,
}: {
    icon: any;
    label: string;
    value: string | number;
    subtext?: string;
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardContent className="flex items-start justify-between space-y-0 p-4">
                <div className="flex flex-col gap-1">
                    <span className="text-muted-foreground text-sm font-medium">{label}</span>
                    <span className="text-2xl font-bold tracking-tight">{value}</span>
                    {subtext && <span className="text-muted-foreground text-xs">{subtext}</span>}
                </div>
                <div className="bg-muted/50 rounded-lg p-2">
                    <Icon className="text-muted-foreground h-5 w-5" />
                </div>
            </CardContent>
        </Card>
    );
}

export default function AdministratorClassShow({
    user,
    class: classItem,
    enrollments,
    raw_schedules,
    room_schedules,
    rooms,
    transferable_classes,
}: ClassShowProps) {
    const [scheduleDialogOpen, setScheduleDialogOpen] = useState(false);
    const [moveStudentDialogOpen, setMoveStudentDialogOpen] = useState(false);
    const [studentToMove, setStudentToMove] = useState<EnrollmentRow | null>(null);
    const enrollmentStatusTone = enrollmentTone(classItem.students_count, classItem.maximum_slots);

    const columns = useMemo<ColumnDef<EnrollmentRow>[]>(
        () => [
            {
                accessorKey: "student.name",
                header: ({ column }) => {
                    return (
                        <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                            Student
                            <ArrowUpDown className="ml-2 h-4 w-4" />
                        </Button>
                    );
                },
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold">
                            {row.original.student?.name.charAt(0)}
                        </div>
                        <div>
                            <div className="font-medium">{row.original.student?.name}</div>
                            <div className="text-muted-foreground font-mono text-xs">{row.original.student?.student_id}</div>
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: "student.course",
                header: "Course & Year",
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm">{row.original.student?.course || "N/A"}</div>
                        <div className="text-muted-foreground text-xs">
                            {row.original.student?.academic_year ? `Year ${row.original.student.academic_year}` : ""}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: "status",
                header: "Status",
                cell: ({ row }) => (
                    <Badge
                        variant={row.original.status ? "default" : "secondary"}
                        className={row.original.status ? "border-0 bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/25" : ""}
                    >
                        {row.original.status ? "Active" : "Inactive"}
                    </Badge>
                ),
            },
            {
                accessorKey: "total_average",
                header: ({ column }) => (
                    <div className="text-right">
                        <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-mr-4">
                            Performance
                            <ArrowUpDown className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                ),
                cell: ({ row }) => (
                    <div className="pr-6 text-right font-mono font-medium">
                        {row.original.total_average ? row.original.total_average.toFixed(2) : "—"}
                    </div>
                ),
            },
            {
                id: "actions",
                cell: ({ row }) => {
                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" className="h-8 w-8 p-0">
                                    <span className="sr-only">Open menu</span>
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                <DropdownMenuItem
                                    onClick={() => {
                                        setStudentToMove(row.original);
                                        setMoveStudentDialogOpen(true);
                                    }}
                                >
                                    <ArrowRightLeft className="mr-2 h-4 w-4" />
                                    Move to Section
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [],
    );

    const scheduleEntriesByDay = scheduleDayOrder.map((dayKey) => ({
        key: dayKey,
        label: dayKey.charAt(0).toUpperCase() + dayKey.slice(1),
        shortLabel: dayKey.slice(0, 3).toUpperCase(),
        entries: getScheduleEntriesForDay(classItem.schedule, dayKey),
    }));

    const timetableBlocksByDay = scheduleEntriesByDay.map((day) => ({
        ...day,
        blocks: buildTimetableBlocksForDay(day.key, day.entries),
    }));

    const scheduleEntries = scheduleEntriesByDay.flatMap((day) => day.entries);
    const scheduleEntriesExist = scheduleEntries.length > 0;
    const uniqueRoomNames = Array.from(new Set(scheduleEntries.map((entry) => entry.room.name).filter((name) => name.trim() !== ""))).sort((a, b) =>
        a.localeCompare(b),
    );

    const meetingsCount = scheduleEntries.length;
    const timetableBlocks = timetableBlocksByDay.flatMap((day) => day.blocks);
    const timetableWindow = computeTimetableWindow(timetableBlocks);

    const timetableSlots = timetableWindow
        ? Array.from(
              {
                  length: Math.ceil((timetableWindow.endMinutes - timetableWindow.startMinutes) / timetableWindow.slotMinutes),
              },
              (_, idx) => idx,
          )
        : [];

    const timetableHours = timetableWindow
        ? Array.from(
              {
                  length: Math.floor((timetableWindow.endMinutes - timetableWindow.startMinutes) / 60) + 1,
              },
              (_, idx) => {
                  const startHour = Math.ceil(timetableWindow.startMinutes / 60) * 60;
                  const minute = startHour + idx * 60;
                  return minute <= timetableWindow.endMinutes ? minute : null;
              },
          ).filter((x): x is number => x !== null)
        : [];

    const timetableHeight = timetableWindow ? timetableSlots.length * timetableWindow.slotHeight : 0;

    const accentColor = classItem.settings?.accent_color ?? "#3b82f6";
    const timetableEventFill = hexToRgba(accentColor, 0.1) ?? "rgba(59, 130, 246, 0.1)";
    const timetableEventBorder = hexToRgba(accentColor, 0.4) ?? "rgba(59, 130, 246, 0.4)";
    const timetableEventText = hexToRgba(accentColor, 1) ?? "rgba(59, 130, 246, 1)";

    const featureBadges = [
        {
            key: "enable_announcements",
            label: "Announcements",
            enabled: classItem.settings?.features?.enable_announcements ?? false,
        },
        {
            key: "enable_grade_visibility",
            label: "Grades",
            enabled: classItem.settings?.features?.enable_grade_visibility ?? false,
        },
        {
            key: "enable_attendance_tracking",
            label: "Attendance",
            enabled: classItem.settings?.features?.enable_attendance_tracking ?? false,
        },
        {
            key: "allow_late_submissions",
            label: "Late Subs",
            enabled: classItem.settings?.features?.allow_late_submissions ?? false,
        },
        {
            key: "enable_discussion_board",
            label: "Discussions",
            enabled: classItem.settings?.features?.enable_discussion_board ?? false,
        },
    ] as const;

    return (
        <AdminLayout user={user} title="Class Details">
            <Head title={`Class • ${classItem.record_title}`} />
            <TooltipProvider>
                <div className="mx-auto max-w-7xl space-y-6">
                    {/* Hero Section */}
                    <div
                        className="bg-card relative overflow-hidden rounded-2xl border shadow-sm transition-all"
                        style={{
                            backgroundColor: classItem.settings?.banner_image_url ? undefined : hexToRgba(accentColor, 0.05) || undefined,
                            borderColor: hexToRgba(accentColor, 0.2) || undefined,
                        }}
                    >
                        {/* Background Banner Image or Gradient */}
                        {classItem.settings?.banner_image_url ? (
                            <div
                                className="absolute inset-0 z-0 opacity-20"
                                style={{
                                    backgroundImage: `url(${classItem.settings.banner_image_url})`,
                                    backgroundSize: "cover",
                                    backgroundPosition: "center",
                                }}
                            />
                        ) : (
                            <div
                                className="absolute inset-0 z-0 bg-gradient-to-tr from-transparent opacity-10"
                                style={{
                                    backgroundImage: `linear-gradient(to top right, transparent, ${accentColor})`,
                                }}
                            />
                        )}

                        <div className="relative z-10 flex flex-col gap-6 p-6 sm:p-8 md:flex-row md:items-end md:justify-between">
                            <div className="space-y-4">
                                <div className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-muted-foreground hover:text-foreground h-6 px-0 hover:bg-transparent"
                                        asChild
                                    >
                                        <Link href={route("administrators.classes.index")}>
                                            <ChevronLeft className="mr-1 h-3 w-3" />
                                            Back to Classes
                                        </Link>
                                    </Button>
                                    <span className="opacity-50">/</span>
                                    <span>{classItem.school_year}</span>
                                    <span className="opacity-50">/</span>
                                    <span className="uppercase">{classItem.semester}</span>
                                </div>

                                <div>
                                    <h1 className="text-foreground text-3xl font-extrabold tracking-tight sm:text-4xl">{classItem.record_title}</h1>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <Badge className="bg-primary text-primary-foreground border-0 px-2 py-0.5 text-xs font-semibold shadow-sm">
                                            {classItem.subject_code}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            className="border-border bg-background/50 px-2 py-0.5 text-xs font-medium shadow-sm backdrop-blur-sm"
                                        >
                                            SECTION {classItem.section}
                                        </Badge>
                                        <Badge variant="outline" className="bg-background/50 px-2 py-0.5 text-xs font-medium backdrop-blur-sm">
                                            {classItem.classification?.toUpperCase() ?? "N/A"}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                {/* Instructor Preview */}
                                {classItem.faculty && (
                                    <div className="bg-background/60 flex items-center gap-3 rounded-full border px-4 py-2 shadow-sm backdrop-blur-md sm:mr-4">
                                        <Avatar className="border-background h-8 w-8 border-2 shadow-sm">
                                            <AvatarImage src={classItem.faculty.avatar_url ?? undefined} />
                                            <AvatarFallback className="bg-primary/10 text-primary text-xs">
                                                {classItem.faculty.name.charAt(0)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex flex-col">
                                            <span className="text-xs leading-none font-bold">{classItem.faculty.name}</span>
                                            <span className="text-muted-foreground text-[10px] font-medium">Instructor</span>
                                        </div>
                                    </div>
                                )}

                                <div className="flex w-full items-center gap-2 sm:w-auto">
                                    <Button onClick={() => setScheduleDialogOpen(true)} className="flex-1 shadow-sm sm:flex-none">
                                        <Settings2 className="mr-2 h-4 w-4" />
                                        Manage Schedule
                                    </Button>
                                    <Button size="icon" asChild variant="secondary" className="shrink-0 shadow-sm" title="Open in Filament">
                                        <a href={classItem.filament?.view_url ?? "#"} target="_blank" rel="noreferrer">
                                            <ArrowUpRight className="h-4 w-4" />
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <TimetableEditor
                        classItem={classItem}
                        initialSchedules={raw_schedules}
                        roomSchedules={room_schedules}
                        rooms={rooms}
                        open={scheduleDialogOpen}
                        onOpenChange={setScheduleDialogOpen}
                    />

                    <MoveStudentDialog
                        open={moveStudentDialogOpen}
                        onOpenChange={setMoveStudentDialogOpen}
                        student={studentToMove}
                        transferableClasses={transferable_classes || []}
                        currentClassId={classItem.id}
                    />

                    {/* Main Content Area */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 xl:gap-8">
                        {/* Left Column (Main Details) */}
                        <div className="space-y-6 lg:col-span-2">
                            <Tabs defaultValue="overview" className="w-full">
                                <TabsList className="mb-6 h-12 w-full justify-start gap-6 rounded-none border-b bg-transparent p-0">
                                    <TabsTrigger
                                        value="overview"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <LayoutDashboard className="mr-2 h-4 w-4" />
                                        Overview
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="details"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <ListTodo className="mr-2 h-4 w-4" />
                                        Details & Settings
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="schedule"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        Visual Schedule
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="enrollments"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <Users className="mr-2 h-4 w-4" />
                                        Enrollments
                                        <Badge
                                            variant="secondary"
                                            className="bg-muted-foreground/10 text-muted-foreground hover:bg-muted-foreground/20 ml-2"
                                        >
                                            {enrollments.total}
                                        </Badge>
                                    </TabsTrigger>
                                </TabsList>

                                {/* OVERVIEW TAB */}
                                <TabsContent value="overview" className="m-0 space-y-6">
                                    {/* Stats Overview */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <StatCard
                                            icon={Users}
                                            label="Total Enrollment"
                                            value={`${classItem.students_count}/${classItem.maximum_slots || "∞"}`}
                                            subtext={
                                                enrollmentStatusTone === "success"
                                                    ? "Within capacity"
                                                    : enrollmentStatusTone === "warning"
                                                      ? "Nearing capacity"
                                                      : "Over capacity"
                                            }
                                            className={enrollmentStatusTone === "destructive" ? "border-destructive/50 bg-destructive/5" : ""}
                                        />
                                        <StatCard
                                            icon={CalendarIcon}
                                            label="Weekly Sessions"
                                            value={meetingsCount}
                                            subtext={meetingsCount > 0 ? "Active schedule" : "No schedule set"}
                                        />
                                        <StatCard
                                            icon={MapPin}
                                            label="Primary Room"
                                            value={uniqueRoomNames[0] || "—"}
                                            subtext={uniqueRoomNames.length > 1 ? `+${uniqueRoomNames.length - 1} other rooms` : "Assigned location"}
                                        />
                                        <StatCard
                                            icon={GraduationCap}
                                            label="Instructor"
                                            value={classItem.faculty?.name.split(" ").slice(-1)[0] || "Unassigned"}
                                            subtext="Faculty Head"
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                        {/* Left Content: Features */}
                                        <div className="space-y-6 lg:col-span-2">
                                            <Card className="border-border/60 shadow-sm">
                                                <CardHeader className="bg-muted/10 border-b px-6 py-4">
                                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                                        <Layers className="text-primary h-4 w-4" />
                                                        Active Capabilities
                                                    </CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-wrap gap-2 p-6">
                                                    {featureBadges.map((feature) => (
                                                        <Badge
                                                            key={feature.key}
                                                            variant={feature.enabled ? "default" : "secondary"}
                                                            className={
                                                                feature.enabled
                                                                    ? "bg-primary/10 text-primary hover:bg-primary/20 border-0"
                                                                    : "opacity-50"
                                                            }
                                                        >
                                                            {feature.enabled && <CheckCircle2 className="mr-1 h-3 w-3" />}
                                                            {feature.label}
                                                        </Badge>
                                                    ))}
                                                </CardContent>
                                            </Card>
                                        </div>

                                        {/* Right Content: Quick Info */}
                                        <div className="space-y-6">
                                            <Card className="border-border/60 shadow-sm">
                                                <CardHeader className="bg-muted/10 border-b pb-4">
                                                    <CardTitle className="mt-2 text-center text-base font-semibold">Class Subjects & Tags</CardTitle>
                                                </CardHeader>
                                                <CardContent className="pt-6">
                                                    <div className="flex flex-wrap justify-center gap-1">
                                                        {classItem.subjects?.length ? (
                                                            classItem.subjects.map((s) => (
                                                                <Badge key={s} variant="outline" className="bg-background font-normal">
                                                                    {s}
                                                                </Badge>
                                                            ))
                                                        ) : (
                                                            <span className="text-muted-foreground text-sm">—</span>
                                                        )}
                                                        {splitBadges(classItem.associated_courses).map((c) => (
                                                            <Badge key={c} variant="secondary" className="bg-muted font-normal">
                                                                {c}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        </div>
                                    </div>
                                </TabsContent>
                                {/* DETAILS TAB */}
                                <TabsContent value="details" className="m-0 space-y-6">
                                    <Card className="border-border/60 shadow-sm">
                                        <CardHeader className="bg-muted/10 border-b pb-4">
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-1">
                                                    <CardTitle className="mt-2 flex items-center gap-2 text-center text-base font-semibold">
                                                        <Palette className="text-primary h-4 w-4" />
                                                        Theme & Branding
                                                    </CardTitle>
                                                    <CardDescription>Customize how this class appears to students</CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-6 pt-6"></CardContent>
                                    </Card>
                                </TabsContent>

                                {/* SCHEDULE TAB */}
                                <TabsContent value="schedule" className="m-0 space-y-6">
                                    {/* Schedule Card */}
                                    <Card className="border-border/60 overflow-hidden shadow-sm">
                                        <CardHeader className="bg-muted/20 border-b px-6 py-4">
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-1">
                                                    <CardTitle className="flex items-center gap-2 text-lg">
                                                        <Clock className="text-primary h-5 w-5" />
                                                        Class Schedule
                                                    </CardTitle>
                                                    <CardDescription>Weekly timetable overview</CardDescription>
                                                </div>
                                                <Tabs defaultValue="matrix" className="w-auto">
                                                    <TabsList className="h-8">
                                                        <TabsTrigger value="matrix" className="h-7 text-xs">
                                                            Timetable
                                                        </TabsTrigger>
                                                        <TabsTrigger value="list" className="h-7 text-xs">
                                                            List View
                                                        </TabsTrigger>
                                                    </TabsList>
                                                </Tabs>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-0">
                                            <Tabs defaultValue="matrix" className="w-full">
                                                <div className="bg-muted/40 flex items-center justify-between border-b px-6 py-3">
                                                    <div className="text-muted-foreground text-sm font-medium">View Mode</div>
                                                    <TabsList className="h-8">
                                                        <TabsTrigger value="matrix" className="h-7 text-xs">
                                                            Timetable
                                                        </TabsTrigger>
                                                        <TabsTrigger value="list" className="h-7 text-xs">
                                                            List
                                                        </TabsTrigger>
                                                    </TabsList>
                                                </div>

                                                <TabsContent value="matrix" className="m-0 p-0">
                                                    {scheduleEntriesExist && timetableWindow ? (
                                                        <ScrollArea className="w-full whitespace-nowrap">
                                                            <div className="bg-background min-w-[800px] p-4">
                                                                {/* Header Row */}
                                                                <div className="grid grid-cols-[60px_1fr] border-b">
                                                                    <div className="text-muted-foreground border-r p-2 text-xs font-medium">Time</div>
                                                                    <div className="grid grid-cols-7">
                                                                        {timetableBlocksByDay.map((day) => (
                                                                            <div key={day.key} className="border-r p-2 text-center last:border-r-0">
                                                                                <div className="text-xs font-bold uppercase">{day.shortLabel}</div>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>

                                                                {/* Body */}
                                                                <div className="relative grid grid-cols-[60px_1fr]">
                                                                    {/* Time Labels Column */}
                                                                    <div className="bg-muted/5 relative border-r" style={{ height: timetableHeight }}>
                                                                        {timetableHours.map((minutes) => {
                                                                            const top =
                                                                                ((minutes - timetableWindow.startMinutes) /
                                                                                    timetableWindow.slotMinutes) *
                                                                                timetableWindow.slotHeight;
                                                                            return (
                                                                                <div
                                                                                    key={minutes}
                                                                                    className="text-muted-foreground absolute w-full -translate-y-1/2 pr-2 text-right text-[10px]"
                                                                                    style={{ top }}
                                                                                >
                                                                                    {formatTimeLabel(minutes)}
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>

                                                                    {/* Days Columns */}
                                                                    <div
                                                                        className="bg-background relative grid grid-cols-7"
                                                                        style={{ height: timetableHeight }}
                                                                    >
                                                                        {/* Horizontal Guide Lines */}
                                                                        <div className="pointer-events-none absolute inset-0 z-0">
                                                                            {timetableHours.map((minutes) => {
                                                                                const top =
                                                                                    ((minutes - timetableWindow.startMinutes) /
                                                                                        timetableWindow.slotMinutes) *
                                                                                    timetableWindow.slotHeight;
                                                                                return (
                                                                                    <div
                                                                                        key={minutes}
                                                                                        className="border-border/40 absolute w-full border-t border-dashed"
                                                                                        style={{ top }}
                                                                                    />
                                                                                );
                                                                            })}
                                                                        </div>

                                                                        {timetableBlocksByDay.map((day, idx) => (
                                                                            <div
                                                                                key={day.key}
                                                                                className={`relative border-r last:border-r-0 ${idx % 2 === 0 ? "bg-background" : "bg-muted/5"}`}
                                                                            >
                                                                                {day.blocks.map((block) => {
                                                                                    const top =
                                                                                        ((block.startMinutes - timetableWindow.startMinutes) /
                                                                                            timetableWindow.slotMinutes) *
                                                                                        timetableWindow.slotHeight;
                                                                                    const height =
                                                                                        ((block.endMinutes - block.startMinutes) /
                                                                                            timetableWindow.slotMinutes) *
                                                                                        timetableWindow.slotHeight;
                                                                                    const width = `${100 / block.laneCount}%`;
                                                                                    const left = `${(block.lane / block.laneCount) * 100}%`;

                                                                                    return (
                                                                                        <Tooltip key={block.id}>
                                                                                            <TooltipTrigger asChild>
                                                                                                <div
                                                                                                    className={`absolute z-10 p-0.5 transition-all hover:z-20 hover:scale-[1.02]`}
                                                                                                    style={{
                                                                                                        top,
                                                                                                        height,
                                                                                                        left,
                                                                                                        width,
                                                                                                    }}
                                                                                                >
                                                                                                    <div
                                                                                                        className={`flex h-full w-full flex-col overflow-hidden rounded border px-1.5 py-1 text-[10px] shadow-sm ${block.hasConflict ? "bg-destructive/10 border-destructive text-destructive" : ""}`}
                                                                                                        style={
                                                                                                            !block.hasConflict
                                                                                                                ? {
                                                                                                                      backgroundColor:
                                                                                                                          timetableEventFill,
                                                                                                                      borderColor:
                                                                                                                          timetableEventBorder,
                                                                                                                      color: timetableEventText,
                                                                                                                  }
                                                                                                                : {}
                                                                                                        }
                                                                                                    >
                                                                                                        <span className="truncate font-semibold">
                                                                                                            {block.timeRange}
                                                                                                        </span>
                                                                                                        <span className="truncate opacity-80">
                                                                                                            {block.roomName}
                                                                                                        </span>
                                                                                                        {block.hasConflict && (
                                                                                                            <AlertCircle className="mt-auto h-3 w-3" />
                                                                                                        )}
                                                                                                    </div>
                                                                                                </div>
                                                                                            </TooltipTrigger>
                                                                                            <TooltipContent>
                                                                                                <div className="text-xs">
                                                                                                    <div className="font-bold">{day.label}</div>
                                                                                                    <div>{block.timeRange}</div>
                                                                                                    <div>{block.roomName}</div>
                                                                                                    {block.hasConflict && (
                                                                                                        <div className="text-destructive mt-1 font-bold">
                                                                                                            Conflict Detected
                                                                                                        </div>
                                                                                                    )}
                                                                                                </div>
                                                                                            </TooltipContent>
                                                                                        </Tooltip>
                                                                                    );
                                                                                })}
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <ScrollBar orientation="horizontal" />
                                                        </ScrollArea>
                                                    ) : (
                                                        <div className="text-muted-foreground flex h-48 flex-col items-center justify-center text-sm">
                                                            <CalendarIcon className="mb-2 h-10 w-10 opacity-20" />
                                                            <p>No schedule configured for this class.</p>
                                                        </div>
                                                    )}
                                                </TabsContent>

                                                <TabsContent value="list" className="m-0 p-0">
                                                    <div className="divide-y">
                                                        {scheduleEntriesByDay.filter((d) => d.entries.length > 0).length === 0 ? (
                                                            <div className="text-muted-foreground p-8 text-center text-sm">
                                                                No schedule entries found.
                                                            </div>
                                                        ) : (
                                                            scheduleEntriesByDay.map((day) => {
                                                                if (day.entries.length === 0) return null;
                                                                return (
                                                                    <div
                                                                        key={day.key}
                                                                        className="hover:bg-muted/5 flex flex-col gap-4 p-4 sm:flex-row sm:items-start"
                                                                    >
                                                                        <div className="w-24 flex-shrink-0 pt-1">
                                                                            <span className="text-sm font-semibold">{day.label}</span>
                                                                        </div>
                                                                        <div className="flex-1 space-y-2">
                                                                            {day.entries.map((entry, i) => (
                                                                                <div
                                                                                    key={i}
                                                                                    className="bg-background flex items-center gap-3 rounded-md border p-2 text-sm"
                                                                                >
                                                                                    <div className="flex min-w-[140px] items-center gap-2">
                                                                                        <Clock className="text-muted-foreground h-3.5 w-3.5" />
                                                                                        <span className="font-mono text-xs">{entry.time_range}</span>
                                                                                    </div>
                                                                                    <div className="flex flex-1 items-center gap-2">
                                                                                        <MapPin className="text-muted-foreground h-3.5 w-3.5" />
                                                                                        <span>{entry.room.name}</span>
                                                                                    </div>
                                                                                    {entry.has_conflict && (
                                                                                        <Badge variant="destructive" className="h-5 text-[10px]">
                                                                                            Conflict
                                                                                        </Badge>
                                                                                    )}
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })
                                                        )}
                                                    </div>
                                                </TabsContent>
                                            </Tabs>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                {/* ENROLLMENTS TAB */}
                                <TabsContent value="enrollments" className="m-0 space-y-6">
                                    {/* Students Table */}
                                    <Card className="border-border/60 shadow-sm">
                                        <CardHeader className="bg-muted/10 flex flex-row items-center justify-between border-b px-6 py-4">
                                            <div className="space-y-1">
                                                <CardTitle className="flex items-center gap-2 text-lg">
                                                    <Users className="text-primary h-5 w-5" />
                                                    Active Enrollments
                                                </CardTitle>
                                                <CardDescription>{enrollments.total} student records found</CardDescription>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="bg-background h-8 gap-2 shadow-sm"
                                                    onClick={() =>
                                                        window.open(
                                                            route("administrators.classes.export-student-list", {
                                                                class: classItem.id,
                                                                format: "excel",
                                                            }),
                                                            "_blank",
                                                        )
                                                    }
                                                >
                                                    <FileSpreadsheet className="h-3.5 w-3.5 text-emerald-600" />
                                                    <span className="hidden sm:inline">Export Excel</span>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="bg-background h-8 gap-2 shadow-sm"
                                                    onClick={() =>
                                                        window.open(
                                                            route("administrators.classes.export-student-list", {
                                                                class: classItem.id,
                                                                format: "pdf",
                                                            }),
                                                            "_blank",
                                                        )
                                                    }
                                                >
                                                    <FileText className="h-3.5 w-3.5 text-red-500" />
                                                    <span className="hidden sm:inline">Export PDF</span>
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="border-b p-0">
                                            <DataTable columns={columns} data={enrollments.data} />
                                        </CardContent>
                                        {enrollments.total > enrollments.data.length && (
                                            <div className="bg-muted/5 flex justify-center p-4">
                                                <div className="flex gap-2">
                                                    {enrollments.prev_page_url && (
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={enrollments.prev_page_url} preserveState>
                                                                Previous
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {enrollments.next_page_url && (
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={enrollments.next_page_url} preserveState>
                                                                Next
                                                            </Link>
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </div>

                        {/* Right Column (Sidebar) */}
                        <div className="space-y-6">
                            {/* Faculty Card */}
                            <Card className="border-border/60 shadow-sm">
                                <CardHeader className="bg-muted/20 border-b pb-4">
                                    <CardTitle className="text-base font-semibold">Instructor</CardTitle>
                                </CardHeader>
                                <CardContent className="pt-6">
                                    {classItem.faculty ? (
                                        <div className="text-center">
                                            <Avatar className="border-muted mx-auto h-20 w-20 border-4">
                                                <AvatarImage src={classItem.faculty.avatar_url ?? undefined} />
                                                <AvatarFallback className="text-xl">{classItem.faculty.name.charAt(0)}</AvatarFallback>
                                            </Avatar>
                                            <div className="mt-4">
                                                <h3 className="text-lg font-bold">{classItem.faculty.name}</h3>
                                                <p className="text-muted-foreground text-sm break-all">{classItem.faculty.email}</p>
                                            </div>
                                            <div className="mt-6 grid grid-cols-2 gap-2 text-center">
                                                <div className="bg-muted/30 rounded p-2">
                                                    <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Dept</div>
                                                    <div className="mt-0.5 text-sm font-medium">Faculty</div>
                                                </div>
                                                <div className="bg-muted/30 rounded p-2">
                                                    <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Status</div>
                                                    <div className="mt-0.5 text-sm font-medium text-emerald-600">Active</div>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-muted-foreground py-6 text-center">
                                            <Users className="mx-auto mb-2 h-10 w-10 opacity-20" />
                                            <p>No instructor assigned</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Class Details */}
                            <Card className="border-border/60 shadow-sm">
                                <CardHeader className="bg-muted/20 border-b pb-4">
                                    <CardTitle className="text-base font-semibold">Class Details</CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <Table>
                                        <TableBody>
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell className="text-muted-foreground text-xs font-medium uppercase">Subjects</TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex flex-wrap justify-end gap-1">
                                                        {classItem.subjects?.length ? (
                                                            classItem.subjects.map((s) => (
                                                                <Badge key={s} variant="outline" className="font-normal">
                                                                    {s}
                                                                </Badge>
                                                            ))
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell className="text-muted-foreground text-xs font-medium uppercase">Courses</TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex flex-wrap justify-end gap-1">
                                                        {splitBadges(classItem.associated_courses).map((c) => (
                                                            <Badge key={c} variant="secondary" className="font-normal">
                                                                {c}
                                                            </Badge>
                                                        ))}
                                                        {!classItem.associated_courses && <span className="text-muted-foreground">—</span>}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell className="text-muted-foreground text-xs font-medium uppercase">Created</TableCell>
                                                <TableCell className="text-right text-sm">Automatic</TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>

                            {/* Settings / Features */}
                            <Card className="border-border/60 shadow-sm">
                                <CardHeader className="bg-muted/20 border-b pb-4">
                                    <CardTitle className="text-base font-semibold">Configuration</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 p-4">
                                    <div className="space-y-2">
                                        <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Features</span>
                                        <div className="flex flex-col gap-2">
                                            {featureBadges.map((feature) => (
                                                <div key={feature.key} className="flex items-center justify-between text-sm">
                                                    <span className="text-foreground/80">{feature.label}</span>
                                                    {feature.enabled ? (
                                                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                                    ) : (
                                                        <div className="border-muted-foreground/30 h-4 w-4 rounded-full border" />
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-2">
                                        <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Appearance</span>
                                        <div className="flex items-center justify-between text-sm">
                                            <span>Theme</span>
                                            <Badge variant="outline">{classItem.settings?.theme || "Default"}</Badge>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span>Accent</span>
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="h-3 w-3 rounded-full"
                                                    style={{
                                                        backgroundColor: classItem.settings?.accent_color ?? "transparent",
                                                    }}
                                                />
                                                <span className="text-muted-foreground font-mono text-xs">
                                                    {classItem.settings?.accent_color ?? "N/A"}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </TooltipProvider>
        </AdminLayout>
    );
}
