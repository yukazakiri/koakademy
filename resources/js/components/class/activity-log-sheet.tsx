import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { IconActivity, IconChartBar, IconNews, IconPencil, IconSchool, IconUserMinus, IconUserPlus } from "@tabler/icons-react";
import axios from "axios";
import { useEffect, useState } from "react";

interface Activity {
    id: number;
    action: string;
    details: string;
    time: string;
    timestamp: string;
    event: string;
    type: string;
}

interface ActivityLogSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classId: number;
    className: string;
}

export function ActivityLogSheet({ open, onOpenChange, classId, className }: ActivityLogSheetProps) {
    const [activities, setActivities] = useState<Activity[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open && classId) {
            fetchActivities();
        }
    }, [open, classId]);

    const fetchActivities = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/faculty/classes/${classId}/activity-log`);
            setActivities(response.data.activities);
        } catch (err) {
            console.error("Failed to fetch activity log:", err);
            setError("Failed to load activity log");
        } finally {
            setLoading(false);
        }
    };

    const getActivityIcon = (type: string, event: string) => {
        if (type === "ClassEnrollment") {
            if (event === "created") return <IconUserPlus className="size-4 text-emerald-500" />;
            if (event === "deleted") return <IconUserMinus className="size-4 text-rose-500" />;
            return <IconChartBar className="size-4 text-blue-500" />;
        }
        if (type === "ClassPost") {
            return <IconNews className="size-4 text-violet-500" />;
        }
        if (type === "Classes") {
            return <IconSchool className="size-4 text-amber-500" />;
        }
        return <IconPencil className="text-muted-foreground size-4" />;
    };

    const getEventBadge = (event: string) => {
        const variants: Record<string, { label: string; className: string }> = {
            created: { label: "New", className: "bg-emerald-500/10 text-emerald-500 border-emerald-500/20" },
            updated: { label: "Updated", className: "bg-blue-500/10 text-blue-500 border-blue-500/20" },
            deleted: { label: "Removed", className: "bg-rose-500/10 text-rose-500 border-rose-500/20" },
        };
        const variant = variants[event] || { label: event, className: "bg-muted" };
        return (
            <Badge variant="outline" className={`px-1.5 py-0 text-[10px] ${variant.className}`}>
                {variant.label}
            </Badge>
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader className="border-b pb-4">
                    <div className="flex items-center gap-2">
                        <div className="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-full">
                            <IconActivity className="text-primary size-4" />
                        </div>
                        <div>
                            <SheetTitle>Activity Log</SheetTitle>
                            <SheetDescription className="text-xs">{className}</SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <ScrollArea className="h-[calc(100vh-120px)] pr-4">
                    <div className="space-y-1 py-4">
                        {loading ? (
                            // Loading skeleton
                            Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex gap-3 p-3">
                                    <Skeleton className="h-8 w-8 shrink-0 rounded-full" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-4 w-3/4" />
                                        <Skeleton className="h-3 w-1/2" />
                                    </div>
                                </div>
                            ))
                        ) : error ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <IconActivity className="text-muted-foreground/30 mb-3 size-12" />
                                <p className="text-muted-foreground text-sm">{error}</p>
                            </div>
                        ) : activities.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <IconActivity className="text-muted-foreground/30 mb-3 size-12" />
                                <p className="text-foreground text-sm font-medium">No activity yet</p>
                                <p className="text-muted-foreground mt-1 text-xs">Activity will appear here when changes are made</p>
                            </div>
                        ) : (
                            <div className="relative">
                                {/* Timeline line */}
                                <div className="bg-border absolute top-3 bottom-3 left-[19px] w-px" />

                                {activities.map((activity, index) => (
                                    <div key={activity.id} className="hover:bg-muted/50 relative flex gap-3 rounded-lg p-3 transition-colors">
                                        {/* Icon */}
                                        <div className="bg-background border-border z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border">
                                            {getActivityIcon(activity.type, activity.event)}
                                        </div>

                                        {/* Content */}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="text-foreground text-sm leading-tight font-medium">{activity.action}</p>
                                                {getEventBadge(activity.event)}
                                            </div>
                                            {activity.details && <p className="text-muted-foreground mt-0.5 truncate text-xs">{activity.details}</p>}
                                            <p className="text-muted-foreground/70 mt-1 text-[10px]">{activity.time}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}
