import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Link } from "@inertiajs/react";
import type { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal, Pencil, Search, Users } from "lucide-react";
import { route } from "ziggy-js";
import type { EnrollmentRow } from "./columns";
import { DataTable } from "./data-table";
import type { EnrollmentManagementProps } from "./types";

type EnrollmentPagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    from: number;
    to: number;
};

type EnrollmentsCardProps = {
    filament: EnrollmentManagementProps["filament"];
    enrollmentsTotal: number;
    enrollmentSearch: string;
    statusFilter: string;
    departmentFilter: string;
    yearLevelFilter: string;
    hasActiveFilters: boolean;
    isSearching: boolean;
    enrollmentsData: EnrollmentRow[];
    enrollmentColumns: ColumnDef<EnrollmentRow, unknown>[];
    pagination: EnrollmentPagination;
    filters: EnrollmentManagementProps["filters"];
    analytics: EnrollmentManagementProps["analytics"];
    statusOptions: Array<{ value: string; label: string }>;
    onSearchChange: (value: string) => void;
    onStatusFilterChange: (value: string) => void;
    onDepartmentFilterChange: (value: string) => void;
    onYearLevelFilterChange: (value: string) => void;
    onClearFilters: () => void;
    onRowClick: (row: EnrollmentRow) => void;
};

export function EnrollmentsCard({
    filament,
    enrollmentsTotal,
    enrollmentSearch,
    statusFilter,
    departmentFilter,
    yearLevelFilter,
    hasActiveFilters,
    isSearching,
    enrollmentsData,
    enrollmentColumns,
    pagination,
    filters,
    analytics,
    statusOptions,
    onSearchChange,
    onStatusFilterChange,
    onDepartmentFilterChange,
    onYearLevelFilterChange,
    onClearFilters,
    onRowClick,
}: EnrollmentsCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-4">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5" />
                        Enrolled Students
                    </CardTitle>
                    <CardDescription>
                        {enrollmentsTotal} enrollment{enrollmentsTotal !== 1 ? "s" : ""} for this semester
                    </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <a href={filament.student_enrollments.index_url} target="_blank" rel="noreferrer">
                            <MoreHorizontal className="mr-2 h-4 w-4" />
                            Advanced View
                        </a>
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Filters Bar */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-[200px] flex-1">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input
                            placeholder="Search by name, ID, or course..."
                            className="pl-9"
                            value={enrollmentSearch}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>
                    <Select value={statusFilter} onValueChange={onStatusFilterChange}>
                        <SelectTrigger className="w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="trashed">Deleted</SelectItem>
                            {statusOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={yearLevelFilter} onValueChange={onYearLevelFilterChange}>
                        <SelectTrigger className="w-28">
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Years</SelectItem>
                            <SelectItem value="1">Year 1</SelectItem>
                            <SelectItem value="2">Year 2</SelectItem>
                            <SelectItem value="3">Year 3</SelectItem>
                            <SelectItem value="4">Year 4</SelectItem>
                        </SelectContent>
                    </Select>
                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={onClearFilters} className="text-muted-foreground">
                            Clear Filters
                        </Button>
                    )}
                </div>

                {/* Data Table */}
                <DataTable
                    columns={enrollmentColumns}
                    data={enrollmentsData}
                    pagination={pagination}
                    filters={{
                        ...filters,
                        search: enrollmentSearch,
                        status_filter: statusFilter,
                        department_filter: departmentFilter,
                        year_level_filter: yearLevelFilter,
                    }}
                    dataKey="enrollments"
                    isLoading={isSearching}
                    onRowClick={onRowClick}
                    selectionActions={(selectedRows) => {
                        if (selectedRows.length !== 1) return null;
                        const enrollment = selectedRows[0] as EnrollmentRow;

                        return (
                            <Button size="sm" className="h-8" asChild>
                                <Link href={route("administrators.enrollments.edit", enrollment.id)}>
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Edit Selected
                                </Link>
                            </Button>
                        );
                    }}
                />
            </CardContent>
        </Card>
    );
}
