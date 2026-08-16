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
    Filter,
    GraduationCap,
    History,
    RotateCcw,
    Settings2,
    UserPlus,
    Users,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { createColumns, type EnrollmentRow } from "./columns";
import { DeleteEnrollmentDialog, ForceDeleteEnrollmentDialog, RestoreEnrollmentDialog } from "./enrollment-dialogs";
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
        <AdminLayout user={user} title="Enrollment Records">
            <Head title="Administrators • Enrollment Records" />

            <div className="space-y-6 pb-10">
                <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Admissions & Enrollment</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Enrollment Records</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                            Process applicant handoffs and maintain current-semester enrollment workflow records.
                        </p>
                    </div>
                    {workflow_setup_required ? (
                        <Link href={route("administrators.system-management.index")} className={buttonVariants({ className: "gap-2" })}>
                            <Settings2 className="size-4" aria-hidden="true" />
                            Configure workflow
                        </Link>
                    ) : (
                        <div className="flex flex-wrap items-center gap-3">
                            <SemesterSelector {...filters} />
                            <Link href={route("administrators.enrollments.create")} className={buttonVariants({ className: "gap-2" })}>
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
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                {
                                    label: "Pending applicants",
                                    value: stats.applicants,
                                    detail: "Awaiting registrar handoff",
                                    icon: UserPlus,
                                    tone: "text-blue-600 dark:text-blue-400",
                                },
                                {
                                    label: "Active enrollments",
                                    value: stats.active,
                                    detail: "Current-semester records",
                                    icon: Users,
                                    tone: "text-emerald-600 dark:text-emerald-400",
                                },
                                {
                                    label: "Workflow exceptions",
                                    value: stats.exceptions,
                                    detail: stats.exceptions > 0 ? "Requires operational review" : "No exceptions detected",
                                    icon: AlertTriangle,
                                    tone: "text-amber-600 dark:text-amber-400",
                                },
                                {
                                    label: "Recently completed",
                                    value: stats.completed,
                                    detail: "Reached final workflow status",
                                    icon: History,
                                    tone: "text-muted-foreground",
                                },
                            ].map((metric) => (
                                <Card key={metric.label} size="sm" className="gap-0 py-0">
                                    <CardContent className="flex items-center justify-between gap-4 p-4">
                                        <div className="min-w-0">
                                            <p className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">{metric.label}</p>
                                            <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">{metric.value}</p>
                                            <p className="text-muted-foreground mt-1 truncate text-xs">{metric.detail}</p>
                                        </div>
                                        <div className="bg-muted/60 flex size-10 shrink-0 items-center justify-center rounded-lg">
                                            <metric.icon className={`size-5 ${metric.tone}`} aria-hidden="true" />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {stats.applicants > 0 && (
                            <div className="flex flex-col gap-3 rounded-lg border border-blue-200/70 bg-blue-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-blue-900/50 dark:bg-blue-950/20">
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
                </>
            )}
        </AdminLayout>
    );
}
