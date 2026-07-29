import AdminLayout from "@/components/administrators/admin-layout";
import PortalLayout from "@/components/portal-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Pagination, PaginationContent, PaginationEllipsis, PaginationItem } from "@/components/ui/pagination";
import { isAdministratorPortalRole, isFacultyPortalRole } from "@/lib/portal-role";
import { cn } from "@/lib/utils";
import {
    inbox as administratorNotificationInbox,
    destroy as destroyAdministratorNotification,
    read as readAdministratorNotification,
    readAll as readAllAdministratorNotifications,
} from "@/routes/administrators/notifications";
import {
    destroy as destroyFacultyNotification,
    inbox as facultyNotificationInbox,
    readAll as readAllFacultyNotifications,
    read as readFacultyNotification,
} from "@/routes/faculty/notifications";
import {
    destroy as destroyStudentNotification,
    readAll as readAllStudentNotifications,
    read as readStudentNotification,
    inbox as studentNotificationInbox,
} from "@/routes/student/notifications";
import type { NotificationAction, PaginatedNotifications, PortalNotification } from "@/types/notification";
import type { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    IconAlertTriangle,
    IconArrowUpRight,
    IconBell,
    IconCheck,
    IconChecks,
    IconCircleCheck,
    IconInfoCircle,
    IconTrash,
    IconX,
} from "@tabler/icons-react";
import { format, formatDistanceToNow } from "date-fns";
import { useState, type ComponentProps } from "react";

type NotificationStatus = "all" | "unread" | "read";

interface NotificationInboxProps {
    notificationFeed: PaginatedNotifications;
    filters: {
        status: NotificationStatus;
    };
}

interface SharedPageProps {
    auth: {
        user: User;
    };
    unreadNotificationsCount: number;
    [key: string]: unknown;
}

interface NotificationRouteSet {
    inbox: (status: NotificationStatus) => string;
    read: (id: string) => string;
    readAll: () => string;
    destroy: (id: string) => string;
}

const statusFilters: Array<{ value: NotificationStatus; label: string }> = [
    { value: "all", label: "All" },
    { value: "unread", label: "Unread" },
    { value: "read", label: "Read" },
];

function routesForRole(role: string): NotificationRouteSet {
    if (isAdministratorPortalRole(role)) {
        return {
            inbox: (status) => administratorNotificationInbox.url({ query: { status: status === "all" ? undefined : status } }),
            read: (id) => readAdministratorNotification.url(id),
            readAll: () => readAllAdministratorNotifications.url(),
            destroy: (id) => destroyAdministratorNotification.url(id),
        };
    }

    if (isFacultyPortalRole(role)) {
        return {
            inbox: (status) => facultyNotificationInbox.url({ query: { status: status === "all" ? undefined : status } }),
            read: (id) => readFacultyNotification.url(id),
            readAll: () => readAllFacultyNotifications.url(),
            destroy: (id) => destroyFacultyNotification.url(id),
        };
    }

    return {
        inbox: (status) => studentNotificationInbox.url({ query: { status: status === "all" ? undefined : status } }),
        read: (id) => readStudentNotification.url(id),
        readAll: () => readAllStudentNotifications.url(),
        destroy: (id) => destroyStudentNotification.url(id),
    };
}

function typePresentation(type: PortalNotification["notificationType"]) {
    switch (type) {
        case "success":
            return {
                icon: IconCircleCheck,
                className: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
            };
        case "warning":
            return {
                icon: IconAlertTriangle,
                className: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
            };
        case "error":
            return {
                icon: IconX,
                className: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
            };
        default:
            return {
                icon: IconInfoCircle,
                className: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
            };
    }
}

function actionVariant(color: string | null): ComponentProps<typeof Button>["variant"] {
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
}

function notificationTime(createdAt: string): { relative: string; exact: string } {
    const date = new Date(createdAt);

    if (Number.isNaN(date.getTime())) {
        return { relative: createdAt, exact: createdAt };
    }

    return {
        relative: formatDistanceToNow(date, { addSuffix: true }),
        exact: format(date, "MMM d, yyyy 'at' h:mm a"),
    };
}

export default function NotificationInbox({ notificationFeed, filters }: NotificationInboxProps) {
    const { auth, unreadNotificationsCount } = usePage<SharedPageProps>().props;
    const user = auth.user;
    const notificationRoutes = routesForRole(user.role);
    const isAdministrator = isAdministratorPortalRole(user.role);
    const [notificationToDelete, setNotificationToDelete] = useState<PortalNotification | null>(null);

    const markAsRead = (notification: PortalNotification) => {
        if (notification.readAt) {
            return;
        }

        router.post(notificationRoutes.read(notification.id), {}, { preserveScroll: true });
    };

    const markAllAsRead = () => {
        router.post(notificationRoutes.readAll(), {}, { preserveScroll: true });
    };

    const deleteNotification = () => {
        if (!notificationToDelete) {
            return;
        }

        router.delete(notificationRoutes.destroy(notificationToDelete.id), {
            preserveScroll: true,
            onFinish: () => setNotificationToDelete(null),
        });
    };

    const openAction = (notification: PortalNotification, action: NotificationAction) => {
        if (!action.url) {
            return;
        }

        if (action.shouldOpenInNewTab) {
            window.open(action.url, "_blank", "noopener,noreferrer");

            if (!notification.readAt) {
                router.post(notificationRoutes.read(notification.id), {}, { preserveScroll: true });
            }

            return;
        }

        if (notification.readAt) {
            router.visit(action.url);
            return;
        }

        router.post(notificationRoutes.read(notification.id), {}, { onSuccess: () => router.visit(action.url as string) });
    };

    const content = (
        <>
            <Head title="Notifications" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <header className="bg-foreground text-background relative overflow-hidden rounded-2xl px-5 py-6 shadow-sm sm:px-7 sm:py-8">
                    <div className="absolute inset-y-0 right-0 w-48 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.16),transparent_68%)]" />
                    <div className="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div className="max-w-2xl space-y-2">
                            <div className="text-background/70 flex items-center gap-2 text-sm font-medium">
                                <IconBell className="size-4" />
                                Personal inbox
                            </div>
                            <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Notifications</h1>
                            <p className="text-background/70 max-w-xl text-sm leading-6 text-pretty sm:text-base">
                                Review updates from across your portal, follow their actions, and keep your inbox current.
                            </p>
                        </div>

                        {unreadNotificationsCount > 0 && (
                            <Button variant="secondary" className="w-fit gap-2 transition-transform active:scale-[0.96]" onClick={markAllAsRead}>
                                <IconChecks className="size-4" />
                                Mark all as read
                            </Button>
                        )}
                    </div>
                </header>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="bg-muted inline-flex w-fit items-center gap-1 rounded-xl p-1">
                        {statusFilters.map((filter) => (
                            <Button key={filter.value} variant={filters.status === filter.value ? "default" : "ghost"} size="sm" asChild>
                                <Link
                                    href={notificationRoutes.inbox(filter.value)}
                                    preserveState
                                    className="min-w-20 transition-transform active:scale-[0.96]"
                                >
                                    {filter.label}
                                </Link>
                            </Button>
                        ))}
                    </div>

                    <p className="text-muted-foreground text-sm tabular-nums">
                        {notificationFeed.total === 0
                            ? "No notifications"
                            : `Showing ${notificationFeed.from}–${notificationFeed.to} of ${notificationFeed.total}`}
                    </p>
                </div>

                {notificationFeed.data.length === 0 ? (
                    <Card className="border-dashed shadow-none">
                        <CardContent className="flex min-h-72 flex-col items-center justify-center gap-3 px-6 text-center">
                            <div className="bg-muted text-muted-foreground flex size-14 items-center justify-center rounded-2xl">
                                <IconBell className="size-7" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold">Nothing here right now</h2>
                                <p className="text-muted-foreground max-w-sm text-sm text-pretty">
                                    {filters.status === "all"
                                        ? "New portal updates will appear here as soon as they arrive."
                                        : `You have no ${filters.status} notifications.`}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <section className="flex flex-col gap-3" aria-label="Notification list">
                        {notificationFeed.data.map((notification) => {
                            const presentation = typePresentation(notification.notificationType);
                            const TypeIcon = presentation.icon;
                            const timestamp = notificationTime(notification.createdAt);
                            const actions = (notification.actions ?? []).filter((action) => action.url);

                            return (
                                <article
                                    key={notification.id}
                                    className={cn(
                                        "bg-card text-card-foreground ring-foreground/10 relative overflow-hidden rounded-2xl px-4 py-4 shadow-sm ring-1 sm:px-5",
                                        !notification.readAt && "ring-primary/25",
                                    )}
                                >
                                    {!notification.readAt && <span className="bg-primary absolute inset-y-0 left-0 w-1" />}

                                    <div className="flex items-start gap-3 sm:gap-4">
                                        <div
                                            className={cn(
                                                "mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl",
                                                presentation.className,
                                            )}
                                        >
                                            <TypeIcon className="size-5" />
                                        </div>

                                        <div className="min-w-0 flex-1 space-y-3">
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h2 className="text-sm font-semibold text-pretty sm:text-base">{notification.title}</h2>
                                                        <Badge variant={notification.readAt ? "outline" : "default"}>
                                                            {notification.readAt ? "Read" : "Unread"}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-muted-foreground text-sm leading-6 text-pretty">{notification.message}</p>
                                                </div>

                                                <time
                                                    dateTime={notification.createdAt}
                                                    title={timestamp.exact}
                                                    className="text-muted-foreground shrink-0 text-xs"
                                                >
                                                    {timestamp.relative}
                                                </time>
                                            </div>

                                            <div className="border-foreground/8 flex flex-col gap-3 border-t pt-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {actions.map((action) => (
                                                        <Button
                                                            key={`${notification.id}-${action.name}`}
                                                            variant={actionVariant(action.color)}
                                                            size="sm"
                                                            className="gap-1.5 transition-transform active:scale-[0.96]"
                                                            onClick={() => openAction(notification, action)}
                                                        >
                                                            {action.label}
                                                            <IconArrowUpRight className="size-3.5" />
                                                        </Button>
                                                    ))}
                                                </div>

                                                <div className="flex items-center gap-1 self-end sm:self-auto">
                                                    {!notification.readAt && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="gap-1.5 transition-transform active:scale-[0.96]"
                                                            onClick={() => markAsRead(notification)}
                                                        >
                                                            <IconCheck className="size-4" />
                                                            Mark read
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive transition-[color,transform] active:scale-[0.96]"
                                                        onClick={() => setNotificationToDelete(notification)}
                                                        aria-label={`Delete ${notification.title}`}
                                                    >
                                                        <IconTrash className="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </section>
                )}

                {notificationFeed.last_page > 1 && (
                    <Pagination>
                        <PaginationContent className="flex-wrap">
                            <PaginationItem>
                                {notificationFeed.prev_page_url ? (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={notificationFeed.prev_page_url}>Previous</Link>
                                    </Button>
                                ) : (
                                    <Button variant="ghost" size="sm" disabled>
                                        Previous
                                    </Button>
                                )}
                            </PaginationItem>

                            {notificationFeed.links.slice(1, -1).map((link) => (
                                <PaginationItem key={link.label}>
                                    {link.label === "..." ? (
                                        <PaginationEllipsis />
                                    ) : link.url ? (
                                        <Button variant={link.active ? "outline" : "ghost"} size="icon" asChild>
                                            <Link href={link.url} aria-current={link.active ? "page" : undefined}>
                                                {link.label}
                                            </Link>
                                        </Button>
                                    ) : null}
                                </PaginationItem>
                            ))}

                            <PaginationItem>
                                {notificationFeed.next_page_url ? (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={notificationFeed.next_page_url}>Next</Link>
                                    </Button>
                                ) : (
                                    <Button variant="ghost" size="sm" disabled>
                                        Next
                                    </Button>
                                )}
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                )}
            </div>

            <AlertDialog open={notificationToDelete !== null} onOpenChange={(open) => !open && setNotificationToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete this notification?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This removes “{notificationToDelete?.title}” from your notification history. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive hover:bg-destructive/90 text-white transition-transform active:scale-[0.96]"
                            onClick={deleteNotification}
                        >
                            Delete notification
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );

    if (isAdministrator) {
        return (
            <AdminLayout user={user} title="Notifications">
                {content}
            </AdminLayout>
        );
    }

    return <PortalLayout user={user}>{content}</PortalLayout>;
}
