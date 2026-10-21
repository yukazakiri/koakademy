export interface FeaturePolicy {
    enabled: boolean;
    mode: "legacy" | "enforced";
    release_audience: "stable" | "beta" | "selected" | null;
    strategy: "defaults" | "targeted" | "percentage";
    percentage: number;
    allow_opt_in: boolean;
    activate_at: string | null;
    retire_at: string | null;
    revision: number;
}

export interface FeatureImpact {
    eligible: number | null;
    sampled: number;
    gaining: number;
    losing: number;
    unchanged: number;
    note: string | null;
    mode: string | null;
    revision: number;
    evaluated_at: string;
    policy: FeaturePolicy;
    scope_note: string;
    public_current?: boolean;
    public_proposed?: boolean;
}

export interface ManagedFeature {
    key: string;
    name: string;
    summary: string | null;
    audience: string;
    category: string;
    class: string;
    catalog: { roles: string[]; default_enabled: boolean; tier: "built_in" | "beta" | "restricted"; opt_in: boolean; public: boolean };
    policy: FeaturePolicy;
    assignment_count: number;
    release_status: "legacy" | "disabled" | "scheduled" | "retired" | "released" | "uninitialized";
}

export interface FeatureUser {
    id: number;
    name: string;
    email: string;
    role?: string;
    school_id: number | null;
}

export interface BetaGroup {
    id: number;
    name: string;
    description: string | null;
    school_id: number | null;
    members: FeatureUser[];
}

export interface Assignment {
    id: number;
    subject_type: string;
    subject_key: string;
    label: string;
    effect: "allow" | "deny";
    source: string;
}

export interface AuditEntry {
    id: number;
    action: string;
    actor: string | null;
    feature_key: string | null;
    created_at: string;
}

// Domain-bound routes may be generated outside the runtime host/port.
// Feature endpoints live in this portal: keep the typed path and query.
export function portalFeatureUrl(url: string): string {
    const parsed = new URL(url, "http://localhost");
    return parsed.pathname + parsed.search;
}
