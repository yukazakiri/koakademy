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
import {
    Stepper,
    StepperContent,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperNav,
    StepperPanel,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from "@/components/reui/stepper";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    Award,
    BookOpen,
    CalendarClock,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    GraduationCap,
    Layers,
    Loader2,
    Plus,
    Receipt,
    Search,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserCheck,
    Users,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import type { EnrollmentPeriodSummary } from "./types";
import { AssessmentDiffRail } from "./workbench/assessment-diff-rail";
import { GuardBanners } from "./workbench/guard-banners";
import { ScheduleMatrixPanel } from "./workbench/schedule-matrix-panel";
import { SubjectLoadRow } from "./workbench/subject-load-row";
import { SubjectPickerPanel } from "./workbench/subject-picker-panel";
import { normalizeSemesterOptions, type EnrollmentWorkbenchPageProps } from "./workbench/types";
import { useEnrollmentDraft } from "./workbench/use-enrollment-draft";
import { WorkbenchDialogs } from "./workbench/workbench-dialogs";

type StepNumber = 1 | 2 | 3 | 4;

function getPeriodBadgeVariant(
    tone?: EnrollmentPeriodSummary["tone"],
): BadgeProps["variant"] {
    switch (tone) {
        case "success":
            return "success-light";
        case "warning":
            return "warning-light";
        case "destructive":
            return "destructive-light";
        case "info":
            return "info-light";
        default:
            return "secondary";
    }
}

function getInitials(fullName: string): string {
    const parts = fullName.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return "?";
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

export default function AdministratorEnrollmentCreate(
    props: EnrollmentWorkbenchPageProps,
) {
    const draft = useEnrollmentDraft(props);
    const [activeStep, setActiveStep] = useState<StepNumber>(1);
    const [highlightedSubjectId, setHighlightedSubjectId] = useState<
        number | null
    >(null);
    const [unsavedDialogOpen, setUnsavedDialogOpen] = useState(false);

    // Support direct ?student_id=<id> deep links from Applicants / Students pages
    const preselectedStudentRef = useRef(false);
    const {
        isEdit,
        selectedStudent,
        handleSelectStudent,
    } = draft;
    useEffect(() => {
        if (preselectedStudentRef.current || isEdit || selectedStudent) {
            return;
        }
        const rawId = new URLSearchParams(window.location.search).get(
            "student_id",
        );
        if (!rawId) return;
        const parsedId = Number.parseInt(rawId, 10);
        if (!Number.isFinite(parsedId) || parsedId <= 0) return;

        preselectedStudentRef.current = true;
        void Promise.resolve().then(() =>
            handleSelectStudent({
                id: parsedId,
                student_id: "",
                full_name: "",
                email: "",
                course_id: null,
                course_code: null,
                academic_year: null,
                formatted_academic_year: "",
                label: "",
            }),
        );
    }, [isEdit, selectedStudent, handleSelectStudent]);

    const hasDuplicateEnrollment = Boolean(
        draft.selectedStudent?.existing_enrollment,
    );
    const canProceedStep1 = Boolean(
        draft.selectedStudent && !hasDuplicateEnrollment,
    );
    const canProceedStep2 = canProceedStep1 && draft.subjectsEnrolled.length > 0;

    // Derive a safe active step if a student is cleared or all subjects are removed
    const currentStep: StepNumber =
        activeStep > 1 && !canProceedStep1
            ? 1
            : activeStep > 2 && !canProceedStep2
              ? 2
              : activeStep;

    const handleStepChange = (step: number) => {
        if (step < 1 || step > 4) return;
        if (step >= 2 && !canProceedStep1) return;
        if (step >= 3 && !canProceedStep2) return;
        setActiveStep(step as StepNumber);
    };

    const enrolledSubjectIds = useMemo(
        () => new Set(draft.subjectsEnrolled.map((s) => s.subject_id)),
        [draft.subjectsEnrolled],
    );

    const semesterOptions = useMemo(
        () => normalizeSemesterOptions(props.settings.availableSemesters),
        [props.settings.availableSemesters],
    );

    const semesterLabel = useMemo(() => {
        const match = semesterOptions.find(
            (item) => item.value === draft.semester,
        );
        return match?.label ?? `Semester ${draft.semester}`;
    }, [semesterOptions, draft.semester]);

    const academicYearOptions = useMemo<[string, string][]>(() => {
        if (draft.isTesdaPathway) {
            return [
                ["1", "Training Level 1"],
                ["2", "Training Level 2"],
                ["3", "Training Level 3"],
                ["4", "Training Level 4"],
                ["5", "Training Level 5"],
            ];
        }
        return Object.entries(props.settings.availableAcademicYears);
    }, [draft.isTesdaPathway, props.settings.availableAcademicYears]);

    const academicYearLabel = useMemo(() => {
        const match = academicYearOptions.find(
            ([value]) => value === draft.academicYear,
        );
        return (
            match?.[1] ??
            (draft.isTesdaPathway
                ? `Training Level ${draft.academicYear}`
                : `Year ${draft.academicYear}`)
        );
    }, [academicYearOptions, draft.academicYear, draft.isTesdaPathway]);

    const conflictDetailsBySubjectId = useMemo(() => {
        const map = new Map<number, string[]>();
        for (const conflict of draft.scheduleConflicts) {
            const listA = map.get(conflict.subjectAId) ?? [];
            listA.push(
                `Clashes with ${conflict.subjectBCode} (${conflict.overlapWindow})`,
            );
            map.set(conflict.subjectAId, listA);

            const listB = map.get(conflict.subjectBId) ?? [];
            listB.push(
                `Clashes with ${conflict.subjectACode} (${conflict.overlapWindow})`,
            );
            map.set(conflict.subjectBId, listB);
        }
        return map;
    }, [draft.scheduleConflicts]);

    const assignedSectionCount = useMemo(
        () =>
            draft.subjectsEnrolled.filter((s) => s.class_id !== null).length,
        [draft.subjectsEnrolled],
    );

    const fullSelectedSectionsCount = useMemo(() => {
        return draft.subjectsEnrolled.filter((sub) => {
            if (!sub.class_id || !sub.sections) return false;
            const sec = sub.sections.find((s) => s.id === sub.class_id);
            return Boolean(sec?.is_full);
        }).length;
    }, [draft.subjectsEnrolled]);

    const windowBlockerActive =
        draft.windowRequiresOverride && !draft.overrideWindow;
    const holdsBlockerActive =
        draft.holdsRequireOverride &&
        (!draft.overrideHolds || draft.overrideHoldsReason.trim().length < 5);

    const isSubmitDisabled =
        draft.submitting ||
        draft.isTerminalLocked ||
        !canProceedStep2;

    const isNextDisabled =
        (currentStep === 1 && !canProceedStep1) ||
        (currentStep === 2 && !canProceedStep2) ||
        (currentStep === 3 && !canProceedStep2);

    const handleBackClick = () => {
        if (draft.diff.isDirty) {
            setUnsavedDialogOpen(true);
            return;
        }
        router.visit(enrollmentIndex.url());
    };

    const handleConfirmLeave = () => {
        setUnsavedDialogOpen(false);
        router.visit(enrollmentIndex.url());
    };

    const period = draft.enrollmentPeriod;
    const student = draft.selectedStudent;
    const checklist = student?.checklist_summary ?? props.checklist_summary;
    const hasScholarship = Boolean(
        student?.scholarship_label ||
            (student?.scholarship_type && student.scholarship_type !== "none"),
    );

    return (
        <AdminLayout user={props.user} title="Create Enrollment">
            <Head title="Create Enrollment" />

            <div className="min-w-0 space-y-4">
                {/* Compact Page Header */}
                <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <h1 className="truncate text-lg font-semibold tracking-tight text-foreground sm:text-xl">
                            New Enrollment
                        </h1>

                        <Badge variant="outline" size="default" className="tabular-nums">
                            {draft.schoolYear} · {semesterLabel}
                        </Badge>

                        {period?.phase_label && (
                            <Badge
                                variant={getPeriodBadgeVariant(period.tone)}
                                size="default"
                                title={period.summary_text}
                            >
                                <CalendarClock className="size-3" />
                                {period.phase_label}
                            </Badge>
                        )}

                        {draft.isTesdaPathway && (
                            <Badge variant="info-light" size="default">
                                <GraduationCap className="size-3" />
                                TESDA Pathway
                            </Badge>
                        )}
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={handleBackClick}
                        className="shrink-0 gap-1.5"
                    >
                        <ArrowLeft className="size-3.5" />
                        <span>Back to Enrollments</span>
                    </Button>
                </div>

                {/* Guard & Override Banners */}
                <GuardBanners draft={draft} />

                {/* Responsive Two-Column Workbench Grid (zero overflow at 1280px) */}
                <div className="grid min-w-0 grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_336px]">
                    {/* Left Column: 4-Step Guided Stepper + Persistent Bottom Dock */}
                    <div className="min-w-0 space-y-4">
                        <Stepper
                            value={currentStep}
                            onValueChange={handleStepChange}
                            indicators={{
                                completed: <Check className="size-3.5" />,
                            }}
                            className="min-w-0 space-y-4"
                        >
                            {/* Stepper Navigation Frame Bar */}
                            <Frame spacing="xs" className="min-w-0">
                                <FramePanel className="min-w-0 px-3 py-2.5">
                                    <StepperNav className="grid min-w-0 grid-cols-2 gap-2.5 sm:flex sm:items-center sm:gap-0">
                                        <StepperItem
                                            step={1}
                                            completed={canProceedStep1}
                                            className="min-w-0 justify-start sm:justify-center"
                                        >
                                            <StepperTrigger className="min-w-0 gap-2 rounded-lg px-1 py-0.5 text-left">
                                                <StepperIndicator>1</StepperIndicator>
                                                <div className="min-w-0">
                                                    <StepperTitle className="truncate text-xs font-semibold">
                                                        Student &amp; Term
                                                    </StepperTitle>
                                                    <StepperDescription className="hidden truncate text-[11px] lg:block">
                                                        Select learner
                                                    </StepperDescription>
                                                </div>
                                            </StepperTrigger>
                                            <StepperSeparator className="mx-2 hidden sm:block group-data-[state=completed]/step:bg-primary" />
                                        </StepperItem>

                                        <StepperItem
                                            step={2}
                                            disabled={!canProceedStep1}
                                            completed={
                                                draft.subjectsEnrolled.length > 0
                                            }
                                            className="min-w-0 justify-start sm:justify-center"
                                        >
                                            <StepperTrigger className="min-w-0 gap-2 rounded-lg px-1 py-0.5 text-left">
                                                <StepperIndicator>2</StepperIndicator>
                                                <div className="min-w-0">
                                                    <StepperTitle className="truncate text-xs font-semibold">
                                                        Subjects &amp; Load
                                                    </StepperTitle>
                                                    <StepperDescription className="hidden truncate text-[11px] lg:block">
                                                        Pick courses
                                                    </StepperDescription>
                                                </div>
                                            </StepperTrigger>
                                            <StepperSeparator className="mx-2 hidden sm:block group-data-[state=completed]/step:bg-primary" />
                                        </StepperItem>

                                        <StepperItem
                                            step={3}
                                            disabled={
                                                draft.subjectsEnrolled.length === 0
                                            }
                                            className="min-w-0 justify-start sm:justify-center"
                                        >
                                            <StepperTrigger className="min-w-0 gap-2 rounded-lg px-1 py-0.5 text-left">
                                                <StepperIndicator>3</StepperIndicator>
                                                <div className="min-w-0">
                                                    <StepperTitle className="truncate text-xs font-semibold">
                                                        Sections &amp; Schedule
                                                    </StepperTitle>
                                                    <StepperDescription className="hidden truncate text-[11px] lg:block">
                                                        Assign slots
                                                    </StepperDescription>
                                                </div>
                                            </StepperTrigger>
                                            <StepperSeparator className="mx-2 hidden sm:block group-data-[state=completed]/step:bg-primary" />
                                        </StepperItem>

                                        <StepperItem
                                            step={4}
                                            disabled={
                                                draft.subjectsEnrolled.length === 0
                                            }
                                            className="min-w-0 justify-start sm:justify-center"
                                        >
                                            <StepperTrigger className="min-w-0 gap-2 rounded-lg px-1 py-0.5 text-left">
                                                <StepperIndicator>4</StepperIndicator>
                                                <div className="min-w-0">
                                                    <StepperTitle className="truncate text-xs font-semibold">
                                                        Tuition &amp; Confirm
                                                    </StepperTitle>
                                                    <StepperDescription className="hidden truncate text-[11px] lg:block">
                                                        Fees &amp; submit
                                                    </StepperDescription>
                                                </div>
                                            </StepperTrigger>
                                        </StepperItem>
                                    </StepperNav>
                                </FramePanel>
                            </Frame>

                            {/* Step Panels */}
                            <StepperPanel className="min-w-0">
                                {/* STEP 1: Student & Term */}
                                <StepperContent value={1} className="min-w-0 space-y-4">
                                    <Frame spacing="sm" className="min-w-0">
                                        <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <IconTile
                                                    variant="soft"
                                                    size="xs"
                                                    className="shrink-0 text-primary"
                                                >
                                                    <Users />
                                                </IconTile>
                                                <div className="min-w-0">
                                                    <FrameTitle className="truncate">
                                                        Find Student
                                                    </FrameTitle>
                                                    <FrameDescription className="truncate text-xs">
                                                        Search by name, student ID, or email to begin enrollment
                                                    </FrameDescription>
                                                </div>
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                size="xs"
                                                className="tabular-nums"
                                            >
                                                {draft.studentOptions.length} shown
                                            </Badge>
                                        </FrameHeader>

                                        <FramePanel className="min-w-0 p-0">
                                            {/* Search bar */}
                                            <div className="border-b border-border/70 bg-muted/20 p-3">
                                                <div className="relative min-w-0">
                                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        type="text"
                                                        value={draft.studentSearch}
                                                        onChange={(event) =>
                                                            draft.setStudentSearch(
                                                                event.target.value,
                                                            )
                                                        }
                                                        placeholder="Search student name, ID number, or email…"
                                                        aria-label="Search students"
                                                        className="h-9 bg-background pr-9 pl-8 text-xs"
                                                    />
                                                    <div className="absolute top-1/2 right-2.5 flex -translate-y-1/2 items-center gap-1">
                                                        {draft.loadingStudents && (
                                                            <Loader2 className="size-3.5 animate-spin text-muted-foreground" />
                                                        )}
                                                        {draft.studentSearch && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    draft.setStudentSearch(
                                                                        "",
                                                                    )
                                                                }
                                                                className="inline-flex size-5 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                                aria-label="Clear student search"
                                                            >
                                                                <X className="size-3" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Scrollable Student Results */}
                                            <div className="max-h-64 divide-y divide-border/60 overflow-y-auto">
                                                {draft.loadingStudents &&
                                                draft.studentOptions.length === 0 ? (
                                                    <div className="flex items-center justify-center gap-2 px-4 py-10 text-xs text-muted-foreground">
                                                        <Loader2 className="size-4 animate-spin" />
                                                        <span>Loading students…</span>
                                                    </div>
                                                ) : draft.studentOptions.length === 0 ? (
                                                    <div className="flex flex-col items-center justify-center gap-1 px-4 py-8 text-center">
                                                        <p className="text-xs font-medium text-foreground">
                                                            No matching students found
                                                        </p>
                                                        <p className="text-[11px] text-muted-foreground">
                                                            {draft.studentSearch
                                                                ? `No student records matched "${draft.studentSearch}".`
                                                                : "Type a student name or ID number to search."}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    draft.studentOptions.map(
                                                        (option) => {
                                                            const isSelected =
                                                                student?.id ===
                                                                option.id;
                                                            return (
                                                                <button
                                                                    key={option.id}
                                                                    type="button"
                                                                    onClick={() =>
                                                                        void draft.handleSelectStudent(
                                                                            option,
                                                                        )
                                                                    }
                                                                    className={cn(
                                                                        "flex w-full min-w-0 items-center justify-between gap-3 px-3.5 py-2.5 text-left transition-colors outline-none focus-visible:bg-muted/60",
                                                                        isSelected
                                                                            ? "bg-primary/[0.06] dark:bg-primary/[0.12]"
                                                                            : "hover:bg-muted/40",
                                                                    )}
                                                                >
                                                                    <div className="flex min-w-0 items-center gap-2.5">
                                                                        <span
                                                                            className={cn(
                                                                                "inline-flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold",
                                                                                isSelected
                                                                                    ? "border-primary/40 bg-primary text-primary-foreground"
                                                                                    : "border-border bg-muted/60 text-foreground",
                                                                            )}
                                                                        >
                                                                            {getInitials(
                                                                                option.full_name,
                                                                            )}
                                                                        </span>
                                                                        <div className="min-w-0">
                                                                            <div className="flex min-w-0 items-center gap-1.5">
                                                                                <span className="truncate text-xs font-semibold text-foreground">
                                                                                    {
                                                                                        option.full_name
                                                                                    }
                                                                                </span>
                                                                                {option.curriculum_kind ===
                                                                                    "tesda_qualification" && (
                                                                                    <Badge
                                                                                        variant="info-light"
                                                                                        size="xs"
                                                                                    >
                                                                                        TESDA
                                                                                    </Badge>
                                                                                )}
                                                                            </div>
                                                                            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted-foreground">
                                                                                <span className="font-mono tabular-nums">
                                                                                    {
                                                                                        option.student_id
                                                                                    }
                                                                                </span>
                                                                                {option.course_code && (
                                                                                    <>
                                                                                        <span>·</span>
                                                                                        <span className="font-medium text-foreground/80">
                                                                                            {
                                                                                                option.course_code
                                                                                            }
                                                                                        </span>
                                                                                    </>
                                                                                )}
                                                                                {option.formatted_academic_year && (
                                                                                    <>
                                                                                        <span>·</span>
                                                                                        <span className="truncate">
                                                                                            {
                                                                                                option.formatted_academic_year
                                                                                            }
                                                                                        </span>
                                                                                    </>
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div className="shrink-0">
                                                                        {isSelected ? (
                                                                            <Badge
                                                                                variant="primary-light"
                                                                                size="xs"
                                                                            >
                                                                                <Check className="size-3" />
                                                                                Selected
                                                                            </Badge>
                                                                        ) : (
                                                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                                                Select
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </button>
                                                            );
                                                        },
                                                    )
                                                )}
                                            </div>
                                        </FramePanel>
                                    </Frame>

                                    {/* Selected Student Confirmation & Term Configuration Card */}
                                    <Frame spacing="sm" className="min-w-0">
                                        <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <IconTile
                                                    variant="soft"
                                                    size="xs"
                                                    className="shrink-0 text-primary"
                                                >
                                                    <UserCheck />
                                                </IconTile>
                                                <div className="min-w-0">
                                                    <FrameTitle className="truncate">
                                                        Selected Student &amp; Term
                                                    </FrameTitle>
                                                    <FrameDescription className="truncate text-xs">
                                                        Verify learner profile, program pathway, and target term
                                                    </FrameDescription>
                                                </div>
                                            </div>

                                            {student?.existing_enrollment && (
                                                <Link
                                                    href={showEnrollment.url(
                                                        student.existing_enrollment.id,
                                                    )}
                                                    className={cn(
                                                        buttonVariants({
                                                            variant: "outline",
                                                            size: "xs",
                                                        }),
                                                        "shrink-0",
                                                    )}
                                                >
                                                    Open Enrollment #
                                                    {student.existing_enrollment.id}
                                                </Link>
                                            )}
                                        </FrameHeader>

                                        <FramePanel className="min-w-0 space-y-4">
                                            {!student ? (
                                                <div className="flex flex-col items-center justify-center gap-2 py-6 text-center">
                                                    <IconTile
                                                        variant="frame"
                                                        size="default"
                                                        className="text-muted-foreground"
                                                    >
                                                        <UserCheck />
                                                    </IconTile>
                                                    <div className="space-y-0.5">
                                                        <p className="text-xs font-medium text-foreground">
                                                            No student selected yet
                                                        </p>
                                                        <p className="text-[11px] text-muted-foreground">
                                                            Choose a student from the list above to configure semester, year level, and subjects.
                                                        </p>
                                                    </div>
                                                </div>
                                            ) : (
                                                <>
                                                    {/* Identity & Badges */}
                                                    <div className="flex min-w-0 flex-wrap items-start justify-between gap-3">
                                                        <div className="flex min-w-0 items-start gap-3">
                                                            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-sm font-semibold text-primary">
                                                                {getInitials(
                                                                    student.full_name,
                                                                )}
                                                            </span>
                                                            <div className="min-w-0 space-y-1">
                                                                <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                                    <h3 className="truncate text-sm font-semibold text-foreground">
                                                                        {student.full_name}
                                                                    </h3>
                                                                    {student.student_id && (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            size="xs"
                                                                            className="font-mono tabular-nums"
                                                                        >
                                                                            {
                                                                                student.student_id
                                                                            }
                                                                        </Badge>
                                                                    )}
                                                                    {hasScholarship && (
                                                                        <Badge
                                                                            variant="info-light"
                                                                            size="xs"
                                                                        >
                                                                            <Award className="size-3" />
                                                                            {student.scholarship_label ||
                                                                                student.scholarship_type}
                                                                        </Badge>
                                                                    )}
                                                                </div>

                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    <span className="font-semibold text-foreground/90">
                                                                        {student.course_code ||
                                                                            "No Program"}
                                                                    </span>
                                                                    {student.course_name
                                                                        ? ` · ${student.course_name}`
                                                                        : ""}
                                                                </p>

                                                                {/* TESDA Qualification Pills */}
                                                                {draft.isTesdaPathway && (
                                                                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                                                        {student.qualification_level && (
                                                                            <Badge
                                                                                variant="info-light"
                                                                                size="xs"
                                                                            >
                                                                                {
                                                                                    student.qualification_level
                                                                                }
                                                                            </Badge>
                                                                        )}
                                                                        {student.tesda_program_type && (
                                                                            <Badge
                                                                                variant="outline"
                                                                                size="xs"
                                                                            >
                                                                                {
                                                                                    student.tesda_program_type
                                                                                }
                                                                            </Badge>
                                                                        )}
                                                                        {student.duration_hours ? (
                                                                            <Badge
                                                                                variant="outline"
                                                                                size="xs"
                                                                                className="tabular-nums"
                                                                            >
                                                                                <Clock className="size-2.5" />
                                                                                {
                                                                                    student.duration_hours
                                                                                }{" "}
                                                                                hrs
                                                                            </Badge>
                                                                        ) : null}
                                                                        {student.duration_years ? (
                                                                            <Badge
                                                                                variant="outline"
                                                                                size="xs"
                                                                                className="tabular-nums"
                                                                            >
                                                                                {
                                                                                    student.duration_years
                                                                                }{" "}
                                                                                yr(s)
                                                                            </Badge>
                                                                        ) : null}
                                                                        {student.internship_hours ? (
                                                                            <Badge
                                                                                variant="primary-light"
                                                                                size="xs"
                                                                                className="tabular-nums"
                                                                            >
                                                                                OJT:{" "}
                                                                                {
                                                                                    student.internship_hours
                                                                                }{" "}
                                                                                hrs
                                                                            </Badge>
                                                                        ) : null}
                                                                        {student.bundled_qualifications?.map(
                                                                            (qual) => (
                                                                                <Badge
                                                                                    key={
                                                                                        qual
                                                                                    }
                                                                                    variant="secondary"
                                                                                    size="xs"
                                                                                >
                                                                                    {qual}
                                                                                </Badge>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>

                                                        {/* Curriculum Progress Pill */}
                                                        {checklist && (
                                                            <div className="min-w-[148px] shrink-0 rounded-lg border border-border bg-muted/35 px-3 py-2">
                                                                <div className="flex items-center justify-between gap-2 text-[11px]">
                                                                    <span className="text-muted-foreground">
                                                                        Curriculum
                                                                    </span>
                                                                    <span className="font-semibold text-foreground tabular-nums">
                                                                        {Math.round(
                                                                            checklist.progress_percentage,
                                                                        )}
                                                                        %
                                                                    </span>
                                                                </div>
                                                                <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                                    <div
                                                                        className="h-full rounded-full bg-primary transition-all"
                                                                        style={{
                                                                            width: `${Math.min(100, Math.max(0, checklist.progress_percentage))}%`,
                                                                        }}
                                                                    />
                                                                </div>
                                                                <div className="mt-1 flex items-center justify-between gap-2 text-[10px] text-muted-foreground tabular-nums">
                                                                    <span>
                                                                        {
                                                                            checklist.completed_subjects_count
                                                                        }{" "}
                                                                        passed
                                                                    </span>
                                                                    {checklist.failed_retakes_count >
                                                                        0 && (
                                                                        <span className="font-medium text-destructive">
                                                                            {
                                                                                checklist.failed_retakes_count
                                                                            }{" "}
                                                                            retake(s)
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Inline Semester & Year / Training Level Selectors */}
                                                    <div className="grid grid-cols-1 gap-3 border-t border-border/70 pt-3.5 sm:grid-cols-2">
                                                        <div className="min-w-0 space-y-1.5">
                                                            <Label
                                                                htmlFor="create-semester"
                                                                className="text-xs"
                                                            >
                                                                Semester ({draft.schoolYear})
                                                            </Label>
                                                            <Select
                                                                value={draft.semester}
                                                                onValueChange={
                                                                    draft.setSemester
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id="create-semester"
                                                                    className="h-8 w-full text-xs"
                                                                >
                                                                    <SelectValue placeholder="Select semester" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {semesterOptions.map(
                                                                        (sem) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    sem.value
                                                                                }
                                                                                value={
                                                                                    sem.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    sem.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="min-w-0 space-y-1.5">
                                                            <Label
                                                                htmlFor="create-academic-year"
                                                                className="text-xs"
                                                            >
                                                                {draft.isTesdaPathway
                                                                    ? "Training Level"
                                                                    : "Academic Year Level"}
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    draft.academicYear
                                                                }
                                                                onValueChange={
                                                                    draft.setAcademicYear
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id="create-academic-year"
                                                                    className="h-8 w-full text-xs"
                                                                >
                                                                    <SelectValue
                                                                        placeholder={
                                                                            draft.isTesdaPathway
                                                                                ? "Select training level"
                                                                                : "Select year level"
                                                                        }
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {academicYearOptions.map(
                                                                        ([
                                                                            value,
                                                                            label,
                                                                        ]) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    value
                                                                                }
                                                                                value={
                                                                                    value
                                                                                }
                                                                            >
                                                                                {label}
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                        </FramePanel>
                                    </Frame>
                                </StepperContent>

                                {/* STEP 2: Subjects & Load */}
                                <StepperContent value={2} className="min-w-0 space-y-4">
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
                                            Number(draft.semester) ||
                                            props.settings.currentSemester
                                        }
                                        disabled={
                                            draft.isTerminalLocked ||
                                            !canProceedStep1
                                        }
                                    />

                                    {/* Compact Selected Subjects Summary List */}
                                    <Frame spacing="sm" className="min-w-0">
                                        <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <IconTile
                                                    variant="soft"
                                                    size="xs"
                                                    className="shrink-0 text-primary"
                                                >
                                                    <Layers />
                                                </IconTile>
                                                <div className="min-w-0">
                                                    <FrameTitle className="truncate">
                                                        Draft Subject Load (
                                                        {
                                                            draft.subjectsEnrolled
                                                                .length
                                                        }
                                                        )
                                                    </FrameTitle>
                                                    <FrameDescription className="truncate text-xs tabular-nums">
                                                        {draft.totals.totalUnits} /{" "}
                                                        {draft.unitCap} units ·{" "}
                                                        {draft.totals.totalLectureUnits}
                                                        L / {draft.totals.totalLabUnits}
                                                        Lab
                                                    </FrameDescription>
                                                </div>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-1.5">
                                                {draft.isUnitOverload && (
                                                    <Badge
                                                        variant="warning-light"
                                                        size="xs"
                                                    >
                                                        Unit Overload
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant="secondary"
                                                    size="xs"
                                                    className="tabular-nums"
                                                >
                                                    {draft.formatCurrency(
                                                        draft.totals.totalTuition,
                                                    )}{" "}
                                                    tuition
                                                </Badge>
                                            </div>
                                        </FrameHeader>

                                        <FramePanel className="min-w-0 p-0">
                                            {draft.subjectsEnrolled.length === 0 ? (
                                                <div className="flex flex-col items-center justify-center gap-1.5 px-4 py-8 text-center">
                                                    <BookOpen className="size-5 text-muted-foreground/70" />
                                                    <p className="text-xs font-medium text-foreground">
                                                        No subjects added yet
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Click <strong>Load Recommended</strong> above or add individual subjects from the catalog.
                                                    </p>
                                                </div>
                                            ) : (
                                                <div className="divide-y divide-border/70">
                                                    {draft.subjectsEnrolled.map(
                                                        (sub) => {
                                                            const lec =
                                                                Number(
                                                                    sub.lecture_units,
                                                                ) || 0;
                                                            const lab =
                                                                Number(
                                                                    sub.laboratory_units,
                                                                ) || 0;
                                                            const totalUnits =
                                                                lec + lab;
                                                            const lineFee =
                                                                (Number(
                                                                    sub.lecture_fee,
                                                                ) || 0) +
                                                                (Number(
                                                                    sub.laboratory_fee,
                                                                ) || 0);

                                                            return (
                                                                <div
                                                                    key={
                                                                        sub.subject_id
                                                                    }
                                                                    className="flex min-w-0 flex-wrap items-center justify-between gap-2.5 px-3.5 py-2.5 transition-colors hover:bg-muted/30 sm:flex-nowrap"
                                                                >
                                                                    <div className="min-w-0 flex-1 space-y-0.5">
                                                                        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                                            <span className="shrink-0 font-mono text-xs font-semibold text-foreground">
                                                                                {
                                                                                    sub.subject_code
                                                                                }
                                                                            </span>
                                                                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                                                                {
                                                                                    totalUnits
                                                                                }
                                                                                u ({lec}
                                                                                L/{lab}
                                                                                Lab)
                                                                            </span>
                                                                            {sub.is_failed_retake && (
                                                                                <Badge
                                                                                    variant="destructive-light"
                                                                                    size="xs"
                                                                                >
                                                                                    Retake
                                                                                </Badge>
                                                                            )}
                                                                            {sub.already_passed && (
                                                                                <Badge
                                                                                    variant="secondary"
                                                                                    size="xs"
                                                                                >
                                                                                    Passed
                                                                                </Badge>
                                                                            )}
                                                                            {sub.prerequisite_satisfied ===
                                                                                false && (
                                                                                <Badge
                                                                                    variant="warning-outline"
                                                                                    size="xs"
                                                                                >
                                                                                    Missing prereq
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                        <p
                                                                            className="truncate text-xs text-foreground/90"
                                                                            title={
                                                                                sub.subject_title
                                                                            }
                                                                        >
                                                                            {
                                                                                sub.subject_title
                                                                            }
                                                                        </p>
                                                                    </div>

                                                                    <div className="flex shrink-0 items-center gap-2">
                                                                        {/* Mod & Excl quick chips */}
                                                                        <div className="flex items-center gap-1">
                                                                            <button
                                                                                type="button"
                                                                                aria-pressed={
                                                                                    sub.is_modular
                                                                                }
                                                                                disabled={
                                                                                    draft.isTerminalLocked
                                                                                }
                                                                                onClick={() =>
                                                                                    void draft.handleToggleModular(
                                                                                        sub.subject_id,
                                                                                        !sub.is_modular,
                                                                                    )
                                                                                }
                                                                                title={
                                                                                    sub.is_modular
                                                                                        ? "Modular delivery enabled"
                                                                                        : "Enable modular delivery"
                                                                                }
                                                                                className={cn(
                                                                                    "inline-flex h-6 items-center rounded-md border px-2 text-[11px] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50",
                                                                                    sub.is_modular
                                                                                        ? "border-primary/40 bg-primary/10 text-primary dark:border-primary/45 dark:bg-primary/20"
                                                                                        : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
                                                                                )}
                                                                            >
                                                                                Mod
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                aria-pressed={
                                                                                    sub.exclude_from_tuition
                                                                                }
                                                                                disabled={
                                                                                    draft.isTerminalLocked ||
                                                                                    !draft.canManageTuitionFees
                                                                                }
                                                                                onClick={() =>
                                                                                    draft.handleToggleExclude(
                                                                                        sub.subject_id,
                                                                                        !sub.exclude_from_tuition,
                                                                                    )
                                                                                }
                                                                                title={
                                                                                    !draft.canManageTuitionFees
                                                                                        ? "Finance managed"
                                                                                        : sub.exclude_from_tuition
                                                                                          ? "Excluded from tuition"
                                                                                          : "Exclude from tuition"
                                                                                }
                                                                                className={cn(
                                                                                    "inline-flex h-6 items-center rounded-md border px-2 text-[11px] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50",
                                                                                    sub.exclude_from_tuition
                                                                                        ? "border-warning/45 bg-warning/10 text-warning-foreground dark:border-warning/45 dark:bg-warning/15 dark:text-warning"
                                                                                        : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
                                                                                )}
                                                                            >
                                                                                Excl
                                                                            </button>
                                                                        </div>

                                                                        {/* Line Fee */}
                                                                        <div className="min-w-[76px] text-right">
                                                                            {sub.calculating_fees ? (
                                                                                <Loader2 className="ml-auto size-3.5 animate-spin text-muted-foreground" />
                                                                            ) : sub.exclude_from_tuition ? (
                                                                                <span className="text-[11px] font-medium text-muted-foreground">
                                                                                    Excluded
                                                                                </span>
                                                                            ) : (
                                                                                <span className="text-xs font-semibold text-foreground tabular-nums">
                                                                                    {draft.formatCurrency(
                                                                                        lineFee,
                                                                                    )}
                                                                                </span>
                                                                            )}
                                                                        </div>

                                                                        {/* Remove */}
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon-xs"
                                                                            onClick={() =>
                                                                                draft.handleRemoveSubject(
                                                                                    sub.subject_id,
                                                                                )
                                                                            }
                                                                            aria-label={`Remove ${sub.subject_code}`}
                                                                            title={`Remove ${sub.subject_code}`}
                                                                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                        >
                                                                            <Trash2 className="size-3.5" />
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            )}
                                        </FramePanel>
                                    </Frame>
                                </StepperContent>

                                {/* STEP 3: Sections & Schedule */}
                                <StepperContent value={3} className="min-w-0 space-y-4">
                                    <Frame spacing="sm" className="min-w-0">
                                        <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <IconTile
                                                    variant="soft"
                                                    size="xs"
                                                    className="shrink-0 text-primary"
                                                >
                                                    <Clock />
                                                </IconTile>
                                                <div className="min-w-0">
                                                    <FrameTitle className="truncate">
                                                        Section Assignment &amp; Capacity
                                                    </FrameTitle>
                                                    <FrameDescription className="truncate text-xs tabular-nums">
                                                        {assignedSectionCount} of{" "}
                                                        {
                                                            draft.subjectsEnrolled
                                                                .length
                                                        }{" "}
                                                        subject(s) assigned · hover a row to highlight on timetable
                                                    </FrameDescription>
                                                </div>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-1.5">
                                                {fullSelectedSectionsCount > 0 && (
                                                    <Badge
                                                        variant="warning-light"
                                                        size="xs"
                                                    >
                                                        {fullSelectedSectionsCount}{" "}
                                                        full section
                                                        {fullSelectedSectionsCount ===
                                                        1
                                                            ? ""
                                                            : "s"}
                                                    </Badge>
                                                )}
                                                {draft.scheduleConflicts.length >
                                                0 ? (
                                                    <Badge
                                                        variant="destructive-light"
                                                        size="xs"
                                                    >
                                                        <AlertTriangle className="size-3" />
                                                        {
                                                            draft
                                                                .scheduleConflicts
                                                                .length
                                                        }{" "}
                                                        {draft.scheduleConflicts
                                                            .length === 1
                                                            ? "clash"
                                                            : "clashes"}
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="success-light"
                                                        size="xs"
                                                    >
                                                        0 clashes
                                                    </Badge>
                                                )}
                                            </div>
                                        </FrameHeader>

                                        <FramePanel className="min-w-0 p-0">
                                            {draft.subjectsEnrolled.map((sub) => (
                                                <SubjectLoadRow
                                                    key={sub.subject_id}
                                                    subject={sub}
                                                    hasConflict={draft.conflictingSubjectIds.has(
                                                        sub.subject_id,
                                                    )}
                                                    conflictDetails={conflictDetailsBySubjectId.get(
                                                        sub.subject_id,
                                                    )}
                                                    waitlist={draft.waitlistBySubjectId.get(
                                                        sub.subject_id,
                                                    )}
                                                    canManageTuitionFees={
                                                        draft.canManageTuitionFees
                                                    }
                                                    disabled={
                                                        draft.isTerminalLocked
                                                    }
                                                    isHighlighted={
                                                        highlightedSubjectId ===
                                                        sub.subject_id
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
                                            ))}
                                        </FramePanel>
                                    </Frame>

                                    <ScheduleMatrixPanel
                                        subjectsEnrolled={draft.subjectsEnrolled}
                                        scheduleConflicts={
                                            draft.scheduleConflicts
                                        }
                                        highlightedSubjectId={
                                            highlightedSubjectId
                                        }
                                        onHoverSubject={setHighlightedSubjectId}
                                    />
                                </StepperContent>

                                {/* STEP 4: Tuition & Confirm */}
                                <StepperContent value={4} className="min-w-0 space-y-4">
                                    {/* Additional Fees Editor */}
                                    <Frame spacing="sm" className="min-w-0">
                                        <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <IconTile
                                                    variant="soft"
                                                    size="xs"
                                                    className="shrink-0 text-primary"
                                                >
                                                    <Receipt />
                                                </IconTile>
                                                <div className="min-w-0">
                                                    <FrameTitle className="truncate">
                                                        Additional Fees
                                                    </FrameTitle>
                                                    <FrameDescription className="truncate text-xs">
                                                        Add optional charges (ID, lab manual, materials) to this assessment
                                                    </FrameDescription>
                                                </div>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-2">
                                                {!draft.canManageTuitionFees && (
                                                    <Badge
                                                        variant="secondary"
                                                        size="xs"
                                                    >
                                                        Finance managed
                                                    </Badge>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    onClick={draft.handleAddFee}
                                                    disabled={
                                                        !draft.canManageTuitionFees ||
                                                        draft.isTerminalLocked
                                                    }
                                                    className="gap-1"
                                                >
                                                    <Plus className="size-3" />
                                                    <span>Add Fee</span>
                                                </Button>
                                            </div>
                                        </FrameHeader>

                                        <FramePanel className="min-w-0">
                                            {draft.additionalFees.length === 0 ? (
                                                <div className="flex flex-col items-center justify-center gap-1 py-5 text-center">
                                                    <p className="text-xs font-medium text-foreground">
                                                        No additional fees attached
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Use <strong>Add Fee</strong> if this enrollment requires extra itemized charges.
                                                    </p>
                                                </div>
                                            ) : (
                                                <div className="space-y-2.5">
                                                    {draft.additionalFees.map(
                                                        (fee, index) => {
                                                            const isProtected =
                                                                Boolean(
                                                                    fee.is_separate_transaction ||
                                                                        fee.transaction_number,
                                                                );
                                                            const rowDisabled =
                                                                !draft.canManageTuitionFees ||
                                                                draft.isTerminalLocked ||
                                                                isProtected;

                                                            return (
                                                                <div
                                                                    key={
                                                                        fee.id ??
                                                                        `fee-${index}`
                                                                    }
                                                                    className="grid grid-cols-1 items-center gap-2 sm:grid-cols-[minmax(0,1fr)_140px_auto]"
                                                                >
                                                                    <Input
                                                                        type="text"
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
                                                                        placeholder="Fee description (e.g. Laboratory Kit)"
                                                                        aria-label={`Additional fee ${index + 1} name`}
                                                                        disabled={
                                                                            rowDisabled
                                                                        }
                                                                        className="h-8 text-xs"
                                                                    />
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
                                                                        aria-label={`Additional fee ${index + 1} amount`}
                                                                        disabled={
                                                                            rowDisabled
                                                                        }
                                                                        className="h-8 text-xs tabular-nums"
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon-xs"
                                                                        onClick={() =>
                                                                            draft.handleRemoveFee(
                                                                                index,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            rowDisabled
                                                                        }
                                                                        aria-label={`Remove fee ${fee.fee_name || index + 1}`}
                                                                        className="justify-self-end text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                    >
                                                                        <Trash2 className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            )}
                                        </FramePanel>
                                    </Frame>

                                    {/* Final Pre-Submission Review Checklist */}
                                    <Frame spacing="sm" className="min-w-0">
                                        <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <IconTile
                                                    variant="soft"
                                                    size="xs"
                                                    className="shrink-0 text-primary"
                                                >
                                                    <ClipboardCheck />
                                                </IconTile>
                                                <div className="min-w-0">
                                                    <FrameTitle className="truncate">
                                                        Pre-Submission Review Checklist
                                                    </FrameTitle>
                                                    <FrameDescription className="truncate text-xs">
                                                        Confirm learner, load, schedule, clearance, and financial totals before creating
                                                    </FrameDescription>
                                                </div>
                                            </div>

                                            {windowBlockerActive ||
                                            holdsBlockerActive ? (
                                                <Badge
                                                    variant="destructive-light"
                                                    size="xs"
                                                >
                                                    <ShieldAlert className="size-3" />
                                                    Override needed
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="success-light"
                                                    size="xs"
                                                >
                                                    <ShieldCheck className="size-3" />
                                                    Ready to submit
                                                </Badge>
                                            )}
                                        </FrameHeader>

                                        <FramePanel className="min-w-0 space-y-4">
                                            <dl className="divide-y divide-border/70 text-xs">
                                                {/* Student */}
                                                <div className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0">
                                                    <dt className="text-muted-foreground">
                                                        Student
                                                    </dt>
                                                    <dd className="flex min-w-0 flex-wrap items-center justify-end gap-1.5 text-right font-medium text-foreground">
                                                        <span className="truncate">
                                                            {student?.full_name ??
                                                                "—"}
                                                        </span>
                                                        {student?.student_id && (
                                                            <Badge
                                                                variant="secondary"
                                                                size="xs"
                                                                className="font-mono tabular-nums"
                                                            >
                                                                {
                                                                    student.student_id
                                                                }
                                                            </Badge>
                                                        )}
                                                        {student?.course_code && (
                                                            <Badge
                                                                variant="outline"
                                                                size="xs"
                                                            >
                                                                {
                                                                    student.course_code
                                                                }
                                                            </Badge>
                                                        )}
                                                    </dd>
                                                </div>

                                                {/* Term */}
                                                <div className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                                    <dt className="text-muted-foreground">
                                                        Term &amp; Level
                                                    </dt>
                                                    <dd className="text-right font-medium text-foreground tabular-nums">
                                                        {draft.schoolYear} ·{" "}
                                                        {semesterLabel} ·{" "}
                                                        {academicYearLabel}
                                                    </dd>
                                                </div>

                                                {/* Subjects / Units / Cap */}
                                                <div className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                                    <dt className="text-muted-foreground">
                                                        Subjects &amp; Unit Cap
                                                    </dt>
                                                    <dd className="flex flex-wrap items-center justify-end gap-1.5 tabular-nums">
                                                        <span className="font-medium text-foreground">
                                                            {
                                                                draft
                                                                    .subjectsEnrolled
                                                                    .length
                                                            }{" "}
                                                            subject
                                                            {draft
                                                                .subjectsEnrolled
                                                                .length === 1
                                                                ? ""
                                                                : "s"}{" "}
                                                            ·{" "}
                                                            {
                                                                draft.totals
                                                                    .totalUnits
                                                            }
                                                            /{draft.unitCap} u
                                                        </span>
                                                        {draft.isUnitOverload ? (
                                                            <Badge
                                                                variant="warning-light"
                                                                size="xs"
                                                            >
                                                                Overload (+
                                                                {draft.totals
                                                                    .totalUnits -
                                                                    draft.unitCap}
                                                                u)
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="success-light"
                                                                size="xs"
                                                            >
                                                                Within cap
                                                            </Badge>
                                                        )}
                                                    </dd>
                                                </div>

                                                {/* Sections & Clashes */}
                                                <div className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                                    <dt className="text-muted-foreground">
                                                        Assigned Sections &amp; Clashes
                                                    </dt>
                                                    <dd className="flex flex-wrap items-center justify-end gap-1.5 tabular-nums">
                                                        <span className="font-medium text-foreground">
                                                            {
                                                                assignedSectionCount
                                                            }
                                                            /
                                                            {
                                                                draft
                                                                    .subjectsEnrolled
                                                                    .length
                                                            }{" "}
                                                            assigned
                                                        </span>
                                                        {draft.scheduleConflicts
                                                            .length > 0 ? (
                                                            <Badge
                                                                variant="destructive-light"
                                                                size="xs"
                                                            >
                                                                <AlertTriangle className="size-2.5" />
                                                                {
                                                                    draft
                                                                        .scheduleConflicts
                                                                        .length
                                                                }{" "}
                                                                {draft
                                                                    .scheduleConflicts
                                                                    .length ===
                                                                1
                                                                    ? "clash"
                                                                    : "clashes"}
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="success-light"
                                                                size="xs"
                                                            >
                                                                0 clashes
                                                            </Badge>
                                                        )}
                                                        {fullSelectedSectionsCount >
                                                            0 && (
                                                            <Badge
                                                                variant="warning-light"
                                                                size="xs"
                                                            >
                                                                {
                                                                    fullSelectedSectionsCount
                                                                }{" "}
                                                                capacity overload
                                                            </Badge>
                                                        )}
                                                    </dd>
                                                </div>

                                                {/* Window / Hold Clearance */}
                                                <div className="flex flex-wrap items-center justify-between gap-2 py-2.5 last:pb-0">
                                                    <dt className="text-muted-foreground">
                                                        Window &amp; Hold Clearance
                                                    </dt>
                                                    <dd className="flex flex-wrap items-center justify-end gap-1.5">
                                                        {windowBlockerActive ? (
                                                            <Badge
                                                                variant="destructive-light"
                                                                size="xs"
                                                            >
                                                                Window override required
                                                            </Badge>
                                                        ) : draft.windowRequiresOverride &&
                                                          draft.overrideWindow ? (
                                                            <Badge
                                                                variant="warning-light"
                                                                size="xs"
                                                            >
                                                                Window overridden
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="success-light"
                                                                size="xs"
                                                            >
                                                                Window clear
                                                            </Badge>
                                                        )}

                                                        {holdsBlockerActive ? (
                                                            <Badge
                                                                variant="destructive-light"
                                                                size="xs"
                                                            >
                                                                {
                                                                    draft
                                                                        .blockingHolds
                                                                        .length
                                                                }{" "}
                                                                blocking hold
                                                                {draft
                                                                    .blockingHolds
                                                                    .length ===
                                                                1
                                                                    ? ""
                                                                    : "s"}
                                                            </Badge>
                                                        ) : draft.holdsRequireOverride &&
                                                          draft.overrideHolds ? (
                                                            <Badge
                                                                variant="warning-light"
                                                                size="xs"
                                                            >
                                                                Holds overridden
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="success-light"
                                                                size="xs"
                                                            >
                                                                No blocking holds
                                                            </Badge>
                                                        )}
                                                    </dd>
                                                </div>
                                            </dl>

                                            {/* Financial Summary Tiles */}
                                            <div className="grid grid-cols-1 gap-2 border-t border-border/70 pt-3 sm:grid-cols-3">
                                                <div className="min-w-0 rounded-lg border border-border bg-muted/35 px-3 py-2">
                                                    <p className="truncate text-[11px] text-muted-foreground">
                                                        Estimated Total
                                                    </p>
                                                    <p className="mt-0.5 truncate text-sm font-bold text-primary tabular-nums">
                                                        {draft.formatCurrency(
                                                            draft.totals
                                                                .overallTotal,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="min-w-0 rounded-lg border border-border bg-muted/35 px-3 py-2">
                                                    <p className="truncate text-[11px] text-muted-foreground">
                                                        Downpayment
                                                    </p>
                                                    <p className="mt-0.5 truncate text-sm font-semibold text-foreground tabular-nums">
                                                        {draft.formatCurrency(
                                                            draft.totals
                                                                .downpaymentAmount,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="min-w-0 rounded-lg border border-border bg-muted/35 px-3 py-2">
                                                    <p className="truncate text-[11px] text-muted-foreground">
                                                        Projected Balance
                                                    </p>
                                                    <p className="mt-0.5 truncate text-sm font-semibold text-foreground tabular-nums">
                                                        {draft.formatCurrency(
                                                            draft.totals
                                                                .projectedBalance,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        </FramePanel>
                                    </Frame>
                                </StepperContent>
                            </StepperPanel>
                        </Stepper>

                        {/* Persistent Bottom Tally & Blocker Dock */}
                        <Frame
                            spacing="xs"
                            className="sticky bottom-3 z-20 min-w-0 shadow-sm"
                        >
                            <FramePanel className="flex min-w-0 flex-wrap items-center justify-between gap-3 px-3.5 py-2.5">
                                {/* Left Tally */}
                                <div className="flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1.5 text-xs tabular-nums">
                                    <span className="font-medium text-foreground">
                                        {draft.subjectsEnrolled.length}{" "}
                                        {draft.subjectsEnrolled.length === 1
                                            ? "subject"
                                            : "subjects"}{" "}
                                        · {draft.totals.totalUnits}/
                                        {draft.unitCap} u
                                    </span>

                                    {draft.scheduleConflicts.length > 0 ? (
                                        <Badge
                                            variant="destructive-light"
                                            size="sm"
                                        >
                                            <AlertTriangle className="size-3" />
                                            {draft.scheduleConflicts.length}{" "}
                                            {draft.scheduleConflicts.length === 1
                                                ? "clash"
                                                : "clashes"}
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="success-light"
                                            size="sm"
                                        >
                                            0 clashes
                                        </Badge>
                                    )}

                                    <span className="text-muted-foreground">
                                        Est. Total{" "}
                                        <strong className="font-semibold text-foreground">
                                            {draft.formatCurrency(
                                                draft.totals.overallTotal,
                                            )}
                                        </strong>
                                    </span>
                                </div>

                                {/* Right Step Controls */}
                                <div className="flex shrink-0 items-center gap-2">
                                    {currentStep > 1 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                handleStepChange(
                                                    currentStep - 1,
                                                )
                                            }
                                            className="gap-1"
                                        >
                                            <ChevronLeft className="size-3.5" />
                                            <span>Previous</span>
                                        </Button>
                                    )}

                                    {currentStep < 4 ? (
                                        <Button
                                            type="button"
                                            variant="default"
                                            size="sm"
                                            disabled={isNextDisabled}
                                            onClick={() =>
                                                handleStepChange(
                                                    currentStep + 1,
                                                )
                                            }
                                            className="gap-1"
                                        >
                                            <span>Next Step</span>
                                            <ChevronRight className="size-3.5" />
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="default"
                                            size="sm"
                                            disabled={isSubmitDisabled}
                                            onClick={() => draft.handleSubmit()}
                                            className="gap-1.5"
                                        >
                                            {draft.submitting ? (
                                                <>
                                                    <Loader2 className="size-3.5 animate-spin" />
                                                    <span>
                                                        Creating Enrollment…
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <CheckCircle2 className="size-3.5" />
                                                    <span>
                                                        Create Enrollment
                                                    </span>
                                                </>
                                            )}
                                        </Button>
                                    )}
                                </div>
                            </FramePanel>
                        </Frame>
                    </div>

                    {/* Right Column: Live Assessment Rail */}
                    <div className="min-w-0">
                        <AssessmentDiffRail
                            draft={draft}
                            submitLabel="Create Enrollment"
                            hideSubmitButton={currentStep < 4}
                        />
                    </div>
                </div>
            </div>

            {/* Workbench Dialogs */}
            <WorkbenchDialogs
                draft={draft}
                unsavedDialogOpen={unsavedDialogOpen}
                onUnsavedDialogOpenChange={setUnsavedDialogOpen}
                onConfirmLeave={handleConfirmLeave}
            />
        </AdminLayout>
    );
}
