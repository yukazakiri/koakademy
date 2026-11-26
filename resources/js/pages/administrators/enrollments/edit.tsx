import {
    index as enrollmentIndex,
    show as showEnrollment,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge, type BadgeProps } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { Head, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    ArrowLeftRight,
    ArrowRightLeft,
    Award,
    BookOpen,
    CalendarDays,
    ChevronUp,
    Clock,
    GraduationCap,
    History,
    Lock,
    Plus,
    Receipt,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    Wrench,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { EnrollmentLoadChangeDialog } from "./enrollment-load-change-dialog";
import { EnrollmentTransferDialog } from "./enrollment-transfer-dialog";
import { getInitials } from "./enrollment-utils";
import { AssessmentDiffRail } from "./workbench/assessment-diff-rail";
import { AuditHistoryPanel } from "./workbench/audit-history-panel";
import { GuardBanners } from "./workbench/guard-banners";
import { ScheduleMatrixPanel } from "./workbench/schedule-matrix-panel";
import { SubjectLoadRow } from "./workbench/subject-load-row";
import { SubjectPickerPanel } from "./workbench/subject-picker-panel";
import { normalizeSemesterOptions, type EnrollmentWorkbenchPageProps } from "./workbench/types";
import { useEnrollmentDraft } from "./workbench/use-enrollment-draft";
import { WorkbenchDialogs } from "./workbench/workbench-dialogs";

function getWorkflowStatusBadge(
    status: string | null | undefined,
    terminalOutcome: string | null | undefined,
): { label: string; variant: BadgeProps["variant"] } {
    if (terminalOutcome === "cancelled" || /cancel|reject/i.test(status ?? "")) {
        return {
            label:
                terminalOutcome === "cancelled"
                    ? "Cancelled"
                    : status || "Cancelled",
            variant: "destructive-light",
        };
    }

    const normalized = (status ?? "").trim();
    if (!normalized) {
        return { label: "Draft", variant: "secondary" };
    }

    if (
        /enrolled|completed|verified/i.test(normalized) &&
        !/pending/i.test(normalized)
    ) {
        return { label: normalized, variant: "success-light" };
    }

    if (/pending/i.test(normalized)) {
        return { label: normalized, variant: "warning-light" };
    }

    if (/dept|head|academic|cashier|payment/i.test(normalized)) {
        return { label: normalized, variant: "info-light" };
    }

    return { label: normalized, variant: "secondary" };
}

export default function AdministratorEnrollmentEdit(
    props: EnrollmentWorkbenchPageProps,
) {
    const draft = useEnrollmentDraft(props);
    const enrollment = props.enrollment;
    const student = draft.selectedStudent ?? enrollment?.student ?? null;

    const [pickerOpen, setPickerOpen] = useState(false);
    const [highlightedSubjectId, setHighlightedSubjectId] = useState<
        number | null
    >(null);
    const [activeDockTab, setActiveDockTab] = useState<string>("schedule");
    const [unsavedDialogOpen, setUnsavedDialogOpen] = useState(false);
    const [loadChangeDialogOpen, setLoadChangeDialogOpen] = useState(false);
    const [transferDialogOpen, setTransferDialogOpen] = useState(false);

    const isRevisionLocked = Boolean(enrollment?.tuition?.active_revision_id);
    const feesDisabled =
        !draft.canManageTuitionFees ||
        isRevisionLocked ||
        draft.isTerminalLocked;

    const backTargetUrl = enrollment
        ? showEnrollment.url(enrollment.id)
        : enrollmentIndex.url();

    const handleBackClick = useCallback(() => {
        if (draft.diff.isDirty) {
            setUnsavedDialogOpen(true);
            return;
        }
        router.visit(backTargetUrl);
    }, [draft.diff.isDirty, backTargetUrl]);

    const handleConfirmLeave = useCallback(() => {
        setUnsavedDialogOpen(false);
        router.visit(backTargetUrl);
    }, [backTargetUrl]);

    // Open the Subject Picker with "/" when not focused in an editable control
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                event.key !== "/" ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey
            ) {
                return;
            }
            const target = event.target as HTMLElement | null;
            const tagName = target?.tagName?.toLowerCase();
            if (
                tagName === "input" ||
                tagName === "textarea" ||
                tagName === "select" ||
                target?.isContentEditable
            ) {
                return;
            }
            if (draft.isTerminalLocked || pickerOpen) return;

            event.preventDefault();
            setPickerOpen(true);
            requestAnimationFrame(() => {
                const input = document.querySelector<HTMLInputElement>(
                    'input[aria-label="Search curriculum subjects"]',
                );
                input?.focus();
            });
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [draft.isTerminalLocked, pickerOpen]);

    const semesterOptions = useMemo(
        () => normalizeSemesterOptions(props.settings.availableSemesters),
        [props.settings.availableSemesters],
    );

    const semesterLabel = useMemo(() => {
        const matched = semesterOptions.find(
            (item) => item.value === draft.semester,
        );
        return matched?.label ?? `Sem ${draft.semester}`;
    }, [semesterOptions, draft.semester]);

    const academicYearOptions = useMemo(() => {
        const entries = Object.entries(
            props.settings.availableAcademicYears ?? {},
        );
        if (entries.length > 0) {
            return entries
                .map(([value, label]) => ({ value: String(value), label }))
                .sort((a, b) => Number(a.value) - Number(b.value));
        }
        return ["1", "2", "3", "4", "5"].map((level) => ({
            value: level,
            label: draft.isTesdaPathway ? `Level ${level}` : `Year ${level}`,
        }));
    }, [props.settings.availableAcademicYears, draft.isTesdaPathway]);

    const enrolledSubjectIds = useMemo(
        () => new Set(draft.subjectsEnrolled.map((sub) => sub.subject_id)),
        [draft.subjectsEnrolled],
    );

    const addedSubjectIdSet = useMemo(
        () => new Set(draft.diff.addedSubjectIds),
        [draft.diff.addedSubjectIds],
    );

    const conflictDetailsBySubjectId = useMemo(() => {
        const map = new Map<number, string[]>();
        for (const conflict of draft.scheduleConflicts) {
            const listA = map.get(conflict.subjectAId) ?? [];
            listA.push(`${conflict.subjectBCode} (${conflict.overlapWindow})`);
            map.set(conflict.subjectAId, listA);

            const listB = map.get(conflict.subjectBId) ?? [];
            listB.push(`${conflict.subjectACode} (${conflict.overlapWindow})`);
            map.set(conflict.subjectBId, listB);
        }
        return map;
    }, [draft.scheduleConflicts]);

    const statusBadge = getWorkflowStatusBadge(
        enrollment?.status,
        enrollment?.terminal_outcome,
    );

    const hasScholarship = Boolean(
        student?.scholarship_type && student.scholarship_type !== "none",
    );
    const scholarshipLabel =
        student?.scholarship_label || student?.scholarship_type || "Scholar";

    const activeHoldsCount = draft.studentHolds.length;
    const editHistory = props.edit_history ?? [];

    const deltaSign =
        draft.diff.tuitionDelta > 0
            ? "+"
            : draft.diff.tuitionDelta < 0
              ? "-"
              : "";
    const liveDiffSummary = `+${draft.diff.addedSubjectIds.length} / -${draft.diff.removedSubjects.length} · Δ ${deltaSign}${draft.formatCurrency(Math.abs(draft.diff.tuitionDelta))}`;

    return (
        <AdminLayout user={props.user} title="Edit Enrollment">
            <Head title="Edit Enrollment" />

            <div className="min-w-0 space-y-4">
                {/* Compact Page Header */}
                <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <h1 className="text-lg font-semibold tracking-tight text-foreground sm:text-xl">
                            Edit Enrollment
                        </h1>

                        {enrollment && (
                            <Badge
                                variant="outline"
                                size="sm"
                                className="font-mono tabular-nums"
                            >
                                #{enrollment.id}
                            </Badge>
                        )}

                        <Badge variant={statusBadge.variant} size="sm">
                            {statusBadge.label}
                        </Badge>

                        <Badge
                            variant="secondary"
                            size="sm"
                            className="tabular-nums"
                        >
                            {draft.schoolYear} · {semesterLabel}
                        </Badge>

                        {draft.diff.isDirty && (
                            <Badge
                                variant="warning-light"
                                size="sm"
                                className="font-mono tabular-nums"
                            >
                                {liveDiffSummary}
                            </Badge>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {enrollment && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setLoadChangeDialogOpen(true)
                                    }
                                    className="gap-1.5"
                                >
                                    <ArrowLeftRight className="size-3.5" />
                                    <span>Formal Add/Drop (COL)</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setTransferDialogOpen(true)}
                                    className="gap-1.5"
                                >
                                    <ArrowRightLeft className="size-3.5" />
                                    <span>Shift / Transfer</span>
                                </Button>
                            </>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleBackClick}
                            className="gap-1.5"
                        >
                            <ArrowLeft className="size-3.5" />
                            <span>Back to Dossier</span>
                        </Button>
                    </div>
                </div>

                {/* Contextual Guards & Override Controls */}
                <GuardBanners draft={draft} />

                {/* Split-Pane Workbench Grid */}
                <div className="grid min-w-0 grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                    {/* Left Column */}
                    <div className="min-w-0 space-y-4">
                        {/* Identity & Term Strip */}
                        <Frame spacing="sm" className="w-full min-w-0">
                            <FramePanel className="min-w-0">
                                <div className="flex min-w-0 flex-col gap-3.5 lg:flex-row lg:items-center lg:justify-between">
                                    {/* Student Identity */}
                                    <div className="flex min-w-0 items-start gap-3">
                                        <Avatar className="size-10 shrink-0 border border-border">
                                            <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                                {getInitials(student?.full_name)}
                                            </AvatarFallback>
                                        </Avatar>

                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                                <span className="truncate text-sm font-semibold text-foreground">
                                                    {student?.full_name ??
                                                        "Unknown Student"}
                                                </span>

                                                {student?.student_id && (
                                                    <Badge
                                                        variant="outline"
                                                        size="xs"
                                                        className="font-mono tabular-nums"
                                                    >
                                                        {student.student_id}
                                                    </Badge>
                                                )}

                                                {draft.isTesdaPathway ? (
                                                    <Badge
                                                        variant="info-light"
                                                        size="xs"
                                                        className="gap-1"
                                                    >
                                                        <Wrench className="size-2.5" />
                                                        TESDA
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="primary-light"
                                                        size="xs"
                                                        className="gap-1"
                                                    >
                                                        <GraduationCap className="size-2.5" />
                                                        College
                                                    </Badge>
                                                )}

                                                {activeHoldsCount === 0 ? (
                                                    <Badge
                                                        variant="success-light"
                                                        size="xs"
                                                        className="gap-1"
                                                    >
                                                        <ShieldCheck className="size-2.5" />
                                                        No active holds
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="destructive-light"
                                                        size="xs"
                                                        className="gap-1 tabular-nums"
                                                    >
                                                        <ShieldAlert className="size-2.5" />
                                                        {activeHoldsCount} active{" "}
                                                        {activeHoldsCount === 1
                                                            ? "hold"
                                                            : "holds"}
                                                    </Badge>
                                                )}
                                            </div>

                                            {(student?.course_code ||
                                                student?.course_name) && (
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {student.course_code && (
                                                        <span className="font-mono font-medium text-foreground/85">
                                                            {student.course_code}
                                                        </span>
                                                    )}
                                                    {student.course_code &&
                                                        student.course_name &&
                                                        " · "}
                                                    {student.course_name && (
                                                        <span>
                                                            {student.course_name}
                                                        </span>
                                                    )}
                                                </p>
                                            )}

                                            {/* Pathway & Scholarship Pills */}
                                            {(draft.isTesdaPathway ||
                                                hasScholarship) && (
                                                <div className="flex flex-wrap items-center gap-1.5 pt-0.5">
                                                    {draft.isTesdaPathway &&
                                                        student?.qualification_level && (
                                                            <Badge
                                                                variant="outline"
                                                                size="xs"
                                                            >
                                                                {
                                                                    student.qualification_level
                                                                }
                                                            </Badge>
                                                        )}

                                                    {draft.isTesdaPathway &&
                                                        Boolean(
                                                            student?.duration_hours,
                                                        ) && (
                                                            <Badge
                                                                variant="secondary"
                                                                size="xs"
                                                                className="gap-1 tabular-nums"
                                                            >
                                                                <Clock className="size-2.5" />
                                                                {
                                                                    student?.duration_hours
                                                                }{" "}
                                                                hrs
                                                            </Badge>
                                                        )}

                                                    {draft.isTesdaPathway &&
                                                        Boolean(
                                                            student?.internship_hours,
                                                        ) && (
                                                            <Badge
                                                                variant="secondary"
                                                                size="xs"
                                                                className="tabular-nums"
                                                            >
                                                                OJT:{" "}
                                                                {
                                                                    student?.internship_hours
                                                                }{" "}
                                                                hrs
                                                            </Badge>
                                                        )}

                                                    {hasScholarship && (
                                                        <Badge
                                                            variant="info-light"
                                                            size="xs"
                                                            className="gap-1"
                                                        >
                                                            <Award className="size-2.5" />
                                                            {scholarshipLabel}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Inline Compact Term Controls */}
                                    <div className="grid shrink-0 grid-cols-2 gap-2.5 lg:w-[270px]">
                                        <div className="min-w-0 space-y-1">
                                            <Label
                                                htmlFor="edit-enrollment-semester"
                                                className="text-[11px] text-muted-foreground"
                                            >
                                                Semester
                                            </Label>
                                            <Select
                                                value={draft.semester}
                                                onValueChange={
                                                    draft.setSemester
                                                }
                                                disabled={
                                                    draft.isTerminalLocked
                                                }
                                            >
                                                <SelectTrigger
                                                    id="edit-enrollment-semester"
                                                    className="h-8 w-full text-xs"
                                                >
                                                    <SelectValue placeholder="Semester" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {semesterOptions.map(
                                                        (sem) => (
                                                            <SelectItem
                                                                key={sem.value}
                                                                value={sem.value}
                                                            >
                                                                {sem.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="min-w-0 space-y-1">
                                            <Label
                                                htmlFor="edit-enrollment-academic-year"
                                                className="text-[11px] text-muted-foreground"
                                            >
                                                {draft.isTesdaPathway
                                                    ? "Training Level"
                                                    : "Year Level"}
                                            </Label>
                                            <Select
                                                value={draft.academicYear}
                                                onValueChange={
                                                    draft.setAcademicYear
                                                }
                                                disabled={
                                                    draft.isTerminalLocked
                                                }
                                            >
                                                <SelectTrigger
                                                    id="edit-enrollment-academic-year"
                                                    className="h-8 w-full text-xs"
                                                >
                                                    <SelectValue
                                                        placeholder={
                                                            draft.isTesdaPathway
                                                                ? "Training Level"
                                                                : "Year Level"
                                                        }
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {academicYearOptions.map(
                                                        (opt) => (
                                                            <SelectItem
                                                                key={opt.value}
                                                                value={
                                                                    opt.value
                                                                }
                                                            >
                                                                {opt.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </FramePanel>
                        </Frame>

                        {/* Unified Subject Load Workbench */}
                        <Frame spacing="sm" className="w-full min-w-0">
                            <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                <div className="flex min-w-0 items-center gap-2.5">
                                    <IconTile
                                        variant="soft"
                                        size="xs"
                                        className="shrink-0 text-primary"
                                    >
                                        <BookOpen />
                                    </IconTile>
                                    <div className="min-w-0">
                                        <FrameTitle className="truncate tabular-nums">
                                            Enrolled Load &amp; Sections (
                                            {draft.subjectsEnrolled.length}{" "}
                                            {draft.subjectsEnrolled.length === 1
                                                ? "subject"
                                                : "subjects"}{" "}
                                            · {draft.totals.totalUnits} /{" "}
                                            {draft.unitCap} units)
                                        </FrameTitle>
                                        <FrameDescription className="truncate text-xs">
                                            Assign class sections, toggle
                                            modular delivery or tuition
                                            exclusions, and monitor clashes
                                            live.
                                        </FrameDescription>
                                    </div>
                                </div>

                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                    {draft.scheduleConflicts.length > 0 && (
                                        <Badge
                                            variant="destructive-light"
                                            size="sm"
                                            className="gap-1 tabular-nums"
                                        >
                                            <AlertTriangle className="size-3" />
                                            {draft.scheduleConflicts.length}{" "}
                                            {draft.scheduleConflicts.length ===
                                            1
                                                ? "clash"
                                                : "clashes"}
                                        </Badge>
                                    )}

                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={
                                            pickerOpen ? "secondary" : "default"
                                        }
                                        disabled={draft.isTerminalLocked}
                                        onClick={() =>
                                            setPickerOpen((prev) => !prev)
                                        }
                                        className="gap-1.5"
                                    >
                                        {pickerOpen ? (
                                            <>
                                                <ChevronUp className="size-3.5" />
                                                <span>Hide Catalog</span>
                                            </>
                                        ) : (
                                            <>
                                                <Plus className="size-3.5" />
                                                <span>Add Subject</span>
                                                <kbd className="ml-0.5 hidden rounded border border-current/25 px-1 font-mono text-[10px] opacity-80 sm:inline">
                                                    /
                                                </kbd>
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </FrameHeader>

                            {/* Collapsible Subject Picker */}
                            {pickerOpen && (
                                <div className="min-w-0">
                                    <SubjectPickerPanel
                                        subjectOptions={draft.subjectOptions}
                                        enrolledSubjectIds={enrolledSubjectIds}
                                        loadingSubjects={draft.loadingSubjects}
                                        subjectSearch={draft.subjectSearch}
                                        onSubjectSearchChange={
                                            draft.setSubjectSearch
                                        }
                                        onAddSubject={draft.handleAddSubject}
                                        onLoadRecommendedBlock={
                                            draft.handleLoadRecommendedBlock
                                        }
                                        isTesdaPathway={draft.isTesdaPathway}
                                        targetAcademicYear={
                                            Number(draft.academicYear) || 1
                                        }
                                        targetSemester={
                                            Number(draft.semester) || 1
                                        }
                                        disabled={draft.isTerminalLocked}
                                    />
                                </div>
                            )}

                            <FramePanel className="min-w-0 p-0">
                                {draft.subjectsEnrolled.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center gap-2.5 px-6 py-10 text-center">
                                        <IconTile
                                            variant="frame"
                                            size="default"
                                            className="text-muted-foreground"
                                        >
                                            <BookOpen />
                                        </IconTile>
                                        <div className="max-w-sm space-y-1">
                                            <p className="text-sm font-medium text-foreground">
                                                No subjects in this draft
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Open the subject catalog to add
                                                curriculum subjects or load the
                                                recommended term block.
                                            </p>
                                        </div>
                                        {!pickerOpen &&
                                            !draft.isTerminalLocked && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setPickerOpen(true)
                                                    }
                                                    className="mt-1 gap-1.5"
                                                >
                                                    <Plus className="size-3.5" />
                                                    <span>
                                                        Open Subject Catalog
                                                    </span>
                                                </Button>
                                            )}
                                    </div>
                                ) : (
                                    <div className="min-w-0">
                                        {draft.subjectsEnrolled.map(
                                            (subject) => (
                                                <SubjectLoadRow
                                                    key={subject.subject_id}
                                                    subject={subject}
                                                    isAdded={addedSubjectIdSet.has(
                                                        subject.subject_id,
                                                    )}
                                                    hasConflict={draft.conflictingSubjectIds.has(
                                                        subject.subject_id,
                                                    )}
                                                    conflictDetails={conflictDetailsBySubjectId.get(
                                                        subject.subject_id,
                                                    )}
                                                    waitlist={draft.waitlistBySubjectId.get(
                                                        subject.subject_id,
                                                    )}
                                                    canManageTuitionFees={
                                                        draft.canManageTuitionFees
                                                    }
                                                    disabled={
                                                        draft.isTerminalLocked
                                                    }
                                                    isHighlighted={
                                                        highlightedSubjectId ===
                                                        subject.subject_id
                                                    }
                                                    onHoverChange={
                                                        setHighlightedSubjectId
                                                    }
                                                    onSelectSection={
                                                        draft.handleSelectSection
                                                    }
                                                    onToggleModular={
                                                        draft.handleToggleModular
                                                    }
                                                    onToggleExclude={
                                                        draft.handleToggleExclude
                                                    }
                                                    onRemove={
                                                        draft.handleRemoveSubject
                                                    }
                                                    checkSectionConflicts={
                                                        draft.checkSectionConflictsWithDraft
                                                    }
                                                    formatCurrency={
                                                        draft.formatCurrency
                                                    }
                                                />
                                            ),
                                        )}
                                    </div>
                                )}

                                {/* Subtle Removed-in-Draft Summary Bar */}
                                {draft.diff.removedSubjects.length > 0 && (
                                    <div
                                        className={cn(
                                            "flex flex-wrap items-center gap-1.5 border-t border-border/70 px-3.5 py-2 text-xs",
                                            draft.diff.gradedRemovals.length > 0
                                                ? "bg-destructive/[0.05] dark:bg-destructive/[0.08]"
                                                : "bg-muted/30",
                                        )}
                                    >
                                        {draft.diff.gradedRemovals.length >
                                            0 && (
                                            <AlertTriangle className="size-3.5 shrink-0 text-destructive" />
                                        )}
                                        <span className="font-medium text-muted-foreground">
                                            Removed in this draft:
                                        </span>
                                        {draft.diff.removedSubjects.map(
                                            (sub) => (
                                                <Badge
                                                    key={sub.subject_id}
                                                    variant={
                                                        sub.has_grades
                                                            ? "destructive-light"
                                                            : "outline"
                                                    }
                                                    size="xs"
                                                    className="font-mono"
                                                    title={
                                                        sub.has_grades
                                                            ? `Has recorded grades (${sub.grade_summary || "will require confirmation"})`
                                                            : sub.subject_title
                                                    }
                                                >
                                                    -{sub.subject_code}
                                                    {sub.has_grades
                                                        ? " · graded"
                                                        : ""}
                                                </Badge>
                                            ),
                                        )}
                                    </div>
                                )}
                            </FramePanel>
                        </Frame>

                        {/* Secondary Tabbed Dock */}
                        <Frame spacing="sm" className="w-full min-w-0">
                            <Tabs
                                value={activeDockTab}
                                onValueChange={(value) =>
                                    setActiveDockTab(String(value))
                                }
                                className="min-w-0 gap-2"
                            >
                                <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2 pb-1">
                                    <TabsList className="max-w-full flex-wrap">
                                        <TabsTrigger
                                            value="schedule"
                                            className="h-7 gap-1.5 text-xs"
                                        >
                                            <CalendarDays className="size-3.5" />
                                            <span>Schedule Preview</span>
                                            {draft.scheduleConflicts.length >
                                                0 && (
                                                <Badge
                                                    variant="destructive-light"
                                                    size="xs"
                                                    className="tabular-nums"
                                                >
                                                    {
                                                        draft.scheduleConflicts
                                                            .length
                                                    }
                                                </Badge>
                                            )}
                                        </TabsTrigger>

                                        <TabsTrigger
                                            value="fees"
                                            className="h-7 gap-1.5 text-xs"
                                        >
                                            <Receipt className="size-3.5" />
                                            <span>
                                                Additional Fees (
                                                {draft.additionalFees.length})
                                            </span>
                                        </TabsTrigger>

                                        <TabsTrigger
                                            value="audit"
                                            className="h-7 gap-1.5 text-xs"
                                        >
                                            <History className="size-3.5" />
                                            <span>
                                                Audit History (
                                                {editHistory.length})
                                            </span>
                                        </TabsTrigger>
                                    </TabsList>
                                </FrameHeader>

                                <TabsContent
                                    value="schedule"
                                    className="min-w-0"
                                >
                                    <ScheduleMatrixPanel
                                        subjectsEnrolled={
                                            draft.subjectsEnrolled
                                        }
                                        scheduleConflicts={
                                            draft.scheduleConflicts
                                        }
                                        schedulingPolicy={
                                            draft.schedulingPolicy
                                        }
                                        highlightedSubjectId={
                                            highlightedSubjectId
                                        }
                                        onHoverSubject={setHighlightedSubjectId}
                                    />
                                </TabsContent>

                                <TabsContent value="fees" className="min-w-0">
                                    <FramePanel className="min-w-0 space-y-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="min-w-0">
                                                <h3 className="text-sm font-semibold text-foreground">
                                                    Additional Fees
                                                </h3>
                                                <p className="text-xs text-muted-foreground">
                                                    {isRevisionLocked
                                                        ? "Additional fees are frozen while a Finance revision is active."
                                                        : !draft.canManageTuitionFees
                                                          ? "Only authorized Finance/Registrar roles can modify additional fees."
                                                          : "Receipted separate-transaction fees are locked to preserve payment audit trails."}
                                                </p>
                                            </div>

                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={feesDisabled}
                                                onClick={draft.handleAddFee}
                                                className="shrink-0 gap-1.5"
                                            >
                                                <Plus className="size-3.5" />
                                                <span>Add Fee</span>
                                            </Button>
                                        </div>

                                        {draft.additionalFees.length === 0 ? (
                                            <div className="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-6 text-center text-xs text-muted-foreground">
                                                No additional fees attached to
                                                this enrollment.
                                            </div>
                                        ) : (
                                            <div className="divide-y divide-border/70 rounded-lg border border-border">
                                                {draft.additionalFees.map(
                                                    (fee, index) => {
                                                        const isProtected =
                                                            Boolean(
                                                                fee.is_separate_transaction ||
                                                                    fee.transaction_number,
                                                            );
                                                        const rowDisabled =
                                                            feesDisabled ||
                                                            isProtected;

                                                        return (
                                                            <div
                                                                key={
                                                                    fee.id ??
                                                                    `new-fee-${index}`
                                                                }
                                                                className={cn(
                                                                    "grid grid-cols-1 items-center gap-2.5 p-2.5 sm:grid-cols-[minmax(0,1fr)_140px_auto]",
                                                                    isProtected &&
                                                                        "bg-muted/20",
                                                                )}
                                                            >
                                                                <div className="min-w-0 space-y-1">
                                                                    <Input
                                                                        value={
                                                                            fee.fee_name
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            draft.handleUpdateFee(
                                                                                index,
                                                                                "fee_name",
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="Fee description (e.g., Lab Manual, ID Replacement)"
                                                                        disabled={
                                                                            rowDisabled
                                                                        }
                                                                        readOnly={
                                                                            isProtected
                                                                        }
                                                                        className="h-8 text-xs"
                                                                        aria-label={`Additional fee name ${index + 1}`}
                                                                    />
                                                                    {isProtected && (
                                                                        <Badge
                                                                            variant="success-light"
                                                                            size="xs"
                                                                            className="gap-1 font-mono"
                                                                        >
                                                                            <Lock className="size-2.5" />
                                                                            {fee.transaction_number
                                                                                ? `Receipt #${fee.transaction_number} · Protected`
                                                                                : "Separate Tx · Protected"}
                                                                        </Badge>
                                                                    )}
                                                                </div>

                                                                <Input
                                                                    type="number"
                                                                    min={0}
                                                                    step="0.01"
                                                                    value={
                                                                        fee.amount
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        draft.handleUpdateFee(
                                                                            index,
                                                                            "amount",
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="0.00"
                                                                    disabled={
                                                                        rowDisabled
                                                                    }
                                                                    readOnly={
                                                                        isProtected
                                                                    }
                                                                    className="h-8 text-right text-xs tabular-nums"
                                                                    aria-label={`Additional fee amount ${index + 1}`}
                                                                />

                                                                <div className="flex items-center justify-end">
                                                                    {isProtected ? (
                                                                        <span
                                                                            className="inline-flex size-7 items-center justify-center text-muted-foreground"
                                                                            title="Receipted fee cannot be removed"
                                                                        >
                                                                            <Lock className="size-3.5" />
                                                                        </span>
                                                                    ) : (
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon-xs"
                                                                            disabled={
                                                                                feesDisabled
                                                                            }
                                                                            onClick={() =>
                                                                                draft.handleRemoveFee(
                                                                                    index,
                                                                                )
                                                                            }
                                                                            aria-label={`Remove fee ${fee.fee_name || index + 1}`}
                                                                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                        >
                                                                            <Trash2 className="size-3.5" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        )}
                                    </FramePanel>
                                </TabsContent>

                                <TabsContent value="audit" className="min-w-0">
                                    <AuditHistoryPanel
                                        history={editHistory}
                                        formatCurrency={draft.formatCurrency}
                                    />
                                </TabsContent>
                            </Tabs>
                        </Frame>
                    </div>

                    {/* Right Column — Sticky Live Assessment & Diff Rail */}
                    <div className="min-w-0">
                        <AssessmentDiffRail
                            draft={draft}
                            submitLabel="Save Changes"
                        />
                    </div>
                </div>
            </div>

            {/* Confirmation & Companion Workflow Dialogs */}
            <WorkbenchDialogs
                draft={draft}
                unsavedDialogOpen={unsavedDialogOpen}
                onUnsavedDialogOpenChange={setUnsavedDialogOpen}
                onConfirmLeave={handleConfirmLeave}
            />

            <EnrollmentLoadChangeDialog
                open={loadChangeDialogOpen}
                onOpenChange={setLoadChangeDialogOpen}
                enrollmentId={enrollment?.id ?? null}
                courseId={student?.course_id ?? null}
                studentName={student?.full_name ?? null}
                onChanged={() => router.reload()}
            />

            <EnrollmentTransferDialog
                open={transferDialogOpen}
                onOpenChange={setTransferDialogOpen}
                enrollmentId={enrollment?.id ?? null}
                studentName={student?.full_name ?? null}
                onTransferred={() => router.reload()}
            />
        </AdminLayout>
    );
}
