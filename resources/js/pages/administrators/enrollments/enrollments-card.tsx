import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Link } from "@inertiajs/react";
import type { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal, Pencil, Search, Users } from "lucide-react";
import type { ReactNode } from "react";
import { route } from "ziggy-js";
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
}: EnrollmentsCardProps) {
    const totalLabel =
        enrollmentsData.length === enrollmentsTotal
            ? `${enrollmentsTotal} enrollment${enrollmentsTotal !== 1 ? "s" : ""} for this semester`
            : `Showing ${enrollmentsData.length} of ${enrollmentsTotal} enrollments`;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-4">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5" />
                        Enrolled Students
                    </CardTitle>
                    <CardDescription>{totalLabel}</CardDescription>
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
                {scopeControl ? <div className="border-b pb-3">{scopeControl}</div> : null}

                <div className="bg-muted/20 flex flex-col justify-between gap-3 rounded-lg border p-2.5 lg:flex-row lg:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input
                            placeholder="Search by student name, ID, course, or status..."
                            className="bg-background h-9 pl-9"
                            value={enrollmentSearch}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={sortOption} onValueChange={onSortChange}>
                            <SelectTrigger className="h-8 w-[190px]">
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

                        {filterControl}

                        {hasActiveFilters ? resetControl : null}
                    </div>
                </div>

                <DataTable
                    columns={enrollmentColumns}
                    data={enrollmentsData}
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
