import AdminLayout from "@/components/administrators/admin-layout";
import PTabs10 from "@/components/p-tabs-10";
import { SemesterSelector } from "@/components/semester-selector";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { ComboboxOption } from "@/components/ui/combobox";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { ChevronRight, CreditCard, Settings2, UserPlus, Users } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { EnrollmentAnalyticsSection } from "./analytics-section";
import { createColumns, type EnrollmentRow } from "./columns";
import { BulkReportsDialog, DeleteEnrollmentDialog, ForceDeleteEnrollmentDialog, RestoreEnrollmentDialog } from "./enrollment-dialogs";
import { EnrollmentsCard } from "./enrollments-card";
import { ReportsSection } from "./reports-section";
import type { Branding, BulkReportFilters, EnrollmentManagementProps, ReportFilters } from "./types";

export default function AdministratorEnrollmentsIndex({
    user,
    workflow_setup_required,
    filament,
    applicantsCount,
    enrollments,
    analytics,
    filters,
    enrollment_pipeline,
}: EnrollmentManagementProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatMoney = (value: number | null | undefined): string => {
        if (value === null || value === undefined) return "—";
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(value);
    };

    const [isSearching, setIsSearching] = useState(false);

    // Enrollment filters
    const [enrollmentSearch, setEnrollmentSearch] = useState(filters.search || "");
    const [statusFilter, setStatusFilter] = useState<string>(filters.status_filter || "all");
    const [departmentFilter, setDepartmentFilter] = useState<string>(filters.department_filter || "all");
    const [yearLevelFilter, setYearLevelFilter] = useState<string>(filters.year_level_filter || "all");

    // Delete/restore dialog states for enrollments
    const [deleteEnrollment, setDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [forceDeleteEnrollment, setForceDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [restoreEnrollment, setRestoreEnrollment] = useState<EnrollmentRow | null>(null);
    const [isEnrollmentDeleting, setIsEnrollmentDeleting] = useState(false);

    // Bulk Reports dialog state
    const [isBulkReportsOpen, setIsBulkReportsOpen] = useState(false);
    const [bulkReportFilters, setBulkReportFilters] = useState<BulkReportFilters>({
        course_filter: "all",
        year_level_filter: "all",
        student_limit: "all",
        include_deleted: false,
    });
    const [isGeneratingBulkReport, setIsGeneratingBulkReport] = useState(false);

    // Enrollment Report state (inline card-based flow)
    const [activeReportCard, setActiveReportCard] = useState<string | null>(null);
    const [reportType, setReportType] = useState<string>("enrolled_by_course");
    const [reportFilters, setReportFilters] = useState<ReportFilters>({
        course_filter: "all",
        subject_filter: "all",
        department_filter: "all",
        year_level_filter: "all",
        status_filter: "active",
    });
    const [isLoadingReport, setIsLoadingReport] = useState(false);
    const [availableSubjects, setAvailableSubjects] = useState<
        { id: number; code: string; title: string; units: number; label: string; enrolled_count: number; class_count: number; sections: string[] }[]
    >([]);
    const [availableCourses, setAvailableCourses] = useState<{ id: number; code: string; title: string; department: string; label: string }[]>([]);
    const [isLoadingFilterOptions, setIsLoadingFilterOptions] = useState(false);

    // Sync local state with filters prop when it changes (e.g. after navigation)
    useEffect(() => {
        setEnrollmentSearch(filters.search || "");
        setStatusFilter(filters.status_filter || "all");
        setDepartmentFilter(filters.department_filter || "all");
        setYearLevelFilter(filters.year_level_filter || "all");
    }, [filters.search, filters.status_filter, filters.department_filter, filters.year_level_filter]);

    const handleEnrollmentSearch = useDebouncedCallback((term: string) => {
        applyFilters({ search: term || undefined });
    }, 300);

    const handleEnrollmentSearchChange = (value: string) => {
        setEnrollmentSearch(value);
        handleEnrollmentSearch(value);
    };

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);
        applyFilters({ status_filter: value });
    };

    const handleDepartmentFilterChange = (value: string) => {
        setDepartmentFilter(value);
        applyFilters({ department_filter: value });
    };

    const handleYearLevelFilterChange = (value: string) => {
        setYearLevelFilter(value);
        applyFilters({ year_level_filter: value });
    };

    const applyFilters = (overrides: Record<string, unknown> = {}) => {
        router.get(
            route("administrators.enrollments.index"),
            {
                ...filters,
                search: enrollmentSearch || undefined,
                status_filter: statusFilter !== "all" ? statusFilter : undefined,
                department_filter: departmentFilter !== "all" ? departmentFilter : undefined,
                year_level_filter: yearLevelFilter !== "all" ? yearLevelFilter : undefined,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ["enrollments", "filters"],
                onStart: () => setIsSearching(true),
                onFinish: () => setIsSearching(false),
            },
        );
    };

    const clearFilters = () => {
        setEnrollmentSearch("");
        setStatusFilter("all");
        setDepartmentFilter("all");
        setYearLevelFilter("all");
        router.get(
            route("administrators.enrollments.index"),
            {
                currentSemester: filters.currentSemester,
                currentSchoolYear: filters.currentSchoolYear,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ["enrollments", "filters"],
            },
        );
    };

    const hasActiveFilters = enrollmentSearch || statusFilter !== "all" || departmentFilter !== "all" || yearLevelFilter !== "all";

    // Ensure enrollments data is always an array
    const enrollmentsData = Array.isArray(enrollments?.data) ? enrollments.data : [];
    const enrollmentsTotal = enrollments?.total ?? 0;
    const enrollmentPagination = {
        current_page: enrollments?.current_page ?? 1,
        last_page: enrollments?.last_page ?? 1,
        per_page: enrollments?.per_page ?? 10,
        total: enrollmentsTotal,
        next_page_url: enrollments?.next_page_url ?? null,
        prev_page_url: enrollments?.prev_page_url ?? null,
        from: enrollments?.from ?? 0,
        to: enrollments?.to ?? 0,
    };

    const stats = useMemo(() => {
        const totalTuition = enrollmentsData.reduce((sum, e) => sum + (e.tuition?.overall || 0), 0);

        return {
            applicants: applicantsCount,
            enrolled: enrollmentsTotal,
            tuition: totalTuition,
        };
    }, [applicantsCount, enrollmentsData, enrollmentsTotal]);

    const handleEnrollmentClick = (enrollment: EnrollmentRow) => {
        router.visit(route("administrators.enrollments.show", enrollment.id));
    };

    const handleDeleteEnrollment = () => {
        if (!deleteEnrollment) return;

        setIsEnrollmentDeleting(true);
        router.delete(route("administrators.enrollments.destroy", { enrollment: deleteEnrollment.id }), {
            onSuccess: () => {
                toast.success(`Enrollment for "${deleteEnrollment.student_name}" has been deleted.`);
                setDeleteEnrollment(null);
            },
            onError: () => {
                toast.error("Failed to delete enrollment.");
            },
            onFinish: () => {
                setIsEnrollmentDeleting(false);
            },
        });
    };

    const handleForceDeleteEnrollment = () => {
        if (!forceDeleteEnrollment) return;

        setIsEnrollmentDeleting(true);
        router.delete(route("administrators.enrollments.force-destroy", { enrollment: forceDeleteEnrollment.id }), {
            onSuccess: () => {
                toast.success(`Enrollment for "${forceDeleteEnrollment.student_name}" has been permanently deleted.`);
                setForceDeleteEnrollment(null);
            },
            onError: () => {
                toast.error("Failed to permanently delete enrollment.");
            },
            onFinish: () => {
                setIsEnrollmentDeleting(false);
            },
        });
    };

    const handleRestoreEnrollment = () => {
        if (!restoreEnrollment) return;

        setIsEnrollmentDeleting(true);
        router.post(
            route("administrators.enrollments.restore", { enrollment: restoreEnrollment.id }),
            {},
            {
                onSuccess: () => {
                    toast.success(`Enrollment for "${restoreEnrollment.student_name}" has been restored.`);
                    setRestoreEnrollment(null);
                },
                onError: () => {
                    toast.error("Failed to restore enrollment.");
                },
                onFinish: () => {
                    setIsEnrollmentDeleting(false);
                },
            },
        );
    };

    // Handle bulk assessment generation
    const handleGenerateBulkAssessments = () => {
        setIsGeneratingBulkReport(true);
        router.post(route("administrators.enrollments.reports.bulk-assessments"), bulkReportFilters, {
            onSuccess: () => {
                toast.success("Bulk assessment generation has been queued. You will receive a notification when it's ready.");
                setIsBulkReportsOpen(false);
                setBulkReportFilters({
                    course_filter: "all",
                    year_level_filter: "all",
                    student_limit: "all",
                    include_deleted: false,
                });
            },
            onError: () => {
                toast.error("Failed to queue bulk assessment generation.");
            },
            onFinish: () => {
                setIsGeneratingBulkReport(false);
            },
        });
    };

    // Fetch filter options for enrollment reports
    const fetchReportFilterOptions = useCallback(async () => {
        setIsLoadingFilterOptions(true);
        try {
            const [subjectsRes, coursesRes] = await Promise.all([
                fetch(route("administrators.enrollments.reports.subject-options")),
                fetch(route("administrators.enrollments.reports.course-options")),
            ]);
            if (subjectsRes.ok) {
                const data = await subjectsRes.json();
                setAvailableSubjects(data.subjects || []);
            }
            if (coursesRes.ok) {
                const data = await coursesRes.json();
                setAvailableCourses(data.courses || []);
            }
        } catch (error) {
            console.error("Failed to fetch report filter options:", error);
        } finally {
            setIsLoadingFilterOptions(false);
        }
    }, []);

    const handleReportCardClick = (type: string) => {
        if (activeReportCard === type) {
            setActiveReportCard(null);
            return;
        }
        setActiveReportCard(type);
        setReportType(type);
        setReportFilters({
            course_filter: "all",
            subject_filter: "all",
            department_filter: "all",
            year_level_filter: "all",
            status_filter: "active",
        });
        fetchReportFilterOptions();
    };

    const subjectComboboxOptions = useMemo<ComboboxOption[]>(() => {
        return [
            { label: "All Subjects", value: "all", description: "Include all subjects with active classes" },
            ...availableSubjects.map((subject) => ({
                label: `${subject.code} - ${subject.title}`,
                value: String(subject.id),
                description: `${subject.enrolled_count} enrolled | ${subject.class_count} class${subject.class_count !== 1 ? "es" : ""} (${subject.sections.join(", ")})`,
                searchText: `${subject.code} ${subject.title} ${subject.sections.join(" ")}`,
            })),
        ];
    }, [availableSubjects]);

    const courseComboboxOptions = useMemo<ComboboxOption[]>(() => {
        return [
            { label: "All Courses", value: "all", description: "Include all courses" },
            ...availableCourses.map((course) => ({
                label: `${course.code} - ${course.title}`,
                value: course.code,
                description: course.department,
                searchText: `${course.code} ${course.title} ${course.department}`,
            })),
        ];
    }, [availableCourses]);

    const handleGenerateReport = async () => {
        setIsLoadingReport(true);
        try {
            const params = new URLSearchParams({
                report_type: reportType,
                ...reportFilters,
            });

            const url = route("administrators.enrollments.reports.preview-pdf") + `?${params.toString()}`;
            const response = await fetch(url, {
                method: "GET",
                headers: {
                    Accept: "application/json",
                },
            });
            const payload = (await response.json().catch(() => ({}))) as { message?: string; error?: string };

            if (!response.ok) {
                toast.error(payload.error || payload.message || "Failed to queue PDF preview report.");
                return;
            }

            toast.success(payload.message || "PDF preview queued. You will be notified when the file is ready.");

            setActiveReportCard(null);
        } catch (error) {
            console.error("Failed to generate report:", error);
            toast.error("Failed to queue PDF preview. Please try again.");
        } finally {
            setIsLoadingReport(false);
        }
    };

    const handleExportExcel = () => {
        const params = new URLSearchParams({
            report_type: reportType,
            ...reportFilters,
            format: "excel",
        });
        const url = route("administrators.enrollments.reports.export") + `?${params.toString()}`;
        window.open(url, "_blank", "noopener");
    };

    // Create enrollment columns with action handlers
    const enrollmentColumns = useMemo(
        () =>
            createColumns(
                {
                    onDelete: (enrollment) => setDeleteEnrollment(enrollment),
                    onForceDelete: (enrollment) => setForceDeleteEnrollment(enrollment),
                    onRestore: (enrollment) => setRestoreEnrollment(enrollment),
                },
                currency,
                {
                    finalStatus: enrollment_pipeline.cashier_verified_status,
                    statusClasses: enrollment_pipeline.status_classes,
                },
            ),
        [currency, enrollment_pipeline.cashier_verified_status, enrollment_pipeline.status_classes],
    );

    const getDepartmentCount = (dept: string) => {
        const entry = analytics?.by_department?.find((d) => d.department === dept);
        return entry ? entry.count : 0;
    };

    const totalDeptsCount = analytics?.by_department?.reduce((acc, curr) => acc + curr.count, 0) ?? 0;
    const departmentTabs = [
        { value: "all", label: "All Departments", count: totalDeptsCount },
        ...["IT", "HM", "BA", "TESDA"].map((department) => ({
            value: department,
            label: department,
            count: getDepartmentCount(department),
        })),
    ];

    return (
        <AdminLayout user={user} title="Enrollments">
            <Head title="Administrators • Enrollments" />

            <div className="space-y-8 pb-10">
                {/* Header Section */}
                <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-foreground text-3xl font-bold tracking-tight">Enrolled Students</h2>
                        <p className="text-muted-foreground">
                            Real-time insights and management for {filters.currentSchoolYear} - {(filters.currentSchoolYear ?? 0) + 1}.
                        </p>
                    </div>
                    {workflow_setup_required ? (
                        <Button asChild className="bg-primary text-primary-foreground hover:bg-primary/90">
                            <Link href={route("administrators.system-management.index")}>
                                <Settings2 className="mr-2 h-4 w-4" />
                                Configure Workflow
                            </Link>
                        </Button>
                    ) : (
                        <div className="flex items-center gap-3">
                            <SemesterSelector {...filters} />
                            <Button asChild className="bg-primary text-primary-foreground hover:bg-primary/90 hidden sm:flex">
                                <Link href={route("administrators.enrollments.create")}>
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    New Enrollment
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {workflow_setup_required ? (
                    <Card className="border-amber-200 bg-amber-50/70 dark:border-amber-900/40 dark:bg-amber-950/20">
                        <CardHeader>
                            <CardTitle>Enrollment Workflow Setup Required</CardTitle>
                            <CardDescription>
                                Enrollment Management is locked until you configure an Enrollment Pipeline in System Management.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-muted-foreground text-sm">
                                Define at least one step, choose entry/completion steps, and assign allowed roles before using enrollment tools.
                            </p>
                            <Button asChild>
                                <Link href={route("administrators.system-management.index")}>
                                    <Settings2 className="mr-2 h-4 w-4" />
                                    Open System Management
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Metrics Grid */}
                        <div className="grid gap-6 md:grid-cols-3">
                            {/* Card 1 */}
                            <div className="bg-card ring-border relative overflow-hidden rounded-2xl p-6 shadow-sm ring-1 transition-all hover:shadow-md">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-muted-foreground text-sm font-medium">Pending Applicants</p>
                                        <div className="mt-2 flex items-baseline gap-2">
                                            <span className="text-foreground text-4xl font-bold">{stats.applicants}</span>
                                            <span className="text-muted-foreground text-sm font-medium">students</span>
                                        </div>
                                    </div>
                                    <div className="rounded-xl bg-blue-100 p-3 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">
                                        <UserPlus className="h-6 w-6" />
                                    </div>
                                </div>
                                <div className="text-muted-foreground mt-4 flex items-center gap-2 text-xs font-medium">
                                    <Link
                                        href={route("administrators.enrollments.applicants")}
                                        className="flex items-center text-blue-600 dark:text-blue-400"
                                    >
                                        View applicants
                                        <ChevronRight className="h-3 w-3" />
                                    </Link>
                                </div>
                            </div>

                            {/* Card 2 */}
                            <div className="bg-card ring-border relative overflow-hidden rounded-2xl p-6 shadow-sm ring-1 transition-all hover:shadow-md">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-muted-foreground text-sm font-medium">Total Enrolled</p>
                                        <div className="mt-2 flex items-baseline gap-2">
                                            <span className="text-foreground text-4xl font-bold">{stats.enrolled}</span>
                                            <span className="text-muted-foreground text-sm font-medium">students</span>
                                        </div>
                                    </div>
                                    <div className="rounded-xl bg-emerald-100 p-3 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                                        <Users className="h-6 w-6" />
                                    </div>
                                </div>
                                <div className="mt-4 flex items-center gap-3 text-xs">
                                    <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                        <span className="font-semibold">{analytics?.active_count ?? 0}</span> active
                                    </span>
                                    <span className="text-muted-foreground">•</span>
                                    <span className="flex items-center gap-1 text-red-600 dark:text-red-400">
                                        <span className="font-semibold">{analytics?.trashed_count ?? 0}</span> deleted
                                    </span>
                                </div>
                            </div>

                            {/* Card 3 */}
                            <div className="bg-card ring-border relative overflow-hidden rounded-2xl p-6 shadow-sm ring-1 transition-all hover:shadow-md">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-muted-foreground text-sm font-medium">Tuition Revenue</p>
                                        <div className="mt-2 flex items-baseline gap-2">
                                            <span className="text-foreground text-3xl font-bold">{formatMoney(stats.tuition)}</span>
                                            <span className="text-muted-foreground ml-1 text-xs">(Page View)</span>
                                        </div>
                                    </div>
                                    <div className="rounded-xl bg-purple-100 p-3 text-purple-600 dark:bg-purple-900/50 dark:text-purple-400">
                                        <CreditCard className="h-6 w-6" />
                                    </div>
                                </div>
                                <div className="text-muted-foreground mt-4 text-xs">Projected for current semester (visible records)</div>
                            </div>
                        </div>

                        {/* Enrollments Section */}
                        <div className="flex flex-col gap-4">
                            <PTabs10 value={departmentFilter} onValueChange={handleDepartmentFilterChange} tabs={departmentTabs} />

                            <EnrollmentsCard
                                filament={filament}
                                enrollmentsTotal={enrollmentsTotal}
                                enrollmentSearch={enrollmentSearch}
                                statusFilter={statusFilter}
                                departmentFilter={departmentFilter}
                                yearLevelFilter={yearLevelFilter}
                                hasActiveFilters={!!hasActiveFilters}
                                isSearching={isSearching}
                                enrollmentsData={enrollmentsData}
                                enrollmentColumns={enrollmentColumns}
                                pagination={enrollmentPagination}
                                filters={filters}
                                analytics={analytics}
                                statusOptions={enrollment_pipeline.status_options}
                                onSearchChange={handleEnrollmentSearchChange}
                                onStatusFilterChange={handleStatusFilterChange}
                                onDepartmentFilterChange={handleDepartmentFilterChange}
                                onYearLevelFilterChange={handleYearLevelFilterChange}
                                onClearFilters={clearFilters}
                                onRowClick={handleEnrollmentClick}
                            />
                        </div>
                    </>
                )}
            </div>

            {!workflow_setup_required && (
                <>
                    {/* Comprehensive Analytics Section */}
                    <EnrollmentAnalyticsSection
                        analytics={analytics}
                        filters={filters}
                        stats={stats}
                        enrollmentsData={enrollmentsData}
                        enrollmentsTotal={enrollmentsTotal}
                        formatMoney={formatMoney}
                    />

                    {/* Reports Section */}
                    <ReportsSection
                        activeReportCard={activeReportCard}
                        reportFilters={reportFilters}
                        courseComboboxOptions={courseComboboxOptions}
                        subjectComboboxOptions={subjectComboboxOptions}
                        isLoadingFilterOptions={isLoadingFilterOptions}
                        isLoadingReport={isLoadingReport}
                        onOpenBulkReports={() => setIsBulkReportsOpen(true)}
                        onReportCardClick={handleReportCardClick}
                        onReportFiltersChange={setReportFilters}
                        onCancelInlineFilters={() => setActiveReportCard(null)}
                        onGenerateReport={handleGenerateReport}
                        onExportExcel={handleExportExcel}
                    />

                    {/* Bulk Assessments Export Dialog */}
                    <BulkReportsDialog
                        open={isBulkReportsOpen}
                        onOpenChange={setIsBulkReportsOpen}
                        filters={bulkReportFilters}
                        onFiltersChange={setBulkReportFilters}
                        isGenerating={isGeneratingBulkReport}
                        onGenerate={handleGenerateBulkAssessments}
                    />

                    {/* Delete Enrollment Confirmation Dialog */}
                    <DeleteEnrollmentDialog
                        open={!!deleteEnrollment}
                        enrollment={deleteEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setDeleteEnrollment(null)}
                        onConfirm={handleDeleteEnrollment}
                    />

                    {/* Force Delete Enrollment Confirmation Dialog */}
                    <ForceDeleteEnrollmentDialog
                        open={!!forceDeleteEnrollment}
                        enrollment={forceDeleteEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setForceDeleteEnrollment(null)}
                        onConfirm={handleForceDeleteEnrollment}
                    />

                    {/* Restore Enrollment Confirmation Dialog */}
                    <RestoreEnrollmentDialog
                        open={!!restoreEnrollment}
                        enrollment={restoreEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setRestoreEnrollment(null)}
                        onConfirm={handleRestoreEnrollment}
                    />
                </>
            )}
        </AdminLayout>
    );
}
