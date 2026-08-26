import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { ColumnDef } from "@tanstack/react-table";
import { ArrowUpRight, ListFilter, Search, Users } from "lucide-react";
import type { ReactNode } from "react";
import type { EnrollmentRow } from "./columns";
import { DataTable } from "./data-table";
import type { EnrollmentManagementProps } from "./types";

type EnrollmentsCardProps = {
    filament: EnrollmentManagementProps["filament"];
    enrollmentsTotal: number;
    enrollmentSearch: string;
    hasActiveFilters: boolean;
    enrollmentsData: EnrollmentRow[];
    enrollmentColumns: ColumnDef<EnrollmentRow, unknown>[];
    sortOption: string;
    scopeControl?: ReactNode;
    filterControl: ReactNode;
    resetControl: ReactNode;
    onSearchChange: (value: string) => void;
    onSortChange: (value: string) => void;
    onRowClick: (row: EnrollmentRow) => void;
    selectionActions?: (selectedRows: EnrollmentRow[], helpers: { clearSelection: () => void }) => ReactNode;
    getRowId?: (row: EnrollmentRow) => string;
};

export function EnrollmentsCard({
    filament,
    enrollmentsTotal,
    enrollmentSearch,
    hasActiveFilters,
    enrollmentsData,
    enrollmentColumns,
    sortOption,
    scopeControl,
    filterControl,
    resetControl,
    onSearchChange,
    onSortChange,
    onRowClick,
    selectionActions,
    getRowId,
}: EnrollmentsCardProps) {
    const totalLabel =
        enrollmentsData.length === enrollmentsTotal
            ? `${enrollmentsTotal} enrollment${enrollmentsTotal !== 1 ? "s" : ""} for this semester`
            : `Showing ${enrollmentsData.length} of ${enrollmentsTotal} enrollments`;

    return (
        <Card className="border-border/70 bg-card/85 overflow-hidden shadow-sm">
            <CardHeader className="border-border/60 flex flex-col gap-4 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary border-primary/15 flex size-9 shrink-0 items-center justify-center rounded-lg border">
                        <Users className="size-4" aria-hidden="true" />
                    </div>
                    <div className="space-y-0.5">
                        <CardTitle className="text-base font-semibold tracking-tight">Enrollment directory</CardTitle>
                        <CardDescription className="text-xs">{totalLabel}</CardDescription>
                    </div>
                </div>
                <Button variant="outline" size="sm" asChild className="self-start sm:self-auto">
                    <a href={filament.student_enrollments.index_url} target="_blank" rel="noreferrer">
                        <ArrowUpRight aria-hidden="true" />
                        Advanced view
                    </a>
                </Button>
            </CardHeader>
            <CardContent className="space-y-0 p-0">
                {scopeControl ? <div className="border-border/60 border-b px-4 pt-3 sm:px-5">{scopeControl}</div> : null}

                <div className="border-border/60 flex flex-col justify-between gap-3 border-b px-4 py-3 sm:px-5 lg:flex-row lg:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" aria-hidden="true" />
                        <Input
                            placeholder="Search by student, ID, course, or status..."
                            className="border-border/70 bg-background/60 h-9 pl-9 shadow-none"
                            value={enrollmentSearch}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={sortOption} onValueChange={onSortChange}>
                            <SelectTrigger className="border-border/70 bg-background/60 h-8 w-full sm:w-[175px]">
                                <SelectValue placeholder="Sort enrollments" />
                            </SelectTrigger>
                            <SelectContent align="end">
                                <SelectItem value="created_at:desc">Latest enrolled</SelectItem>
                                <SelectItem value="created_at:asc">Oldest enrolled</SelectItem>
                                <SelectItem value="student_name:asc">Student A-Z</SelectItem>
                                <SelectItem value="student_name:desc">Student Z-A</SelectItem>
                                <SelectItem value="tuition:desc">Highest tuition</SelectItem>
                                <SelectItem value="tuition:asc">Lowest tuition</SelectItem>
                            </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2">
                            <ListFilter className="text-muted-foreground hidden size-3.5 sm:block" aria-hidden="true" />
                            {filterControl}
                        </div>

                        {hasActiveFilters ? resetControl : null}
                    </div>
                </div>

                <div className="px-4 pt-1 pb-4 sm:px-5">
                    <DataTable
                        columns={enrollmentColumns}
                        data={enrollmentsData}
                        onRowClick={onRowClick}
                        tableVariant="spreadsheet"
                        selectionActions={selectionActions}
                        getRowId={getRowId}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
