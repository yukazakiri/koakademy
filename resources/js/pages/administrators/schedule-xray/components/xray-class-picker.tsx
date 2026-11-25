import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Check, Search } from "lucide-react";
import { useMemo, useState } from "react";
import { scheduledHours } from "../../components/scheduling-readiness";
import type { ClassScheduleData } from "../../components/scheduling-types";

type XRayClassPickerProps = {
    classes: ClassScheduleData[];
    selectedClassId: number | null;
    onSelectClass: (classItem: ClassScheduleData) => void;
    conflictScheduleIds: Set<number>;
};

type PickerFilter = "all" | "overlaps" | "needs_time";

export default function XRayClassPicker({
    classes,
    selectedClassId,
    onSelectClass,
    conflictScheduleIds,
}: XRayClassPickerProps) {
    const [query, setQuery] = useState("");
    const [filter, setFilter] = useState<PickerFilter>("all");

    const hasOverlap = (cls: ClassScheduleData) =>
        cls.schedules.some((s) => s.id !== undefined && conflictScheduleIds.has(s.id));

    const needsTime = (cls: ClassScheduleData) =>
        scheduledHours(cls.schedules) < (cls.required_hours ?? 3) || cls.schedules.length === 0;

    const counts = useMemo(() => {
        let overlaps = 0;
        let needs = 0;
        for (const cls of classes) {
            if (hasOverlap(cls)) overlaps++;
            if (needsTime(cls)) needs++;
        }
        return { all: classes.length, overlaps, needs };
    }, [classes, conflictScheduleIds]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return classes.filter((cls) => {
            if (filter === "overlaps" && !hasOverlap(cls)) return false;
            if (filter === "needs_time" && !needsTime(cls)) return false;
            if (!q) return true;
            return `${cls.subject_code} ${cls.subject_title} ${cls.section} ${cls.faculty_name ?? ""} ${cls.courses ?? ""}`
                .toLowerCase()
                .includes(q);
        });
    }, [classes, filter, query, conflictScheduleIds]);

    return (
        <aside
            aria-label="Choose a class to X-Ray"
            className="bg-card/75 border-border/60 flex h-full w-full flex-col overflow-hidden rounded-2xl border lg:w-[300px] lg:shrink-0"
        >
            <div className="border-border/50 space-y-3 border-b p-3.5">
                <div className="space-y-0.5">
                    <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        1. Pick a Class
                    </h2>
                    <p className="text-muted-foreground text-[11px]">
                        The week lights up green wherever it fits.
                    </p>
                </div>

                {/* Search */}
                <div className="relative">
                    <Search
                        className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search code, title, teacher…"
                        className="bg-background/80 h-9 rounded-xl pl-8.5 text-xs"
                        aria-label="Search classes"
                    />
                </div>

                {/* Filter Tabs */}
                <div className="bg-muted/70 grid grid-cols-3 rounded-xl p-1 text-[11px] font-medium">
                    <button
                        type="button"
                        onClick={() => setFilter("all")}
                        className={`rounded-lg py-1 transition-colors tabular-nums ${
                            filter === "all"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        All ({counts.all})
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilter("overlaps")}
                        className={`rounded-lg py-1 transition-colors tabular-nums ${
                            filter === "overlaps"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        Overlaps ({counts.overlaps})
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilter("needs_time")}
                        className={`rounded-lg py-1 transition-colors tabular-nums ${
                            filter === "needs_time"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        Needs ({counts.needs})
                    </button>
                </div>
            </div>

            {/* Class List */}
            <div className="flex-1 space-y-1 overflow-y-auto p-2">
                {filtered.length === 0 ? (
                    <div className="text-muted-foreground px-4 py-12 text-center text-xs">
                        No classes match this filter.
                    </div>
                ) : (
                    filtered.map((cls) => {
                        const selected = cls.id === selectedClassId;
                        const overlapping = hasOverlap(cls);
                        const scheduled = scheduledHours(cls.schedules);
                        const required = cls.required_hours ?? 3;
                        const missingHours = scheduled < required || cls.schedules.length === 0;

                        return (
                            <button
                                key={cls.id}
                                type="button"
                                onClick={() => onSelectClass(cls)}
                                className={`flex w-full items-start justify-between gap-2 rounded-xl px-3 py-2.5 text-left transition-all active:scale-[0.99] ${
                                    selected
                                        ? "bg-foreground text-background shadow-sm"
                                        : "hover:bg-muted/65 text-foreground"
                                }`}
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <div className="flex items-center gap-1.5">
                                        <span className="truncate text-[13px] font-semibold tracking-tight">
                                            {cls.subject_code}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className={`h-4 rounded px-1.5 text-[10px] font-medium ${
                                                selected
                                                    ? "border-background/25 bg-background/15 text-background"
                                                    : "border-border/70 bg-muted/60 text-muted-foreground"
                                            }`}
                                        >
                                            Sec {cls.section}
                                        </Badge>
                                    </div>
                                    <p
                                        className={`truncate text-[11px] ${
                                            selected ? "text-background/80" : "text-muted-foreground"
                                        }`}
                                    >
                                        {cls.subject_title}
                                    </p>
                                    <p
                                        className={`truncate text-[10px] ${
                                            selected ? "text-background/65" : "text-muted-foreground/80"
                                        }`}
                                    >
                                        {cls.faculty_name ?? "No teacher assigned"}
                                    </p>
                                </div>

                                <div className="flex shrink-0 flex-col items-end gap-1 pt-0.5">
                                    <span
                                        className={`text-[10px] font-medium tabular-nums ${
                                            selected ? "text-background/80" : "text-muted-foreground"
                                        }`}
                                    >
                                        {Number(scheduled.toFixed(1))}/{required}h
                                    </span>
                                    {overlapping ? (
                                        <span
                                            title="Has schedule overlap"
                                            className={`size-2 rounded-full ${
                                                selected ? "bg-rose-400" : "bg-rose-500"
                                            }`}
                                        />
                                    ) : missingHours ? (
                                        <span
                                            title="Needs weekly hours"
                                            className={`size-2 rounded-full ${
                                                selected ? "bg-amber-300" : "bg-amber-500"
                                            }`}
                                        />
                                    ) : (
                                        <span
                                            title="Clean & complete"
                                            className={`flex size-4 items-center justify-center rounded-full ${
                                                selected
                                                    ? "bg-emerald-400/25 text-emerald-300"
                                                    : "bg-emerald-500/12 text-emerald-600 dark:text-emerald-400"
                                            }`}
                                        >
                                            <Check className="size-2.5" />
                                        </span>
                                    )}
                                </div>
                            </button>
                        );
                    })
                )}
            </div>
        </aside>
    );
}
