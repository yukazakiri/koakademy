import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { IconBooks, IconClock, IconHeart, IconMinus, IconSchool, IconStar, IconTrendingDown, IconTrendingUp, IconUsers } from "@tabler/icons-react";
import { motion } from "framer-motion";

export interface Stat {
    label: string;
    value: string | number;
    icon?: string;
    trend?: string;
    trendDirection?: "up" | "down" | "neutral";
}

interface StatsGridProps {
    stats?: Stat[];
}

const iconMap: Record<string, React.ElementType> = {
    book: IconBooks,
    books: IconBooks,
    users: IconUsers,
    clock: IconClock,
    activity: IconStar,
    school: IconSchool,
    heart: IconHeart,
};

const colorSchemes = [
    {
        bg: "bg-gradient-to-br from-rose-500/10 to-rose-600/5",
        iconBg: "bg-gradient-to-br from-rose-500 to-rose-600",
        border: "border-rose-500/20",
        accent: "text-rose-600",
        ring: "stroke-rose-500",
    },
    {
        bg: "bg-gradient-to-br from-amber-500/10 to-amber-600/5",
        iconBg: "bg-gradient-to-br from-amber-500 to-amber-600",
        border: "border-amber-500/20",
        accent: "text-amber-600",
        ring: "stroke-amber-500",
    },
    {
        bg: "bg-gradient-to-br from-emerald-500/10 to-emerald-600/5",
        iconBg: "bg-gradient-to-br from-emerald-500 to-emerald-600",
        border: "border-emerald-500/20",
        accent: "text-emerald-600",
        ring: "stroke-emerald-500",
    },
    {
        bg: "bg-gradient-to-br from-sky-500/10 to-sky-600/5",
        iconBg: "bg-gradient-to-br from-sky-500 to-sky-600",
        border: "border-sky-500/20",
        accent: "text-sky-600",
        ring: "stroke-sky-500",
    },
];

function AnimatedRing({ progress, color, delay = 0 }: { progress: number; color: string; delay?: number }) {
    const radius = 40;
    const circumference = 2 * Math.PI * radius;
    const strokeDashoffset = circumference - (progress / 100) * circumference;

    return (
        <motion.svg
            width="100"
            height="100"
            viewBox="0 0 100 100"
            className="absolute -top-3 -right-3 opacity-30"
            initial={{ rotate: -90 }}
            animate={{ rotate: -90 }}
        >
            <circle cx="50" cy="50" r={radius} fill="none" strokeWidth="6" stroke="currentColor" className="text-border/30" />
            <motion.circle
                cx="50"
                cy="50"
                r={radius}
                fill="none"
                strokeWidth="6"
                strokeLinecap="round"
                className={color}
                initial={{ strokeDashoffset: circumference }}
                animate={{ strokeDashoffset }}
                transition={{ duration: 1.5, delay, ease: "easeOut" }}
                style={{ strokeDasharray: circumference }}
            />
        </motion.svg>
    );
}

export function StatsGrid({ stats = [] }: StatsGridProps) {
    if (!stats.length) return null;

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {stats.map((stat, index) => {
                const scheme = colorSchemes[index % colorSchemes.length];
                const IconComponent = stat.icon ? (iconMap[stat.icon] ?? IconBooks) : IconBooks;
                const numericValue = typeof stat.value === "number" ? stat.value : parseInt(String(stat.value).replace(/[^0-9]/g, ""), 10) || 0;
                const progress = Math.min(numericValue > 100 ? 100 : numericValue, 100);

                return (
                    <motion.div
                        key={index}
                        initial={{ opacity: 0, y: 20, scale: 0.95 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        transition={{ delay: index * 0.1, duration: 0.4 }}
                        whileHover={{ y: -4, transition: { duration: 0.2 } }}
                    >
                        <Card
                            className={cn(
                                "relative overflow-hidden border-2 transition-all duration-300",
                                scheme.bg,
                                scheme.border,
                                "hover:shadow-primary/5 hover:shadow-lg",
                            )}
                        >
                            <CardContent className="p-5">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <motion.div
                                            initial={{ scale: 0 }}
                                            animate={{ scale: 1 }}
                                            transition={{ type: "spring", stiffness: 200, damping: 15, delay: index * 0.1 + 0.2 }}
                                            className={cn("flex h-11 w-11 items-center justify-center rounded-xl shadow-lg", scheme.iconBg)}
                                        >
                                            <IconComponent className="h-5 w-5 text-white" />
                                        </motion.div>

                                        <div className="space-y-0.5">
                                            <p className="text-muted-foreground text-xs font-medium">{stat.label}</p>
                                            <motion.p
                                                initial={{ opacity: 0, x: -10 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ delay: index * 0.1 + 0.3 }}
                                                className="text-foreground text-2xl font-bold"
                                            >
                                                {stat.value}
                                            </motion.p>
                                        </div>
                                    </div>

                                    {stat.trend && (
                                        <motion.div
                                            initial={{ opacity: 0, scale: 0 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            transition={{ delay: index * 0.1 + 0.4 }}
                                            className={cn(
                                                "flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[10px] font-semibold",
                                                stat.trendDirection === "up" && "bg-emerald-500/15 text-emerald-600",
                                                stat.trendDirection === "down" && "bg-rose-500/15 text-rose-600",
                                                stat.trendDirection === "neutral" && "bg-slate-500/15 text-slate-600",
                                            )}
                                        >
                                            {stat.trendDirection === "up" && <IconTrendingUp className="h-3 w-3" />}
                                            {stat.trendDirection === "down" && <IconTrendingDown className="h-3 w-3" />}
                                            {stat.trendDirection === "neutral" && <IconMinus className="h-3 w-3" />}
                                            {stat.trend}
                                        </motion.div>
                                    )}
                                </div>

                                <div className="mt-3 flex items-center gap-2">
                                    <div className="bg-border/30 h-1.5 flex-1 overflow-hidden rounded-full">
                                        <motion.div
                                            initial={{ width: 0 }}
                                            animate={{ width: `${progress}%` }}
                                            transition={{ duration: 1, delay: index * 0.1 + 0.5, ease: "easeOut" }}
                                            className={cn("h-full rounded-full", scheme.iconBg)}
                                        />
                                    </div>
                                    <span className="text-muted-foreground text-[10px] font-medium">{progress}%</span>
                                </div>

                                <AnimatedRing progress={progress} color={scheme.ring} delay={index * 0.1} />
                            </CardContent>
                        </Card>
                    </motion.div>
                );
            })}
        </div>
    );
}
