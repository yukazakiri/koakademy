import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import type { User } from "@/types/user";
import { Head } from "@inertiajs/react";
import { IconAlertTriangle, IconBell, IconCalendar, IconCheck, IconInfoCircle, IconLoader2, IconSpeakerphone } from "@tabler/icons-react";
import { Megaphone } from "lucide-react";

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
    info: { icon: IconInfoCircle, accent: "bg-primary", tone: "text-primary bg-primary/10", badge: null },
    success: { icon: IconCheck, accent: "bg-emerald-500", tone: "text-emerald-500 bg-emerald-500/10", badge: null },
    warning: { icon: IconAlertTriangle, accent: "bg-amber-500", tone: "text-amber-500 bg-amber-500/10", badge: "Warning" },
    danger: { icon: IconAlertTriangle, accent: "bg-destructive", tone: "text-destructive bg-destructive/10", badge: "Critical" },
    maintenance: { icon: IconLoader2, accent: "bg-purple-500", tone: "text-purple-500 bg-purple-500/10", badge: "Maintenance" },
    enrollment: { icon: IconCalendar, accent: "bg-cyan-500", tone: "text-cyan-500 bg-cyan-500/10", badge: "Enrollment" },
    update: { icon: IconSpeakerphone, accent: "bg-indigo-500", tone: "text-indigo-500 bg-indigo-500/10", badge: null },
} as const;

const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

export default function PublicAnnouncementIndex({ user, announcements }: AnnouncementsIndexProps) {
    return (
        <PortalLayout user={user}>
            <Head title="Announcements" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 pb-16 md:gap-6 md:p-6">
                <Card className={dashboardPanelClass}>
                    <CardContent className="flex flex-col justify-between gap-5 p-4 md:flex-row md:items-end md:p-5">
                        <div className="space-y-2">
                            <div className="text-primary flex items-center gap-2 font-medium">
                                <Megaphone className="h-4 w-4" />
                                <span className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Student Updates</span>
                            </div>
                            <div>
                                <h1 className="text-foreground text-2xl font-semibold tracking-tight md:text-3xl">Announcements</h1>
                                <p className="text-muted-foreground mt-1 max-w-xl text-sm">
                                    Stay updated with the latest news and important notices for your academic period.
                                </p>
                            </div>
                        </div>
                        <div className="border-border/60 bg-background/65 rounded-lg border px-4 py-3 text-sm shadow-sm">
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Visible Notices</p>
                            <p className="text-foreground mt-1 text-2xl font-semibold tabular-nums">{announcements.length}</p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4">
                    {announcements.length === 0 ? (
                        <Card className={`${dashboardPanelClass} overflow-hidden`}>
                            <CardContent className="relative flex min-h-[340px] flex-col items-center justify-center p-8 text-center">
                                <IconBell className="text-primary pointer-events-none absolute top-8 right-8 size-24 opacity-10" />
                                <div className="bg-primary/10 text-primary mb-4 rounded-lg p-4">
                                    <IconBell className="size-9" />
                                </div>
                                <p className="text-foreground font-semibold">No announcements found</p>
                                <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                    New notices and school updates will appear here once they are published.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        announcements.map((announcement) => {
                            const meta = typeMeta[announcement.type] ?? typeMeta.info;
                            const Icon = meta.icon;

                            return (
                                <Card key={announcement.id} className={`${dashboardCardClass} group relative overflow-hidden hover:-translate-y-0.5`}>
                                    <Icon className="text-primary pointer-events-none absolute top-5 right-6 size-16 opacity-10 transition-all duration-200 group-hover:scale-105 group-hover:opacity-20" />
                                    <div className={`absolute inset-y-0 left-0 w-1 ${meta.accent}`} />
                                    <CardContent className="p-5 pr-20 pl-6 md:p-6 md:pr-24 md:pl-7">
                                        <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="flex min-w-0 items-start gap-3">
                                                <div className={`mt-0.5 shrink-0 rounded-lg p-2 ${meta.tone}`}>
                                                    <Icon className="size-4" />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h3 className="text-foreground text-base font-semibold md:text-lg">{announcement.title}</h3>
                                                        {meta.badge ? <Badge variant="secondary">{meta.badge}</Badge> : null}
                                                    </div>
                                                    <div className="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                                                        <IconCalendar className="size-3.5" />
                                                        <span>{announcement.date}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <p className="text-muted-foreground text-sm leading-relaxed whitespace-pre-wrap">{announcement.content}</p>
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}
                </div>
            </div>
        </PortalLayout>
    );
}
