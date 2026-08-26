import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Link } from "@inertiajs/react";
import { Activity, ArrowUpRight, Signal, Users } from "lucide-react";
import { ExtendedUser } from "./columns";

declare const route: (name: string, ...parameters: unknown[]) => string;

interface OnlineUsersWidgetProps {
    users: ExtendedUser[];
    onlineUserIds: number[];
}

export function OnlineUsersWidget({ users, onlineUserIds }: OnlineUsersWidgetProps) {
    const onlineUsers = users.filter((user) => onlineUserIds.includes(user.id));

    return (
        <Card size="sm" className="flex flex-col">
            <CardHeader className="border-b">
                <div className="flex items-center justify-between gap-3">
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <Signal aria-hidden="true" className="text-emerald-600 motion-safe:animate-pulse dark:text-emerald-400" />
                        Online now
                    </CardTitle>
                    <Badge variant="secondary" className="rounded-md tabular-nums">
                        {onlineUsers.length}
                    </Badge>
                </div>
                <p className="text-muted-foreground mt-1 text-xs">Active sessions in the current directory.</p>
            </CardHeader>
            <CardContent className="flex-1 pt-3">
                {onlineUsers.length === 0 ? (
                    <div className="text-muted-foreground flex min-h-28 flex-col items-center justify-center gap-2 text-center">
                        <Activity aria-hidden="true" className="size-5 opacity-40" />
                        <p className="text-sm">No active users</p>
                    </div>
                ) : (
                    <div className="max-h-64 space-y-1 overflow-y-auto pr-1">
                        {onlineUsers.map((user) => (
                            <Link
                                key={user.id}
                                href={route("administrators.users.edit", user.id)}
                                aria-label={`Edit ${user.name}`}
                                className="hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:ring-ring/45 flex min-h-11 items-center justify-between gap-3 rounded-lg px-2 py-1.5 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <div className="flex items-center gap-3 overflow-hidden">
                                    <div className="relative">
                                        <Avatar className="size-8 border">
                                            <AvatarImage src={user.avatar_url || undefined} alt={user.name} />
                                            <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                                        </Avatar>
                                        <span className="border-background absolute right-0 bottom-0 size-2.5 rounded-full border-2 bg-emerald-500" />
                                    </div>
                                    <div className="grid gap-0.5 overflow-hidden">
                                        <p className="truncate text-sm leading-tight font-medium">{user.name}</p>
                                        <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                    </div>
                                </div>
                                <ArrowUpRight aria-hidden="true" className="text-muted-foreground size-4 shrink-0" />
                            </Link>
                        ))}
                    </div>
                )}
                <div className="text-muted-foreground mt-3 flex items-center gap-1.5 border-t pt-3 text-xs">
                    <Users aria-hidden="true" className="size-3.5" />
                    <span>Presence updates live</span>
                </div>
            </CardContent>
        </Card>
    );
}
