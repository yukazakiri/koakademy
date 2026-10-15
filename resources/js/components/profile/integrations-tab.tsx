import React, { useState } from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge as ReUiBadge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { NewsletterSettingsCard } from "@/pages/administrators/settings/newsletter-settings-card";
import { SiFacebook, SiGithub, SiGoogle, SiX } from "@icons-pack/react-simple-icons";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    Calendar,
    Check,
    ChevronDown,
    ChevronUp,
    Cloud,
    Copy,
    Download,
    ExternalLink,
    GraduationCap,
    Info,
    Link2,
    Mail,
    Plus,
    Radio,
    RefreshCw,
    Share2,
    Trash2,
    Video,
} from "lucide-react";
import { toast } from "sonner";

type ConnectedAccount = {
    id: number;
    provider: string;
    provider_id: string;
    name?: string | null;
    nickname?: string | null;
    email?: string | null;
    avatar_path?: string | null;
};

type ConnectedAccountsPayload = {
    providers: Record<string, boolean>;
    accounts: ConnectedAccount[];
};

type SocialAuthProvider = {
    key: string;
    label: string;
    redirect_url: string;
};

type CalendarFeedData = {
    feed_url?: string;
    webcal_url?: string;
    download_url?: string;
};

interface IntegrationsTabProps {
    connectedAccounts: ConnectedAccountsPayload;
    calendarFeed?: CalendarFeedData;
    canViewNewsletterSettings?: boolean;
    newsletterSettingsUrl?: string | null;
}

const socialProviderConfigs = [
    {
        key: "google",
        label: "Google",
        description: "Institutional or personal Google account for login & calendar sync",
        icon: SiGoogle,
        iconClassName: "text-red-600 dark:text-red-400",
        iconBackground: "bg-red-100 dark:bg-red-900/30",
    },
    {
        key: "github",
        label: "GitHub",
        description: "Developer profile for engineering courses, code repositories, and fast SSO",
        icon: SiGithub,
        iconClassName: "text-slate-900 dark:text-slate-100",
        iconBackground: "bg-slate-100 dark:bg-slate-800",
    },
    {
        key: "linkedin",
        label: "LinkedIn",
        description: "Professional network profile for career services and alumni directory",
        icon: Link2,
        iconClassName: "text-blue-700 dark:text-blue-400",
        iconBackground: "bg-blue-50 dark:bg-blue-900/20",
    },
    {
        key: "facebook",
        label: "Facebook",
        description: "Connect your Facebook account for alternative passwordless sign-in",
        icon: SiFacebook,
        iconClassName: "text-blue-600 dark:text-blue-400",
        iconBackground: "bg-blue-100 dark:bg-blue-900/30",
    },
    {
        key: "twitter",
        label: "X (Twitter)",
        description: "Connect your X profile for quick institutional verification",
        icon: SiX,
        iconClassName: "text-slate-900 dark:text-slate-100",
        iconBackground: "bg-slate-100 dark:bg-slate-800",
    },
];

export function IntegrationsTab({
    connectedAccounts,
    calendarFeed,
    canViewNewsletterSettings = false,
    newsletterSettingsUrl = null,
}: IntegrationsTabProps) {
    const { socialAuthProviders = [] } = usePage<{ socialAuthProviders?: SocialAuthProvider[] }>().props;
    const enabledProviderKeys = new Set(socialAuthProviders.map((provider) => provider.key));

    const [activeSection, setActiveSection] = useState<"services" | "social">("services");
    const [confirmingUnsync, setConfirmingUnsync] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [copiedFeed, setCopiedFeed] = useState(false);
    const [showOutlookGuide, setShowOutlookGuide] = useState(false);

    const isGoogleConnected = Boolean(connectedAccounts.providers?.google);
    const googleAccounts = connectedAccounts.accounts.filter((account) => account.provider === "google");
    const primaryGoogleAccount = googleAccounts[0];

    const handleCopyFeed = () => {
        if (!calendarFeed?.feed_url) {
            toast.error("Calendar feed URL is not available.");
            return;
        }
        navigator.clipboard.writeText(calendarFeed.feed_url);
        setCopiedFeed(true);
        toast.success("Calendar subscription URL copied! Paste into Outlook or Apple Calendar.");
        setTimeout(() => setCopiedFeed(false), 2500);
    };

    const handleWebcalSubscribe = () => {
        if (!calendarFeed?.webcal_url) return;
        window.location.href = calendarFeed.webcal_url;
    };

    const handleDownloadIcs = () => {
        window.location.href = calendarFeed?.download_url || "/calendar/download";
    };

    const handleGoogleToggle = (checked: boolean) => {
        if (checked) {
            window.location.href = "/integrations/google/connect";
            return;
        }

        if (!window.confirm("Disconnect Google from your profile? This removes the linked account and calendar access.")) {
            return;
        }

        router.post(
            "/integrations/google/disconnect",
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Google disconnected successfully.");
                },
                onError: () => {
                    toast.error("Failed to disconnect Google.");
                },
            },
        );
    };

    const handleSyncGoogleCalendar = () => {
        setIsSyncing(true);
        const toastId = toast.loading("Syncing academic calendar with Google...");
        axios
            .post("/integrations/google/sync")
            .then((response) => {
                toast.dismiss(toastId);
                toast.success(response.data?.message || "Google Calendar synchronized successfully!");
            })
            .catch((error) => {
                toast.dismiss(toastId);
                toast.error("Failed to sync calendar. Please check permissions or reconnect.");
                console.error(error);
            })
            .finally(() => {
                setIsSyncing(false);
            });
    };

    const executeUnsyncGoogleCalendar = () => {
        const toastId = toast.loading("Removing synced events...");
        axios
            .post("/integrations/google/unsync")
            .then((response) => {
                toast.dismiss(toastId);
                toast.success(response.data?.message || "Events removed from Google Calendar.");
                setConfirmingUnsync(false);
            })
            .catch((error) => {
                toast.dismiss(toastId);
                toast.error("Failed to unsync calendar.");
                console.error(error);
                setConfirmingUnsync(false);
            });
    };

    const handleConnectSocial = (provider: string) => {
        window.location.href = `/integrations/${provider}/connect`;
    };

    const handleDisconnectSocial = (provider: string, accountId?: number) => {
        const label = socialProviderConfigs.find((config) => config.key === provider)?.label ?? provider;

        if (!window.confirm(`Are you sure you want to disconnect ${label}?`)) {
            return;
        }

        router.post(
            `/integrations/${provider}/disconnect`,
            accountId ? { account_id: accountId } : {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success(`${label} disconnected successfully`),
                onError: () => toast.error(`Failed to disconnect ${label}`),
            },
        );
    };

    return (
        <div className="space-y-6">
            {canViewNewsletterSettings && newsletterSettingsUrl ? <NewsletterSettingsCard href={newsletterSettingsUrl} /> : null}

            {/* Sub-navigation switcher */}
            <div className="flex items-center justify-between border-b pb-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight">Connected Accounts & Apps</h2>
                    <p className="text-muted-foreground text-xs sm:text-sm">
                        Manage external tool synchronization, single sign-on providers, and institutional integrations.
                    </p>
                </div>
                <div className="flex items-center gap-1 rounded-xl bg-muted/60 p-1">
                    <button
                        type="button"
                        onClick={() => setActiveSection("services")}
                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                            activeSection === "services"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        Services & Sync
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveSection("social")}
                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                            activeSection === "social"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground"
                        }`}
                    >
                        Sign-In Providers
                    </button>
                </div>
            </div>

            {activeSection === "services" ? (
                <div className="space-y-6">
                    {/* Live Services Section */}
                    <Frame spacing="sm" className="border-border/70 rounded-2xl shadow-sm">
                        <FrameHeader className="border-b pb-4">
                            <div className="flex items-center justify-between">
                                <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Calendar className="h-4 w-4 text-primary" />
                                    Academic Calendar & Productivity
                                </FrameTitle>
                                <ReUiBadge variant="success-light" size="xs">
                                    Production Ready
                                </ReUiBadge>
                            </div>
                            <p className="text-muted-foreground text-xs sm:text-sm">
                                Synchronize scheduled classes, consultation hours, and academic deadlines directly with your personal calendar.
                            </p>
                        </FrameHeader>

                        {/* Google Calendar Card */}
                        <FramePanel className="bg-card">
                            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div className="flex items-start gap-4">
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-info/10 text-info ring-1 ring-info/20 shadow-xs">
                                        <Calendar className="h-6 w-6" />
                                    </div>
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold text-foreground text-base">Google Calendar</h3>
                                            <ReUiBadge
                                                variant={isGoogleConnected ? "success-light" : "secondary"}
                                                size="sm"
                                            >
                                                {isGoogleConnected ? "Connected & Active" : "Not connected"}
                                            </ReUiBadge>
                                        </div>
                                        <p className="text-muted-foreground text-xs sm:text-sm max-w-xl">
                                            {isGoogleConnected
                                                ? `Synchronized with ${primaryGoogleAccount?.email || primaryGoogleAccount?.name || "your Google account"}. Updates export on demand or during roster changes.`
                                                : "Automatically sync teaching rosters, exam schedules, and consultation appointments to your Google Calendar."}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 pt-2 md:pt-0">
                                    {isGoogleConnected ? (
                                        <>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleSyncGoogleCalendar}
                                                disabled={isSyncing}
                                                className="rounded-xl text-xs font-semibold gap-1.5"
                                            >
                                                <RefreshCw className={`h-3.5 w-3.5 ${isSyncing ? "animate-spin text-primary" : ""}`} />
                                                {isSyncing ? "Syncing..." : "Sync now"}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setConfirmingUnsync(true)}
                                                className="rounded-xl text-xs font-semibold text-destructive hover:bg-destructive/10"
                                            >
                                                Unsync
                                            </Button>
                                        </>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() => (window.location.href = "/integrations/google/connect")}
                                            className="rounded-xl text-xs font-semibold gap-1.5"
                                        >
                                            Connect Calendar
                                        </Button>
                                    )}
                                    <Switch
                                        checked={isGoogleConnected}
                                        onCheckedChange={handleGoogleToggle}
                                        aria-label="Toggle Google Calendar connection"
                                    />
                                </div>
                            </div>
                        </FramePanel>

                        {/* Microsoft 365, Outlook & Apple Calendar Feed */}
                        <FramePanel className="bg-card border-t border-border/60">
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                    <div className="flex items-start gap-4">
                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-1 ring-blue-500/20 shadow-xs">
                                            <Mail className="h-6 w-6" />
                                        </div>
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-semibold text-foreground text-base">Microsoft Outlook & Universal Calendar Feed</h3>
                                                <ReUiBadge variant="success-light" size="sm">
                                                    Live Feed (iCal / Webcal)
                                                </ReUiBadge>
                                            </div>
                                            <p className="text-muted-foreground text-xs sm:text-sm max-w-xl">
                                                Universal real-time subscription feed. Syncs class timetables, consultation hours, and academic deadlines directly with Microsoft 365 Outlook, Apple Calendar, and mobile devices.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 pt-2 md:pt-0">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleCopyFeed}
                                            className="rounded-xl text-xs font-semibold gap-1.5"
                                            title="Copy calendar subscription feed URL"
                                        >
                                            {copiedFeed ? (
                                                <>
                                                    <Check className="h-3.5 w-3.5 text-success" />
                                                    Copied URL
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="h-3.5 w-3.5" />
                                                    Copy Feed URL
                                                </>
                                            )}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={handleWebcalSubscribe}
                                            className="rounded-xl text-xs font-semibold gap-1.5"
                                        >
                                            <Radio className="h-3.5 w-3.5" />
                                            Subscribe (Webcal)
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleDownloadIcs}
                                            className="rounded-xl text-xs font-medium gap-1 text-muted-foreground"
                                            title="Download static .ics file"
                                        >
                                            <Download className="h-3.5 w-3.5" />
                                            .ics
                                        </Button>
                                    </div>
                                </div>

                                {/* Collapsible Outlook & Apple Calendar Setup Guide */}
                                <div className="border-t border-border/40 pt-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowOutlookGuide((prev) => !prev)}
                                        className="flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                                    >
                                        {showOutlookGuide ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                                        {showOutlookGuide ? "Hide subscription instructions" : "How to subscribe in Microsoft Outlook 365 & Apple Calendar"}
                                    </button>

                                    {showOutlookGuide && (
                                        <div className="mt-3 grid gap-3 rounded-xl bg-muted/40 p-3.5 text-xs text-muted-foreground sm:grid-cols-2">
                                            <div className="space-y-1.5">
                                                <p className="font-semibold text-foreground flex items-center gap-1.5">
                                                    <Mail className="h-3.5 w-3.5 text-blue-600" />
                                                    Microsoft 365 & Outlook Web
                                                </p>
                                                <ol className="list-decimal list-inside space-y-1">
                                                    <li>Copy your feed URL above.</li>
                                                    <li>In Outlook, open <strong>Calendar</strong>.</li>
                                                    <li>Select <strong>Add calendar</strong> &rarr; <strong>Subscribe from web</strong>.</li>
                                                    <li>Paste your URL and click <strong>Import</strong>.</li>
                                                </ol>
                                            </div>
                                            <div className="space-y-1.5">
                                                <p className="font-semibold text-foreground flex items-center gap-1.5">
                                                    <Calendar className="h-3.5 w-3.5 text-red-500" />
                                                    Apple Calendar & Mobile
                                                </p>
                                                <ol className="list-decimal list-inside space-y-1">
                                                    <li>Click <strong>Subscribe (Webcal)</strong> above.</li>
                                                    <li>Or in Apple Calendar, press <strong>File &rarr; New Calendar Subscription</strong>.</li>
                                                    <li>Paste the URL and set Auto-refresh to <strong>Every Hour</strong>.</li>
                                                </ol>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>

                    {/* Upcoming Integrations Roadmap */}
                    <Frame spacing="sm" className="border-border/70 rounded-2xl shadow-sm bg-muted/10">
                        <FrameHeader className="border-b pb-4">
                            <div className="flex items-center justify-between">
                                <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Cloud className="h-4 w-4 text-muted-foreground" />
                                    Institutional Ecosystem Roadmap
                                </FrameTitle>
                                <ReUiBadge variant="warning-light" size="xs">
                                    Active Development
                                </ReUiBadge>
                            </div>
                            <p className="text-muted-foreground text-xs sm:text-sm">
                                These integrations are scheduled across upcoming phases. Available features are active above.
                            </p>
                        </FrameHeader>

                        {[
                            {
                                name: "Google Drive & Microsoft OneDrive Picker",
                                description: "Attach syllabus documents, lab reports, and assignment files directly from cloud storage.",
                                icon: Cloud,
                                statusBadge: "Phase 1: In Progress",
                            },
                            {
                                name: "Zoom & Google Meet Automation",
                                description: "Automated recurring consultation links and virtual classroom creation with attendance logs.",
                                icon: Video,
                                statusBadge: "Phase 2: Planned",
                            },
                            {
                                name: "Canvas LMS (LTI 1.3)",
                                description: "Synchronize class rosters, assignment submissions, and midterm/final grading records.",
                                icon: GraduationCap,
                                statusBadge: "Phase 2: Planned",
                            },
                        ].map((provider) => (
                            <FramePanel key={provider.name} className="bg-card/50">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between opacity-80">
                                    <div className="flex items-start gap-4">
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                                            <provider.icon className="h-5 w-5" />
                                        </div>
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="font-semibold text-sm">{provider.name}</h4>
                                                <ReUiBadge variant="warning-light" size="xs">
                                                    {provider.statusBadge}
                                                </ReUiBadge>
                                            </div>
                                            <p className="text-muted-foreground text-xs max-w-xl">
                                                {provider.description}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-[11px] text-muted-foreground italic">Coming soon</span>
                                        <Switch checked={false} disabled aria-disabled="true" />
                                    </div>
                                </div>
                            </FramePanel>
                        ))}
                    </Frame>
                </div>
            ) : (
                /* Social & Single Sign-On Section */
                <Frame spacing="sm" className="border-border/70 rounded-2xl shadow-sm">
                    <FrameHeader className="border-b pb-4">
                        <div className="flex items-center justify-between">
                            <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                <Share2 className="h-4 w-4 text-primary" />
                                Single Sign-On & Social Logins
                            </FrameTitle>
                            <ReUiBadge variant="primary-light" size="xs">
                                Authentication
                            </ReUiBadge>
                        </div>
                        <p className="text-muted-foreground text-xs sm:text-sm">
                            Link external accounts to enable one-click passwordless login and maintain access across devices.
                        </p>
                    </FrameHeader>

                    {socialProviderConfigs.map((provider) => {
                        const Icon = provider.icon;
                        const accounts = connectedAccounts.accounts.filter((account) => account.provider === provider.key);
                        const isConnected = Boolean(connectedAccounts.providers[provider.key]);
                        const isEnabled = enabledProviderKeys.has(provider.key);

                        return (
                            <FramePanel key={provider.key} className="bg-card">
                                <div className="flex flex-col gap-4">
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="flex min-w-0 items-center gap-4">
                                            <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${provider.iconBackground} shadow-xs`}>
                                                <Icon className={`h-5 w-5 ${provider.iconClassName}`} />
                                            </div>
                                            <div className="min-w-0 space-y-0.5">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-semibold text-sm">{provider.label}</p>
                                                    {isConnected && (
                                                        <ReUiBadge variant="success-light" size="xs">
                                                            Linked
                                                        </ReUiBadge>
                                                    )}
                                                    {!isEnabled && !isConnected && (
                                                        <ReUiBadge variant="invert-light" size="xs">
                                                            Disabled by Admin
                                                        </ReUiBadge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground text-xs truncate sm:whitespace-normal">
                                                    {provider.description}
                                                </p>
                                            </div>
                                        </div>

                                        {provider.key === "google" && isConnected ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleConnectSocial(provider.key)}
                                                disabled={!isEnabled}
                                                className="rounded-xl text-xs gap-1"
                                            >
                                                <Plus className="h-3.5 w-3.5" />
                                                Add Account
                                            </Button>
                                        ) : (
                                            <Switch
                                                checked={isConnected}
                                                disabled={!isEnabled && !isConnected}
                                                onCheckedChange={(checked) =>
                                                    checked ? handleConnectSocial(provider.key) : handleDisconnectSocial(provider.key)
                                                }
                                                aria-label={`Toggle ${provider.label} connection`}
                                            />
                                        )}
                                    </div>

                                    {/* Linked Accounts List */}
                                    {accounts.length > 0 && (
                                        <div className="grid gap-2 border-t pt-3">
                                            {accounts.map((account) => (
                                                <div
                                                    key={account.id}
                                                    className="flex items-center justify-between gap-3 rounded-xl border border-border/50 bg-muted/30 p-2.5 sm:px-3"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <Avatar className="h-8 w-8 rounded-lg">
                                                            <AvatarImage src={account.avatar_path ?? undefined} alt={account.name ?? account.email ?? provider.label} />
                                                            <AvatarFallback className="rounded-lg text-xs font-semibold">
                                                                {(account.name ?? account.email ?? "A").slice(0, 1).toUpperCase()}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="min-w-0">
                                                            <p className="truncate text-xs font-semibold">{account.name ?? "Linked Account"}</p>
                                                            <p className="text-muted-foreground truncate text-[11px]">{account.email}</p>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 rounded-lg text-muted-foreground hover:text-destructive"
                                                        onClick={() => handleDisconnectSocial(provider.key, account.id)}
                                                        title={`Disconnect ${account.email}`}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                        <span className="sr-only">Disconnect {account.email}</span>
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </FramePanel>
                        );
                    })}
                </Frame>
            )}

            {/* Unsync Confirmation Dialog */}
            <Dialog open={confirmingUnsync} onOpenChange={setConfirmingUnsync}>
                <DialogContent className="rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Unsync Google Calendar Events</DialogTitle>
                        <DialogDescription className="text-xs sm:text-sm">
                            This will remove all synchronized KoAkademy events and class schedules from your connected Google Calendar.
                            Your timetable inside KoAkademy will remain intact.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button variant="ghost" className="rounded-xl text-xs" onClick={() => setConfirmingUnsync(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" className="rounded-xl text-xs font-semibold" onClick={executeUnsyncGoogleCalendar}>
                            Confirm & Remove Events
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

