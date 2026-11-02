export type StudentCategory = "college" | "shs" | "tesda" | "dhrt";

export interface StudentFieldPolicy {
    label: string;
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

export interface StudentConfiguration {
    preset: "neutral" | "legacy_ph" | "custom";
    enabled_types: StudentCategory[];
    default_type: StudentCategory;
    defaults: { nationality: string | null; citizenship: string | null; gender: string | null };
    philippine_reporting: boolean;
    fields: Record<string, StudentFieldPolicy>;
    custom_fields: CustomStudentField[];
    type_labels: Record<StudentCategory, string>;
    year_levels: Record<StudentCategory, Array<{ value: number; label: string }>>;
    external_identifier: {
        enabled: boolean;
        label: string;
        help_text: string | null;
        required: boolean;
        student_types: StudentCategory[];
        format: "text" | "digits";
        min_length: number;
        max_length: number;
        use_as_student_id: boolean;
    };
}

export interface StudentIdentifierPolicy {
    display_label: string;
    assignment_mode: "automatic" | "manual" | "hybrid";
    character_set: "numeric" | "alphanumeric";
    legacy_format: boolean;
}
