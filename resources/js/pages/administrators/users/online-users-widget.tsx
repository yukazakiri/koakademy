import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Link } from "@inertiajs/react";
import { Activity, Signal, Users } from "lucide-react";
import { ExtendedUser } from "./columns";

declare const route: any;

interface OnlineUsersWidgetProps {
    users: ExtendedUser[];
    onlineUserIds: number[];
}

export function OnlineUsersWidget({ users, onlineUserIds }: OnlineUsersWidgetProps) {
    const onlineUsers = users.filter((user) => onlineUserIds.includes(user.id));

    return (
        <Card className="col-span-3 flex flex-col">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Signal className="h-4 w-4 animate-pulse text-emerald-500" />
                    Online Now
                </CardTitle>
                <CardDescription>Users currently active on the platform</CardDescription>
            </CardHeader>
            <CardContent className="flex-1">
                {onlineUsers.length === 0 ? (
                    <div className="text-muted-foreground flex h-full flex-col items-center justify-center space-y-2 py-8 text-center">
                        <Activity className="h-8 w-8 opacity-20" />
                        <p className="text-sm">No users online right now</p>
                    </div>
                ) : (
                    <div className="max-h-[280px] space-y-3 overflow-y-auto pr-2">
                        {onlineUsers.map((user) => (
                            <Link
                                key={user.id}
                                href={route("administrators.users.edit", user.id)}
                                className="hover:bg-muted/50 flex items-center justify-between gap-3 rounded-lg p-2 transition-colors"
                            >
                                <div className="flex items-center gap-3 overflow-hidden">
                                    <div className="relative">
                                        <Avatar className="h-9 w-9 border">
                                            <AvatarImage src={user.avatar_url || undefined} alt={user.name} />
                                            <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                                        </Avatar>
                                        <span className="border-background absolute right-0 bottom-0 h-2.5 w-2.5 rounded-full border-2 bg-emerald-500" />
                                    </div>
                                    <div className="grid gap-0.5 overflow-hidden">
                                        <p className="truncate text-sm leading-none font-medium">{user.name}</p>
                                        <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                    </div>
                                </div>
                                <Badge variant="outline" className="shrink-0 text-xs">
                                    {user.role}
                                </Badge>
                            </Link>
                        ))}
                    </div>
                )}
                {onlineUsers.length > 0 && (
                    <div className="mt-4 flex items-center justify-between border-t pt-3">
                        <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Users className="h-3.5 w-3.5" />
                            <span>
                                {onlineUsers.length} user{onlineUsers.length !== 1 ? "s" : ""} online
                            </span>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
