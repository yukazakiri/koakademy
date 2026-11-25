import AdminLayout from "@/components/administrators/admin-layout";
import { Head } from "@inertiajs/react";
import { useCallback, useEffect, useState } from "react";
import GenerateOfferingsDialog from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingPolicyDialog from "./components/scheduling-policy-dialog";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import SchedulingSessionDialog from "./components/scheduling-session-dialog";
import type { ClassScheduleData, SchedulingAnalyticsProps } from "./components/scheduling-types";
import BacklogRail from "./scheduling/components/backlog-rail";
import ConflictWorkbench from "./scheduling/components/conflict-workbench";
import OccupancyHeatmapView from "./scheduling/components/occupancy-heatmap-view";
import ResourceMatrixView from "./scheduling/components/resource-matrix-view";
import StudioFiltersStrip from "./scheduling/components/studio-filters-strip";
import StudioHeader from "./scheduling/components/studio-header";
import StudioKpiStrip from "./scheduling/components/studio-kpi-strip";
import WeekCanvas from "./scheduling/components/week-canvas";
import { useScheduleStudio } from "./scheduling/hooks/use-schedule-studio";

export default function SchedulingAnalytics(props: SchedulingAnalyticsProps) {
    const { user, term_context, filters, creation_options } = props;
    const studio = useScheduleStudio(props);

    // Floating cursor badge while dragging a class from the Backlog Rail
    const [dragPointer, setDragPointer] = useState<{ x: number; y: number } | null>(null);

    const handleStartBacklogDrag = useCallback(
        (classItem: ClassScheduleData, event: React.PointerEvent) => {
            if (event.button !== 0) return;
            event.preventDefault();
            if (studio.activeLens !== "week") {
                studio.setActiveLens("week");
            }
            const remainingHours = Math.max(
                1,
                Math.min(3, (classItem.required_hours ?? 3) - (classItem.scheduled_hours ?? 0)),
            );
            const durationMinutes = Math.max(60, Math.round(remainingHours * 2) * 30);
            studio.setDraggingBacklog({ classItem, durationMinutes });
            setDragPointer({ x: event.clientX, y: event.clientY });
        },
        [studio],
    );

    useEffect(() => {
        if (!studio.draggingBacklog) {
            setDragPointer(null);
            return;
        }
        const onMove = (event: PointerEvent) => setDragPointer({ x: event.clientX, y: event.clientY });
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                studio.setDraggingBacklog(null);
                setDragPointer(null);
            }
        };
        window.addEventListener("pointermove", onMove);
        window.addEventListener("keydown", onKeyDown);
        return () => {
            window.removeEventListener("pointermove", onMove);
            window.removeEventListener("keydown", onKeyDown);
        };
    }, [studio]);

    const editorClass = studio.editorTarget
        ? studio.classes.find((item) => item.id === studio.editorTarget?.classId)
        : undefined;
    const editorSession =
        editorClass && studio.editorTarget?.sessionId !== undefined
            ? editorClass.schedules.find((s) => s.id === studio.editorTarget?.sessionId)
            : undefined;

    return (
        <AdminLayout user={user} title="Schedule Studio">
            <Head title="Schedule Studio" />

            <div className="flex min-h-[calc(100dvh-5.5rem)] flex-col gap-3 p-3 sm:p-4 lg:p-5">
                {/* Command Header */}
                <StudioHeader studio={studio} termContext={term_context} />

                {/* Bird's-Eye KPI Strip */}
                <StudioKpiStrip studio={studio} />

                {/* Filters & Student Lookup Strip */}
                <StudioFiltersStrip studio={studio} filterOptions={filters} />

                {/* Main 3-Pane Studio Workspace */}
                <div className="flex min-h-[600px] flex-1 flex-col gap-3 lg:flex-row lg:items-stretch">
                    {/* Left Collapsible Backlog Rail */}
                    {studio.railOpen && (
                        <div className="h-[420px] lg:h-[calc(100dvh-16.5rem)] lg:min-h-[580px]">
                            <BacklogRail studio={studio} onStartBacklogDrag={handleStartBacklogDrag} />
                        </div>
                    )}

                    {/* Center Active Lens */}
                    <div className="flex min-w-0 flex-1 flex-col lg:h-[calc(100dvh-16.5rem)] lg:min-h-[580px]">
                        {studio.activeLens === "week" && (
                            <WeekCanvas studio={studio} rooms={creation_options.rooms} />
                        )}
                        {studio.activeLens === "matrix" && (
                            <ResourceMatrixView
                                studio={studio}
                                rooms={creation_options.rooms}
                                faculty={creation_options.faculty}
                            />
                        )}
                        {studio.activeLens === "heatmap" && <OccupancyHeatmapView studio={studio} />}
                    </div>

                    {/* Right Collapsible Visual Conflict Resolution Workbench */}
                    {studio.workbenchOpen && (
                        <div className="h-[460px] lg:h-[calc(100dvh-16.5rem)] lg:min-h-[580px]">
                            <ConflictWorkbench studio={studio} />
                        </div>
                    )}
                </div>
            </div>

            {/* Floating cursor chip while dragging a class from the Backlog Rail */}
            {studio.draggingBacklog && dragPointer && (
                <div
                    style={{
                        transform: `translate3d(${dragPointer.x + 14}px, ${dragPointer.y + 14}px, 0)`,
                    }}
                    className="bg-primary text-primary-foreground pointer-events-none fixed top-0 left-0 z-50 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-lg"
                >
                    {studio.draggingBacklog.classItem.subject_code} · Sec {studio.draggingBacklog.classItem.section}
                    <span className="ml-1.5 text-[10px] font-normal opacity-80">
                        ({studio.draggingBacklog.durationMinutes / 60}h)
                    </span>
                </div>
            )}

            {/* Session Create/Edit Dialog */}
            {editorClass && (
                <SchedulingSessionDialog
                    key={`${editorClass.id}:${studio.editorTarget?.sessionId ?? "new"}:${studio.editorTarget?.initialDay ?? ""}:${studio.editorTarget?.initialStartTime ?? ""}`}
                    classItem={editorClass}
                    session={editorSession}
                    rooms={creation_options.rooms}
                    allowSunday={studio.policy.allow_sunday_scheduling}
                    initialDay={studio.editorTarget?.initialDay}
                    initialStartTime={studio.editorTarget?.initialStartTime}
                    initialEndTime={studio.editorTarget?.initialEndTime}
                    initialRoomId={studio.editorTarget?.initialRoomId}
                    onClose={() => studio.setEditorTarget(null)}
                    onSave={studio.saveEditorSession}
                    onDelete={async () => {
                        if (editorSession?.id !== undefined) {
                            await studio.deleteScheduleBlock(editorSession.id);
                        }
                    }}
                />
            )}

            {/* Review & Publish Dialog */}
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
                onResolveConflicts={() => {
                    studio.setReviewOpen(false);
                    studio.setWorkbenchOpen(true);
                    studio.setSpotlightMode(true);
                }}
            />

            {/* Bulk Curriculum Offering Generator */}
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

            {/* PDF Export Dialog */}
            <ScheduleExportDialog
                open={studio.exportOpen}
                onOpenChange={studio.setExportOpen}
                courses={filters.available_courses}
                initialCourseId={studio.viewFilters.course === "all" ? null : Number(studio.viewFilters.course)}
            />

            {/* Scheduling Rules & Conflict Policy Dialog */}
            <SchedulingPolicyDialog
                open={studio.policyOpen}
                onOpenChange={studio.setPolicyOpen}
                policy={studio.policy}
                onSave={studio.saveSchedulingPolicy}
            />
        </AdminLayout>
    );
}
