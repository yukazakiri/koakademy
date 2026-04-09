import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import type { User } from "@/types/user";
import { Head } from "@inertiajs/react";
import { IconAlertTriangle, IconBell, IconCalendar, IconCheck, IconInfoCircle, IconLoader2, IconSpeakerphone } from "@tabler/icons-react";

interface Announcement {
    id: number;
    title: string;
    content: string;
    type: "info" | "success" | "warning" | "danger" | "maintenance" | "enrollment" | "update";
    priority: string;
    date: string;
    is_read: boolean;
}

interface AnnouncementsIndexProps {
    user: User;
    announcements: Announcement[];
}

const typeMeta = {
    info: { icon: IconInfoCircle, accent: "bg-primary", badge: null },
    success: { icon: IconCheck, accent: "bg-emerald-500", badge: null },
    warning: { icon: IconAlertTriangle, accent: "bg-amber-500", badge: "Warning" },
    danger: { icon: IconAlertTriangle, accent: "bg-destructive", badge: "Critical" },
    maintenance: { icon: IconLoader2, accent: "bg-purple-500", badge: "Maintenance" },
    enrollment: { icon: IconCalendar, accent: "bg-cyan-500", badge: "Enrollment" },
    update: { icon: IconSpeakerphone, accent: "bg-indigo-500", badge: null },
} as const;

export default function PublicAnnouncementIndex({ user, announcements }: AnnouncementsIndexProps) {
    return (
        <PortalLayout user={user}>
            <Head title="Announcements" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-foreground text-3xl font-bold tracking-tight">Announcements</h1>
                    <p className="text-muted-foreground">Stay updated with the latest news and important notices.</p>
                </div>

                <div className="grid gap-4 md:grid-cols-1">
                    {announcements.length === 0 ? (
                        <Card className="border-dashed">
                            <CardContent className="text-muted-foreground flex flex-col items-center justify-center py-10 text-center">
                                <IconBell className="mb-4 size-10 opacity-20" />
                                <p>No announcements found.</p>
                            </CardContent>
                        </Card>
                    ) : (
                        announcements.map((announcement) => {
                            const meta = typeMeta[announcement.type] ?? typeMeta.info;
                            const Icon = meta.icon;

                            return (
                                <Card key={announcement.id} className="overflow-hidden transition-all hover:shadow-md">
                                    <div className="flex flex-col sm:flex-row">
                                        <div className={`w-full shrink-0 sm:w-2 ${meta.accent}`} />
                                        <div className="flex-1 p-6">
                                            <div className="mb-2 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Icon className="size-5" />
                                                    <h3 className="text-lg font-semibold">{announcement.title}</h3>
                                                    {meta.badge ? <Badge className="ml-2">{meta.badge}</Badge> : null}
                                                </div>
                                                <div className="text-muted-foreground flex items-center gap-1 text-sm">
                                                    <IconCalendar className="size-4" />
                                                    <span>{announcement.date}</span>
                                                </div>
                                            </div>
                                            <p className="text-muted-foreground text-sm leading-relaxed whitespace-pre-wrap">{announcement.content}</p>
                                        </div>
                                    </div>
                                </Card>
                            );
                        })
                    )}
                </div>
            </div>
        </PortalLayout>
    );
}
