import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { SiFacebook, SiGithub, SiGoogle, SiX } from "@icons-pack/react-simple-icons";
import { useForm } from "@inertiajs/react";
import { Info, Link2, Loader2, Save } from "lucide-react";
import type { ComponentType } from "react";

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
        label: "Google",
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
        label: "Facebook",
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
        label: "LinkedIn",
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

    const callbackBase = typeof window === "undefined" ? "" : window.location.origin;

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="socialite"
            heading="Sign-in Providers"
            description="Configure OAuth providers available on your institution's sign-in screen."
        >
            <div className="space-y-5">
                <div className="border-border/70 bg-card flex flex-col gap-4 rounded-2xl border px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-foreground text-sm font-semibold">Choose the sign-in methods people can actually use.</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Provider credentials stay collapsed until you need to configure or review them.
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            submitSystemForm({
                                form: socialiteForm,
                                routeName: "administrators.system-management.socialite.update",
                                successMessage: "Social authentication settings updated successfully.",
                                errorMessage: "Failed to update social authentication settings.",
                            })
                        }
                        disabled={socialiteForm.processing || !socialiteForm.isDirty}
                        className="shrink-0"
                    >
                        {socialiteForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        {socialiteForm.processing ? "Saving" : "Save changes"}
                    </Button>
                </div>

                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>Sign-in providers</CardTitle>
                        <CardDescription>
                            Open only the provider you want to configure. A provider cannot be enabled until both credentials are present.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <div className="border-primary/20 bg-primary/5 text-primary mt-5 rounded-xl border px-4 py-3">
                            <div className="flex items-start gap-3 text-sm">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                <div>
                                    <p className="font-medium">Callback address</p>
                                    <code className="bg-background/80 text-foreground mt-1 block w-fit rounded-md px-2 py-1 text-xs">
                                        {callbackBase}/auth/{"{provider}"}/callback
                                    </code>
                                </div>
                            </div>
                        </div>

                        <Accordion type="multiple" className="mt-4 space-y-2">
                            {providerConfigs.map((provider) => {
                                const Icon = provider.icon;
                                const isConfigured =
                                    String(socialiteForm.data[provider.idField]).trim() !== "" &&
                                    String(socialiteForm.data[provider.secretField]).trim() !== "";
                                const isEnabled = Boolean(socialiteForm.data[provider.enabledField]);

                                return (
                                    <AccordionItem
                                        key={provider.key}
                                        value={provider.key}
                                        className="border-border/70 rounded-xl border px-4 last:border-b"
                                    >
                                        <AccordionTrigger className="py-4 hover:no-underline">
                                            <div className="flex min-w-0 items-center gap-3 text-left">
                                                <span className="bg-muted text-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
                                                    <Icon className="size-4" />
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="text-foreground block font-medium">{provider.label}</span>
                                                    <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                                                        {isConfigured
                                                            ? isEnabled
                                                                ? "Configured and enabled"
                                                                : "Configured but not enabled"
                                                            : "Needs credentials"}
                                                    </span>
                                                </span>
                                                <Badge variant={isConfigured ? "outline" : "secondary"} className="mr-2 ml-auto shrink-0">
                                                    {isConfigured ? (isEnabled ? "Enabled" : "Ready") : "Not set up"}
                                                </Badge>
                                            </div>
                                        </AccordionTrigger>
                                        <AccordionContent className="pb-4">
                                            <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor={`${provider.key}-client-id`}>{provider.idLabel}</Label>
                                                    <Input
                                                        id={`${provider.key}-client-id`}
                                                        value={String(socialiteForm.data[provider.idField] || "")}
                                                        onChange={(event) => socialiteForm.setData(provider.idField, event.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`${provider.key}-client-secret`}>{provider.secretLabel}</Label>
                                                    <Input
                                                        id={`${provider.key}-client-secret`}
                                                        type="password"
                                                        value={String(socialiteForm.data[provider.secretField] || "")}
                                                        onChange={(event) => socialiteForm.setData(provider.secretField, event.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2 sm:col-span-2">
                                                    <Label htmlFor={`${provider.key}-callback`}>Login callback URL</Label>
                                                    <Input
                                                        id={`${provider.key}-callback`}
                                                        value={String(
                                                            socialiteForm.data[provider.redirectField] ||
                                                                `${callbackBase}/auth/${provider.key}/callback`,
                                                        )}
                                                        onChange={(event) => socialiteForm.setData(provider.redirectField, event.target.value)}
                                                    />
                                                </div>
                                                <div className="bg-muted/50 flex items-center justify-between rounded-lg px-3 py-2.5 sm:col-span-2">
                                                    <div>
                                                        <Label htmlFor={`${provider.key}-enabled`} className="font-medium">
                                                            Offer {provider.label} sign-in
                                                        </Label>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            {isConfigured
                                                                ? "Available to users after you save."
                                                                : "Add both credentials before enabling this option."}
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        id={`${provider.key}-enabled`}
                                                        checked={isEnabled}
                                                        disabled={!isConfigured}
                                                        onCheckedChange={(checked) => socialiteForm.setData(provider.enabledField, checked)}
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
