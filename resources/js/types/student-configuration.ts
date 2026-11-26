export type StudentCategory = "college" | "shs" | "tesda" | "dhrt";

export type DocumentSlotKey = "birth_certificate" | "form_138" | "good_moral_cert" | "picture_1x1";

export interface StudentFieldPolicy {
    label: string;
    visible: boolean;
    required: boolean;
}

export interface DocumentSlotPolicy {
    label: string;
    description: string;
    visible: boolean;
    required: boolean;
}

export interface CustomStudentField {
    key: string;
    label: string;
    type: "text" | "number" | "date" | "choice";
    active: boolean;
    required: boolean;
    options: string[];
}

export interface ExternalIdentifierConfiguration {
    enabled: boolean;
    label: string;
    help_text: string | null;
    placeholder?: string | null;
    required: boolean;
    student_types: StudentCategory[];
    format: "text" | "digits" | "alphanumeric";
    min_length: number;
    max_length: number;
    use_as_student_id: boolean;
    allow_for_signup?: boolean;
}

export interface ExternalIdentifierPreset {
    name: string;
    region: string;
    description: string;
    config: ExternalIdentifierConfiguration;
}

export interface SignupPolicyConfiguration {
    allow_student_self_signup: boolean;
    allow_faculty_self_signup: boolean;
    allow_identifier_recovery: boolean;
    require_faculty_id: boolean;
    student_identifier_placeholder: string | null;
    student_identifier_help_text: string | null;
    faculty_identifier_placeholder: string | null;
    faculty_identifier_help_text: string | null;
}

export interface SignupPresentationConfig {
    student_id_label: string;
    faculty_id_label: string;
    external_identifier_enabled: boolean;
    external_identifier_label: string;
    external_identifier_help_text: string | null;
    external_identifier_only: boolean;
    universal_identifier_label: string;
    student_identifier_placeholder: string;
    student_identifier_help_text: string;
    faculty_identifier_placeholder: string;
    faculty_identifier_help_text: string;
    enabled_track_labels: string[];
    allow_student_self_signup: boolean;
    allow_faculty_self_signup: boolean;
    allow_identifier_recovery: boolean;
    require_faculty_id: boolean;
}

export interface StudentConfiguration {
    preset: "neutral" | "legacy_ph" | "custom";
    enabled_types: StudentCategory[];
    default_type: StudentCategory;
    defaults: { nationality: string | null; citizenship: string | null; gender: string | null };
    philippine_reporting: boolean;
    fields: Record<string, StudentFieldPolicy>;
    documents?: Record<DocumentSlotKey, DocumentSlotPolicy>;
    custom_fields: CustomStudentField[];
    type_labels: Record<StudentCategory, string>;
    year_levels: Record<StudentCategory, Array<{ value: number; label: string }>>;
    external_identifier: ExternalIdentifierConfiguration;
    signup?: SignupPolicyConfiguration;
}

export interface StudentIdentifierPolicy {
    display_label: string;
    assignment_mode: "automatic" | "manual" | "hybrid";
    character_set: "numeric" | "alphanumeric";
    legacy_format: boolean;
}
