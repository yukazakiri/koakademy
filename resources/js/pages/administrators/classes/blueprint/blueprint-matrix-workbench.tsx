import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect as SearchableMultiSelect } from "@/components/ui/multi-select";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { index as classesIndex, show as showClass } from "@/routes/administrators/classes";
import { shsStrands, shsSubjects, subjects as subjectOptionsRoute } from "@/routes/administrators/classes/options";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowUpRight,
    BookOpen,
    Building2,
    Calendar,
    CheckCircle2,
    Copy,
    GraduationCap,
    Loader2,
    Plus,
    RefreshCw,
    Save,
    ShieldAlert,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    Users,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { postBatchBlueprint } from "../crud/api";
import { LiveBusyGrid, type LiveBusyGridBlock } from "../crud/live-busy-grid";
import type {
    BatchBlueprintConflict,
    BatchBlueprintResponse,
    BatchBlueprintResult,
    BlueprintSection,
    BlueprintSharedFields,
    ClassPolicySettings,
    CrudScheduleBlock,
    PreflightConflict,
    SiblingSection,
} from "../crud/types";
import {
    buildSubjectCodeFromSubjectOptions,
    canonicalDeliveryMode,
    deliveryModeShortLabel,
    isAsynchronousOnlineMode,
    isOnlineDeliveryMode,
    type ClassFormData,
    type ClassFormOptions,
    type EntityOption,
    type ShsSubjectOption,
    type SubjectOption,
} from "../lib/class-defaults";
import { SectionSlotPopover } from "./section-slot-popover";
import { facultyLoadDeltas } from "./faculty-load";
import { WorkspaceApiError } from "../workspace/api";

type MatrixSectionRow = BlueprintSection & {
    enrolled_count?: number;
    capacity_input: string;
};

type BlueprintMatrixWorkbenchProps = {
    semester: "1" | "2" | "summer";
    school_year: string;
    options: ClassFormOptions;
    initialBlueprint?: Partial<ClassFormData> | null;
    siblingSections?: SiblingSection[];
    preloadedSections?: BlueprintSection[];
};

const DEFAULT_POLICY_SETTINGS: ClassPolicySettings = {
    enable_announcements: true,
    enable_grade_visibility: true,
    enable_attendance_tracking: false,
    enable_online_resources: true,
    allow_late_submissions: false,
    enable_discussion_board: false,
    notify_students_on_schedule_changes: true,
};

const POLICY_ITEMS: Array<{ key: keyof ClassPolicySettings; label: string; description: string }> = [
    { key: "enable_announcements", label: "Announcements", description: "Allow instructors to post class announcements." },
    { key: "enable_grade_visibility", label: "Grade visibility", description: "Students can view published grades." },
    { key: "enable_attendance_tracking", label: "Attendance tracking", description: "Enable session attendance logs." },
    { key: "enable_online_resources", label: "Online resources", description: "Share files, links, and syllabus materials." },
    { key: "allow_late_submissions", label: "Late submissions", description: "Permit student submissions past due dates." },
    { key: "enable_discussion_board", label: "Discussion board", description: "Enable threaded classroom discussions." },
    { key: "notify_students_on_schedule_changes", label: "Schedule alerts", description: "Alert enrolled students when sessions move." },
];

const SECTION_SWATCHES = [
    "bg-primary",
    "bg-indigo-500",
    "bg-amber-500",
    "bg-emerald-500",
    "bg-sky-500",
    "bg-violet-500",
    "bg-rose-500",
    "bg-teal-500",
];

const STAGGER_BANDS: Array<{ days: string[]; start_time: string; end_time: string }> = [
    { days: ["Monday", "Wednesday"], start_time: "08:00", end_time: "09:30" },
    { days: ["Tuesday", "Thursday"], start_time: "08:00", end_time: "09:30" },
    { days: ["Monday", "Wednesday"], start_time: "10:00", end_time: "11:30" },
    { days: ["Tuesday", "Thursday"], start_time: "10:00", end_time: "11:30" },
    { days: ["Monday", "Wednesday"], start_time: "13:00", end_time: "14:30" },
    { days: ["Tuesday", "Thursday"], start_time: "13:00", end_time: "14:30" },
    { days: ["Monday", "Wednesday"], start_time: "15:00", end_time: "16:30" },
    { days: ["Tuesday", "Thursday"], start_time: "15:00", end_time: "16:30" },
    { days: ["Friday"], start_time: "08:00", end_time: "11:00" },
    { days: ["Friday"], start_time: "13:00", end_time: "16:00" },
    { days: ["Saturday"], start_time: "08:00", end_time: "11:00" },
];

const LAB_STAGGER_BANDS: Array<{ day: string; start_time: string; end_time: string }> = [
    { day: "Friday", start_time: "08:00", end_time: "10:00" },
    { day: "Friday", start_time: "10:00", end_time: "12:00" },
    { day: "Friday", start_time: "13:00", end_time: "15:00" },
    { day: "Friday", start_time: "15:00", end_time: "17:00" },
    { day: "Saturday", start_time: "08:00", end_time: "10:00" },
    { day: "Saturday", start_time: "10:00", end_time: "12:00" },
    { day: "Saturday", start_time: "13:00", end_time: "15:00" },
];

function parseMinutes(time?: string | null): number | null {
    if (!time) return null;
    const parts = time.trim().split(":");
    if (parts.length < 2) return null;
    const h = Number(parts[0]);
    const m = Number(parts[1]);
    if (!Number.isFinite(h) || !Number.isFinite(m)) return null;
    return h * 60 + m;
}

function slotHours(slot: Pick<CrudScheduleBlock, "start_time" | "end_time">): number {
    const start = parseMinutes(slot.start_time);
    const end = parseMinutes(slot.end_time);
    if (start === null || end === null || end <= start) return 0;
    return (end - start) / 60;
}

function timesOverlap(aStart: string, aEnd: string, bStart: string, bEnd: string): boolean {
    const as = parseMinutes(aStart);
    const ae = parseMinutes(aEnd);
    const bs = parseMinutes(bStart);
    const be = parseMinutes(bEnd);
    if (as === null || ae === null || bs === null || be === null) return false;
    return as < be && ae > bs;
}

function formatShortTime(time: string): string {
    const mins = parseMinutes(time);
    if (mins === null) return time;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    const suffix = h >= 12 ? "p" : "a";
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return m === 0 ? `${h12}${suffix}` : `${h12}:${String(m).padStart(2, "0")}${suffix}`;
}

function nextSectionCode(existingCodes: string[]): string {
    const normalized = new Set(existingCodes.map((c) => c.trim().toUpperCase()));
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
    for (const letter of alphabet) {
        if (!normalized.has(letter)) return letter;
    }
    let idx = 1;
    while (normalized.has(`S${idx}`)) idx += 1;
    return `S${idx}`;
}

function isSubjectOption(value: unknown): value is SubjectOption {
    return typeof value === "object" && value !== null && "id" in value && "label" in value && "code" in value && "course_id" in value;
}

function isEntityOption(value: unknown): value is EntityOption {
    return typeof value === "object" && value !== null && "id" in value && "label" in value;
}

function isShsSubjectOption(value: unknown): value is ShsSubjectOption {
    return typeof value === "object" && value !== null && "code" in value && "label" in value;
}

async function fetchOptionList<T>(url: string, guard: (value: unknown) => value is T): Promise<T[]> {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error("Curriculum options could not be loaded.");
    const payload = (await response.json()) as { data?: unknown[] };
    const rows = Array.isArray(payload.data) ? payload.data : [];
    return rows.filter(guard);
}

function buildStarterSections(
    preloadedSections: BlueprintSection[] | undefined,
    siblingSections: SiblingSection[] | undefined,
    initialBlueprint: Partial<ClassFormData> | null | undefined,
    options: ClassFormOptions,
): MatrixSectionRow[] {
    if (preloadedSections && preloadedSections.length > 0) {
        return preloadedSections.map((section, idx) => ({
            ...section,
            client_key: section.client_key || `preloaded-${section.class_id ?? idx}`,
            capacity_input: String(section.maximum_slots),
        }));
    }

    const defaultMode = canonicalDeliveryMode(initialBlueprint?.delivery_mode ?? "face_to_face");

    if (siblingSections && siblingSections.length > 0) {
        return siblingSections.map((sib, idx) => {
            const matchedFaculty = sib.faculty
                ? options.faculties.find((f) => f.label.trim().toLowerCase() === sib.faculty?.trim().toLowerCase())
                : null;
            const isSource = sib.is_current && initialBlueprint;
            const seededSchedules: CrudScheduleBlock[] = isSource
                ? (initialBlueprint.schedules ?? []).map((sched) => ({
                      id: sched.id,
                      day_of_week: sched.day_of_week,
                      start_time: sched.start_time,
                      end_time: sched.end_time,
                      room_id: sched.room_id ?? null,
                      delivery_mode: canonicalDeliveryMode(sched.delivery_mode ?? defaultMode),
                      component_type: sched.component_type === "lab" ? "lab" : "lecture",
                      status: sched.status,
                  }))
                : [];
            const maxSlots = sib.maximum_slots;
            return {
                client_key: `existing-${sib.id}-${idx}`,
                class_id: sib.id,
                section: sib.section,
                faculty_id: isSource ? (initialBlueprint.faculty_id ?? (matchedFaculty ? String(matchedFaculty.id) : null)) : (matchedFaculty ? String(matchedFaculty.id) : null),
                maximum_slots: maxSlots,
                room_id: isSource ? (initialBlueprint.room_id ?? null) : null,
                schedules: seededSchedules,
                enrolled_count: sib.students_count,
                capacity_input: String(maxSlots),
            };
        });
    }

    // Fresh /create?mode=batch starter sections: Sec A, Sec B, Sec C with clean staggered starter slots
    const starterDefs: Array<{ section: string; bandIdx: number }> = [
        { section: "A", bandIdx: 0 },
        { section: "B", bandIdx: 1 },
        { section: "C", bandIdx: 2 },
    ];

    return starterDefs.map((starter, idx) => {
        const band = STAGGER_BANDS[starter.bandIdx];
        return {
            client_key: `new-starter-${starter.section}-${idx}`,
            class_id: null,
            section: starter.section,
            faculty_id: null,
            maximum_slots: 40,
            room_id: null,
            schedules: band.days.map((day) => ({
                day_of_week: day,
                start_time: band.start_time,
                end_time: band.end_time,
                room_id: null,
                delivery_mode: defaultMode,
                component_type: "lecture",
            })),
            capacity_input: "40",
        };
    });
}

function detectClientMatrixConflicts(
    rows: MatrixSectionRow[],
    options: ClassFormOptions,
    roomsById: Map<number, { label: string; capacity?: number | null }>,
    facultiesById: Map<string, { label: string; load_hours?: number; max_load_hours?: number | null }>,
    facultyDeltas: Map<string, number>,
    hasTermLoad: boolean,
): BatchBlueprintConflict[] {
    const conflicts: BatchBlueprintConflict[] = [];
    const policy = options.scheduling_policy;
    const checkRoom = policy?.check_room_conflicts ?? true;
    const checkFaculty = policy?.check_faculty_conflicts ?? true;
    const checkAsync = policy?.check_asynchronous_online_conflicts ?? false;

    // 1. Duplicate section code check
    const seenSections = new Map<string, string>();
    for (const row of rows) {
        const code = row.section.trim().toUpperCase();
        if (!code) {
            conflicts.push({
                client_key: row.client_key,
                kind: "self",
                severity: "blocking",
                schedule_index: null,
                message: "Section code is required.",
            });
            continue;
        }
        const prevKey = seenSections.get(code);
        if (prevKey) {
            conflicts.push({
                client_key: row.client_key,
                kind: "self",
                severity: "blocking",
                schedule_index: null,
                message: `Duplicate section code "${row.section.trim()}" in this batch.`,
            });
        } else {
            seenSections.set(code, row.client_key);
        }
    }

    // 2. Per-section self overlaps + room capacity warnings
    for (const row of rows) {
        row.schedules.forEach((slot, sIdx) => {
            if (!slot.day_of_week || !slot.start_time || !slot.end_time || slot.end_time <= slot.start_time) {
                conflicts.push({
                    client_key: row.client_key,
                    kind: "self",
                    severity: "blocking",
                    schedule_index: sIdx,
                    day_of_week: slot.day_of_week,
                    start_time: slot.start_time,
                    end_time: slot.end_time,
                    message: `Sec ${row.section || "?"}: slot has invalid time range.`,
                });
                return;
            }

            // Self overlap within the same section
            for (let j = sIdx + 1; j < row.schedules.length; j += 1) {
                const other = row.schedules[j];
                if (slot.day_of_week === other.day_of_week && timesOverlap(slot.start_time, slot.end_time, other.start_time, other.end_time)) {
                    conflicts.push({
                        client_key: row.client_key,
                        kind: "self",
                        severity: "blocking",
                        schedule_index: sIdx,
                        day_of_week: slot.day_of_week,
                        start_time: slot.start_time,
                        end_time: slot.end_time,
                        message: `Sec ${row.section || "?"} has overlapping sessions on ${slot.day_of_week} (${formatShortTime(slot.start_time)}–${formatShortTime(slot.end_time)}).`,
                    });
                    conflicts.push({
                        client_key: row.client_key,
                        kind: "self",
                        severity: "blocking",
                        schedule_index: j,
                        day_of_week: other.day_of_week,
                        start_time: other.start_time,
                        end_time: other.end_time,
                        message: `Sec ${row.section || "?"} has overlapping sessions on ${other.day_of_week} (${formatShortTime(other.start_time)}–${formatShortTime(other.end_time)}).`,
                    });
                }
            }

            // Room capacity check
            if (!isOnlineDeliveryMode(slot.delivery_mode) && slot.room_id !== null) {
                const room = roomsById.get(Number(slot.room_id));
                if (room && typeof room.capacity === "number" && room.capacity > 0 && row.maximum_slots > room.capacity) {
                    conflicts.push({
                        client_key: row.client_key,
                        kind: "capacity",
                        severity: "warning",
                        schedule_index: sIdx,
                        day_of_week: slot.day_of_week,
                        start_time: slot.start_time,
                        end_time: slot.end_time,
                        message: `Sec ${row.section}: ${room.label} capacity (${room.capacity}) is below ${row.maximum_slots} seats.`,
                    });
                }
            }
        });
    }

    // 3. Cross-section Room & Faculty overlaps
    for (let i = 0; i < rows.length; i += 1) {
        for (let j = i + 1; j < rows.length; j += 1) {
            const rowA = rows[i];
            const rowB = rows[j];
            rowA.schedules.forEach((slotA, idxA) => {
                rowB.schedules.forEach((slotB, idxB) => {
                    if (slotA.day_of_week !== slotB.day_of_week) return;
                    if (!timesOverlap(slotA.start_time, slotA.end_time, slotB.start_time, slotB.end_time)) return;

                    // Cross-section Room clash
                    const roomA = isOnlineDeliveryMode(slotA.delivery_mode) ? null : slotA.room_id;
                    const roomB = isOnlineDeliveryMode(slotB.delivery_mode) ? null : slotB.room_id;
                    if (checkRoom && roomA !== null && roomB !== null && Number(roomA) === Number(roomB)) {
                        const roomName = roomsById.get(Number(roomA))?.label ?? `Room #${roomA}`;
                        const msg = `${roomName} double-booked between Sec ${rowA.section || "?"} and Sec ${rowB.section || "?"} on ${slotA.day_of_week} (${formatShortTime(slotA.start_time)}–${formatShortTime(slotA.end_time)}).`;
                        conflicts.push({
                            client_key: rowA.client_key,
                            kind: "room",
                            severity: "blocking",
                            schedule_index: idxA,
                            day_of_week: slotA.day_of_week,
                            start_time: slotA.start_time,
                            end_time: slotA.end_time,
                            message: msg,
                        });
                        conflicts.push({
                            client_key: rowB.client_key,
                            kind: "room",
                            severity: "blocking",
                            schedule_index: idxB,
                            day_of_week: slotB.day_of_week,
                            start_time: slotB.start_time,
                            end_time: slotB.end_time,
                            message: msg,
                        });
                    }

                    // Cross-section Faculty clash
                    const skipFacultyAsync = !checkAsync && (isAsynchronousOnlineMode(slotA.delivery_mode) || isAsynchronousOnlineMode(slotB.delivery_mode));
                    if (checkFaculty && !skipFacultyAsync && rowA.faculty_id && rowB.faculty_id && rowA.faculty_id === rowB.faculty_id) {
                        const facName = facultiesById.get(rowA.faculty_id)?.label ?? "Instructor";
                        const msg = `${facName} double-booked between Sec ${rowA.section || "?"} and Sec ${rowB.section || "?"} on ${slotA.day_of_week} (${formatShortTime(slotA.start_time)}–${formatShortTime(slotA.end_time)}).`;
                        conflicts.push({
                            client_key: rowA.client_key,
                            kind: "faculty",
                            severity: "blocking",
                            schedule_index: idxA,
                            day_of_week: slotA.day_of_week,
                            start_time: slotA.start_time,
                            end_time: slotA.end_time,
                            message: msg,
                        });
                        conflicts.push({
                            client_key: rowB.client_key,
                            kind: "faculty",
                            severity: "blocking",
                            schedule_index: idxB,
                            day_of_week: slotB.day_of_week,
                            start_time: slotB.start_time,
                            end_time: slotB.end_time,
                            message: msg,
                        });
                    }
                });
            });
        }
    }

    // 4. Projected faculty overload warnings (> max_load_hours or 24h)
    for (const [facId, addedHours] of hasTermLoad ? facultyDeltas.entries() : []) {
        const fac = facultiesById.get(facId);
        if (typeof fac?.load_hours !== "number") continue;
        const base = fac.load_hours;
        const max = fac?.max_load_hours ?? 24;
        const projected = Number((base + addedHours).toFixed(1));
        if (projected > max) {
            const firstRow = rows.find((r) => r.faculty_id === facId);
            if (firstRow) {
                conflicts.push({
                    client_key: firstRow.client_key,
                    kind: "faculty_load",
                    severity: "warning",
                    schedule_index: null,
                    message: `${fac?.label ?? "Instructor"} projected load reaches ${projected}h (exceeds ${max}h limit).`,
                });
            }
        }
    }

    return conflicts;
}

export function BlueprintMatrixWorkbench({
    semester,
    school_year,
    options,
    initialBlueprint = null,
    siblingSections = [],
    preloadedSections = [],
}: BlueprintMatrixWorkbenchProps): ReactNode {
    const [blueprint, setBlueprint] = useState<BlueprintSharedFields>(() => ({
        classification: initialBlueprint?.classification ?? "college",
        course_codes: initialBlueprint?.course_codes ?? [],
        subject_ids: initialBlueprint?.subject_ids ?? [],
        subject_code: initialBlueprint?.subject_code ?? "",
        academic_year: initialBlueprint?.academic_year ?? 1,
        shs_track_id: initialBlueprint?.shs_track_id ?? null,
        shs_strand_id: initialBlueprint?.shs_strand_id ?? null,
        subject_code_shs: initialBlueprint?.subject_code_shs ?? "",
        grade_level: initialBlueprint?.grade_level ?? "Grade 11",
        school_year: initialBlueprint?.school_year ?? school_year,
        semester: initialBlueprint?.semester ?? semester,
        start_date: initialBlueprint?.start_date ?? null,
        delivery_mode: canonicalDeliveryMode(initialBlueprint?.delivery_mode ?? "face_to_face"),
        settings: {
            ...DEFAULT_POLICY_SETTINGS,
            enable_announcements: initialBlueprint?.settings?.enable_announcements ?? DEFAULT_POLICY_SETTINGS.enable_announcements,
            enable_grade_visibility: initialBlueprint?.settings?.enable_grade_visibility ?? DEFAULT_POLICY_SETTINGS.enable_grade_visibility,
            enable_attendance_tracking: initialBlueprint?.settings?.enable_attendance_tracking ?? DEFAULT_POLICY_SETTINGS.enable_attendance_tracking,
            enable_online_resources: initialBlueprint?.settings?.enable_online_resources ?? DEFAULT_POLICY_SETTINGS.enable_online_resources,
            allow_late_submissions: initialBlueprint?.settings?.allow_late_submissions ?? DEFAULT_POLICY_SETTINGS.allow_late_submissions,
            enable_discussion_board: initialBlueprint?.settings?.enable_discussion_board ?? DEFAULT_POLICY_SETTINGS.enable_discussion_board,
            notify_students_on_schedule_changes:
                initialBlueprint?.settings?.notify_students_on_schedule_changes ?? DEFAULT_POLICY_SETTINGS.notify_students_on_schedule_changes,
        },
    }));

    const [subjectCodeTouched, setSubjectCodeTouched] = useState(Boolean(initialBlueprint?.subject_code));
    const [collegeSubjects, setCollegeSubjects] = useState<SubjectOption[]>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = useState(false);
    const [loadedCourseKey, setLoadedCourseKey] = useState<string | null>(null);
    const [shsStrandList, setShsStrandList] = useState<EntityOption[]>([]);
    const [shsStrandsLoading, setShsStrandsLoading] = useState(false);
    const [loadedTrackId, setLoadedTrackId] = useState<number | null>(null);
    const [strandError, setStrandError] = useState(false);
    const [strandRetry, setStrandRetry] = useState(0);
    const [shsSubjectList, setShsSubjectList] = useState<ShsSubjectOption[]>([]);
    const [shsSubjectsLoading, setShsSubjectsLoading] = useState(false);
    const [loadedStrandId, setLoadedStrandId] = useState<number | null>(null);
    const [subjectError, setSubjectError] = useState(false);
    const [subjectRetry, setSubjectRetry] = useState(0);
    const [curriculumError, setCurriculumError] = useState<string | null>(null);

    const [rows, setRows] = useState<MatrixSectionRow[]>(() =>
        buildStarterSections(preloadedSections, siblingSections, initialBlueprint, options),
    );
    const [notifyEnrolled, setNotifyEnrolled] = useState(true);
    const [hoveredRowKey, setHoveredRowKey] = useState<string | null>(null);

    // Dry-run & commit state
    const [dryRunState, setDryRunState] = useState<"idle" | "checking" | "verified" | "rejected" | "fallback">("idle");
    const [serverConflicts, setServerConflicts] = useState<BatchBlueprintConflict[]>([]);
    const [serverSignature, setServerSignature] = useState<string | null>(null);
    const [submittingMode, setSubmittingMode] = useState<"draft" | "published" | null>(null);
    const [commitResult, setCommitResult] = useState<BatchBlueprintResponse | null>(null);
    const [commitError, setCommitError] = useState<string | null>(null);

    const subjectReqRef = useRef(0);
    const dryRunReqRef = useRef(0);

    const roomsById = useMemo(() => {
        const map = new Map<number, { label: string; capacity?: number | null; type?: string | null }>();
        for (const room of options.rooms) {
            map.set(Number(room.id), { label: room.label, capacity: room.capacity, type: room.type });
        }
        return map;
    }, [options.rooms]);

    const facultiesById = useMemo(() => {
        const map = new Map<string, { label: string; load_hours?: number; max_load_hours?: number | null }>();
        for (const fac of options.faculties) {
            map.set(String(fac.id), { label: fac.label, load_hours: fac.load_hours, max_load_hours: fac.max_load_hours });
        }
        return map;
    }, [options.faculties]);

    // Fetch College subjects whenever course_codes changes
    const courseKey = blueprint.course_codes.join(",");
    useEffect(() => {
        const reqId = ++subjectReqRef.current;
        if (blueprint.classification !== "college") {
            setCollegeSubjectsLoading(false);
            setLoadedCourseKey(null);
            return;
        }

        if (blueprint.course_codes.length === 0) {
            setCollegeSubjects([]);
            setCollegeSubjectsLoading(false);
            setLoadedCourseKey(null);
            return;
        }

        setCollegeSubjectsLoading(true);
        setCurriculumError(null);
        fetchOptionList<SubjectOption>(subjectOptionsRoute.url({ query: { course_ids: blueprint.course_codes } }), isSubjectOption)
            .then((list) => {
                if (reqId !== subjectReqRef.current) return;
                setCollegeSubjects(list);
                setLoadedCourseKey(courseKey);
                setBlueprint((prev) => {
                    const kept = prev.subject_ids.filter((id) => list.some((s) => s.id === id));
                    const selected = list.filter((s) => kept.includes(s.id));
                    const years = Array.from(
                        new Set(selected.map((s) => s.academic_year).filter((y): y is number => y !== null)),
                    ).sort((a, b) => a - b);

                    let nextAcademicYear = prev.academic_year;
                    if (selected.length > 0 && selected.every((s) => s.academic_year !== null) && years.length === 1) {
                        nextAcademicYear = years[0];
                    } else if (years.length > 1 && !years.includes(prev.academic_year)) {
                        nextAcademicYear = years[0];
                    }

                    const nextCode =
                        !subjectCodeTouched && kept.length !== prev.subject_ids.length
                            ? buildSubjectCodeFromSubjectOptions(kept, list)
                            : prev.subject_code;

                    if (kept.length === prev.subject_ids.length && nextAcademicYear === prev.academic_year && nextCode === prev.subject_code) {
                        return prev;
                    }
                    return {
                        ...prev,
                        subject_ids: kept,
                        academic_year: nextAcademicYear,
                        subject_code: nextCode,
                    };
                });
            })
            .catch(() => {
                if (reqId === subjectReqRef.current) {
                    setLoadedCourseKey(null);
                    setCurriculumError("Subjects could not be loaded. Check your connection and try again.");
                }
            })
            .finally(() => {
                if (reqId === subjectReqRef.current) setCollegeSubjectsLoading(false);
            });
        return () => {
            if (reqId === subjectReqRef.current) subjectReqRef.current += 1;
        };
    }, [blueprint.classification, courseKey, subjectCodeTouched]);

    // Fetch SHS strands whenever track changes
    useEffect(() => {
        if (blueprint.classification !== "shs" || !blueprint.shs_track_id) {
            setShsStrandList([]);
            setLoadedTrackId(null);
            return;
        }
        let active = true;
        const trackId = blueprint.shs_track_id;
        setShsStrandList([]);
        setLoadedTrackId(null);
        setStrandError(false);
        setShsStrandsLoading(true);
        fetchOptionList<EntityOption>(shsStrands.url({ query: { track_id: blueprint.shs_track_id } }), isEntityOption)
            .then((list) => {
                if (active) {
                    setShsStrandList(list);
                    setLoadedTrackId(trackId);
                }
            })
            .catch(() => {
                if (active) setStrandError(true);
            })
            .finally(() => {
                if (active) setShsStrandsLoading(false);
            });
        return () => {
            active = false;
        };
    }, [blueprint.classification, blueprint.shs_track_id, strandRetry]);

    // Fetch SHS subjects whenever strand changes
    useEffect(() => {
        if (blueprint.classification !== "shs" || !blueprint.shs_strand_id) {
            setShsSubjectList([]);
            setLoadedStrandId(null);
            return;
        }
        let active = true;
        const strandId = blueprint.shs_strand_id;
        setShsSubjectList([]);
        setLoadedStrandId(null);
        setSubjectError(false);
        setShsSubjectsLoading(true);
        fetchOptionList<ShsSubjectOption>(shsSubjects.url({ query: { strand_id: blueprint.shs_strand_id } }), isShsSubjectOption)
            .then((list) => {
                if (active) {
                    setShsSubjectList(list);
                    setLoadedStrandId(strandId);
                }
            })
            .catch(() => {
                if (active) setSubjectError(true);
            })
            .finally(() => {
                if (active) setShsSubjectsLoading(false);
            });
        return () => {
            active = false;
        };
    }, [blueprint.classification, blueprint.shs_strand_id, subjectRetry]);

    // Required Lec / Lab / Total hours computed from selected subject(s)
    const requiredHours = useMemo(() => {
        if (blueprint.classification === "college") {
            const selected = collegeSubjects.filter((s) => blueprint.subject_ids.includes(s.id));
            if (selected.length === 0) return { lec: 0, lab: 0, units: 0, total: null as number | null };
            const lec = selected.reduce((sum, s) => sum + (Number(s.lecture) || 0), 0);
            const lab = selected.reduce((sum, s) => sum + (Number(s.laboratory) || 0), 0);
            const units = selected.reduce((sum, s) => sum + (Number(s.units) || 0), 0);
            const total = lec + lab > 0 ? lec + lab : units > 0 ? units : null;
            return { lec, lab, units, total };
        }

        const matchedShs = shsSubjectList.find((s) => s.code === blueprint.subject_code_shs);
        if (!matchedShs) return { lec: 0, lab: 0, units: 0, total: null as number | null };
        const lec = Number(matchedShs.lecture) || 0;
        const lab = Number(matchedShs.laboratory) || 0;
        const units = Number(matchedShs.units) || 0;
        const total = lec + lab > 0 ? lec + lab : units > 0 ? units : null;
        return { lec, lab, units, total };
    }, [blueprint.classification, blueprint.subject_code_shs, blueprint.subject_ids, collegeSubjects, shsSubjectList]);

    // Blueprint completeness check
    const blueprintMissingReason = useMemo<string | null>(() => {
        if (siblingSections.some((sibling) => !preloadedSections.some((section) => section.class_id === sibling.id))) {
            return "Existing section details are unavailable. Reload before saving to avoid changing their schedules.";
        }
        if (!blueprint.school_year.trim() || !blueprint.semester) {
            return "Confirm the academic term and school year.";
        }
        if (blueprint.classification === "college") {
            if (blueprint.course_codes.length === 0) return "Select at least one course program.";
            if (collegeSubjectsLoading || loadedCourseKey !== courseKey) return "Wait for subjects to load for the selected programs.";
            if (blueprint.subject_ids.length === 0) return "Select at least one college subject.";
            if (blueprint.subject_ids.some((id) => !collegeSubjects.some((subject) => subject.id === id))) return "Choose subjects from the selected programs.";
            if (!blueprint.subject_code.trim()) return "Provide a subject code.";
        } else {
            if (!blueprint.shs_track_id) return "Select an SHS track.";
            if (!blueprint.shs_strand_id) return "Select an SHS strand.";
            if (loadedTrackId !== blueprint.shs_track_id || !shsStrandList.some((strand) => Number(strand.id) === blueprint.shs_strand_id)) return "Select a strand from the chosen track.";
            if (!blueprint.subject_code_shs.trim()) return "Select an SHS subject.";
            if (loadedStrandId !== blueprint.shs_strand_id || !shsSubjectList.some((subject) => subject.code === blueprint.subject_code_shs)) return "Select a subject from the chosen strand.";
            if (!blueprint.grade_level.trim()) return "Select a grade level.";
        }
        if (rows.length === 0) return "Add at least one section.";
        return null;
    }, [blueprint, rows.length, siblingSections, preloadedSections, collegeSubjectsLoading, loadedCourseKey, courseKey, collegeSubjects, loadedTrackId, loadedStrandId, shsStrandList, shsSubjectList]);

    const isBlueprintReady = blueprintMissingReason === null;

    // Build payload helper
    const buildRequestPayload = useCallback(
        (dryRun: boolean, scheduleStatus: "draft" | "published") => {
            const sectionsPayload: BlueprintSection[] = rows.map((row) => ({
                client_key: row.client_key,
                class_id: row.class_id,
                section: row.section.trim(),
                faculty_id: row.faculty_id,
                maximum_slots: row.maximum_slots,
                room_id: isOnlineDeliveryMode(blueprint.delivery_mode) ? null : row.room_id,
                schedules: row.schedules.map((s) => {
                    const mode = canonicalDeliveryMode(s.delivery_mode || blueprint.delivery_mode);
                    return {
                        id: s.id,
                        day_of_week: s.day_of_week,
                        start_time: s.start_time,
                        end_time: s.end_time,
                        room_id: isOnlineDeliveryMode(mode) ? null : s.room_id,
                        delivery_mode: mode,
                        component_type: s.component_type === "lab" ? "lab" : "lecture",
                        status: scheduleStatus,
                    };
                }),
            }));

            return {
                blueprint: {
                    ...blueprint,
                    start_date: blueprint.start_date || null,
                    delivery_mode: canonicalDeliveryMode(blueprint.delivery_mode),
                },
                sections: sectionsPayload,
                dry_run: dryRun,
                schedule_status: scheduleStatus,
                notify_enrolled: notifyEnrolled,
            };
        },
        [blueprint, notifyEnrolled, rows],
    );

    const payloadSignature = useMemo(() => JSON.stringify({ blueprint, rows, notifyEnrolled }), [blueprint, rows, notifyEnrolled]);
    const currentDryRunState = !isBlueprintReady ? "idle" : serverSignature === payloadSignature ? dryRunState : "checking";

    // Run dry-run preflight against server
    const runDryRunCheck = useCallback(
        async (manual = false) => {
            if (!isBlueprintReady) {
                setServerConflicts([]);
                setDryRunState("idle");
                return;
            }
            const reqId = ++dryRunReqRef.current;
            setDryRunState("checking");
            try {
                const res = await postBatchBlueprint(buildRequestPayload(true, "published"));
                if (reqId !== dryRunReqRef.current) return;
                setServerConflicts(Array.isArray(res.conflicts) ? res.conflicts : []);
                setServerSignature(payloadSignature);
                setDryRunState(res.ok ? "verified" : "rejected");
                if (!res.ok) {
                    setCommitResult(res);
                    setCommitError(res.message || "Review the blueprint and section errors.");
                } else {
                    setCommitResult((previous) => previous?.dry_run ? null : previous);
                    setCommitError(null);
                }
                if (manual) {
                    const blocking = (res.conflicts ?? []).filter((c) => c.severity === "blocking").length;
                    if (res.ok && blocking === 0) {
                        toast.success("Dry-run check complete: all sections are clear.");
                    } else if (blocking === 0) {
                        toast.warning("Dry-run found blueprint errors. Review the blueprint settings.");
                    } else {
                        toast.warning(`Dry-run found ${blocking} blocking conflict${blocking === 1 ? "" : "s"}.`);
                    }
                }
            } catch {
                if (reqId !== dryRunReqRef.current) return;
                setServerSignature(payloadSignature);
                setDryRunState("fallback");
                if (manual) {
                    toast.info("Server dry-run unavailable right now; using live client conflict checks.");
                }
            }
        },
        [buildRequestPayload, isBlueprintReady, payloadSignature],
    );

    // Debounced auto dry-run (450ms) whenever blueprint or sections change
    useEffect(() => {
        ++dryRunReqRef.current;
        setServerConflicts([]);
        setCommitResult((previous) => previous && !previous.ok ? null : previous);
        setCommitError(null);
        if (!isBlueprintReady) {
            setDryRunState("idle");
            return;
        }
        setDryRunState("checking");
        const timer = window.setTimeout(() => {
            void runDryRunCheck(false);
        }, 450);
        return () => {
            window.clearTimeout(timer);
            ++dryRunReqRef.current;
        };
    }, [isBlueprintReady, payloadSignature, runDryRunCheck]);

    // Client-side instant conflict detection + server conflict merge
    const hasTermLoad = blueprint.semester === semester && blueprint.school_year.replace(/\s/g, "") === school_year.replace(/\s/g, "");
    const batchHoursByFaculty = useMemo(() => facultyLoadDeltas(rows, preloadedSections), [rows, preloadedSections]);
    const clientConflicts = useMemo(
        () => detectClientMatrixConflicts(rows, options, roomsById, facultiesById, batchHoursByFaculty, hasTermLoad),
        [facultiesById, options, roomsById, rows, batchHoursByFaculty, hasTermLoad],
    );

    const allConflicts = useMemo<BatchBlueprintConflict[]>(() => {
        const merged: BatchBlueprintConflict[] = [];
        const seen = new Set<string>();
        const pushUnique = (item: BatchBlueprintConflict) => {
            const key = `${item.client_key}|${item.kind}|${item.severity}|${item.schedule_index ?? "n"}|${item.day_of_week ?? ""}|${item.start_time ?? ""}|${item.end_time ?? ""}|${item.message}`;
            if (!seen.has(key)) {
                seen.add(key);
                merged.push(item);
            }
        };
        if (serverSignature === payloadSignature) serverConflicts.forEach(pushUnique);
        clientConflicts.forEach(pushUnique);
        return merged;
    }, [clientConflicts, serverConflicts, serverSignature, payloadSignature]);

    const blockingConflicts = useMemo(() => allConflicts.filter((c) => c.severity === "blocking"), [allConflicts]);
    const warningConflicts = useMemo(() => allConflicts.filter((c) => c.severity === "warning"), [allConflicts]);

    const conflictsByRowKey = useMemo(() => {
        const map = new Map<string, BatchBlueprintConflict[]>();
        for (const c of allConflicts) {
            const list = map.get(c.client_key) ?? [];
            list.push(c);
            map.set(c.client_key, list);
        }
        return map;
    }, [allConflicts]);

    // Single-source-of-truth reactive totals
    const totals = useMemo(() => {
        const totalSections = rows.length;
        const totalSeats = rows.reduce((sum, r) => sum + (Number.isFinite(r.maximum_slots) ? r.maximum_slots : 0), 0);
        const existingCount = rows.filter((r) => r.class_id !== null).length;
        const newCount = totalSections - existingCount;
        const scheduledHours = Number(
            rows.reduce((sum, r) => sum + r.schedules.reduce((s, slot) => s + slotHours(slot), 0), 0).toFixed(1),
        );
        const requiredTotalAcrossSections = requiredHours.total !== null ? Number((requiredHours.total * totalSections).toFixed(1)) : null;
        return {
            totalSections,
            totalSeats,
            existingCount,
            newCount,
            scheduledHours,
            requiredTotalAcrossSections,
            blockingCount: blockingConflicts.length,
            warningCount: warningConflicts.length,
        };
    }, [blockingConflicts.length, requiredHours.total, rows, warningConflicts.length]);

    // Staged blocks for LiveBusyGrid
    const { gridBlocks, gridConflicts } = useMemo(() => {
        const blocks: LiveBusyGridBlock[] = [];
        const mappedConflicts: PreflightConflict[] = [];
        const visibleRows = hoveredRowKey ? rows.filter((r) => r.client_key === hoveredRowKey) : rows;

        for (const row of visibleRows) {
            const rowConflicts = conflictsByRowKey.get(row.client_key) ?? [];
            row.schedules.forEach((slot, sIdx) => {
                const blockIndex = blocks.length;
                const slotConflicts = rowConflicts.filter((c) => c.schedule_index === sIdx);
                const hasBlocking = slotConflicts.some((c) => c.severity === "blocking");
                const typeTag = slot.component_type === "lab" ? "Lab" : "Lec";
                const roomName = slot.room_id ? (roomsById.get(Number(slot.room_id))?.label ?? null) : null;

                blocks.push({
                    ...slot,
                    label: `Sec ${row.section || "?"} · ${typeTag}`,
                    section: row.section,
                    room_name: isOnlineDeliveryMode(slot.delivery_mode) ? deliveryModeShortLabel(slot.delivery_mode) : roomName,
                    has_conflict: hasBlocking,
                });

                for (const c of slotConflicts) {
                    mappedConflicts.push({
                        ...c,
                        schedule_index: blockIndex,
                    });
                }
            });
        }

        return { gridBlocks: blocks, gridConflicts: mappedConflicts };
    }, [conflictsByRowKey, hoveredRowKey, roomsById, rows]);

    // Row mutations
    const updateRow = (clientKey: string, updater: (row: MatrixSectionRow) => MatrixSectionRow) => {
        setCommitResult(null);
        setCommitError(null);
        setRows((prev) => prev.map((row) => (row.client_key === clientKey ? updater(row) : row)));
    };

    const handleAddSection = () => {
        const nextCode = nextSectionCode(rows.map((r) => r.section));
        const defaultMode = canonicalDeliveryMode(blueprint.delivery_mode);
        const band = STAGGER_BANDS[rows.length % STAGGER_BANDS.length];
        setCommitResult(null);
        setRows((prev) => [
            ...prev,
            {
                client_key: `new-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
                class_id: null,
                section: nextCode,
                faculty_id: null,
                maximum_slots: 40,
                room_id: null,
                schedules: band.days.map((day) => ({
                    day_of_week: day,
                    start_time: band.start_time,
                    end_time: band.end_time,
                    room_id: null,
                    delivery_mode: defaultMode,
                    component_type: "lecture",
                })),
                capacity_input: "40",
            },
        ]);
    };

    const handleDuplicateRow = (source: MatrixSectionRow) => {
        const nextCode = nextSectionCode(rows.map((r) => r.section));
        setCommitResult(null);
        setRows((prev) => [
            ...prev,
            {
                client_key: `dup-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
                class_id: null,
                section: nextCode,
                faculty_id: source.faculty_id,
                maximum_slots: source.maximum_slots,
                room_id: source.room_id,
                schedules: source.schedules.map((s) => ({ ...s, id: undefined })),
                capacity_input: String(source.maximum_slots),
            },
        ]);
        toast.info(`Duplicated Sec ${source.section} as Sec ${nextCode}. Use Auto-Stagger to avoid overlapping slots.`);
    };

    const handleRemoveRow = (clientKey: string) => {
        setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.client_key !== clientKey) : prev));
    };

    const handleAutoStagger = () => {
        const defaultMode = canonicalDeliveryMode(blueprint.delivery_mode);
        const isOnline = isOnlineDeliveryMode(defaultMode);
        const needsLab = requiredHours.lab > 0;

        // Track occupied room/faculty slots from preserved existing rows
        const usedRoomSlots: Array<{ room_id: number; day: string; start: string; end: string }> = [];
        const usedFacultySlots: Array<{ faculty_id: string; day: string; start: string; end: string }> = [];

        const isSlotFree = (day: string, start: string, end: string, roomId: number | null, facultyId: string | null) => {
            if (roomId !== null && usedRoomSlots.some((u) => u.room_id === roomId && u.day === day && timesOverlap(u.start, u.end, start, end))) {
                return false;
            }
            if (
                facultyId !== null &&
                usedFacultySlots.some((u) => u.faculty_id === facultyId && u.day === day && timesOverlap(u.start, u.end, start, end))
            ) {
                return false;
            }
            return true;
        };

        const pickBestRoom = (capacity: number, days: string[], start: string, end: string, preferLab: boolean): number | null => {
            if (isOnline || options.rooms.length === 0) return null;
            const sortedRooms = [...options.rooms].sort((a, b) => {
                const aIsLab = (a.type ?? "").toLowerCase().includes("lab") || a.label.toLowerCase().includes("lab");
                const bIsLab = (b.type ?? "").toLowerCase().includes("lab") || b.label.toLowerCase().includes("lab");
                if (preferLab && aIsLab !== bIsLab) return aIsLab ? -1 : 1;
                if (!preferLab && aIsLab !== bIsLab) return aIsLab ? 1 : -1;
                const aFits = !a.capacity || a.capacity >= capacity;
                const bFits = !b.capacity || b.capacity >= capacity;
                if (aFits !== bFits) return aFits ? -1 : 1;
                return (a.capacity ?? 999) - (b.capacity ?? 999);
            });

            for (const room of sortedRooms) {
                const id = Number(room.id);
                if (days.every((day) => isSlotFree(day, start, end, id, null))) {
                    return id;
                }
            }
            return null;
        };

        const rowHasBlocking = new Set(blockingConflicts.map((c) => c.client_key));
        let shiftedCount = 0;
        const skippedSections: string[] = [];

        // First register schedules of clean existing rows so we don't move them
        for (const row of rows) {
            if (row.class_id !== null && row.schedules.length > 0 && !rowHasBlocking.has(row.client_key)) {
                for (const s of row.schedules) {
                    if (!isOnlineDeliveryMode(s.delivery_mode) && s.room_id !== null) {
                        usedRoomSlots.push({ room_id: Number(s.room_id), day: s.day_of_week, start: s.start_time, end: s.end_time });
                    }
                    if (row.faculty_id) {
                        usedFacultySlots.push({ faculty_id: row.faculty_id, day: s.day_of_week, start: s.start_time, end: s.end_time });
                    }
                }
            }
        }

        const nextRows = rows.map((row, idx) => {
            const shouldStagger = row.class_id === null || row.schedules.length === 0 || rowHasBlocking.has(row.client_key);
            if (!shouldStagger) return row;

            // Find first clean lecture band
            let chosenBand: (typeof STAGGER_BANDS)[number] | null = null;
            let chosenRoom: number | null = isOnline ? null : row.room_id;

            for (let attempt = 0; attempt < STAGGER_BANDS.length; attempt += 1) {
                const candidate = STAGGER_BANDS[(idx + attempt) % STAGGER_BANDS.length];
                const facFree = candidate.days.every((day) => isSlotFree(day, candidate.start_time, candidate.end_time, null, row.faculty_id));
                if (!facFree) continue;

                const roomCandidate =
                    !isOnline && row.room_id !== null && candidate.days.every((d) => isSlotFree(d, candidate.start_time, candidate.end_time, row.room_id, null))
                        ? row.room_id
                        : pickBestRoom(row.maximum_slots, candidate.days, candidate.start_time, candidate.end_time, false);

                if (isOnline || roomCandidate !== null || options.rooms.length === 0) {
                    chosenBand = candidate;
                    chosenRoom = roomCandidate;
                    break;
                }
            }

            if (!chosenBand) {
                skippedSections.push(row.section || "(unnamed)");
                return row;
            }

            const roomCountBefore = usedRoomSlots.length;
            const facultyCountBefore = usedFacultySlots.length;

            const newSchedules: CrudScheduleBlock[] = chosenBand.days.map((day) => ({
                day_of_week: day,
                start_time: chosenBand.start_time,
                end_time: chosenBand.end_time,
                room_id: isOnline ? null : chosenRoom,
                delivery_mode: defaultMode,
                component_type: "lecture",
            }));

            for (const s of newSchedules) {
                if (s.room_id !== null) {
                    usedRoomSlots.push({ room_id: Number(s.room_id), day: s.day_of_week, start: s.start_time, end: s.end_time });
                }
                if (row.faculty_id) {
                    usedFacultySlots.push({ faculty_id: row.faculty_id, day: s.day_of_week, start: s.start_time, end: s.end_time });
                }
            }

            if (needsLab) {
                let labBand: (typeof LAB_STAGGER_BANDS)[number] | null = null;
                let labRoom: number | null = null;
                for (let attempt = 0; attempt < LAB_STAGGER_BANDS.length; attempt += 1) {
                    const candidate = LAB_STAGGER_BANDS[(idx + attempt) % LAB_STAGGER_BANDS.length];
                    if (!isSlotFree(candidate.day, candidate.start_time, candidate.end_time, null, row.faculty_id)) continue;
                    if (newSchedules.some((slot) => slot.day_of_week === candidate.day && timesOverlap(slot.start_time, slot.end_time, candidate.start_time, candidate.end_time))) continue;
                    const roomCandidate = pickBestRoom(row.maximum_slots, [candidate.day], candidate.start_time, candidate.end_time, true);
                    if (isOnline || roomCandidate !== null || options.rooms.length === 0) {
                        labBand = candidate;
                        labRoom = roomCandidate;
                        break;
                    }
                }
                if (!labBand) {
                    usedRoomSlots.length = roomCountBefore;
                    usedFacultySlots.length = facultyCountBefore;
                    skippedSections.push(row.section || "(unnamed)");
                    return row;
                }
                const labSlot: CrudScheduleBlock = {
                    day_of_week: labBand.day,
                    start_time: labBand.start_time,
                    end_time: labBand.end_time,
                    room_id: isOnline ? null : labRoom,
                    delivery_mode: defaultMode,
                    component_type: "lab",
                };
                newSchedules.push(labSlot);
                if (labSlot.room_id !== null) {
                    usedRoomSlots.push({ room_id: Number(labSlot.room_id), day: labSlot.day_of_week, start: labSlot.start_time, end: labSlot.end_time });
                }
                if (row.faculty_id) {
                    usedFacultySlots.push({ faculty_id: row.faculty_id, day: labSlot.day_of_week, start: labSlot.start_time, end: labSlot.end_time });
                }
            }

            shiftedCount += 1;
            return {
                ...row,
                room_id: isOnline ? null : (chosenRoom ?? row.room_id),
                schedules: newSchedules,
            };
        });

        setRows(nextRows);
        if (skippedSections.length > 0) {
            toast.warning(`Auto-staggered ${shiftedCount} sections; ${skippedSections.join(", ")} need manual placement and remain unchanged.`);
        } else {
            toast.success(shiftedCount > 0 ? `Auto-staggered ${shiftedCount} sections.` : "All existing sections are already conflict-free.");
        }
    };

    // Atomic commit (Draft or Publish)
    const handleCommit = async (scheduleStatus: "draft" | "published") => {
        if (!isBlueprintReady) {
            toast.error(blueprintMissingReason ?? "Complete the subject blueprint first.");
            return;
        }
        if (scheduleStatus === "published" && totals.blockingCount > 0) {
            toast.error("Resolve all blocking conflicts before publishing.");
            return;
        }
        if (scheduleStatus === "published" && currentDryRunState !== "verified") {
            toast.error("Wait for a successful current schedule check before publishing.");
            return;
        }

        setSubmittingMode(scheduleStatus);
        setCommitError(null);
        setCommitResult(null);

        try {
            const response = await postBatchBlueprint(buildRequestPayload(false, scheduleStatus));
            setCommitResult(response);
            setServerConflicts(Array.isArray(response.conflicts) ? response.conflicts : []);
            setServerSignature(payloadSignature);

            // Hydrate newly created class_ids back into rows so Dossier links appear immediately
            if (Array.isArray(response.results) && response.results.length > 0) {
                const byClientKey = new Map<string, BatchBlueprintResult>(response.results.map((r) => [r.client_key, r]));
                setRows((prev) =>
                    prev.map((row) => {
                        const match = byClientKey.get(row.client_key);
                        if (match && match.class_id !== null) {
                            return { ...row, class_id: match.class_id };
                        }
                        return row;
                    }),
                );
            }

            if (response.ok) {
                toast.success(
                    response.message ||
                        (scheduleStatus === "published"
                            ? "All sections published successfully."
                            : "All sections saved as drafts."),
                );
            } else {
                setCommitError(response.message || "Some sections could not be saved. Review the conflicts below.");
                toast.error(response.message || "Batch save encountered issues.");
            }
        } catch (error) {
            if (error instanceof WorkspaceApiError && error.status === 422) {
                const payload = error.payload;
                if (Array.isArray(payload.conflicts)) {
                    setServerConflicts(payload.conflicts as BatchBlueprintConflict[]);
                    setServerSignature(payloadSignature);
                }
                if (Array.isArray(payload.results)) {
                    setCommitResult(payload as BatchBlueprintResponse);
                }
                setDryRunState("fallback");
            }
            const message = error instanceof Error ? error.message : "Failed to save batch blueprint.";
            setCommitError(message);
            toast.error(message);
        } finally {
            setSubmittingMode(null);
        }
    };

    const courseSelectOptions = useMemo(
        () => options.courses.map((c) => ({ label: c.label, value: String(c.id) })),
        [options.courses],
    );
    const subjectSelectOptions = useMemo(
        () =>
            collegeSubjects.map((s) => ({
                label: s.label,
                value: String(s.id),
                description: `${s.course_code ?? "Program"}${s.academic_year ? ` · Year ${s.academic_year}` : ""}${
                    s.units ? ` · ${s.units}u` : ""
                }`,
                searchText: `${s.code} ${s.label}`,
            })),
        [collegeSubjects],
    );

    const activePolicyCount = useMemo(() => {
        const s = blueprint.settings ?? DEFAULT_POLICY_SETTINGS;
        return POLICY_ITEMS.filter((item) => Boolean(s[item.key])).length;
    }, [blueprint.settings]);

    const createdCount = commitResult?.results.filter((r) => r.status === "created").length ?? 0;
    const updatedCount = commitResult?.results.filter((r) => r.status === "updated").length ?? 0;

    return (
        <TooltipProvider delay={150}>
            <div className="flex flex-col gap-5 pb-20">
                {/* Result banner after atomic commit */}
                {commitResult?.ok ? (
                    <Alert className="border-emerald-500/40 bg-emerald-500/10 text-emerald-950 dark:text-emerald-100">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle className="flex flex-wrap items-center justify-between gap-2 font-semibold">
                            <span>
                                {commitResult.message || `Batch committed: ${createdCount} created, ${updatedCount} updated.`}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={<Link href={classesIndex.url()} />}
                                className="h-7 gap-1 border-emerald-600/30 bg-background text-xs font-semibold"
                            >
                                Back to Classes
                            </Button>
                        </AlertTitle>
                        <AlertDescription className="mt-2 flex flex-wrap items-center gap-2">
                            {commitResult.results
                                .filter((r) => r.class_id !== null)
                                .map((r) => (
                                    <Link
                                        key={r.client_key}
                                        href={showClass.url(r.class_id!)}
                                        className="inline-flex items-center gap-1 rounded-md border border-emerald-600/30 bg-background px-2.5 py-1 text-xs font-semibold text-foreground transition-colors hover:border-emerald-600 hover:text-emerald-700 dark:hover:text-emerald-300"
                                    >
                                        <span>Sec {r.section}</span>
                                        <span className="text-muted-foreground font-normal capitalize">({r.status})</span>
                                        <ArrowUpRight className="size-3" aria-hidden="true" />
                                    </Link>
                                ))}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {commitError ? (
                    <Alert variant="destructive">
                        <ShieldAlert className="size-4" />
                        <AlertTitle>Batch save blocked</AlertTitle>
                        <AlertDescription>{commitError}</AlertDescription>
                        {commitResult?.results.some((result) => result.errors.length > 0) ? (
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-xs">
                                {commitResult.results.flatMap((result) => result.errors.map((message, index) => (
                                    <li key={`${result.client_key}-${index}`}>Section {result.section}: {message}</li>
                                )))}
                            </ul>
                        ) : null}
                    </Alert>
                ) : null}

                <fieldset disabled={submittingMode !== null} className="flex min-w-0 flex-col gap-5">
                {/* 1. 5-Column Shared Subject Blueprint Bar */}
                <section className="bg-card overflow-hidden rounded-2xl border shadow-xs" aria-label="Shared Subject Blueprint">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/35 px-4 py-2.5">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <span className="size-2 rounded-full bg-emerald-500" aria-hidden="true" />
                            <span>Shared Subject Blueprint — Configured once, inherited by all sections</span>
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            {currentDryRunState === "checking" ? (
                                <span className="inline-flex items-center gap-1.5 text-primary font-medium">
                                    <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                                    Checking term conflicts…
                                </span>
                            ) : currentDryRunState === "verified" ? (
                                <span className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400 font-medium">
                                    <CheckCircle2 className="size-3" aria-hidden="true" />
                                    Term preflight synced
                                </span>
                            ) : currentDryRunState === "rejected" ? (
                                <span className="text-destructive">Resolve blueprint or schedule errors</span>
                            ) : currentDryRunState === "fallback" ? (
                                <span className="text-muted-foreground">Live local conflict preview</span>
                            ) : (
                                <span>Select subject to enable live term preflight</span>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 [&>div]:border-b [&>div]:border-border">
                        {/* Col 1: Level & Programs / Track */}
                        <div className="flex min-w-0 flex-col gap-2.5 p-3.5">
                            <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                1 · Level &amp; Programs
                            </div>

                            <div className="grid grid-cols-2 gap-1 rounded-lg border bg-muted/40 p-0.5" role="group" aria-label="Academic level">
                                {(
                                    [
                                        { value: "college", label: "College", icon: BookOpen },
                                        { value: "shs", label: "SHS", icon: GraduationCap },
                                    ] as const
                                ).map((item) => {
                                    const Icon = item.icon;
                                    const active = blueprint.classification === item.value;
                                    return (
                                        <button
                                            key={item.value}
                                            type="button"
                                            onClick={() => {
                                                if (active) return;
                                                setSubjectCodeTouched(false);
                                                setBlueprint((prev) => ({
                                                    ...prev,
                                                    classification: item.value,
                                                    ...(item.value === "college"
                                                        ? { shs_track_id: null, shs_strand_id: null, subject_code_shs: "" }
                                                        : { course_codes: [], subject_ids: [], subject_code: "" }),
                                                }));
                                            }}
                                            className={cn(
                                                "inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold transition-colors",
                                                active
                                                    ? "bg-background text-foreground shadow-2xs"
                                                    : "text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            <Icon className="size-3.5" aria-hidden="true" />
                                            {item.label}
                                        </button>
                                    );
                                })}
                            </div>

                            {blueprint.classification === "college" ? (
                                <div className="space-y-2">
                                    <SearchableMultiSelect
                                        className="min-h-8 text-xs [&_span]:text-xs"
                                        badgeClassName="text-[11px]"
                                        options={courseSelectOptions}
                                        selected={blueprint.course_codes.map(String)}
                                        onChange={(vals) =>
                                            setBlueprint((prev) => ({
                                                ...prev,
                                                course_codes: vals.map(Number).filter(Number.isFinite),
                                            }))
                                        }
                                        placeholder="Pick program(s)"
                                        searchPlaceholder="Search courses…"
                                        emptyText="No matching courses."
                                    />
                                    <Select
                                        value={String(blueprint.academic_year)}
                                        onValueChange={(val) => setBlueprint((prev) => ({ ...prev, academic_year: Number(val) }))}
                                    >
                                        <SelectTrigger className="h-8 w-full text-xs" aria-label="Academic year">
                                            <SelectValue placeholder="Academic year" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.academic_years.map((yr) => (
                                                <SelectItem key={yr.value} value={yr.value}>
                                                    {yr.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Select
                                        value={blueprint.shs_track_id ? String(blueprint.shs_track_id) : ""}
                                        onValueChange={(val) =>
                                            setBlueprint((prev) => ({
                                                ...prev,
                                                shs_track_id: Number(val),
                                                shs_strand_id: null,
                                                subject_code_shs: "",
                                            }))
                                        }
                                    >
                                        <SelectTrigger className="h-8 w-full text-xs" aria-label="SHS track">
                                            <SelectValue placeholder="Choose track" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.shs_tracks.map((track) => (
                                                <SelectItem key={track.id} value={String(track.id)}>
                                                    {track.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <div className="grid grid-cols-2 gap-1.5">
                                        <Select
                                            value={blueprint.shs_strand_id ? String(blueprint.shs_strand_id) : ""}
                                            onValueChange={(val) =>
                                                setBlueprint((prev) => ({
                                                    ...prev,
                                                    shs_strand_id: Number(val),
                                                    subject_code_shs: "",
                                                }))
                                            }
                                            disabled={!blueprint.shs_track_id || shsStrandsLoading || loadedTrackId !== blueprint.shs_track_id}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs" aria-label="SHS strand">
                                                <SelectValue placeholder={shsStrandsLoading ? "Loading…" : "Strand"} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {shsStrandList.map((strand) => (
                                                    <SelectItem key={strand.id} value={String(strand.id)}>
                                                        {strand.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <Select
                                            value={blueprint.grade_level}
                                            onValueChange={(val) => setBlueprint((prev) => ({ ...prev, grade_level: val }))}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs" aria-label="Grade level">
                                                <SelectValue placeholder="Grade" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.grade_levels.map((grade) => (
                                                    <SelectItem key={grade.value} value={grade.value}>
                                                        {grade.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Col 2: Subject & Required Hours */}
                        <div className="flex min-w-0 flex-col gap-2.5 p-3.5">
                            <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                2 · Subject &amp; Required Hours
                            </div>

                            {blueprint.classification === "college" ? (
                                <div className="space-y-2">
                                    <SearchableMultiSelect
                                        className="min-h-8 text-xs [&_span]:text-xs"
                                        badgeClassName="text-[11px]"
                                        options={subjectSelectOptions}
                                        selected={blueprint.subject_ids.map(String)}
                                        disabled={blueprint.course_codes.length === 0 || collegeSubjectsLoading}
                                        onChange={(vals) => {
                                            const ids = vals.map(Number).filter(Number.isFinite);
                                            const autoCode = subjectCodeTouched
                                                ? blueprint.subject_code
                                                : buildSubjectCodeFromSubjectOptions(ids, collegeSubjects);
                                            setBlueprint((prev) => ({
                                                ...prev,
                                                subject_ids: ids,
                                                subject_code: autoCode,
                                            }));
                                        }}
                                        placeholder={
                                            collegeSubjectsLoading
                                                ? "Loading subjects…"
                                                : blueprint.course_codes.length === 0
                                                  ? "Pick course first"
                                                  : "Choose subject(s)"
                                        }
                                        searchPlaceholder="Search subject code or title…"
                                        emptyText="No subjects found."
                                    />
                                    <Input
                                        value={blueprint.subject_code}
                                        onChange={(e) => {
                                            setSubjectCodeTouched(true);
                                            setBlueprint((prev) => ({ ...prev, subject_code: e.target.value }));
                                        }}
                                        placeholder="Subject code (e.g. IT 201)"
                                        aria-label="Subject code"
                                        className="h-8 font-mono text-xs"
                                    />
                                </div>
                            ) : (
                                <Select
                                    value={blueprint.subject_code_shs}
                                    onValueChange={(val) => setBlueprint((prev) => ({ ...prev, subject_code_shs: val }))}
                                    disabled={!blueprint.shs_strand_id || shsSubjectsLoading || loadedStrandId !== blueprint.shs_strand_id}
                                >
                                    <SelectTrigger className="h-8 w-full text-xs" aria-label="SHS subject">
                                        <SelectValue placeholder={shsSubjectsLoading ? "Loading subjects…" : "Choose SHS subject"} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {shsSubjectList.map((subject) => (
                                            <SelectItem key={subject.code} value={subject.code}>
                                                {subject.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                            <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                {requiredHours.total !== null ? (
                                    <>
                                        {requiredHours.units > 0 ? (
                                            <Badge variant="secondary" className="px-1.5 py-0 font-mono text-[10px]">
                                                {requiredHours.units}u
                                            </Badge>
                                        ) : null}
                                        <span className="font-mono tabular-nums">
                                            {requiredHours.lec > 0 || requiredHours.lab > 0
                                                ? `${requiredHours.lec}h Lec + ${requiredHours.lab}h Lab = `
                                                : "Required: "}
                                            <strong className="text-foreground">{requiredHours.total}h/wk</strong>
                                        </span>
                                    </>
                                ) : (
                                    <span>Required hours calculate once a subject is picked.</span>
                                )}
                            </div>
                            {blueprint.classification === "college" && curriculumError ? <p className="text-destructive text-[11px]">{curriculumError}</p> : null}
                            {blueprint.classification === "shs" && strandError ? (
                                <p className="text-destructive text-[11px]">SHS strands could not be loaded. <button type="button" className="underline" onClick={() => setStrandRetry((value) => value + 1)}>Retry</button></p>
                            ) : null}
                            {blueprint.classification === "shs" && subjectError ? (
                                <p className="text-destructive text-[11px]">SHS subjects could not be loaded. <button type="button" className="underline" onClick={() => setSubjectRetry((value) => value + 1)}>Retry</button></p>
                            ) : null}
                        </div>

                        {/* Col 3: Term & Start Date */}
                        <div className="flex min-w-0 flex-col gap-2.5 p-3.5">
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    3 · Term &amp; Start Date
                                </span>
                                <Calendar className="size-3.5 text-muted-foreground" aria-hidden="true" />
                            </div>

                            <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                <Select
                                    value={blueprint.semester}
                                    onValueChange={(val) =>
                                        setBlueprint((prev) => ({
                                            ...prev,
                                            semester: val as "1" | "2" | "summer",
                                        }))
                                    }
                                >
                                    <SelectTrigger className="h-8 w-full text-xs" aria-label="Semester">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.semesters.map((term) => (
                                            <SelectItem key={term.value} value={term.value}>
                                                {term.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Input
                                    value={blueprint.school_year}
                                    onChange={(e) => setBlueprint((prev) => ({ ...prev, school_year: e.target.value }))}
                                    placeholder="2026-2027"
                                    aria-label="School year"
                                    className="h-8 font-mono text-xs tabular-nums"
                                />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="bp-start-date" className="text-[11px] text-muted-foreground">
                                    Start date (optional)
                                </Label>
                                <Input
                                    id="bp-start-date"
                                    type="date"
                                    value={blueprint.start_date ?? ""}
                                    onChange={(e) => setBlueprint((prev) => ({ ...prev, start_date: e.target.value || null }))}
                                    className="h-8 text-xs tabular-nums"
                                />
                            </div>
                        </div>

                        {/* Col 4: Default Delivery & Policies */}
                        <div className="flex min-w-0 flex-col gap-2.5 p-3.5">
                            <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                4 · Default Delivery &amp; Policies
                            </div>

                            <Select
                                value={canonicalDeliveryMode(blueprint.delivery_mode)}
                                onValueChange={(val) => {
                                    const nextMode = canonicalDeliveryMode(val);
                                    setBlueprint((prev) => ({ ...prev, delivery_mode: nextMode }));
                                }}
                            >
                                <SelectTrigger className="h-8 w-full text-xs" aria-label="Default delivery mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="face_to_face">Face to face</SelectItem>
                                    <SelectItem value="online_synchronous">Online (Sync)</SelectItem>
                                    <SelectItem value="online">Online (Async)</SelectItem>
                                </SelectContent>
                            </Select>

                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button type="button" variant="outline" size="sm" className="h-8 w-full justify-between px-2.5 text-xs font-medium">
                                        <span className="inline-flex items-center gap-1.5">
                                            <SlidersHorizontal className="size-3.5 text-muted-foreground" aria-hidden="true" />
                                            Classroom policies
                                        </span>
                                        <Badge variant="secondary" className="px-1.5 py-0 font-mono text-[10px]">
                                            {activePolicyCount}/7
                                        </Badge>
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="start" className="w-80 space-y-3 p-4">
                                    <div className="border-b pb-2">
                                        <h4 className="text-sm font-semibold">Shared Classroom Policies</h4>
                                        <p className="text-xs text-muted-foreground">Applied consistently to every section in this batch.</p>
                                    </div>
                                    <div className="space-y-2.5">
                                        {POLICY_ITEMS.map((item) => {
                                            const checked = Boolean((blueprint.settings ?? DEFAULT_POLICY_SETTINGS)[item.key]);
                                            return (
                                                <label
                                                    key={item.key}
                                                    className="flex cursor-pointer items-start justify-between gap-3 rounded-md p-1 transition-colors hover:bg-muted/50"
                                                >
                                                    <div className="space-y-0.5">
                                                        <div className="text-xs font-medium text-foreground">{item.label}</div>
                                                        <div className="text-[11px] text-muted-foreground">{item.description}</div>
                                                    </div>
                                                    <Switch
                                                        checked={checked}
                                                        onCheckedChange={(next) =>
                                                            setBlueprint((prev) => ({
                                                                ...prev,
                                                                settings: {
                                                                    ...(prev.settings ?? DEFAULT_POLICY_SETTINGS),
                                                                    [item.key]: next,
                                                                },
                                                            }))
                                                        }
                                                    />
                                                </label>
                                            );
                                        })}
                                    </div>
                                </PopoverContent>
                            </Popover>

                            <div className="flex flex-wrap gap-1">
                                {POLICY_ITEMS.filter((item) => Boolean((blueprint.settings ?? DEFAULT_POLICY_SETTINGS)[item.key]))
                                    .slice(0, 3)
                                    .map((item) => (
                                        <span
                                            key={item.key}
                                            className="rounded border bg-muted/40 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            {item.label}
                                        </span>
                                    ))}
                                {activePolicyCount > 3 ? (
                                    <span className="rounded border bg-muted/40 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                        +{activePolicyCount - 3} more
                                    </span>
                                ) : null}
                            </div>
                        </div>

                        {/* Col 5: Live Reactive Totals */}
                        <div className="flex min-w-0 flex-col justify-between gap-2 bg-muted/20 p-3.5 md:col-span-2 xl:col-span-1">
                            <div className="flex items-center justify-between gap-1.5">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    5 · Live Totals
                                </span>
                                {totals.blockingCount > 0 ? (
                                    <Badge variant="destructive" className="gap-1 px-1.5 py-0 text-[10px] font-semibold">
                                        <AlertTriangle className="size-2.5" aria-hidden="true" />
                                        {totals.blockingCount} Blocking
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="gap-1 border-emerald-500/40 bg-emerald-500/10 px-1.5 py-0 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300"
                                    >
                                        <CheckCircle2 className="size-2.5" aria-hidden="true" />
                                        All Clear
                                    </Badge>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-sm font-bold text-foreground tabular-nums">
                                        {totals.totalSections} {totals.totalSections === 1 ? "Section" : "Sections"}
                                    </span>
                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-muted-foreground tabular-nums">
                                        <Users className="size-3" aria-hidden="true" />
                                        {totals.totalSeats} seats
                                    </span>
                                </div>

                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Scheduled hours</span>
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {totals.scheduledHours}h
                                        {totals.requiredTotalAcrossSections !== null ? ` / ${totals.requiredTotalAcrossSections}h` : ""}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>
                                        {totals.newCount} new · {totals.existingCount} existing
                                    </span>
                                    {totals.warningCount > 0 ? (
                                        <span className="font-medium text-amber-700 dark:text-amber-300">
                                            {totals.warningCount} warning{totals.warningCount === 1 ? "" : "s"}
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 2. Parallel Section Matrix Table */}
                <section className="bg-card overflow-hidden rounded-2xl border shadow-xs" aria-label="Parallel Section Matrix">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/30 px-4 py-3">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-sm font-bold text-foreground">Parallel Section Matrix</h2>
                                {totals.blockingCount > 0 ? (
                                    <Badge variant="destructive" className="text-[11px]">
                                        {totals.blockingCount} blocking conflict{totals.blockingCount === 1 ? "" : "s"}
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-emerald-500/40 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-300"
                                    >
                                        No blocking conflicts
                                    </Badge>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Configure instructors, capacities, default rooms, and Lec/Lab blocks side-by-side. Hover a row to focus it on the
                                weekly strip below.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={handleAddSection} className="h-8 gap-1.5 text-xs font-semibold">
                                <Plus className="size-3.5" aria-hidden="true" />
                                Add Section
                            </Button>

                            <Button type="button" variant="outline" size="sm" onClick={handleAutoStagger} className="h-8 gap-1.5 text-xs font-semibold">
                                <Sparkles className="size-3.5 text-primary" aria-hidden="true" />
                                Auto-Stagger Free Slots
                            </Button>

                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={!isBlueprintReady || currentDryRunState === "checking"}
                                onClick={() => void runDryRunCheck(true)}
                                className="h-8 gap-1.5 text-xs font-semibold"
                            >
                                <RefreshCw className={cn("size-3.5", currentDryRunState === "checking" && "animate-spin")} aria-hidden="true" />
                                Run Dry-Run Check
                            </Button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/20 hover:bg-muted/20">
                                    <TableHead className="w-[165px] text-[11px] uppercase">Section</TableHead>
                                    <TableHead className="w-[235px] text-[11px] uppercase">Instructor &amp; Load</TableHead>
                                    <TableHead className="w-[105px] text-[11px] uppercase">Capacity</TableHead>
                                    <TableHead className="w-[205px] text-[11px] uppercase">Default Room</TableHead>
                                    <TableHead className="min-w-[290px] text-[11px] uppercase">Lec / Lab Schedule Slots</TableHead>
                                    <TableHead className="w-[175px] text-right text-[11px] uppercase">Status &amp; Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, rowIndex) => {
                                    const rowConflicts = conflictsByRowKey.get(row.client_key) ?? [];
                                    const rowBlocking = rowConflicts.filter((c) => c.severity === "blocking");
                                    const rowWarnings = rowConflicts.filter((c) => c.severity === "warning");
                                    const rowHours = Number(row.schedules.reduce((sum, s) => sum + slotHours(s), 0).toFixed(1));
                                    const selectedFac = row.faculty_id ? facultiesById.get(row.faculty_id) : null;
                                    const facBaseHours = selectedFac?.load_hours ?? 0;
                                    const facBatchHours = row.faculty_id ? Number((batchHoursByFaculty.get(row.faculty_id) ?? 0).toFixed(1)) : 0;
                                    const facProjected = Number((facBaseHours + facBatchHours).toFixed(1));
                                    const facMax = selectedFac?.max_load_hours ?? 24;
                                    const hasFacultyLoad = hasTermLoad && typeof selectedFac?.load_hours === "number";
                                    const facOverloaded = Boolean(hasFacultyLoad && facProjected > facMax);

                                    const defaultRoomObj = row.room_id ? roomsById.get(Number(row.room_id)) : null;
                                    const defaultRoomTight = Boolean(
                                        !isOnlineDeliveryMode(blueprint.delivery_mode) &&
                                            defaultRoomObj &&
                                            typeof defaultRoomObj.capacity === "number" &&
                                            defaultRoomObj.capacity > 0 &&
                                            row.maximum_slots > defaultRoomObj.capacity,
                                    );

                                    const swatchClass = SECTION_SWATCHES[rowIndex % SECTION_SWATCHES.length];
                                    const belowEnrolled = row.maximum_slots > 0 && typeof row.enrolled_count === "number" && row.maximum_slots < row.enrolled_count;

                                    const nextSuggestedComponent: "lecture" | "lab" =
                                        requiredHours.lab > 0 && !row.schedules.some((s) => s.component_type === "lab") ? "lab" : "lecture";

                                    return (
                                        <TableRow
                                            key={row.client_key}
                                            onMouseEnter={() => setHoveredRowKey(row.client_key)}
                                            onMouseLeave={() => setHoveredRowKey((curr) => (curr === row.client_key ? null : curr))}
                                            className={cn(
                                                "align-top transition-colors",
                                                rowBlocking.length > 0 && "bg-destructive/5 hover:bg-destructive/8",
                                                row.class_id === null && rowBlocking.length === 0 && "bg-muted/15",
                                            )}
                                        >
                                            {/* Section Code */}
                                            <TableCell className="py-3">
                                                <div className="flex items-start gap-2">
                                                    <span className={cn("mt-1 h-8 w-1 shrink-0 rounded-full", swatchClass)} aria-hidden="true" />
                                                    <div className="min-w-0 flex-1 space-y-1.5">
                                                        <Input
                                                            value={row.section}
                                                            maxLength={20}
                                                            onChange={(e) =>
                                                                updateRow(row.client_key, (curr) => ({ ...curr, section: e.target.value }))
                                                            }
                                                            placeholder="e.g. A"
                                                            aria-label={`Section code for row ${rowIndex + 1}`}
                                                            className="h-8 font-semibold text-xs"
                                                        />
                                                        <div className="flex flex-wrap items-center gap-1">
                                                            {row.class_id !== null ? (
                                                                <Badge variant="secondary" className="px-1.5 py-0 text-[10px]">
                                                                    Existing
                                                                </Badge>
                                                            ) : (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-primary/40 bg-primary/10 px-1.5 py-0 text-[10px] text-primary"
                                                                >
                                                                    New
                                                                </Badge>
                                                            )}
                                                            {typeof row.enrolled_count === "number" && row.enrolled_count > 0 ? (
                                                                <span className="text-[10px] text-muted-foreground tabular-nums">
                                                                    {row.enrolled_count} enrolled
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Instructor & Load */}
                                            <TableCell className="py-3">
                                                <div className="space-y-1.5">
                                                    <Select
                                                        value={row.faculty_id ?? "none"}
                                                        onValueChange={(val) =>
                                                            updateRow(row.client_key, (curr) => ({
                                                                ...curr,
                                                                faculty_id: val === "none" ? null : val,
                                                            }))
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className={cn(
                                                                "h-8 w-full text-xs",
                                                                !row.faculty_id && "border-amber-500/40 bg-amber-500/5 text-muted-foreground",
                                                            )}
                                                            aria-label={`Instructor for section ${row.section}`}
                                                        >
                                                            <SelectValue placeholder="TBA / Unassigned" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">Unassigned (TBA)</SelectItem>
                                                            {options.faculties.map((fac) => (
                                                                <SelectItem key={fac.id} value={String(fac.id)}>
                                                                    {fac.label}
                                                                    {hasTermLoad && typeof fac.load_hours === "number" ? ` · ${fac.load_hours}h` : ""}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>

                                                    {selectedFac && hasFacultyLoad ? (
                                                        <div
                                                            className={cn(
                                                                "flex items-center gap-1 text-[11px] tabular-nums",
                                                                facOverloaded
                                                                    ? "font-semibold text-destructive"
                                                                    : facProjected >= facMax - 3
                                                                      ? "font-medium text-amber-700 dark:text-amber-300"
                                                                      : "text-muted-foreground",
                                                            )}
                                                        >
                                                            {facOverloaded ? <AlertTriangle className="size-3 shrink-0" aria-hidden="true" /> : null}
                                                            <span>
                                                                {facBaseHours}h {facBatchHours < 0 ? "−" : "+"} {Math.abs(facBatchHours)}h change = {facProjected}h/{facMax}h
                                                            </span>
                                                        </div>
                                                    ) : selectedFac ? (
                                                        <div className="text-[11px] text-muted-foreground">Load unavailable for this term; use the schedule check.</div>
                                                    ) : (
                                                        <div className="text-[11px] text-muted-foreground">Assign instructor or leave TBA</div>
                                                    )}
                                                </div>
                                            </TableCell>

                                            {/* Capacity */}
                                            <TableCell className="py-3">
                                                <div className="space-y-1">
                                                    <Input
                                                        type="text"
                                                        inputMode="numeric"
                                                        value={row.capacity_input}
                                                        onChange={(e) => {
                                                            const raw = e.target.value;
                                                            const parsed = Number.parseInt(raw, 10);
                                                            updateRow(row.client_key, (curr) => ({
                                                                ...curr,
                                                                capacity_input: raw,
                                                                maximum_slots: Number.isFinite(parsed) && parsed >= 0 ? parsed : curr.maximum_slots,
                                                            }));
                                                        }}
                                                        onBlur={() => {
                                                            const parsed = Number.parseInt(row.capacity_input, 10);
                                                            const clamped = Number.isFinite(parsed) && parsed >= 0 ? parsed : row.maximum_slots;
                                                            updateRow(row.client_key, (curr) => ({
                                                                ...curr,
                                                                maximum_slots: clamped,
                                                                capacity_input: String(clamped),
                                                            }));
                                                        }}
                                                        aria-label={`Capacity for section ${row.section}`}
                                                        className={cn("h-8 w-20 font-mono text-xs tabular-nums", belowEnrolled && "border-destructive")}
                                                    />
                                                    {belowEnrolled ? (
                                                        <div className="text-[10px] font-medium text-destructive">
                                                            Below {row.enrolled_count} enrolled
                                                        </div>
                                                    ) : (
                                                        <div className="text-[10px] text-muted-foreground">max seats</div>
                                                    )}
                                                </div>
                                            </TableCell>

                                            {/* Default Room */}
                                            <TableCell className="py-3">
                                                <div className="space-y-1">
                                                    <Select
                                                        value={row.room_id !== null ? String(row.room_id) : "none"}
                                                        disabled={isOnlineDeliveryMode(blueprint.delivery_mode)}
                                                        onValueChange={(val) => {
                                                            const nextRoomId = val === "none" ? null : Number(val);
                                                            updateRow(row.client_key, (curr) => ({
                                                                ...curr,
                                                                room_id: nextRoomId,
                                                                schedules: curr.schedules.map((s) =>
                                                                    !isOnlineDeliveryMode(s.delivery_mode) && s.room_id === curr.room_id
                                                                        ? { ...s, room_id: nextRoomId }
                                                                        : s,
                                                                ),
                                                            }));
                                                        }}
                                                    >
                                                        <SelectTrigger
                                                            className={cn("h-8 w-full text-xs", defaultRoomTight && "border-amber-500/60")}
                                                            aria-label={`Default room for section ${row.section}`}
                                                        >
                                                            <SelectValue
                                                                placeholder={
                                                                    isOnlineDeliveryMode(blueprint.delivery_mode) ? "Online mode" : "Room unassigned"
                                                                }
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">Unassigned (TBA)</SelectItem>
                                                            {options.rooms.map((room) => (
                                                                <SelectItem key={room.id} value={String(room.id)}>
                                                                    {room.label}
                                                                    {room.capacity ? ` · ${room.capacity} seats` : ""}
                                                                    {room.type ? ` · ${room.type}` : ""}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    {defaultRoomTight ? (
                                                        <div className="flex items-center gap-1 text-[10px] font-medium text-amber-700 dark:text-amber-300">
                                                            <Building2 className="size-3 shrink-0" aria-hidden="true" />
                                                            Holds {defaultRoomObj?.capacity} / {row.maximum_slots} seats
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </TableCell>

                                            {/* Lec / Lab Schedule Slots */}
                                            <TableCell className="py-3">
                                                <div className="space-y-1.5">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {row.schedules.map((slot, sIdx) => {
                                                            const slotBlocking = rowBlocking.some((c) => c.schedule_index === sIdx);
                                                            const slotWarning = rowWarnings.some((c) => c.schedule_index === sIdx);
                                                            const isLab = slot.component_type === "lab";
                                                            const roomLabel = isOnlineDeliveryMode(slot.delivery_mode)
                                                                ? deliveryModeShortLabel(slot.delivery_mode)
                                                                : slot.room_id
                                                                  ? (roomsById.get(Number(slot.room_id))?.label ?? "Room")
                                                                  : "TBA";

                                                            return (
                                                                <SectionSlotPopover
                                                                    key={`${row.client_key}-slot-${sIdx}`}
                                                                    slot={slot}
                                                                    defaultRoomId={row.room_id}
                                                                    defaultDeliveryMode={canonicalDeliveryMode(blueprint.delivery_mode)}
                                                                    sectionCap={row.maximum_slots}
                                                                    rooms={options.rooms}
                                                                    days={options.day_of_week}
                                                                    allowSunday={options.scheduling_policy?.allow_sunday_scheduling ?? false}
                                                                    title={`Sec ${row.section || "?"} · Edit slot`}
                                                                    onSave={(updated) =>
                                                                        updateRow(row.client_key, (curr) => ({
                                                                            ...curr,
                                                                            schedules: curr.schedules.map((item, idx) =>
                                                                                idx === sIdx ? updated : item,
                                                                            ),
                                                                        }))
                                                                    }
                                                                    onDelete={() =>
                                                                        updateRow(row.client_key, (curr) => ({
                                                                            ...curr,
                                                                            schedules: curr.schedules.filter((_, idx) => idx !== sIdx),
                                                                        }))
                                                                    }
                                                                    trigger={
                                                                        <button
                                                                            type="button"
                                                                            className={cn(
                                                                                "inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-medium transition-colors",
                                                                                slotBlocking
                                                                                    ? "border-destructive bg-destructive/12 text-destructive hover:bg-destructive/20"
                                                                                    : slotWarning
                                                                                      ? "border-amber-500/60 bg-amber-500/12 text-amber-800 hover:bg-amber-500/20 dark:text-amber-200"
                                                                                      : isLab
                                                                                        ? "border-emerald-600/40 bg-emerald-600/10 text-emerald-900 hover:bg-emerald-600/20 dark:text-emerald-200"
                                                                                        : "border-primary/35 bg-primary/10 text-primary hover:bg-primary/20",
                                                                            )}
                                                                        >
                                                                            {slotBlocking ? (
                                                                                <AlertTriangle className="size-3 shrink-0" aria-hidden="true" />
                                                                            ) : null}
                                                                            <span className="font-bold">{slot.day_of_week.slice(0, 3)}</span>
                                                                            <span className="font-mono text-[10px] tabular-nums">
                                                                                {formatShortTime(slot.start_time)}–{formatShortTime(slot.end_time)}
                                                                            </span>
                                                                            <span className="opacity-75">· {isLab ? "Lab" : "Lec"}</span>
                                                                            <span className="max-w-[90px] truncate opacity-80">· {roomLabel}</span>
                                                                        </button>
                                                                    }
                                                                />
                                                            );
                                                        })}

                                                        <SectionSlotPopover
                                                            defaultRoomId={row.room_id}
                                                            defaultDeliveryMode={canonicalDeliveryMode(blueprint.delivery_mode)}
                                                            defaultComponentType={nextSuggestedComponent}
                                                            sectionCap={row.maximum_slots}
                                                            rooms={options.rooms}
                                                            days={options.day_of_week}
                                                            allowSunday={options.scheduling_policy?.allow_sunday_scheduling ?? false}
                                                            title={`Sec ${row.section || "?"} · Add slot`}
                                                            onSave={(newSlot) =>
                                                                updateRow(row.client_key, (curr) => ({
                                                                    ...curr,
                                                                    schedules: [...curr.schedules, newSlot],
                                                                }))
                                                            }
                                                            trigger={
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex items-center gap-1 rounded-md border border-dashed px-2 py-1 text-[11px] font-semibold text-muted-foreground transition-colors hover:border-primary hover:bg-primary/5 hover:text-primary"
                                                                >
                                                                    <Plus className="size-3" aria-hidden="true" />
                                                                    Add Slot
                                                                </button>
                                                            }
                                                        />
                                                    </div>

                                                    <div className="flex items-center gap-2 text-[11px] text-muted-foreground tabular-nums">
                                                        <span>
                                                            Scheduled: <strong className="text-foreground">{rowHours}h</strong>
                                                            {requiredHours.total !== null ? ` / ${requiredHours.total}h required` : ""}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Row Status & Actions */}
                                            <TableCell className="py-3 text-right">
                                                <div className="flex flex-col items-end gap-2">
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            render={
                                                                <span className="inline-flex cursor-default">
                                                                    {rowBlocking.length > 0 ? (
                                                                        <Badge variant="destructive" className="gap-1 text-[10px]">
                                                                            <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                                            {rowBlocking.length}{" "}
                                                                            {rowBlocking.length === 1 ? "Clash" : "Clashes"}
                                                                        </Badge>
                                                                    ) : rowWarnings.length > 0 ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="gap-1 border-amber-500/50 bg-amber-500/10 text-[10px] text-amber-800 dark:text-amber-200"
                                                                        >
                                                                            <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                                            {rowWarnings.length} Warn
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-[10px] text-emerald-700 dark:text-emerald-300"
                                                                        >
                                                                            <CheckCircle2 className="size-2.5" aria-hidden="true" />
                                                                            Clear
                                                                        </Badge>
                                                                    )}
                                                                </span>
                                                            }
                                                        />
                                                        <TooltipContent side="left" className="max-w-xs space-y-1 p-2.5 text-left">
                                                            {rowConflicts.length === 0 ? (
                                                                <span>No conflicts detected for Sec {row.section || "?"}.</span>
                                                            ) : (
                                                                <ul className="space-y-1 text-[11px]">
                                                                    {rowConflicts.slice(0, 5).map((c, cIdx) => (
                                                                        <li key={cIdx}>
                                                                            <strong className="uppercase">{c.severity}:</strong> {c.message}
                                                                        </li>
                                                                    ))}
                                                                </ul>
                                                            )}
                                                        </TooltipContent>
                                                    </Tooltip>

                                                    <div className="flex items-center justify-end gap-1">
                                                        {row.class_id !== null ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                nativeButton={false}
                                                                render={<Link href={showClass.url(row.class_id)} />}
                                                                className="h-7 gap-1 px-2 text-[11px] font-semibold"
                                                            >
                                                                Dossier
                                                                <ArrowUpRight className="size-3" aria-hidden="true" />
                                                            </Button>
                                                        ) : null}

                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleDuplicateRow(row)}
                                                            title={`Duplicate section ${row.section}`}
                                                            aria-label={`Duplicate section ${row.section}`}
                                                            className="size-7 text-muted-foreground hover:text-foreground"
                                                        >
                                                            <Copy className="size-3.5" aria-hidden="true" />
                                                        </Button>

                                                        {row.class_id === null && rows.length > 1 ? (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleRemoveRow(row.client_key)}
                                                                title={`Remove section ${row.section}`}
                                                                aria-label={`Remove section ${row.section}`}
                                                                className="size-7 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                            >
                                                                <Trash2 className="size-3.5" aria-hidden="true" />
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {/* 3. Time-Scaled Cross-Section Conflict Strip (Room + Faculty + Cohort) */}
                <section className="grid gap-4 lg:grid-cols-[1.55fr_1fr]" aria-label="Cross-Section Weekly Conflict Strip">
                    <div className="bg-card rounded-2xl border p-4 shadow-xs">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div className="text-xs font-semibold text-foreground">
                                Cross-Section Weekly Preview
                                {hoveredRowKey ? (
                                    <span className="ml-2 text-primary font-medium">
                                        (Isolating Sec {rows.find((r) => r.client_key === hoveredRowKey)?.section ?? "?"})
                                    </span>
                                ) : (
                                    <span className="ml-2 font-normal text-muted-foreground">(All staged sections)</span>
                                )}
                            </div>
                        </div>
                        <LiveBusyGrid
                            compact
                            schedules={gridBlocks}
                            conflicts={gridConflicts}
                            roomsById={roomsById}
                            allowSunday={options.scheduling_policy?.allow_sunday_scheduling ?? false}
                        />
                    </div>

                    <div className="bg-card flex flex-col rounded-2xl border p-4 shadow-xs">
                        <div className="flex items-center justify-between gap-2 border-b pb-2.5">
                            <div className="text-xs font-semibold text-foreground">Conflict &amp; Preflight Summary</div>
                            <div className="flex items-center gap-1.5">
                                <Badge variant={blockingConflicts.length > 0 ? "destructive" : "secondary"} className="text-[10px] tabular-nums">
                                    {blockingConflicts.length} Blocking
                                </Badge>
                                <Badge variant="outline" className="text-[10px] tabular-nums">
                                    {warningConflicts.length} Warnings
                                </Badge>
                            </div>
                        </div>

                        <div className="mt-3 flex-1 space-y-3 overflow-y-auto max-h-[320px] pr-1">
                            {blockingConflicts.length === 0 && warningConflicts.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-emerald-500/40 bg-emerald-500/5 px-4 py-8 text-center">
                                    <CheckCircle2 className="size-6 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                    <div className="text-sm font-semibold text-foreground">All clear across sections</div>
                                    <p className="max-w-xs text-xs text-muted-foreground">
                                        No room, instructor, or self-schedule overlaps detected across this blueprint matrix.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    {blockingConflicts.length > 0 ? (
                                        <div className="space-y-1.5">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-destructive">
                                                Blocking ({blockingConflicts.length}) — Must resolve to publish
                                            </div>
                                            {blockingConflicts.map((conflict, idx) => (
                                                <div
                                                    key={`block-${idx}`}
                                                    className="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/8 p-2.5 text-xs text-destructive"
                                                >
                                                    <ShieldAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                                    <div className="min-w-0">
                                                        <div className="font-semibold capitalize">{conflict.kind.replace("_", " ")} conflict</div>
                                                        <div className="text-foreground/90">{conflict.message}</div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}

                                    {warningConflicts.length > 0 ? (
                                        <div className="space-y-1.5">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                                                Warnings ({warningConflicts.length}) — Advisory
                                            </div>
                                            {warningConflicts.map((conflict, idx) => (
                                                <div
                                                    key={`warn-${idx}`}
                                                    className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2.5 text-xs text-amber-900 dark:text-amber-100"
                                                >
                                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                                                    <div className="min-w-0">
                                                        <div className="font-semibold capitalize">{conflict.kind.replace("_", " ")} notice</div>
                                                        <div>{conflict.message}</div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}
                                </>
                            )}
                        </div>
                    </div>
                </section>

                </fieldset>
                {/* 4. Bottom Atomic Staging Bar (Sticky) */}
                <footer
                    className="bg-card/95 sticky bottom-3 z-30 flex flex-col gap-3 rounded-2xl border p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between"
                    aria-label="Atomic batch staging bar"
                >
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="text-[10px] font-bold uppercase tracking-wider">
                                Staging
                            </Badge>
                            <span className="text-xs font-semibold text-foreground tabular-nums">
                                {totals.totalSections} {totals.totalSections === 1 ? "section" : "sections"} · {totals.totalSeats} total seats ·{" "}
                                {totals.newCount} new, {totals.existingCount} existing ·{" "}
                                <span className={totals.blockingCount > 0 ? "text-destructive" : "text-emerald-700 dark:text-emerald-400"}>
                                    {totals.blockingCount} blocking {totals.blockingCount === 1 ? "conflict" : "conflicts"}
                                </span>
                            </span>
                        </div>

                        {blueprintMissingReason ? (
                            <p className="text-xs text-amber-700 dark:text-amber-300">{blueprintMissingReason}</p>
                        ) : totals.blockingCount > 0 ? (
                            <p className="text-xs text-destructive">
                                Publish is locked until blocking conflicts are resolved. You can still save all sections as Draft.
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Ready to commit atomically. Every section inherits the shared subject blueprint above.
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {totals.existingCount > 0 ? (
                            <label htmlFor="batch-notify-enrolled" className="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                                <Checkbox
                                    id="batch-notify-enrolled"
                                    checked={notifyEnrolled}
                                    disabled={submittingMode !== null}
                                    onCheckedChange={(checked) => setNotifyEnrolled(checked === true)}
                                />
                                <span>Notify enrolled students of schedule changes</span>
                            </label>
                        ) : null}

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!isBlueprintReady || submittingMode !== null}
                                onClick={() => void handleCommit("draft")}
                                className="h-9 gap-1.5 px-3 text-xs font-semibold"
                            >
                                {submittingMode === "draft" ? (
                                    <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                ) : (
                                    <Save className="size-3.5" aria-hidden="true" />
                                )}
                                Save All as Draft
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                disabled={!isBlueprintReady || totals.blockingCount > 0 || currentDryRunState !== "verified" || submittingMode !== null}
                                onClick={() => void handleCommit("published")}
                                className="h-9 gap-1.5 px-4 text-xs font-semibold shadow-xs"
                            >
                                {submittingMode === "published" ? (
                                    <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                ) : (
                                    <CheckCircle2 className="size-3.5" aria-hidden="true" />
                                )}
                                Publish All Sections
                            </Button>
                        </div>
                    </div>
                </footer>
            </div>
        </TooltipProvider>
    );
}
