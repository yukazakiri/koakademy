import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect as SearchableMultiSelect } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { edit as editClassRoute, index as classesIndexRoute, show as showClassRoute, store as storeClassRoute, update as updateClassRoute } from "@/routes/administrators/classes";
import { shsStrands, shsSubjects, subjects as subjectOptionsRoute } from "@/routes/administrators/classes/options";
import { Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    Archive,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    CopyPlus,
    GraduationCap,
    Loader2,
    RotateCcw,
    Save,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Users,
    Wand2,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { postClassPreflight } from "../crud/api";
import { LiveBusyGrid, type LiveBusyGridBlock } from "../crud/live-busy-grid";
import type {
    CrudScheduleBlock,
    EditClassMeta,
    PreflightResponse,
    PreflightSuggestion,
    SiblingSection,
} from "../crud/types";
import {
    buildClassDefaults,
    buildSubjectCodeFromSubjectOptions,
    canonicalDeliveryMode,
    isClassDraft,
    isOnlineDeliveryMode,
    normalizeClassDelivery,
    step1IsValid,
    step2IsValid,
    type ClassDraft,
    type ClassFormData,
    type ClassFormOptions,
    type Classification,
    type ClassSettings,
    type DeliveryMode,
    type EntityOption,
    type ShsSubjectOption,
    type SubjectOption,
} from "../lib/class-defaults";
import { PoliciesBannerCard } from "./policies-banner-card";
import { ScheduleBuilderCard } from "./schedule-builder-card";

export type SplitComposerStudioProps = {
    semester: "1" | "2" | "summer";
    school_year: string;
    options: ClassFormOptions;
    mode: "create" | "edit";
    classId?: number;
    initialData?: ClassFormData;
    editMeta?: EditClassMeta | null;
    siblingSections?: SiblingSection[];
    initialFocus?: "details" | "schedule" | "settings";
};

function isSubjectOption(value: unknown): value is SubjectOption {
    return (
        typeof value === "object" &&
        value !== null &&
        "id" in value &&
        "label" in value &&
        "code" in value &&
        "course_id" in value &&
        "academic_year" in value
    );
}

function isEntityOption(value: unknown): value is EntityOption {
    return typeof value === "object" && value !== null && "id" in value && "label" in value;
}

function isShsSubjectOption(value: unknown): value is ShsSubjectOption {
    return typeof value === "object" && value !== null && "code" in value && "label" in value;
}

async function fetchOptionData<T>(url: string, guard: (value: unknown) => value is T, signal?: AbortSignal): Promise<T[]> {
    const response = await fetch(url, { headers: { Accept: "application/json" }, signal });
    if (!response.ok) throw new Error("Options could not be loaded.");
    const payload = (await response.json()) as { data?: unknown[] };
    const rows = Array.isArray(payload.data) ? payload.data : [];
    return rows.filter(guard);
}

function toNumbers(values: string[]): number[] {
    return values.map((v) => Number(v)).filter((v) => Number.isFinite(v));
}

function formatSubjectHours(subject: { lecture?: number | null; laboratory?: number | null; units?: number | null }): string {
    const lec = typeof subject.lecture === "number" ? subject.lecture : null;
    const lab = typeof subject.laboratory === "number" ? subject.laboratory : null;
    if (lec !== null || lab !== null) {
        return `${lec ?? 0}h Lec / ${lab ?? 0}h Lab`;
    }
    if (typeof subject.units === "number" && subject.units > 0) {
        return `${subject.units} units`;
    }
    return "";
}

function formatSuggestionCta(suggestion: PreflightSuggestion): string {
    if (suggestion.kind === "room" && suggestion.room_name) {
        return `Apply Suggested Room: ${suggestion.room_name}`;
    }
    if (suggestion.kind === "slot" && suggestion.day_of_week && suggestion.start_time && suggestion.end_time) {
        return `Apply Suggested Slot: ${suggestion.day_of_week.slice(0, 3)} ${suggestion.start_time.slice(0, 5)}–${suggestion.end_time.slice(0, 5)}`;
    }
    return suggestion.label || "Apply Suggested Fix";
}

export function SplitComposerStudio({
    semester,
    school_year,
    options,
    mode,
    classId,
    initialData,
    editMeta = null,
    siblingSections = [],
    initialFocus = "details",
}: SplitComposerStudioProps): ReactNode {
    const isEditing = mode === "edit";
    const draftStorageKey = `class-composer-draft:${school_year}:${semester}`;

    const baseDefaults = useMemo(
        () => buildClassDefaults({ semester, school_year, options }),
        [semester, school_year, options],
    );

    const [formData, setFormData] = useState<ClassFormData>(() =>
        normalizeClassDelivery(
            initialData
                ? {
                      ...baseDefaults,
                      ...initialData,
                      settings: {
                          ...baseDefaults.settings,
                          ...initialData.settings,
                          banner_image: null,
                      },
                  }
                : baseDefaults,
        ),
    );

    const [isDirty, setIsDirty] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
    const [validationError, setValidationError] = useState<string | null>(null);
    const [subjectCodeTouched, setSubjectCodeTouched] = useState(Boolean(initialData?.subject_code));
    const [classificationSwitchWarn, setClassificationSwitchWarn] = useState(false);

    // Capacity text draft to avoid `|| 1` typing trap
    const [capacityDraft, setCapacityDraft] = useState<string>(String(formData.maximum_slots));
    const [allowBelowEnrolled, setAllowBelowEnrolled] = useState(false);
    const [notifyEnrolled, setNotifyEnrolled] = useState<boolean>(
        formData.settings.notify_students_on_schedule_changes ?? true,
    );

    // Banner removal state (explicit opt-in so existing banner is never silently wiped)
    const [removeBannerImage, setRemoveBannerImage] = useState<boolean>(
        Boolean(initialData?.settings?.remove_banner_image),
    );
    const existingBannerUrl = editMeta?.banner_image_url ?? initialData?.settings?.banner_image_url ?? null;

    // Policies card open state
    const [policiesOpen, setPoliciesOpen] = useState<boolean>(isEditing || initialFocus === "settings");

    // Scoped localStorage draft (Create mode only)
    const [savedDraft, setSavedDraft] = useState<ClassDraft | null>(null);
    const [draftChecked, setDraftChecked] = useState(false);

    // Dynamic curriculum options
    const [collegeSubjects, setCollegeSubjects] = useState<SubjectOption[]>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = useState(false);
    const [collegeSubjectsError, setCollegeSubjectsError] = useState<string | null>(null);
    const [loadedCourseKey, setLoadedCourseKey] = useState<string | null>(null);

    const [shsStrandOptions, setShsStrandOptions] = useState<EntityOption[]>([]);
    const [shsStrandsLoading, setShsStrandsLoading] = useState(false);
    const [shsSubjectOptions, setShsSubjectOptions] = useState<ShsSubjectOption[]>([]);
    const [shsSubjectsLoading, setShsSubjectsLoading] = useState(false);
    const [shsLoadError, setShsLoadError] = useState<string | null>(null);

    // Schedule builder active editing index
    const [editingScheduleIndex, setEditingScheduleIndex] = useState<number | null>(null);

    // Preflight state
    const [preflight, setPreflight] = useState<PreflightResponse | null>(null);
    const [preflightLoading, setPreflightLoading] = useState(false);
    const [preflightError, setPreflightError] = useState<string | null>(null);
    const [clashesExpanded, setClashesExpanded] = useState(false);

    // Scroll to requested initial focus card on mount
    useEffect(() => {
        if (initialFocus === "schedule") {
            requestAnimationFrame(() => {
                document.getElementById("composer-schedule")?.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        } else if (initialFocus === "settings") {
            setPoliciesOpen(true);
            requestAnimationFrame(() => {
                document.getElementById("composer-settings")?.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        }
    }, [initialFocus]);

    // Check scoped localStorage draft once in Create mode
    useEffect(() => {
        if (isEditing) {
            setDraftChecked(true);
            return;
        }
        try {
            const raw = localStorage.getItem(draftStorageKey);
            const parsed: unknown = raw ? JSON.parse(raw) : null;
            if (isClassDraft(parsed)) {
                setSavedDraft(parsed);
            }
        } catch {
            // Ignore malformed storage entries
        } finally {
            setDraftChecked(true);
        }
    }, [isEditing, draftStorageKey]);

    // Dirty-only autosave to scoped localStorage in Create mode
    useEffect(() => {
        if (isEditing || !draftChecked || !isDirty || savedDraft) return;
        const timer = window.setTimeout(() => {
            try {
                const serializable: ClassDraft = {
                    ...formData,
                    settings: {
                        ...formData.settings,
                        banner_image: null,
                    },
                };
                localStorage.setItem(draftStorageKey, JSON.stringify(serializable));
            } catch {
                // Ignore quota/storage errors
            }
        }, 600);
        return () => window.clearTimeout(timer);
    }, [formData, isEditing, draftChecked, isDirty, savedDraft, draftStorageKey]);

    // Warn on unload if dirty and not submitting
    useEffect(() => {
        if (!isDirty || isSubmitting) return;
        const handler = (event: BeforeUnloadEvent): void => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", handler);
        return () => window.removeEventListener("beforeunload", handler);
    }, [isDirty, isSubmitting]);

    const updateForm = useCallback((changes: Partial<ClassFormData>, markDirty = true): void => {
        setFormData((prev) => normalizeClassDelivery({ ...prev, ...changes }));
        if (markDirty) {
            setIsDirty(true);
        }
        setValidationError(null);
    }, []);

    const updateSettings = useCallback((patch: Partial<ClassSettings>): void => {
        setFormData((prev) => ({
            ...prev,
            settings: {
                ...prev.settings,
                ...patch,
            },
        }));
        setIsDirty(true);
        setValidationError(null);
    }, []);

    // Fetch College subjects whenever selected courses change
    const courseKey = formData.course_codes.join(",");
    const fetchCollegeSubjectsRef = useRef(0);

    const loadCollegeSubjects = useCallback(async (courseIds: number[]) => {
        const reqId = ++fetchCollegeSubjectsRef.current;
        const key = courseIds.join(",");
        setCollegeSubjectsError(null);

        if (courseIds.length === 0) {
            setCollegeSubjects([]);
            setLoadedCourseKey(key);
            setCollegeSubjectsLoading(false);
            return;
        }

        setCollegeSubjectsLoading(true);
        try {
            const rows = await fetchOptionData<SubjectOption>(
                subjectOptionsRoute.url({ query: { course_ids: courseIds } }),
                isSubjectOption,
            );
            if (reqId === fetchCollegeSubjectsRef.current) {
                setCollegeSubjects(rows);
                setLoadedCourseKey(key);
            }
        } catch {
            if (reqId === fetchCollegeSubjectsRef.current) {
                setCollegeSubjectsError("Subjects could not be loaded. Check your connection and retry.");
            }
        } finally {
            if (reqId === fetchCollegeSubjectsRef.current) {
                setCollegeSubjectsLoading(false);
            }
        }
    }, []);

    useEffect(() => {
        if (formData.classification !== "college") return;
        if (formData.course_codes.length === 0) {
            setCollegeSubjects([]);
            setLoadedCourseKey("");
            return;
        }
        void loadCollegeSubjects(formData.course_codes);
    }, [formData.classification, courseKey, loadCollegeSubjects]);

    // Keep college subject_ids and academic_year aligned with loaded subjects
    useEffect(() => {
        if (formData.classification !== "college" || loadedCourseKey !== courseKey || collegeSubjectsLoading) return;
        const keptIds = formData.subject_ids.filter((id) => collegeSubjects.some((s) => s.id === id));
        const selected = collegeSubjects.filter((s) => keptIds.includes(s.id));
        const years = Array.from(
            new Set(selected.map((s) => s.academic_year).filter((y): y is number => y !== null)),
        ).sort((a, b) => a - b);

        const patch: Partial<ClassFormData> = {};
        if (keptIds.length !== formData.subject_ids.length) {
            patch.subject_ids = keptIds;
            if (!subjectCodeTouched) {
                patch.subject_code = buildSubjectCodeFromSubjectOptions(keptIds, collegeSubjects);
            }
        }
        if (
            selected.length > 0 &&
            selected.every((s) => s.academic_year !== null) &&
            years.length === 1 &&
            formData.academic_year !== years[0]
        ) {
            patch.academic_year = years[0];
        } else if (years.length > 1 && !years.includes(formData.academic_year)) {
            patch.academic_year = years[0];
        }

        if (Object.keys(patch).length > 0) {
            updateForm(patch, false);
        }
    }, [
        collegeSubjects,
        loadedCourseKey,
        courseKey,
        collegeSubjectsLoading,
        formData.classification,
        formData.subject_ids,
        formData.academic_year,
        subjectCodeTouched,
        updateForm,
    ]);

    // Fetch SHS strands when track changes
    useEffect(() => {
        if (formData.classification !== "shs" || !formData.shs_track_id) {
            setShsStrandOptions([]);
            return;
        }
        const controller = new AbortController();
        setShsStrandsLoading(true);
        setShsLoadError(null);
        fetchOptionData<EntityOption>(
            shsStrands.url({ query: { track_id: formData.shs_track_id } }),
            isEntityOption,
            controller.signal,
        )
            .then((rows) => setShsStrandOptions(rows))
            .catch((err) => {
                if ((err as Error).name !== "AbortError") {
                    setShsLoadError("SHS strands could not be loaded.");
                }
            })
            .finally(() => setShsStrandsLoading(false));

        return () => controller.abort();
    }, [formData.classification, formData.shs_track_id]);

    // Fetch SHS subjects when strand changes
    useEffect(() => {
        if (formData.classification !== "shs" || !formData.shs_strand_id) {
            setShsSubjectOptions([]);
            return;
        }
        const controller = new AbortController();
        setShsSubjectsLoading(true);
        setShsLoadError(null);
        fetchOptionData<ShsSubjectOption>(
            shsSubjects.url({ query: { strand_id: formData.shs_strand_id } }),
            isShsSubjectOption,
            controller.signal,
        )
            .then((rows) => setShsSubjectOptions(rows))
            .catch((err) => {
                if ((err as Error).name !== "AbortError") {
                    setShsLoadError("SHS subjects could not be loaded.");
                }
            })
            .finally(() => setShsSubjectsLoading(false));

        return () => controller.abort();
    }, [formData.classification, formData.shs_strand_id]);

    // Debounced (350ms) live Preflight check with AbortController cleanup
    const preflightSchedules = useMemo<CrudScheduleBlock[]>(
        () =>
            formData.schedules.map((s) => ({
                id: s.id,
                day_of_week: s.day_of_week,
                start_time: s.start_time.slice(0, 5),
                end_time: s.end_time.slice(0, 5),
                room_id: isOnlineDeliveryMode(s.delivery_mode ?? formData.delivery_mode) ? null : s.room_id,
                delivery_mode: canonicalDeliveryMode(s.delivery_mode ?? formData.delivery_mode),
                component_type: s.component_type === "lab" ? "lab" : "lecture",
                status: s.status === "published" ? "published" : "draft",
            })),
        [formData.schedules, formData.delivery_mode],
    );

    const preflightPayload = useMemo(
        () => ({
            class_id: isEditing && classId ? classId : null,
            classification: formData.classification,
            faculty_id: formData.faculty_id,
            room_id: isOnlineDeliveryMode(formData.delivery_mode) ? null : formData.room_id,
            school_year: formData.school_year,
            semester: formData.semester,
            course_codes: formData.classification === "college" ? formData.course_codes : [],
            subject_ids: formData.classification === "college" ? formData.subject_ids : [],
            academic_year: formData.classification === "college" ? formData.academic_year : null,
            grade_level: formData.classification === "shs" ? formData.grade_level : null,
            shs_strand_id: formData.classification === "shs" ? formData.shs_strand_id : null,
            maximum_slots: formData.maximum_slots,
            delivery_mode: formData.delivery_mode,
            schedules: preflightSchedules,
        }),
        [
            isEditing,
            classId,
            formData.classification,
            formData.faculty_id,
            formData.delivery_mode,
            formData.room_id,
            formData.school_year,
            formData.semester,
            formData.course_codes,
            formData.subject_ids,
            formData.academic_year,
            formData.grade_level,
            formData.shs_strand_id,
            formData.maximum_slots,
            preflightSchedules,
        ],
    );

    const preflightSignature = useMemo(() => JSON.stringify(preflightPayload), [preflightPayload]);

    useEffect(() => {
        if (!formData.school_year.trim() || !formData.semester) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setPreflightLoading(true);
            setPreflightError(null);
            postClassPreflight(preflightPayload, controller.signal)
                .then((res) => {
                    if (!controller.signal.aborted) {
                        setPreflight(res);
                    }
                })
                .catch((err) => {
                    if (controller.signal.aborted || (err as Error).name === "AbortError") return;
                    setPreflightError("Live preflight check is temporarily unavailable.");
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setPreflightLoading(false);
                    }
                });
        }, 350);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [preflightSignature, preflightPayload, formData.school_year, formData.semester]);

    // Derived curriculum helpers
    const selectedCollegeSubjects = useMemo(
        () => collegeSubjects.filter((s) => formData.subject_ids.includes(s.id)),
        [collegeSubjects, formData.subject_ids],
    );

    const curriculumYears = useMemo(
        () =>
            Array.from(
                new Set(selectedCollegeSubjects.map((s) => s.academic_year).filter((y): y is number => y !== null)),
            ).sort((a, b) => a - b),
        [selectedCollegeSubjects],
    );

    const allSubjectsResolved =
        selectedCollegeSubjects.length === formData.subject_ids.length &&
        selectedCollegeSubjects.every((s) => s.academic_year !== null);
    const unanimousYear = allSubjectsResolved && curriculumYears.length === 1 ? curriculumYears[0] : null;
    const spansYears = curriculumYears.length > 1;

    const requiredFallbackHours = useMemo(() => {
        if (formData.classification === "college" && selectedCollegeSubjects.length > 0) {
            const lecture = selectedCollegeSubjects.reduce((sum, s) => sum + (s.lecture ?? 0), 0);
            const lab = selectedCollegeSubjects.reduce((sum, s) => sum + (s.laboratory ?? 0), 0);
            return { lecture, lab, total: lecture + lab };
        }
        if (formData.classification === "shs" && formData.subject_code_shs) {
            const match = shsSubjectOptions.find((s) => s.code === formData.subject_code_shs);
            if (match && (match.lecture || match.laboratory)) {
                const lecture = match.lecture ?? 0;
                const lab = match.laboratory ?? 0;
                return { lecture, lab, total: lecture + lab };
            }
        }
        return null;
    }, [formData.classification, formData.subject_code_shs, selectedCollegeSubjects, shsSubjectOptions]);

    const roomsById = useMemo(() => {
        const map = new Map<number, { label: string; capacity?: number | null }>();
        for (const room of options.rooms) {
            const id = Number(room.id);
            if (Number.isFinite(id)) {
                map.set(id, { label: room.label, capacity: room.capacity });
            }
        }
        return map;
    }, [options.rooms]);

    const gridSchedules = useMemo<LiveBusyGridBlock[]>(() => {
        const codeLabel =
            formData.classification === "shs"
                ? formData.subject_code_shs || "Class"
                : formData.subject_code || "Class";
        return preflightSchedules.map((block) => ({
            ...block,
            label: `${codeLabel} ${block.component_type === "lab" ? "Lab" : "Lec"}`,
            room_name: block.room_id ? (roomsById.get(block.room_id)?.label ?? null) : null,
            section: formData.section,
        }));
    }, [preflightSchedules, formData.classification, formData.subject_code, formData.subject_code_shs, formData.section, roomsById]);

    const activeEnrolledCount = editMeta?.active_enrolled_count ?? 0;
    const isBelowEnrolled = isEditing && activeEnrolledCount > 0 && formData.maximum_slots < activeEnrolledCount;
    const belowEnrolledBlocked = isBelowEnrolled && !allowBelowEnrolled;

    const blockingConflicts = useMemo(
        () => (preflight?.conflicts ?? []).filter((c) => c.severity === "blocking"),
        [preflight],
    );
    const warningConflicts = useMemo(
        () => (preflight?.conflicts ?? []).filter((c) => c.severity === "warning"),
        [preflight],
    );

    const topSuggestion = preflight?.suggestions?.[0] ?? null;
    const preflightBlocksPublish = preflight?.can_publish === false;
    const canPublish = !preflightBlocksPublish && !belowEnrolledBlocked;

    const publishDisabledReason = preflightBlocksPublish
        ? `Resolve ${blockingConflicts.length || 1} blocking schedule ${blockingConflicts.length === 1 ? "conflict" : "conflicts"} before publishing, or use Save Draft.`
        : belowEnrolledBlocked
          ? `Capacity is below ${activeEnrolledCount} enrolled students. Increase capacity or confirm the override checkbox.`
          : null;

    // Handlers
    const handleClassificationSwitch = (next: Classification): void => {
        if (next === formData.classification) return;
        if (isEditing) {
            setClassificationSwitchWarn(true);
        }
        setSubjectCodeTouched(false);
        updateForm(
            next === "college"
                ? {
                      classification: "college",
                      shs_track_id: null,
                      shs_strand_id: null,
                      subject_code_shs: "",
                      grade_level: "Grade 11",
                  }
                : {
                      classification: "shs",
                      course_codes: [],
                      subject_ids: [],
                      subject_code: "",
                      academic_year: 1,
                  },
        );
    };

    const handleCapacityChange = (raw: string): void => {
        if (raw === "") {
            setCapacityDraft("");
            return;
        }
        if (!/^\d+$/.test(raw)) return;
        setCapacityDraft(raw);
        const parsed = Number.parseInt(raw, 10);
        if (Number.isFinite(parsed) && parsed >= 1) {
            updateForm({ maximum_slots: Math.min(parsed, 500) });
        }
    };

    const handleCapacityBlur = (): void => {
        const parsed = Number.parseInt(capacityDraft, 10);
        const clamped = Number.isFinite(parsed) ? Math.min(Math.max(parsed, 1), 500) : Math.max(1, formData.maximum_slots || 1);
        setCapacityDraft(String(clamped));
        if (clamped !== formData.maximum_slots) {
            updateForm({ maximum_slots: clamped });
        }
    };

    const handleApplySuggestion = (suggestion: PreflightSuggestion): void => {
        const idx = suggestion.schedule_index;
        if (idx < 0 || idx >= formData.schedules.length) return;

        const nextSchedules = formData.schedules.map((block, blockIdx) => {
            if (blockIdx !== idx) return block;
            if (suggestion.kind === "room" && suggestion.room_id) {
                return {
                    ...block,
                    delivery_mode: "face_to_face" as DeliveryMode,
                    room_id: suggestion.room_id,
                    room_name: suggestion.room_name ?? roomsById.get(suggestion.room_id)?.label ?? block.room_name,
                };
            }
            if (suggestion.kind === "slot") {
                return {
                    ...block,
                    day_of_week: suggestion.day_of_week ?? block.day_of_week,
                    start_time: suggestion.start_time ? suggestion.start_time.slice(0, 5) : block.start_time,
                    end_time: suggestion.end_time ? suggestion.end_time.slice(0, 5) : block.end_time,
                    ...(suggestion.room_id !== undefined
                        ? {
                              room_id: suggestion.room_id,
                              room_name: suggestion.room_name ?? (suggestion.room_id ? roomsById.get(suggestion.room_id)?.label ?? null : null),
                          }
                        : {}),
                };
            }
            return block;
        });

        updateForm({ schedules: nextSchedules });
        toast.success(`Applied suggestion: ${suggestion.label}`);
    };

    const handleRestoreDraft = (): void => {
        if (!savedDraft) return;
        const restored = normalizeClassDelivery({
            ...baseDefaults,
            ...savedDraft,
            // Never overwrite the active school_year or semester props!
            school_year,
            semester,
            settings: {
                ...baseDefaults.settings,
                ...savedDraft.settings,
                banner_image: null,
            },
        });
        setFormData(restored);
        setCapacityDraft(String(restored.maximum_slots));
        setSubjectCodeTouched(Boolean(restored.subject_code));
        setSavedDraft(null);
        setIsDirty(true);
        toast.success("Unsaved draft restored.");
    };

    const handleDiscardDraft = (): void => {
        setSavedDraft(null);
        try {
            localStorage.removeItem(draftStorageKey);
        } catch {
            // Ignore
        }
    };

    const jumpToSection = (sectionId: "composer-details" | "composer-schedule" | "composer-settings"): void => {
        if (sectionId === "composer-settings") {
            setPoliciesOpen(true);
        }
        requestAnimationFrame(() => {
            document.getElementById(sectionId)?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    };

    const submitForm = (targetStatus: "draft" | "published", redirectTo: "show" | "duplicate" | "index"): void => {
        if (isSubmitting) return;

        // Ensure capacity draft is committed
        const parsedCap = Number.parseInt(capacityDraft, 10);
        const committedSlots = Number.isFinite(parsedCap) ? Math.min(Math.max(parsedCap, 1), 500) : Math.max(1, formData.maximum_slots || 1);
        const currentData: ClassFormData = normalizeClassDelivery({
            ...formData,
            section: formData.section.trim(),
            school_year: formData.school_year.trim(),
            maximum_slots: committedSlots,
        });

        const basicsCheck = step1IsValid(currentData, options);
        if (basicsCheck !== true) {
            setValidationError(basicsCheck.message);
            toast.error(basicsCheck.message);
            jumpToSection("composer-details");
            return;
        }

        if (isEditing && activeEnrolledCount > 0 && currentData.maximum_slots < activeEnrolledCount && !allowBelowEnrolled) {
            const msg = `Capacity (${currentData.maximum_slots}) is below ${activeEnrolledCount} enrolled students. Check the override option to proceed.`;
            setValidationError(msg);
            toast.error(msg);
            jumpToSection("composer-details");
            return;
        }

        if (targetStatus === "published") {
            const scheduleCheck = step2IsValid(currentData);
            if (scheduleCheck !== true) {
                setValidationError(scheduleCheck.message);
                toast.error(scheduleCheck.message);
                jumpToSection("composer-schedule");
                return;
            }
            if (preflightBlocksPublish) {
                toast.error("Resolve blocking conflicts before publishing, or save as draft.");
                return;
            }
        }

        const bannerFile = currentData.settings.banner_image instanceof File ? currentData.settings.banner_image : null;

        // Build settings cleanly: omit banner_image completely when no new File is attached!
        const settingsPayload: Record<string, unknown> = {
            background_color: currentData.settings.background_color,
            accent_color: currentData.settings.accent_color,
            theme: currentData.settings.theme,
            enable_announcements: currentData.settings.enable_announcements,
            enable_grade_visibility: currentData.settings.enable_grade_visibility,
            enable_attendance_tracking: currentData.settings.enable_attendance_tracking,
            enable_online_resources: currentData.settings.enable_online_resources ?? true,
            allow_late_submissions: currentData.settings.allow_late_submissions,
            enable_discussion_board: currentData.settings.enable_discussion_board,
            notify_students_on_schedule_changes: currentData.settings.notify_students_on_schedule_changes,
        };

        const customEntries = Object.entries(currentData.settings.custom ?? {}).filter(([k]) => k.trim().length > 0);
        if (customEntries.length > 0) {
            settingsPayload.custom = Object.fromEntries(customEntries);
        } else if (!bannerFile) {
            settingsPayload.custom = {};
        }

        if (bannerFile) {
            settingsPayload.banner_image = bannerFile;
        }

        const serializedSchedules = currentData.schedules.map((s) => {
            const blockMode = canonicalDeliveryMode(s.delivery_mode ?? currentData.delivery_mode);
            const base: Record<string, unknown> = {
                day_of_week: s.day_of_week,
                start_time: s.start_time.slice(0, 5),
                end_time: s.end_time.slice(0, 5),
                delivery_mode: blockMode,
                room_id: isOnlineDeliveryMode(blockMode) ? null : s.room_id,
                component_type: s.component_type === "lab" ? "lab" : "lecture",
                status: targetStatus,
            };
            if (s.pattern_group_id) base.pattern_group_id = s.pattern_group_id;
            if (s.subject_id) base.subject_id = s.subject_id;
            // StoreClassRequest prohibits schedules.*.id; only include id on edit!
            if (isEditing && typeof s.id === "number") {
                base.id = s.id;
            }
            return base;
        });

        const payload: Record<string, unknown> = {
            classification: currentData.classification,
            faculty_id: currentData.faculty_id,
            semester: currentData.semester,
            school_year: currentData.school_year,
            start_date: currentData.start_date || null,
            section: currentData.section,
            delivery_mode: currentData.delivery_mode,
            room_id: isOnlineDeliveryMode(currentData.delivery_mode) ? null : currentData.room_id,
            maximum_slots: currentData.maximum_slots,
            schedules: serializedSchedules,
            schedule_status: targetStatus,
            redirect_to: redirectTo,
            settings: settingsPayload,
        };

        if (currentData.classification === "college") {
            payload.course_codes = currentData.course_codes;
            payload.subject_ids = currentData.subject_ids;
            payload.subject_code = currentData.subject_code;
            payload.academic_year = currentData.academic_year;
        } else {
            payload.shs_track_id = currentData.shs_track_id ?? 0;
            payload.shs_strand_id = currentData.shs_strand_id ?? 0;
            payload.grade_level = currentData.grade_level;
            payload.subject_code_shs = currentData.subject_code_shs;
        }

        if (isEditing) {
            payload.schedules_match_by_id = true;
            if (allowBelowEnrolled) {
                payload.allow_below_enrolled = true;
            }
            if (activeEnrolledCount > 0) {
                payload.notify_enrolled = notifyEnrolled;
            }
            if (removeBannerImage && !bannerFile) {
                payload.remove_banner_image = true;
            }
        }

        const visitCallbacks = {
            preserveScroll: true,
            onStart: () => {
                setIsSubmitting(true);
                setValidationError(null);
                setServerErrors({});
            },
            onSuccess: () => {
                setIsDirty(false);
                if (!isEditing) {
                    try {
                        localStorage.removeItem(draftStorageKey);
                    } catch {
                        // Ignore
                    }
                }
            },
            onError: (errors: Record<string, string>) => {
                setServerErrors(errors);
                const firstKey = Object.keys(errors)[0] ?? "";
                if (firstKey.startsWith("settings")) {
                    jumpToSection("composer-settings");
                } else if (
                    firstKey.startsWith("schedules") ||
                    firstKey === "faculty_id" ||
                    firstKey === "room_id" ||
                    firstKey === "delivery_mode"
                ) {
                    jumpToSection("composer-schedule");
                } else if (firstKey) {
                    jumpToSection("composer-details");
                }
                toast.error("Class could not be saved", {
                    description: Object.values(errors)[0] ?? "Review highlighted fields and try again.",
                });
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        };

        if (isEditing && classId) {
            if (bannerFile) {
                // PHP does not parse multipart/form-data on raw HTTP PATCH; spoof _method=PATCH over POST!
                router.post(
                    updateClassRoute.url(classId),
                    { ...payload, _method: "PATCH" } as Parameters<typeof router.post>[1],
                    { ...visitCallbacks, forceFormData: true },
                );
            } else {
                router.patch(
                    updateClassRoute.url(classId),
                    payload as Parameters<typeof router.patch>[1],
                    visitCallbacks,
                );
            }
            return;
        }

        router.post(
            storeClassRoute.url(),
            payload as Parameters<typeof router.post>[1],
            { ...visitCallbacks, forceFormData: Boolean(bannerFile) },
        );
    };

    const courseSelectOptions = useMemo(
        () => options.courses.map((course) => ({ label: course.label, value: String(course.id) })),
        [options.courses],
    );

    const collegeSubjectSelectOptions = useMemo(
        () =>
            collegeSubjects.map((subject) => {
                const hoursText = formatSubjectHours(subject);
                const metaParts = [
                    subject.course_code ?? "Program",
                    subject.academic_year ? `Year ${subject.academic_year}` : null,
                    hoursText || null,
                ].filter(Boolean);
                return {
                    label: subject.label,
                    value: String(subject.id),
                    description: metaParts.join(" · "),
                    searchText: `${subject.code} ${subject.label}`,
                };
            }),
        [collegeSubjects],
    );

    const quickSectionPills = useMemo(() => {
        if (options.sections.length > 0) return options.sections.map((s) => s.value);
        return ["A", "B", "C", "D"];
    }, [options.sections]);

    const allowSunday = Boolean(options.scheduling_policy?.allow_sunday_scheduling);

    return (
        <div className="space-y-5">
            {/* Archived Class Banner (Edit Mode) */}
            {isEditing && editMeta?.is_archived ? (
                <Alert className="rounded-xl border-amber-500/50 bg-amber-500/10 text-amber-950 dark:text-amber-100">
                    <Archive className="size-4 text-amber-600 dark:text-amber-400" />
                    <AlertTitle className="text-sm font-semibold">Archived Class Offering</AlertTitle>
                    <AlertDescription className="text-xs">
                        This class is currently archived. Saving changes updates the archived record without restoring it automatically.
                    </AlertDescription>
                </Alert>
            ) : null}

            {/* Dismissible Local Draft Banner (Create Mode Only) */}
            {!isEditing && savedDraft ? (
                <div
                    role="status"
                    className="border-primary/30 bg-primary/[0.04] flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg">
                            <RotateCcw className="size-4" aria-hidden="true" />
                        </div>
                        <div>
                            <p className="text-foreground text-xs font-semibold">Unsaved draft found for {school_year}</p>
                            <p className="text-muted-foreground text-xs">
                                Restore your previous composer inputs for this term or discard them to start clean.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 self-end sm:self-auto">
                        <Button type="button" variant="ghost" size="sm" className="h-8 text-xs" onClick={handleDiscardDraft}>
                            Discard
                        </Button>
                        <Button type="button" size="sm" className="h-8 gap-1.5 text-xs font-semibold" onClick={handleRestoreDraft}>
                            <Sparkles className="size-3.5" aria-hidden="true" />
                            Restore unsaved draft
                        </Button>
                    </div>
                </div>
            ) : null}

            {/* Quick-Jump Strip (Zero-Stepper Navigation) */}
            <nav
                aria-label="Composer sections"
                className="bg-card flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3.5 py-2 shadow-2xs"
            >
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-muted-foreground mr-1 text-[11px] font-semibold tracking-wider uppercase">
                        Jump to
                    </span>
                    <button
                        type="button"
                        onClick={() => jumpToSection("composer-details")}
                        className="hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                    >
                        <span className="bg-primary/15 text-primary flex size-4 items-center justify-center rounded-full text-[10px] font-bold">
                            1
                        </span>
                        Curriculum &amp; Capacity
                    </button>
                    <button
                        type="button"
                        onClick={() => jumpToSection("composer-schedule")}
                        className="hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                    >
                        <span className="bg-primary/15 text-primary flex size-4 items-center justify-center rounded-full text-[10px] font-bold">
                            2
                        </span>
                        Teaching &amp; Schedule
                    </button>
                    <button
                        type="button"
                        onClick={() => jumpToSection("composer-settings")}
                        className="hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                    >
                        <span className="bg-primary/15 text-primary flex size-4 items-center justify-center rounded-full text-[10px] font-bold">
                            3
                        </span>
                        Policies &amp; Banner
                    </button>
                </div>

                {isDirty ? (
                    <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-[11px] text-amber-800 dark:text-amber-300">
                        Unsaved changes
                    </Badge>
                ) : (
                    <span className="text-muted-foreground hidden text-xs sm:inline">All sections editable inline</span>
                )}
            </nav>

            {/* Validation & Server Errors */}
            {validationError || Object.keys(serverErrors).length > 0 ? (
                <Alert variant="destructive" role="alert" className="rounded-xl">
                    <AlertTriangle className="size-4" />
                    <AlertTitle className="text-sm font-semibold">Please review the following</AlertTitle>
                    <AlertDescription className="space-y-1 text-xs">
                        {validationError ? <p>{validationError}</p> : null}
                        {Object.entries(serverErrors).map(([key, msg]) => (
                            <p key={key}>{msg}</p>
                        ))}
                    </AlertDescription>
                </Alert>
            ) : null}

            {/* 2-Column Split Workspace */}
            <div className="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                {/* LEFT COLUMN: 3 Stacked Direct-Edit Cards */}
                <div className="min-w-0 space-y-5">
                    {/* CARD 1: Curriculum, Section & Capacity */}
                    <Card id="composer-details" className="scroll-mt-24 rounded-2xl shadow-2xs">
                        <CardHeader className="border-b pb-3.5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2.5">
                                    <span className="bg-muted text-muted-foreground flex size-6 shrink-0 items-center justify-center rounded-md border text-xs font-bold">
                                        1
                                    </span>
                                    <CardTitle className="text-sm font-bold tracking-tight">
                                        Curriculum, Section &amp; Capacity
                                    </CardTitle>
                                </div>

                                {/* College vs SHS Segmented Toggle */}
                                <div
                                    className="bg-muted inline-flex rounded-lg border p-0.5"
                                    role="radiogroup"
                                    aria-label="Academic classification"
                                >
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked={formData.classification === "college"}
                                        onClick={() => handleClassificationSwitch("college")}
                                        className={cn(
                                            "inline-flex h-7 items-center gap-1.5 rounded-md px-3 text-xs font-semibold transition-all",
                                            formData.classification === "college"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        <BookOpen className="size-3.5" aria-hidden="true" />
                                        College
                                    </button>
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked={formData.classification === "shs"}
                                        onClick={() => handleClassificationSwitch("shs")}
                                        className={cn(
                                            "inline-flex h-7 items-center gap-1.5 rounded-md px-3 text-xs font-semibold transition-all",
                                            formData.classification === "shs"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        <GraduationCap className="size-3.5" aria-hidden="true" />
                                        SHS
                                    </button>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-5 pt-4">
                            {isEditing && classificationSwitchWarn ? (
                                <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 px-3.5 py-2.5 text-xs text-amber-900 dark:text-amber-200">
                                    Switching between College and Senior High School clears the previous curriculum fields when saved.
                                </div>
                            ) : null}

                            {/* Curriculum Fields */}
                            {formData.classification === "college" ? (
                                <div className="space-y-4">
                                    {collegeSubjectsError ? (
                                        <Alert variant="destructive" className="rounded-xl">
                                            <AlertDescription className="flex items-center justify-between gap-2 text-xs">
                                                <span>{collegeSubjectsError}</span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    onClick={() => void loadCollegeSubjects(formData.course_codes)}
                                                >
                                                    Retry
                                                </Button>
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="min-w-0 space-y-1.5">
                                            <Label className="text-xs font-semibold">Programs / Courses</Label>
                                            <SearchableMultiSelect
                                                className="min-h-10 text-xs"
                                                options={courseSelectOptions}
                                                selected={formData.course_codes.map(String)}
                                                onChange={(values) => updateForm({ course_codes: toNumbers(values) })}
                                                placeholder="Select one or more programs…"
                                                searchPlaceholder="Search courses…"
                                                emptyText="No matching programs."
                                            />
                                            {serverErrors.course_codes ? (
                                                <p className="text-destructive text-xs">{serverErrors.course_codes}</p>
                                            ) : null}
                                        </div>

                                        <div className="min-w-0 space-y-1.5">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label className="text-xs font-semibold">Subjects</Label>
                                                {requiredFallbackHours && requiredFallbackHours.total > 0 ? (
                                                    <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                                                        {requiredFallbackHours.lecture}h Lec / {requiredFallbackHours.lab}h Lab
                                                    </span>
                                                ) : null}
                                            </div>
                                            <SearchableMultiSelect
                                                className="min-h-10 text-xs"
                                                options={collegeSubjectSelectOptions}
                                                selected={formData.subject_ids.map(String)}
                                                disabled={formData.course_codes.length === 0 || collegeSubjectsLoading}
                                                onChange={(values) => {
                                                    const nextIds = toNumbers(values);
                                                    updateForm({
                                                        subject_ids: nextIds,
                                                        ...(subjectCodeTouched
                                                            ? {}
                                                            : {
                                                                  subject_code: buildSubjectCodeFromSubjectOptions(
                                                                      nextIds,
                                                                      collegeSubjects,
                                                                  ),
                                                              }),
                                                    });
                                                }}
                                                placeholder={
                                                    collegeSubjectsLoading
                                                        ? "Loading subjects…"
                                                        : formData.course_codes.length === 0
                                                          ? "Pick programs first…"
                                                          : "Select subjects…"
                                                }
                                                searchPlaceholder="Search by code or title…"
                                                emptyText="No subjects found for the chosen programs."
                                            />
                                            {serverErrors.subject_ids ? (
                                                <p className="text-destructive text-xs">{serverErrors.subject_ids}</p>
                                            ) : null}
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label htmlFor="composer-subject-code" className="text-xs font-semibold">
                                                Subject code
                                            </Label>
                                            <Input
                                                id="composer-subject-code"
                                                value={formData.subject_code}
                                                onChange={(e) => {
                                                    setSubjectCodeTouched(true);
                                                    updateForm({ subject_code: e.target.value });
                                                }}
                                                placeholder="e.g. IT 201"
                                                className="h-10 rounded-xl font-mono text-xs font-semibold"
                                            />
                                            {serverErrors.subject_code ? (
                                                <p className="text-destructive text-xs">{serverErrors.subject_code}</p>
                                            ) : null}
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label htmlFor="composer-academic-year" className="text-xs font-semibold">
                                                Academic year
                                            </Label>
                                            <Select
                                                value={String(formData.academic_year)}
                                                onValueChange={(value) => updateForm({ academic_year: Number(value) })}
                                                disabled={unanimousYear !== null}
                                            >
                                                <SelectTrigger id="composer-academic-year" className="h-10 w-full rounded-xl">
                                                    <SelectValue placeholder="Choose year level" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.academic_years.map((year) => (
                                                        <SelectItem
                                                            key={year.value}
                                                            value={year.value}
                                                            disabled={
                                                                unanimousYear !== null ||
                                                                (spansYears && !curriculumYears.includes(Number(year.value)))
                                                            }
                                                        >
                                                            {year.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {unanimousYear !== null ? (
                                                <p className="text-muted-foreground text-[11px]">
                                                    Auto-resolved from selected subject curriculum.
                                                </p>
                                            ) : spansYears ? (
                                                <p className="text-amber-700 text-[11px] dark:text-amber-400">
                                                    Spans years {curriculumYears.join(" & ")}; used as fallback cohort year.
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-shs-track" className="text-xs font-semibold">
                                            SHS Track
                                        </Label>
                                        <Select
                                            value={formData.shs_track_id ? String(formData.shs_track_id) : ""}
                                            onValueChange={(value) =>
                                                updateForm({
                                                    shs_track_id: Number(value) || null,
                                                    shs_strand_id: null,
                                                    subject_code_shs: "",
                                                })
                                            }
                                        >
                                            <SelectTrigger id="composer-shs-track" className="h-10 w-full rounded-xl">
                                                <SelectValue placeholder="Choose SHS track" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.shs_tracks.map((track) => (
                                                    <SelectItem key={track.id} value={String(track.id)}>
                                                        {track.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-shs-strand" className="text-xs font-semibold">
                                            SHS Strand
                                        </Label>
                                        <Select
                                            value={formData.shs_strand_id ? String(formData.shs_strand_id) : ""}
                                            onValueChange={(value) =>
                                                updateForm({
                                                    shs_strand_id: Number(value) || null,
                                                    subject_code_shs: "",
                                                })
                                            }
                                            disabled={!formData.shs_track_id || shsStrandsLoading}
                                        >
                                            <SelectTrigger id="composer-shs-strand" className="h-10 w-full rounded-xl">
                                                <SelectValue
                                                    placeholder={shsStrandsLoading ? "Loading strands…" : "Choose strand"}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {shsStrandOptions.map((strand) => (
                                                    <SelectItem key={strand.id} value={String(strand.id)}>
                                                        {strand.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-shs-grade" className="text-xs font-semibold">
                                            Grade level
                                        </Label>
                                        <Select
                                            value={formData.grade_level}
                                            onValueChange={(value) => updateForm({ grade_level: value })}
                                        >
                                            <SelectTrigger id="composer-shs-grade" className="h-10 w-full rounded-xl">
                                                <SelectValue placeholder="Choose grade level" />
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

                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-shs-subject" className="text-xs font-semibold">
                                            SHS Subject
                                        </Label>
                                        <Select
                                            value={formData.subject_code_shs}
                                            onValueChange={(value) => updateForm({ subject_code_shs: value })}
                                            disabled={!formData.shs_strand_id || shsSubjectsLoading}
                                        >
                                            <SelectTrigger id="composer-shs-subject" className="h-10 w-full rounded-xl">
                                                <SelectValue
                                                    placeholder={shsSubjectsLoading ? "Loading subjects…" : "Choose subject"}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {shsSubjectOptions.map((subj) => {
                                                    const hrs = formatSubjectHours(subj);
                                                    return (
                                                        <SelectItem key={subj.code} value={subj.code}>
                                                            {hrs ? `${subj.label} (${hrs})` : subj.label}
                                                        </SelectItem>
                                                    );
                                                })}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {shsLoadError ? (
                                        <p role="alert" className="text-destructive text-xs md:col-span-2">
                                            {shsLoadError}
                                        </p>
                                    ) : null}
                                </div>
                            )}

                            {/* Section Code & Sibling Sections */}
                            <div className="space-y-2.5 border-t pt-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Label htmlFor="composer-section" className="text-xs font-semibold">
                                        Section code
                                    </Label>
                                    <span className="text-muted-foreground text-[11px]">
                                        Quick pick or type custom code (max 20 chars)
                                    </span>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="flex flex-wrap items-center gap-1.5" role="group" aria-label="Quick section presets">
                                        {quickSectionPills.map((sec) => {
                                            const active = formData.section.trim().toUpperCase() === sec.toUpperCase();
                                            return (
                                                <button
                                                    key={sec}
                                                    type="button"
                                                    aria-pressed={active}
                                                    onClick={() => updateForm({ section: sec })}
                                                    className={cn(
                                                        "h-9 min-w-10 rounded-lg border px-3 text-xs font-bold transition-colors",
                                                        active
                                                            ? "border-primary bg-primary text-primary-foreground shadow-2xs"
                                                            : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
                                                    )}
                                                >
                                                    {sec}
                                                </button>
                                            );
                                        })}
                                    </div>

                                    <Input
                                        id="composer-section"
                                        value={formData.section}
                                        maxLength={20}
                                        onChange={(e) => updateForm({ section: e.target.value })}
                                        onBlur={(e) => updateForm({ section: e.target.value.trim() })}
                                        placeholder="e.g. E, STEM-1"
                                        className="h-9 w-40 rounded-lg font-mono text-xs font-semibold"
                                    />
                                </div>
                                {serverErrors.section ? (
                                    <p className="text-destructive text-xs">{serverErrors.section}</p>
                                ) : null}

                                {siblingSections.length > 0 ? (
                                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span className="text-muted-foreground text-[11px] font-medium">Sibling sections:</span>
                                        {siblingSections.map((sibling) => (
                                            <Link
                                                key={sibling.id}
                                                href={isEditing ? editClassRoute.url(sibling.id) : showClassRoute.url(sibling.id)}
                                                className={cn(
                                                    "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors",
                                                    sibling.is_current
                                                        ? "border-primary/40 bg-primary/10 text-primary font-semibold"
                                                        : "border-border bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground",
                                                )}
                                            >
                                                <span>Sec {sibling.section}</span>
                                                <span className="font-mono text-[10px] opacity-80">
                                                    {sibling.students_count}/{sibling.maximum_slots}
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                ) : null}
                            </div>

                            {/* Term, School Year, Start Date & Capacity */}
                            <div className="grid gap-4 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="composer-semester" className="text-xs font-semibold">
                                        Semester
                                    </Label>
                                    <Select
                                        value={formData.semester}
                                        onValueChange={(value) =>
                                            updateForm({ semester: value as "1" | "2" | "summer" })
                                        }
                                    >
                                        <SelectTrigger id="composer-semester" className="h-10 w-full rounded-xl">
                                            <SelectValue placeholder="Semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.semesters.map((sem) => (
                                                <SelectItem key={sem.value} value={sem.value}>
                                                    {sem.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="composer-school-year" className="text-xs font-semibold">
                                        School year
                                    </Label>
                                    <Input
                                        id="composer-school-year"
                                        value={formData.school_year}
                                        onChange={(e) => updateForm({ school_year: e.target.value })}
                                        placeholder="2026-2027"
                                        className="h-10 rounded-xl font-mono text-xs"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="composer-start-date" className="text-xs font-semibold">
                                        Start date
                                    </Label>
                                    <Input
                                        id="composer-start-date"
                                        type="date"
                                        value={formData.start_date ?? ""}
                                        onChange={(e) => updateForm({ start_date: e.target.value || null })}
                                        className="h-10 rounded-xl font-mono text-xs"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between gap-1">
                                        <Label htmlFor="composer-capacity" className="text-xs font-semibold">
                                            Capacity
                                        </Label>
                                        {isEditing && activeEnrolledCount > 0 ? (
                                            <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                                                {activeEnrolledCount} enrolled
                                            </span>
                                        ) : null}
                                    </div>
                                    <Input
                                        id="composer-capacity"
                                        type="text"
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        value={capacityDraft}
                                        onChange={(e) => handleCapacityChange(e.target.value)}
                                        onBlur={handleCapacityBlur}
                                        className={cn(
                                            "h-10 rounded-xl font-mono text-sm font-semibold tabular-nums",
                                            isBelowEnrolled && "border-destructive text-destructive",
                                        )}
                                    />
                                    {serverErrors.maximum_slots ? (
                                        <p className="text-destructive text-xs">{serverErrors.maximum_slots}</p>
                                    ) : null}
                                </div>
                            </div>

                            {/* Below-enrolled capacity warning & explicit override */}
                            {isBelowEnrolled ? (
                                <div className="border-destructive/40 bg-destructive/[0.06] flex flex-col gap-2.5 rounded-xl border p-3.5 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-start gap-2.5">
                                        <AlertTriangle className="text-destructive mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                        <div className="space-y-0.5">
                                            <p className="text-destructive text-xs font-semibold">
                                                Capacity ({formData.maximum_slots}) is below active enrollment ({activeEnrolledCount})
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                Increase max slots or explicitly confirm keeping capacity below currently enrolled students.
                                            </p>
                                        </div>
                                    </div>

                                    <label className="flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border bg-background px-3 py-2 text-xs font-semibold">
                                        <Checkbox
                                            checked={allowBelowEnrolled}
                                            onCheckedChange={(checked) => setAllowBelowEnrolled(checked === true)}
                                        />
                                        <span>Allow below enrolled ({activeEnrolledCount})</span>
                                    </label>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    {/* CARD 2: Teaching Assignment & Schedule Builder */}
                    <ScheduleBuilderCard
                        facultyId={formData.faculty_id}
                        deliveryMode={formData.delivery_mode}
                        roomId={formData.room_id}
                        maximumSlots={formData.maximum_slots}
                        schedules={formData.schedules}
                        faculties={options.faculties}
                        rooms={options.rooms}
                        allowSunday={allowSunday}
                        conflicts={preflight?.conflicts ?? []}
                        roomChecks={preflight?.room_checks ?? []}
                        hours={preflight?.hours ?? null}
                        requiredFallbackHours={requiredFallbackHours}
                        editingIndex={editingScheduleIndex}
                        onEditingIndexChange={setEditingScheduleIndex}
                        onFacultyChange={(faculty_id) => updateForm({ faculty_id })}
                        onDeliveryModeChange={(delivery_mode) => updateForm({ delivery_mode })}
                        onRoomChange={(room_id) => updateForm({ room_id })}
                        onSchedulesChange={(schedules) => updateForm({ schedules })}
                        errors={serverErrors}
                    />

                    {/* CARD 3: Policies, Appearance & Banner */}
                    <PoliciesBannerCard
                        settings={formData.settings}
                        existingBannerUrl={existingBannerUrl}
                        removeBannerImage={removeBannerImage}
                        open={policiesOpen}
                        onOpenChange={setPoliciesOpen}
                        onSettingsChange={updateSettings}
                        onRemoveBannerChange={(nextRemove) => {
                            setRemoveBannerImage(nextRemove);
                            setIsDirty(true);
                        }}
                        errors={serverErrors}
                    />
                </div>

                {/* RIGHT COLUMN (Sticky): Consolidated Preflight Guard + Live Weekly Busy Grid */}
                <aside className="space-y-4 xl:sticky xl:top-20 xl:self-start">
                    {/* Consolidated Preflight Guard Card */}
                    <Card className="rounded-2xl shadow-2xs">
                        <CardHeader className="border-b pb-3">
                            <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    {preflightBlocksPublish ? (
                                        <ShieldAlert className="text-destructive size-4" aria-hidden="true" />
                                    ) : (
                                        <ShieldCheck className="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                    )}
                                    <CardTitle className="text-sm font-bold tracking-tight">Preflight Guard</CardTitle>
                                </div>

                                {preflightLoading ? (
                                    <Badge variant="outline" className="gap-1.5 text-[11px]">
                                        <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                                        Checking…
                                    </Badge>
                                ) : preflightError || !preflight ? (
                                    <Badge variant="outline" className="text-[11px]">Schedule check unavailable</Badge>
                                ) : preflightBlocksPublish ? (
                                    <Badge variant="destructive" className="text-[11px] font-semibold">
                                        {blockingConflicts.length} Blocking {blockingConflicts.length === 1 ? "Conflict" : "Conflicts"}
                                    </Badge>
                                ) : warningConflicts.length > 0 ? (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-500/40 bg-amber-500/10 text-[11px] font-semibold text-amber-800 dark:text-amber-300"
                                    >
                                        {warningConflicts.length} {warningConflicts.length === 1 ? "Warning" : "Warnings"}
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-emerald-500/40 bg-emerald-500/10 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"
                                    >
                                        Schedule clear
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-3.5 pt-3.5">
                            {preflightError ? (
                                <p className="text-muted-foreground rounded-lg border border-dashed px-3 py-2 text-xs">
                                    {preflightError}
                                </p>
                            ) : null}

                            {/* Conflicts & Warnings List */}
                            {blockingConflicts.length === 0 && warningConflicts.length === 0 && !preflightError && preflight ? (
                                <div className="flex items-center gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/[0.06] px-3 py-2.5 text-xs text-emerald-900 dark:text-emerald-200">
                                    <CheckCircle2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                    <span>
                                        {formData.schedules.length === 0
                                            ? "No schedule conflicts detected. Add sessions or save as an unscheduled class."
                                            : "All schedule blocks clear room, faculty, and cohort checks."}
                                    </span>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {blockingConflicts.map((conflict, idx) => (
                                        <div
                                            key={`block-${conflict.kind}-${idx}`}
                                            className="border-destructive/35 bg-destructive/[0.05] rounded-xl border px-3 py-2 text-xs"
                                        >
                                            <div className="flex items-start gap-2">
                                                <AlertTriangle className="text-destructive mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                                <div className="min-w-0 space-y-0.5">
                                                    <p className="text-destructive font-semibold">{conflict.message}</p>
                                                    {conflict.with_class ? (
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Overlaps with <strong className="text-foreground">{conflict.with_class.label}</strong>
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    {warningConflicts.map((warning, idx) => (
                                        <div
                                            key={`warn-${warning.kind}-${idx}`}
                                            className="rounded-xl border border-amber-500/35 bg-amber-500/[0.06] px-3 py-2 text-xs"
                                        >
                                            <div className="flex items-start gap-2">
                                                <AlertTriangle
                                                    className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400"
                                                    aria-hidden="true"
                                                />
                                                <p className="text-amber-950 font-medium dark:text-amber-200">{warning.message}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Single Top Actionable Fix CTA */}
                            {topSuggestion ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-9 w-full justify-center gap-1.5 rounded-xl text-xs font-semibold shadow-2xs"
                                    onClick={() => handleApplySuggestion(topSuggestion)}
                                >
                                    <Wand2 className="size-3.5" aria-hidden="true" />
                                    {formatSuggestionCta(topSuggestion)}
                                </Button>
                            ) : null}

                            {/* Faculty Load Indicator from Preflight */}
                            {preflight?.faculty_load ? (
                                <div className="bg-muted/30 flex items-center justify-between rounded-lg border px-3 py-2 text-xs">
                                    <span className="text-muted-foreground font-medium">Projected instructor load</span>
                                    <span
                                        className={cn(
                                            "font-mono font-semibold tabular-nums",
                                            preflight.faculty_load.max && preflight.faculty_load.hours > preflight.faculty_load.max
                                                ? "text-amber-700 dark:text-amber-400"
                                                : "text-foreground",
                                        )}
                                    >
                                        {Number(preflight.faculty_load.hours.toFixed(1))}h
                                        {preflight.faculty_load.max ? ` / ${preflight.faculty_load.max}h` : ""}
                                    </span>
                                </div>
                            ) : null}

                            {/* Enrolled Student Impact (Edit Mode) */}
                            {isEditing && activeEnrolledCount > 0 && preflight?.student_impact ? (
                                <div className="rounded-xl border p-3">
                                    <Collapsible open={clashesExpanded} onOpenChange={setClashesExpanded}>
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2 text-xs">
                                                <Users className="text-primary size-3.5 shrink-0" aria-hidden="true" />
                                                <span className="text-foreground font-semibold">Enrolled Student Impact</span>
                                            </div>
                                            <span
                                                className={cn(
                                                    "font-mono text-[11px] font-semibold tabular-nums",
                                                    preflight.student_impact.clashing_count > 0
                                                        ? "text-amber-700 dark:text-amber-400"
                                                        : "text-emerald-700 dark:text-emerald-400",
                                                )}
                                            >
                                                {preflight.student_impact.clear_count}/{preflight.student_impact.total_enrolled} clear
                                                {preflight.student_impact.clashing_count > 0
                                                    ? ` · ${preflight.student_impact.clashing_count} ${preflight.student_impact.clashing_count === 1 ? "clash" : "clashes"}`
                                                    : ""}
                                            </span>
                                        </div>

                                        {preflight.student_impact.clashing_students.length > 0 ? (
                                            <>
                                                <CollapsibleTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="text-primary mt-2 inline-flex items-center gap-1 text-[11px] font-semibold hover:underline"
                                                    >
                                                        <span>
                                                            {clashesExpanded ? "Hide" : "View"}{" "}
                                                            {preflight.student_impact.clashing_students.length} clashing{" "}
                                                            {preflight.student_impact.clashing_students.length === 1
                                                                ? "student"
                                                                : "students"}
                                                        </span>
                                                        <ChevronDown
                                                            className={cn(
                                                                "size-3 transition-transform",
                                                                clashesExpanded && "rotate-180",
                                                            )}
                                                            aria-hidden="true"
                                                        />
                                                    </button>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <ul className="mt-2 max-h-40 space-y-1.5 overflow-y-auto border-t pt-2">
                                                        {preflight.student_impact.clashing_students.map((student) => (
                                                            <li
                                                                key={`${student.student_id}-${student.conflicting_slot}`}
                                                                className="bg-muted/30 flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-[11px]"
                                                            >
                                                                <span className="text-foreground truncate font-medium">
                                                                    {student.name}
                                                                </span>
                                                                <span className="text-muted-foreground shrink-0 font-mono text-[10px]">
                                                                    {student.conflicting_subject} · {student.conflicting_slot}
                                                                </span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </CollapsibleContent>
                                            </>
                                        ) : null}
                                    </Collapsible>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    {/* Live Weekly Busy Grid Card */}
                    <Card className="rounded-2xl shadow-2xs">
                        <CardContent className="pt-4">
                            <LiveBusyGrid
                                schedules={gridSchedules}
                                busyOverlay={preflight?.busy_overlay ?? null}
                                conflicts={preflight?.conflicts ?? []}
                                allowSunday={allowSunday}
                                roomsById={roomsById}
                                activeScheduleIndex={editingScheduleIndex}
                                onBlockClick={(idx) => {
                                    setEditingScheduleIndex(idx);
                                    jumpToSection("composer-schedule");
                                }}
                            />
                        </CardContent>
                    </Card>
                </aside>
            </div>

            {/* Direct-Save Sticky Footer Bar */}
            <footer className="bg-card/95 sticky bottom-3 z-30 rounded-2xl border p-3.5 shadow-md backdrop-blur sm:px-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    {/* Left: Cancel + Edit Notify Enrolled Checkbox */}
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9 rounded-xl px-3 text-xs font-semibold"
                            nativeButton={false}
                            render={
                                <Link href={isEditing && classId ? showClassRoute.url(classId) : classesIndexRoute.url()} />
                            }
                        >
                            Cancel
                        </Button>

                        {isEditing && activeEnrolledCount > 0 ? (
                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-xs font-medium">
                                <Checkbox
                                    checked={notifyEnrolled}
                                    onCheckedChange={(checked) => setNotifyEnrolled(checked === true)}
                                />
                                <span>
                                    Notify {activeEnrolledCount} enrolled{" "}
                                    {activeEnrolledCount === 1 ? "student" : "students"} of schedule changes
                                </span>
                            </label>
                        ) : null}
                    </div>

                    {/* Right: Actions (1 Primary CTA) */}
                    <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end">
                        {publishDisabledReason ? (
                            <span className="text-destructive text-right text-[11px] font-medium lg:max-w-xs">
                                {publishDisabledReason}
                            </span>
                        ) : null}

                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10 gap-1.5 rounded-xl px-3.5 text-xs font-semibold"
                                disabled={isSubmitting}
                                onClick={() => submitForm("draft", "show")}
                            >
                                <Save className="size-3.5" aria-hidden="true" />
                                Save Draft
                            </Button>

                            {!isEditing ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-10 gap-1.5 rounded-xl px-3.5 text-xs font-semibold"
                                    disabled={isSubmitting || !canPublish}
                                    onClick={() => submitForm("published", "duplicate")}
                                >
                                    <CopyPlus className="size-3.5" aria-hidden="true" />
                                    Save &amp; Add Next Section
                                </Button>
                            ) : null}

                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger
                                        render={
                                            <span className="inline-flex">
                                                <Button
                                                    type="button"
                                                    className="h-10 gap-1.5 rounded-xl px-5 text-xs font-semibold shadow-xs"
                                                    disabled={isSubmitting || !canPublish}
                                                    onClick={() => submitForm("published", "show")}
                                                >
                                                    {isSubmitting ? (
                                                        <>
                                                            <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                                            Saving…
                                                        </>
                                                    ) : (
                                                        <>
                                                            <CheckCircle2 className="size-3.5" aria-hidden="true" />
                                                            {isEditing ? "Save & Return to Record" : "Publish Class"}
                                                        </>
                                                    )}
                                                </Button>
                                            </span>
                                        }
                                    />
                                    {publishDisabledReason ? (
                                        <TooltipContent>{publishDisabledReason}</TooltipContent>
                                    ) : null}
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}
