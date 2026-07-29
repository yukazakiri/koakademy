export interface NotificationAction {
    name: string;
    label: string;
    url: string | null;
    color: string | null;
    icon: string | null;
    shouldOpenInNewTab: boolean;
}

export interface PortalNotification {
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

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedNotifications {
    data: PortalNotification[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}
