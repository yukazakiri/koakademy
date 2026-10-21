import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircle,
    Award,
    CheckCircle2,
    Download,
    FileText,
    Loader2,
    Mail,
    MailCheck,
    Phone,
    RotateCcw,
    ShieldCheck,
    UserPlus,
} from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { route } from "ziggy-js";
import { STAGE_BADGE_CLASSES, avatarToneClass, formatDate, getInitials, requirementStatusClasses, stageLabel } from "./applicants-utils";
import type { ApplicantDetail, ApplicantRow, ApplicantStage } from "./types";

type ApplicantDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    applicant: ApplicantRow | null;
    onNotifyApproval: (applicant: ApplicantRow) => void;
    onManageScholarship: (applicant: ApplicantRow) => void;
    onRevertCancellation: (applicant: ApplicantRow) => void;
    notifyingApplicantId: number | null;
    stageMeta?: Record<string, { label: string }>;
    pipelineStatusLabels?: Record<string, string>;
};

type DetailField = { label: string; value: string | null | undefined };

function DetailItem({ field }: { field: DetailField }) {
    return (
        <div className="min-w-0">
            <dt className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">{field.label}</dt>
            <dd className="mt-1 truncate text-sm" title={field.value ?? undefined}>
                {field.value || "—"}
            </dd>
        </div>
    );
}

export function ApplicantDrawer({
    open,
    onOpenChange,
    applicant,
    onNotifyApproval,
    onManageScholarship,
    onRevertCancellation,
    notifyingApplicantId,
    stageMeta,
    pipelineStatusLabels,
}: ApplicantDrawerProps) {
    const [detail, setDetail] = useState<ApplicantDetail | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const applicantId = applicant?.id ?? null;

    const loadDetail = useCallback(async () => {
        if (!applicantId) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await axios.get<ApplicantDetail>(route("administrators.enrollments.applicants.show", applicantId));
            setDetail(response.data);
        } catch {
            setDetail(null);
            setError("The applicant dossier could not be loaded. Please try again.");
        } finally {
            setIsLoading(false);
        }
    }, [applicantId]);

    useEffect(() => {
        if (!open) {
            setDetail(null);
            setError(null);
            return;
        }

        void loadDetail();
    }, [open, loadDetail]);

    const profile = detail?.applicant;
    const cancellation = detail?.cancellation ?? null;
    const history = detail?.cancellation_history ?? [];
    const requirements = detail?.requirements ?? [];
    const requiredRequirements = requirements.filter((requirement) => requirement.required);
    const submittedCount = requiredRequirements.filter((requirement) => requirement.evidence_path !== null).length;
    const reviewedCount = requiredRequirements.filter((requirement) => requirement.status === "verified" || requirement.status === "waived").length;
    const readiness = requiredRequirements.length > 0 ? (submittedCount / requiredRequirements.length) * 100 : 0;
    const isNotifying = applicant !== null && notifyingApplicantId === applicant.id;

    const stage = (applicant?.stage ?? "manual") as ApplicantStage;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col gap-0 overflow-y-auto p-0 sm:max-w-xl">
                <SheetHeader className="border-b pb-4">
                    <div className="flex items-start gap-3">
                        <Avatar className="size-12 shrink-0 border">
                            <AvatarFallback className={avatarToneClass(applicant?.is_trashed ?? false)}>
                                {getInitials(applicant?.name ?? profile?.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <SheetTitle className="truncate text-base">{applicant?.name ?? profile?.name ?? "Applicant"}</SheetTitle>
                            <SheetDescription className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                {applicant?.student_id ? (
                                    <span className="text-foreground font-medium">Student ID {applicant.student_id}</span>
                                ) : (
                                    <span>Student ID not issued yet</span>
                                )}
                                {applicant?.lrn && <span>LRN {applicant.lrn}</span>}
                            </SheetDescription>
                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline" className={cn("rounded-full px-2 py-0.5 font-medium", STAGE_BADGE_CLASSES[stage])}>
                                    {stageLabel(stage, stageMeta)}
                                </Badge>
                                {applicant?.course && (
                                    <Badge variant="secondary" className="rounded-full px-2 py-0.5 font-normal">
                                        {applicant.course}
                                    </Badge>
                                )}
                                {applicant?.is_trashed && <Badge variant="destructive">Deleted</Badge>}
                            </div>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex-1 space-y-6 px-4 py-5">
                    {isLoading && !detail ? (
                        <div className="space-y-4">
                            <Skeleton className="h-20 w-full rounded-lg" />
                            <Skeleton className="h-32 w-full rounded-lg" />
                            <Skeleton className="h-40 w-full rounded-lg" />
                        </div>
                    ) : error ? (
                        <div className="flex flex-col items-center gap-3 rounded-lg border border-red-200 bg-red-50/60 p-6 text-center dark:border-red-900/60 dark:bg-red-950/30">
                            <AlertCircle className="size-5 text-red-600 dark:text-red-400" aria-hidden="true" />
                            <p className="text-sm text-red-800 dark:text-red-200">{error}</p>
                            <Button variant="outline" size="sm" onClick={() => void loadDetail()}>
                                Try again
                            </Button>
                        </div>
                    ) : (
                        <>
                            <section className="space-y-3">
                                <h3 className="text-foreground text-sm font-semibold">Contact</h3>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <DetailItem field={{ label: "Email", value: profile?.email }} />
                                    <DetailItem field={{ label: "Phone", value: profile?.phone }} />
                                    <DetailItem field={{ label: "Emergency contact", value: profile?.emergency_contact }} />
                                    <DetailItem field={{ label: "Address", value: profile?.address }} />
                                </div>
                                {(profile?.email || profile?.phone) && (
                                    <div className="flex flex-wrap gap-2">
                                        {profile?.email && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-8 gap-1.5 text-xs"
                                                render={<a href={`mailto:${profile.email}`} />}
                                            >
                                                <Mail className="size-3.5" aria-hidden="true" />
                                                Email applicant
                                            </Button>
                                        )}
                                        {profile?.phone && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-8 gap-1.5 text-xs"
                                                render={<a href={`tel:${profile.phone.replace(/\s+/g, "")}`} />}
                                            >
                                                <Phone className="size-3.5" aria-hidden="true" />
                                                Call applicant
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </section>

                            <Separator />

                            <section className="space-y-3">
                                <h3 className="text-foreground text-sm font-semibold">Applicant details</h3>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <DetailItem field={{ label: "Program", value: profile?.course_code }} />
                                    <DetailItem field={{ label: "Department", value: profile?.department_code }} />
                                    <DetailItem field={{ label: "Level", value: profile?.student_type_label }} />
                                    <DetailItem field={{ label: "Academic year", value: profile?.academic_year?.toString() }} />
                                    <DetailItem field={{ label: "Birth date", value: profile?.birth_date ? formatDate(profile.birth_date) : null }} />
                                    <DetailItem field={{ label: "Gender", value: profile?.gender }} />
                                    <DetailItem field={{ label: "Civil status", value: profile?.civil_status }} />
                                    <DetailItem field={{ label: "Nationality", value: profile?.nationality }} />
                                    <DetailItem field={{ label: "Religion", value: profile?.religion }} />
                                    <DetailItem field={{ label: "Applied on", value: formatDate(profile?.created_at) }} />
                                    <DetailItem
                                        field={{
                                            label: "Scholarship",
                                            value: profile?.scholarship_label,
                                        }}
                                    />
                                    <DetailItem field={{ label: "Channel", value: detail?.enrollment?.submission_channel ?? "Manual entry" }} />
                                </div>
                            </section>

                            <Separator />

                            {cancellation && (
                                <section className="space-y-3">
                                    <h3 className="text-foreground text-sm font-semibold">Application closure</h3>
                                    <div className="rounded-lg border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900/60 dark:bg-rose-950/25">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline" className="rounded-full px-2 py-0.5 text-[10px] font-medium">
                                                {cancellation.reason_label}
                                            </Badge>
                                            <span className="text-muted-foreground text-[11px]">
                                                {formatDate(cancellation.cancelled_at)}
                                                {cancellation.cancelled_by ? ` · ${cancellation.cancelled_by}` : ""}
                                            </span>
                                        </div>

                                        {cancellation.note && <p className="mt-2 text-sm leading-relaxed">{cancellation.note}</p>}

                                        {cancellation.email_subject && (
                                            <div className="mt-3 space-y-1 border-t pt-2">
                                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">
                                                    Email sent to the applicant
                                                </p>
                                                <p className="text-sm font-medium">{cancellation.email_subject}</p>
                                                {cancellation.email_body && (
                                                    <p className="text-muted-foreground text-xs leading-relaxed whitespace-pre-line">
                                                        {cancellation.email_body}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {history.length > 1 && (
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">
                                                Previous decisions
                                            </p>
                                            <ul className="space-y-1.5">
                                                {history
                                                    .filter((entry) => entry.id !== cancellation.id)
                                                    .map((entry) => (
                                                        <li
                                                            key={entry.id}
                                                            className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs"
                                                        >
                                                            <span className="text-foreground font-medium">{entry.reason_label}</span>
                                                            <span>{formatDate(entry.cancelled_at)}</span>
                                                            {entry.reverted_at && (
                                                                <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
                                                                    Reopened {formatDate(entry.reverted_at)}
                                                                </Badge>
                                                            )}
                                                        </li>
                                                    ))}
                                            </ul>
                                        </div>
                                    )}
                                </section>
                            )}

                            <Separator />

                            <section className="space-y-3">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="text-foreground text-sm font-semibold">Document checklist</h3>
                                    {requiredRequirements.length > 0 && (
                                        <span className="text-muted-foreground text-xs tabular-nums">
                                            {submittedCount}/{requiredRequirements.length} uploaded · {reviewedCount} reviewed
                                        </span>
                                    )}
                                </div>

                                {requiredRequirements.length > 0 && (
                                    <Progress
                                        value={readiness}
                                        className={cn(
                                            "h-1.5",
                                            submittedCount === requiredRequirements.length
                                                ? "[&>[data-slot=progress-indicator]]:bg-emerald-500"
                                                : "[&>[data-slot=progress-indicator]]:bg-amber-500",
                                        )}
                                    />
                                )}

                                {requirements.length === 0 ? (
                                    <div className="rounded-lg border border-dashed p-5 text-center">
                                        <FileText className="text-muted-foreground mx-auto size-5" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-medium">No document checklist on record</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            This applicant was added manually and has no online submission attached.
                                        </p>
                                    </div>
                                ) : (
                                    <ul className="divide-border/70 divide-y overflow-hidden rounded-lg border">
                                        {requirements.map((requirement) => (
                                            <li
                                                key={requirement.id}
                                                className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <p className="text-sm font-medium">{requirement.label}</p>
                                                        {requirement.required && (
                                                            <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
                                                                Required
                                                            </Badge>
                                                        )}
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                "rounded-full px-2 py-0 text-[10px] capitalize",
                                                                requirementStatusClasses(requirement.status),
                                                            )}
                                                        >
                                                            {requirement.status}
                                                        </Badge>
                                                    </div>
                                                    {requirement.description && (
                                                        <p className="text-muted-foreground mt-1 text-xs">{requirement.description}</p>
                                                    )}
                                                    {requirement.waiver_reason && (
                                                        <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                            Waived: {requirement.waiver_reason}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="shrink-0">
                                                    {requirement.evidence_path ? (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8 gap-1.5 text-xs"
                                                            render={
                                                                <a
                                                                    href={route("administrators.enrollments.applicants.document", {
                                                                        student: applicantId ?? 0,
                                                                        requirement: requirement.id,
                                                                    })}
                                                                />
                                                            }
                                                        >
                                                            <Download className="size-3.5" aria-hidden="true" />
                                                            {requirement.uploaded_at ? formatDate(requirement.uploaded_at) : "Download"}
                                                        </Button>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">Not uploaded</span>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {detail?.enrollment && (
                                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <ShieldCheck className="size-3.5" aria-hidden="true" />
                                        Pipeline status:{" "}
                                        <span className="text-foreground font-medium">
                                            {pipelineStatusLabels?.[detail.enrollment.status] ?? detail.enrollment.status}
                                        </span>
                                        {detail.enrollment.school_year ? ` · ${detail.enrollment.school_year}` : ""}
                                        {detail.enrollment.semester ? ` · Semester ${detail.enrollment.semester}` : ""}
                                    </p>
                                )}
                            </section>
                        </>
                    )}
                </div>

                <SheetFooter className="bg-background/80 border-t px-4 py-3">
                    <div className="flex w-full flex-wrap items-center gap-2">
                        {applicant && cancellation && (
                            <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" onClick={() => onRevertCancellation(applicant)}>
                                <RotateCcw className="size-3.5" aria-hidden="true" />
                                Reopen application
                            </Button>
                        )}
                        {applicant && !applicant.is_trashed && !cancellation && (
                            <>
                                <Button
                                    size="sm"
                                    className="h-8 gap-1.5 bg-blue-600 text-white shadow-blue-600/20 hover:bg-blue-500"
                                    render={<Link href={route("administrators.enrollments.create", { student_id: applicant.id })} />}
                                >
                                    <UserPlus className="size-3.5" aria-hidden="true" />
                                    Process enrollment
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs"
                                    disabled={isNotifying || !applicant.email}
                                    onClick={() => onNotifyApproval(applicant)}
                                >
                                    {isNotifying ? (
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                    ) : (
                                        <MailCheck className="size-3.5" aria-hidden="true" />
                                    )}
                                    Notify approval
                                </Button>
                                <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" onClick={() => onManageScholarship(applicant)}>
                                    <Award className="size-3.5" aria-hidden="true" />
                                    Scholarship
                                </Button>
                            </>
                        )}
                        {applicant && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-auto h-8 gap-1.5 text-xs"
                                render={<Link href={route("administrators.students.show", applicant.id)} />}
                            >
                                <CheckCircle2 className="size-3.5" aria-hidden="true" />
                                Full student record
                            </Button>
                        )}
                    </div>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
