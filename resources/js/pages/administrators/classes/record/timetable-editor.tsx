import { Button } from "@/components/ui/button";
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { update } from "@/routes/administrators/classes";
import type { DragEndEvent, DragStartEvent } from "@dnd-kit/core";
import { DndContext, DragOverlay, KeyboardSensor, MouseSensor, TouchSensor, useDraggable, useDroppable, useSensor, useSensors } from "@dnd-kit/core";
import { snapCenterToCursor } from "@dnd-kit/modifiers";
import { useForm } from "@inertiajs/react";
import { format, parse, set } from "date-fns";
import { AlertCircle, CheckCircle2, Pencil, Plus, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { toast } from "sonner";
import { parseTimeToMinutes, scheduleDayOrder } from "./schedule-utils";
import type { ClassShowData, RawScheduleEntry, RoomOption, RoomScheduleEntry } from "./types";

// Grid Constants
const START_HOUR = 7; // 7 AM
const END_HOUR = 21; // 9 PM
const PIXELS_PER_HOUR = 60;
const PIXELS_PER_MINUTE = PIXELS_PER_HOUR / 60;
const SNAP_MINUTES = 15;

function minutesToTime(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
}

function getGridPosition(startTime: string, endTime: string): { top: number; height: number } {
    const startMinutes = parseTimeToMinutes(startTime);
    const endMinutes = parseTimeToMinutes(endTime);

    const gridStartMinutes = START_HOUR * 60;

    const top = (startMinutes - gridStartMinutes) * PIXELS_PER_MINUTE;
    const height = (endMinutes - startMinutes) * PIXELS_PER_MINUTE;

    return { top, height };
}

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

    const online = entry.delivery_mode === "online";
    const { top, height } = getGridPosition(entry.start_time, entry.end_time);
    const roomName = online ? "Online" : rooms.find((r) => r.id === entry.room_id)?.label || "Room not assigned";

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
                newStart = Math.max(newStart, START_HOUR * 60);
                setPreviewTime(`${minutesToTime(newStart)} - ${minutesToTime(endMinutes)}`);
            } else {
                newEnd = Math.max(endMinutes + snappedDelta, startMinutes + SNAP_MINUTES);
                newEnd = Math.min(newEnd, END_HOUR * 60);
                setPreviewTime(`${minutesToTime(startMinutes)} - ${minutesToTime(newEnd)}`);
            }
        };

        const handleMouseUp = (upEvent: MouseEvent) => {
            document.removeEventListener("mousemove", handleMouseMove);
            document.removeEventListener("mouseup", handleMouseUp);
            setIsResizing(null);
            setPreviewTime(null);

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
                        aria-label="Remove schedule session"
                        className="text-muted-foreground hover:bg-destructive hover:text-destructive-foreground focus-visible:ring-ring rounded p-1 focus-visible:ring-2"
                    >
                        <Trash2 className="h-3 w-3" />
                    </button>
                )}
            </div>
            <div className="truncate font-medium opacity-90">{roomName}</div>
            {hasConflict && (
                <div className="text-destructive mt-auto flex items-center gap-1 text-xs font-bold">
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
                    {!online && (
                        <ContextMenuSub>
                            <ContextMenuSubTrigger>Move to room</ContextMenuSubTrigger>
                            <ContextMenuSubContent className="max-h-64 w-48 overflow-y-auto">
                                {rooms.map((room) => (
                                    <ContextMenuItem key={room.id} onSelect={() => onChangeRoom?.(entry.temp_id || String(entry.id), room.id)}>
                                        {room.label}
                                        {room.id === entry.room_id && <CheckCircle2 className="ml-auto h-4 w-4" />}
                                    </ContextMenuItem>
                                ))}
                            </ContextMenuSubContent>
                        </ContextMenuSub>
                    )}
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

    const occupiedSlots = useMemo(() => {
        const slots: { top: number; height: number; title: string }[] = [];
        const roomIds = [...new Set(entries.filter((entry) => entry.delivery_mode !== "online").map((entry) => entry.room_id))];
        roomIds.forEach((roomId) => {
            if (roomId === null) return;
            const roomScheds = roomSchedules[roomId] || [];
            roomScheds.forEach((sched) => {
                if (sched.day_of_week === day) {
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
                    className="border-destructive/25 bg-destructive/10 text-destructive absolute right-1 left-1 z-0 flex items-center justify-center overflow-hidden rounded border text-xs font-medium"
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

export function TimetableEditor({
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
    const online = classItem.delivery_mode === "online";
    const { data, setData, transform, patch, processing, errors } = useForm({
        schedules: initialSchedules.map((s, i) => ({
            ...s,
            delivery_mode: s.delivery_mode ?? classItem.delivery_mode,
            room_id: (s.delivery_mode ?? classItem.delivery_mode) === "online" ? null : s.room_id,
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

    const handleDragStart = (event: DragStartEvent) => {
        setActiveId(String(event.active.id));
    };

    const handleDragEnd = (event: DragEndEvent) => {
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
            delivery_mode: classItem.delivery_mode,
            room_id: online ? null : (rooms[0]?.id ?? null),
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
            data.schedules.map((s) =>
                s.temp_id === updated.temp_id
                    ? ({ ...updated, room_id: updated.delivery_mode === "online" ? null : updated.room_id, temp_id: updated.temp_id! } as typeof s)
                    : s,
            ),
        );
        setEditingEntry(null);
    };

    const handleChangeRoom = (id: string, roomId: number) => {
        setData(
            "schedules",
            data.schedules.map((s) => ((s.temp_id || String(s.id)) === id && s.delivery_mode !== "online" ? { ...s, room_id: roomId } : s)),
        );
    };

    const handleChangeTime = (id: string, start: string, end: string) => {
        setData(
            "schedules",
            data.schedules.map((s) => ((s.temp_id || String(s.id)) === id ? { ...s, start_time: start, end_time: end } : s)),
        );
    };

    const handleSubmit = () => {
        transform((payload) => ({
            ...payload,
            schedules: payload.schedules.map((entry) => ({ ...entry, room_id: entry.delivery_mode === "online" ? null : entry.room_id })),
        }));
        patch(update.url(classItem.id), {
            onSuccess: () => {
                toast.success("Schedule updated successfully");
                onOpenChange(false);
            },
            onError: () => {
                toast.error("Failed to update. Check conflicts.");
            },
        });
    };

    const availableRoomSchedules = useMemo(
        () =>
            Object.fromEntries(
                Object.entries(roomSchedules).map(([roomId, schedules]) => [
                    roomId,
                    schedules.filter((schedule) => schedule.class_id !== classItem.id),
                ]),
            ),
        [roomSchedules, classItem.id],
    );

    const activeEntry = useMemo(() => data.schedules.find((s) => (s.temp_id || String(s.id)) === activeId), [activeId, data.schedules]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex h-[90dvh] w-[calc(100vw-2rem)] max-w-6xl flex-col gap-0 overflow-hidden p-0 sm:max-w-6xl">
                <DialogHeader className="border-b p-4 pr-12 text-left sm:p-6 sm:pr-12">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <DialogTitle>Manage weekly schedule</DialogTitle>
                            <DialogDescription>
                                Choose face to face or online for each meeting. You can also drag and resize its time in the timetable.
                            </DialogDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="text-muted-foreground mr-4 hidden items-center gap-2 text-xs lg:flex">
                                <div className="flex items-center gap-1">
                                    <div className="bg-primary/20 border-primary/30 h-3 w-3 rounded border" />
                                    <span>Scheduled</span>
                                </div>
                                {
                                    <div className="flex items-center gap-1">
                                        <div className="border-destructive/25 bg-destructive/10 h-3 w-3 rounded border" />
                                        <span>Room occupied</span>
                                    </div>
                                }
                            </div>
                            <Button variant="outline" className="h-9 px-3" onClick={handleAdd}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add session
                            </Button>
                            <Button className="h-9 px-3" onClick={handleSubmit} disabled={processing}>
                                {processing ? "Saving..." : "Save schedule"}
                            </Button>
                        </div>
                    </div>
                </DialogHeader>

                {Object.keys(errors).length > 0 && (
                    <div role="alert" className="border-destructive/30 bg-destructive/5 text-destructive mx-6 rounded-lg border p-4 text-sm">
                        {Object.values(errors).map((message, index) => (
                            <p key={index}>{message}</p>
                        ))}
                    </div>
                )}
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <ScrollArea className="min-h-0 w-full flex-1">
                        <section aria-label="Scheduled sessions" className="space-y-3 border-b p-4 sm:p-6">
                            <h3 className="text-sm font-medium">Scheduled sessions</h3>
                            {data.schedules.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No sessions yet. Add a session to set its day, time, and meeting type.
                                </p>
                            )}
                            <div className="grid gap-3 md:grid-cols-2">
                                {data.schedules.map((entry) => (
                                    <div key={entry.temp_id} className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                                        <div className="min-w-0 space-y-1">
                                            <p className="font-medium">
                                                {entry.day_of_week} · {entry.start_time}–{entry.end_time}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {entry.delivery_mode === "online"
                                                    ? "Online"
                                                    : `Face to face · ${rooms.find((room) => room.id === entry.room_id)?.label || "Room not assigned"}`}
                                            </p>
                                            {conflicts.has(entry.temp_id) && <p className="text-destructive">Schedule conflict</p>}
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9"
                                                aria-label={`Edit ${entry.day_of_week} session at ${entry.start_time}`}
                                                onClick={() => setEditingEntry(entry)}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive size-9"
                                                aria-label={`Remove ${entry.day_of_week} session at ${entry.start_time}`}
                                                onClick={() => handleRemove(entry.temp_id)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                        <div className="hidden min-w-[1200px] p-4 sm:flex">
                            {/* Time Labels */}
                            <div className="text-muted-foreground mr-2 w-16 shrink-0 pt-8 text-right text-xs">
                                {Array.from({ length: END_HOUR - START_HOUR + 1 }).map((_, i) => {
                                    const hour = START_HOUR + i;
                                    const label = format(set(new Date(), { hours: hour, minutes: 0 }), "h a");
                                    return (
                                        <div key={hour} className="pr-2" style={{ height: `${PIXELS_PER_HOUR}px` }}>
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
                                                roomSchedules={availableRoomSchedules}
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
                                <DialogTitle>Edit session</DialogTitle>
                                <DialogDescription>Modify the details for this class session.</DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4 py-4">
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="day" className="text-right">
                                        Day
                                    </Label>
                                    <Select
                                        value={editingEntry.day_of_week}
                                        onValueChange={(val) => setEditingEntry({ ...editingEntry, day_of_week: val })}
                                    >
                                        <SelectTrigger id="day" className="col-span-3">
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
                                    <Label htmlFor="record-meeting-delivery" className="text-right">
                                        Meeting type
                                    </Label>
                                    <Select
                                        value={editingEntry.delivery_mode ?? classItem.delivery_mode}
                                        onValueChange={(value) =>
                                            setEditingEntry({
                                                ...editingEntry,
                                                delivery_mode: value as "face_to_face" | "online",
                                                room_id: value === "online" ? null : editingEntry.room_id,
                                            })
                                        }
                                    >
                                        <SelectTrigger id="record-meeting-delivery" className="col-span-3">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="face_to_face">Face to face</SelectItem>
                                            <SelectItem value="online">Online</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                {editingEntry.delivery_mode === "online" ? (
                                    <p className="text-muted-foreground text-sm">No room reserved for this meeting.</p>
                                ) : (
                                    <div className="grid grid-cols-4 items-center gap-4">
                                        <Label htmlFor="room" className="text-right">
                                            Room
                                        </Label>
                                        <Select
                                            value={editingEntry.room_id ? String(editingEntry.room_id) : "none"}
                                            onValueChange={(val) =>
                                                setEditingEntry({
                                                    ...editingEntry,
                                                    room_id: val === "none" ? null : parseInt(val),
                                                })
                                            }
                                        >
                                            <SelectTrigger id="room" className="col-span-3">
                                                <SelectValue placeholder="Select room" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Assign later</SelectItem>
                                                {rooms.map((room) => (
                                                    <SelectItem key={room.id} value={String(room.id)}>
                                                        {room.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
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
                                <Button
                                    type="button"
                                    disabled={!editingEntry.start_time || !editingEntry.end_time || editingEntry.end_time <= editingEntry.start_time}
                                    onClick={() => handleUpdateEntry(editingEntry)}
                                >
                                    Apply session
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </DialogContent>
        </Dialog>
    );
}
