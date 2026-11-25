import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    Check,
    CheckCircle2,
    GraduationCap,
    Loader2,
    MapPin,
    Monitor,
    Sparkles,
    Trash2,
    User,
    X,
} from "lucide-react";
import { useMemo, useState } from "react";
import { isOnlineDeliveryMode, type DeliveryMode } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, ScheduleEntry } from "../../components/scheduling-types";
import {
    formatTimeRange,
    minutesToApiTime,
    minutesToShortHour,
} from "../../scheduling/lib/time-geometry";
import {
    evaluatePatternFit,
    XRAY_START_HOURS,
    type XRayCell,
    type XRayGridResult,
    type XRayRoomCandidate,
} from "../lib/xray-engine";

type XRaySlotMatrixProps = {
    targetClass: ClassScheduleData;
    days: string[];
    grid: XRayGridResult;
    durationMinutes: number;
    deliveryMode: DeliveryMode;
    relocatingSession: ScheduleEntry | null;
    onStartRelocate: (session: ScheduleEntry) => void;
    onCancelRelocate: () => void;
    onPlaceOrMove: (payload: {
        days: string[];
        startTime: string;
        endTime: string;
        roomId: number | null;
        deliveryMode: DeliveryMode;
    }) => Promise<void>;
    onDeleteSession: (sessionId: number) => Promise<void>;
};

export default function XRaySlotMatrix({
    targetClass,
    days,
    grid,
    durationMinutes,
    deliveryMode,
    relocatingSession,
    onStartRelocate,
    onCancelRelocate,
    onPlaceOrMove,
    onDeleteSession,
}: XRaySlotMatrixProps) {
    const [selectedCell, setSelectedCell] = useState<XRayCell | null>(null);
    const [selectedPattern, setSelectedPattern] = useState<"single" | "mwf" | "tth">("single");
    const [submittingKey, setSubmittingKey] = useState<string | null>(null);

    const online = isOnlineDeliveryMode(deliveryMode);

    // Compute pattern options when an open cell is clicked in Add mode
    const patternOptions = useMemo(() => {
        if (!selectedCell || relocatingSession || selectedCell.status !== "open") return null;
        const mwfDays = ["Monday", "Wednesday", "Friday"].filter((d) => days.includes(d));
        const tthDays = ["Tuesday", "Thursday"].filter((d) => days.includes(d));
        const mwf =
            mwfDays.length === 3 && mwfDays.includes(selectedCell.day)
                ? evaluatePatternFit(grid, mwfDays, selectedCell.startHour, online)
                : { allOpen: false, commonRooms: [] };
        const tth =
            tthDays.length === 2 && tthDays.includes(selectedCell.day)
                ? evaluatePatternFit(grid, tthDays, selectedCell.startHour, online)
                : { allOpen: false, commonRooms: [] };
        return { mwf, tth };
    }, [selectedCell, relocatingSession, days, grid, online]);

    const activePatternDays = useMemo(() => {
        if (!selectedCell) return [];
        if (selectedPattern === "mwf" && patternOptions?.mwf.allOpen) return ["Monday", "Wednesday", "Friday"];
        if (selectedPattern === "tth" && patternOptions?.tth.allOpen) return ["Tuesday", "Thursday"];
        return [selectedCell.day];
    }, [selectedCell, selectedPattern, patternOptions]);

    const activeRoomChoices: XRayRoomCandidate[] = useMemo(() => {
        if (!selectedCell) return [];
        if (selectedPattern === "mwf" && patternOptions?.mwf.allOpen) return patternOptions.mwf.commonRooms;
        if (selectedPattern === "tth" && patternOptions?.tth.allOpen) return patternOptions.tth.commonRooms;
        return selectedCell.openRooms;
    }, [selectedCell, selectedPattern, patternOptions]);

    const handleCellClick = (cell: XRayCell) => {
        if (cell.status === "current" && cell.existingSession) {
            setSelectedCell(null);
            if (relocatingSession?.id === cell.existingSession.id) {
                onCancelRelocate();
            } else {
                onStartRelocate(cell.existingSession);
            }
            return;
        }
        setSelectedPattern("single");
        setSelectedCell((prev) => (prev?.key === cell.key ? null : cell));
    };

    const commitPlacement = async (roomId: number | null, keyLabel: string) => {
        if (!selectedCell) return;
        setSubmittingKey(keyLabel);
        try {
            await onPlaceOrMove({
                days: activePatternDays,
                startTime: minutesToApiTime(selectedCell.startMin),
                endTime: minutesToApiTime(selectedCell.endMin),
                roomId: online ? null : roomId,
                deliveryMode,
            });
            setSelectedCell(null);
        } finally {
            setSubmittingKey(null);
        }
    };

    const handleRemoveRelocating = async () => {
        if (!relocatingSession?.id) return;
        setSubmittingKey("delete");
        try {
            await onDeleteSession(relocatingSession.id);
            onCancelRelocate();
        } finally {
            setSubmittingKey(null);
        }
    };

    return (
        <div className="relative flex flex-1 flex-col overflow-hidden">
            {/* Active Relocate Mode Banner */}
            {relocatingSession && (
                <div className="border-primary/25 bg-primary/[0.06] flex flex-wrap items-center justify-between gap-2 border-b px-5 py-2.5 text-xs">
                    <div className="flex items-center gap-2">
                        <Sparkles className="text-primary size-4 shrink-0" />
                        <span>
                            <strong>Relocating {relocatingSession.day_of_week}</strong> ({relocatingSession.time_range}) — click any{" "}
                            <span className="font-semibold text-emerald-700 dark:text-emerald-400">green open slot</span> below to move it there.
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        {relocatingSession.id !== undefined && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="text-destructive hover:text-destructive h-7 gap-1 px-2 text-xs"
                                disabled={submittingKey !== null}
                                onClick={() => void handleRemoveRelocating()}
                            >
                                <Trash2 className="size-3.5" /> Remove
                            </Button>
                        )}
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7 px-2.5 text-xs"
                            onClick={onCancelRelocate}
                        >
                            Done
                        </Button>
                    </div>
                </div>
            )}

            {/* Scrollable Availability X-Ray Grid */}
            <div className="flex-1 overflow-auto p-4">
                <div style={{ minWidth: days.length === 7 ? 780 : 680 }}>
                    {/* Day Headers */}
                    <div
                        className="mb-2 grid gap-2"
                        style={{ gridTemplateColumns: `68px repeat(${days.length}, minmax(0, 1fr))` }}
                    >
                        <div className="text-muted-foreground flex items-center justify-end pr-2 text-[11px] font-medium">
                            Start
                        </div>
                        {days.map((day) => {
                            const dayOpenCount = XRAY_START_HOURS.filter(
                                (h) => grid.cells.get(`${day}:${h}`)?.status === "open",
                            ).length;
                            const isSunday = day === "Sunday";
                            return (
                                <div
                                    key={day}
                                    className={`flex items-center justify-between gap-1 rounded-lg px-2.5 py-1.5 ${
                                        isSunday ? "bg-amber-500/[0.06]" : "bg-muted/35"
                                    }`}
                                >
                                    <span className="truncate text-xs font-semibold tracking-tight">{day.slice(0, 3)}</span>
                                    <span className="text-muted-foreground shrink-0 text-[10px] font-medium whitespace-nowrap tabular-nums">
                                        {dayOpenCount} open
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    {/* Hourly Rows */}
                    <div className="space-y-1.5">
                        {XRAY_START_HOURS.map((hour) => (
                            <div
                                key={hour}
                                className="grid items-stretch gap-2"
                                style={{ gridTemplateColumns: `68px repeat(${days.length}, minmax(0, 1fr))` }}
                            >
                                <div className="text-muted-foreground flex items-center justify-end pr-2 text-xs font-medium tabular-nums">
                                    {minutesToShortHour(hour)}
                                </div>

                                {days.map((day) => {
                                    const cell = grid.cells.get(`${day}:${hour}`);
                                    if (!cell) return <div key={`${day}:${hour}`} />;

                                    const isSelected = selectedCell?.key === cell.key;
                                    const isPatternHighlight =
                                        selectedCell !== null &&
                                        cell.startHour === selectedCell.startHour &&
                                        activePatternDays.includes(cell.day);

                                    if (cell.status === "current") {
                                        const hasConflict = Boolean(cell.existingHasConflict);
                                        const isMoving = Boolean(cell.isRelocatingThis);
                                        return (
                                            <button
                                                key={cell.key}
                                                type="button"
                                                onClick={() => handleCellClick(cell)}
                                                title={
                                                    hasConflict
                                                        ? "Current session (has overlap) — click to relocate to any green slot"
                                                        : "Current session — click to relocate"
                                                }
                                                className={`group flex min-h-[52px] flex-col justify-between rounded-xl border px-2.5 py-1.5 text-left transition-all active:scale-[0.98] ${
                                                    isMoving
                                                        ? "border-primary bg-primary/15 ring-primary/40 border-dashed ring-2"
                                                        : hasConflict
                                                          ? "border-rose-400/70 bg-rose-500/[0.11] text-rose-950 hover:bg-rose-500/[0.16] dark:text-rose-50"
                                                          : "border-indigo-500/45 bg-indigo-500/[0.12] text-indigo-950 hover:bg-indigo-500/[0.18] dark:text-indigo-50"
                                                }`}
                                            >
                                                <div className="flex items-center justify-between gap-1">
                                                    <span className="truncate text-[11px] font-semibold">
                                                        {isMoving ? "Moving…" : targetClass.subject_code}
                                                    </span>
                                                    {hasConflict ? (
                                                        <AlertTriangle className="size-3 shrink-0 text-rose-600 dark:text-rose-300" />
                                                    ) : (
                                                        <Check className="size-3 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                                    )}
                                                </div>
                                                <span className="truncate text-[10px] opacity-80">
                                                    {cell.existingSession?.room ?? (online ? "Online" : "No room")}
                                                </span>
                                            </button>
                                        );
                                    }

                                    if (cell.status === "open") {
                                        return (
                                            <button
                                                key={cell.key}
                                                type="button"
                                                onClick={() => handleCellClick(cell)}
                                                title={`Open slot: ${day} ${formatTimeRange(cell.startMin, cell.endMin)} · ${
                                                    online
                                                        ? "Online available"
                                                        : `${cell.openRooms.length} open rooms (${cell.fittingRoomsCount} fit class size)`
                                                }`}
                                                className={`group flex min-h-[52px] flex-col justify-between rounded-xl border px-2.5 py-1.5 text-left transition-all active:scale-[0.98] ${
                                                    isSelected || isPatternHighlight
                                                        ? "border-emerald-600 bg-emerald-500/25 text-emerald-950 ring-2 ring-emerald-500/40 dark:text-emerald-50"
                                                        : "border-emerald-500/25 bg-emerald-500/[0.08] text-emerald-950 hover:border-emerald-500/55 hover:bg-emerald-500/[0.16] dark:bg-emerald-500/[0.12] dark:text-emerald-100"
                                                }`}
                                            >
                                                <div className="flex items-center gap-1">
                                                    <CheckCircle2 className="size-3 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                    <span className="truncate text-[11px] font-semibold tabular-nums">
                                                        {online
                                                            ? "Open"
                                                            : `${cell.openRooms.length} ${cell.openRooms.length === 1 ? "room" : "rooms"}`}
                                                    </span>
                                                </div>
                                                <span className="truncate text-[10px] text-emerald-800/75 dark:text-emerald-200/75 tabular-nums">
                                                    {online
                                                        ? "Online · No room"
                                                        : cell.openRooms[0]?.name ?? "Click to place"}
                                                </span>
                                            </button>
                                        );
                                    }

                                    // Blocked Slot — calm muted tile with clear 2-word human reason
                                    const BlockerIcon =
                                        cell.blockerKind === "faculty"
                                            ? User
                                            : cell.blockerKind === "section"
                                              ? GraduationCap
                                              : Building2;

                                    return (
                                        <button
                                            key={cell.key}
                                            type="button"
                                            onClick={() => handleCellClick(cell)}
                                            title={`${cell.detailReason ?? cell.shortReason} (click to inspect or override)`}
                                            className={`flex min-h-[52px] flex-col justify-center gap-0.5 rounded-xl border px-2.5 py-1.5 text-left transition-colors ${
                                                isSelected
                                                    ? "border-amber-500/60 bg-amber-500/10 text-foreground ring-1 ring-amber-500/40"
                                                    : "border-border/40 bg-muted/30 text-muted-foreground/85 hover:bg-muted/55"
                                            }`}
                                        >
                                            <span className="flex items-center gap-1 truncate text-[11px] font-medium">
                                                <BlockerIcon className="size-3 shrink-0 opacity-65" />
                                                <span className="truncate">{cell.shortReason}</span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Inline Quick-Confirm Placement Tray (appears smoothly at bottom when any slot is clicked) */}
            {selectedCell && (
                <div className="bg-card/95 border-border/80 z-20 border-t px-5 py-3.5 shadow-lg backdrop-blur-md">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                {selectedCell.status === "open" ? (
                                    <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                                        {relocatingSession ? "Move here" : "Guaranteed Open"}
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="border-amber-500/50 bg-amber-500/10 text-amber-800 dark:text-amber-300">
                                        Blocked · Override allowed
                                    </Badge>
                                )}
                                <span className="text-sm font-semibold tabular-nums">
                                    {activePatternDays.map((d) => d.slice(0, 3)).join(" / ")} ·{" "}
                                    {formatTimeRange(selectedCell.startMin, selectedCell.endMin)}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {selectedCell.status === "open"
                                    ? online
                                        ? "Ready to place as an Online session — no room required."
                                        : "Pick any open room below to confirm in one click:"
                                    : selectedCell.detailReason}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            {/* Multi-day pattern pills when adding a new session */}
                            {!relocatingSession && selectedCell.status === "open" && patternOptions && (
                                <div className="bg-muted/70 inline-flex items-center rounded-lg p-0.5 text-[11px] font-medium">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedPattern("single")}
                                        className={`rounded-md px-2 py-1 transition-colors ${
                                            selectedPattern === "single"
                                                ? "bg-background text-foreground shadow-xs"
                                                : "text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        Just {selectedCell.day.slice(0, 3)}
                                    </button>
                                    {patternOptions.mwf.allOpen && (
                                        <button
                                            type="button"
                                            onClick={() => setSelectedPattern("mwf")}
                                            className={`rounded-md px-2 py-1 transition-colors ${
                                                selectedPattern === "mwf"
                                                    ? "bg-background text-emerald-700 shadow-xs dark:text-emerald-300"
                                                    : "text-muted-foreground hover:text-foreground"
                                            }`}
                                        >
                                            M / W / F ✓
                                        </button>
                                    )}
                                    {patternOptions.tth.allOpen && (
                                        <button
                                            type="button"
                                            onClick={() => setSelectedPattern("tth")}
                                            className={`rounded-md px-2 py-1 transition-colors ${
                                                selectedPattern === "tth"
                                                    ? "bg-background text-emerald-700 shadow-xs dark:text-emerald-300"
                                                    : "text-muted-foreground hover:text-foreground"
                                            }`}
                                        >
                                            T / Th ✓
                                        </button>
                                    )}
                                </div>
                            )}

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={() => setSelectedCell(null)}
                                aria-label="Close placement bar"
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    </div>

                    {/* 1-Click Room / Online Action Chips */}
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {online ? (
                            <Button
                                type="button"
                                size="sm"
                                className="h-8 gap-1.5 rounded-xl"
                                disabled={submittingKey !== null}
                                onClick={() => void commitPlacement(null, "online")}
                            >
                                {submittingKey === "online" ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Monitor className="size-3.5" />
                                )}
                                <span>
                                    {relocatingSession ? "Move session here (Online)" : "Place Online session"}
                                </span>
                                <ArrowRight className="size-3.5" />
                            </Button>
                        ) : activeRoomChoices.length > 0 ? (
                            <>
                                {activeRoomChoices.slice(0, 5).map((room) => (
                                    <Button
                                        key={room.id}
                                        type="button"
                                        size="sm"
                                        variant={selectedCell.status === "open" ? "secondary" : "outline"}
                                        disabled={submittingKey !== null}
                                        onClick={() => void commitPlacement(room.id, `room:${room.id}`)}
                                        className={`h-8 gap-1.5 rounded-xl text-xs ${
                                            selectedCell.status === "open"
                                                ? "border-emerald-500/35 bg-emerald-500/12 text-emerald-950 hover:bg-emerald-500/20 dark:text-emerald-100"
                                                : ""
                                        }`}
                                    >
                                        {submittingKey === `room:${room.id}` ? (
                                            <Loader2 className="size-3.5 animate-spin" />
                                        ) : (
                                            <MapPin className="size-3 text-emerald-600 dark:text-emerald-400" />
                                        )}
                                        <span className="font-semibold">{room.name}</span>
                                        <span className="text-[10px] opacity-75 tabular-nums">Cap {room.capacity}</span>
                                    </Button>
                                ))}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={submittingKey !== null}
                                    onClick={() => void commitPlacement(null, "no-room")}
                                    className="text-muted-foreground h-8 rounded-xl text-xs"
                                >
                                    Place without room
                                </Button>
                            </>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={submittingKey !== null}
                                onClick={() => void commitPlacement(null, "override")}
                                className="h-8 gap-1.5 rounded-xl text-xs"
                            >
                                Place anyway (no room)
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* Bottom Quiet Status Bar */}
            {!selectedCell && (
                <div className="bg-muted/20 border-border/50 text-muted-foreground flex flex-wrap items-center justify-between gap-2 border-t px-5 py-2 text-[11px]">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="inline-flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400 tabular-nums">
                            <span className="size-2 rounded-full bg-emerald-500" />
                            {grid.openSlotCount} guaranteed open slots
                        </span>
                        <span className="inline-flex items-center gap-1.5 tabular-nums">
                            <span className="size-2 rounded-full bg-indigo-500" />
                            {targetClass.schedules.length} current {targetClass.schedules.length === 1 ? "session" : "sessions"}
                        </span>
                        <span className="inline-flex items-center gap-1.5 tabular-nums">
                            <span className="bg-muted-foreground/40 size-2 rounded-full" />
                            {grid.blockedByFacultyCount + grid.blockedBySectionCount + grid.blockedByRoomCount} blocked
                        </span>
                    </div>
                    <span className="hidden sm:inline">
                        Click any green tile to place · Click an existing session to relocate · Ctrl+Z to undo
                    </span>
                </div>
            )}
        </div>
    );
}
