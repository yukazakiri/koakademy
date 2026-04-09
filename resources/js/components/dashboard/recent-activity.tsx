import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { IconActivity } from "@tabler/icons-react";

interface Activity {
    action: string;
    target: string;
    time: string;
}

interface RecentActivityProps {
    activities: Activity[];
}

export function RecentActivity({ activities }: RecentActivityProps) {
    return (
        <Card className="border-border/60 flex h-full max-h-[400px] flex-col shadow-sm">
            <CardHeader className="pb-3">
                <div className="flex items-center gap-2">
                    <IconActivity className="text-muted-foreground size-5" />
                    <CardTitle className="text-base">Recent Activity</CardTitle>
                </div>
            </CardHeader>
            <CardContent className="min-h-0 flex-1">
                <ScrollArea className="h-[300px] pr-4">
                    <div className="border-border/50 relative my-2 ml-2 space-y-6 border-l">
                        {activities.length === 0 ? (
                            <p className="text-muted-foreground pl-6 text-sm">No recent activity.</p>
                        ) : (
                            activities.map((activity, index) => (
                                <div key={index} className="relative pl-6">
                                    <div className="border-background bg-muted-foreground/30 ring-background absolute top-1 -left-[5px] h-2.5 w-2.5 rounded-full border ring-2" />
                                    <div className="flex flex-col gap-1">
                                        <p className="text-foreground text-sm leading-none font-medium">{activity.action}</p>
                                        <p className="text-muted-foreground line-clamp-1 text-xs">{activity.target}</p>
                                        <span className="text-muted-foreground/70 text-[10px]">{activity.time}</span>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </ScrollArea>
            </CardContent>
        </Card>
    );
}
