import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { IconArrowRight, IconClock, IconCoffee, IconMapPin, IconNotebook, IconPlayerPlay } from "@tabler/icons-react";
import { motion } from "framer-motion";

interface TodayScheduleEntry {
    id: number | string;
    start_time: string;
    end_time: string;
    subject_code: string;
    subject_title: string;
    section: string;
    room: string;
    course_codes?: string;
    classification?: string;
    class_id?: number | string;
}

interface TodayScheduleCardProps {
    schedule: {
        day: string;
        entries: TodayScheduleEntry[];
    };
}

function getCurrentPeriod() {
    const now = new Date();
    const hours = now.getHours();
    const minutes = now.getMinutes();
    return hours * 60 + minutes;
}

function parseTimeToMinutes(time: string): number {
    const parts = time.split(" ")[0].split(":");
    const hours = parseInt(parts[0], 10);
    const minutes = parseInt(parts[1] || "0", 10);
    const isPM = time.toLowerCase().includes("pm");
    const isAM = time.toLowerCase().includes("am");

    let totalMinutes = hours * 60 + minutes;
    if (isPM && hours !== 12) totalMinutes += 12 * 60;
    if (isAM && hours === 12) totalMinutes = minutes;

    return totalMinutes;
}

function getClassStatus(entry: TodayScheduleEntry, currentTime: number): "completed" | "active" | "upcoming" {
    const start = parseTimeToMinutes(entry.start_time);
    const end = parseTimeToMinutes(entry.end_time);

    if (currentTime > end) return "completed";
    if (currentTime >= start && currentTime <= end) return "active";
    return "upcoming";
}

const colorPalettes = [
    { bg: "from-violet-500/10 to-purple-600/5", border: "border-violet-500/30", accent: "bg-violet-500", dot: "bg-violet-400" },
    { bg: "from-blue-500/10 to-cyan-600/5", border: "border-blue-500/30", accent: "bg-blue-500", dot: "bg-blue-400" },
    { bg: "from-emerald-500/10 to-teal-600/5", border: "border-emerald-500/30", accent: "bg-emerald-500", dot: "bg-emerald-400" },
    { bg: "from-amber-500/10 to-orange-600/5", border: "border-amber-500/30", accent: "bg-amber-500", dot: "bg-amber-400" },
    { bg: "from-rose-500/10 to-pink-600/5", border: "border-rose-500/30", accent: "bg-rose-500", dot: "bg-rose-400" },
];

export function TodayScheduleCard({ schedule }: TodayScheduleCardProps) {
    const entries = schedule?.entries ?? [];
    const dayLabel = schedule?.day ?? "Today";
    const currentTime = getCurrentPeriod();

    const sortedEntries = [...entries].sort((a, b) => parseTimeToMinutes(a.start_time) - parseTimeToMinutes(b.start_time));

    const activeIndex = sortedEntries.findIndex((e) => getClassStatus(e, currentTime) === "active");
    const upcomingIndex = sortedEntries.findIndex((e) => getClassStatus(e, currentTime) === "upcoming");
    const focusIndex = activeIndex >= 0 ? activeIndex : upcomingIndex;

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="border-border/60 bg-card/80 overflow-hidden rounded-2xl border backdrop-blur-sm"
        >
            <div className="from-primary/5 bg-gradient-to-r via-transparent to-transparent p-5">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="from-primary/20 to-primary/5 flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br">
                            <IconNotebook className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-foreground text-lg font-semibold">Today&apos;s Classes</h2>
                            <p className="text-muted-foreground text-sm">
                                {entries.length === 0
                                    ? "No classes scheduled"
                                    : `${entries.length} class${entries.length !== 1 ? "es" : ""} on ${dayLabel}`}
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline" className="bg-background/50 rounded-full px-3 py-1 text-xs font-medium">
                        {dayLabel}
                    </Badge>
                </div>
            </div>

            {entries.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-4 py-12">
                    <motion.div
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        transition={{ type: "spring", stiffness: 200, damping: 15 }}
                        className="from-muted to-muted/50 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br"
                    >
                        <IconCoffee className="text-muted-foreground h-8 w-8" />
                    </motion.div>
                    <div className="text-center">
                        <p className="text-foreground font-medium">Free Day!</p>
                        <p className="text-muted-foreground text-sm">No classes scheduled for today</p>
                    </div>
                </div>
            ) : (
                <ScrollArea className="max-h-[400px]">
                    <div className="relative p-4">
                        <div className="from-primary/30 via-border absolute top-6 bottom-6 left-8 w-0.5 bg-gradient-to-b to-transparent" />

                        <div className="space-y-3">
                            {sortedEntries.map((entry, index) => {
                                const status = getClassStatus(entry, currentTime);
                                const palette = colorPalettes[index % colorPalettes.length];
                                const isFocus = index === focusIndex;
                                const isCompleted = status === "completed";
                                const isActive = status === "active";

                                return (
                                    <motion.div
                                        key={entry.id}
                                        initial={{ opacity: 0, x: -20 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        transition={{ delay: index * 0.1 }}
                                    >
                                        <Link
                                            href={entry.class_id ? `/faculty/classes/${entry.class_id}` : "#"}
                                            className={cn(
                                                "group relative flex items-start gap-4 rounded-xl p-3 transition-all duration-300",
                                                "hover:bg-muted/30",
                                                isCompleted && "opacity-60",
                                                isFocus && "bg-primary/5 ring-primary/20 ring-1",
                                            )}
                                        >
                                            <div className="relative flex flex-col items-center pt-1">
                                                <motion.div
                                                    whileHover={{ scale: 1.2 }}
                                                    className={cn(
                                                        "ring-background h-4 w-4 rounded-full shadow-sm ring-2",
                                                        palette.dot,
                                                        isCompleted && "bg-muted-foreground/30 ring-muted",
                                                        isActive && "h-5 w-5 animate-pulse",
                                                    )}
                                                />
                                                {isActive && (
                                                    <motion.div
                                                        className="bg-primary/30 absolute inset-0 h-5 w-5 rounded-full"
                                                        animate={{ scale: [1, 1.5, 1] }}
                                                        transition={{ duration: 2, repeat: Infinity }}
                                                    />
                                                )}
                                            </div>

                                            <div
                                                className={cn(
                                                    "flex-1 rounded-xl border p-3 transition-all duration-300",
                                                    `bg-gradient-to-r ${palette.bg}`,
                                                    palette.border,
                                                    isActive && "shadow-lg",
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <div
                                                            className={cn(
                                                                "flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold text-white shadow-sm",
                                                                palette.accent,
                                                            )}
                                                        >
                                                            {entry.start_time.split(" ")[0].slice(0, 5)}
                                                        </div>
                                                        <div>
                                                            <p
                                                                className={cn(
                                                                    "text-sm leading-tight font-medium",
                                                                    isCompleted ? "text-muted-foreground line-through" : "text-foreground",
                                                                )}
                                                            >
                                                                {entry.subject_title}
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">
                                                                {entry.subject_code} • {entry.section}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    {isActive && (
                                                        <Badge className="bg-primary text-primary-foreground animate-pulse text-[10px]">NOW</Badge>
                                                    )}
                                                    {status === "upcoming" && index === upcomingIndex && (
                                                        <Badge variant="outline" className="text-[10px]">
                                                            NEXT
                                                        </Badge>
                                                    )}
                                                </div>

                                                <div className="text-muted-foreground mt-2 flex items-center gap-3 text-xs">
                                                    <span className="flex items-center gap-1">
                                                        <IconClock className="h-3 w-3" />
                                                        {entry.start_time} - {entry.end_time}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <IconMapPin className="h-3 w-3" />
                                                        {entry.room}
                                                    </span>
                                                </div>

                                                {isActive && (
                                                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="mt-3">
                                                        <Button size="sm" className="bg-primary w-full gap-1.5">
                                                            <IconPlayerPlay className="h-3.5 w-3.5" />
                                                            Start Class
                                                            <IconArrowRight className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </motion.div>
                                                )}
                                            </div>
                                        </Link>
                                    </motion.div>
                                );
                            })}
                        </div>
                    </div>
                </ScrollArea>
            )}
        </motion.div>
    );
}
