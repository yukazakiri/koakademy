import type { SemesterSelectorProps } from "@/components/semester-selector";
import type { User } from "@/types/user";
import type { EnrollmentRow } from "./columns";

export type ApplicantRow = {
    id: number;
    student_id: number | null;
    name: string;
    student_type: string | null;
    course: string | null;
    department: string | null;
    academic_year: number | null;
    scholarship_type: string | null;
    created_at: string | null;
    deleted_at?: string | null;
    is_trashed?: boolean;
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
        per_page?: string | number;
        status_filter?: string;
        department_filter?: string;
        year_level_filter?: string;
    };
    enrollment_pipeline: EnrollmentPipeline;
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
        search?: string;
        per_page?: string | number;
    };
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
    course_filter: string;
    year_level_filter: string;
    student_limit: string;
    include_deleted: boolean;
};

export type EnrollmentStats = {
    applicants: number;
    enrolled: number;
    tuition: number;
};
