/**
 * Shared contract for the 3-surface class system:
 *   - Surface 1: Split Composer Studio  (create single class + edit)   -> pages/administrators/classes/composer/*
 *   - Surface 2: Command Dossier Hub    (show / view record)           -> pages/administrators/classes/dossier/*
 *   - Surface 3: Blueprint Matrix       (batch create parallel sections) -> pages/administrators/classes/blueprint/*
 *
 * These shapes are produced by AdministratorClassManagementController (create/edit/show props) and
 * AdministratorClassWorkspaceController (preflight, batchBlueprint, verifyGrades, sendScheduleNotices, quickSettings).
 */
import type { BusyOverlayBlock, StudentImpactSummary, WeeklyBusyOverlay } from "../workspace/types";

export type { BusyOverlayBlock, StudentImpactSummary, WeeklyBusyOverlay };

export type CrudComponentType = "lecture" | "lab";
export type CrudScheduleStatus = "draft" | "published";

export type CrudScheduleBlock = {
    id?: number;
    day_of_week: string;
    start_time: string; // HH:mm
    end_time: string; // HH:mm
    room_id: number | null;
    delivery_mode: string;
    component_type: CrudComponentType;
    status?: CrudScheduleStatus;
};

/* ------------------------------------------------------------------ */
/* Preflight (POST /administrators/classes/preflight)                  */
/* ------------------------------------------------------------------ */

export type PreflightRequest = {
    class_id?: number | null;
    classification: "college" | "shs";
    faculty_id: string | null;
    room_id: number | null;
    school_year: string;
    semester: string;
    course_codes?: number[];
    subject_ids?: number[];
    subject_code?: string;
    academic_year?: number | null;
    grade_level?: string | null;
    shs_strand_id?: number | null;
    maximum_slots: number;
    delivery_mode: string;
    schedules: CrudScheduleBlock[];
};

export type PreflightConflictKind = "room" | "faculty" | "cohort" | "capacity" | "self" | "hours" | "faculty_load";

export type PreflightConflict = {
    kind: PreflightConflictKind;
    severity: "blocking" | "warning";
    /** Index into the request `schedules` array, null when the conflict is not tied to one block. */
    schedule_index: number | null;
    message: string;
    day_of_week?: string;
    start_time?: string;
    end_time?: string;
    with_class?: { id: number; label: string } | null;
};

export type PreflightRoomCheck = {
    schedule_index: number;
    room_id: number | null;
    room_name: string | null;
    capacity: number | null;
    /** null when the room is unassigned or online. */
    fits: boolean | null;
};

export type PreflightSuggestion = {
    schedule_index: number;
    kind: "room" | "slot";
    label: string;
    room_id?: number | null;
    room_name?: string | null;
    capacity?: number | null;
    day_of_week?: string;
    start_time?: string;
    end_time?: string;
    /** Only present for existing classes (class_id sent). */
    student_clashes_count?: number;
    student_clear_count?: number;
};

export type PreflightResponse = {
    hours: { scheduled: number; required: number | null; lecture: number; lab: number };
    conflicts: PreflightConflict[];
    busy_overlay: WeeklyBusyOverlay;
    room_checks: PreflightRoomCheck[];
    suggestions: PreflightSuggestion[];
    student_impact: StudentImpactSummary | null;
    faculty_load: { hours: number; max: number | null } | null;
    /** false when any `blocking` conflict exists. Drives the Publish guard. */
    can_publish: boolean;
};

/* ------------------------------------------------------------------ */
/* Batch blueprint (POST /administrators/classes/batch-blueprint)      */
/* ------------------------------------------------------------------ */

export type BlueprintSharedFields = {
    classification: "college" | "shs";
    course_codes: number[];
    subject_ids: number[];
    subject_code: string;
    academic_year: number;
    shs_track_id: number | null;
    shs_strand_id: number | null;
    subject_code_shs: string;
    grade_level: string;
    school_year: string;
    semester: "1" | "2" | "summer";
    start_date: string | null;
    delivery_mode: string;
    settings?: Partial<ClassPolicySettings>;
};

export type BlueprintSection = {
    /** Stable client id so results/conflicts can be mapped back to a row. */
    client_key: string;
    /** Existing class id, null for a new section. */
    class_id: number | null;
    section: string;
    faculty_id: string | null;
    maximum_slots: number;
    room_id: number | null;
    schedules: CrudScheduleBlock[];
};

export type BatchBlueprintRequest = {
    blueprint: BlueprintSharedFields;
    sections: BlueprintSection[];
    dry_run: boolean;
    schedule_status: CrudScheduleStatus;
    notify_enrolled: boolean;
};

export type BatchBlueprintResult = {
    client_key: string;
    class_id: number | null;
    section: string;
    status: "created" | "updated" | "unchanged" | "error" | "valid";
    errors: string[];
};

export type BatchBlueprintConflict = PreflightConflict & { client_key: string };

export type BatchBlueprintResponse = {
    ok: boolean;
    dry_run: boolean;
    results: BatchBlueprintResult[];
    conflicts: BatchBlueprintConflict[];
    totals: { sections: number; seats: number; scheduled_hours: number; required_hours: number | null };
    message: string;
};

/* ------------------------------------------------------------------ */
/* Grades / notices / quick settings                                   */
/* ------------------------------------------------------------------ */

export type VerifyGradesRequest = {
    /** Omit to act on every eligible enrollment of the class. */
    enrollment_ids?: number[];
    action: "verify" | "unverify" | "finalize" | "unlock";
    notes?: string | null;
};

export type VerifyGradesResponse = {
    updated: number;
    skipped: Array<{ id: number; reason: string }>;
    message: string;
};

export type SendScheduleNoticesResponse = { count: number; recipients: number; failed: number; message: string };

export type ClassPolicySettings = {
    enable_announcements: boolean;
    enable_grade_visibility: boolean;
    enable_attendance_tracking: boolean;
    enable_online_resources: boolean;
    allow_late_submissions: boolean;
    enable_discussion_board: boolean;
    notify_students_on_schedule_changes: boolean;
};

export type QuickSettingsResponse = { settings: ClassPolicySettings; message: string };

/* ------------------------------------------------------------------ */
/* Create / Edit props (AdministratorClassManagementController)         */
/* ------------------------------------------------------------------ */

export type CrudRoomOption = {
    id: number | string;
    label: string;
    capacity?: number | null;
    type?: string | null;
};

export type CrudFacultyOption = {
    id: number | string;
    label: string;
    /** Weekly scheduled contact hours for the class term. */
    load_hours?: number;
    max_load_hours?: number | null;
};

export type SiblingSection = {
    id: number;
    section: string;
    record_title: string;
    faculty: string | null;
    students_count: number;
    maximum_slots: number;
    is_current: boolean;
};

export type EditClassMeta = {
    enrolled_count: number;
    active_enrolled_count: number;
    is_archived: boolean;
    banner_image_url: string | null;
    schedule_revision: string;
};

/* ------------------------------------------------------------------ */
/* Show / Dossier props                                                 */
/* ------------------------------------------------------------------ */

export type DossierHealth = {
    seats: { active: number; max: number; over: number; waitlisted: number; unassigned_cohort: number };
    schedule: {
        scheduled_hours: number;
        required_hours: number | null;
        status: "published" | "draft" | "unscheduled";
        conflict_count: number;
        conflict_summary: string | null;
        pending_notices: number;
        revision_label: string;
    };
    grades: { total: number; verified: number; finalized: number; in_progress: number; pending: number };
    attendance: { average_rate: number | null; sessions_held: number; sessions_expected: number | null };
    posts: { count: number };
    last_activity_at: string | null;
};

export type DossierWaitlistEntry = {
    id: number;
    position: number | null;
    student_id: number;
    student_number: string | number | null;
    student_name: string;
    queued_at: string | null;
    /** Per sibling section id. */
    compatibility: Record<number, { status: "clear" | "full" | "clash"; reason: string | null }>;
};

export type DossierScheduleChange = {
    id: number;
    changed_at: string | null;
    changed_by: string | null;
    summary: string;
    notified_count: number;
    pending_count: number;
};

export type DossierActivityEntry = {
    id: number;
    at: string | null;
    actor: string | null;
    description: string;
    log: "classes" | "class_enrollments" | "class_posts" | string;
};

export type DossierAttendanceSession = {
    id: number;
    date: string | null;
    title: string | null;
    present: number;
    absent: number;
    late: number;
    excused: number;
    is_locked: boolean;
};

export type DossierPost = {
    id: number;
    title: string;
    type: string;
    created_at: string | null;
    author: string | null;
};

export type DossierProps = {
    health: DossierHealth;
    sibling_sections: SiblingSection[];
    waitlist: DossierWaitlistEntry[];
    schedule_changes: DossierScheduleChange[];
    activity: DossierActivityEntry[];
    attendance_sessions: DossierAttendanceSession[];
    posts: DossierPost[];
};
