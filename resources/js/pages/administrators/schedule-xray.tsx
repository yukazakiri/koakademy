import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { create as createClass, show as showClass } from "@/routes/administrators/classes";
import { index as boardIndex } from "@/routes/administrators/schedule-board";
import { index as studioIndex } from "@/routes/administrators/scheduling-analytics";
import { Head, Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowUpRight,
    Building2,
    CalendarDays,
    Download,
    GraduationCap,
    LayoutGrid,
    Monitor,
    MoreHorizontal,
    Plus,
    Redo2,
    Send,
    SlidersHorizontal,
    Sparkles,
    Sun,
    Undo2,
    User,
    Users,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { canonicalDeliveryMode, isOnlineDeliveryMode, type DeliveryMode } from "./classes/lib/class-defaults";
import GenerateOfferingsDialog from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingPolicyDialog from "./components/scheduling-policy-dialog";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import type { ScheduleEntry, SchedulingAnalyticsProps } from "./components/scheduling-types";
import XRayClassPicker from "./schedule-xray/components/xray-class-picker";
import XRaySlotMatrix from "./schedule-xray/components/xray-slot-matrix";
import { evaluateXRayGrid } from "./schedule-xray/lib/xray-engine";
import { useScheduleStudio } from "./scheduling/hooks/use-schedule-studio";
import { parseTimeToMinutes } from "./scheduling/lib/time-geometry";

const DURATION_OPTIONS = [
    { minutes: 60, label: "1h" },
    { minutes: 90, label: "1.5h" },
    { minutes: 120, label: "2h" },
    { minutes: 180, label: "3h" },
] as const;

export default function ScheduleXRay(props: SchedulingAnalyticsProps) {
    const { user, term_context, filters, creation_options } = props;
    const studio = useScheduleStudio(props);

    const [selectedClassId, setSelectedClassId] = useState<number | null>(
        () => studio.classes[0]?.id ?? null,
    );
    const [durationMinutes, setDurationMinutes] = useState<number>(90);
    const [modeChoice, setModeChoice] = useState<"campus" | "online">("campus");
    const [relocatingSessionId, setRelocatingSessionId] = useState<number | null>(null);

    const selectedClass = useMemo(
        () => studio.classes.find((c) => c.id === selectedClassId) ?? studio.classes[0] ?? null,
        [studio.classes, selectedClassId],
    );

    // Sync default mode & duration when switching classes
    useEffect(() => {
        if (!selectedClass) return;
        setRelocatingSessionId(null);
        const classOnline = isOnlineDeliveryMode(selectedClass.delivery_mode);
        setModeChoice(classOnline ? "online" : "campus");
        if (selectedClass.schedules.length > 0) {
            const first = selectedClass.schedules[0];
            const diff = parseTimeToMinutes(first.end_time) - parseTimeToMinutes(first.start_time);
            if (diff === 60 || diff === 90 || diff === 120 || diff === 180) {
                setDurationMinutes(diff);
            }
        }
    }, [selectedClass?.id]);

    const relocatingSession: ScheduleEntry | null = useMemo(() => {
        if (!selectedClass || relocatingSessionId === null) return null;
        return selectedClass.schedules.find((s) => s.id === relocatingSessionId) ?? null;
    }, [selectedClass, relocatingSessionId]);

    // When entering relocate mode on a session, match its exact duration
    const startRelocate = (session: ScheduleEntry) => {
        if (session.id === undefined) return;
        setRelocatingSessionId(session.id);
        const diff = parseTimeToMinutes(session.end_time) - parseTimeToMinutes(session.start_time);
        if (diff >= 60 && diff <= 180) {
            setDurationMinutes(diff);
        }
        setModeChoice(isOnlineDeliveryMode(session.delivery_mode ?? selectedClass?.delivery_mode) ? "online" : "campus");
    };

    const effectiveDeliveryMode: DeliveryMode =
        modeChoice === "online" ? studio.policy.default_online_delivery_mode : "face_to_face";

    const xrayGrid = useMemo(() => {
        if (!selectedClass) return null;
        return evaluateXRayGrid({
            targetClass: selectedClass,
            allClasses: studio.classes,
            rooms: creation_options.rooms,
            policy: studio.policy,
            days: studio.visibleDays,
            durationMinutes,
            deliveryMode: effectiveDeliveryMode,
            relocatingScheduleId: relocatingSessionId,
            conflictScheduleIds: studio.conflictScheduleIdSet,
        });
    }, [
        selectedClass,
        studio.classes,
        creation_options.rooms,
        studio.policy,
        studio.visibleDays,
        durationMinutes,
        effectiveDeliveryMode,
        relocatingSessionId,
        studio.conflictScheduleIdSet,
    ]);

    const handlePlaceOrMove = async (payload: {
        days: string[];
        startTime: string;
        endTime: string;
        roomId: number | null;
        deliveryMode: DeliveryMode;
    }) => {
        if (!selectedClass) return;
        if (relocatingSession?.id !== undefined) {
            await studio.updateScheduleBlockDirect(
                selectedClass,
                relocatingSession,
                {
                    day_of_week: payload.days[0],
                    start_time: payload.startTime,
                    end_time: payload.endTime,
                    room_id: payload.roomId,
                    delivery_mode: payload.deliveryMode,
                },
                `Relocated ${selectedClass.subject_code} to ${payload.days[0]} ${payload.startTime}`,
            );
            setRelocatingSessionId(null);
            return;
        }

        await studio.addClassScheduleBlocks(selectedClass, {
            days: payload.days,
            start_time: payload.startTime,
            end_time: payload.endTime,
            room_id: payload.roomId,
            delivery_mode: canonicalDeliveryMode(payload.deliveryMode),
            status: "draft",
        });
    };

    return (
        <AdminLayout user={user} title="Schedule X-Ray">
            <Head title="Schedule X-Ray" />

            <div className="flex h-[calc(100dvh-4.5rem)] min-h-[640px] flex-col gap-4 p-4 lg:flex-row lg:p-5">
                {/* ZONE 1: Class Picker */}
                <XRayClassPicker
                    classes={studio.classes}
                    selectedClassId={selectedClass?.id ?? null}
                    onSelectClass={(cls) => setSelectedClassId(cls.id)}
                    conflictScheduleIds={studio.conflictScheduleIdSet}
                />

                {/* ZONE 2: Live Availability X-Ray Canvas */}
                <main className="bg-card/85 border-border/60 flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border shadow-2xs">
                    {!selectedClass || !xrayGrid ? (
                        <div className="text-muted-foreground flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
                            <p className="text-sm font-semibold text-foreground">No classes found for this term</p>
                            <p className="max-w-sm text-xs">
                                Create a class or generate offerings from the curriculum to use Availability X-Ray.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Single Clean Focus Header */}
                            <header className="border-border/50 space-y-3 border-b px-5 py-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h1 className="truncate text-lg font-semibold tracking-tight sm:text-xl">
                                                {selectedClass.subject_code}
                                            </h1>
                                            <Badge variant="secondary" className="rounded-md px-2 py-0.5 text-xs">
                                                Sec {selectedClass.section}
                                            </Badge>
                                            <Link
                                                href={showClass.url(selectedClass.id)}
                                                className="text-muted-foreground hover:text-primary inline-flex items-center gap-0.5 text-xs font-medium hover:underline"
                                            >
                                                Class <ArrowUpRight className="size-3" />
                                            </Link>
                                        </div>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {selectedClass.subject_title}
                                        </p>

                                        {/* The 3 Active Constraints Being X-Rayed */}
                                        <div className="flex flex-wrap items-center gap-2 pt-0.5 text-[11px] text-muted-foreground">
                                            <span className="bg-muted/60 inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium text-foreground/85">
                                                <User className="size-3" />
                                                {selectedClass.faculty_name ?? "No teacher"}
                                            </span>
                                            <span className="bg-muted/60 inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium text-foreground/85">
                                                <GraduationCap className="size-3" />
                                                {selectedClass.courses || "General"} · {selectedClass.grade_level ?? "All"} · Sec{" "}
                                                {selectedClass.section}
                                            </span>
                                            <span className="bg-muted/60 inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium text-foreground/85 tabular-nums">
                                                <Users className="size-3" />
                                                {Math.max(selectedClass.student_count, selectedClass.maximum_slots ?? 0)} seats
                                            </span>
                                        </div>
                                    </div>

                                    {/* Minimal Slot Parameters (Duration + Campus/Online + Overflow) */}
                                    <div className="flex flex-wrap items-center gap-2">
                                        {/* Duration Pills */}
                                        <div
                                            role="group"
                                            aria-label="Session length"
                                            className="bg-muted/70 inline-flex items-center rounded-xl p-0.5 text-xs font-medium"
                                        >
                                            {DURATION_OPTIONS.map((opt) => (
                                                <button
                                                    key={opt.minutes}
                                                    type="button"
                                                    onClick={() => setDurationMinutes(opt.minutes)}
                                                    className={`rounded-lg px-2.5 py-1 transition-colors tabular-nums ${
                                                        durationMinutes === opt.minutes
                                                            ? "bg-background text-foreground shadow-xs"
                                                            : "text-muted-foreground hover:text-foreground"
                                                    }`}
                                                >
                                                    {opt.label}
                                                </button>
                                            ))}
                                        </div>

                                        {/* Campus | Online Toggle */}
                                        <div
                                            role="group"
                                            aria-label="Delivery mode"
                                            className="bg-muted/70 inline-flex items-center rounded-xl p-0.5 text-xs font-medium"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => setModeChoice("campus")}
                                                className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 transition-colors ${
                                                    modeChoice === "campus"
                                                        ? "bg-background text-foreground shadow-xs"
                                                        : "text-muted-foreground hover:text-foreground"
                                                }`}
                                            >
                                                <Building2 className="size-3.5" /> Campus
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setModeChoice("online")}
                                                className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 transition-colors ${
                                                    modeChoice === "online"
                                                        ? "bg-background text-foreground shadow-xs"
                                                        : "text-muted-foreground hover:text-foreground"
                                                }`}
                                            >
                                                <Monitor className="size-3.5" /> Online
                                            </button>
                                        </div>

                                        {/* Quiet Overflow Menu */}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-8 rounded-xl"
                                                    aria-label="More X-Ray options"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-60">
                                                <DropdownMenuItem
                                                    disabled={!studio.history.canUndo}
                                                    onSelect={() => void studio.history.undo()}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Undo2 className="size-3.5" /> Undo last move (Ctrl+Z)
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    disabled={!studio.history.canRedo}
                                                    onSelect={() => void studio.history.redo()}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Redo2 className="size-3.5" /> Redo (Ctrl+Shift+Z)
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    onSelect={() => void studio.toggleSundayScheduling()}
                                                    className="flex items-center justify-between gap-2"
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <Sun className="size-3.5" /> Sunday scheduling
                                                    </span>
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {studio.policy.allow_sunday_scheduling ? "On" : "Off"}
                                                    </Badge>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() => studio.setPolicyOpen(true)}
                                                    className="flex items-center gap-2"
                                                >
                                                    <SlidersHorizontal className="size-3.5" /> Conflict rules
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem asChild>
                                                    <Link href={createClass.url()} className="flex items-center gap-2">
                                                        <Plus className="size-3.5" /> Create new class
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() => studio.setGenerateOpen(true)}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Sparkles className="size-3.5" /> Generate from curriculum
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() => studio.setExportOpen(true)}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Download className="size-3.5" /> Export PDF timetable
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() => studio.setReviewOpen(true)}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Send className="size-3.5" /> Review &amp; publish ({studio.draftCount} draft)
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem asChild>
                                                    <Link href={boardIndex.url()} className="flex items-center gap-2">
                                                        <CalendarDays className="size-3.5" /> Open Schedule Board
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link href={studioIndex.url()} className="flex items-center gap-2">
                                                        <LayoutGrid className="size-3.5" /> Open Multi-Lens Studio
                                                    </Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>

                                {/* Current Sessions Strip — Click any session pill to Relocate it! */}
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className="text-muted-foreground mr-1 text-[11px] font-medium">
                                        Current times:
                                    </span>
                                    {selectedClass.schedules.length === 0 ? (
                                        <span className="text-amber-700 dark:text-amber-300 text-xs font-medium">
                                            Not scheduled yet — click any green slot below to place
                                        </span>
                                    ) : (
                                        selectedClass.schedules.map((s) => {
                                            const isConflicting =
                                                s.id !== undefined && studio.conflictScheduleIdSet.has(s.id);
                                            const isRelocating = s.id === relocatingSessionId;
                                            return (
                                                <button
                                                    key={s.id ?? `${s.day_of_week}-${s.start_time}`}
                                                    type="button"
                                                    data-xray-session-chip
                                                    onClick={() =>
                                                        isRelocating ? setRelocatingSessionId(null) : startRelocate(s)
                                                    }
                                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition-all tabular-nums ${
                                                        isRelocating
                                                            ? "border-primary bg-primary text-primary-foreground shadow-xs"
                                                            : isConflicting
                                                              ? "border-rose-400/60 bg-rose-500/10 text-rose-800 hover:bg-rose-500/15 dark:text-rose-200"
                                                              : "border-border/70 bg-background hover:bg-muted text-foreground"
                                                    }`}
                                                >
                                                    {isConflicting && <AlertTriangle className="size-3 shrink-0" />}
                                                    <span>
                                                        {s.day_of_week.slice(0, 3)} {s.time_range}
                                                    </span>
                                                    {s.room && <span className="opacity-70">· {s.room}</span>}
                                                </button>
                                            );
                                        })
                                    )}
                                    {relocatingSessionId !== null && (
                                        <button
                                            type="button"
                                            onClick={() => setRelocatingSessionId(null)}
                                            className="text-muted-foreground hover:text-foreground ml-1 text-[11px] underline"
                                        >
                                            Switch to Add New
                                        </button>
                                    )}
                                </div>
                            </header>

                            {/* The Availability X-Ray Grid */}
                            <XRaySlotMatrix
                                targetClass={selectedClass}
                                days={studio.visibleDays}
                                grid={xrayGrid}
                                durationMinutes={durationMinutes}
                                deliveryMode={effectiveDeliveryMode}
                                relocatingSession={relocatingSession}
                                onStartRelocate={startRelocate}
                                onCancelRelocate={() => setRelocatingSessionId(null)}
                                onPlaceOrMove={handlePlaceOrMove}
                                onDeleteSession={(id) => studio.deleteScheduleBlock(id, false)}
                            />
                        </>
                    )}
                </main>
            </div>

            {/* Existing Power Dialogs */}
            <SchedulingReviewDialog
                key={`${studio.reviewOpen}-${studio.draftCount}`}
                open={studio.reviewOpen}
                onOpenChange={studio.setReviewOpen}
                classes={studio.classes}
                conflictCount={studio.conflicts.length}
                draftCount={studio.draftCount}
                termLabel={`${studio.schoolYear} · ${term_context?.semester_label ?? `Semester ${studio.semester}`}`}
                isPublishing={studio.publishing}
                onPublish={studio.publishDrafts}
                onResolveConflicts={() => studio.setReviewOpen(false)}
            />

            <GenerateOfferingsDialog
                open={studio.generateOpen}
                onOpenChange={studio.setGenerateOpen}
                schoolYear={studio.schoolYear}
                semester={studio.semester}
                courses={creation_options.courses}
                shsStrands={creation_options.shs_strands}
                rooms={creation_options.rooms}
                onGenerate={studio.generateClasses}
            />

            <ScheduleExportDialog
                open={studio.exportOpen}
                onOpenChange={studio.setExportOpen}
                courses={filters.available_courses}
                initialCourseId={null}
            />

            <SchedulingPolicyDialog
                open={studio.policyOpen}
                onOpenChange={studio.setPolicyOpen}
                policy={studio.policy}
                onSave={studio.saveSchedulingPolicy}
            />
        </AdminLayout>
    );
}
