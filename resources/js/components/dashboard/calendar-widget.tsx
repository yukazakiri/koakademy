"use client";

import { format } from "date-fns";
import * as React from "react";
import { DayButton, getDefaultClassNames } from "react-day-picker";

import { Calendar } from "@/components/ui/calendar";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { IconCalendar, IconCalendarEvent, IconChevronRight, IconClock, IconMapPin } from "@tabler/icons-react";
import { motion } from "framer-motion";

interface CalendarEvent {
    id: number;
    title: string;
    description: string | null;
    location: string | null;
    start_datetime: string;
    end_datetime: string | null;
    is_all_day: boolean;
    type: string;
    category: string;
    status: string;
    color: string;
}

interface CalendarWidgetProps {
    events: CalendarEvent[];
}

const TIME_OPTIONS = [
    "12:00 AM",
    "12:30 AM",
    "01:00 AM",
    "01:30 AM",
    "02:00 AM",
    "02:30 AM",
    "03:00 AM",
    "03:30 AM",
    "04:00 AM",
    "04:30 AM",
    "05:00 AM",
    "05:30 AM",
    "06:00 AM",
    "06:30 AM",
    "07:00 AM",
    "07:30 AM",
    "08:00 AM",
    "08:30 AM",
    "09:00 AM",
    "09:30 AM",
    "10:00 AM",
    "10:30 AM",
    "11:00 AM",
    "11:30 AM",
    "12:00 PM",
    "12:30 PM",
    "01:00 PM",
    "01:30 PM",
    "02:00 PM",
    "02:30 PM",
    "03:00 PM",
    "03:30 PM",
    "04:00 PM",
    "04:30 PM",
    "05:00 PM",
    "05:30 PM",
    "06:00 PM",
    "06:30 PM",
    "07:00 PM",
    "07:30 PM",
    "08:00 PM",
    "08:30 PM",
    "09:00 PM",
    "09:30 PM",
    "10:00 PM",
    "10:30 PM",
    "11:00 PM",
    "11:30 PM",
];

function getEventColor(type?: string, category?: string): string {
    switch (type) {
        case "academic_calendar":
            return "bg-emerald-500";
        case "resource_booking":
            return "bg-amber-500";
        default:
            switch (category) {
                case "academic":
                    return "bg-blue-500";
                case "administrative":
                    return "bg-violet-500";
                case "extracurricular":
                    return "bg-pink-500";
                case "social":
                    return "bg-indigo-500";
                case "sports":
                    return "bg-red-500";
                case "cultural":
                    return "bg-orange-500";
                case "holiday":
                    return "bg-green-500";
                default:
                    return "bg-gray-500";
            }
    }
}

function formatEventTime(startDatetime: string, endDatetime: string | null, isAllDay: boolean): string {
    if (isAllDay) {
        return "All day";
    }

    const start = new Date(startDatetime);
    const startStr = start.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
    });

    if (!endDatetime) {
        return startStr;
    }

    const end = new Date(endDatetime);
    const endStr = end.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
    });

    return `${startStr} - ${endStr}`;
}

type CustomDayButtonProps = React.ComponentProps<typeof DayButton> & {
    eventsData?: Record<string, CalendarEvent[]>;
    onEventClick?: (date: Date) => void;
};

const CustomDayButton = React.forwardRef<HTMLButtonElement, CustomDayButtonProps>(
    ({ className, day, modifiers, eventsData, onEventClick, ...props }, ref) => {
        const defaultClassNames = getDefaultClassNames();
        const dateKey = format(day.date, "yyyy-MM-dd");
        const dayEvents = (eventsData || {})[dateKey] || [];
        const hasEvents = dayEvents.length > 0;

        const handleEventClick = (e: React.MouseEvent, clickedDate: Date) => {
            e.stopPropagation();
            if (onEventClick) {
                onEventClick(clickedDate);
            }
        };

        return (
            <div className="relative flex min-h-[52px] w-full flex-col items-center gap-1">
                <button
                    ref={ref}
                    data-selected-single={modifiers.selected && !modifiers.range_start && !modifiers.range_end && !modifiers.range_middle}
                    data-range-start={modifiers.range_start}
                    data-range-end={modifiers.range_end}
                    data-range-middle={modifiers.range_middle}
                    data-today={modifiers.today}
                    data-disabled={modifiers.disabled}
                    className={cn(
                        "data-[selected-single=true]:bg-primary data-[selected-single=true]:text-primary-foreground data-[range-middle=true]:bg-accent data-[range-middle=true]:text-accent-foreground data-[range-start=true]:bg-primary data-[range-start=true]:text-primary-foreground data-[range-end=true]:bg-primary data-[range-end=true]:text-primary-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring flex aspect-square size-auto h-10 w-full min-w-[40px] cursor-pointer flex-col items-center justify-center rounded-md text-sm font-normal transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50",
                        modifiers.today &&
                            "bg-primary/10 text-primary data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground font-semibold",
                        defaultClassNames.day,
                        className,
                    )}
                    {...props}
                >
                    {day.date.getDate()}
                </button>
                {hasEvents && (
                    <div className="absolute bottom-1.5 flex max-w-full flex-wrap justify-center gap-0.5 px-1">
                        {dayEvents.slice(0, 3).map((event) => (
                            <div
                                key={event.id}
                                onClick={(e) => handleEventClick(e, day.date)}
                                className={cn(
                                    "h-1.5 w-1.5 cursor-pointer rounded-full transition-transform hover:scale-125",
                                    getEventColor(event.type, event.category),
                                )}
                                title={event.title}
                            />
                        ))}
                        {dayEvents.length > 3 && (
                            <div
                                onClick={(e) => handleEventClick(e, day.date)}
                                className="text-muted-foreground cursor-pointer text-[8px] font-medium transition-opacity hover:opacity-70"
                            >
                                +{dayEvents.length - 3}
                            </div>
                        )}
                    </div>
                )}
            </div>
        );
    },
);
CustomDayButton.displayName = "CustomDayButton";

export function CalendarWidget({ events }: CalendarWidgetProps) {
    const [currentMonth, setCurrentMonth] = React.useState<Date>(new Date());
    const [date, setDate] = React.useState<Date | undefined>(new Date());
    const [selectedDate, setSelectedDate] = React.useState<Date | null>(null);
    const [isDialogOpen, setIsDialogOpen] = React.useState(false);

    const eventsByDate = React.useMemo(() => {
        const grouped: Record<string, CalendarEvent[]> = {};
        events.forEach((event) => {
            const dateKey = format(new Date(event.start_datetime), "yyyy-MM-dd");
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(event);
        });
        return grouped;
    }, [events]);

    const handleDateSelect = (selectedDate: Date | undefined) => {
        if (selectedDate) {
            setDate(selectedDate);
            setSelectedDate(selectedDate);
            const dateKey = format(selectedDate, "yyyy-MM-dd");
            const dayEvents = eventsByDate[dateKey] || [];
            if (dayEvents.length > 0) {
                setIsDialogOpen(true);
            }
        }
    };

    const handleEventClick = (clickedDate: Date) => {
        setDate(clickedDate);
        setSelectedDate(clickedDate);
        setIsDialogOpen(true);
    };

    const selectedDateKey = selectedDate ? format(selectedDate, "yyyy-MM-dd") : date ? format(date, "yyyy-MM-dd") : null;

    const selectedDateEvents = selectedDateKey ? eventsByDate[selectedDateKey] || [] : [];

    const upcomingEvents = React.useMemo(() => {
        const now = new Date();
        return events.filter((event) => new Date(event.start_datetime) >= now).slice(0, 5);
    }, [events]);

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="border-border/60 bg-card/80 overflow-hidden rounded-2xl border backdrop-blur-sm"
        >
            <div className="border-border/60 from-muted/30 flex items-center justify-between border-b bg-gradient-to-r to-transparent p-4">
                <div className="flex items-center gap-2.5">
                    <div className="from-primary/20 to-primary/5 flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br">
                        <IconCalendar className="text-primary h-4 w-4" />
                    </div>
                    <div>
                        <h2 className="text-foreground text-base font-semibold">Events</h2>
                        <p className="text-muted-foreground text-xs">
                            {events.length} upcoming event{events.length !== 1 ? "s" : ""}
                        </p>
                    </div>
                </div>
            </div>

            <div className="p-3">
                <Calendar
                    mode="single"
                    selected={date}
                    onSelect={handleDateSelect}
                    month={currentMonth}
                    onMonthChange={setCurrentMonth}
                    defaultMonth={new Date()}
                    className="w-full rounded-xl border-0 p-0 [--cell-size:40px]"
                    components={{
                        DayButton: (props) => <CustomDayButton {...props} eventsData={eventsByDate} onEventClick={handleEventClick} />,
                    }}
                />
            </div>

            {upcomingEvents.length > 0 && (
                <div className="border-border/50 border-t">
                    <div className="px-3 py-2">
                        <p className="text-muted-foreground text-xs font-medium">Upcoming</p>
                    </div>
                    <ScrollArea className="h-[140px]">
                        <div className="space-y-1 px-3 pb-3">
                            {upcomingEvents.map((event) => (
                                <motion.div
                                    key={event.id}
                                    initial={{ opacity: 0, x: -10 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    className="hover:bg-muted/40 flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors"
                                    onClick={() => {
                                        const eventDate = new Date(event.start_datetime);
                                        setDate(eventDate);
                                        setSelectedDate(eventDate);
                                        setIsDialogOpen(true);
                                    }}
                                >
                                    <div className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: event.color }} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-foreground truncate text-xs font-medium">{event.title}</p>
                                        <p className="text-muted-foreground text-[10px]">
                                            {format(new Date(event.start_datetime), "MMM d")} ·{" "}
                                            {formatEventTime(event.start_datetime, event.end_datetime, event.is_all_day)}
                                        </p>
                                    </div>
                                    <IconChevronRight className="text-muted-foreground/50 h-3.5 w-3.5 shrink-0" />
                                </motion.div>
                            ))}
                        </div>
                    </ScrollArea>
                </div>
            )}

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="sm:max-w-[450px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <IconCalendarEvent className="text-primary h-5 w-5" />
                            {selectedDate ? format(selectedDate, "EEEE, MMMM d, yyyy") : "Events"}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedDateEvents.length > 0
                                ? `${selectedDateEvents.length} event${selectedDateEvents.length > 1 ? "s" : ""} scheduled`
                                : "No events scheduled for this date"}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        {selectedDateEvents.length > 0 ? (
                            <ScrollArea className="max-h-[300px] pr-2">
                                <div className="space-y-3">
                                    {selectedDateEvents.map((event, index) => (
                                        <motion.div
                                            key={event.id}
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ delay: index * 0.05 }}
                                            className="border-border/50 bg-muted/20 rounded-lg border p-3"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex items-start gap-2">
                                                    <div className="mt-0.5 h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: event.color }} />
                                                    <div>
                                                        <h3 className="text-foreground text-sm font-semibold">{event.title}</h3>
                                                        <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                                            <span className="flex items-center gap-1">
                                                                <IconClock className="h-3 w-3" />
                                                                {formatEventTime(event.start_datetime, event.end_datetime, event.is_all_day)}
                                                            </span>
                                                            {event.location && (
                                                                <span className="flex items-center gap-1">
                                                                    <IconMapPin className="h-3 w-3" />
                                                                    {event.location}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {event.description && (
                                                            <p className="text-muted-foreground mt-2 text-xs leading-relaxed">{event.description}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </motion.div>
                                    ))}
                                </div>
                            </ScrollArea>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <div className="bg-muted/40 mb-3 rounded-full p-3">
                                    <IconCalendar className="text-muted-foreground h-5 w-5" />
                                </div>
                                <p className="text-muted-foreground text-sm">No events scheduled</p>
                                <p className="text-muted-foreground/60 mt-1 text-xs">This day is clear</p>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </motion.div>
    );
}
