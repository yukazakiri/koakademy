import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { useForm } from "@inertiajs/react";
import axios from "axios";
import { ExternalLink, FlaskConical, Globe, Loader2, PackageCheck, PackageX, Save, Send, Server, Siren, Terminal } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type {
    ErrorReportingProviderKey,
    ErrorReportingProviderMeta,
    SimpleErrorReportingProviderConfig,
    SystemManagementPageProps,
} from "./types";

interface SentryFormData {
    enabled: boolean;
    dsn: string;
    environment: string;
    release: string;
    sample_rate: number;
    traces_sample_rate: number;
    profiles_sample_rate: number | null;
    send_default_pii: boolean;
    enable_logs: boolean;
    frontend_enabled: boolean;
    frontend_dsn: string;
    frontend_script: string;
    frontend_traces_sample_rate: number;
    frontend_replays_session_sample_rate: number;
    frontend_replays_on_error_sample_rate: number;
}

interface ErrorReportingFormData {
    providers: {
        sentry: SentryFormData;
        flare: SimpleErrorReportingProviderConfig;
        bugsnag: SimpleErrorReportingProviderConfig;
        honeybadger: SimpleErrorReportingProviderConfig;
    };
}

const ENVIRONMENT_OPTIONS = ["production", "staging", "local", "testing", "demo"];

function RateInput({
    id,
    label,
    description,
    value,
    onChange,
}: {
    id: string;
    label: string;
    description: string;
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <div className="space-y-2.5">
            <Label htmlFor={id} className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                {label}
            </Label>
            <Input
                id={id}
                type="number"
                min={0}
                max={1}
                step={0.05}
                value={value}
                onChange={(event) => {
                    const parsed = Number(event.target.value);
                    onChange(Number.isFinite(parsed) ? Math.min(1, Math.max(0, parsed)) : 0);
                }}
                className="bg-background"
            />
            <p className="text-muted-foreground text-[11px] leading-tight">{description}</p>
        </div>
    );
}

function ToggleRow({
    label,
    description,
    checked,
    onCheckedChange,
}: {
    label: string;
    description: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="bg-background flex min-h-11 items-center justify-between gap-3 rounded-lg border px-3 py-2.5">
            <div className="space-y-0.5">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-muted-foreground text-xs">{description}</p>
            </div>
            <Switch checked={checked} onCheckedChange={onCheckedChange} />
        </div>
    );
}

function EnvironmentSelect({
    id,
    value,
    onChange,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} className="bg-background">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {ENVIRONMENT_OPTIONS.map((option) => (
                    <SelectItem key={option} value={option}>
                        {option}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function SimpleProviderCard({
    meta,
    data,
    onChange,
    testing,
    onTest,
}: {
    meta: ErrorReportingProviderMeta;
    data: SimpleErrorReportingProviderConfig;
    onChange: (patch: Partial<SimpleErrorReportingProviderConfig>) => void;
    testing: boolean;
    onTest: () => void;
}) {
    const keyConfigured = data.api_key.trim() !== "";

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            {meta.installed ? <PackageCheck className="h-4 w-4" /> : <PackageX className="h-4 w-4" />}
                            {meta.label}
                        </CardTitle>
                        <CardDescription className="mt-1">{meta.description}</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={data.enabled && keyConfigured ? "default" : "secondary"}>
                            {data.enabled && keyConfigured ? "On" : "Off"}
                        </Badge>
                        <Badge variant={meta.installed ? "default" : "outline"}>
                            {meta.installed ? "SDK installed" : "SDK missing"}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                {!meta.installed && (
                    <Alert>
                        <Terminal className="size-4" />
                        <AlertTitle>Install the SDK to activate {meta.label}</AlertTitle>
                        <AlertDescription className="space-y-2">
                            <p>
                                Credentials are stored safely, but events are only delivered once the package is installed. Run
                                this on the server, then redeploy:
                            </p>
                            <code className="bg-background block rounded-lg border px-3 py-2 font-mono text-xs">
                                {meta.install_command}
                            </code>
                            <a
                                href={meta.docs_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
                            >
                                Provider documentation <ExternalLink className="size-3" />
                            </a>
                        </AlertDescription>
                    </Alert>
                )}

                <ToggleRow
                    label={`Enable ${meta.label} reporting`}
                    description={meta.installed ? "Captured events are forwarded to this provider." : "Takes effect once the SDK is installed."}
                    checked={data.enabled}
                    onCheckedChange={(checked) => onChange({ enabled: checked })}
                />

                <div className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2.5">
                        <Label htmlFor={`${meta.key}_api_key`} className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                            API key
                        </Label>
                        <Input
                            id={`${meta.key}_api_key`}
                            type="password"
                            autoComplete="new-password"
                            value={data.api_key}
                            onChange={(event) => onChange({ api_key: event.target.value })}
                            className="bg-background font-mono text-xs"
                            placeholder={`${meta.label} project API key`}
                        />
                    </div>
                    <div className="space-y-2.5">
                        <Label htmlFor={`${meta.key}_environment`} className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                            Environment
                        </Label>
                        <EnvironmentSelect id={`${meta.key}_environment`} value={data.environment} onChange={(value) => onChange({ environment: value })} />
                    </div>
                </div>

                <div className="space-y-2.5">
                    <Label htmlFor={`${meta.key}_release`} className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                        Release (optional)
                    </Label>
                    <Input
                        id={`${meta.key}_release`}
                        value={data.release}
                        onChange={(event) => onChange({ release: event.target.value })}
                        className="bg-background font-mono text-xs"
                        placeholder="e.g. koakademy@1.4.0"
                    />
                </div>

                <div>
                    <Button variant="outline" size="sm" onClick={onTest} disabled={testing || !data.enabled || !keyConfigured}>
                        {testing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Send className="mr-2 h-4 w-4" />}
                        Send test event to {meta.label}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

const SIMPLE_PROVIDERS: ErrorReportingProviderKey[] = ["flare", "bugsnag", "honeybadger"];

const defaultSentry = (sentry: SystemManagementPageProps["sentry"]): SentryFormData => ({
    enabled: sentry?.enabled ?? false,
    dsn: sentry?.dsn ?? "",
    environment: sentry?.environment ?? "production",
    release: sentry?.release ?? "",
    sample_rate: sentry?.sample_rate ?? 1,
    traces_sample_rate: sentry?.traces_sample_rate ?? 0.2,
    profiles_sample_rate: sentry?.profiles_sample_rate ?? null,
    send_default_pii: sentry?.send_default_pii ?? false,
    enable_logs: sentry?.enable_logs ?? false,
    frontend_enabled: sentry?.frontend_enabled ?? false,
    frontend_dsn: sentry?.frontend_dsn ?? "",
    frontend_script: sentry?.frontend_script ?? "",
    frontend_traces_sample_rate: sentry?.frontend_traces_sample_rate ?? 0.1,
    frontend_replays_session_sample_rate: sentry?.frontend_replays_session_sample_rate ?? 0,
    frontend_replays_on_error_sample_rate: sentry?.frontend_replays_on_error_sample_rate ?? 1,
});

const defaultSimple = (row?: SimpleErrorReportingProviderConfig | null): SimpleErrorReportingProviderConfig => ({
    enabled: row?.enabled ?? false,
    api_key: row?.api_key ?? "",
    environment: row?.environment ?? "production",
    release: row?.release ?? "",
});

export default function SystemManagementObservabilityPage({ user, sentry, error_reporting, access }: SystemManagementPageProps) {
    const providers = error_reporting?.providers;
    const meta = error_reporting?.meta;
    const [testingProvider, setTestingProvider] = useState<ErrorReportingProviderKey | null>(null);
    const form = useForm<ErrorReportingFormData>({
        providers: {
            sentry: defaultSentry(sentry ?? providers?.sentry),
            flare: defaultSimple(providers?.flare),
            bugsnag: defaultSimple(providers?.bugsnag),
            honeybadger: defaultSimple(providers?.honeybadger),
        },
    });

    const sentryData = form.data.providers.sentry;
    const setSentry = (patch: Partial<SentryFormData>) =>
        form.setData("providers", { ...form.data.providers, sentry: { ...sentryData, ...patch } });
    const setSimple = (key: ErrorReportingProviderKey, patch: Partial<SimpleErrorReportingProviderConfig>) =>
        form.setData("providers", { ...form.data.providers, [key]: { ...form.data.providers[key], ...patch } });

    const dsnConfigured = sentryData.dsn.trim() !== "";
    const enabledCount = (Object.keys(form.data.providers) as ErrorReportingProviderKey[]).filter(
        (key) => form.data.providers[key].enabled,
    ).length;
    const canUpdate = access.sections.observability?.can_update ?? false;

    const sendTestEvent = async (provider: ErrorReportingProviderKey) => {
        setTestingProvider(provider);
        try {
            const response = await axios.post(route("administrators.system-management.observability.test"), {
                provider,
                providers: form.data.providers,
            });
            toast.success(response.data.message ?? "Test event sent.");
        } catch (error) {
            if (axios.isAxiosError(error)) {
                toast.error(error.response?.data?.message ?? "Test event failed.");
            } else {
                toast.error("Test event failed.");
            }
        } finally {
            setTestingProvider(null);
        }
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="observability"
            heading="Error Reporting"
            description="Configure one or more error reporting providers. Each enabled provider receives captured events independently."
        >
            <div className="bg-card/70 flex flex-col gap-4 rounded-2xl border p-4 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary rounded-xl p-2.5">
                        <Siren className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-medium">Providers</p>
                            <Badge variant={enabledCount > 0 ? "default" : "secondary"}>
                                {enabledCount > 0 ? `${enabledCount} enabled` : "All off"}
                            </Badge>
                            {sentryData.frontend_enabled && <Badge variant="outline">Browser tracking</Badge>}
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Sentry SDK ships with the app; Flare, Bugsnag, and Honeybadger activate once their packages are
                            installed.
                        </p>
                    </div>
                </div>
                <Button
                    onClick={() =>
                        submitSystemForm({
                            form,
                            routeName: "administrators.system-management.observability.update",
                            successMessage: "Error reporting settings updated successfully.",
                            errorMessage: "Failed to update error reporting settings.",
                        })
                    }
                    disabled={form.processing || !form.isDirty}
                    className="w-full shrink-0 sm:w-auto"
                >
                    {form.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                    Save Configuration
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Server className="h-4 w-4" />
                                Sentry (Backend)
                            </CardTitle>
                            <CardDescription>Server-side exception capture, tracing, and log forwarding.</CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant={sentryData.enabled && dsnConfigured ? "default" : "secondary"}>
                                {sentryData.enabled && dsnConfigured ? "Reporting on" : "Reporting off"}
                            </Badge>
                            {!dsnConfigured && <Badge variant="outline">No DSN</Badge>}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <ToggleRow
                            label="Enable error reporting"
                            description="Capture unhandled exceptions and send them to Sentry."
                            checked={sentryData.enabled}
                            onCheckedChange={(checked) => setSentry({ enabled: checked })}
                        />
                        <ToggleRow
                            label="Send PII"
                            description="Attach user IDs and request data to events."
                            checked={sentryData.send_default_pii}
                            onCheckedChange={(checked) => setSentry({ send_default_pii: checked })}
                        />
                    </div>

                    <div className="space-y-2.5">
                        <Label htmlFor="sentry_dsn" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                            DSN
                        </Label>
                        <Input
                            id="sentry_dsn"
                            value={sentryData.dsn}
                            onChange={(event) => setSentry({ dsn: event.target.value })}
                            className="bg-background font-mono text-xs"
                            placeholder="https://<key>@o<org>.ingest.sentry.io/<project>"
                            inputMode="url"
                        />
                        {form.errors["providers.sentry.dsn"] && (
                            <p className="text-destructive text-xs">{form.errors["providers.sentry.dsn"]}</p>
                        )}
                        <p className="text-muted-foreground text-[11px] leading-tight">
                            Find this under your Sentry project settings. Leave empty to fall back to{" "}
                            <span className="font-mono">SENTRY_LARAVEL_DSN</span>.
                        </p>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2.5">
                            <Label htmlFor="sentry_environment" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Environment
                            </Label>
                            <EnvironmentSelect id="sentry_environment" value={sentryData.environment} onChange={(value) => setSentry({ environment: value })} />
                        </div>
                        <div className="space-y-2.5">
                            <Label htmlFor="sentry_release" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Release (optional)
                            </Label>
                            <Input
                                id="sentry_release"
                                value={sentryData.release}
                                onChange={(event) => setSentry({ release: event.target.value })}
                                className="bg-background font-mono text-xs"
                                placeholder="e.g. koakademy@1.4.0"
                            />
                        </div>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-3">
                        <RateInput
                            id="sentry_sample_rate"
                            label="Error sample rate"
                            description="Fraction of error events to send (1 = all)."
                            value={sentryData.sample_rate}
                            onChange={(value) => setSentry({ sample_rate: value })}
                        />
                        <RateInput
                            id="sentry_traces_sample_rate"
                            label="Traces sample rate"
                            description="Fraction of requests traced for performance."
                            value={sentryData.traces_sample_rate}
                            onChange={(value) => setSentry({ traces_sample_rate: value })}
                        />
                        <div className="space-y-2.5">
                            <Label
                                htmlFor="sentry_profiles_sample_rate"
                                className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                            >
                                Profiles sample rate
                            </Label>
                            <Input
                                id="sentry_profiles_sample_rate"
                                type="number"
                                min={0}
                                max={1}
                                step={0.05}
                                value={sentryData.profiles_sample_rate ?? ""}
                                onChange={(event) => {
                                    const raw = event.target.value;
                                    setSentry({ profiles_sample_rate: raw === "" ? null : Math.min(1, Math.max(0, Number(raw))) });
                                }}
                                className="bg-background"
                                placeholder="Disabled"
                            />
                            <p className="text-muted-foreground text-[11px] leading-tight">Leave empty to disable profiling.</p>
                        </div>
                    </div>

                    <ToggleRow
                        label="Forward logs"
                        description="Send application logs to Sentry as log events."
                        checked={sentryData.enable_logs}
                        onCheckedChange={(checked) => setSentry({ enable_logs: checked })}
                    />

                    <div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => sendTestEvent("sentry")}
                            disabled={testingProvider !== null || !sentryData.enabled || !dsnConfigured}
                        >
                            {testingProvider === "sentry" ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="mr-2 h-4 w-4" />
                            )}
                            Send test event to Sentry
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Globe className="h-4 w-4" />
                        Sentry (Browser)
                    </CardTitle>
                    <CardDescription>
                        Inject the Sentry browser loader into every page. No JavaScript dependency to install.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <ToggleRow
                        label="Enable browser tracking"
                        description="Capture uncaught frontend errors and slow page loads."
                        checked={sentryData.frontend_enabled}
                        onCheckedChange={(checked) => setSentry({ frontend_enabled: checked })}
                    />

                    <div className="space-y-2.5">
                        <Label htmlFor="sentry_frontend_dsn" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                            Frontend DSN
                        </Label>
                        <Input
                            id="sentry_frontend_dsn"
                            value={sentryData.frontend_dsn}
                            onChange={(event) => setSentry({ frontend_dsn: event.target.value })}
                            className="bg-background font-mono text-xs"
                            placeholder="Defaults to the backend DSN"
                            inputMode="url"
                        />
                        {form.errors["providers.sentry.frontend_dsn"] && (
                            <p className="text-destructive text-xs">{form.errors["providers.sentry.frontend_dsn"]}</p>
                        )}
                        <p className="text-muted-foreground text-[11px] leading-tight">
                            Use a separate Sentry project when you want frontend and backend issues triaged independently.
                        </p>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-3">
                        <RateInput
                            id="sentry_frontend_traces"
                            label="Traces sample rate"
                            description="Fraction of page loads traced."
                            value={sentryData.frontend_traces_sample_rate}
                            onChange={(value) => setSentry({ frontend_traces_sample_rate: value })}
                        />
                        <RateInput
                            id="sentry_frontend_replays_session"
                            label="Session replays"
                            description="Fraction of sessions recorded."
                            value={sentryData.frontend_replays_session_sample_rate}
                            onChange={(value) => setSentry({ frontend_replays_session_sample_rate: value })}
                        />
                        <RateInput
                            id="sentry_frontend_replays_error"
                            label="Replays on error"
                            description="Fraction of error sessions recorded."
                            value={sentryData.frontend_replays_on_error_sample_rate}
                            onChange={(value) => setSentry({ frontend_replays_on_error_sample_rate: value })}
                        />
                    </div>

                    <Accordion
                        type="single"
                        collapsible
                        defaultValue={sentryData.frontend_script.trim() !== "" ? "manual-override" : undefined}
                        className="rounded-xl border px-4"
                    >
                        <AccordionItem value="manual-override" className="border-0">
                            <AccordionTrigger className="py-4 text-left hover:no-underline">
                                <span>
                                    <span className="block font-medium">Manual browser snippet override</span>
                                    <span className="text-muted-foreground block text-sm font-normal">
                                        Paste your Sentry loader snippet when the generated configuration is not enough.
                                    </span>
                                </span>
                            </AccordionTrigger>
                            <AccordionContent className="pb-4">
                                <Textarea
                                    id="sentry_frontend_script"
                                    rows={7}
                                    value={sentryData.frontend_script}
                                    onChange={(event) => setSentry({ frontend_script: event.target.value })}
                                    className="bg-background resize-y font-mono text-xs"
                                    placeholder={`<script src="https://js.sentry-cdn.com/<loader-key>.min.js" crossorigin="anonymous"></script>`}
                                />
                                <p className="text-muted-foreground mt-2 text-[11px] leading-tight">
                                    When filled, the application injects this snippet exactly and ignores the generated browser
                                    configuration above.
                                </p>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </CardContent>
            </Card>

            {SIMPLE_PROVIDERS.map((key) =>
                meta?.[key] ? (
                    <SimpleProviderCard
                        key={key}
                        meta={meta[key]}
                        data={form.data.providers[key]}
                        onChange={(patch) => setSimple(key, patch)}
                        testing={testingProvider === key}
                        onTest={() => sendTestEvent(key)}
                    />
                ) : null,
            )}

            <Alert>
                <FlaskConical className="size-4" />
                <AlertTitle>Verify before relying on it</AlertTitle>
                <AlertDescription>
                    Save the configuration, then use each provider&apos;s “Send test event” button and confirm the message
                    appears in that provider&apos;s project stream.
                    {canUpdate ? "" : " Your role has read-only access to this section."}
                </AlertDescription>
            </Alert>
        </SystemManagementLayout>
    );
}
