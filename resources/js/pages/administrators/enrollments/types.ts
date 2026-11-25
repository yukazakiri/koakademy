import type { SemesterSelectorProps } from "@/components/semester-selector";
import type { User } from "@/types/user";
import type { EnrollmentRow, EnrollmentTransitionOption } from "./columns";

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

export interface PipelineMeta {
    engine: "policy_v1" | "legacy";
    policy_id: number | null;
    policy_name: string | null;
    policy_version: number | null;
    active_policies_count: number;
    configure_url: string;
    can_configure: boolean;
    available_roles: string[];
}

export interface EnrollmentPeriodSummary {
    id: number | null;
    configured: boolean;
    school_year: string;
    semester: number;
    phase: "unconfigured" | "upcoming" | "regular" | "late" | "closed";
    phase_label: string;
    tone: "neutral" | "info" | "success" | "warning" | "destructive";
    can_enroll: boolean;
    is_late: boolean;
    add_drop_open: boolean;
    enforce_windows: boolean;
    is_manually_closed: boolean;
    auto_apply_late_fee: boolean;
    late_fee_amount: number;
    late_fee_label: string;
    regular_starts_at: string | null;
    regular_ends_at: string | null;
    late_starts_at: string | null;
    late_ends_at: string | null;
    add_drop_deadline_at: string | null;
    notes: string | null;
    summary_text: string;
}

export interface EnrollmentWorkspacePreferences {
    defaultView: "table" | "board";
    density: "comfortable" | "compact";
    showPipelineStrip: boolean;
    showDepartmentChips: boolean;
    showTuitionBars: boolean;
    collapseCompletedLane: boolean;
    completedPreviewLimit: number;
}

export interface EnrollmentIndexPermissions {
    can_verify_head: boolean;
    can_verify_cashier: boolean;
    can_delete: boolean;
    can_reopen: boolean;
    is_super_admin: boolean;
}

export interface EnrollmentDetailSummary {
    enrollment: {
        id: number;
        student_id: number | string | null;
        status: string;
        terminal_outcome: string | null;
        workflow_runtime: string | null;
        current_step_key: string | null;
        allows_no_receipt_transition: boolean;
        school_year: string | null;
        semester: number | null;
        academic_year: number | null;
        course_code: string | null;
        course_title: string | null;
        department: string | null;
        remarks: string | null;
        created_at: string | null;
        deleted_at: string | null;
        is_trashed: boolean;
        show_url: string;
        edit_url: string;
        assessment_download_url: string;
        assessment_preview_url: string;
    };
    student: {
        id: number | null;
        student_number: string | number | null;
        full_name: string;
        email: string | null;
        phone: string | null;
        course_code: string | null;
        student_url: string | null;
    };
    workflow: {
        engine?: "policy_v1" | "legacy";
        current_step_key: string | null;
        terminal_outcome: string | null;
        can_cancel: boolean;
        outstanding_payments?: {
            count: number;
            amount: number;
        };
        allowed_payment_methods: string[];
        steps: EnrollmentPipeline["steps"];
        next_step: EnrollmentPipeline["next_step"];
        allowed_transitions?: EnrollmentTransitionOption[];
    };
    permissions: EnrollmentIndexPermissions & {
        can_advance_pipeline: boolean;
        can_cancel: boolean;
        can_review_requirements?: boolean;
    };
    subjects: Array<{
        id: number;
        code: string;
        title: string;
        units: number;
        lecture_fee: number;
        lab_fee: number;
    }>;
    total_units: number;
    class_enrollments_count: number;
    missing_classes_count: number;
    tuition: {
        id: number;
        overall_tuition: number;
        total_balance: number;
        total_paid: number;
        total_tuition: number;
        total_lectures: number;
        total_laboratory: number;
        total_miscelaneous_fees: number;
        additional_fees_total: number;
        discount: number;
        downpayment: number;
        required_downpayment: number;
        status: string;
    } | null;
    transactions: Array<{
        id: number;
        invoicenumber: string | null;
        description: string | null;
        status: string | null;
        amount: number;
        created_at: string | null;
    }>;
    requirements: Array<{
        id: number;
        key: string;
        label: string;
        status: string;
        required: boolean;
        verified_at: string | null;
        waived_at: string | null;
    }>;
    resources_count: number;
    timeline: Array<{
        id: string;
        type: "created" | "workflow" | "payment" | "deleted";
        title: string;
        description: string;
        timestamp: string | null;
        date_label: string;
        tone: "info" | "success" | "destructive";
    }>;
}

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
    board_enrollments?: EnrollmentRow[];
    permissions?: EnrollmentIndexPermissions;
    pipeline_meta?: PipelineMeta;
    enrollment_period?: EnrollmentPeriodSummary;
    analytics: {
        current_semester_count: number;
        current_school_year_count: number;
        previous_semester_count: number;
        by_department: { department: string; count: number }[];
        by_year_level: { year_level: number; count: number }[];
        trashed_count: number;
        active_count: number;
        by_status: { status: string; count: number }[];
        unpaid_balance_total?: number;
        unpaid_count?: number;
        in_pipeline_count?: number;
        completed_count?: number;
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
        payment_filter?: string;
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
        color?: string;
        allowed_roles?: string[];
        is_core?: boolean;
        key: string;
        action_type?: "standard" | "department_verification" | "cashier_verification" | string;
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
