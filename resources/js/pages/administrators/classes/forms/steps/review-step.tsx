import { Button } from "@/components/ui/button";
import { Pencil } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions, ClassSettings, SubjectOption } from "../../lib/class-defaults";
import { FormSection } from "../form-section";

type ReviewStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    collegeSubjects: SubjectOption[];
    onJumpToStep: (stepIndex: number) => void;
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
        <div className="grid gap-2 py-4 first:pt-0 last:pb-0 sm:grid-cols-[10rem_minmax(0,1fr)]">
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="min-w-0 text-sm font-medium break-words">{children}</dd>
        </div>
    );
}

export function ReviewStep({ data, options, collegeSubjects, onJumpToStep }: ReviewStepProps): ReactNode {
    const subjectCode = data.classification === "college" ? data.subject_code : data.subject_code_shs;
    const editAction = (section: number, label: string): ReactNode => (
        <Button type="button" variant="outline" className="h-11 px-3" aria-label={`Edit ${label}`} onClick={() => onJumpToStep(section)}>
            <Pencil className="size-4" aria-hidden="true" />
            Edit
        </Button>
    );

    return (
        <div className="space-y-6">
            <FormSection title="Class details" description="Curriculum and enrollment information." action={editAction(0, "class details")}>
                <dl className="divide-y">
                    <ReviewRow label="Academic level">{data.classification === "college" ? "College" : "Senior high school"}</ReviewRow>
                    {data.classification === "college" ? (
                        <>
                            <ReviewRow label="Programs">
                                {data.course_codes.map((id) => optionLabel(options.courses, id)).join(", ") || "None selected"}
                            </ReviewRow>
                            <ReviewRow label="Subjects">
                                {data.subject_ids
                                    .map((id) => collegeSubjects.find((subject) => subject.id === id)?.label ?? `Subject ${id}`)
                                    .join(", ") || "None selected"}
                            </ReviewRow>
                            <ReviewRow label="Academic year">{optionLabel(options.academic_years, data.academic_year)}</ReviewRow>
                        </>
                    ) : (
                        <>
                            <ReviewRow label="Track">{optionLabel(options.shs_tracks, data.shs_track_id)}</ReviewRow>
                            <ReviewRow label="Grade level">{data.grade_level}</ReviewRow>
                        </>
                    )}
                    <ReviewRow label="Subject code">{subjectCode || "Not set"}</ReviewRow>
                    <ReviewRow label="Section">{data.section}</ReviewRow>
                    <ReviewRow label="Capacity">{data.maximum_slots} students</ReviewRow>
                    <ReviewRow label="Academic period">
                        {data.school_year} · {optionLabel(options.semesters, data.semester)}
                    </ReviewRow>
                </dl>
            </FormSection>
            <FormSection
                title="Teaching and schedule"
                description="Assignments and recurring weekly sessions."
                action={editAction(1, "teaching and schedule")}
            >
                <dl className="divide-y">
                    <ReviewRow label="Faculty">{optionLabel(options.faculties, data.faculty_id)}</ReviewRow>
                    <ReviewRow label="Taught">{data.delivery_mode === "online" ? "Online" : "Face to face"}</ReviewRow>
                    {data.delivery_mode === "online" ? null : <ReviewRow label="Primary room">{optionLabel(options.rooms, data.room_id)}</ReviewRow>}
                    <ReviewRow label="Weekly sessions">
                        {data.schedules.length === 0 ? (
                            <span className="text-muted-foreground font-normal">Unscheduled offering. Meeting times can be added later.</span>
                        ) : (
                            <ul className="space-y-4">
                                {data.schedules.map((schedule, index) => (
                                    <li key={schedule.id ?? index} className="space-y-1">
                                        <p>
                                            {schedule.day_of_week} · {schedule.start_time}–{schedule.end_time}
                                        </p>
                                        <p className="text-muted-foreground font-normal">
                                            {data.delivery_mode === "online" ? "Online" : optionLabel(options.rooms, schedule.room_id)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </ReviewRow>
                </dl>
            </FormSection>
            <FormSection
                title="Classroom settings"
                description="Tools and preferences that apply to the student classroom."
                action={editAction(2, "classroom settings")}
            >
                <dl className="divide-y">
                    {featureToggles.map((toggle) => (
                        <ReviewRow key={toggle.key} label={toggle.label}>
                            {data.settings[toggle.key] ? "Enabled" : "Disabled"}
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
                    {Object.entries(data.settings.custom ?? {}).map(([key, value]) => (
                        <ReviewRow key={key} label={key}>
                            {value || "No value"}
                        </ReviewRow>
                    ))}
                </dl>
            </FormSection>
        </div>
    );
}
