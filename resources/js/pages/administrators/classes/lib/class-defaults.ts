export type Classification = "college" | "shs";

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
};
export type ShsSubjectOption = { code: string; label: string; title?: string };

export type ClassFormOptions = {
    classifications: ValueOption[];
    sections: ValueOption[];
    semesters: ValueOption[];
    grade_levels: ValueOption[];
    academic_years: ValueOption[];
    day_of_week: ValueOption[];
    courses: EntityOption[];
    faculties: EntityOption[];
    rooms: EntityOption[];
    shs_tracks: EntityOption[];
};

export type ClassSchedule = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number;
};

export type ClassSettings = {
    background_color: string;
    accent_color: string;
    theme: "default" | "modern" | "classic" | "minimal" | "vibrant";
    enable_announcements: boolean;
    enable_grade_visibility: boolean;
    enable_attendance_tracking: boolean;
    allow_late_submissions: boolean;
    enable_discussion_board: boolean;
    notify_students_on_schedule_changes: boolean;
    custom: Record<string, string>;
    banner_image: File | null;
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
    section: string;
    room_id: number;
    maximum_slots: number;
    schedules: ClassSchedule[];
    settings: ClassSettings;
};

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
    section: string;
    room_id: number;
    maximum_slots: number;
    schedules: ClassSchedule[];
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

export function createDefaultSchedule(defaultRoomId: number): ClassSchedule {
    return {
        day_of_week: "Monday",
        start_time: "08:00",
        end_time: "09:00",
        room_id: defaultRoomId,
    };
}

export function buildClassDefaults(defaults: ClassDefaultsInput): ClassFormData {
    const defaultRoomId = Number(defaults.options.rooms[0]?.id ?? 0);

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
        section: "A",
        room_id: defaultRoomId,
        maximum_slots: 40,
        schedules: [createDefaultSchedule(defaultRoomId)],
        settings: {
            background_color: "#ffffff",
            accent_color: "#3b82f6",
            theme: "default",
            enable_announcements: true,
            enable_grade_visibility: true,
            enable_attendance_tracking: false,
            allow_late_submissions: false,
            enable_discussion_board: false,
            notify_students_on_schedule_changes: true,
            custom: {},
            banner_image: null,
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
    if (data.schedules.length === 0) {
        return { message: "Add at least one schedule block." };
    }

    const invalidSchedule = data.schedules.find((schedule) => {
        return !schedule.day_of_week || !schedule.start_time || !schedule.end_time || !schedule.room_id || schedule.end_time <= schedule.start_time;
    });

    if (invalidSchedule) {
        return { message: "Every schedule block needs a day, room, and an end time after the start time." };
    }

    return true;
}

export function step3IsValid(): true {
    return true;
}

export function buildClassStorePayload(data: ClassFormData): ClassStorePayload {
    const common = {
        faculty_id: data.faculty_id,
        semester: data.semester,
        school_year: data.school_year,
        section: data.section,
        room_id: data.room_id,
        maximum_slots: data.maximum_slots,
        schedules: data.schedules,
        settings: data.settings,
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
