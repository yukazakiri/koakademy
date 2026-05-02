export interface ScheduleEntry {
    id: number | string;
    day: string;
    room: string;
    start: string;
    end: string;
    start_24h?: string | null;
    end_24h?: string | null;
    notes?: string | null;
}

export interface StudentEntry {
    id: number | string;
    student_db_id: number | string | null;
    student_id: string;
    name: string;
    email?: string | null;
    status: string;
    grades: {
        prelim?: number | null;
        midterm?: number | null;
        final?: number | null;
        average?: number | null;
    };
}

export interface TeacherEntry {
    id: string | null;
    name: string;
    email?: string | null;
    department?: string | null;
    photo_url?: string | null;
}

export interface QuickAction {
    label: string;
    description: string;
    icon: string;
    href: string;
}

export interface MetricCard {
    label: string;
    value: string | number;
    meta?: string | null;
    icon: string;
}

export interface ClassPostAttachment {
    name: string;
    url: string;
    kind: "link" | "file";
}

export type AssignmentAudienceMode = "all_students" | "specific_students";

export interface AssignmentRubricLevel {
    title: string;
    description?: string | null;
}

export interface AssignmentRubricCriterion {
    title: string;
    description?: string | null;
    points: number;
    levels: AssignmentRubricLevel[];
}

export interface AssignmentStructure {
    instruction?: string | null;
    audience_mode: AssignmentAudienceMode;
    assigned_student_ids: Array<number | string>;
    rubric: AssignmentRubricCriterion[];
}

export interface ClassPostSubmissionEntry {
    id: number;
    student_name: string;
    student_id: string;
    content?: string | null;
    attachments: ClassPostAttachment[];
    points?: number | null;
    status: string;
    submitted_at?: string | null;
    graded_at?: string | null;
}

export interface ClassPostEntry {
    id: number;
    title: string;
    content?: string | null;
    type: string;
    status?: string | null;
    priority?: string | null;
    start_date?: string | null;
    due_date?: string | null;
    progress_percent?: number | null;
    total_points?: number | null;
    assigned_faculty_id?: string | null;
    attachments: ClassPostAttachment[];
    assignment?: AssignmentStructure | null;
    submission_count?: number;
    graded_count?: number;
    my_submission?: {
        id: number;
        points?: number | null;
        status: string;
        submitted_at?: string | null;
        graded_at?: string | null;
    } | null;
    created_at?: string | null;
}

export type AttendanceStatus = "present" | "late" | "absent" | "excused";

export interface AttendanceRecordEntry {
    id: number;
    class_enrollment_id: number;
    student_id: number | string | null;
    status: AttendanceStatus;
    remarks?: string | null;
    student: {
        id: number | string | null;
        name: string;
        student_number: string;
        email?: string | null;
    };
}

export interface AttendanceSessionEntry {
    id: number;
    session_date?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    schedule_id?: number | string | null;
    topic?: string | null;
    notes?: string | null;
    taken_by?: string | null;
    taken_at?: string | null;
    is_locked: boolean;
    locked_at?: string | null;
    is_no_meeting?: boolean;
    no_meeting_reason?: string | null;
    status_counts: Record<AttendanceStatus, number>;
    records: AttendanceRecordEntry[];
}

export interface LinkPreview {
    host: string;
    href: string;
}

export interface CalendarEvent {
    date: string;
    type: "recorded" | "no-meeting" | "missing";
    session_id?: number;
    reason?: string;
    stats?: {
        present: number;
        late: number;
        absent: number;
    };
}

export interface AttendanceOverview {
    sessions: AttendanceSessionEntry[];
    calendar_events?: CalendarEvent[];
    summary: {
        by_status: Record<AttendanceStatus, number>;
        total_sessions: number;
        last_taken_at?: string | null;
    };
}

export type ClassTab = "stream" | "classwork" | "attendance" | "people" | "grades";

export interface ClassSettings {
    accent_color: string | null;
    background_color: string | null;
    banner_image: string | null;
    enable_announcements: boolean;
    enable_grade_visibility: boolean;
    enable_attendance_tracking: boolean;
    enable_performance_analytics: boolean;
    allow_late_submissions: boolean;
    enable_discussion_board: boolean;
    start_date: string | null;
}
