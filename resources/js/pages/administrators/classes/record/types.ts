import type { User } from "@/types/user";

export type WeeklyScheduleEntry = {
    delivery_mode?: "face_to_face" | "online";
    start_time: string;
    end_time: string;
    time_range: string;
    room: {
        id: number | null;
        name: string;
    };
    has_conflict: boolean;
};

export type RawScheduleEntry = {
    delivery_mode?: "face_to_face" | "online";
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    temp_id?: string;
};

export type RoomScheduleEntry = {
    id: number;
    class_id: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    title: string;
};

export type ClassShowData = {
    id: number;
    record_title: string;
    classification: string;
    delivery_mode: "face_to_face" | "online";
    delivery_summary?: "face_to_face" | "online" | "mixed";

    subjects: string[];
    associated_courses: string | null;

    subject_code: string;
    subject_title: string;

    section: string;
    year_level: string | null;
    semester: string;
    school_year: string;

    students_count: number;
    maximum_slots: number;

    faculty: {
        name: string;
        email: string | null;
        avatar_url: string | null;
    } | null;

    schedule: Record<string, WeeklyScheduleEntry[]>;

    settings: {
        background_color: string | null;
        accent_color: string | null;
        theme: string | null;
        banner_image_url: string | null;
        features: {
            enable_announcements: boolean;
            enable_grade_visibility: boolean;
            enable_attendance_tracking: boolean;
            allow_late_submissions: boolean;
            enable_discussion_board: boolean;
            notify_students_on_schedule_changes: boolean;
        };
        custom: Record<string, string>;
    };

    filament: {
        view_url: string;
        edit_url: string;
    };
    deleted_at: string | null;
    is_archived: boolean;
};

export type EnrollmentRow = {
    id: number;
    student_enrollment_id: number | null;
    status: boolean | null;
    prelim_grade: number | null;
    midterm_grade: number | null;
    finals_grade: number | null;
    total_average: number | null;
    remarks: string | null;
    student: {
        id: number;
        student_id: string | number | null;
        name: string;
        course: string | null;
        academic_year: string | number | null;
    } | null;
};

export type RoomOption = {
    id: number;
    label: string;
};

export type TransferableClass = {
    id: number;
    section: string;
    label: string;
    is_full: boolean;
    available_slots: number;
};

export type ClassShowCapabilities = {
    update: boolean;
    delete: boolean;
    restore: boolean;
    force_delete: boolean;
};

export interface ClassShowProps {
    user: User;
    class: ClassShowData;
    capabilities?: ClassShowCapabilities;
    enrollments: EnrollmentRow[];
    raw_schedules: RawScheduleEntry[];
    room_schedules: Record<number, RoomScheduleEntry[]>;
    rooms: RoomOption[];
    transferable_classes: TransferableClass[];
}
