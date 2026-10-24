import type { User } from "@/types/user";
// ── Types ──────────────────────────────────────────────────────────

export type ScheduleEntry = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    room: string | null;
    room_id?: number | null;
    component_type?: string;
    pattern_group_id?: string | null;
    status?: string;
};

export type ClassScheduleData = {
    id: number;
    delivery_mode?: "face_to_face" | "online";
    subject_code: string;
    subject_title: string;
    section: string;
    grade_level: string | null;
    year_levels: string[];
    course_year_levels: Record<string, string[]>;
    classification?: string;
    shs_strand_id?: number | null;
    faculty_id?: string | null;
    faculty_name: string | null;
    room_id?: number | null;
    room_name: string | null;
    maximum_slots?: number;
    courses: string;
    course_ids: number[] | null;
    student_count: number;
    lecture_hours?: number;
    lab_hours?: number;
    required_hours?: number;
    scheduled_hours?: number;
    is_unscheduled?: boolean;
    is_complete?: boolean;
    schedules: ScheduleEntry[];
};

export type CourseOption = { id: number; code: string; title: string };
export type RoomOption = { id: number; name: string; class_code: string | null; capacity?: number; type?: string; building?: string | null };
export type FacultyOption = { id: string; name: string; department: string | null };

export type ScheduleConflict = {
    day: string;
    time: string;
    schedule_1_id?: number;
    schedule_2_id?: number;
    class_1: { id?: number; subject_code: string; section: string; room: string | null; faculty: string | null };
    class_2: { id?: number; subject_code: string; section: string; room: string | null; faculty: string | null };
    conflict_type: "room" | "faculty" | "section";
};

export type ScheduleUpdateResponse = {
    schedule: {
        id?: number;
        day_of_week: string;
        start_time: string;
        end_time: string;
        time_range: string;
        room: string | null;
        room_id: number | null;
        component_type?: string;
        pattern_group_id?: string | null;
        status?: string;
    };
    conflicts: ScheduleConflict[];
};

export type ScheduleStats = {
    total_classes: number;
    total_students: number;
    unscheduled_classes_count?: number;
    incomplete_classes_count?: number;
    draft_schedules_count?: number;
    classes_by_year_level: Record<string, number>;
    classes_by_course: Record<string, number>;
    schedule_conflicts: ScheduleConflict[];
};

export type StudentSearchResult = {
    id: number;
    student_id: number;
    name: string;
    course_id: number | null;
    academic_year: number | null;
};

export type StudentSchedule = {
    student: { id: number; student_id: number; name: string; course: string | null; academic_year: number | null };
    schedule: Array<{
        id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty_name: string | null;
        schedules: ScheduleEntry[];
    }>;
};

export interface SchedulingAnalyticsProps {
    user: User;
    schedule_data: ClassScheduleData[];
    stats: ScheduleStats;
    term_context?: {
        school_year: string;
        semester: string;
        semester_label: string;
        available_school_years: string[];
        available_semesters: Array<{ value: string; label: string }>;
    };
    filters: {
        available_courses: CourseOption[];
        available_year_levels: string[];
        available_sections: string[];
        available_rooms: RoomOption[];
        available_faculty: FacultyOption[];
        current_filters: { course: string | null; year_level: string | null; section: string | null };
    };
    creation_options: {
        rooms: RoomOption[];
        faculty: FacultyOption[];
        courses: Array<{ id: number; code: string; title: string; curriculum_year: string | null }>;
        shs_tracks: Array<{ id: number; track_name: string }>;
        shs_strands: Array<{ id: number; strand_name: string; track_id: number; track_name: string | null }>;
        sections: string[];
        semesters: Array<{ value: string; label: string }>;
    };
    defaults: { semester: string; school_year: string };
}
