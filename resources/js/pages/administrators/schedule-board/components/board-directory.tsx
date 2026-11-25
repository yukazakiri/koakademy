import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Building2, Check, Clock, GraduationCap, Search, User } from "lucide-react";
import { useMemo, useState } from "react";
import type { DirectoryCategory, DirectoryEntity } from "../lib/board-entities";

type BoardDirectoryProps = {
    category: DirectoryCategory;
    onCategoryChange: (category: DirectoryCategory) => void;
    directories: Record<DirectoryCategory, DirectoryEntity[]>;
    selectedId: string | null;
    onSelect: (entity: DirectoryEntity) => void;
    unscheduledCount: number;
    onOpenUnscheduled: () => void;
};

const TABS: Array<{ id: DirectoryCategory; label: string; icon: typeof GraduationCap }> = [
    { id: "sections", label: "Sections", icon: GraduationCap },
    { id: "rooms", label: "Rooms", icon: Building2 },
    { id: "teachers", label: "Teachers", icon: User },
];

export default function BoardDirectory({
    category,
    onCategoryChange,
    directories,
    selectedId,
    onSelect,
    unscheduledCount,
    onOpenUnscheduled,
}: BoardDirectoryProps) {
    const [query, setQuery] = useState("");
    const [overlapsOnly, setOverlapsOnly] = useState(false);

    const items = directories[category] ?? [];

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return items.filter((item) => {
            if (overlapsOnly && item.conflictCount === 0) return false;
            if (!q) return true;
            return `${item.title} ${item.badge ?? ""} ${item.metaDetail}`.toLowerCase().includes(q);
        });
    }, [items, query, overlapsOnly]);

    const itemsWithOverlaps = useMemo(() => items.filter((i) => i.conflictCount > 0).length, [items]);

    return (
        <aside
            aria-label="Schedule focus directory"
            className="bg-card/70 border-border/60 flex h-full w-full flex-col overflow-hidden rounded-2xl border lg:w-[292px] lg:shrink-0"
        >
            {/* Top Category Switcher */}
            <div className="border-border/50 space-y-3 border-b p-3.5">
                <div role="tablist" aria-label="Browse schedules by" className="bg-muted/70 grid grid-cols-3 rounded-xl p-1">
                    {TABS.map((tab) => {
                        const Icon = tab.icon;
                        const active = category === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() => {
                                    onCategoryChange(tab.id);
                                    setQuery("");
                                }}
                                className={`inline-flex items-center justify-center gap-1.5 rounded-lg py-1.5 text-xs font-medium transition-all ${
                                    active
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                <Icon className="size-3.5" aria-hidden="true" />
                                <span>{tab.label}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Calm Search */}
                <div className="relative">
                    <Search
                        className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={
                            category === "sections"
                                ? "Search course or section…"
                                : category === "rooms"
                                  ? "Search room name…"
                                  : "Search teacher name…"
                        }
                        className="bg-background/80 h-9 rounded-xl pl-8.5 text-xs"
                        aria-label={`Search ${category}`}
                    />
                </div>

                {/* Quiet Filter Summary */}
                <div className="flex items-center justify-between text-[11px]">
                    <span className="text-muted-foreground tabular-nums">
                        {filtered.length} {category}
                    </span>
                    {itemsWithOverlaps > 0 && (
                        <button
                            type="button"
                            onClick={() => setOverlapsOnly((v) => !v)}
                            className={`rounded-full px-2 py-0.5 font-medium transition-colors tabular-nums ${
                                overlapsOnly
                                    ? "bg-rose-500/15 text-rose-700 dark:text-rose-300"
                                    : "text-muted-foreground hover:bg-muted hover:text-foreground"
                            }`}
                        >
                            {overlapsOnly ? "Showing overlaps" : `${itemsWithOverlaps} with overlaps`}
                        </button>
                    )}
                </div>
            </div>

            {/* Directory List */}
            <div className="flex-1 space-y-1 overflow-y-auto p-2">
                {filtered.length === 0 ? (
                    <div className="text-muted-foreground px-4 py-12 text-center text-xs">
                        No matching {category}.
                    </div>
                ) : (
                    filtered.map((entity) => {
                        const selected = entity.id === selectedId;
                        return (
                            <button
                                key={entity.id}
                                type="button"
                                onClick={() => onSelect(entity)}
                                className={`group flex w-full items-center justify-between gap-2.5 rounded-xl px-3 py-2.5 text-left transition-all active:scale-[0.99] ${
                                    selected
                                        ? "bg-foreground text-background shadow-sm"
                                        : "hover:bg-muted/65 text-foreground"
                                }`}
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <div className="flex items-center gap-1.5">
                                        <span className="truncate text-[13px] font-semibold tracking-tight">
                                            {entity.title}
                                        </span>
                                        {entity.badge && (
                                            <span
                                                className={`shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium ${
                                                    selected
                                                        ? "bg-background/15 text-background"
                                                        : "bg-muted text-muted-foreground"
                                                }`}
                                            >
                                                {entity.badge}
                                            </span>
                                        )}
                                    </div>
                                    <p
                                        className={`truncate text-[11px] tabular-nums ${
                                            selected ? "text-background/75" : "text-muted-foreground"
                                        }`}
                                    >
                                        {entity.subtitle}
                                    </p>
                                </div>

                                {/* Right Status Indicator */}
                                <div className="shrink-0">
                                    {entity.conflictCount > 0 ? (
                                        <span
                                            title={`${entity.conflictCount} schedule overlaps`}
                                            className={`inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-semibold tabular-nums ${
                                                selected
                                                    ? "bg-rose-400 text-rose-950"
                                                    : "bg-rose-500/15 text-rose-700 dark:text-rose-300"
                                            }`}
                                        >
                                            {entity.conflictCount}
                                        </span>
                                    ) : entity.sessions.length > 0 ? (
                                        <span
                                            title="Clean — no overlaps"
                                            className={`flex size-5 items-center justify-center rounded-full ${
                                                selected
                                                    ? "bg-emerald-400/25 text-emerald-300"
                                                    : "bg-emerald-500/12 text-emerald-600 dark:text-emerald-400"
                                            }`}
                                        >
                                            <Check className="size-3" />
                                        </span>
                                    ) : (
                                        <span
                                            className={`text-[10px] ${
                                                selected ? "text-background/60" : "text-muted-foreground/60"
                                            }`}
                                        >
                                            Empty
                                        </span>
                                    )}
                                </div>
                            </button>
                        );
                    })
                )}
            </div>

            {/* Bottom Quiet Unscheduled Nudge */}
            {unscheduledCount > 0 && (
                <div className="border-border/50 border-t p-2.5">
                    <button
                        type="button"
                        onClick={onOpenUnscheduled}
                        className="flex w-full items-center justify-between gap-2 rounded-xl bg-amber-500/10 px-3 py-2 text-left text-xs font-medium text-amber-800 transition-colors hover:bg-amber-500/15 dark:text-amber-300"
                    >
                        <span className="flex items-center gap-2">
                            <Clock className="size-3.5 shrink-0" />
                            <span>
                                {unscheduledCount} {unscheduledCount === 1 ? "class needs" : "classes need"} a time
                            </span>
                        </span>
                        <Badge variant="outline" className="border-amber-500/40 bg-transparent text-[10px]">
                            Assign
                        </Badge>
                    </button>
                </div>
            )}
        </aside>
    );
}
