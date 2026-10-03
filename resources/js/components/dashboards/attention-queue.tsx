import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    ArrowRight,
    Check,
    CheckCircle2,
    CircleAlert,
    CircleCheck,
    ClipboardCheck,
    Filter,
    Landmark,
    RotateCcw,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    UserCheck,
    Users,
    Wrench,
    type LucideIcon,
} from "lucide-react";
import { useMemo, useState } from "react";
import type { DeskQueueItem, DeskTone } from "./types";

type AttentionQueueProps = {
    items: DeskQueueItem[];
    title?: string;
    description?: string;
};

type AlertVariant = "default" | "destructive" | "warning" | "success" | "info" | "invert";

type SeverityConfig = {
    alertVariant: AlertVariant;
    badgeVariant: "warning-light" | "destructive-light" | "info-light" | "success-light" | "outline";
    iconClass: string;
    alertClass: string;
};

const SEVERITY_CONFIG: Record<DeskTone, SeverityConfig> = {
    destructive: {
        alertVariant: "destructive",
        badgeVariant: "destructive-light",
        iconClass: "text-rose-600 dark:text-rose-400",
        alertClass: "hover:border-rose-500/50 bg-rose-500/5",
    },
    warning: {
        alertVariant: "warning",
        badgeVariant: "warning-light",
        iconClass: "text-amber-600 dark:text-amber-400",
        alertClass: "hover:border-amber-500/50 bg-amber-500/5",
    },
    info: {
        alertVariant: "info",
        badgeVariant: "info-light",
        iconClass: "text-sky-600 dark:text-sky-400",
        alertClass: "hover:border-sky-500/50 bg-sky-500/5",
    },
    success: {
        alertVariant: "success",
        badgeVariant: "success-light",
        iconClass: "text-emerald-600 dark:text-emerald-400",
        alertClass: "hover:border-emerald-500/50 bg-emerald-500/5",
    },
    neutral: {
        alertVariant: "default",
        badgeVariant: "outline",
        iconClass: "text-primary",
        alertClass: "hover:border-primary/40 bg-card",
    },
};

const ICONS: Record<string, LucideIcon> = {
    banknote: Landmark,
    briefcase: CircleAlert,
    "clipboard-check": ClipboardCheck,
    help: CircleAlert,
    "shield-check": ShieldCheck,
    "shield-alert": ShieldAlert,
    tools: Wrench,
    "user-check": UserCheck,
    users: Users,
};

function iconFor(key?: string): LucideIcon {
    return (key && ICONS[key]) || CircleCheck;
}

/**
 * The prioritized action list at the top of a desk.
 *
 * Polished with category filtering, clear-all/dismiss capabilities, urgency styling,
 * ReUI Alert and Badges, and positive empty state.
 */
export function AttentionQueue({
    items,
    title = "Needs attention",
    description = "Prioritized operational items waiting on this desk.",
}: AttentionQueueProps) {
    const [dismissedIds, setDismissedIds] = useState<string[]>([]);
    const [filter, setFilter] = useState<"all" | "urgent" | "normal">("all");

    // Exclude locally dismissed items
    const activeItems = useMemo(() => {
        return items.filter((item) => !dismissedIds.includes(item.id));
    }, [items, dismissedIds]);

    const urgentCount = useMemo(() => {
        return activeItems.filter((i) => i.severity === "destructive" || i.severity === "warning").length;
    }, [activeItems]);

    const normalCount = useMemo(() => {
        return activeItems.filter((i) => i.severity !== "destructive" && i.severity !== "warning").length;
    }, [activeItems]);

    const filteredItems = useMemo(() => {
        if (filter === "urgent") {
            return activeItems.filter((i) => i.severity === "destructive" || i.severity === "warning");
        }
        if (filter === "normal") {
            return activeItems.filter((i) => i.severity !== "destructive" && i.severity !== "warning");
        }
        return activeItems;
    }, [activeItems, filter]);

    const dismissItem = (id: string) => {
        setDismissedIds((prev) => [...prev, id]);
    };

    const dismissAll = () => {
        setDismissedIds(items.map((i) => i.id));
    };

    const restoreAll = () => {
        setDismissedIds([]);
        setFilter("all");
    };

    if (activeItems.length === 0) {
        return (
            <Frame variant="default" spacing="default" className="shadow-xs">
                <FramePanel className="bg-card">
                    <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                        <div className="flex items-center justify-between">
                            <div className="space-y-0.5">
                                <FrameTitle className="text-foreground flex items-center gap-2 text-base font-semibold">
                                    <span>{title}</span>
                                    <Badge variant="success-light" size="sm" radius="full" className="font-medium">
                                        0 Actions
                                    </Badge>
                                </FrameTitle>
                                <FrameDescription className="text-muted-foreground text-xs">{description}</FrameDescription>
                            </div>
                            {dismissedIds.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={restoreAll}
                                    className="text-muted-foreground hover:text-foreground gap-1.5 text-xs"
                                >
                                    <RotateCcw className="size-3.5" />
                                    <span>Reset ({dismissedIds.length} hidden)</span>
                                </Button>
                            )}
                        </div>
                    </FrameHeader>

                    <div className="border-border/80 bg-muted/20 m-4 flex flex-col items-center justify-center rounded-xl border border-dashed px-4 py-8 text-center sm:m-5">
                        <IconTile variant="soft" size="lg" className="mb-3 text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="size-5" />
                        </IconTile>
                        <p className="text-foreground text-sm font-semibold">Desk queue clear</p>
                        <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                            No pending reviews, discrepancies, or unassigned tasks requiring your immediate attention.
                        </p>
                        <Badge variant="success-light" size="sm" radius="full" className="mt-3.5 gap-1.5 font-medium">
                            <Sparkles className="size-3" />
                            <span>All caught up</span>
                        </Badge>
                    </div>
                </FramePanel>
            </Frame>
        );
    }

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div className="space-y-0.5">
                            <div className="flex items-center gap-2">
                                <FrameTitle className="text-foreground text-base font-semibold">{title}</FrameTitle>
                                <Badge
                                    variant={urgentCount > 0 ? "warning-light" : "secondary"}
                                    size="sm"
                                    radius="full"
                                    className="font-medium tabular-nums"
                                >
                                    {activeItems.length} {activeItems.length === 1 ? "Action" : "Actions"}
                                </Badge>
                                {urgentCount > 0 && (
                                    <Badge variant="destructive-light" size="xs" radius="full" className="font-medium tabular-nums">
                                        {urgentCount} urgent
                                    </Badge>
                                )}
                            </div>
                            <FrameDescription className="text-muted-foreground text-xs">{description}</FrameDescription>
                        </div>

                        {/* Filter pills and dismiss support */}
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="bg-muted/50 border-border/50 inline-flex items-center gap-1 rounded-lg border p-0.5">
                                <button
                                    type="button"
                                    onClick={() => setFilter("all")}
                                    className={cn(
                                        "cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-all",
                                        filter === "all"
                                            ? "bg-background text-foreground font-semibold shadow-2xs"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    All ({activeItems.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setFilter("urgent")}
                                    className={cn(
                                        "cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-all",
                                        filter === "urgent"
                                            ? "bg-background font-semibold text-amber-600 shadow-2xs dark:text-amber-400"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    Urgent ({urgentCount})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setFilter("normal")}
                                    className={cn(
                                        "cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-all",
                                        filter === "normal"
                                            ? "bg-background text-foreground font-semibold shadow-2xs"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    Normal ({normalCount})
                                </button>
                            </div>

                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={dismissAll}
                                className="text-muted-foreground hover:text-foreground h-7 px-2 text-xs"
                                title="Dismiss all visible actions"
                            >
                                <Check className="mr-1 size-3" />
                                <span>Dismiss all</span>
                            </Button>
                        </div>
                    </div>
                </FrameHeader>

                <div className="grid gap-3 p-4 sm:p-5">
                    {filteredItems.length === 0 ? (
                        <div className="border-border/60 bg-muted/10 flex flex-col items-center justify-center rounded-xl border border-dashed py-8 text-center">
                            <Filter className="text-muted-foreground/60 mb-2 size-5" />
                            <p className="text-foreground text-xs font-medium">No items matching this filter</p>
                            <Button variant="link" size="sm" onClick={() => setFilter("all")} className="mt-1 text-xs">
                                Show all ({activeItems.length}) actions
                            </Button>
                        </div>
                    ) : (
                        filteredItems.map((item) => {
                            const Icon = iconFor(item.icon);
                            const config = SEVERITY_CONFIG[item.severity] ?? SEVERITY_CONFIG.neutral;

                            return (
                                <Alert
                                    key={item.id}
                                    variant={config.alertVariant}
                                    className={cn(
                                        "group relative flex flex-col justify-between gap-3.5 rounded-xl border p-3.5 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xs sm:flex-row sm:items-center sm:p-4",
                                        config.alertClass,
                                    )}
                                >
                                    <div className="flex min-w-0 items-start gap-3.5 sm:items-center">
                                        <IconTile
                                            variant="soft"
                                            size="default"
                                            className={cn("shrink-0 transition-transform duration-200 group-hover:scale-105", config.iconClass)}
                                        >
                                            <Icon className="size-4" />
                                        </IconTile>
                                        <div className="min-w-0 space-y-0.5">
                                            <AlertTitle className="text-foreground line-clamp-1 text-sm font-semibold tracking-tight">
                                                {item.title}
                                            </AlertTitle>
                                            {item.description ? (
                                                <AlertDescription className="text-muted-foreground line-clamp-1 text-xs">
                                                    {item.description}
                                                </AlertDescription>
                                            ) : null}
                                        </div>
                                    </div>

                                    <AlertAction className="border-border/40 flex shrink-0 items-center justify-between gap-2.5 border-t pt-2 sm:justify-end sm:border-t-0 sm:pt-0">
                                        <Badge
                                            variant={config.badgeVariant}
                                            size="default"
                                            radius="full"
                                            className="px-2.5 py-0.5 font-mono text-xs font-semibold tabular-nums"
                                        >
                                            {item.count} {item.count === 1 ? "item" : "items"}
                                        </Badge>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="group-hover:bg-primary group-hover:text-primary-foreground group-hover:border-primary shrink-0 gap-1.5 font-medium transition-all duration-200"
                                                render={<Link href={item.href} />}
                                            >
                                                <span>Review</span>
                                                <ArrowRight
                                                    aria-hidden="true"
                                                    className="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5"
                                                />
                                            </Button>

                                            <Button
                                                variant="ghost"
                                                size="icon-xs"
                                                onClick={() => dismissItem(item.id)}
                                                className="text-muted-foreground/60 hover:text-foreground opacity-60 transition-opacity group-hover:opacity-100"
                                                title="Dismiss from queue"
                                            >
                                                <Check className="size-3.5" />
                                            </Button>
                                        </div>
                                    </AlertAction>
                                </Alert>
                            );
                        })
                    )}
                </div>
            </FramePanel>
        </Frame>
    );
}
