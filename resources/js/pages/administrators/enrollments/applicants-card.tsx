import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Search, UserPlus } from "lucide-react";
import { createApplicantColumns } from "./applicants-columns";
import { DataTable } from "./data-table";
import type { ApplicantRow, EnrollmentApplicantsProps } from "./types";

type ApplicantsCardProps = {
    applicants: ApplicantRow[];
    pagination: Omit<EnrollmentApplicantsProps["applicants"], "data">;
    filters: EnrollmentApplicantsProps["filters"];
    applicantSearch: string;
    isSearching: boolean;
    onSearchChange: (value: string) => void;
    onManageScholarship: (applicant: ApplicantRow) => void;
    onDeleteApplicant: (applicant: ApplicantRow) => void;
    onForceDeleteApplicant: (applicant: ApplicantRow) => void;
};

export function ApplicantsCard({
    applicants,
    pagination,
    filters,
    applicantSearch,
    isSearching,
    onSearchChange,
    onManageScholarship,
    onDeleteApplicant,
    onForceDeleteApplicant,
}: ApplicantsCardProps) {
    const columns = createApplicantColumns({
        onManageScholarship,
        onDelete: onDeleteApplicant,
        onForceDelete: onForceDeleteApplicant,
    });
    const totalApplicants = pagination.total ?? applicants.length;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2">
                        <UserPlus className="h-5 w-5" />
                        Pending Applicants
                    </CardTitle>
                    <CardDescription>
                        {totalApplicants} student{totalApplicants !== 1 ? "s" : ""} awaiting enrollment
                    </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative w-48">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input
                            placeholder="Search applicants..."
                            className="pl-9"
                            value={applicantSearch}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <DataTable
                    columns={columns}
                    data={applicants}
                    pagination={pagination}
                    filters={{
                        ...filters,
                        search: applicantSearch || undefined,
                    }}
                    routeName="administrators.enrollments.applicants"
                    dataKey="applicants"
                    isLoading={isSearching}
                />
            </CardContent>
        </Card>
    );
}
