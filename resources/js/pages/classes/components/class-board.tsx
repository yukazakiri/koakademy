import { ClassData } from "@/components/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import { IconSchool } from "@tabler/icons-react";
import { useMemo } from "react";
import { DAYS, getDayNameFromDate, isValidDay, ScheduleEvent } from "../hooks/use-class-schedule";

// Helper functions for themes
type BoardTheme = {
    background: string;
    accentBg: string;
    accentText: string;
    hoverShadow: string;
};

const BOARD_THEMES: Record<string, BoardTheme> = {
    violet: {
        background: "bg-gradient-to-br from-violet-950/35 via-background to-background",
        accentBg: "bg-violet-500/15",
        accentText: "text-violet-500",
        hoverShadow: "hover:shadow-violet-500/10",
    },
    indigo: {
        background: "bg-gradient-to-br from-indigo-950/35 via-background to-background",
        accentBg: "bg-indigo-500/15",
        accentText: "text-indigo-500",
        hoverShadow: "hover:shadow-indigo-500/10",
    },
    blue: {
        background: "bg-gradient-to-br from-blue-950/35 via-background to-background",
        accentBg: "bg-blue-500/15",
        accentText: "text-blue-500",
        hoverShadow: "hover:shadow-blue-500/10",
    },
    cyan: {
        background: "bg-gradient-to-br from-cyan-950/35 via-background to-background",
        accentBg: "bg-cyan-500/15",
        accentText: "text-cyan-500",
        hoverShadow: "hover:shadow-cyan-500/10",
    },
    emerald: {
        background: "bg-gradient-to-br from-emerald-950/35 via-background to-background",
        accentBg: "bg-emerald-500/15",
        accentText: "text-emerald-500",
        hoverShadow: "hover:shadow-emerald-500/10",
    },
    lime: {
        background: "bg-gradient-to-br from-lime-950/35 via-background to-background",
        accentBg: "bg-lime-500/15",
        accentText: "text-lime-500",
        hoverShadow: "hover:shadow-lime-500/10",
    },
    amber: {
        background: "bg-gradient-to-br from-amber-950/35 via-background to-background",
        accentBg: "bg-amber-500/15",
        accentText: "text-amber-500",
        hoverShadow: "hover:shadow-amber-500/10",
    },
    orange: {
        background: "bg-gradient-to-br from-orange-950/35 via-background to-background",
        accentBg: "bg-orange-500/15",
        accentText: "text-orange-500",
        hoverShadow: "hover:shadow-orange-500/10",
    },
    rose: {
        background: "bg-gradient-to-br from-rose-950/35 via-background to-background",
        accentBg: "bg-rose-500/15",
        accentText: "text-rose-500",
        hoverShadow: "hover:shadow-rose-500/10",
    },
    fuchsia: {
        background: "bg-gradient-to-br from-fuchsia-950/35 via-background to-background",
        accentBg: "bg-fuchsia-500/15",
        accentText: "text-fuchsia-500",
        hoverShadow: "hover:shadow-fuchsia-500/10",
    },
    background: {
        background: "bg-gradient-to-br from-muted/25 via-background to-background",
        accentBg: "bg-primary/10",
        accentText: "text-primary",
        hoverShadow: "hover:shadow-primary/10",
    },
};

function extractColorToken(bgClass: string | null | undefined): string | null {
    if (!bgClass) return null;
    const match = bgClass.match(/\bbg-([a-z]+)-\d+\b/);
    if (!match) return null;
    return match[1] ?? null;
}

function resolveBoardTheme(classItem: ClassData): BoardTheme {
    const settings = (classItem.settings ?? {}) as Record<string, unknown>;
    const backgroundClass = typeof settings.background_color === "string" ? settings.background_color : null;
    const accentClass = typeof settings.accent_color === "string" ? settings.accent_color : null;

    const backgroundToken = extractColorToken(backgroundClass) ?? "background";
    const accentToken = extractColorToken(accentClass) ?? "blue";

    const backgroundTheme = BOARD_THEMES[backgroundToken] ?? BOARD_THEMES.background;
    const accentTheme = BOARD_THEMES[accentToken] ?? BOARD_THEMES.blue;

    return {
        ...backgroundTheme,
        accentBg: accentTheme.accentBg,
        accentText: accentTheme.accentText,
    };
}

function resolveAccentVisual(classItem: ClassData) {
    const settings = (classItem.settings ?? {}) as Record<string, unknown>;
    const settingsAccent = typeof settings.accent_color === "string" ? settings.accent_color : null;
    const modelAccent = typeof classItem.accent_color === "string" ? classItem.accent_color : null;

    const raw = settingsAccent || modelAccent;
    if (raw && raw.includes("bg-")) {
        return { className: raw };
    }

    if (raw) {
        return {
            className: "bg-transparent",
            style: { backgroundColor: raw },
        };
    }

    return { className: "bg-primary" };
}

const DAY_TO_INDEX: Record<string, number> = {
    Monday: 1,
    Tuesday: 2,
    Wednesday: 3,
    Thursday: 4,
    Friday: 5,
    Saturday: 6,
    Sunday: 0,
};

export function ClassBoard({
    events,
    classes,
    conflictIds,
    pinnedIds,
    onTogglePinned,
    onEdit,
    filterDay,
}: {
    events: ScheduleEvent[];
    classes: ClassData[];
    conflictIds: Set<string>;
    pinnedIds: Set<string>;
    onTogglePinned: (id: string) => void;
    onEdit: (classItem: ClassData) => void;
    filterDay: string;
}) {
    const today = useMemo(() => getDayNameFromDate(new Date()), []);

    const idsInScope = useMemo(() => new Set(classes.map((c) => String(c.id))), [classes]);

    const scopedEvents = useMemo(() => {
        return events
            .filter((event) => idsInScope.has(String(event.classItem.id)))
            .sort((a, b) => {
                if (a.day !== b.day) {
                    return DAY_TO_INDEX[a.day] - DAY_TO_INDEX[b.day];
                }

                return a.startMinutes - b.startMinutes;
            });
    }, [events, idsInScope]);

    const visibleDays = useMemo(() => {
        if (filterDay !== "all" && isValidDay(filterDay)) {
            return [filterDay];
        }
        return DAYS;
    }, [filterDay]);

    const pinnedClasses = useMemo(() => {
        return classes.filter((classItem) => pinnedIds.has(String(classItem.id)));
    }, [classes, pinnedIds]);

    const unscheduledClasses = useMemo(() => {
        const scheduledIds = new Set(scopedEvents.map((event) => String(event.classItem.id)));
        return classes.filter((classItem) => !scheduledIds.has(String(classItem.id)));
    }, [classes, scopedEvents]);

    const eventsByDay = useMemo(() => {
        const map = new Map<string, ScheduleEvent[]>();
        for (const day of DAYS) {
            map.set(day, []);
        }

        for (const event of scopedEvents) {
            map.get(event.day)?.push(event);
        }

        for (const day of DAYS) {
            map.get(day)?.sort((a, b) => a.startMinutes - b.startMinutes);
        }

        return map;
    }, [scopedEvents]);

    if (!classes.length) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <div className="bg-muted rounded-full p-8">
                    <IconSchool className="text-muted-foreground h-12 w-12" />
                </div>
                <h3 className="mt-6 text-xl font-bold">No Classes Found</h3>
                <p className="text-muted-foreground mt-2 max-w-sm text-sm">Try clearing filters.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="scrollbar-hide flex gap-4 overflow-x-auto pb-2">
                <BoardColumn title="Pinned" subtitle="Your most-used classes" tone={pinnedClasses.length ? "default" : "muted"}>
                    {pinnedClasses.length ? (
                        pinnedClasses.map((classItem) => (
                            <BoardTile
                                key={`pinned-${classItem.id}`}
                                classItem={classItem}
                                conflictIds={conflictIds}
                                pinnedIds={pinnedIds}
                                subtitle=""
                                onTogglePinned={onTogglePinned}
                                onEdit={onEdit}
                            />
                        ))
                    ) : (
                        <div className="border-border/60 bg-background rounded-lg border border-dashed p-3">
                            <p className="text-muted-foreground text-xs">Pin classes to keep them here.</p>
                        </div>
                    )}
                </BoardColumn>

                {visibleDays.map((day) => {
                    const dayEvents = eventsByDay.get(day) ?? [];

                    return (
                        <BoardColumn key={day} title={day} subtitle={day === today ? "Today" : ""} tone={day === today ? "active" : "default"}>
                            {dayEvents.length ? (
                                dayEvents.map((event) => (
                                    <BoardEventTile
                                        key={`${event.classItem.id}-${day}-${event.startMinutes}`}
                                        event={event}
                                        conflictIds={conflictIds}
                                        pinnedIds={pinnedIds}
                                        onTogglePinned={onTogglePinned}
                                        onEdit={onEdit}
                                    />
                                ))
                            ) : (
                                <div className="border-border/60 bg-background rounded-lg border border-dashed p-3">
                                    <p className="text-muted-foreground text-xs">No scheduled classes.</p>
                                </div>
                            )}
                        </BoardColumn>
                    );
                })}

                <BoardColumn title="Unscheduled" subtitle="Needs a schedule" tone={unscheduledClasses.length ? "warning" : "muted"}>
                    {unscheduledClasses.length ? (
                        unscheduledClasses.map((classItem) => (
                            <BoardTile
                                key={`unscheduled-${classItem.id}`}
                                classItem={classItem}
                                conflictIds={conflictIds}
                                pinnedIds={pinnedIds}
                                subtitle="Schedule missing"
                                onTogglePinned={onTogglePinned}
                                onEdit={onEdit}
                            />
                        ))
                    ) : (
                        <div className="border-border/60 bg-background rounded-lg border border-dashed p-3">
                            <p className="text-muted-foreground text-xs">All classes are scheduled.</p>
                        </div>
                    )}
                </BoardColumn>
            </div>
        </div>
    );
}

function BoardColumn({
    title,
    subtitle,
    tone,
    children,
}: {
    title: string;
    subtitle?: string;
    tone: "default" | "active" | "warning" | "muted";
    children: React.ReactNode;
}) {
    const headerTone =
        tone === "active"
            ? "border-primary/30 bg-primary/5"
            : tone === "warning"
              ? "border-amber-500/30 bg-amber-500/5"
              : "border-border/60 bg-card/50";

    return (
        <div className="w-[320px] shrink-0">
            <div className="border-border/60 bg-card/40 rounded-xl border">
                <div className={cn("border-b px-4 py-3", headerTone)}>
                    <p className="text-foreground text-sm font-semibold">{title}</p>
                    {subtitle ? <p className="text-muted-foreground text-xs">{subtitle}</p> : null}
                </div>
                <div className="space-y-3 p-3">{children}</div>
            </div>
        </div>
    );
}

function BoardEventTile({
    event,
    conflictIds,
    pinnedIds,
    onTogglePinned,
    onEdit,
}: {
    event: ScheduleEvent;
    conflictIds: Set<string>;
    pinnedIds: Set<string>;
    onTogglePinned: (id: string) => void;
    onEdit: (classItem: ClassData) => void;
}) {
    return (
        <BoardTile
            classItem={event.classItem}
            conflictIds={conflictIds}
            pinnedIds={pinnedIds}
            subtitle={`${event.startLabel}-${event.endLabel} • ${event.roomLabel}`}
            onTogglePinned={onTogglePinned}
            onEdit={onEdit}
        />
    );
}

function BoardTile({
    classItem,
    conflictIds,
    pinnedIds,
    subtitle,
    onTogglePinned,
    onEdit,
}: {
    classItem: ClassData;
    conflictIds: Set<string>;
    pinnedIds: Set<string>;
    subtitle: string;
    onTogglePinned: (id: string) => void;
    onEdit: (classItem: ClassData) => void;
}) {
    const classId = String(classItem.id);
    const isPinned = pinnedIds.has(classId);
    const theme = resolveBoardTheme(classItem);
    const accent = resolveAccentVisual(classItem);

    const { props } = usePage<{ user: User }>();
    const user = props.user;

    const isFaculty = ["professor", "instructor", "associate_professor", "assistant_professor", "part_time_faculty"].includes(user.role);
    const isStudent = ["student", "shs_student", "graduate_student"].includes(user.role);
    const isAdmin = ["admin", "super_admin", "developer", "president"].includes(user.role);

    const getBaseUrl = () => {
        if (isFaculty) return "/faculty/classes";
        if (isStudent) return "/student/classes";
        if (isAdmin) return "/administrators/classes";
        return "/classes";
    };

    const baseUrl = getBaseUrl();

    return (
        <div
            className={cn(
                "group border-border/60 bg-background/70 overflow-hidden rounded-lg border backdrop-blur transition-shadow",
                theme.hoverShadow,
            )}
        >
            <div className={cn("px-3 py-2", theme.background)}>
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <span className={cn("size-2.5 rounded-full", accent.className)} style={accent.style} />
                            <p className="text-foreground truncate text-sm font-semibold">
                                {classItem.subject_code} • {classItem.section}
                            </p>
                            {conflictIds.has(classId) ? (
                                <Badge variant="destructive" className="rounded-md">
                                    Conflict
                                </Badge>
                            ) : null}
                        </div>
                        <p className="text-muted-foreground mt-1 truncate text-xs">{classItem.subject_title}</p>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        variant={isPinned ? "secondary" : "outline"}
                        className="h-8 rounded-md px-2"
                        onClick={() => onTogglePinned(classId)}
                    >
                        {isPinned ? "Pinned" : "Pin"}
                    </Button>
                </div>

                {subtitle ? <p className="text-muted-foreground mt-2 truncate text-xs">{subtitle}</p> : null}
            </div>

            <div className="border-border/60 bg-background/60 flex items-center gap-2 border-t px-3 py-2">
                <Button asChild size="sm" className="h-8 rounded-md">
                    <Link href={`${baseUrl}/${classId}`}>Open</Link>
                </Button>
                <Button asChild size="sm" variant="outline" className="h-8 rounded-md">
                    <Link href={`${baseUrl}/${classId}?view=attendance`} prefetch>
                        Attendance
                    </Link>
                </Button>
                <Button type="button" size="sm" variant="ghost" className="ml-auto h-8 rounded-md" onClick={() => onEdit(classItem)}>
                    Edit
                </Button>
            </div>
        </div>
    );
}
