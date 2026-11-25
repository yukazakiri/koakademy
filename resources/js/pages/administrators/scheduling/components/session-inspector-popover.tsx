import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { show as showClass } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowUpRight,
    Clock,
    Layers,
    MapPin,
    Monitor,
    Pencil,
    Sparkles,
    Trash2,
    Users,
    X,
} from "lucide-react";
import { useState } from "react";
import {
    canonicalDeliveryMode,
    deliveryModeLabel,
    isOnlineDeliveryMode,
    type DeliveryMode,
} from "../../classes/lib/class-defaults";
import type { ClassScheduleData, RoomOption, ScheduleEntry } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";
import { conflictKindShortLabel } from "../lib/conflict-engine";

type SessionInspectorPopoverProps = {
    studio: ScheduleStudioController;
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    rooms: RoomOption[];
    onClose: () => void;
};

export default function SessionInspectorPopover({
    studio,
    classItem,
    session,
    rooms,
    onClose,
}: SessionInspectorPopoverProps) {
    const [busy, setBusy] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    if (session.id === undefined) return null;

    const sessionId = session.id;
    const mode = canonicalDeliveryMode(session.delivery_mode ?? classItem.delivery_mode);
    const online = isOnlineDeliveryMode(mode);
    const sessionConflicts = studio.conflictsByScheduleId.get(sessionId) ?? [];
    const assignedRoom = session.room_id ? studio.roomsById.get(session.room_id) : undefined;
    const capacity = assignedRoom?.capacity ?? 0;
    const isOvercrowded = !online && capacity > 0 && classItem.student_count > capacity;
    const patternSiblings = session.pattern_group_id
        ? classItem.schedules.filter((entry) => entry.pattern_group_id === session.pattern_group_id).length
        : 0;

    const handleRoomChange = async (value: string) => {
        setBusy(true);
        try {
            const nextRoomId = value === "none" ? null : Number(value);
            const roomName = nextRoomId ? studio.roomsById.get(nextRoomId)?.name : "Unassigned";
            await studio.updateScheduleBlockDirect(
                classItem,
                session,
                { room_id: nextRoomId },
                `Moved ${classItem.subject_code} to ${roomName ?? "room"}`,
            );
        } finally {
            setBusy(false);
        }
    };

    const handleDeliveryChange = async (value: string) => {
        setBusy(true);
        try {
            const nextMode = value as DeliveryMode;
            await studio.updateScheduleBlockDirect(
                classItem,
                session,
                {
                    delivery_mode: nextMode,
                    room_id: isOnlineDeliveryMode(nextMode) ? null : (session.room_id ?? classItem.room_id ?? null),
                },
                `Switched ${classItem.subject_code} to ${deliveryModeLabel(nextMode)}`,
            );
        } finally {
            setBusy(false);
        }
    };

    const handleDelete = async (deletePattern: boolean) => {
        setBusy(true);
        try {
            await studio.deleteScheduleBlock(sessionId, deletePattern);
            onClose();
        } finally {
            setBusy(false);
        }
    };

    return (
        <div
            role="dialog"
            aria-label={`Session inspector for ${classItem.subject_code}`}
            className="bg-popover text-popover-foreground border-border/80 w-full max-w-sm rounded-xl border p-3.5 shadow-xl"
        >
            {/* Header */}
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 space-y-0.5">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Link
                            href={showClass.url(classItem.id)}
                            className="hover:text-primary inline-flex items-center gap-1 text-sm font-semibold tracking-tight hover:underline"
                        >
                            {classItem.subject_code}
                            <ArrowUpRight className="size-3.5 opacity-70" />
                        </Link>
                        <Badge variant="outline" className="h-5 px-1.5 text-[11px]">
                            Sec {classItem.section}
                        </Badge>
                        {session.status === "draft" && (
                            <Badge variant="outline" className="h-5 border-amber-500/40 bg-amber-500/10 px-1.5 text-[10px] text-amber-700 dark:text-amber-300">
                                Draft
                            </Badge>
                        )}
                        {session.component_type === "lab" && (
                            <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                                Lab
                            </Badge>
                        )}
                    </div>
                    <p className="text-muted-foreground line-clamp-1 text-xs">{classItem.subject_title}</p>
                </div>
                <Button type="button" variant="ghost" size="icon" className="size-7 shrink-0" onClick={onClose} aria-label="Close inspector">
                    <X className="size-4" />
                </Button>
            </div>

            {/* Conflict Banner if colliding */}
            {sessionConflicts.length > 0 && (
                <div className="mt-2.5 rounded-lg border border-red-500/35 bg-red-500/[0.08] p-2.5">
                    <div className="flex items-start justify-between gap-2">
                        <div className="space-y-1">
                            <p className="flex items-center gap-1.5 text-xs font-semibold text-red-700 dark:text-red-300">
                                <AlertTriangle className="size-3.5 shrink-0" />
                                {sessionConflicts.length} active {sessionConflicts.length === 1 ? "conflict" : "conflicts"}
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {Object.entries(
                                    sessionConflicts.reduce<Record<string, number>>((acc, c) => {
                                        for (const kind of c.conflict_types ?? [c.conflict_type]) {
                                            acc[kind] = (acc[kind] ?? 0) + 1;
                                        }
                                        return acc;
                                    }, {}),
                                ).map(([kind, count]) => (
                                    <span
                                        key={kind}
                                        className="rounded bg-red-500/15 px-1.5 py-0.5 text-[10px] font-medium text-red-700 tabular-nums dark:text-red-200"
                                    >
                                        {conflictKindShortLabel(kind as "room" | "faculty" | "section")}
                                        {count > 1 ? ` ×${count}` : ""}
                                    </span>
                                ))}
                            </div>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="h-7 gap-1 px-2 text-xs"
                            onClick={() => {
                                studio.setWorkbenchOpen(true);
                                studio.setSpotlightMode(true);
                                studio.setFocusedScheduleIds([sessionId]);
                                void studio.loadSuggestionsForSchedule(sessionId);
                                onClose();
                            }}
                        >
                            <Sparkles className="size-3" />
                            Resolve
                        </Button>
                    </div>
                </div>
            )}

            {/* Metadata Grid */}
            <div className="bg-muted/35 mt-3 space-y-1.5 rounded-lg border p-2.5 text-xs">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground flex items-center gap-1.5">
                        <Clock className="size-3.5" /> Schedule
                    </span>
                    <span className="font-medium tabular-nums">
                        {session.day_of_week} · {session.time_range}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground flex items-center gap-1.5">
                        <Users className="size-3.5" /> Instructor
                    </span>
                    <span className="truncate font-medium">{classItem.faculty_name ?? "Unassigned"}</span>
                </div>
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground flex items-center gap-1.5">
                        <Layers className="size-3.5" /> Enrollment
                    </span>
                    <span className={`font-medium tabular-nums ${isOvercrowded ? "text-amber-700 dark:text-amber-300" : ""}`}>
                        {classItem.student_count} / {classItem.maximum_slots ?? 40} students
                        {!online && capacity > 0 ? ` (Room cap ${capacity})` : ""}
                    </span>
                </div>
                {patternSiblings > 1 && (
                    <div className="text-muted-foreground border-border/50 border-t pt-1.5 text-[11px]">
                        Part of a recurring {patternSiblings}-day pattern group
                    </div>
                )}
            </div>

            {/* Inline Quick Controls */}
            <div className="mt-3 space-y-2.5">
                <div className="space-y-1">
                    <label htmlFor="inspector-delivery" className="text-muted-foreground flex items-center gap-1 text-[11px] font-medium">
                        <Monitor className="size-3" /> Delivery Mode
                    </label>
                    <Select value={mode} disabled={busy} onValueChange={(v) => void handleDeliveryChange(v)}>
                        <SelectTrigger id="inspector-delivery" className="h-8 text-xs">
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
                    <div className="space-y-1">
                        <label htmlFor="inspector-room" className="text-muted-foreground flex items-center gap-1 text-[11px] font-medium">
                            <MapPin className="size-3" /> Quick Room Switch
                        </label>
                        <Select
                            value={session.room_id ? String(session.room_id) : "none"}
                            disabled={busy}
                            onValueChange={(v) => void handleRoomChange(v)}
                        >
                            <SelectTrigger id="inspector-room" className="h-8 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Unassigned</SelectItem>
                                {rooms.map((room) => (
                                    <SelectItem key={room.id} value={String(room.id)}>
                                        {room.name}
                                        {room.capacity ? ` · Cap ${room.capacity}` : ""}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </div>

            {/* Footer Actions */}
            {confirmDelete ? (
                <div className="border-destructive/30 mt-3 space-y-2 rounded-lg border bg-red-500/[0.04] p-2.5">
                    <p className="text-xs font-medium">Remove {session.day_of_week} session?</p>
                    <div className="flex flex-wrap gap-1.5">
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="h-7 text-xs"
                            disabled={busy}
                            onClick={() => void handleDelete(false)}
                        >
                            This session only
                        </Button>
                        {patternSiblings > 1 && (
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                className="h-7 text-xs"
                                disabled={busy}
                                onClick={() => void handleDelete(true)}
                            >
                                All {patternSiblings} linked days
                            </Button>
                        )}
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7 text-xs"
                            disabled={busy}
                            onClick={() => setConfirmDelete(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="border-border/60 mt-3 flex items-center justify-between gap-2 border-t pt-2.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive h-7 gap-1 px-2 text-xs"
                        disabled={busy}
                        onClick={() => setConfirmDelete(true)}
                    >
                        <Trash2 className="size-3.5" /> Remove
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-7 gap-1 px-2.5 text-xs"
                        disabled={busy}
                        onClick={() => {
                            studio.setEditorTarget({ classId: classItem.id, sessionId });
                            onClose();
                        }}
                    >
                        <Pencil className="size-3" /> Full Editor
                    </Button>
                </div>
            )}
        </div>
    );
}
