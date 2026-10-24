import { cn } from "@/lib/utils";
import {
    buildScheduleDays,
    scheduleTimeLabel,
    scheduleTimeRange,
    scheduleWindow,
    type WeeklyScheduleBlock,
    type WeeklyScheduleEvent,
} from "./weekly-schedule-utils";

export type { WeeklyScheduleEvent } from "./weekly-schedule-utils";

type WeeklyScheduleProps = {
    events: WeeklyScheduleEvent[];
    onSelect?: (event: WeeklyScheduleEvent) => void;
    emptyMessage?: string;
};

export function WeeklySchedule({ events, onSelect, emptyMessage = "No sessions scheduled yet." }: WeeklyScheduleProps) {
    const days = buildScheduleDays(events).filter(({ day, blocks }) => day !== "Sunday" || blocks.length > 0);
    const window = scheduleWindow(days.flatMap(({ blocks }) => blocks));

    if (!window) return <p className="text-muted-foreground p-6 text-sm text-pretty sm:p-8">{emptyMessage}</p>;

    const pixelsPerMinute = 1.2;
    const height = (window.end - window.start) * pixelsPerMinute;
    const hours = Array.from({ length: (window.end - window.start) / 60 }, (_, index) => window.start + index * 60);
    const gridTemplateColumns = `4rem repeat(${days.length}, minmax(0, 1fr))`;

    return (
        <div className="min-w-0">
            <div className="divide-y md:hidden" aria-label="Weekly schedule">
                {days
                    .filter(({ blocks }) => blocks.length)
                    .map(({ day, blocks }) => (
                        <section key={day} aria-label={day} className="space-y-3 p-4">
                            <h3 className="text-sm font-semibold">{day}</h3>
                            <ul className="space-y-2">
                                {blocks.map((block) => (
                                    <li key={block.event.id}>
                                        <ScheduleEvent block={block} day={day} onSelect={onSelect} />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
            </div>

            <div className="hidden max-h-[44rem] overflow-auto md:block" role="region" aria-label="Weekly schedule" tabIndex={0}>
                <div className="min-w-[44rem]">
                    <div className="bg-card sticky top-0 z-20 grid border-b text-sm" style={{ gridTemplateColumns }}>
                        <div className="text-muted-foreground p-3 text-right">Time</div>
                        {days.map(({ day }) => (
                            <div key={day} className="p-3 text-center font-medium">
                                <span aria-hidden="true">{day.slice(0, 3)}</span>
                                <span className="sr-only">{day}</span>
                            </div>
                        ))}
                    </div>
                    <div className="relative grid" style={{ height, gridTemplateColumns }}>
                        <div className="bg-card relative z-10">
                            {hours.map((minute) => (
                                <span
                                    key={minute}
                                    className="text-muted-foreground absolute right-3 pt-1 text-xs tabular-nums"
                                    style={{ top: (minute - window.start) * pixelsPerMinute }}
                                >
                                    {scheduleTimeLabel(minute)}
                                </span>
                            ))}
                        </div>
                        {days.map(({ day, blocks }) => (
                            <div key={day} className="border-border/60 relative border-l" role="group" aria-label={day}>
                                {hours.map((minute) => (
                                    <div
                                        key={minute}
                                        aria-hidden="true"
                                        className="border-border/50 pointer-events-none absolute inset-x-0 border-t"
                                        style={{ top: (minute - window.start) * pixelsPerMinute }}
                                    />
                                ))}
                                {blocks.map((block) => (
                                    <div
                                        key={block.event.id}
                                        className="absolute p-0.5"
                                        style={{
                                            top: (block.startMinutes - window.start) * pixelsPerMinute,
                                            height: (block.endMinutes - block.startMinutes) * pixelsPerMinute,
                                            left: `${(block.lane / block.laneCount) * 100}%`,
                                            width: `${100 / block.laneCount}%`,
                                        }}
                                    >
                                        <ScheduleEvent block={block} day={day} onSelect={onSelect} compact />
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function ScheduleEvent({
    block,
    day,
    onSelect,
    compact = false,
}: {
    block: WeeklyScheduleBlock;
    day: string;
    onSelect?: WeeklyScheduleProps["onSelect"];
    compact?: boolean;
}) {
    const { event, startMinutes, endMinutes } = block;
    const time = `${scheduleTimeLabel(startMinutes)} – ${scheduleTimeLabel(endMinutes)}`;
    const location = event.online ? "Online" : event.location;
    const label = [day, time, event.title, location, event.conflict ? "Schedule conflict" : ""].filter(Boolean).join(", ");
    const className = cn(
        "flex w-full min-w-0 flex-col gap-1 rounded-md border p-3 text-left text-sm",
        compact && "h-full overflow-hidden p-2 text-xs",
        event.conflict ? "border-destructive/50 bg-destructive/5" : "border-primary/15 bg-primary/5",
        onSelect &&
            "hover:bg-primary/10 focus-visible:outline-ring active:bg-primary/15 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2",
    );
    const content = (
        <>
            {event.title && <span className="truncate font-semibold">{event.title}</span>}
            <span className="truncate tabular-nums">{compact ? scheduleTimeRange(startMinutes, endMinutes) : time}</span>
            {location && <span className="text-muted-foreground truncate">{location}</span>}
            {event.conflict && <span className="sr-only">Schedule conflict</span>}
        </>
    );

    return onSelect ? (
        <button type="button" className={className} onClick={() => onSelect(event)} aria-label={label} title={label}>
            {content}
        </button>
    ) : (
        <div className={className} aria-label={label} title={label}>
            {content}
        </div>
    );
}
