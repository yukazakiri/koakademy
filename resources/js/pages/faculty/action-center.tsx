import FacultyLayout from "@/components/faculty/faculty-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Toggle } from "@/components/ui/toggle";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    IconCalendar,
    IconCircleCheck,
    IconClock,
    IconEye,
    IconEyeOff,
    IconFilter,
    IconLayoutKanban,
    IconProgressCheck,
    IconSearch,
    IconTimeline,
    IconUsers,
} from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

type ActivityStatus = "backlog" | "in_progress" | "review" | "done" | "blocked";

type ActionCenterActivity = {
    id: string;
    source_id: string | number;
    type: string;
    status: ActivityStatus;
    priority: "low" | "medium" | "high";
    title: string;
    subtitle: string;
    meta: string;
    start_date: string;
    due_date: string;
    progress: number;
    assignee: {
        id?: string;
        name: string;
        avatar?: string;
        initials?: string;
        is_self: boolean;
    };
    primary_action: {
        label: string;
        href: string;
    };
};

type ActionCenterProps = {
    user: User;
    action_center: {
        date: string;
        activities: ActionCenterActivity[];
        stats: {
            active: number;
            done: number;
            focus: number;
            total: number;
            completion_rate: number;
            status_breakdown: {
                backlog: number;
                in_progress: number;
                review: number;
                blocked: number;
                done: number;
            };
        };
    };
};

type ViewMode = "board" | "gantt" | "assignments" | "progress";

type GanttRange = {
    start: Date;
    end: Date;
    days: number;
    label: string;
};

const priorityOrder: Record<ActionCenterActivity["priority"], number> = {
    high: 0,
    medium: 1,
    low: 2,
};

const statusLabels: Record<ActivityStatus, string> = {
    backlog: "Planned",
    in_progress: "In Progress",
    review: "Needs Review",
    done: "Completed",
    blocked: "Needs Help",
};

const statusDescriptions: Record<ActivityStatus, string> = {
    backlog: "Ready to schedule",
    in_progress: "Work happening now",
    review: "Waiting for review",
    done: "Finished work",
    blocked: "Needs attention",
};

function getInitials(name: string) {
    if (!name) return "??";
    return name
        .split(" ")
        .map((n) => n[0])
        .filter(Boolean)
        .join("")
        .substring(0, 2)
        .toUpperCase();
}

function PriorityBadge({ priority }: { priority: ActionCenterActivity["priority"] }) {
    if (priority === "high") {
        return (
            <Badge variant="outline" className="border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300">
                High
            </Badge>
        );
    }

    if (priority === "medium") {
        return (
            <Badge
                variant="outline"
                className="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
            >
                Medium
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
            Low
        </Badge>
    );
}

function StatusBadge({ status }: { status: ActivityStatus }) {
    if (status === "done") {
        return (
            <Badge variant="secondary" className="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                Completed
            </Badge>
        );
    }

    if (status === "review") {
        return (
            <Badge variant="secondary" className="bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400">
                Needs review
            </Badge>
        );
    }

    if (status === "blocked") {
        return (
            <Badge variant="destructive" className="bg-rose-100 text-rose-700 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-400">
                Needs help
            </Badge>
        );
    }

    if (status === "in_progress") {
        return (
            <Badge variant="secondary" className="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                In Progress
            </Badge>
        );
    }

    return (
        <Badge variant="secondary" className="bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400">
            Planned
        </Badge>
    );
}

function parseDate(value: string) {
    return new Date(`${value}T00:00:00`);
}

function addDays(date: Date, days: number) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
}

function dayDiff(start: Date, end: Date) {
    const msPerDay = 1000 * 60 * 60 * 24;
    return Math.round((end.getTime() - start.getTime()) / msPerDay);
}

function formatDateLabel(date: Date) {
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

function buildGanttRange(activities: ActionCenterActivity[]): GanttRange {
    const today = new Date();
    const fallbackStart = addDays(today, -3);
    const fallbackEnd = addDays(today, 10);

    if (activities.length === 0) {
        return {
            start: fallbackStart,
            end: fallbackEnd,
            days: 14,
            label: `${formatDateLabel(fallbackStart)} - ${formatDateLabel(fallbackEnd)}`,
        };
    }

    const dates = activities.flatMap((activity) => [parseDate(activity.start_date), parseDate(activity.due_date)]);

    const minDate = new Date(Math.min(...dates.map((date) => date.getTime())));
    const maxDate = new Date(Math.max(...dates.map((date) => date.getTime())));

    const rawDays = dayDiff(minDate, maxDate) + 1;
    const rangeStart = minDate;
    const rangeEnd = rawDays > 21 ? addDays(minDate, 20) : maxDate;
    const days = dayDiff(rangeStart, rangeEnd) + 1;

    return {
        start: rangeStart,
        end: rangeEnd,
        days,
        label: `${formatDateLabel(rangeStart)} - ${formatDateLabel(rangeEnd)}`,
    };
}

export default function ActionCenter({ user, action_center }: ActionCenterProps) {
    const [view, setView] = useState<ViewMode>("board");
    const [searchQuery, setSearchQuery] = useState("");
    const [priorityFilter, setPriorityFilter] = useState<string>("all");
    const [showExtraColumns, setShowExtraColumns] = useState(false);
    const [activities, setActivities] = useState(action_center.activities);
    const [draggedActivityId, setDraggedActivityId] = useState<string | null>(null);

    useEffect(() => {
        setActivities(action_center.activities);
    }, [action_center.activities]);

    const filteredActivities = useMemo(() => {
        const normalizedQuery = searchQuery.trim().toLowerCase();

        return activities.filter((activity) => {
            const matchesSearch =
                !normalizedQuery ||
                activity.title.toLowerCase().includes(normalizedQuery) ||
                activity.subtitle.toLowerCase().includes(normalizedQuery) ||
                activity.meta.toLowerCase().includes(normalizedQuery) ||
                activity.assignee.name.toLowerCase().includes(normalizedQuery);

            const matchesPriority = priorityFilter === "all" || activity.priority === priorityFilter;

            return matchesSearch && matchesPriority;
        });
    }, [activities, searchQuery, priorityFilter]);

    const groupedActivities = useMemo(
        () => ({
            backlog: filteredActivities.filter((a) => a.status === "backlog"),
            in_progress: filteredActivities.filter((a) => a.status === "in_progress"),
            review: filteredActivities.filter((a) => a.status === "review"),
            blocked: filteredActivities.filter((a) => a.status === "blocked"),
            done: filteredActivities.filter((a) => a.status === "done"),
        }),
        [filteredActivities],
    );

    const priorityBreakdown = useMemo(() => {
        return (["high", "medium", "low"] as const).map((priority) => {
            const total = filteredActivities.filter((activity) => activity.priority === priority).length;
            const done = filteredActivities.filter((activity) => activity.priority === priority && activity.status === "done").length;

            return {
                priority,
                total,
                done,
                percent: total ? Math.round((done / total) * 100) : 0,
            };
        });
    }, [filteredActivities]);

    const assignedToYou = filteredActivities.filter((activity) => activity.assignee.is_self);
    const assignedToTeam = filteredActivities.filter((activity) => !activity.assignee.is_self);

    const ganttRange = useMemo(() => buildGanttRange(filteredActivities), [filteredActivities]);
    const ganttDays = useMemo(() => Array.from({ length: ganttRange.days }, (_, index) => addDays(ganttRange.start, index)), [ganttRange]);

    const ganttActivities = useMemo(() => {
        return [...filteredActivities].sort((left, right) => {
            const startDiff = parseDate(left.start_date).getTime() - parseDate(right.start_date).getTime();
            if (startDiff !== 0) return startDiff;
            if (left.status !== right.status) return left.status === "done" ? 1 : -1;
            return priorityOrder[left.priority] - priorityOrder[right.priority];
        });
    }, [filteredActivities]);

    const stats = useMemo(() => {
        const counts = activities.reduce<Record<ActivityStatus, number>>(
            (acc, activity) => {
                acc[activity.status] += 1;
                return acc;
            },
            { backlog: 0, in_progress: 0, review: 0, blocked: 0, done: 0 },
        );

        const total = activities.length;
        const done = counts.done;
        const active = Math.max(total - done, 0);
        const focus = activities.filter((activity) => activity.status !== "done" && activity.priority === "high").length;
        const completionRate = total > 0 ? Math.round((done / total) * 100) : 0;

        return {
            active,
            done,
            focus,
            completionRate,
            statusBreakdown: counts,
        };
    }, [activities]);

    const handleDragStart = (activityId: string) => {
        setDraggedActivityId(activityId);
    };

    const handleDragEnd = () => {
        setDraggedActivityId(null);
    };

    const handleDrop = (status: ActivityStatus) => {
        if (!draggedActivityId) return;

        const activity = activities.find((a) => a.id === draggedActivityId);
        if (!activity) return;

        // Optimistic update
        setActivities((prev) => prev.map((a) => (a.id === draggedActivityId ? { ...a, status } : a)));

        // @ts-ignore
        router.patch(
            route("action-center.status.update", activity.source_id),
            {
                status: status,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Activity status updated");
                },
                onError: () => {
                    toast.error("Failed to update status");
                    // Revert
                    setActivities((prev) => prev.map((a) => (a.id === draggedActivityId ? { ...a, status: activity.status } : a)));
                },
            },
        );

        setDraggedActivityId(null);
    };

    return (
        <FacultyLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Action Center" />

            <main className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-foreground text-2xl font-bold tracking-tight">Action Center</h2>
                        <p className="text-muted-foreground text-sm">
                            Manage your class activities, assignments, and workflow.
                            <span className="bg-muted text-foreground ml-1 inline-block rounded-md px-2 py-0.5 text-xs font-medium">
                                {action_center.date}
                            </span>
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Tabs value={view} onValueChange={(value) => setView(value as ViewMode)} className="w-full sm:w-auto">
                            <TabsList className="grid w-full grid-cols-4 sm:w-auto">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <TabsTrigger value="board">
                                            <IconLayoutKanban className="size-4" />
                                            <span className="sr-only sm:not-sr-only sm:ml-2">Board</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Kanban Board</TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <TabsTrigger value="gantt">
                                            <IconTimeline className="size-4" />
                                            <span className="sr-only sm:not-sr-only sm:ml-2">Timeline</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Gantt Chart</TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <TabsTrigger value="assignments">
                                            <IconUsers className="size-4" />
                                            <span className="sr-only sm:not-sr-only sm:ml-2">Team</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Assignments</TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <TabsTrigger value="progress">
                                            <IconProgressCheck className="size-4" />
                                            <span className="sr-only sm:not-sr-only sm:ml-2">Stats</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Completion Stats</TooltipContent>
                                </Tooltip>
                            </TabsList>
                        </Tabs>
                    </div>
                </div>

                {/* Filters & Search */}
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex flex-1 items-center gap-3">
                        <div className="relative flex-1 sm:max-w-xs">
                            <IconSearch className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                            <Input
                                value={searchQuery}
                                onChange={(event) => setSearchQuery(event.target.value)}
                                placeholder="Search activities..."
                                className="bg-background pl-9"
                            />
                        </div>
                        <Select value={priorityFilter} onValueChange={setPriorityFilter}>
                            <SelectTrigger className="w-[140px]">
                                <div className="flex items-center gap-2">
                                    <IconFilter className="text-muted-foreground size-4" />
                                    <SelectValue placeholder="Priority" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Priorities</SelectItem>
                                <SelectItem value="high">High Priority</SelectItem>
                                <SelectItem value="medium">Medium Priority</SelectItem>
                                <SelectItem value="low">Low Priority</SelectItem>
                            </SelectContent>
                        </Select>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Toggle
                                    pressed={showExtraColumns}
                                    onPressedChange={setShowExtraColumns}
                                    variant="outline"
                                    aria-label="Toggle extra columns"
                                    className="h-10 w-10 p-0 sm:w-auto sm:px-3"
                                >
                                    {showExtraColumns ? <IconEyeOff className="size-4" /> : <IconEye className="size-4" />}
                                    <span className="ml-2 hidden sm:inline-block">
                                        {showExtraColumns ? "Hide Review & Blocked" : "Show All Columns"}
                                    </span>
                                </Toggle>
                            </TooltipTrigger>
                            <TooltipContent>
                                {showExtraColumns ? "Hide 'Needs Review' and 'Needs Help' columns" : "Show 'Needs Review' and 'Needs Help' columns"}
                            </TooltipContent>
                        </Tooltip>
                    </div>

                    <div className="text-muted-foreground flex items-center gap-4 text-sm">
                        <div className="hidden items-center gap-2 md:flex">
                            <span className="flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                Active: {stats.active}
                            </span>
                            <span className="flex items-center gap-1.5 rounded-full bg-rose-500/10 px-2.5 py-0.5 text-xs font-medium text-rose-600 dark:text-rose-400">
                                Focus: {stats.focus}
                            </span>
                        </div>
                    </div>
                </div>

                {filteredActivities.length === 0 ? (
                    <Empty className="bg-background border-border/60 flex h-[400px] items-center justify-center rounded-lg border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon" className="bg-muted/50 mb-4 rounded-full p-4">
                                <IconCircleCheck className="text-muted-foreground size-8" />
                            </EmptyMedia>
                            <EmptyTitle>No activities found</EmptyTitle>
                            <EmptyDescription>
                                {searchQuery || priorityFilter !== "all"
                                    ? "Try adjusting your search or filters to find what you're looking for."
                                    : "You are all caught up! Enjoy your free time or schedule new activities."}
                            </EmptyDescription>
                        </EmptyHeader>
                        {!searchQuery && priorityFilter === "all" && (
                            <EmptyContent>
                                <Button asChild variant="default">
                                    <Link href="/classes">Go to My Classes</Link>
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : null}

                {filteredActivities.length > 0 && view === "board" && (
                    <div
                        className="grid h-full items-start gap-6 overflow-x-auto pb-4"
                        style={{
                            gridTemplateColumns: `repeat(${showExtraColumns ? 5 : 3}, minmax(280px, 1fr))`,
                        }}
                    >
                        {[
                            {
                                key: "backlog",
                                status: "backlog",
                                label: "Planned",
                                items: groupedActivities.backlog,
                                color: "bg-slate-500",
                                visible: true,
                            },
                            {
                                key: "in_progress",
                                status: "in_progress",
                                label: "In Progress",
                                items: groupedActivities.in_progress,
                                color: "bg-indigo-500",
                                visible: true,
                            },
                            {
                                key: "review",
                                status: "review",
                                label: "Needs Review",
                                items: groupedActivities.review,
                                color: "bg-sky-500",
                                visible: showExtraColumns,
                            },
                            {
                                key: "blocked",
                                status: "blocked",
                                label: "Needs Help",
                                items: groupedActivities.blocked,
                                color: "bg-rose-500",
                                visible: showExtraColumns,
                            },
                            {
                                key: "done",
                                status: "done",
                                label: "Completed",
                                items: groupedActivities.done,
                                color: "bg-emerald-500",
                                visible: true,
                            },
                        ]
                            .filter((col) => col.visible)
                            .map((column) => (
                                <div
                                    key={column.key}
                                    className="bg-muted/40 border-border/50 flex h-full min-w-[280px] flex-col rounded-xl border"
                                    onDragOver={(event) => event.preventDefault()}
                                    onDrop={() => handleDrop(column.status as ActivityStatus)}
                                >
                                    <div className="flex items-center justify-between p-3 pb-0">
                                        <div className="flex items-center gap-2">
                                            <div className={cn("size-2 rounded-full", column.color)} />
                                            <h3 className="text-sm font-semibold">{column.label}</h3>
                                        </div>
                                        <Badge variant="secondary" className="bg-background font-mono text-xs">
                                            {column.items.length}
                                        </Badge>
                                    </div>

                                    <ScrollArea className="flex-1 p-3">
                                        <div className="flex flex-col gap-3">
                                            {column.items.map((activity) => (
                                                <Card
                                                    key={activity.id}
                                                    className="hover:border-primary/50 cursor-grab shadow-sm transition-colors active:cursor-grabbing"
                                                    draggable
                                                    onDragStart={() => handleDragStart(activity.id)}
                                                    onDragEnd={handleDragEnd}
                                                >
                                                    <CardContent className="space-y-3 p-3">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="space-y-1">
                                                                <h4 className="text-sm leading-tight font-medium">{activity.title}</h4>
                                                                <p className="text-muted-foreground line-clamp-1 text-xs">{activity.subtitle}</p>
                                                            </div>
                                                            <PriorityBadge priority={activity.priority} />
                                                        </div>

                                                        <div className="flex items-center justify-between gap-2 pt-1">
                                                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                                <Avatar className="size-5">
                                                                    <AvatarImage src={activity.assignee.avatar} />
                                                                    <AvatarFallback className="text-[9px]">
                                                                        {getInitials(activity.assignee.name)}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                                <span className="max-w-[80px] truncate">{activity.assignee.name.split(" ")[0]}</span>
                                                            </div>
                                                            <div className="text-muted-foreground flex items-center gap-1 text-xs" title="Due Date">
                                                                <IconCalendar className="size-3" />
                                                                <span>
                                                                    {new Date(activity.due_date).toLocaleDateString(undefined, {
                                                                        month: "short",
                                                                        day: "numeric",
                                                                    })}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        {activity.progress > 0 && activity.status !== "done" && (
                                                            <div className="space-y-1">
                                                                <div className="text-muted-foreground flex justify-between text-[10px]">
                                                                    <span>Progress</span>
                                                                    <span>{activity.progress}%</span>
                                                                </div>
                                                                <Progress value={activity.progress} className="h-1" />
                                                            </div>
                                                        )}

                                                        <Button asChild size="sm" variant="outline" className="h-7 w-full text-xs">
                                                            <Link href={activity.primary_action.href}>{activity.primary_action.label}</Link>
                                                        </Button>
                                                    </CardContent>
                                                </Card>
                                            ))}
                                            {column.items.length === 0 && (
                                                <div className="border-border/50 text-muted-foreground flex h-24 items-center justify-center rounded-lg border border-dashed text-xs">
                                                    No tasks
                                                </div>
                                            )}
                                        </div>
                                    </ScrollArea>
                                </div>
                            ))}
                    </div>
                )}

                {filteredActivities.length > 0 && view === "gantt" && (
                    <Card className="border-border/70 overflow-hidden">
                        <CardHeader className="bg-muted/20 border-border/50 border-b pb-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-base">Timeline</CardTitle>
                                    <CardDescription>
                                        {ganttRange.label} • {ganttRange.days} days overview
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <span className="size-2 rounded-full bg-emerald-500" /> Done
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <span className="size-2 rounded-full bg-indigo-500" /> In Progress
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <span className="size-2 rounded-full bg-slate-400" /> Planned
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <div className="min-w-[800px]">
                                {/* Header Row */}
                                <div
                                    className="border-border/40 bg-muted/10 text-muted-foreground sticky top-0 z-10 grid border-b py-2 text-xs font-medium"
                                    style={{ gridTemplateColumns: `280px repeat(${ganttDays.length}, minmax(32px, 1fr))` }}
                                >
                                    <div className="flex items-center px-4">Activity</div>
                                    {ganttDays.map((day) => (
                                        <div
                                            key={day.toISOString()}
                                            className={cn(
                                                "border-border/20 border-l py-1 text-center",
                                                day.toDateString() === new Date().toDateString() && "bg-primary/5 text-primary font-bold",
                                            )}
                                        >
                                            <div className="text-[10px] uppercase">{day.toLocaleDateString("en-US", { weekday: "narrow" })}</div>
                                            <div>{day.getDate()}</div>
                                        </div>
                                    ))}
                                </div>

                                {/* Rows */}
                                <div className="divide-border/30 divide-y">
                                    {ganttActivities.map((activity) => {
                                        const start = parseDate(activity.start_date);
                                        const end = parseDate(activity.due_date);
                                        const startIndex = Math.max(dayDiff(ganttRange.start, start), 0);
                                        const endIndex = Math.min(dayDiff(ganttRange.start, end), ganttRange.days - 1);
                                        const span = Math.max(endIndex - startIndex + 1, 1);
                                        const offset = startIndex + 1; // +1 because col 1 is title

                                        const statusColor =
                                            activity.status === "done"
                                                ? "bg-emerald-500"
                                                : activity.status === "in_progress"
                                                  ? "bg-indigo-500"
                                                  : "bg-slate-400";

                                        return (
                                            <div
                                                key={activity.id}
                                                className="hover:bg-muted/5 grid items-center py-3 transition-colors"
                                                style={{
                                                    gridTemplateColumns: `280px repeat(${ganttDays.length}, minmax(32px, 1fr))`,
                                                }}
                                            >
                                                <div className="min-w-0 px-4 pr-6">
                                                    <p className="text-foreground truncate text-sm font-medium">{activity.title}</p>
                                                    <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                        <Avatar className="size-4">
                                                            <AvatarFallback className="text-[8px]">
                                                                {getInitials(activity.assignee.name)}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="truncate">{activity.subtitle}</span>
                                                    </div>
                                                </div>

                                                <div className="relative h-full py-1" style={{ gridColumn: `${offset + 1} / span ${span}` }}>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div
                                                                    className={cn(
                                                                        "mx-1 flex h-6 cursor-help items-center rounded-full px-2 opacity-90 shadow-sm transition-opacity hover:opacity-100",
                                                                        statusColor,
                                                                    )}
                                                                >
                                                                    <span className="w-full truncate text-[10px] font-medium text-white">
                                                                        {activity.meta}
                                                                    </span>
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <div className="text-xs">
                                                                    <p className="font-semibold">{activity.title}</p>
                                                                    <p>
                                                                        {formatDateLabel(start)} - {formatDateLabel(end)}
                                                                    </p>
                                                                    <p className="capitalize">{activity.status.replace("_", " ")}</p>
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {filteredActivities.length > 0 && view === "assignments" && (
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <h3 className="text-lg font-semibold">Assigned to You</h3>
                                <Badge variant="secondary">{assignedToYou.length}</Badge>
                            </div>
                            {assignedToYou.length === 0 ? (
                                <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                                    No tasks assigned to you specifically.
                                </div>
                            ) : (
                                <div className="grid gap-3">
                                    {assignedToYou.map((activity) => (
                                        <Card
                                            key={activity.id}
                                            className="border-l-primary overflow-hidden border-l-4 transition-shadow hover:shadow-md"
                                        >
                                            <CardContent className="flex items-start gap-4 p-4">
                                                <div className="flex-1 space-y-1">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="text-sm font-medium">{activity.title}</h4>
                                                        <StatusBadge status={activity.status} />
                                                    </div>
                                                    <p className="text-muted-foreground text-xs">{activity.subtitle}</p>
                                                    <div className="text-muted-foreground flex items-center gap-4 pt-2 text-xs">
                                                        <div className="flex items-center gap-1">
                                                            <IconClock className="size-3.5" />
                                                            <span>{activity.meta}</span>
                                                        </div>
                                                        <PriorityBadge priority={activity.priority} />
                                                    </div>
                                                </div>
                                                <Button asChild size="icon" variant="ghost" className="h-8 w-8 shrink-0">
                                                    <Link href={activity.primary_action.href}>
                                                        <IconLayoutKanban className="size-4" />
                                                    </Link>
                                                </Button>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <h3 className="text-lg font-semibold">Team Assignments</h3>
                                <Badge variant="secondary">{assignedToTeam.length}</Badge>
                            </div>
                            {assignedToTeam.length === 0 ? (
                                <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                                    No team assignments found.
                                </div>
                            ) : (
                                <div className="grid gap-3">
                                    {assignedToTeam.map((activity) => (
                                        <Card key={activity.id} className="transition-shadow hover:shadow-md">
                                            <CardContent className="flex items-start gap-4 p-4">
                                                <Avatar className="mt-1 size-8">
                                                    <AvatarFallback>{getInitials(activity.assignee.name)}</AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 space-y-1">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="text-sm font-medium">{activity.title}</h4>
                                                        <StatusBadge status={activity.status} />
                                                    </div>
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <span className="text-foreground font-medium">{activity.assignee.name}</span>
                                                        <span>•</span>
                                                        <span>{activity.subtitle}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between pt-2">
                                                        <PriorityBadge priority={activity.priority} />
                                                        <Button asChild size="sm" variant="link" className="h-auto p-0 text-xs">
                                                            <Link href={activity.primary_action.href}>View Details</Link>
                                                        </Button>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {filteredActivities.length > 0 && view === "progress" && (
                    <div className="grid gap-6 md:grid-cols-3">
                        <Card className="border-border/70 md:col-span-2">
                            <CardHeader>
                                <CardTitle>Completion Overview</CardTitle>
                                <CardDescription>Overall progress metrics across all activities</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-8">
                                <div className="space-y-2">
                                    <div className="flex items-end justify-between">
                                        <span className="text-muted-foreground text-sm font-medium">Total Completion</span>
                                        <span className="text-foreground text-2xl font-bold">{stats.completionRate}%</span>
                                    </div>
                                    <Progress value={stats.completionRate} className="h-3" />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    {priorityBreakdown.map((item) => (
                                        <div key={item.priority} className="bg-card space-y-3 rounded-lg border p-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-medium capitalize">{item.priority} Priority</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {item.done}/{item.total}
                                                </span>
                                            </div>
                                            <Progress
                                                value={item.percent}
                                                className={cn(
                                                    "h-2",
                                                    item.priority === "high"
                                                        ? "bg-rose-100 dark:bg-rose-950 [&>[data-slot=progress-indicator]]:bg-rose-500"
                                                        : item.priority === "medium"
                                                          ? "bg-amber-100 dark:bg-amber-950 [&>[data-slot=progress-indicator]]:bg-amber-500"
                                                          : "bg-slate-100 dark:bg-slate-800 [&>[data-slot=progress-indicator]]:bg-slate-500",
                                                )}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/70 h-fit">
                            <CardHeader>
                                <CardTitle>Status Breakdown</CardTitle>
                                <CardDescription>Distribution by stage</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {Object.entries(stats.statusBreakdown).map(([status, count]) => (
                                    <div key={status} className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div
                                                className={cn(
                                                    "size-2.5 rounded-full",
                                                    status === "done"
                                                        ? "bg-emerald-500"
                                                        : status === "in_progress"
                                                          ? "bg-indigo-500"
                                                          : status === "review"
                                                            ? "bg-sky-500"
                                                            : status === "blocked"
                                                              ? "bg-rose-500"
                                                              : "bg-slate-400",
                                                )}
                                            />
                                            <span className="text-sm capitalize">{statusLabels[status as ActivityStatus]}</span>
                                        </div>
                                        <span className="font-mono text-sm font-medium">{count}</span>
                                    </div>
                                ))}
                                <Separator className="my-2" />
                                <div className="flex items-center justify-between pt-2 font-medium">
                                    <span>Total Activities</span>
                                    <span>{stats.total}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </main>
        </FacultyLayout>
    );
}
