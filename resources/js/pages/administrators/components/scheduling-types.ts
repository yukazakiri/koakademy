import type { User } from "@/types/user";
import type { DeliveryMode, DeliverySummary, SchedulingPolicy } from "../classes/lib/class-defaults";
// ── Types ──────────────────────────────────────────────────────────

export type ScheduleEntry = {
    delivery_mode?: DeliveryMode;
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
    delivery_mode?: DeliveryMode;
    delivery_summary?: DeliverySummary;
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

export type ConflictKind = "room" | "faculty" | "section";

export type ScheduleConflict = {
    day: string;
    time: string;
    overlap_start?: string;
    overlap_end?: string;
    overlap_minutes?: number;
    schedule_1_id?: number;
    schedule_2_id?: number;
    room_id?: number | null;
    faculty_id?: string | null;
    class_1: { id?: number; subject_code: string; section: string; room: string | null; faculty: string | null; delivery_mode?: DeliveryMode };
    class_2: { id?: number; subject_code: string; section: string; room: string | null; faculty: string | null; delivery_mode?: DeliveryMode };
    conflict_type: ConflictKind;
    conflict_types?: ConflictKind[];
};

export type HeatmapCell = {
    day: string;
    hour: number;
    active_sessions: number;
    occupied_rooms: number;
    free_rooms: number;
    online_sessions: number;
    conflict_sessions: number;
    room_utilization_pct: number;
};

export type UtilizationSummary = {
    total_active_rooms: number;
    total_scheduled_sessions: number;
    overcrowded_sessions_count: number;
    unassigned_room_sessions_count: number;
    average_room_utilization_pct: number;
    peak_hour: HeatmapCell | null;
    heatmap: HeatmapCell[];
};

export type SuggestedRoom = {
    room_id: number;
    name: string;
    class_code: string | null;
    capacity: number;
    type: string;
    building: string | null;
    fits_capacity: boolean;
    score: number;
    reason: string;
};

export type SuggestedSlot = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    room_id: number | null;
    room_name: string | null;
    same_day: boolean;
    same_room: boolean;
    score: number;
    reason: string;
};

export type ResolutionSuggestions = {
    schedule_id: number;
    available_rooms: SuggestedRoom[];
    available_slots: SuggestedSlot[];
    can_convert_to_online: boolean;
};

export type ScheduleUpdateResponse = {
    schedule: {
        delivery_mode?: DeliveryMode;
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
    class?: ClassScheduleData | null;
    conflicts: ScheduleConflict[];
    utilization?: UtilizationSummary | null;
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
    utilization?: UtilizationSummary;
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
    scheduling_policy?: SchedulingPolicy;
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
        delivery_modes?: Array<{ value: string; label: string }>;
        scheduling_policy?: SchedulingPolicy;
    };
    defaults: { semester: string; school_year: string };
}
