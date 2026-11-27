import type { CrudFacultyOption, CrudRoomOption } from "../crud/types";

export type Classification = "college" | "shs";
export type DeliveryMode = "face_to_face" | "online" | "online_synchronous";
export type DeliverySummary = DeliveryMode | "mixed";

export type SchedulingPolicy = {
    default_online_delivery_mode: "online" | "online_synchronous";
    check_room_conflicts: boolean;
    check_faculty_conflicts: boolean;
    check_student_cohort_conflicts: boolean;
    check_synchronous_online_conflicts: boolean;
    check_asynchronous_online_conflicts: boolean;
    allow_sunday_scheduling: boolean;
};

export function isOnlineDeliveryMode(mode?: string | null): boolean {
    return mode === "online" || mode === "online_asynchronous" || mode === "online_synchronous";
}

export function isAsynchronousOnlineMode(mode?: string | null): boolean {
    return mode === "online" || mode === "online_asynchronous";
}

export function isSynchronousOnlineMode(mode?: string | null): boolean {
    return mode === "online_synchronous";
}

export function isValidDeliveryMode(mode: unknown): mode is DeliveryMode {
    return mode === "face_to_face" || mode === "online" || mode === "online_synchronous";
}

export function canonicalDeliveryMode(mode?: string | null): DeliveryMode {
    if (mode === "online_synchronous") return "online_synchronous";
    if (mode === "online" || mode === "online_asynchronous") return "online";
    return "face_to_face";
}

export type EntityOption = { id: number | string; label: string };
export type ValueOption = { value: string; label: string };
export type SubjectOption = {
    id: number;
    label: string;
    code: string;
    title?: string;
    course_id: number;
    course_code: string | null;
    academic_year: number | null;
    semester: string | null;
    lecture?: number | null;
    laboratory?: number | null;
    units?: number | null;
};
export type ShsSubjectOption = {
    code: string;
    label: string;
    title?: string;
    lecture?: number | null;
    laboratory?: number | null;
    units?: number | null;
};

export type ClassFormOptions = {
    classifications: ValueOption[];
    delivery_modes?: ValueOption[];
    scheduling_policy?: SchedulingPolicy;
    sections: ValueOption[];
    semesters: ValueOption[];
    grade_levels: ValueOption[];
    academic_years: ValueOption[];
    day_of_week: ValueOption[];
    courses: EntityOption[];
    faculties: CrudFacultyOption[];
    rooms: CrudRoomOption[];
    shs_tracks: EntityOption[];
};

export type ClassSchedule = {
    delivery_mode?: DeliveryMode;
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    room_name?: string | null;
    component_type?: "lecture" | "lab";
    pattern_group_id?: string | null;
    status?: "draft" | "published";
    subject_id?: number | null;
};

export type ClassSettings = {
    background_color: string;
    accent_color: string;
    theme: "default" | "modern" | "classic" | "minimal" | "vibrant";
    enable_announcements: boolean;
    enable_grade_visibility: boolean;
    enable_attendance_tracking: boolean;
    enable_online_resources?: boolean;
    allow_late_submissions: boolean;
    enable_discussion_board: boolean;
    notify_students_on_schedule_changes: boolean;
    custom: Record<string, string>;
    banner_image: File | null;
    banner_image_url?: string | null;
    remove_banner_image?: boolean;
};

export type ClassFormData = {
    classification: Classification;
    course_codes: number[];
    subject_ids: number[];
    subject_code: string;
    academic_year: number;
    shs_track_id: number | null;
    shs_strand_id: number | null;
    subject_code_shs: string;
    grade_level: string;
    faculty_id: string | null;
    semester: "1" | "2" | "summer";
    school_year: string;
    start_date?: string | null;
    section: string;
    delivery_mode: DeliveryMode;
    room_id: number | null;
    maximum_slots: number;
    schedules: ClassSchedule[];
    settings: ClassSettings;
};

export type ClassDraft = Omit<ClassFormData, "delivery_mode"> & { delivery_mode?: DeliveryMode };

export function deliveryModeLabel(mode?: DeliverySummary | string | null): string {
    if (mode === "mixed") return "Mixed";
    if (isSynchronousOnlineMode(mode)) return "Online (Synchronous)";
    if (isAsynchronousOnlineMode(mode)) return "Online (Asynchronous)";
    return "Face to face";
}

export function deliveryModeShortLabel(mode?: DeliverySummary | string | null): string {
    if (mode === "mixed") return "Mixed";
    if (isSynchronousOnlineMode(mode)) return "Online (Sync)";
    if (isAsynchronousOnlineMode(mode)) return "Online (Async)";
    return "Face to face";
}

export function deliveryModeLocationLabel(mode?: DeliveryMode | string | null, roomName?: string | null, fallback = "Room unassigned"): string {
    if (isSynchronousOnlineMode(mode)) return "Online (Sync)";
    if (isAsynchronousOnlineMode(mode)) return "Online (Async)";
    return roomName || fallback;
}

export function meetingDeliverySummary(
    schedules: Pick<ClassSchedule, "delivery_mode">[],
    defaultMode: DeliveryMode = "face_to_face",
): DeliverySummary {
    const normalizedDefault = canonicalDeliveryMode(defaultMode);
    const modes = new Set(schedules.map((schedule) => canonicalDeliveryMode(schedule.delivery_mode ?? normalizedDefault)));
    return modes.size > 1 ? "mixed" : ([...modes][0] ?? normalizedDefault);
}

export function normalizeClassDelivery<T extends { delivery_mode?: DeliveryMode; room_id: number | null; schedules: ClassSchedule[] }>(
    data: T,
): T & { delivery_mode: DeliveryMode } {
    const delivery_mode = canonicalDeliveryMode(data.delivery_mode);

    return {
        ...data,
        delivery_mode,
        room_id: isOnlineDeliveryMode(delivery_mode) ? null : data.room_id || null,
        schedules: data.schedules.map((schedule) => {
            const meetingMode = canonicalDeliveryMode(schedule.delivery_mode ?? delivery_mode);
            return {
                ...schedule,
                delivery_mode: meetingMode,
                room_id: isOnlineDeliveryMode(meetingMode) ? null : schedule.room_id || null,
            };
        }),
    };
}

export type ClassDefaultsInput = {
    semester: "1" | "2" | "summer";
    school_year: string;
    options: ClassFormOptions;
};

export type ValidationResult = true | { message: string };

export type ClassStorePayload = {
    classification: Classification;
    faculty_id: string | null;
    semester: "1" | "2" | "summer";
    school_year: string;
    start_date?: string | null;
    section: string;
    delivery_mode: DeliveryMode;
    room_id: number | null;
    maximum_slots: number;
    schedules: ClassSchedule[];
    schedules_match_by_id?: boolean;
    schedule_status?: "draft" | "published";
    notify_enrolled?: boolean;
    allow_below_enrolled?: boolean;
    redirect_to?: "index" | "show" | "duplicate";
    remove_banner_image?: boolean;
    settings: ClassSettings;
} & (
    | {
          classification: "college";
          course_codes: number[];
          subject_ids: number[];
          subject_code: string;
          academic_year: number;
      }
    | {
          classification: "shs";
          shs_track_id: number;
          shs_strand_id: number;
          grade_level: string;
          subject_code_shs: string;
      }
);

export function createDefaultSchedule(
    defaultRoomId: number | null,
    delivery_mode: DeliveryMode = "face_to_face",
    component_type: "lecture" | "lab" = "lecture",
): ClassSchedule {
    const mode = canonicalDeliveryMode(delivery_mode);
    return {
        day_of_week: "Monday",
        start_time: "08:00",
        end_time: "09:00",
        delivery_mode: mode,
        component_type,
        room_id: isOnlineDeliveryMode(mode) ? null : defaultRoomId || null,
    };
}

export function buildClassDefaults(defaults: ClassDefaultsInput): ClassFormData {
    return {
        classification: "college",
        course_codes: [],
        subject_ids: [],
        subject_code: "",
        academic_year: 1,
        shs_track_id: null,
        shs_strand_id: null,
        subject_code_shs: "",
        grade_level: "Grade 11",
        faculty_id: null,
        semester: defaults.semester,
        school_year: defaults.school_year,
        start_date: null,
        section: "A",
        delivery_mode: "face_to_face",
        room_id: null,
        maximum_slots: 40,
        schedules: [],
        settings: {
            background_color: "#ffffff",
            accent_color: "#3b82f6",
            theme: "default",
            enable_announcements: true,
            enable_grade_visibility: true,
            enable_attendance_tracking: false,
            enable_online_resources: true,
            allow_late_submissions: false,
            enable_discussion_board: false,
            notify_students_on_schedule_changes: true,
            custom: {},
            banner_image: null,
            banner_image_url: null,
            remove_banner_image: false,
        },
    };
}

export function buildSubjectCodeFromSubjectOptions(selectedIds: number[], options: { id: number; code: string }[]): string {
    const selectedCodes = options
        .filter((option) => selectedIds.includes(option.id))
        .map((option) => option.code.trim())
        .filter((code) => code.length > 0);

    return Array.from(new Set(selectedCodes)).join(", ");
}

export function step1IsValid(data: ClassFormData, options: ClassFormOptions): ValidationResult {
    if (!options.classifications.some((option) => option.value === data.classification)) {
        return { message: "Choose College or Senior High School." };
    }

    if (!data.section) {
        return { message: "Choose a section." };
    }

    if (!data.semester || !data.school_year.trim()) {
        return { message: "Confirm the semester and school year." };
    }

    if (!Number.isInteger(data.maximum_slots) || data.maximum_slots < 1) {
        return { message: "Set a capacity of at least one student." };
    }

    if (data.classification === "college") {
        if (data.course_codes.length === 0) {
            return { message: "Choose at least one course." };
        }

        if (data.subject_ids.length === 0) {
            return { message: "Choose at least one subject." };
        }

        if (!data.subject_code.trim()) {
            return { message: "Add a subject code." };
        }

        if (!Number.isInteger(data.academic_year) || data.academic_year < 1) {
            return { message: "Choose an academic year." };
        }
    }

    if (data.classification === "shs") {
        if (!data.shs_track_id) {
            return { message: "Choose an SHS track." };
        }

        if (!data.shs_strand_id) {
            return { message: "Choose an SHS strand." };
        }

        if (!data.grade_level.trim()) {
            return { message: "Choose a grade level." };
        }

        if (!data.subject_code_shs.trim()) {
            return { message: "Choose an SHS subject." };
        }
    }

    return true;
}

export function step2IsValid(data: ClassFormData): ValidationResult {
    const invalidSchedule = data.schedules.find((schedule) => {
        return !schedule.day_of_week || !schedule.start_time || !schedule.end_time || schedule.end_time <= schedule.start_time;
    });

    if (invalidSchedule) {
        return { message: "Each session needs a day and an end time after its start time." };
    }

    const hasOverlap = data.schedules.some((schedule, index) =>
        data.schedules.some(
            (other, otherIndex) =>
                index !== otherIndex &&
                schedule.day_of_week === other.day_of_week &&
                schedule.start_time < other.end_time &&
                schedule.end_time > other.start_time,
        ),
    );

    if (hasOverlap) {
        return { message: "Two sessions overlap on the same day. Adjust their times before saving." };
    }

    return true;
}

export function step3IsValid(): true {
    return true;
}

export function buildClassStorePayload(formData: ClassFormData): ClassStorePayload {
    const data = normalizeClassDelivery(formData);
    const common = {
        faculty_id: data.faculty_id,
        semester: data.semester,
        school_year: data.school_year,
        start_date: data.start_date || null,
        section: data.section,
        delivery_mode: data.delivery_mode,
        room_id: data.room_id,
        maximum_slots: data.maximum_slots,
        schedules: data.schedules,
        settings: {
            ...data.settings,
            enable_online_resources: data.settings.enable_online_resources ?? true,
        },
    };

    if (data.classification === "shs") {
        return {
            ...common,
            classification: "shs",
            shs_track_id: data.shs_track_id ?? 0,
            shs_strand_id: data.shs_strand_id ?? 0,
            grade_level: data.grade_level,
            subject_code_shs: data.subject_code_shs,
        };
    }

    return {
        ...common,
        classification: "college",
        course_codes: data.course_codes,
        subject_ids: data.subject_ids,
        subject_code: data.subject_code,
        academic_year: data.academic_year,
    };
}

export function isClassDraft(value: unknown): value is ClassDraft {
    if (typeof value !== "object" || value === null) return false;
    const draft = value as Partial<ClassFormData>;
    return (
        (draft.classification === "college" || draft.classification === "shs") &&
        Array.isArray(draft.course_codes) &&
        draft.course_codes.every(Number.isInteger) &&
        Array.isArray(draft.subject_ids) &&
        draft.subject_ids.every(Number.isInteger) &&
        typeof draft.subject_code === "string" &&
        typeof draft.subject_code_shs === "string" &&
        Array.isArray(draft.schedules) &&
        draft.schedules.every(
            (schedule) =>
                typeof schedule === "object" &&
                schedule !== null &&
                typeof schedule.day_of_week === "string" &&
                typeof schedule.start_time === "string" &&
                typeof schedule.end_time === "string" &&
                (schedule.delivery_mode === undefined || isValidDeliveryMode(schedule.delivery_mode)) &&
                (typeof schedule.room_id === "number" || schedule.room_id === null),
        ) &&
        (draft.delivery_mode === undefined || isValidDeliveryMode(draft.delivery_mode)) &&
        typeof draft.settings === "object" &&
        draft.settings !== null
    );
}
