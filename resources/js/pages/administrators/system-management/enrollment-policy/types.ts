import type { SystemManagementPageProps } from "../types";

export type JsonObject = Record<string, unknown>;
export type Option = { value: string; label: string };

export type VisibleWhen = {
    field: string;
    equals?: unknown;
    in?: unknown[];
};

export type OperatorField = {
    key: string;
    label: string;
    control: string;
    required?: boolean;
    options?: Option[];
    option_source?: string;
    description?: string;
    placeholder?: string;
    example?: string | number | boolean | null;
    prefix?: string;
    suffix?: string;
    min?: number;
    max?: number;
    step?: number;
    recommended?: unknown;
    visible_when?: VisibleWhen;
};

export type OperatorSchema = {
    description?: string;
    what_it_does?: string;
    impact?: string;
    example?: string;
    docs_anchor?: string;
    fields?: OperatorField[];
};

export type RegistryItem = {
    key: string;
    label: string;
    category?: string;
    operator_configurable: boolean;
    operator_schema?: OperatorSchema;
};

export type Rule = {
    key: string;
    handler: string;
    enabled?: boolean;
    configuration: JsonObject;
};

export type Requirement = {
    key: string;
    label: string;
    description?: string;
    required?: boolean;
    enabled?: boolean;
    enforcement_step?: string | null;
};

export type PaymentActionConfiguration = JsonObject & {
    receipt_mode?: "required" | "optional" | "none";
    record_transaction?: boolean;
    allow_no_receipt?: boolean;
};

export type Action = { key: string; handler: string; configuration: JsonObject | PaymentActionConfiguration };
export type Transition = { key: string; label: string; to: string; fallback: boolean; requires_reason?: boolean; conditions: Rule[] };

export type BillingConfiguration = JsonObject & {
    nstp_lecture_multiplier?: number;
    modular_laboratory_multiplier?: number;
    modular_fee?: number;
    miscellaneous_fee_fallback?: number;
    course_lecture_rate_per_unit?: number | null;
    course_laboratory_rate_per_unit?: number | null;
    course_miscellaneous_fee?: number | null;
    discount_scope?: "lecture_only";
    allow_overall_override?: boolean;
    receipt_mode?: "required" | "optional" | "none";
    minimum_payment?: { type: "none" | "fixed" | "percentage"; value: number };
};

export type WorkflowStep = {
    key: string;
    label: string;
    entry: boolean;
    terminal: boolean;
    status: string;
    outcome?: "completed" | "rejected" | "cancelled";
    permission?: string;
    authorized_role_ids?: string[];
    actions: Action[];
    transitions: Transition[];
};

export type NotificationSetting = {
    key: string;
    event: string;
    channel: string;
    enabled?: boolean;
};

export type Configuration = {
    schema_version?: number;
    rules?: Rule[];
    requirements?: Requirement[];
    assignment?: { strategy: string; configuration: JsonObject };
    billing?: { strategy: string; configuration: BillingConfiguration; allowed_payment_methods?: string[] };
    workflow?: { steps: WorkflowStep[] };
    notifications?: NotificationSetting[];
};

export type EffectiveConfiguration = Required<
    Pick<Configuration, "rules" | "requirements" | "assignment" | "billing" | "workflow" | "notifications">
> & {
    schema_version: number;
};

export type PolicyVersion = {
    id: number;
    version: number;
    state: "draft" | "published" | "archived";
    configuration: Configuration;
    change_notes: string | null;
};

export type Policy = {
    id: number;
    name: string;
    scope: Record<string, string>;
    scope_values: Record<string, string | number | null>;
    is_enabled: boolean;
    active_version_id: number | null;
    active_version: PolicyVersion | null;
    versions: PolicyVersion[];
};

export type RegistryManifest = {
    rules: Record<string, RegistryItem>;
    actions: Record<string, RegistryItem>;
    billing_strategies: Record<string, RegistryItem>;
    assignment_strategies: Record<string, RegistryItem>;
};

export type Rollout = {
    state: "legacy" | "ready" | "active";
    active: boolean;
    ready: boolean;
    errors: string[];
    global_policy_id: number | null;
    global_version_id: number | null;
    checksum: string | null;
    legacy_enrollments: number;
    policy_enrollments: number;
    migration_warnings: number;
};

export type PolicySource = {
    policy_id?: number;
    policy_name?: string;
    version_id: number;
    version?: number;
    scope?: Record<string, string>;
};

export type InheritanceResponse = {
    configuration: Configuration | null;
    layers: PolicySource[];
    source_map: Record<string, PolicySource>;
};

export type SimulationCheck = {
    key: string;
    handler: string;
    label?: string;
    section?: BlueprintStepId;
    passed: boolean;
    message?: string | null;
    metadata?: JsonObject;
};

export type Simulation = {
    error?: unknown;
    checksum?: string;
    source_version_ids?: number[];
    matched_policies?: PolicySource[];
    source_map?: Record<string, PolicySource>;
    eligibility?: SimulationCheck[];
    blockers?: SimulationCheck[];
    entry_step?: WorkflowStep;
    workflow_route?: { key: string; label: string; terminal: boolean }[];
    assignment?: JsonObject;
    billing?: JsonObject;
    notifications?: NotificationSetting[];
};

export type Preset = { label: string; description: string };

export type BlueprintStepId = "scope" | "eligibility" | "documents" | "assignment" | "billing" | "workflow" | "publish";

export type HelpTopic = {
    title: string;
    whatItDoes: string;
    impact?: string;
    example?: string;
    docsAnchor?: string;
};

export interface EnrollmentPolicyPageProps extends SystemManagementPageProps {
    enrollment_policies: Policy[];
    enrollment_registry: RegistryManifest;
    enrollment_rollout: Rollout;
    enrollment_presets: Record<string, Preset>;
    enrollment_operator_options: Record<string, Option[]>;
    has_global_published_policy: boolean;
    enrollment_documentation_url: string;
}
