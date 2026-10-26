import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { BookOpen, Building2, Calendar, Clock, GraduationCap, MapPin, Monitor, Pencil, Sparkles, UserCheck, Users } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions, ClassSettings, SubjectOption } from "../../lib/class-defaults";
import { deliveryModeLabel, meetingDeliverySummary } from "../../lib/class-defaults";
import { FormSection } from "../form-section";

type ReviewStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    collegeSubjects: SubjectOption[];
    onJumpToStep: (stepIndex: number) => void;
    isEditing?: boolean;
};

const featureToggles: Array<{
    key: keyof Pick<
        ClassSettings,
        | "enable_announcements"
        | "enable_grade_visibility"
        | "enable_attendance_tracking"
        | "allow_late_submissions"
        | "enable_discussion_board"
        | "notify_students_on_schedule_changes"
    >;
    label: string;
}> = [
    { key: "enable_announcements", label: "Announcements" },
    { key: "enable_grade_visibility", label: "Grade visibility" },
    { key: "enable_attendance_tracking", label: "Attendance tracking" },
    { key: "allow_late_submissions", label: "Late submissions" },
    { key: "enable_discussion_board", label: "Discussion board" },
    { key: "notify_students_on_schedule_changes", label: "Schedule alerts" },
];

function optionLabel(options: Array<{ id?: number | string; value?: string; label: string }>, value: number | string | null): string {
    return options.find((option) => String(option.id ?? option.value) === String(value))?.label ?? "Unassigned";
}

function ReviewRow({ label, children }: { label: string; children: ReactNode }): ReactNode {
    return (
        <div className="grid gap-2 py-3.5 first:pt-0 last:pb-0 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-baseline">
            <dt className="text-muted-foreground text-sm font-medium">{label}</dt>
            <dd className="text-foreground min-w-0 text-sm font-semibold break-words">{children}</dd>
        </div>
    );
}

export function ReviewStep({ data, options, collegeSubjects, onJumpToStep, isEditing = false }: ReviewStepProps): ReactNode {
    const isCollege = data.classification === "college";
    const subjectCode = isCollege ? data.subject_code : data.subject_code_shs;
    const deliverySummary = meetingDeliverySummary(data.schedules, data.delivery_mode);

    const editAction = (section: number, label: string): ReactNode => (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className="text-muted-foreground hover:text-foreground h-9 gap-1.5 px-3 text-xs font-semibold"
            aria-label={`Edit ${label}`}
            onClick={() => onJumpToStep(section)}
        >
            <Pencil className="size-3.5" aria-hidden="true" />
            Edit
        </Button>
    );

    return (
        <div className="space-y-6">
            {/* Step 1 Review: Class details */}
            <FormSection
                title="Class & Curriculum"
                description="Core academic identification, programs, and enrollment capacity."
                action={editAction(0, "class details")}
            >
                <dl className="divide-border/60 divide-y">
                    <ReviewRow label="Academic level">
                        <span className="inline-flex items-center gap-1.5">
                            {isCollege ? <BookOpen className="text-primary size-4" /> : <GraduationCap className="text-primary size-4" />}
                            {isCollege ? "College (Undergraduate)" : "Senior High School (K-12)"}
                        </span>
                    </ReviewRow>

                    {isCollege ? (
                        <>
                            <ReviewRow label="Associated programs">
                                {data.course_codes.length > 0 ? (
                                    <div className="flex flex-wrap gap-1.5">
                                        {data.course_codes.map((id) => (
                                            <Badge key={id} variant="secondary" className="font-medium">
                                                {optionLabel(options.courses, id)}
                                            </Badge>
                                        ))}
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground font-normal">None selected</span>
                                )}
                            </ReviewRow>

                            <ReviewRow label="Subjects">
                                {data.subject_ids.length > 0 ? (
                                    <div className="flex flex-wrap gap-1.5">
                                        {data.subject_ids.map((id) => {
                                            const sub = collegeSubjects.find((s) => s.id === id);
                                            return (
                                                <Badge key={id} variant="outline" className="font-mono text-xs">
                                                    {sub?.code ? `${sub.code} - ` : ""}
                                                    {sub?.label ?? `Subject ${id}`}
                                                </Badge>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground font-normal">None selected</span>
                                )}
                            </ReviewRow>

                            <ReviewRow label="Academic year">{optionLabel(options.academic_years, data.academic_year)}</ReviewRow>
                        </>
                    ) : (
                        <>
                            <ReviewRow label="Track">{optionLabel(options.shs_tracks, data.shs_track_id)}</ReviewRow>
                            <ReviewRow label="Grade level">{data.grade_level}</ReviewRow>
                        </>
                    )}

                    <ReviewRow label="Subject code">
                        <span className="text-primary font-mono font-bold">{subjectCode || "Not set"}</span>
                    </ReviewRow>

                    <ReviewRow label="Section">
                        <Badge variant="outline" className="font-semibold">
                            Section {data.section}
                        </Badge>
                    </ReviewRow>

                    <ReviewRow label="Capacity">
                        <span className="inline-flex items-center gap-1.5">
                            <Users className="text-muted-foreground size-4" />
                            {data.maximum_slots} students maximum
                        </span>
                    </ReviewRow>

                    <ReviewRow label="Academic period">
                        <span className="inline-flex items-center gap-1.5">
                            <Calendar className="text-muted-foreground size-4" />
                            {data.school_year} · {optionLabel(options.semesters, data.semester)}
                        </span>
                    </ReviewRow>
                </dl>
            </FormSection>

            {/* Step 2 Review: Teaching and schedule */}
            <FormSection
                title="Teaching & Timetable"
                description="Assigned instructor, delivery format, and recurring weekly meetings."
                action={editAction(1, "teaching and schedule")}
            >
                <dl className="divide-border/60 divide-y">
                    <ReviewRow label="Faculty">
                        <span className="inline-flex items-center gap-1.5">
                            <UserCheck className="text-primary size-4" />
                            {optionLabel(options.faculties, data.faculty_id)}
                        </span>
                    </ReviewRow>

                    <ReviewRow label="Delivery mode">
                        <Badge variant="secondary" className="gap-1 font-medium capitalize">
                            {deliverySummary === "online" ? <Monitor className="size-3.5" /> : <Building2 className="size-3.5" />}
                            {deliveryModeLabel(deliverySummary)}
                        </Badge>
                    </ReviewRow>

                    {data.delivery_mode !== "online" ? (
                        <ReviewRow label="Default campus room">
                            <span className="inline-flex items-center gap-1.5">
                                <MapPin className="text-muted-foreground size-4" />
                                {optionLabel(options.rooms, data.room_id)}
                            </span>
                        </ReviewRow>
                    ) : null}

                    <ReviewRow label="Weekly sessions">
                        {data.schedules.length === 0 ? (
                            <span className="text-muted-foreground font-normal">
                                Unscheduled offering. Meeting times can be added later in the schedule planner.
                            </span>
                        ) : (
                            <div className="space-y-2 pt-1">
                                {data.schedules.map((schedule, index) => {
                                    const isOnline = (schedule.delivery_mode ?? data.delivery_mode) === "online";
                                    const room = options.rooms.find((r) => Number(r.id) === schedule.room_id);

                                    return (
                                        <div
                                            key={schedule.id ?? index}
                                            className="bg-muted/20 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="font-semibold">
                                                    {schedule.day_of_week}
                                                </Badge>
                                                <span className="text-muted-foreground inline-flex items-center gap-1 font-mono">
                                                    <Clock className="size-3" />
                                                    {schedule.start_time} – {schedule.end_time}
                                                </span>
                                            </div>

                                            <span className="text-muted-foreground font-medium">
                                                {isOnline ? "Online (No room)" : `Campus · ${room?.label ?? "Room unassigned"}`}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </ReviewRow>
                </dl>
            </FormSection>

            {/* If editing: show class settings if available; otherwise show informative callout */}
            {isEditing ? (
                <FormSection
                    title="Classroom settings"
                    description="Tools and preferences that apply to the student classroom."
                    action={editAction(2, "classroom settings")}
                >
                    <dl className="divide-border/60 divide-y">
                        {featureToggles.map((toggle) => (
                            <ReviewRow key={toggle.key} label={toggle.label}>
                                <Badge variant={data.settings[toggle.key] ? "default" : "outline"} className="text-xs">
                                    {data.settings[toggle.key] ? "Enabled" : "Disabled"}
                                </Badge>
                            </ReviewRow>
                        ))}
                        <ReviewRow label="Theme">
                            <span className="capitalize">{data.settings.theme}</span>
                        </ReviewRow>
                        <ReviewRow label="Colors">
                            <span className="font-normal">
                                Background {data.settings.background_color} · Accent {data.settings.accent_color}
                            </span>
                        </ReviewRow>
                        <ReviewRow label="Banner image">{data.settings.banner_image?.name ?? "No new image selected"}</ReviewRow>
                    </dl>
                </FormSection>
            ) : (
                <div className="bg-muted/20 text-muted-foreground flex items-start gap-3 rounded-xl border border-dashed p-4 text-sm">
                    <Sparkles className="text-primary mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <div className="space-y-1">
                        <p className="text-foreground text-sm font-semibold">Classroom settings & appearance</p>
                        <p className="text-xs leading-relaxed text-pretty">
                            Classroom settings (banner, theme colours, late submissions, discussions, and grading visibility) are excluded during
                            creation and will be configured directly by the assigned faculty member.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
