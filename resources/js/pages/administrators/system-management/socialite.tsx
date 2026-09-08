import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { SiFacebook, SiGithub, SiGoogle, SiX } from "@icons-pack/react-simple-icons";
import { useForm } from "@inertiajs/react";
import { Check, Copy, Info, Link2, Loader2, Lock, Save, ShieldCheck } from "lucide-react";
import { useState, type ComponentType } from "react";
import { toast } from "sonner";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface SocialiteFormData {
    facebook_client_id: string;
    facebook_client_secret: string;
    facebook_enabled: boolean;
    facebook_redirect_uri: string;
    google_client_id: string;
    google_client_secret: string;
    google_enabled: boolean;
    google_redirect_uri: string;
    twitter_client_id: string;
    twitter_client_secret: string;
    twitter_enabled: boolean;
    twitter_redirect_uri: string;
    github_client_id: string;
    github_client_secret: string;
    github_enabled: boolean;
    github_redirect_uri: string;
    linkedin_client_id: string;
    linkedin_client_secret: string;
    linkedin_enabled: boolean;
    linkedin_redirect_uri: string;
}

interface ProviderConfig {
    key: string;
    label: string;
    icon: ComponentType<{ className?: string }>;
    idField: keyof SocialiteFormData;
    secretField: keyof SocialiteFormData;
    enabledField: keyof SocialiteFormData;
    redirectField: keyof SocialiteFormData;
    idLabel: string;
    secretLabel: string;
}

const providerConfigs: ProviderConfig[] = [
    {
        key: "google",
        label: "Google Workspace",
        icon: SiGoogle,
        idField: "google_client_id",
        secretField: "google_client_secret",
        enabledField: "google_enabled",
        redirectField: "google_redirect_uri",
        idLabel: "Client ID",
        secretLabel: "Client Secret",
    },
    {
        key: "facebook",
        label: "Facebook Login",
        icon: SiFacebook,
        idField: "facebook_client_id",
        secretField: "facebook_client_secret",
        enabledField: "facebook_enabled",
        redirectField: "facebook_redirect_uri",
        idLabel: "App ID",
        secretLabel: "App Secret",
    },
    {
        key: "github",
        label: "GitHub",
        icon: SiGithub,
        idField: "github_client_id",
        secretField: "github_client_secret",
        enabledField: "github_enabled",
        redirectField: "github_redirect_uri",
        idLabel: "Client ID",
        secretLabel: "Client Secret",
    },
    {
        key: "twitter",
        label: "Twitter / X",
        icon: SiX,
        idField: "twitter_client_id",
        secretField: "twitter_client_secret",
        enabledField: "twitter_enabled",
        redirectField: "twitter_redirect_uri",
        idLabel: "Client ID",
        secretLabel: "Client Secret",
    },
    {
        key: "linkedin",
        label: "LinkedIn OpenID",
        icon: Link2,
        idField: "linkedin_client_id",
        secretField: "linkedin_client_secret",
        enabledField: "linkedin_enabled",
        redirectField: "linkedin_redirect_uri",
        idLabel: "Client ID",
        secretLabel: "Client Secret",
    },
];

export default function SystemManagementSocialitePage({ user, socialite_config, access }: SystemManagementPageProps) {
    const socialiteForm = useForm<SocialiteFormData>({
        facebook_client_id: socialite_config?.facebook_client_id || "",
        facebook_client_secret: socialite_config?.facebook_client_secret || "",
        facebook_enabled: Boolean(socialite_config?.facebook_enabled),
        facebook_redirect_uri: socialite_config?.facebook_redirect_uri || "",
        google_client_id: socialite_config?.google_client_id || "",
        google_client_secret: socialite_config?.google_client_secret || "",
        google_enabled: Boolean(socialite_config?.google_enabled),
        google_redirect_uri: socialite_config?.google_redirect_uri || "",
        twitter_client_id: socialite_config?.twitter_client_id || "",
        twitter_client_secret: socialite_config?.twitter_client_secret || "",
        twitter_enabled: Boolean(socialite_config?.twitter_enabled),
        twitter_redirect_uri: socialite_config?.twitter_redirect_uri || "",
        github_client_id: socialite_config?.github_client_id || "",
        github_client_secret: socialite_config?.github_client_secret || "",
        github_enabled: Boolean(socialite_config?.github_enabled),
        github_redirect_uri: socialite_config?.github_redirect_uri || "",
        linkedin_client_id: socialite_config?.linkedin_client_id || "",
        linkedin_client_secret: socialite_config?.linkedin_client_secret || "",
        linkedin_enabled: Boolean(socialite_config?.linkedin_enabled),
        linkedin_redirect_uri: socialite_config?.linkedin_redirect_uri || "",
    });

    const [copiedKey, setCopiedKey] = useState<string | null>(null);
    const callbackBase = typeof window === "undefined" ? "" : window.location.origin;

    const copyCallbackUrl = (providerKey: string, customUrl?: string) => {
        const urlToCopy = customUrl || `${callbackBase}/auth/${providerKey}/callback`;
        navigator.clipboard.writeText(urlToCopy);
        setCopiedKey(providerKey);
        toast.success(`Callback URL for ${providerKey} copied.`);
        setTimeout(() => setCopiedKey(null), 2000);
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="socialite"
            heading="Sign-in Providers"
            description="Configure OAuth 2.0 social authentication providers available on your sign-in screens."
        >
            <div className="space-y-6">
                {/* Save Bar Card */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-border/60 bg-card/65 p-4 shadow-xs backdrop-blur-xs">
                    <div>
                        <p className="text-sm font-semibold text-foreground">OAuth Identity Providers</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Credentials remain securely encrypted in application storage. Toggle on providers after credentials are verified.
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            submitSystemForm({
                                form: socialiteForm,
                                routeName: "administrators.system-management.socialite.update",
                                successMessage: "Sign-in providers updated successfully.",
                                errorMessage: "Failed to update sign-in provider credentials.",
                            })
                        }
                        disabled={socialiteForm.processing || !socialiteForm.isDirty}
                        className="h-9 gap-1.5 shrink-0 self-start sm:self-center"
                    >
                        {socialiteForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        <span>Save Provider Changes</span>
                    </Button>
                </div>

                {/* Providers Accordion Card */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="border-b border-border/40 pb-4">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-base font-semibold">Available Providers</CardTitle>
                            <Badge variant="outline" className="text-xs font-normal border-border/60">
                                {providerConfigs.length} Supported
                            </Badge>
                        </div>
                        <CardDescription className="text-xs">
                            Select a provider to configure Client ID, Client Secret, and Authorized Redirect Callback URIs.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="pt-4">
                        <Accordion type="multiple" className="space-y-2.5">
                            {providerConfigs.map((provider) => {
                                const Icon = provider.icon;
                                const isConfigured =
                                    String(socialiteForm.data[provider.idField]).trim() !== "" &&
                                    String(socialiteForm.data[provider.secretField]).trim() !== "";
                                const isEnabled = Boolean(socialiteForm.data[provider.enabledField]);
                                const callbackUrl =
                                    String(socialiteForm.data[provider.redirectField]) ||
                                    `${callbackBase}/auth/${provider.key}/callback`;

                                return (
                                    <AccordionItem
                                        key={provider.key}
                                        value={provider.key}
                                        className="rounded-xl border border-border/60 bg-background/50 px-4 transition-colors"
                                    >
                                        <AccordionTrigger className="py-3.5 hover:no-underline">
                                            <div className="flex min-w-0 items-center gap-3.5 text-left w-full pr-3">
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted/80 text-foreground">
                                                    <Icon className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-semibold text-sm text-foreground">
                                                            {provider.label}
                                                        </span>
                                                        {isEnabled && (
                                                            <span className="size-1.5 rounded-full bg-emerald-500 shrink-0" />
                                                        )}
                                                    </div>
                                                    <span className="block text-xs text-muted-foreground mt-0.5">
                                                        {isConfigured
                                                            ? isEnabled
                                                                ? "Active on sign-in page"
                                                                : "Credentials saved (inactive)"
                                                            : "Requires API credentials"}
                                                    </span>
                                                </div>

                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        isEnabled
                                                            ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-[11px]"
                                                            : isConfigured
                                                              ? "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-[11px]"
                                                              : "border-border/60 text-muted-foreground text-[11px]"
                                                    }
                                                >
                                                    {isEnabled ? "Enabled" : isConfigured ? "Ready" : "Unconfigured"}
                                                </Badge>
                                            </div>
                                        </AccordionTrigger>

                                        <AccordionContent className="pb-4 pt-1">
                                            <div className="grid gap-4 border-t border-border/40 pt-4 sm:grid-cols-2">
                                                <div className="space-y-1.5">
                                                    <Label htmlFor={`${provider.key}-client-id`} className="text-xs font-semibold">
                                                        {provider.idLabel}
                                                    </Label>
                                                    <Input
                                                        id={`${provider.key}-client-id`}
                                                        value={String(socialiteForm.data[provider.idField] || "")}
                                                        onChange={(event) =>
                                                            socialiteForm.setData(provider.idField, event.target.value)
                                                        }
                                                        placeholder={`Paste ${provider.idLabel}`}
                                                        className="font-mono text-xs"
                                                    />
                                                </div>

                                                <div className="space-y-1.5">
                                                    <Label htmlFor={`${provider.key}-client-secret`} className="text-xs font-semibold">
                                                        {provider.secretLabel}
                                                    </Label>
                                                    <Input
                                                        id={`${provider.key}-client-secret`}
                                                        type="password"
                                                        value={String(socialiteForm.data[provider.secretField] || "")}
                                                        onChange={(event) =>
                                                            socialiteForm.setData(provider.secretField, event.target.value)
                                                        }
                                                        placeholder="••••••••••••••••"
                                                        className="font-mono text-xs"
                                                    />
                                                </div>

                                                <div className="space-y-1.5 sm:col-span-2">
                                                    <div className="flex items-center justify-between">
                                                        <Label htmlFor={`${provider.key}-callback`} className="text-xs font-semibold">
                                                            Authorized Redirect URI
                                                        </Label>
                                                        <button
                                                            type="button"
                                                            onClick={() => copyCallbackUrl(provider.key, callbackUrl)}
                                                            className="flex items-center gap-1 text-[11px] text-primary hover:underline"
                                                        >
                                                            {copiedKey === provider.key ? (
                                                                <>
                                                                    <Check className="size-3 text-emerald-500" />
                                                                    <span>Copied</span>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Copy className="size-3" />
                                                                    <span>Copy URI</span>
                                                                </>
                                                            )}
                                                        </button>
                                                    </div>
                                                    <Input
                                                        id={`${provider.key}-callback`}
                                                        value={callbackUrl}
                                                        onChange={(event) =>
                                                            socialiteForm.setData(provider.redirectField, event.target.value)
                                                        }
                                                        className="font-mono text-xs bg-muted/25"
                                                    />
                                                </div>

                                                <div className="flex items-center justify-between rounded-lg border border-border/50 bg-muted/40 px-3.5 py-2.5 sm:col-span-2">
                                                    <div>
                                                        <Label
                                                            htmlFor={`${provider.key}-enabled`}
                                                            className="text-xs font-semibold cursor-pointer"
                                                        >
                                                            Display {provider.label} on login screen
                                                        </Label>
                                                        <p className="text-[11px] text-muted-foreground mt-0.5">
                                                            {isConfigured
                                                                ? "Students and faculty can authenticate using their accounts."
                                                                : "Both ID and Secret are required before activation."}
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        id={`${provider.key}-enabled`}
                                                        checked={isEnabled}
                                                        disabled={!isConfigured}
                                                        onCheckedChange={(checked) =>
                                                            socialiteForm.setData(provider.enabledField, checked)
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                );
                            })}
                        </Accordion>
                    </CardContent>
                </Card>
            </div>
        </SystemManagementLayout>
    );
}
