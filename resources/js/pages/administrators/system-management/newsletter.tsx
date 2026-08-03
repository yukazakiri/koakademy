import { testNewsletterConnection, updateNewsletter } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, CheckCircle2, KeyRound, Loader2, MailCheck, Newspaper, Save, Send, ShieldCheck } from "lucide-react";
import { FormEvent, useState } from "react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { NewsletterProviderName, SystemManagementPageProps } from "./types";

interface NewsletterFormData {
    enabled: boolean;
    provider: NewsletterProviderName;
    providers: {
        sequenzy: { api_key: string };
        brevo: { api_key: string; list_id: string };
        mailchimp: { api_key: string; server_prefix: string; audience_id: string };
    };
}

const providerDetails: Record<NewsletterProviderName, { label: string; description: string }> = {
    sequenzy: { label: "Sequenzy", description: "Connect KoAkademy directly to your Sequenzy subscriber workspace." },
    brevo: { label: "Brevo", description: "Create or update consented contacts in a specific Brevo list." },
    mailchimp: { label: "Mailchimp", description: "Add consented contacts idempotently to a Mailchimp audience." },
};

export default function NewsletterSettingsPage({ user, newsletter_config: config, access }: SystemManagementPageProps) {
    const [testing, setTesting] = useState(false);
    const canUpdate = access.sections.newsletter?.can_update ?? false;
    const form = useForm<NewsletterFormData>({
        enabled: config.enabled,
        provider: config.provider,
        providers: {
            sequenzy: { api_key: "" },
            brevo: { api_key: "", list_id: config.providers.brevo.list_id },
            mailchimp: {
                api_key: "",
                server_prefix: config.providers.mailchimp.server_prefix,
                audience_id: config.providers.mailchimp.audience_id,
            },
        },
    });

    const selected = providerDetails[form.data.provider];
    const configured = config.providers[form.data.provider].configured;

    const updateProvider = <T extends NewsletterProviderName>(provider: T, values: NewsletterFormData["providers"][T]) => {
        form.setData("providers", { ...form.data.providers, [provider]: values });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(updateNewsletter.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData("providers", {
                    sequenzy: { api_key: "" },
                    brevo: { ...form.data.providers.brevo, api_key: "" },
                    mailchimp: { ...form.data.providers.mailchimp, api_key: "" },
                });
                toast.success("Newsletter settings updated.");
            },
            onError: () => toast.error("Review the provider settings and try again."),
        });
    };

    const testConnection = async () => {
        setTesting(true);
        try {
            const response = await axios.post(testNewsletterConnection.url(), form.data);
            toast.success(response.data.message);
        } catch (error) {
            if (axios.isAxiosError(error)) {
                toast.error(error.response?.data?.message ?? "Connection test failed.");
            } else {
                toast.error("Connection test failed.");
            }
        } finally {
            setTesting(false);
        }
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="newsletter"
            heading="Newsletter"
            description="Configure consent-based marketing contacts separately from transactional email delivery."
        >
            <Alert>
                <ShieldCheck className="size-4" />
                <AlertTitle>Marketing contact integration</AlertTitle>
                <AlertDescription>
                    Newsletter subscriptions never use SMTP and do not change password resets, receipts, alerts, or other transactional messages.
                </AlertDescription>
            </Alert>

            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-5">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Newspaper className="size-5" />
                                Consent prompt
                            </CardTitle>
                            <CardDescription className="mt-1">
                                New installations start disabled. When enabled, eligible students and faculty may opt in from the portal.
                            </CardDescription>
                        </div>
                        <Switch
                            checked={form.data.enabled}
                            onCheckedChange={(enabled) => form.setData("enabled", enabled)}
                            disabled={!canUpdate}
                            aria-label="Enable newsletter consent prompt"
                        />
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <Badge variant={form.data.enabled ? "default" : "secondary"}>{form.data.enabled ? "Enabled" : "Disabled"}</Badge>
                            <span className="text-muted-foreground">
                                Disabling hides the prompt immediately and does not require provider connectivity.
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Marketing provider</CardTitle>
                        <CardDescription>Choose where future newsletter signups are stored. SMTP is intentionally not available.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="newsletter-provider">Provider</Label>
                            <Select
                                value={form.data.provider}
                                onValueChange={(provider) => form.setData("provider", provider as NewsletterProviderName)}
                                disabled={!canUpdate}
                            >
                                <SelectTrigger id="newsletter-provider" className="w-full sm:max-w-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(providerDetails).map(([value, details]) => (
                                        <SelectItem key={value} value={value}>
                                            {details.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-sm">{selected.description}</p>
                        </div>

                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Provider changes affect future signups only</AlertTitle>
                            <AlertDescription>
                                Existing local subscribed and declined records are retained. KoAkademy does not backfill or migrate contacts when the
                                provider changes.
                            </AlertDescription>
                        </Alert>

                        <div className="rounded-xl border p-5">
                            <div className="mb-5 flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="font-semibold">{selected.label} configuration</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Blank API key fields preserve the encrypted credential already saved.
                                    </p>
                                </div>
                                <Badge variant={configured ? "outline" : "secondary"} className="gap-1.5">
                                    {configured ? <CheckCircle2 className="size-3.5" /> : <KeyRound className="size-3.5" />}
                                    {configured ? "Configured" : "Not configured"}
                                </Badge>
                            </div>

                            {form.data.provider === "sequenzy" ? (
                                <SecretField
                                    id="sequenzy-api-key"
                                    label="API key"
                                    configured={config.providers.sequenzy.configured}
                                    value={form.data.providers.sequenzy.api_key}
                                    disabled={!canUpdate}
                                    onChange={(api_key) => updateProvider("sequenzy", { api_key })}
                                />
                            ) : null}

                            {form.data.provider === "brevo" ? (
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <SecretField
                                        id="brevo-api-key"
                                        label="API key"
                                        configured={config.providers.brevo.configured}
                                        value={form.data.providers.brevo.api_key}
                                        disabled={!canUpdate}
                                        onChange={(api_key) => updateProvider("brevo", { ...form.data.providers.brevo, api_key })}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="brevo-list-id">List ID</Label>
                                        <Input
                                            id="brevo-list-id"
                                            inputMode="numeric"
                                            value={form.data.providers.brevo.list_id}
                                            disabled={!canUpdate}
                                            onChange={(event) =>
                                                updateProvider("brevo", { ...form.data.providers.brevo, list_id: event.target.value })
                                            }
                                        />
                                    </div>
                                </div>
                            ) : null}

                            {form.data.provider === "mailchimp" ? (
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <SecretField
                                        id="mailchimp-api-key"
                                        label="API key"
                                        configured={config.providers.mailchimp.configured}
                                        value={form.data.providers.mailchimp.api_key}
                                        disabled={!canUpdate}
                                        onChange={(api_key) => updateProvider("mailchimp", { ...form.data.providers.mailchimp, api_key })}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="mailchimp-server-prefix">Server prefix</Label>
                                        <Input
                                            id="mailchimp-server-prefix"
                                            placeholder="us21"
                                            value={form.data.providers.mailchimp.server_prefix}
                                            disabled={!canUpdate}
                                            onChange={(event) =>
                                                updateProvider("mailchimp", { ...form.data.providers.mailchimp, server_prefix: event.target.value })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="mailchimp-audience-id">Audience ID</Label>
                                        <Input
                                            id="mailchimp-audience-id"
                                            value={form.data.providers.mailchimp.audience_id}
                                            disabled={!canUpdate}
                                            onChange={(event) =>
                                                updateProvider("mailchimp", { ...form.data.providers.mailchimp, audience_id: event.target.value })
                                            }
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        {form.errors.provider ? <p className="text-destructive text-sm">{form.errors.provider}</p> : null}

                        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button type="button" variant="outline" onClick={testConnection} disabled={!canUpdate || testing || form.processing}>
                                {testing ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                                Test connection
                            </Button>
                            <Button type="submit" disabled={!canUpdate || testing || form.processing}>
                                {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                Save newsletter settings
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <MailCheck className="size-4" />
                        Operational policy
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm sm:grid-cols-3">
                    <PolicyItem title="Credentials" description="Stored database-encrypted and never returned to the browser." />
                    <PolicyItem title="Provider outages" description="Do not create a successful local subscription record." />
                    <PolicyItem title="Provider switching" description="Clears lookup caches without historical synchronization." />
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}

function SecretField({
    id,
    label,
    configured,
    value,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    configured: boolean;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="password"
                autoComplete="new-password"
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
            <p className="text-muted-foreground text-xs">
                {configured ? "A credential is saved. Leave blank to keep it." : "No credential is saved yet."}
            </p>
        </div>
    );
}

function PolicyItem({ title, description }: { title: string; description: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="font-medium">{title}</p>
            <p className="text-muted-foreground mt-1 leading-5">{description}</p>
        </div>
    );
}
