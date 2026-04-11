import type { User } from "@/types/user";

export interface Branding {
    organizationName: string;
    currency: string;
}

export interface StudentOptions {
    school_years: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    classifications: string[];
    credited_subjects: { value: number; label: string }[];
    school_names: string[];
    id_changes: { value: number; label: string }[];
    enrollment_ids: { value: number; label: string }[];
    previous_period: { academic_year: string; semester: number };
    courses: { value: number; label: string }[];
}

export interface EnrolledClassSchedule {
    day: string;
    start_time: string;
    end_time: string;
    room: string;
}

export interface EnrolledClass {
    class_id: number;
    subject_code: string;
    subject_title: string;
    units: number;
    section: string;
    faculty: string;
    color: string;
    schedules: EnrolledClassSchedule[];
}

export interface ChecklistHistoryRecord {
    id: number;
    enrollment_id?: number | null;
    grade: number | string | null;
    remarks: string | null;
    classification: string | null;
    school_name: string | null;
    external_subject_code: string | null;
    external_subject_title: string | null;
    external_subject_units: number | string | null;
    school_year: string | null;
    semester: number | string | null;
    academic_year: number | string | null;
    credited_subject_id?: number | null;
}

export interface ChecklistSubject {
    id: number;
    routeSubjectId?: number;
    enrollment_id?: number | null;
    code: string;
    title: string;
    units: number;
    classification: string | null;
    status: string;
    grade: number | string | null;
    remarks: string | null;
    history: ChecklistHistoryRecord[];
    isStandaloneNonCredited?: boolean;
}

export interface ChecklistSemesterGroup {
    semester: number;
    subjects: ChecklistSubject[];
}

export interface ChecklistYearGroup {
    year: number;
    semesters: ChecklistSemesterGroup[];
}

export type StudentDetail = {
    id: number;
    student_id: number | string | null;
    name: string;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    gender: string | null;
    birth_date: string | null;
    type: string | null;
    status: string | null;
    academic_year: string;
    course: { id?: number; code: string | null; title: string | null };
    created_at: string | null;
    updated_at: string | null;
    contacts: any;
    parents: any;
    education: any;
    personal_info: any;
    documents: any;
    signature_url: string | null;
    current_clearance: any;
    previous_clearance_validation: any;
    clearance_history: any[];
    tuition: any;
    current_school_year: string;
    current_semester: number;
    current_enrolled_classes: EnrolledClass[];
    checklist: ChecklistYearGroup[];
    non_credited_subjects: Array<{
        id: number;
        grade: number | null;
        remarks: string | null;
        school_name: string | null;
        external_subject_code: string | null;
        external_subject_title: string | null;
        external_subject_units: number | null;
        academic_year: number | null;
        school_year: string | null;
        semester: number | null;
        linked_subject: {
            id: number;
            code: string;
            title: string;
        } | null;
    }>;
    filament: {
        view_url: string;
        edit_url: string;
    };
};

export type PrintOption = "subjects" | "schedule" | "both" | "checklist_completed" | "checklist_full" | "transcript";

export interface SubjectEnrollmentFormData {
    enrollment_record_id: number | null;
    is_new_record: boolean;
    grade: string;
    remarks: string;
    classification: string;
    school_name: string;
    external_subject_code: string;
    external_subject_title: string;
    external_subject_units: string;
    credited_subject_id: string;
    academic_year: string;
    school_year: string;
    semester: string;
}

export interface StudentShowProps {
    user: User;
    student: StudentDetail;
    options: StudentOptions;
}
