import type { AnalyticsConfig, AnalyticsProviderSettings } from "@/types/analytics";
import type { User } from "@/types/user";
import type { SystemManagementSectionKey } from "./layout";

export type SystemManagementSectionKey =
    | "school"
    | "pipeline"
    | "seo"
    | "analytics"
    | "brand"
    | "socialite"
    | "mail"
    | "api"
    | "notifications"
    | "grading"
    | "pulse";

export interface GradingConfigPayload {
    scale: "point" | "percent" | "auto";
    point_passing_grade: number;
    percent_passing_grade: number;
    point_decimal_places: number;
    percent_decimal_places: number;
    include_failed_in_gwa: boolean;
    excluded_keywords: string[];
    excluded_subject_ids: number[];
}

export interface CourseSubjectSummary {
    id: number;
    code: string;
    title: string;
    units: number;
    year_level: number;
    semester: number;
}

export interface CourseWithSubjects {
    id: number;
    code: string;
    title: string;
    subjects: CourseSubjectSummary[];
}

export interface School {
    id: number;
    name: string;
    code: string;
    description?: string;
    dean_name?: string;
    dean_email?: string;
    location?: string;
    phone?: string;
    email?: string;
    is_active: boolean;
}

export interface SeoMetadata {
    robots?: string;
    og_image?: string;
    twitter_handle?: string;
    twitter_card?: string;
    canonical_url?: string;
}

export interface GeneralSettings {
    site_name: string;
    site_description: string | null;
    google_analytics_id?: string | null;
    analytics_enabled: boolean;
    analytics_provider: AnalyticsConfig["provider"];
    analytics_script: string | null;
    analytics_settings: AnalyticsProviderSettings | null;
    seo_title: string | null;
    seo_keywords: string | null;
    seo_metadata: SeoMetadata | null;
    theme_color: string | null;
    currency: string | null;
    support_email: string | null;
    support_phone: string | null;
    social_network: Record<string, string | null> | null;
    email_from_address: string | null;
    email_from_name: string | null;
    school_portal_url: string | null;
    school_portal_enabled: boolean;
    online_enrollment_enabled: boolean;
    school_portal_maintenance: boolean;
    school_portal_title: string | null;
    school_portal_description: string | null;
    school_year_string?: string | null;
    semester_name?: string | null;
}

export interface SocialiteConfig {
    facebook_client_id?: string;
    facebook_client_secret?: string;
    google_client_id?: string;
    google_client_secret?: string;
    twitter_client_id?: string;
    twitter_client_secret?: string;
    github_client_id?: string;
    github_client_secret?: string;
    linkedin_client_id?: string;
    linkedin_client_secret?: string;
}

export interface MailConfig {
    driver: string;
    host: string;
    port: number;
    username: string;
    password: string;
    encryption: string;
}

export type EnrollmentPipelineActionType = "standard" | "department_verification" | "cashier_verification";

export interface EnrollmentPipelineStep {
    key: string;
    status: string;
    label: string;
    color: string;
    allowed_roles: string[];
    action_type: EnrollmentPipelineActionType;
}

export interface EnrollmentPipelineSettings {
    submitted_label: string;
    entry_step_key?: string;
    completion_step_key?: string;
    steps: EnrollmentPipelineStep[];
}

export type EnrollmentStatMetric = "total_records" | "active_records" | "trashed_records" | "status_count" | "paid_count";

export interface EnrollmentStatsCard {
    key: string;
    label: string;
    metric: EnrollmentStatMetric;
    statuses: string[];
    color: string;
}

export interface EnrollmentStatsSettings {
    cards: EnrollmentStatsCard[];
}

export interface PulseData {
    servers: {
        servers: Record<
            string,
            {
                name: string;
                cpu_current: number;
                memory_current: number;
                memory_total: number;
                storage: Array<{ directory: string; total: number; used: number }>;
                updated_at: string;
            }
        >;
    };
    usage: {
        userRequestCounts: Array<{
            key: string;
            user: { name: string; email: string; avatar?: string };
            count: number;
        }>;
    };
    slow_requests: {
        slowRequests: Array<{
            uri: string;
            method: string;
            action: string;
            count: string;
            slowest: string;
            threshold: number;
        }>;
    };
    queues: {
        queues: Array<{
            queue: string;
            size: number;
            failed: number;
        }>;
    };
    cache: {
        allCacheInteractions: {
            hits: string;
            misses: string;
        };
    };
    slow_queries: {
        slowQueries: Array<{
            sql: string;
            count: number;
            slowest: number;
            threshold: number;
        }>;
    };
    exceptions: {
        exceptions: Array<{
            class: string;
            message: string;
            count: number;
            latest: string;
        }>;
    };
    slow_jobs: {
        slowJobs: Array<{
            job: string;
            count: number;
            slowest: number;
            threshold: number;
        }>;
    };
}

export interface BrandingSettings {
    app_name: string | null;
    app_short_name: string | null;
    organization_name: string | null;
    organization_short_name: string | null;
    organization_address: string | null;
    support_email: string | null;
    support_phone: string | null;
    tagline: string | null;
    copyright_text: string | null;
    theme_color: string | null;
    currency: string | null;
    auth_layout: "card" | "split" | "minimal" | null;
    logo: string | null;
    favicon: string | null;
}

export interface PusherConfig {
    app_id: string;
    key: string;
    secret: string;
    cluster: string;
}

export interface SmsConfig {
    provider: string;
    api_key: string;
    sender_id: string;
}

export interface NotificationChannelConfig {
    enabled_channels: string[];
    pusher: PusherConfig;
    sms: SmsConfig;
}

export type ThirdPartyServicesConfig = Record<string, Record<string, string | null>>;

export interface ApiManagementConfig {
    public_api_enabled: boolean;
    public_settings_enabled: boolean;
    public_settings_fields: string[];
}

export interface PublicApiFieldDefinition {
    label: string;
    description: string;
    input: string;
    editable: boolean;
}

export interface SocialNetworkSettings {
    facebook?: string | null;
    instagram?: string | null;
    twitter?: string | null;
    linkedin?: string | null;
    youtube?: string | null;
    tiktok?: string | null;
    [key: string]: string | null | undefined;
}

export interface SystemManagementSectionAccess {
    can_view: boolean;
    can_update: boolean;
    view_permission: string;
    update_permission: string | null;
}

export interface SystemManagementAccess {
    active_section: SystemManagementSectionKey;
    sections: Record<SystemManagementSectionKey, SystemManagementSectionAccess>;
}

export interface SystemManagementPageProps {
    user: User;
    general_settings: GeneralSettings;
    active_school: School | null;
    schools: School[];
    sanity_config: SanityConfig;
    socialite_config: SocialiteConfig;
    mail_config: MailConfig;
    analytics: AnalyticsConfig;
    branding: BrandingSettings;
    enrollment_pipeline: EnrollmentPipelineSettings;
    enrollment_stats: EnrollmentStatsSettings;
    api_management: ApiManagementConfig;
    grading_config: GradingConfigPayload;
    courses_with_subjects: CourseWithSubjects[];
    public_api_url: string;
    public_api_fields: Record<string, PublicApiFieldDefinition>;
    available_roles: string[];
    notification_channels: NotificationChannelConfig;
    third_party_services: ThirdPartyServicesConfig;
    access: SystemManagementAccess;
    system_semester?: number | null;
    system_school_year_start?: number | null;
    system_school_year_end?: number | null;
    system_school_starting_date?: string | null;
    system_school_ending_date?: string | null;
    available_semesters?: Record<number, string>;
    available_school_years?: Record<number, string>;
    [key: string]: unknown;
}
