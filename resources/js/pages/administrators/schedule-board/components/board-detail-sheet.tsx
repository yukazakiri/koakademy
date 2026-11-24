import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { show as showClass } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    Loader2,
    MapPin,
    Monitor,
    Sparkles,
    Trash2,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
    canonicalDeliveryMode,
    isOnlineDeliveryMode,
    type DeliveryMode,
} from "../../classes/lib/class-defaults";
import type { RoomOption, ScheduleConflict, SuggestedRoom, SuggestedSlot } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../../scheduling/hooks/use-schedule-studio";
import { minutesToApiTime, parseTimeToMinutes } from "../../scheduling/lib/time-geometry";
import type { BoardSessionItem, DirectoryEntity } from "../lib/board-entities";

export type DetailSheetMode =
    | { kind: "session"; sessionId: number; classId: number }
    | { kind: "fix-overlaps"; index: number }
    | null;

type BoardDetailSheetProps = {
    mode: DetailSheetMode;
    onClose: () => void;
    onModeChange: (mode: DetailSheetMode) => void;
    entity: DirectoryEntity | null;
    studio: ScheduleStudioController;
    rooms: RoomOption[];
    days: string[];
};

function plainConflictReason(conflict: ScheduleConflict): string {
    const kinds = conflict.conflict_types ?? [conflict.conflict_type];
    const parts: string[] = [];
    if (kinds.includes("room")) {
        parts.push(`shares ${conflict.class_1.room ?? "the same room"}`);
    }
    if (kinds.includes("faculty")) {
        parts.push(`shares instructor ${conflict.class_1.faculty ?? ""}`.trim());
    }
    if (kinds.includes("section")) {
        parts.push(`overlaps the same student section`);
    }
    return parts.join(" and ");
}

export default function BoardDetailSheet({
    mode,
    onClose,
    onModeChange,
    entity,
    studio,
    rooms,
    days,
}: BoardDetailSheetProps) {
    const [applyingKey, setApplyingKey] = useState<string | null>(null);
    const [savingEdit, setSavingEdit] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    // Resolve active session either directly (session mode) or from the focused overlap (fix-overlaps mode)
    const activeOverlap: ScheduleConflict | null = useMemo(() => {
        if (mode?.kind !== "fix-overlaps" || !entity) return null;
        return entity.conflicts[mode.index] ?? entity.conflicts[0] ?? null;
    }, [mode, entity]);

    const activeItem: BoardSessionItem | null = useMemo(() => {
        if (!mode) return null;
        if (mode.kind === "session") {
            const cls = studio.classes.find((c) => c.id === mode.classId);
            const session = cls?.schedules.find((s) => s.id === mode.sessionId);
            if (!cls || !session) return null;
            const startMin = parseTimeToMinutes(session.start_time);
            const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
            return {
                key: `${cls.id}:${session.id}`,
                classItem: cls,
                session,
                startMin,
                endMin,
                conflicts: session.id !== undefined ? (studio.conflictsByScheduleId.get(session.id) ?? []) : [],
            };
        }
        if (activeOverlap && entity) {
            // Prefer the side of the overlap that belongs to the currently focused entity
            const match1 = entity.sessions.find((s) => s.session.id === activeOverlap.schedule_1_id);
            if (match1) return match1;
            const match2 = entity.sessions.find((s) => s.session.id === activeOverlap.schedule_2_id);
            if (match2) return match2;
        }
        return null;
    }, [mode, studio.classes, studio.conflictsByScheduleId, activeOverlap, entity]);

    // Editable fields synced when activeItem changes
    const [day, setDay] = useState("Monday");
    const [startTime, setStartTime] = useState("08:00");
    const [endTime, setEndTime] = useState("09:30");
    const [deliveryMode, setDeliveryMode] = useState<DeliveryMode>("face_to_face");
    const [roomId, setRoomId] = useState<number | null>(null);

    useEffect(() => {
        if (!activeItem) return;
        setDay(activeItem.session.day_of_week);
        setStartTime(minutesToApiTime(activeItem.startMin));
        setEndTime(minutesToApiTime(activeItem.endMin));
        setDeliveryMode(canonicalDeliveryMode(activeItem.session.delivery_mode ?? activeItem.classItem.delivery_mode));
        setRoomId(activeItem.session.room_id ?? null);
        setConfirmDelete(false);
    }, [activeItem?.session.id, activeItem?.session.day_of_week, activeItem?.startMin, activeItem?.endMin, activeItem?.session.room_id]);

    // Fetch 1-click suggestions whenever activeItem has overlaps
    const activeScheduleId = activeItem?.session.id;
    const hasOverlaps = (activeItem?.conflicts.length ?? 0) > 0;

    useEffect(() => {
        if (activeScheduleId !== undefined && hasOverlaps) {
            void studio.loadSuggestionsForSchedule(activeScheduleId);
        }
    }, [activeScheduleId, hasOverlaps, studio.loadSuggestionsForSchedule]);

    const suggestions = activeScheduleId !== undefined ? studio.suggestionsCache[activeScheduleId] : undefined;
    const loadingSuggestions = activeScheduleId !== undefined && studio.loadingSuggestionsFor === activeScheduleId;

    const online = isOnlineDeliveryMode(deliveryMode);
    const validTimes = Boolean(startTime && endTime && endTime > startTime);

    const handleSaveChanges = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!activeItem || !validTimes) return;
        setSavingEdit(true);
        try {
            await studio.updateScheduleBlockDirect(
                activeItem.classItem,
                activeItem.session,
                {
                    day_of_week: day,
                    start_time: startTime,
                    end_time: endTime,
                    delivery_mode: deliveryMode,
                    room_id: online ? null : roomId,
                },
                `Updated ${activeItem.classItem.subject_code}`,
            );
            onClose();
        } finally {
            setSavingEdit(false);
        }
    };

    const applyRoom = async (room: SuggestedRoom) => {
        if (!activeItem) return;
        setApplyingKey(`room:${room.room_id}`);
        try {
            await studio.updateScheduleBlockDirect(
                activeItem.classItem,
                activeItem.session,
                { room_id: room.room_id, delivery_mode: "face_to_face" },
                `Moved ${activeItem.classItem.subject_code} to ${room.name}`,
            );
            studio.setGhostPreview(null);
            if (mode?.kind === "session") onClose();
        } finally {
            setApplyingKey(null);
        }
    };

    const applySlot = async (slot: SuggestedSlot) => {
        if (!activeItem) return;
        setApplyingKey(`slot:${slot.day_of_week}:${slot.start_time}`);
        try {
            await studio.updateScheduleBlockDirect(
                activeItem.classItem,
                activeItem.session,
                {
                    day_of_week: slot.day_of_week,
                    start_time: slot.start_time,
                    end_time: slot.end_time,
                    room_id: slot.room_id,
                },
                `Moved ${activeItem.classItem.subject_code} to ${slot.day_of_week} ${slot.time_range}`,
            );
            studio.setGhostPreview(null);
            if (mode?.kind === "session") onClose();
        } finally {
            setApplyingKey(null);
        }
    };

    const applyOnline = async () => {
        if (!activeItem) return;
        setApplyingKey("online");
        try {
            await studio.updateScheduleBlockDirect(
                activeItem.classItem,
                activeItem.session,
                { delivery_mode: studio.policy.default_online_delivery_mode, room_id: null },
                `Switched ${activeItem.classItem.subject_code} to Online`,
            );
            studio.setGhostPreview(null);
            if (mode?.kind === "session") onClose();
        } finally {
            setApplyingKey(null);
        }
    };

    const applySwap = async (conflict: ScheduleConflict) => {
        if (!conflict.schedule_1_id || !conflict.schedule_2_id) return;
        const c1 = studio.classes.find((c) => c.id === conflict.class_1.id);
        const c2 = studio.classes.find((c) => c.id === conflict.class_2.id);
        const s1 = c1?.schedules.find((s) => s.id === conflict.schedule_1_id);
        const s2 = c2?.schedules.find((s) => s.id === conflict.schedule_2_id);
        if (!c1 || !c2 || !s1?.id || !s2?.id) return;

        setApplyingKey("swap");
        try {
            const before = [
                {
                    schedule_id: s1.id,
                    day_of_week: s1.day_of_week,
                    start_time: minutesToApiTime(parseTimeToMinutes(s1.start_time)),
                    end_time: minutesToApiTime(parseTimeToMinutes(s1.end_time)),
                    room_id: s1.room_id ?? null,
                    delivery_mode: canonicalDeliveryMode(s1.delivery_mode ?? c1.delivery_mode),
                    component_type: s1.component_type ?? "lecture",
                    status: s1.status ?? "published",
                },
                {
                    schedule_id: s2.id,
                    day_of_week: s2.day_of_week,
                    start_time: minutesToApiTime(parseTimeToMinutes(s2.start_time)),
                    end_time: minutesToApiTime(parseTimeToMinutes(s2.end_time)),
                    room_id: s2.room_id ?? null,
                    delivery_mode: canonicalDeliveryMode(s2.delivery_mode ?? c2.delivery_mode),
                    component_type: s2.component_type ?? "lecture",
                    status: s2.status ?? "published",
                },
            ];
            const after = [
                {
                    ...before[0],
                    day_of_week: before[1].day_of_week,
                    start_time: before[1].start_time,
                    end_time: before[1].end_time,
                    room_id: before[1].room_id,
                },
                {
                    ...before[1],
                    day_of_week: before[0].day_of_week,
                    start_time: before[0].start_time,
                    end_time: before[0].end_time,
                    room_id: before[0].room_id,
                },
            ];
            await studio.applyBatchOperations(after, `Swapped ${c1.subject_code} and ${c2.subject_code}`, {
                label: `Swap ${c1.subject_code} ↔ ${c2.subject_code}`,
                before,
            });
        } finally {
            setApplyingKey(null);
        }
    };

    const handleDelete = async () => {
        if (!activeItem?.session.id) return;
        setSavingEdit(true);
        try {
            await studio.deleteScheduleBlock(activeItem.session.id, false);
            onClose();
        } finally {
            setSavingEdit(false);
        }
    };

    const totalEntityOverlaps = entity?.conflicts.length ?? 0;
    const currentOverlapIdx = mode?.kind === "fix-overlaps" ? Math.min(mode.index, Math.max(0, totalEntityOverlaps - 1)) : 0;

    return (
        <Sheet
            open={mode !== null}
            onOpenChange={(open) => {
                if (!open) {
                    studio.setGhostPreview(null);
                    onClose();
                }
            }}
        >
            <SheetContent side="right" className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-[430px]">
                {/* Header */}
                <SheetHeader className="border-border/60 border-b px-5 py-4">
                    {mode?.kind === "fix-overlaps" ? (
                        <>
                            <div className="flex items-center justify-between pr-6">
                                <SheetTitle className="flex items-center gap-2 text-base">
                                    <Sparkles className="text-primary size-4" />
                                    Guided Overlap Fix
                                </SheetTitle>
                                {totalEntityOverlaps > 1 && (
                                    <div className="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            disabled={currentOverlapIdx <= 0}
                                            onClick={() =>
                                                onModeChange({ kind: "fix-overlaps", index: Math.max(0, currentOverlapIdx - 1) })
                                            }
                                            aria-label="Previous overlap"
                                        >
                                            <ArrowLeft className="size-3.5" />
                                        </Button>
                                        <span className="text-muted-foreground px-1 text-xs tabular-nums">
                                            {currentOverlapIdx + 1} / {totalEntityOverlaps}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            disabled={currentOverlapIdx >= totalEntityOverlaps - 1}
                                            onClick={() =>
                                                onModeChange({
                                                    kind: "fix-overlaps",
                                                    index: Math.min(totalEntityOverlaps - 1, currentOverlapIdx + 1),
                                                })
                                            }
                                            aria-label="Next overlap"
                                        >
                                            <ArrowRight className="size-3.5" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <SheetDescription className="text-xs">
                                {entity ? `Resolving overlaps for ${entity.metaDetail}` : "Pick a conflict-free option below."}
                            </SheetDescription>
                        </>
                    ) : activeItem ? (
                        <>
                            <div className="flex items-center gap-2 pr-6">
                                <SheetTitle className="truncate text-base">{activeItem.classItem.subject_code}</SheetTitle>
                                <Badge variant="outline" className="text-[11px]">
                                    Sec {activeItem.classItem.section}
                                </Badge>
                            </div>
                            <SheetDescription className="flex items-center justify-between gap-2 text-xs">
                                <span className="truncate">{activeItem.classItem.subject_title}</span>
                                <Link
                                    href={showClass.url(activeItem.classItem.id)}
                                    className="text-primary inline-flex shrink-0 items-center gap-0.5 font-medium hover:underline"
                                >
                                    Class <ArrowUpRight className="size-3" />
                                </Link>
                            </SheetDescription>
                        </>
                    ) : (
                        <>
                            <SheetTitle>All clear</SheetTitle>
                            <SheetDescription>No remaining overlaps for this selection.</SheetDescription>
                        </>
                    )}
                </SheetHeader>

                {/* Scrollable Body */}
                <div className="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                    {!activeItem ? (
                        <div className="flex flex-col items-center justify-center gap-2.5 py-16 text-center">
                            <span className="flex size-12 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 className="size-6" />
                            </span>
                            <p className="text-sm font-semibold">All overlaps resolved!</p>
                            <p className="text-muted-foreground max-w-[260px] text-xs">
                                This schedule is completely clear. You can close this panel or switch to another section in the directory.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Plain-English Overlap Explainer + 1-Click Fixes */}
                            {activeItem.conflicts.length > 0 && (
                                <div className="space-y-3 rounded-2xl border border-rose-400/40 bg-rose-500/[0.06] p-4">
                                    <div className="space-y-1">
                                        <p className="flex items-center gap-1.5 text-xs font-semibold text-rose-700 dark:text-rose-300">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            Why this overlaps
                                        </p>
                                        {activeItem.conflicts.slice(0, 2).map((c, idx) => {
                                            const other =
                                                c.schedule_1_id === activeItem.session.id ? c.class_2 : c.class_1;
                                            return (
                                                <p key={idx} className="text-xs leading-relaxed text-foreground/85">
                                                    <strong>{activeItem.classItem.subject_code}</strong> {plainConflictReason(c)} with{" "}
                                                    <strong>
                                                        {other.subject_code} (Sec {other.section})
                                                    </strong>{" "}
                                                    on {c.day} ({c.time}).
                                                </p>
                                            );
                                        })}
                                        {activeItem.conflicts.length > 2 && (
                                            <p className="text-muted-foreground text-[11px]">
                                                +{activeItem.conflicts.length - 2} additional overlap(s)
                                            </p>
                                        )}
                                    </div>

                                    {/* Instant 1-Click Suggestions */}
                                    <div className="space-y-2 pt-1">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide">
                                                <Sparkles className="size-3" /> 1-Click Fixes
                                            </span>
                                            {activeOverlap && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-6 gap-1 px-2 text-[11px]"
                                                    disabled={applyingKey !== null}
                                                    onClick={() => void applySwap(activeOverlap)}
                                                >
                                                    <ArrowLeftRight className="size-3" /> Swap pair
                                                </Button>
                                            )}
                                        </div>

                                        {loadingSuggestions ? (
                                            <div className="text-muted-foreground flex items-center gap-2 py-3 text-xs">
                                                <Loader2 className="size-3.5 animate-spin" /> Finding open rooms &amp; times…
                                            </div>
                                        ) : suggestions ? (
                                            <div className="space-y-1.5">
                                                {suggestions.available_rooms.slice(0, 3).map((room) => (
                                                    <div
                                                        key={room.room_id}
                                                        onMouseEnter={() =>
                                                            studio.setGhostPreview({
                                                                scheduleId: activeItem.session.id,
                                                                classItem: activeItem.classItem,
                                                                day_of_week: activeItem.session.day_of_week,
                                                                start_time: activeItem.session.start_time,
                                                                end_time: activeItem.session.end_time,
                                                                room_id: room.room_id,
                                                                room_name: room.name,
                                                                label: `Room: ${room.name}`,
                                                            })
                                                        }
                                                        onMouseLeave={() => studio.setGhostPreview(null)}
                                                        className="bg-background border-border/70 flex items-center justify-between gap-2 rounded-xl border px-3 py-2"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="flex items-center gap-1.5 truncate text-xs font-semibold">
                                                                <MapPin className="size-3 text-emerald-600" />
                                                                Use {room.name}
                                                            </p>
                                                            <p className="text-muted-foreground truncate text-[11px]">{room.reason}</p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="h-7 shrink-0 px-2.5 text-xs"
                                                            disabled={applyingKey !== null}
                                                            onClick={() => void applyRoom(room)}
                                                        >
                                                            {applyingKey === `room:${room.room_id}` ? (
                                                                <Loader2 className="size-3 animate-spin" />
                                                            ) : (
                                                                "Apply"
                                                            )}
                                                        </Button>
                                                    </div>
                                                ))}

                                                {suggestions.available_slots.slice(0, 3).map((slot, idx) => (
                                                    <div
                                                        key={`${slot.day_of_week}-${slot.start_time}-${idx}`}
                                                        onMouseEnter={() =>
                                                            studio.setGhostPreview({
                                                                scheduleId: activeItem.session.id,
                                                                classItem: activeItem.classItem,
                                                                day_of_week: slot.day_of_week,
                                                                start_time: slot.start_time,
                                                                end_time: slot.end_time,
                                                                room_id: slot.room_id,
                                                                room_name: slot.room_name,
                                                                label: `${slot.day_of_week.slice(0, 3)} ${slot.time_range}`,
                                                            })
                                                        }
                                                        onMouseLeave={() => studio.setGhostPreview(null)}
                                                        className="bg-background border-border/70 flex items-center justify-between gap-2 rounded-xl border px-3 py-2"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="flex items-center gap-1.5 truncate text-xs font-semibold tabular-nums">
                                                                <Clock className="size-3 text-sky-600" />
                                                                {slot.day_of_week} · {slot.time_range}
                                                            </p>
                                                            <p className="text-muted-foreground truncate text-[11px]">{slot.reason}</p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="secondary"
                                                            className="h-7 shrink-0 px-2.5 text-xs"
                                                            disabled={applyingKey !== null}
                                                            onClick={() => void applySlot(slot)}
                                                        >
                                                            {applyingKey === `slot:${slot.day_of_week}:${slot.start_time}` ? (
                                                                <Loader2 className="size-3 animate-spin" />
                                                            ) : (
                                                                "Move"
                                                            )}
                                                        </Button>
                                                    </div>
                                                ))}

                                                {suggestions.can_convert_to_online && (
                                                    <div className="bg-background border-border/70 flex items-center justify-between gap-2 rounded-xl border px-3 py-2">
                                                        <div className="min-w-0">
                                                            <p className="flex items-center gap-1.5 text-xs font-semibold">
                                                                <Monitor className="size-3 text-violet-600" />
                                                                Switch to Online
                                                            </p>
                                                            <p className="text-muted-foreground text-[11px]">Frees the physical room</p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 shrink-0 px-2.5 text-xs"
                                                            disabled={applyingKey !== null}
                                                            onClick={() => void applyOnline()}
                                                        >
                                                            Switch
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            )}

                            {/* Clean Manual Adjustment Form */}
                            <form onSubmit={(e) => void handleSaveChanges(e)} className="space-y-4">
                                <h4 className="text-muted-foreground text-[11px] font-semibold uppercase tracking-wide">
                                    Adjust Day, Time or Room
                                </h4>

                                <div className="space-y-1.5">
                                    <Label className="text-xs">Day</Label>
                                    <div className="grid grid-cols-4 gap-1.5 sm:grid-cols-6">
                                        {days.map((d) => (
                                            <Button
                                                key={d}
                                                type="button"
                                                size="sm"
                                                variant={day === d ? "default" : "outline"}
                                                className="h-8 text-xs"
                                                onClick={() => setDay(d)}
                                            >
                                                {d.slice(0, 3)}
                                            </Button>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="board-start" className="text-xs">
                                            Start
                                        </Label>
                                        <Input
                                            id="board-start"
                                            type="time"
                                            value={startTime}
                                            onChange={(e) => setStartTime(e.target.value)}
                                            className="h-9 text-xs tabular-nums"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="board-end" className="text-xs">
                                            End
                                        </Label>
                                        <Input
                                            id="board-end"
                                            type="time"
                                            value={endTime}
                                            onChange={(e) => setEndTime(e.target.value)}
                                            className="h-9 text-xs tabular-nums"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="board-mode" className="text-xs">
                                        Meeting Type
                                    </Label>
                                    <Select
                                        value={deliveryMode}
                                        onValueChange={(v) => {
                                            const next = v as DeliveryMode;
                                            setDeliveryMode(next);
                                            if (isOnlineDeliveryMode(next)) setRoomId(null);
                                        }}
                                    >
                                        <SelectTrigger id="board-mode" className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="face_to_face">Face to face</SelectItem>
                                            <SelectItem value="online">Online (Asynchronous)</SelectItem>
                                            <SelectItem value="online_synchronous">Online (Synchronous)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {!online && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="board-room" className="text-xs">
                                            Room
                                        </Label>
                                        <Select
                                            value={roomId === null ? "none" : String(roomId)}
                                            onValueChange={(v) => setRoomId(v === "none" ? null : Number(v))}
                                        >
                                            <SelectTrigger id="board-room" className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Assign later</SelectItem>
                                                {rooms.map((r) => (
                                                    <SelectItem key={r.id} value={String(r.id)}>
                                                        {r.name}
                                                        {r.capacity ? ` (Cap ${r.capacity})` : ""}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                <div className="border-border/60 flex items-center justify-between gap-2 border-t pt-4">
                                    {confirmDelete ? (
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                disabled={savingEdit}
                                                onClick={() => void handleDelete()}
                                            >
                                                Confirm remove
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setConfirmDelete(false)}
                                            >
                                                Keep
                                            </Button>
                                        </div>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive gap-1.5 text-xs"
                                            onClick={() => setConfirmDelete(true)}
                                        >
                                            <Trash2 className="size-3.5" /> Remove
                                        </Button>
                                    )}

                                    <Button type="submit" size="sm" disabled={!validTimes || savingEdit}>
                                        {savingEdit ? <Loader2 className="mr-1.5 size-3.5 animate-spin" /> : null}
                                        Save changes
                                    </Button>
                                </div>
                            </form>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
