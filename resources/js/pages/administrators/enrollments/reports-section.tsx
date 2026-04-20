import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { Download, Eye, FileSpreadsheet, FileText, GraduationCap, Loader2, Users, X } from "lucide-react";
import type { Dispatch, SetStateAction } from "react";
import type { ReportFilters } from "./types";

type ReportsSectionProps = {
    activeReportCard: string | null;
    reportFilters: ReportFilters;
    courseComboboxOptions: ComboboxOption[];
    subjectComboboxOptions: ComboboxOption[];
    isLoadingFilterOptions: boolean;
    isLoadingReport: boolean;
    onOpenBulkReports: () => void;
    onReportCardClick: (type: string) => void;
    onReportFiltersChange: Dispatch<SetStateAction<ReportFilters>>;
    onCancelInlineFilters: () => void;
    onGenerateReport: () => void;
    onExportExcel: () => void;
};

export function ReportsSection({
    activeReportCard,
    reportFilters,
    courseComboboxOptions,
    subjectComboboxOptions,
    isLoadingFilterOptions,
    isLoadingReport,
    onOpenBulkReports,
    onReportCardClick,
    onReportFiltersChange,
    onCancelInlineFilters,
    onGenerateReport,
    onExportExcel,
}: ReportsSectionProps) {
    return (
        <Card className="border-dashed">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Reports & Exports
                        </CardTitle>
                        <CardDescription>Select a report type, apply filters, then open a direct landscape PDF preview</CardDescription>
                    </div>
                    <Button onClick={onOpenBulkReports}>
                        <Download className="mr-2 h-4 w-4" />
                        Bulk Export Assessments
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-4 md:grid-cols-3">
                    {/* Enrolled Students by Course Card */}
                    <button
                        type="button"
                        onClick={() => onReportCardClick("enrolled_by_course")}
                        className={cn(
                            "cursor-pointer rounded-lg border p-4 text-left transition-all",
                            activeReportCard === "enrolled_by_course"
                                ? "border-primary bg-primary/5 ring-primary/20 ring-2"
                                : "bg-muted/30 hover:bg-muted/50",
                        )}
                    >
                        <div className="flex items-center gap-3">
                            <div className={cn("rounded-lg p-2", activeReportCard === "enrolled_by_course" ? "bg-primary/20" : "bg-primary/10")}>
                                <Users className="text-primary h-5 w-5" />
                            </div>
                            <div>
                                <p className="font-medium">Enrolled Students List</p>
                                <p className="text-muted-foreground text-xs">Students enrolled by course, department, or year level</p>
                            </div>
                        </div>
                    </button>

                    {/* Students by Subject Card */}
                    <button
                        type="button"
                        onClick={() => onReportCardClick("enrolled_by_subject")}
                        className={cn(
                            "cursor-pointer rounded-lg border p-4 text-left transition-all",
                            activeReportCard === "enrolled_by_subject"
                                ? "border-primary bg-primary/5 ring-primary/20 ring-2"
                                : "bg-muted/30 hover:bg-muted/50",
                        )}
                    >
                        <div className="flex items-center gap-3">
                            <div className={cn("rounded-lg p-2", activeReportCard === "enrolled_by_subject" ? "bg-primary/20" : "bg-primary/10")}>
                                <GraduationCap className="text-primary h-5 w-5" />
                            </div>
                            <div>
                                <p className="font-medium">Students by Subject</p>
                                <p className="text-muted-foreground text-xs">List of students enrolled in a specific subject</p>
                            </div>
                        </div>
                    </button>

                    {/* Enrollment Summary Card */}
                    <button
                        type="button"
                        onClick={() => onReportCardClick("enrollment_summary")}
                        className={cn(
                            "cursor-pointer rounded-lg border p-4 text-left transition-all",
                            activeReportCard === "enrollment_summary"
                                ? "border-primary bg-primary/5 ring-primary/20 ring-2"
                                : "bg-muted/30 hover:bg-muted/50",
                        )}
                    >
                        <div className="flex items-center gap-3">
                            <div className={cn("rounded-lg p-2", activeReportCard === "enrollment_summary" ? "bg-primary/20" : "bg-primary/10")}>
                                <FileText className="text-primary h-5 w-5" />
                            </div>
                            <div>
                                <p className="font-medium">Enrollment Summary</p>
                                <p className="text-muted-foreground text-xs">Summary statistics by department, course, and year</p>
                            </div>
                        </div>
                    </button>
                </div>

                {/* Inline Filter Panel - shown when a report card is active */}
                {activeReportCard && (
                    <div className="bg-muted/20 animate-in fade-in slide-in-from-top-2 rounded-lg border p-5 duration-200">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h4 className="text-sm font-semibold">
                                    {activeReportCard === "enrolled_by_course" && "Configure: Enrolled Students List"}
                                    {activeReportCard === "enrolled_by_subject" && "Configure: Students by Subject"}
                                    {activeReportCard === "enrollment_summary" && "Configure: Enrollment Summary"}
                                </h4>
                                <p className="text-muted-foreground text-xs">Adjust filters below, then open the PDF preview in a new tab.</p>
                            </div>
                            <Button variant="ghost" size="sm" onClick={onCancelInlineFilters}>
                                <X className="h-4 w-4" />
                            </Button>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {/* Course Filter - for enrolled_by_course */}
                            {activeReportCard === "enrolled_by_course" && (
                                <Combobox
                                    label="Course"
                                    options={courseComboboxOptions}
                                    value={reportFilters.course_filter}
                                    onValueChange={(value) => onReportFiltersChange((prev) => ({ ...prev, course_filter: value }))}
                                    placeholder="Select course..."
                                    searchPlaceholder="Search courses..."
                                    emptyText={isLoadingFilterOptions ? "Loading courses..." : "No courses found."}
                                    disabled={isLoadingFilterOptions}
                                />
                            )}

                            {/* Subject Filter - for enrolled_by_subject (searchable Combobox) */}
                            {activeReportCard === "enrolled_by_subject" && (
                                <div className="sm:col-span-2 lg:col-span-2">
                                    <Combobox
                                        label="Subject"
                                        required
                                        options={subjectComboboxOptions}
                                        value={reportFilters.subject_filter}
                                        onValueChange={(value) => onReportFiltersChange((prev) => ({ ...prev, subject_filter: value }))}
                                        placeholder="Search and select a subject..."
                                        searchPlaceholder="Type to search subjects by code or title..."
                                        emptyText={isLoadingFilterOptions ? "Loading subjects..." : "No subjects with active classes found."}
                                        disabled={isLoadingFilterOptions}
                                    />
                                </div>
                            )}

                            {/* Department Filter - for enrolled_by_course and enrollment_summary */}
                            {(activeReportCard === "enrolled_by_course" || activeReportCard === "enrollment_summary") && (
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Department</Label>
                                    <Select
                                        value={reportFilters.department_filter}
                                        onValueChange={(value) => onReportFiltersChange((prev) => ({ ...prev, department_filter: value }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Departments</SelectItem>
                                            <SelectItem value="IT">IT</SelectItem>
                                            <SelectItem value="HM">HM</SelectItem>
                                            <SelectItem value="BA">BA</SelectItem>
                                            <SelectItem value="TESDA">TESDA</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Year Level Filter - for enrolled_by_course */}
                            {activeReportCard === "enrolled_by_course" && (
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Year Level</Label>
                                    <Select
                                        value={reportFilters.year_level_filter}
                                        onValueChange={(value) => onReportFiltersChange((prev) => ({ ...prev, year_level_filter: value }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select year level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Year Levels</SelectItem>
                                            <SelectItem value="1">1st Year</SelectItem>
                                            <SelectItem value="2">2nd Year</SelectItem>
                                            <SelectItem value="3">3rd Year</SelectItem>
                                            <SelectItem value="4">4th Year</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Status Filter - for all report types */}
                            <div className="space-y-2">
                                <Label className="text-sm font-medium">Student Status</Label>
                                <Select
                                    value={reportFilters.status_filter}
                                    onValueChange={(value) => onReportFiltersChange((prev) => ({ ...prev, status_filter: value }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active Only</SelectItem>
                                        <SelectItem value="all">Include Deleted</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Generate Button */}
                        <div className="mt-5 flex items-center justify-end gap-3">
                            <Button variant="ghost" size="sm" onClick={onCancelInlineFilters} disabled={isLoadingReport}>
                                Cancel
                            </Button>
                            <Button variant="outline" size="sm" onClick={onExportExcel} disabled={isLoadingReport || isLoadingFilterOptions}>
                                <FileSpreadsheet className="mr-2 h-4 w-4" />
                                Export Excel
                            </Button>
                            <Button onClick={onGenerateReport} disabled={isLoadingReport}>
                                {isLoadingReport ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Generating...
                                    </>
                                ) : (
                                    <>
                                        <Eye className="mr-2 h-4 w-4" />
                                        Preview PDF
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
