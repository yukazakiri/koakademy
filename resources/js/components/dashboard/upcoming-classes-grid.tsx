import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { IconBook2, IconChevronRight, IconMapPin, IconSchool, IconUsers } from "@tabler/icons-react";
import { motion } from "framer-motion";

interface ClassData {
    id: number | string;
    subject_code: string;
    subject_title: string;
    section: string;
    room?: string;
    school_year?: string;
    semester?: string;
    students_count?: number;
    schedule?: string;
    classification?: string;
}

interface UpcomingClassesGridProps {
    classes: ClassData[];
}

const cardColors = [
    {
        gradient: "from-violet-500/10 via-purple-500/5 to-transparent",
        accent: "bg-violet-500",
        border: "border-violet-500/20",
        badge: "bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300",
    },
    {
        gradient: "from-blue-500/10 via-cyan-500/5 to-transparent",
        accent: "bg-blue-500",
        border: "border-blue-500/20",
        badge: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
    },
    {
        gradient: "from-emerald-500/10 via-teal-500/5 to-transparent",
        accent: "bg-emerald-500",
        border: "border-emerald-500/20",
        badge: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300",
    },
    {
        gradient: "from-amber-500/10 via-orange-500/5 to-transparent",
        accent: "bg-amber-500",
        border: "border-amber-500/20",
        badge: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
    },
    {
        gradient: "from-rose-500/10 via-pink-500/5 to-transparent",
        accent: "bg-rose-500",
        border: "border-rose-500/20",
        badge: "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300",
    },
    {
        gradient: "from-indigo-500/10 via-blue-500/5 to-transparent",
        accent: "bg-indigo-500",
        border: "border-indigo-500/20",
        badge: "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300",
    },
];

export function UpcomingClassesGrid({ classes }: UpcomingClassesGridProps) {
    if (!classes?.length) {
        return (
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className="border-border/60 bg-muted/20 rounded-2xl border border-dashed p-8"
            >
                <div className="flex flex-col items-center justify-center gap-4 text-center">
                    <div className="from-muted to-muted/50 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br">
                        <IconSchool className="text-muted-foreground h-7 w-7" />
                    </div>
                    <div>
                        <p className="text-foreground font-medium">No upcoming classes</p>
                        <p className="text-muted-foreground mt-1 text-sm">Create a new class to get started</p>
                    </div>
                    <Button asChild size="sm" className="mt-2">
                        <Link href="/faculty/classes/create">Create Class</Link>
                    </Button>
                </div>
            </motion.div>
        );
    }

    return (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.4 }} className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                    <div className="from-primary/20 to-primary/5 flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br">
                        <IconBook2 className="text-primary h-4 w-4" />
                    </div>
                    <div>
                        <h2 className="text-foreground text-lg font-semibold">Your Classes</h2>
                        <p className="text-muted-foreground text-xs">
                            {classes.length} active class{classes.length !== 1 ? "es" : ""}
                        </p>
                    </div>
                </div>
                <Button variant="ghost" size="sm" className="gap-1 text-xs" asChild>
                    <Link href="/faculty/classes">
                        View All
                        <IconChevronRight className="h-3.5 w-3.5" />
                    </Link>
                </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {classes.slice(0, 6).map((classItem, index) => {
                    const colors = cardColors[index % cardColors.length];

                    return (
                        <motion.div
                            key={classItem.id}
                            initial={{ opacity: 0, y: 20, scale: 0.95 }}
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            transition={{ delay: index * 0.1 }}
                            whileHover={{ y: -4, transition: { duration: 0.2 } }}
                        >
                            <Link
                                href={`/faculty/classes/${classItem.id}`}
                                className={cn(
                                    "group block rounded-2xl border-2 p-4 transition-all duration-300",
                                    `bg-gradient-to-br ${colors.gradient}`,
                                    colors.border,
                                    "hover:shadow-primary/5 hover:shadow-lg",
                                )}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <div className={cn("flex h-10 w-10 items-center justify-center rounded-xl shadow-sm", colors.accent)}>
                                            <IconBook2 className="h-5 w-5 text-white" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-foreground group-hover:text-foreground/90 line-clamp-1 font-semibold">
                                                {classItem.subject_title}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{classItem.subject_code}</p>
                                        </div>
                                    </div>
                                    <Badge className={cn("text-[10px] font-medium", colors.badge)}>{classItem.section}</Badge>
                                </div>

                                <div className="mt-4 flex items-center justify-between">
                                    <div className="text-muted-foreground flex items-center gap-4 text-xs">
                                        {classItem.students_count !== undefined && (
                                            <span className="flex items-center gap-1">
                                                <IconUsers className="h-3.5 w-3.5" />
                                                {classItem.students_count}
                                            </span>
                                        )}
                                        {classItem.room && (
                                            <span className="flex items-center gap-1">
                                                <IconMapPin className="h-3.5 w-3.5" />
                                                {classItem.room}
                                            </span>
                                        )}
                                    </div>

                                    <motion.div
                                        initial={{ opacity: 0, x: -5 }}
                                        whileHover={{ opacity: 1, x: 0 }}
                                        className="text-primary flex items-center gap-1 text-xs opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        Open
                                        <IconChevronRight className="h-3.5 w-3.5" />
                                    </motion.div>
                                </div>

                                {classItem.classification && (
                                    <div className="border-border/30 mt-3 border-t pt-3">
                                        <Badge variant="outline" className="text-[10px]">
                                            {classItem.classification === "college" ? "College" : "Senior High"}
                                        </Badge>
                                    </div>
                                )}
                            </Link>
                        </motion.div>
                    );
                })}
            </div>
        </motion.div>
    );
}
