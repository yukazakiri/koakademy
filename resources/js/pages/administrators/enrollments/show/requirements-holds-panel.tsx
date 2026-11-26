import {
    override as overrideHold,
    release as releaseHold,
    store as storeHold,
} from "@/actions/App/Http/Controllers/AdministratorStudentHoldController";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/reui/alert-dialog";
import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    FileCheck,
    Loader2,
    Plus,
    RotateCcw,
    ShieldAlert,
    ShieldCheck,
    ShieldOff,
    Unlock,
} from "lucide-react";
import { useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { formatShortDate } from "../enrollment-utils";
import type { StudentHoldItem } from "../types";
import type {
    EnrollmentData,
    HoldOfficeOption,
    ShowEnrollmentPageProps,
} from "./types";

export interface RequirementsHoldsPanelProps {
    enrollment: EnrollmentData;
    holdOffices: HoldOfficeOption[];
    auth: ShowEnrollmentPageProps["auth"];
    onVerifyRequirement: (requirementId: number) => void;
    onWaiveRequirement: (requirementId: number, reason: string) => void;
    onResetRequirement: (requirementId: number) => void;
    reviewingRequirementId: number | null;
}

export function RequirementsHoldsPanel({
    enrollment,
    holdOffices,
    auth,
    onVerifyRequirement,
    onWaiveRequirement,
    onResetRequirement,
    reviewingRequirementId,
}: RequirementsHoldsPanelProps) {
    const canManageHolds =
        auth.can_manage_enrollment && !enrollment.is_trashed;
    const canReviewRequirements =
        auth.can_review_requirements && !enrollment.is_trashed;

    // Collapsible Place Hold form state
    const [showHoldForm, setShowHoldForm] = useState(false);
    const [office, setOffice] = useState<string>(
        holdOffices[0]?.value ?? "registrar",
    );
    const [severity, setSeverity] = useState<"blocking" | "warning">(
        "blocking",
    );
    const [title, setTitle] = useState("");
    const [reason, setReason] = useState("");
    const [blocksEnrollment, setBlocksEnrollment] = useState(true);
    const [blocksAddDrop, setBlocksAddDrop] = useState(true);
    const [blocksGradeRelease, setBlocksGradeRelease] = useState(false);
    const [expiresAt, setExpiresAt] = useState("");
    const [placingHold, setPlacingHold] = useState(false);

    // Hold action states
    const [releasingHoldId, setReleasingHoldId] = useState<number | null>(null);
    const [overrideTarget, setOverrideTarget] =
        useState<StudentHoldItem | null>(null);
    const [overrideReason, setOverrideReason] = useState("");
    const [overridingHold, setOverridingHold] = useState(false);

    // Requirement waiver dialog state
    const [waiveTarget, setWaiveTarget] = useState<
        EnrollmentData["requirements"][number] | null
    >(null);
    const [waiverReason, setWaiverReason] = useState("");

    const holds: StudentHoldItem[] = enrollment.holds;

    const sortedHolds = useMemo(() => {
        const statusOrder: Record<StudentHoldItem["status"], number> = {
            active: 0,
            overridden: 1,
            released: 2,
        };
        return [...holds].sort((a, b) => {
            const diff =
                (statusOrder[a.status] ?? 9) - (statusOrder[b.status] ?? 9);
            if (diff !== 0) return diff;
            if (a.severity !== b.severity) {
                return a.severity === "blocking" ? -1 : 1;
            }
            return b.id - a.id;
        });
    }, [holds]);

    const activeHoldCount = useMemo(
        () => holds.filter((h) => h.status === "active").length,
        [holds],
    );
    const blockingHoldCount = useMemo(
        () =>
            holds.filter(
                (h) =>
                    h.status === "active" &&
                    h.severity === "blocking" &&
                    h.blocks_enrollment,
            ).length,
        [holds],
    );

    const reqStats = useMemo(() => {
        let verified = 0;
        let waived = 0;
        let pending = 0;
        for (const req of enrollment.requirements) {
            if (req.status === "verified") verified += 1;
            else if (req.status === "waived") waived += 1;
            else pending += 1;
        }
        return { verified, waived, pending };
    }, [enrollment.requirements]);

    const handlePlaceHold = (e: FormEvent) => {
        e.preventDefault();
        if (!title.trim() || !reason.trim()) {
            toast.error("Please enter both a hold title and reason.");
            return;
        }

        setPlacingHold(true);
        router.post(
            storeHold.url(),
            {
                student_id: enrollment.student_id,
                office,
                severity,
                title: title.trim(),
                reason: reason.trim(),
                blocks_enrollment: blocksEnrollment,
                blocks_add_drop: blocksAddDrop,
                blocks_grade_release: blocksGradeRelease,
                expires_at: expiresAt || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Student hold placed.");
                    setTitle("");
                    setReason("");
                    setExpiresAt("");
                    setShowHoldForm(false);
                    router.reload({ only: ["enrollment"] });
                },
                onError: (errors) => {
                    const msg =
                        Object.values(errors)[0] ?? "Failed to place hold.";
                    toast.error(String(msg));
                },
                onFinish: () => setPlacingHold(false),
            },
        );
    };

    const handleReleaseHold = (hold: StudentHoldItem) => {
        setReleasingHoldId(hold.id);
        router.post(
            releaseHold.url(hold.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Released hold "${hold.title}".`);
                    router.reload({ only: ["enrollment"] });
                },
                onError: () => {
                    toast.error("Unable to release student hold.");
                },
                onFinish: () => setReleasingHoldId(null),
            },
        );
    };

    const handleOverrideHoldSubmit = () => {
        if (!overrideTarget) return;
        const trimmed = overrideReason.trim();
        if (trimmed.length < 5) {
            toast.error("Override reason must be at least 5 characters.");
            return;
        }

        setOverridingHold(true);
        router.post(
            overrideHold.url(overrideTarget.id),
            { override_reason: trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Hold overridden with audit trail.");
                    setOverrideTarget(null);
                    setOverrideReason("");
                    router.reload({ only: ["enrollment"] });
                },
                onError: (errors) => {
                    const msg =
                        Object.values(errors)[0] ?? "Unable to override hold.";
                    toast.error(String(msg));
                },
                onFinish: () => setOverridingHold(false),
            },
        );
    };

    const handleConfirmWaiveRequirement = () => {
        if (!waiveTarget) return;
        const trimmed = waiverReason.trim();
        if (!trimmed) {
            toast.error("Please provide a reason for waiving this requirement.");
            return;
        }
        onWaiveRequirement(waiveTarget.id, trimmed);
        setWaiveTarget(null);
        setWaiverReason("");
    };

    return (
        <div className="min-w-0 space-y-4">
            {/* Top Frame: Student Holds & Clearances */}
            <Frame>
                <FrameHeader className="flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className={
                                blockingHoldCount > 0
                                    ? "text-destructive"
                                    : activeHoldCount > 0
                                      ? "text-warning"
                                      : "text-emerald-600 dark:text-emerald-400"
                            }
                        >
                            {activeHoldCount > 0 ? (
                                <ShieldAlert />
                            ) : (
                                <ShieldCheck />
                            )}
                        </IconTile>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <FrameTitle>
                                    Student Holds & Clearances
                                </FrameTitle>
                                {blockingHoldCount > 0 && (
                                    <Badge
                                        size="xs"
                                        variant="destructive-light"
                                        className="tabular-nums"
                                    >
                                        {blockingHoldCount} Blocking
                                    </Badge>
                                )}
                                {activeHoldCount > 0 ? (
                                    <Badge
                                        size="xs"
                                        variant="warning-light"
                                        className="tabular-nums"
                                    >
                                        {activeHoldCount} Active
                                    </Badge>
                                ) : (
                                    <Badge size="xs" variant="success-light">
                                        Cleared
                                    </Badge>
                                )}
                            </div>
                            <FrameDescription>
                                Office clearances, active enrollment blocks, and
                                override history for{" "}
                                {enrollment.student.full_name}.
                            </FrameDescription>
                        </div>
                    </div>

                    {canManageHolds && (
                        <Button
                            type="button"
                            size="sm"
                            variant={showHoldForm ? "secondary" : "outline"}
                            onClick={() => setShowHoldForm((prev) => !prev)}
                        >
                            <Plus className="size-3.5" />
                            Place Hold
                            {showHoldForm ? (
                                <ChevronUp className="size-3.5" />
                            ) : (
                                <ChevronDown className="size-3.5" />
                            )}
                        </Button>
                    )}
                </FrameHeader>

                {/* Inline Collapsible Place Hold Form */}
                {showHoldForm && canManageHolds && (
                    <FramePanel className="bg-muted/25">
                        <form onSubmit={handlePlaceHold} className="space-y-3">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div className="space-y-1">
                                    <Label className="text-xs">Office</Label>
                                    <Select
                                        value={office}
                                        onValueChange={setOffice}
                                    >
                                        <SelectTrigger size="sm">
                                            <SelectValue placeholder="Select office" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {holdOffices.map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-xs">Severity</Label>
                                    <Select
                                        value={severity}
                                        onValueChange={(val) =>
                                            setSeverity(
                                                val === "warning"
                                                    ? "warning"
                                                    : "blocking",
                                            )
                                        }
                                    >
                                        <SelectTrigger size="sm">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="blocking">
                                                Blocking
                                            </SelectItem>
                                            <SelectItem value="warning">
                                                Warning Only
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1">
                                    <Label
                                        htmlFor="hold-expires-at"
                                        className="text-xs"
                                    >
                                        Expires On (Optional)
                                    </Label>
                                    <Input
                                        id="hold-expires-at"
                                        type="date"
                                        value={expiresAt}
                                        onChange={(e) =>
                                            setExpiresAt(e.target.value)
                                        }
                                        className="h-8 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="hold-title"
                                    className="text-xs"
                                >
                                    Hold Title
                                </Label>
                                <Input
                                    id="hold-title"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    placeholder="e.g., Unsettled Library Balance or Missing Form 137"
                                    required
                                    maxLength={160}
                                    className="h-8 text-xs"
                                />
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="hold-reason"
                                    className="text-xs"
                                >
                                    Reason & Resolution Instructions
                                </Label>
                                <Textarea
                                    id="hold-reason"
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    placeholder="Explain why this hold is placed and how the student can clear it..."
                                    required
                                    rows={2}
                                    className="text-xs"
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-4 pt-0.5">
                                <label className="flex cursor-pointer items-center gap-1.5 text-xs">
                                    <Checkbox
                                        checked={blocksEnrollment}
                                        onCheckedChange={(v) =>
                                            setBlocksEnrollment(Boolean(v))
                                        }
                                    />
                                    Blocks Enrollment
                                </label>
                                <label className="flex cursor-pointer items-center gap-1.5 text-xs">
                                    <Checkbox
                                        checked={blocksAddDrop}
                                        onCheckedChange={(v) =>
                                            setBlocksAddDrop(Boolean(v))
                                        }
                                    />
                                    Blocks Add / Drop
                                </label>
                                <label className="flex cursor-pointer items-center gap-1.5 text-xs">
                                    <Checkbox
                                        checked={blocksGradeRelease}
                                        onCheckedChange={(v) =>
                                            setBlocksGradeRelease(Boolean(v))
                                        }
                                    />
                                    Blocks Grade Release
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-2 border-t pt-2.5">
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    onClick={() => setShowHoldForm(false)}
                                    disabled={placingHold}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="xs"
                                    variant="destructive"
                                    disabled={placingHold}
                                >
                                    {placingHold ? (
                                        <Loader2 className="size-3 animate-spin" />
                                    ) : (
                                        <ShieldAlert className="size-3" />
                                    )}
                                    Confirm Place Hold
                                </Button>
                            </div>
                        </form>
                    </FramePanel>
                )}

                <FramePanel>
                    {sortedHolds.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed py-8 text-center">
                            <ShieldCheck className="size-7 text-emerald-500/60" />
                            <p className="text-xs font-medium text-foreground">
                                No holds on this student&apos;s record
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                All registrar, finance, library, and academic
                                clearances are currently clear.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {sortedHolds.map((hold) => {
                                const isActive = hold.status === "active";
                                const isReleasing = releasingHoldId === hold.id;
                                const scopeLabels = [
                                    hold.blocks_enrollment && "Enrollment",
                                    hold.blocks_add_drop && "Add/Drop",
                                    hold.blocks_grade_release && "Grades",
                                ].filter(Boolean) as string[];

                                return (
                                    <div
                                        key={hold.id}
                                        className={cn(
                                            "flex flex-col gap-3 p-3.5 sm:flex-row sm:items-start sm:justify-between",
                                            !isActive && "opacity-75",
                                        )}
                                    >
                                        <div className="min-w-0 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="text-sm font-semibold text-foreground">
                                                    {hold.title}
                                                </span>
                                                <Badge
                                                    size="xs"
                                                    variant="outline"
                                                >
                                                    {hold.office_label}
                                                </Badge>
                                                <Badge
                                                    size="xs"
                                                    variant={
                                                        hold.severity ===
                                                        "blocking"
                                                            ? "destructive-light"
                                                            : "warning-light"
                                                    }
                                                    className="capitalize"
                                                >
                                                    {hold.severity}
                                                </Badge>
                                                <Badge
                                                    size="xs"
                                                    variant={
                                                        hold.status === "active"
                                                            ? "destructive"
                                                            : hold.status ===
                                                                "overridden"
                                                              ? "warning-outline"
                                                              : "success-light"
                                                    }
                                                    className="capitalize"
                                                >
                                                    {hold.status}
                                                </Badge>
                                            </div>

                                            <p className="text-xs leading-relaxed text-muted-foreground">
                                                {hold.reason}
                                            </p>

                                            <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                                {scopeLabels.length > 0 && (
                                                    <span>
                                                        Blocks:{" "}
                                                        <span className="font-medium text-foreground">
                                                            {scopeLabels.join(
                                                                ", ",
                                                            )}
                                                        </span>
                                                    </span>
                                                )}
                                                <span>
                                                    · Placed by {hold.placed_by}{" "}
                                                    on{" "}
                                                    {formatShortDate(
                                                        hold.placed_at,
                                                    )}
                                                </span>
                                                {hold.expires_at && (
                                                    <span>
                                                        · Expires{" "}
                                                        {formatShortDate(
                                                            hold.expires_at,
                                                        )}
                                                    </span>
                                                )}
                                            </div>

                                            {hold.status === "overridden" &&
                                                hold.override_reason && (
                                                    <p className="rounded bg-warning/10 px-2 py-1 text-[11px] text-foreground">
                                                        Override by{" "}
                                                        {hold.overridden_by ??
                                                            "Administrator"}
                                                        :{" "}
                                                        {hold.override_reason}
                                                    </p>
                                                )}
                                            {hold.status === "released" && (
                                                <p className="text-[11px] text-emerald-700 dark:text-emerald-400">
                                                    Released by{" "}
                                                    {hold.released_by ??
                                                        "Administrator"}{" "}
                                                    on{" "}
                                                    {formatShortDate(
                                                        hold.released_at,
                                                    )}
                                                    {hold.release_notes
                                                        ? ` · ${hold.release_notes}`
                                                        : ""}
                                                </p>
                                            )}
                                        </div>

                                        {isActive && canManageHolds && (
                                            <div className="flex shrink-0 items-center gap-1.5">
                                                <Button
                                                    type="button"
                                                    size="xs"
                                                    variant="outline"
                                                    disabled={isReleasing}
                                                    onClick={() =>
                                                        handleReleaseHold(hold)
                                                    }
                                                >
                                                    {isReleasing ? (
                                                        <Loader2 className="size-3 animate-spin" />
                                                    ) : (
                                                        <Unlock className="size-3" />
                                                    )}
                                                    Release
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="xs"
                                                    variant="destructive"
                                                    disabled={isReleasing}
                                                    onClick={() => {
                                                        setOverrideTarget(hold);
                                                        setOverrideReason("");
                                                    }}
                                                >
                                                    <ShieldOff className="size-3" />
                                                    Override
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </FramePanel>
            </Frame>

            {/* Bottom Frame: Enrollment Requirements */}
            <Frame>
                <FrameHeader className="flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className="text-primary"
                        >
                            <FileCheck />
                        </IconTile>
                        <div className="min-w-0">
                            <FrameTitle>Enrollment Requirements</FrameTitle>
                            <FrameDescription>
                                Document checklist verification, evidence
                                review, and policy waivers.
                            </FrameDescription>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge
                            size="xs"
                            variant="success-light"
                            className="tabular-nums"
                        >
                            {reqStats.verified} Verified
                        </Badge>
                        {reqStats.waived > 0 && (
                            <Badge
                                size="xs"
                                variant="warning-light"
                                className="tabular-nums"
                            >
                                {reqStats.waived} Waived
                            </Badge>
                        )}
                        <Badge
                            size="xs"
                            variant={
                                reqStats.pending > 0
                                    ? "secondary"
                                    : "outline"
                            }
                            className="tabular-nums"
                        >
                            {reqStats.pending} Pending
                        </Badge>
                    </div>
                </FrameHeader>

                <FramePanel>
                    {enrollment.requirements.length === 0 ? (
                        <div className="rounded-lg border border-dashed py-8 text-center text-xs text-muted-foreground">
                            No document requirements configured for this
                            enrollment policy.
                        </div>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {enrollment.requirements.map((req) => {
                                const isBusy =
                                    reviewingRequirementId === req.id;
                                return (
                                    <div
                                        key={req.id}
                                        className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div className="min-w-0 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="text-sm font-semibold text-foreground">
                                                    {req.label}
                                                </span>
                                                <Badge
                                                    size="xs"
                                                    variant={
                                                        req.required
                                                            ? "primary-light"
                                                            : "outline"
                                                    }
                                                >
                                                    {req.required
                                                        ? "Required"
                                                        : "Optional"}
                                                </Badge>
                                                <Badge
                                                    size="xs"
                                                    variant={
                                                        req.status ===
                                                        "verified"
                                                            ? "success-light"
                                                            : req.status ===
                                                                "waived"
                                                              ? "warning-light"
                                                              : "secondary"
                                                    }
                                                    className="capitalize"
                                                >
                                                    {req.status}
                                                </Badge>
                                                {req.enforcement_step && (
                                                    <Badge
                                                        size="xs"
                                                        variant="outline"
                                                    >
                                                        Enforced at:{" "}
                                                        {req.enforcement_step.replaceAll(
                                                            "_",
                                                            " ",
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>

                                            {req.description && (
                                                <p className="text-xs text-muted-foreground">
                                                    {req.description}
                                                </p>
                                            )}

                                            {req.waiver_reason && (
                                                <p className="rounded bg-warning/10 px-2 py-1 text-[11px] text-foreground">
                                                    Waiver reason:{" "}
                                                    {req.waiver_reason}
                                                </p>
                                            )}

                                            <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                                {req.verified_at && (
                                                    <span>
                                                        Verified{" "}
                                                        {formatShortDate(
                                                            req.verified_at,
                                                        )}
                                                    </span>
                                                )}
                                                {req.waived_at && (
                                                    <span>
                                                        Waived{" "}
                                                        {formatShortDate(
                                                            req.waived_at,
                                                        )}
                                                    </span>
                                                )}
                                                {req.document_url && (
                                                    <a
                                                        href={req.document_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                                    >
                                                        <ExternalLink className="size-3" />
                                                        View Evidence
                                                    </a>
                                                )}
                                            </div>
                                        </div>

                                        {canReviewRequirements && (
                                            <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                                                {req.status !== "verified" && (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="default"
                                                        disabled={isBusy}
                                                        onClick={() =>
                                                            onVerifyRequirement(
                                                                req.id,
                                                            )
                                                        }
                                                    >
                                                        {isBusy ? (
                                                            <Loader2 className="size-3 animate-spin" />
                                                        ) : (
                                                            <CheckCircle2 className="size-3" />
                                                        )}
                                                        Verify
                                                    </Button>
                                                )}
                                                {req.status !== "waived" && (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="outline"
                                                        disabled={isBusy}
                                                        onClick={() => {
                                                            setWaiveTarget(req);
                                                            setWaiverReason(
                                                                req.waiver_reason ??
                                                                    "",
                                                            );
                                                        }}
                                                    >
                                                        <AlertTriangle className="size-3" />
                                                        Waive
                                                    </Button>
                                                )}
                                                {(req.status === "verified" ||
                                                    req.status ===
                                                        "waived") && (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="ghost"
                                                        disabled={isBusy}
                                                        onClick={() =>
                                                            onResetRequirement(
                                                                req.id,
                                                            )
                                                        }
                                                    >
                                                        {isBusy ? (
                                                            <Loader2 className="size-3 animate-spin" />
                                                        ) : (
                                                            <RotateCcw className="size-3" />
                                                        )}
                                                        Reset to Pending
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </FramePanel>
            </Frame>

            {/* Override Hold Dialog */}
            <AlertDialog
                open={overrideTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverrideTarget(null);
                        setOverrideReason("");
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Override Student Hold
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Overriding &ldquo;{overrideTarget?.title}&rdquo;
                            bypasses its block and logs an audited override
                            record. Provide a justification (minimum 5
                            characters).
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="space-y-1.5">
                        <Label
                            htmlFor="hold-override-reason"
                            className="text-xs"
                        >
                            Override Reason
                        </Label>
                        <Textarea
                            id="hold-override-reason"
                            value={overrideReason}
                            onChange={(e) => setOverrideReason(e.target.value)}
                            placeholder="e.g., Promissory note approved by Dean / Finance Director..."
                            rows={3}
                            className="text-xs"
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={overridingHold}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={
                                overridingHold ||
                                overrideReason.trim().length < 5
                            }
                            onClick={handleOverrideHoldSubmit}
                        >
                            {overridingHold ? (
                                <>
                                    <Loader2 className="size-3.5 animate-spin" />
                                    Overriding…
                                </>
                            ) : (
                                "Confirm Override"
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Waive Requirement Dialog */}
            <AlertDialog
                open={waiveTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setWaiveTarget(null);
                        setWaiverReason("");
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Waive Requirement</AlertDialogTitle>
                        <AlertDialogDescription>
                            Enter a clear reason for waiving &ldquo;
                            {waiveTarget?.label}&rdquo; for this enrollment.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="space-y-1.5">
                        <Label
                            htmlFor="requirement-waiver-reason"
                            className="text-xs"
                        >
                            Waiver Reason
                        </Label>
                        <Textarea
                            id="requirement-waiver-reason"
                            value={waiverReason}
                            onChange={(e) => setWaiverReason(e.target.value)}
                            placeholder="e.g., Student permitted to submit original copy before midterms..."
                            rows={3}
                            className="text-xs"
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={!waiverReason.trim()}
                            onClick={handleConfirmWaiveRequirement}
                        >
                            Confirm Waiver
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
