import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import {
    IconAlertTriangle,
    IconCalendar,
    IconCheck,
    IconChevronDown,
    IconChevronUp,
    IconClock,
    IconInfoCircle,
    IconLink,
    IconLoader2,
    IconSpeakerphone,
    IconX,
} from "@tabler/icons-react";
import { AnimatePresence, motion } from "framer-motion";
import { useCallback, useEffect, useState } from "react";

export type AnnouncementDisplayMode = "banner" | "toast" | "modal";
export type AnnouncementPriority = "urgent" | "high" | "medium" | "low";
export type AnnouncementType = "info" | "success" | "warning" | "danger" | "maintenance" | "enrollment" | "update";

interface Announcement {
    id: number;
    title: string;
    content: string;
    type: AnnouncementType;
    priority?: AnnouncementPriority;
    display_mode?: AnnouncementDisplayMode;
    requires_acknowledgment?: boolean;
    link?: string | null;
    is_active?: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
}

interface AnnouncementBannerProps {
    announcements: Announcement[] | { data: Announcement[] };
    displayMode?: AnnouncementDisplayMode;
}

const priorityConfig = {
    urgent: {
        pulse: true,
        iconBg: "bg-red-600",
        borderColor: "border-l-red-600",
    },
    high: {
        pulse: false,
        iconBg: "bg-orange-500",
        borderColor: "border-l-orange-500",
    },
    medium: {
        pulse: false,
        iconBg: "bg-blue-500",
        borderColor: "border-l-blue-500",
    },
    low: {
        pulse: false,
        iconBg: "bg-gray-500",
        borderColor: "border-l-gray-500",
    },
};

const typeConfig = {
    info: {
        icon: IconInfoCircle,
        gradient: "from-blue-600 to-blue-700",
        bgGradient: "bg-gradient-to-r from-blue-600 to-blue-700",
        lightBg: "bg-blue-50 dark:bg-blue-950/30",
        textColor: "text-blue-100",
        iconColor: "text-white",
        borderColor: "border-l-blue-500",
        description: "Information",
    },
    success: {
        icon: IconCheck,
        gradient: "from-emerald-600 to-emerald-700",
        bgGradient: "bg-gradient-to-r from-emerald-600 to-emerald-700",
        lightBg: "bg-emerald-50 dark:bg-emerald-950/30",
        textColor: "text-emerald-100",
        iconColor: "text-white",
        borderColor: "border-l-emerald-500",
        description: "Success",
    },
    warning: {
        icon: IconAlertTriangle,
        gradient: "from-amber-500 to-amber-600",
        bgGradient: "bg-gradient-to-r from-amber-500 to-amber-600",
        lightBg: "bg-amber-50 dark:bg-amber-950/30",
        textColor: "text-amber-100",
        iconColor: "text-white",
        borderColor: "border-l-amber-500",
        description: "Warning",
    },
    danger: {
        icon: IconAlertTriangle,
        gradient: "from-red-600 to-red-700",
        bgGradient: "bg-gradient-to-r from-red-600 to-red-700",
        lightBg: "bg-red-50 dark:bg-red-950/30",
        textColor: "text-red-100",
        iconColor: "text-white",
        borderColor: "border-l-red-500",
        description: "Critical",
    },
    maintenance: {
        icon: IconLoader2,
        gradient: "from-purple-600 to-purple-700",
        bgGradient: "bg-gradient-to-r from-purple-600 to-purple-700",
        lightBg: "bg-purple-50 dark:bg-purple-950/30",
        textColor: "text-purple-100",
        iconColor: "text-white",
        borderColor: "border-l-purple-500",
        description: "Maintenance",
    },
    enrollment: {
        icon: IconCalendar,
        gradient: "from-cyan-600 to-cyan-700",
        bgGradient: "bg-gradient-to-r from-cyan-600 to-cyan-700",
        lightBg: "bg-cyan-50 dark:bg-cyan-950/30",
        textColor: "text-cyan-100",
        iconColor: "text-white",
        borderColor: "border-l-cyan-500",
        description: "Enrollment",
    },
    update: {
        icon: IconSpeakerphone,
        gradient: "from-indigo-600 to-indigo-700",
        bgGradient: "bg-gradient-to-r from-indigo-600 to-indigo-700",
        lightBg: "bg-indigo-50 dark:bg-indigo-950/30",
        textColor: "text-indigo-100",
        iconColor: "text-white",
        borderColor: "border-l-indigo-500",
        description: "Update",
    },
};

function AnnouncementItem({
    announcement,
    onDismiss,
    onAcknowledge,
    isAcknowledged,
}: {
    announcement: Announcement;
    onDismiss: (id: number) => void;
    onAcknowledge?: (id: number) => void;
    isAcknowledged: boolean;
}) {
    const [isExpanded, setIsExpanded] = useState(false);
    const [timeRemaining, setTimeRemaining] = useState<string | null>(null);
    const config = typeConfig[announcement.type] || typeConfig.info;
    const priority = priorityConfig[announcement.priority || "medium"];
    const Icon = config.icon;
    const isLongContent = announcement.content.length > 150;

    useEffect(() => {
        if (announcement.ends_at) {
            const updateCountdown = () => {
                const now = new Date().getTime();
                const end = new Date(announcement.ends_at!).getTime();
                const diff = end - now;

                if (diff <= 0) {
                    setTimeRemaining("Expired");
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

                if (days > 0) {
                    setTimeRemaining(`${days}d ${hours}h left`);
                } else if (hours > 0) {
                    setTimeRemaining(`${hours}h ${minutes}m left`);
                } else {
                    setTimeRemaining(`${minutes}m left`);
                }
            };

            updateCountdown();
            const interval = setInterval(updateCountdown, 60000);
            return () => clearInterval(interval);
        }
    }, [announcement.ends_at]);

    return (
        <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className={cn("relative overflow-hidden rounded-lg border shadow-sm", priority.borderColor, config.lightBg)}
        >
            <div className="flex items-start gap-3 p-4">
                <div className={cn("flex shrink-0 items-center justify-center rounded-lg shadow-md", config.gradient, "h-10 w-10")}>
                    <Icon className={cn("h-5 w-5", config.iconColor)} />
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h4 className="text-foreground font-semibold">{announcement.title}</h4>
                            {priority.pulse && (
                                <span className="flex h-2 w-2">
                                    <span className="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-red-400 opacity-75" />
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-red-500" />
                                </span>
                            )}
                        </div>
                        {timeRemaining && (
                            <span className="text-muted-foreground flex items-center gap-1 rounded-full bg-white/50 px-2 py-0.5 text-xs font-medium whitespace-nowrap dark:bg-black/20">
                                <IconClock className="h-3 w-3" />
                                {timeRemaining}
                            </span>
                        )}
                    </div>

                    <div className="mt-1">
                        {isLongContent && !isExpanded ? (
                            <p className="text-muted-foreground text-sm">
                                {announcement.content.slice(0, 150)}...
                                <button
                                    onClick={() => setIsExpanded(true)}
                                    className="ml-1 inline-flex items-center text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Read more <IconChevronDown className="h-3 w-3" />
                                </button>
                            </p>
                        ) : (
                            <p className="text-muted-foreground text-sm">{announcement.content}</p>
                        )}
                        {isLongContent && isExpanded && (
                            <button
                                onClick={() => setIsExpanded(false)}
                                className="mt-1 inline-flex items-center text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                Show less <IconChevronUp className="h-3 w-3" />
                            </button>
                        )}
                    </div>

                    {announcement.link && (
                        <a
                            href={announcement.link}
                            className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            <IconLink className="h-3 w-3" />
                            Learn more
                        </a>
                    )}
                </div>

                <div className="flex shrink-0 gap-2">
                    {announcement.requires_acknowledgment && !isAcknowledged ? (
                        <Button size="sm" onClick={() => onAcknowledge?.(announcement.id)} className="h-8 gap-1">
                            <IconCheck className="h-3.5 w-3.5" />
                            Acknowledge
                        </Button>
                    ) : (
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => onDismiss(announcement.id)}
                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                        >
                            <IconX className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </div>
        </motion.div>
    );
}

function ToastNotification({
    announcement,
    onDismiss,
    onAcknowledge,
    isAcknowledged,
}: {
    announcement: Announcement;
    onDismiss: (id: number) => void;
    onAcknowledge?: (id: number) => void;
    isAcknowledged: boolean;
}) {
    const config = typeConfig[announcement.type] || typeConfig.info;
    const Icon = config.icon;
    const priority = priorityConfig[announcement.priority || "medium"];

    return (
        <motion.div
            initial={{ opacity: 0, x: 100, scale: 0.9 }}
            animate={{ opacity: 1, x: 0, scale: 1 }}
            exit={{ opacity: 0, x: 100, scale: 0.9 }}
            className={cn("relative overflow-hidden rounded-lg border shadow-xl", config.lightBg, priority.borderColor, "border-l-4")}
        >
            <div className="flex items-start gap-3 p-4">
                <div className={cn("flex shrink-0 items-center justify-center rounded-full", config.gradient, "h-8 w-8")}>
                    <Icon className={cn("h-4 w-4", config.iconColor)} />
                </div>

                <div className="min-w-0 flex-1">
                    <h4 className="text-foreground text-sm font-semibold">{announcement.title}</h4>
                    <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">{announcement.content}</p>
                </div>

                {announcement.requires_acknowledgment && !isAcknowledged ? (
                    <Button size="sm" onClick={() => onAcknowledge?.(announcement.id)} className="h-7 text-xs">
                        <IconCheck className="mr-1 h-3 w-3" />
                        OK
                    </Button>
                ) : (
                    <Button variant="ghost" size="icon" onClick={() => onDismiss(announcement.id)} className="h-6 w-6">
                        <IconX className="h-3.5 w-3.5" />
                    </Button>
                )}
            </div>
        </motion.div>
    );
}

function ModalPopup({
    announcement,
    onDismiss,
    onAcknowledge,
    isAcknowledged,
}: {
    announcement: Announcement;
    onDismiss: (id: number) => void;
    onAcknowledge?: (id: number) => void;
    isAcknowledged: boolean;
}) {
    const config = typeConfig[announcement.type] || typeConfig.info;
    const Icon = config.icon;
    const priority = priorityConfig[announcement.priority || "medium"];

    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            onClick={() => !announcement.requires_acknowledgment && onDismiss(announcement.id)}
        >
            <motion.div
                initial={{ opacity: 0, scale: 0.9, y: 20 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.9, y: 20 }}
                onClick={(e) => e.stopPropagation()}
                className={cn(
                    "w-full max-w-md overflow-hidden rounded-2xl border-2 border-l-8 shadow-2xl",
                    priority.borderColor.replace("border-l", "border-l-8"),
                    "bg-background",
                )}
            >
                <div className={cn("p-6", config.gradient)}>
                    <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                            <Icon className="h-6 w-6 text-white" />
                        </div>
                        <div className="text-white">
                            <h3 className="text-lg font-bold">{announcement.title}</h3>
                            <p className="text-sm text-white/80">{config.description}</p>
                        </div>
                    </div>
                </div>

                <div className="p-6">
                    <p className="text-muted-foreground leading-relaxed">{announcement.content}</p>

                    {announcement.link && (
                        <a
                            href={announcement.link}
                            className="mt-4 inline-flex items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            <IconLink className="h-4 w-4" />
                            Learn more
                        </a>
                    )}

                    <div className="mt-6 flex gap-3">
                        {!announcement.requires_acknowledgment && (
                            <Button variant="outline" onClick={() => onDismiss(announcement.id)} className="flex-1">
                                Dismiss
                            </Button>
                        )}
                        <Button
                            onClick={() => onAcknowledge?.(announcement.id)}
                            className={cn("flex-1", announcement.requires_acknowledgment ? "w-full" : "")}
                        >
                            {announcement.requires_acknowledgment ? (
                                <>
                                    <IconCheck className="mr-2 h-4 w-4" />I Understand
                                </>
                            ) : (
                                "Got it"
                            )}
                        </Button>
                    </div>
                </div>
            </motion.div>
        </motion.div>
    );
}

export function AnnouncementBanner({ announcements, displayMode: defaultDisplayMode }: AnnouncementBannerProps) {
    const [dismissedIds, setDismissedIds] = useState<number[]>(() => {
        if (typeof window !== "undefined") {
            const saved = localStorage.getItem("dismissed_announcements");
            return saved ? JSON.parse(saved) : [];
        }
        return [];
    });

    const [acknowledgedIds, setAcknowledgedIds] = useState<number[]>(() => {
        if (typeof window !== "undefined") {
            const saved = localStorage.getItem("acknowledged_announcements");
            return saved ? JSON.parse(saved) : [];
        }
        return [];
    });

    const announcementsArray = Array.isArray(announcements) ? announcements : announcements?.data || [];

    const activeAnnouncements = announcementsArray.filter((a: Announcement) => {
        const now = new Date();
        const startsAt = a.starts_at ? new Date(a.starts_at) : null;
        const endsAt = a.ends_at ? new Date(a.ends_at) : null;

        if (startsAt && startsAt > now) return false;
        if (endsAt && endsAt < now) return false;

        return !dismissedIds.includes(a.id);
    });

    const urgentAnnouncement = activeAnnouncements.find((a: Announcement) => a.priority === "urgent" || a.requires_acknowledgment);

    const handleDismiss = useCallback((id: number) => {
        setDismissedIds((prev) => {
            const updated = [...prev, id];
            localStorage.setItem("dismissed_announcements", JSON.stringify(updated));
            return updated;
        });
    }, []);

    const handleAcknowledge = useCallback((id: number) => {
        setAcknowledgedIds((prev) => {
            const updated = [...prev, id];
            localStorage.setItem("acknowledged_announcements", JSON.stringify(updated));
            return updated;
        });
    }, []);

    const isAcknowledged = useCallback((id: number) => acknowledgedIds.includes(id), [acknowledgedIds]);

    if (!activeAnnouncements || activeAnnouncements.length === 0) {
        return null;
    }

    if (urgentAnnouncement) {
        const displayMode = urgentAnnouncement.display_mode || defaultDisplayMode || "modal";

        if (displayMode === "modal" || urgentAnnouncement.requires_acknowledgment) {
            return (
                <ModalPopup
                    announcement={urgentAnnouncement}
                    onDismiss={handleDismiss}
                    onAcknowledge={handleAcknowledge}
                    isAcknowledged={isAcknowledged(urgentAnnouncement.id)}
                />
            );
        }

        if (displayMode === "toast") {
            return (
                <div className="fixed top-20 right-4 z-50 flex w-full max-w-sm flex-col gap-2">
                    <ToastNotification
                        announcement={urgentAnnouncement}
                        onDismiss={handleDismiss}
                        onAcknowledge={handleAcknowledge}
                        isAcknowledged={isAcknowledged(urgentAnnouncement.id)}
                    />
                </div>
            );
        }

        return (
            <div className="z-[100]">
                <div className="mx-auto max-w-full space-y-2">
                    <AnnouncementItem
                        announcement={urgentAnnouncement}
                        onDismiss={handleDismiss}
                        onAcknowledge={handleAcknowledge}
                        isAcknowledged={isAcknowledged(urgentAnnouncement.id)}
                    />
                </div>
            </div>
        );
    }

    const sortedAnnouncements = [...activeAnnouncements].sort((a: Announcement, b: Announcement) => {
        const priorityOrder = { urgent: 0, high: 1, medium: 2, low: 3 };
        return (priorityOrder[a.priority || "medium"] || 2) - (priorityOrder[b.priority || "medium"] || 2);
    });

    return (
        <div className="z-[100]">
            <div className="mx-auto max-w-full space-y-2">
                <AnimatePresence mode="popLayout">
                    {sortedAnnouncements.slice(0, 3).map((announcement: Announcement) => {
                        const displayMode = announcement.display_mode || defaultDisplayMode || "banner";

                        if (displayMode === "toast") {
                            return (
                                <ToastNotification
                                    key={announcement.id}
                                    announcement={announcement}
                                    onDismiss={handleDismiss}
                                    onAcknowledge={handleAcknowledge}
                                    isAcknowledged={isAcknowledged(announcement.id)}
                                />
                            );
                        }

                        return (
                            <AnnouncementItem
                                key={announcement.id}
                                announcement={announcement}
                                onDismiss={handleDismiss}
                                onAcknowledge={handleAcknowledge}
                                isAcknowledged={isAcknowledged(announcement.id)}
                            />
                        );
                    })}
                </AnimatePresence>
                {sortedAnnouncements.length > 3 && (
                    <div className="bg-muted text-muted-foreground py-1 text-center text-xs">
                        +{sortedAnnouncements.length - 3} more announcements
                    </div>
                )}
            </div>
        </div>
    );
}

export default AnnouncementBanner;
