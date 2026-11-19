import { Alert, AlertDescription } from "@/components/ui/alert";
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
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { show as showClass } from "@/routes/administrators/classes";
import { update as updateSchedule } from "@/routes/administrators/classes/schedule";
import { Link, router, useForm } from "@inertiajs/react";
import { CalendarPlus, Trash2 } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { scheduleDayOrder } from "./schedule-utils";
import type { ClassShowData, RawScheduleEntry, RoomOption } from "./types";

type Meeting = RawScheduleEntry & { temp_id: string; delivery_mode: "face_to_face" | "online" };
type SchedulePayload = { id?: number; day_of_week: string; start_time: string; end_time: string; delivery_mode: string; room_id: number | null };

const selectClass =
    "border-input bg-background text-foreground focus-visible:ring-ring min-h-10 w-full min-w-0 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60";
// Native selects keep day, mode and room choices usable with mobile pickers and keyboards.

function meetingsFromServer(schedules: RawScheduleEntry[], defaultMode: ClassShowData["delivery_mode"]): Meeting[] {
    return schedules.map((schedule, index) => ({
        ...schedule,
        temp_id: schedule.temp_id ?? `existing-${schedule.id ?? index}`,
        delivery_mode: schedule.delivery_mode ?? defaultMode,
    }));
}

function previewDateTime(schedule: Meeting): string {
    return `${schedule.day_of_week} ${schedule.start_time || "—"}–${schedule.end_time || "—"}`;
}

function meetingLocation(meeting: Pick<Meeting, "room_id" | "delivery_mode" | "room_name">, rooms: RoomOption[]): string {
    if (meeting.delivery_mode === "online") return "Online";
    if (meeting.room_id === null) return "Room unassigned";
    return rooms.find((room) => room.id === meeting.room_id)?.label ?? meeting.room_name ?? "Room unassigned";
}

export function ScheduleEditor({
    classItem,
    initialSchedules,
    revision,
    rooms,
    onClose,
}: {
    classItem: ClassShowData;
    initialSchedules: RawScheduleEntry[];
    revision: string;
    rooms: RoomOption[];
    onClose: () => void;
}) {
    const form = useForm<{ schedules: Meeting[]; schedule_revision: string }>({
        schedules: meetingsFromServer(initialSchedules, classItem.delivery_mode),
        schedule_revision: revision,
    });
    const [discardOpen, setDiscardOpen] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [applyGroup, setApplyGroup] = useState<Record<string, boolean>>({});
    const baseline = useRef(initialSchedules);
    const allowNextVisit = useRef(false);
    const nextId = useRef(0);
    const editorHeading = useRef<HTMLHeadingElement>(null);
    const initialById = useMemo(() => new Map(baseline.current.map((schedule) => [schedule.id, schedule])), []);
    const errors = form.errors as Record<string, string>;

    useEffect(() => {
        editorHeading.current?.focus();
    }, []);

    const updateMeeting = (tempId: string, changes: Partial<Meeting>) => {
        form.setData(
            "schedules",
            form.data.schedules.map((meeting) => (meeting.temp_id === tempId ? { ...meeting, ...changes } : meeting)),
        );
        const staleRevision = errors.schedule_revision;
        form.clearErrors();
        if (staleRevision) form.setError("schedule_revision", staleRevision);
    };

    const effectiveMeetings = useMemo(() => {
        const meetings = form.data.schedules.map((meeting) => ({ ...meeting }));
        for (const [tempId, enabled] of Object.entries(applyGroup)) {
            if (!enabled) continue;
            const source = meetings.find((meeting) => meeting.temp_id === tempId);
            if (!source?.pattern_group_id || meetings.filter((meeting) => meeting.pattern_group_id === source.pattern_group_id).length < 2) continue;
            for (const meeting of meetings) {
                if (meeting.pattern_group_id === source.pattern_group_id && meeting.temp_id !== source.temp_id) {
                    meeting.start_time = source.start_time;
                    meeting.end_time = source.end_time;
                }
            }
        }
        return meetings;
    }, [form.data.schedules, applyGroup]);

    const localErrors = useMemo(() => {
        const result: Record<string, string> = {};
        effectiveMeetings.forEach((meeting, index) => {
            if (!meeting.start_time || !meeting.end_time || meeting.end_time <= meeting.start_time) {
                result[meeting.temp_id] = "End time must be after start time.";
            } else if (
                effectiveMeetings.some(
                    (other, otherIndex) =>
                        index !== otherIndex &&
                        meeting.day_of_week === other.day_of_week &&
                        meeting.start_time < other.end_time &&
                        meeting.end_time > other.start_time,
                )
            ) {
                result[meeting.temp_id] = "This meeting overlaps another meeting in this class.";
            }
        });
        return result;
    }, [effectiveMeetings]);

    const changes = useMemo(() => {
        const lines: string[] = [];
        for (const meeting of effectiveMeetings) {
            const before = initialById.get(meeting.id);
            const room = meetingLocation(meeting, rooms);
            if (!before) {
                lines.push(`Add ${previewDateTime(meeting)} · ${room}`);
            } else if (
                before.day_of_week !== meeting.day_of_week ||
                before.start_time !== meeting.start_time ||
                before.end_time !== meeting.end_time ||
                (before.delivery_mode ?? classItem.delivery_mode) !== meeting.delivery_mode ||
                before.room_id !== meeting.room_id
            ) {
                const original = { ...meeting, ...before } as Meeting;
                original.delivery_mode = before.delivery_mode ?? classItem.delivery_mode;
                lines.push(`Change ${previewDateTime(original)} · ${meetingLocation(original, rooms)} to ${previewDateTime(meeting)} · ${room}`);
            }
        }
        for (const before of baseline.current) {
            if (!effectiveMeetings.some((meeting) => meeting.id === before.id)) {
                lines.push(`Remove ${before.day_of_week} ${before.start_time}–${before.end_time}`);
            }
        }
        return lines;
    }, [effectiveMeetings, initialById, rooms, classItem.delivery_mode]);
    const isDirty = changes.length > 0;

    useEffect(() => {
        if (!isDirty) return;
        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", beforeUnload);
        const removeListener = router.on("before", (event) => {
            if (event.detail.visit.prefetch || event.detail.visit.async) return;
            if (allowNextVisit.current) {
                allowNextVisit.current = false;
                return;
            }
            return window.confirm("Discard unsaved schedule changes and leave this page?");
        });
        return () => {
            window.removeEventListener("beforeunload", beforeUnload);
            removeListener();
        };
    }, [isDirty]);

    const addMeeting = () => {
        const startsAt = "08:00";
        const endsAt = "09:00";
        const nextDay = scheduleDayOrder.find((day) =>
            form.data.schedules.every((meeting) => meeting.day_of_week !== day || meeting.end_time <= startsAt || meeting.start_time >= endsAt),
        );
        const next: Meeting = {
            temp_id: `new-${++nextId.current}`,
            day_of_week: nextDay ?? "Monday",
            start_time: startsAt,
            end_time: endsAt,
            delivery_mode: classItem.delivery_mode,
            room_id: null,
        };
        form.setData("schedules", [...form.data.schedules, next]);
        const staleRevision = errors.schedule_revision;
        form.clearErrors();
        if (staleRevision) form.setError("schedule_revision", staleRevision);
    };

    const save = () => {
        const schedules: SchedulePayload[] = effectiveMeetings.map(({ id, day_of_week, start_time, end_time, delivery_mode, room_id }) => ({
            ...(id ? { id } : {}),
            day_of_week,
            start_time,
            end_time,
            delivery_mode,
            room_id: delivery_mode === "online" ? null : room_id,
        }));
        form.transform(() => ({ schedules, schedule_revision: revision }));
        setPreviewOpen(false);
        allowNextVisit.current = true;
        form.patch(updateSchedule.url(classItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Schedule updated.");
                onClose();
            },
            onError: () => toast.error("Schedule could not be saved. Review the highlighted meetings."),
            onFinish: () => {
                allowNextVisit.current = false;
            },
        });
    };

    return (
        <Card aria-label="Edit weekly schedule">
            <CardHeader>
                <CardTitle>
                    <h2 ref={editorHeading} tabIndex={-1}>
                        Update weekly meetings
                    </h2>
                </CardTitle>
                <CardDescription>Changes apply every week. Review before saving.</CardDescription>
            </CardHeader>
            <CardContent className="flex min-w-0 flex-col gap-4">
                {errors.schedules && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.schedules}</AlertDescription>
                    </Alert>
                )}
                {errors.schedule_revision && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.schedule_revision}</AlertDescription>
                        <Button variant="outline" size="sm" nativeButton={false} render={<Link href={showClass.url(classItem.id)} />}>
                            Reload class
                        </Button>
                    </Alert>
                )}
                {form.data.schedules.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No meetings yet. Add one to create a weekly schedule.</p>
                ) : (
                    <div className="divide-y">
                        {form.data.schedules.map((meeting, index) => {
                            const groupMembers = meeting.pattern_group_id
                                ? form.data.schedules.filter((item) => item.pattern_group_id === meeting.pattern_group_id)
                                : [];
                            const groupSource = groupMembers.length > 1 ? groupMembers.find((item) => applyGroup[item.temp_id]) : undefined;
                            const timeControlledByGroup = Boolean(groupSource && groupSource.temp_id !== meeting.temp_id);
                            const issue = localErrors[meeting.temp_id] ?? errors[`schedules.${index}`];
                            const fieldError = (field: string) => errors[`schedules.${index}.${field}`];
                            return (
                                <section
                                    key={meeting.temp_id}
                                    className="flex min-w-0 flex-col gap-3 py-4 first:pt-0"
                                    aria-label={`Meeting ${index + 1}`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-sm font-semibold">Meeting {index + 1}</h3>
                                            {meeting.pattern_group_id && groupMembers.length > 1 && (
                                                <Badge variant="outline">Recurring pattern</Badge>
                                            )}
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={form.processing}
                                            aria-label={`Remove meeting ${index + 1}`}
                                            onClick={() => {
                                                form.setData(
                                                    "schedules",
                                                    form.data.schedules.filter((item) => item.temp_id !== meeting.temp_id),
                                                );
                                                setApplyGroup((current) => ({ ...current, [meeting.temp_id]: false }));
                                                const staleRevision = errors.schedule_revision;
                                                form.clearErrors();
                                                if (staleRevision) form.setError("schedule_revision", staleRevision);
                                            }}
                                        >
                                            <Trash2 aria-hidden="true" /> Remove
                                        </Button>
                                    </div>
                                    <div className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                        <div className="flex min-w-0 flex-col gap-1.5">
                                            <Label htmlFor={`day-${meeting.temp_id}`}>Day</Label>
                                            <select
                                                id={`day-${meeting.temp_id}`}
                                                aria-invalid={Boolean(issue || fieldError("day_of_week"))}
                                                aria-describedby={issue ? `error-${meeting.temp_id}` : undefined}
                                                className={selectClass}
                                                value={meeting.day_of_week}
                                                disabled={form.processing}
                                                onChange={(event) => updateMeeting(meeting.temp_id, { day_of_week: event.target.value })}
                                            >
                                                {scheduleDayOrder.map((day) => (
                                                    <option key={day} value={day}>
                                                        {day}
                                                    </option>
                                                ))}
                                            </select>
                                            {fieldError("day_of_week") && <p className="text-destructive text-xs">{fieldError("day_of_week")}</p>}
                                        </div>
                                        <div className="flex min-w-0 flex-col gap-1.5">
                                            <Label htmlFor={`start-${meeting.temp_id}`}>Start</Label>
                                            <Input
                                                id={`start-${meeting.temp_id}`}
                                                aria-invalid={Boolean(issue || fieldError("start_time"))}
                                                aria-describedby={issue ? `error-${meeting.temp_id}` : undefined}
                                                type="time"
                                                value={timeControlledByGroup ? groupSource!.start_time : meeting.start_time}
                                                disabled={form.processing || timeControlledByGroup}
                                                onChange={(event) => updateMeeting(meeting.temp_id, { start_time: event.target.value })}
                                            />
                                            {fieldError("start_time") && <p className="text-destructive text-xs">{fieldError("start_time")}</p>}
                                        </div>
                                        <div className="flex min-w-0 flex-col gap-1.5">
                                            <Label htmlFor={`end-${meeting.temp_id}`}>End</Label>
                                            <Input
                                                id={`end-${meeting.temp_id}`}
                                                aria-invalid={Boolean(issue || fieldError("end_time"))}
                                                aria-describedby={issue ? `error-${meeting.temp_id}` : undefined}
                                                type="time"
                                                value={timeControlledByGroup ? groupSource!.end_time : meeting.end_time}
                                                disabled={form.processing || timeControlledByGroup}
                                                onChange={(event) => updateMeeting(meeting.temp_id, { end_time: event.target.value })}
                                            />
                                            {fieldError("end_time") && <p className="text-destructive text-xs">{fieldError("end_time")}</p>}
                                        </div>
                                        <div className="flex min-w-0 flex-col gap-1.5">
                                            <Label htmlFor={`delivery-${meeting.temp_id}`}>Meeting type</Label>
                                            <select
                                                id={`delivery-${meeting.temp_id}`}
                                                className={selectClass}
                                                value={meeting.delivery_mode}
                                                disabled={form.processing}
                                                onChange={(event) =>
                                                    updateMeeting(meeting.temp_id, {
                                                        delivery_mode: event.target.value as Meeting["delivery_mode"],
                                                        room_id: event.target.value === "online" ? null : meeting.room_id,
                                                    })
                                                }
                                            >
                                                <option value="face_to_face">Face to face</option>
                                                <option value="online">Online</option>
                                            </select>
                                        </div>
                                        <div className="flex min-w-0 flex-col gap-1.5">
                                            <Label htmlFor={`room-${meeting.temp_id}`}>Room</Label>
                                            <select
                                                id={`room-${meeting.temp_id}`}
                                                aria-invalid={Boolean(issue || fieldError("room_id"))}
                                                aria-describedby={issue ? `error-${meeting.temp_id}` : undefined}
                                                className={selectClass}
                                                value={meeting.room_id ?? ""}
                                                disabled={form.processing || meeting.delivery_mode === "online"}
                                                onChange={(event) =>
                                                    updateMeeting(meeting.temp_id, {
                                                        room_id: event.target.value ? Number(event.target.value) : null,
                                                        room_name: null,
                                                    })
                                                }
                                            >
                                                <option value="">{meeting.delivery_mode === "online" ? "Not needed online" : "Assign later"}</option>
                                                {meeting.room_id && !rooms.some((room) => room.id === meeting.room_id) && (
                                                    <option value={meeting.room_id}>
                                                        {meeting.room_name ?? `Room ${meeting.room_id}`} (inactive)
                                                    </option>
                                                )}
                                                {rooms.map((room) => (
                                                    <option key={room.id} value={room.id}>
                                                        {room.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {fieldError("room_id") && <p className="text-destructive text-xs">{fieldError("room_id")}</p>}
                                        </div>
                                    </div>
                                    {timeControlledByGroup && (
                                        <p className="text-muted-foreground text-xs">
                                            Time follows meeting {form.data.schedules.findIndex((item) => item.temp_id === groupSource?.temp_id) + 1}{" "}
                                            in this pattern.
                                        </p>
                                    )}
                                    {groupMembers.length > 1 && (
                                        <div className="flex flex-col gap-1">
                                            <label className="flex items-start gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-1"
                                                    checked={Boolean(applyGroup[meeting.temp_id])}
                                                    disabled={form.processing || Boolean(groupSource && groupSource.temp_id !== meeting.temp_id)}
                                                    onChange={(event) =>
                                                        setApplyGroup((current) => ({ ...current, [meeting.temp_id]: event.target.checked }))
                                                    }
                                                />
                                                Apply this start and end time to all {groupMembers.length} meetings in this pattern
                                            </label>
                                            <p className="text-muted-foreground text-xs">
                                                Days, meeting types, and rooms stay unchanged. Preview shows each affected meeting.
                                            </p>
                                        </div>
                                    )}
                                    {issue && (
                                        <p id={`error-${meeting.temp_id}`} role="alert" className="text-destructive text-sm">
                                            {issue}
                                        </p>
                                    )}
                                </section>
                            );
                        })}
                    </div>
                )}
                {isDirty && classItem.settings.features.notify_students_on_schedule_changes && (
                    <p className="text-muted-foreground text-xs">Enrolled students will be notified of schedule changes.</p>
                )}
                <div>
                    <Button type="button" variant="outline" size="sm" onClick={addMeeting} disabled={form.processing}>
                        <CalendarPlus aria-hidden="true" /> Add meeting
                    </Button>
                </div>
                <p className="text-muted-foreground text-xs" role="status">
                    {isDirty ? "Unsaved schedule changes" : "No unsaved changes"}
                </p>
            </CardContent>
            <CardFooter className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" disabled={form.processing} onClick={() => (isDirty ? setDiscardOpen(true) : onClose())}>
                    Cancel
                </Button>
                <Button
                    type="button"
                    disabled={!isDirty || form.processing || Boolean(errors.schedule_revision) || Object.keys(localErrors).length > 0}
                    onClick={() => setPreviewOpen(true)}
                >
                    {form.processing ? "Saving…" : "Review changes"}
                </Button>
            </CardFooter>
            <AlertDialog open={discardOpen} onOpenChange={setDiscardOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Discard schedule changes?</AlertDialogTitle>
                        <AlertDialogDescription>Unsaved meeting changes will be lost.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Keep editing</AlertDialogCancel>
                        <AlertDialogAction onClick={onClose}>Discard changes</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <AlertDialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Review schedule changes</AlertDialogTitle>
                        <AlertDialogDescription>Confirm the weekly meetings below. Conflicts will be checked when you save.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <ul className="max-h-64 list-disc space-y-2 overflow-auto pl-5 text-sm">
                        {changes.map((change, index) => (
                            <li key={`${index}-${change}`}>{change}</li>
                        ))}
                    </ul>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Keep editing</AlertDialogCancel>
                        <AlertDialogAction onClick={save}>Save schedule</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Card>
    );
}
