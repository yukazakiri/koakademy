import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { ClassSettings, ScheduleEntry, TeacherEntry } from "@/types/class-detail-types";
import {
    IconCalendar,
    IconChartBar,
    IconChevronRight,
    IconClock,
    IconEye,
    IconHistory,
    IconSchool,
    IconSettings,
    IconSpeakerphone,
    IconUserCheck,
    IconUsers,
} from "@tabler/icons-react";
import { format } from "date-fns";

interface EnhancedClassHeaderProps {
    classData: {
        id: number;
        subject_code: string;
        course_title?: string;
        subject_title?: string;
        section: string;
        classification: string;
        settings: ClassSettings;
    };
    teacher: TeacherEntry;
    schedule: ScheduleEntry[];
    enrollmentStats: {
        current_count: number;
        max_slots: number;
        waitlist_count?: number;
    };
    onSettingsClick?: () => void;
    onActivityLogClick?: () => void;
    isStudent?: boolean;
}

export function EnhancedClassHeader({
    classData,
    teacher,
    schedule,
    enrollmentStats,
    onSettingsClick,
    onActivityLogClick,
    isStudent,
}: EnhancedClassHeaderProps) {
    const settings = classData.settings;
    // Use a default gradient if no color provided, but try to use the accent color for the gradient base if available
    const accentColorBase = settings.accent_color?.replace("bg-", "") || "primary";

    // Format schedule for display
    const formatSchedule = () => {
        if (!schedule || schedule.length === 0) return [];

        // Group by day
        const scheduleByDay = schedule.reduce(
            (acc, item) => {
                const day = item.day;
                if (!acc[day]) acc[day] = [];
                acc[day].push(item);
                return acc;
            },
            {} as Record<string, ScheduleEntry[]>,
        );

        return Object.entries(scheduleByDay).map(([day, entries]) => ({
            day,
            times: entries.map((entry) => `${entry.start} - ${entry.end}`),
            rooms: [...new Set(entries.map((entry) => entry.room).filter(Boolean))],
        }));
    };

    const formattedSchedule = formatSchedule();
    const nextSchedule = formattedSchedule[0]; // Simplified "Next Class" logic could go here

    // Classification styling
    const isSHS = classData.classification === "shs";
    const classificationLabel = isSHS ? "Senior High" : "College";
    const classificationColor = isSHS ? "bg-amber-500/90 text-amber-50" : "bg-emerald-500/90 text-emerald-50";

    // Occupation percentage
    const occupancyPercent = enrollmentStats.max_slots > 0 ? (enrollmentStats.current_count / enrollmentStats.max_slots) * 100 : 0;

    // Active Features
    const features = [
        { key: "enable_announcements", icon: IconSpeakerphone, label: "Announcements" },
        { key: "enable_grade_visibility", icon: IconEye, label: "Grades Visible" },
        { key: "enable_attendance_tracking", icon: IconUserCheck, label: "Attendance" },
        { key: "enable_performance_analytics", icon: IconChartBar, label: "Analytics" },
    ].filter((f) => settings[f.key as keyof typeof settings]);

    // Theme configurations using semantic colors from app.css
    const THEME_STYLES: Record<string, { gradient: string; iconBg: string; iconText: string; hoverShadow: string }> = {
        violet: {
            gradient: "bg-gradient-to-br from-violet-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-violet-500/20",
            iconText: "text-violet-500",
            hoverShadow: "hover:shadow-violet-500/10",
        },
        indigo: {
            gradient: "bg-gradient-to-br from-indigo-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-indigo-500/20",
            iconText: "text-indigo-500",
            hoverShadow: "hover:shadow-indigo-500/10",
        },
        blue: {
            gradient: "bg-gradient-to-br from-blue-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-blue-500/20",
            iconText: "text-blue-500",
            hoverShadow: "hover:shadow-blue-500/10",
        },
        cyan: {
            gradient: "bg-gradient-to-br from-cyan-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-cyan-500/20",
            iconText: "text-cyan-500",
            hoverShadow: "hover:shadow-cyan-500/10",
        },
        emerald: {
            gradient: "bg-gradient-to-br from-emerald-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-emerald-500/20",
            iconText: "text-emerald-500",
            hoverShadow: "hover:shadow-emerald-500/10",
        },
        lime: {
            gradient: "bg-gradient-to-br from-lime-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-lime-500/20",
            iconText: "text-lime-500",
            hoverShadow: "hover:shadow-lime-500/10",
        },
        amber: {
            gradient: "bg-gradient-to-br from-amber-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-amber-500/20",
            iconText: "text-amber-500",
            hoverShadow: "hover:shadow-amber-500/10",
        },
        orange: {
            gradient: "bg-gradient-to-br from-orange-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-orange-500/20",
            iconText: "text-orange-500",
            hoverShadow: "hover:shadow-orange-500/10",
        },
        rose: {
            gradient: "bg-gradient-to-br from-rose-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-rose-500/20",
            iconText: "text-rose-500",
            hoverShadow: "hover:shadow-rose-500/10",
        },
        fuchsia: {
            gradient: "bg-gradient-to-br from-fuchsia-950/80 via-background-950/80 to-background-900/80",
            iconBg: "bg-fuchsia-500/20",
            iconText: "text-fuchsia-500",
            hoverShadow: "hover:shadow-fuchsia-500/10",
        },
        // Fallback adapts to light/dark mode
        background: {
            gradient: "bg-gradient-to-br from-background-50/60 to-background-100/60 dark:from-background-500 dark:to-background-900",
            iconBg: "bg-background-500/20 dark:bg-background-500/30",
            iconText: "text-background-600 dark:text-background-400",
            hoverShadow: "hover:shadow-background-500/10",
        },
    };

    // Determine the theme styles based on primary (background) and accent colors
    const getThemeStyles = () => {
        let gradientColor = "primary";
        let accentColor = "blue";

        // Primary color controls the background gradient
        if (settings.background_color) {
            const match = settings.background_color.match(/bg-([a-z]+)-(\d+)/);
            if (match && THEME_STYLES[match[1]]) {
                gradientColor = match[1];
            }
        }

        // Accent color controls icons and interactive elements
        if (settings.accent_color) {
            const match = settings.accent_color.match(/bg-([a-z]+)-(\d+)/);
            if (match && THEME_STYLES[match[1]]) {
                accentColor = match[1];
            }
        }

        const gradientStyles = THEME_STYLES[gradientColor] || THEME_STYLES.background;
        const accentStyles = THEME_STYLES[accentColor] || THEME_STYLES.blue;

        return {
            gradient: gradientStyles.gradient,
            hoverShadow: gradientStyles.hoverShadow,
            iconBg: accentStyles.iconBg,
            iconText: accentStyles.iconText,
        };
    };

    const theme = getThemeStyles();

    return (
        <div className={cn("group relative w-full overflow-hidden rounded-3xl shadow-2xl transition-all", theme.hoverShadow)}>
            {/* Dynamic Background */}
            <div className="absolute inset-0 z-0">
                {settings.banner_image ? (
                    <div
                        className="h-full w-full bg-cover bg-center transition-transform duration-700 group-hover:scale-105"
                        style={{ backgroundImage: `url(${settings.banner_image})` }}
                    />
                ) : (
                    <div className={cn("h-full w-full", theme.gradient)} />
                )}
                {/* Gradient Overlay for Text Readability - adapts to theme */}
                <div className="from-primary/70 via-background/30 dark:from-muted/80 dark:via-background/50 absolute inset-0 bg-gradient-to-t to-transparent" />
                <div className="from-primary/10 dark:from-primary/20 absolute inset-0 bg-gradient-to-r to-transparent" />
            </div>

            {/* Content Container */}
            <div className="relative z-10 flex flex-col justify-between p-6 md:h-[320px] md:p-10">
                {/* Top Bar: Settings & Indicators */}
                <div className="flex items-start justify-between">
                    <Badge className={cn("border-0 px-3 py-1 font-medium shadow-sm backdrop-blur-md", classificationColor)}>
                        <IconSchool className="mr-1.5 size-3.5" />
                        {classificationLabel}
                    </Badge>

                    <div className="flex items-center gap-2">
                        {/* Feature Indicators */}
                        <TooltipProvider>
                            <div className="mr-2 hidden items-center gap-1 md:flex">
                                {features.map((f, i) => (
                                    <Tooltip key={i} delayDuration={300}>
                                        <TooltipTrigger asChild>
                                            <div className="text-foreground/70 hover:text-foreground dark:text-foreground/50 flex h-8 w-8 cursor-help items-center justify-center rounded-full bg-white/5 ring-1 ring-white/10 transition-colors hover:bg-white/10 dark:bg-black/20 dark:hover:bg-white/10">
                                                <f.icon className="size-4" />
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent side="bottom" className="bg-popover text-popover-foreground border-border text-xs">
                                            {f.label}
                                        </TooltipContent>
                                    </Tooltip>
                                ))}
                            </div>

                            {/* Activity Log Button */}
                            {onActivityLogClick && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            onClick={onActivityLogClick}
                                            variant="ghost"
                                            size="icon"
                                            className="text-foreground hover:text-foreground dark:text-foreground h-10 w-10 rounded-full bg-white/10 ring-1 ring-white/10 backdrop-blur-md transition-colors hover:bg-white/20 dark:bg-black/20 dark:hover:bg-white/10"
                                        >
                                            <IconHistory className="size-5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="bottom" className="bg-popover text-popover-foreground border-border text-xs">
                                        Activity Log
                                    </TooltipContent>
                                </Tooltip>
                            )}

                            {onSettingsClick && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            onClick={onSettingsClick}
                                            variant="ghost"
                                            size="icon"
                                            className="text-foreground hover:text-foreground dark:text-foreground h-10 w-10 rounded-full bg-white/10 ring-1 ring-white/10 backdrop-blur-md transition-colors hover:bg-white/20 dark:bg-black/20 dark:hover:bg-white/10"
                                        >
                                            <IconSettings className="size-5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="bottom" className="bg-popover text-popover-foreground border-border text-xs">
                                        Class Settings
                                    </TooltipContent>
                                </Tooltip>
                            )}
                        </TooltipProvider>
                    </div>
                </div>

                {/* Bottom Section: Main Info */}
                <div className="mt-8 grid gap-6 md:mt-0 md:grid-cols-[1fr_auto] md:items-end">
                    {/* Left: Title & Meta */}
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <div className="text-background-foreground flex items-center gap-3">
                                <span className="text-foreground bg-accent border-background-foreground inline-block rounded border px-2 py-0.5 font-mono text-sm tracking-wider uppercase opacity-80 backdrop-blur-sm dark:border-white/10 dark:bg-black/20">
                                    {classData.subject_code}
                                </span>
                                <span className="text-foreground flex items-center gap-1 text-sm font-medium tracking-wide opacity-80">
                                    <span className="h-1 w-1 rounded-full text-black"></span>
                                    Section {classData.section}
                                </span>
                            </div>
                            <h1 className="text-foreground text-2xl leading-tight font-bold text-shadow-sm md:text-3xl lg:text-4xl">
                                {classData.subject_title || classData.course_title}
                            </h1>
                        </div>

                        {/* Teacher Pill */}
                        <div className="flex items-center gap-3 pt-2">
                            <div className="bg-background-foreground/5 via-background-foreground/10 hover:bg-background/10 dark:bg-background/20 ring-background-foreground dark:hover:bg-background/10 flex items-center gap-3 rounded-full py-1.5 pr-4 pl-1.5 ring-1 backdrop-blur-md transition-colors">
                                <Avatar className="h-8 w-8 border border-black/10">
                                    <AvatarImage src={teacher.photo_url || undefined} alt={teacher.name} />
                                    <AvatarFallback className="bg-background text-background-foreground text-xs">
                                        {teacher.name.charAt(0)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex flex-col">
                                    <span className="text-foreground text-sm leading-none font-medium">{teacher.name}</span>
                                    <span className="text-foreground mt-1 text-[10px] leading-none">Instructor</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right: Quick Stats & Actions */}
                    <div className="flex min-w-[240px] flex-col gap-3">
                        {/* Schedule Card (Popover) */}
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="text-foreground h-auto w-full justify-start gap-3 border-white/10 bg-white/5 px-4 py-3 text-left backdrop-blur-md hover:bg-white/10 dark:border-white/10 dark:bg-black/20 dark:hover:bg-white/10"
                                >
                                    <div className={cn("rounded-full p-2", theme.iconBg, theme.iconText)}>
                                        <IconClock className="size-4" />
                                    </div>
                                    <div className="flex-1 overflow-hidden">
                                        <p className="text-background-foreground text-xs font-medium tracking-wider uppercase">Class Schedule</p>
                                        {nextSchedule ? (
                                            <p className="text-foreground truncate text-sm font-semibold">
                                                {nextSchedule.day} • {nextSchedule.times[0]}
                                            </p>
                                        ) : (
                                            <p className="text-background-foreground text-sm">No schedule set</p>
                                        )}
                                    </div>
                                    <IconChevronRight className="text-background-foreground size-4" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="border-border bg-popover text-popover-foreground w-80 p-0 shadow-xl" align="end">
                                <div className="border-border bg-background/50 border-b p-4">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-foreground flex items-center gap-2 font-medium">
                                            <IconCalendar className="text-primary size-4" /> Schedule
                                        </h4>
                                        {settings.start_date && (
                                            <span className="text-background-foreground text-[10px] font-medium tracking-wider uppercase">
                                                Starts {format(new Date(settings.start_date), "MMM d")}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1 p-2">
                                    {formattedSchedule.length > 0 ? (
                                        formattedSchedule.map((s, i) => (
                                            <div key={i} className="hover:bg-background/50 flex items-start gap-3 rounded-md p-3">
                                                <div className="bg-background text-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-xs font-bold">
                                                    {s.day.substring(0, 3)}
                                                </div>
                                                <div className="grid gap-1">
                                                    {s.times.map((t, idx) => (
                                                        <div key={idx} className="text-foreground text-sm font-medium">
                                                            {t}
                                                        </div>
                                                    ))}
                                                    <div className="text-background-foreground flex items-center gap-1 text-xs">
                                                        {s.rooms.length > 0 ? s.rooms.join(", ") : "No Room Assigned"}
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="text-background-foreground p-8 text-center text-sm">No schedule configured</div>
                                    )}
                                </div>
                            </PopoverContent>
                        </Popover>

                        {/* Enrollment Stats Card */}
                        <div className="group/stats border-background-foreground/10 bg-background-foreground/5 hover:bg-background/10 dark:hover:bg-accent/10 relative overflow-hidden rounded-xl border p-4 backdrop-blur-md transition-colors dark:border-black/10 dark:bg-black">
                            <div className="mb-2 flex items-center justify-between">
                                <div className="text-background-foreground flex items-center gap-2">
                                    <IconUsers className="size-4" />
                                    <span className="text-xs font-medium tracking-wider uppercase">Enrollment</span>
                                </div>
                                <span className="text-foreground text-sm font-bold">
                                    {enrollmentStats.current_count}{" "}
                                    <span className="text-background-foreground text-xs font-normal">
                                        / {enrollmentStats.max_slots > 0 ? enrollmentStats.max_slots : "∞"}
                                    </span>
                                </span>
                            </div>
                            {enrollmentStats.max_slots > 0 && (
                                <div className="bg-background relative h-1.5 w-full overflow-hidden rounded-full">
                                    <div
                                        className={cn(
                                            "absolute inset-y-0 left-0 transition-all duration-500",
                                            occupancyPercent >= 100 ? "bg-destructive" : occupancyPercent >= 80 ? "bg-amber-500" : "bg-emerald-500",
                                        )}
                                        style={{ width: `${Math.min(occupancyPercent, 100)}%` }}
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
