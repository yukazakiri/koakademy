import type { SemesterSelectorProps } from "@/components/semester-selector";
import type { User } from "@/types/user";
import type { EnrollmentRow } from "./columns";

/**
 * Where an applicant sits in the admissions funnel.
 *
 * - `manual` — added by an administrator, no online submission on record
 * - `awaiting_documents` — required uploads are still missing
 * - `ready_for_review` — every document submitted, none reviewed yet
 * - `reviewed` — the document checklist has been fully reviewed
 */
export type ApplicantStage = "manual" | "awaiting_documents" | "ready_for_review" | "reviewed" | "cancelled";

export type ApplicantRow = {
    id: number;
    student_id: number | null;
    lrn: string | null;
    name: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    student_type: string | null;
    student_type_label: string | null;
    academic_year: number | null;
    scholarship_type: string | null;
    scholarship_label: string | null;
    course: string | null;
    course_title: string | null;
    department: string | null;
    department_name: string | null;
    created_at: string | null;
    applied_ago: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
    enrollment_id: number | null;
    pipeline_status: string | null;
    pipeline_step_key: string | null;
    submission_channel: string | null;
    requirements_total: number;
    requirements_submitted: number;
    requirements_reviewed: number;
    requirements_missing: number;
    stage: ApplicantStage;
};

export type ApplicantRequirement = {
    id: number;
    key: string;
    label: string;
    description: string | null;
    required: boolean;
    status: string;
    evidence_path: string | null;
    uploaded_at: string | null;
    verified_at: string | null;
    waived_at: string | null;
    waiver_reason: string | null;
};

export type ApplicantDetail = {
    applicant: {
        id: number;
        student_id: number | null;
        lrn: string | null;
        name: string;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        email: string | null;
        phone: string | null;
        birth_date: string | null;
        gender: string | null;
        civil_status: string | null;
        nationality: string | null;
        religion: string | null;
        address: string | null;
        emergency_contact: string | null;
        student_type: string | null;
        student_type_label: string | null;
        academic_year: number | null;
        scholarship_type: string | null;
        scholarship_label: string | null;
        course_code: string | null;
        course_title: string | null;
        department_code: string | null;
        department_name: string | null;
        remarks: string | null;
        created_at: string | null;
        deleted_at: string | null;
        is_trashed: boolean;
    };
    enrollment: {
        id: number;
        status: string;
        current_step_key: string | null;
        school_year: string | null;
        semester: number | null;
        submission_channel: string | null;
        created_at: string | null;
    } | null;
    requirements: ApplicantRequirement[];
    documents: {
        requirement_id: number;
        key: string;
        label: string;
        path: string;
        uploaded_at: string | null;
    }[];
    cancellation: ApplicantCancellationSummary | null;
    cancellation_history: ApplicantCancellationHistoryEntry[];
};

export type ApplicantFilterOptions = {
    departments: { value: string; label: string }[];
    courses: { value: string; label: string }[];
    student_types: { value: string; label: string }[];
    scholarships: { value: string; label: string }[];
};

export type ApplicantStageMeta = {
    label: string;
    description: string;
    tone: string;
};

export type ApplicantCancellationReason = {
    value: string;
    label: string;
    description: string;
    requires_note: boolean;
    applicant_initiated: boolean;
};

export type ApplicantCancellationSummary = {
    id: number;
    reason_code: string;
    reason_label: string;
    note: string | null;
    cancelled_at: string | null;
    cancelled_by: string | null;
    email_subject: string | null;
    email_body: string | null;
    email_sent_at: string | null;
};

export type ApplicantCancellationHistoryEntry = {
    id: number;
    reason_label: string;
    note: string | null;
    cancelled_at: string | null;
    cancelled_by: string | null;
    reverted_at: string | null;
    revert_reason: string | null;
    email_sent_at: string | null;
};

export type ApplicantStats = {
    total: number;
    recent: number;
    manual: number;
    awaiting_documents: number;
    ready_for_review: number;
    reviewed: number;
    with_scholarship: number;
    cancelled: number;
    deleted: number;
};

export interface EnrollmentManagementProps {
    user: User;
    workflow_setup_required: boolean;
    filament: {
        student_enrollments: {
            index_url: string;
            create_url: string;
        };
    };
    applicantsCount: number;
    available_courses?: { id: number; code: string; title: string | null }[];
    enrollments: {
        data: EnrollmentRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        from: number;
        to: number;
    };
    analytics: {
        current_semester_count: number;
        current_school_year_count: number;
        previous_semester_count: number;
        by_department: { department: string; count: number }[];
        by_year_level: { year_level: number; count: number }[];
        trashed_count: number;
        active_count: number;
        by_status: { status: string; count: number }[];
    };
    filters: SemesterSelectorProps & {
        search?: string;
        sort?: string;
        direction?: "asc" | "desc";
        per_page?: string | number;
        status_filter?: string;
        department_filter?: string;
        year_level_filter?: string;
        course_filter?: string;
    };
    enrollment_pipeline: EnrollmentPipeline;
    assessment_export_options: {
        student_limits: number[];
    };
    enrollment_stats?: {
        cards: Array<{
            key: string;
            label: string;
            metric: "total_records" | "active_records" | "trashed_records" | "status_count" | "paid_count";
            statuses: string[];
            color: string;
        }>;
    };
}

export interface EnrollmentApplicantsProps {
    user: User;
    applicants: {
        data: ApplicantRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        from: number;
        to: number;
    };
    filters: {
        search: string;
        department: string;
        course: string;
        student_type: string;
        scholarship: string;
        stage: string;
        applied_from: string;
        applied_to: string;
        include_deleted: boolean;
        per_page: string | number;
        sort: string;
        direction: "asc" | "desc";
    };
    stats: ApplicantStats;
    stage_counts: Record<string, number>;
    stage_meta: Record<string, ApplicantStageMeta>;
    filter_options: ApplicantFilterOptions;
    cancellation_reasons: ApplicantCancellationReason[];
    enrollment_pipeline: EnrollmentPipeline;
    flash?: { success?: string; error?: string } | null;
}

export interface Branding {
    currency: string;
}

export interface EnrollmentPipeline {
    submitted_label: string;
    entry_step_key?: string;
    completion_step_key?: string;
    pending_status: string;
    pending_label: string;
    pending_color: string;
    pending_roles: string[];
    department_verified_status: string;
    department_verified_label: string;
    department_verified_color: string;
    department_verified_roles: string[];
    cashier_verified_status: string;
    cashier_verified_label: string;
    cashier_verified_color: string;
    cashier_verified_roles: string[];
    additional_steps: Array<{
        status: string;
        label: string;
        color: string;
        allowed_roles: string[];
    }>;
    steps: Array<{
        status: string;
        label: string;
        color: string;
        allowed_roles: string[];
        is_core: boolean;
        key: string;
        action_type?: "standard" | "department_verification" | "cashier_verification";
        is_completion?: boolean;
    }>;
    status_options: Array<{ value: string; label: string }>;
    status_classes: Record<string, string>;
    next_step?: {
        status: string;
        label: string;
        color: string;
        allowed_roles: string[];
        is_core: boolean;
        key: string;
    } | null;
}

export type ReportFilters = {
    course_filter: string;
    subject_filter: string;
    department_filter: string;
    year_level_filter: string;
    status_filter: string;
};

export type BulkReportFilters = {
    course_id: number | null;
    year_level: number | null;
    student_limit: number | null;
    include_deleted: boolean;
};

export type EnrollmentCourseOption = {
    id: number;
    code: string;
    title: string | null;
};

export type EnrollmentStats = {
    applicants: number;
    enrolled: number;
    active: number;
    deleted: number;
    tuition: number;
};
