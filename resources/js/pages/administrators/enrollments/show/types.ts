import type { User } from "@/types/user";

export interface Branding {
    currency: string;
}

export interface EnrollmentData {
    id: number;
    student_id: string | number;
    status: string;
    workflow_runtime: "legacy" | "policy_v1";
    allows_no_receipt_transition: boolean;
    workflow: {
        current_step_key: string | null;
        terminal_outcome: string | null;
        can_cancel: boolean;
        outstanding_payments: { transactions: number; amount: number };
        allowed_payment_methods: string[];
        steps: Array<{ key: string; label: string; terminal: boolean }>;
        allowed_transitions: Array<{
            key: string;
            label: string;
            to: string;
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
    academic_year: number;
    signature: unknown;
    student: {
        id: number;
        full_name: string;
        email: string | null;
        student_id: string | number;
        course_code: string | null;
        student_url?: string;
    };
    subjects_enrolled: Array<{
        id: number;
        subject_code: string;
        subject_title: string;
        units: number;
        lecture_fee: number;
        lab_fee: number;
    }>;
    class_enrollments: Array<{
        id: number;
        class_id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty: string;
        schedule: string;
        room: string;
        grades: {
            prelim: number | null;
            midterm: number | null;
            finals: number | null;
            average: number | null;
        };
        status: boolean;
    }>;
    missing_classes: Array<{
        class_id: number | null;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty: string;
        available_slots: number | null;
        max_slots: number;
        is_full: boolean;
        enrollment_status?: string;
    }>;
    tuition: {
        total_tuition: number;
        total_lectures: number;
        total_laboratory: number;
        total_miscelaneous_fees: number;
        additional_fees_total: number;
        discount: number;
        downpayment: number;
        required_downpayment: number;
        overall_tuition: number;
        total_balance: number;
        balance_due: number;
        total_paid?: number;
        status?: string;
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
        transaction_number: string;
        invoicenumber: string;
        description: string;
        status: string;
        total_amount: number | null;
        amount: number;
        transaction_date: string;
        created_at: string;
    }>;
    resources: Array<{
        id: number;
        type: string;
        file_name: string;
        file_size: number;
        created_at: string;
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

export interface ShowEnrollmentPageProps {
    user: User;
    enrollment: EnrollmentData;
    auth: {
        user: User;
        can_verify_head: boolean;
        can_verify_cashier: boolean;
        is_super_admin: boolean;
        can_manage_tuition: boolean;
        can_advance_pipeline: boolean;
        can_review_requirements: boolean;
        can_reopen: boolean;
    };
    recent_deletions?: RecentDeletion[];
    enrollment_pipeline: {
        submitted_label: string;
        pending_status: string;
        pending_label: string;
        pending_color: string;
        department_verified_status: string;
        department_verified_label: string;
        department_verified_color: string;
        cashier_verified_status: string;
        cashier_verified_label: string;
        cashier_verified_color: string;
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
            action_type?: "standard" | "department_verification" | "cashier_verification";
            is_completion?: boolean;
        } | null;
    };
}
