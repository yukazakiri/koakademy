import { EventCalendar, type EventCalendarRenderEventProps } from "@/components/reui/event-calendar/event-calendar";
import { EventCalendarWeekView } from "@/components/reui/event-calendar/event-calendar-time-grid";
import type { CalendarEvent, EventCalendarProposedUpdate, EventCalendarSlotDraft } from "@/components/reui/event-calendar/event-calendar-types";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { show as showClass } from "@/routes/administrators/classes";
import { update as updateSchedule } from "@/routes/administrators/classes/schedule";
import { Link, router, useForm } from "@inertiajs/react";
import { AlertTriangle, CalendarDays, CalendarPlus, MousePointer2, Trash2 } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { scheduleDayOrder } from "./schedule-utils";
import type { ClassShowData, RawScheduleEntry, RoomOption } from "./types";

type Meeting = RawScheduleEntry & { temp_id: string; delivery_mode: "face_to_face" | "online" };
type SchedulePayload = { id?: number; day_of_week: string; start_time: string; end_time: string; delivery_mode: string; room_id: number | null };
type CalendarMeetingData = { location: string; issue?: string };

// Anchor to a week without a daylight-saving transition; only weekday and wall time persist.
const REFERENCE_MONDAY = new Date(2024, 0, 1, 12, 0, 0);

function toCalendarDate(day: string, time: string): Date | null {
    const dayIndex = scheduleDayOrder.indexOf(day);
    const match = /^(\d{2}):(\d{2})$/.exec(time);
    if (dayIndex < 0 || !match || Number(match[1]) > 23 || Number(match[2]) > 59) return null;
    return new Date(2024, 0, 1 + dayIndex, Number(match[1]), Number(match[2]), 0);
}

function fromCalendarTime(date: Date): string {
    return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

function renderMeeting({ occurrence }: EventCalendarRenderEventProps<CalendarMeetingData>) {
    const data = occurrence.event.data;
    return (
        <span className="flex min-w-0 flex-col gap-0.5">
            <span className="truncate font-semibold">{occurrence.event.title.split(" · ")[1] ?? occurrence.event.title}</span>
            <span className="truncate text-xs">{data?.location}</span>
            {data?.issue && <span className="text-destructive truncate text-xs">Conflict</span>}
        </span>
    );
}

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
    const [selectedId, setSelectedId] = useState<string | null>(() => form.data.schedules[0]?.temp_id ?? null);
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

    const clearEditedMeetingErrors = (tempId: string) => {
        const index = form.data.schedules.findIndex((meeting) => meeting.temp_id === tempId);
        const keys = Object.keys(errors).filter(
            (key) => key === "schedules" || key === `schedules.${index}` || key.startsWith(`schedules.${index}.`),
        );
        if (keys.length) form.clearErrors(...(keys as (keyof typeof form.data)[]));
    };

    const updateMeeting = (tempId: string, changes: Partial<Meeting>) => {
        form.setData(
            "schedules",
            form.data.schedules.map((meeting) => (meeting.temp_id === tempId ? { ...meeting, ...changes } : meeting)),
        );
        clearEditedMeetingErrors(tempId);
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

    const events = useMemo<CalendarEvent<CalendarMeetingData>[]>(
        () =>
            effectiveMeetings.flatMap((meeting, index) => {
                const start = toCalendarDate(meeting.day_of_week, meeting.start_time);
                const end = toCalendarDate(meeting.day_of_week, meeting.end_time);
                if (!start || !end || end <= start) return [];
                return [
                    {
                        id: meeting.temp_id,
                        title: `${meeting.day_of_week} · ${fromCalendarTime(start)} – ${fromCalendarTime(end)}`,
                        start,
                        end,
                        color: localErrors[meeting.temp_id] || errors[`schedules.${index}`] ? "var(--color-destructive)" : "var(--color-primary)",
                        data: { location: meetingLocation(meeting, rooms), issue: localErrors[meeting.temp_id] ?? errors[`schedules.${index}`] },
                    },
                ];
            }),
        [effectiveMeetings, rooms, localErrors, errors],
    );

    const selectedIndex = form.data.schedules.findIndex((meeting) => meeting.temp_id === selectedId);
    const selectedMeeting = form.data.schedules[selectedIndex];
    const selectedGroup = selectedMeeting?.pattern_group_id
        ? form.data.schedules.filter((meeting) => meeting.pattern_group_id === selectedMeeting.pattern_group_id)
        : [];
    const groupSource = selectedGroup.length > 1 ? selectedGroup.find((meeting) => applyGroup[meeting.temp_id]) : undefined;
    const timeControlledByGroup = Boolean(groupSource && groupSource.temp_id !== selectedId);
    const selectedIssue = selectedMeeting ? (localErrors[selectedMeeting.temp_id] ?? errors[`schedules.${selectedIndex}`]) : undefined;
    const fieldError = (field: string) => (selectedMeeting ? errors[`schedules.${selectedIndex}.${field}`] : undefined);
    const selectedEvent = events.find((event) => event.id === selectedId);

    const selectMeeting = (id: string) => {
        setSelectedId(id);
        window.requestAnimationFrame(() => document.getElementById(`day-${id}`)?.focus());
    };

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

    const addMeeting = (slot?: EventCalendarSlotDraft) => {
        const startsAt = "08:00";
        const endsAt = "09:00";
        const nextDay = scheduleDayOrder.find((day) =>
            form.data.schedules.every((meeting) => meeting.day_of_week !== day || meeting.end_time <= startsAt || meeting.start_time >= endsAt),
        );
        const next: Meeting = {
            temp_id: `new-${++nextId.current}`,
            day_of_week: slot ? scheduleDayOrder[(slot.start.getDay() + 6) % 7] : (nextDay ?? "Monday"),
            start_time: slot ? fromCalendarTime(slot.start) : startsAt,
            end_time: slot ? fromCalendarTime(slot.end) : endsAt,
            delivery_mode: classItem.delivery_mode,
            room_id: null,
        };
        form.setData("schedules", [...form.data.schedules, next]);
        setSelectedId(next.temp_id);
        if (errors.schedules) form.clearErrors("schedules");
    };

    const removeMeeting = (id: string) => {
        const index = form.data.schedules.findIndex((meeting) => meeting.temp_id === id);
        const retained = Object.entries(errors).flatMap(([key, value]): [string, string][] => {
            const match = /^schedules\.(\d+)(.*)$/.exec(key);
            if (!match) return key === "schedules" ? [] : [[key, value]];
            const previousIndex = Number(match[1]);
            if (previousIndex === index) return [];
            return [[`schedules.${previousIndex > index ? previousIndex - 1 : previousIndex}${match[2]}`, value]];
        });
        form.setData(
            "schedules",
            form.data.schedules.filter((meeting) => meeting.temp_id !== id),
        );
        setApplyGroup((current) => ({ ...current, [id]: false }));
        setSelectedId(form.data.schedules.find((meeting) => meeting.temp_id !== id)?.temp_id ?? null);
        form.clearErrors();
        if (retained.length) form.setError(Object.fromEntries(retained));
    };

    const handleCalendarUpdate = (update: EventCalendarProposedUpdate<CalendarMeetingData>): false => {
        if (update.allDay || update.start.toDateString() !== update.end.toDateString()) {
            toast.error("Meetings must start and end on the same day.");
            return false;
        }
        if (update.start < new Date(2024, 0, 1) || update.end > new Date(2024, 0, 8)) {
            toast.error("Move meetings within the displayed week.");
            return false;
        }
        const meeting = form.data.schedules.find((item) => item.temp_id === update.event.id);
        if (!meeting) return false;
        const source =
            meeting.pattern_group_id &&
            form.data.schedules.find((item) => item.pattern_group_id === meeting.pattern_group_id && applyGroup[item.temp_id]);
        if (source && source.temp_id !== meeting.temp_id) {
            toast.error("This meeting follows another meeting’s pattern time. Edit that meeting or turn off the pattern option.");
            setSelectedId(source.temp_id);
            return false;
        }
        updateMeeting(meeting.temp_id, {
            day_of_week: scheduleDayOrder[(update.start.getDay() + 6) % 7],
            start_time: fromCalendarTime(update.start),
            end_time: fromCalendarTime(update.end),
        });
        setSelectedId(meeting.temp_id);
        // Form state is authoritative; reject the calendar's internal write.
        return false;
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
        form.transform((data) => ({ schedules, schedule_revision: data.schedule_revision }));
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
        <Frame aria-label="Edit weekly schedule" spacing="default">
            <FrameHeader>
                <FrameTitle>
                    <h2 ref={editorHeading} tabIndex={-1}>
                        Update weekly meetings
                    </h2>
                </FrameTitle>
                <FrameDescription>Drag meetings to move them, resize their edges, or select one to edit details.</FrameDescription>
            </FrameHeader>
            <FramePanel className="flex min-w-0 flex-col gap-4">
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
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                        <CalendarDays className="size-4" aria-hidden="true" />
                        <span>Weekly recurring schedule</span>
                        <Badge variant="outline">{form.data.schedules.length} meetings</Badge>
                    </div>
                    <Button type="button" variant="outline" size="sm" onClick={() => addMeeting()} disabled={form.processing}>
                        <CalendarPlus aria-hidden="true" /> Add meeting
                    </Button>
                </div>
                <div className="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)]">
                    <div className="min-w-0">
                        <p className="text-muted-foreground mb-3 text-xs">
                            Drag across empty time to add a meeting. Select a block to edit it. Swipe sideways to see all days on a narrow screen.
                        </p>
                        <div className="overflow-x-auto" role="region" aria-label="Editable weekly timetable" tabIndex={0}>
                            <EventCalendar<CalendarMeetingData>
                                events={events}
                                selection={{
                                    eventKeys: selectedEvent ? [`${selectedEvent.id}::${selectedEvent.start.toISOString()}`] : [],
                                    slot: null,
                                }}
                                defaultDate={REFERENCE_MONDAY}
                                defaultView="week"
                                view="week"
                                views={["week"]}
                                weekStartsOn={1}
                                i18n={{ formats: { dayAria: "EEEE" } }}
                                dayStartHour={0}
                                dayEndHour={24}
                                scrollToHour={7}
                                interval={60}
                                snapDuration={15}
                                slotDuration={60}
                                viewSettings={{ weekends: true, nowIndicator: false }}
                                interactions={{ drag: !form.processing, resize: !form.processing, selectSlot: !form.processing }}
                                onEventClick={(occurrence) => selectMeeting(occurrence.eventId)}
                                onEventUpdate={handleCalendarUpdate}
                                onSelectSlot={(slot) => {
                                    if (!slot.allDay && slot.start.toDateString() === slot.end.toDateString()) addMeeting(slot);
                                }}
                                renderDayHeader={({ day }) => <span>{scheduleDayOrder[(day.getDay() + 6) % 7].slice(0, 3)}</span>}
                                renderEvent={renderMeeting}
                                className="h-[560px] w-full min-w-[700px]"
                            >
                                <EventCalendarWeekView showAllDay={false} />
                            </EventCalendar>
                        </div>
                        {form.data.schedules.length === 0 && (
                            <p className="text-muted-foreground mt-3 text-sm">No meetings yet. Select Add meeting or drag across the timetable.</p>
                        )}
                        {form.data.schedules.some((meeting) => !events.some((event) => event.id === meeting.temp_id)) && (
                            <div className="mt-3 flex flex-col gap-2" role="alert">
                                <p className="text-destructive text-sm">
                                    Some meetings have invalid times and cannot appear on the timetable. Select one to correct it.
                                </p>
                                {form.data.schedules
                                    .filter((meeting) => !events.some((event) => event.id === meeting.temp_id))
                                    .map((meeting) => (
                                        <Button
                                            key={meeting.temp_id}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => selectMeeting(meeting.temp_id)}
                                        >
                                            Edit {meeting.day_of_week} meeting
                                        </Button>
                                    ))}
                            </div>
                        )}
                    </div>
                    <div className="min-w-0 border-t pt-4 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-4">
                        {!selectedMeeting ? (
                            <div className="text-muted-foreground flex flex-col items-start gap-2 text-sm">
                                <MousePointer2 className="size-4" aria-hidden="true" />
                                <p>Select a meeting on the timetable to edit its details.</p>
                            </div>
                        ) : (
                            (() => {
                                const meeting = selectedMeeting;
                                const issue = selectedIssue;
                                return (
                                    <section
                                        key={meeting.temp_id}
                                        className="flex min-w-0 flex-col gap-3 py-4 first:pt-0"
                                        aria-label={`Meeting ${selectedIndex + 1}`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-sm font-semibold">Meeting {selectedIndex + 1}</h3>
                                                {meeting.pattern_group_id && selectedGroup.length > 1 && (
                                                    <Badge variant="outline">Recurring pattern</Badge>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={form.processing}
                                                aria-label={`Remove meeting ${selectedIndex + 1}`}
                                                onClick={() => removeMeeting(meeting.temp_id)}
                                            >
                                                <Trash2 aria-hidden="true" /> Remove
                                            </Button>
                                        </div>
                                        <div className="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-1">
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
                                                    <option value="">
                                                        {meeting.delivery_mode === "online" ? "Not needed online" : "Assign later"}
                                                    </option>
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
                                                Time follows meeting{" "}
                                                {form.data.schedules.findIndex((item) => item.temp_id === groupSource?.temp_id) + 1} in this pattern.
                                            </p>
                                        )}
                                        {selectedGroup.length > 1 && (
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
                                                    Apply this start and end time to all {selectedGroup.length} meetings in this pattern
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
                            })()
                        )}
                    </div>
                </div>
                {Object.entries(localErrors).map(([id, message]) => {
                    const meeting = effectiveMeetings.find((item) => item.temp_id === id);
                    if (!meeting || id === selectedId) return null;
                    return (
                        <Button key={id} type="button" variant="ghost" size="sm" onClick={() => selectMeeting(id)}>
                            <AlertTriangle aria-hidden="true" /> {meeting.day_of_week}: {message}
                        </Button>
                    );
                })}
                {Object.entries(errors)
                    .filter(([key]) => /^schedules\.\d+/.test(key))
                    .map(([key, message]) => {
                        const index = Number(key.split(".")[1]);
                        const meeting = form.data.schedules[index];
                        if (!meeting || meeting.temp_id === selectedId) return null;
                        return (
                            <Button key={key} type="button" variant="ghost" size="sm" onClick={() => selectMeeting(meeting.temp_id)}>
                                <AlertTriangle aria-hidden="true" /> {meeting.day_of_week}: {message}
                            </Button>
                        );
                    })}
                {isDirty && classItem.settings.features.notify_students_on_schedule_changes && (
                    <p className="text-muted-foreground text-xs">Enrolled students will be notified of schedule changes.</p>
                )}
                <p className="text-muted-foreground text-xs" role="status">
                    {isDirty ? "Unsaved schedule changes" : "No unsaved changes"}
                </p>
            </FramePanel>
            <FrameFooter className="flex flex-col gap-2 sm:flex-row sm:justify-end">
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
            </FrameFooter>
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
        </Frame>
    );
}
