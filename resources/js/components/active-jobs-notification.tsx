import {
    index as activeJobsIndex,
    cancel as cancelActiveJob,
    dismiss as dismissActiveJob,
    retry as retryActiveJob,
    show as showActiveJob,
} from "@/actions/App/Http/Controllers/Api/ActiveJobsController";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, Download, FileText, RefreshCw, X } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

interface ActiveJob {
    id: string;
    user_id: number;
    type: string;
    title: string;
    status: "pending" | "processing" | "cancelling" | "completed" | "failed" | "cancelled";
    stage: string;
    percentage: number;
    message: string;
    counts: {
        total: number;
        processed: number;
        completed: number;
        skipped: number;
        failed: number;
        merged_parts: number;
        total_parts: number;
    };
    metadata: {
        report_url?: string | null;
        filters?: Record<string, unknown>;
    };
    error?: {
        code?: string | null;
        summary: string;
        context?: Record<string, unknown> | null;
        correlation_id: string;
    } | null;
    failed_items?: Array<{
        id: number;
        enrollment_id: number;
        sequence: number;
        attempts: number;
        code?: string | null;
        message?: string | null;
        context?: Record<string, unknown> | null;
    }>;
    actions: {
        can_cancel: boolean;
        can_retry: boolean;
        can_dismiss: boolean;
        can_download: boolean;
    };
    download_url?: string | null;
    created_at: string;
    updated_at: string;
    completed_at?: string | null;
    failed_at?: string | null;
}

interface ActiveJobsResponse {
    jobs: ActiveJob[];
    count: number;
    has_active: boolean;
}

const ACTIVE_POLL_INTERVAL = 2000;
const IDLE_POLL_INTERVAL = 30000;
export const ACTIVE_JOBS_REFRESH_EVENT = "active-jobs:refresh";

const activeToasts = new Map<string, string>();
const completedJobs = new Set<string>();
const lastJobState = new Map<string, string>();

function getJobStateKey(job: ActiveJob): string {
    return `${job.status}|${job.stage}|${job.percentage}|${job.counts.processed}|${job.counts.merged_parts}|${job.message}`;
}

function ActionButton({ children, onClick, destructive = false }: { children: React.ReactNode; onClick: () => void; destructive?: boolean }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors",
                destructive ? "bg-destructive/10 text-destructive hover:bg-destructive/20" : "bg-primary/10 text-primary hover:bg-primary/20",
            )}
        >
            {children}
        </button>
    );
}

function JobProgressContent({ job, isExpanded, onCancel }: { job: ActiveJob; isExpanded: boolean; onCancel: () => void }) {
    return (
        <div className={cn("flex w-full flex-col gap-2 transition-all duration-200", isExpanded ? "min-w-[340px]" : "min-w-[280px]")}>
            <div className="flex items-center gap-2">
                <FileText className="text-primary size-4 shrink-0" />
                <span className="text-sm font-medium">{job.title}</span>
                <span className="bg-muted text-muted-foreground ml-auto rounded px-1.5 py-0.5 text-[10px] uppercase">
                    {job.stage.replace(/_/g, " ")}
                </span>
            </div>
            <div className="flex items-center gap-2">
                <Progress value={job.percentage} className="h-2 flex-1" />
                <span className="text-muted-foreground text-xs tabular-nums">{job.percentage}%</span>
            </div>
            {job.counts.total > 0 && (
                <div className="text-muted-foreground flex flex-wrap gap-x-3 text-xs tabular-nums">
                    <span>
                        {job.counts.completed} of {job.counts.total} rendered
                    </span>
                    {job.counts.skipped > 0 && <span>{job.counts.skipped} skipped</span>}
                    {job.counts.failed > 0 && <span className="text-destructive">{job.counts.failed} failed</span>}
                    {job.counts.total_parts > 0 && (
                        <span>
                            {job.counts.merged_parts} of {job.counts.total_parts} parts
                        </span>
                    )}
                </div>
            )}
            <p className="text-muted-foreground text-xs">{job.message}</p>
            {isExpanded && (
                <div className="border-border/50 bg-muted/30 text-muted-foreground rounded-md border p-2 text-xs">
                    <div>Reference: {job.id}</div>
                    <div>Updated: {new Date(job.updated_at).toLocaleTimeString()}</div>
                </div>
            )}
            {job.actions.can_cancel && (
                <div>
                    <ActionButton onClick={onCancel} destructive>
                        <X className="size-3" /> Cancel
                    </ActionButton>
                </div>
            )}
        </div>
    );
}

function ExpandableJobContent({ job, onCancel }: { job: ActiveJob; onCancel: () => void }) {
    const [isExpanded, setIsExpanded] = React.useState(false);
    return (
        <div onMouseEnter={() => setIsExpanded(true)} onMouseLeave={() => setIsExpanded(false)}>
            <JobProgressContent job={job} isExpanded={isExpanded} onCancel={onCancel} />
        </div>
    );
}

function JobCompletedContent({ job, onDismiss }: { job: ActiveJob; onDismiss: () => void }) {
    return (
        <div className="flex w-full min-w-[280px] flex-col gap-2">
            <div className="flex items-center gap-2">
                <FileText className="size-4 text-green-500" />
                <span className="text-sm font-medium">{job.title}</span>
            </div>
            <p className="text-muted-foreground text-xs">{job.message}</p>
            <div className="flex flex-wrap gap-2">
                {job.download_url && (
                    <a
                        href={job.download_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-primary text-primary-foreground inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium"
                    >
                        <Download className="size-3" /> Download PDF
                    </a>
                )}
                {job.metadata.report_url && (
                    <a
                        href={job.metadata.report_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-destructive/10 text-destructive inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium"
                    >
                        <FileText className="size-3" /> Skipped report
                    </a>
                )}
                {job.actions.can_dismiss && <ActionButton onClick={onDismiss}>Dismiss</ActionButton>}
            </div>
        </div>
    );
}

function JobFailedContent({
    job,
    onRetry,
    onDetails,
    onDismiss,
}: {
    job: ActiveJob;
    onRetry: () => void;
    onDetails: () => void;
    onDismiss: () => void;
}) {
    return (
        <div className="flex w-full min-w-[300px] flex-col gap-2">
            <div className="flex items-center gap-2">
                <AlertTriangle className="text-destructive size-4" />
                <span className="text-sm font-medium">{job.status === "cancelled" ? "Export cancelled" : job.title}</span>
            </div>
            <p className="text-muted-foreground text-xs">{job.error?.summary ?? job.message}</p>
            <p className="text-muted-foreground font-mono text-[10px]">Reference: {job.id}</p>
            <div className="flex gap-2">
                {job.actions.can_retry && (
                    <ActionButton onClick={onRetry}>
                        <RefreshCw className="size-3" /> Retry failed
                    </ActionButton>
                )}
                <ActionButton onClick={onDetails}>
                    <FileText className="size-3" /> View details
                </ActionButton>
                {job.actions.can_dismiss && <ActionButton onClick={onDismiss}>Dismiss</ActionButton>}
            </div>
        </div>
    );
}

function FailureDetails({ job }: { job: ActiveJob }) {
    return (
        <div className="max-w-[440px] space-y-2 text-xs">
            <p>{job.error?.summary ?? job.message}</p>
            <p className="font-mono">Export: {job.id}</p>
            {job.error?.context && (
                <div className="border-border rounded border p-2">
                    <div>
                        Stage: {String(job.error.context.stage ?? job.stage)} ·{" "}
                        {String(job.error.context.exception_class ?? job.error.code ?? "failure")}
                    </div>
                    {job.error.context.file && (
                        <div>
                            {String(job.error.context.file)}:{String(job.error.context.line ?? "?")}
                        </div>
                    )}
                    {job.error.context.occurred_at && <div>{new Date(String(job.error.context.occurred_at)).toLocaleString()}</div>}
                </div>
            )}
            {job.failed_items?.map((item) => (
                <div key={item.id} className="border-border rounded border p-2">
                    <div>
                        Enrollment #{item.enrollment_id} · item {item.sequence} · {item.attempts} attempt(s)
                    </div>
                    <div className="text-destructive">
                        {item.code}: {item.message}
                    </div>
                    {item.context?.exception_class && (
                        <div>
                            {String(item.context.exception_class)} at {String(item.context.file)}:{String(item.context.line)}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

export function ActiveJobsNotification() {
    const page = usePage();
    const user = (page.props.auth as { user?: User } | undefined)?.user ?? (page.props as { user?: User }).user;
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = React.useRef(true);
    const inFlightRef = React.useRef(false);
    const hasActiveRef = React.useRef(false);
    const realtimeConnectedRef = React.useRef(false);

    const retryJob = React.useCallback(async (job: ActiveJob) => {
        completedJobs.delete(job.id);
        const response = await axios.post<{ job: ActiveJob }>(retryActiveJob.url(job.id));
        toast.success("Assessment export retry queued.");
        window.dispatchEvent(new Event(ACTIVE_JOBS_REFRESH_EVENT));
        return response.data.job;
    }, []);

    const cancelJob = React.useCallback(async (job: ActiveJob) => {
        await axios.post(cancelActiveJob.url(job.id));
        toast.info("Cancelling assessment export...");
        window.dispatchEvent(new Event(ACTIVE_JOBS_REFRESH_EVENT));
    }, []);

    const showDetails = React.useCallback(async (job: ActiveJob) => {
        const response = await axios.get<{ job: ActiveJob }>(showActiveJob.url(job.id));
        toast.info(<FailureDetails job={response.data.job} />, { duration: 30000 });
    }, []);

    const dismissJob = React.useCallback(async (job: ActiveJob) => {
        await axios.delete(dismissActiveJob.url(job.id));
        toast.dismiss(activeToasts.get(job.id) ?? `job-${job.id}`);
        activeToasts.delete(job.id);
        completedJobs.add(job.id);
        window.dispatchEvent(new Event(ACTIVE_JOBS_REFRESH_EVENT));
    }, []);

    React.useEffect(() => {
        const exportId = new URLSearchParams(window.location.search).get("assessment_export");
        if (!exportId) return;

        void axios
            .get<{ job: ActiveJob }>(showActiveJob.url(exportId))
            .then((response) => {
                toast.info(<FailureDetails job={response.data.job} />, { duration: 30000 });
            })
            .catch(() => {
                toast.error("This assessment export is unavailable or has expired.");
            })
            .finally(() => {
                const url = new URL(window.location.href);
                url.searchParams.delete("assessment_export");
                window.history.replaceState({}, "", url);
            });
    }, []);

    const applyJob = React.useCallback(
        (job: ActiveJob) => {
            const existingToastId = activeToasts.get(job.id);
            const stateKey = getJobStateKey(job);
            const changed = stateKey !== lastJobState.get(job.id);
            const active = ["pending", "processing", "cancelling"].includes(job.status);
            hasActiveRef.current = active || hasActiveRef.current;

            if (active) {
                completedJobs.delete(job.id);
                if (!changed && existingToastId) return;
                lastJobState.set(job.id, stateKey);
                const id = toast.loading(<ExpandableJobContent job={job} onCancel={() => void cancelJob(job)} />, {
                    id: existingToastId ?? `job-${job.id}`,
                    duration: Infinity,
                });
                activeToasts.set(job.id, String(id));
                return;
            }

            if (completedJobs.has(job.id)) return;
            completedJobs.add(job.id);
            lastJobState.delete(job.id);
            const id = existingToastId ?? `job-${job.id}`;
            if (job.status === "completed") {
                toast.success(<JobCompletedContent job={job} onDismiss={() => void dismissJob(job)} />, { id, duration: 20000 });
            } else {
                toast.error(
                    <JobFailedContent
                        job={job}
                        onRetry={() => void retryJob(job)}
                        onDetails={() => void showDetails(job)}
                        onDismiss={() => void dismissJob(job)}
                    />,
                    {
                        id,
                        duration: 30000,
                    },
                );
            }
            activeToasts.delete(job.id);
        },
        [cancelJob, dismissJob, retryJob, showDetails],
    );

    const fetchAndUpdateJobs = React.useCallback(async () => {
        if (!mountedRef.current || inFlightRef.current) return;
        inFlightRef.current = true;
        try {
            const response = await axios.get<ActiveJobsResponse>(activeJobsIndex.url());
            hasActiveRef.current = response.data.has_active;
            response.data.jobs.forEach(applyJob);
            const currentIds = new Set(response.data.jobs.map((job) => job.id));
            for (const [jobId, toastId] of activeToasts.entries()) {
                if (!currentIds.has(jobId)) {
                    toast.dismiss(toastId);
                    activeToasts.delete(jobId);
                }
            }
        } catch (error) {
            console.error("Error fetching active jobs:", error);
        } finally {
            inFlightRef.current = false;
        }
    }, [applyJob]);

    React.useEffect(() => {
        if (!user?.id || !window.Echo) {
            realtimeConnectedRef.current = false;
            return;
        }

        const connection = window.Echo.connector.pusher.connection;
        const refreshAfterConnectionChange = () => window.dispatchEvent(new Event(ACTIVE_JOBS_REFRESH_EVENT));
        const markConnected = () => {
            realtimeConnectedRef.current = true;
            refreshAfterConnectionChange();
        };
        const markDisconnected = () => {
            realtimeConnectedRef.current = false;
            refreshAfterConnectionChange();
        };
        realtimeConnectedRef.current = connection.state === "connected";
        connection.bind("connected", markConnected);
        connection.bind("disconnected", markDisconnected);
        connection.bind("unavailable", markDisconnected);
        connection.bind("failed", markDisconnected);

        const channel = window.Echo.private(`App.Models.User.${user.id}`);
        channel.listen(".assessment-export.progress", (event: { export: ActiveJob }) => applyJob(event.export));
        return () => {
            channel.stopListening(".assessment-export.progress");
            connection.unbind("connected", markConnected);
            connection.unbind("disconnected", markDisconnected);
            connection.unbind("unavailable", markDisconnected);
            connection.unbind("failed", markDisconnected);
            realtimeConnectedRef.current = false;
        };
    }, [applyJob, user?.id]);

    React.useEffect(() => {
        mountedRef.current = true;
        const clearPending = () => {
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        };
        const schedule = (delay: number) => {
            clearPending();
            if (mountedRef.current) timeoutRef.current = setTimeout(tick, delay);
        };
        const tick = async () => {
            if (!mountedRef.current || document.visibilityState === "hidden") return;
            await fetchAndUpdateJobs();
            if (!realtimeConnectedRef.current) {
                schedule(hasActiveRef.current ? ACTIVE_POLL_INTERVAL : IDLE_POLL_INTERVAL);
            }
        };
        const refresh = () => (document.visibilityState === "visible" ? schedule(0) : clearPending());
        document.addEventListener("visibilitychange", refresh);
        window.addEventListener(ACTIVE_JOBS_REFRESH_EVENT, refresh);
        schedule(0);
        return () => {
            mountedRef.current = false;
            clearPending();
            document.removeEventListener("visibilitychange", refresh);
            window.removeEventListener(ACTIVE_JOBS_REFRESH_EVENT, refresh);
        };
    }, [fetchAndUpdateJobs]);

    return null;
}
