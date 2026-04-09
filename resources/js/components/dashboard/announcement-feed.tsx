import { Button } from "@/components/ui/button";
import { CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { IconAlertTriangle, IconBell, IconBulb, IconCalendar, IconChevronRight, IconInfoCircle } from "@tabler/icons-react";
import { motion } from "framer-motion";

interface Announcement {
    id?: number | string;
    title: string;
    content: string;
    date: string;
    type: "info" | "warning" | "important" | "update";
}

interface AnnouncementFeedProps {
    announcements: Announcement[];
}

const typeConfig = {
    info: {
        icon: IconInfoCircle,
        gradient: "from-sky-500/20 to-blue-600/10",
        border: "border-sky-500/30",
        iconBg: "bg-sky-500",
        iconColor: "text-sky-600",
        badge: "bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300",
    },
    warning: {
        icon: IconAlertTriangle,
        gradient: "from-amber-500/20 to-orange-600/10",
        border: "border-amber-500/30",
        iconBg: "bg-amber-500",
        iconColor: "text-amber-600",
        badge: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
    },
    important: {
        icon: IconAlertTriangle,
        gradient: "from-rose-500/20 to-red-600/10",
        border: "border-rose-500/30",
        iconBg: "bg-rose-500",
        iconColor: "text-rose-600",
        badge: "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300",
    },
    update: {
        icon: IconBulb,
        gradient: "from-emerald-500/20 to-teal-600/10",
        border: "border-emerald-500/30",
        iconBg: "bg-emerald-500",
        iconColor: "text-emerald-600",
        badge: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300",
    },
};

export function AnnouncementFeed({ announcements }: AnnouncementFeedProps) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="border-border/60 bg-card/80 rounded-2xl border backdrop-blur-sm"
        >
            <div className="border-border/60 from-muted/30 flex items-center justify-between border-b bg-gradient-to-r to-transparent p-4">
                <div className="flex items-center gap-2.5">
                    <motion.div
                        animate={{ rotate: [0, 15, -15, 0] }}
                        transition={{ duration: 2, repeat: Infinity, repeatDelay: 3 }}
                        className="from-primary/20 to-primary/5 flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br"
                    >
                        <IconBell className="text-primary h-4 w-4" />
                    </motion.div>
                    <div>
                        <h2 className="text-foreground text-base font-semibold">Announcements</h2>
                        <p className="text-muted-foreground text-xs">Stay updated with the latest news</p>
                    </div>
                </div>
                <Button variant="ghost" size="sm" className="h-8 gap-1 text-xs" asChild>
                    <Link href="/announcements">
                        View All
                        <IconChevronRight className="h-3.5 w-3.5" />
                    </Link>
                </Button>
            </div>

            <CardContent className="grid gap-2 p-3">
                {announcements.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-8 text-center">
                        <div className="bg-muted/50 flex h-12 w-12 items-center justify-center rounded-xl">
                            <IconBell className="text-muted-foreground h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-foreground text-sm font-medium">No announcements</p>
                            <p className="text-muted-foreground text-xs">Check back later for updates</p>
                        </div>
                    </div>
                ) : (
                    announcements.slice(0, 4).map((announcement, index) => {
                        const config = typeConfig[announcement.type] ?? typeConfig.info;
                        const Icon = config.icon;

                        return (
                            <motion.div
                                key={announcement.id ?? index}
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: index * 0.1 }}
                                whileHover={{ x: 4 }}
                                className={cn(
                                    "group relative overflow-hidden rounded-xl border p-3 transition-all duration-300",
                                    `bg-gradient-to-r ${config.gradient}`,
                                    config.border,
                                    "hover:shadow-md",
                                )}
                            >
                                <div className="flex items-start gap-3">
                                    <div className={cn("flex h-8 w-8 shrink-0 items-center justify-center rounded-lg shadow-sm", config.iconBg)}>
                                        <Icon className="h-4 w-4 text-white" />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="text-foreground group-hover:text-foreground/90 line-clamp-1 text-sm font-medium">
                                                {announcement.title}
                                            </p>
                                            <span
                                                className={cn("shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium capitalize", config.badge)}
                                            >
                                                {announcement.type}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">{announcement.content}</p>
                                        <div className="text-muted-foreground mt-2 flex items-center gap-1.5 text-[10px]">
                                            <IconCalendar className="h-3 w-3" />
                                            {announcement.date}
                                        </div>
                                    </div>
                                </div>

                                <motion.div initial={{ opacity: 0 }} whileHover={{ opacity: 1 }} className="absolute right-2 bottom-2">
                                    <IconChevronRight className="text-muted-foreground h-4 w-4" />
                                </motion.div>
                            </motion.div>
                        );
                    })
                )}
            </CardContent>
        </motion.div>
    );
}
