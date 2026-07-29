import { generateBulkAssessments } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { ACTIVE_JOBS_REFRESH_EVENT } from "@/components/active-jobs-notification";
import AdminLayout from "@/components/administrators/admin-layout";
import PTabs10 from "@/components/p-tabs-10";
import { Filters, type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
import { SemesterSelector } from "@/components/semester-selector";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { ComboboxOption } from "@/components/ui/combobox";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import { BookOpen, Building2, ChevronRight, CreditCard, Filter, GraduationCap, RotateCcw, Settings2, UserPlus, Users } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { EnrollmentAnalyticsSection } from "./analytics-section";
import { createColumns, type EnrollmentRow } from "./columns";
import { BulkReportsDialog, DeleteEnrollmentDialog, ForceDeleteEnrollmentDialog, RestoreEnrollmentDialog } from "./enrollment-dialogs";
import { EnrollmentsCard } from "./enrollments-card";
import { ReportsSection } from "./reports-section";
import type { Branding, BulkReportFilters, EnrollmentManagementProps, ReportFilters } from "./types";

function createInitialEnrollmentFilters(filters: EnrollmentManagementProps["filters"]): FilterType[] {
    const initialFilters: FilterType[] = [];

    if (filters.status_filter && filters.status_filter !== "all") {
        initialFilters.push({ id: "status_filter", field: "status_filter", operator: "is", values: [filters.status_filter] });
    }

    if (filters.department_filter && filters.department_filter !== "all") {
        initialFilters.push({ id: "department_filter", field: "department_filter", operator: "is", values: [filters.department_filter] });
    }

    if (filters.year_level_filter && filters.year_level_filter !== "all") {
        initialFilters.push({ id: "year_level_filter", field: "year_level_filter", operator: "is", values: [filters.year_level_filter] });
    }

    if (filters.course_filter && filters.course_filter !== "all") {
        initialFilters.push({ id: "course_filter", field: "course_filter", operator: "is", values: [filters.course_filter] });
    }

    return initialFilters;
}

function getActiveFilterValue(filters: FilterType[], field: string): string {
    const value = filters.find((filter) => filter.field === field)?.values[0];

    return value === undefined || value === null ? "all" : String(value);
}

function upsertSingleFilter(filters: FilterType[], field: string, value: string): FilterType[] {
    const nextFilters = filters.filter((filter) => filter.field !== field);

    if (value === "all") {
        return nextFilters;
    }

    return [...nextFilters, { id: field, field, operator: "is", values: [value] }];
}

function enrollmentMatchesSearch(enrollment: EnrollmentRow, searchTerm: string): boolean {
    return [
        enrollment.student_name,
        enrollment.student_id === null ? null : String(enrollment.student_id),
        enrollment.course,
        enrollment.department,
        enrollment.status,
        enrollment.school_year,
        enrollment.semester === null ? null : `Semester ${enrollment.semester}`,
        enrollment.academic_year === null ? null : `Year ${enrollment.academic_year}`,
    ]
        .filter((value): value is string => value !== null && value !== undefined)
        .some((value) => value.toLowerCase().includes(searchTerm));
}

function enrollmentMatchesFilters(enrollment: EnrollmentRow, activeFilters: FilterType[]): boolean {
    return activeFilters.every((filter) => {
        const value = filter.values[0];

        if (value === undefined || value === null) {
            return true;
        }

        const filterValue = String(value);

        if (filter.field === "status_filter") {
            if (filterValue === "active") {
                return !enrollment.is_trashed;
            }

            if (filterValue === "trashed") {
                return !!enrollment.is_trashed;
            }

            return enrollment.status === filterValue;
        }

        if (filter.field === "department_filter") {
            return enrollment.department === filterValue;
        }

        if (filter.field === "year_level_filter") {
            return String(enrollment.academic_year ?? "") === filterValue;
        }

        if (filter.field === "course_filter") {
            return String(enrollment.course_id ?? "") === filterValue;
        }

        return true;
    });
}

function sortEnrollments(enrollments: EnrollmentRow[], sortOption: string): EnrollmentRow[] {
    const [sort = "created_at", direction = "desc"] = sortOption.split(":");
    const multiplier = direction === "asc" ? 1 : -1;

    return [...enrollments].sort((a, b) => {
        if (sort === "student_name") {
            return (a.student_name ?? "").localeCompare(b.student_name ?? "") * multiplier;
        }

        if (sort === "tuition") {
            return ((a.tuition?.overall ?? 0) - (b.tuition?.overall ?? 0)) * multiplier;
        }

        const aTime = a.created_at ? Date.parse(a.created_at) : 0;
        const bTime = b.created_at ? Date.parse(b.created_at) : 0;

        return (aTime - bTime) * multiplier;
    });
}

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

    const [enrollmentSearch, setEnrollmentSearch] = useState(filters.search || "");
    const [activeFilters, setActiveFilters] = useState<FilterType[]>(() => createInitialEnrollmentFilters(filters));
    const [sortOption, setSortOption] = useState("created_at:desc");

    // Delete/restore dialog states for enrollments
    const [deleteEnrollment, setDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [forceDeleteEnrollment, setForceDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [restoreEnrollment, setRestoreEnrollment] = useState<EnrollmentRow | null>(null);
    const [isEnrollmentDeleting, setIsEnrollmentDeleting] = useState(false);

    // Bulk Reports dialog state
    const [isBulkReportsOpen, setIsBulkReportsOpen] = useState(false);
    const [bulkReportFilters, setBulkReportFilters] = useState<BulkReportFilters>({
        course_id: null,
        year_level: null,
        student_limit: null,
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

    useEffect(() => {
        setEnrollmentSearch(filters.search || "");
        setActiveFilters(createInitialEnrollmentFilters(filters));
    }, [filters.search, filters.status_filter, filters.department_filter, filters.year_level_filter, filters.course_filter]);

    const handleEnrollmentSearchChange = (value: string) => {
        setEnrollmentSearch(value);
    };

    const handleDepartmentFilterChange = (value: string) => {
        setActiveFilters((currentFilters) => upsertSingleFilter(currentFilters, "department_filter", value));
    };

    const clearFilters = () => {
        setEnrollmentSearch("");
        setActiveFilters([]);
        setSortOption("created_at:desc");
    };

    const departmentFilter = getActiveFilterValue(activeFilters, "department_filter");
    const hasActiveFilters = enrollmentSearch.trim() !== "" || activeFilters.length > 0 || sortOption !== "created_at:desc";

    const enrollmentsData = Array.isArray(enrollments?.data) ? enrollments.data : [];
    const enrollmentsTotal = enrollments?.total ?? 0;
    const enrollmentCourseOptions = useMemo(() => {
        const courses = new Map<number, { id: number; code: string; title: string | null }>();

        for (const enrollment of enrollmentsData) {
            if (enrollment.course_id === null || !enrollment.course) {
                continue;
            }

            courses.set(enrollment.course_id, {
                id: enrollment.course_id,
                code: enrollment.course,
                title: enrollment.course_title,
            });
        }

        return [...courses.values()].sort((left, right) => left.code.localeCompare(right.code));
    }, [enrollmentsData]);

    const visibleEnrollments = useMemo(() => {
        const searchTerm = enrollmentSearch.trim().toLowerCase();
        const filteredEnrollments = enrollmentsData.filter((enrollment) => {
            const matchesSearch = searchTerm === "" || enrollmentMatchesSearch(enrollment, searchTerm);

            return matchesSearch && enrollmentMatchesFilters(enrollment, activeFilters);
        });

        return sortEnrollments(filteredEnrollments, sortOption);
    }, [activeFilters, enrollmentSearch, enrollmentsData, sortOption]);

    const stats = useMemo(() => {
        const totalTuition = visibleEnrollments.reduce((sum, e) => sum + (e.tuition?.overall || 0), 0);
        const activeEnrollments = visibleEnrollments.filter((enrollment) => !enrollment.is_trashed).length;

        return {
            applicants: applicantsCount,
            enrolled: visibleEnrollments.length,
            active: activeEnrollments,
            deleted: visibleEnrollments.length - activeEnrollments,
            tuition: totalTuition,
        };
    }, [applicantsCount, visibleEnrollments]);

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
    const handleGenerateBulkAssessments = async () => {
        setIsGeneratingBulkReport(true);
        const queueToastId = "bulk-assessment-queue";
        toast.loading("Queueing bulk assessment export...", { id: queueToastId });

        try {
            const response = await axios.post<{ job_id: string; status: string; message: string }>(generateBulkAssessments.url(), bulkReportFilters);

            toast.success(response.data.message, { id: queueToastId });
            window.dispatchEvent(new Event(ACTIVE_JOBS_REFRESH_EVENT));
            setIsBulkReportsOpen(false);
            setBulkReportFilters({
                course_id: null,
                year_level: null,
                student_limit: null,
                include_deleted: false,
            });
        } catch (error) {
            const message = axios.isAxiosError<{ message?: string }>(error) ? error.response?.data?.message : null;

            toast.error(message || "Failed to queue bulk assessment generation.", { id: queueToastId });
        } finally {
            setIsGeneratingBulkReport(false);
        }
    };

    const handleOpenBulkReports = () => {
        const activeCourseId = getActiveFilterValue(activeFilters, "course_filter");

        setBulkReportFilters((currentFilters) => ({
            ...currentFilters,
            course_id: activeCourseId === "all" ? null : Number(activeCourseId),
        }));
        setIsBulkReportsOpen(true);
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

    const filterFields: FilterFieldConfig[] = useMemo(
        () => [
            {
                key: "status_filter",
                label: "Status",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: [
                    { value: "active", label: "Active records", icon: <Users className="text-muted-foreground h-4 w-4" /> },
                    { value: "trashed", label: "Deleted records", icon: <Users className="text-muted-foreground h-4 w-4" /> },
                    ...enrollment_pipeline.status_options.map((option) => ({
                        value: option.value,
                        label: option.label,
                        icon: <Users className="text-muted-foreground h-4 w-4" />,
                    })),
                ],
            },
            {
                key: "department_filter",
                label: "Department",
                type: "select",
                icon: <Building2 className="h-4 w-4" />,
                options: departmentTabs
                    .filter((department) => department.value !== "all")
                    .map((department) => ({
                        value: department.value,
                        label: department.label,
                        icon: <Building2 className="text-muted-foreground h-4 w-4" />,
                    })),
            },
            {
                key: "course_filter",
                label: "Course",
                type: "select",
                searchable: true,
                icon: <BookOpen className="h-4 w-4" />,
                options: enrollmentCourseOptions.map((course) => ({
                    value: String(course.id),
                    label: course.title ? `${course.code} — ${course.title}` : course.code,
                    icon: <BookOpen className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "year_level_filter",
                label: "Year Level",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: [1, 2, 3, 4].map((yearLevel) => ({
                    value: String(yearLevel),
                    label: `Year ${yearLevel}`,
                    icon: <GraduationCap className="text-muted-foreground h-4 w-4" />,
                })),
            },
        ],
        [departmentTabs, enrollmentCourseOptions, enrollment_pipeline.status_options],
    );

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
                        <Link
                            href={route("administrators.system-management.index")}
                            className={buttonVariants({ className: "bg-primary text-primary-foreground hover:bg-primary/90 gap-2" })}
                        >
                            <Settings2 className="h-4 w-4" />
                            Configure Workflow
                        </Link>
                    ) : (
                        <div className="flex items-center gap-3">
                            <SemesterSelector {...filters} />
                            <Link
                                href={route("administrators.enrollments.create")}
                                className={buttonVariants({
                                    className: "bg-primary text-primary-foreground hover:bg-primary/90 hidden gap-2 sm:flex",
                                })}
                            >
                                <UserPlus className="h-4 w-4" />
                                New Enrollment
                            </Link>
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
                            <Link href={route("administrators.system-management.index")} className={buttonVariants({ className: "gap-2" })}>
                                <Settings2 className="h-4 w-4" />
                                Open System Management
                            </Link>
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
                                        <span className="font-semibold">{stats.active}</span> active
                                    </span>
                                    <span className="text-muted-foreground">•</span>
                                    <span className="flex items-center gap-1 text-red-600 dark:text-red-400">
                                        <span className="font-semibold">{stats.deleted}</span> deleted
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
                                hasActiveFilters={!!hasActiveFilters}
                                enrollmentsData={visibleEnrollments}
                                enrollmentColumns={enrollmentColumns}
                                sortOption={sortOption}
                                filterControl={
                                    <Filters
                                        fields={filterFields}
                                        filters={activeFilters}
                                        onChange={setActiveFilters}
                                        trigger={
                                            <Button variant="outline" className="relative gap-2" size="sm">
                                                <Filter className="h-4 w-4" />
                                                Filters
                                                {activeFilters.length > 0 && (
                                                    <Badge variant="secondary" className="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs">
                                                        {activeFilters.length}
                                                    </Badge>
                                                )}
                                            </Button>
                                        }
                                    />
                                }
                                resetControl={
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearFilters}
                                        className="text-muted-foreground hover:text-foreground h-8 px-2"
                                    >
                                        <RotateCcw className="mr-2 h-3.5 w-3.5" />
                                        Reset
                                    </Button>
                                }
                                onSearchChange={handleEnrollmentSearchChange}
                                onSortChange={setSortOption}
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
                        enrollmentsData={visibleEnrollments}
                        enrollmentsTotal={visibleEnrollments.length}
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
                        onOpenBulkReports={handleOpenBulkReports}
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
                        courseOptions={enrollmentCourseOptions}
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
