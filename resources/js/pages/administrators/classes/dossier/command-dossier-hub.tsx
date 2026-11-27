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
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { create, destroy, edit, forceDestroy, index, restore, show } from "@/routes/administrators/classes";
import { Link, router } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    Archive,
    ArrowLeft,
    CalendarCheck2,
    CalendarDays,
    CheckCircle2,
    Copy,
    History,
    Layers,
    Megaphone,
    MoreHorizontal,
    Pencil,
    RotateCcw,
    Settings2,
    ShieldCheck,
    Trash2,
    UserPlus,
    UserRound,
    Users,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { patchQuickSettings } from "../crud/api";
import type { ClassPolicySettings, DossierHealth } from "../crud/types";
import { deliveryModeLabel } from "../lib/class-defaults";
import { MoveStudentDialog } from "../record/move-student-dialog";
import { parseTimeToMinutes } from "../record/schedule-utils";
import type { ClassShowCapabilities, ClassShowData, ClassShowProps, EnrollmentRow, RawScheduleEntry } from "../record/types";
import { postBulkClassAction, WorkspaceApiError } from "../workspace/api";
import { DossierGradesTab } from "./dossier-grades-tab";
import { DossierAttendanceTab, DossierAuditTab, DossierPostsTab, DossierSettingsTab, formatShortDate } from "./dossier-oversight-tabs";
import { DossierRosterTab } from "./dossier-roster-tab";
import { DossierScheduleRail, DossierScheduleTab } from "./dossier-schedule-rail";

const DEFAULT_CAPABILITIES: ClassShowCapabilities = {
    create: false,
    update: false,
    delete: false,
    restore: false,
    force_delete: false,
};

function computeFallbackHealth(
    classItem: ClassShowData,
    enrollments: EnrollmentRow[],
    rawSchedules: RawScheduleEntry[],
): DossierHealth {
    const activeEnrollments = enrollments.filter(
        (e) => !e.deleted_at && e.enrollment_status !== "dropped" && e.enrollment_status !== "withdrawn",
    );
    const activeCount = activeEnrollments.length;
    const maxSlots = classItem.maximum_slots ?? 0;
    const over = maxSlots > 0 ? Math.max(0, activeCount - maxSlots) : 0;

    let scheduledMinutes = 0;
    let hasDraft = false;
    for (const s of rawSchedules) {
        const start = parseTimeToMinutes(s.start_time);
        const end = parseTimeToMinutes(s.end_time);
        if (Number.isFinite(start) && Number.isFinite(end) && end > start) {
            scheduledMinutes += end - start;
        }
        if (s.status === "draft") hasDraft = true;
    }

    const scheduleStatus: DossierHealth["schedule"]["status"] =
        rawSchedules.length === 0 ? "unscheduled" : hasDraft ? "draft" : "published";

    let verified = 0;
    let finalized = 0;
    let inProgress = 0;
    let pending = 0;
    for (const e of activeEnrollments) {
        if (e.is_grades_finalized) finalized++;
        if (e.is_grades_verified) {
            verified++;
        } else if (e.total_average !== null || e.prelim_grade !== null || e.midterm_grade !== null || e.finals_grade !== null) {
            inProgress++;
        } else {
            pending++;
        }
    }

    return {
        seats: {
            active: activeCount,
            max: maxSlots,
            over,
            waitlisted: 0,
            unassigned_cohort: 0,
        },
        schedule: {
            scheduled_hours: Math.round((scheduledMinutes / 60) * 10) / 10,
            required_hours: null,
            status: scheduleStatus,
            conflict_count: 0,
            conflict_summary: null,
            pending_notices: 0,
            revision_label: "Rev 1",
        },
        grades: {
            total: activeCount,
            verified,
            finalized,
            in_progress: inProgress,
            pending,
        },
        attendance: {
            average_rate: null,
            sessions_held: 0,
            sessions_expected: null,
        },
        posts: { count: 0 },
        last_activity_at: null,
    };
}

function formatRelativeTime(value: string | null | undefined): string {
    if (!value) return "No recent edits";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    const diffSec = Math.round((Date.now() - parsed.getTime()) / 1000);
    if (diffSec < 60) return "Just now";
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;
    if (diffSec < 604800) return `${Math.floor(diffSec / 86400)}d ago`;
    return formatShortDate(value);
}

function buildInitialPolicyState(classItem: ClassShowData): ClassPolicySettings {
    const features = classItem.settings?.features;
    return {
        enable_announcements: Boolean(features?.enable_announcements),
        enable_grade_visibility: Boolean(features?.enable_grade_visibility),
        enable_attendance_tracking: Boolean(features?.enable_attendance_tracking),
        enable_online_resources: features?.enable_online_resources ?? true,
        allow_late_submissions: Boolean(features?.allow_late_submissions),
        enable_discussion_board: Boolean(features?.enable_discussion_board),
        notify_students_on_schedule_changes: features?.notify_students_on_schedule_changes ?? true,
    };
}

export function CommandDossierHub({
    class: classItem,
    capabilities: rawCapabilities,
    enrollments,
    trashed_enrollments,
    raw_schedules,
    schedule_revision,
    rooms,
    faculties = [],
    transferable_classes,
    dossier,
}: ClassShowProps) {
    const capabilities = rawCapabilities ?? DEFAULT_CAPABILITIES;
    const canEditLive = capabilities.update && !classItem.is_archived;

    const [activeTab, setActiveTab] = useState("roster");
    const [enrollDialogOpen, setEnrollDialogOpen] = useState(false);
    const [scheduleEditing, setScheduleEditing] = useState(false);
    const [studentToMove, setStudentToMove] = useState<EnrollmentRow | null>(null);

    // Inline Header Popover state
    const [facultyPopoverOpen, setFacultyPopoverOpen] = useState(false);
    const [facultyDraft, setFacultyDraft] = useState<string>(
        classItem.faculty_id ?? (classItem.faculty?.id ? String(classItem.faculty.id) : ""),
    );
    const [savingFaculty, setSavingFaculty] = useState(false);

    const [capacityPopoverOpen, setCapacityPopoverOpen] = useState(false);
    const [capacityDraft, setCapacityDraft] = useState<string>(String(classItem.maximum_slots ?? 0));
    const [savingCapacity, setSavingCapacity] = useState(false);

    // Lifecycle confirmation dialogs
    const [archiveConfirmOpen, setArchiveConfirmOpen] = useState(false);
    const [forceDeleteConfirmOpen, setForceDeleteConfirmOpen] = useState(false);
    const [lifecycleBusy, setLifecycleBusy] = useState(false);

    // Optimistic quick policy toggles
    const [policyState, setPolicyState] = useState<ClassPolicySettings>(() => buildInitialPolicyState(classItem));
    const [savingPolicyKey, setSavingPolicyKey] = useState<keyof ClassPolicySettings | null>(null);

    useEffect(() => {
        setPolicyState(buildInitialPolicyState(classItem));
        setFacultyDraft(classItem.faculty_id ?? (classItem.faculty?.id ? String(classItem.faculty.id) : ""));
        setCapacityDraft(String(classItem.maximum_slots ?? 0));
    }, [classItem]);

    const health = useMemo<DossierHealth>(
        () => dossier?.health ?? computeFallbackHealth(classItem, enrollments, raw_schedules),
        [dossier?.health, classItem, enrollments, raw_schedules],
    );

    const siblingSections = dossier?.sibling_sections ?? [];
    const waitlist = dossier?.waitlist ?? [];
    const scheduleChanges = dossier?.schedule_changes ?? [];
    const activity = dossier?.activity ?? [];
    const attendanceSessions = dossier?.attendance_sessions ?? [];
    const posts = dossier?.posts ?? [];

    const reloadPage = () => {
        router.reload();
    };

    const handleTogglePolicy = async (key: keyof ClassPolicySettings, value: boolean) => {
        const previous = policyState[key];
        setPolicyState((prev) => ({ ...prev, [key]: value }));
        setSavingPolicyKey(key);
        try {
            const response = await patchQuickSettings(classItem.id, { [key]: value });
            if (response.settings) {
                setPolicyState(response.settings);
            }
            toast.success(response.message || "Policy updated.");
        } catch (error) {
            setPolicyState((prev) => ({ ...prev, [key]: previous }));
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not update policy.");
        } finally {
            setSavingPolicyKey(null);
        }
    };

    const handleSaveFaculty = async () => {
        setSavingFaculty(true);
        try {
            const response = await postBulkClassAction({
                class_ids: [classItem.id],
                action: "assign_faculty",
                faculty_id: facultyDraft || null,
            });
            toast.success(response.message || "Faculty assignment updated.");
            setFacultyPopoverOpen(false);
            reloadPage();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not update faculty assignment.");
        } finally {
            setSavingFaculty(false);
        }
    };

    const handleSaveCapacity = async () => {
        const parsed = Number(capacityDraft);
        if (!capacityDraft.trim() || !Number.isInteger(parsed) || parsed < 0) {
            toast.error("Enter a valid non-negative integer capacity.");
            return;
        }
        setSavingCapacity(true);
        try {
            const response = await postBulkClassAction({
                class_ids: [classItem.id],
                action: "set_capacity",
                maximum_slots: parsed,
            });
            toast.success(response.message || "Section capacity updated.");
            setCapacityPopoverOpen(false);
            reloadPage();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not update capacity.");
        } finally {
            setSavingCapacity(false);
        }
    };

    const handleArchive = () => {
        router.delete(destroy.url(classItem.id), {
            preserveScroll: true,
            onStart: () => setLifecycleBusy(true),
            onFinish: () => {
                setLifecycleBusy(false);
                setArchiveConfirmOpen(false);
            },
            onSuccess: () => toast.success(`${classItem.record_title} archived.`),
            onError: () => toast.error("Could not archive this class."),
        });
    };

    const handleRestore = () => {
        router.post(
            restore.url(classItem.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setLifecycleBusy(true),
                onFinish: () => setLifecycleBusy(false),
                onSuccess: () => toast.success(`${classItem.record_title} restored.`),
                onError: () => toast.error("Could not restore this class."),
            },
        );
    };

    const handleForceDelete = () => {
        router.delete(forceDestroy.url(classItem.id), {
            onStart: () => setLifecycleBusy(true),
            onFinish: () => {
                setLifecycleBusy(false);
                setForceDeleteConfirmOpen(false);
            },
            onSuccess: () => toast.success(`${classItem.record_title} permanently deleted.`),
            onError: () => toast.error("Could not permanently delete this class."),
        });
    };

    const seatRatioPct = health.seats.max > 0 ? Math.min(100, Math.round((health.seats.active / health.seats.max) * 100)) : 0;
    const isOverCapacity = health.seats.over > 0;
    const gradeVerifiedPct = health.grades.total > 0 ? Math.round((health.grades.verified / health.grades.total) * 100) : 0;
    const hasLifecycleOverflow =
        (!classItem.is_archived && capabilities.delete) ||
        (classItem.is_archived && capabilities.restore) ||
        capabilities.force_delete;

    return (
        <div className="text-foreground mx-auto flex w-full max-w-[1600px] min-w-0 flex-col gap-5">
            {/* 1. Compact Lifecycle Command Header */}
            <header className="bg-card flex flex-col gap-4 rounded-xl border p-4 shadow-2xs sm:p-5">
                {/* Row 1: Breadcrumb, Status, Sibling Section Switcher & Primary/Secondary Action Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-wrap items-center gap-2.5">
                        <nav aria-label="Breadcrumb" className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                            <Link
                                href={index.url()}
                                className="hover:text-foreground inline-flex items-center gap-1 transition-colors"
                            >
                                <ArrowLeft className="size-3.5" aria-hidden="true" />
                                Classes
                            </Link>
                            <span aria-hidden="true">/</span>
                            <span className="text-foreground font-mono font-semibold">
                                {classItem.subject_code} · Sec {classItem.section}
                            </span>
                        </nav>

                        <Badge
                            variant="outline"
                            className={cn(
                                "text-[11px]",
                                classItem.is_archived
                                    ? "border-amber-600/35 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                    : "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                            )}
                        >
                            {classItem.is_archived ? "Archived" : "Active"}
                        </Badge>

                        {/* Sibling Section Switcher */}
                        {siblingSections.length > 1 && (
                            <nav
                                aria-label="Sibling sections"
                                className="bg-muted/70 inline-flex flex-wrap items-center gap-0.5 rounded-full border p-0.5"
                            >
                                {siblingSections.map((sib) => {
                                    const sibOver = sib.maximum_slots > 0 && sib.students_count > sib.maximum_slots;
                                    return (
                                        <Link
                                            key={sib.id}
                                            href={show.url(sib.id)}
                                            aria-current={sib.is_current ? "page" : undefined}
                                            className={cn(
                                                "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold transition-colors",
                                                sib.is_current
                                                    ? "bg-background text-foreground shadow-2xs"
                                                    : "text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            <span>Sec {sib.section}</span>
                                            <span
                                                className={cn(
                                                    "font-mono text-[10px] tabular-nums",
                                                    sibOver ? "text-rose-600 dark:text-rose-400" : "opacity-75",
                                                )}
                                            >
                                                ({sib.students_count}/{sib.maximum_slots || "∞"})
                                            </span>
                                        </Link>
                                    );
                                })}
                            </nav>
                        )}
                    </div>

                    {/* Action Bar (1 Primary CTA + Clear Secondary Hierarchy) */}
                    <div className="flex flex-wrap items-center gap-2">
                        {canEditLive && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => {
                                    setActiveTab("roster");
                                    setEnrollDialogOpen(true);
                                }}
                            >
                                <UserPlus className="size-3.5" aria-hidden="true" />
                                Enroll Student
                            </Button>
                        )}

                        {canEditLive && (
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={<Link href={edit.url(classItem.id)} />}
                            >
                                <Pencil className="size-3.5" aria-hidden="true" />
                                Edit class
                            </Button>
                        )}

                        {capabilities.create !== false && (
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={<Link href={create.url({ query: { duplicate_from: classItem.id } })} />}
                            >
                                <Copy className="size-3.5" aria-hidden="true" />
                                Duplicate
                            </Button>
                        )}

                        {capabilities.create !== false && !classItem.is_archived && <Button
                            variant="outline"
                            size="sm"
                            nativeButton={false}
                            render={
                                <Link
                                    href={create.url({
                                        query: { mode: "batch", blueprint_class_id: classItem.id },
                                    })}
                                />
                            }
                        >
                            <Layers className="size-3.5" aria-hidden="true" />
                            Manage All Sections
                        </Button>}

                        {hasLifecycleOverflow && (
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            aria-label="Class lifecycle actions"
                                            disabled={lifecycleBusy}
                                        />
                                    }
                                >
                                    <MoreHorizontal className="size-4" aria-hidden="true" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-48">
                                    {!classItem.is_archived && capabilities.delete && (
                                        <DropdownMenuItem onClick={() => setArchiveConfirmOpen(true)}>
                                            <Archive className="size-4" aria-hidden="true" />
                                            Archive class
                                        </DropdownMenuItem>
                                    )}
                                    {classItem.is_archived && capabilities.restore && (
                                        <DropdownMenuItem onClick={handleRestore}>
                                            <RotateCcw className="size-4" aria-hidden="true" />
                                            Restore class
                                        </DropdownMenuItem>
                                    )}
                                    {capabilities.force_delete && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() => setForceDeleteConfirmOpen(true)}
                                            >
                                                <Trash2 className="size-4" aria-hidden="true" />
                                                Force delete
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>

                {/* Row 2: Identity + Inline-Editable Faculty & Capacity Chips */}
                <div className="flex flex-col justify-between gap-3 border-t pt-3.5 lg:flex-row lg:items-center">
                    <div className="min-w-0 space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-balance sm:text-2xl">
                                {classItem.record_title}
                            </h1>
                            <Badge variant="outline" className="text-xs">
                                {classItem.classification === "shs" ? "Senior High School" : "College"}
                            </Badge>
                            <Badge variant="secondary" className="text-xs">
                                {deliveryModeLabel(classItem.delivery_summary ?? classItem.delivery_mode)}
                            </Badge>
                        </div>
                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs">
                            {classItem.subject_title &&
                                classItem.subject_title !== "N/A" &&
                                classItem.subject_title !== classItem.record_title && (
                                    <span className="text-foreground/85 font-medium">{classItem.subject_title}</span>
                                )}
                            {classItem.associated_courses && (
                                <span>
                                    {classItem.classification === "shs" ? "Track/Strand:" : "Cohort:"}{" "}
                                    <strong className="text-foreground font-medium">{classItem.associated_courses}</strong>
                                </span>
                            )}
                            <span className="font-mono">
                                {classItem.school_year} · {classItem.semester}
                            </span>
                            {classItem.year_level && <span>{classItem.year_level}</span>}
                            {classItem.start_date && <span>Starts {formatShortDate(classItem.start_date)}</span>}
                        </div>
                    </div>

                    {/* Inline-Editable Header Chips */}
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Faculty Chip */}
                        {canEditLive ? (
                            <Popover open={facultyPopoverOpen} onOpenChange={setFacultyPopoverOpen}>
                                <PopoverTrigger asChild>
                                    <button
                                        type="button"
                                        className="border-border bg-muted/40 hover:border-primary/50 hover:bg-primary/5 inline-flex h-8 items-center gap-2 rounded-lg border border-dashed px-3 text-xs font-medium transition-colors"
                                    >
                                        <UserRound className="text-muted-foreground size-3.5" aria-hidden="true" />
                                        <span className="text-muted-foreground uppercase tracking-wide text-[10px]">Faculty</span>
                                        <span className="max-w-44 truncate font-semibold">
                                            {classItem.faculty?.name || "Unassigned"}
                                        </span>
                                        <Pencil className="text-primary size-3" aria-hidden="true" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent align="end" className="w-72 space-y-3 p-3.5">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="inline-faculty-select" className="text-xs">
                                            Assign Lead Faculty
                                        </Label>
                                        <select
                                            id="inline-faculty-select"
                                            value={facultyDraft}
                                            onChange={(event) => setFacultyDraft(event.target.value)}
                                            className="border-input bg-background text-foreground focus-visible:ring-ring h-9 w-full rounded-md border px-2.5 text-xs focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <option value="">Unassigned</option>
                                            {faculties.map((f) => (
                                                <option key={String(f.id)} value={String(f.id)}>
                                                    {f.label}
                                                    {typeof f.load_hours === "number" ? ` (${f.load_hours}h)` : ""}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex justify-end gap-1.5">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="xs"
                                            onClick={() => setFacultyPopoverOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            size="xs"
                                            disabled={savingFaculty}
                                            onClick={handleSaveFaculty}
                                        >
                                            {savingFaculty ? "Saving…" : "Save"}
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        ) : (
                            <div className="border-border bg-muted/40 inline-flex h-8 items-center gap-2 rounded-lg border px-3 text-xs">
                                <UserRound className="text-muted-foreground size-3.5" aria-hidden="true" />
                                <span className="text-muted-foreground text-[10px] uppercase">Faculty</span>
                                <span className="font-semibold">{classItem.faculty?.name || "Unassigned"}</span>
                            </div>
                        )}

                        {/* Capacity Chip */}
                        {canEditLive ? (
                            <Popover open={capacityPopoverOpen} onOpenChange={setCapacityPopoverOpen}>
                                <PopoverTrigger asChild>
                                    <button
                                        type="button"
                                        className={cn(
                                            "inline-flex h-8 items-center gap-2 rounded-lg border border-dashed px-3 text-xs font-medium transition-colors",
                                            isOverCapacity
                                                ? "border-rose-500/40 bg-rose-500/10 text-rose-700 hover:border-rose-500 dark:text-rose-300"
                                                : "border-border bg-muted/40 hover:border-primary/50 hover:bg-primary/5",
                                        )}
                                    >
                                        <span className="text-muted-foreground text-[10px] uppercase tracking-wide">Capacity</span>
                                        <span className="font-mono font-semibold tabular-nums">
                                            {health.seats.active} / {health.seats.max || "∞"}
                                        </span>
                                        <Pencil className="text-primary size-3" aria-hidden="true" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent align="end" className="w-64 space-y-3 p-3.5">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="inline-capacity-input" className="text-xs">
                                            Section Max Capacity
                                        </Label>
                                        <Input
                                            id="inline-capacity-input"
                                            type="number"
                                            min={0}
                                            value={capacityDraft}
                                            onChange={(event) => setCapacityDraft(event.target.value)}
                                            className="h-8 font-mono text-xs"
                                        />
                                        <p className="text-muted-foreground text-[11px]">
                                            Currently {health.seats.active} active seats. Use 0 for unlimited.
                                        </p>
                                    </div>
                                    <div className="flex justify-end gap-1.5">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="xs"
                                            onClick={() => setCapacityPopoverOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            size="xs"
                                            disabled={savingCapacity}
                                            onClick={handleSaveCapacity}
                                        >
                                            {savingCapacity ? "Applying…" : "Apply"}
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        ) : (
                            <div className="border-border bg-muted/40 inline-flex h-8 items-center gap-2 rounded-lg border px-3 text-xs">
                                <span className="text-muted-foreground text-[10px] uppercase">Capacity</span>
                                <span className="font-mono font-semibold tabular-nums">
                                    {health.seats.active} / {health.seats.max || "∞"}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Archived Banner */}
                {classItem.is_archived && (
                    <div
                        className="bg-muted/40 flex flex-col gap-3 rounded-lg border p-3.5 sm:flex-row sm:items-center sm:justify-between"
                        role="status"
                    >
                        <div className="flex items-start gap-2.5 text-xs">
                            <Archive className="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <div className="space-y-0.5">
                                <p className="font-semibold">This class is archived</p>
                                <p className="text-muted-foreground">
                                    Live editing, enrollment changes, and policy toggles are locked until restored.
                                </p>
                            </div>
                        </div>
                        {capabilities.restore && (
                            <Button variant="outline" size="sm" disabled={lifecycleBusy} onClick={handleRestore}>
                                <RotateCcw className="size-3.5" aria-hidden="true" />
                                {lifecycleBusy ? "Restoring…" : "Restore class"}
                            </Button>
                        )}
                    </div>
                )}
            </header>

            {/* 2. 4-Card Operational Health Strip */}
            <section
                aria-label="Operational Health Summary"
                className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                {/* Card 1: Seats & Waitlist */}
                <div
                    className={cn(
                        "bg-card flex flex-col justify-between gap-2 rounded-xl border p-3.5 shadow-2xs",
                        isOverCapacity && "border-l-4 border-l-rose-500",
                    )}
                >
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide">
                            <Users className="size-3.5" aria-hidden="true" />
                            Seats &amp; Waitlist
                        </span>
                        {isOverCapacity ? (
                            <Badge
                                variant="outline"
                                className="border-rose-500/40 bg-rose-500/10 font-mono text-[10px] text-rose-700 dark:text-rose-300"
                            >
                                +{health.seats.over} Over Cap
                            </Badge>
                        ) : health.seats.waitlisted > 0 ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500/40 bg-amber-500/10 font-mono text-[10px] text-amber-700 dark:text-amber-300"
                            >
                                +{health.seats.waitlisted} waitlisted
                            </Badge>
                        ) : null}
                    </div>
                    <div className="flex items-baseline justify-between gap-2">
                        <div className="font-mono text-lg font-bold tabular-nums">
                            {health.seats.active} / {health.seats.max || "∞"}{" "}
                            <span className="text-muted-foreground font-sans text-xs font-medium">seats</span>
                        </div>
                        {health.seats.max > 0 && (
                            <span
                                className={cn(
                                    "font-mono text-xs font-semibold tabular-nums",
                                    isOverCapacity ? "text-rose-600 dark:text-rose-400" : "text-muted-foreground",
                                )}
                            >
                                {Math.round((health.seats.active / health.seats.max) * 100)}%
                            </span>
                        )}
                    </div>
                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                        <div
                            className={cn("h-full rounded-full transition-all", isOverCapacity ? "bg-rose-500" : "bg-primary")}
                            style={{ width: `${health.seats.max > 0 ? seatRatioPct : 0}%` }}
                        />
                    </div>
                    <p className="text-muted-foreground text-xs tabular-nums">
                        {health.seats.waitlisted > 0 ? `+${health.seats.waitlisted} waitlisted` : "No waitlist"}
                        {health.seats.unassigned_cohort > 0 ? ` · ${health.seats.unassigned_cohort} unassigned cohort` : ""}
                    </p>
                </div>

                {/* Card 2: Schedule & Room Health */}
                <div
                    className={cn(
                        "bg-card flex flex-col justify-between gap-2 rounded-xl border p-3.5 shadow-2xs",
                        health.schedule.conflict_count > 0 && "border-l-4 border-l-rose-500",
                    )}
                >
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide">
                            <CalendarDays className="size-3.5" aria-hidden="true" />
                            Schedule &amp; Room
                        </span>
                        <Badge
                            variant="outline"
                            className={cn(
                                "text-[10px] capitalize",
                                health.schedule.status === "published"
                                    ? "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                    : health.schedule.status === "draft"
                                      ? "border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                      : "text-muted-foreground",
                            )}
                        >
                            {health.schedule.status}
                        </Badge>
                    </div>
                    <div className="flex items-baseline justify-between gap-2">
                        <div className="font-mono text-lg font-bold tabular-nums">
                            {health.schedule.scheduled_hours}h{" "}
                            <span className="text-muted-foreground text-xs font-medium">
                                / {health.schedule.required_hours !== null ? `${health.schedule.required_hours}h` : "—"}
                            </span>
                        </div>
                        {health.schedule.conflict_count > 0 ? (
                            <Badge
                                variant="outline"
                                className="gap-1 border-rose-500/40 bg-rose-500/10 text-[10px] text-rose-700 dark:text-rose-300"
                            >
                                <AlertTriangle className="size-2.5" aria-hidden="true" />
                                {health.schedule.conflict_count} {health.schedule.conflict_count === 1 ? "Conflict" : "Conflicts"}
                            </Badge>
                        ) : (
                            <Badge
                                variant="outline"
                                className="gap-1 border-emerald-600/30 bg-emerald-500/10 text-[10px] text-emerald-700 dark:text-emerald-300"
                            >
                                <CheckCircle2 className="size-2.5" aria-hidden="true" />
                                All Clear
                            </Badge>
                        )}
                    </div>
                    <div className="text-muted-foreground flex items-center justify-between gap-2 text-xs">
                        <span className="font-mono font-medium">{health.schedule.revision_label}</span>
                        {health.schedule.pending_notices > 0 ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500/40 bg-amber-500/10 text-[10px] text-amber-700 tabular-nums dark:text-amber-300"
                            >
                                {health.schedule.pending_notices} pending notices
                            </Badge>
                        ) : (
                            <span>Notices up to date</span>
                        )}
                    </div>
                </div>

                {/* Card 3: Grade Governance */}
                <div className="bg-card flex flex-col justify-between gap-2 rounded-xl border p-3.5 shadow-2xs">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide">
                            <ShieldCheck className="size-3.5" aria-hidden="true" />
                            Grade Governance
                        </span>
                        <span className="font-mono text-xs font-semibold text-emerald-700 tabular-nums dark:text-emerald-400">
                            {gradeVerifiedPct}%
                        </span>
                    </div>
                    <div className="font-mono text-lg font-bold tabular-nums">
                        {health.grades.verified}/{health.grades.total}{" "}
                        <span className="text-muted-foreground font-sans text-xs font-medium">Verified</span>
                    </div>
                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                        <div
                            className="h-full rounded-full bg-emerald-600 transition-all"
                            style={{ width: `${gradeVerifiedPct}%` }}
                        />
                    </div>
                    <p className="text-muted-foreground text-xs tabular-nums">
                        {health.grades.finalized} Finalized · {health.grades.pending} Pending
                    </p>
                </div>

                {/* Card 4: Attendance & Activity */}
                <div className="bg-card flex flex-col justify-between gap-2 rounded-xl border p-3.5 shadow-2xs">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide">
                            <Activity className="size-3.5" aria-hidden="true" />
                            Attendance &amp; Activity
                        </span>
                        <Badge variant="secondary" className="text-[10px] tabular-nums">
                            {health.posts.count} {health.posts.count === 1 ? "post" : "posts"}
                        </Badge>
                    </div>
                    <div className="flex items-baseline justify-between gap-2">
                        <div className="font-mono text-lg font-bold tabular-nums">
                            {health.attendance.average_rate !== null ? `${Math.round(health.attendance.average_rate)}%` : "—"}{" "}
                            <span className="text-muted-foreground font-sans text-xs font-medium">avg</span>
                        </div>
                        <span className="text-muted-foreground font-mono text-xs tabular-nums">
                            {health.attendance.sessions_held}
                            {health.attendance.sessions_expected ? `/${health.attendance.sessions_expected}` : ""} sessions
                        </span>
                    </div>
                    <p className="text-muted-foreground truncate text-xs">
                        Last activity: <span className="text-foreground font-medium">{formatRelativeTime(health.last_activity_at)}</span>
                    </p>
                </div>
            </section>

            {/* 3. Main 2-Column Body Layout */}
            <div className="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                {/* Left Main Column: 7 Operational Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="min-w-0 gap-4" key={classItem.id}>
                    <div className="min-w-0 border-b">
                        <TabsList
                            variant="underline"
                            aria-label="Class dossier sections"
                            className="h-auto w-full flex-wrap justify-start gap-x-2 gap-y-0 p-0"
                        >
                            <TabsTrigger value="roster" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <Users className="size-3.5" aria-hidden="true" />
                                Roster &amp; Waitlist
                                <span className="bg-muted text-muted-foreground rounded-md px-1.5 font-mono text-[11px] tabular-nums">
                                    {health.seats.active}
                                    {waitlist.length > 0 ? ` + ${waitlist.length}` : ""}
                                </span>
                            </TabsTrigger>
                            <TabsTrigger value="schedule" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <CalendarDays className="size-3.5" aria-hidden="true" />
                                Schedule &amp; History
                                {health.schedule.conflict_count > 0 && (
                                    <span className="rounded-md bg-rose-500/15 px-1.5 font-mono text-[11px] text-rose-700 tabular-nums dark:text-rose-300">
                                        {health.schedule.conflict_count}
                                    </span>
                                )}
                            </TabsTrigger>
                            <TabsTrigger value="grades" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <ShieldCheck className="size-3.5" aria-hidden="true" />
                                Grades &amp; Verify
                                <span className="bg-muted text-muted-foreground rounded-md px-1.5 font-mono text-[11px] tabular-nums">
                                    {health.grades.verified}/{health.grades.total}
                                </span>
                            </TabsTrigger>
                            <TabsTrigger value="attendance" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <CalendarCheck2 className="size-3.5" aria-hidden="true" />
                                Attendance
                            </TabsTrigger>
                            <TabsTrigger value="posts" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <Megaphone className="size-3.5" aria-hidden="true" />
                                Posts
                                {posts.length > 0 && (
                                    <span className="bg-muted text-muted-foreground rounded-md px-1.5 font-mono text-[11px] tabular-nums">
                                        {posts.length}
                                    </span>
                                )}
                            </TabsTrigger>
                            <TabsTrigger value="settings" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <Settings2 className="size-3.5" aria-hidden="true" />
                                Settings
                            </TabsTrigger>
                            <TabsTrigger value="audit" className="data-active:text-primary h-10 grow-0 gap-1.5 rounded-none px-3 text-xs sm:text-sm">
                                <History className="size-3.5" aria-hidden="true" />
                                Audit Log
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="roster" className="min-w-0">
                        <DossierRosterTab
                            classItem={classItem}
                            enrollments={enrollments}
                            trashedEnrollments={trashed_enrollments}
                            waitlist={waitlist}
                            siblingSections={siblingSections}
                            transferableClasses={transferable_classes || []}
                            capabilities={capabilities}
                            enrollDialogOpen={enrollDialogOpen}
                            onSetEnrollDialogOpen={setEnrollDialogOpen}
                            onMoveStudent={setStudentToMove}
                            onRefresh={reloadPage}
                        />
                    </TabsContent>

                    <TabsContent value="schedule" className="min-w-0">
                        <DossierScheduleTab
                            classItem={classItem}
                            rawSchedules={raw_schedules}
                            scheduleRevision={schedule_revision}
                            rooms={rooms}
                            health={health}
                            scheduleChanges={scheduleChanges}
                            notifyEnrolled={policyState.notify_students_on_schedule_changes}
                            canUpdate={capabilities.update}
                            scheduleEditing={scheduleEditing}
                            onSetScheduleEditing={setScheduleEditing}
                            onRefresh={reloadPage}
                        />
                    </TabsContent>

                    <TabsContent value="grades" className="min-w-0">
                        <DossierGradesTab
                            classItem={classItem}
                            enrollments={enrollments}
                            canUpdate={capabilities.update}
                            onRefresh={reloadPage}
                        />
                    </TabsContent>

                    <TabsContent value="attendance" className="min-w-0">
                        <DossierAttendanceTab
                            sessions={attendanceSessions}
                            sessionsHeld={health.attendance.sessions_held}
                            averageRate={health.attendance.average_rate}
                            sessionsExpected={health.attendance.sessions_expected}
                        />
                    </TabsContent>

                    <TabsContent value="posts" className="min-w-0">
                        <DossierPostsTab posts={posts} />
                    </TabsContent>

                    <TabsContent value="settings" className="min-w-0">
                        <DossierSettingsTab
                            classItem={classItem}
                            policyState={policyState}
                            savingPolicyKey={savingPolicyKey}
                            canUpdate={capabilities.update}
                            onTogglePolicy={handleTogglePolicy}
                        />
                    </TabsContent>

                    <TabsContent value="audit" className="min-w-0">
                        <DossierAuditTab activity={activity} />
                    </TabsContent>
                </Tabs>

                {/* Right Sticky Operational Rail */}
                <DossierScheduleRail
                    classItem={classItem}
                    rawSchedules={raw_schedules}
                    scheduleRevision={schedule_revision}
                    rooms={rooms}
                    health={health}
                    scheduleChanges={scheduleChanges}
                    policyState={policyState}
                    savingPolicyKey={savingPolicyKey}
                    canUpdate={capabilities.update}
                    onTogglePolicy={handleTogglePolicy}
                    onOpenScheduleTab={() => {
                        setScheduleEditing(false);
                        setActiveTab("schedule");
                    }}
                    onEditSchedule={() => {
                        setActiveTab("schedule");
                        setScheduleEditing(true);
                    }}
                    onRefresh={reloadPage}
                />
            </div>

            {/* Move Student Dialog */}
            {studentToMove && (
                <MoveStudentDialog
                    key={studentToMove.id}
                    student={studentToMove}
                    transferableClasses={transferable_classes || []}
                    currentClassId={classItem.id}
                    onClose={() => setStudentToMove(null)}
                />
            )}

            {/* Archive Confirmation Dialog */}
            <AlertDialog open={archiveConfirmOpen} onOpenChange={setArchiveConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Archive {classItem.record_title}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Archiving moves this class, its schedule, and active roster out of the live list. You can restore it
                            later at any time.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={lifecycleBusy}>Cancel</AlertDialogCancel>
                        <AlertDialogAction disabled={lifecycleBusy} onClick={handleArchive}>
                            {lifecycleBusy ? "Archiving…" : "Archive class"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Force Delete Confirmation Dialog */}
            <AlertDialog open={forceDeleteConfirmOpen} onOpenChange={setForceDeleteConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Permanently delete {classItem.record_title}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. All schedules and records attached to this class will be permanently
                            removed.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={lifecycleBusy}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={lifecycleBusy}
                            onClick={handleForceDelete}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {lifecycleBusy ? "Deleting…" : "Permanently delete"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
