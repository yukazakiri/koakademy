import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import type { AnalyticsProvider } from "@/types/analytics";
import { useForm } from "@inertiajs/react";
import { Activity, BarChart3, Bot, Fingerprint, Loader2, Radio, Save } from "lucide-react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface AnalyticsFormData {
    analytics_enabled: boolean;
    analytics_provider: AnalyticsProvider;
    analytics_script: string;
    analytics_settings: {
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
    };
}

export default function SystemManagementAnalyticsPage({ user, general_settings, access }: SystemManagementPageProps) {
    const analyticsForm = useForm<AnalyticsFormData>({
        analytics_enabled: general_settings?.analytics_enabled ?? false,
        analytics_provider: general_settings?.analytics_provider || "google",
        analytics_script: general_settings?.analytics_script || "",
        analytics_settings: {
            google_measurement_id: general_settings?.analytics_settings?.google_measurement_id || general_settings?.google_analytics_id || "",
            ackee_script_url: general_settings?.analytics_settings?.ackee_script_url || "",
            ackee_server_url: general_settings?.analytics_settings?.ackee_server_url || "",
            ackee_domain_id: general_settings?.analytics_settings?.ackee_domain_id || "",
            umami_script_url: general_settings?.analytics_settings?.umami_script_url || "",
            umami_website_id: general_settings?.analytics_settings?.umami_website_id || "",
            umami_host_url: general_settings?.analytics_settings?.umami_host_url || "",
            umami_domains: general_settings?.analytics_settings?.umami_domains || "",
            openpanel_script_url: general_settings?.analytics_settings?.openpanel_script_url || "https://openpanel.dev/op1.js",
            openpanel_client_id: general_settings?.analytics_settings?.openpanel_client_id || "",
            openpanel_api_url: general_settings?.analytics_settings?.openpanel_api_url || "",
            openpanel_track_screen_views: general_settings?.analytics_settings?.openpanel_track_screen_views ?? true,
            openpanel_track_outgoing_links: general_settings?.analytics_settings?.openpanel_track_outgoing_links ?? true,
            openpanel_track_attributes: general_settings?.analytics_settings?.openpanel_track_attributes ?? true,
            openpanel_session_replay: general_settings?.analytics_settings?.openpanel_session_replay ?? false,
        },
    });

    const analyticsProvider = analyticsForm.data.analytics_provider;
    const manualAnalyticsOverride = analyticsForm.data.analytics_script.trim() !== "";

    const providerDisplayName =
        analyticsProvider === "google"
            ? "Google Analytics"
            : analyticsProvider === "ackee"
              ? "Ackee"
              : analyticsProvider === "umami"
                ? "Umami"
                : analyticsProvider === "openpanel"
                  ? "OpenPanel"
                  : "Custom";

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="analytics"
            heading="Analytics & Tracking"
            description="Configure site telemetry providers, tracking snippets, and client-side analytics behavior."
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                <div className="space-y-6">
                    <div className="bg-muted/30 border-muted-foreground/10 flex flex-col justify-between gap-4 rounded-xl border p-4 sm:flex-row sm:items-center">
                        <div className="space-y-1">
                            <h2 className="text-foreground text-lg font-semibold tracking-tight">Analytics Configuration</h2>
                            <p className="text-muted-foreground text-sm">
                                Choose one provider or paste the final script snippet directly for full control.
                            </p>
                        </div>
                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form: analyticsForm,
                                    routeName: "administrators.system-management.analytics.update",
                                    successMessage: "Analytics settings updated successfully.",
                                    errorMessage: "Failed to update analytics settings.",
                                })
                            }
                            disabled={analyticsForm.processing || !analyticsForm.isDirty}
                            className="w-full shrink-0 sm:w-auto"
                        >
                            {analyticsForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save Configuration
                        </Button>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 className="h-4 w-4" />
                                Provider
                            </CardTitle>
                            <CardDescription>Enable analytics globally and choose which provider should be injected.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="space-y-2.5">
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Analytics Enabled</Label>
                                    <div className="bg-background flex min-h-11 items-center justify-between rounded-lg border px-3">
                                        <div className="space-y-0.5">
                                            <p className="text-sm font-medium">Inject tracking scripts</p>
                                            <p className="text-muted-foreground text-xs">
                                                Applies to both Inertia pages and the Filament admin panel.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={analyticsForm.data.analytics_enabled}
                                            onCheckedChange={(checked) => analyticsForm.setData("analytics_enabled", checked)}
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="analytics_provider"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Provider
                                    </Label>
                                    <Select
                                        value={analyticsForm.data.analytics_provider}
                                        onValueChange={(value) => analyticsForm.setData("analytics_provider", value as AnalyticsProvider)}
                                    >
                                        <SelectTrigger id="analytics_provider" className="bg-background">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="google">Google Analytics</SelectItem>
                                            <SelectItem value="ackee">Ackee</SelectItem>
                                            <SelectItem value="umami">Umami</SelectItem>
                                            <SelectItem value="openpanel">OpenPanel</SelectItem>
                                            <SelectItem value="custom">Custom Script</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-2.5">
                                <Label htmlFor="analytics_script" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                    Manual Script Override
                                </Label>
                                <Textarea
                                    id="analytics_script"
                                    rows={7}
                                    value={analyticsForm.data.analytics_script}
                                    onChange={(event) => analyticsForm.setData("analytics_script", event.target.value)}
                                    className="bg-background resize-y font-mono text-xs"
                                    placeholder={`<script async src="..."></script>\n<script>/* provider init */</script>`}
                                />
                                <p className="text-muted-foreground text-[11px] leading-tight">
                                    When this is filled, the app will inject this snippet exactly and ignore the generated provider configuration
                                    below.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {!manualAnalyticsOverride && analyticsProvider === "google" ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Radio className="h-4 w-4" />
                                    Google Analytics
                                </CardTitle>
                                <CardDescription>Use a GA4 measurement ID for page tracking.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="google_measurement_id"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Measurement ID
                                    </Label>
                                    <Input
                                        id="google_measurement_id"
                                        value={analyticsForm.data.analytics_settings.google_measurement_id}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                google_measurement_id: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="G-XXXXXXXXXX"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    {!manualAnalyticsOverride && analyticsProvider === "ackee" ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Fingerprint className="h-4 w-4" />
                                    Ackee
                                </CardTitle>
                                <CardDescription>Provide the tracker script, Ackee server URL, and domain ID.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="ackee_script_url"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Tracker Script URL
                                    </Label>
                                    <Input
                                        id="ackee_script_url"
                                        value={analyticsForm.data.analytics_settings.ackee_script_url}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                ackee_script_url: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="https://ackee.example.com/tracker.js"
                                    />
                                </div>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="ackee_server_url"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Ackee Server URL
                                    </Label>
                                    <Input
                                        id="ackee_server_url"
                                        value={analyticsForm.data.analytics_settings.ackee_server_url}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                ackee_server_url: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="https://ackee.example.com"
                                    />
                                </div>
                                <div className="space-y-2.5 sm:col-span-2">
                                    <Label htmlFor="ackee_domain_id" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Domain ID
                                    </Label>
                                    <Input
                                        id="ackee_domain_id"
                                        value={analyticsForm.data.analytics_settings.ackee_domain_id}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                ackee_domain_id: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="e5235bfe-046a-4899-8c08-95a99eb02b00"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    {!manualAnalyticsOverride && analyticsProvider === "umami" ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Activity className="h-4 w-4" />
                                    Umami
                                </CardTitle>
                                <CardDescription>Configure the Umami script, website ID, optional host URL, and allowed domains.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="umami_script_url"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Script URL
                                    </Label>
                                    <Input
                                        id="umami_script_url"
                                        value={analyticsForm.data.analytics_settings.umami_script_url}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                umami_script_url: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="https://cloud.umami.is/script.js"
                                    />
                                </div>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="umami_website_id"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Website ID
                                    </Label>
                                    <Input
                                        id="umami_website_id"
                                        value={analyticsForm.data.analytics_settings.umami_website_id}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                umami_website_id: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="c3f8e397-1612-4b95-8963-c20a654f02f6"
                                    />
                                </div>
                                <div className="space-y-2.5">
                                    <Label htmlFor="umami_host_url" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Host URL
                                    </Label>
                                    <Input
                                        id="umami_host_url"
                                        value={analyticsForm.data.analytics_settings.umami_host_url}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                umami_host_url: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="https://umami.example.com"
                                    />
                                </div>
                                <div className="space-y-2.5">
                                    <Label htmlFor="umami_domains" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Domains
                                    </Label>
                                    <Input
                                        id="umami_domains"
                                        value={analyticsForm.data.analytics_settings.umami_domains}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                umami_domains: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="portal.koakademy.edu,admin.koakademy.edu"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    {!manualAnalyticsOverride && analyticsProvider === "openpanel" ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Bot className="h-4 w-4" />
                                    OpenPanel
                                </CardTitle>
                                <CardDescription>
                                    Configure the client script, API endpoint, project client ID, and client-side tracking toggles.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="openpanel_script_url"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Script URL
                                        </Label>
                                        <Input
                                            id="openpanel_script_url"
                                            value={analyticsForm.data.analytics_settings.openpanel_script_url}
                                            onChange={(event) =>
                                                analyticsForm.setData("analytics_settings", {
                                                    ...analyticsForm.data.analytics_settings,
                                                    openpanel_script_url: event.target.value,
                                                })
                                            }
                                            className="bg-background"
                                            placeholder="https://openpanel.dev/op1.js"
                                        />
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="openpanel_api_url"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            API URL
                                        </Label>
                                        <Input
                                            id="openpanel_api_url"
                                            value={analyticsForm.data.analytics_settings.openpanel_api_url}
                                            onChange={(event) =>
                                                analyticsForm.setData("analytics_settings", {
                                                    ...analyticsForm.data.analytics_settings,
                                                    openpanel_api_url: event.target.value,
                                                })
                                            }
                                            className="bg-background"
                                            placeholder="https://openpanel.koakademy.edu/api"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="openpanel_client_id"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Client ID
                                    </Label>
                                    <Input
                                        id="openpanel_client_id"
                                        value={analyticsForm.data.analytics_settings.openpanel_client_id}
                                        onChange={(event) =>
                                            analyticsForm.setData("analytics_settings", {
                                                ...analyticsForm.data.analytics_settings,
                                                openpanel_client_id: event.target.value,
                                            })
                                        }
                                        className="bg-background"
                                        placeholder="e4e45149-bbde-44d7-b436-f9a0ae1042b0"
                                    />
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2.5">
                                        <div>
                                            <p className="text-sm font-medium">Track screen views</p>
                                            <p className="text-muted-foreground text-xs">Record client-side page transitions.</p>
                                        </div>
                                        <Switch
                                            checked={analyticsForm.data.analytics_settings.openpanel_track_screen_views}
                                            onCheckedChange={(checked) =>
                                                analyticsForm.setData("analytics_settings", {
                                                    ...analyticsForm.data.analytics_settings,
                                                    openpanel_track_screen_views: checked,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2.5">
                                        <div>
                                            <p className="text-sm font-medium">Track outgoing links</p>
                                            <p className="text-muted-foreground text-xs">Capture external link clicks automatically.</p>
                                        </div>
                                        <Switch
                                            checked={analyticsForm.data.analytics_settings.openpanel_track_outgoing_links}
                                            onCheckedChange={(checked) =>
                                                analyticsForm.setData("analytics_settings", {
                                                    ...analyticsForm.data.analytics_settings,
                                                    openpanel_track_outgoing_links: checked,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2.5">
                                        <div>
                                            <p className="text-sm font-medium">Track attributes</p>
                                            <p className="text-muted-foreground text-xs">Send enriched visit metadata to OpenPanel.</p>
                                        </div>
                                        <Switch
                                            checked={analyticsForm.data.analytics_settings.openpanel_track_attributes}
                                            onCheckedChange={(checked) =>
                                                analyticsForm.setData("analytics_settings", {
                                                    ...analyticsForm.data.analytics_settings,
                                                    openpanel_track_attributes: checked,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2.5">
                                        <div>
                                            <p className="text-sm font-medium">Session replay</p>
                                            <p className="text-muted-foreground text-xs">Enable client session recording if supported.</p>
                                        </div>
                                        <Switch
                                            checked={analyticsForm.data.analytics_settings.openpanel_session_replay}
                                            onCheckedChange={(checked) =>
                                                analyticsForm.setData("analytics_settings", {
                                                    ...analyticsForm.data.analytics_settings,
                                                    openpanel_session_replay: checked,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Current Status</CardTitle>
                            <CardDescription>Quick summary of the analytics configuration that will be injected.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">State</span>
                                <Badge variant={analyticsForm.data.analytics_enabled ? "default" : "outline"}>
                                    {analyticsForm.data.analytics_enabled ? "Enabled" : "Disabled"}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">Provider</span>
                                <Badge variant="secondary">{providerDisplayName}</Badge>
                            </div>
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Runtime source</p>
                                <p className="text-muted-foreground text-sm">
                                    {manualAnalyticsOverride
                                        ? "Manual script override"
                                        : analyticsProvider === "google"
                                          ? analyticsForm.data.analytics_settings.google_measurement_id || "Waiting for a Google measurement ID."
                                          : analyticsProvider === "ackee"
                                            ? analyticsForm.data.analytics_settings.ackee_server_url || "Waiting for Ackee server details."
                                            : analyticsProvider === "umami"
                                              ? analyticsForm.data.analytics_settings.umami_website_id || "Waiting for a Umami website ID."
                                              : analyticsProvider === "openpanel"
                                                ? analyticsForm.data.analytics_settings.openpanel_api_url || "Waiting for an OpenPanel API URL."
                                                : "Manual custom script mode"}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SystemManagementLayout>
    );
}
