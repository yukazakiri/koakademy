import PTabs10 from "@/components/p-tabs-10";
import { Filter, FilterFieldConfig, Filters } from "@/components/reui/filters";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ArrowDownUp, Filter as FilterIcon, RotateCcw, Search, UserPlus } from "lucide-react";
import type { RefObject } from "react";
import { createApplicantColumns, type ApplicantActions } from "./applicants-columns";
import { APPLICANT_STAGE_ORDER, STAGE_FALLBACK_LABELS } from "./applicants-utils";
import { DataTable } from "./data-table";
import type { ApplicantRow, EnrollmentApplicantsProps } from "./types";

type ApplicantsCardProps = {
    applicants: ApplicantRow[];
    pagination: Omit<EnrollmentApplicantsProps["applicants"], "data">;
    filters: Record<string, string | number | boolean | null | undefined>;
    applicantSearch: string;
    isSearching: boolean;
    onSearchChange: (value: string) => void;
    searchInputRef: RefObject<HTMLInputElement | null>;
    stage: string;
    onStageChange: (value: string) => void;
    stageCounts: Record<string, number>;
    stageMeta: Record<string, { label: string; description: string; tone: string }>;
    sort: string;
    direction: "asc" | "desc";
    onSortChange: (sort: string, direction: "asc" | "desc") => void;
    filterFields: FilterFieldConfig[];
    activeFilters: Filter[];
    onFiltersChange: (filters: Filter[]) => void;
    onResetFilters: () => void;
    hasActiveFilters: boolean;
    onRowClick: (applicant: ApplicantRow) => void;
    actions: ApplicantActions;
    pipelineStatusLabels?: Record<string, string>;
};

const SORT_OPTIONS: { value: string; label: string; sort: string; direction: "asc" | "desc" }[] = [
    { value: "created_at:desc", label: "Newest first", sort: "created_at", direction: "desc" },
    { value: "created_at:asc", label: "Oldest first", sort: "created_at", direction: "asc" },
    { value: "name:asc", label: "Name (A–Z)", sort: "name", direction: "asc" },
    { value: "name:desc", label: "Name (Z–A)", sort: "name", direction: "desc" },
    { value: "course:asc", label: "Program (A–Z)", sort: "course", direction: "asc" },
    { value: "department:asc", label: "Department (A–Z)", sort: "department", direction: "asc" },
    { value: "scholarship_type:asc", label: "Scholarship (A–Z)", sort: "scholarship_type", direction: "asc" },
    { value: "student_id:asc", label: "Student ID", sort: "student_id", direction: "asc" },
];

export function ApplicantsCard({
    applicants,
    pagination,
    filters,
    applicantSearch,
    isSearching,
    onSearchChange,
    searchInputRef,
    stage,
    onStageChange,
    stageCounts,
    stageMeta,
    sort,
    direction,
    onSortChange,
    filterFields,
    activeFilters,
    onFiltersChange,
    onResetFilters,
    hasActiveFilters,
    onRowClick,
    actions,
    pipelineStatusLabels,
}: ApplicantsCardProps) {
    const columns = createApplicantColumns({ actions, stageMeta, pipelineStatusLabels });
    const totalApplicants = pagination.total ?? applicants.length;
    const sortValue = `${sort}:${direction}`;

    const stageTabs = [
        { value: "all", label: "All applicants", count: stageCounts.all ?? totalApplicants },
        ...APPLICANT_STAGE_ORDER.map((key) => ({
            value: key,
            label: stageMeta[key]?.label ?? STAGE_FALLBACK_LABELS[key],
            count: stageCounts[key] ?? 0,
        })),
        ...(stageCounts.cancelled ? [{ value: "cancelled", label: "Cancelled", count: stageCounts.cancelled }] : []),
        ...(stageCounts.deleted ? [{ value: "deleted", label: "Deleted", count: stageCounts.deleted }] : []),
    ];

    return (
        <Card className="border-border/70 bg-card/85 overflow-hidden shadow-sm">
            <CardHeader className="gap-0 border-b pb-0">
                <div className="flex flex-col gap-4 pb-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="border-primary/15 bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg border">
                            <UserPlus className="size-5" aria-hidden="true" />
                        </div>
                        <div className="min-w-0">
                            <CardTitle className="text-base font-semibold tracking-tight">Application queue</CardTitle>
                            <CardDescription className="text-xs">
                                {totalApplicants} applicant{totalApplicants !== 1 ? "s" : ""} matching the current view
                            </CardDescription>
                        </div>
                    </div>

                    <div className="relative w-full lg:w-80">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" aria-hidden="true" />
                        <Input
                            ref={searchInputRef}
                            placeholder="Search name, email, phone, ID…"
                            aria-label="Search applicants"
                            className="pl-9"
                            value={applicantSearch}
                            onChange={(event) => onSearchChange(event.target.value)}
                        />
                        <kbd className="text-muted-foreground pointer-events-none absolute top-2 right-2 hidden rounded border px-1.5 py-0.5 font-mono text-[10px] sm:block">
                            /
                        </kbd>
                    </div>
                </div>

                <div className="flex flex-col gap-3 pb-3 xl:flex-row xl:items-center xl:justify-between">
                    <PTabs10 value={stage || "all"} onValueChange={onStageChange} tabs={stageTabs} />

                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={sortValue}
                            onValueChange={(value) => {
                                const option = SORT_OPTIONS.find((entry) => entry.value === value) ?? SORT_OPTIONS[0];
                                onSortChange(option.sort, option.direction);
                            }}
                        >
                            <SelectTrigger className="h-8 w-[168px]" aria-label="Sort applicants">
                                <ArrowDownUp className="mr-1.5 size-3.5" aria-hidden="true" />
                                <SelectValue placeholder="Sort" />
                            </SelectTrigger>
                            <SelectContent>
                                {SORT_OPTIONS.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Filters
                            fields={filterFields}
                            filters={activeFilters}
                            onChange={onFiltersChange}
                            trigger={
                                <Button variant="outline" size="sm" className="relative h-8 gap-2">
                                    <FilterIcon className="size-4" aria-hidden="true" />
                                    Filters
                                    {activeFilters.length > 0 && (
                                        <Badge variant="secondary" className="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs">
                                            {activeFilters.length}
                                        </Badge>
                                    )}
                                </Button>
                            }
                        />

                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={onResetFilters}
                                className="text-muted-foreground hover:text-foreground h-8 px-2"
                            >
                                <RotateCcw className="size-3.5" aria-hidden="true" />
                                Reset
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>

            <CardContent className="pt-4">
                <DataTable
                    columns={columns}
                    data={applicants}
                    pagination={pagination}
                    filters={filters}
                    routeName="administrators.enrollments.applicants"
                    dataKey="applicants"
                    isLoading={isSearching}
                    onRowClick={onRowClick}
                    tableVariant="spreadsheet"
                    emptyTitle="No applicants match this view"
                    emptyDescription={
                        hasActiveFilters
                            ? "Try clearing a filter or switching to the All applicants tab."
                            : "Applications submitted through the online enrollment portal will appear here."
                    }
                />
            </CardContent>
        </Card>
    );
}
