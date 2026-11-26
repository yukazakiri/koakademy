import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import {
    AlertTriangle,
    ArrowLeftRight,
    ArrowRight,
    Calendar,
    CalendarRange,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Eye,
    GitMerge,
    GripVertical,
    Loader2,
    Lock,
    MapPin,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Trash2,
    Undo2,
    UserCheck,
    UserPlus,
    Users,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import type { ClassRow } from "../columns";
import { semesterLabel } from "../components/class-list-details";
import { isOnlineDeliveryMode } from "../lib/class-defaults";
import {
    WorkspaceApiError,
    deleteClassEnrollment,
    enrollStudentsInClass,
    fetchParallelSectionsWorkspace,
    patchClassEnrollment,
    postBulkMoveStudents,
    postPromoteWaitlist,
    searchClassCandidates,
} from "./api";
import type {
    EnrollmentStatusValue,
    IndexScheduleBlock,
    ParallelSectionEnrollment,
    ParallelSectionItem,
    ParallelSectionsWorkspacePayload,
    ParallelWaitlistEntry,
    StudentSearchCandidate,
} from "./types";

export interface SectionRebalancerStudioProps {
    anchorClassId: number | null;
    availableClasses: ClassRow[];
    onChangeAnchorClass: (classId: number) => void;
    onOpenScheduleInLedger: (classId: number) => void;
    onMutated: () => void;
    canUpdate: boolean;
}

type StagedMove = {
    studentId: number;
    enrollmentId: number;
    studentName: string;
    studentNumber: string | number | null;
    course: string | null;
    fromSectionId: number;
    fromSectionLabel: string;
    toSectionId: number;
    toSectionLabel: string;
    forceOverload: boolean;
};

type SelectedMoveStudent = {
    studentId: number;
    enrollmentId: number;
    name: string;
    studentNumber: string | number | null;
    course: string | null;
    fromSectionId: number;
    fromSectionLabel: string;
};

type InspectedStudentMeta = {
    studentId: number;
    name: string;
    studentNumber: string | number | null;
    source: "move" | "inspect" | "waitlist";
};

type SectionColorTheme = {
    accentBar: string;
    swatch: string;
    block: string;
    badge: string;
};

const SECTION_THEMES: SectionColorTheme[] = [
    {
        accentBar: "bg-[#b8533c] dark:bg-[#d96b52]",
        swatch: "bg-[#b8533c]",
        block: "border-stone-400/70 bg-stone-100/95 text-stone-900 dark:border-stone-600/80 dark:bg-stone-800/85 dark:text-stone-100",
        badge: "border-stone-300 bg-stone-100 text-stone-800 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-200",
    },
    {
        accentBar: "bg-emerald-600 dark:bg-emerald-500",
        swatch: "bg-emerald-500",
        block: "border-emerald-300/90 bg-emerald-50/90 text-emerald-950 dark:border-emerald-700/70 dark:bg-emerald-950/45 dark:text-emerald-100",
        badge: "border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200",
    },
    {
        accentBar: "bg-amber-500 dark:bg-amber-400",
        swatch: "bg-amber-500",
        block: "border-amber-300/90 bg-amber-50/90 text-amber-950 dark:border-amber-700/70 dark:bg-amber-950/45 dark:text-amber-100",
        badge: "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200",
    },
    {
        accentBar: "bg-sky-600 dark:bg-sky-500",
        swatch: "bg-sky-500",
        block: "border-sky-300/90 bg-sky-50/90 text-sky-950 dark:border-sky-700/70 dark:bg-sky-950/45 dark:text-sky-100",
        badge: "border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-200",
    },
    {
        accentBar: "bg-indigo-600 dark:bg-indigo-500",
        swatch: "bg-indigo-500",
        block: "border-indigo-300/90 bg-indigo-50/90 text-indigo-950 dark:border-indigo-700/70 dark:bg-indigo-950/45 dark:text-indigo-100",
        badge: "border-indigo-300 bg-indigo-50 text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200",
    },
    {
        accentBar: "bg-teal-600 dark:bg-teal-500",
        swatch: "bg-teal-500",
        block: "border-teal-300/90 bg-teal-50/90 text-teal-950 dark:border-teal-700/70 dark:bg-teal-950/45 dark:text-teal-100",
        badge: "border-teal-300 bg-teal-50 text-teal-800 dark:border-teal-800 dark:bg-teal-950/60 dark:text-teal-200",
    },
];

const WEEK_DAYS = [
    { key: "Monday", short: "Mon" },
    { key: "Tuesday", short: "Tue" },
    { key: "Wednesday", short: "Wed" },
    { key: "Thursday", short: "Thu" },
    { key: "Friday", short: "Fri" },
    { key: "Saturday", short: "Sat" },
] as const;

const DAY_ABBREV: Record<string, string> = {
    Monday: "M",
    Tuesday: "T",
    Wednesday: "W",
    Thursday: "Th",
    Friday: "F",
    Saturday: "S",
    Sunday: "Su",
};

const ENROLLMENT_STATUS_OPTIONS: Array<{ value: EnrollmentStatusValue; label: string }> = [
    { value: "enrolled", label: "Enrolled" },
    { value: "dropped", label: "Dropped" },
    { value: "withdrawn", label: "Withdrawn" },
    { value: "incomplete", label: "INC" },
    { value: "audit", label: "Audit" },
];

function shortTime(value: string | null | undefined): string {
    if (!value) return "--:--";
    return value.slice(0, 5);
}

function parseMinutes(value: string | null | undefined): number | null {
    if (!value) return null;
    const match = /^(\d{1,2}):(\d{2})/.exec(value.trim());
    if (!match) return null;
    return Number(match[1]) * 60 + Number(match[2]);
}

function blocksOverlap(startA: string | null | undefined, endA: string | null | undefined, startB: string | null | undefined, endB: string | null | undefined): boolean {
    const a1 = parseMinutes(startA);
    const a2 = parseMinutes(endA);
    const b1 = parseMinutes(startB);
    const b2 = parseMinutes(endB);
    if (a1 === null || a2 === null || b1 === null || b2 === null) return false;
    return a1 < b2 && a2 > b1;
}

function formatHours(value: number): string {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

function getWaitlistCourse(entry: ParallelWaitlistEntry): string | null {
    const withAlias = entry as ParallelWaitlistEntry & { course_code?: string | null };
    return entry.course ?? withAlias.course_code ?? null;
}

function statusBadgeStyle(status: EnrollmentStatusValue): { label: string; className: string } {
    switch (status) {
        case "enrolled":
            return {
                label: "Enrolled",
                className: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
            };
        case "incomplete":
            return {
                label: "INC",
                className: "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300",
            };
        case "audit":
            return {
                label: "Audit",
                className: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300",
            };
        case "withdrawn":
            return {
                label: "Withdrawn",
                className: "border-orange-500/30 bg-orange-500/10 text-orange-700 dark:text-orange-300",
            };
        case "dropped":
            return {
                label: "Dropped",
                className: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300",
            };
    }
}

type CompactSchedulePill = {
    key: string;
    daysLabel: string;
    timeLabel: string;
    componentLabel: string;
    roomLabel: string | null;
    roomCapacity: number | null;
    hasConflict: boolean;
    isOvercrowded: boolean;
    isOnline: boolean;
};

const DAY_ORDER: Record<string, number> = {
    Monday: 0,
    Tuesday: 1,
    Wednesday: 2,
    Thursday: 3,
    Friday: 4,
    Saturday: 5,
    Sunday: 6,
};

function groupSchedulesIntoPills(schedules: IndexScheduleBlock[]): CompactSchedulePill[] {
    if (!schedules.length) return [];

    const sortedSchedules = [...schedules].sort(
        (a, b) => (DAY_ORDER[a.day_of_week] ?? 99) - (DAY_ORDER[b.day_of_week] ?? 99),
    );
    const map = new Map<string, CompactSchedulePill & { days: string[] }>();

    for (const block of sortedSchedules) {
        const isOnline = isOnlineDeliveryMode(block.delivery_mode);
        const start = shortTime(block.start_time);
        const end = shortTime(block.end_time);
        const componentLabel = block.component_type === "lab" ? "Lab" : "Lec";
        const roomLabel = block.room_name ?? (isOnline ? "Online" : null);
        const key = `${start}-${end}|${componentLabel}|${roomLabel ?? ""}|${block.room_capacity ?? ""}`;
        const dayCode = DAY_ABBREV[block.day_of_week] ?? block.day_of_week.slice(0, 2);

        const existing = map.get(key);
        if (existing) {
            if (!existing.days.includes(dayCode)) {
                existing.days.push(dayCode);
            }
            existing.hasConflict = existing.hasConflict || block.has_conflict;
            existing.isOvercrowded = existing.isOvercrowded || block.is_overcrowded;
        } else {
            map.set(key, {
                key,
                days: [dayCode],
                daysLabel: dayCode,
                timeLabel: `${start}–${end}`,
                componentLabel,
                roomLabel,
                roomCapacity: block.room_capacity,
                hasConflict: block.has_conflict,
                isOvercrowded: block.is_overcrowded,
                isOnline,
            });
        }
    }

    return Array.from(map.values()).map((item) => ({
        key: item.key,
        daysLabel: item.days.join(""),
        timeLabel: item.timeLabel,
        componentLabel: item.componentLabel,
        roomLabel: item.roomLabel,
        roomCapacity: item.roomCapacity,
        hasConflict: item.hasConflict,
        isOvercrowded: item.isOvercrowded,
        isOnline: item.isOnline,
    }));
}

type SubjectGroupOption = {
    subjectCode: string;
    subjectTitle: string;
    anchorId: number;
    sectionCount: number;
    classIds: number[];
    hasProblem: boolean;
};

function getErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof WorkspaceApiError) return error.message;
    if (error instanceof Error && error.message) return error.message;
    return fallback;
}

export function SectionRebalancerStudio({
    anchorClassId,
    availableClasses,
    onChangeAnchorClass,
    onOpenScheduleInLedger,
    onMutated,
    canUpdate,
}: SectionRebalancerStudioProps) {
    const effectiveAnchorId = anchorClassId ?? availableClasses[0]?.id ?? null;

    const [workspace, setWorkspace] = React.useState<ParallelSectionsWorkspacePayload | null>(null);
    const [loading, setLoading] = React.useState(false);
    const [refreshing, setRefreshing] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const [showWeeklyGrid, setShowWeeklyGrid] = React.useState(true);
    const [leftTab, setLeftTab] = React.useState<"waitlist" | "search">("waitlist");

    const [selectedMoveStudent, setSelectedMoveStudent] = React.useState<SelectedMoveStudent | null>(null);
    const [inspectedStudentMeta, setInspectedStudentMeta] = React.useState<InspectedStudentMeta | null>(null);
    const [inspectLoading, setInspectLoading] = React.useState(false);
    const [dragOverSectionId, setDragOverSectionId] = React.useState<number | null>(null);

    const [stagedMoves, setStagedMoves] = React.useState<StagedMove[]>([]);
    const [committingMoves, setCommittingMoves] = React.useState(false);

    // Per-lane roster search
    const [laneQueries, setLaneQueries] = React.useState<Record<number, string>>({});

    // Inline enrollee editor state
    const [expandedEnrollmentKey, setExpandedEnrollmentKey] = React.useState<string | null>(null);
    const [editStatus, setEditStatus] = React.useState<EnrollmentStatusValue>("enrolled");
    const [editRemarks, setEditRemarks] = React.useState("");
    const [savingEnrollmentKey, setSavingEnrollmentKey] = React.useState<string | null>(null);

    // Waitlist promotion busy states
    const [promotingWaitlistIds, setPromotingWaitlistIds] = React.useState<Record<number, boolean>>({});
    const [batchPromoting, setBatchPromoting] = React.useState(false);

    // Search & Add state
    const [activeTargetSectionId, setActiveTargetSectionId] = React.useState<number | null>(null);
    const [candidateQuery, setCandidateQuery] = React.useState("");
    const [candidates, setCandidates] = React.useState<StudentSearchCandidate[]>([]);
    const [searchingCandidates, setSearchingCandidates] = React.useState(false);
    const [enrollingCandidateId, setEnrollingCandidateId] = React.useState<number | null>(null);
    const [allowOverrides, setAllowOverrides] = React.useState(false);

    const requestCounterRef = React.useRef(0);

    // Group available classes by unique subject_code for the top selector
    const subjectGroups = React.useMemo<SubjectGroupOption[]>(() => {
        const grouped = new Map<string, SubjectGroupOption>();
        for (const cls of availableClasses) {
            const code = cls.subject_code || cls.record_title;
            const isOverCap = cls.maximum_slots > 0 && cls.students_count > cls.maximum_slots;
            const isProblem = Boolean(isOverCap || cls.schedule_health?.has_conflict || cls.schedule_health?.is_room_overcrowded);
            const existing = grouped.get(code);
            if (existing) {
                existing.sectionCount += 1;
                existing.classIds.push(cls.id);
                if (cls.id === effectiveAnchorId) {
                    existing.anchorId = cls.id;
                }
                existing.hasProblem = existing.hasProblem || isProblem;
            } else {
                grouped.set(code, {
                    subjectCode: code,
                    subjectTitle: cls.subject_title || cls.record_title,
                    anchorId: cls.id,
                    sectionCount: 1,
                    classIds: [cls.id],
                    hasProblem: isProblem,
                });
            }
        }
        return Array.from(grouped.values());
    }, [availableClasses, effectiveAnchorId]);

    const selectedGroupValue = React.useMemo(() => {
        if (!effectiveAnchorId) return "";
        const match = subjectGroups.find((group) => group.classIds.includes(effectiveAnchorId));
        return match ? String(match.anchorId) : String(effectiveAnchorId);
    }, [effectiveAnchorId, subjectGroups]);

    // Quick problem pills for over-cap / conflicted / overcrowded classes on current page
    const problemClasses = React.useMemo(() => {
        return availableClasses
            .filter((cls) => {
                const overCap = cls.maximum_slots > 0 && cls.students_count > cls.maximum_slots;
                return overCap || cls.schedule_health?.has_conflict || cls.schedule_health?.is_room_overcrowded;
            })
            .slice(0, 6);
    }, [availableClasses]);

    const loadWorkspace = React.useCallback(
        async (classId: number, inspectStudentId: number | null = null, opts: { silent?: boolean; isInspect?: boolean } = {}) => {
            const requestId = ++requestCounterRef.current;
            if (opts.isInspect) {
                setInspectLoading(true);
            } else if (opts.silent) {
                setRefreshing(true);
            } else {
                setLoading(true);
            }
            setError(null);

            try {
                const data = await fetchParallelSectionsWorkspace(classId, inspectStudentId);
                if (requestCounterRef.current !== requestId) return;
                setWorkspace(data);

                // Keep activeTargetSectionId aligned with loaded sections
                setActiveTargetSectionId((prev) => {
                    if (prev && data.sections.some((sec) => sec.id === prev)) {
                        return prev;
                    }
                    const openSec = data.sections.find((sec) => sec.maximum_slots <= 0 || sec.students_count < sec.maximum_slots);
                    return openSec?.id ?? data.sections[0]?.id ?? null;
                });
            } catch (err) {
                if (requestCounterRef.current !== requestId) return;
                const message = getErrorMessage(err, "Failed to load parallel sections workspace.");
                setError(message);
                toast.error(message);
            } finally {
                if (requestCounterRef.current === requestId) {
                    setLoading(false);
                    setRefreshing(false);
                    setInspectLoading(false);
                }
            }
        },
        [],
    );

    // Reload when anchor class changes
    React.useEffect(() => {
        if (!effectiveAnchorId) {
            setWorkspace(null);
            return;
        }
        setSelectedMoveStudent(null);
        setInspectedStudentMeta(null);
        setStagedMoves([]);
        setExpandedEnrollmentKey(null);
        setLaneQueries({});
        void loadWorkspace(effectiveAnchorId, null);
    }, [effectiveAnchorId, loadWorkspace]);

    // Candidate search debounce when Search & Add tab is open
    React.useEffect(() => {
        if (leftTab !== "search" || !activeTargetSectionId) return;
        const trimmed = candidateQuery.trim();
        let cancelled = false;
        const timer = window.setTimeout(async () => {
            setSearchingCandidates(true);
            try {
                const results = await searchClassCandidates(activeTargetSectionId, trimmed, 15);
                if (!cancelled) {
                    setCandidates(results);
                }
            } catch (err) {
                if (!cancelled) {
                    toast.error(getErrorMessage(err, "Failed to search student candidates."));
                }
            } finally {
                if (!cancelled) {
                    setSearchingCandidates(false);
                }
            }
        }, 220);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [leftTab, activeTargetSectionId, candidateQuery]);

    // Map sectionId -> color theme
    const sectionThemeById = React.useMemo(() => {
        const map = new Map<number, SectionColorTheme>();
        (workspace?.sections ?? []).forEach((sec, index) => {
            map.set(sec.id, SECTION_THEMES[index % SECTION_THEMES.length]);
        });
        return map;
    }, [workspace?.sections]);

    // Projected enrollment counts incorporating staged moves
    const projectedCountsBySection = React.useMemo(() => {
        const counts = new Map<number, number>();
        for (const sec of workspace?.sections ?? []) {
            counts.set(sec.id, sec.students_count);
        }
        for (const move of stagedMoves) {
            counts.set(move.fromSectionId, Math.max(0, (counts.get(move.fromSectionId) ?? 0) - 1));
            counts.set(move.toSectionId, (counts.get(move.toSectionId) ?? 0) + 1);
        }
        return counts;
    }, [workspace?.sections, stagedMoves]);

    const handleInspectStudent = React.useCallback(
        (meta: InspectedStudentMeta, ensureWeeklyOpen = false) => {
            if (!effectiveAnchorId) return;
            setInspectedStudentMeta(meta);
            if (ensureWeeklyOpen) {
                setShowWeeklyGrid(true);
            }
            void loadWorkspace(effectiveAnchorId, meta.studentId, { isInspect: true });
        },
        [effectiveAnchorId, loadWorkspace],
    );

    const handleClearInspection = React.useCallback(() => {
        setSelectedMoveStudent(null);
        setInspectedStudentMeta(null);
        if (effectiveAnchorId) {
            void loadWorkspace(effectiveAnchorId, null, { isInspect: true });
        }
    }, [effectiveAnchorId, loadWorkspace]);

    const handleSelectStudentForMove = React.useCallback(
        (section: ParallelSectionItem, enrollment: ParallelSectionEnrollment) => {
            if (selectedMoveStudent?.studentId === enrollment.student_id) {
                setSelectedMoveStudent(null);
                setInspectedStudentMeta(null);
                if (effectiveAnchorId) {
                    void loadWorkspace(effectiveAnchorId, null, { isInspect: true });
                }
                return;
            }
            setSelectedMoveStudent({
                studentId: enrollment.student_id,
                enrollmentId: enrollment.id,
                name: enrollment.name,
                studentNumber: enrollment.student_number,
                course: enrollment.course,
                fromSectionId: section.id,
                fromSectionLabel: section.section,
            });
            handleInspectStudent({
                studentId: enrollment.student_id,
                name: enrollment.name,
                studentNumber: enrollment.student_number,
                source: "move",
            });
        },
        [selectedMoveStudent, effectiveAnchorId, loadWorkspace, handleInspectStudent],
    );

    const handleStageMove = React.useCallback(
        (student: SelectedMoveStudent, targetSection: ParallelSectionItem, forceOverload = false) => {
            if (student.fromSectionId === targetSection.id) return;

            setStagedMoves((prev) => {
                const filtered = prev.filter((item) => item.studentId !== student.studentId);
                return [
                    ...filtered,
                    {
                        studentId: student.studentId,
                        enrollmentId: student.enrollmentId,
                        studentName: student.name,
                        studentNumber: student.studentNumber,
                        course: student.course,
                        fromSectionId: student.fromSectionId,
                        fromSectionLabel: student.fromSectionLabel,
                        toSectionId: targetSection.id,
                        toSectionLabel: targetSection.section,
                        forceOverload,
                    },
                ];
            });

            setSelectedMoveStudent(null);
            toast.success(`Staged ${student.name} → Section ${targetSection.section}`);
        },
        [],
    );

    const handleUnstageStudent = React.useCallback((studentId: number) => {
        setStagedMoves((prev) => prev.filter((item) => item.studentId !== studentId));
    }, []);

    const handleUndoLastMove = React.useCallback(() => {
        setStagedMoves((prev) => prev.slice(0, -1));
    }, []);

    const handleStageMerge = React.useCallback(
        (sourceSection: ParallelSectionItem) => {
            const candidate = sourceSection.merge_candidate;
            if (!candidate || !workspace) return;
            const targetSection = workspace.sections.find((sec) => sec.id === candidate.target_class_id);
            if (!targetSection) return;

            const movable = sourceSection.enrollments.filter((enr) => !enr.is_grade_protected);
            if (!movable.length) {
                toast.warning("No movable (non-grade-protected) students in this section.");
                return;
            }

            const alreadyStagedIds = new Set(stagedMoves.map((m) => m.studentId));
            const additions: StagedMove[] = movable
                .filter((enr) => !alreadyStagedIds.has(enr.student_id))
                .map((enr) => ({
                    studentId: enr.student_id,
                    enrollmentId: enr.id,
                    studentName: enr.name,
                    studentNumber: enr.student_number,
                    course: enr.course,
                    fromSectionId: sourceSection.id,
                    fromSectionLabel: sourceSection.section,
                    toSectionId: targetSection.id,
                    toSectionLabel: targetSection.section,
                    forceOverload:
                        targetSection.maximum_slots > 0 && candidate.projected_target_count > targetSection.maximum_slots,
                }));

            if (!additions.length) {
                toast.info("All movable students in this section are already staged.");
                return;
            }

            setStagedMoves((prev) => [...prev, ...additions]);
            toast.success(`Staged merge of ${additions.length} student(s): Sec ${sourceSection.section} → Sec ${targetSection.section}`);
        },
        [workspace, stagedMoves],
    );

    const handleCommitStagedMoves = React.useCallback(async () => {
        if (!stagedMoves.length || !effectiveAnchorId) return;
        setCommittingMoves(true);

        // Group moves by (fromSectionId, toSectionId, forceOverload)
        const groups = new Map<
            string,
            { fromSectionId: number; toSectionId: number; forceOverload: boolean; moves: StagedMove[] }
        >();
        for (const move of stagedMoves) {
            const key = `${move.fromSectionId}->${move.toSectionId}:${move.forceOverload ? "1" : "0"}`;
            const existing = groups.get(key);
            if (existing) {
                existing.moves.push(move);
            } else {
                groups.set(key, {
                    fromSectionId: move.fromSectionId,
                    toSectionId: move.toSectionId,
                    forceOverload: move.forceOverload,
                    moves: [move],
                });
            }
        }

        let totalMoved = 0;
        let totalErrors = 0;
        const remainingMoves: StagedMove[] = [];
        const errorMessages: string[] = [];

        for (const group of groups.values()) {
            try {
                const response = await postBulkMoveStudents(group.fromSectionId, {
                    target_class_id: group.toSectionId,
                    student_ids: group.moves.map((m) => m.studentId),
                    force_overload: group.forceOverload,
                    reason: "Rebalanced via Section Studio",
                });
                totalMoved += response.result.moved_count;
                totalErrors += response.result.error_count;
                if (response.result.errors?.length) {
                    const failedIds = new Set(
                        response.result.errors
                            .map((item) => Number(item.student_id ?? 0))
                            .filter((id) => id > 0),
                    );
                    for (const move of group.moves) {
                        if (failedIds.has(move.studentId)) {
                            remainingMoves.push(move);
                        }
                    }
                    const firstErr = response.result.errors[0];
                    if (typeof firstErr?.message === "string") {
                        errorMessages.push(firstErr.message);
                    }
                }
            } catch (err) {
                totalErrors += group.moves.length;
                remainingMoves.push(...group.moves);
                errorMessages.push(getErrorMessage(err, "Failed to move staged students."));
            }
        }

        setStagedMoves(remainingMoves);
        setCommittingMoves(false);

        if (totalMoved > 0 && totalErrors === 0) {
            toast.success(`Committed ${totalMoved} student move(s) cleanly.`);
        } else if (totalMoved > 0 && totalErrors > 0) {
            toast.warning(`Moved ${totalMoved} student(s), ${totalErrors} could not be moved. ${errorMessages[0] ?? ""}`);
        } else if (totalErrors > 0) {
            toast.error(errorMessages[0] || "Failed to commit staged student moves.");
        }

        await loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
        if (totalMoved > 0) {
            onMutated();
        }
    }, [stagedMoves, effectiveAnchorId, inspectedStudentMeta?.studentId, loadWorkspace, onMutated]);

    const handlePromoteSingleWaitlist = React.useCallback(
        async (entry: ParallelWaitlistEntry, targetSection: ParallelSectionItem) => {
            if (!effectiveAnchorId) return;
            setPromotingWaitlistIds((prev) => ({ ...prev, [entry.id]: true }));
            try {
                const res = await postPromoteWaitlist(targetSection.id, { waitlist_id: entry.id });
                toast.success(res.message || `Promoted ${entry.student_name} into Section ${targetSection.section}.`);
                await loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
                onMutated();
            } catch (err) {
                toast.error(getErrorMessage(err, `Could not promote ${entry.student_name}.`));
            } finally {
                setPromotingWaitlistIds((prev) => {
                    const next = { ...prev };
                    delete next[entry.id];
                    return next;
                });
            }
        },
        [effectiveAnchorId, inspectedStudentMeta?.studentId, loadWorkspace, onMutated],
    );

    const autoPromotablePlan = React.useMemo(() => {
        if (!workspace?.waitlist.length || !workspace.sections.length) return [];
        const remainingSeats = new Map<number, number>();
        for (const sec of workspace.sections) {
            const seats = sec.maximum_slots > 0 ? Math.max(0, sec.maximum_slots - sec.students_count) : Number.POSITIVE_INFINITY;
            remainingSeats.set(sec.id, seats);
        }

        const plan: Array<{ entry: ParallelWaitlistEntry; targetSection: ParallelSectionItem }> = [];
        for (const entry of workspace.waitlist) {
            // Prefer preferred_class_id if clear and has seats, else first clear section with open seats
            const orderedSections = [...workspace.sections].sort((a, b) => {
                if (entry.preferred_class_id === a.id) return -1;
                if (entry.preferred_class_id === b.id) return 1;
                return 0;
            });

            const match = orderedSections.find((sec) => {
                const compat = entry.section_compatibility?.[sec.id];
                const seats = remainingSeats.get(sec.id) ?? 0;
                return compat?.status === "clear" && seats > 0;
            });

            if (match) {
                plan.push({ entry, targetSection: match });
                const seats = remainingSeats.get(match.id) ?? 0;
                if (Number.isFinite(seats)) {
                    remainingSeats.set(match.id, seats - 1);
                }
            }
        }
        return plan;
    }, [workspace]);

    const handleBatchAutoPromote = React.useCallback(async () => {
        if (!autoPromotablePlan.length || !effectiveAnchorId) return;
        setBatchPromoting(true);

        const bySection = new Map<number, number[]>();
        for (const item of autoPromotablePlan) {
            const list = bySection.get(item.targetSection.id) ?? [];
            list.push(item.entry.id);
            bySection.set(item.targetSection.id, list);
        }

        let promotedTotal = 0;
        let failedTotal = 0;

        for (const [sectionId, waitlistIds] of bySection.entries()) {
            try {
                const res = await postPromoteWaitlist(sectionId, { waitlist_ids: waitlistIds });
                promotedTotal += Number(res.promoted_count ?? waitlistIds.length);
                failedTotal += Number(res.error_count ?? 0);
            } catch {
                failedTotal += waitlistIds.length;
            }
        }

        setBatchPromoting(false);
        if (promotedTotal > 0) {
            toast.success(
                failedTotal > 0
                    ? `Auto-promoted ${promotedTotal} waitlisted student(s); ${failedTotal} skipped.`
                    : `Auto-promoted ${promotedTotal} clear waitlisted student(s).`,
            );
            await loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
            onMutated();
        } else {
            toast.error("No waitlisted students could be promoted.");
        }
    }, [autoPromotablePlan, effectiveAnchorId, inspectedStudentMeta?.studentId, loadWorkspace, onMutated]);

    const handleEnrollCandidate = React.useCallback(
        async (candidate: StudentSearchCandidate) => {
            if (!activeTargetSectionId || !effectiveAnchorId) return;
            setEnrollingCandidateId(candidate.id);
            try {
                const res = await enrollStudentsInClass(activeTargetSectionId, {
                    student_ids: [candidate.id],
                    allow_transfer_from_sibling: Boolean(candidate.sibling_section_enrollment),
                    force_overload: allowOverrides && (candidate.is_class_full || candidate.would_exceed_capacity),
                    allow_clash_override: allowOverrides && candidate.schedule_conflicts.length > 0,
                    allow_hold_override: allowOverrides && candidate.holds.blocking_count > 0,
                    override_reason: allowOverrides ? "Authorized via Section Studio intake" : null,
                });

                const errCount = res.result.errors?.length ?? 0;
                if (errCount > 0 && res.result.enrolled.length === 0 && res.result.transferred.length === 0) {
                    const firstMsg = typeof res.result.errors[0]?.message === "string" ? res.result.errors[0].message : res.message;
                    toast.error(firstMsg);
                } else {
                    toast.success(res.message);
                    await loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
                    onMutated();
                    const refreshed = await searchClassCandidates(activeTargetSectionId, candidateQuery.trim(), 15);
                    setCandidates(refreshed);
                }
            } catch (err) {
                toast.error(getErrorMessage(err, `Failed to enroll ${candidate.full_name}.`));
            } finally {
                setEnrollingCandidateId(null);
            }
        },
        [activeTargetSectionId, effectiveAnchorId, allowOverrides, inspectedStudentMeta?.studentId, loadWorkspace, onMutated, candidateQuery],
    );

    const handleToggleInlineEditor = React.useCallback(
        (sectionId: number, enrollment: ParallelSectionEnrollment) => {
            const key = `${sectionId}:${enrollment.id}`;
            if (expandedEnrollmentKey === key) {
                setExpandedEnrollmentKey(null);
                return;
            }
            setExpandedEnrollmentKey(key);
            setEditStatus(enrollment.enrollment_status);
            setEditRemarks("");
        },
        [expandedEnrollmentKey],
    );

    const handleSaveInlineEdit = React.useCallback(
        async (sectionId: number, enrollment: ParallelSectionEnrollment) => {
            if (!effectiveAnchorId) return;
            const key = `${sectionId}:${enrollment.id}`;
            setSavingEnrollmentKey(key);
            try {
                const res = await patchClassEnrollment(sectionId, enrollment.id, {
                    enrollment_status: editStatus,
                    remarks: editRemarks.trim() || undefined,
                });
                toast.success(res.message || `Updated ${enrollment.name}.`);
                setExpandedEnrollmentKey(null);
                await loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
                onMutated();
            } catch (err) {
                toast.error(getErrorMessage(err, "Failed to save enrollment edit."));
            } finally {
                setSavingEnrollmentKey(null);
            }
        },
        [effectiveAnchorId, editStatus, editRemarks, inspectedStudentMeta?.studentId, loadWorkspace, onMutated],
    );

    const handleDropEnrollmentSafe = React.useCallback(
        async (sectionId: number, enrollment: ParallelSectionEnrollment) => {
            if (!effectiveAnchorId || enrollment.is_grade_protected) return;
            const key = `${sectionId}:${enrollment.id}`;
            setSavingEnrollmentKey(key);
            try {
                const res = await deleteClassEnrollment(sectionId, enrollment.id, {
                    reason: editRemarks.trim() || "Dropped from Section Studio",
                });
                toast.success(res.message || `Dropped ${enrollment.name}.`);
                setExpandedEnrollmentKey(null);
                setStagedMoves((prev) => prev.filter((item) => item.studentId !== enrollment.student_id));
                await loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
                onMutated();
            } catch (err) {
                toast.error(getErrorMessage(err, "Failed to drop enrollment."));
            } finally {
                setSavingEnrollmentKey(null);
            }
        },
        [effectiveAnchorId, editRemarks, inspectedStudentMeta?.studentId, loadWorkspace, onMutated],
    );

    // Determine visible days for Weekly Strip (Mon–Sat, plus Sunday only if present)
    const visibleDays = React.useMemo(() => {
        const hasSunday =
            (workspace?.sections ?? []).some((sec) => sec.schedules.some((s) => s.day_of_week === "Sunday")) ||
            (workspace?.inspected_student_timetable?.blocks ?? []).some((b) => b.day_of_week === "Sunday");

        return hasSunday ? [...WEEK_DAYS, { key: "Sunday", short: "Sun" } as const] : [...WEEK_DAYS];
    }, [workspace]);

    // Summary string for bottom staging bar
    const stagingDeltaSummary = React.useMemo(() => {
        if (!stagedMoves.length || !workspace) return "";
        const parts: string[] = [];
        for (const sec of workspace.sections) {
            const projected = projectedCountsBySection.get(sec.id) ?? sec.students_count;
            if (projected !== sec.students_count) {
                parts.push(`Sec ${sec.section} ${sec.students_count}→${projected}`);
            }
        }
        return parts.join(" · ");
    }, [stagedMoves.length, workspace, projectedCountsBySection]);

    const overloadStagedCount = React.useMemo(() => stagedMoves.filter((m) => m.forceOverload).length, [stagedMoves]);

    if (!availableClasses.length && !effectiveAnchorId) {
        return (
            <Card className="flex flex-col items-center justify-center gap-3 rounded-2xl border-dashed p-12 text-center">
                <div className="bg-muted flex size-11 items-center justify-center rounded-xl">
                    <Users className="text-muted-foreground size-5" />
                </div>
                <div className="space-y-1">
                    <h3 className="text-foreground text-base font-semibold">No classes available to rebalance</h3>
                    <p className="text-muted-foreground max-w-md text-sm">
                        Adjust your active filters or create a class section first to open the Visual Section &amp; Timetable Rebalancer Studio.
                    </p>
                </div>
            </Card>
        );
    }

    const sections = workspace?.sections ?? [];
    const inspectedTimetable = workspace?.inspected_student_timetable ?? null;

    return (
        <div className="relative space-y-4">
            {/* Studio Top Bar */}
            <div className="bg-card flex flex-col gap-3 rounded-2xl border p-3.5 shadow-2xs">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2.5">
                        <div className="w-full sm:w-80">
                            <Select
                                value={selectedGroupValue}
                                onValueChange={(value) => {
                                    const nextId = Number(value);
                                    if (Number.isFinite(nextId) && nextId > 0) {
                                        onChangeAnchorClass(nextId);
                                    }
                                }}
                            >
                                <SelectTrigger className="h-9 bg-background font-medium">
                                    <SelectValue placeholder="Choose subject group..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {subjectGroups.map((group) => (
                                        <SelectItem key={group.subjectCode} value={String(group.anchorId)}>
                                            <span className="flex items-center gap-2">
                                                <span className="font-mono text-xs font-semibold">{group.subjectCode}</span>
                                                <span className="text-muted-foreground max-w-44 truncate text-xs">{group.subjectTitle}</span>
                                                <span className="text-muted-foreground text-[11px]">
                                                    ({group.sectionCount} {group.sectionCount === 1 ? "sec" : "secs"})
                                                </span>
                                                {group.hasProblem ? (
                                                    <span className="size-1.5 rounded-full bg-rose-500" aria-label="Needs attention" />
                                                ) : null}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {workspace ? (
                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                <Badge variant="secondary" className="font-mono text-[11px]">
                                    {workspace.subject_code}
                                </Badge>
                                <span className="text-muted-foreground hidden sm:inline">
                                    {workspace.school_year} · {semesterLabel(workspace.semester)}
                                </span>
                                <span className="text-foreground tabular-nums font-medium">
                                    {workspace.total_enrolled}/{workspace.total_capacity} total seats
                                </span>
                                <span className="text-muted-foreground">
                                    · {sections.length} {sections.length === 1 ? "section" : "sections"}
                                </span>
                            </div>
                        ) : null}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant={showWeeklyGrid ? "secondary" : "outline"}
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            onClick={() => setShowWeeklyGrid((prev) => !prev)}
                        >
                            <CalendarRange className="size-3.5" />
                            Weekly Grid
                            {showWeeklyGrid ? <ChevronUp className="size-3.5 opacity-70" /> : <ChevronDown className="size-3.5 opacity-70" />}
                        </Button>

                        <Badge
                            variant="outline"
                            className="h-8 gap-1.5 rounded-md border-emerald-500/35 bg-emerald-500/10 px-2.5 text-xs font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            <ShieldCheck className="size-3.5" />
                            Auto-Guard ON
                        </Badge>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            disabled={!effectiveAnchorId || loading || refreshing}
                            onClick={() => {
                                if (effectiveAnchorId) {
                                    void loadWorkspace(effectiveAnchorId, inspectedStudentMeta?.studentId ?? null, { silent: true });
                                }
                            }}
                        >
                            <RefreshCw className={cn("size-3.5", (loading || refreshing) && "animate-spin")} />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Quick Problem Pills */}
                {problemClasses.length > 0 ? (
                    <div className="flex flex-wrap items-center gap-1.5 border-t pt-2.5">
                        <span className="text-muted-foreground mr-1 flex items-center gap-1 text-[11px] font-medium">
                            <AlertTriangle className="size-3 text-rose-500" />
                            Needs attention:
                        </span>
                        {problemClasses.map((cls) => {
                            const overBy = cls.maximum_slots > 0 ? cls.students_count - cls.maximum_slots : 0;
                            const isActive = cls.id === effectiveAnchorId || sections.some((s) => s.id === cls.id);
                            const issueTag =
                                overBy > 0
                                    ? `+${overBy} over`
                                    : cls.schedule_health?.has_conflict
                                      ? "Clash"
                                      : "Room cap";

                            return (
                                <button
                                    key={cls.id}
                                    type="button"
                                    onClick={() => onChangeAnchorClass(cls.id)}
                                    className={cn(
                                        "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors",
                                        isActive
                                            ? "border-rose-500/50 bg-rose-500/15 text-rose-700 dark:text-rose-300"
                                            : "border-rose-500/25 bg-rose-500/5 text-rose-700 hover:bg-rose-500/10 dark:text-rose-300",
                                    )}
                                >
                                    <span className="font-mono">{cls.subject_code}</span>
                                    <span className="opacity-75">Sec {cls.section}</span>
                                    <span className="rounded bg-rose-500/15 px-1 text-[10px] font-semibold">{issueTag}</span>
                                </button>
                            );
                        })}
                    </div>
                ) : null}
            </div>

            {/* Loading / Error State */}
            {loading && !workspace ? (
                <Card className="flex items-center justify-center gap-2.5 rounded-2xl p-12 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" />
                    Loading parallel sections &amp; timetable guards...
                </Card>
            ) : error && !workspace ? (
                <Card className="flex flex-col items-center justify-center gap-3 rounded-2xl border-rose-500/30 bg-rose-500/5 p-10 text-center">
                    <ShieldAlert className="size-6 text-rose-500" />
                    <p className="text-sm font-medium text-rose-700 dark:text-rose-300">{error}</p>
                    {effectiveAnchorId ? (
                        <Button size="sm" variant="outline" onClick={() => void loadWorkspace(effectiveAnchorId, null)}>
                            Retry
                        </Button>
                    ) : null}
                </Card>
            ) : workspace ? (
                <>
                    {/* Collapsible Parallel Sections Weekly Timetable Strip */}
                    {showWeeklyGrid ? (
                        <div className="bg-card overflow-hidden rounded-2xl border shadow-2xs">
                            <div className="bg-muted/25 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-2.5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-foreground flex items-center gap-1.5 text-xs font-semibold">
                                        <Calendar className="text-primary size-3.5" />
                                        Parallel Sections Weekly Strip
                                    </span>
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {sections.map((sec) => {
                                            const theme = sectionThemeById.get(sec.id) ?? SECTION_THEMES[0];
                                            const check = inspectedTimetable?.section_checks?.[sec.id];
                                            return (
                                                <span
                                                    key={sec.id}
                                                    className={cn(
                                                        "inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium",
                                                        theme.badge,
                                                    )}
                                                >
                                                    <span className={cn("size-2 rounded-full", theme.swatch)} />
                                                    Sec {sec.section}
                                                    {check ? (
                                                        <span
                                                            className={cn(
                                                                "ml-0.5 rounded px-1 text-[10px] font-semibold",
                                                                check.status === "clear"
                                                                    ? "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                                                                    : "bg-rose-500/15 text-rose-700 dark:text-rose-300",
                                                            )}
                                                        >
                                                            {check.status === "clear" ? "✓ Clear" : "× Clash"}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Active Student Overlay Indicator */}
                                {inspectedStudentMeta ? (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-dashed border-purple-500/70 bg-purple-500/10 px-2.5 py-0.5 text-[11px] font-medium text-purple-800 dark:text-purple-200">
                                            {inspectLoading ? (
                                                <Loader2 className="size-3 animate-spin" />
                                            ) : (
                                                <Eye className="size-3" />
                                            )}
                                            Overlay: {inspectedStudentMeta.name}
                                            {inspectedTimetable ? (
                                                <span className="opacity-75">
                                                    ({inspectedTimetable.blocks.length} other{" "}
                                                    {inspectedTimetable.blocks.length === 1 ? "slot" : "slots"})
                                                </span>
                                            ) : null}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 px-2 text-[11px]"
                                            onClick={handleClearInspection}
                                        >
                                            <X className="size-3" />
                                            Clear
                                        </Button>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground text-[11px]">
                                        Click <strong>Move ⇄</strong> or <strong>Inspect Timetable</strong> on any student to overlay their personal schedule
                                    </span>
                                )}
                            </div>

                            <div className="overflow-x-auto">
                                <div
                                    className="grid min-w-[760px] divide-x"
                                    style={{ gridTemplateColumns: `repeat(${visibleDays.length}, minmax(0, 1fr))` }}
                                >
                                    {visibleDays.map((day) => {
                                        const daySectionBlocks = sections
                                            .flatMap((section) =>
                                                section.schedules
                                                    .filter((sched) => sched.day_of_week === day.key)
                                                    .map((sched) => ({ section, sched })),
                                            )
                                            .sort((a, b) => (a.sched.start_time ?? "").localeCompare(b.sched.start_time ?? ""));

                                        const dayStudentBlocks = (inspectedTimetable?.blocks ?? [])
                                            .filter((b) => b.day_of_week === day.key)
                                            .sort((a, b) => a.start_time.localeCompare(b.start_time));

                                        return (
                                            <div key={day.key} className="flex min-h-[124px] flex-col">
                                                <div className="bg-muted/15 flex items-center justify-between border-b px-2.5 py-1.5">
                                                    <span className="text-foreground text-[11px] font-semibold tracking-wide uppercase">
                                                        {day.short}
                                                    </span>
                                                    <span className="text-muted-foreground text-[10px] tabular-nums">
                                                        {daySectionBlocks.length > 0 ? `${daySectionBlocks.length}` : "—"}
                                                    </span>
                                                </div>

                                                <div className="flex flex-1 flex-col gap-1.5 p-2">
                                                    {daySectionBlocks.length === 0 && dayStudentBlocks.length === 0 ? (
                                                        <div className="text-muted-foreground/50 flex flex-1 items-center justify-center text-[11px]">
                                                            No slots
                                                        </div>
                                                    ) : null}

                                                    {daySectionBlocks.map(({ section, sched }) => {
                                                        const theme = sectionThemeById.get(section.id) ?? SECTION_THEMES[0];
                                                        const isProblem = sched.has_conflict || sched.is_overcrowded;
                                                        const clashesOverlay = dayStudentBlocks.some((sb) =>
                                                            blocksOverlap(sched.start_time, sched.end_time, sb.start_time, sb.end_time),
                                                        );

                                                        return (
                                                            <div
                                                                key={`${section.id}-${sched.id}`}
                                                                className={cn(
                                                                    "relative overflow-hidden rounded-lg border px-2 py-1.5 text-[11px] transition-all",
                                                                    theme.block,
                                                                    isProblem &&
                                                                        "border-rose-500/80 ring-1 ring-rose-500/30 dark:border-rose-500/70",
                                                                    clashesOverlay &&
                                                                        "ring-2 ring-purple-500/70 dark:ring-purple-400/70",
                                                                )}
                                                            >
                                                                <span
                                                                    className={cn(
                                                                        "absolute inset-y-0 left-0 w-1",
                                                                        isProblem ? "bg-rose-500" : theme.accentBar,
                                                                    )}
                                                                    aria-hidden="true"
                                                                />
                                                                <div className="pl-1.5">
                                                                    <div className="flex items-center justify-between gap-1">
                                                                        <span className="font-semibold">
                                                                            Sec {section.section}{" "}
                                                                            <span className="font-normal opacity-75">
                                                                                {sched.component_type === "lab" ? "Lab" : "Lec"}
                                                                            </span>
                                                                        </span>
                                                                        <span className="font-mono text-[10px] opacity-85 tabular-nums">
                                                                            {shortTime(sched.start_time)}–{shortTime(sched.end_time)}
                                                                        </span>
                                                                    </div>

                                                                    <div className="mt-0.5 flex items-center justify-between gap-1 text-[10px] opacity-85">
                                                                        <span className="truncate">
                                                                            {sched.room_name ?? (isOnlineDeliveryMode(sched.delivery_mode) ? "Online" : "No room")}
                                                                            {sched.room_capacity ? ` (${sched.room_capacity})` : ""}
                                                                        </span>
                                                                        <span className="shrink-0 tabular-nums">
                                                                            {projectedCountsBySection.get(section.id) ?? section.students_count}/{section.maximum_slots}
                                                                        </span>
                                                                    </div>

                                                                    {isProblem ? (
                                                                        <div className="mt-1.5 flex items-center justify-between gap-1 border-t border-rose-500/25 pt-1">
                                                                            <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-rose-700 dark:text-rose-300">
                                                                                <AlertTriangle className="size-2.5" />
                                                                                {sched.has_conflict ? "Conflict" : "Over cap"}
                                                                            </span>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => onOpenScheduleInLedger(section.id)}
                                                                                className="rounded bg-rose-600 px-1.5 py-0.5 text-[10px] font-semibold text-white transition-colors hover:bg-rose-700"
                                                                            >
                                                                                Fix in Studio
                                                                            </button>
                                                                        </div>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                        );
                                                    })}

                                                    {/* Personal Student Timetable Overlay (Dotted Purple) */}
                                                    {dayStudentBlocks.map((sBlock, idx) => (
                                                        <div
                                                            key={`overlay-${sBlock.subject_code}-${sBlock.start_time}-${idx}`}
                                                            className="rounded-lg border-2 border-dashed border-purple-500/80 bg-purple-50/90 px-2 py-1.5 text-[11px] text-purple-950 dark:border-purple-400/80 dark:bg-purple-950/45 dark:text-purple-100"
                                                        >
                                                            <div className="flex items-center justify-between gap-1">
                                                                <span className="truncate font-mono text-[10px] font-bold">
                                                                    {sBlock.subject_code}
                                                                    {sBlock.section ? `-${sBlock.section}` : ""}
                                                                </span>
                                                                <span className="shrink-0 font-mono text-[10px] tabular-nums">
                                                                    {shortTime(sBlock.start_time)}–{shortTime(sBlock.end_time)}
                                                                </span>
                                                            </div>
                                                            {sBlock.room_name ? (
                                                                <p className="mt-0.5 truncate text-[10px] opacity-80">{sBlock.room_name}</p>
                                                            ) : null}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    ) : null}

                    {/* Main Split Workspace */}
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                        {/* Left Rail — Intake & Waitlist Pool */}
                        <aside className="bg-card flex w-full shrink-0 flex-col overflow-hidden rounded-2xl border shadow-2xs lg:w-[320px] xl:w-[340px]">
                            <Tabs
                                value={leftTab}
                                onValueChange={(val) => setLeftTab(val as "waitlist" | "search")}
                                className="flex flex-col"
                            >
                                <div className="bg-muted/20 border-b px-3 pt-3 pb-2.5">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-foreground text-xs font-semibold tracking-tight">
                                            Intake &amp; Waitlist Pool
                                        </span>
                                        <Badge variant="outline" className="text-[10px] font-medium">
                                            FIFO Guarded
                                        </Badge>
                                    </div>
                                    <TabsList className="grid h-8 w-full grid-cols-2">
                                        <TabsTrigger value="waitlist" className="text-xs">
                                            Waitlist ({workspace.waitlist.length})
                                        </TabsTrigger>
                                        <TabsTrigger value="search" className="text-xs">
                                            Search &amp; Add
                                        </TabsTrigger>
                                    </TabsList>
                                </div>

                                {/* Waitlist Tab */}
                                <TabsContent value="waitlist" className="m-0 flex flex-col">
                                    <ScrollArea className="h-[520px]">
                                        <div className="space-y-2.5 p-3">
                                            {workspace.waitlist.length === 0 ? (
                                                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-10 text-center">
                                                    <CheckCircle2 className="size-5 text-emerald-500" />
                                                    <p className="text-foreground text-xs font-semibold">Waitlist is empty</p>
                                                    <p className="text-muted-foreground text-[11px]">
                                                        Use <strong>Search &amp; Add</strong> to enroll students directly into any open section.
                                                    </p>
                                                </div>
                                            ) : (
                                                workspace.waitlist.map((entry, idx) => {
                                                    const pos = entry.position ?? idx + 1;
                                                    const courseLabel = getWaitlistCourse(entry);
                                                    const isPromoting = Boolean(promotingWaitlistIds[entry.id]);
                                                    const clearSections = sections.filter(
                                                        (sec) => entry.section_compatibility?.[sec.id]?.status === "clear",
                                                    );
                                                    const isInspected = inspectedStudentMeta?.studentId === entry.student_id;

                                                    return (
                                                        <div
                                                            key={entry.id}
                                                            className={cn(
                                                                "bg-background rounded-xl border p-2.5 transition-colors",
                                                                isInspected && "border-purple-500/60 ring-1 ring-purple-500/25",
                                                            )}
                                                        >
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="min-w-0">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="h-4 px-1.5 font-mono text-[10px] font-bold"
                                                                        >
                                                                            #{pos}
                                                                        </Badge>
                                                                        <p className="text-foreground truncate text-xs font-semibold">
                                                                            {entry.student_name}
                                                                        </p>
                                                                    </div>
                                                                    <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                                                        {entry.student_number ?? "No ID"}
                                                                        {courseLabel ? ` · ${courseLabel}` : ""}
                                                                    </p>
                                                                </div>

                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-6 px-1.5 text-[10px]"
                                                                    title="Overlay student's weekly timetable"
                                                                    onClick={() =>
                                                                        handleInspectStudent(
                                                                            {
                                                                                studentId: entry.student_id,
                                                                                name: entry.student_name,
                                                                                studentNumber: entry.student_number,
                                                                                source: "waitlist",
                                                                            },
                                                                            true,
                                                                        )
                                                                    }
                                                                >
                                                                    <Eye className="size-3" />
                                                                </Button>
                                                            </div>

                                                            {/* Per-section live compatibility pills */}
                                                            <div className="mt-2 flex flex-wrap gap-1">
                                                                {sections.map((sec) => {
                                                                    const compat = entry.section_compatibility?.[sec.id];
                                                                    const status = compat?.status ?? "clear";
                                                                    return (
                                                                        <span
                                                                            key={sec.id}
                                                                            title={compat?.reason ?? undefined}
                                                                            className={cn(
                                                                                "inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-medium",
                                                                                status === "clear" &&
                                                                                    "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                                                                                status === "full" &&
                                                                                    "border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                                                                                status === "clash" &&
                                                                                    "border-rose-500/35 bg-rose-500/10 text-rose-700 dark:text-rose-300",
                                                                            )}
                                                                        >
                                                                            Sec {sec.section}:{" "}
                                                                            {status === "clear"
                                                                                ? "✓ Clear"
                                                                                : status === "full"
                                                                                  ? "Full"
                                                                                  : "× Clash"}
                                                                        </span>
                                                                    );
                                                                })}
                                                            </div>

                                                            {/* 1-click promote actions */}
                                                            {canUpdate && clearSections.length > 0 ? (
                                                                <div className="mt-2 flex flex-wrap gap-1.5 border-t pt-2">
                                                                    {clearSections.map((targetSec) => (
                                                                        <Button
                                                                            key={targetSec.id}
                                                                            type="button"
                                                                            size="sm"
                                                                            variant={
                                                                                entry.preferred_class_id === targetSec.id
                                                                                    ? "default"
                                                                                    : "outline"
                                                                            }
                                                                            className="h-6 gap-1 px-2 text-[11px]"
                                                                            disabled={isPromoting || batchPromoting}
                                                                            onClick={() => void handlePromoteSingleWaitlist(entry, targetSec)}
                                                                        >
                                                                            {isPromoting ? (
                                                                                <Loader2 className="size-3 animate-spin" />
                                                                            ) : (
                                                                                <ArrowRight className="size-3" />
                                                                            )}
                                                                            Sec {targetSec.section}
                                                                        </Button>
                                                                    ))}
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    );
                                                })
                                            )}
                                        </div>
                                    </ScrollArea>

                                    <div className="bg-muted/20 border-t p-3">
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="w-full gap-1.5 text-xs"
                                            disabled={!canUpdate || autoPromotablePlan.length === 0 || batchPromoting}
                                            onClick={() => void handleBatchAutoPromote()}
                                        >
                                            {batchPromoting ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Sparkles className="size-3.5" />
                                            )}
                                            Auto-Promote Clear Waitlisted
                                            {autoPromotablePlan.length > 0 ? ` (${autoPromotablePlan.length})` : ""}
                                        </Button>
                                    </div>
                                </TabsContent>

                                {/* Search & Add Tab */}
                                <TabsContent value="search" className="m-0 flex flex-col">
                                    <div className="space-y-2.5 border-b p-3">
                                        <div className="space-y-1">
                                            <label className="text-muted-foreground text-[11px] font-medium">Target Section</label>
                                            <Select
                                                value={activeTargetSectionId ? String(activeTargetSectionId) : ""}
                                                onValueChange={(val) => setActiveTargetSectionId(Number(val))}
                                            >
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue placeholder="Choose target section" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {sections.map((sec) => (
                                                        <SelectItem key={sec.id} value={String(sec.id)}>
                                                            Section {sec.section} ({sec.students_count}/{sec.maximum_slots})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="relative">
                                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                            <Input
                                                value={candidateQuery}
                                                onChange={(e) => setCandidateQuery(e.target.value)}
                                                placeholder="Search student name, ID, or LRN..."
                                                className="h-8 pl-8 text-xs"
                                            />
                                        </div>

                                        {canUpdate ? (
                                            <label className="text-muted-foreground flex cursor-pointer items-center gap-2 text-[11px]">
                                                <input
                                                    type="checkbox"
                                                    checked={allowOverrides}
                                                    onChange={(e) => setAllowOverrides(e.target.checked)}
                                                    className="accent-primary rounded"
                                                />
                                                Allow guard overrides (clash / hold / overload)
                                            </label>
                                        ) : null}
                                    </div>

                                    <ScrollArea className="h-[445px]">
                                        <div className="space-y-2 p-3">
                                            {searchingCandidates ? (
                                                <div className="text-muted-foreground flex items-center justify-center gap-2 py-8 text-xs">
                                                    <Loader2 className="size-3.5 animate-spin" />
                                                    Checking roster &amp; timetable guards...
                                                </div>
                                            ) : candidates.length === 0 ? (
                                                <div className="text-muted-foreground rounded-xl border border-dashed px-4 py-8 text-center text-xs">
                                                    {candidateQuery.trim()
                                                        ? "No matching students found."
                                                        : "Type a name or student ID to search eligible students."}
                                                </div>
                                            ) : (
                                                candidates.map((candidate) => {
                                                    const isBusy = enrollingCandidateId === candidate.id;
                                                    const hasHold = candidate.holds.blocking_count > 0;
                                                    const hasClash = candidate.schedule_conflicts.length > 0;
                                                    const needsOverload = candidate.is_class_full || candidate.would_exceed_capacity;
                                                    const isBlocked = (hasHold || hasClash || needsOverload) && !allowOverrides;
                                                    const firstConflict =
                                                        candidate.schedule_conflicts[0]?.label ??
                                                        (candidate.schedule_conflicts[0]?.subject_code
                                                            ? `${candidate.schedule_conflicts[0].subject_code} ${candidate.schedule_conflicts[0].day ?? ""}`
                                                            : "Schedule conflict");

                                                    return (
                                                        <div key={candidate.id} className="bg-background rounded-xl border p-2.5">
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="min-w-0">
                                                                    <p className="text-foreground truncate text-xs font-semibold">
                                                                        {candidate.full_name}
                                                                    </p>
                                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                                        {candidate.student_id ?? "No ID"}
                                                                        {candidate.course ? ` · ${candidate.course}` : ""}
                                                                        {candidate.academic_year ? ` Y${candidate.academic_year}` : ""}
                                                                    </p>
                                                                </div>

                                                                {canUpdate && !candidate.already_enrolled_here ? (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant={isBlocked ? "outline" : "default"}
                                                                        className="h-6 shrink-0 gap-1 px-2 text-[11px]"
                                                                        disabled={isBlocked || isBusy}
                                                                        onClick={() => void handleEnrollCandidate(candidate)}
                                                                    >
                                                                        {isBusy ? (
                                                                            <Loader2 className="size-3 animate-spin" />
                                                                        ) : (
                                                                            <UserPlus className="size-3" />
                                                                        )}
                                                                        {candidate.sibling_section_enrollment ? "Transfer" : "Enroll"}
                                                                    </Button>
                                                                ) : null}
                                                            </div>

                                                            <div className="mt-2 flex flex-wrap gap-1">
                                                                {candidate.already_enrolled_here ? (
                                                                    <Badge variant="secondary" className="text-[10px]">
                                                                        Already enrolled
                                                                    </Badge>
                                                                ) : null}
                                                                {candidate.sibling_section_enrollment ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-sky-500/35 bg-sky-500/10 text-[10px] text-sky-700 dark:text-sky-300"
                                                                    >
                                                                        In Sec {candidate.sibling_section_enrollment.section}
                                                                    </Badge>
                                                                ) : null}
                                                                {hasHold ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-rose-500/35 bg-rose-500/10 text-[10px] text-rose-700 dark:text-rose-300"
                                                                    >
                                                                        Hold: {candidate.holds.offices.join(", ") || `${candidate.holds.blocking_count}`}
                                                                    </Badge>
                                                                ) : null}
                                                                {hasClash ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-rose-500/35 bg-rose-500/10 text-[10px] text-rose-700 dark:text-rose-300"
                                                                    >
                                                                        Clash: {firstConflict}
                                                                    </Badge>
                                                                ) : null}
                                                                {needsOverload ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-amber-500/35 bg-amber-500/10 text-[10px] text-amber-700 dark:text-amber-300"
                                                                    >
                                                                        Section full
                                                                    </Badge>
                                                                ) : null}
                                                                {!candidate.already_enrolled_here && !hasHold && !hasClash && !needsOverload ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-emerald-500/35 bg-emerald-500/10 text-[10px] text-emerald-700 dark:text-emerald-300"
                                                                    >
                                                                        ✓ Clear
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    );
                                                })
                                            )}
                                        </div>
                                    </ScrollArea>
                                </TabsContent>
                            </Tabs>
                        </aside>

                        {/* Right Multi-Column Parallel Section Lanes */}
                        <div className="min-w-0 flex-1">
                            <div
                                className={cn(
                                    sections.length >= 4
                                        ? "flex gap-3.5 overflow-x-auto pb-2"
                                        : sections.length === 3
                                          ? "grid grid-cols-1 gap-3.5 md:grid-cols-2 2xl:grid-cols-3"
                                          : sections.length === 2
                                            ? "grid grid-cols-1 gap-3.5 xl:grid-cols-2"
                                            : "grid grid-cols-1 gap-3.5",
                                )}
                            >
                                {sections.map((section) => {
                                    const theme = sectionThemeById.get(section.id) ?? SECTION_THEMES[0];
                                    const projectedCount = projectedCountsBySection.get(section.id) ?? section.students_count;
                                    const maxSlots = section.maximum_slots;
                                    const overBy = maxSlots > 0 ? projectedCount - maxSlots : 0;
                                    const openSeats = maxSlots > 0 ? Math.max(0, maxSlots - projectedCount) : null;
                                    const fillRatio = maxSlots > 0 ? projectedCount / maxSlots : 0;
                                    const isLowFill = maxSlots > 0 && projectedCount > 0 && fillRatio < 0.5;
                                    const gaugePct = maxSlots > 0 ? Math.min(100, (projectedCount / maxSlots) * 100) : 0;

                                    const schedulePills = groupSchedulesIntoPills(section.schedules);
                                    const health = section.schedule_health;
                                    const laneQuery = (laneQueries[section.id] ?? "").trim().toLowerCase();

                                    const incomingMoves = stagedMoves.filter((m) => m.toSectionId === section.id);
                                    const outgoingMoveByStudentId = new Map(
                                        stagedMoves
                                            .filter((m) => m.fromSectionId === section.id)
                                            .map((m) => [m.studentId, m]),
                                    );

                                    const filteredEnrollments = section.enrollments.filter((enr) => {
                                        if (!laneQuery) return true;
                                        return (
                                            enr.name.toLowerCase().includes(laneQuery) ||
                                            String(enr.student_number ?? "").toLowerCase().includes(laneQuery) ||
                                            (enr.course ?? "").toLowerCase().includes(laneQuery)
                                        );
                                    });

                                    // Drop / Move Shield evaluation for this target lane
                                    const isMoveTargetCandidate =
                                        selectedMoveStudent !== null && selectedMoveStudent.fromSectionId !== section.id;
                                    const sectionCheck =
                                        inspectedTimetable && inspectedTimetable.student_id === selectedMoveStudent?.studentId
                                            ? inspectedTimetable.section_checks?.[section.id]
                                            : null;
                                    const isTargetClashing = sectionCheck?.status === "clash";
                                    const isTargetFull = maxSlots > 0 && projectedCount >= maxSlots;
                                    const isDragOver = dragOverSectionId === section.id;

                                    const mergeCandidate = section.merge_candidate;
                                    const movableForMergeCount = section.enrollments.filter((e) => !e.is_grade_protected).length;

                                    return (
                                        <section
                                            key={section.id}
                                            onDragOver={(e) => {
                                                if (!canUpdate || !selectedMoveStudent || selectedMoveStudent.fromSectionId === section.id) {
                                                    return;
                                                }
                                                e.preventDefault();
                                                if (dragOverSectionId !== section.id) {
                                                    setDragOverSectionId(section.id);
                                                }
                                            }}
                                            onDragLeave={() => {
                                                if (dragOverSectionId === section.id) {
                                                    setDragOverSectionId(null);
                                                }
                                            }}
                                            onDrop={(e) => {
                                                e.preventDefault();
                                                setDragOverSectionId(null);
                                                if (!canUpdate || !selectedMoveStudent || selectedMoveStudent.fromSectionId === section.id) {
                                                    return;
                                                }
                                                if (isTargetClashing) {
                                                    toast.error(
                                                        `Blocked: ${selectedMoveStudent.name} clashes with Section ${section.section}.`,
                                                    );
                                                    return;
                                                }
                                                if (isTargetFull) {
                                                    toast.warning(
                                                        `Section ${section.section} is full. Click "Stage w/ Overload" to confirm override.`,
                                                    );
                                                    return;
                                                }
                                                handleStageMove(selectedMoveStudent, section, false);
                                            }}
                                            className={cn(
                                                "bg-card flex flex-col overflow-hidden rounded-2xl border shadow-2xs transition-all",
                                                sections.length >= 4 && "w-[350px] min-w-[330px] shrink-0",
                                                isDragOver &&
                                                    !isTargetClashing &&
                                                    !isTargetFull &&
                                                    "border-emerald-500 ring-2 ring-emerald-500/30",
                                                isDragOver && isTargetClashing && "border-rose-500 ring-2 ring-rose-500/30",
                                            )}
                                        >
                                            {/* Colored Section Top Accent */}
                                            <div className={cn("h-1.5 w-full", theme.accentBar)} />

                                            {/* Lane Header */}
                                            <div className="space-y-2.5 border-b p-3.5">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <h3 className="text-foreground text-sm font-bold tracking-tight">
                                                                Section {section.section}
                                                            </h3>
                                                            <span className="text-foreground font-mono text-xs font-semibold tabular-nums">
                                                                {projectedCount !== section.students_count ? (
                                                                    <>
                                                                        <span className="text-muted-foreground line-through">
                                                                            {section.students_count}
                                                                        </span>{" "}
                                                                        <span className="text-primary">{projectedCount}</span>
                                                                    </>
                                                                ) : (
                                                                    projectedCount
                                                                )}
                                                                <span className="text-muted-foreground font-normal"> / {maxSlots}</span>
                                                            </span>
                                                        </div>

                                                        <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                                            <span className="inline-flex items-center gap-1 truncate">
                                                                <UserCheck className="size-3 shrink-0" />
                                                                {section.faculty?.name ?? "Unassigned faculty"}
                                                            </span>
                                                            {health ? (
                                                                <>
                                                                    <span>·</span>
                                                                    <span
                                                                        className={cn(
                                                                            "inline-flex items-center gap-1 font-medium",
                                                                            health.status === "published"
                                                                                ? "text-emerald-700 dark:text-emerald-400"
                                                                                : health.status === "draft"
                                                                                  ? "text-amber-700 dark:text-amber-400"
                                                                                  : "text-muted-foreground",
                                                                        )}
                                                                    >
                                                                        {formatHours(health.scheduled_hours)}/{formatHours(health.required_hours)}h ·{" "}
                                                                        {health.status === "published"
                                                                            ? "Published"
                                                                            : health.status === "draft"
                                                                              ? "Draft"
                                                                              : "Unscheduled"}
                                                                    </span>
                                                                </>
                                                            ) : null}
                                                        </div>
                                                    </div>

                                                    {/* Capacity Status Pill */}
                                                    {overBy > 0 ? (
                                                        <Badge className="shrink-0 border-rose-500/40 bg-rose-500/15 text-[11px] font-semibold text-rose-700 hover:bg-rose-500/20 dark:text-rose-300">
                                                            +{overBy} OVER
                                                        </Badge>
                                                    ) : isLowFill ? (
                                                        <Badge className="shrink-0 border-amber-500/40 bg-amber-500/15 text-[11px] font-semibold text-amber-700 hover:bg-amber-500/20 dark:text-amber-300">
                                                            Low fill
                                                        </Badge>
                                                    ) : openSeats !== null && openSeats > 0 ? (
                                                        <Badge className="shrink-0 border-emerald-500/40 bg-emerald-500/15 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300">
                                                            {openSeats} open
                                                        </Badge>
                                                    ) : maxSlots > 0 ? (
                                                        <Badge className="shrink-0 border-amber-500/40 bg-amber-500/15 text-[11px] font-semibold text-amber-700 hover:bg-amber-500/20 dark:text-amber-300">
                                                            Full
                                                        </Badge>
                                                    ) : null}
                                                </div>

                                                {/* Compact Schedule & Room Pills + [Edit Slot] */}
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {schedulePills.length === 0 ? (
                                                        <span className="text-muted-foreground rounded-md border border-dashed px-2 py-0.5 text-[11px]">
                                                            Unscheduled
                                                        </span>
                                                    ) : (
                                                        schedulePills.map((pill) => (
                                                            <span
                                                                key={pill.key}
                                                                className={cn(
                                                                    "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px]",
                                                                    pill.hasConflict || pill.isOvercrowded
                                                                        ? "border-rose-500/45 bg-rose-500/10 font-medium text-rose-700 dark:text-rose-300"
                                                                        : "bg-muted/40 text-foreground border-border/80",
                                                                )}
                                                            >
                                                                {pill.hasConflict || pill.isOvercrowded ? (
                                                                    <AlertTriangle className="size-3 shrink-0" />
                                                                ) : (
                                                                    <Clock className="text-muted-foreground size-3 shrink-0" />
                                                                )}
                                                                <span className="font-mono font-semibold">{pill.daysLabel}</span>
                                                                <span className="tabular-nums">{pill.timeLabel}</span>
                                                                <span className="opacity-75">{pill.componentLabel}</span>
                                                                {pill.roomLabel ? (
                                                                    <>
                                                                        <span className="opacity-50">·</span>
                                                                        <span className="inline-flex items-center gap-0.5">
                                                                            <MapPin className="size-2.5 opacity-70" />
                                                                            {pill.roomLabel}
                                                                            {pill.roomCapacity ? ` (${pill.roomCapacity} cap)` : ""}
                                                                        </span>
                                                                    </>
                                                                ) : null}
                                                            </span>
                                                        ))
                                                    )}

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="ml-auto h-6 px-2 text-[11px] font-medium"
                                                        onClick={() => onOpenScheduleInLedger(section.id)}
                                                    >
                                                        Edit Slot
                                                    </Button>
                                                </div>

                                                {/* Capacity Gauge Bar */}
                                                <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                                    <div
                                                        className={cn(
                                                            "h-full rounded-full transition-all duration-300",
                                                            overBy > 0
                                                                ? "bg-rose-500"
                                                                : isTargetFull
                                                                  ? "bg-amber-500"
                                                                  : isLowFill
                                                                    ? "bg-amber-400"
                                                                    : "bg-emerald-500",
                                                        )}
                                                        style={{ width: `${gaugePct}%` }}
                                                    />
                                                </div>

                                                {/* Per-lane Roster Filter */}
                                                <div className="relative">
                                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                                    <Input
                                                        value={laneQueries[section.id] ?? ""}
                                                        onChange={(e) =>
                                                            setLaneQueries((prev) => ({ ...prev, [section.id]: e.target.value }))
                                                        }
                                                        placeholder={`Filter ${section.enrollments.length} students...`}
                                                        className="h-8 pl-8 text-xs"
                                                    />
                                                </div>
                                            </div>

                                            {/* Live Drop / Move Shield Banner */}
                                            {isMoveTargetCandidate && selectedMoveStudent ? (
                                                <div className="border-b p-2.5">
                                                    {inspectLoading && !sectionCheck ? (
                                                        <div className="bg-muted/40 text-muted-foreground flex items-center gap-2 rounded-xl border border-dashed px-3 py-2 text-xs">
                                                            <Loader2 className="size-3.5 animate-spin" />
                                                            Checking {selectedMoveStudent.name}&apos;s timetable against Section {section.section}...
                                                        </div>
                                                    ) : isTargetClashing ? (
                                                        <div className="rounded-xl border border-rose-500/45 bg-rose-500/10 px-3 py-2 text-xs text-rose-800 dark:text-rose-200">
                                                            <div className="flex items-start gap-2">
                                                                <ShieldAlert className="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                                                                <div className="min-w-0">
                                                                    <p className="font-semibold">Student Schedule Clash</p>
                                                                    <p className="mt-0.5 text-[11px] opacity-90">
                                                                        {sectionCheck?.conflicts[0] ??
                                                                            "Overlaps with another enrolled class in this term."}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ) : isTargetFull ? (
                                                        <div className="rounded-xl border border-amber-500/45 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
                                                            <div className="flex items-center justify-between gap-2">
                                                                <div className="flex items-start gap-2">
                                                                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                                                    <div>
                                                                        <p className="font-semibold">
                                                                            Full ({projectedCount}/{maxSlots}) — requires overload override
                                                                        </p>
                                                                        <p className="text-[11px] opacity-85">
                                                                            0 timetable clashes for {selectedMoveStudent.name}.
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                {canUpdate ? (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        className="h-7 shrink-0 border-amber-500/50 bg-background px-2 text-[11px]"
                                                                        onClick={() => handleStageMove(selectedMoveStudent, section, true)}
                                                                    >
                                                                        Stage Overload
                                                                    </Button>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="rounded-xl border-2 border-dashed border-emerald-500/55 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-900 dark:text-emerald-200">
                                                            <div className="flex items-center justify-between gap-2">
                                                                <div className="min-w-0">
                                                                    <p className="font-semibold">
                                                                        ✓ Move {selectedMoveStudent.name} to Section {section.section}
                                                                    </p>
                                                                    <p className="mt-0.5 text-[11px] opacity-85">
                                                                        0 timetable clashes · Seat {projectedCount + 1}/{maxSlots || "∞"}
                                                                    </p>
                                                                </div>
                                                                {canUpdate ? (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        className="h-7 shrink-0 bg-emerald-600 px-2.5 text-[11px] text-white hover:bg-emerald-700"
                                                                        onClick={() => handleStageMove(selectedMoveStudent, section, false)}
                                                                    >
                                                                        Stage Move
                                                                    </Button>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            ) : null}

                                            {/* Student Cards List */}
                                            <ScrollArea className="h-[420px]">
                                                <div className="space-y-2 p-3">
                                                    {/* Incoming staged students */}
                                                    {incomingMoves.map((move) => (
                                                        <div
                                                            key={`incoming-${move.studentId}`}
                                                            className="flex items-center justify-between gap-2 rounded-xl border border-dashed border-emerald-500/60 bg-emerald-500/10 px-2.5 py-2 text-xs"
                                                        >
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <Badge className="h-4 border-emerald-500/40 bg-emerald-600 px-1.5 text-[10px] text-white">
                                                                        Incoming ← Sec {move.fromSectionLabel}
                                                                    </Badge>
                                                                    {move.forceOverload ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="h-4 border-amber-500/50 px-1 text-[10px] text-amber-700 dark:text-amber-300"
                                                                        >
                                                                            Overload
                                                                        </Badge>
                                                                    ) : null}
                                                                </div>
                                                                <p className="text-foreground mt-1 truncate font-semibold">{move.studentName}</p>
                                                                <p className="text-muted-foreground truncate text-[11px]">
                                                                    {move.studentNumber ?? "No ID"}
                                                                    {move.course ? ` · ${move.course}` : ""}
                                                                </p>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7 shrink-0"
                                                                aria-label={`Remove staged move for ${move.studentName}`}
                                                                onClick={() => handleUnstageStudent(move.studentId)}
                                                            >
                                                                <X className="size-3.5" />
                                                            </Button>
                                                        </div>
                                                    ))}

                                                    {filteredEnrollments.length === 0 && incomingMoves.length === 0 ? (
                                                        <div className="text-muted-foreground rounded-xl border border-dashed px-4 py-10 text-center text-xs">
                                                            {laneQuery ? "No students match this filter." : "No enrolled students in this section."}
                                                        </div>
                                                    ) : null}

                                                    {filteredEnrollments.map((enrollment) => {
                                                        const cardKey = `${section.id}:${enrollment.id}`;
                                                        const isExpanded = expandedEnrollmentKey === cardKey;
                                                        const isSelectedForMove = selectedMoveStudent?.studentId === enrollment.student_id;
                                                        const outgoingMove = outgoingMoveByStudentId.get(enrollment.student_id);
                                                        const statusMeta = statusBadgeStyle(enrollment.enrollment_status);
                                                        const isSavingThis = savingEnrollmentKey === cardKey;
                                                        const canDrag = canUpdate && !enrollment.is_grade_protected && !outgoingMove;

                                                        return (
                                                            <div
                                                                key={enrollment.id}
                                                                draggable={canDrag}
                                                                onDragStart={(e) => {
                                                                    if (!canDrag) return;
                                                                    e.dataTransfer.effectAllowed = "move";
                                                                    e.dataTransfer.setData("text/plain", String(enrollment.student_id));
                                                                    setSelectedMoveStudent({
                                                                        studentId: enrollment.student_id,
                                                                        enrollmentId: enrollment.id,
                                                                        name: enrollment.name,
                                                                        studentNumber: enrollment.student_number,
                                                                        course: enrollment.course,
                                                                        fromSectionId: section.id,
                                                                        fromSectionLabel: section.section,
                                                                    });
                                                                    handleInspectStudent({
                                                                        studentId: enrollment.student_id,
                                                                        name: enrollment.name,
                                                                        studentNumber: enrollment.student_number,
                                                                        source: "move",
                                                                    });
                                                                }}
                                                                onDragEnd={() => setDragOverSectionId(null)}
                                                                className={cn(
                                                                    "bg-background rounded-xl border transition-all",
                                                                    canDrag && "cursor-grab active:cursor-grabbing",
                                                                    isSelectedForMove &&
                                                                        "border-purple-500 bg-purple-500/[0.04] ring-1 ring-purple-500/35",
                                                                    outgoingMove && "border-dashed opacity-55",
                                                                )}
                                                            >
                                                                <div
                                                                    role="button"
                                                                    tabIndex={0}
                                                                    onClick={() => handleToggleInlineEditor(section.id, enrollment)}
                                                                    onKeyDown={(e) => {
                                                                        if (e.key === "Enter" || e.key === " ") {
                                                                            e.preventDefault();
                                                                            handleToggleInlineEditor(section.id, enrollment);
                                                                        }
                                                                    }}
                                                                    className="flex items-start justify-between gap-2 p-2.5 text-left"
                                                                >
                                                                    <div className="flex min-w-0 items-start gap-1.5">
                                                                        {canDrag ? (
                                                                            <GripVertical className="text-muted-foreground/50 mt-0.5 size-3.5 shrink-0" />
                                                                        ) : null}
                                                                        <div className="min-w-0">
                                                                            <div className="flex flex-wrap items-center gap-1.5">
                                                                                <span className="text-foreground truncate text-xs font-semibold">
                                                                                    {enrollment.name}
                                                                                </span>
                                                                                {enrollment.is_grade_protected ? (
                                                                                    <span
                                                                                        title="Grade-protected: has recorded, verified, or finalized grades"
                                                                                        className="inline-flex items-center gap-0.5 rounded bg-blue-500/15 px-1 py-0.5 text-[10px] font-semibold text-blue-700 dark:text-blue-300"
                                                                                    >
                                                                                        <Lock className="size-2.5" />
                                                                                    </span>
                                                                                ) : null}
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className={cn("h-4 px-1.5 text-[10px]", statusMeta.className)}
                                                                                >
                                                                                    {statusMeta.label}
                                                                                </Badge>
                                                                            </div>
                                                                            <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                                                                {enrollment.student_number ?? "No ID"}
                                                                                {enrollment.course ? ` · ${enrollment.course}` : ""}
                                                                                {enrollment.academic_year ? ` Y${enrollment.academic_year}` : ""}
                                                                            </p>
                                                                            {outgoingMove ? (
                                                                                <div className="mt-1 flex items-center gap-1.5">
                                                                                    <Badge
                                                                                        variant="outline"
                                                                                        className="h-4 border-primary/40 bg-primary/10 px-1.5 text-[10px] text-primary"
                                                                                    >
                                                                                        Staged → Sec {outgoingMove.toSectionLabel}
                                                                                    </Badge>
                                                                                    <button
                                                                                        type="button"
                                                                                        className="text-muted-foreground hover:text-foreground text-[10px] underline"
                                                                                        onClick={(e) => {
                                                                                            e.stopPropagation();
                                                                                            handleUnstageStudent(enrollment.student_id);
                                                                                        }}
                                                                                    >
                                                                                        Cancel
                                                                                    </button>
                                                                                </div>
                                                                            ) : null}
                                                                        </div>
                                                                    </div>

                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant={isSelectedForMove ? "default" : "outline"}
                                                                        className="h-6 shrink-0 gap-1 px-2 text-[11px]"
                                                                        disabled={!canUpdate || enrollment.is_grade_protected || Boolean(outgoingMove)}
                                                                        title={
                                                                            enrollment.is_grade_protected
                                                                                ? "Grade-protected students cannot be moved"
                                                                                : "Inspect timetable & move student to another section"
                                                                        }
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            handleSelectStudentForMove(section, enrollment);
                                                                        }}
                                                                    >
                                                                        <ArrowLeftRight className="size-3" />
                                                                        Move
                                                                    </Button>
                                                                </div>

                                                                {/* Inline Enrollee Quick-Editor */}
                                                                {isExpanded ? (
                                                                    <div className="bg-muted/25 space-y-2.5 border-t px-2.5 py-2.5">
                                                                        <div className="flex flex-wrap gap-1">
                                                                            {ENROLLMENT_STATUS_OPTIONS.map((opt) => (
                                                                                <button
                                                                                    key={opt.value}
                                                                                    type="button"
                                                                                    disabled={!canUpdate || isSavingThis}
                                                                                    onClick={() => setEditStatus(opt.value)}
                                                                                    className={cn(
                                                                                        "rounded-md border px-2 py-0.5 text-[10px] font-medium transition-colors",
                                                                                        editStatus === opt.value
                                                                                            ? "border-primary bg-primary text-primary-foreground"
                                                                                            : "bg-background text-muted-foreground hover:text-foreground",
                                                                                    )}
                                                                                >
                                                                                    {opt.label}
                                                                                </button>
                                                                            ))}
                                                                        </div>

                                                                        <Input
                                                                            value={editRemarks}
                                                                            onChange={(e) => setEditRemarks(e.target.value)}
                                                                            placeholder="Optional remarks or status note..."
                                                                            disabled={!canUpdate || isSavingThis}
                                                                            className="h-7 bg-background text-xs"
                                                                        />

                                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                                            {canUpdate ? (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    className="h-7 gap-1 px-2.5 text-[11px]"
                                                                                    disabled={isSavingThis}
                                                                                    onClick={() => void handleSaveInlineEdit(section.id, enrollment)}
                                                                                >
                                                                                    {isSavingThis ? (
                                                                                        <Loader2 className="size-3 animate-spin" />
                                                                                    ) : (
                                                                                        <Check className="size-3" />
                                                                                    )}
                                                                                    Save Edit
                                                                                </Button>
                                                                            ) : null}

                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="h-7 gap-1 px-2 text-[11px]"
                                                                                onClick={() =>
                                                                                    handleInspectStudent(
                                                                                        {
                                                                                            studentId: enrollment.student_id,
                                                                                            name: enrollment.name,
                                                                                            studentNumber: enrollment.student_number,
                                                                                            source: "inspect",
                                                                                        },
                                                                                        true,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Eye className="size-3" />
                                                                                Inspect Timetable
                                                                            </Button>

                                                                            {canUpdate ? (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="ghost"
                                                                                    className="ml-auto h-7 gap-1 px-2 text-[11px] text-rose-600 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-400"
                                                                                    disabled={enrollment.is_grade_protected || isSavingThis}
                                                                                    title={
                                                                                        enrollment.is_grade_protected
                                                                                            ? "Grade-protected: cannot drop safely"
                                                                                            : "Drop enrollment safely"
                                                                                    }
                                                                                    onClick={() =>
                                                                                        void handleDropEnrollmentSafe(section.id, enrollment)
                                                                                    }
                                                                                >
                                                                                    <Trash2 className="size-3" />
                                                                                    Drop (Safe)
                                                                                </Button>
                                                                            ) : null}
                                                                        </div>
                                                                    </div>
                                                                ) : null}
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </ScrollArea>

                                            {/* Proactive Merge Candidate Card */}
                                            {mergeCandidate ? (
                                                <div className="border-t bg-amber-500/10 p-3">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 space-y-1">
                                                            <p className="flex items-center gap-1.5 text-xs font-semibold text-amber-900 dark:text-amber-200">
                                                                <GitMerge className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                                                                Merge Candidate
                                                                {mergeCandidate.freed_room ? ` · Frees ${mergeCandidate.freed_room}` : ""}
                                                            </p>
                                                            <p className="text-[11px] text-amber-800/90 dark:text-amber-200/85">
                                                                All {mergeCandidate.moving_count} student(s) have {mergeCandidate.clashing_count}{" "}
                                                                clash(es) with Section {mergeCandidate.target_section} (
                                                                {mergeCandidate.projected_target_count}/{mergeCandidate.target_maximum_slots})
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="mt-2 h-7 w-full gap-1.5 border-amber-500/50 bg-background text-xs font-semibold text-amber-900 hover:bg-amber-500/15 dark:text-amber-200"
                                                        disabled={
                                                            !canUpdate ||
                                                            mergeCandidate.clashing_count > 0 ||
                                                            movableForMergeCount === 0
                                                        }
                                                        onClick={() => handleStageMerge(section)}
                                                    >
                                                        Stage Merge → Sec {mergeCandidate.target_section}
                                                    </Button>
                                                </div>
                                            ) : null}
                                        </section>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </>
            ) : null}

            {/* Unified Atomic Bottom Staging Bar */}
            {stagedMoves.length > 0 ? (
                <div className="bg-card/95 sticky bottom-4 z-30 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/40 px-4 py-3 shadow-lg backdrop-blur">
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <Badge className="bg-primary text-primary-foreground font-semibold">
                            Previewing {stagedMoves.length} student {stagedMoves.length === 1 ? "move" : "moves"}
                        </Badge>
                        {stagingDeltaSummary ? (
                            <span className="text-foreground font-mono text-xs font-medium">{stagingDeltaSummary}</span>
                        ) : null}
                        <span className="text-emerald-700 dark:text-emerald-400 font-medium">· 0 clashes</span>
                        {overloadStagedCount > 0 ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500/40 bg-amber-500/10 text-[11px] text-amber-700 dark:text-amber-300"
                            >
                                {overloadStagedCount} overload override
                            </Badge>
                        ) : null}
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            disabled={committingMoves}
                            onClick={handleUndoLastMove}
                        >
                            <Undo2 className="size-3.5" />
                            Undo
                        </Button>
                        {stagedMoves.length > 1 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 px-2 text-xs"
                                disabled={committingMoves}
                                onClick={() => setStagedMoves([])}
                            >
                                Clear all
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            disabled={!canUpdate || committingMoves}
                            onClick={() => void handleCommitStagedMoves()}
                        >
                            {committingMoves ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
                            Commit Changes
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
