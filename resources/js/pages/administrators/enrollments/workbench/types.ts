import type { EnrollmentPeriodSummary, StudentHoldItem } from "../types";

export interface User {
    name: string;
    email: string;
    avatar: string;
    role: string;
}

export interface StudentOption {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    course_id: number | null;
    course_code: string | null;
    course_name?: string | null;
    curriculum_kind?: string | null;
    qualification_level?: string | null;
    duration_hours?: number | null;
    tesda_program_type?: string | null;
    duration_years?: number | string | null;
    internship_hours?: number | null;
    bundled_qualifications?: string[] | null;
    academic_year: number | null;
    formatted_academic_year: string;
    label: string;
}

export interface ExistingEnrollmentSummary {
    id: number;
    status: string;
    school_year: string;
    semester: number;
}

export interface ChecklistSummary {
    progress_percentage: number;
    failed_retakes_count: number;
    completed_subjects_count: number;
}

export interface StudentDetails {
    id: number;
    student_id?: string | null;
    full_name: string;
    email: string;
    course_id: number | null;
    course_code: string | null;
    course_name?: string | null;
    curriculum_kind?: string | null;
    qualification_level?: string | null;
    duration_hours?: number | null;
    tesda_program_type?: string | null;
    duration_years?: number | string | null;
    internship_hours?: number | null;
    bundled_qualifications?: string[] | null;
    scholarship_type?: string | null;
    scholarship_label?: string | null;
    academic_year: number | null;
    formatted_academic_year: string;
    miscellaneous_fee: number;
    recommended_unit_cap?: number;
    existing_enrollment?: ExistingEnrollmentSummary | null;
    checklist_summary?: ChecklistSummary | null;
    active_holds?: StudentHoldItem[];
    billing_configuration?: BillingConfiguration;
}

export interface SubjectOption {
    id: number;
    code: string;
    title: string;
    academic_year?: number | null;
    semester?: number | null;
    lecture: number;
    laboratory: number;
    lec_per_unit: number;
    lab_per_unit: number;
    has_classes: boolean;
    checklist_status?: string | null;
    already_passed?: boolean;
    is_failed_retake?: boolean;
    prerequisite_satisfied?: boolean;
    missing_prerequisite?: string | null;
    recommended?: boolean;
    label: string;
}

export interface SectionSchedule {
    day: string;
    start_time: string;
    end_time: string;
    room: string | null;
}

export interface SectionOption {
    id: number;
    section: string;
    faculty: string;
    enrolled_count: number;
    max_slots: number;
    available_slots: number | null;
    is_full: boolean;
    schedule: string;
    schedules: SectionSchedule[];
    label: string;
}

export interface WaitlistBadgeItem {
    id: number;
    subject_id: number;
    subject_enrollment_id: number;
    preferred_class_id: number | null;
    position: number;
    status: string;
    notes: string | null;
}

export interface EditHistoryItem {
    id: number;
    event: string;
    description: string;
    causer_name: string;
    created_at: string | null;
    changes: Record<string, unknown>;
}

export interface LatestInvoiceSummary {
    id: number;
    document_number: string | null;
    status: string | null;
    issued_at: string | null;
}

export interface DraftSubjectEnrollment {
    id?: number;
    subject_id: number;
    subject_code: string;
    subject_title: string;
    class_id: number | null;
    class_section?: string | null;
    is_modular: boolean;
    exclude_from_tuition: boolean;
    lecture_units: number;
    laboratory_units: number;
    lecture_fee: number;
    laboratory_fee: number;
    lec_per_unit: number;
    lab_per_unit: number;
    has_classes?: boolean;
    has_grades?: boolean;
    grade_summary?: string | null;
    prerequisite_satisfied?: boolean;
    missing_prerequisite?: string | null;
    already_passed?: boolean;
    is_failed_retake?: boolean;
    sections?: SectionOption[];
    loading_sections?: boolean;
    calculating_fees?: boolean;
}

export interface DraftAdditionalFee {
    id?: number;
    fee_name: string;
    amount: string;
    is_separate_transaction?: boolean;
    transaction_number?: string | null;
}

export interface DiscountOption {
    id: number;
    name: string;
    percentage: number;
}

export interface BillingConfiguration {
    nstp_lecture_multiplier: number;
    modular_laboratory_multiplier: number;
    modular_fee: number;
    default_downpayment: number;
    discount_scope?: "lecture_only" | "lecture_and_laboratory";
    course_lecture_rate_per_unit?: number;
    course_laboratory_rate_per_unit?: number;
    miscellaneous_fee_override?: number | null;
}

export interface EnrollmentTuitionProps {
    discount: number;
    discount_id: number | null;
    downpayment: number;
    miscellaneous_fee: number;
    overall_tuition?: number;
    total_tuition?: number;
    total_balance?: number;
    total_paid?: number;
    balance_due?: number;
    credit?: number;
    payment_status?: string;
    active_revision_id?: number | null;
    needs_finance_review?: boolean;
    assessment_adjustment?: number;
}

export interface EnrollmentEditRecord {
    id: number;
    semester: number;
    academic_year: number;
    school_year: string;
    status?: string | null;
    terminal_outcome?: string | null;
    remarks?: string | null;
    student: StudentDetails;
    subjects: DraftSubjectEnrollment[];
    tuition: EnrollmentTuitionProps | null;
    latest_invoice?: LatestInvoiceSummary | null;
    additional_fees: {
        id?: number;
        fee_name: string;
        amount: number;
        is_separate_transaction?: boolean;
        transaction_number?: string | null;
    }[];
}

export interface EnrollmentWorkbenchPageProps {
    billingConfiguration: BillingConfiguration;
    enrollment_period?: EnrollmentPeriodSummary;
    active_holds?: StudentHoldItem[];
    waitlists?: WaitlistBadgeItem[];
    edit_history?: EditHistoryItem[];
    is_terminal_locked?: boolean;
    can_manage_tuition_fees?: boolean;
    recommended_unit_cap?: number;
    checklist_summary?: ChecklistSummary | null;
    user: User;
    settings: {
        currentSemester: number;
        currentSchoolYear: string;
        availableSemesters:
            | Record<string, string>
            | { value: number | string; label: string }[];
        availableAcademicYears: Record<string, string>;
    };
    discounts: DiscountOption[];
    enrollment?: EnrollmentEditRecord;
    flash?: {
        success?: string;
        error?: string;
    };
}

export interface EnrollmentTotals {
    lectureFees: number;
    labFees: number;
    modularFees: number;
    discountAmount: number;
    discountedLecture: number;
    discountedLab: number;
    totalTuition: number;
    miscFee: number;
    additionalFeesTotal: number;
    overallTotal: number;
    downpaymentAmount: number;
    projectedBalance: number;
    totalLectureUnits: number;
    totalLabUnits: number;
    totalUnits: number;
    excludedCount: number;
}

export interface ScheduleConflict {
    subjectAId: number;
    subjectACode: string;
    subjectBId: number;
    subjectBCode: string;
    day: string;
    overlapWindow: string;
}

export interface PendingOverloadSection {
    subjectCode: string;
    subjectTitle: string;
    section: string;
    enrolledCount: number;
    maxSlots: number;
}

export interface PendingOverloadConfirmation {
    fullSections: PendingOverloadSection[];
}

export interface DraftDiffSummary {
    isDirty: boolean;
    addedSubjectIds: number[];
    removedSubjects: DraftSubjectEnrollment[];
    changedSectionSubjectIds: number[];
    gradedRemovals: DraftSubjectEnrollment[];
    previousTotal: number;
    tuitionDelta: number;
    unitsDelta: number;
    projectedBalanceAfterPaid: number;
    projectedOverpaymentCredit: number;
}

export function normalizeSemesterOptions(
    availableSemesters:
        | Record<string, string>
        | { value: number | string; label: string }[]
        | null
        | undefined,
): { value: string; label: string }[] {
    if (Array.isArray(availableSemesters)) {
        return availableSemesters.map((item) => ({
            value: String(item.value),
            label: item.label,
        }));
    }
    if (availableSemesters && typeof availableSemesters === "object") {
        return Object.entries(availableSemesters).map(([value, label]) => ({
            value: String(value),
            label: String(label),
        }));
    }
    return [
        { value: "1", label: "1st Semester" },
        { value: "2", label: "2nd Semester" },
    ];
}
