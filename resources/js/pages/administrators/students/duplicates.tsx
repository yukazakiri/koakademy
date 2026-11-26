import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AdminLink } from "@/lib/admin-navigation";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    ExternalLink,
    GitMerge,
    Loader2,
    Mail,
    RefreshCw,
    Search,
    ShieldAlert,
    UserCheck,
    Users,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

interface DuplicateStudentCard {
    id: number;
    student_id: string | null;
    full_name: string;
    email: string | null;
    birth_date: string | null;
    status: string;
    enrollments_count: number;
    class_enrollments_count: number;
    portal_linked: boolean;
    created_at: string | null;
}

interface DuplicateCluster {
    id: string;
    match_type: "email" | "name_dob" | "both";
    match_label: string;
    match_value: string;
    students: DuplicateStudentCard[];
}

interface TransferPreviewPayload {
    mode: string;
    source_student: {
        id: number;
        student_id: string | null;
        name: string;
        email: string | null;
        status: string;
    };
    target_student: {
        id: number;
        student_id: string | null;
        name: string;
        email: string | null;
        status: string;
    };
    records_to_transfer: {
        enrollments_count: number;
        subject_enrollments_count: number;
        class_enrollments_count: number;
        tuition_ledgers_count: number;
        transactions_count: number;
        clearances_count: number;
        attendance_records_count: number;
    };
    warnings: string[];
    safe_to_proceed: boolean;
}

interface DuplicatesPageProps {
    user: User;
    clusters: DuplicateCluster[];
    stats: {
        total_clusters: number;
        email_clusters: number;
        identity_clusters: number;
        affected_students: number;
    };
    flash?: {
        success?: string;
        error?: string;
    } | null;
}

type ClusterFilter = "all" | "email" | "name_dob";

interface ClusterSelection {
    sourceId: number | null;
    targetId: number | null;
}

export default function AdministratorStudentDuplicates({ user, clusters, stats, flash }: DuplicatesPageProps) {
    const [search, setSearch] = useState("");
    const [typeFilter, setTypeFilter] = useState<ClusterFilter>("all");
    const [selections, setSelections] = useState<Record<string, ClusterSelection>>(() =>
        Object.fromEntries(
            clusters.map((cluster) => [
                cluster.id,
                {
                    sourceId: cluster.students[1]?.id ?? null,
                    targetId: cluster.students[0]?.id ?? null,
                },
            ]),
        ),
    );
    const [previews, setPreviews] = useState<Record<string, TransferPreviewPayload>>({});
    const [loadingPreviewId, setLoadingPreviewId] = useState<string | null>(null);
    const [archiveSource, setArchiveSource] = useState(true);
    const [confirmClusterId, setConfirmClusterId] = useState<string | null>(null);
    const [merging, setMerging] = useState(false);

    useEffect(() => {
        setSelections((previous) => {
            const next: Record<string, ClusterSelection> = {};
            for (const cluster of clusters) {
                const existing = previous[cluster.id];
                const studentIds = new Set(cluster.students.map((s) => s.id));
                const validSource = existing?.sourceId && studentIds.has(existing.sourceId) ? existing.sourceId : (cluster.students[1]?.id ?? null);
                const validTarget = existing?.targetId && studentIds.has(existing.targetId) ? existing.targetId : (cluster.students[0]?.id ?? null);
                next[cluster.id] = {
                    sourceId: validSource === validTarget ? (cluster.students.find((s) => s.id !== validTarget)?.id ?? null) : validSource,
                    targetId: validTarget,
                };
            }
            return next;
        });
    }, [clusters]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const filteredClusters = useMemo(() => {
        const query = search.trim().toLowerCase();
        return clusters.filter((cluster) => {
            if (typeFilter === "email" && cluster.match_type === "name_dob") return false;
            if (typeFilter === "name_dob" && cluster.match_type === "email") return false;
            if (!query) return true;
            if (cluster.match_value.toLowerCase().includes(query)) return true;
            return cluster.students.some(
                (student) =>
                    student.full_name.toLowerCase().includes(query) ||
                    (student.email ?? "").toLowerCase().includes(query) ||
                    (student.student_id ?? "").toLowerCase().includes(query),
            );
        });
    }, [clusters, search, typeFilter]);

    const selectRole = (clusterId: string, studentId: number, role: "source" | "target") => {
        setSelections((previous) => {
            const current = previous[clusterId] ?? { sourceId: null, targetId: null };
            const updated: ClusterSelection =
                role === "source"
                    ? {
                          sourceId: studentId,
                          targetId: current.targetId === studentId ? null : current.targetId,
                      }
                    : {
                          sourceId: current.sourceId === studentId ? null : current.sourceId,
                          targetId: studentId,
                      };
            return { ...previous, [clusterId]: updated };
        });
        setPreviews((previous) => {
            const copy = { ...previous };
            delete copy[clusterId];
            return copy;
        });
    };

    const handlePreview = async (clusterId: string) => {
        const selection = selections[clusterId];
        if (!selection?.sourceId || !selection?.targetId || selection.sourceId === selection.targetId) {
            toast.error("Choose two different students: one Source (Merge From) and one Target (Keep).");
            return;
        }

        setLoadingPreviewId(clusterId);
        try {
            const response = await axios.post<TransferPreviewPayload>("/administrators/students/duplicates/preview", {
                source_student_id: selection.sourceId,
                target_student_id: selection.targetId,
            });
            setPreviews((previous) => ({ ...previous, [clusterId]: response.data }));
        } catch (error: unknown) {
            const message =
                axios.isAxiosError(error) && typeof error.response?.data?.message === "string"
                    ? error.response.data.message
                    : "Unable to preview transfer impact.";
            toast.error(message);
        } finally {
            setLoadingPreviewId(null);
        }
    };

    const activeConfirmPreview = confirmClusterId ? previews[confirmClusterId] : null;
    const activeConfirmSelection = confirmClusterId ? selections[confirmClusterId] : null;

    const handleConfirmMerge = () => {
        if (!confirmClusterId || !activeConfirmSelection?.sourceId || !activeConfirmSelection?.targetId) return;

        setMerging(true);
        router.post(
            "/administrators/students/duplicates/merge",
            {
                source_student_id: activeConfirmSelection.sourceId,
                target_student_id: activeConfirmSelection.targetId,
                archive_source: archiveSource,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConfirmClusterId(null);
                    setPreviews((previous) => {
                        const copy = { ...previous };
                        delete copy[confirmClusterId];
                        return copy;
                    });
                },
                onError: () => toast.error("Failed to merge student records."),
                onFinish: () => setMerging(false),
            },
        );
    };

    const totalImpactCount = (preview: TransferPreviewPayload) => {
        const r = preview.records_to_transfer;
        return (
            r.enrollments_count +
            r.subject_enrollments_count +
            r.class_enrollments_count +
            r.tuition_ledgers_count +
            r.transactions_count +
            r.clearances_count +
            r.attendance_records_count
        );
    };

    return (
        <AdminLayout user={user} title="Duplicate Student Scanner">
            <Head title="Administrators • Duplicate Student Scanner" />

            <div className="flex flex-col gap-5">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Student Records</p>
                            <span className="text-muted-foreground/60 text-xs">•</span>
                            <span className="text-muted-foreground text-xs">Data Hygiene</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Duplicate Student Scanner</h1>
                        <p className="text-muted-foreground max-w-2xl text-xs leading-relaxed sm:text-sm">
                            Detect duplicate student profiles by normalized email or name and birth date, preview transfer impact, and merge records
                            safely into a single canonical profile.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <AdminLink href="/administrators/students" className={buttonVariants({ variant: "outline", size: "sm", className: "gap-2" })}>
                            <ArrowLeft className="size-4" />
                            Back to Directory
                        </AdminLink>
                        <Button
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={() => router.reload({ only: ["clusters", "stats"] })}
                        >
                            <RefreshCw className="size-4" />
                            Rescan
                        </Button>
                    </div>
                </header>

                {/* KPI Strip */}
                <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Duplicate Clusters</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">{stats.total_clusters}</p>
                            </div>
                            <div className="bg-amber-500/10 flex size-10 items-center justify-center rounded-lg">
                                <GitMerge className="size-5 text-amber-600 dark:text-amber-400" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Exact Email Matches</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">{stats.email_clusters}</p>
                            </div>
                            <div className="bg-sky-500/10 flex size-10 items-center justify-center rounded-lg">
                                <Mail className="size-5 text-sky-600 dark:text-sky-400" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Name + DOB Matches</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">{stats.identity_clusters}</p>
                            </div>
                            <div className="bg-violet-500/10 flex size-10 items-center justify-center rounded-lg">
                                <UserCheck className="size-5 text-violet-600 dark:text-violet-400" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="flex items-center justify-between gap-3 p-4">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Affected Profiles</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">{stats.affected_students}</p>
                            </div>
                            <div className="bg-primary/10 flex size-10 items-center justify-center rounded-lg">
                                <Users className="text-primary size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter bar */}
                <Card className="py-3">
                    <CardContent className="flex flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative max-w-md flex-1">
                            <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Filter by student name, email, or ID..."
                                className="pl-9"
                            />
                        </div>
                        <div className="flex flex-wrap items-center gap-1.5">
                            {(
                                [
                                    { key: "all", label: "All Clusters" },
                                    { key: "email", label: "Email Matches" },
                                    { key: "name_dob", label: "Name + DOB Matches" },
                                ] as const
                            ).map((tab) => (
                                <Button
                                    key={tab.key}
                                    size="sm"
                                    variant={typeFilter === tab.key ? "default" : "outline"}
                                    onClick={() => setTypeFilter(tab.key)}
                                >
                                    {tab.label}
                                </Button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Clusters */}
                {filteredClusters.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-14 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-emerald-500/10">
                                <CheckCircle2 className="size-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">
                                    {clusters.length === 0 ? "No duplicate student records detected" : "No clusters match your filter"}
                                </h2>
                                <p className="text-muted-foreground max-w-md text-sm">
                                    {clusters.length === 0
                                        ? "All active student profiles in this school currently have unique email addresses and name + birth date identities."
                                        : "Try clearing your search term or switching match type filters."}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {filteredClusters.map((cluster) => {
                            const selection = selections[cluster.id] ?? { sourceId: null, targetId: null };
                            const preview = previews[cluster.id];
                            const isLoading = loadingPreviewId === cluster.id;
                            const canPreview =
                                selection.sourceId !== null && selection.targetId !== null && selection.sourceId !== selection.targetId;

                            return (
                                <Card key={cluster.id} className="overflow-hidden">
                                    <CardHeader className="bg-muted/25 flex flex-col gap-3 border-b sm:flex-row sm:items-center sm:justify-between">
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        cluster.match_type === "email"
                                                            ? "border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300"
                                                            : cluster.match_type === "both"
                                                              ? "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                                              : "border-violet-500/40 bg-violet-500/10 text-violet-700 dark:text-violet-300"
                                                    }
                                                >
                                                    {cluster.match_label}
                                                </Badge>
                                                <Badge variant="secondary">{cluster.students.length} profiles</Badge>
                                            </div>
                                            <CardTitle className="text-base font-semibold">{cluster.match_value}</CardTitle>
                                            <CardDescription>
                                                Select the duplicate profile to merge from (Source) and the canonical profile to keep (Target).
                                            </CardDescription>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={!canPreview || isLoading}
                                                onClick={() => handlePreview(cluster.id)}
                                                className="gap-2"
                                            >
                                                {isLoading ? <Loader2 className="size-4 animate-spin" /> : <GitMerge className="size-4" />}
                                                Preview Transfer Impact
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-4">
                                        <div className="grid gap-3 md:grid-cols-2">
                                            {cluster.students.map((student) => {
                                                const isSource = selection.sourceId === student.id;
                                                const isTarget = selection.targetId === student.id;

                                                return (
                                                    <div
                                                        key={student.id}
                                                        className={`flex flex-col justify-between gap-3 rounded-lg border p-4 transition-colors ${
                                                            isTarget
                                                                ? "border-emerald-500/60 bg-emerald-500/5"
                                                                : isSource
                                                                  ? "border-amber-500/60 bg-amber-500/5"
                                                                  : "border-border"
                                                        }`}
                                                    >
                                                        <div className="space-y-2">
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="min-w-0">
                                                                    <div className="flex items-center gap-2">
                                                                        <p className="truncate font-semibold">{student.full_name}</p>
                                                                        <Link
                                                                            href={`/administrators/students/${student.id}`}
                                                                            className="text-muted-foreground hover:text-primary shrink-0"
                                                                            title="Open student profile"
                                                                        >
                                                                            <ExternalLink className="size-3.5" />
                                                                        </Link>
                                                                    </div>
                                                                    <p className="text-muted-foreground font-mono text-xs">
                                                                        ID: {student.student_id ?? `#${student.id}`}
                                                                    </p>
                                                                </div>
                                                                <Badge variant="outline" className="capitalize">
                                                                    {student.status}
                                                                </Badge>
                                                            </div>

                                                            <div className="text-muted-foreground grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                                                <span className="truncate">Email: {student.email || "—"}</span>
                                                                <span>DOB: {student.birth_date || "—"}</span>
                                                                <span>Enrollments: {student.enrollments_count}</span>
                                                                <span>Classes: {student.class_enrollments_count}</span>
                                                                <span>Created: {student.created_at || "—"}</span>
                                                                <span>
                                                                    Portal:{" "}
                                                                    {student.portal_linked ? (
                                                                        <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                                                            Linked
                                                                        </span>
                                                                    ) : (
                                                                        "Not linked"
                                                                    )}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                                                            <Button
                                                                type="button"
                                                                size="xs"
                                                                variant={isSource ? "default" : "outline"}
                                                                className={
                                                                    isSource
                                                                        ? "bg-amber-600 text-white hover:bg-amber-700"
                                                                        : "hover:border-amber-500/50"
                                                                }
                                                                onClick={() => selectRole(cluster.id, student.id, "source")}
                                                            >
                                                                Source (Merge From)
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="xs"
                                                                variant={isTarget ? "default" : "outline"}
                                                                className={
                                                                    isTarget
                                                                        ? "bg-emerald-600 text-white hover:bg-emerald-700"
                                                                        : "hover:border-emerald-500/50"
                                                                }
                                                                onClick={() => selectRole(cluster.id, student.id, "target")}
                                                            >
                                                                Target (Keep)
                                                            </Button>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {preview && (
                                            <div className="bg-muted/30 space-y-4 rounded-lg border p-4">
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                                        <span className="text-amber-700 dark:text-amber-300">
                                                            {preview.source_student.name} ({preview.source_student.student_id ?? `#${preview.source_student.id}`})
                                                        </span>
                                                        <ArrowRight className="text-muted-foreground size-4" />
                                                        <span className="text-emerald-700 dark:text-emerald-300">
                                                            {preview.target_student.name} ({preview.target_student.student_id ?? `#${preview.target_student.id}`})
                                                        </span>
                                                    </div>
                                                    <Badge variant={preview.safe_to_proceed ? "secondary" : "destructive"}>
                                                        {preview.safe_to_proceed
                                                            ? `${totalImpactCount(preview)} records will move`
                                                            : "Transfer blocked"}
                                                    </Badge>
                                                </div>

                                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                                                    {[
                                                        { label: "Enrollments", count: preview.records_to_transfer.enrollments_count },
                                                        { label: "Subjects", count: preview.records_to_transfer.subject_enrollments_count },
                                                        { label: "Classes", count: preview.records_to_transfer.class_enrollments_count },
                                                        { label: "Tuitions", count: preview.records_to_transfer.tuition_ledgers_count },
                                                        { label: "Transactions", count: preview.records_to_transfer.transactions_count },
                                                        { label: "Attendance", count: preview.records_to_transfer.attendance_records_count },
                                                        { label: "Clearances", count: preview.records_to_transfer.clearances_count },
                                                    ].map((item) => (
                                                        <div key={item.label} className="bg-background rounded-md border px-3 py-2 text-center">
                                                            <div className="text-lg font-bold tabular-nums">{item.count}</div>
                                                            <div className="text-muted-foreground text-[11px]">{item.label}</div>
                                                        </div>
                                                    ))}
                                                </div>

                                                {preview.warnings.length > 0 && (
                                                    <div className="space-y-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-200">
                                                        <div className="flex items-center gap-1.5 font-semibold">
                                                            <AlertTriangle className="size-4 shrink-0" />
                                                            Warnings ({preview.warnings.length})
                                                        </div>
                                                        <ul className="list-disc space-y-1 pl-5">
                                                            {preview.warnings.map((warning, idx) => (
                                                                <li key={idx}>{warning}</li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                )}

                                                <div className="flex flex-col gap-3 border-t pt-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <Checkbox
                                                            id={`archive-${cluster.id}`}
                                                            checked={archiveSource}
                                                            onCheckedChange={(checked) => setArchiveSource(checked === true)}
                                                        />
                                                        <Label htmlFor={`archive-${cluster.id}`} className="cursor-pointer text-xs font-medium">
                                                            Archive (soft-delete) empty source student after merge
                                                        </Label>
                                                    </div>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        disabled={!preview.safe_to_proceed || merging}
                                                        onClick={() => setConfirmClusterId(cluster.id)}
                                                        className="gap-2"
                                                    >
                                                        <GitMerge className="size-4" />
                                                        Merge Records
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>

            <AlertDialog open={confirmClusterId !== null} onOpenChange={(open) => !open && setConfirmClusterId(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <ShieldAlert className="size-5 text-amber-500" />
                            Confirm Student Record Merge
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {activeConfirmPreview ? (
                                <>
                                    Move <strong>{totalImpactCount(activeConfirmPreview)}</strong> linked records from{" "}
                                    <strong>{activeConfirmPreview.source_student.name}</strong> into{" "}
                                    <strong>{activeConfirmPreview.target_student.name}</strong>
                                    {archiveSource ? " and archive the empty source profile" : ""}. All changes are recorded in the student ID change
                                    log.
                                </>
                            ) : (
                                "Are you sure you want to merge these student records?"
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={merging}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={merging}
                            onClick={(event) => {
                                event.preventDefault();
                                handleConfirmMerge();
                            }}
                        >
                            {merging ? "Merging..." : "Confirm Merge"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
