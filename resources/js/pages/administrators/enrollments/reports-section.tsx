import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { ClipboardList, Download, Eye, FileClock, FileSpreadsheet, FileText, GraduationCap, Loader2, Search, Users } from "lucide-react";
import { useMemo, useState, type Dispatch, type SetStateAction } from "react";
import type { ReportFilters } from "./types";

export type RecentReportOutput = {
    id: string;
    name: string;
    format: "PDF" | "Excel" | "PDF bundle";
    status: "Queued" | "Opened";
    createdAt: string;
};

type ReportsSectionProps = {
    activeReportCard: string | null;
    reportFilters: ReportFilters;
    courseComboboxOptions: ComboboxOption[];
    subjectComboboxOptions: ComboboxOption[];
    isLoadingFilterOptions: boolean;
    isLoadingReport: boolean;
    recentOutputs: RecentReportOutput[];
    onOpenBulkReports: () => void;
    onReportCardClick: (type: string) => void;
    onReportFiltersChange: Dispatch<SetStateAction<ReportFilters>>;
    onCancelInlineFilters: () => void;
    onGenerateReport: () => void;
    onExportExcel: () => void;
};

const REPORT_GROUPS = [
    {
        label: "Student Lists",
        items: [
            {
                type: "enrolled_by_course",
                title: "Master enrollment list",
                description: "Students grouped by course, department, and year level.",
                icon: Users,
            },
            {
                type: "enrolled_by_subject",
                title: "Students by subject",
                description: "Official subject roster for active classes.",
                icon: GraduationCap,
            },
        ],
    },
    {
        label: "Enrollment Summaries",
        items: [
            {
                type: "enrollment_summary",
                title: "Enrollment summary",
                description: "Aggregated counts by department, course, and year.",
                icon: ClipboardList,
            },
        ],
    },
] as const;

function reportTitle(reportType: string | null): string {
    for (const group of REPORT_GROUPS) {
        const report = group.items.find((item) => item.type === reportType);
        if (report) return report.title;
    }

    return "Select a report";
}

export function ReportsSection({
    activeReportCard,
    reportFilters,
    courseComboboxOptions,
    subjectComboboxOptions,
    isLoadingFilterOptions,
    isLoadingReport,
    recentOutputs,
    onOpenBulkReports,
    onReportCardClick,
    onReportFiltersChange,
    onCancelInlineFilters,
    onGenerateReport,
    onExportExcel,
}: ReportsSectionProps) {
    const [catalogSearch, setCatalogSearch] = useState("");
    const normalizedSearch = catalogSearch.trim().toLowerCase();

    const visibleGroups = useMemo(
        () =>
            REPORT_GROUPS.map((group) => ({
                ...group,
                items: group.items.filter(
                    (item) => !normalizedSearch || `${item.title} ${item.description}`.toLowerCase().includes(normalizedSearch),
                ),
            })).filter((group) => group.items.length > 0),
        [normalizedSearch],
    );

    const selectedTitle = reportTitle(activeReportCard);
    const selectedCourse = courseComboboxOptions.find((option) => option.value === reportFilters.course_filter)?.label ?? "All courses";
    const selectedSubject = subjectComboboxOptions.find((option) => option.value === reportFilters.subject_filter)?.label ?? "All subjects";
    const includedFilters = [
        activeReportCard === "enrolled_by_subject" ? selectedSubject : selectedCourse,
        reportFilters.department_filter === "all" ? "All departments" : reportFilters.department_filter,
        reportFilters.year_level_filter === "all" ? "All year levels" : `Year ${reportFilters.year_level_filter}`,
        reportFilters.status_filter === "active" ? "Active records only" : "Active and deleted records",
    ];

    return (
        <div className="space-y-6">
            <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                <Card className="h-fit">
                    <CardHeader className="gap-3">
                        <div>
                            <CardTitle>Report catalog</CardTitle>
                            <CardDescription>Choose an official registrar output to configure.</CardDescription>
                        </div>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" aria-hidden="true" />
                            <Input
                                value={catalogSearch}
                                onChange={(event) => setCatalogSearch(event.target.value)}
                                placeholder="Find a report..."
                                className="pl-8"
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {visibleGroups.length === 0 ? (
                            <p className="text-muted-foreground rounded-lg border border-dashed px-3 py-6 text-center text-sm">
                                No matching reports.
                            </p>
                        ) : (
                            visibleGroups.map((group) => (
                                <section key={group.label} className="space-y-2">
                                    <h3 className="text-muted-foreground px-1 text-[10px] font-semibold tracking-[0.14em] uppercase">
                                        {group.label}
                                    </h3>
                                    <div className="space-y-1.5">
                                        {group.items.map((item) => (
                                            <button
                                                key={item.type}
                                                type="button"
                                                onClick={() => onReportCardClick(item.type)}
                                                className={cn(
                                                    "focus-visible:ring-ring flex w-full items-start gap-3 rounded-lg border px-3 py-3 text-left transition-colors outline-none focus-visible:ring-2",
                                                    activeReportCard === item.type
                                                        ? "border-primary/30 bg-primary/7"
                                                        : "hover:border-border hover:bg-muted/45 border-transparent",
                                                )}
                                            >
                                                <div
                                                    className={cn(
                                                        "mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md",
                                                        activeReportCard === item.type
                                                            ? "bg-primary text-primary-foreground"
                                                            : "bg-muted text-muted-foreground",
                                                    )}
                                                >
                                                    <item.icon className="size-4" aria-hidden="true" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold">{item.title}</p>
                                                    <p className="text-muted-foreground mt-0.5 text-xs leading-relaxed">{item.description}</p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                </section>
                            ))
                        )}

                        <section className="space-y-2 border-t pt-4">
                            <h3 className="text-muted-foreground px-1 text-[10px] font-semibold tracking-[0.14em] uppercase">
                                Official Documents & Assessments
                            </h3>
                            <button
                                type="button"
                                onClick={onOpenBulkReports}
                                className="hover:bg-muted/45 focus-visible:ring-ring hover:border-border flex w-full items-start gap-3 rounded-lg border border-transparent px-3 py-3 text-left transition-colors outline-none focus-visible:ring-2"
                            >
                                <div className="bg-muted text-muted-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md">
                                    <Download className="size-4" aria-hidden="true" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">Bulk assessment export</p>
                                    <p className="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                                        Queue assessment PDFs for a scoped student group.
                                    </p>
                                </div>
                            </button>
                        </section>
                    </CardContent>
                </Card>

                <Card className="min-w-0">
                    <CardHeader className="flex-row items-start justify-between gap-4 border-b">
                        <div>
                            <div className="mb-1.5 flex items-center gap-2">
                                <CardTitle>{selectedTitle}</CardTitle>
                                {activeReportCard && (
                                    <Badge variant="outline" className="text-emerald-700 dark:text-emerald-400">
                                        Data ready
                                    </Badge>
                                )}
                            </div>
                            <CardDescription>Configure included records and choose the output format.</CardDescription>
                        </div>
                        <FileText className="text-muted-foreground size-5" aria-hidden="true" />
                    </CardHeader>
                    <CardContent className="space-y-6 pt-5">
                        {!activeReportCard ? (
                            <div className="flex min-h-72 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                <FileText className="text-muted-foreground/50 size-9" aria-hidden="true" />
                                <p className="mt-3 text-sm font-semibold">Choose a report from the catalog</p>
                                <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                                    Its available filters and output options will appear here.
                                </p>
                            </div>
                        ) : (
                            <>
                                <section className="space-y-3">
                                    <div>
                                        <h3 className="text-sm font-semibold">Report scope</h3>
                                        <p className="text-muted-foreground text-xs">Filters are applied to the active semester and school year.</p>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {activeReportCard === "enrolled_by_course" && (
                                            <Combobox
                                                label="Course"
                                                options={courseComboboxOptions}
                                                value={reportFilters.course_filter}
                                                onValueChange={(value) =>
                                                    onReportFiltersChange((previous) => ({ ...previous, course_filter: value }))
                                                }
                                                placeholder="Select course..."
                                                searchPlaceholder="Search courses..."
                                                emptyText={isLoadingFilterOptions ? "Loading courses..." : "No courses found."}
                                                disabled={isLoadingFilterOptions}
                                            />
                                        )}

                                        {activeReportCard === "enrolled_by_subject" && (
                                            <div className="sm:col-span-2">
                                                <Combobox
                                                    label="Subject"
                                                    required
                                                    options={subjectComboboxOptions}
                                                    value={reportFilters.subject_filter}
                                                    onValueChange={(value) =>
                                                        onReportFiltersChange((previous) => ({ ...previous, subject_filter: value }))
                                                    }
                                                    placeholder="Search and select a subject..."
                                                    searchPlaceholder="Search by subject code or title..."
                                                    emptyText={isLoadingFilterOptions ? "Loading subjects..." : "No active subjects found."}
                                                    disabled={isLoadingFilterOptions}
                                                />
                                            </div>
                                        )}

                                        {(activeReportCard === "enrolled_by_course" || activeReportCard === "enrollment_summary") && (
                                            <div className="space-y-2">
                                                <Label>Department</Label>
                                                <Select
                                                    value={reportFilters.department_filter}
                                                    onValueChange={(value) =>
                                                        onReportFiltersChange((previous) => ({ ...previous, department_filter: value }))
                                                    }
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

                                        {activeReportCard === "enrolled_by_course" && (
                                            <div className="space-y-2">
                                                <Label>Year level</Label>
                                                <Select
                                                    value={reportFilters.year_level_filter}
                                                    onValueChange={(value) =>
                                                        onReportFiltersChange((previous) => ({ ...previous, year_level_filter: value }))
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select year level" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">All Year Levels</SelectItem>
                                                        {[1, 2, 3, 4].map((year) => (
                                                            <SelectItem key={year} value={String(year)}>
                                                                Year {year}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        )}

                                        <div className="space-y-2">
                                            <Label>Enrollment status</Label>
                                            <Select
                                                value={reportFilters.status_filter}
                                                onValueChange={(value) =>
                                                    onReportFiltersChange((previous) => ({ ...previous, status_filter: value }))
                                                }
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
                                </section>

                                <section className="bg-muted/30 rounded-lg border p-4">
                                    <h3 className="text-sm font-semibold">Included in this output</h3>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {includedFilters.map((filter) => (
                                            <Badge key={filter} variant="secondary">
                                                {filter}
                                            </Badge>
                                        ))}
                                    </div>
                                    <p className="text-muted-foreground mt-3 text-xs leading-relaxed">
                                        {selectedTitle} for the active academic period. PDF previews are generated asynchronously; Excel exports open
                                        immediately.
                                    </p>
                                </section>

                                <section className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="text-sm font-semibold">Output options</p>
                                        <p className="text-muted-foreground text-xs">Choose a preview-ready PDF or spreadsheet export.</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="ghost" size="sm" onClick={onCancelInlineFilters} disabled={isLoadingReport}>
                                            Reset filters
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={onExportExcel}
                                            disabled={isLoadingReport || isLoadingFilterOptions}
                                        >
                                            <FileSpreadsheet className="size-4" aria-hidden="true" />
                                            Export Excel
                                        </Button>
                                        <Button size="sm" onClick={onGenerateReport} disabled={isLoadingReport || isLoadingFilterOptions}>
                                            {isLoadingReport ? (
                                                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                                            ) : (
                                                <Eye className="size-4" aria-hidden="true" />
                                            )}
                                            {isLoadingReport ? "Queueing preview..." : "Preview PDF"}
                                        </Button>
                                    </div>
                                </section>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileClock className="size-4" aria-hidden="true" /> Recent outputs
                    </CardTitle>
                    <CardDescription>
                        Reports initiated during this browser session. Completed files are delivered through notifications.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {recentOutputs.length === 0 ? (
                        <div className="rounded-lg border border-dashed px-4 py-8 text-center">
                            <p className="text-sm font-medium">No reports generated in this session</p>
                            <p className="text-muted-foreground mt-1 text-xs">Select a report above to create the first output.</p>
                        </div>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {recentOutputs.map((output) => (
                                <div key={output.id} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-muted flex size-9 items-center justify-center rounded-md">
                                            {output.format === "Excel" ? (
                                                <FileSpreadsheet className="size-4" aria-hidden="true" />
                                            ) : (
                                                <FileText className="size-4" aria-hidden="true" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold">{output.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {output.format} · {output.createdAt}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge variant={output.status === "Queued" ? "secondary" : "outline"}>{output.status}</Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
