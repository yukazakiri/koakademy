import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import {
    Calendar,
    CalendarClock,
    Check,
    Cloud,
    Copy,
    GraduationCap,
    Info,
    Loader2,
    Lock,
    Mail,
    Radio,
    Save,
    Share2,
    Video,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { ApplicationIntegrationsConfig, SystemManagementPageProps } from "./types";

export default function SystemManagementIntegrationsPage({
    user,
    application_integrations,
    access,
}: SystemManagementPageProps) {
    const integrationsForm = useForm<ApplicationIntegrationsConfig>({
        google_calendar: {
            enabled: Boolean(application_integrations?.google_calendar?.enabled ?? true),
            client_id: application_integrations?.google_calendar?.client_id || "",
            client_secret: application_integrations?.google_calendar?.client_secret || "",
            redirect_uri: application_integrations?.google_calendar?.redirect_uri || "",
            auto_sync: Boolean(application_integrations?.google_calendar?.auto_sync ?? true),
        },
        calendar_feed: {
            enabled: Boolean(application_integrations?.calendar_feed?.enabled ?? true),
            students_enabled: Boolean(application_integrations?.calendar_feed?.students_enabled ?? true),
            faculty_enabled: Boolean(application_integrations?.calendar_feed?.faculty_enabled ?? true),
            title_prefix: application_integrations?.calendar_feed?.title_prefix || "KoAkademy",
            rrule_weeks: application_integrations?.calendar_feed?.rrule_weeks || 18,
        },
        microsoft: {
            enabled: Boolean(application_integrations?.microsoft?.enabled ?? false),
            client_id: application_integrations?.microsoft?.client_id || "",
            client_secret: application_integrations?.microsoft?.client_secret || "",
            tenant_id: application_integrations?.microsoft?.tenant_id || "common",
            redirect_uri: application_integrations?.microsoft?.redirect_uri || "",
        },
        cloud_storage: {
            google_drive_enabled: Boolean(application_integrations?.cloud_storage?.google_drive_enabled ?? false),
            google_drive_api_key: application_integrations?.cloud_storage?.google_drive_api_key || "",
            google_drive_client_id: application_integrations?.cloud_storage?.google_drive_client_id || "",
            onedrive_enabled: Boolean(application_integrations?.cloud_storage?.onedrive_enabled ?? false),
            onedrive_client_id: application_integrations?.cloud_storage?.onedrive_client_id || "",
        },
        conferencing_and_lms: {
            zoom_enabled: Boolean(application_integrations?.conferencing_and_lms?.zoom_enabled ?? false),
            zoom_account_id: application_integrations?.conferencing_and_lms?.zoom_account_id || "",
            zoom_client_id: application_integrations?.conferencing_and_lms?.zoom_client_id || "",
            zoom_client_secret: application_integrations?.conferencing_and_lms?.zoom_client_secret || "",
            canvas_enabled: Boolean(application_integrations?.conferencing_and_lms?.canvas_enabled ?? false),
            canvas_base_url: application_integrations?.conferencing_and_lms?.canvas_base_url || "",
            canvas_client_id: application_integrations?.conferencing_and_lms?.canvas_client_id || "",
        },
    });

    const [copiedKey, setCopiedKey] = useState<string | null>(null);
    const callbackBase = typeof window === "undefined" ? "" : window.location.origin;

    const copyUrl = (key: string, url: string) => {
        navigator.clipboard.writeText(url);
        setCopiedKey(key);
        toast.success("Callback URL copied.");
        setTimeout(() => setCopiedKey(null), 2000);
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="integrations"
            heading="Connected Services & Application Integrations"
            description="Configure third-party services, academic calendar sync, and cloud ecosystems for the whole institution."
        >
            <div className="space-y-6">
                {/* Save Bar Card */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-border/60 bg-card/65 p-4 shadow-xs backdrop-blur-xs">
                    <div>
                        <p className="text-sm font-semibold text-foreground">Global Integration Settings</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Settings apply across student portals, faculty workspaces, and administrative tools. Credentials are encrypted in application storage.
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            submitSystemForm({
                                form: integrationsForm,
                                routeName: "administrators.system-management.integrations.update",
                                successMessage: "Application integrations updated successfully.",
                                errorMessage: "Failed to update integration settings.",
                            })
                        }
                        disabled={integrationsForm.processing || !integrationsForm.isDirty}
                        className="h-9 gap-1.5 shrink-0 self-start sm:self-center"
                    >
                        {integrationsForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        <span>Save Integration Changes</span>
                    </Button>
                </div>

                {/* Main Integrations Accordion */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="border-b border-border/40 pb-4">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-base font-semibold">Configurable Services & Ecosystems</CardTitle>
                            <Badge variant="outline" className="text-xs font-normal border-border/60">
                                Runtime Configured
                            </Badge>
                        </div>
                        <CardDescription className="text-xs">
                            Enable services application-wide and manage OAuth credentials, calendar publication rules, and file storage pickers.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="pt-4">
                        <Accordion type="multiple" defaultValue={["google_calendar", "calendar_feed"]} className="space-y-3">
                            {/* 1. Google Workspace & Calendar */}
                            <AccordionItem
                                value="google_calendar"
                                className="rounded-xl border border-border/60 bg-background/50 px-4 transition-colors"
                            >
                                <AccordionTrigger className="py-3.5 hover:no-underline">
                                    <div className="flex min-w-0 items-center gap-3.5 text-left w-full pr-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-400">
                                            <Calendar className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-sm text-foreground">Google Workspace & Calendar</span>
                                                <Badge
                                                    variant={integrationsForm.data.google_calendar.enabled ? "default" : "secondary"}
                                                    className="text-[10px] px-1.5 py-0"
                                                >
                                                    {integrationsForm.data.google_calendar.enabled ? "Active" : "Disabled"}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                                                Two-way calendar event sync and institutional Google OAuth integration
                                            </p>
                                        </div>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pt-2 pb-5 border-t border-border/40 mt-1 space-y-4">
                                    <div className="flex items-center justify-between p-3 rounded-lg bg-muted/40 border border-border/40">
                                        <div className="space-y-0.5">
                                            <Label className="text-sm font-medium">Enable Google Calendar for Whole Application</Label>
                                            <p className="text-xs text-muted-foreground">
                                                When disabled, users cannot connect or sync Google Calendars on their profile.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={integrationsForm.data.google_calendar.enabled}
                                            onCheckedChange={(checked) =>
                                                integrationsForm.setData("google_calendar", {
                                                    ...integrationsForm.data.google_calendar,
                                                    enabled: checked,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Google Client ID</Label>
                                            <Input
                                                placeholder="e.g. 123456789-abc.apps.googleusercontent.com"
                                                value={integrationsForm.data.google_calendar.client_id || ""}
                                                onChange={(e) =>
                                                    integrationsForm.setData("google_calendar", {
                                                        ...integrationsForm.data.google_calendar,
                                                        client_id: e.target.value,
                                                    })
                                                }
                                                className="text-xs font-mono"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Google Client Secret</Label>
                                            <Input
                                                type="password"
                                                placeholder="••••••••••••••••"
                                                value={integrationsForm.data.google_calendar.client_secret || ""}
                                                onChange={(e) =>
                                                    integrationsForm.setData("google_calendar", {
                                                        ...integrationsForm.data.google_calendar,
                                                        client_secret: e.target.value,
                                                    })
                                                }
                                                className="text-xs font-mono"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Authorized Redirect URI</Label>
                                        <div className="flex gap-2">
                                            <Input
                                                readOnly
                                                value={integrationsForm.data.google_calendar.redirect_uri || `${callbackBase}/integrations/google/callback`}
                                                className="text-xs font-mono bg-muted/30"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    copyUrl(
                                                        "google_calendar",
                                                        integrationsForm.data.google_calendar.redirect_uri || `${callbackBase}/integrations/google/callback`,
                                                    )
                                                }
                                                className="shrink-0 gap-1 text-xs"
                                            >
                                                {copiedKey === "google_calendar" ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5" />}
                                                <span>Copy</span>
                                            </Button>
                                        </div>
                                        <p className="text-[11px] text-muted-foreground">
                                            Add this URI in Google Cloud Console &rarr; Credentials &rarr; Authorized redirect URIs.
                                        </p>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>

                            {/* 2. Universal Calendar Feeds (iCal / Webcal) */}
                            <AccordionItem
                                value="calendar_feed"
                                className="rounded-xl border border-border/60 bg-background/50 px-4 transition-colors"
                            >
                                <AccordionTrigger className="py-3.5 hover:no-underline">
                                    <div className="flex min-w-0 items-center gap-3.5 text-left w-full pr-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                            <Radio className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-sm text-foreground">Universal Calendar Feeds (iCal & Webcal)</span>
                                                <Badge
                                                    variant={integrationsForm.data.calendar_feed.enabled ? "default" : "secondary"}
                                                    className="text-[10px] px-1.5 py-0"
                                                >
                                                    {integrationsForm.data.calendar_feed.enabled ? "Active" : "Disabled"}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                                                RFC 5545 timetable publishing for Outlook 365, Apple Calendar, and mobile apps
                                            </p>
                                        </div>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pt-2 pb-5 border-t border-border/40 mt-1 space-y-4">
                                    <div className="flex items-center justify-between p-3 rounded-lg bg-muted/40 border border-border/40">
                                        <div className="space-y-0.5">
                                            <Label className="text-sm font-medium">Enable Calendar Feeds for Entire Application</Label>
                                            <p className="text-xs text-muted-foreground">
                                                Controls whether live subscription tokens and endpoints (/calendar/feed) are active.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={integrationsForm.data.calendar_feed.enabled}
                                            onCheckedChange={(checked) =>
                                                integrationsForm.setData("calendar_feed", {
                                                    ...integrationsForm.data.calendar_feed,
                                                    enabled: checked,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="flex items-center justify-between p-3 rounded-lg bg-background border border-border/50">
                                            <div className="space-y-0.5">
                                                <Label className="text-xs font-medium">Allow Student Feeds</Label>
                                                <p className="text-[11px] text-muted-foreground">Permit students to sync class schedules</p>
                                            </div>
                                            <Switch
                                                checked={integrationsForm.data.calendar_feed.students_enabled}
                                                onCheckedChange={(checked) =>
                                                    integrationsForm.setData("calendar_feed", {
                                                        ...integrationsForm.data.calendar_feed,
                                                        students_enabled: checked,
                                                    })
                                                }
                                            />
                                        </div>

                                        <div className="flex items-center justify-between p-3 rounded-lg bg-background border border-border/50">
                                            <div className="space-y-0.5">
                                                <Label className="text-xs font-medium">Allow Faculty Feeds</Label>
                                                <p className="text-[11px] text-muted-foreground">Permit instructors to sync teaching timetables</p>
                                            </div>
                                            <Switch
                                                checked={integrationsForm.data.calendar_feed.faculty_enabled}
                                                onCheckedChange={(checked) =>
                                                    integrationsForm.setData("calendar_feed", {
                                                        ...integrationsForm.data.calendar_feed,
                                                        faculty_enabled: checked,
                                                    })
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Calendar Display Name Prefix</Label>
                                            <Input
                                                placeholder="e.g. KoAkademy or My University"
                                                value={integrationsForm.data.calendar_feed.title_prefix || ""}
                                                onChange={(e) =>
                                                    integrationsForm.setData("calendar_feed", {
                                                        ...integrationsForm.data.calendar_feed,
                                                        title_prefix: e.target.value,
                                                    })
                                                }
                                                className="text-xs"
                                            />
                                            <p className="text-[11px] text-muted-foreground">Appears as the calendar title in Outlook and iOS.</p>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Weekly Recurrence Count (RRULE Weeks)</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={52}
                                                value={integrationsForm.data.calendar_feed.rrule_weeks}
                                                onChange={(e) =>
                                                    integrationsForm.setData("calendar_feed", {
                                                        ...integrationsForm.data.calendar_feed,
                                                        rrule_weeks: parseInt(e.target.value, 10) || 18,
                                                    })
                                                }
                                                className="text-xs"
                                            />
                                            <p className="text-[11px] text-muted-foreground">Standard semester length for weekly repetition (default 18).</p>
                                        </div>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>

                            {/* 3. Microsoft 365 & Outlook */}
                            <AccordionItem
                                value="microsoft"
                                className="rounded-xl border border-border/60 bg-background/50 px-4 transition-colors"
                            >
                                <AccordionTrigger className="py-3.5 hover:no-underline">
                                    <div className="flex min-w-0 items-center gap-3.5 text-left w-full pr-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                            <Mail className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-sm text-foreground">Microsoft 365 & Azure Entra ID</span>
                                                <Badge
                                                    variant={integrationsForm.data.microsoft.enabled ? "default" : "secondary"}
                                                    className="text-[10px] px-1.5 py-0"
                                                >
                                                    {integrationsForm.data.microsoft.enabled ? "Active" : "Disabled"}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                                                Enterprise Outlook calendar sync, Teams meetings, and Microsoft account connectivity
                                            </p>
                                        </div>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pt-2 pb-5 border-t border-border/40 mt-1 space-y-4">
                                    <div className="flex items-center justify-between p-3 rounded-lg bg-muted/40 border border-border/40">
                                        <div className="space-y-0.5">
                                            <Label className="text-sm font-medium">Enable Microsoft 365 Integration</Label>
                                            <p className="text-xs text-muted-foreground">
                                                Allow Microsoft account linkages and direct Graph API calendar provisioning.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={integrationsForm.data.microsoft.enabled}
                                            onCheckedChange={(checked) =>
                                                integrationsForm.setData("microsoft", {
                                                    ...integrationsForm.data.microsoft,
                                                    enabled: checked,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Application (Client) ID</Label>
                                            <Input
                                                placeholder="Azure App Client ID"
                                                value={integrationsForm.data.microsoft.client_id || ""}
                                                onChange={(e) =>
                                                    integrationsForm.setData("microsoft", {
                                                        ...integrationsForm.data.microsoft,
                                                        client_id: e.target.value,
                                                    })
                                                }
                                                className="text-xs font-mono"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Client Secret Value</Label>
                                            <Input
                                                type="password"
                                                placeholder="••••••••••••••••"
                                                value={integrationsForm.data.microsoft.client_secret || ""}
                                                onChange={(e) =>
                                                    integrationsForm.setData("microsoft", {
                                                        ...integrationsForm.data.microsoft,
                                                        client_secret: e.target.value,
                                                    })
                                                }
                                                className="text-xs font-mono"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Directory (Tenant) ID</Label>
                                            <Input
                                                placeholder="common or tenant UUID"
                                                value={integrationsForm.data.microsoft.tenant_id || "common"}
                                                onChange={(e) =>
                                                    integrationsForm.setData("microsoft", {
                                                        ...integrationsForm.data.microsoft,
                                                        tenant_id: e.target.value,
                                                    })
                                                }
                                                className="text-xs font-mono"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Authorized Redirect URI</Label>
                                        <div className="flex gap-2">
                                            <Input
                                                readOnly
                                                value={integrationsForm.data.microsoft.redirect_uri || `${callbackBase}/integrations/microsoft/callback`}
                                                className="text-xs font-mono bg-muted/30"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    copyUrl(
                                                        "microsoft",
                                                        integrationsForm.data.microsoft.redirect_uri || `${callbackBase}/integrations/microsoft/callback`,
                                                    )
                                                }
                                                className="shrink-0 gap-1 text-xs"
                                            >
                                                {copiedKey === "microsoft" ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5" />}
                                                <span>Copy</span>
                                            </Button>
                                        </div>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>

                            {/* 4. Cloud Storage File Pickers */}
                            <AccordionItem
                                value="cloud_storage"
                                className="rounded-xl border border-border/60 bg-background/50 px-4 transition-colors"
                            >
                                <AccordionTrigger className="py-3.5 hover:no-underline">
                                    <div className="flex min-w-0 items-center gap-3.5 text-left w-full pr-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                            <Cloud className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-sm text-foreground">Cloud Storage Pickers</span>
                                                <Badge
                                                    variant={
                                                        integrationsForm.data.cloud_storage.google_drive_enabled ||
                                                        integrationsForm.data.cloud_storage.onedrive_enabled
                                                            ? "default"
                                                            : "secondary"
                                                    }
                                                    className="text-[10px] px-1.5 py-0"
                                                >
                                                    {integrationsForm.data.cloud_storage.google_drive_enabled ||
                                                    integrationsForm.data.cloud_storage.onedrive_enabled
                                                        ? "Active"
                                                        : "Disabled"}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                                                Google Drive & Microsoft OneDrive file pickers for assignments and course materials
                                            </p>
                                        </div>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pt-2 pb-5 border-t border-border/40 mt-1 space-y-4">
                                    {/* Google Drive */}
                                    <div className="p-3.5 rounded-lg border border-border/50 bg-muted/20 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs font-semibold">Google Drive File Picker</Label>
                                            <Switch
                                                checked={integrationsForm.data.cloud_storage.google_drive_enabled}
                                                onCheckedChange={(checked) =>
                                                    integrationsForm.setData("cloud_storage", {
                                                        ...integrationsForm.data.cloud_storage,
                                                        google_drive_enabled: checked,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Google Developer API Key</Label>
                                                <Input
                                                    placeholder="AIzaSy..."
                                                    value={integrationsForm.data.cloud_storage.google_drive_api_key || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("cloud_storage", {
                                                            ...integrationsForm.data.cloud_storage,
                                                            google_drive_api_key: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Google OAuth Client ID</Label>
                                                <Input
                                                    placeholder="Leave empty to use main Google Client ID"
                                                    value={integrationsForm.data.cloud_storage.google_drive_client_id || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("cloud_storage", {
                                                            ...integrationsForm.data.cloud_storage,
                                                            google_drive_client_id: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* OneDrive */}
                                    <div className="p-3.5 rounded-lg border border-border/50 bg-muted/20 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs font-semibold">Microsoft OneDrive File Picker</Label>
                                            <Switch
                                                checked={integrationsForm.data.cloud_storage.onedrive_enabled}
                                                onCheckedChange={(checked) =>
                                                    integrationsForm.setData("cloud_storage", {
                                                        ...integrationsForm.data.cloud_storage,
                                                        onedrive_enabled: checked,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px] text-muted-foreground">Microsoft App (Client) ID</Label>
                                            <Input
                                                placeholder="Leave empty to use main Microsoft Client ID"
                                                value={integrationsForm.data.cloud_storage.onedrive_client_id || ""}
                                                onChange={(e) =>
                                                    integrationsForm.setData("cloud_storage", {
                                                        ...integrationsForm.data.cloud_storage,
                                                        onedrive_client_id: e.target.value,
                                                    })
                                                }
                                                className="text-xs font-mono"
                                            />
                                        </div>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>

                            {/* 5. Virtual Meeting & Learning Management */}
                            <AccordionItem
                                value="conferencing_and_lms"
                                className="rounded-xl border border-border/60 bg-background/50 px-4 transition-colors"
                            >
                                <AccordionTrigger className="py-3.5 hover:no-underline">
                                    <div className="flex min-w-0 items-center gap-3.5 text-left w-full pr-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                            <Video className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-sm text-foreground">Video Conferencing & LMS (Zoom & Canvas)</span>
                                                <Badge
                                                    variant={
                                                        integrationsForm.data.conferencing_and_lms.zoom_enabled ||
                                                        integrationsForm.data.conferencing_and_lms.canvas_enabled
                                                            ? "default"
                                                            : "secondary"
                                                    }
                                                    className="text-[10px] px-1.5 py-0"
                                                >
                                                    {integrationsForm.data.conferencing_and_lms.zoom_enabled ||
                                                    integrationsForm.data.conferencing_and_lms.canvas_enabled
                                                        ? "Active"
                                                        : "Disabled"}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                                                Server-to-Server Zoom OAuth links and Canvas LTI 1.3 gradebook synchronization
                                            </p>
                                        </div>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pt-2 pb-5 border-t border-border/40 mt-1 space-y-4">
                                    {/* Zoom */}
                                    <div className="p-3.5 rounded-lg border border-border/50 bg-muted/20 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs font-semibold">Zoom Meetings (Server-to-Server OAuth)</Label>
                                            <Switch
                                                checked={integrationsForm.data.conferencing_and_lms.zoom_enabled}
                                                onCheckedChange={(checked) =>
                                                    integrationsForm.setData("conferencing_and_lms", {
                                                        ...integrationsForm.data.conferencing_and_lms,
                                                        zoom_enabled: checked,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Account ID</Label>
                                                <Input
                                                    placeholder="Zoom Account ID"
                                                    value={integrationsForm.data.conferencing_and_lms.zoom_account_id || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("conferencing_and_lms", {
                                                            ...integrationsForm.data.conferencing_and_lms,
                                                            zoom_account_id: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Client ID</Label>
                                                <Input
                                                    placeholder="Zoom Client ID"
                                                    value={integrationsForm.data.conferencing_and_lms.zoom_client_id || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("conferencing_and_lms", {
                                                            ...integrationsForm.data.conferencing_and_lms,
                                                            zoom_client_id: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Client Secret</Label>
                                                <Input
                                                    type="password"
                                                    placeholder="••••••••••••••••"
                                                    value={integrationsForm.data.conferencing_and_lms.zoom_client_secret || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("conferencing_and_lms", {
                                                            ...integrationsForm.data.conferencing_and_lms,
                                                            zoom_client_secret: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Canvas LMS */}
                                    <div className="p-3.5 rounded-lg border border-border/50 bg-muted/20 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs font-semibold">Canvas LMS (LTI 1.3)</Label>
                                            <Switch
                                                checked={integrationsForm.data.conferencing_and_lms.canvas_enabled}
                                                onCheckedChange={(checked) =>
                                                    integrationsForm.setData("conferencing_and_lms", {
                                                        ...integrationsForm.data.conferencing_and_lms,
                                                        canvas_enabled: checked,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Canvas Instance URL</Label>
                                                <Input
                                                    placeholder="https://canvas.institution.edu"
                                                    value={integrationsForm.data.conferencing_and_lms.canvas_base_url || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("conferencing_and_lms", {
                                                            ...integrationsForm.data.conferencing_and_lms,
                                                            canvas_base_url: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[11px] text-muted-foreground">Canvas Developer Client ID</Label>
                                                <Input
                                                    placeholder="LTI 1.3 Client ID"
                                                    value={integrationsForm.data.conferencing_and_lms.canvas_client_id || ""}
                                                    onChange={(e) =>
                                                        integrationsForm.setData("conferencing_and_lms", {
                                                            ...integrationsForm.data.conferencing_and_lms,
                                                            canvas_client_id: e.target.value,
                                                        })
                                                    }
                                                    className="text-xs font-mono"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        </Accordion>
                    </CardContent>
                </Card>
            </div>
        </SystemManagementLayout>
    );
}
