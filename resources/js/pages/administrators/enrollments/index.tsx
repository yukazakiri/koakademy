import AdminLayout from "@/components/administrators/admin-layout";
import PTabs10 from "@/components/p-tabs-10";
import { Filters, type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
import { SemesterSelector } from "@/components/semester-selector";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    ChevronRight,
    CircleCheckBig,
    FileDown,
    Filter,
    GraduationCap,
    Loader2,
    RotateCcw,
    Settings2,
    Trash2,
    UserPlus,
    Users,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { createColumns, type EnrollmentRow, type EnrollmentSelectionRefs } from "./columns";
import {
    BulkDeleteEnrollmentsDialog,
    BulkForceDeleteEnrollmentsDialog,
    DeleteEnrollmentDialog,
    ForceDeleteEnrollmentDialog,
    RestoreEnrollmentDialog,
} from "./enrollment-dialogs";
import { EnrollmentsCard } from "./enrollments-card";
import type { Branding, EnrollmentManagementProps } from "./types";

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

function nowStamp(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, "0");

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;
}

async function readBlobErrorMessage(error: unknown): Promise<string | null> {
    const response = (error as { response?: { data?: unknown } } | undefined)?.response;

    if (!(response?.data instanceof Blob)) {
        return null;
    }

    try {
        const parsed = JSON.parse(await response.data.text()) as { message?: unknown };

        return typeof parsed.message === "string" ? parsed.message : null;
    } catch {
        return null;
    }
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

    const [enrollmentSearch, setEnrollmentSearch] = useState(filters.search || "");
    const [activeFilters, setActiveFilters] = useState<FilterType[]>(() => createInitialEnrollmentFilters(filters));
    const [sortOption, setSortOption] = useState("created_at:desc");

    // Delete/restore dialog states for enrollments
    const [deleteEnrollment, setDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [forceDeleteEnrollment, setForceDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [restoreEnrollment, setRestoreEnrollment] = useState<EnrollmentRow | null>(null);
    const [isEnrollmentDeleting, setIsEnrollmentDeleting] = useState(false);

    const selectionRefs: EnrollmentSelectionRefs = {
        anchorId: useRef<string | null>(null),
        shiftPressed: useRef(false),
    };
    const clearSelectionRef = useRef<(() => void) | null>(null);
    const [selectedEnrollments, setSelectedEnrollments] = useState<EnrollmentRow[]>([]);
    const [bulkDialog, setBulkDialog] = useState<"delete" | "forceDelete" | null>(null);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

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
        const activeEnrollments = visibleEnrollments.filter((enrollment) => !enrollment.is_trashed).length;
        const completedEnrollments = visibleEnrollments.filter(
            (enrollment) => !enrollment.is_trashed && enrollment.status === enrollment_pipeline.cashier_verified_status,
        ).length;
        const workflowExceptions = visibleEnrollments.filter(
            (enrollment) => enrollment.is_trashed || enrollment.status !== enrollment_pipeline.cashier_verified_status,
        ).length;

        return {
            applicants: applicantsCount,
            enrolled: visibleEnrollments.length,
            active: activeEnrollments,
            deleted: visibleEnrollments.length - activeEnrollments,
            completed: completedEnrollments,
            exceptions: workflowExceptions,
        };
    }, [applicantsCount, enrollment_pipeline.cashier_verified_status, visibleEnrollments]);

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

    const resetBulkSelection = () => {
        setSelectedEnrollments([]);
        setBulkDialog(null);
        clearSelectionRef.current?.();
        selectionRefs.anchorId.current = null;
    };

    const handleBulkDelete = () => {
        if (selectedEnrollments.length === 0) return;

        setIsBulkDeleting(true);
        router.post(
            route("administrators.enrollments.bulk-destroy"),
            { ids: selectedEnrollments.map((enrollment) => enrollment.id) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${selectedEnrollments.length} enrollment(s) have been deleted.`);
                    resetBulkSelection();
                },
                onError: () => {
                    toast.error("Failed to delete enrollments.");
                },
                onFinish: () => {
                    setIsBulkDeleting(false);
                },
            },
        );
    };

    const handleBulkForceDelete = () => {
        if (selectedEnrollments.length === 0) return;

        setIsBulkDeleting(true);
        router.post(
            route("administrators.enrollments.bulk-force-destroy"),
            { ids: selectedEnrollments.map((enrollment) => enrollment.id) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${selectedEnrollments.length} enrollment(s) have been permanently deleted.`);
                    resetBulkSelection();
                },
                onError: () => {
                    toast.error("Failed to permanently delete enrollments.");
                },
                onFinish: () => {
                    setIsBulkDeleting(false);
                },
            },
        );
    };

    const handleBulkExport = async (rows: EnrollmentRow[]) => {
        if (rows.length === 0 || isExporting) return;

        setIsExporting(true);
        try {
            const response = await window.axios.post(
                route("administrators.enrollments.bulk-export-assessments"),
                { ids: rows.map((enrollment) => enrollment.id) },
                { responseType: "blob" },
            );

            const disposition = String(response.headers?.["content-disposition"] ?? "");
            const fileName = /filename="?([^";]+)"?/i.exec(disposition)?.[1] ?? `bulk-assessments-${nowStamp()}.pdf`;

            const url = URL.createObjectURL(response.data as Blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);

            const included = Number(response.headers?.["x-assessment-count"] ?? 0);
            const skipped = Number(response.headers?.["x-assessment-skipped-count"] ?? 0);

            toast.success(`Exported ${included} assessment${included === 1 ? "" : "s"} to "${fileName}".`);

            if (skipped > 0) {
                toast.info(`${skipped} enrollment${skipped === 1 ? "" : "s"} had no assessment file and were skipped.`);
            }
        } catch (error) {
            toast.error((await readBlobErrorMessage(error)) ?? "Failed to export assessments.");
        } finally {
            setIsExporting(false);
        }
    };

    const renderSelectionActions = (selectedRows: EnrollmentRow[], helpers: { clearSelection: () => void }) => {
        if (selectedRows.length === 0) return null;

        clearSelectionRef.current = helpers.clearSelection;

        const trashedCount = selectedRows.filter((row) => row.is_trashed).length;

        return (
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary" className="h-6 rounded-full px-2.5 text-xs">
                    {selectedRows.length} selected
                </Badge>
                <Button
                    variant="outline"
                    size="sm"
                    className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40"
                    onClick={() => {
                        setSelectedEnrollments(selectedRows);
                        setBulkDialog("delete");
                    }}
                >
                    <Trash2 className="size-3.5" aria-hidden="true" />
                    Delete
                </Button>
                {trashedCount > 0 && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40"
                        onClick={() => {
                            setSelectedEnrollments(selectedRows);
                            setBulkDialog("forceDelete");
                        }}
                    >
                        <AlertTriangle className="size-3.5" aria-hidden="true" />
                        Force delete
                    </Button>
                )}
                <Button
                    variant="outline"
                    size="sm"
                    className="border-border/70 bg-background/60 h-8 gap-1.5"
                    disabled={isExporting}
                    onClick={() => {
                        setSelectedEnrollments(selectedRows);
                        void handleBulkExport(selectedRows);
                    }}
                >
                    {isExporting ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : <FileDown className="size-3.5" aria-hidden="true" />}
                    Export assessments
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground hover:text-foreground h-8 w-8 p-0"
                    onClick={resetBulkSelection}
                    aria-label="Clear selection"
                >
                    <X className="size-3.5" aria-hidden="true" />
                </Button>
            </div>
        );
    };

    // Create enrollment columns with action handlers
    const enrollmentColumns = useMemo(
        () =>
            createColumns(
                {
                    onDelete: (enrollment) => setDeleteEnrollment(enrollment),
                    onForceDelete: (enrollment) => setForceDeleteEnrollment(enrollment),
                    onRestore: (enrollment) => setRestoreEnrollment(enrollment),
                    selectionRefs,
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
        <AdminLayout user={user} title="Enrollment Records">
            <Head title="Administrators • Enrollment Records" />

            <div className="relative space-y-6 pb-10">
                <div className="pointer-events-none absolute -top-24 right-0 size-72 rounded-full bg-blue-500/5 blur-3xl" aria-hidden="true" />

                <header className="border-border/70 relative flex flex-col gap-6 border-b pb-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-3">
                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase">
                            <span className="size-1.5 rounded-full bg-blue-500" aria-hidden="true" />
                            Admissions workspace
                            <span className="border-border/70 text-muted-foreground rounded-full border px-2 py-0.5 tracking-normal normal-case">
                                Current term
                            </span>
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-[-0.03em] sm:text-4xl">Enrollment records</h1>
                            <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                                Track student handoffs, review workflow exceptions, and keep the current term moving.
                            </p>
                        </div>
                    </div>
                    {workflow_setup_required ? (
                        <Link href={route("administrators.system-management.index")} className={buttonVariants({ className: "gap-2" })}>
                            <Settings2 className="size-4" aria-hidden="true" />
                            Configure workflow
                        </Link>
                    ) : (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="space-y-1.5">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-[0.14em] uppercase">Viewing period</p>
                                <SemesterSelector {...filters} />
                            </div>
                            <Link
                                href={route("administrators.enrollments.create")}
                                className={buttonVariants({
                                    className: "gap-2 bg-blue-600 text-white shadow-blue-600/20 hover:bg-blue-500 hover:shadow-md",
                                })}
                            >
                                <UserPlus className="size-4" aria-hidden="true" />
                                New enrollment
                            </Link>
                        </div>
                    )}
                </header>

                {workflow_setup_required ? (
                    <Card className="border-amber-300/70 bg-amber-50/70 dark:border-amber-900/50 dark:bg-amber-950/20">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                                Enrollment workflow setup required
                            </CardTitle>
                            <CardDescription>
                                Enrollment operations remain locked until entry, completion, and role assignments are configured.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-muted-foreground max-w-2xl text-sm">
                                Define at least one workflow step and assign the roles responsible for moving students through enrollment.
                            </p>
                            <Link href={route("administrators.system-management.index")} className={buttonVariants({ className: "gap-2" })}>
                                <Settings2 className="size-4" aria-hidden="true" />
                                Open system settings
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="relative grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                {
                                    label: "Total enrolled",
                                    value: analytics?.current_semester_count ?? enrollmentsTotal,
                                    detail: "Current semester records",
                                    icon: GraduationCap,
                                    tone: "text-blue-600 dark:text-blue-400",
                                },
                                {
                                    label: "Active records",
                                    value: analytics?.active_count ?? stats.active,
                                    detail: "Ready for the next step",
                                    icon: Users,
                                    tone: "text-emerald-600 dark:text-emerald-400",
                                },
                                {
                                    label: "Pending applicants",
                                    value: stats.applicants,
                                    detail: "Awaiting registrar handoff",
                                    icon: UserPlus,
                                    tone: "text-violet-600 dark:text-violet-400",
                                },
                                {
                                    label: "Workflow exceptions",
                                    value: stats.exceptions,
                                    detail: stats.exceptions > 0 ? "Requires operational review" : "No exceptions detected",
                                    icon: AlertTriangle,
                                    tone: "text-amber-600 dark:text-amber-400",
                                },
                            ].map((metric) => (
                                <Card key={metric.label} size="sm" className="border-border/70 bg-card/80 gap-0 py-0 shadow-sm">
                                    <CardContent className="flex items-start justify-between gap-4 p-4 sm:p-5">
                                        <div className="min-w-0">
                                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">
                                                {metric.label}
                                            </p>
                                            <p className="mt-2 text-2xl font-semibold tracking-[-0.03em] tabular-nums sm:text-3xl">{metric.value}</p>
                                            <p className="text-muted-foreground mt-1 truncate text-xs">{metric.detail}</p>
                                        </div>
                                        <div className="bg-muted/60 border-border/60 flex size-10 shrink-0 items-center justify-center rounded-lg border">
                                            <metric.icon className={`size-5 ${metric.tone}`} aria-hidden="true" />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {stats.applicants > 0 && (
                            <div className="flex flex-col gap-3 rounded-xl border border-blue-200/70 bg-blue-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-blue-900/50 dark:bg-blue-950/20">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300">
                                        <CircleCheckBig className="size-4" aria-hidden="true" />
                                    </div>
                                    <p className="text-sm text-blue-950 dark:text-blue-100">
                                        <span className="font-semibold">
                                            {stats.applicants} applicant{stats.applicants === 1 ? "" : "s"}
                                        </span>{" "}
                                        available for admissions review and enrollment handoff.
                                    </p>
                                </div>
                                <Link
                                    href={route("administrators.enrollments.applicants")}
                                    className="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-100"
                                >
                                    Review applicants
                                    <ChevronRight className="size-3.5" aria-hidden="true" />
                                </Link>
                            </div>
                        )}

                        <EnrollmentsCard
                            filament={filament}
                            enrollmentsTotal={enrollmentsTotal}
                            enrollmentSearch={enrollmentSearch}
                            hasActiveFilters={!!hasActiveFilters}
                            enrollmentsData={visibleEnrollments}
                            enrollmentColumns={enrollmentColumns}
                            sortOption={sortOption}
                            scopeControl={<PTabs10 value={departmentFilter} onValueChange={handleDepartmentFilterChange} tabs={departmentTabs} />}
                            filterControl={
                                <Filters
                                    fields={filterFields}
                                    filters={activeFilters}
                                    onChange={setActiveFilters}
                                    trigger={
                                        <Button variant="outline" className="relative gap-2" size="sm">
                                            <Filter className="size-4" aria-hidden="true" />
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
                                    <RotateCcw className="size-3.5" aria-hidden="true" />
                                    Reset
                                </Button>
                            }
                            onSearchChange={handleEnrollmentSearchChange}
                            onSortChange={setSortOption}
                            onRowClick={handleEnrollmentClick}
                            selectionActions={renderSelectionActions}
                            getRowId={(row) => String(row.id)}
                        />
                    </>
                )}
            </div>

            {!workflow_setup_required && (
                <>
                    <DeleteEnrollmentDialog
                        open={!!deleteEnrollment}
                        enrollment={deleteEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setDeleteEnrollment(null)}
                        onConfirm={handleDeleteEnrollment}
                    />
                    <ForceDeleteEnrollmentDialog
                        open={!!forceDeleteEnrollment}
                        enrollment={forceDeleteEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setForceDeleteEnrollment(null)}
                        onConfirm={handleForceDeleteEnrollment}
                    />
                    <RestoreEnrollmentDialog
                        open={!!restoreEnrollment}
                        enrollment={restoreEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setRestoreEnrollment(null)}
                        onConfirm={handleRestoreEnrollment}
                    />
                    <BulkDeleteEnrollmentsDialog
                        open={bulkDialog === "delete"}
                        count={selectedEnrollments.length}
                        isDeleting={isBulkDeleting}
                        onOpenChange={(open) => !open && setBulkDialog(null)}
                        onConfirm={handleBulkDelete}
                    />
                    <BulkForceDeleteEnrollmentsDialog
                        open={bulkDialog === "forceDelete"}
                        count={selectedEnrollments.length}
                        isDeleting={isBulkDeleting}
                        onOpenChange={(open) => !open && setBulkDialog(null)}
                        onConfirm={handleBulkForceDelete}
                    />
                </>
            )}
        </AdminLayout>
    );
}
