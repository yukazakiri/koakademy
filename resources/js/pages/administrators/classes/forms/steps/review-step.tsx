import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Pencil } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions, ClassSettings, SubjectOption } from "../../lib/class-defaults";

type ReviewStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    collegeSubjects: SubjectOption[];
    onJumpToStep: (stepIndex: number) => void;
};

type SummaryRowProps = {
    label: string;
    value: ReactNode;
    stepIndex: number;
    onJumpToStep: (stepIndex: number) => void;
};

/** The step each group of the summary belongs to. */
const STEP_IDENTITY = 0;
const STEP_TEACHING = 1;
const STEP_SCHEDULE = 2;
const STEP_POLICY = 3;

const featureToggles: Array<{ key: keyof Pick<
    ClassSettings,
    | "enable_announcements"
    | "enable_grade_visibility"
    | "enable_attendance_tracking"
    | "allow_late_submissions"
    | "enable_discussion_board"
    | "notify_students_on_schedule_changes"
>; label: string }> = [
    { key: "enable_announcements", label: "Announcements" },
    { key: "enable_grade_visibility", label: "Grade visibility" },
    { key: "enable_attendance_tracking", label: "Attendance tracking" },
    { key: "allow_late_submissions", label: "Late submissions" },
    { key: "enable_discussion_board", label: "Discussion board" },
    { key: "notify_students_on_schedule_changes", label: "Schedule alerts" },
];

function optionLabel(options: Array<{ id?: number | string; value?: string; label: string }>, value: number | string | null): string {
    if (value === null || value === "") {
        return "Not set";
    }

    return options.find((option) => String(option.id ?? option.value) === String(value))?.label ?? String(value);
}

function lookupLabels(ids: number[], pool: Array<{ id: number | string; label: string }>): string[] {
    return ids.map((id) => {
        const match = pool.find((entry) => String(entry.id) === String(id));

        return match?.label ?? `#${id}`;
    });
}

function SummaryRow({ label, value, stepIndex, onJumpToStep }: SummaryRowProps): ReactNode {
    return (
        <div className="flex items-start justify-between gap-4 rounded-lg border bg-background/60 px-3 py-2">
            <div className="min-w-0">
                <div className="text-muted-foreground text-xs font-medium uppercase tracking-wide">{label}</div>
                <div className="mt-1 text-sm font-medium text-foreground">{value}</div>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => onJumpToStep(stepIndex)}
                className="text-primary hover:text-primary shrink-0"
                aria-label={`Edit ${label}`}
            >
                <Pencil className="size-3.5" />
                Edit
            </Button>
        </div>
    );
}

export function ReviewStep({ data, options, collegeSubjects, onJumpToStep }: ReviewStepProps): ReactNode {
    const subjectCode = data.classification === "college" ? data.subject_code : data.subject_code_shs;
    const enabledFeatures = featureToggles.filter((toggle) => Boolean(data.settings[toggle.key]));

    return (
        <div className="space-y-5">
            <div className="grid gap-5 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Identity</CardTitle>
                        <CardDescription>What this class is and who it is for.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <SummaryRow
                            label="Type"
                            value={optionLabel(options.classifications, data.classification)}
                            stepIndex={STEP_IDENTITY}
                            onJumpToStep={onJumpToStep}
                        />

                        {data.classification === "college" ? (
                            <SummaryRow
                                label={data.course_codes.length > 1 ? `Programs (${data.course_codes.length})` : "Program"}
                                value={
                                    data.course_codes.length === 0 ? (
                                        "Not set"
                                    ) : (
                                        <div className="flex flex-wrap gap-1.5">
                                            {lookupLabels(data.course_codes, options.courses).map((label, index) => (
                                                <Badge key={`course-${index}`} variant="secondary">
                                                    {label}
                                                </Badge>
                                            ))}
                                        </div>
                                    )
                                }
                                stepIndex={STEP_IDENTITY}
                                onJumpToStep={onJumpToStep}
                            />
                        ) : null}

                        {data.classification === "college" ? (
                            <SummaryRow
                                label={data.subject_ids.length > 1 ? `Subjects (${data.subject_ids.length})` : "Subject"}
                                value={
                                    data.subject_ids.length === 0 ? (
                                        "Not set"
                                    ) : (
                                        <div className="flex flex-wrap gap-1.5">
                                            {lookupLabels(data.subject_ids, collegeSubjects).map((label, index) => (
                                                <Badge key={`subject-${index}`} variant="secondary">
                                                    {label}
                                                </Badge>
                                            ))}
                                        </div>
                                    )
                                }
                                stepIndex={STEP_IDENTITY}
                                onJumpToStep={onJumpToStep}
                            />
                        ) : null}

                        <SummaryRow label="Class code" value={subjectCode || "Not set"} stepIndex={STEP_IDENTITY} onJumpToStep={onJumpToStep} />
                        <SummaryRow label="Section" value={data.section} stepIndex={STEP_IDENTITY} onJumpToStep={onJumpToStep} />
                        <SummaryRow
                            label="Capacity"
                            value={`${data.maximum_slots} slots`}
                            stepIndex={STEP_IDENTITY}
                            onJumpToStep={onJumpToStep}
                        />
                        <SummaryRow
                            label="Term"
                            value={`${data.school_year} · Semester ${data.semester}`}
                            stepIndex={STEP_IDENTITY}
                            onJumpToStep={onJumpToStep}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Teaching</CardTitle>
                        <CardDescription>Who teaches it and where students go.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <SummaryRow
                            label="Faculty"
                            value={optionLabel(options.faculties, data.faculty_id)}
                            stepIndex={STEP_TEACHING}
                            onJumpToStep={onJumpToStep}
                        />
                        <SummaryRow
                            label="Primary room"
                            value={optionLabel(options.rooms, data.room_id)}
                            stepIndex={STEP_TEACHING}
                            onJumpToStep={onJumpToStep}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Schedule</CardTitle>
                        <CardDescription>The weekly meetings of this class.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {data.schedules.length === 0 ? (
                            <SummaryRow label="Blocks" value="Not set" stepIndex={STEP_SCHEDULE} onJumpToStep={onJumpToStep} />
                        ) : (
                            data.schedules.map((schedule, index) => (
                                <SummaryRow
                                    key={`schedule-${index}`}
                                    label={schedule.day_of_week}
                                    value={`${schedule.start_time}–${schedule.end_time} · ${optionLabel(options.rooms, schedule.room_id)}`}
                                    stepIndex={STEP_SCHEDULE}
                                    onJumpToStep={onJumpToStep}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Policy</CardTitle>
                        <CardDescription>What the students of this class can do.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <SummaryRow
                            label="Features"
                            value={
                                enabledFeatures.length === 0 ? (
                                    <span className="text-muted-foreground">None enabled</span>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {enabledFeatures.map((toggle) => (
                                            <Badge key={toggle.key} variant="outline">
                                                {toggle.label}
                                            </Badge>
                                        ))}
                                    </div>
                                )
                            }
                            stepIndex={STEP_POLICY}
                            onJumpToStep={onJumpToStep}
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
