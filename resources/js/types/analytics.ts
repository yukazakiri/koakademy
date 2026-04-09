export type AnalyticsProvider = "google" | "ackee" | "umami" | "openpanel" | "custom";

export interface AnalyticsProviderSettings {
    google_measurement_id: string;
    ackee_script_url: string;
    ackee_server_url: string;
    ackee_domain_id: string;
    umami_script_url: string;
    umami_website_id: string;
    umami_host_url: string;
    umami_domains: string;
    openpanel_script_url: string;
    openpanel_client_id: string;
    openpanel_api_url: string;
    openpanel_track_screen_views: boolean;
    openpanel_track_outgoing_links: boolean;
    openpanel_track_attributes: boolean;
    openpanel_session_replay: boolean;
}

export interface AnalyticsConfig {
    enabled: boolean;
    provider: AnalyticsProvider | null;
    script: string;
    settings: AnalyticsProviderSettings;
}
