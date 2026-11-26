import type { SchedulingPolicy } from "../../classes/lib/class-defaults";
import type { EnrollmentPeriodSummary, StudentHoldItem } from "../types";
import type { EditHistoryItem, SectionSchedule } from "../workbench/types";

export interface Branding {
    currency: string;
}

export interface ViewUserSummary {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: string;
}

export interface EnrollmentWaitlistSummary {
    id: number;
    subject_id: number | null;
    subject_enrollment_id: number | null;
    subject_code: string | null;
    subject_title: string | null;
    preferred_class_id: number | null;
    preferred_section: string | null;
    status: string;
    position: number | null;
    notes: string | null;
    created_at: string | null;
}

export interface SubjectEnrolledViewItem {
    id: number;
    subject_id: number | null;
    class_id: number | null;
    class_url: string | null;
    subject_code: string;
    subject_title: string;
    units: number;
    lecture_units: number;
    laboratory_units: number;
    lecture_fee: number | null;
    lab_fee: number | null;
    is_modular: boolean;
    exclude_from_tuition: boolean;
    section: string | null;
    faculty: string;
    schedule: string;
    room: string;
    schedules: SectionSchedule[];
    grades: {
        prelim: number | null;
        midterm: number | null;
        finals: number | null;
        average: number | null;
    } | null;
    grade: number | null;
    has_grades: boolean;
    is_failed_retake: boolean;
    already_passed: boolean;
    prerequisite_satisfied: boolean;
    missing_prerequisite: string | null;
    recommended: boolean;
    waitlist: EnrollmentWaitlistSummary | null;
}

export interface ClassEnrollmentViewItem {
    id: number;
    class_id: number;
    class_url?: string | null;
    subject_code: string;
    subject_title: string;
    section: string;
    faculty: string;
    delivery_mode?: string;
    schedule: string;
    schedules: SectionSchedule[];
    room: string;
    grades: {
        prelim: number | null;
        midterm: number | null;
        finals: number | null;
        average: number | null;
    };
    status: boolean;
}

export interface MissingClassViewItem {
    subject_enrollment_id?: number;
    subject_id?: number;
    class_id: number | null;
    subject_code: string;
    subject_title: string;
    section?: string;
    faculty?: string;
    schedule?: string;
    available_slots?: number | null;
    max_slots?: number;
    enrolled_count?: number;
    is_full?: boolean;
    enrollment_status?: string;
}

export interface EnrollmentTimelineEvent {
    id: string;
    type:
        | "created"
        | "workflow"
        | "audit"
        | "load_change"
        | "payment"
        | "deleted";
    title: string;
    description: string;
    actor: string | null;
    timestamp: string | null;
    date_label: string;
    tone: "info" | "success" | "warning" | "destructive";
}

export interface LoadChangeSummaryItem {
    id: number;
    reference_number: string;
    change_type: string;
    status: string;
    added_subject_code: string | null;
    added_subject_title: string | null;
    dropped_subject_code: string | null;
    dropped_subject_title: string | null;
    units_delta: number;
    tuition_delta: number;
    reason: string | null;
    created_at: string | null;
    slip_url: string;
}

export interface TransferSummaryItem {
    id: number;
    transfer_type: string;
    from_course: string | null;
    to_course: string | null;
    from_section: string | null;
    to_section: string | null;
    reassigned_classes_count: number;
    credited_subjects_count: number;
    fee_delta: number;
    reason: string | null;
    performed_by: string;
    created_at: string | null;
}

export interface EnrollmentData {
    id: number;
    student_id: number;
    course_id: number | null;
    course_code: string | null;
    course_title: string | null;
    department: string | null;
    curriculum_kind: string | null;
    qualification_level: string | null;
    duration_hours: number | null;
    tesda_program_type: string | null;
    status: string;
    workflow_runtime: "legacy" | "policy_v1";
    allows_no_receipt_transition: boolean;
    policy_snapshot_name: string | null;
    policy_snapshot_version: number | null;
    workflow: {
        current_step_key: string | null;
        terminal_outcome: string | null;
        can_cancel: boolean;
        is_completed: boolean;
        is_terminal_locked: boolean;
        can_print_assessment: boolean;
        can_resend_assessment: boolean;
        available_reopen_steps: Array<{
            key: string;
            label: string;
            status: string;
        }>;
        outstanding_payments: { transactions: number; amount: number };
        allowed_payment_methods: string[];
        steps: Array<{ key: string; label: string; terminal: boolean }>;
        allowed_transitions: Array<{
            key: string;
            label: string;
            to: string;
            to_status?: string;
            requires_reason: boolean;
            target_actions: string[];
            payment_configuration: {
                receipt_mode?: "required" | "optional" | "none";
                allow_no_receipt?: boolean;
            } | null;
        }>;
    };
    school_year: string;
    semester: number;
    semester_label: string;
    academic_year: number;
    remarks: string | null;
    submission_channel: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
    student: {
        id: number | null;
        full_name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        emergency_contact_name: string | null;
        emergency_contact_phone: string | null;
        student_id: string | number | null;
        course_code: string | null;
        scholarship_type: string | null;
        scholarship_label: string | null;
        student_url: string | null;
        documents_url: string | null;
    };
    subjects_enrolled: SubjectEnrolledViewItem[];
    class_enrollments: ClassEnrollmentViewItem[];
    missing_classes: MissingClassViewItem[];
    tuition: {
        total_tuition: number;
        total_lectures: number;
        total_laboratory: number;
        total_miscelaneous_fees: number;
        additional_fees_total: number;
        discount: number;
        discount_id?: number | null;
        discount_name?: string | null;
        downpayment: number;
        required_downpayment: number;
        overall_tuition: number;
        total_balance: number;
        balance_due: number;
        total_paid?: number;
        status?: string;
        active_revision_id?: number | null;
        needs_finance_review?: boolean;
        credit?: number;
        assessment_adjustment?: number;
    } | null;
    additional_fees: Array<{
        id: number;
        fee_name: string;
        amount: number;
        is_separate_transaction: boolean;
        transaction_number: string | null;
    }>;
    transactions: Array<{
        id: number;
        transaction_number: string | null;
        invoicenumber: string | null;
        description: string | null;
        status: string | null;
        total_amount: number | null;
        amount: number;
        transaction_date: string | null;
        created_at: string | null;
        receipt_url: string;
    }>;
    latest_invoice: {
        id: number;
        document_number: string | null;
        status: string | null;
        sent_at: string | null;
        download_url: string;
    } | null;
    financial_documents: Array<{
        id: number;
        document_type: string;
        document_number: string | null;
        status: string | null;
        created_at: string | null;
        download_url: string;
    }>;
    resources: Array<{
        id: number;
        type: string;
        file_name: string;
        file_size: number;
        created_at: string | null;
        download_url: string;
    }>;
    requirements: Array<{
        id: number;
        key: string;
        label: string;
        description: string | null;
        status: "pending" | "verified" | "waived";
        required: boolean;
        enforcement_step: string | null;
        evidence_path: string | null;
        document_url: string | null;
        verified_at: string | null;
        waived_at: string | null;
        waiver_reason: string | null;
    }>;
    pending_class_schedule_changes: Array<{
        id: number;
        class_id: number | null;
        class_title: string;
        subject_code: string | null;
        subject_title: string | null;
        section: string | null;
        faculty: string | null;
        old_schedule: string[];
        new_schedule: string[];
        changed_by: string | null;
        changed_at: string | null;
    }>;
    holds: StudentHoldItem[];
    waitlists: EnrollmentWaitlistSummary[];
    checklist_summary: {
        progress_percentage: number;
        failed_retakes_count: number;
        completed_subjects_count: number;
        total_subjects_count: number;
    } | null;
    load_changes_summary: {
        pending_count: number;
        total_count: number;
        recent: LoadChangeSummaryItem[];
    };
    transfers_summary: {
        total_count: number;
        recent: TransferSummaryItem[];
    };
    timeline: EnrollmentTimelineEvent[];
    edit_history: EditHistoryItem[];
}

export interface RecentDeletion {
    id: number;
    log_name: string;
    description: string;
    subject_code: string;
    subject_title: string;
    subject_id: number | null;
    class_id: number | null;
    grade: number | null;
    created_at: string;
    created_at_human: string;
    causer: string;
}

export interface HoldOfficeOption {
    value: string;
    label: string;
}

export interface DocumentLinks {
    assessment_preview_url: string;
    cor_pdf_url: string | null;
    reg_form_student_url: string | null;
    reg_form_adviser_url: string | null;
    reg_form_receipt_url: string | null;
    grade_report_url: string | null;
    soa_url: string | null;
    cashier_payment_url: string | null;
    tuition_adjustments_url: string;
    re_enroll_url: string;
    new_enrollment_url: string;
}

export interface QueueNavigation {
    prev_id: number | null;
    next_id: number | null;
    return_to: string | null;
}

export interface PipelineStep {
    status: string;
    label: string;
    color?: string;
    allowed_roles?: string[];
    is_core?: boolean;
    key: string;
    action_type?: "standard" | "department_verification" | "cashier_verification";
    is_completion?: boolean;
}

export interface ShowEnrollmentPageProps {
    user: ViewUserSummary;
    enrollment: EnrollmentData;
    hold_offices: HoldOfficeOption[];
    enrollment_period: EnrollmentPeriodSummary;
    scheduling_policy?: Partial<SchedulingPolicy>;
    scholarship_options: Array<{ value: string; label: string }>;
    document_links: DocumentLinks;
    queue_navigation: QueueNavigation;
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    } | null;
    auth: {
        user: ViewUserSummary;
        can_manage_enrollment: boolean;
        can_verify_head: boolean;
        can_verify_cashier: boolean;
        is_super_admin: boolean;
        can_manage_tuition: boolean;
        can_advance_pipeline: boolean;
        can_review_requirements: boolean;
        can_reopen: boolean;
        can_delete: boolean;
        can_restore: boolean;
        can_force_delete: boolean;
        can_export_documents: boolean;
    };
    recent_deletions?: RecentDeletion[];
    enrollment_pipeline: {
        submitted_label?: string;
        pending_status: string;
        pending_label: string;
        pending_color?: string;
        department_verified_status: string;
        department_verified_label: string;
        department_verified_color?: string;
        cashier_verified_status: string;
        cashier_verified_label: string;
        cashier_verified_color?: string;
        steps: PipelineStep[];
        status_options: Array<{ value: string; label: string }>;
        status_classes: Record<string, string>;
        next_step?: PipelineStep | null;
    };
}

export type EnrollmentViewLayoutMode = "lifecycle" | "command";
