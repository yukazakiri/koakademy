import { CardContent } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { IconActivity, IconBook, IconCircleCheck, IconClipboardText, IconEdit, IconSchool, IconTrash, IconUserPlus } from "@tabler/icons-react";
import { motion } from "framer-motion";

interface Activity {
    action: string;
    target: string;
    time: string;
}

interface ActivityTimelineProps {
    activities: Activity[];
}

function getActivityIcon(action: string) {
    const actionLower = action.toLowerCase();
    if (actionLower.includes("created") || actionLower.includes("new")) return IconUserPlus;
    if (actionLower.includes("updated") || actionLower.includes("grade")) return IconSchool;
    if (actionLower.includes("deleted") || actionLower.includes("removed")) return IconTrash;
    if (actionLower.includes("class")) return IconBook;
    if (actionLower.includes("enroll")) return IconUserPlus;
    if (actionLower.includes("attendance")) return IconClipboardText;
    return IconEdit;
}

function getActivityColor(action: string) {
    const actionLower = action.toLowerCase();
    if (actionLower.includes("created") || actionLower.includes("new") || actionLower.includes("enroll")) {
        return { dot: "bg-emerald-500", ring: "ring-emerald-500/30", bg: "bg-emerald-500/10" };
    }
    if (actionLower.includes("updated") || actionLower.includes("grade")) {
        return { dot: "bg-blue-500", ring: "ring-blue-500/30", bg: "bg-blue-500/10" };
    }
    if (actionLower.includes("deleted") || actionLower.includes("removed")) {
        return { dot: "bg-rose-500", ring: "ring-rose-500/30", bg: "bg-rose-500/10" };
    }
    return { dot: "bg-amber-500", ring: "ring-amber-500/30", bg: "bg-amber-500/10" };
}

export function ActivityTimeline({ activities }: ActivityTimelineProps) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="border-border/60 bg-card/80 rounded-2xl border backdrop-blur-sm"
        >
            <div className="border-border/60 from-muted/30 flex items-center gap-2.5 border-b bg-gradient-to-r to-transparent p-4">
                <motion.div
                    animate={{ scale: [1, 1.1, 1] }}
                    transition={{ duration: 2, repeat: Infinity, repeatDelay: 4 }}
                    className="from-primary/20 to-primary/5 flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br"
                >
                    <IconActivity className="text-primary h-4 w-4" />
                </motion.div>
                <div>
                    <h2 className="text-foreground text-base font-semibold">Recent Activity</h2>
                    <p className="text-muted-foreground text-xs">Your latest actions</p>
                </div>
            </div>

            <CardContent className="p-0">
                {activities.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-8 text-center">
                        <div className="bg-muted/50 flex h-12 w-12 items-center justify-center rounded-xl">
                            <IconCircleCheck className="text-muted-foreground h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-foreground text-sm font-medium">All caught up!</p>
                            <p className="text-muted-foreground text-xs">No recent activity to show</p>
                        </div>
                    </div>
                ) : (
                    <ScrollArea className="h-[280px]">
                        <div className="relative p-4">
                            <div className="from-primary/20 via-border absolute top-3 bottom-3 left-7 w-0.5 bg-gradient-to-b to-transparent" />

                            <div className="space-y-3">
                                {activities.map((activity, index) => {
                                    const Icon = getActivityIcon(activity.action);
                                    const colors = getActivityColor(activity.action);

                                    return (
                                        <motion.div
                                            key={index}
                                            initial={{ opacity: 0, x: -20 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            transition={{ delay: index * 0.08 }}
                                            whileHover={{ x: 4 }}
                                            className="group relative flex items-start gap-3"
                                        >
                                            <div className="relative z-10 flex items-center justify-center">
                                                <motion.div
                                                    whileHover={{ scale: 1.2 }}
                                                    className={cn(
                                                        "ring-background flex h-6 w-6 items-center justify-center rounded-full ring-2",
                                                        colors.bg,
                                                        colors.ring,
                                                    )}
                                                >
                                                    <Icon className="text-foreground/70 h-3 w-3" />
                                                </motion.div>
                                            </div>

                                            <div
                                                className={cn(
                                                    "border-border/60 flex-1 rounded-xl border p-3 transition-all duration-200",
                                                    "group-hover:border-primary/30 group-hover:bg-muted/30",
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="text-foreground text-sm leading-tight font-medium">{activity.action}</p>
                                                    <span className="text-muted-foreground bg-muted/50 shrink-0 rounded px-1.5 py-0.5 text-[10px]">
                                                        {activity.time}
                                                    </span>
                                                </div>
                                                <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">{activity.target}</p>
                                            </div>
                                        </motion.div>
                                    );
                                })}
                            </div>
                        </div>
                    </ScrollArea>
                )}
            </CardContent>
        </motion.div>
    );
}
