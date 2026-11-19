import {
    EventCalendar,
    useEventCalendar,
    useEventCalendarOccurrences,
    useEventCalendarSelector,
    useEventCalendarState,
    type EventCalendarApi,
} from "@/components/reui/event-calendar/event-calendar";
import { spansMultipleDays } from "@/components/reui/event-calendar/event-calendar-lib";
import { expandRecurrence, parseRRuleString } from "@/components/reui/event-calendar/event-calendar-recurrence";
import { EventCalendarWeekView } from "@/components/reui/event-calendar/event-calendar-time-grid";
import type { CalendarEvent } from "@/components/reui/event-calendar/event-calendar-types";
import { Suspense, startTransition, useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";

const date = (value: string) => new Date(value);
const event = (id: string, start: string, end: string, recurrence?: string): CalendarEvent => ({
    id,
    title: id,
    start: date(start),
    end: date(end),
    recurrence,
});
const apiRef: { current: EventCalendarApi | null } = { current: null };
const original = [
    event("original", "2024-01-01T08:00:00Z", "2024-01-01T09:00:00Z", "FREQ=WEEKLY"),
    event("milestone", "2024-01-01T00:00:00Z", "2024-01-01T00:00:00Z"),
];
const replacement = [event("replacement", "2024-01-01T10:00:00Z", "2024-01-01T11:00:00Z")];
const never = new Promise(() => {});

function Probe({ suffix }: { suffix: string }) {
    const calendar = useEventCalendar();
    const selected = useEventCalendarSelector((state) => `${state.events[0]?.id}:${suffix}`);
    const first = useEventCalendarOccurrences({ start: date("2025-01-01T00:00:00Z"), end: date("2025-02-01T00:00:00Z") });
    const second = useEventCalendarOccurrences({ start: date("2025-02-01T00:00:00Z"), end: date("2025-03-01T00:00:00Z") });
    Object.assign(window, {
        calendarProbe: () => ({ api: calendar.api.getEvents()[0]?.id, selected: document.querySelector("#selection")?.textContent }),
        calendarNotify: () => calendar.internals.setDrag(null),
        calendarState: () => calendar.getState(),
    });
    return (
        <span id="selection">
            {selected}:{first.length}:{second.length}
        </span>
    );
}

function Suspend({ enabled }: { enabled: boolean }) {
    if (enabled) {
        Object.assign(window, { suspendedRenderAttempted: true });
        throw never;
    }
    return null;
}

function AtomicProbe() {
    const instance = useEventCalendarState({
        defaultEvents: [event("atomic", "2024-01-01T08:00:00Z", "2024-01-01T09:00:00Z")],
        defaultView: "week",
        defaultDate: date("2024-01-01T12:00:00Z"),
        timeZone: "UTC",
    });
    useEffect(() => {
        Object.assign(window, {
            checkAtomic: () => {
                const oldKey = "atomic::2024-01-01T08:00:00.000Z";
                instance.api.selectEvent(oldKey);
                const states: boolean[] = [];
                const unsubscribe = instance.subscribe(() => {
                    const selected = instance.getState().selection.eventKeys[0];
                    states.push(instance.api.getOccurrences().some((item) => item.key === selected));
                });
                instance.api.updateEvent("atomic", { start: date("2024-01-01T10:00:00Z"), end: date("2024-01-01T11:00:00Z") });
                unsubscribe();
                return states;
            },
        });
    }, [instance]);
    return null;
}

function App() {
    const [pending, setPending] = useState(false);
    const [visible, setVisible] = useState(true);
    const [version, setVersion] = useState(0);
    const [calendarEvents, setCalendarEvents] = useState(original);
    const saved = useRef(apiRef);
    Object.assign(window, {
        startSuspended: () => startTransition(() => setPending(true)),
        changeEvents: () => setCalendarEvents(replacement),
        rerenderProbe: () => setVersion((value) => value + 1),
        hideCalendar: () => setVisible(false),
        apiRefValue: () => saved.current.current,
        rangeIdentity: () => {
            const first = { start: date("2025-01-01T00:00:00Z"), end: date("2025-02-01T00:00:00Z") };
            const second = { start: date("2025-02-01T00:00:00Z"), end: date("2025-03-01T00:00:00Z") };
            const api = saved.current.current!;
            const a = api.getOccurrences(first);
            api.getOccurrences(second);
            const again = api.getOccurrences(first);
            return { count: a.length, stable: a[0] === again[0] };
        },
        rangeBoundaries: () => {
            const api = saved.current.current!;
            return {
                empty: api.getOccurrences({ start: date("2024-01-01T08:30:00Z"), end: date("2024-01-01T08:30:00Z") }).length,
                included: api
                    .getOccurrences({ start: date("2024-01-01T00:00:00Z"), end: date("2024-01-01T01:00:00Z") })
                    .some((occurrence) => occurrence.eventId === "milestone"),
                excluded: api
                    .getOccurrences({ start: date("2023-12-31T23:00:00Z"), end: date("2024-01-01T00:00:00Z") })
                    .some((occurrence) => occurrence.eventId === "milestone"),
            };
        },
        recurrenceCases: () => {
            const expand = (entry: CalendarEvent, start: string, end: string, timeZone = "UTC") =>
                expandRecurrence(entry, { start: date(start), end: date(end) }, { timeZone });
            const monthly = event("monthly", "2026-01-31T09:00:00Z", "2026-01-31T10:00:00Z", "FREQ=MONTHLY;COUNT=2");
            const lastDay = { ...monthly, recurrence: "FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=2" };
            const point = event("point", "2026-01-01T09:00:00Z", "2026-01-01T09:00:00Z", "FREQ=DAILY;COUNT=3");
            const extras = { ...point, recurrence: { freq: "daily" as const, count: 3, rDates: [date("2026-01-01T10:00:00Z")] } };
            const allDay = { ...event("dst", "2026-03-08T05:00:00Z", "2026-03-09T04:00:00Z", "FREQ=WEEKLY;COUNT=2"), allDay: true };
            let unsupported = false;
            try {
                expand({ ...monthly, recurrence: "FREQ=YEARLY;BYDAY=20MO" }, "2026-01-01T00:00:00Z", "2027-01-01T00:00:00Z");
            } catch {
                unsupported = true;
            }
            return {
                monthly: expand(monthly, "2026-01-01T00:00:00Z", "2026-05-01T00:00:00Z").map((o) => o.start.toISOString()),
                far: expand(monthly, "2026-03-01T00:00:00Z", "2026-04-01T00:00:00Z").map((o) => o.start.toISOString()),
                lastDay: expand(lastDay, "2026-01-01T00:00:00Z", "2026-04-01T00:00:00Z").map((o) => o.start.toISOString()),
                point: expand(point, "2026-01-02T09:00:00Z", "2026-01-03T09:00:00Z").map((o) => o.start.toISOString()),
                extraIndices: ["2026-01-02T00:00:00Z", "2026-01-04T00:00:00Z"].map(
                    (end) =>
                        expand(extras, "2026-01-01T00:00:00Z", end).find((o) => o.start.toISOString() === "2026-01-01T10:00:00.000Z")
                            ?.recurrenceIndex ?? null,
                ),
                dst: expand(allDay, "2026-03-16T03:30:00Z", "2026-03-16T04:00:00Z", "America/New_York").map((o) => o.start.toISOString()),
                plus: parseRRuleString("FREQ=MONTHLY;BYDAY=+1MO").byWeekday,
                floating: parseRRuleString("FREQ=DAILY;UNTIL=20261020T090000").until?.toISOString(),
                overnight: spansMultipleDays({ start: date("2024-01-01T23:00:00Z"), end: date("2024-01-02T01:00:00Z") }, "UTC"),
                unsupported,
            };
        },
    });
    return (
        <Suspense fallback={<span>Suspended</span>}>
            <AtomicProbe />
            {visible && (
                <EventCalendar
                    apiRef={apiRef}
                    events={pending ? replacement : calendarEvents}
                    defaultView="week"
                    defaultDate={date("2024-01-01T12:00:00Z")}
                    timeZone="UTC"
                    weekStartsOn={1}
                    className="h-[560px]"
                    scrollToHour={7}
                    canSelectSlot={(slot) => slot.end.getTime() - slot.start.getTime() <= 30 * 60000}
                    onSelectSlot={(slot) => Object.assign(window, { selectedSlot: slot })}
                >
                    <Probe suffix={pending ? "pending" : `committed-${version}`} />
                    <Suspend enabled={pending} />
                    <EventCalendarWeekView showAllDay={false} />
                </EventCalendar>
            )}
            <EventCalendar
                defaultEvents={original}
                defaultView="week"
                defaultDate={date("2024-01-01T12:00:00Z")}
                timeZone="UTC"
                weekStartsOn={1}
                className="h-[200px]"
            >
                <EventCalendarWeekView showAllDay={false} />
            </EventCalendar>
        </Suspense>
    );
}

createRoot(document.getElementById("root")!).render(<App />);
