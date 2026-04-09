import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { AnalyticsProvider } from "@/types/analytics";
import { useForm } from "@inertiajs/react";
import {
    Activity,
    AtSign,
    BarChart3,
    Bot,
    Fingerprint,
    Globe,
    Image as ImageIcon,
    Link2,
    Loader2,
    Radio,
    Save,
    Search,
    Share2,
    Type,
} from "lucide-react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface SeoFormData {
    site_name: string;
    site_description: string;
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
    seo_title: string;
    seo_keywords: string;
    seo_metadata: {
        robots: string;
        og_image: string;
        twitter_handle: string;
        twitter_card: string;
        canonical_url: string;
    };
}

export default function SystemManagementSeoPage({ user, general_settings, access }: SystemManagementPageProps) {
    const seoForm = useForm<SeoFormData>({
        site_name: general_settings?.site_name || "",
        site_description: general_settings?.site_description || "",
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
        seo_title: general_settings?.seo_title || "",
        seo_keywords: general_settings?.seo_keywords || "",
        seo_metadata: {
            robots: general_settings?.seo_metadata?.robots || "index, follow",
            og_image: general_settings?.seo_metadata?.og_image || "",
            twitter_handle: general_settings?.seo_metadata?.twitter_handle || "",
            twitter_card: general_settings?.seo_metadata?.twitter_card || "summary_large_image",
            canonical_url: general_settings?.seo_metadata?.canonical_url || "",
        },
    });
    const analyticsProvider = seoForm.data.analytics_provider;
    const manualAnalyticsOverride = seoForm.data.analytics_script.trim() !== "";
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
            activeSection="seo"
            heading="SEO & Metadata Console"
            description="Manage search indexing defaults, social sharing metadata, and crawlers configurations."
        >
            <div className="flex flex-col gap-6 xl:flex-row">
                {/* Left Column: Forms */}
                <div className="flex-1 space-y-6">
                    <div className="bg-muted/30 border-muted-foreground/10 flex flex-col justify-between gap-4 rounded-xl border p-4 sm:flex-row sm:items-center">
                        <div className="space-y-1">
                            <h2 className="text-foreground text-lg font-semibold tracking-tight">Global Configuration</h2>
                            <p className="text-muted-foreground text-sm">Adjust attributes used by search engines and social platforms.</p>
                        </div>
                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form: seoForm,
                                    routeName: "administrators.system-management.seo.update",
                                    successMessage: "SEO settings updated successfully.",
                                    errorMessage: "Failed to update SEO settings.",
                                })
                            }
                            disabled={seoForm.processing || !seoForm.isDirty}
                            className="w-full shrink-0 shadow-sm sm:w-auto"
                        >
                            {seoForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save Configuration
                        </Button>
                    </div>

                    <div className="grid gap-6">
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <div className="h-1 w-full bg-blue-500/80" />
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-blue-100 p-1.5 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        <Search className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">General Metadata</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">Core properties defining your site's identity.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label htmlFor="site_name" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Site Name
                                        </Label>
                                        <div className="relative">
                                            <Globe className="text-muted-foreground/70 absolute top-2.5 left-3 h-4 w-4" />
                                            <Input
                                                id="site_name"
                                                value={seoForm.data.site_name}
                                                onChange={(event) => seoForm.setData("site_name", event.target.value)}
                                                className="bg-background pl-9 focus-visible:ring-blue-500/30"
                                                placeholder="e.g. Acme University Portal"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label htmlFor="seo_title" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Default SEO Title
                                        </Label>
                                        <div className="relative">
                                            <Type className="text-muted-foreground/70 absolute top-2.5 left-3 h-4 w-4" />
                                            <Input
                                                id="seo_title"
                                                value={seoForm.data.seo_title}
                                                onChange={(event) => seoForm.setData("seo_title", event.target.value)}
                                                className="bg-background pl-9 focus-visible:ring-blue-500/30"
                                                placeholder="e.g. Student Portal | Acme University"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-2.5">
                                    <div className="flex items-center justify-between">
                                        <Label
                                            htmlFor="site_description"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Meta Description
                                        </Label>
                                        <span
                                            className={cn(
                                                "text-xs font-medium",
                                                seoForm.data.site_description.length > 155 ? "text-destructive" : "text-muted-foreground",
                                            )}
                                        >
                                            {seoForm.data.site_description.length} / 155 chars
                                        </span>
                                    </div>
                                    <Textarea
                                        id="site_description"
                                        rows={3}
                                        value={seoForm.data.site_description}
                                        onChange={(event) => seoForm.setData("site_description", event.target.value)}
                                        className="bg-background resize-none focus-visible:ring-blue-500/30"
                                        placeholder="Briefly describe the portal purpose to encourage click-through rates..."
                                    />
                                </div>

                                <Separator className="my-2" />

                                <div className="space-y-2.5">
                                    <Label htmlFor="seo_keywords" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        SEO Keywords
                                    </Label>
                                    <Textarea
                                        id="seo_keywords"
                                        rows={2}
                                        value={seoForm.data.seo_keywords}
                                        onChange={(event) => seoForm.setData("seo_keywords", event.target.value)}
                                        placeholder="education, enrollment, student portal, records"
                                        className="bg-background resize-none text-sm"
                                    />
                                    <p className="text-muted-foreground text-[11px]">
                                        Comma-separated terms. (Note: Many modern search engines ignore this meta tag)
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <div className="h-1 w-full bg-violet-500/80" />
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-violet-100 p-1.5 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                        <Share2 className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Social Graph & Sharing</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">
                                            Control how links appear on Facebook, X, LinkedIn, etc.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="og_image"
                                        className="text-muted-foreground flex justify-between text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Open Graph Image URL
                                        <span className="font-normal normal-case">1200x630px recommended</span>
                                    </Label>
                                    <div className="relative">
                                        <ImageIcon className="text-muted-foreground/70 absolute top-2.5 left-3 h-4 w-4" />
                                        <Input
                                            id="og_image"
                                            value={seoForm.data.seo_metadata.og_image}
                                            onChange={(event) =>
                                                seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, og_image: event.target.value })
                                            }
                                            className="bg-background pl-9 focus-visible:ring-violet-500/30"
                                            placeholder="https://example.com/images/og-banner.png"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="twitter_card"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            X (Twitter) Card Style
                                        </Label>
                                        <Select
                                            value={seoForm.data.seo_metadata.twitter_card}
                                            onValueChange={(value) =>
                                                seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, twitter_card: value })
                                            }
                                        >
                                            <SelectTrigger id="twitter_card" className="bg-background focus:ring-violet-500/30">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="summary">Summary (Small Image)</SelectItem>
                                                <SelectItem value="summary_large_image">Summary Large Image</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="twitter_handle"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            X (Twitter) Handle
                                        </Label>
                                        <div className="relative">
                                            <AtSign className="text-muted-foreground/70 absolute top-2.5 left-3 h-4 w-4" />
                                            <Input
                                                id="twitter_handle"
                                                value={seoForm.data.seo_metadata.twitter_handle}
                                                onChange={(event) =>
                                                    seoForm.setData("seo_metadata", {
                                                        ...seoForm.data.seo_metadata,
                                                        twitter_handle: event.target.value,
                                                    })
                                                }
                                                className="bg-background pl-9 focus-visible:ring-violet-500/30"
                                                placeholder="@universityhandle"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <div className="h-1 w-full bg-emerald-500/80" />
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-emerald-100 p-1.5 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        <BarChart3 className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Analytics & Tracking</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">
                                            Configure one analytics provider or paste the final tracking snippet directly.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Analytics Enabled
                                        </Label>
                                        <div className="bg-background flex min-h-11 items-center justify-between rounded-lg border px-3">
                                            <div className="space-y-0.5">
                                                <p className="text-sm font-medium">Inject tracking scripts</p>
                                                <p className="text-muted-foreground text-xs">Applies to Inertia pages and the Filament panel head.</p>
                                            </div>
                                            <Switch
                                                checked={seoForm.data.analytics_enabled}
                                                onCheckedChange={(checked) => seoForm.setData("analytics_enabled", checked)}
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
                                            value={seoForm.data.analytics_provider}
                                            onValueChange={(value) => seoForm.setData("analytics_provider", value as AnalyticsProvider)}
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
                                    <Label
                                        htmlFor="analytics_script"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Manual Script Override
                                    </Label>
                                    <Textarea
                                        id="analytics_script"
                                        rows={6}
                                        value={seoForm.data.analytics_script}
                                        onChange={(event) => seoForm.setData("analytics_script", event.target.value)}
                                        className="bg-background resize-y font-mono text-xs"
                                        placeholder={`<script async src="..."></script>\n<script>/* provider init */</script>`}
                                    />
                                    <p className="text-muted-foreground text-[11px] leading-tight">
                                        If this field is filled, the app will use this snippet exactly and ignore the generated provider fields below.
                                    </p>
                                </div>

                                {!manualAnalyticsOverride && analyticsProvider === "google" ? (
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="space-y-2.5">
                                            <Label
                                                htmlFor="google_measurement_id"
                                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                Measurement ID
                                            </Label>
                                            <Input
                                                id="google_measurement_id"
                                                value={seoForm.data.analytics_settings.google_measurement_id}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
                                                        google_measurement_id: event.target.value,
                                                    })
                                                }
                                                className="bg-background"
                                                placeholder="G-XXXXXXXXXX"
                                            />
                                        </div>
                                    </div>
                                ) : null}

                                {!manualAnalyticsOverride && analyticsProvider === "ackee" ? (
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="space-y-2.5">
                                            <Label
                                                htmlFor="ackee_script_url"
                                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                Tracker Script URL
                                            </Label>
                                            <Input
                                                id="ackee_script_url"
                                                value={seoForm.data.analytics_settings.ackee_script_url}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
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
                                                value={seoForm.data.analytics_settings.ackee_server_url}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
                                                        ackee_server_url: event.target.value,
                                                    })
                                                }
                                                className="bg-background"
                                                placeholder="https://ackee.example.com"
                                            />
                                        </div>
                                        <div className="space-y-2.5 sm:col-span-2">
                                            <Label
                                                htmlFor="ackee_domain_id"
                                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                Domain ID
                                            </Label>
                                            <Input
                                                id="ackee_domain_id"
                                                value={seoForm.data.analytics_settings.ackee_domain_id}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
                                                        ackee_domain_id: event.target.value,
                                                    })
                                                }
                                                className="bg-background"
                                                placeholder="e5235bfe-046a-4899-8c08-95a99eb02b00"
                                            />
                                        </div>
                                    </div>
                                ) : null}

                                {!manualAnalyticsOverride && analyticsProvider === "umami" ? (
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="space-y-2.5">
                                            <Label
                                                htmlFor="umami_script_url"
                                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                Script URL
                                            </Label>
                                            <Input
                                                id="umami_script_url"
                                                value={seoForm.data.analytics_settings.umami_script_url}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
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
                                                value={seoForm.data.analytics_settings.umami_website_id}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
                                                        umami_website_id: event.target.value,
                                                    })
                                                }
                                                className="bg-background"
                                                placeholder="8f6f9c40-..."
                                            />
                                        </div>
                                        <div className="space-y-2.5">
                                            <Label
                                                htmlFor="umami_host_url"
                                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                Host API URL
                                            </Label>
                                            <Input
                                                id="umami_host_url"
                                                value={seoForm.data.analytics_settings.umami_host_url}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
                                                        umami_host_url: event.target.value,
                                                    })
                                                }
                                                className="bg-background"
                                                placeholder="https://umami.example.com"
                                            />
                                        </div>
                                        <div className="space-y-2.5">
                                            <Label
                                                htmlFor="umami_domains"
                                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                Allowed Domains
                                            </Label>
                                            <Input
                                                id="umami_domains"
                                                value={seoForm.data.analytics_settings.umami_domains}
                                                onChange={(event) =>
                                                    seoForm.setData("analytics_settings", {
                                                        ...seoForm.data.analytics_settings,
                                                        umami_domains: event.target.value,
                                                    })
                                                }
                                                className="bg-background"
                                                placeholder="koakademy.edu,portal.koakademy.edu"
                                            />
                                        </div>
                                    </div>
                                ) : null}

                                {!manualAnalyticsOverride && analyticsProvider === "openpanel" ? (
                                    <div className="space-y-5">
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
                                                    value={seoForm.data.analytics_settings.openpanel_script_url}
                                                    onChange={(event) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
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
                                                    value={seoForm.data.analytics_settings.openpanel_api_url}
                                                    onChange={(event) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
                                                            openpanel_api_url: event.target.value,
                                                        })
                                                    }
                                                    className="bg-background"
                                                    placeholder="https://openpanel.koakademy.edu/api"
                                                />
                                            </div>
                                            <div className="space-y-2.5 sm:col-span-2">
                                                <Label
                                                    htmlFor="openpanel_client_id"
                                                    className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                >
                                                    Client ID
                                                </Label>
                                                <Input
                                                    id="openpanel_client_id"
                                                    value={seoForm.data.analytics_settings.openpanel_client_id}
                                                    onChange={(event) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
                                                            openpanel_client_id: event.target.value,
                                                        })
                                                    }
                                                    className="bg-background"
                                                    placeholder="e4e45149-bbde-44d7-b436-f9a0ae1042b0"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2">
                                                <div>
                                                    <p className="text-sm font-medium">Track screen views</p>
                                                    <p className="text-muted-foreground text-xs">Track SPA page transitions.</p>
                                                </div>
                                                <Switch
                                                    checked={seoForm.data.analytics_settings.openpanel_track_screen_views}
                                                    onCheckedChange={(checked) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
                                                            openpanel_track_screen_views: checked,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2">
                                                <div>
                                                    <p className="text-sm font-medium">Track outgoing links</p>
                                                    <p className="text-muted-foreground text-xs">Capture external link clicks.</p>
                                                </div>
                                                <Switch
                                                    checked={seoForm.data.analytics_settings.openpanel_track_outgoing_links}
                                                    onCheckedChange={(checked) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
                                                            openpanel_track_outgoing_links: checked,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2">
                                                <div>
                                                    <p className="text-sm font-medium">Track attributes</p>
                                                    <p className="text-muted-foreground text-xs">Allow attribute enrichment on events.</p>
                                                </div>
                                                <Switch
                                                    checked={seoForm.data.analytics_settings.openpanel_track_attributes}
                                                    onCheckedChange={(checked) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
                                                            openpanel_track_attributes: checked,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2">
                                                <div>
                                                    <p className="text-sm font-medium">Session replay</p>
                                                    <p className="text-muted-foreground text-xs">Enable replay capture if your server supports it.</p>
                                                </div>
                                                <Switch
                                                    checked={seoForm.data.analytics_settings.openpanel_session_replay}
                                                    onCheckedChange={(checked) =>
                                                        seoForm.setData("analytics_settings", {
                                                            ...seoForm.data.analytics_settings,
                                                            openpanel_session_replay: checked,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>

                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <div className="h-1 w-full bg-slate-500/80" />
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-slate-200 p-1.5 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Indexing & Crawling</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">
                                            Direct search engine bots on how to handle the portal.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label htmlFor="robots" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Robots Directives
                                        </Label>
                                        <Select
                                            value={seoForm.data.seo_metadata.robots}
                                            onValueChange={(value) =>
                                                seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, robots: value })
                                            }
                                        >
                                            <SelectTrigger id="robots" className="bg-background">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="index, follow">Index, Follow (Recommended)</SelectItem>
                                                <SelectItem value="index, nofollow">Index, No Follow</SelectItem>
                                                <SelectItem value="noindex, follow">No Index, Follow</SelectItem>
                                                <SelectItem value="noindex, nofollow">No Index, No Follow</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground mt-1 text-[11px] leading-tight">
                                            Instructs bots on whether to add pages to their index and follow links.
                                        </p>
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="canonical_url"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Canonical Override URL
                                        </Label>
                                        <div className="relative">
                                            <Link2 className="text-muted-foreground/70 absolute top-2.5 left-3 h-4 w-4" />
                                            <Input
                                                id="canonical_url"
                                                value={seoForm.data.seo_metadata.canonical_url}
                                                onChange={(event) =>
                                                    seoForm.setData("seo_metadata", {
                                                        ...seoForm.data.seo_metadata,
                                                        canonical_url: event.target.value,
                                                    })
                                                }
                                                className="bg-background pl-9"
                                                placeholder="https://example.com/official-page"
                                            />
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-[11px] leading-tight">
                                            Define the "master" version of the domain to prevent duplicate content issues.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Right Column: Previews */}
                <div className="w-full shrink-0 space-y-6 xl:w-[380px] 2xl:w-[420px]">
                    <div className="top-[104px] space-y-6 xl:sticky">
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/30 border-b px-4 pb-3">
                                <CardTitle className="flex items-center justify-between text-sm font-semibold">
                                    <div className="flex items-center gap-2">
                                        <Search className="text-muted-foreground h-4 w-4" />
                                        Search Preview
                                    </div>
                                    <Badge variant="outline" className="px-1.5 font-mono text-[10px] font-normal uppercase">
                                        {seoForm.data.seo_metadata.robots}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="bg-background p-4">
                                <div className="space-y-1.5 md:space-y-1">
                                    <div className="flex items-center gap-2.5">
                                        <div className="bg-muted flex h-7 w-7 items-center justify-center rounded-full border shadow-sm">
                                            <Globe className="text-muted-foreground h-3.5 w-3.5" />
                                        </div>
                                        <div className="flex flex-col leading-tight">
                                            <span className="text-foreground text-sm font-medium">{seoForm.data.site_name || "Your Site Name"}</span>
                                            <span className="text-muted-foreground text-xs">
                                                https://example.com
                                                {seoForm.data.seo_metadata.canonical_url
                                                    ? ` › ${seoForm.data.seo_metadata.canonical_url.split("/")[seoForm.data.seo_metadata.canonical_url.split("/").length - 1]}`
                                                    : ""}
                                            </span>
                                        </div>
                                    </div>
                                    <h3 className="mt-2 cursor-pointer truncate text-lg leading-tight font-normal text-[#1a0dab] hover:underline lg:text-xl dark:text-[#8ab4f8]">
                                        {seoForm.data.seo_title || seoForm.data.site_name || "Page Title"}
                                    </h3>
                                    <p className="line-clamp-2 text-sm leading-snug text-[#4d5156] dark:text-[#bdc1c6]">
                                        {seoForm.data.site_description ||
                                            "Add a meta description to improve your search snippet and encourage relevant clicks to your portal."}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/30 border-b px-4 pb-3">
                                <CardTitle className="flex items-center justify-between text-sm font-semibold">
                                    <div className="flex items-center gap-2">
                                        <Share2 className="text-muted-foreground h-4 w-4" />
                                        Social Share Preview
                                    </div>
                                    <Badge variant="outline" className="px-1.5 font-mono text-[10px] font-normal uppercase">
                                        {seoForm.data.seo_metadata.twitter_card === "summary_large_image" ? "Large Card" : "Summary"}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div
                                    className={cn(
                                        "bg-muted/20 flex flex-col items-center justify-center overflow-hidden border-b",
                                        seoForm.data.seo_metadata.twitter_card === "summary_large_image" ? "aspect-[1.91/1]" : "aspect-video",
                                    )}
                                >
                                    {seoForm.data.seo_metadata.og_image ? (
                                        <img
                                            src={seoForm.data.seo_metadata.og_image}
                                            alt="Preview"
                                            className="h-full w-full object-cover"
                                            onError={(e) => {
                                                e.currentTarget.style.display = "none";
                                                const sibling = e.currentTarget.nextElementSibling;
                                                if (sibling) sibling.classList.remove("hidden");
                                            }}
                                        />
                                    ) : null}
                                    <div
                                        className={cn(
                                            "text-muted-foreground flex flex-col items-center gap-2",
                                            seoForm.data.seo_metadata.og_image ? "hidden" : "",
                                        )}
                                    >
                                        <ImageIcon className="h-8 w-8 shrink-0 opacity-40" />
                                        <span className="text-xs font-medium">No Social Image</span>
                                    </div>
                                </div>
                                <div className="bg-muted/5 space-y-1 p-4">
                                    <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">example.com</p>
                                    <p className="text-foreground truncate text-base leading-tight font-semibold">
                                        {seoForm.data.seo_title || seoForm.data.site_name || "Page Title"}
                                    </p>
                                    <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed">
                                        {seoForm.data.site_description || "Add a meta description to improve your snippet."}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/30 border-b px-4 pb-3">
                                <CardTitle className="flex items-center justify-between text-sm font-semibold">
                                    <div className="flex items-center gap-2">
                                        <Activity className="text-muted-foreground h-4 w-4" />
                                        Analytics Runtime
                                    </div>
                                    <Badge
                                        variant={seoForm.data.analytics_enabled ? "default" : "outline"}
                                        className="px-1.5 font-mono text-[10px] font-normal uppercase"
                                    >
                                        {seoForm.data.analytics_enabled ? "Enabled" : "Disabled"}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 p-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="bg-muted/20 rounded-lg border p-3">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Radio className="text-muted-foreground h-3.5 w-3.5" />
                                            <p className="text-xs font-semibold tracking-wider uppercase">Provider</p>
                                        </div>
                                        <p className="text-sm font-medium">{providerDisplayName}</p>
                                    </div>
                                    <div className="bg-muted/20 rounded-lg border p-3">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Fingerprint className="text-muted-foreground h-3.5 w-3.5" />
                                            <p className="text-xs font-semibold tracking-wider uppercase">Source</p>
                                        </div>
                                        <p className="text-sm font-medium">
                                            {manualAnalyticsOverride ? "Manual script override" : "Generated snippet"}
                                        </p>
                                    </div>
                                </div>

                                <div className="bg-muted/10 rounded-lg border p-3">
                                    <p className="text-xs font-semibold tracking-wider uppercase">Resolved target</p>
                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                        {manualAnalyticsOverride
                                            ? "A custom script snippet will be injected exactly as entered."
                                            : analyticsProvider === "google"
                                              ? seoForm.data.analytics_settings.google_measurement_id || "Waiting for a Google measurement ID."
                                              : analyticsProvider === "ackee"
                                                ? seoForm.data.analytics_settings.ackee_server_url || "Waiting for Ackee server details."
                                                : analyticsProvider === "umami"
                                                  ? seoForm.data.analytics_settings.umami_website_id || "Waiting for a Umami website ID."
                                                  : analyticsProvider === "openpanel"
                                                    ? seoForm.data.analytics_settings.openpanel_api_url || "Waiting for an OpenPanel API URL."
                                                    : "Custom mode requires a script snippet."}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </SystemManagementLayout>
    );
}
