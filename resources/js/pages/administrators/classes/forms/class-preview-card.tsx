import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { BookOpen, Building2, Calendar, Clock, GraduationCap, MapPin, Monitor, Sparkles, UserCheck, Users } from "lucide-react";
import type { ReactNode } from "react";
import { deliveryModeLabel, meetingDeliverySummary, type ClassFormData, type ClassFormOptions, type SubjectOption } from "../lib/class-defaults";

type ClassPreviewCardProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    collegeSubjects: SubjectOption[];
    semester: string;
    schoolYear: string;
    className?: string;
};

function toMinutes(time: string): number {
    return Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
}

export function ClassPreviewCard({ data, options, collegeSubjects, semester, schoolYear, className }: ClassPreviewCardProps): ReactNode {
    const isCollege = data.classification === "college";
    const subjectCode = isCollege ? data.subject_code : data.subject_code_shs;

    const selectedFaculty = options.faculties.find((faculty) => String(faculty.id) === String(data.faculty_id));
    const selectedRoom = options.rooms.find((room) => Number(room.id) === data.room_id);

    const termLabel = options.semesters.find((term) => term.value === semester)?.label ?? semester;
    const deliverySummary = meetingDeliverySummary(data.schedules, data.delivery_mode);

    const selectedPrograms = isCollege ? options.courses.filter((course) => data.course_codes.includes(Number(course.id))) : [];
    const selectedSubjects = isCollege ? collegeSubjects.filter((subject) => data.subject_ids.includes(subject.id)) : [];

    const selectedTrack = !isCollege ? options.shs_tracks.find((t) => Number(t.id) === Number(data.shs_track_id)) : null;

    const weeklyHours = data.schedules.reduce((total, item) => total + Math.max(0, toMinutes(item.end_time) - toMinutes(item.start_time)), 0) / 60;

    return (
        <aside
            aria-label="Live class offering preview"
            className={cn("bg-card text-card-foreground flex flex-col overflow-hidden rounded-2xl border shadow-xs transition-all", className)}
        >
            {/* Visual Top Banner */}
            <div className="from-primary/15 via-primary/5 to-muted/40 relative border-b bg-gradient-to-br p-4 sm:p-5">
                <div className="flex items-center justify-between gap-2">
                    <div className="bg-background/80 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium shadow-2xs backdrop-blur">
                        <span className="size-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true" />
                        <span className="text-muted-foreground">Live Preview</span>
                    </div>

                    <Badge variant="outline" className="bg-background/80 font-mono text-xs font-semibold backdrop-blur">
                        Section {data.section || "—"}
                    </Badge>
                </div>

                <div className="mt-4 space-y-1">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge variant="secondary" className="gap-1 text-xs font-medium">
                            {isCollege ? <BookOpen className="size-3" /> : <GraduationCap className="size-3" />}
                            {isCollege ? "College" : "Senior High"}
                        </Badge>
                        <Badge
                            variant="outline"
                            className={cn(
                                "gap-1 text-xs font-medium",
                                deliverySummary === "online" &&
                                    "border-indigo-500/30 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300",
                                deliverySummary === "face_to_face" &&
                                    "border-blue-500/30 bg-blue-50/50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300",
                            )}
                        >
                            {deliverySummary === "online" ? <Monitor className="size-3" /> : <Building2 className="size-3" />}
                            {deliveryModeLabel(deliverySummary)}
                        </Badge>
                    </div>

                    <h3 className="text-foreground text-xl font-bold tracking-tight break-words sm:text-2xl">
                        {subjectCode ? subjectCode : <span className="text-muted-foreground font-normal italic">Subject code pending...</span>}
                    </h3>

                    <p className="text-muted-foreground text-xs font-medium text-pretty">
                        {isCollege
                            ? selectedPrograms.length > 0
                                ? `${selectedPrograms.map((p) => p.label).join(", ")} · Year ${data.academic_year}${
                                      selectedSubjects.length > 0 ? ` · ${selectedSubjects.map((s) => s.code || s.label).join(", ")}` : ""
                                  }`
                                : "No program selected yet"
                            : selectedTrack
                              ? `${selectedTrack.label} · ${data.grade_level}`
                              : "No track selected yet"}
                    </p>
                </div>
            </div>

            {/* Quick Details Body */}
            <div className="flex flex-1 flex-col gap-4 p-4 sm:p-5">
                {/* Information Grid */}
                <div className="grid grid-cols-2 gap-3 text-xs">
                    {/* Faculty */}
                    <div className="bg-muted/20 rounded-xl border p-3">
                        <span className="text-muted-foreground flex items-center gap-1.5 font-medium">
                            <UserCheck className="text-primary size-3.5" />
                            Instructor
                        </span>
                        <p className="text-foreground mt-1 truncate font-semibold">
                            {selectedFaculty ? selectedFaculty.label : <span className="text-muted-foreground font-normal">Unassigned</span>}
                        </p>
                    </div>

                    {/* Room */}
                    <div className="bg-muted/20 rounded-xl border p-3">
                        <span className="text-muted-foreground flex items-center gap-1.5 font-medium">
                            <MapPin className="text-primary size-3.5" />
                            Campus Room
                        </span>
                        <p className="text-foreground mt-1 truncate font-semibold">
                            {data.delivery_mode === "online" ? (
                                <span className="font-medium text-indigo-600 dark:text-indigo-400">Online (Virtual)</span>
                            ) : selectedRoom ? (
                                selectedRoom.label
                            ) : (
                                <span className="text-muted-foreground font-normal">Unassigned</span>
                            )}
                        </p>
                    </div>

                    {/* Capacity */}
                    <div className="bg-muted/20 rounded-xl border p-3">
                        <span className="text-muted-foreground flex items-center gap-1.5 font-medium">
                            <Users className="text-primary size-3.5" />
                            Capacity
                        </span>
                        <p className="text-foreground mt-1 font-semibold tabular-nums">
                            {data.maximum_slots} <span className="text-muted-foreground font-normal">max students</span>
                        </p>
                    </div>

                    {/* Term */}
                    <div className="bg-muted/20 rounded-xl border p-3">
                        <span className="text-muted-foreground flex items-center gap-1.5 font-medium">
                            <Calendar className="text-primary size-3.5" />
                            Period
                        </span>
                        <p className="text-foreground mt-1 truncate font-semibold">
                            {termLabel} · {schoolYear}
                        </p>
                    </div>
                </div>

                {/* Schedule Visualizer */}
                <div className="bg-muted/10 space-y-2 rounded-xl border p-3">
                    <div className="flex items-center justify-between text-xs font-medium">
                        <span className="text-muted-foreground flex items-center gap-1.5">
                            <Clock className="text-primary size-3.5" />
                            Weekly Schedule
                        </span>
                        {data.schedules.length > 0 ? (
                            <Badge variant="secondary" className="px-1.5 py-0 text-[11px] tabular-nums">
                                {Number(weeklyHours.toFixed(1))} hrs/wk
                            </Badge>
                        ) : null}
                    </div>

                    {data.schedules.length === 0 ? (
                        <div className="text-muted-foreground rounded-lg border border-dashed py-3 text-center text-xs">
                            Unscheduled offering (meetings can be added anytime)
                        </div>
                    ) : (
                        <div className="space-y-1.5 pt-1">
                            {data.schedules.map((schedule, index) => {
                                const isItemOnline = (schedule.delivery_mode ?? data.delivery_mode) === "online";
                                const room = options.rooms.find((r) => Number(r.id) === schedule.room_id);

                                return (
                                    <div
                                        key={schedule.id ?? `preview-sched-${index}`}
                                        className="bg-background flex items-center justify-between gap-2 rounded-lg border px-2.5 py-1.5 text-xs shadow-2xs"
                                    >
                                        <div className="flex min-w-0 items-center gap-2">
                                            <span className="text-foreground shrink-0 font-semibold">{schedule.day_of_week.slice(0, 3)}</span>
                                            <span className="text-muted-foreground truncate tabular-nums">
                                                {schedule.start_time} - {schedule.end_time}
                                            </span>
                                        </div>
                                        <span className="text-muted-foreground shrink-0 text-[11px] font-medium">
                                            {isItemOnline ? "Online" : (room?.label ?? "Room pending")}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Faculty note */}
                <div className="bg-muted/20 text-muted-foreground mt-auto flex items-start gap-2.5 rounded-xl border border-dashed p-3 text-xs">
                    <Sparkles className="text-primary/70 mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p className="text-pretty">
                        Classroom settings (appearance, discussions, late submissions, and announcements) are managed by faculty in their portal.
                    </p>
                </div>
            </div>
        </aside>
    );
}
