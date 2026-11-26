import {
    update as updateStudentScholarship,
    updateRemarks,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameFooter,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button, buttonVariants } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Link, router } from "@inertiajs/react";
import {
    AlertCircle,
    Award,
    Check,
    CheckCircle2,
    ChevronRight,
    Copy,
    CreditCard,
    ExternalLink,
    FolderOpen,
    GraduationCap,
    Lock,
    Mail,
    MessageSquareText,
    Phone,
    ShieldAlert,
    SlidersHorizontal,
    User,
} from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import {
    formatMoney,
    getInitials,
    tuitionSettlementPercent,
} from "../enrollment-utils";
import type { DocumentLinks, EnrollmentData, ShowEnrollmentPageProps } from "./types";

export interface ReadinessActionRailProps {
    enrollment: EnrollmentData;
    auth: ShowEnrollmentPageProps["auth"];
    documentLinks: DocumentLinks;
    scholarshipOptions: ShowEnrollmentPageProps["scholarship_options"];
    currency: string;
    scheduleConflictsCount: number;
    onJumpToTab: (tab: string) => void;
    compact?: boolean;
    className?: string;
}

type FlashPayload = {
    error?: string;
    success?: string;
    warning?: string;
    info?: string;
};

function readFlash(page: { props: unknown }): FlashPayload | undefined {
    const props = page.props as { flash?: FlashPayload | null } | undefined;
    return props?.flash ?? undefined;
}

export function ReadinessActionRail({
    enrollment,
    auth,
    documentLinks,
    scholarshipOptions,
    currency,
    scheduleConflictsCount,
    onJumpToTab,
    compact = false,
    className,
}: ReadinessActionRailProps) {
    const [copiedId, setCopiedId] = useState(false);

    // Inline Scholarship state
    const currentScholarship = enrollment.student.scholarship_type ?? "none";
    const [scholarshipValue, setScholarshipValue] = useState<string>(currentScholarship);
    const [savingScholarship, setSavingScholarship] = useState(false);

    useEffect(() => {
        setScholarshipValue(enrollment.student.scholarship_type ?? "none");
    }, [enrollment.student.scholarship_type, enrollment.id]);

    // Inline Remarks state
    const serverRemarks = enrollment.remarks ?? "";
    const [remarksDraft, setRemarksDraft] = useState<string>(serverRemarks);
    const [savingRemarks, setSavingRemarks] = useState(false);

    useEffect(() => {
        setRemarksDraft(enrollment.remarks ?? "");
    }, [enrollment.remarks, enrollment.id]);

    const remarksDirty = remarksDraft.trim() !== serverRemarks.trim();

    const canEditInline = auth.can_manage_enrollment && !enrollment.is_trashed;

    const handleCopyStudentId = () => {
        const raw = enrollment.student.student_id;
        if (raw === null || raw === undefined || String(raw).trim() === "") return;

        navigator.clipboard
            ?.writeText(String(raw))
            .then(() => {
                setCopiedId(true);
                toast.success("Student ID copied.");
                window.setTimeout(() => setCopiedId(false), 1800);
            })
            .catch(() => {
                toast.error("Could not copy Student ID.");
            });
    };

    const handleScholarshipChange = (nextValue: string) => {
        if (!enrollment.student.id || !canEditInline) return;

        const previous = scholarshipValue;
        setScholarshipValue(nextValue);
        router.patch(
            updateStudentScholarship.url(enrollment.student.id),
            { scholarship_type: nextValue },
            {
                preserveScroll: true,
                onStart: () => setSavingScholarship(true),
                onFinish: () => setSavingScholarship(false),
                onSuccess: (page) => {
                    const flash = readFlash(page);
                    if (flash?.error) {
                        setScholarshipValue(previous);
                    }
                },
                onError: () => {
                    setScholarshipValue(previous);
                    toast.error("Failed to update scholarship classification.");
                },
            },
        );
    };

    const handleSaveRemarks = () => {
        if (!canEditInline || !remarksDirty) return;

        router.patch(
            updateRemarks.url(enrollment.id),
            { remarks: remarksDraft.trim() || null },
            {
                preserveScroll: true,
                onStart: () => setSavingRemarks(true),
                onFinish: () => setSavingRemarks(false),
                onError: () => {
                    toast.error("Failed to save registrar remarks.");
                },
            },
        );
    };

    // Readiness checks
    const activeHolds = enrollment.holds.filter((hold) => hold.status === "active");
    const blockingHolds = activeHolds.filter(
        (hold) => hold.severity === "blocking" || hold.blocks_enrollment,
    );
    const totalRequirements = enrollment.requirements.length;
    const pendingRequirements = enrollment.requirements.filter(
        (requirement) => requirement.status === "pending",
    ).length;
    const missingSections = enrollment.missing_classes.length;
    const totalSubjects = enrollment.subjects_enrolled.length;

    const overallTuition = Number(enrollment.tuition?.overall_tuition ?? 0);
    const totalPaid = Number(enrollment.tuition?.total_paid ?? 0);
    const balanceDue = Number(
        enrollment.tuition?.balance_due ?? enrollment.tuition?.total_balance ?? 0,
    );
    const requiredDownpayment = Number(
        enrollment.tuition?.required_downpayment ?? enrollment.tuition?.downpayment ?? 0,
    );
    const creditAmount = Number(enrollment.tuition?.credit ?? 0);
    const settlementPercent = tuitionSettlementPercent(overallTuition, balanceDue);
    const downpaymentShortfall = Math.max(
        0,
        Math.min(requiredDownpayment, overallTuition) - totalPaid,
    );
    const downpaymentReady = downpaymentShortfall <= 0 || balanceDue <= 0;

    const readinessChecks = [
        {
            id: "holds",
            label: "Student Holds",
            tab: "holds",
            ok: blockingHolds.length === 0,
            warn: blockingHolds.length === 0 && activeHolds.length > 0,
            detail:
                blockingHolds.length > 0
                    ? `${blockingHolds.length} blocking`
                    : activeHolds.length > 0
                      ? `${activeHolds.length} warning`
                      : "Clear",
        },
        {
            id: "requirements",
            label: "Requirements",
            tab: "requirements",
            ok: pendingRequirements === 0,
            warn: false,
            detail:
                totalRequirements === 0
                    ? "None required"
                    : pendingRequirements === 0
                      ? `${totalRequirements}/${totalRequirements} verified`
                      : `${pendingRequirements} pending`,
        },
        {
            id: "sections",
            label: "Class Sections",
            tab: "subjects",
            ok: missingSections === 0 && totalSubjects > 0,
            warn: totalSubjects === 0,
            detail:
                totalSubjects === 0
                    ? "No subjects"
                    : missingSections === 0
                      ? `${totalSubjects}/${totalSubjects} assigned`
                      : `${missingSections} missing`,
        },
        {
            id: "clashes",
            label: "Schedule Clashes",
            tab: "schedule",
            ok: scheduleConflictsCount === 0,
            warn: false,
            detail:
                scheduleConflictsCount === 0
                    ? "No conflicts"
                    : `${scheduleConflictsCount} clash${scheduleConflictsCount === 1 ? "" : "es"}`,
        },
        {
            id: "downpayment",
            label: "Downpayment",
            tab: "financial",
            ok: downpaymentReady,
            warn: !downpaymentReady,
            detail: downpaymentReady
                ? balanceDue <= 0
                    ? "Paid in full"
                    : "Met"
                : `Short ${formatMoney(downpaymentShortfall, currency)}`,
        },
    ];

    const readyCount = readinessChecks.filter((check) => check.ok).length;
    const hasFinanceLock = Boolean(
        enrollment.tuition?.active_revision_id || enrollment.tuition?.needs_finance_review,
    );
    const isTesda =
        enrollment.curriculum_kind === "tesda" || Boolean(enrollment.tesda_program_type);

    return (
        <aside
            aria-label="Student dossier and readiness rail"
            className={cn(
                "min-w-0 space-y-3",
                !compact &&
                    "xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:overflow-y-auto xl:pr-0.5",
                className,
            )}
        >
            {/* 1. Student Identity & Contact Frame */}
            <Frame spacing="xs">
                <FramePanel className="space-y-3 p-3">
                    <div className="flex items-start gap-2.5">
                        <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg text-xs font-semibold">
                            {getInitials(enrollment.student.full_name)}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-foreground truncate text-sm font-semibold">
                                {enrollment.student.full_name}
                            </p>
                            <div className="mt-0.5 flex items-center gap-1">
                                <span className="text-muted-foreground truncate font-mono text-xs tabular-nums">
                                    {enrollment.student.student_id ?? `STU-${enrollment.student_id}`}
                                </span>
                                {enrollment.student.student_id && (
                                    <button
                                        type="button"
                                        onClick={handleCopyStudentId}
                                        className="text-muted-foreground hover:text-foreground inline-flex size-5 items-center justify-center rounded transition-colors"
                                        title="Copy Student ID"
                                        aria-label="Copy Student ID"
                                    >
                                        {copiedId ? (
                                            <Check className="size-3 text-emerald-600" />
                                        ) : (
                                            <Copy className="size-3" />
                                        )}
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="bg-muted/35 space-y-1 rounded-lg border px-2.5 py-2 text-xs">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Program</span>
                            <span className="truncate font-medium">
                                {enrollment.course_code ?? "Unassigned"}
                            </span>
                        </div>
                        {enrollment.department && (
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">Department</span>
                                <span className="truncate font-medium">{enrollment.department}</span>
                            </div>
                        )}
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Year Level</span>
                            <span className="font-medium tabular-nums">
                                Year {enrollment.academic_year}
                            </span>
                        </div>
                        {isTesda && (
                            <div className="flex items-center justify-between gap-2 pt-0.5">
                                <span className="text-muted-foreground">Track</span>
                                <Badge variant="info-light" size="xs">
                                    <GraduationCap className="size-3" />
                                    TESDA
                                    {enrollment.duration_hours
                                        ? ` · ${enrollment.duration_hours}h`
                                        : ""}
                                </Badge>
                            </div>
                        )}
                    </div>

                    {/* Contact Pills */}
                    <div className="flex flex-col gap-1.5">
                        {enrollment.student.email && (
                            <a
                                href={`mailto:${enrollment.student.email}`}
                                className="bg-background hover:bg-muted/60 text-foreground flex min-w-0 items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs transition-colors"
                            >
                                <Mail className="text-muted-foreground size-3.5 shrink-0" />
                                <span className="truncate">{enrollment.student.email}</span>
                            </a>
                        )}
                        {enrollment.student.phone && (
                            <a
                                href={`tel:${enrollment.student.phone}`}
                                className="bg-background hover:bg-muted/60 text-foreground flex min-w-0 items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs tabular-nums transition-colors"
                            >
                                <Phone className="text-muted-foreground size-3.5 shrink-0" />
                                <span className="truncate">{enrollment.student.phone}</span>
                            </a>
                        )}
                    </div>

                    {(enrollment.student.emergency_contact_name ||
                        enrollment.student.emergency_contact_phone) && (
                        <div className="text-muted-foreground border-t pt-2 text-[11px]">
                            <p className="font-medium">Emergency Contact</p>
                            <p className="text-foreground truncate">
                                {enrollment.student.emergency_contact_name ?? "—"}
                                {enrollment.student.emergency_contact_phone
                                    ? ` · ${enrollment.student.emergency_contact_phone}`
                                    : ""}
                            </p>
                        </div>
                    )}
                </FramePanel>

                {(enrollment.student.student_url || enrollment.student.documents_url) && (
                    <FrameFooter className="flex-row items-center gap-2 px-3 py-2">
                        {enrollment.student.student_url && (
                            <Link
                                href={enrollment.student.student_url}
                                className={cn(
                                    buttonVariants({ variant: "outline", size: "sm" }),
                                    "h-7 flex-1 gap-1.5 text-xs",
                                )}
                            >
                                <User className="size-3" />
                                Profile
                            </Link>
                        )}
                        {enrollment.student.documents_url && (
                            <Link
                                href={enrollment.student.documents_url}
                                className={cn(
                                    buttonVariants({ variant: "outline", size: "sm" }),
                                    "h-7 flex-1 gap-1.5 text-xs",
                                )}
                            >
                                <FolderOpen className="size-3" />
                                Documents
                            </Link>
                        )}
                    </FrameFooter>
                )}
            </Frame>

            {/* 2. Pre-Approval Decision Readiness Checklist */}
            <Frame spacing="xs">
                <FrameHeader className="flex-row items-center justify-between gap-2 px-3 py-2">
                    <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                        Decision Readiness
                    </FrameTitle>
                    <Badge
                        variant={
                            readyCount === readinessChecks.length ? "success-light" : "warning-light"
                        }
                        size="sm"
                    >
                        {readyCount}/{readinessChecks.length} Ready
                    </Badge>
                </FrameHeader>
                <FramePanel className="divide-y p-0">
                    {readinessChecks.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => onJumpToTab(item.tab)}
                            className="hover:bg-muted/50 flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                {item.ok ? (
                                    <CheckCircle2 className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                ) : item.warn ? (
                                    <AlertCircle className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                                ) : (
                                    <ShieldAlert className="text-destructive size-3.5 shrink-0" />
                                )}
                                <span className="truncate font-medium">{item.label}</span>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <span
                                    className={cn(
                                        "text-[11px] font-medium tabular-nums",
                                        item.ok
                                            ? "text-emerald-600 dark:text-emerald-400"
                                            : item.warn
                                              ? "text-amber-600 dark:text-amber-400"
                                              : "text-destructive",
                                    )}
                                >
                                    {item.detail}
                                </span>
                                <ChevronRight className="text-muted-foreground size-3" />
                            </div>
                        </button>
                    ))}
                </FramePanel>
            </Frame>

            {/* 3. Tuition & Balance Meter */}
            <Frame spacing="xs">
                <FrameHeader className="flex-row items-center justify-between gap-2 px-3 py-2">
                    <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                        Tuition &amp; Balance
                    </FrameTitle>
                    {hasFinanceLock ? (
                        <Badge variant="warning-light" size="xs">
                            <Lock className="size-2.5" />
                            Finance Lock
                        </Badge>
                    ) : (
                        <span className="text-muted-foreground text-[11px] font-semibold tabular-nums">
                            {settlementPercent}% settled
                        </span>
                    )}
                </FrameHeader>
                <FramePanel className="space-y-3 p-3">
                    <Progress value={settlementPercent} className="h-1.5" />

                    <div className="space-y-1.5 text-xs">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Assessed</span>
                            <span className="font-medium tabular-nums">
                                {formatMoney(overallTuition, currency)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Paid</span>
                            <span className="font-medium text-emerald-600 tabular-nums dark:text-emerald-400">
                                {formatMoney(totalPaid, currency)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-2 border-t pt-1.5">
                            <span className="font-medium">Balance Due</span>
                            <span
                                className={cn(
                                    "font-semibold tabular-nums",
                                    balanceDue > 0
                                        ? "text-amber-600 dark:text-amber-400"
                                        : "text-emerald-600 dark:text-emerald-400",
                                )}
                            >
                                {formatMoney(balanceDue, currency)}
                            </span>
                        </div>
                        {creditAmount > 0 && (
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">Overpayment Credit</span>
                                <span className="text-info font-medium tabular-nums">
                                    {formatMoney(creditAmount, currency)}
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2 pt-0.5">
                        {documentLinks.cashier_payment_url && (
                            <Link
                                href={documentLinks.cashier_payment_url}
                                className={cn(
                                    buttonVariants({ variant: "secondary", size: "sm" }),
                                    "h-7 flex-1 gap-1.5 text-xs",
                                )}
                            >
                                <CreditCard className="size-3" />
                                Record Payment
                            </Link>
                        )}
                        <Link
                            href={documentLinks.tuition_adjustments_url}
                            className={cn(
                                buttonVariants({ variant: "outline", size: "sm" }),
                                "h-7 flex-1 gap-1.5 text-xs",
                            )}
                        >
                            <SlidersHorizontal className="size-3" />
                            Adjustments
                            <ExternalLink className="size-2.5 opacity-60" />
                        </Link>
                    </div>
                </FramePanel>
            </Frame>

            {/* 4. Inline Scholarship & Registrar Remarks */}
            <Frame spacing="xs">
                <FrameHeader className="flex-row items-center gap-2 px-3 py-2">
                    <IconTile variant="soft" size="xs" className="text-primary">
                        <Award className="size-3.5" />
                    </IconTile>
                    <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                        Scholarship &amp; Remarks
                    </FrameTitle>
                </FrameHeader>
                <FramePanel className="space-y-3 p-3">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="rail-scholarship-select"
                            className="text-muted-foreground text-xs"
                        >
                            Scholarship Classification
                        </Label>
                        {canEditInline && enrollment.student.id ? (
                            <Select
                                value={scholarshipValue}
                                onValueChange={handleScholarshipChange}
                                disabled={savingScholarship}
                            >
                                <SelectTrigger
                                    id="rail-scholarship-select"
                                    className="h-8 text-xs"
                                >
                                    <SelectValue placeholder="Select scholarship" />
                                </SelectTrigger>
                                <SelectContent>
                                    {scholarshipOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <p className="text-foreground text-xs font-medium">
                                {enrollment.student.scholarship_label ?? "None"}
                            </p>
                        )}
                    </div>

                    <Separator />

                    <div className="grid gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label
                                htmlFor="rail-registrar-remarks"
                                className="text-muted-foreground flex items-center gap-1.5 text-xs"
                            >
                                <MessageSquareText className="size-3" />
                                Registrar Remarks
                            </Label>
                            {remarksDirty && (
                                <Badge variant="info-light" size="xs">
                                    Unsaved
                                </Badge>
                            )}
                        </div>

                        {canEditInline ? (
                            <>
                                <Textarea
                                    id="rail-registrar-remarks"
                                    rows={3}
                                    value={remarksDraft}
                                    onChange={(e) => setRemarksDraft(e.target.value)}
                                    placeholder="Add internal advising or registrar notes…"
                                    className="min-h-[72px] resize-y text-xs"
                                    maxLength={2000}
                                />
                                <div className="flex items-center justify-end gap-1.5">
                                    {remarksDirty && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            className="h-7 px-2 text-xs"
                                            disabled={savingRemarks}
                                            onClick={() => setRemarksDraft(serverRemarks)}
                                        >
                                            Reset
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="h-7 px-2.5 text-xs"
                                        disabled={!remarksDirty || savingRemarks}
                                        onClick={handleSaveRemarks}
                                    >
                                        {savingRemarks ? "Saving…" : "Save Remarks"}
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="text-muted-foreground bg-muted/30 rounded-md border p-2 text-xs whitespace-pre-wrap">
                                {serverRemarks || "No registrar remarks recorded."}
                            </p>
                        )}
                    </div>
                </FramePanel>
            </Frame>
        </aside>
    );
}
