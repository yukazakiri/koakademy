import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import {
    AlertTriangle,
    ArrowLeftRight,
    Building2,
    CheckCircle2,
    Clock,
    Eye,
    Loader2,
    MapPin,
    Monitor,
    Sparkles,
    Users,
    X,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import type { ConflictKind, ScheduleConflict, SuggestedRoom, SuggestedSlot } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";
import { conflictKindShortLabel } from "../lib/conflict-engine";
import { minutesToApiTime, parseTimeToMinutes } from "../lib/time-geometry";

type ConflictWorkbenchProps = {
    studio: ScheduleStudioController;
};

type ConflictFilter = "all" | ConflictKind;

export default function ConflictWorkbench({ studio }: ConflictWorkbenchProps) {
    const [filter, setFilter] = useState<ConflictFilter>("all");
    const [scope, setScope] = useState<"visible" | "all">("visible");
    const [selectedSide, setSelectedSide] = useState<1 | 2>(1);
    const [applyingKey, setApplyingKey] = useState<string | null>(null);
    const [visibleLimit, setVisibleLimit] = useState(25);

    const visibleClassIds = useMemo(() => new Set(studio.visibleClasses.map((c) => c.id)), [studio.visibleClasses]);
    const isFiltered = studio.visibleClasses.length < studio.classes.length;

    const scopedConflicts = useMemo(() => {
        if (!isFiltered || scope === "all") return studio.conflicts;
        return studio.conflicts.filter(
            (c) =>
                (c.class_1.id !== undefined && visibleClassIds.has(c.class_1.id)) ||
                (c.class_2.id !== undefined && visibleClassIds.has(c.class_2.id)),
        );
    }, [studio.conflicts, isFiltered, scope, visibleClassIds]);

    const filteredConflicts = useMemo(() => {
        if (filter === "all") return scopedConflicts;
        return scopedConflicts.filter((c) => {
            const types = c.conflict_types ?? [c.conflict_type];
            return types.includes(filter);
        });
    }, [scopedConflicts, filter]);

    const activeConflict: ScheduleConflict | null = useMemo(() => {
        if (studio.focusedConflictIndex !== null) {
            const candidate = studio.conflicts[studio.focusedConflictIndex];
            if (candidate && filteredConflicts.includes(candidate)) {
                return candidate;
            }
        }
        if (studio.focusedScheduleIds.length > 0) {
            const matched = filteredConflicts.find(
                (c) =>
                    (c.schedule_1_id !== undefined && studio.focusedScheduleIds.includes(c.schedule_1_id)) ||
                    (c.schedule_2_id !== undefined && studio.focusedScheduleIds.includes(c.schedule_2_id)),
            );
            if (matched) return matched;
        }
        return filteredConflicts[0] ?? null;
    }, [studio.focusedConflictIndex, studio.conflicts, studio.focusedScheduleIds, filteredConflicts]);

    const activeScheduleId = selectedSide === 1 ? activeConflict?.schedule_1_id : activeConflict?.schedule_2_id;
    const activeClassId = selectedSide === 1 ? activeConflict?.class_1.id : activeConflict?.class_2.id;

    const activeClassItem = useMemo(
        () => studio.classes.find((c) => c.id === activeClassId),
        [studio.classes, activeClassId],
    );

    const activeSession = useMemo(
        () => activeClassItem?.schedules.find((s) => s.id === activeScheduleId),
        [activeClassItem, activeScheduleId],
    );

    useEffect(() => {
        if (activeScheduleId !== undefined) {
            void studio.loadSuggestionsForSchedule(activeScheduleId);
        }
    }, [activeScheduleId, studio.loadSuggestionsForSchedule]);

    const suggestions = activeScheduleId !== undefined ? studio.suggestionsCache[activeScheduleId] : undefined;
    const isLoadingSuggestions = activeScheduleId !== undefined && studio.loadingSuggestionsFor === activeScheduleId;

    const canSwapBlocks = useMemo(() => {
        if (!activeConflict || !activeConflict.schedule_1_id || !activeConflict.schedule_2_id) return false;
        const c1 = studio.classes.find((c) => c.id === activeConflict.class_1.id);
        const c2 = studio.classes.find((c) => c.id === activeConflict.class_2.id);
        const s1 = c1?.schedules.find((s) => s.id === activeConflict.schedule_1_id);
        const s2 = c2?.schedules.find((s) => s.id === activeConflict.schedule_2_id);
        if (!s1 || !s2) return false;
        return s1.room_id !== s2.room_id || s1.start_time !== s2.start_time || s1.end_time !== s2.end_time;
    }, [activeConflict, studio.classes]);

    const focusConflict = (conflict: ScheduleConflict, globalIndex: number) => {
        studio.setFocusedConflictIndex(globalIndex);
        const ids = [conflict.schedule_1_id, conflict.schedule_2_id].filter((id): id is number => typeof id === "number");
        studio.setFocusedScheduleIds(ids);
        setSelectedSide(1);
        studio.setGhostPreview(null);
        if (studio.activeLens === "matrix") {
            studio.setMatrixDay(conflict.day);
        }
        if (conflict.schedule_1_id !== undefined) {
            void studio.loadSuggestionsForSchedule(conflict.schedule_1_id);
        }
    };

    const applyRoomSuggestion = async (room: SuggestedRoom) => {
        if (!activeClassItem || !activeSession) return;
        setApplyingKey(`room:${room.room_id}`);
        try {
            await studio.updateScheduleBlockDirect(
                activeClassItem,
                activeSession,
                { room_id: room.room_id, delivery_mode: "face_to_face" },
                `Moved ${activeClassItem.subject_code} to ${room.name}`,
            );
            studio.setGhostPreview(null);
        } finally {
            setApplyingKey(null);
        }
    };

    const applySlotSuggestion = async (slot: SuggestedSlot) => {
        if (!activeClassItem || !activeSession) return;
        setApplyingKey(`slot:${slot.day_of_week}:${slot.start_time}`);
        try {
            await studio.updateScheduleBlockDirect(
                activeClassItem,
                activeSession,
                {
                    day_of_week: slot.day_of_week,
                    start_time: slot.start_time,
                    end_time: slot.end_time,
                    room_id: slot.room_id,
                },
                `Moved ${activeClassItem.subject_code} to ${slot.day_of_week} ${slot.time_range}`,
            );
            studio.setGhostPreview(null);
        } finally {
            setApplyingKey(null);
        }
    };

    const applyConvertToOnline = async () => {
        if (!activeClassItem || !activeSession) return;
        setApplyingKey("online");
        try {
            const targetMode = studio.policy.default_online_delivery_mode;
            await studio.updateScheduleBlockDirect(
                activeClassItem,
                activeSession,
                { delivery_mode: targetMode, room_id: null },
                `Converted ${activeClassItem.subject_code} to Online`,
            );
            studio.setGhostPreview(null);
        } finally {
            setApplyingKey(null);
        }
    };

    const applyAtomicSwap = async () => {
        if (!activeConflict || !activeConflict.schedule_1_id || !activeConflict.schedule_2_id) return;
        const c1 = studio.classes.find((c) => c.id === activeConflict.class_1.id);
        const c2 = studio.classes.find((c) => c.id === activeConflict.class_2.id);
        const s1 = c1?.schedules.find((s) => s.id === activeConflict.schedule_1_id);
        const s2 = c2?.schedules.find((s) => s.id === activeConflict.schedule_2_id);
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
                    delivery_mode: s1.delivery_mode ?? c1.delivery_mode ?? "face_to_face",
                    component_type: s1.component_type ?? "lecture",
                    status: s1.status ?? "published",
                },
                {
                    schedule_id: s2.id,
                    day_of_week: s2.day_of_week,
                    start_time: minutesToApiTime(parseTimeToMinutes(s2.start_time)),
                    end_time: minutesToApiTime(parseTimeToMinutes(s2.end_time)),
                    room_id: s2.room_id ?? null,
                    delivery_mode: s2.delivery_mode ?? c2.delivery_mode ?? "face_to_face",
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

    const countsByKind = useMemo(() => {
        const counts = { room: 0, faculty: 0, section: 0 };
        for (const c of scopedConflicts) {
            const types = c.conflict_types ?? [c.conflict_type];
            for (const t of types) {
                counts[t] = (counts[t] ?? 0) + 1;
            }
        }
        return counts;
    }, [scopedConflicts]);

    return (
        <aside
            aria-label="Visual conflict resolution workbench"
            className="bg-card border-border/70 flex h-full w-full flex-col overflow-hidden rounded-xl border lg:w-[340px] xl:w-[368px] lg:shrink-0"
        >
            {/* Header */}
            <div className="border-border/60 space-y-2.5 border-b p-3">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span
                            className={`flex size-7 items-center justify-center rounded-lg ${
                                scopedConflicts.length > 0
                                    ? "bg-red-500/15 text-red-600 dark:text-red-400"
                                    : "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
                            }`}
                        >
                            {scopedConflicts.length > 0 ? (
                                <AlertTriangle className="size-4" />
                            ) : (
                                <CheckCircle2 className="size-4" />
                            )}
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold tracking-tight">Conflict Workbench</h2>
                            <p className="text-muted-foreground text-[11px] tabular-nums">
                                {scopedConflicts.length > 0
                                    ? `${scopedConflicts.length} ${isFiltered && scope === "visible" ? "in filter" : "unresolved"} · hover fixes to preview`
                                    : "Schedule is conflict-free"}
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => {
                            studio.setWorkbenchOpen(false);
                            studio.setSpotlightMode(false);
                            studio.setGhostPreview(null);
                        }}
                        aria-label="Close conflict workbench"
                    >
                        <X className="size-4" />
                    </Button>
                </div>

                {/* Scope switcher when filters are active */}
                {isFiltered && studio.conflicts.length > 0 && (
                    <div className="bg-muted/70 grid grid-cols-2 rounded-lg p-0.5 text-[11px] font-medium">
                        <button
                            type="button"
                            onClick={() => setScope("visible")}
                            className={`rounded-md px-2 py-1 transition-colors tabular-nums ${
                                scope === "visible"
                                    ? "bg-background text-foreground shadow-xs"
                                    : "text-muted-foreground hover:text-foreground"
                            }`}
                        >
                            Visible on grid
                        </button>
                        <button
                            type="button"
                            onClick={() => setScope("all")}
                            className={`rounded-md px-2 py-1 transition-colors tabular-nums ${
                                scope === "all"
                                    ? "bg-background text-foreground shadow-xs"
                                    : "text-muted-foreground hover:text-foreground"
                            }`}
                        >
                            All term ({studio.conflicts.length})
                        </button>
                    </div>
                )}

                {/* Spotlight Mode Toggle */}
                {scopedConflicts.length > 0 && (
                    <div className="bg-muted/40 flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2">
                        <label htmlFor="studio-spotlight-toggle" className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                            <Eye className="text-primary size-3.5" />
                            <span>Spotlight conflicts on grid</span>
                        </label>
                        <Switch
                            id="studio-spotlight-toggle"
                            checked={studio.spotlightMode}
                            onCheckedChange={(checked) => {
                                studio.setSpotlightMode(checked);
                                if (!checked) studio.setFocusedScheduleIds([]);
                            }}
                        />
                    </div>
                )}

                {/* Filter Pills */}
                {scopedConflicts.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {(
                            [
                                { id: "all", label: `All (${scopedConflicts.length})` },
                                { id: "room", label: `Room (${countsByKind.room})` },
                                { id: "faculty", label: `Faculty (${countsByKind.faculty})` },
                                { id: "section", label: `Cohort (${countsByKind.section})` },
                            ] as Array<{ id: ConflictFilter; label: string }>
                        ).map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setFilter(item.id)}
                                className={`rounded-md border px-2 py-0.5 text-[11px] font-medium tabular-nums transition-colors ${
                                    filter === item.id
                                        ? "bg-primary text-primary-foreground border-primary"
                                        : "bg-background hover:bg-muted border-border/70 text-muted-foreground"
                                }`}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Body */}
            <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-3">
                {scopedConflicts.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                        <div className="flex size-11 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="size-6" />
                        </div>
                        <p className="text-sm font-semibold">Zero Scheduling Conflicts</p>
                        <p className="text-muted-foreground max-w-[240px] text-xs">
                            All visible rooms, instructors, and student cohorts are clear under your active scheduling rules.
                        </p>
                    </div>
                ) : (
                    <>
                        {/* Bounded Scrollable Conflict List so Smart Fix Deck stays immediately visible */}
                        <div className="max-h-[175px] shrink-0 space-y-1.5 overflow-y-auto pr-1">
                            {filteredConflicts.slice(0, visibleLimit).map((conflict, idx) => {
                                const globalIdx = studio.conflicts.indexOf(conflict);
                                const isSelected = activeConflict === conflict;
                                const kinds = conflict.conflict_types ?? [conflict.conflict_type];

                                return (
                                    <button
                                        key={`${conflict.schedule_1_id}-${conflict.schedule_2_id}-${idx}`}
                                        type="button"
                                        onClick={() => focusConflict(conflict, globalIdx)}
                                        className={`w-full rounded-lg border p-2 text-left transition-colors ${
                                            isSelected
                                                ? "border-red-500/60 bg-red-500/[0.08] ring-1 ring-red-500/30"
                                                : "border-border/70 bg-background hover:bg-muted/40"
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-1.5">
                                            <div className="flex flex-wrap items-center gap-1">
                                                {kinds.map((kind) => (
                                                    <span
                                                        key={kind}
                                                        className="rounded bg-red-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:text-red-300"
                                                    >
                                                        {conflictKindShortLabel(kind)}
                                                    </span>
                                                ))}
                                            </div>
                                            <span className="text-muted-foreground text-[10px] font-medium tabular-nums">
                                                {conflict.day.slice(0, 3)} · {conflict.overlap_minutes ? `${conflict.overlap_minutes}m` : conflict.time}
                                            </span>
                                        </div>

                                        <div className="mt-1 space-y-0.5 text-xs">
                                            <p className="truncate font-semibold">
                                                {conflict.class_1.subject_code} · Sec {conflict.class_1.section}
                                                <span className="text-muted-foreground font-normal"> vs </span>
                                                {conflict.class_2.subject_code} · Sec {conflict.class_2.section}
                                            </p>
                                            <p className="text-muted-foreground truncate text-[11px] tabular-nums">
                                                {conflict.time}
                                                {conflict.class_1.room ? ` · ${conflict.class_1.room}` : ""}
                                            </p>
                                        </div>
                                    </button>
                                );
                            })}
                            {filteredConflicts.length > visibleLimit && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-full text-xs"
                                    onClick={() => setVisibleLimit((n) => n + 25)}
                                >
                                    Show more ({filteredConflicts.length - visibleLimit} remaining)
                                </Button>
                            )}
                        </div>

                        {/* Active Conflict Resolution Deck */}
                        {activeConflict && (
                            <div className="border-border/80 bg-muted/20 space-y-3 rounded-xl border p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="flex items-center gap-1.5 text-xs font-semibold">
                                        <Sparkles className="text-primary size-3.5" />
                                        Smart Fix Options
                                    </span>
                                    {canSwapBlocks && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="h-6 gap-1 px-2 text-[11px]"
                                            disabled={applyingKey !== null}
                                            onClick={() => void applyAtomicSwap()}
                                        >
                                            <ArrowLeftRight className="size-3" />
                                            Swap Both
                                        </Button>
                                    )}
                                </div>

                                {/* Choose which colliding class to move */}
                                <div className="bg-background grid grid-cols-2 gap-1 rounded-lg border p-1">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSelectedSide(1);
                                            studio.setGhostPreview(null);
                                        }}
                                        className={`rounded-md px-2 py-1.5 text-left text-[11px] transition-colors ${
                                            selectedSide === 1
                                                ? "bg-primary text-primary-foreground font-semibold"
                                                : "hover:bg-muted text-muted-foreground"
                                        }`}
                                    >
                                        <div className="truncate">Move {activeConflict.class_1.subject_code}</div>
                                        <div className="truncate text-[10px] opacity-80">Sec {activeConflict.class_1.section}</div>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSelectedSide(2);
                                            studio.setGhostPreview(null);
                                        }}
                                        className={`rounded-md px-2 py-1.5 text-left text-[11px] transition-colors ${
                                            selectedSide === 2
                                                ? "bg-primary text-primary-foreground font-semibold"
                                                : "hover:bg-muted text-muted-foreground"
                                        }`}
                                    >
                                        <div className="truncate">Move {activeConflict.class_2.subject_code}</div>
                                        <div className="truncate text-[10px] opacity-80">Sec {activeConflict.class_2.section}</div>
                                    </button>
                                </div>

                                {isLoadingSuggestions ? (
                                    <div className="text-muted-foreground flex items-center justify-center gap-2 py-6 text-xs">
                                        <Loader2 className="size-4 animate-spin" />
                                        Finding conflict-free rooms &amp; slots…
                                    </div>
                                ) : suggestions ? (
                                    <div className="space-y-3">
                                        {/* Suggested Free Rooms */}
                                        {suggestions.available_rooms.length > 0 && (
                                            <div className="space-y-1.5">
                                                <p className="text-muted-foreground flex items-center gap-1 text-[11px] font-medium">
                                                    <Building2 className="size-3" /> Free Rooms (Keep Same Time)
                                                </p>
                                                <div className="space-y-1">
                                                    {suggestions.available_rooms.slice(0, 4).map((room) => (
                                                        <div
                                                            key={room.room_id}
                                                            onMouseEnter={() => {
                                                                if (activeClassItem && activeSession) {
                                                                    studio.setGhostPreview({
                                                                        scheduleId: activeSession.id,
                                                                        classItem: activeClassItem,
                                                                        day_of_week: activeSession.day_of_week,
                                                                        start_time: activeSession.start_time,
                                                                        end_time: activeSession.end_time,
                                                                        room_id: room.room_id,
                                                                        room_name: room.name,
                                                                        label: `Move to ${room.name}`,
                                                                    });
                                                                }
                                                            }}
                                                            onMouseLeave={() => studio.setGhostPreview(null)}
                                                            className="bg-background border-border/70 hover:border-emerald-500/50 flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2 transition-colors"
                                                        >
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <MapPin className="size-3 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                                    <span className="truncate text-xs font-semibold">{room.name}</span>
                                                                    <Badge variant="outline" className="h-4 px-1 text-[10px] tabular-nums">
                                                                        Cap {room.capacity}
                                                                    </Badge>
                                                                </div>
                                                                <p className="text-muted-foreground truncate text-[10px]">{room.reason}</p>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="secondary"
                                                                className="h-7 shrink-0 px-2.5 text-[11px]"
                                                                disabled={applyingKey !== null}
                                                                onClick={() => void applyRoomSuggestion(room)}
                                                            >
                                                                {applyingKey === `room:${room.room_id}` ? (
                                                                    <Loader2 className="size-3 animate-spin" />
                                                                ) : (
                                                                    "Apply"
                                                                )}
                                                            </Button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Suggested Conflict-Free Time Slots */}
                                        {suggestions.available_slots.length > 0 && (
                                            <div className="space-y-1.5">
                                                <p className="text-muted-foreground flex items-center gap-1 text-[11px] font-medium">
                                                    <Clock className="size-3" /> Conflict-Free Time Slots
                                                </p>
                                                <div className="space-y-1">
                                                    {suggestions.available_slots.slice(0, 5).map((slot, idx) => (
                                                        <div
                                                            key={`${slot.day_of_week}-${slot.start_time}-${idx}`}
                                                            onMouseEnter={() => {
                                                                if (activeClassItem && activeSession) {
                                                                    studio.setGhostPreview({
                                                                        scheduleId: activeSession.id,
                                                                        classItem: activeClassItem,
                                                                        day_of_week: slot.day_of_week,
                                                                        start_time: slot.start_time,
                                                                        end_time: slot.end_time,
                                                                        room_id: slot.room_id,
                                                                        room_name: slot.room_name,
                                                                        label: `Move to ${slot.day_of_week.slice(0, 3)} ${slot.time_range}`,
                                                                    });
                                                                }
                                                            }}
                                                            onMouseLeave={() => studio.setGhostPreview(null)}
                                                            className="bg-background border-border/70 hover:border-emerald-500/50 flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2 transition-colors"
                                                        >
                                                            <div className="min-w-0">
                                                                <p className="truncate text-xs font-semibold tabular-nums">
                                                                    {slot.day_of_week.slice(0, 3)} · {slot.time_range}
                                                                </p>
                                                                <p className="text-muted-foreground truncate text-[10px]">
                                                                    {slot.room_name ? `${slot.room_name} · ` : ""}
                                                                    {slot.reason}
                                                                </p>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="secondary"
                                                                className="h-7 shrink-0 px-2.5 text-[11px]"
                                                                disabled={applyingKey !== null}
                                                                onClick={() => void applySlotSuggestion(slot)}
                                                            >
                                                                {applyingKey === `slot:${slot.day_of_week}:${slot.start_time}` ? (
                                                                    <Loader2 className="size-3 animate-spin" />
                                                                ) : (
                                                                    "Move"
                                                                )}
                                                            </Button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Convert to Online */}
                                        {suggestions.can_convert_to_online && (
                                            <div className="bg-background border-border/70 flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2">
                                                <div className="min-w-0">
                                                    <p className="flex items-center gap-1.5 text-xs font-semibold">
                                                        <Monitor className="size-3.5 text-sky-600 dark:text-sky-400" />
                                                        Convert session to Online
                                                    </p>
                                                    <p className="text-muted-foreground text-[10px]">Frees the physical room immediately</p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 shrink-0 px-2.5 text-[11px]"
                                                    disabled={applyingKey !== null}
                                                    onClick={() => void applyConvertToOnline()}
                                                >
                                                    {applyingKey === "online" ? <Loader2 className="size-3 animate-spin" /> : "Switch"}
                                                </Button>
                                            </div>
                                        )}

                                        {suggestions.available_rooms.length === 0 &&
                                            suggestions.available_slots.length === 0 &&
                                            !suggestions.can_convert_to_online && (
                                                <p className="text-muted-foreground py-2 text-center text-xs">
                                                    No single-step slot found. Drag the block directly on the grid or switch to Resource Matrix to
                                                    rearrange multiple sessions.
                                                </p>
                                            )}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <Users className="size-3.5" /> Select a conflict above to load instant fix options.
                                    </p>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </aside>
    );
}
