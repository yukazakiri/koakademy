import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { IconBell, IconChevronRight } from "@tabler/icons-react";

interface Announcement {
    title: string;
    content: string;
    date: string;
    type: "info" | "warning" | "important";
}

import { Link } from "@inertiajs/react";

interface AnnouncementsWidgetProps {
    announcements: Announcement[];
}

export function AnnouncementsWidget({ announcements }: AnnouncementsWidgetProps) {
    return (
        <Card className="border-border/60 shadow-sm">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <IconBell className="text-muted-foreground size-5" />
                        <CardTitle className="text-base">Announcements</CardTitle>
                    </div>
                    <Button variant="ghost" size="sm" className="h-8 text-xs" asChild>
                        <Link href="/announcements">
                            View All <IconChevronRight className="ml-1 size-3" />
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3">
                {announcements.length === 0 ? (
                    <p className="text-muted-foreground py-4 text-center text-sm">No announcements.</p>
                ) : (
                    announcements.slice(0, 3).map((announcement, index) => (
                        <div key={index} className="bg-card/50 hover:bg-muted/30 flex flex-col gap-1 rounded-lg border p-3 text-sm transition-colors">
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-foreground line-clamp-1 leading-none font-medium">{announcement.title}</span>
                                {announcement.type === "important" && (
                                    <div className="bg-destructive size-2 shrink-0 rounded-full" title="Important" />
                                )}
                            </div>
                            <p className="text-muted-foreground line-clamp-2 text-xs">{announcement.content}</p>
                            <div className="text-muted-foreground mt-1 text-[10px]">{announcement.date}</div>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
