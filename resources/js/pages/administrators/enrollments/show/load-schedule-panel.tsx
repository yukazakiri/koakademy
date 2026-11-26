import {
    getSubjectSections,
    swapSubjectSection,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import {
    autoAssign as autoAssignWaitlists,
    destroy as destroyWaitlist,
    promote as promoteWaitlist,
    store as storeWaitlist,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentWaitlistController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeftRight,
    BookOpen,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    ExternalLink,
    ListChecks,
    Loader2,
    Plus,
    RefreshCw,
    Repeat,
    Sparkles,
    Trash2,
    UserPlus,
    Users,
} from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import type { SchedulingPolicy } from "../../classes/lib/class-defaults";
import { formatMoney, formatShortDate } from "../enrollment-utils";
import {
    findCandidateSectionConflicts,
    resolveSchedulingPolicy,
} from "../workbench/schedule-conflicts";
import { ScheduleMatrixPanel } from "../workbench/schedule-matrix-panel";
import type {
    DraftSubjectEnrollment,
    ScheduleConflict,
    SectionOption,
} from "../workbench/types";
import type {
    EnrollmentData,
    ShowEnrollmentPageProps,
    SubjectEnrolledViewItem,
} from "./types";

export interface LoadSchedulePanelProps {
    enrollment: EnrollmentData;
    schedulingPolicy?: Partial<SchedulingPolicy>;
    auth: ShowEnrollmentPageProps["auth"];
    currency: string;
    scheduleConflicts: ScheduleConflict[];
    draftSubjectsForMatrix: DraftSubjectEnrollment[];
    onOpenLoadChange: () => void;
    onOpenTransfer: () => void;
    onRetryAutoEnroll: () => void;
    retryingAutoEnroll: boolean;
    onEnrollClass: (classId: number, force: boolean) => void;
    enrollingClassId: number | null;
}

function hasGradeValue(value: number | null | undefined): value is number {
    return value !== null && value !== undefined && !Number.isNaN(value);
}

function formatGradeCell(value: number | null | undefined): string {
    if (!hasGradeValue(value)) {
        return "—";
    }
    return Number.isInteger(value) ? String(value) : value.toFixed(2);
}

interface QuickSectionSwapPopoverProps {
    enrollment: EnrollmentData;
    subject: SubjectEnrolledViewItem;
    draftSubjectsForMatrix: DraftSubjectEnrollment[];
    schedulingPolicy?: Partial<SchedulingPolicy>;
}

function QuickSectionSwapPopover({
    enrollment,
    subject,
    draftSubjectsForMatrix,
    schedulingPolicy,
}: QuickSectionSwapPopoverProps) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [sections, setSections] = useState<SectionOption[]>([]);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const [selectedClassId, setSelectedClassId] = useState<number | null>(
        subject.class_id,
    );
    const [forceOverload, setForceOverload] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const selectedOption = useMemo(
        () => sections.find((sec) => sec.id === selectedClassId) ?? null,
        [sections, selectedClassId],
    );

    const handleOpenChange = async (nextOpen: boolean) => {
        setOpen(nextOpen);
        if (!nextOpen) {
            return;
        }

        setSelectedClassId(subject.class_id);
        setForceOverload(false);
        setFetchError(null);

        if (!subject.subject_id || !enrollment.course_id) {
            setSections([]);
            return;
        }

        setLoading(true);
        try {
            const url = getSubjectSections.url({
                query: {
                    school_year: enrollment.school_year,
                    semester: enrollment.semester,
                    course_id: enrollment.course_id,
                    subject_id: subject.subject_id,
                },
            });
            const response = await fetch(url, {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            if (!response.ok) {
                throw new Error("Could not load available sections.");
            }
            const data = (await response.json()) as SectionOption[];
            setSections(Array.isArray(data) ? data : []);
        } catch (err) {
            setFetchError(
                err instanceof Error
                    ? err.message
                    : "Failed to load available sections.",
            );
        } finally {
            setLoading(false);
        }
    };

    const handleSwapSubmit = () => {
        if (selectedClassId === subject.class_id) {
            setOpen(false);
            return;
        }

        setSubmitting(true);
        router.patch(
            swapSubjectSection.url({
                enrollment: enrollment.id,
                subjectEnrollment: subject.id,
            }),
            {
                class_id: selectedClassId,
                force_overload: selectedOption?.is_full ? forceOverload : false,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    toast.success(`Updated section for ${subject.subject_code}.`);
                },
                onError: (errors) => {
                    const msg =
                        Object.values(errors)[0] ??
                        "Unable to swap class section.";
                    toast.error(String(msg));
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        buttonVariants({ variant: "outline", size: "xs" }),
                        "gap-1",
                    )}
                >
                    <Repeat className="size-3" />
                    Swap
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-80 max-w-[calc(100vw-2rem)] p-3"
            >
                <div className="space-y-2.5">
                    <div className="space-y-0.5">
                        <p className="text-xs font-semibold text-foreground">
                            Quick Section Swap · {subject.subject_code}
                        </p>
                        <p className="text-[11px] text-muted-foreground">
                            Choose a class offering for {enrollment.school_year}{" "}
                            ({enrollment.semester_label}).
                        </p>
                    </div>

                    {loading ? (
                        <div className="flex items-center justify-center gap-2 py-6 text-xs text-muted-foreground">
                            <Loader2 className="size-3.5 animate-spin" />
                            Loading sections…
                        </div>
                    ) : fetchError ? (
                        <p className="rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive">
                            {fetchError}
                        </p>
                    ) : sections.length === 0 ? (
                        <p className="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                            No class sections found for this subject in the
                            current term.
                        </p>
                    ) : (
                        <div className="max-h-56 space-y-1.5 overflow-y-auto pr-0.5">
                            {subject.class_id !== null && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedClassId(null);
                                        setForceOverload(false);
                                    }}
                                    className={cn(
                                        "flex w-full items-center justify-between rounded-md border px-2.5 py-2 text-left text-xs transition-colors",
                                        selectedClassId === null
                                            ? "border-primary bg-primary/5 text-foreground"
                                            : "border-border hover:bg-muted/60",
                                    )}
                                >
                                    <div>
                                        <p className="font-medium">
                                            Unassign section
                                        </p>
                                        <p className="text-[11px] text-muted-foreground">
                                            Keep subject load without a class
                                        </p>
                                    </div>
                                    {selectedClassId === null && (
                                        <Check className="size-3.5 text-primary" />
                                    )}
                                </button>
                            )}

                            {sections.map((sec) => {
                                const isCurrent = sec.id === subject.class_id;
                                const isSelected = sec.id === selectedClassId;
                                const conflictWarnings =
                                    subject.subject_id !== null
                                        ? findCandidateSectionConflicts(
                                              subject.subject_id,
                                              sec,
                                              draftSubjectsForMatrix,
                                              schedulingPolicy,
                                          )
                                        : [];

                                return (
                                    <button
                                        key={sec.id}
                                        type="button"
                                        onClick={() => {
                                            setSelectedClassId(sec.id);
                                            if (!sec.is_full) {
                                                setForceOverload(false);
                                            }
                                        }}
                                        className={cn(
                                            "flex w-full flex-col gap-1 rounded-md border px-2.5 py-2 text-left text-xs transition-colors",
                                            isSelected
                                                ? "border-primary bg-primary/5"
                                                : "border-border hover:bg-muted/60",
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold text-foreground">
                                                Section {sec.section}
                                                {isCurrent && (
                                                    <span className="ml-1.5 text-[10px] font-normal text-muted-foreground">
                                                        (Current)
                                                    </span>
                                                )}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <Badge
                                                    size="xs"
                                                    variant={
                                                        sec.is_full
                                                            ? "destructive-light"
                                                            : "success-light"
                                                    }
                                                    className="tabular-nums"
                                                >
                                                    {sec.max_slots > 0
                                                        ? `${sec.enrolled_count}/${sec.max_slots}`
                                                        : `${sec.enrolled_count} · ∞`}
                                                </Badge>
                                                {isSelected && (
                                                    <Check className="size-3.5 text-primary" />
                                                )}
                                            </div>
                                        </div>
                                        <p className="truncate text-[11px] text-muted-foreground">
                                            {sec.faculty} · {sec.schedule}
                                        </p>
                                        {conflictWarnings.length > 0 && (
                                            <p className="flex items-center gap-1 text-[10px] font-medium text-destructive">
                                                <AlertTriangle className="size-3 shrink-0" />
                                                Clashes with{" "}
                                                {conflictWarnings.join(", ")}
                                            </p>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {selectedOption?.is_full &&
                        selectedClassId !== subject.class_id && (
                            <div className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 p-2">
                                <Checkbox
                                    id={`overload-${subject.id}`}
                                    checked={forceOverload}
                                    onCheckedChange={(checked) =>
                                        setForceOverload(Boolean(checked))
                                    }
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor={`overload-${subject.id}`}
                                    className="cursor-pointer text-[11px] leading-snug text-foreground"
                                >
                                    Approve section overload (
                                    {selectedOption.enrolled_count}/
                                    {selectedOption.max_slots} seats filled)
                                </Label>
                            </div>
                        )}

                    <div className="flex items-center justify-end gap-1.5 border-t pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="xs"
                            onClick={() => setOpen(false)}
                            disabled={submitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="xs"
                            onClick={handleSwapSubmit}
                            disabled={
                                submitting ||
                                loading ||
                                selectedClassId === subject.class_id ||
                                (Boolean(selectedOption?.is_full) &&
                                    !forceOverload)
                            }
                        >
                            {submitting ? (
                                <>
                                    <Loader2 className="size-3 animate-spin" />
                                    Saving…
                                </>
                            ) : (
                                "Apply Swap"
                            )}
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}

export function LoadSchedulePanel({
    enrollment,
    schedulingPolicy,
    auth,
    currency,
    scheduleConflicts,
    draftSubjectsForMatrix,
    onOpenLoadChange,
    onOpenTransfer,
    onRetryAutoEnroll,
    retryingAutoEnroll,
    onEnrollClass,
    enrollingClassId,
}: LoadSchedulePanelProps) {
    const [viewMode, setViewMode] = useState<"subjects" | "timetable">(
        "subjects",
    );
    const [waitlistBusyKey, setWaitlistBusyKey] = useState<string | null>(null);

    const canMutateLoad =
        auth.can_manage_enrollment &&
        !enrollment.is_trashed &&
        !enrollment.workflow.is_terminal_locked;

    const totalSubjects = enrollment.subjects_enrolled.length;
    const totalUnits = useMemo(
        () =>
            enrollment.subjects_enrolled.reduce(
                (sum, sub) => sum + Number(sub.units || 0),
                0,
            ),
        [enrollment.subjects_enrolled],
    );
    const assignedCount = useMemo(
        () =>
            enrollment.subjects_enrolled.filter((sub) => sub.class_id !== null)
                .length,
        [enrollment.subjects_enrolled],
    );
    const unassignedSubjectEnrollmentIds = useMemo(
        () =>
            enrollment.subjects_enrolled
                .filter((sub) => sub.class_id === null)
                .map((sub) => sub.id),
        [enrollment.subjects_enrolled],
    );

    const conflictSubjectIds = useMemo(() => {
        const set = new Set<number>();
        for (const conflict of scheduleConflicts) {
            set.add(conflict.subjectAId);
            set.add(conflict.subjectBId);
        }
        return set;
    }, [scheduleConflicts]);

    const resolvedPolicy = useMemo(
        () => resolveSchedulingPolicy(schedulingPolicy),
        [schedulingPolicy],
    );

    const handleJoinWaitlist = (
        subjectEnrollmentId: number,
        preferredClassId: number | null,
    ) => {
        const key = `join-${subjectEnrollmentId}`;
        setWaitlistBusyKey(key);
        router.post(
            storeWaitlist.url(),
            {
                subject_enrollment_id: subjectEnrollmentId,
                preferred_class_id: preferredClassId,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Student added to waitlist.");
                },
                onError: () => {
                    toast.error("Failed to add student to waitlist.");
                },
                onFinish: () => setWaitlistBusyKey(null),
            },
        );
    };

    const handleAutoAssignOpenSeats = () => {
        setWaitlistBusyKey("auto-assign");
        router.post(
            autoAssignWaitlists.url(),
            {
                subject_enrollment_ids:
                    unassignedSubjectEnrollmentIds.length > 0
                        ? unassignedSubjectEnrollmentIds
                        : undefined,
                waitlist_when_full: true,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Auto-assign completed.");
                },
                onError: () => {
                    toast.error("Unable to auto-assign open seats.");
                },
                onFinish: () => setWaitlistBusyKey(null),
            },
        );
    };

    const handlePromoteWaitlist = (
        waitlistId: number,
        preferredClassId: number | null,
    ) => {
        const key = `promote-${waitlistId}`;
        setWaitlistBusyKey(key);
        router.post(
            promoteWaitlist.url(waitlistId),
            {
                class_id: preferredClassId,
                force_overload: false,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Promoted student from waitlist.");
                },
                onError: () => {
                    toast.error("Could not promote waitlist entry.");
                },
                onFinish: () => setWaitlistBusyKey(null),
            },
        );
    };

    const handleRemoveWaitlist = (waitlistId: number) => {
        const key = `remove-${waitlistId}`;
        setWaitlistBusyKey(key);
        router.delete(destroyWaitlist.url(waitlistId), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Waitlist entry removed.");
            },
            onError: () => {
                toast.error("Could not remove waitlist entry.");
            },
            onFinish: () => setWaitlistBusyKey(null),
        });
    };

    const hasMissingOrWaitlists =
        enrollment.missing_classes.length > 0 ||
        enrollment.waitlists.length > 0;

    return (
        <div className="min-w-0 space-y-4">
            {/* Top Bar: View toggle, summary counts, quick actions */}
            <Frame spacing="sm">
                <FramePanel className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <div
                            role="tablist"
                            aria-label="Load and schedule view"
                            className="inline-flex items-center rounded-lg border border-border bg-muted/60 p-0.5"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={viewMode === "subjects"}
                                onClick={() => setViewMode("subjects")}
                                className={cn(
                                    "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
                                    viewMode === "subjects"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <ListChecks className="size-3.5" />
                                Subjects & Sections
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={viewMode === "timetable"}
                                onClick={() => setViewMode("timetable")}
                                className={cn(
                                    "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
                                    viewMode === "timetable"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <CalendarDays className="size-3.5" />
                                Weekly Timetable
                            </button>
                        </div>

                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant="outline" className="tabular-nums">
                                <BookOpen className="size-3" />
                                {totalSubjects}{" "}
                                {totalSubjects === 1 ? "Subject" : "Subjects"}
                            </Badge>
                            <Badge
                                variant="primary-light"
                                className="tabular-nums"
                            >
                                {totalUnits} Units
                            </Badge>
                            <Badge
                                variant={
                                    assignedCount === totalSubjects &&
                                    totalSubjects > 0
                                        ? "success-light"
                                        : "warning-light"
                                }
                                className="tabular-nums"
                            >
                                {assignedCount}/{totalSubjects} Assigned
                            </Badge>
                            {scheduleConflicts.length > 0 ? (
                                <Badge
                                    variant="destructive-light"
                                    className="tabular-nums"
                                >
                                    <AlertTriangle className="size-3" />
                                    {scheduleConflicts.length}{" "}
                                    {scheduleConflicts.length === 1
                                        ? "Clash"
                                        : "Clashes"}
                                </Badge>
                            ) : (
                                <Badge variant="success-light">
                                    <CheckCircle2 className="size-3" />
                                    No Clashes
                                </Badge>
                            )}
                        </div>
                    </div>

                    {auth.can_manage_enrollment && (
                        <div className="flex flex-wrap items-center gap-2">
                            {unassignedSubjectEnrollmentIds.length > 0 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={onRetryAutoEnroll}
                                    disabled={
                                        !canMutateLoad || retryingAutoEnroll
                                    }
                                >
                                    {retryingAutoEnroll ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <RefreshCw className="size-3.5" />
                                    )}
                                    Retry Auto-Enroll
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onOpenLoadChange}
                                disabled={!canMutateLoad}
                            >
                                <ArrowLeftRight className="size-3.5" />
                                Add / Drop / Swap
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onOpenTransfer}
                                disabled={!canMutateLoad}
                            >
                                <Repeat className="size-3.5" />
                                Shift / Transfer
                            </Button>
                        </div>
                    )}
                </FramePanel>
            </Frame>

            {enrollment.pending_class_schedule_changes.length > 0 && (
                <Alert variant="warning">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>
                        {enrollment.pending_class_schedule_changes.length}{" "}
                        pending class schedule{" "}
                        {enrollment.pending_class_schedule_changes.length === 1
                            ? "change"
                            : "changes"}
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="mt-1 space-y-1 text-xs">
                            {enrollment.pending_class_schedule_changes.map(
                                (change) => (
                                    <li key={change.id}>
                                        <span className="font-medium text-foreground">
                                            {change.subject_code ??
                                                change.class_title}
                                            {change.section
                                                ? ` (${change.section})`
                                                : ""}
                                        </span>
                                        :{" "}
                                        {change.new_schedule.length > 0
                                            ? change.new_schedule.join(", ")
                                            : "Schedule updated"}
                                        {change.changed_by
                                            ? ` · by ${change.changed_by}`
                                            : ""}
                                    </li>
                                ),
                            )}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            {/* Main Content */}
            {viewMode === "timetable" ? (
                <ScheduleMatrixPanel
                    subjectsEnrolled={draftSubjectsForMatrix}
                    scheduleConflicts={scheduleConflicts}
                    schedulingPolicy={resolvedPolicy}
                />
            ) : (
                <>
                    <Frame>
                        <FrameHeader className="flex-row items-center justify-between gap-3">
                            <div className="flex items-center gap-2.5">
                                <IconTile
                                    variant="soft"
                                    size="sm"
                                    className="text-primary"
                                >
                                    <BookOpen />
                                </IconTile>
                                <div className="min-w-0">
                                    <FrameTitle>
                                        Enrolled Subjects & Class Assignments
                                    </FrameTitle>
                                    <FrameDescription>
                                        All {totalSubjects} subjects in this
                                        enrollment load, including unassigned
                                        and waitlisted subjects.
                                    </FrameDescription>
                                </div>
                            </div>
                        </FrameHeader>
                        <FramePanel className="p-0">
                            {enrollment.subjects_enrolled.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 px-6 py-10 text-center">
                                    <BookOpen className="size-8 text-muted-foreground/40" />
                                    <p className="text-sm font-medium text-foreground">
                                        No subjects enrolled yet
                                    </p>
                                    <p className="max-w-sm text-xs text-muted-foreground">
                                        Use Add / Drop / Swap or open the
                                        workbench to add subjects to this
                                        student&apos;s load.
                                    </p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="min-w-[210px] pl-4">
                                                Subject & Flags
                                            </TableHead>
                                            <TableHead className="whitespace-nowrap">
                                                Units & Fees
                                            </TableHead>
                                            <TableHead className="min-w-[200px]">
                                                Section, Faculty & Schedule
                                            </TableHead>
                                            <TableHead className="whitespace-nowrap">
                                                Grades (P / M / F / Avg)
                                            </TableHead>
                                            {canMutateLoad && (
                                                <TableHead className="pr-4 text-right">
                                                    Section
                                                </TableHead>
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {enrollment.subjects_enrolled.map(
                                            (sub) => {
                                                const hasClash =
                                                    sub.subject_id !== null &&
                                                    conflictSubjectIds.has(
                                                        sub.subject_id,
                                                    );
                                                const prelim =
                                                    sub.grades?.prelim ?? null;
                                                const midterm =
                                                    sub.grades?.midterm ?? null;
                                                const finals =
                                                    sub.grades?.finals ?? null;
                                                const average =
                                                    sub.grades?.average ??
                                                    sub.grade ??
                                                    null;
                                                const hasAnyGrade =
                                                    hasGradeValue(prelim) ||
                                                    hasGradeValue(midterm) ||
                                                    hasGradeValue(finals) ||
                                                    hasGradeValue(average);

                                                return (
                                                    <TableRow
                                                        key={sub.id}
                                                        className={cn(
                                                            hasClash &&
                                                                "bg-destructive/5 hover:bg-destructive/10",
                                                        )}
                                                    >
                                                        <TableCell className="py-3 pl-4 align-top">
                                                            <div className="min-w-0 space-y-1">
                                                                <div className="flex flex-wrap items-center gap-1.5">
                                                                    <span className="font-mono text-xs font-semibold text-foreground">
                                                                        {
                                                                            sub.subject_code
                                                                        }
                                                                    </span>
                                                                    {sub.is_modular && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="info-light"
                                                                        >
                                                                            Modular
                                                                        </Badge>
                                                                    )}
                                                                    {sub.exclude_from_tuition && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="secondary"
                                                                        >
                                                                            Excluded
                                                                        </Badge>
                                                                    )}
                                                                    {sub.is_failed_retake && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="warning-light"
                                                                        >
                                                                            Retake
                                                                        </Badge>
                                                                    )}
                                                                    {sub.already_passed && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="success-light"
                                                                        >
                                                                            Passed
                                                                        </Badge>
                                                                    )}
                                                                    {!sub.prerequisite_satisfied && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="destructive-light"
                                                                            title={
                                                                                sub.missing_prerequisite ??
                                                                                "Missing prerequisite"
                                                                            }
                                                                        >
                                                                            Missing
                                                                            Prereq
                                                                            {sub.missing_prerequisite
                                                                                ? `: ${sub.missing_prerequisite}`
                                                                                : ""}
                                                                        </Badge>
                                                                    )}
                                                                    {sub.recommended && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="primary-light"
                                                                        >
                                                                            Recommended
                                                                        </Badge>
                                                                    )}
                                                                    {sub.waitlist && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="warning-outline"
                                                                            className="tabular-nums"
                                                                        >
                                                                            <Clock className="size-2.5" />
                                                                            Waitlist
                                                                            #
                                                                            {sub
                                                                                .waitlist
                                                                                .position ??
                                                                                "—"}
                                                                        </Badge>
                                                                    )}
                                                                    {hasClash && (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="destructive"
                                                                        >
                                                                            Clash
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <p className="text-xs leading-snug text-muted-foreground">
                                                                    {
                                                                        sub.subject_title
                                                                    }
                                                                </p>
                                                            </div>
                                                        </TableCell>

                                                        <TableCell className="py-3 align-top tabular-nums">
                                                            <div className="space-y-0.5 text-xs">
                                                                <p className="font-medium text-foreground">
                                                                    {sub.units}{" "}
                                                                    units
                                                                    <span className="ml-1 font-normal text-muted-foreground">
                                                                        (
                                                                        {
                                                                            sub.lecture_units
                                                                        }
                                                                        /
                                                                        {
                                                                            sub.laboratory_units
                                                                        }
                                                                        )
                                                                    </span>
                                                                </p>
                                                                <p className="text-[11px] text-muted-foreground">
                                                                    Lec{" "}
                                                                    {formatMoney(
                                                                        sub.lecture_fee ??
                                                                            0,
                                                                        currency,
                                                                    )}
                                                                    {(sub.lab_fee ??
                                                                        0) >
                                                                        0 &&
                                                                        ` · Lab ${formatMoney(sub.lab_fee, currency)}`}
                                                                </p>
                                                            </div>
                                                        </TableCell>

                                                        <TableCell className="py-3 align-top">
                                                            <div className="min-w-0 space-y-1 text-xs">
                                                                <div className="flex flex-wrap items-center gap-1.5">
                                                                    {sub.section ? (
                                                                        sub.class_url ? (
                                                                            <Link
                                                                                href={
                                                                                    sub.class_url
                                                                                }
                                                                                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                                                            >
                                                                                Section{" "}
                                                                                {
                                                                                    sub.section
                                                                                }
                                                                                <ExternalLink className="size-3" />
                                                                            </Link>
                                                                        ) : (
                                                                            <span className="font-medium text-foreground">
                                                                                Section{" "}
                                                                                {
                                                                                    sub.section
                                                                                }
                                                                            </span>
                                                                        )
                                                                    ) : (
                                                                        <Badge
                                                                            size="xs"
                                                                            variant="warning-light"
                                                                        >
                                                                            Unassigned
                                                                        </Badge>
                                                                    )}
                                                                    {sub.room &&
                                                                        sub.room !==
                                                                            "TBA" && (
                                                                            <span className="text-[11px] text-muted-foreground">
                                                                                ·{" "}
                                                                                {
                                                                                    sub.room
                                                                                }
                                                                            </span>
                                                                        )}
                                                                </div>
                                                                <p className="truncate text-[11px] text-muted-foreground">
                                                                    {sub.faculty ||
                                                                        "TBA"}
                                                                </p>
                                                                <p className="text-[11px] text-muted-foreground">
                                                                    {sub.schedule ||
                                                                        "Schedule TBA"}
                                                                </p>
                                                            </div>
                                                        </TableCell>

                                                        <TableCell className="py-3 align-top tabular-nums">
                                                            {hasAnyGrade ? (
                                                                <div className="flex flex-wrap items-center gap-1 text-xs">
                                                                    <span
                                                                        className={cn(
                                                                            "rounded bg-muted/70 px-1.5 py-0.5 text-[11px]",
                                                                            hasGradeValue(
                                                                                prelim,
                                                                            )
                                                                                ? "font-medium text-foreground"
                                                                                : "text-muted-foreground/60",
                                                                        )}
                                                                        title="Prelim"
                                                                    >
                                                                        {formatGradeCell(
                                                                            prelim,
                                                                        )}
                                                                    </span>
                                                                    <span
                                                                        className={cn(
                                                                            "rounded bg-muted/70 px-1.5 py-0.5 text-[11px]",
                                                                            hasGradeValue(
                                                                                midterm,
                                                                            )
                                                                                ? "font-medium text-foreground"
                                                                                : "text-muted-foreground/60",
                                                                        )}
                                                                        title="Midterm"
                                                                    >
                                                                        {formatGradeCell(
                                                                            midterm,
                                                                        )}
                                                                    </span>
                                                                    <span
                                                                        className={cn(
                                                                            "rounded bg-muted/70 px-1.5 py-0.5 text-[11px]",
                                                                            hasGradeValue(
                                                                                finals,
                                                                            )
                                                                                ? "font-medium text-foreground"
                                                                                : "text-muted-foreground/60",
                                                                        )}
                                                                        title="Finals"
                                                                    >
                                                                        {formatGradeCell(
                                                                            finals,
                                                                        )}
                                                                    </span>
                                                                    <Badge
                                                                        size="xs"
                                                                        variant={
                                                                            hasGradeValue(
                                                                                average,
                                                                            )
                                                                                ? "info-light"
                                                                                : "outline"
                                                                        }
                                                                        className="tabular-nums"
                                                                        title="Average / Final Grade"
                                                                    >
                                                                        {formatGradeCell(
                                                                            average,
                                                                        )}
                                                                    </Badge>
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-muted-foreground">
                                                                    No grades
                                                                </span>
                                                            )}
                                                        </TableCell>

                                                        {canMutateLoad && (
                                                            <TableCell className="py-3 pr-4 text-right align-top">
                                                                {sub.subject_id !==
                                                                    null &&
                                                                enrollment.course_id !==
                                                                    null ? (
                                                                    <QuickSectionSwapPopover
                                                                        enrollment={
                                                                            enrollment
                                                                        }
                                                                        subject={
                                                                            sub
                                                                        }
                                                                        draftSubjectsForMatrix={
                                                                            draftSubjectsForMatrix
                                                                        }
                                                                        schedulingPolicy={
                                                                            schedulingPolicy
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        —
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                        )}
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            )}
                        </FramePanel>
                    </Frame>

                    {/* Missing Class Offerings & Waitlist Queue */}
                    {hasMissingOrWaitlists && (
                        <Frame>
                            <FrameHeader className="flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-2.5">
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className="text-warning"
                                    >
                                        <Users />
                                    </IconTile>
                                    <div>
                                        <FrameTitle>
                                            Missing Class Offerings & Waitlist
                                            Queue
                                        </FrameTitle>
                                        <FrameDescription>
                                            Enroll available offerings, force
                                            overload full seats, or manage
                                            waitlist entries.
                                        </FrameDescription>
                                    </div>
                                </div>
                                {canMutateLoad && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            size="xs"
                                            variant="outline"
                                            onClick={handleAutoAssignOpenSeats}
                                            disabled={
                                                waitlistBusyKey ===
                                                "auto-assign"
                                            }
                                        >
                                            {waitlistBusyKey ===
                                            "auto-assign" ? (
                                                <Loader2 className="size-3 animate-spin" />
                                            ) : (
                                                <Sparkles className="size-3" />
                                            )}
                                            Auto-Assign Open Seats
                                        </Button>
                                    </div>
                                )}
                            </FrameHeader>

                            {enrollment.missing_classes.length > 0 && (
                                <FramePanel className="space-y-2.5">
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Unassigned Class Offerings (
                                            {enrollment.missing_classes.length})
                                        </p>
                                    </div>
                                    <div className="divide-y rounded-lg border">
                                        {enrollment.missing_classes.map(
                                            (item, idx) => {
                                                const isEnrolling =
                                                    item.class_id !== null &&
                                                    enrollingClassId ===
                                                        item.class_id;
                                                const joinKey =
                                                    item.subject_enrollment_id
                                                        ? `join-${item.subject_enrollment_id}`
                                                        : null;
                                                const isJoining =
                                                    joinKey !== null &&
                                                    waitlistBusyKey === joinKey;

                                                return (
                                                    <div
                                                        key={`${item.subject_code}-${item.class_id ?? idx}`}
                                                        className="flex flex-col gap-2.5 p-3 sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <div className="min-w-0 space-y-0.5">
                                                            <div className="flex flex-wrap items-center gap-1.5">
                                                                <span className="font-mono text-xs font-semibold">
                                                                    {
                                                                        item.subject_code
                                                                    }
                                                                </span>
                                                                <span className="truncate text-xs text-foreground">
                                                                    {
                                                                        item.subject_title
                                                                    }
                                                                </span>
                                                                {item.section && (
                                                                    <Badge
                                                                        size="xs"
                                                                        variant="outline"
                                                                    >
                                                                        Sec{" "}
                                                                        {
                                                                            item.section
                                                                        }
                                                                    </Badge>
                                                                )}
                                                                {item.class_id ===
                                                                null ? (
                                                                    <Badge
                                                                        size="xs"
                                                                        variant="secondary"
                                                                    >
                                                                        No
                                                                        Offering
                                                                    </Badge>
                                                                ) : item.is_full ? (
                                                                    <Badge
                                                                        size="xs"
                                                                        variant="destructive-light"
                                                                        className="tabular-nums"
                                                                    >
                                                                        FULL (
                                                                        {item.enrolled_count ??
                                                                            0}
                                                                        /
                                                                        {item.max_slots ??
                                                                            0}
                                                                        )
                                                                    </Badge>
                                                                ) : item.max_slots ? (
                                                                    <Badge
                                                                        size="xs"
                                                                        variant="success-light"
                                                                        className="tabular-nums"
                                                                    >
                                                                        {item.enrolled_count ??
                                                                            0}
                                                                        /
                                                                        {
                                                                            item.max_slots
                                                                        }{" "}
                                                                        seats
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                            {(item.faculty ||
                                                                item.schedule) && (
                                                                <p className="text-[11px] text-muted-foreground">
                                                                    {[
                                                                        item.faculty,
                                                                        item.schedule,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            " · ",
                                                                        )}
                                                                </p>
                                                            )}
                                                        </div>

                                                        {canMutateLoad && (
                                                            <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                                                                {item.class_id !==
                                                                    null && (
                                                                    <Button
                                                                        type="button"
                                                                        size="xs"
                                                                        variant={
                                                                            item.is_full
                                                                                ? "destructive"
                                                                                : "default"
                                                                        }
                                                                        disabled={
                                                                            isEnrolling
                                                                        }
                                                                        onClick={() =>
                                                                            onEnrollClass(
                                                                                item.class_id!,
                                                                                Boolean(
                                                                                    item.is_full,
                                                                                ),
                                                                            )
                                                                        }
                                                                    >
                                                                        {isEnrolling ? (
                                                                            <Loader2 className="size-3 animate-spin" />
                                                                        ) : (
                                                                            <UserPlus className="size-3" />
                                                                        )}
                                                                        {item.is_full
                                                                            ? "Force Enroll"
                                                                            : "Enroll Class"}
                                                                    </Button>
                                                                )}
                                                                {item.subject_enrollment_id !==
                                                                    undefined && (
                                                                    <Button
                                                                        type="button"
                                                                        size="xs"
                                                                        variant="outline"
                                                                        disabled={
                                                                            isJoining
                                                                        }
                                                                        onClick={() =>
                                                                            handleJoinWaitlist(
                                                                                item.subject_enrollment_id!,
                                                                                item.class_id,
                                                                            )
                                                                        }
                                                                    >
                                                                        {isJoining ? (
                                                                            <Loader2 className="size-3 animate-spin" />
                                                                        ) : (
                                                                            <Plus className="size-3" />
                                                                        )}
                                                                        Join
                                                                        Waitlist
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                </FramePanel>
                            )}

                            {enrollment.waitlists.length > 0 && (
                                <FramePanel className="space-y-2.5">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Active Waitlist Entries (
                                        {enrollment.waitlists.length})
                                    </p>
                                    <div className="divide-y rounded-lg border">
                                        {enrollment.waitlists.map((wl) => {
                                            const isPromoting =
                                                waitlistBusyKey ===
                                                `promote-${wl.id}`;
                                            const isRemoving =
                                                waitlistBusyKey ===
                                                `remove-${wl.id}`;
                                            return (
                                                <div
                                                    key={wl.id}
                                                    className="flex flex-col gap-2.5 p-3 sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div className="min-w-0 space-y-0.5">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <Badge
                                                                size="xs"
                                                                variant="warning-light"
                                                                className="tabular-nums"
                                                            >
                                                                #
                                                                {wl.position ??
                                                                    "—"}
                                                            </Badge>
                                                            <span className="font-mono text-xs font-semibold">
                                                                {wl.subject_code ??
                                                                    "Subject"}
                                                            </span>
                                                            <span className="truncate text-xs text-foreground">
                                                                {wl.subject_title ??
                                                                    ""}
                                                            </span>
                                                            {wl.preferred_section && (
                                                                <Badge
                                                                    size="xs"
                                                                    variant="outline"
                                                                >
                                                                    Prefers Sec{" "}
                                                                    {
                                                                        wl.preferred_section
                                                                    }
                                                                </Badge>
                                                            )}
                                                            <Badge
                                                                size="xs"
                                                                variant="secondary"
                                                                className="capitalize"
                                                            >
                                                                {wl.status}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-[11px] text-muted-foreground">
                                                            Queued{" "}
                                                            {formatShortDate(
                                                                wl.created_at,
                                                            )}
                                                            {wl.notes
                                                                ? ` · ${wl.notes}`
                                                                : ""}
                                                        </p>
                                                    </div>

                                                    {canMutateLoad && (
                                                        <div className="flex shrink-0 items-center gap-1.5">
                                                            <Button
                                                                type="button"
                                                                size="xs"
                                                                variant="default"
                                                                disabled={
                                                                    isPromoting ||
                                                                    isRemoving
                                                                }
                                                                onClick={() =>
                                                                    handlePromoteWaitlist(
                                                                        wl.id,
                                                                        wl.preferred_class_id,
                                                                    )
                                                                }
                                                            >
                                                                {isPromoting ? (
                                                                    <Loader2 className="size-3 animate-spin" />
                                                                ) : (
                                                                    <CheckCircle2 className="size-3" />
                                                                )}
                                                                Promote
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="xs"
                                                                variant="destructive"
                                                                disabled={
                                                                    isPromoting ||
                                                                    isRemoving
                                                                }
                                                                onClick={() =>
                                                                    handleRemoveWaitlist(
                                                                        wl.id,
                                                                    )
                                                                }
                                                            >
                                                                {isRemoving ? (
                                                                    <Loader2 className="size-3 animate-spin" />
                                                                ) : (
                                                                    <Trash2 className="size-3" />
                                                                )}
                                                                Remove
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </FramePanel>
                            )}
                        </Frame>
                    )}
                </>
            )}
        </div>
    );
}
