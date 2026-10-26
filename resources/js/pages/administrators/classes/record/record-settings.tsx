import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { edit } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { ArrowUpRight, Check, Minus, Pencil } from "lucide-react";
import type { ClassShowData } from "./types";

const features = [
    { key: "enable_announcements", label: "Announcements", description: "Class updates and notices" },
    { key: "enable_grade_visibility", label: "Grade visibility", description: "Student access to recorded grades" },
    { key: "enable_attendance_tracking", label: "Attendance tracking", description: "Attendance records for class sessions" },
    { key: "allow_late_submissions", label: "Late submissions", description: "Submissions after the due date" },
    { key: "enable_discussion_board", label: "Discussion board", description: "Class discussions and conversations" },
    { key: "notify_students_on_schedule_changes", label: "Schedule notifications", description: "Notify students when the schedule changes" },
] as const;

export function RecordSettings({ classItem, canUpdate }: { classItem: ClassShowData; canUpdate: boolean }) {
    const courses =
        classItem.associated_courses && classItem.associated_courses !== "N/A"
            ? classItem.associated_courses
                  .split(",")
                  .map((course) => course.trim())
                  .filter(Boolean)
            : [];
    const customSettings = Object.entries(classItem.settings?.custom ?? {});

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.6fr)]">
            <section className="bg-card min-w-0 overflow-hidden rounded-xl border" aria-labelledby="class-features-heading">
                <div className="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-6">
                    <div className="space-y-1">
                        <h2 id="class-features-heading" className="text-base font-semibold">
                            Class settings
                        </h2>
                        <p className="text-muted-foreground text-sm">Features currently configured for this class.</p>
                    </div>
                    {canUpdate && (
                        <Button variant="outline" nativeButton={false} render={<Link href={edit(classItem.id)} />} className="h-9 px-3">
                            <Pencil className="size-4" aria-hidden="true" />
                            Edit settings
                        </Button>
                    )}
                </div>
                <dl className="divide-y border-t">
                    {features.map(({ key, label, description }) => {
                        const enabled = Boolean(classItem.settings?.features?.[key]);
                        return (
                            <div key={key} className="flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
                                <dt className="space-y-1">
                                    <span className="block text-sm font-medium">{label}</span>
                                    <span className="text-muted-foreground block text-sm">{description}</span>
                                </dt>
                                <dd className={`flex shrink-0 items-center gap-2 text-sm ${enabled ? "text-foreground" : "text-muted-foreground"}`}>
                                    {enabled ? <Check className="size-4" aria-hidden="true" /> : <Minus className="size-4" aria-hidden="true" />}
                                    {enabled ? "Enabled" : "Disabled"}
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </section>
            <div className="space-y-6">
                <section className="bg-card space-y-6 rounded-xl border p-4 sm:p-6" aria-labelledby="academic-context-heading">
                    <h2 id="academic-context-heading" className="text-base font-semibold">
                        Academic context
                    </h2>
                    <div className="space-y-3">
                        <h3 className="text-muted-foreground text-sm">Subjects</h3>
                        {classItem.subjects?.length ? (
                            <div className="flex flex-wrap gap-2">
                                {classItem.subjects.map((subject) => (
                                    <Badge key={subject} variant="outline" className="h-auto max-w-full whitespace-normal">
                                        {subject}
                                    </Badge>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm">No subjects listed</p>
                        )}
                    </div>
                    <div className="space-y-3">
                        <h3 className="text-muted-foreground text-sm">
                            {classItem.classification === "shs" ? "Track & strand" : "Associated courses"}
                        </h3>
                        {courses.length ? (
                            <div className="flex flex-wrap gap-2">
                                {courses.map((course) => (
                                    <Badge key={course} variant="secondary" className="h-auto max-w-full whitespace-normal">
                                        {course}
                                    </Badge>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm">Not specified</p>
                        )}
                    </div>
                </section>
                {customSettings.length > 0 && (
                    <section className="bg-card space-y-4 rounded-xl border p-4 sm:p-6" aria-labelledby="custom-settings-heading">
                        <h2 id="custom-settings-heading" className="text-base font-semibold">
                            Additional settings
                        </h2>
                        <dl className="space-y-4">
                            {customSettings.map(([key, value]) => (
                                <div key={key} className="space-y-1 text-sm">
                                    <dt className="text-muted-foreground">{key.replaceAll("_", " ")}</dt>
                                    <dd className="break-words">{String(value)}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                )}
                {classItem.filament?.view_url && (
                    <section className="bg-muted/20 space-y-3 rounded-xl border p-4 sm:p-6">
                        <h2 className="text-sm font-semibold">Administrative record</h2>
                        <p className="text-muted-foreground text-sm">Open this class in the administration panel.</p>
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={<a href={classItem.filament.view_url} target="_blank" rel="noreferrer" />}
                            className="h-9 px-3"
                        >
                            Open record
                            <ArrowUpRight className="size-4" aria-hidden="true" />
                            <span className="sr-only"> in a new tab</span>
                        </Button>
                    </section>
                )}
            </div>
        </div>
    );
}
