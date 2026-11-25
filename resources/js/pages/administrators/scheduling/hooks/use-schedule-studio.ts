import { index as plannerIndex, publish } from "@/routes/administrators/scheduling-analytics";
import { store as storeSessions } from "@/routes/administrators/scheduling-analytics/classes/schedules";
import { generate } from "@/routes/administrators/scheduling-analytics/offerings";
import { update as updatePolicy } from "@/routes/administrators/scheduling-analytics/policy";
import {
    batchUpdate,
    destroy as destroySession,
    suggestions as fetchSuggestionsRoute,
    update as updateSession,
} from "@/routes/administrators/scheduling-analytics/schedules";
import { search as searchStudents, schedule as studentSchedule } from "@/routes/administrators/scheduling-analytics/students";
import { router } from "@inertiajs/react";
import axios from "axios";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { canonicalDeliveryMode, isOnlineDeliveryMode, type DeliveryMode, type SchedulingPolicy } from "../../classes/lib/class-defaults";
import type { GenerateOfferingsPayload } from "../../components/generate-offerings-dialog";
import { emptyPlannerFilters, type PlannerFilters } from "../../components/scheduling-filters-bar";
import { DEFAULT_SCHEDULING_POLICY } from "../../components/scheduling-policy-dialog";
import { scheduledHours } from "../../components/scheduling-readiness";
import type { SessionValues } from "../../components/scheduling-session-dialog";
import type {
    ClassScheduleData,
    ResolutionSuggestions,
    ScheduleConflict,
    ScheduleEntry,
    ScheduleUpdateResponse,
    SchedulingAnalyticsProps,
    StudentSchedule,
    StudentSearchResult,
    UtilizationSummary,
} from "../../components/scheduling-types";
import { matchesPlannerGroup } from "../../components/scheduling-view";
import type { ColorScheme } from "../lib/schedule-colors";
import {
    getVisibleDays,
    minutesToApiTime,
    parseTimeToMinutes,
    type GridDensity,
} from "../lib/time-geometry";
import { useScheduleHistory, type ScheduleBlockSnapshot } from "./use-schedule-history";

export type StudioLens = "week" | "matrix" | "heatmap";
export type MatrixPivot = "room" | "faculty" | "cohort";

export type GhostPreview = {
    scheduleId?: number;
    classItem: ClassScheduleData;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    room_name: string | null;
    label: string;
    isOnline?: boolean;
};

export type SessionEditorTarget = {
    classId: number;
    sessionId?: number;
    initialDay?: string;
    initialStartTime?: string;
    initialEndTime?: string;
    initialRoomId?: number | null;
};

export type DraggingBacklogClass = {
    classItem: ClassScheduleData;
    durationMinutes: number;
};

export function extractRequestError(error: unknown): Error {
    if (axios.isAxiosError(error)) {
        const errors = error.response?.data?.errors;
        const first =
            errors &&
            Object.values(errors)
                .flat()
                .find((message) => typeof message === "string");
        return new Error(String(first || error.response?.data?.message || "Unable to save changes. Please try again."));
    }
    return error instanceof Error ? error : new Error("Unable to save changes. Please try again.");
}

function toSnapshot(classItem: ClassScheduleData, session: ScheduleEntry): ScheduleBlockSnapshot | null {
    if (session.id === undefined) return null;
    return {
        schedule_id: session.id,
        day_of_week: session.day_of_week,
        start_time: minutesToApiTime(parseTimeToMinutes(session.start_time)),
        end_time: minutesToApiTime(parseTimeToMinutes(session.end_time)),
        room_id: session.room_id ?? null,
        delivery_mode: canonicalDeliveryMode(session.delivery_mode ?? classItem.delivery_mode),
        component_type: session.component_type ?? "lecture",
        status: session.status ?? "published",
    };
}

export function useScheduleStudio(props: SchedulingAnalyticsProps) {
    const { schedule_data, stats, term_context, filters, creation_options, defaults, scheduling_policy } = props;

    const initialPolicy: SchedulingPolicy = {
        ...DEFAULT_SCHEDULING_POLICY,
        ...(creation_options.scheduling_policy ?? {}),
        ...(scheduling_policy ?? {}),
    };

    const [classes, setClasses] = useState<ClassScheduleData[]>(schedule_data);
    const [conflicts, setConflicts] = useState<ScheduleConflict[]>(stats.schedule_conflicts ?? []);
    const [utilization, setUtilization] = useState<UtilizationSummary | null>(stats.utilization ?? null);
    const [policy, setPolicy] = useState<SchedulingPolicy>(initialPolicy);

    // Studio workspace controls
    const [activeLens, setActiveLens] = useState<StudioLens>("week");
    const [matrixPivot, setMatrixPivot] = useState<MatrixPivot>("room");
    const [matrixDay, setMatrixDay] = useState<string>("Monday");
    const [density, setDensity] = useState<GridDensity>("birdseye");
    const [colorScheme, setColorScheme] = useState<ColorScheme>("subject");
    const [railOpen, setRailOpenRaw] = useState(true);
    const [workbenchOpen, setWorkbenchOpenRaw] = useState(false);

    // Keep left Backlog Rail and right Conflict Workbench mutually adaptive on viewports < 1600px
    // so the center schedule grid always enjoys generous breathing room.
    const setRailOpen = useCallback((next: boolean | ((prev: boolean) => boolean)) => {
        setRailOpenRaw((prev) => {
            const resolved = typeof next === "function" ? next(prev) : next;
            if (resolved && typeof window !== "undefined" && window.innerWidth < 1600) {
                setWorkbenchOpenRaw(false);
            }
            return resolved;
        });
    }, []);

    const setWorkbenchOpen = useCallback((next: boolean | ((prev: boolean) => boolean)) => {
        setWorkbenchOpenRaw((prev) => {
            const resolved = typeof next === "function" ? next(prev) : next;
            if (resolved && typeof window !== "undefined" && window.innerWidth < 1600) {
                setRailOpenRaw(false);
            }
            return resolved;
        });
    }, []);
    const [spotlightMode, setSpotlightMode] = useState(false);
    const [focusedConflictIndex, setFocusedConflictIndex] = useState<number | null>(null);
    const [focusedScheduleIds, setFocusedScheduleIds] = useState<number[]>([]);
    const [ghostPreview, setGhostPreview] = useState<GhostPreview | null>(null);
    const [savingScheduleIds, setSavingScheduleIds] = useState<Set<number>>(new Set());
    const [draggingBacklog, setDraggingBacklog] = useState<DraggingBacklogClass | null>(null);

    // Filters & search
    const [viewFilters, setViewFilters] = useState<PlannerFilters>({
        ...emptyPlannerFilters,
        course: filters.current_filters.course || "all",
        year: filters.current_filters.year_level || "all",
        section: filters.current_filters.section || "all",
    });
    const [search, setSearch] = useState("");
    const [railTab, setRailTab] = useState<"needs_times" | "all" | "drafts">("needs_times");

    // Modals & inspector
    const [editorTarget, setEditorTarget] = useState<SessionEditorTarget | null>(null);
    const [inspectedTarget, setInspectedTarget] = useState<{ classId: number; sessionId: number } | null>(null);
    const [reviewOpen, setReviewOpen] = useState(false);
    const [generateOpen, setGenerateOpen] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const [policyOpen, setPolicyOpen] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [togglingSunday, setTogglingSunday] = useState(false);

    // Student timetable lens
    const [studentQuery, setStudentQuery] = useState("");
    const [students, setStudents] = useState<StudentSearchResult[]>([]);
    const [activeStudent, setActiveStudent] = useState<StudentSchedule | null>(null);
    const [loadingStudent, setLoadingStudent] = useState(false);
    const [searchingStudent, setSearchingStudent] = useState(false);
    const studentRequest = useRef(0);

    // Resolution suggestions cache by schedule_id
    const [suggestionsCache, setSuggestionsCache] = useState<Record<number, ResolutionSuggestions>>({});
    const [loadingSuggestionsFor, setLoadingSuggestionsFor] = useState<number | null>(null);

    const schoolYear = term_context?.school_year ?? defaults.school_year;
    const semester = term_context?.semester ?? defaults.semester;

    // Sync props on term switch
    useEffect(() => setClasses(schedule_data), [schedule_data]);
    useEffect(() => {
        setConflicts(stats.schedule_conflicts ?? []);
        setUtilization(stats.utilization ?? null);
    }, [stats.schedule_conflicts, stats.utilization]);
    useEffect(() => {
        const merged = {
            ...DEFAULT_SCHEDULING_POLICY,
            ...(creation_options.scheduling_policy ?? {}),
            ...(scheduling_policy ?? {}),
        };
        setPolicy(merged);
    }, [creation_options.scheduling_policy, scheduling_policy]);

    // Student search debounce
    useEffect(() => {
        const controller = new AbortController();
        const query = studentQuery.trim();
        setStudents([]);
        setSearchingStudent(false);
        if (query.length < 2) return;
        const timer = setTimeout(async () => {
            setSearchingStudent(true);
            try {
                const response = await axios.get(searchStudents.url({ query: { query } }), { signal: controller.signal });
                if (!controller.signal.aborted) setStudents(response.data.students ?? []);
            } catch (error) {
                if (!controller.signal.aborted) toast.error(extractRequestError(error).message);
            } finally {
                if (!controller.signal.aborted) setSearchingStudent(false);
            }
        }, 280);
        return () => {
            controller.abort();
            clearTimeout(timer);
        };
    }, [studentQuery]);

    // Batch-apply snapshots for Undo/Redo and atomic conflict swaps
    const applyBatchOperations = useCallback(
        async (
            operations: ScheduleBlockSnapshot[],
            toastMessage?: string,
            recordHistory?: { label: string; before: ScheduleBlockSnapshot[] },
        ) => {
            const ids = operations.map((op) => op.schedule_id);
            setSavingScheduleIds((prev) => new Set([...prev, ...ids]));
            try {
                const response = await axios.post<{
                    classes: ClassScheduleData[];
                    conflicts: ScheduleConflict[];
                    utilization?: UtilizationSummary | null;
                }>(batchUpdate.url(), { operations });

                const updatedById = new Map((response.data.classes ?? []).map((item) => [item.id, item]));
                setClasses((previous) => previous.map((item) => updatedById.get(item.id) ?? item));
                setConflicts(response.data.conflicts ?? []);
                if (response.data.utilization !== undefined && response.data.utilization !== null) {
                    setUtilization(response.data.utilization);
                }
                setSuggestionsCache({});
                setGhostPreview(null);
                if (recordHistory) {
                    historyRecordRef.current({
                        label: recordHistory.label,
                        before: recordHistory.before,
                        after: operations,
                    });
                }
                if (toastMessage) toast.success(toastMessage);
            } catch (error) {
                toast.error(extractRequestError(error).message);
                throw error;
            } finally {
                setSavingScheduleIds((prev) => {
                    const next = new Set(prev);
                    ids.forEach((id) => next.delete(id));
                    return next;
                });
            }
        },
        [],
    );

    const history = useScheduleHistory(
        useCallback(
            async (snapshots, label) => {
                await applyBatchOperations(snapshots, label);
            },
            [applyBatchOperations],
        ),
    );
    const historyRecordRef = useRef(history.record);
    historyRecordRef.current = history.record;

    // Lookups
    const roomsById = useMemo(
        () => new Map(creation_options.rooms.map((room) => [room.id, room])),
        [creation_options.rooms],
    );

    const hasSundaySchedules = useMemo(
        () => classes.some((cls) => cls.schedules.some((entry) => entry.day_of_week === "Sunday")),
        [classes],
    );

    const visibleDays = useMemo(
        () => getVisibleDays(policy.allow_sunday_scheduling, hasSundaySchedules),
        [policy.allow_sunday_scheduling, hasSundaySchedules],
    );

    // Filtered data
    const matchesProgramAndYear = useCallback(
        (item: ClassScheduleData) => matchesPlannerGroup(item, viewFilters.course, viewFilters.year),
        [viewFilters.course, viewFilters.year],
    );

    const availableSections = useMemo(
        () => [...new Set(classes.filter(matchesProgramAndYear).map((item) => item.section))].sort(),
        [classes, matchesProgramAndYear],
    );

    const visibleClasses = useMemo(
        () =>
            classes.filter(
                (item) =>
                    matchesProgramAndYear(item) &&
                    (viewFilters.section === "all" || item.section === viewFilters.section) &&
                    (viewFilters.room === "all" || item.schedules.some((session) => String(session.room_id) === viewFilters.room)) &&
                    (viewFilters.faculty === "all" || item.faculty_id === viewFilters.faculty) &&
                    (!activeStudent || activeStudent.schedule.some((entry) => entry.id === item.id)),
            ),
        [classes, matchesProgramAndYear, viewFilters, activeStudent],
    );

    const conflictScheduleIdSet = useMemo(
        () =>
            new Set(
                conflicts
                    .flatMap((c) => [c.schedule_1_id, c.schedule_2_id])
                    .filter((id): id is number => typeof id === "number"),
            ),
        [conflicts],
    );

    const conflictsByScheduleId = useMemo(() => {
        const map = new Map<number, ScheduleConflict[]>();
        for (const conflict of conflicts) {
            for (const sid of [conflict.schedule_1_id, conflict.schedule_2_id]) {
                if (typeof sid === "number") {
                    const list = map.get(sid) ?? [];
                    list.push(conflict);
                    map.set(sid, list);
                }
            }
        }
        return map;
    }, [conflicts]);

    const needsTimesCount = useMemo(
        () => classes.filter((item) => scheduledHours(item.schedules) < (item.required_hours ?? 3) || item.schedules.length === 0).length,
        [classes],
    );

    const draftCount = useMemo(
        () => classes.reduce((count, item) => count + item.schedules.filter((s) => s.status === "draft").length, 0),
        [classes],
    );

    // Actions
    const changeTerm = useCallback(
        (nextYear: string, nextSem: string) => {
            history.clear();
            setSuggestionsCache({});
            setFocusedConflictIndex(null);
            setFocusedScheduleIds([]);
            setInspectedTarget(null);
            router.get(plannerIndex.url({ query: { school_year: nextYear, semester: nextSem } }), {}, { preserveState: false });
        },
        [history],
    );

    const selectStudent = useCallback(async (student: StudentSearchResult) => {
        const request = ++studentRequest.current;
        setStudentQuery("");
        setStudents([]);
        setLoadingStudent(true);
        try {
            const response = await axios.get<StudentSchedule>(studentSchedule.url(student.id));
            if (request === studentRequest.current) setActiveStudent(response.data);
        } catch (error) {
            if (request === studentRequest.current) toast.error(extractRequestError(error).message);
        } finally {
            if (request === studentRequest.current) setLoadingStudent(false);
        }
    }, []);

    const clearStudent = useCallback(() => {
        studentRequest.current++;
        setActiveStudent(null);
        setLoadingStudent(false);
    }, []);

    // Direct move/resize/reassign of an existing schedule block (allows conflicting drops!)
    const updateScheduleBlockDirect = useCallback(
        async (
            classItem: ClassScheduleData,
            session: ScheduleEntry,
            patch: {
                day_of_week?: string;
                start_time?: string;
                end_time?: string;
                room_id?: number | null;
                delivery_mode?: DeliveryMode;
                component_type?: "lecture" | "lab";
                apply_to_pattern_group?: boolean;
            },
            actionLabel?: string,
        ) => {
            if (session.id === undefined) return;
            const beforeSnap = toSnapshot(classItem, session);
            const nextMode = canonicalDeliveryMode(patch.delivery_mode ?? session.delivery_mode ?? classItem.delivery_mode);
            const payload = {
                day_of_week: patch.day_of_week ?? session.day_of_week,
                start_time: patch.start_time ?? minutesToApiTime(parseTimeToMinutes(session.start_time)),
                end_time: patch.end_time ?? minutesToApiTime(parseTimeToMinutes(session.end_time)),
                room_id: isOnlineDeliveryMode(nextMode) ? null : patch.room_id !== undefined ? patch.room_id : (session.room_id ?? null),
                delivery_mode: nextMode,
                component_type: patch.component_type ?? (session.component_type === "lab" ? "lab" : "lecture"),
                apply_to_pattern_group: patch.apply_to_pattern_group ?? false,
            };

            setSavingScheduleIds((prev) => new Set([...prev, session.id!]));
            try {
                const response = await axios.patch<ScheduleUpdateResponse>(updateSession.url(session.id), payload);

                if (response.data.class) {
                    const updatedClass = response.data.class;
                    setClasses((previous) => previous.map((item) => (item.id === updatedClass.id ? updatedClass : item)));
                } else {
                    setClasses((previous) =>
                        previous.map((item) => ({
                            ...item,
                            schedules: item.schedules.map((entry) =>
                                entry.id === session.id ? { ...entry, ...response.data.schedule } : entry,
                            ),
                        })),
                    );
                }

                const nextConflicts = response.data.conflicts ?? [];
                setConflicts(nextConflicts);
                if (response.data.utilization !== undefined && response.data.utilization !== null) {
                    setUtilization(response.data.utilization);
                }
                setSuggestionsCache({});
                setGhostPreview(null);

                if (beforeSnap && !payload.apply_to_pattern_group) {
                    const afterSnap: ScheduleBlockSnapshot = {
                        schedule_id: session.id,
                        day_of_week: payload.day_of_week,
                        start_time: payload.start_time,
                        end_time: payload.end_time,
                        room_id: payload.room_id,
                        delivery_mode: payload.delivery_mode,
                        component_type: payload.component_type,
                        status: session.status ?? "published",
                    };
                    history.record({
                        label: actionLabel ?? `Updated ${classItem.subject_code}`,
                        before: [beforeSnap],
                        after: [afterSnap],
                    });
                }

                // Check if this specific block now has conflicts
                const blockConflicts = nextConflicts.filter(
                    (c) => c.schedule_1_id === session.id || c.schedule_2_id === session.id,
                );
                if (blockConflicts.length > 0) {
                    toast.warning(`${classItem.subject_code} moved with ${blockConflicts.length} conflict(s) flagged.`, {
                        action: {
                            label: "Resolve",
                            onClick: () => {
                                setWorkbenchOpen(true);
                                setSpotlightMode(true);
                                setFocusedScheduleIds([session.id!]);
                            },
                        },
                    });
                } else {
                    toast.success(actionLabel ?? `${classItem.subject_code} schedule updated.`);
                }
            } catch (error) {
                toast.error(extractRequestError(error).message);
                throw error;
            } finally {
                setSavingScheduleIds((prev) => {
                    const next = new Set(prev);
                    next.delete(session.id!);
                    return next;
                });
            }
        },
        [history],
    );

    const addClassScheduleBlocks = useCallback(
        async (
            classItem: ClassScheduleData,
            values: {
                days: string[];
                start_time: string;
                end_time: string;
                room_id: number | null;
                delivery_mode: DeliveryMode;
                component_type?: "lecture" | "lab";
                status?: "draft" | "published";
            },
        ) => {
            try {
                const response = await axios.post<{
                    class: ClassScheduleData;
                    conflicts: ScheduleConflict[];
                    utilization?: UtilizationSummary | null;
                }>(storeSessions.url(classItem.id), {
                    ...values,
                    status: values.status ?? "draft",
                });

                setClasses((previous) => previous.map((item) => (item.id === classItem.id ? response.data.class : item)));
                const nextConflicts = response.data.conflicts ?? [];
                setConflicts(nextConflicts);
                if (response.data.utilization !== undefined && response.data.utilization !== null) {
                    setUtilization(response.data.utilization);
                }
                setSuggestionsCache({});

                const newBlockIds = new Set(response.data.class.schedules.map((s) => s.id).filter(Boolean));
                const newConflicts = nextConflicts.filter(
                    (c) =>
                        (c.schedule_1_id !== undefined && newBlockIds.has(c.schedule_1_id)) ||
                        (c.schedule_2_id !== undefined && newBlockIds.has(c.schedule_2_id)),
                );
                if (newConflicts.length > 0) {
                    toast.warning(`Scheduled ${classItem.subject_code} (${newConflicts.length} conflict(s) detected).`, {
                        action: {
                            label: "Resolve",
                            onClick: () => {
                                setWorkbenchOpen(true);
                                setSpotlightMode(true);
                            },
                        },
                    });
                } else {
                    toast.success(`Scheduled ${classItem.subject_code} on ${values.days.join(", ")}.`);
                }
            } catch (error) {
                throw extractRequestError(error);
            }
        },
        [],
    );

    const saveEditorSession = useCallback(
        async (values: SessionValues) => {
            if (!editorTarget) throw new Error("Class no longer available.");
            const targetClass = classes.find((c) => c.id === editorTarget.classId);
            if (!targetClass) throw new Error("Class no longer available.");
            const targetSession = editorTarget.sessionId
                ? targetClass.schedules.find((s) => s.id === editorTarget.sessionId)
                : undefined;

            if (targetSession?.id !== undefined) {
                await updateScheduleBlockDirect(
                    targetClass,
                    targetSession,
                    {
                        day_of_week: values.days[0],
                        start_time: values.start_time,
                        end_time: values.end_time,
                        room_id: values.room_id,
                        delivery_mode: values.delivery_mode,
                        component_type: values.component_type,
                        apply_to_pattern_group: values.apply_to_pattern_group,
                    },
                    `Updated ${targetClass.subject_code}`,
                );
            } else {
                await addClassScheduleBlocks(targetClass, {
                    days: values.days,
                    start_time: values.start_time,
                    end_time: values.end_time,
                    room_id: values.room_id,
                    delivery_mode: values.delivery_mode,
                    component_type: values.component_type,
                    status: "draft",
                });
            }
        },
        [editorTarget, classes, updateScheduleBlockDirect, addClassScheduleBlocks],
    );

    const deleteScheduleBlock = useCallback(async (sessionId: number, deletePatternGroup = false) => {
        setSavingScheduleIds((prev) => new Set([...prev, sessionId]));
        try {
            const response = await axios.delete<{
                class?: ClassScheduleData | null;
                conflicts: ScheduleConflict[];
                utilization?: UtilizationSummary | null;
            }>(destroySession.url(sessionId), {
                data: { delete_pattern_group: deletePatternGroup },
            });

            if (response.data.class) {
                const updatedClass = response.data.class;
                setClasses((previous) => previous.map((item) => (item.id === updatedClass.id ? updatedClass : item)));
            } else {
                setClasses((previous) =>
                    previous.map((item) => ({
                        ...item,
                        schedules: item.schedules.filter((s) => s.id !== sessionId),
                    })),
                );
            }
            setConflicts(response.data.conflicts ?? []);
            if (response.data.utilization !== undefined && response.data.utilization !== null) {
                setUtilization(response.data.utilization);
            }
            setSuggestionsCache({});
            setInspectedTarget((prev) => (prev?.sessionId === sessionId ? null : prev));
            toast.success(deletePatternGroup ? "Recurring pattern removed." : "Session removed.");
        } catch (error) {
            const err = extractRequestError(error);
            toast.error(err.message);
            throw err;
        } finally {
            setSavingScheduleIds((prev) => {
                const next = new Set(prev);
                next.delete(sessionId);
                return next;
            });
        }
    }, []);

    const loadSuggestionsForSchedule = useCallback(
        async (scheduleId: number, force = false): Promise<ResolutionSuggestions | null> => {
            if (!force && suggestionsCache[scheduleId]) {
                return suggestionsCache[scheduleId];
            }
            setLoadingSuggestionsFor(scheduleId);
            try {
                const response = await axios.get<ResolutionSuggestions>(fetchSuggestionsRoute.url(scheduleId));
                setSuggestionsCache((prev) => ({ ...prev, [scheduleId]: response.data }));
                return response.data;
            } catch (error) {
                toast.error(extractRequestError(error).message);
                return null;
            } finally {
                setLoadingSuggestionsFor((prev) => (prev === scheduleId ? null : prev));
            }
        },
        [suggestionsCache],
    );

    const saveSchedulingPolicy = useCallback(
        async (nextPolicy: SchedulingPolicy) => {
            try {
                const response = await axios.patch<{
                    scheduling_policy: SchedulingPolicy;
                    conflicts: ScheduleConflict[];
                    utilization?: UtilizationSummary | null;
                }>(updatePolicy.url(), {
                    ...nextPolicy,
                    school_year: schoolYear,
                    semester,
                });
                setPolicy({ ...DEFAULT_SCHEDULING_POLICY, ...response.data.scheduling_policy });
                setConflicts(response.data.conflicts ?? []);
                if (response.data.utilization !== undefined && response.data.utilization !== null) {
                    setUtilization(response.data.utilization);
                }
                setSuggestionsCache({});
                toast.success("Scheduling rules updated.");
            } catch (error) {
                const err = extractRequestError(error);
                toast.error(err.message);
                throw err;
            }
        },
        [schoolYear, semester],
    );

    const toggleSundayScheduling = useCallback(async () => {
        if (togglingSunday) return;
        setTogglingSunday(true);
        try {
            const nextValue = !policy.allow_sunday_scheduling;
            await saveSchedulingPolicy({ ...policy, allow_sunday_scheduling: nextValue });
            if (!nextValue && matrixDay === "Sunday") {
                setMatrixDay("Monday");
            }
        } finally {
            setTogglingSunday(false);
        }
    }, [togglingSunday, policy, saveSchedulingPolicy, matrixDay]);

    const generateClasses = useCallback(async (payload: GenerateOfferingsPayload) => {
        try {
            const response = await axios.post<{
                created_count: number;
                skipped_count: number;
                subjects_found: number;
                classes: ClassScheduleData[];
            }>(generate.url(), payload);
            setClasses((previous) => [...previous, ...(response.data.classes ?? [])]);
            if (!response.data.subjects_found) {
                toast.info("No subjects found for this program, year, and semester.");
            } else {
                toast.success(`${response.data.created_count} classes added. ${response.data.skipped_count} already existed.`);
            }
        } catch (error) {
            toast.error(extractRequestError(error).message);
            throw error;
        }
    }, []);

    const publishDrafts = useCallback(
        async (force = false) => {
            setPublishing(true);
            try {
                const response = await axios.post<{ published_count: number; conflicts: ScheduleConflict[] }>(publish.url(), {
                    school_year: schoolYear,
                    semester,
                    force,
                });
                setClasses((previous) =>
                    previous.map((item) => ({
                        ...item,
                        schedules: item.schedules.map((session) => ({ ...session, status: "published" })),
                    })),
                );
                setConflicts(response.data.conflicts ?? []);
                setReviewOpen(false);
                toast.success(`${response.data.published_count} draft sessions published.`);
            } catch (error) {
                toast.error(extractRequestError(error).message);
            } finally {
                setPublishing(false);
            }
        },
        [schoolYear, semester],
    );

    const jumpToDayAndHour = useCallback((day: string, lens: StudioLens = "week") => {
        setActiveLens(lens);
        if (lens === "matrix") {
            setMatrixDay(day);
        }
    }, []);

    return {
        // Core data
        classes,
        visibleClasses,
        conflicts,
        utilization,
        policy,
        schoolYear,
        semester,
        roomsById,
        visibleDays,
        hasSundaySchedules,
        availableSections,
        conflictScheduleIdSet,
        conflictsByScheduleId,
        needsTimesCount,
        draftCount,

        // Lens & view state
        activeLens,
        setActiveLens,
        matrixPivot,
        setMatrixPivot,
        matrixDay,
        setMatrixDay,
        density,
        setDensity,
        colorScheme,
        setColorScheme,
        railOpen,
        setRailOpen,
        workbenchOpen,
        setWorkbenchOpen,
        spotlightMode,
        setSpotlightMode,
        focusedConflictIndex,
        setFocusedConflictIndex,
        focusedScheduleIds,
        setFocusedScheduleIds,
        ghostPreview,
        setGhostPreview,
        savingScheduleIds,
        draggingBacklog,
        setDraggingBacklog,

        // Filters
        viewFilters,
        setViewFilters,
        search,
        setSearch,
        railTab,
        setRailTab,

        // Modals & inspector
        editorTarget,
        setEditorTarget,
        inspectedTarget,
        setInspectedTarget,
        reviewOpen,
        setReviewOpen,
        generateOpen,
        setGenerateOpen,
        exportOpen,
        setExportOpen,
        policyOpen,
        setPolicyOpen,
        publishing,
        togglingSunday,

        // Student lookup
        studentQuery,
        setStudentQuery,
        students,
        activeStudent,
        loadingStudent,
        searchingStudent,
        selectStudent,
        clearStudent,

        // Suggestions & history
        suggestionsCache,
        loadingSuggestionsFor,
        loadSuggestionsForSchedule,
        history,

        // Mutations
        changeTerm,
        updateScheduleBlockDirect,
        addClassScheduleBlocks,
        saveEditorSession,
        deleteScheduleBlock,
        applyBatchOperations,
        saveSchedulingPolicy,
        toggleSundayScheduling,
        generateClasses,
        publishDrafts,
        jumpToDayAndHour,
    };
}

export type ScheduleStudioController = ReturnType<typeof useScheduleStudio>;
