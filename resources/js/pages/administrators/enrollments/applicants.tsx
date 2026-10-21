import AdminLayout from "@/components/administrators/admin-layout";
import { Filter, FilterFieldConfig } from "@/components/reui/filters";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowRight,
    Building2,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    FileWarning,
    GraduationCap,
    Sparkles,
    UserPlus,
    Users,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { ApplicantCancellationDialog } from "./applicant-cancellation-dialog";
import { ApplicantDrawer } from "./applicant-drawer";
import { ApplicantsCard } from "./applicants-card";
import type { ApplicantActions } from "./applicants-columns";
import { DeleteApplicantDialog, ForceDeleteApplicantDialog, ManageApplicantDialog } from "./enrollment-dialogs";
import type { ApplicantRow, EnrollmentApplicantsProps } from "./types";

const FILTER_FIELDS: { key: string; label: string }[] = [
    { key: "department", label: "Department" },
    { key: "course", label: "Program" },
    { key: "student_type", label: "Student level" },
    { key: "scholarship", label: "Scholarship" },
    { key: "applied_from", label: "Applied from" },
    { key: "applied_to", label: "Applied to" },
];

function createInitialFilters(filters: EnrollmentApplicantsProps["filters"]): Filter[] {
    const initial: Filter[] = [];

    for (const field of FILTER_FIELDS) {
        const value = (filters as Record<string, unknown>)[field.key];

        if (typeof value === "string" && value !== "") {
            initial.push({ id: field.key, field: field.key, operator: "is", values: [value] });
        }
    }

    return initial;
}

function filterValue(filters: Filter[], field: string): string | null {
    const value = filters.find((entry) => entry.field === field)?.values[0];

    return value === undefined || value === null || value === "" ? null : String(value);
}

/**
 * Filter set that narrows the view to the last seven days, preserving every
 * other active filter.
 */
function recentFilter(current: Filter[]): Filter[] {
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    const value = sevenDaysAgo.toISOString().slice(0, 10);

    return [
        ...current.filter((entry) => entry.field !== "applied_from"),
        { id: "applied_from", field: "applied_from", operator: "is", values: [value] },
    ];
}

export default function AdministratorEnrollmentApplicants({
    user,
    applicants,
    filters,
    stats,
    stage_counts: stageCounts,
    stage_meta: stageMeta,
    filter_options: filterOptions,
    cancellation_reasons: cancellationReasons,
    enrollment_pipeline: enrollmentPipeline,
    flash,
}: EnrollmentApplicantsProps) {
    const [applicantSearch, setApplicantSearch] = useState(filters.search || "");
    const [activeFilters, setActiveFilters] = useState<Filter[]>(() => createInitialFilters(filters));
    const [isSearching, setIsSearching] = useState(false);

    const [reviewApplicant, setReviewApplicant] = useState<ApplicantRow | null>(null);
    const [selectedApplicant, setSelectedApplicant] = useState<ApplicantRow | null>(null);
    const [scholarshipType, setScholarshipType] = useState<string>("none");
    const [isUpdating, setIsUpdating] = useState(false);

    const [deleteApplicant, setDeleteApplicant] = useState<ApplicantRow | null>(null);
    const [forceDeleteApplicant, setForceDeleteApplicant] = useState<ApplicantRow | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [notifyingApplicantId, setNotifyingApplicantId] = useState<number | null>(null);

    const [cancelApplicant, setCancelApplicant] = useState<ApplicantRow | null>(null);
    const [isCancelling, setIsCancelling] = useState(false);

    const searchInputRef = useRef<HTMLInputElement>(null);
    const searchDraftRef = useRef(filters.search || "");
    const debouncedSearchRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const safeApplicants = Array.isArray(applicants?.data) ? applicants.data : [];
    const applicantsPagination = {
        current_page: applicants?.current_page ?? 1,
        last_page: applicants?.last_page ?? 1,
        per_page: applicants?.per_page ?? 10,
        total: applicants?.total ?? 0,
        next_page_url: applicants?.next_page_url ?? null,
        prev_page_url: applicants?.prev_page_url ?? null,
        from: applicants?.from ?? 0,
        to: applicants?.to ?? 0,
    };

    const pipelineStatusLabels = useMemo(() => {
        const labels: Record<string, string> = {};

        for (const option of enrollmentPipeline?.status_options ?? []) {
            labels[option.value] = option.label;
        }

        return labels;
    }, [enrollmentPipeline?.status_options]);

    const cancelPendingSearch = useCallback(() => {
        if (debouncedSearchRef.current) {
            clearTimeout(debouncedSearchRef.current);
            debouncedSearchRef.current = null;
        }
    }, []);

    // Keyboard shortcut to focus the search field: "/" or "Cmd+K" / "Ctrl+K".
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const isTyping = target?.tagName === "INPUT" || target?.tagName === "TEXTAREA" || target?.isContentEditable === true;

            if (isTyping) return;

            if (event.key === "/" || ((event.metaKey || event.ctrlKey) && event.key === "k")) {
                event.preventDefault();
                searchInputRef.current?.focus();
            }
        };

        window.addEventListener("keydown", handleKeyDown);

        return () => window.removeEventListener("keydown", handleKeyDown);
    }, []);

    useEffect(() => {
        return () => cancelPendingSearch();
    }, [cancelPendingSearch]);

    // Keep local state aligned with the server response without clobbering an in-flight search draft.
    useEffect(() => {
        const serverSearch = filters.search || "";

        if (debouncedSearchRef.current === null) {
            setApplicantSearch(serverSearch);
            searchDraftRef.current = serverSearch;
        } else if (searchDraftRef.current === serverSearch) {
            setApplicantSearch(serverSearch);
        }

        setActiveFilters(createInitialFilters(filters));
    }, [filters]);

    useEffect(() => {
        if (!flash) return;

        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash]);

    const buildQueryParams = useCallback(
        (
            overrides: {
                search?: string;
                filters?: Filter[];
                stage?: string;
                sort?: string;
                direction?: "asc" | "desc";
                per_page?: string | number;
                page?: number;
            } = {},
        ) => {
            const nextFilters = overrides.filters ?? activeFilters;
            const activeSearch = overrides.search !== undefined ? overrides.search : applicantSearch;
            const activeStage = overrides.stage !== undefined ? overrides.stage : (filters.stage ?? "");
            const activeSort = overrides.sort ?? filters.sort ?? "created_at";
            const activeDirection = overrides.direction ?? filters.direction ?? "desc";
            const activePerPage = overrides.per_page ?? filters.per_page ?? 10;

            return {
                search: activeSearch.trim() ? activeSearch.trim() : null,
                stage: activeStage ? activeStage : null,
                department: filterValue(nextFilters, "department"),
                course: filterValue(nextFilters, "course"),
                student_type: filterValue(nextFilters, "student_type"),
                scholarship: filterValue(nextFilters, "scholarship"),
                applied_from: filterValue(nextFilters, "applied_from"),
                applied_to: filterValue(nextFilters, "applied_to"),
                sort: activeSort,
                direction: activeDirection,
                per_page: activePerPage,
                page: overrides.page ?? 1,
            };
        },
        [activeFilters, applicantSearch, filters.direction, filters.per_page, filters.sort, filters.stage],
    );

    const navigate = useCallback((params: Record<string, string | number | null>, options: { replace?: boolean } = {}) => {
        router.get(route("administrators.enrollments.applicants"), params, {
            preserveState: true,
            preserveScroll: true,
            replace: options.replace ?? true,
            only: ["applicants", "filters", "stats", "stage_counts"],
            onStart: () => setIsSearching(true),
            onFinish: () => setIsSearching(false),
        });
    }, []);

    const handleSearchChange = (value: string) => {
        searchDraftRef.current = value;
        setApplicantSearch(value);
        cancelPendingSearch();

        debouncedSearchRef.current = setTimeout(() => {
            debouncedSearchRef.current = null;
            navigate(buildQueryParams({ search: value, page: 1 }));
        }, 300);
    };

    const handleStageChange = (value: string) => {
        navigate(buildQueryParams({ stage: value === "all" ? "" : value, page: 1 }));
    };

    const handleFiltersChange = (nextFilters: Filter[]) => {
        setActiveFilters(nextFilters);
        navigate(buildQueryParams({ filters: nextFilters, page: 1 }));
    };

    const handleSortChange = (sort: string, direction: "asc" | "desc") => {
        navigate(buildQueryParams({ sort, direction, page: 1 }));
    };

    const hasActiveFilters =
        applicantSearch.trim() !== "" || activeFilters.length > 0 || (filters.stage ?? "") !== "" || filters.sort !== "created_at";

    const resetFilters = () => {
        searchDraftRef.current = "";
        setApplicantSearch("");
        setActiveFilters([]);
        cancelPendingSearch();
        navigate(buildQueryParams({ search: "", filters: [], stage: "", sort: "created_at", direction: "desc", page: 1 }));
    };

    const filterFields: FilterFieldConfig[] = useMemo(
        () => [
            {
                key: "department",
                label: "Department",
                type: "select",
                icon: <Building2 className="size-4" />,
                options: filterOptions.departments.map((option) => ({
                    value: option.value,
                    label: option.label,
                    icon: <Building2 className="text-muted-foreground size-4" />,
                })),
            },
            {
                key: "course",
                label: "Program",
                type: "select",
                searchable: true,
                icon: <GraduationCap className="size-4" />,
                options: filterOptions.courses.map((option) => ({
                    value: option.value,
                    label: option.label,
                    icon: <GraduationCap className="text-muted-foreground size-4" />,
                })),
            },
            {
                key: "student_type",
                label: "Student level",
                type: "select",
                icon: <Users className="size-4" />,
                options: filterOptions.student_types.map((option) => ({
                    value: option.value,
                    label: option.label,
                    icon: <Users className="text-muted-foreground size-4" />,
                })),
            },
            {
                key: "scholarship",
                label: "Scholarship",
                type: "select",
                icon: <Sparkles className="size-4" />,
                options: filterOptions.scholarships.map((option) => ({
                    value: option.value,
                    label: option.label,
                    icon: <Sparkles className="text-muted-foreground size-4" />,
                })),
            },
            {
                key: "applied_from",
                label: "Applied from",
                type: "text",
                placeholder: "YYYY-MM-DD",
                icon: <CalendarClock className="size-4" />,
            },
            {
                key: "applied_to",
                label: "Applied to",
                type: "text",
                placeholder: "YYYY-MM-DD",
                icon: <CalendarClock className="size-4" />,
            },
        ],
        [filterOptions],
    );

    const handleCopy = (value: string, label: string) => {
        if (!value) {
            toast.error(`No ${label.toLowerCase()} available to copy.`);
            return;
        }

        navigator.clipboard
            .writeText(value)
            .then(() => toast.success(`${label} copied to clipboard.`))
            .catch(() => toast.error("The clipboard is not available in this browser."));
    };

    const handleManageClick = (applicant: ApplicantRow) => {
        setSelectedApplicant(applicant);
        setScholarshipType(applicant.scholarship_type || "none");
    };

    const handleUpdateScholarship = () => {
        if (!selectedApplicant) return;

        setIsUpdating(true);
        router.patch(
            route("administrators.enrollments.scholarship.update", { student: selectedApplicant.id }),
            {
                scholarship_type: scholarshipType === "none" ? null : scholarshipType,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Scholarship updated for ${selectedApplicant.name}.`);
                    setSelectedApplicant(null);
                    navigate(buildQueryParams({ page: applicantsPagination.current_page }));
                },
                onError: () => {
                    toast.error("Failed to update the scholarship.");
                },
                onFinish: () => {
                    setIsUpdating(false);
                },
            },
        );
    };

    const handleNotifyApproval = (applicant: ApplicantRow) => {
        if (!applicant.email) {
            toast.error("This applicant does not have an email address.");
            return;
        }

        setNotifyingApplicantId(applicant.id);
        router.post(
            route("administrators.enrollments.applicants.notify-approval", { student: applicant.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Approval email queued for ${applicant.name}.`);
                },
                onError: () => {
                    toast.error("Failed to queue the approval email.");
                },
                onFinish: () => {
                    setNotifyingApplicantId(null);
                },
            },
        );
    };

    const handleDeleteApplicant = () => {
        if (!deleteApplicant) return;

        setIsDeleting(true);
        router.delete(route("administrators.students.destroy", { student: deleteApplicant.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${deleteApplicant.name} has been removed from the queue.`);
                setDeleteApplicant(null);
            },
            onError: () => {
                toast.error("Failed to delete the applicant.");
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleForceDeleteApplicant = () => {
        if (!forceDeleteApplicant) return;

        setIsDeleting(true);
        router.delete(route("administrators.students.force-destroy", { student: forceDeleteApplicant.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${forceDeleteApplicant.name} was permanently deleted.`);
                setForceDeleteApplicant(null);
            },
            onError: () => {
                toast.error("Failed to permanently delete the applicant.");
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleCancelApplicant = (payload: { reason: string; note: string; sendEmail: boolean }) => {
        if (!cancelApplicant) return;

        setIsCancelling(true);
        router.post(
            route("administrators.enrollments.applicants.cancel", { student: cancelApplicant.id }),
            {
                reason: payload.reason,
                note: payload.note || null,
                send_email: payload.sendEmail ? 1 : 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Application closed for ${cancelApplicant.name}.`);
                    setCancelApplicant(null);
                },
                onError: (errors) => {
                    const message = Object.values(errors as Record<string, string>)[0];
                    toast.error(message ?? "The application could not be closed.");
                },
                onFinish: () => {
                    setIsCancelling(false);
                },
            },
        );
    };

    const handleRevertCancellation = (applicant: ApplicantRow) => {
        router.post(
            route("administrators.enrollments.applicants.cancel.revert", { student: applicant.id }),
            { reason: "Reversed from the applicants inbox." },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`The application for ${applicant.name} is back in the queue.`);
                    setReviewApplicant(null);
                    navigate(buildQueryParams({ page: 1 }));
                },
                onError: () => {
                    toast.error("The cancellation could not be reversed.");
                },
            },
        );
    };

    const actions: ApplicantActions = {
        onOpenReview: (applicant) => setReviewApplicant(applicant),
        onManageScholarship: handleManageClick,
        onNotifyApproval: handleNotifyApproval,
        onCopy: handleCopy,
        notifyingApplicantId,
        onCancel: (applicant) => setCancelApplicant(applicant),
        onDelete: setDeleteApplicant,
        onForceDelete: setForceDeleteApplicant,
    };

    const statCards = [
        {
            key: "recent",
            label: "New this week",
            value: stats.recent,
            detail: "Applications received in the last 7 days",
            icon: Sparkles,
            tone: "text-blue-600 dark:text-blue-400",
            onClick: () => navigate(buildQueryParams({ filters: recentFilter(activeFilters), page: 1 })),
        },
        {
            key: "awaiting_documents",
            label: "Awaiting documents",
            value: stats.awaiting_documents,
            detail: "Required uploads still missing",
            icon: FileWarning,
            tone: "text-amber-600 dark:text-amber-400",
            onClick: () => handleStageChange("awaiting_documents"),
        },
        {
            key: "ready_for_review",
            label: "Ready for review",
            value: stats.ready_for_review,
            detail: "Documents complete, awaiting a decision",
            icon: ClipboardCheck,
            tone: "text-violet-600 dark:text-violet-400",
            onClick: () => handleStageChange("ready_for_review"),
        },
        {
            key: "reviewed",
            label: "Reviewed",
            value: stats.reviewed,
            detail: "Checklist cleared by a registrar",
            icon: CheckCircle2,
            tone: "text-emerald-600 dark:text-emerald-400",
            onClick: () => handleStageChange("reviewed"),
        },
    ];

    const insights = [
        {
            show: stats.recent > 0,
            count: stats.recent,
            message: (count: number) => (
                <>
                    <span className="font-semibold">
                        {count} new application{count === 1 ? "" : "s"}
                    </span>{" "}
                    arrived in the last 7 days.
                </>
            ),
            stage: "",
            tone: "border-blue-200/70 bg-blue-50/60 dark:border-blue-900/50 dark:bg-blue-950/20",
            chip: "bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300",
            icon: Sparkles,
        },
        {
            show: stats.awaiting_documents > 0,
            count: stats.awaiting_documents,
            message: (count: number) => (
                <>
                    <span className="font-semibold">
                        {count} applicant{count === 1 ? "" : "s"}
                    </span>{" "}
                    still missing required documents.
                </>
            ),
            stage: "awaiting_documents",
            tone: "border-amber-200/70 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-950/20",
            chip: "bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300",
            icon: FileWarning,
        },
        {
            show: stats.ready_for_review > 0,
            count: stats.ready_for_review,
            message: (count: number) => (
                <>
                    <span className="font-semibold">
                        {count} applicant{count === 1 ? "" : "s"}
                    </span>{" "}
                    submitted everything and are ready to review.
                </>
            ),
            stage: "ready_for_review",
            tone: "border-violet-200/70 bg-violet-50/60 dark:border-violet-900/50 dark:bg-violet-950/20",
            chip: "bg-violet-100 text-violet-700 dark:bg-violet-900/60 dark:text-violet-300",
            icon: ClipboardCheck,
        },
    ];

    const activeInsight = insights.find((insight) => insight.show);

    return (
        <AdminLayout user={user} title="Admissions">
            <Head title="Administrators • Applicants" />

            <div className="relative space-y-6 pb-10">
                <div className="pointer-events-none absolute -top-24 right-0 size-72 rounded-full bg-blue-500/5 blur-3xl" aria-hidden="true" />

                <header className="border-border/70 relative flex flex-col gap-6 border-b pb-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-3">
                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase">
                            <span className="size-1.5 rounded-full bg-blue-500" aria-hidden="true" />
                            Admissions workspace
                            <span className="border-border/70 text-muted-foreground rounded-full border px-2 py-0.5 tracking-normal normal-case">
                                Online intake
                            </span>
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-[-0.03em] sm:text-4xl">Applicants</h1>
                            <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                                Triage applications submitted through the online enrollment portal, chase missing documents, and move students into
                                enrollment.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="flex flex-wrap items-center gap-2 text-xs">
                            <Badge className="rounded-full bg-blue-600 px-2.5 py-0.5 font-medium text-white hover:bg-blue-600">
                                {stats.total} application{stats.total === 1 ? "" : "s"}
                            </Badge>
                            {stats.with_scholarship > 0 && (
                                <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 font-medium">
                                    {stats.with_scholarship} with scholarship
                                </Badge>
                            )}
                            {stats.cancelled > 0 && (
                                <Badge
                                    variant="outline"
                                    className="rounded-full border-rose-200 px-2.5 py-0.5 font-medium text-rose-700 dark:border-rose-800 dark:text-rose-300"
                                >
                                    {stats.cancelled} cancelled
                                </Badge>
                            )}
                            {stats.deleted > 0 && (
                                <Badge variant="outline" className="rounded-full px-2.5 py-0.5 font-medium">
                                    {stats.deleted} deleted
                                </Badge>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" render={<Link href={route("administrators.enrollments.index")} />}>
                                Enrollments
                            </Button>
                            <Button
                                className="gap-2 bg-blue-600 text-white shadow-blue-600/20 hover:bg-blue-500 hover:shadow-md"
                                render={<Link href={route("administrators.enrollments.create")} />}
                            >
                                <UserPlus className="size-4" aria-hidden="true" />
                                New enrollment
                            </Button>
                        </div>
                    </div>
                </header>

                <div className="relative grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((metric) => (
                        <Card
                            key={metric.key}
                            size="sm"
                            className="border-border/70 bg-card/80 hover:border-primary/40 hover:bg-card gap-0 py-0 shadow-sm transition-colors"
                        >
                            <CardContent className="p-0">
                                <button
                                    type="button"
                                    onClick={metric.onClick}
                                    className="flex w-full items-start justify-between gap-4 p-4 text-left sm:p-5"
                                >
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">{metric.label}</p>
                                        <p className="mt-2 text-2xl font-semibold tracking-[-0.03em] tabular-nums sm:text-3xl">{metric.value}</p>
                                        <p className="text-muted-foreground mt-1 truncate text-xs">{metric.detail}</p>
                                    </div>
                                    <div className="bg-muted/60 border-border/60 flex size-10 shrink-0 items-center justify-center rounded-lg border">
                                        <metric.icon className={`size-5 ${metric.tone}`} aria-hidden="true" />
                                    </div>
                                </button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {activeInsight && (
                    <div
                        className={cn(
                            "flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between",
                            activeInsight.tone,
                        )}
                    >
                        <div className="flex items-center gap-3">
                            <div className={cn("flex size-8 shrink-0 items-center justify-center rounded-full", activeInsight.chip)}>
                                <activeInsight.icon className="size-4" aria-hidden="true" />
                            </div>
                            <p className="text-foreground text-sm">{activeInsight.message(activeInsight.count)}</p>
                        </div>
                        {activeInsight.stage && (
                            <button
                                type="button"
                                onClick={() => handleStageChange(activeInsight.stage)}
                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs font-semibold"
                            >
                                Show these applicants
                                <ArrowRight className="size-3.5" aria-hidden="true" />
                            </button>
                        )}
                    </div>
                )}

                <ApplicantsCard
                    applicants={safeApplicants}
                    pagination={applicantsPagination}
                    filters={buildQueryParams({ page: applicantsPagination.current_page })}
                    applicantSearch={applicantSearch}
                    isSearching={isSearching}
                    onSearchChange={handleSearchChange}
                    searchInputRef={searchInputRef}
                    stage={filters.stage ?? ""}
                    onStageChange={handleStageChange}
                    stageCounts={stageCounts}
                    stageMeta={stageMeta}
                    sort={filters.sort ?? "created_at"}
                    direction={filters.direction ?? "desc"}
                    onSortChange={handleSortChange}
                    filterFields={filterFields}
                    activeFilters={activeFilters}
                    onFiltersChange={handleFiltersChange}
                    onResetFilters={resetFilters}
                    hasActiveFilters={hasActiveFilters}
                    onRowClick={(applicant) => setReviewApplicant(applicant)}
                    actions={actions}
                    pipelineStatusLabels={pipelineStatusLabels}
                />
            </div>

            <ApplicantDrawer
                open={!!reviewApplicant}
                onOpenChange={(open) => !open && setReviewApplicant(null)}
                applicant={reviewApplicant}
                onNotifyApproval={handleNotifyApproval}
                onManageScholarship={handleManageClick}
                onRevertCancellation={handleRevertCancellation}
                notifyingApplicantId={notifyingApplicantId}
                stageMeta={stageMeta}
                pipelineStatusLabels={pipelineStatusLabels}
            />

            <ApplicantCancellationDialog
                open={!!cancelApplicant}
                onOpenChange={(open) => !open && setCancelApplicant(null)}
                applicant={cancelApplicant}
                reasons={cancellationReasons}
                onConfirm={handleCancelApplicant}
                isCancelling={isCancelling}
            />

            <ManageApplicantDialog
                open={!!selectedApplicant}
                applicant={selectedApplicant}
                scholarshipType={scholarshipType}
                onScholarshipChange={setScholarshipType}
                onOpenChange={(open) => !open && setSelectedApplicant(null)}
                onSave={handleUpdateScholarship}
                isUpdating={isUpdating}
            />

            <DeleteApplicantDialog
                open={!!deleteApplicant}
                applicant={deleteApplicant}
                isDeleting={isDeleting}
                onOpenChange={(open) => !open && setDeleteApplicant(null)}
                onConfirm={handleDeleteApplicant}
            />

            <ForceDeleteApplicantDialog
                open={!!forceDeleteApplicant}
                applicant={forceDeleteApplicant}
                isDeleting={isDeleting}
                onOpenChange={(open) => !open && setForceDeleteApplicant(null)}
                onConfirm={handleForceDeleteApplicant}
            />
        </AdminLayout>
    );
}
