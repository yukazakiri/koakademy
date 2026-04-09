import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { Download, FileText } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

interface ActiveJob {
    id: string;
    user_id: number;
    type: string;
    title: string;
    status: "pending" | "processing" | "completed" | "failed";
    percentage: number;
    message: string;
    metadata: {
        processed_count?: number;
        total_count?: number;
        filters?: Record<string, unknown>;
        report_url?: string;
    };
    download_url?: string;
    created_at: string;
    updated_at: string;
    completed_at?: string;
    failed_at?: string;
}

interface ActiveJobsResponse {
    jobs: ActiveJob[];
    count: number;
    has_active: boolean;
}

const POLL_INTERVAL = 2000; // 2 seconds for responsive updates

// Track which jobs have active toasts to prevent duplicates
const activeToasts = new Map<string, string>();

// Track which jobs have been completed/failed to prevent re-showing
const completedJobs = new Set<string>();

// Track last known job state to avoid redundant toast updates
const lastJobState = new Map<string, string>();

function getJobStateKey(job: ActiveJob): string {
    return `${job.status}|${job.percentage}|${job.message}`;
}

function JobProgressContent({ job, isExpanded }: { job: ActiveJob; isExpanded: boolean }) {
    const processedCount = job.metadata?.processed_count ?? 0;
    const totalCount = job.metadata?.total_count ?? 0;
    const hasStudentCounts = totalCount > 0;

    return (
        <div className={cn("flex w-full flex-col gap-2 transition-all duration-200", isExpanded ? "min-w-[320px]" : "min-w-[260px]")}>
            <div className="flex items-center gap-2">
                <FileText className="text-primary size-4 shrink-0" />
                <span className="text-sm font-medium">{job.title}</span>
            </div>

            <div className="flex items-center gap-2">
                <Progress value={job.percentage} className="h-2 flex-1" />
                <span className="text-muted-foreground text-xs tabular-nums">{job.percentage}%</span>
            </div>

            {hasStudentCounts && (
                <div className="text-muted-foreground flex items-center justify-between text-xs">
                    <span>
                        {processedCount} of {totalCount} students
                    </span>
                    {isExpanded && totalCount > 0 && (
                        <span className="tabular-nums">~{Math.ceil(((totalCount - processedCount) * 2) / 60)} min remaining</span>
                    )}
                </div>
            )}

            <p className="text-muted-foreground text-xs">{job.message}</p>

            {isExpanded && (
                <div className="border-border/50 bg-muted/30 text-muted-foreground mt-1 rounded-md border p-2 text-xs">
                    <div className="font-medium">Details:</div>
                    <div className="mt-1 space-y-0.5">
                        <div>Job ID: {job.id.substring(0, 20)}...</div>
                        <div>Started: {new Date(job.created_at).toLocaleTimeString()}</div>
                        {job.type && <div>Type: {job.type.replace(/_/g, " ")}</div>}
                    </div>
                </div>
            )}
        </div>
    );
}

function JobCompletedContent({ job }: { job: ActiveJob }) {
    const totalCount = job.metadata?.total_count ?? 0;
    const reportUrl = job.metadata?.report_url;

    return (
        <div className="flex w-full min-w-[260px] flex-col gap-2">
            <div className="flex items-center gap-2">
                <FileText className="size-4 shrink-0 text-green-500" />
                <span className="text-sm font-medium">{job.title}</span>
            </div>
            <p className="text-muted-foreground text-xs">{job.message}</p>
            {totalCount > 0 && <p className="text-xs text-green-600 dark:text-green-400">Successfully generated {totalCount} assessments</p>}
            <div className="mt-1 flex flex-wrap gap-2">
                {job.download_url && (
                    <a
                        href={job.download_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                    >
                        <Download className="size-3" />
                        Download PDF
                    </a>
                )}
                {reportUrl && (
                    <a
                        href={reportUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-destructive/10 text-destructive hover:bg-destructive/20 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                    >
                        <FileText className="size-3" />
                        View Skipped Report
                    </a>
                )}
            </div>
        </div>
    );
}

function JobFailedContent({ job }: { job: ActiveJob }) {
    return (
        <div className="flex w-full min-w-[260px] flex-col gap-1">
            <div className="flex items-center gap-2">
                <FileText className="text-destructive size-4 shrink-0" />
                <span className="text-sm font-medium">{job.title}</span>
            </div>
            <p className="text-muted-foreground text-xs">{job.message}</p>
        </div>
    );
}

// Wrapper component that handles hover state
function ExpandableJobContent({ job }: { job: ActiveJob }) {
    const [isExpanded, setIsExpanded] = React.useState(false);

    return (
        <div onMouseEnter={() => setIsExpanded(true)} onMouseLeave={() => setIsExpanded(false)}>
            <JobProgressContent job={job} isExpanded={isExpanded} />
        </div>
    );
}

export function ActiveJobsNotification() {
    const pollRef = React.useRef<ReturnType<typeof setInterval> | null>(null);
    const mountedRef = React.useRef(true);

    const getCsrfToken = React.useCallback(() => {
        return (
            document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
            document.cookie
                .split("; ")
                .find((row) => row.startsWith("XSRF-TOKEN="))
                ?.split("=")[1]
        );
    }, []);

    const fetchAndUpdateJobs = React.useCallback(async () => {
        if (!mountedRef.current) return;

        try {
            const csrfToken = getCsrfToken();
            const response = await fetch("/api/jobs", {
                credentials: "include",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    ...(csrfToken ? { "X-XSRF-TOKEN": decodeURIComponent(csrfToken) } : {}),
                },
            });

            if (!response.ok) {
                return;
            }

            const data: ActiveJobsResponse = await response.json();

            // Process each job
            for (const job of data.jobs) {
                const existingToastId = activeToasts.get(job.id);
                const currentStateKey = getJobStateKey(job);
                const previousStateKey = lastJobState.get(job.id);
                const hasStateChanged = currentStateKey !== previousStateKey;

                if (job.status === "pending" || job.status === "processing") {
                    if (existingToastId) {
                        // Only update the toast when something changed to avoid Sonner's
                        // internal setRef triggering an infinite React state update loop.
                        if (hasStateChanged) {
                            lastJobState.set(job.id, currentStateKey);
                            toast.loading(<ExpandableJobContent job={job} />, {
                                id: existingToastId,
                                duration: Infinity,
                            });
                        }
                    } else if (!completedJobs.has(job.id)) {
                        // Create new loading toast
                        lastJobState.set(job.id, currentStateKey);
                        const toastId = toast.loading(<ExpandableJobContent job={job} />, {
                            duration: Infinity,
                            id: `job-${job.id}`,
                        });
                        activeToasts.set(job.id, String(toastId));
                    }
                } else if (job.status === "completed") {
                    // Mark as completed and update toast
                    if (!completedJobs.has(job.id)) {
                        completedJobs.add(job.id);
                        lastJobState.delete(job.id);

                        if (existingToastId) {
                            toast.success(<JobCompletedContent job={job} />, {
                                id: existingToastId,
                                duration: 15000, // Keep for 15 seconds so user can click download
                            });
                        } else {
                            toast.success(<JobCompletedContent job={job} />, {
                                id: `job-${job.id}`,
                                duration: 15000,
                            });
                        }
                        activeToasts.delete(job.id);
                    }
                } else if (job.status === "failed") {
                    // Mark as failed and update toast
                    if (!completedJobs.has(job.id)) {
                        completedJobs.add(job.id);
                        lastJobState.delete(job.id);

                        if (existingToastId) {
                            toast.error(<JobFailedContent job={job} />, {
                                id: existingToastId,
                                duration: 10000,
                            });
                        } else {
                            toast.error(<JobFailedContent job={job} />, {
                                id: `job-${job.id}`,
                                duration: 10000,
                            });
                        }
                        activeToasts.delete(job.id);
                    }
                }
            }

            // Clean up toasts for jobs that no longer exist
            const currentJobIds = new Set(data.jobs.map((j) => j.id));
            for (const [jobId, toastId] of activeToasts.entries()) {
                if (!currentJobIds.has(jobId)) {
                    toast.dismiss(toastId);
                    activeToasts.delete(jobId);
                }
            }

            // Clean up old completed job IDs (keep last 50)
            if (completedJobs.size > 50) {
                const iterator = completedJobs.values();
                for (let i = 0; i < completedJobs.size - 50; i++) {
                    const value = iterator.next().value;
                    if (value) {
                        completedJobs.delete(value);
                    }
                }
            }
        } catch (error) {
            console.error("Error fetching active jobs:", error);
        }
    }, [getCsrfToken]);

    // Start polling on mount
    React.useEffect(() => {
        mountedRef.current = true;

        // Initial fetch
        fetchAndUpdateJobs();

        // Set up polling
        pollRef.current = setInterval(fetchAndUpdateJobs, POLL_INTERVAL);

        return () => {
            mountedRef.current = false;
            if (pollRef.current) {
                clearInterval(pollRef.current);
            }
        };
    }, [fetchAndUpdateJobs]);

    // This component doesn't render anything visible - it just manages toasts
    return null;
}
