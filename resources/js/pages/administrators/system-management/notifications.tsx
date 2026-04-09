import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import type { LucideIcon } from "lucide-react";
import { Bell, BellRing, Bolt, Loader2, Mail, MessageSquare, Save, Signal, Smartphone } from "lucide-react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

// ── Channel definition ──────────────────────────────────────────────

interface ChannelDefinition {
    key: string;
    label: string;
    description: string;
    icon: LucideIcon;
    color: string;
}

const CHANNEL_DEFINITIONS: ChannelDefinition[] = [
    {
        key: "mail",
        label: "Email",
        description: "Send notifications via email using your configured SMTP provider.",
        icon: Mail,
        color: "text-blue-500",
    },
    {
        key: "database",
        label: "In-App (Database)",
        description: "Store notifications in the database for in-app bell / notification panel display.",
        icon: Bell,
        color: "text-amber-500",
    },
    {
        key: "broadcast",
        label: "Realtime (Broadcast)",
        description: "Push live notifications to the browser via WebSocket broadcasting.",
        icon: Signal,
        color: "text-emerald-500",
    },
    {
        key: "sms",
        label: "SMS",
        description: "Deliver notifications as SMS text messages via a gateway provider.",
        icon: Smartphone,
        color: "text-violet-500",
    },
    {
        key: "pusher",
        label: "Pusher Channels",
        description: "Use Pusher as the broadcast driver for realtime events.",
        icon: Bolt,
        color: "text-orange-500",
    },
];

// ── Component ───────────────────────────────────────────────────────

export default function SystemManagementNotificationsPage({ user, notification_channels, third_party_services, access }: SystemManagementPageProps) {
    const form = useForm({
        enabled_channels: notification_channels?.enabled_channels ?? ["mail", "database"],
        pusher: {
            app_id: notification_channels?.pusher?.app_id ?? "",
            key: notification_channels?.pusher?.key ?? "",
            secret: notification_channels?.pusher?.secret ?? "",
            cluster: notification_channels?.pusher?.cluster ?? "mt1",
        },
        sms: {
            provider: notification_channels?.sms?.provider ?? "",
            api_key: notification_channels?.sms?.api_key ?? "",
            sender_id: notification_channels?.sms?.sender_id ?? "",
        },
        third_party_services: third_party_services ?? {},
    });

    const isEnabled = (channelKey: string) => form.data.enabled_channels.includes(channelKey);

    const toggleChannel = (channelKey: string) => {
        const current = [...form.data.enabled_channels];

        if (current.includes(channelKey)) {
            form.setData(
                "enabled_channels",
                current.filter((key) => key !== channelKey),
            );
        } else {
            form.setData("enabled_channels", [...current, channelKey]);
        }
    };

    const showPusherConfig = isEnabled("broadcast") || isEnabled("pusher");
    const showSmsConfig = isEnabled("sms");

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="notifications"
            heading="Notification Channels"
            description="Enable, disable, and configure notification delivery channels used across the application."
        >
            {/* ── Master toggle card ─────────────────────────────────── */}
            <Card>
                <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle className="flex items-center gap-2">
                                <BellRing className="h-5 w-5" />
                                Channel Configuration
                            </CardTitle>
                            <CardDescription>
                                Toggle each channel on or off. Provider-specific settings appear below when a channel is active.
                            </CardDescription>
                        </div>
                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form: form as any,
                                    routeName: "administrators.system-management.notifications.update",
                                    successMessage: "Notification channels updated successfully.",
                                    errorMessage: "Failed to update notification channels.",
                                })
                            }
                            disabled={form.processing}
                        >
                            {form.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save
                        </Button>
                    </div>
                </CardHeader>

                <CardContent className="space-y-4">
                    {CHANNEL_DEFINITIONS.map((channel) => {
                        const Icon = channel.icon;
                        const active = isEnabled(channel.key);

                        return (
                            <div
                                key={channel.key}
                                className={`flex items-center justify-between rounded-lg border p-4 transition-colors ${active ? "border-primary/30 bg-primary/5" : "border-border"}`}
                            >
                                <div className="flex items-center gap-4">
                                    <div className={`bg-muted rounded-lg p-2.5 ${channel.color}`}>
                                        <Icon className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{channel.label}</span>
                                            {active && (
                                                <Badge variant="outline" className="text-xs text-green-600">
                                                    Active
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground text-sm">{channel.description}</p>
                                    </div>
                                </div>
                                <Switch checked={active} onCheckedChange={() => toggleChannel(channel.key)} />
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            {/* ── Pusher config card ─────────────────────────────────── */}
            {showPusherConfig && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Bolt className="h-4 w-4 text-orange-500" />
                            Pusher Configuration
                        </CardTitle>
                        <CardDescription>
                            Credentials for Pusher Channels. These values are also written to your environment file on save.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="pusher_app_id">App ID</Label>
                                <Input
                                    id="pusher_app_id"
                                    value={form.data.pusher.app_id}
                                    onChange={(e) => form.setData("pusher", { ...form.data.pusher, app_id: e.target.value })}
                                    placeholder="123456"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pusher_key">Key</Label>
                                <Input
                                    id="pusher_key"
                                    value={form.data.pusher.key}
                                    onChange={(e) => form.setData("pusher", { ...form.data.pusher, key: e.target.value })}
                                    placeholder="xxxxxxxxxxxxxxxx"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pusher_secret">Secret</Label>
                                <Input
                                    id="pusher_secret"
                                    type="password"
                                    value={form.data.pusher.secret}
                                    onChange={(e) => form.setData("pusher", { ...form.data.pusher, secret: e.target.value })}
                                    placeholder="••••••••••••"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pusher_cluster">Cluster</Label>
                                <Select
                                    value={form.data.pusher.cluster || "mt1"}
                                    onValueChange={(value) => form.setData("pusher", { ...form.data.pusher, cluster: value })}
                                >
                                    <SelectTrigger id="pusher_cluster">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="mt1">mt1 (US East)</SelectItem>
                                        <SelectItem value="us2">us2 (US East 2)</SelectItem>
                                        <SelectItem value="us3">us3 (US West)</SelectItem>
                                        <SelectItem value="eu">eu (EU Ireland)</SelectItem>
                                        <SelectItem value="ap1">ap1 (Asia Pacific – Singapore)</SelectItem>
                                        <SelectItem value="ap2">ap2 (Asia Pacific – Mumbai)</SelectItem>
                                        <SelectItem value="ap3">ap3 (Asia Pacific – Tokyo)</SelectItem>
                                        <SelectItem value="ap4">ap4 (Asia Pacific – Sydney)</SelectItem>
                                        <SelectItem value="sa1">sa1 (South America – São Paulo)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* ── SMS config card ────────────────────────────────────── */}
            {showSmsConfig && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <MessageSquare className="h-4 w-4 text-violet-500" />
                            SMS Provider Configuration
                        </CardTitle>
                        <CardDescription>Enter credentials for your SMS gateway provider (Vonage, Twilio, Semaphore, etc.).</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="sms_provider">Provider</Label>
                                <Select
                                    value={form.data.sms.provider || ""}
                                    onValueChange={(value) => form.setData("sms", { ...form.data.sms, provider: value })}
                                >
                                    <SelectTrigger id="sms_provider">
                                        <SelectValue placeholder="Select a provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="vonage">Vonage (Nexmo)</SelectItem>
                                        <SelectItem value="twilio">Twilio</SelectItem>
                                        <SelectItem value="semaphore">Semaphore</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="sms_sender_id">Sender ID / From</Label>
                                <Input
                                    id="sms_sender_id"
                                    value={form.data.sms.sender_id}
                                    onChange={(e) => form.setData("sms", { ...form.data.sms, sender_id: e.target.value })}
                                    placeholder="KOA"
                                />
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="sms_api_key">API Key / Auth Token</Label>
                                <Input
                                    id="sms_api_key"
                                    type="password"
                                    value={form.data.sms.api_key}
                                    onChange={(e) => form.setData("sms", { ...form.data.sms, api_key: e.target.value })}
                                    placeholder="••••••••••••"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* ── Third-Party Services config cards ────────────────────── */}
            {Object.entries(form.data.third_party_services || {}).map(([serviceName, config]) => (
                <Card key={serviceName}>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base capitalize">
                            <Bolt className="text-primary h-4 w-4" />
                            {serviceName} Configuration
                        </CardTitle>
                        <CardDescription>Auto-detected from your services configuration. Override specific keys below.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {Object.entries(config || {}).map(([key, value]) => {
                                // Simple heuristic: if key contains 'secret', 'token', or 'key', make it a password field
                                const isSensitive =
                                    key.toLowerCase().includes("secret") || key.toLowerCase().includes("token") || key.toLowerCase().includes("key");
                                return (
                                    <div key={key} className="space-y-2">
                                        <Label htmlFor={`${serviceName}_${key}`} className="capitalize">
                                            {key.replace(/_/g, " ")}
                                        </Label>
                                        <Input
                                            id={`${serviceName}_${key}`}
                                            type={isSensitive ? "password" : "text"}
                                            value={value || ""}
                                            onChange={(e) => {
                                                const newServices = { ...form.data.third_party_services };
                                                newServices[serviceName] = {
                                                    ...newServices[serviceName],
                                                    [key]: e.target.value,
                                                };
                                                form.setData("third_party_services", newServices);
                                            }}
                                            placeholder={`Optionally override ${key}`}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            ))}

            {/* ── Summary / info card ────────────────────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Active Channels Summary</CardTitle>
                    <CardDescription>
                        When services or controllers send notifications, only the channels marked active above will be used. You can reference the{" "}
                        <code className="text-xs">NotificationChannel</code> enum in PHP for type-safe channel selection.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-2">
                        {form.data.enabled_channels.length === 0 && (
                            <p className="text-muted-foreground text-sm italic">No channels enabled. Notifications will not be delivered.</p>
                        )}
                        {form.data.enabled_channels.map((channelKey) => {
                            const def = CHANNEL_DEFINITIONS.find((d) => d.key === channelKey);
                            if (!def) return null;
                            const Icon = def.icon;
                            return (
                                <Badge key={channelKey} variant="secondary" className="gap-1.5 px-3 py-1">
                                    <Icon className={`h-3.5 w-3.5 ${def.color}`} />
                                    {def.label}
                                </Badge>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}
