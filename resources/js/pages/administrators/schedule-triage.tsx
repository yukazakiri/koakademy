import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { show as showClass } from "@/routes/administrators/classes";
import { index as boardIndex } from "@/routes/administrators/schedule-board";
import { index as floorboardIndex } from "@/routes/administrators/schedule-floorboard";
import { index as xrayIndex } from "@/routes/administrators/schedule-xray";
import { index as studioIndex } from "@/routes/administrators/scheduling-analytics";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock,
    Download,
    GraduationCap,
    LayoutGrid,
    Loader2,
    MapPin,
    Monitor,
    MoreHorizontal,
    Plus,
    Redo2,
    Send,
    SkipForward,
    SlidersHorizontal,
    Sparkles,
    Sun,
    Undo2,
    User,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import GenerateOfferingsDialog from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingPolicyDialog from "./components/scheduling-policy-dialog";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import SchedulingSessionDialog from "./components/scheduling-session-dialog";
import type { SchedulingAnalyticsProps, SuggestedRoom, SuggestedSlot } from "./components/scheduling-types";
import { buildTriageQueue, type TriageCategory, type TriageIssue } from "./schedule-triage/lib/triage-engine";
import { useScheduleStudio } from "./scheduling/hooks/use-schedule-studio";
import { formatTimeRange } from "./scheduling/lib/time-geometry";

type QueueFilter = "all" | TriageCategory;

type TriageAction =
    | { key: string; kind: "room"; title: string; subtitle: string; badge: string; room: SuggestedRoom }
    | { key: string; kind: "slot"; title: string; subtitle: string; badge: string; slot: SuggestedSlot }
    | { key: string; kind: "online"; title: string; subtitle: string; badge: string };

export default function ScheduleTriage(props: SchedulingAnalyticsProps) {
    const { user, term_context, filters, creation_options } = props;
    const studio = useScheduleStudio(props);

    const [queueFilter, setQueueFilter] = useState<QueueFilter>("all");
    const [selectedIssueId, setSelectedIssueId] = useState<string | null>(null);
    const [skippedIds, setSkippedIds] = useState<Set<string>>(new Set());
    const [selectedSessionIdx, setSelectedSessionIdx] = useState(0);
    const [applyingKey, setApplyingKey] = useState<string | null>(null);
    const [resolvedSessionCount, setResolvedSessionCount] = useState(0);

    const triage = useMemo(
        () => buildTriageQueue(studio.classes, studio.conflicts),
        [studio.classes, studio.conflicts],
    );

    const filteredIssues = useMemo(() => {
        return triage.issues.filter((issue) => {
            if (skippedIds.has(issue.id)) return false;
            if (queueFilter !== "all" && issue.category !== queueFilter) return false;
            return true;
        });
    }, [triage.issues, queueFilter, skippedIds]);

    const activeIssueIndex = useMemo(() => {
        if (filteredIssues.length === 0) return -1;
        if (selectedIssueId) {
            const idx = filteredIssues.findIndex((i) => i.id === selectedIssueId);
            if (idx >= 0) return idx;
        }
        return 0;
    }, [filteredIssues, selectedIssueId]);

    const activeIssue: TriageIssue | null = activeIssueIndex >= 0 ? filteredIssues[activeIssueIndex] : null;

    // Reset chosen class-to-move when switching issues
    useEffect(() => {
        setSelectedSessionIdx(activeIssue && activeIssue.sessions.length > 1 ? 1 : 0);
    }, [activeIssue?.id]);

    const targetInvolved = activeIssue?.sessions[selectedSessionIdx] ?? activeIssue?.sessions[0] ?? null;
    const targetScheduleId = targetInvolved?.session.id;

    // Auto-fetch 1-click suggestions for the currently selected session in the active issue
    useEffect(() => {
        if (targetScheduleId !== undefined) {
            void studio.loadSuggestionsForSchedule(targetScheduleId);
        }
    }, [targetScheduleId, studio.loadSuggestionsForSchedule]);

    const suggestions = targetScheduleId !== undefined ? studio.suggestionsCache[targetScheduleId] : undefined;
    const loadingSuggestions = targetScheduleId !== undefined && studio.loadingSuggestionsFor === targetScheduleId;

    // Build clean numbered 1-click action cards ([1], [2], [3], [4])
    const actions: TriageAction[] = useMemo(() => {
        if (!targetInvolved || !suggestions) return [];
        const list: TriageAction[] = [];

        // If this is primarily a room clash, lead with the top 2 free rooms
        if (activeIssue?.category === "room") {
            for (const room of suggestions.available_rooms.slice(0, 2)) {
                list.push({
                    key: `room:${room.room_id}`,
                    kind: "room",
                    title: `Move ${targetInvolved.classItem.subject_code} to ${room.name}`,
                    subtitle: `${room.reason} · Keeps ${activeIssue.day} ${formatTimeRange(targetInvolved.startMin, targetInvolved.endMin)}`,
                    badge: `Cap ${room.capacity}`,
                    room,
                });
            }
        }

        // Top conflict-free time slots (always works for faculty, section, AND room clashes!)
        for (const slot of suggestions.available_slots.slice(0, activeIssue?.category === "room" ? 2 : 3)) {
            list.push({
                key: `slot:${slot.day_of_week}:${slot.start_time}`,
                kind: "slot",
                title: `Move ${targetInvolved.classItem.subject_code} to ${slot.day_of_week} · ${slot.time_range}`,
                subtitle: `${slot.reason}${slot.room_name ? ` · ${slot.room_name}` : ""}`,
                badge: slot.same_day ? "Same day" : slot.day_of_week.slice(0, 3),
                slot,
            });
        }

        if (suggestions.can_convert_to_online) {
            list.push({
                key: "online",
                kind: "online",
                title: `Switch ${targetInvolved.classItem.subject_code} to Online`,
                subtitle: "Frees the physical room immediately and clears room clashes",
                badge: "Online",
            });
        }

        return list.slice(0, 4);
    }, [targetInvolved, suggestions, activeIssue]);

    const executeAction = useCallback(
        async (action: TriageAction) => {
            if (!targetInvolved || applyingKey !== null) return;
            setApplyingKey(action.key);
            try {
                if (action.kind === "room") {
                    await studio.updateScheduleBlockDirect(
                        targetInvolved.classItem,
                        targetInvolved.session,
                        { room_id: action.room.room_id, delivery_mode: "face_to_face" },
                        `Moved ${targetInvolved.classItem.subject_code} to ${action.room.name}`,
                    );
                } else if (action.kind === "slot") {
                    await studio.updateScheduleBlockDirect(
                        targetInvolved.classItem,
                        targetInvolved.session,
                        {
                            day_of_week: action.slot.day_of_week,
                            start_time: action.slot.start_time,
                            end_time: action.slot.end_time,
                            room_id: action.slot.room_id,
                        },
                        `Moved ${targetInvolved.classItem.subject_code} to ${action.slot.day_of_week} ${action.slot.time_range}`,
                    );
                } else {
                    await studio.updateScheduleBlockDirect(
                        targetInvolved.classItem,
                        targetInvolved.session,
                        { delivery_mode: studio.policy.default_online_delivery_mode, room_id: null },
                        `Switched ${targetInvolved.classItem.subject_code} to Online`,
                    );
                }
                setResolvedSessionCount((n) => n + 1);
            } finally {
                setApplyingKey(null);
            }
        },
        [targetInvolved, applyingKey, studio],
    );

    const stepIssue = useCallback(
        (delta: number) => {
            if (filteredIssues.length === 0) return;
            const nextIdx = Math.max(0, Math.min(filteredIssues.length - 1, activeIssueIndex + delta));
            setSelectedIssueId(filteredIssues[nextIdx].id);
        },
        [filteredIssues, activeIssueIndex],
    );

    const skipActiveIssue = useCallback(() => {
        if (!activeIssue) return;
        setSkippedIds((prev) => new Set([...prev, activeIssue.id]));
    }, [activeIssue]);

    // Superhuman-style keyboard shortcuts: J / K navigate, 1..4 apply fix, S skip
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const el = event.target as HTMLElement | null;
            if (el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.isContentEditable)) return;
            if (event.ctrlKey || event.metaKey || event.altKey) return;

            const key = event.key.toLowerCase();
            if (key === "j") {
                event.preventDefault();
                stepIssue(1);
            } else if (key === "k") {
                event.preventDefault();
                stepIssue(-1);
            } else if (key === "s") {
                event.preventDefault();
                skipActiveIssue();
            } else if (["1", "2", "3", "4"].includes(key)) {
                const action = actions[Number(key) - 1];
                if (action) {
                    event.preventDefault();
                    void executeAction(action);
                }
            }
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [stepIssue, skipActiveIssue, actions, executeAction]);

    const editorClass = studio.editorTarget
        ? studio.classes.find((c) => c.id === studio.editorTarget?.classId)
        : undefined;
    const editorSession =
        editorClass && studio.editorTarget?.sessionId !== undefined
            ? editorClass.schedules.find((s) => s.id === studio.editorTarget?.sessionId)
            : undefined;

    const categoryBadge = (category: TriageCategory) => {
        switch (category) {
            case "room":
                return { label: "Room", cls: "bg-rose-500/12 text-rose-700 dark:text-rose-300", icon: Building2 };
            case "faculty":
                return { label: "Teacher", cls: "bg-amber-500/15 text-amber-800 dark:text-amber-300", icon: User };
            case "section":
                return { label: "Section", cls: "bg-violet-500/12 text-violet-700 dark:text-violet-300", icon: GraduationCap };
            case "missing_time":
                return { label: "Needs Time", cls: "bg-sky-500/12 text-sky-700 dark:text-sky-300", icon: Clock };
        }
    };

    return (
        <AdminLayout user={user} title="Schedule Triage">
            <Head title="Schedule Triage" />

            <div className="mx-auto flex h-[calc(100dvh-4.5rem)] min-h-[660px] max-w-[1480px] flex-col gap-4 p-4 sm:p-5">
                {/* 1. Calm Top Progress Header (Inbox-Zero Health Meter) */}
                <header className="bg-card/85 border-border/60 flex flex-wrap items-center justify-between gap-4 rounded-2xl border px-5 py-3.5 shadow-2xs">
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="space-y-0.5">
                            <h1 className="text-lg font-bold tracking-tight">Schedule Triage</h1>
                            <p className="text-muted-foreground text-xs tabular-nums">
                                {triage.rawConflictsCount > 0
                                    ? `${triage.rawConflictsCount} raw overlaps distilled into ${triage.rootIssuesCount} clear decisions`
                                    : "Every conflict cleared — term schedule is ready to publish"}
                            </p>
                        </div>

                        <div className="bg-border/60 hidden h-8 w-px md:block" />

                        {/* Health Meter */}
                        <div className="flex items-center gap-3">
                            <div className="w-32 space-y-1">
                                <div className="flex items-center justify-between text-[11px] font-medium tabular-nums">
                                    <span className="text-muted-foreground">Term health</span>
                                    <span>{triage.healthScorePct}%</span>
                                </div>
                                <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                    <div
                                        className={`h-full rounded-full transition-[width] duration-300 ${
                                            triage.healthScorePct >= 90
                                                ? "bg-emerald-500"
                                                : triage.healthScorePct >= 50
                                                  ? "bg-amber-500"
                                                  : "bg-rose-500"
                                        }`}
                                        style={{ width: `${Math.max(4, triage.healthScorePct)}%` }}
                                    />
                                </div>
                            </div>
                            {resolvedSessionCount > 0 && (
                                <Badge variant="secondary" className="bg-emerald-500/12 text-emerald-700 dark:text-emerald-300 tabular-nums">
                                    {resolvedSessionCount} fixed this session
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 rounded-xl text-xs"
                            disabled={!studio.history.canUndo}
                            onClick={() => void studio.history.undo()}
                        >
                            <Undo2 className="size-3.5" /> Undo
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 gap-1.5 rounded-xl text-xs"
                            onClick={() => studio.setReviewOpen(true)}
                        >
                            <Send className="size-3.5" /> Review &amp; Publish
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="outline" size="icon" className="size-8 rounded-xl" aria-label="More triage options">
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <DropdownMenuItem disabled={!studio.history.canRedo} onSelect={() => void studio.history.redo()} className="flex items-center gap-2">
                                    <Redo2 className="size-3.5" /> Redo (Ctrl+Shift+Z)
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => void studio.toggleSundayScheduling()} className="flex items-center justify-between gap-2">
                                    <span className="flex items-center gap-2">
                                        <Sun className="size-3.5" /> Sunday scheduling
                                    </span>
                                    <Badge variant="outline" className="text-[10px]">
                                        {studio.policy.allow_sunday_scheduling ? "On" : "Off"}
                                    </Badge>
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setPolicyOpen(true)} className="flex items-center gap-2">
                                    <SlidersHorizontal className="size-3.5" /> Conflict rules
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setExportOpen(true)} className="flex items-center gap-2">
                                    <Download className="size-3.5" /> Export PDF timetable
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="text-[11px]">Other Schedule Concepts</DropdownMenuLabel>
                                <DropdownMenuItem asChild>
                                    <Link href={floorboardIndex.url()} className="flex items-center gap-2">
                                        <Building2 className="size-3.5" /> Campus Floorboard
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={xrayIndex.url()} className="flex items-center gap-2">
                                        <Sparkles className="size-3.5" /> Schedule X-Ray
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={boardIndex.url()} className="flex items-center gap-2">
                                        <CalendarDays className="size-3.5" /> Schedule Board
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={studioIndex.url()} className="flex items-center gap-2">
                                        <LayoutGrid className="size-3.5" /> Multi-Lens Studio
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                {/* 2. Split Inbox Queue + Single-Decision Editorial Stage */}
                <div className="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row">
                    {/* Left Triage Inbox */}
                    <aside
                        aria-label="Root-cause issue queue"
                        className="bg-card/75 border-border/60 flex h-full w-full flex-col overflow-hidden rounded-2xl border lg:w-[356px] lg:shrink-0"
                    >
                        <div className="border-border/50 space-y-2 border-b p-2.5">
                            <div className="grid grid-cols-4 gap-1">
                                {(
                                    [
                                        { id: "all", label: `All ${triage.rootIssuesCount}` },
                                        { id: "room", label: `Rooms ${triage.countsByCategory.room}` },
                                        { id: "faculty", label: `Faculty ${triage.countsByCategory.faculty}` },
                                        { id: "section", label: `Sections ${triage.countsByCategory.section}` },
                                    ] as Array<{ id: QueueFilter; label: string }>
                                ).map((tab) => (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        onClick={() => setQueueFilter(tab.id)}
                                        className={`truncate rounded-lg px-1.5 py-1 text-center text-[11px] font-medium transition-colors tabular-nums ${
                                            queueFilter === tab.id
                                                ? "bg-foreground text-background"
                                                : "bg-muted/60 text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                            {skippedIds.size > 0 && (
                                <button
                                    type="button"
                                    onClick={() => setSkippedIds(new Set())}
                                    className="text-primary text-[11px] font-medium hover:underline"
                                >
                                    Show {skippedIds.size} skipped issue(s) again
                                </button>
                            )}
                        </div>

                        <div className="flex-1 space-y-1.5 overflow-y-auto p-2">
                            {filteredIssues.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 py-14 text-center">
                                    <CheckCircle2 className="size-8 text-emerald-600" />
                                    <p className="text-sm font-semibold">Inbox Zero!</p>
                                    <p className="text-muted-foreground max-w-[220px] text-xs">
                                        No remaining issues in this queue.
                                    </p>
                                </div>
                            ) : (
                                filteredIssues.map((issue) => {
                                    const active = activeIssue?.id === issue.id;
                                    const badge = categoryBadge(issue.category);
                                    const Icon = badge.icon;

                                    return (
                                        <button
                                            key={issue.id}
                                            type="button"
                                            onClick={() => setSelectedIssueId(issue.id)}
                                            className={`w-full rounded-xl border p-3 text-left transition-all ${
                                                active
                                                    ? "bg-foreground text-background border-foreground shadow-sm"
                                                    : "bg-background/80 hover:bg-muted/50 border-border/50 text-foreground"
                                            }`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold ${
                                                        active ? "bg-background/15 text-background" : badge.cls
                                                    }`}
                                                >
                                                    <Icon className="size-3" />
                                                    {badge.label}
                                                </span>
                                                <span
                                                    className={`text-[10px] font-semibold tabular-nums ${
                                                        active ? "text-emerald-300" : "text-muted-foreground"
                                                    }`}
                                                >
                                                    {issue.category === "missing_time"
                                                        ? issue.missingHoursLabel
                                                        : `Clears ${issue.impactCount} ${issue.impactCount === 1 ? "overlap" : "overlaps"}`}
                                                </span>
                                            </div>
                                            <p className="mt-1.5 line-clamp-2 text-xs font-semibold leading-normal">
                                                {issue.headline}
                                            </p>
                                            {issue.sessions.length > 0 && (
                                                <p
                                                    className={`mt-1 truncate text-[11px] ${
                                                        active ? "text-background/70" : "text-muted-foreground"
                                                    }`}
                                                >
                                                    {issue.sessions.map((s) => `${s.classItem.subject_code} (${s.classItem.section})`).join(" vs ")}
                                                </p>
                                            )}
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </aside>

                    {/* Center Editorial Decision Stage */}
                    <main className="bg-card/90 border-border/60 flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border shadow-2xs">
                        {!activeIssue ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center">
                                <div className="flex size-16 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-8" />
                                </div>
                                <h2 className="text-xl font-bold tracking-tight">All Clear — Zero Issues Remaining</h2>
                                <p className="text-muted-foreground max-w-md text-sm">
                                    Every room, teacher, and student cohort is conflict-free. You are ready to publish this term&apos;s schedule.
                                </p>
                                <Button type="button" onClick={() => studio.setReviewOpen(true)} className="mt-2 rounded-xl">
                                    <Send className="mr-2 size-4" /> Review &amp; Publish Schedule
                                </Button>
                            </div>
                        ) : (
                            <>
                                {/* Issue Navigation Strip */}
                                <div className="border-border/50 flex items-center justify-between gap-2 border-b px-6 py-3">
                                    <div className="flex items-center gap-2 text-xs">
                                        <span className="font-semibold tabular-nums">
                                            Decision {activeIssueIndex + 1} of {filteredIssues.length}
                                        </span>
                                        <span className="text-muted-foreground hidden sm:inline">
                                            · Keys: <kbd className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">J</kbd> /{" "}
                                            <kbd className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">K</kbd> navigate ·{" "}
                                            <kbd className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">1</kbd>–
                                            <kbd className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">3</kbd> apply ·{" "}
                                            <kbd className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">S</kbd> skip
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1 rounded-lg px-2 text-xs"
                                            disabled={activeIssueIndex <= 0}
                                            onClick={() => stepIssue(-1)}
                                        >
                                            <ArrowLeft className="size-3" /> Prev
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1 rounded-lg px-2 text-xs"
                                            disabled={activeIssueIndex >= filteredIssues.length - 1}
                                            onClick={() => stepIssue(1)}
                                        >
                                            Next <ArrowRight className="size-3" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground h-7 gap-1 rounded-lg px-2 text-xs"
                                            onClick={skipActiveIssue}
                                        >
                                            <SkipForward className="size-3" /> Skip
                                        </Button>
                                    </div>
                                </div>

                                {/* Decision Body */}
                                <div className="flex-1 overflow-y-auto px-5 py-4 sm:px-7">
                                    <div className="mx-auto max-w-2xl space-y-4">
                                        {/* Big Human Headline */}
                                        <div className="space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {(() => {
                                                    const badge = categoryBadge(activeIssue.category);
                                                    const Icon = badge.icon;
                                                    return (
                                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${badge.cls}`}>
                                                            <Icon className="size-3" />
                                                            {badge.label} Issue
                                                        </span>
                                                    );
                                                })()}
                                                {activeIssue.category !== "missing_time" && (
                                                    <span className="bg-muted text-muted-foreground rounded-full px-2.5 py-0.5 text-[11px] font-medium tabular-nums">
                                                        Resolving clears {activeIssue.impactCount}{" "}
                                                        {activeIssue.impactCount === 1 ? "conflict" : "conflicts"}
                                                    </span>
                                                )}
                                            </div>
                                            <h2 className="text-xl font-bold tracking-tight text-balance">
                                                {activeIssue.headline}
                                            </h2>
                                            <p className="text-muted-foreground text-xs">{activeIssue.subline}</p>
                                        </div>

                                        {activeIssue.category === "missing_time" && activeIssue.missingClass ? (
                                            <div className="bg-muted/25 border-border/70 space-y-4 rounded-2xl border p-5">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <p className="text-base font-semibold">
                                                            {activeIssue.missingClass.subject_code} · Sec {activeIssue.missingClass.section}
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">
                                                            {activeIssue.missingClass.subject_title}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        className="gap-1.5 rounded-xl"
                                                        onClick={() =>
                                                            studio.setEditorTarget({ classId: activeIssue.missingClass!.id })
                                                        }
                                                    >
                                                        <Plus className="size-4" /> Add Class Time
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <>
                                                {/* Mini Visual Overlap Comparison (Bounded height so 1-Click Actions stay above the fold) */}
                                                <div className="bg-muted/25 border-border/60 space-y-2 rounded-2xl border p-3">
                                                    <div className="flex items-center justify-between text-[11px] font-medium text-muted-foreground">
                                                        <span>
                                                            Click which class to move ({activeIssue.sessions.length} competing):
                                                        </span>
                                                        <span className="tabular-nums">
                                                            {activeIssue.day} · {formatTimeRange(activeIssue.windowStartMin, activeIssue.windowEndMin)}
                                                        </span>
                                                    </div>

                                                    <div className="max-h-[214px] space-y-1.5 overflow-y-auto pr-0.5">
                                                        {activeIssue.sessions.map((item, idx) => {
                                                            const chosen = idx === selectedSessionIdx;
                                                            const span = Math.max(30, activeIssue.windowEndMin - activeIssue.windowStartMin);
                                                            const leftPct = ((item.startMin - activeIssue.windowStartMin) / span) * 100;
                                                            const widthPct = Math.max(18, ((item.endMin - item.startMin) / span) * 100);

                                                            return (
                                                                <button
                                                                    key={item.session.id ?? idx}
                                                                    type="button"
                                                                    onClick={() => setSelectedSessionIdx(idx)}
                                                                    className={`w-full rounded-xl border px-3 py-2 text-left transition-all ${
                                                                        chosen
                                                                            ? "bg-background border-foreground ring-foreground/20 shadow-xs ring-1"
                                                                            : "bg-background/60 border-border/60 hover:bg-background"
                                                                    }`}
                                                                >
                                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <span className="text-xs font-bold">
                                                                                {item.classItem.subject_code}
                                                                            </span>
                                                                            <Badge variant="secondary" className="h-4.5 px-1.5 text-[10px]">
                                                                                Sec {item.classItem.section}
                                                                            </Badge>
                                                                            {chosen && (
                                                                                <Badge className="h-4.5 px-1.5 text-[10px]">Moving this</Badge>
                                                                            )}
                                                                        </div>
                                                                        <div className="text-muted-foreground flex items-center gap-2.5 text-[11px]">
                                                                            <span className="tabular-nums">
                                                                                {formatTimeRange(item.startMin, item.endMin)}
                                                                            </span>
                                                                            <span>{item.session.room ?? "No room"}</span>
                                                                            <Link
                                                                                href={showClass.url(item.classItem.id)}
                                                                                onClick={(e) => e.stopPropagation()}
                                                                                className="text-primary hover:underline"
                                                                                title="Open class record"
                                                                            >
                                                                                <ArrowUpRight className="size-3.5" />
                                                                            </Link>
                                                                        </div>
                                                                    </div>

                                                                    {/* Mini horizontal overlap bar */}
                                                                    <div className="bg-muted/80 relative mt-1.5 h-1.5 overflow-hidden rounded-full">
                                                                        <div
                                                                            style={{ left: `${leftPct}%`, width: `${widthPct}%` }}
                                                                            className={`absolute top-0 bottom-0 rounded-full ${
                                                                                chosen ? "bg-foreground" : "bg-rose-500/75"
                                                                            }`}
                                                                        />
                                                                    </div>
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                </div>

                                                {/* Numbered 1-Click Action Deck */}
                                                <div className="space-y-2">
                                                    <h3 className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                        <Sparkles className="text-primary size-3.5" />
                                                        Choose a 1-Click Resolution
                                                    </h3>

                                                    {loadingSuggestions ? (
                                                        <div className="text-muted-foreground flex items-center gap-2 rounded-2xl border p-5 text-xs">
                                                            <Loader2 className="size-4 animate-spin" />
                                                            Calculating best conflict-free resolutions…
                                                        </div>
                                                    ) : actions.length > 0 ? (
                                                        <div className="space-y-1.5">
                                                            {actions.map((action, idx) => {
                                                                const busy = applyingKey === action.key;
                                                                const ActionIcon =
                                                                    action.kind === "room"
                                                                        ? MapPin
                                                                        : action.kind === "slot"
                                                                          ? Clock
                                                                          : Monitor;

                                                                return (
                                                                    <button
                                                                        key={action.key}
                                                                        type="button"
                                                                        disabled={applyingKey !== null}
                                                                        onClick={() => void executeAction(action)}
                                                                        className={`group flex w-full items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-left transition-all active:scale-[0.99] ${
                                                                            idx === 0
                                                                                ? "border-emerald-500/45 bg-emerald-500/[0.06] hover:bg-emerald-500/[0.12]"
                                                                                : "bg-background border-border/70 hover:border-foreground/40 hover:bg-muted/30"
                                                                        }`}
                                                                    >
                                                                        <div className="flex min-w-0 items-center gap-3">
                                                                            <kbd
                                                                                className={`flex size-6 shrink-0 items-center justify-center rounded-md font-mono text-xs font-bold ${
                                                                                    idx === 0
                                                                                        ? "bg-emerald-600 text-white"
                                                                                        : "bg-muted text-foreground"
                                                                                }`}
                                                                            >
                                                                                {idx + 1}
                                                                            </kbd>
                                                                            <div className="min-w-0">
                                                                                <p className="flex items-center gap-1.5 truncate text-xs font-semibold sm:text-[13px]">
                                                                                    <ActionIcon className="size-3.5 shrink-0 opacity-75" />
                                                                                    <span className="truncate">{action.title}</span>
                                                                                </p>
                                                                                <p className="text-muted-foreground truncate text-[11px]">
                                                                                    {action.subtitle}
                                                                                </p>
                                                                            </div>
                                                                        </div>

                                                                        <span className="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold">
                                                                            {busy ? (
                                                                                <Loader2 className="size-4 animate-spin" />
                                                                            ) : (
                                                                                <>
                                                                                    <Badge variant="outline" className="text-[10px]">
                                                                                        {action.badge}
                                                                                    </Badge>
                                                                                    <ArrowRight className="text-muted-foreground size-4 transition-transform group-hover:translate-x-0.5" />
                                                                                </>
                                                                            )}
                                                                        </span>
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    ) : (
                                                        <div className="bg-muted/30 rounded-2xl border p-5 text-xs text-muted-foreground">
                                                            No automatic single-step slot found for this block. Click{" "}
                                                            <button
                                                                type="button"
                                                                className="text-primary font-semibold underline"
                                                                onClick={() =>
                                                                    targetInvolved &&
                                                                    studio.setEditorTarget({
                                                                        classId: targetInvolved.classItem.id,
                                                                        sessionId: targetInvolved.session.id,
                                                                    })
                                                                }
                                                            >
                                                                edit manually
                                                            </button>{" "}
                                                            or pick the other colliding class above.
                                                        </div>
                                                    )}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </>
                        )}
                    </main>
                </div>
            </div>

            {/* Standard Dialogs */}
            {editorClass && (
                <SchedulingSessionDialog
                    key={`${editorClass.id}:${studio.editorTarget?.sessionId ?? "new"}`}
                    classItem={editorClass}
                    session={editorSession}
                    rooms={creation_options.rooms}
                    allowSunday={studio.policy.allow_sunday_scheduling}
                    onClose={() => studio.setEditorTarget(null)}
                    onSave={studio.saveEditorSession}
                    onDelete={async () => {
                        if (editorSession?.id !== undefined) {
                            await studio.deleteScheduleBlock(editorSession.id);
                        }
                    }}
                />
            )}

            <SchedulingReviewDialog
                key={`${studio.reviewOpen}-${studio.draftCount}`}
                open={studio.reviewOpen}
                onOpenChange={studio.setReviewOpen}
                classes={studio.classes}
                conflictCount={studio.conflicts.length}
                draftCount={studio.draftCount}
                termLabel={`${studio.schoolYear} · ${term_context?.semester_label ?? `Semester ${studio.semester}`}`}
                isPublishing={studio.publishing}
                onPublish={studio.publishDrafts}
                onResolveConflicts={() => studio.setReviewOpen(false)}
            />

            <GenerateOfferingsDialog
                open={studio.generateOpen}
                onOpenChange={studio.setGenerateOpen}
                schoolYear={studio.schoolYear}
                semester={studio.semester}
                courses={creation_options.courses}
                shsStrands={creation_options.shs_strands}
                rooms={creation_options.rooms}
                onGenerate={studio.generateClasses}
            />

            <ScheduleExportDialog
                open={studio.exportOpen}
                onOpenChange={studio.setExportOpen}
                courses={filters.available_courses}
                initialCourseId={null}
            />

            <SchedulingPolicyDialog
                open={studio.policyOpen}
                onOpenChange={studio.setPolicyOpen}
                policy={studio.policy}
                onSave={studio.saveSchedulingPolicy}
            />
        </AdminLayout>
    );
}
