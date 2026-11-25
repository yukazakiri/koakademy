import type { User } from "@/types/user";
import type { EnrollmentPeriodSummary } from "../types";

export interface StudentOption {
    id: number;
    student_id: string | number | null;
    full_name: string;
    email: string;
    course_id: number | null;
    course_code: string | null;
    course_title?: string | null;
    curriculum_kind?: string | null;
    qualification_level?: string | null;
    tesda_program_type?: string | null;
    duration_hours?: number | null;
    duration_years?: number | string | null;
    internship_hours?: number | null;
    bundled_qualifications?: string[] | null;
    academic_year: number | null;
    formatted_academic_year: string | null;
    label: string;
}

export interface StudentDetails {
    id: number;
    student_id: string | number | null;
    full_name: string;
    email: string;
    course_id: number | null;
    course_code: string | null;
    course_name: string | null;
    curriculum_kind?: string | null;
    qualification_level?: string | null;
    duration_hours?: number | null;
    tesda_program_type?: string | null;
    duration_years?: number | string | null;
    internship_hours?: number | null;
    bundled_qualifications?: string[] | null;
    academic_year: number | null;
    formatted_academic_year: string | null;
    miscellaneous_fee: number;
}

export interface SubjectOption {
    id: number;
    code: string;
    title: string;
    lecture: number;
    laboratory: number;
    lec_per_unit: number;
    lab_per_unit: number;
    has_classes: boolean;
    label: string;
}

export interface SectionOption {
    id: number;
    section: string;
    faculty: string;
    enrolled_count: number;
    max_slots: number;
    available_slots: number | null;
    is_full: boolean;
    label: string;
    schedule: string;
    schedules?: {
        day: string;
        start_time: string;
        end_time: string;
        room: string | null;
    }[];
}

export interface SubjectEnrollment {
    id: string;
    subject_id: number;
    subject_code: string;
    subject_title: string;
    class_id: number | null;
    is_modular: boolean;
    exclude_from_tuition: boolean;
    lecture_units: number;
    laboratory_units: number;
    lecture_fee: number;
    laboratory_fee: number;
    lec_per_unit: number;
    lab_per_unit: number;
    section_options: SectionOption[];
    loading_sections: boolean;
    has_classes: boolean;
}

export interface AdditionalFee {
    id: string;
    fee_name: string;
    amount: number;
}

export interface DiscountOption {
    id: number;
    name: string;
    percentage: number;
}

export interface PendingOverloadConfirmation {
    mode: "select" | "submit";
    subjectId?: number;
    classId?: number;
    sections: Array<{
        subject_code: string;
        section: SectionOption;
    }>;
}

export interface EnrollmentSubject {
    subject_id: number;
    subject_code: string;
    subject_title: string;
    class_id: number | null;
    is_modular: boolean;
    exclude_from_tuition: boolean;
    lecture_units: number;
    laboratory_units: number;
    lecture_fee: number;
    laboratory_fee: number;
    lec_per_unit: number;
    lab_per_unit: number;
    has_classes: boolean;
}

export interface EnrollmentData {
    id: number;
    semester: number;
    academic_year: number;
    school_year: string;
    student: StudentDetails;
    subjects: EnrollmentSubject[];
    tuition: {
        discount: number;
        discount_id: number | null;
        downpayment: number;
        miscellaneous_fee: number;
    } | null;
    additional_fees: Array<{
        fee_name: string;
        amount: number;
    }>;
}

export interface BillingConfiguration {
    nstp_lecture_multiplier: number;
    modular_laboratory_multiplier: number;
    modular_fee: number;
    default_downpayment: number;
    discount_scope?: "lecture_only" | "lecture_and_laboratory";
    course_lecture_rate_per_unit?: number;
    course_laboratory_rate_per_unit?: number;
}

export interface CreateEnrollmentProps {
    billingConfiguration: BillingConfiguration;
    enrollment_period?: EnrollmentPeriodSummary;
    user: User;
    settings: {
        currentSemester: number;
        currentSchoolYear: string;
        availableSemesters: { value: number; label: string }[];
        availableAcademicYears: Record<string, string>;
    };
    discounts: DiscountOption[];
    enrollment?: EnrollmentData;
    flash?: {
        success?: string;
        error?: string;
    };
}

export interface Branding {
    currency: string;
}

export interface EnrollmentTotals {
    subjectsCount: number;
    totalLectureUnits: number;
    totalLabUnits: number;
    totalLectures: number;
    totalLaboratory: number;
    discountAmount: number;
    modularCount: number;
    totalModularFee: number;
    excludedSubjectsCount: number;
    miscellaneous: number;
    totalAdditionalFees: number;
    overallTotal: number;
    balance: number;
}

export function generateId(): string {
    return Math.random().toString(36).substring(2, 9);
}

export function getMinutesFromTime(timeStr: string): number {
    if (!timeStr) return 0;

    if (timeStr.includes("T")) {
        const date = new Date(timeStr);
        if (!isNaN(date.getTime())) {
            return date.getHours() * 60 + date.getMinutes();
        }
    }

    const [h, m] = timeStr.split(":").map(Number);
    if (isNaN(h) || isNaN(m)) return 0;
    return h * 60 + m;
}

export const START_HOUR = 7;
export const END_HOUR = 21;
export const TOTAL_MINUTES = (END_HOUR - START_HOUR) * 60;
export const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
export const SCHEDULE_COLORS = [
    "bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300",
    "bg-green-100 border-green-300 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300",
    "bg-purple-100 border-purple-300 text-purple-800 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-300",
    "bg-orange-100 border-orange-300 text-orange-800 dark:bg-orange-900/30 dark:border-orange-700 dark:text-orange-300",
    "bg-pink-100 border-pink-300 text-pink-800 dark:bg-pink-900/30 dark:border-pink-700 dark:text-pink-300",
    "bg-teal-100 border-teal-300 text-teal-800 dark:bg-teal-900/30 dark:border-teal-700 dark:text-teal-300",
    "bg-indigo-100 border-indigo-300 text-indigo-800 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300",
    "bg-red-100 border-red-300 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300",
];
