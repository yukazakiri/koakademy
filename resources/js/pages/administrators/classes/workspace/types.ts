import type { DeliveryMode, DeliverySummary } from "../lib/class-defaults";

export type EnrollmentStatusValue = "enrolled" | "dropped" | "withdrawn" | "incomplete" | "audit";

export type IndexScheduleBlock = {
    id: number;
    day_of_week: string;
    start_time: string | null;
    end_time: string | null;
    component_type: "lecture" | "lab" | string;
    delivery_mode: DeliveryMode | string;
    room_id: number | null;
    room_name: string | null;
    room_capacity: number | null;
    status: "draft" | "published" | string;
    has_conflict: boolean;
    is_overcrowded: boolean;
};

export type ClassScheduleHealth = {
    scheduled_hours: number;
    required_hours: number;
    is_complete: boolean;
    status: "published" | "draft" | "unscheduled";
    has_conflict: boolean;
    has_room_conflict: boolean;
    has_faculty_conflict: boolean;
    is_room_overcrowded: boolean;
    conflict_summary: string | null;
};

export type IndexClassSummary = {
    room: string | null;
    room_capacity: number | null;
    schedules: IndexScheduleBlock[];
    schedule_health: ClassScheduleHealth;
    waitlist_count: number;
    schedule_revision: string;
};

export type WorkspaceEnrollment = {
    id: number;
    student_id: number;
    student: {
        id: number;
        student_id: string | number | null;
        name: string;
        course: string | null;
        academic_year: string | number | null;
        email: string | null;
    } | null;
    status: boolean;
    enrollment_status: EnrollmentStatusValue;
    status_reason: string | null;
    remarks: string | null;
    prelim_grade: number | null;
    midterm_grade: number | null;
    finals_grade: number | null;
    total_average: number | null;
    is_grades_verified: boolean;
    is_grades_finalized: boolean;
    is_grade_protected: boolean;
    deleted_at: string | null;
    timetable_status: "clear" | "clash";
    timetable_conflicts: string[];
};

export type BusyOverlayBlock = {
    class_id: number;
    subject_code: string;
    section: string;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_name: string | null;
    kind: "faculty" | "room" | "cohort";
};

export type WeeklyBusyOverlay = {
    faculty_busy: BusyOverlayBlock[];
    room_busy: BusyOverlayBlock[];
    cohort_busy: BusyOverlayBlock[];
};

export type ClashingStudentImpact = {
    student_id: number;
    student_number: string | number | null;
    name: string;
    enrollment_status: string;
    conflicting_subject: string;
    conflicting_section: string;
    conflicting_slot: string;
};

export type StudentImpactSummary = {
    total_enrolled: number;
    clear_count: number;
    clashing_count: number;
    clashing_students: ClashingStudentImpact[];
};

export type ScheduleImpactResponse = {
    room_conflicts: Array<Record<string, unknown>>;
    faculty_conflicts: Array<Record<string, unknown>>;
    overcrowded_blocks: Array<Record<string, unknown>>;
    student_impact: StudentImpactSummary;
    enrolled_timetable_map: Record<number, { status: "clear" | "clash"; conflicts: string[] }>;
};

export type RankedRoomResolution = {
    room_id: number;
    room_name: string;
    capacity: number | null;
    is_lab?: boolean;
    student_clashes_count: number;
    student_clear_count: number;
    [key: string]: unknown;
};

export type RankedSlotResolution = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id?: number | null;
    room_name?: string | null;
    student_clashes_count: number;
    student_clear_count: number;
    [key: string]: unknown;
};

export type RankedResolutions = {
    schedule_id: number | null;
    target_schedule: Record<string, unknown> | null;
    available_rooms: RankedRoomResolution[];
    available_slots: RankedSlotResolution[];
    can_convert_to_online: boolean;
    online_student_clashes_count: number;
    online_student_clear_count: number;
    online_conversion: {
        can_convert_to_online: boolean;
        delivery_mode: string;
        student_clashes_count: number;
        student_clear_count: number;
    };
};

export type SiblingSectionOption = {
    id: number;
    section: string;
    record_title: string;
    students_count: number;
    maximum_slots: number;
};

export type ClassWorkspacePayload = {
    class: {
        id: number;
        record_title: string;
        subject_code: string;
        subject_title: string;
        section: string;
        school_year: string;
        semester: string | number;
        classification: string;
        delivery_mode: DeliveryMode;
        delivery_summary: DeliverySummary;
        faculty: { id: string; name: string; email?: string | null } | null;
        room: { id: number; name: string; capacity: number | null } | null;
        maximum_slots: number;
        students_count: number;
        active_students_count: number;
        settings: Record<string, unknown>;
    };
    enrollments: WorkspaceEnrollment[];
    schedule_summary: IndexClassSummary | null;
    busy_overlay: WeeklyBusyOverlay;
    resolutions: RankedResolutions;
    student_impact: StudentImpactSummary;
    sibling_sections: SiblingSectionOption[];
    schedule_revision: string;
};

export type StudentSearchCandidate = {
    id: number;
    student_id: string | number | null;
    lrn: string | null;
    first_name: string | null;
    last_name: string | null;
    full_name: string;
    email: string | null;
    course: string | null;
    academic_year: string | number | null;
    already_enrolled_here: boolean;
    existing_enrollment_id: number | null;
    existing_enrollment_status: EnrollmentStatusValue | null;
    sibling_section_enrollment: {
        class_id: number;
        section: string;
        enrollment_id: number;
    } | null;
    holds: {
        active_count: number;
        blocking_count: number;
        offices: string[];
    };
    schedule_conflicts: Array<{
        subject_code?: string;
        section?: string;
        day?: string;
        start_time?: string;
        end_time?: string;
        label?: string;
        [key: string]: unknown;
    }>;
    is_class_full: boolean;
    would_exceed_capacity: boolean;
    recommended_action: "already_enrolled" | "transfer" | "blocked_hold" | "blocked_clash" | "overload_required" | "enroll";
};

export type ParallelSectionEnrollment = {
    id: number;
    student_id: number;
    student_number: string | number | null;
    name: string;
    course: string | null;
    academic_year: string | number | null;
    status: "active" | "inactive";
    enrollment_status: EnrollmentStatusValue;
    has_grades: boolean;
    is_grades_finalized: boolean;
    is_grades_verified: boolean;
    is_grade_protected: boolean;
};

export type ParallelSectionMergeCandidate = {
    target_class_id: number;
    target_section: string;
    target_record_title: string;
    freed_room: string | null;
    moving_count: number;
    projected_target_count: number;
    target_maximum_slots: number;
    clashing_count: number;
} | null;

export type ParallelSectionItem = {
    id: number;
    section: string;
    record_title: string;
    faculty: { id: string; name: string; email?: string | null } | null;
    students_count: number;
    maximum_slots: number;
    room: string | null;
    room_capacity: number | null;
    schedules: IndexScheduleBlock[];
    schedule_health: ClassScheduleHealth | null;
    schedule_revision: string;
    enrollments: ParallelSectionEnrollment[];
    merge_candidate: ParallelSectionMergeCandidate;
};

export type ParallelWaitlistEntry = {
    id: number;
    student_id: number;
    student_number: string | number | null;
    student_name: string;
    course: string | null;
    academic_year: string | number | null;
    position: number | null;
    preferred_class_id: number | null;
    section_compatibility: Record<
        number,
        {
            status: "clear" | "full" | "clash";
            reason: string | null;
        }
    >;
};

export type ParallelSectionsWorkspacePayload = {
    subject_code: string;
    subject_title: string;
    school_year: string;
    semester: intOrString;
    total_enrolled: number;
    total_capacity: number;
    sections: ParallelSectionItem[];
    waitlist: ParallelWaitlistEntry[];
    inspected_student_timetable: {
        student_id: number;
        blocks: Array<{
            class_id?: number;
            subject_code: string;
            section: string;
            day_of_week: string;
            start_time: string;
            end_time: string;
            room_name?: string | null;
        }>;
        section_checks: Record<number, { status: "clear" | "clash"; conflicts: string[] }>;
    } | null;
};

type intOrString = number | string;

export type RoomOption = {
    id: string | number;
    label: string;
    capacity?: number | null;
    type?: string | null;
};

export type EditableScheduleBlock = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    delivery_mode: string;
    component_type: "lecture" | "lab";
};
