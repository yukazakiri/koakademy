"use client";

import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { router, usePage } from "@inertiajs/react";
import { IconBell, IconBellOff, IconCheck, IconChecks, IconTrash } from "@tabler/icons-react";
import { formatDistanceToNow } from "date-fns";
import { useEffect, useState, type ComponentProps, type MouseEvent } from "react";

export interface NotificationAction {
    name: string;
    label: string;
    url: string | null;
    color: string | null;
    icon: string | null;
    shouldOpenInNewTab: boolean;
}

export interface Notification {
    id: string;
    type: string;
    title: string;
    message: string;
    icon: string;
    notificationType: "info" | "success" | "warning" | "error";
    actionUrl: string | null;
    actions?: NotificationAction[];
    readAt: string | null;
    createdAt: string;
}

interface PageProps {
    notifications: Notification[];
    unreadNotificationsCount: number;
    auth?: {
        user?: {
            id: number;
        };
    };
    [key: string]: unknown;
}

interface NotificationsPopoverProps {
    /**
     * Base URL for notification endpoints.
     * Defaults to "/notifications" for student/faculty portals.
     * Use "/administrators/notifications" for admin portal.
     */
    baseUrl?: string;
}

export function NotificationsPopover({ baseUrl = "/notifications" }: NotificationsPopoverProps) {
    const { props } = usePage<PageProps>();
    const initialNotifications = props.notifications ?? [];
    const initialUnreadCount = props.unreadNotificationsCount ?? 0;
    const userId = props.auth?.user?.id;

    // Local state for real-time updates
    const [notifications, setNotifications] = useState<Notification[]>(initialNotifications);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount);

    // Sync with server data when page props change
    useEffect(() => {
        setNotifications(props.notifications ?? []);
        setUnreadCount(props.unreadNotificationsCount ?? 0);
    }, [props.notifications, props.unreadNotificationsCount]);

    // Listen for real-time notifications
    useEffect(() => {
        if (!userId || typeof window === "undefined" || !window.Echo) {
            return;
        }

        const channel = window.Echo.private(`App.Models.User.${userId}`);

        channel.notification((notification: any) => {
            const data = notification?.data ?? notification ?? {};
            const actionsRaw = Array.isArray(data.actions) ? data.actions : [];

            let actions: NotificationAction[] = actionsRaw
                .filter((action: any) => action && typeof action === "object")
                .map((action: any) => ({
                    name: String(action.name ?? action.id ?? ""),
                    label: String(action.label ?? action.name ?? "View"),
                    url: action.url ?? null,
                    color: action.color ?? null,
                    icon: action.icon ?? null,
                    shouldOpenInNewTab: Boolean(action.shouldOpenInNewTab ?? action.shouldOpenUrlInNewTab ?? action.openUrlInNewTab ?? false),
                }));

            let actionUrl = data.action_url ?? data.actionUrl ?? data.download_url ?? null;

            if (actions.length === 0 && actionUrl) {
                actions = [
                    {
                        name: "action",
                        label: String(data.action_text ?? "View"),
                        url: actionUrl,
                        color: null,
                        icon: null,
                        shouldOpenInNewTab: false,
                    },
                ];
            }

            if (!actionUrl && actions.length === 1) {
                actionUrl = actions[0].url;
            }

            // Add new notification to the top of the list
            const newNotification: Notification = {
                id: String(notification.id ?? data.id),
                type: String(notification.type ?? "DatabaseNotification"),
                title: String(data.title ?? "Notification"),
                message: String(data.message ?? data.body ?? ""),
                icon: String(data.icon ?? "bell"),
                notificationType: (String(data.type ?? data.status ?? "info") as Notification["notificationType"]) || "info",
                actionUrl,
                actions,
                readAt: null,
                createdAt: String(data.created_at ?? notification.created_at ?? new Date().toISOString()),
            };

            setNotifications((prev) => [newNotification, ...prev].slice(0, 10));
            setUnreadCount((prev) => prev + 1);
        });

        return () => {
            channel.stopListening(".Illuminate\\Notifications\\Events\\BroadcastNotificationCreated");
        };
    }, [userId]);

    const handleMarkAsRead = (e: MouseEvent, id: string) => {
        e.stopPropagation();
        router.post(
            `${baseUrl}/${id}/read`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, readAt: new Date().toISOString() } : n)));
                    setUnreadCount((prev) => Math.max(0, prev - 1));
                },
            },
        );
    };

    const handleMarkAllAsRead = () => {
        router.post(
            `${baseUrl}/mark-all-read`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNotifications((prev) => prev.map((n) => ({ ...n, readAt: n.readAt || new Date().toISOString() })));
                    setUnreadCount(0);
                },
            },
        );
    };

    const handleDelete = (e: MouseEvent, id: string) => {
        e.stopPropagation();
        const wasUnread = notifications.find((n) => n.id === id && !n.readAt);
        router.delete(`${baseUrl}/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setNotifications((prev) => prev.filter((n) => n.id !== id));
                if (wasUnread) {
                    setUnreadCount((prev) => Math.max(0, prev - 1));
                }
            },
        });
    };

    const handleNotificationClick = (notification: Notification) => {
        // If there's an action URL, navigate to it
        if (notification.actionUrl) {
            router.visit(notification.actionUrl);
        }
        // Mark as read if unread
        if (!notification.readAt) {
            router.post(
                `${baseUrl}/${notification.id}/read`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setNotifications((prev) => prev.map((n) => (n.id === notification.id ? { ...n, readAt: new Date().toISOString() } : n)));
                        setUnreadCount((prev) => Math.max(0, prev - 1));
                    },
                },
            );
        }
    };

    const handleActionClick = (e: MouseEvent, notification: Notification, action: NotificationAction) => {
        e.preventDefault();
        e.stopPropagation();

        if (!action.url) {
            return;
        }

        if (action.shouldOpenInNewTab) {
            window.open(action.url, "_blank", "noopener,noreferrer");
        } else {
            router.visit(action.url);
        }

        if (!notification.readAt) {
            router.post(
                `${baseUrl}/${notification.id}/read`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setNotifications((prev) => prev.map((n) => (n.id === notification.id ? { ...n, readAt: new Date().toISOString() } : n)));
                        setUnreadCount((prev) => Math.max(0, prev - 1));
                    },
                },
            );
        }
    };

    const getNotificationTypeStyles = (type: string) => {
        switch (type) {
            case "success":
                return "bg-green-500/10 text-green-600 dark:text-green-400";
            case "warning":
                return "bg-yellow-500/10 text-yellow-600 dark:text-yellow-400";
            case "error":
                return "bg-red-500/10 text-red-600 dark:text-red-400";
            default:
                return "bg-blue-500/10 text-blue-600 dark:text-blue-400";
        }
    };

    const getIconForType = (type: string) => {
        // Return first letter of type as fallback
        return type.charAt(0).toUpperCase();
    };

    const getActionButtonVariant = (color: string | null): ComponentProps<typeof Button>["variant"] => {
        switch (color) {
            case "primary":
            case "success":
                return "default";
            case "danger":
            case "error":
                return "destructive";
            case "gray":
            case "secondary":
                return "secondary";
            default:
                return "outline";
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative rounded-full" aria-label="Open notifications">
                    <IconBell className="size-5" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full p-0 text-[10px] font-medium"
                        >
                            {unreadCount > 9 ? "9+" : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="start" className="my-2 w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Notifications</span>
                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground hover:text-foreground h-auto px-2 py-1 text-xs"
                            onClick={handleMarkAllAsRead}
                        >
                            <IconChecks className="mr-1 size-3" />
                            Mark all read
                        </Button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-center">
                        <IconBellOff className="text-muted-foreground/50 mb-2 size-10" />
                        <p className="text-muted-foreground text-sm font-medium">No notifications</p>
                        <p className="text-muted-foreground/70 text-xs">You're all caught up!</p>
                    </div>
                ) : (
                    <ScrollArea className="max-h-[300px]">
                        {notifications.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                className={cn("flex cursor-pointer items-start gap-3 p-3", !notification.readAt && "bg-muted/50")}
                                onClick={() => handleNotificationClick(notification)}
                            >
                                <Avatar className={cn("size-8 shrink-0", getNotificationTypeStyles(notification.notificationType))}>
                                    <AvatarFallback className="text-xs">{getIconForType(notification.notificationType)}</AvatarFallback>
                                </Avatar>
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <span className={cn("text-sm leading-tight font-medium", !notification.readAt && "font-semibold")}>
                                            {notification.title}
                                        </span>
                                        {!notification.readAt && <span className="bg-primary size-2 shrink-0 rounded-full" />}
                                    </div>
                                    <p className="text-muted-foreground line-clamp-2 text-xs">{notification.message}</p>

                                    {notification.actions && notification.actions.length > 0 && (
                                        <div className="flex flex-wrap items-center gap-2 pt-1">
                                            {notification.actions
                                                .filter((action) => action.url)
                                                .slice(0, 2)
                                                .map((action) => (
                                                    <Button
                                                        key={`${notification.id}-${action.name}`}
                                                        variant={getActionButtonVariant(action.color)}
                                                        size="sm"
                                                        className="h-7 px-2 text-xs"
                                                        onClick={(e) => handleActionClick(e, notification, action)}
                                                    >
                                                        {action.label}
                                                    </Button>
                                                ))}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground/70 text-[10px]">
                                            {formatDistanceToNow(new Date(notification.createdAt), {
                                                addSuffix: true,
                                            })}
                                        </span>
                                        <div className="flex items-center gap-1">
                                            {!notification.readAt && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6"
                                                    onClick={(e) => handleMarkAsRead(e, notification.id)}
                                                    title="Mark as read"
                                                >
                                                    <IconCheck className="size-3" />
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive size-6"
                                                onClick={(e) => handleDelete(e, notification.id)}
                                                title="Delete"
                                            >
                                                <IconTrash className="size-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </DropdownMenuItem>
                        ))}
                    </ScrollArea>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
