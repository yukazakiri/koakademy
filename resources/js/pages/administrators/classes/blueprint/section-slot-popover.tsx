import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { AlertTriangle, Clock, Trash2 } from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";
import type { CrudComponentType, CrudRoomOption, CrudScheduleBlock } from "../crud/types";
import { canonicalDeliveryMode, isOnlineDeliveryMode, type DeliveryMode, type ValueOption } from "../lib/class-defaults";

const QUICK_DURATIONS = [
    { label: "1h", minutes: 60 },
    { label: "1.5h", minutes: 90 },
    { label: "2h", minutes: 120 },
    { label: "3h", minutes: 180 },
] as const;

function parseTimeToMinutes(time: string): number | null {
    const parts = time.trim().split(":");
    if (parts.length < 2) return null;
    const h = Number(parts[0]);
    const m = Number(parts[1]);
    if (!Number.isFinite(h) || !Number.isFinite(m)) return null;
    return h * 60 + m;
}

function formatMinutesToTime(totalMinutes: number): string {
    const clamped = Math.max(0, Math.min(23 * 60 + 59, totalMinutes));
    const h = Math.floor(clamped / 60);
    const m = clamped % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

export type SectionSlotPopoverProps = {
    slot?: CrudScheduleBlock;
    defaultRoomId: number | null;
    defaultDeliveryMode: DeliveryMode;
    defaultComponentType?: CrudComponentType;
    sectionCap: number;
    rooms: CrudRoomOption[];
    days: ValueOption[];
    allowSunday?: boolean;
    title?: string;
    trigger: ReactNode;
    onSave: (slot: CrudScheduleBlock) => void;
    onDelete?: () => void;
};

export function SectionSlotPopover({
    slot,
    defaultRoomId,
    defaultDeliveryMode,
    defaultComponentType = "lecture",
    sectionCap,
    rooms,
    days,
    allowSunday = false,
    title,
    trigger,
    onSave,
    onDelete,
}: SectionSlotPopoverProps): ReactNode {
    const [open, setOpen] = useState(false);

    const buildInitialDraft = (): CrudScheduleBlock => {
        if (slot) {
            const mode = canonicalDeliveryMode(slot.delivery_mode);
            return {
                ...slot,
                delivery_mode: mode,
                room_id: isOnlineDeliveryMode(mode) ? null : (slot.room_id ?? null),
                component_type: slot.component_type === "lab" ? "lab" : "lecture",
            };
        }

        const mode = canonicalDeliveryMode(defaultDeliveryMode);
        return {
            day_of_week: "Monday",
            start_time: "08:00",
            end_time: defaultComponentType === "lab" ? "10:00" : "09:30",
            room_id: isOnlineDeliveryMode(mode) ? null : defaultRoomId,
            delivery_mode: mode,
            component_type: defaultComponentType,
        };
    };

    const [draft, setDraft] = useState<CrudScheduleBlock>(buildInitialDraft);

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            setDraft(buildInitialDraft());
        }
        setOpen(nextOpen);
    };

    const availableDays = useMemo(() => {
        return days.filter((day) => allowSunday || day.value !== "Sunday" || draft.day_of_week === "Sunday");
    }, [allowSunday, days, draft.day_of_week]);

    const startMins = parseTimeToMinutes(draft.start_time);
    const endMins = parseTimeToMinutes(draft.end_time);
    const durationMinutes = startMins !== null && endMins !== null ? endMins - startMins : 0;
    const isTimeValid = startMins !== null && endMins !== null && endMins > startMins;
    const durationHoursLabel = isTimeValid ? `${Number((durationMinutes / 60).toFixed(2))}h` : "Invalid time";

    const isOnline = isOnlineDeliveryMode(draft.delivery_mode);
    const selectedRoom = useMemo(() => {
        if (isOnline || draft.room_id === null) return null;
        return rooms.find((room) => Number(room.id) === Number(draft.room_id)) ?? null;
    }, [draft.room_id, isOnline, rooms]);

    const roomCapacityOverflow =
        selectedRoom && typeof selectedRoom.capacity === "number" && selectedRoom.capacity > 0 && sectionCap > selectedRoom.capacity
            ? sectionCap - selectedRoom.capacity
            : 0;

    const applyDuration = (minutes: number) => {
        const base = parseTimeToMinutes(draft.start_time);
        if (base === null || base + minutes > 23 * 60 + 59) return;
        setDraft((prev) => ({
            ...prev,
            end_time: formatMinutesToTime(base + minutes),
        }));
    };

    const handleSave = () => {
        if (!isTimeValid) return;
        const normalizedMode = canonicalDeliveryMode(draft.delivery_mode);
        onSave({
            ...draft,
            delivery_mode: normalizedMode,
            room_id: isOnlineDeliveryMode(normalizedMode) ? null : draft.room_id,
        });
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>{trigger}</PopoverTrigger>
            <PopoverContent align="start" sideOffset={6} className="w-80 space-y-3.5 p-4">
                <div className="flex items-center justify-between gap-2 border-b pb-2.5">
                    <div className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                        <Clock className="size-3.5 text-primary" aria-hidden="true" />
                        <span>{title ?? (slot ? "Edit schedule slot" : "Add schedule slot")}</span>
                    </div>
                    <span
                        className={cn(
                            "rounded-md px-1.5 py-0.5 font-mono text-[11px] font-semibold tabular-nums",
                            isTimeValid ? "bg-muted text-foreground" : "bg-destructive/15 text-destructive",
                        )}
                    >
                        {durationHoursLabel}
                    </span>
                </div>

                {/* Component type */}
                <div className="space-y-1.5">
                    <Label className="text-xs">Session type</Label>
                    <div className="grid grid-cols-2 gap-1 rounded-lg border bg-muted/40 p-1" role="group" aria-label="Session component type">
                        {(
                            [
                                { value: "lecture", label: "Lecture" },
                                { value: "lab", label: "Lab" },
                            ] as const
                        ).map((opt) => {
                            const active = draft.component_type === opt.value;
                            return (
                                <button
                                    key={opt.value}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => setDraft((prev) => ({ ...prev, component_type: opt.value }))}
                                    className={cn(
                                        "rounded-md px-2.5 py-1 text-xs font-semibold transition-colors",
                                        active
                                            ? opt.value === "lab"
                                                ? "bg-emerald-600 text-white shadow-2xs"
                                                : "bg-primary text-primary-foreground shadow-2xs"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    {opt.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Day & Time */}
                <div className="space-y-2">
                    <div className="space-y-1">
                        <Label className="text-xs">Day of week</Label>
                        <Select value={draft.day_of_week} onValueChange={(day_of_week) => setDraft((prev) => ({ ...prev, day_of_week }))}>
                            <SelectTrigger aria-label="Day of week" className="h-8 w-full text-xs">
                                <SelectValue placeholder="Choose day" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableDays.map((day) => (
                                    <SelectItem key={day.value} value={day.value}>
                                        {day.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                            <Label className="text-xs">Start time</Label>
                            <Input
                                type="time"
                                aria-label="Start time"
                                value={draft.start_time}
                                onChange={(event) => setDraft((prev) => ({ ...prev, start_time: event.target.value }))}
                                className="h-8 text-xs tabular-nums"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">End time</Label>
                            <Input
                                type="time"
                                aria-label="End time"
                                value={draft.end_time}
                                onChange={(event) => setDraft((prev) => ({ ...prev, end_time: event.target.value }))}
                                className="h-8 text-xs tabular-nums"
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-1">
                        <span className="text-[11px] text-muted-foreground">Quick length:</span>
                        <div className="flex items-center gap-1">
                            {QUICK_DURATIONS.map((preset) => (
                                <button
                                    key={preset.label}
                                    type="button"
                                    disabled={startMins === null || startMins + preset.minutes > 23 * 60 + 59}
                                    onClick={() => applyDuration(preset.minutes)}
                                    className="rounded border bg-muted/40 px-1.5 py-0.5 font-mono text-[10px] font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    {preset.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {!isTimeValid ? <p className="text-destructive text-[11px] font-medium">End time must be after start time.</p> : null}
                </div>

                {/* Delivery Mode */}
                <div className="space-y-1">
                    <Label className="text-xs">Delivery mode</Label>
                    <Select
                        value={canonicalDeliveryMode(draft.delivery_mode)}
                        onValueChange={(value) => {
                            const mode = canonicalDeliveryMode(value);
                            setDraft((prev) => ({
                                ...prev,
                                delivery_mode: mode,
                                room_id: isOnlineDeliveryMode(mode) ? null : (prev.room_id ?? defaultRoomId),
                            }));
                        }}
                    >
                        <SelectTrigger aria-label="Delivery mode" className="h-8 w-full text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="face_to_face">Face to face</SelectItem>
                            <SelectItem value="online_synchronous">Online (Sync)</SelectItem>
                            <SelectItem value="online">Online (Async)</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Room */}
                <div className="space-y-1">
                    <Label className="text-xs">Room</Label>
                    {isOnline ? (
                        <div className="rounded-md border border-dashed bg-muted/30 px-2.5 py-1.5 text-xs text-muted-foreground">
                            No physical room needed for online sessions.
                        </div>
                    ) : (
                        <>
                            <Select
                                value={draft.room_id !== null ? String(draft.room_id) : "none"}
                                onValueChange={(value) => setDraft((prev) => ({ ...prev, room_id: value === "none" ? null : Number(value) }))}
                            >
                                <SelectTrigger aria-label="Room" className="h-8 w-full text-xs">
                                    <SelectValue placeholder="Room unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Unassigned (TBA)</SelectItem>
                                    {rooms.map((room) => (
                                        <SelectItem key={room.id} value={String(room.id)}>
                                            {room.label}
                                            {room.capacity ? ` · ${room.capacity} seats` : ""}
                                            {room.type ? ` · ${room.type}` : ""}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {roomCapacityOverflow > 0 ? (
                                <div className="flex items-start gap-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 p-2 text-[11px] text-amber-800 dark:text-amber-200">
                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    <span>
                                        {selectedRoom?.label} holds {selectedRoom?.capacity} seats, which is {roomCapacityOverflow} below this
                                        section’s {sectionCap}-seat capacity.
                                    </span>
                                </div>
                            ) : null}
                        </>
                    )}
                </div>

                {/* Footer Actions */}
                <div className="flex items-center justify-between gap-2 border-t pt-2.5">
                    {onDelete ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                onDelete();
                                setOpen(false);
                            }}
                            className="h-8 gap-1 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                            <Trash2 className="size-3.5" aria-hidden="true" />
                            Remove
                        </Button>
                    ) : (
                        <span />
                    )}

                    <div className="flex items-center gap-1.5">
                        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(false)} className="h-8 px-2.5 text-xs">
                            Cancel
                        </Button>
                        <Button type="button" size="sm" disabled={!isTimeValid} onClick={handleSave} className="h-8 px-3 text-xs">
                            {slot ? "Update slot" : "Add slot"}
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
