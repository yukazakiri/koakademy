import { Filters, type Filter, type FilterFieldConfig } from "@/components/reui/filters";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { CalendarX2, ChevronDown, Search, SlidersHorizontal, TriangleAlert, UserX } from "lucide-react";
import { useId, useState, type RefObject } from "react";

export type ClassWorkspaceFilters = {
    classification?: string | null;
    needs_faculty?: boolean | null;
    needs_schedule?: boolean | null;
    over_capacity?: boolean | null;
    trashed?: string | null;
};

type ClassListToolbarProps = {
    search: string;
    searchInputRef: RefObject<HTMLInputElement | null>;
    onSearchChange: (search: string) => void;
    onSearchFocusChange: (focused: boolean) => void;
    filters: ClassWorkspaceFilters;
    onWorkspaceFilterChange: (filters: ClassWorkspaceFilters) => void;
    activeFilters: Filter[];
    filterFields: FilterFieldConfig[];
    onFiltersChange: (filters: Filter[]) => void;
    queues: { unassigned_faculty: number; no_schedule: number; over_capacity: number };
    hasFilters: boolean;
    onReset: () => void;
};

export function ClassListToolbar({
    search,
    searchInputRef,
    onSearchChange,
    onSearchFocusChange,
    filters,
    onWorkspaceFilterChange,
    activeFilters,
    filterFields,
    onFiltersChange,
    queues,
    hasFilters,
    onReset,
}: ClassListToolbarProps) {
    const panelId = useId();
    const queueFilters = [
        { key: "needs_faculty" as const, label: "Needs faculty", count: queues.unassigned_faculty, icon: UserX },
        { key: "needs_schedule" as const, label: "Needs schedule", count: queues.no_schedule, icon: CalendarX2 },
        { key: "over_capacity" as const, label: "Over capacity", count: queues.over_capacity, icon: TriangleAlert },
    ];
    const activeCount = activeFilters.length + queueFilters.filter(({ key }) => filters[key]).length;
    const [expanded, setExpanded] = useState(activeCount > 0);

    return (
        <div className="space-y-4 border-b p-4 sm:p-6">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-end">
                <div className="min-w-0 flex-1 space-y-2">
                    <Label htmlFor="class-search">Search classes</Label>
                    <div className="relative">
                        <Search className="text-muted-foreground pointer-events-none absolute top-3 left-3 size-4" aria-hidden="true" />
                        <Input
                            id="class-search"
                            ref={searchInputRef}
                            type="search"
                            placeholder="Subject, section, program, or faculty"
                            className="bg-background h-10 pl-10 text-sm"
                            value={search}
                            onFocus={() => onSearchFocusChange(true)}
                            onBlur={() => onSearchFocusChange(false)}
                            onChange={(event) => onSearchChange(event.target.value)}
                        />
                    </div>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    <div className="min-w-36 flex-1 space-y-2 sm:flex-none">
                        <Label htmlFor="class-classification">Class type</Label>
                        <Select
                            value={filters.classification || "all"}
                            onValueChange={(value) => onWorkspaceFilterChange({ ...filters, classification: value === "all" ? null : value })}
                        >
                            <SelectTrigger id="class-classification" className="h-10 w-full sm:w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All class types</SelectItem>
                                <SelectItem value="college">College</SelectItem>
                                <SelectItem value="shs">Senior high</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="min-w-36 flex-1 space-y-2 sm:flex-none">
                        <Label htmlFor="class-record-status">Records</Label>
                        <Select
                            value={filters.trashed || "active"}
                            onValueChange={(value) => onWorkspaceFilterChange({ ...filters, trashed: value === "active" ? null : value })}
                        >
                            <SelectTrigger id="class-record-status" className="h-10 w-full sm:w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active classes</SelectItem>
                                <SelectItem value="only">Archived classes</SelectItem>
                                <SelectItem value="with">All records</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        type="button"
                        variant={expanded ? "secondary" : "outline"}
                        className="h-10 gap-2 px-3 text-sm"
                        aria-expanded={expanded}
                        aria-controls={panelId}
                        onClick={() => setExpanded(!expanded)}
                    >
                        <SlidersHorizontal className="size-4" aria-hidden="true" />
                        Filters{activeCount > 0 ? ` (${activeCount})` : ""}
                        <ChevronDown className="size-4" aria-hidden="true" />
                    </Button>
                    {hasFilters ? (
                        <Button type="button" variant="ghost" className="h-10 px-3 text-sm" onClick={onReset}>
                            Reset
                        </Button>
                    ) : null}
                </div>
            </div>
            <div id={panelId} hidden={!expanded}>
                <div className="space-y-4 pt-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground mr-2 text-sm">Needs attention</span>
                        {queueFilters.map(({ key, label, count, icon: Icon }) => (
                            <Button
                                key={key}
                                type="button"
                                variant={filters[key] ? "secondary" : "outline"}
                                className="h-9 gap-2 px-3 text-sm"
                                aria-pressed={Boolean(filters[key])}
                                onClick={() => onWorkspaceFilterChange({ ...filters, [key]: filters[key] ? null : true })}
                            >
                                <Icon className="size-4" aria-hidden="true" />
                                {label}
                                <span className="text-muted-foreground tabular-nums">{count.toLocaleString()}</span>
                            </Button>
                        ))}
                    </div>
                    <Filters
                        enableShortcut={expanded}
                        fields={filterFields}
                        filters={activeFilters}
                        onChange={onFiltersChange}
                        trigger={
                            <Button type="button" variant="outline" className="h-9 px-3 text-sm">
                                <SlidersHorizontal className="size-4" aria-hidden="true" /> Add filter
                            </Button>
                        }
                    />
                </div>
            </div>
        </div>
    );
}
