import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { edit as editClass, show as showClass } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { ArrowUpRight, GripVertical, Monitor, Plus, Search, X } from "lucide-react";
import { useMemo } from "react";
import { deliveryModeShortLabel, isOnlineDeliveryMode, meetingDeliverySummary } from "../../classes/lib/class-defaults";
import { scheduledHours } from "../../components/scheduling-readiness";
import type { ClassScheduleData } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";

type BacklogRailProps = {
    studio: ScheduleStudioController;
    onStartBacklogDrag: (classItem: ClassScheduleData, event: React.PointerEvent) => void;
};

export default function BacklogRail({ studio, onStartBacklogDrag }: BacklogRailProps) {
    const needsTimesPredicate = (item: ClassScheduleData) =>
        scheduledHours(item.schedules) < (item.required_hours ?? 3) || item.schedules.length === 0;

    const filteredClasses = useMemo(() => {
        const q = studio.search.trim().toLowerCase();
        return studio.visibleClasses.filter((item) => {
            if (studio.railTab === "needs_times" && !needsTimesPredicate(item)) return false;
            if (studio.railTab === "drafts" && !item.schedules.some((s) => s.status === "draft")) return false;
            if (!q) return true;
            return `${item.subject_code} ${item.subject_title} ${item.section} ${item.faculty_name ?? ""} ${item.courses ?? ""}`
                .toLowerCase()
                .includes(q);
        });
    }, [studio.visibleClasses, studio.railTab, studio.search]);

    const needsTimesVisibleCount = useMemo(
        () => studio.visibleClasses.filter(needsTimesPredicate).length,
        [studio.visibleClasses],
    );

    const draftsVisibleCount = useMemo(
        () => studio.visibleClasses.filter((c) => c.schedules.some((s) => s.status === "draft")).length,
        [studio.visibleClasses],
    );

    return (
        <aside
            aria-label="Class backlog rail"
            className="bg-card border-border/70 flex h-full w-full flex-col overflow-hidden rounded-xl border lg:w-[300px] xl:w-[320px] lg:shrink-0"
        >
            {/* Header & Tabs */}
            <div className="border-border/60 space-y-2.5 border-b p-3">
                <div className="flex items-center justify-between gap-2">
                    <div>
                        <h2 className="text-sm font-semibold tracking-tight">Classes</h2>
                        <p className="text-muted-foreground text-[11px]">Drag onto grid or click + to schedule</p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 lg:hidden"
                        onClick={() => studio.setRailOpen(false)}
                        aria-label="Close class rail"
                    >
                        <X className="size-4" />
                    </Button>
                </div>

                {/* Search */}
                <div className="relative">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" aria-hidden="true" />
                    <Input
                        type="search"
                        value={studio.search}
                        onChange={(event) => studio.setSearch(event.target.value)}
                        placeholder="Find subject, section, faculty…"
                        className="h-8 pl-8 text-xs"
                        aria-label="Search classes"
                    />
                </div>

                {/* Rail Tabs */}
                <div className="bg-muted/70 grid grid-cols-3 rounded-lg p-0.5 text-[11px] font-medium">
                    <button
                        type="button"
                        onClick={() => studio.setRailTab("needs_times")}
                        className={`rounded-md px-2 py-1 transition-colors tabular-nums ${
                            studio.railTab === "needs_times"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        Needs ({needsTimesVisibleCount})
                    </button>
                    <button
                        type="button"
                        onClick={() => studio.setRailTab("all")}
                        className={`rounded-md px-2 py-1 transition-colors tabular-nums ${
                            studio.railTab === "all"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        All ({studio.visibleClasses.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => studio.setRailTab("drafts")}
                        className={`rounded-md px-2 py-1 transition-colors tabular-nums ${
                            studio.railTab === "drafts"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        Drafts ({draftsVisibleCount})
                    </button>
                </div>
            </div>

            {/* Scrollable Class Cards */}
            <div className="divide-border/50 flex-1 divide-y overflow-y-auto">
                {filteredClasses.length === 0 ? (
                    <div className="space-y-2 px-4 py-10 text-center">
                        <p className="text-sm font-medium">
                            {studio.railTab === "needs_times"
                                ? "All visible classes have their hours placed"
                                : "No classes match this filter"}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            {studio.railTab === "needs_times"
                                ? "Switch to All to adjust existing sessions or generate offerings."
                                : "Try clearing the search query or filters."}
                        </p>
                        {studio.railTab !== "all" && (
                            <Button type="button" size="sm" variant="outline" className="mt-1 h-7 text-xs" onClick={() => studio.setRailTab("all")}>
                                View all classes
                            </Button>
                        )}
                    </div>
                ) : (
                    filteredClasses.map((item) => {
                        const scheduled = scheduledHours(item.schedules);
                        const required = Math.max(1, item.required_hours ?? 3);
                        const pct = Math.min(100, Math.round((scheduled / required) * 100));
                        const isComplete = scheduled >= required && item.schedules.length > 0;
                        const hasConflict = item.schedules.some(
                            (s) => s.id !== undefined && studio.conflictScheduleIdSet.has(s.id),
                        );
                        const summary = meetingDeliverySummary(item.schedules, item.delivery_mode);
                        const isOnline = isOnlineDeliveryMode(summary);
                        const isDraggingThis = studio.draggingBacklog?.classItem.id === item.id;

                        return (
                            <div
                                key={item.id}
                                className={`group relative px-3 py-2.5 transition-colors ${
                                    isDraggingThis ? "bg-primary/5 ring-primary/40 ring-1" : "hover:bg-muted/35"
                                }`}
                            >
                                <div className="flex items-start gap-2">
                                    {/* Drag Handle */}
                                    <button
                                        type="button"
                                        onPointerDown={(event) => onStartBacklogDrag(item, event)}
                                        title="Drag onto Week Canvas to place a session"
                                        aria-label={`Drag ${item.subject_code} Section ${item.section} onto calendar`}
                                        className="text-muted-foreground hover:text-foreground hover:bg-muted mt-0.5 flex size-6 shrink-0 cursor-grab items-center justify-center rounded-md transition-colors active:cursor-grabbing"
                                    >
                                        <GripVertical className="size-3.5" aria-hidden="true" />
                                    </button>

                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex items-center justify-between gap-1.5">
                                            <div className="flex min-w-0 items-center gap-1.5">
                                                <Link
                                                    href={showClass.url(item.id)}
                                                    className="hover:text-primary truncate text-xs font-semibold tracking-tight hover:underline"
                                                >
                                                    {item.subject_code}
                                                </Link>
                                                <Badge variant="outline" className="h-4 px-1.5 text-[10px] font-medium">
                                                    Sec {item.section}
                                                </Badge>
                                                {hasConflict && (
                                                    <span
                                                        className="size-2 shrink-0 rounded-full bg-red-500"
                                                        title="Has scheduling conflict"
                                                        aria-label="Has conflict"
                                                    />
                                                )}
                                            </div>
                                            <div className="flex items-center gap-0.5">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6"
                                                    title="Add class times"
                                                    aria-label={`Add times for ${item.subject_code}`}
                                                    onClick={() => studio.setEditorTarget({ classId: item.id })}
                                                >
                                                    <Plus className="size-3.5" aria-hidden="true" />
                                                </Button>
                                                <Link
                                                    href={editClass.url(item.id)}
                                                    title="Edit class record"
                                                    aria-label={`Open class record for ${item.subject_code}`}
                                                    className="text-muted-foreground hover:text-foreground hover:bg-muted inline-flex size-6 items-center justify-center rounded-md transition-colors"
                                                >
                                                    <ArrowUpRight className="size-3" aria-hidden="true" />
                                                </Link>
                                            </div>
                                        </div>

                                        <p className="text-muted-foreground truncate text-[11px]">{item.subject_title}</p>

                                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
                                            <span className="truncate">{item.faculty_name ?? "Unassigned faculty"}</span>
                                            {isOnline && (
                                                <span className="inline-flex items-center gap-0.5 text-sky-700 dark:text-sky-300">
                                                    <Monitor className="size-3" /> {deliveryModeShortLabel(summary)}
                                                </span>
                                            )}
                                        </div>

                                        {/* Hours Progress Bar */}
                                        <div className="flex items-center gap-2 pt-0.5">
                                            <div className="bg-muted h-1.5 flex-1 overflow-hidden rounded-full">
                                                <div
                                                    className={`h-full rounded-full transition-[width] duration-200 ${
                                                        hasConflict
                                                            ? "bg-red-500"
                                                            : isComplete
                                                              ? "bg-emerald-500"
                                                              : scheduled > 0
                                                                ? "bg-amber-500"
                                                                : "bg-muted-foreground/30"
                                                    }`}
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                            <span className="text-muted-foreground text-[10px] font-medium tabular-nums">
                                                {Number(scheduled.toFixed(1))}/{required}h
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
        </aside>
    );
}
