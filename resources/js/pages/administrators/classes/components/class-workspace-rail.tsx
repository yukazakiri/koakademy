import { Button } from "@/components/ui/button";
import { Archive, CalendarX2, GraduationCap, Layers, TriangleAlert, UserX } from "lucide-react";
import type { ReactNode } from "react";

export type ClassQueueCounts = {
    unassigned_faculty: number;
    no_schedule: number;
    over_capacity: number;
};

export type ClassRailFilters = {
    classification?: string | null;
    needs_faculty?: boolean | null;
    needs_schedule?: boolean | null;
    over_capacity?: boolean | null;
    trashed?: string | null;
};

type ClassWorkspaceRailProps = {
    filters: ClassRailFilters;
    queues: ClassQueueCounts;
    total: number;
    classificationCounts: { college: number; shs: number };
    onFilterChange: (next: ClassRailFilters) => void;
};

type RailItemProps = {
    icon: ReactNode;
    label: string;
    count: number;
    active: boolean;
    tone?: "default" | "warning" | "danger";
    onClick: () => void;
};

function RailItem({ icon, label, count, active, tone = "default", onClick }: RailItemProps): ReactNode {
    const countTone =
        count === 0
            ? "text-muted-foreground"
            : tone === "danger"
              ? "text-destructive"
              : tone === "warning"
                ? "text-amber-600 dark:text-amber-400"
                : "text-foreground";

    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={`group flex w-full items-center gap-2.5 rounded-lg border px-2.5 py-2 text-left transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 ${
                active ? "border-primary/40 bg-primary/5 text-foreground" : "border-transparent text-muted-foreground hover:bg-muted/60 hover:text-foreground"
            }`}
        >
            <span className={active ? "text-primary" : "text-muted-foreground group-hover:text-foreground"}>{icon}</span>
            <span className="min-w-0 flex-1 truncate text-sm">{label}</span>
            <span className={`text-xs tabular-nums ${countTone}`}>{count}</span>
        </button>
    );
}

/**
 * The work queues of the class list.
 *
 * Every entry is a filter the database applies, so the counts and the list
 * always agree and nothing has to be filtered again in the browser.
 */
export function ClassWorkspaceRail({
    filters,
    queues,
    total,
    classificationCounts,
    onFilterChange,
}: ClassWorkspaceRailProps): ReactNode {
    const isArchived = filters.trashed === "only";
    const hasQueueFilter = Boolean(filters.needs_faculty || filters.needs_schedule || filters.over_capacity);

    const toggleQueue = (key: "needs_faculty" | "needs_schedule" | "over_capacity"): void => {
        onFilterChange({
            ...filters,
            [key]: filters[key] ? null : true,
        });
    };

    return (
        <nav aria-label="Class work queues" className="flex flex-col gap-5">
            <div>
                <h2 className="text-muted-foreground mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider">Needs attention</h2>
                <div className="flex flex-col gap-0.5">
                    <RailItem
                        icon={<UserX className="size-4" />}
                        label="No faculty assigned"
                        count={queues.unassigned_faculty}
                        active={Boolean(filters.needs_faculty)}
                        tone="warning"
                        onClick={() => toggleQueue("needs_faculty")}
                    />
                    <RailItem
                        icon={<CalendarX2 className="size-4" />}
                        label="No schedule"
                        count={queues.no_schedule}
                        active={Boolean(filters.needs_schedule)}
                        tone="warning"
                        onClick={() => toggleQueue("needs_schedule")}
                    />
                    <RailItem
                        icon={<TriangleAlert className="size-4" />}
                        label="Over capacity"
                        count={queues.over_capacity}
                        active={Boolean(filters.over_capacity)}
                        tone="danger"
                        onClick={() => toggleQueue("over_capacity")}
                    />
                </div>
            </div>

            <div>
                <h2 className="text-muted-foreground mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider">Sections</h2>
                <div className="flex flex-col gap-0.5">
                    <RailItem
                        icon={<Layers className="size-4" />}
                        label="All classes"
                        count={total}
                        active={!filters.classification && !hasQueueFilter && !isArchived}
                        onClick={() => onFilterChange({})}
                    />
                    <RailItem
                        icon={<GraduationCap className="size-4" />}
                        label="College"
                        count={classificationCounts.college}
                        active={filters.classification === "college"}
                        onClick={() =>
                            onFilterChange({
                                ...filters,
                                classification: filters.classification === "college" ? null : "college",
                            })
                        }
                    />
                    <RailItem
                        icon={<GraduationCap className="size-4" />}
                        label="Senior high"
                        count={classificationCounts.shs}
                        active={filters.classification === "shs"}
                        onClick={() =>
                            onFilterChange({
                                ...filters,
                                classification: filters.classification === "shs" ? null : "shs",
                            })
                        }
                    />
                </div>
            </div>

            <div className="border-t pt-4">
                <Button
                    variant={isArchived ? "secondary" : "ghost"}
                    size="sm"
                    onClick={() => onFilterChange(isArchived ? {} : { trashed: "only" })}
                    className="w-full justify-start"
                >
                    <Archive className="size-4" />
                    Archived classes
                </Button>
            </div>
        </nav>
    );
}
