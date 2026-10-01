import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import type { AnalyticsFieldDefinition, AnalyticsProviderDefinition, AnalyticsSettingValue } from "@/types/analytics";
import { useForm } from "@inertiajs/react";
import { AlertTriangle, BarChart3, ExternalLink, Loader2, Plus, Save, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface ProviderRow {
    /** Null for a row the operator has added but not yet saved. */
    id: number | null;
    provider: string;
    label: string;
    enabled: boolean;
    settings: Record<string, AnalyticsSettingValue>;
    script: string;
}

interface AnalyticsFormData {
    analytics_enabled: boolean;
    providers: ProviderRow[];
}

export default function SystemManagementAnalyticsPage({
    user,
    general_settings,
    access,
    analytics,
    analytics_catalog,
    analytics_providers,
}: SystemManagementPageProps) {
    const catalog = (analytics_catalog ?? []) as AnalyticsProviderDefinition[];
    const providersByKey = useMemo(() => new Map(catalog.map((provider) => [provider.key, provider])), [catalog]);
    const [addingProvider, setAddingProvider] = useState<string>("");

    const analyticsForm = useForm<AnalyticsFormData>({
        analytics_enabled: analytics?.enabled ?? general_settings?.analytics_enabled ?? false,
        providers: ((analytics_providers ?? []) as ProviderRow[]).map((row) => ({
            id: row.id ?? null,
            provider: row.provider,
            label: row.label ?? "",
            enabled: row.enabled ?? false,
            settings: row.settings ?? {},
            script: row.script ?? "",
        })),
    });

    const rows = analyticsForm.data.providers;
    const activeCount = rows.filter((row) => row.enabled).length;

    // Every catalog provider stays selectable. Several instances of the same
    // provider are supported (a separate Umami site per domain, for example),
    // so already-added keys are annotated with a count rather than hidden.
    const instanceCounts = rows.reduce<Record<string, number>>((counts, row) => {
        counts[row.provider] = (counts[row.provider] ?? 0) + 1;

        return counts;
    }, {});

    function addProvider(key: string): void {
        if (!key) {
            return;
        }

        const definition = providersByKey.get(key);

        if (!definition) {
            return;
        }

        const defaults: Record<string, AnalyticsSettingValue> = {};

        definition.fields.forEach((field) => {
            defaults[field.key] = (field.default ?? (field.type === "toggle" ? false : "")) as AnalyticsSettingValue;
        });

        analyticsForm.setData("providers", [
            ...rows,
            {
                id: null,
                provider: key,
                label: "",
                enabled: true,
                settings: defaults,
                script: "",
            },
        ]);

        setAddingProvider("");
    }

    function updateRow(index: number, patch: Partial<ProviderRow>): void {
        analyticsForm.setData(
            "providers",
            rows.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)),
        );
    }

    function updateRowSetting(index: number, field: string, value: AnalyticsSettingValue): void {
        const row = rows[index];

        if (!row) {
            return;
        }

        updateRow(index, { settings: { ...row.settings, [field]: value } });
    }

    function removeRow(index: number): void {
        analyticsForm.setData(
            "providers",
            rows.filter((_, rowIndex) => rowIndex !== index),
        );
    }

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="analytics"
            heading="Analytics & Tracking"
            description="Add as many analytics providers as you need. Each one is enabled and configured independently, and nothing is injected until you turn it on."
        >
            <div className="space-y-6">
                <div className="bg-card/70 flex flex-col gap-4 rounded-2xl border p-4 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 text-primary rounded-xl p-2.5">
                            <BarChart3 className="h-5 w-5" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-medium">
                                    {activeCount === 0
                                        ? "No active providers"
                                        : `${activeCount} active ${activeCount === 1 ? "provider" : "providers"}`}
                                </p>
                                <Badge variant={analyticsForm.data.analytics_enabled ? "default" : "secondary"}>
                                    {analyticsForm.data.analytics_enabled ? "Tracking on" : "Tracking off"}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">Applies to Inertia pages and the Filament admin panel.</p>
                        </div>
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
                            Global switch
                        </CardTitle>
                        <CardDescription>Master toggle. When off, every provider below is skipped.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="bg-background flex min-h-11 items-center justify-between rounded-lg border px-3">
                            <div className="space-y-0.5">
                                <p className="text-sm font-medium">Inject tracking scripts</p>
                                <p className="text-muted-foreground text-xs">Individual providers still have their own switches.</p>
                            </div>
                            <Switch
                                checked={analyticsForm.data.analytics_enabled}
                                onCheckedChange={(checked) => analyticsForm.setData("analytics_enabled", checked)}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="h-4 w-4" />
                            Add a provider
                        </CardTitle>
                        <CardDescription>
                            {catalog.length} providers available. Self-hosted options keep your data on your own infrastructure. Add a provider more
                            than once when you need separate instances, such as one site per domain.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <Select value={addingProvider} onValueChange={setAddingProvider}>
                                <SelectTrigger className="bg-background">
                                    <SelectValue placeholder="Choose a provider to add" />
                                </SelectTrigger>
                                <SelectContent>
                                    {catalog.map((provider) => {
                                        const count = instanceCounts[provider.key] ?? 0;

                                        return (
                                            <SelectItem key={provider.key} value={provider.key}>
                                                {provider.label}
                                                {provider.self_hosted ? " · self-hosted" : ""}
                                                {count > 0 ? ` · ${count} configured` : ""}
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                            <Button type="button" variant="outline" onClick={() => addProvider(addingProvider)} disabled={!addingProvider}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>

                        {rows.length > 0 ? (
                            <div className="space-y-2">
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Configured</p>
                                <div className="flex flex-wrap gap-1.5">
                                    {Object.entries(instanceCounts).map(([key, count]) => (
                                        <Badge key={key} variant="outline" className="font-normal">
                                            {providersByKey.get(key)?.label ?? key}
                                            {count > 1 ? ` × ${count}` : ""}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                {rows.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No analytics providers configured. Add one above, or leave this empty to send no analytics at all.
                        </CardContent>
                    </Card>
                ) : null}

                {rows.map((row, index) => {
                    const definition = providersByKey.get(row.provider);

                    // Number duplicates by their position among instances of the
                    // same provider, so the name is stable across renders.
                    const sameProviderIndex = rows.slice(0, index + 1).filter((candidate) => candidate.provider === row.provider).length;
                    const totalOfProvider = rows.filter((candidate) => candidate.provider === row.provider).length;
                    const fallbackLabel =
                        totalOfProvider > 1
                            ? `${definition?.label ?? row.provider} ${sameProviderIndex} of ${totalOfProvider}`
                            : (definition?.label ?? row.provider);

                    return (
                        <ProviderCard
                            key={row.id ?? `new-${row.provider}-${index}`}
                            definition={definition}
                            row={row}
                            index={index}
                            fallbackLabel={fallbackLabel}
                            onToggle={(enabled) => updateRow(index, { enabled })}
                            onLabelChange={(label) => updateRow(index, { label })}
                            onScriptChange={(script) => updateRow(index, { script })}
                            onSettingChange={(field, value) => updateRowSetting(index, field, value)}
                            onRemove={() => removeRow(index)}
                        />
                    );
                })}
            </div>
        </SystemManagementLayout>
    );
}

interface ProviderCardProps {
    definition: AnalyticsProviderDefinition | undefined;
    row: ProviderRow;
    index: number;
    fallbackLabel: string;
    onToggle: (enabled: boolean) => void;
    onLabelChange: (label: string) => void;
    onScriptChange: (script: string) => void;
    onSettingChange: (field: string, value: AnalyticsSettingValue) => void;
    onRemove: () => void;
}

function ProviderCard({
    definition,
    row,
    index,
    fallbackLabel,
    onToggle,
    onLabelChange,
    onScriptChange,
    onSettingChange,
    onRemove,
}: ProviderCardProps) {
    const [showScript, setShowScript] = useState(false);

    const label = row.label.trim() || fallbackLabel;

    return (
        <Card className={row.enabled ? "" : "opacity-70"}>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 flex-1 space-y-1">
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            {label}
                            <Badge variant={definition?.self_hosted ? "outline" : "secondary"}>
                                {definition?.self_hosted ? "Self-hosted" : "Hosted"}
                            </Badge>
                            {row.id === null && <Badge variant="secondary">New</Badge>}
                        </CardTitle>
                        {definition?.description ? <CardDescription>{definition.description}</CardDescription> : null}
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch checked={row.enabled} onCheckedChange={onToggle} aria-label={`Enable ${label}`} />
                        <Button type="button" variant="ghost" size="icon" onClick={onRemove} aria-label={`Remove ${label}`}>
                            <Trash2 className="text-destructive h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                {definition?.consent_note ? (
                    <div className="flex items-start gap-2.5 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p className="text-muted-foreground text-xs leading-relaxed">{definition.consent_note}</p>
                    </div>
                ) : null}

                <div className="space-y-2.5">
                    <Label htmlFor={`label-${row.id ?? index}`} className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                        Instance name
                    </Label>
                    <Input
                        id={`label-${row.id ?? index}`}
                        value={row.label}
                        onChange={(event) => onLabelChange(event.target.value)}
                        className="bg-background"
                        placeholder={definition?.label ?? "Provider name"}
                    />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    {(definition?.fields ?? []).map((field) => (
                        <ProviderField
                            key={field.key}
                            field={field}
                            value={row.settings[field.key] ?? defaultValueFor(field)}
                            onChange={(value) => onSettingChange(field.key, value)}
                        />
                    ))}
                </div>

                {definition?.docs_url ? (
                    <a
                        href={definition.docs_url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="text-muted-foreground inline-flex items-center gap-1.5 text-xs hover:underline"
                    >
                        <ExternalLink className="h-3 w-3" />
                        {definition.label} documentation
                    </a>
                ) : null}

                <div className="space-y-2.5">
                    <Button type="button" variant="ghost" size="sm" className="px-0" onClick={() => setShowScript((open) => !open)}>
                        {showScript ? "Hide" : "Show"} custom snippet override
                    </Button>
                    {showScript ? (
                        <>
                            <Textarea
                                rows={6}
                                value={row.script}
                                onChange={(event) => onScriptChange(event.target.value)}
                                className="bg-background resize-y font-mono text-xs"
                                placeholder="Leave empty to use the generated snippet above."
                            />
                            <p className="text-muted-foreground text-[11px] leading-tight">
                                When set, this replaces the generated snippet for this instance only.
                            </p>
                        </>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

interface ProviderFieldProps {
    field: AnalyticsFieldDefinition;
    value: AnalyticsSettingValue;
    onChange: (value: AnalyticsSettingValue) => void;
}

function ProviderField({ field, value, onChange }: ProviderFieldProps) {
    if (field.type === "toggle") {
        return (
            <div className="bg-background flex items-center justify-between rounded-lg border px-3 py-2.5 sm:col-span-1">
                <div className="min-w-0 pr-3">
                    <p className="text-sm font-medium">{field.label}</p>
                    {field.help ? <p className="text-muted-foreground text-xs leading-snug">{field.help}</p> : null}
                </div>
                <Switch checked={value === true} onCheckedChange={onChange} />
            </div>
        );
    }

    if (field.type === "select") {
        return (
            <div className="space-y-2.5">
                <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">{field.label}</Label>
                <Select value={String(value ?? "")} onValueChange={onChange}>
                    <SelectTrigger className="bg-background">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {field.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {field.help ? <p className="text-muted-foreground text-xs leading-snug">{field.help}</p> : null}
            </div>
        );
    }

    if (field.type === "textarea") {
        return (
            <div className="space-y-2.5 sm:col-span-2">
                <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">{field.label}</Label>
                <Textarea
                    rows={6}
                    value={String(value ?? "")}
                    onChange={(event) => onChange(event.target.value)}
                    className="bg-background resize-y font-mono text-xs"
                    placeholder={field.placeholder}
                />
                {field.help ? <p className="text-muted-foreground text-xs leading-snug">{field.help}</p> : null}
            </div>
        );
    }

    return (
        <div className="space-y-2.5">
            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">{field.label}</Label>
            <Input
                type={field.type === "url" ? "url" : "text"}
                value={String(value ?? "")}
                onChange={(event) => onChange(event.target.value)}
                className="bg-background"
                placeholder={field.placeholder}
            />
            {field.help ? <p className="text-muted-foreground text-xs leading-snug">{field.help}</p> : null}
        </div>
    );
}

function defaultValueFor(field: AnalyticsFieldDefinition): AnalyticsSettingValue {
    if (field.default !== null && field.default !== undefined) {
        return field.default;
    }

    return field.type === "toggle" ? false : "";
}
