export type AnalyticsFieldType = "text" | "url" | "textarea" | "toggle" | "select";

export interface AnalyticsFieldOption {
    value: string;
    label: string;
}

export interface AnalyticsFieldDefinition {
    key: string;
    label: string;
    type: AnalyticsFieldType;
    placeholder: string;
    help: string | null;
    options: AnalyticsFieldOption[];
    default: string | boolean | null;
}

export interface AnalyticsProviderDefinition {
    key: string;
    label: string;
    description: string;
    docs_url: string;
    category: "self_hosted" | "cloud";
    self_hosted: boolean;
    consent_note: string | null;
    fields: AnalyticsFieldDefinition[];
}

export type AnalyticsSettingValue = string | boolean;

/** One saved provider instance. */
export interface AnalyticsProviderInstance {
    id: number;
    provider: string;
    label: string;
    enabled: boolean;
    settings: Record<string, AnalyticsSettingValue>;
    script: string;
    position: number;
}

/** A provider resolved to a snippet the browser should inject. */
export interface AnalyticsActiveProvider {
    key: string;
    label: string;
    snippet: string;
    session: boolean;
}

export interface AnalyticsConfig {
    enabled: boolean;
    has_providers: boolean;
    providers: AnalyticsActiveProvider[];
}
