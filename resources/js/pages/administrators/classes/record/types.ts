import type { User } from "@/types/user";
import type { CrudFacultyOption, DossierProps } from "../crud/types";
import type { DeliveryMode, DeliverySummary } from "../lib/class-defaults";
import type { EnrollmentStatusValue } from "../workspace/types";

export type WeeklyScheduleEntry = {
    delivery_mode?: DeliveryMode;
    start_time: string;
    end_time: string;
    time_range: string;
    room: {
        id: number | null;
        name: string;
    };
    has_conflict?: boolean;
};

export type RawScheduleEntry = {
    delivery_mode?: DeliveryMode;
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    room_name?: string | null;
    component_type?: "lecture" | "lab" | null;
    pattern_group_id?: string | null;
    status?: "draft" | "published" | null;
    subject_id?: number | null;
    temp_id?: string;
};

// Kept for the legacy timetable component, which is no longer mounted on the record page.
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
    delivery_mode: DeliveryMode;
    delivery_summary?: DeliverySummary;

    subjects: string[];
    associated_courses: string | null;

    subject_code: string;
    subject_title: string;

    section: string;
    year_level: string | null;
    semester: string;
    school_year: string;
    start_date?: string | null;

    students_count: number;
    maximum_slots: number;

    faculty_id?: string | null;
    faculty: {
        id?: string | number | null;
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
            enable_online_resources?: boolean;
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
    enrollment_status?: EnrollmentStatusValue;
    status_reason?: string | null;
    prelim_grade: number | null;
    midterm_grade: number | null;
    finals_grade: number | null;
    total_average: number | null;
    remarks: string | null;
    is_grades_verified?: boolean;
    is_grades_finalized?: boolean;
    is_grade_protected?: boolean;
    grade_symbol?: string | null;
    grade_outcome?: string | null;
    deleted_at?: string | null;
    timetable_status?: "clear" | "clash" | "ok";
    timetable_conflicts?: string[];
    student: {
        id: number;
        student_id: string | number | null;
        name: string;
        course: string | null;
        academic_year: string | number | null;
        email?: string | null;
    } | null;
};

export type RoomOption = {
    id: number;
    label: string;
    capacity?: number | null;
    type?: string | null;
};

export type TransferableClass = {
    id: number;
    section: string;
    label: string;
    is_full: boolean;
    available_slots: number | null;
};

export type ClassShowCapabilities = {
    create?: boolean;
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
    trashed_enrollments?: EnrollmentRow[];
    raw_schedules: RawScheduleEntry[];
    schedule_revision: string;
    rooms: RoomOption[];
    faculties?: CrudFacultyOption[];
    transferable_classes: TransferableClass[];
    dossier?: DossierProps;
}
