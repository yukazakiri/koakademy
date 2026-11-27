export interface SchoolProfile {
    school_id: number | null;
    school_name: string | null;
    country_code: string | null;
    school_level: string;
    school_level_label: string;
    preset: string;
    philippine_reporting: boolean;
    is_basic_ed: boolean;
    is_tvet: boolean;
    is_higher_ed: boolean;
    is_multi_department: boolean;
    guardian_required_types: string[];
    term_label: string;
    placement_label: string;
}

export interface IdentifierPolicy {
    display_label: string;
    assignment_mode: "automatic" | "manual" | "hybrid";
    character_set: "numeric" | "alphanumeric";
    legacy_format: boolean;
}

export interface StudentConfigFieldPolicy {
    label: string;
    visible: boolean;
    required: boolean;
}

export interface StudentCustomFieldConfig {
    key: string;
    label: string;
    type: "text" | "number" | "date" | "choice";
    active: boolean;
    required: boolean;
    options: string[];
}

export interface StudentConfiguration {
    preset: string;
    enabled_types: string[];
    default_type: string;
    defaults: {
        nationality: string;
        citizenship: string;
        gender: string;
    };
    philippine_reporting: boolean;
    fields: Record<string, StudentConfigFieldPolicy>;
    custom_fields: StudentCustomFieldConfig[];
    external_identifier: {
        enabled: boolean;
        label: string;
        help_text: string;
        required: boolean;
        student_types: string[];
        format: "text" | "digits";
        min_length: number;
        max_length: number;
        use_as_student_id: boolean;
    };
    type_labels: Record<string, string>;
    year_levels: Record<string, Array<{ value: number; label: string }>>;
    document_checklist?: Record<
        string,
        Array<{ key: string; label?: string; required: boolean; enabled?: boolean }>
    >;
}

export type DocumentStatus =
    | "missing"
    | "pending_review"
    | "verified"
    | "rejected"
    | "waived"
    | "physical_on_file";

export interface DocumentHistoryEntry {
    event: string;
    status?: string;
    reason?: string | null;
    notes?: string | null;
    actor?: string | null;
    at?: string | null;
    file_name?: string | null;
    previous_file_name?: string | null;
}

export interface DocumentSlot {
    key: string;
    label: string;
    short_label: string;
    description: string;
    accepted: string;
    required: boolean;
    image_only?: boolean;
    status: DocumentStatus;
    is_compliant: boolean;
    has_file: boolean;
    file_name: string | null;
    mime_type: string | null;
    file_size: number | null;
    uploaded_at: string | null;
    uploaded_at_label: string | null;
    verified_at: string | null;
    verified_at_label: string | null;
    verifier_name: string | null;
    rejection_reason: string | null;
    waiver_reason: string | null;
    notes: string | null;
    expires_at: string | null;
    history: DocumentHistoryEntry[];
    file_revision?: string | null;
    download_url: string | null;
    preview_url: string | null;
}

export interface DynamicDocument {
    id: number;
    type: string;
    file_name: string;
    file_path: string;
    mime_type: string | null;
    file_size: number | null;
    created_at: string | null;
    created_at_label: string | null;
    url: string;
    preview_url: string;
}

export interface OfficialDocument {
    key: string;
    label: string;
    description: string;
    url: string;
    type: string;
}

export interface DocumentSummary {
    total_required?: number;
    required_total?: number;
    compliant_count: number;
    verified_count?: number;
    physical_count?: number;
    waived_count?: number;
    uploaded_count?: number;
    pending_review_count: number;
    rejected_count: number;
    missing_count: number;
    dynamic_count?: number;
    compliance_percent: number;
    is_complete: boolean;
}

export interface DocumentDossier {
    slots: DocumentSlot[];
    dynamic_documents: DynamicDocument[];
    official_documents: OfficialDocument[];
    summary: DocumentSummary;
}

export interface MatrixColumn {
    key: string;
    label: string;
    short_label: string;
    description: string;
    accepted: string;
    required: boolean;
    enabled: boolean;
    image_only: boolean;
}

export interface MatrixColumnStat {
    key: string;
    label: string;
    short_label: string;
    required: boolean;
    compliant: number;
    pending_review: number;
    rejected: number;
    missing: number;
    applicable: number;
    compliance_percent: number;
}

export interface MatrixSlotCell {
    key: string;
    label: string;
    short_label: string;
    required: boolean;
    status: DocumentStatus;
    is_compliant: boolean;
    has_file: boolean;
    file_name: string | null;
    rejection_reason: string | null;
    waiver_reason: string | null;
    file_revision?: string | null;
    preview_url: string | null;
    download_url: string | null;
}

export interface DocumentMatrixStudent {
    id: number;
    student_id: string | null;
    lrn: string | null;
    first_name: string;
    last_name: string;
    middle_name: string | null;
    full_name: string;
    email: string | null;
    student_type: string | null;
    academic_year: string;
    course_code: string | null;
    course_title: string | null;
    avatar_url: string | null;
    dynamic_count: number;
    summary: DocumentSummary;
    slots: Record<string, MatrixSlotCell>;
}

export interface TriageCounts {
    all: number;
    needs_doc_review: number;
    missing_docs: number;
    active_holds: number;
    no_portal: number;
    incomplete_profile: number;
    trashed: number;
}

export interface ActiveHoldSummary {
    id: number;
    office: string;
    office_label: string;
    severity: string;
    title: string;
    reason: string;
    blocks_enrollment: boolean;
    blocks_add_drop: boolean;
    blocks_grade_release: boolean;
    placed_by: string | null;
    placed_at: string | null;
}

export const DOCUMENT_REJECTION_PRESETS = [
    "Blurry or unreadable scan — please upload a clearer copy.",
    "Wrong document uploaded for this slot.",
    "Document is cut off or missing required pages.",
    "Missing official school seal, registrar signature, or dry stamp.",
    "Name or date on document does not match student record.",
] as const;

export function documentStatusMeta(status: DocumentStatus): {
    label: string;
    shortLabel: string;
    className: string;
} {
    switch (status) {
        case "verified":
            return {
                label: "Verified",
                shortLabel: "Verified",
                className:
                    "border-emerald-500/30 bg-emerald-500/12 text-emerald-700 dark:text-emerald-300",
            };
        case "physical_on_file":
            return {
                label: "Physical Copy",
                shortLabel: "Physical",
                className:
                    "border-sky-500/30 bg-sky-500/12 text-sky-700 dark:text-sky-300",
            };
        case "waived":
            return {
                label: "Waived (N/A)",
                shortLabel: "Waived",
                className:
                    "border-slate-500/30 bg-slate-500/12 text-slate-700 dark:text-slate-300",
            };
        case "pending_review":
            return {
                label: "Pending Review",
                shortLabel: "Review",
                className:
                    "border-amber-500/35 bg-amber-500/12 text-amber-700 dark:text-amber-300",
            };
        case "rejected":
            return {
                label: "Rejected",
                shortLabel: "Rejected",
                className:
                    "border-rose-500/35 bg-rose-500/12 text-rose-700 dark:text-rose-300",
            };
        default:
            return {
                label: "Missing",
                shortLabel: "Missing",
                className:
                    "border-border bg-muted/60 text-muted-foreground",
            };
    }
}
