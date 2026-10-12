import { cleanSystemLogs, updateLogRetention } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/reui/alert-dialog";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { router, useForm, usePoll } from "@inertiajs/react";
import { Activity, ChartNoAxesCombined, Clock, Loader2, Mail, RefreshCw, Save, ShieldAlert, Telescope, Trash2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import { LogRetentionPolicyFields, type LogRetentionPolicy } from "./log-retention-policy-fields";
import type { SystemManagementPageProps } from "./types";

type SourceKey = "pulse" | "telescope" | "mail" | "activity";
type CleanupMode = "prune" | "purge";

interface CleanupRun {
    id: number;
    source: SourceKey;
    mode: CleanupMode;
    status: "queued" | "running" | "completed" | "failed";
    scheduled: boolean;
    created_at: string;
    finished_at: string | null;
    deleted_records: number;
}

interface LogSource {
    key: SourceKey;
    collecting: boolean;
    available: boolean;
    error: string | null;
    records: number | null;
    eligible_records: number | null;
    protected_records: number | null;
    policy: LogRetentionPolicy;
    next_run_at: string | null;
    active_run: CleanupRun | null;
    last_run: CleanupRun | null;
}

interface RetentionOverview {
    timezone: string;
    history_available: boolean;
    sources: LogSource[];
    recent_runs: CleanupRun[];
}

interface Props extends SystemManagementPageProps {
    log_retention?: RetentionOverview;
}

const sourceDetails = {
    pulse: {
        label: "Pulse",
        icon: ChartNoAxesCombined,
        description: "Performance entries, values, and aggregate buckets.",
        note: "Pulse also trims storage using PULSE_STORAGE_KEEP. This schedule does not extend native retention or clear pending Redis ingest.",
    },
    telescope: {
        label: "Telescope",
        icon: Telescope,
        description: "Recorded requests, queries, jobs, and exceptions.",
        note: "Entry tags are removed with their entries. Monitored tags stay configured.",
    },
    mail: {
        label: "Mail logs",
        icon: Mail,
        description: "Logged messages, delivery events, and stored attachments.",
        note: "Active bounce, complaint, and unsubscribe records stay protected. Deleting local logs does not delete provider data.",
    },
    activity: {
        label: "Activity logs",
        icon: Activity,
        description: "Audit history across all schools on this instance.",
        note: "Cleanup audit records stay protected. Export required audit history before deletion.",
    },
};

const statusVariants = { queued: "primary-light", running: "primary-light", completed: "success-light", failed: "destructive-light" } as const;

function formatTime(value: string | null, timezone: string): string {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short", timeZone: timezone }).format(new Date(value))
        : "Not scheduled";
}

function CleanupConfirmation({ source, mode, disabled }: { source: LogSource; mode: CleanupMode; disabled: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ source: source.key, mode, confirmation: "" });
    const label = sourceDetails[source.key].label;
    const word = mode === "purge" ? "PURGE" : "PRUNE";

    function submit() {
        form.post(cleanSystemLogs.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset("confirmation");
                toast.success("Cleanup requested");
            },
            onError: () => toast.error("Cleanup not queued"),
        });
    }

    return (
        <AlertDialog
            open={open}
            onOpenChange={(next) => {
                if (!form.processing) {
                    setOpen(next);
                    form.reset("confirmation");
                    form.clearErrors();
                }
            }}
        >
            <AlertDialogTrigger render={<Button variant="outline" size="sm" disabled={disabled} />}>
                {mode === "purge" ? <Trash2 /> : <Clock />}
                {mode === "purge" ? "Purge all" : "Prune now"}
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia className="bg-destructive/10 text-destructive dark:bg-destructive/20">
                        <Trash2 />
                    </AlertDialogMedia>
                    <AlertDialogTitle>
                        {mode === "purge" ? "Purge" : "Prune"} {label}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {mode === "purge"
                            ? "All deletable records present when you confirm will be permanently removed."
                            : `Records older than the saved ${source.policy.retention_days}-day retention period will be permanently removed.`}{" "}
                        This affects all schools. This action cannot be undone. Protected records and newer data stay intact.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <p className="text-muted-foreground text-sm">{sourceDetails[source.key].note}</p>
                <Field data-invalid={Boolean(form.errors.confirmation)}>
                    <FieldLabel htmlFor={`${source.key}-${mode}-confirm`}>Type {word} to confirm</FieldLabel>
                    <Input
                        id={`${source.key}-${mode}-confirm`}
                        value={form.data.confirmation}
                        onChange={(event) => form.setData("confirmation", event.target.value)}
                        autoComplete="off"
                        aria-invalid={Boolean(form.errors.confirmation)}
                        disabled={form.processing}
                    />
                    <FieldError>{form.errors.confirmation}</FieldError>
                    <FieldError>{form.errors.source ?? form.errors.mode}</FieldError>
                </Field>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={form.processing}>Cancel</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" disabled={form.processing || form.data.confirmation !== word} onClick={submit}>
                        {form.processing && <Loader2 className="animate-spin" />}
                        {mode === "purge" ? "Purge logs" : "Prune logs"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function LogControls({ overview, canUpdate }: { overview: RetentionOverview; canUpdate: boolean }) {
    const [refreshing, setRefreshing] = useState(false);
    const savedPolicies = useMemo(
        () => Object.fromEntries(overview.sources.map((source) => [source.key, source.policy])) as Record<SourceKey, LogRetentionPolicy>,
        [overview.sources],
    );
    const form = useForm({ policies: savedPolicies });
    const hasActiveRuns = overview.sources.some((source) => source.active_run);
    const { start, stop } = usePoll(5000, { only: ["log_retention"] });

    useEffect(() => {
        if (hasActiveRuns) {
            start();
        } else {
            stop();
        }
    }, [hasActiveRuns, start, stop]);

    // Adopt server values after a save or a polling refresh, without discarding
    // edits the administrator has not submitted yet.
    const savedSignature = JSON.stringify(savedPolicies);
    useEffect(() => {
        if (!form.isDirty) {
            form.setData("policies", savedPolicies);
            form.setDefaults();
        }
    }, [savedSignature]);

    function refresh() {
        router.reload({ only: ["log_retention"], onStart: () => setRefreshing(true), onFinish: () => setRefreshing(false) });
    }

    function save(event: React.FormEvent) {
        event.preventDefault();
        form.put(updateLogRetention.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success("Schedules saved"),
            onError: () => toast.error("Schedules not saved"),
        });
    }

    const errors = form.errors as Record<string, string>;

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <Alert variant="warning" role="status">
                <ShieldAlert />
                <AlertTitle>Instance-wide deletion</AlertTitle>
                <AlertDescription>
                    Cleanup permanently deletes logs across all schools. Student, enrollment, and finance records are not removed. Back up required
                    audit history first.
                </AlertDescription>
            </Alert>
            {!overview.history_available && (
                <Alert variant="destructive">
                    <AlertTitle>Migration required</AlertTitle>
                    <AlertDescription>Run database migrations before requesting cleanup or saving schedules.</AlertDescription>
                </Alert>
            )}
            <form onSubmit={save} className="flex min-w-0 flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Schedule timezone: <span className="text-foreground font-medium">{overview.timezone}</span>
                    </p>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={refresh} disabled={refreshing}>
                            {refreshing ? <Loader2 className="animate-spin" /> : <RefreshCw />}Refresh
                        </Button>
                        {canUpdate && (
                            <Button type="submit" size="sm" disabled={!form.isDirty || form.processing || !overview.history_available}>
                                {form.processing ? <Loader2 className="animate-spin" /> : <Save />}Save schedules
                            </Button>
                        )}
                    </div>
                </div>
                <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                    {overview.sources.map((source) => {
                        const details = sourceDetails[source.key];
                        const Icon = details.icon;
                        const policy = form.data.policies[source.key];
                        const fieldErrors = Object.fromEntries(
                            ["enabled", "retention_days", "frequency", "time"].map((key) => [key, errors[`policies.${source.key}.${key}`]]),
                        );
                        const disabled = !canUpdate || form.processing;
                        return (
                            <Card size="sm" key={source.key}>
                                <CardHeader>
                                    <CardTitle className="flex flex-wrap items-center gap-2">
                                        <Icon className="size-4" />
                                        {details.label}
                                        <Badge variant={source.collecting ? "success-light" : "warning-light"}>
                                            {source.collecting ? "Recording" : "Recording off"}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>{details.description}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex min-w-0 flex-col gap-4">
                                    {source.error ? (
                                        <Alert variant="destructive">
                                            <AlertTitle>Storage unavailable</AlertTitle>
                                            <AlertDescription>{source.error}</AlertDescription>
                                            <AlertAction>
                                                <Button size="sm" variant="outline" onClick={refresh}>
                                                    Retry
                                                </Button>
                                            </AlertAction>
                                        </Alert>
                                    ) : !source.available ? (
                                        <Alert role="status">
                                            <AlertTitle>Storage not configured</AlertTitle>
                                            <AlertDescription>
                                                Check migrations and database storage configuration. Cleanup is unavailable.
                                            </AlertDescription>
                                        </Alert>
                                    ) : (
                                        <dl className="grid grid-cols-2 gap-4">
                                            <div className="flex flex-col gap-0.5">
                                                <dt className="text-muted-foreground text-xs">Stored records</dt>
                                                <dd className="text-xl font-semibold tabular-nums">{source.records?.toLocaleString()}</dd>
                                            </div>
                                            <div className="flex flex-col gap-0.5">
                                                <dt className="text-muted-foreground text-xs">Older than saved retention</dt>
                                                <dd className="text-xl font-semibold tabular-nums">{source.eligible_records?.toLocaleString()}</dd>
                                            </div>
                                        </dl>
                                    )}
                                    {source.available && source.records === 0 && (
                                        <p className="text-muted-foreground text-sm">No stored logs. Refresh after new activity.</p>
                                    )}
                                    {Boolean(source.protected_records) && (
                                        <p className="text-muted-foreground text-xs">
                                            {source.protected_records?.toLocaleString()} protected mail records stay intact.
                                        </p>
                                    )}
                                    <Separator />
                                    <LogRetentionPolicyFields
                                        source={source.key}
                                        policy={policy}
                                        errors={fieldErrors}
                                        disabled={disabled}
                                        onChange={(next) => form.setData("policies", { ...form.data.policies, [source.key]: next })}
                                    />
                                    <p className="text-muted-foreground text-xs">{details.note}</p>
                                    <p className="text-muted-foreground text-xs">
                                        Next saved run: {formatTime(source.next_run_at, overview.timezone)}
                                    </p>
                                    {source.active_run && <Badge variant="primary-light">Cleanup {source.active_run.status}</Badge>}
                                    {source.last_run?.status === "failed" && (
                                        <Alert variant="destructive">
                                            <AlertTitle>Cleanup failed</AlertTitle>
                                            <AlertDescription>
                                                Some records may already be deleted. Check worker logs, fix storage access, then retry.
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </CardContent>
                                <CardFooter className="flex-wrap justify-between gap-3">
                                    <span className="text-muted-foreground text-xs">
                                        {policy.enabled ? "Scheduled cleanup on" : "Manual cleanup only"}
                                        {form.isDirty ? " · Unsaved changes" : ""}
                                    </span>
                                    {canUpdate && (
                                        <div className="flex items-center gap-2">
                                            <CleanupConfirmation
                                                source={source}
                                                mode="prune"
                                                disabled={
                                                    Boolean(source.active_run) ||
                                                    !source.available ||
                                                    !overview.history_available ||
                                                    (source.eligible_records ?? 0) === 0
                                                }
                                            />
                                            <CleanupConfirmation
                                                source={source}
                                                mode="purge"
                                                disabled={
                                                    Boolean(source.active_run) ||
                                                    !source.available ||
                                                    !overview.history_available ||
                                                    (source.records ?? 0) === 0
                                                }
                                            />
                                        </div>
                                    )}
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>
            </form>
            <Card size="sm">
                <CardHeader>
                    <CardTitle>Cleanup history</CardTitle>
                    <CardDescription>Recent manual and scheduled runs. History survives log purges.</CardDescription>
                </CardHeader>
                <CardContent>
                    {overview.recent_runs.length === 0 ? (
                        <div className="flex flex-col items-start gap-3">
                            <p className="text-muted-foreground text-sm">No cleanup runs yet. Save a schedule or request cleanup above.</p>
                            <Button size="sm" variant="outline" onClick={refresh}>
                                Refresh history
                            </Button>
                        </div>
                    ) : (
                        <ul className="flex flex-col gap-4">
                            {overview.recent_runs.map((run) => (
                                <li
                                    key={run.id}
                                    className="border-border flex flex-wrap items-center justify-between gap-3 border-b pb-3 last:border-0 last:pb-0"
                                >
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-sm font-medium">
                                            {sourceDetails[run.source].label} / {run.mode === "purge" ? "Purge" : "Prune"}
                                        </span>
                                        <span className="text-muted-foreground text-xs">
                                            {run.scheduled ? "Scheduled" : "Manual"} · {formatTime(run.created_at, overview.timezone)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-muted-foreground text-xs tabular-nums">
                                            {run.deleted_records.toLocaleString()} deleted
                                        </span>
                                        <Badge variant={statusVariants[run.status]}>{run.status}</Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
            <p className="text-muted-foreground text-xs">
                Schedules require the Laravel scheduler and a queue worker. Recording remains enabled after cleanup; dashboards may receive fresh data
                immediately.
            </p>
        </div>
    );
}

export default function LogRetention({ user, access, log_retention }: Props) {
    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="log_retention"
            heading="Logs & Retention"
            description="Manage diagnostic history and automatic cleanup."
            allowReadOnlyInteractions
        >
            {log_retention ? (
                <LogControls overview={log_retention} canUpdate={access.sections.log_retention?.can_update ?? false} />
            ) : (
                <div className="grid gap-6 xl:grid-cols-2" aria-label="Loading log controls">
                    {Array.from({ length: 4 }, (_, index) => (
                        <Card size="sm" key={index}>
                            <CardHeader>
                                <Skeleton className="h-5 w-28" />
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <Skeleton className="h-8 w-full" />
                                <Skeleton className="h-24 w-full" />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </SystemManagementLayout>
    );
}
