import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { router } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, History, Loader2, Monitor, Smartphone } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export interface SecurityActivityItem {
    id: number;
    event: string;
    label: string;
    outcome: string;
    browser: string;
    platform: string;
    occurred_at: string;
    occurred_at_iso: string | null;
}

interface BrowserSessionsProps {
    sessions: Array<{
        id: string;
        ip_address: string;
        is_current_device: boolean;
        last_active: string;
        user_agent: string;
        browser?: string;
        platform?: string;
        is_desktop?: boolean;
    }>;
    securityActivity?: SecurityActivityItem[];
    paths: {
        browser_sessions_logout: string;
    };
}

export function BrowserSessions({ sessions, securityActivity = [], paths }: BrowserSessionsProps) {
    const [confirmingLogout, setConfirmingLogout] = useState(false);
    const [logoutForm, setLogoutForm] = useState({ password: "" });
    const [logoutError, setLogoutError] = useState<string | null>(null);
    const [loggingOut, setLoggingOut] = useState(false);

    const handleLogoutOtherSessions = (e: React.FormEvent) => {
        e.preventDefault();
        setLoggingOut(true);
        setLogoutError(null);
        router.delete(paths.browser_sessions_logout, {
            data: logoutForm,
            onSuccess: () => {
                setConfirmingLogout(false);
                setLogoutForm({ password: "" });
                setLogoutError(null);
                toast.success("Logged out of other browser sessions.");
            },
            onError: (errors) => {
                const msg = errors.password ?? "Incorrect password.";
                setLogoutError(msg);
                toast.error(msg);
            },
            onFinish: () => setLoggingOut(false),
        });
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Monitor className="size-5" />
                        Browser Sessions
                    </CardTitle>
                    <CardDescription>Manage and log out your active sessions</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-muted-foreground text-sm">
                        If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions
                        are listed below; however, this list may not be exhaustive.
                    </p>
                    {sessions.length > 0 ? (
                        <div className="space-y-3">
                            {sessions.map((session) => {
                                const deviceLabel =
                                    session.browser && session.platform
                                        ? `${session.browser} on ${session.platform}`
                                        : session.user_agent || "Unknown Device";
                                const isDesktop = session.is_desktop ?? true;

                                return (
                                    <div key={session.id} className="flex items-center gap-3 rounded-lg border p-3">
                                        {isDesktop ? (
                                            <Monitor className="text-muted-foreground size-6 shrink-0" />
                                        ) : (
                                            <Smartphone className="text-muted-foreground size-6 shrink-0" />
                                        )}
                                        <div className="min-w-0 flex-1 space-y-0.5">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate text-sm font-medium">{deviceLabel}</p>
                                                {session.is_current_device && (
                                                    <Badge variant="secondary" className="h-5 shrink-0 text-[10px]">
                                                        This device
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                {session.ip_address} • Active {session.last_active}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">No active database sessions available to display.</p>
                    )}
                    <Separator />
                    <div className="pt-2">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setLogoutError(null);
                                setLogoutForm({ password: "" });
                                setConfirmingLogout(true);
                            }}
                        >
                            Log Out Other Browser Sessions
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <History className="size-5" />
                        Recent Security Activity
                    </CardTitle>
                    <CardDescription>Recent sign-ins, verification changes, and account security events</CardDescription>
                </CardHeader>
                <CardContent>
                    {securityActivity.length > 0 ? (
                        <div className="divide-border divide-y rounded-lg border">
                            {securityActivity.map((item) => {
                                const isFailure = item.outcome !== "success";
                                const deviceMeta =
                                    item.browser && item.platform && item.browser !== "Unknown"
                                        ? `${item.browser} on ${item.platform}`
                                        : item.platform && item.platform !== "Unknown"
                                          ? item.platform
                                          : null;

                                return (
                                    <div key={item.id} className="flex items-start justify-between gap-3 px-3.5 py-3">
                                        <div className="flex min-w-0 items-start gap-2.5">
                                            {isFailure ? (
                                                <AlertTriangle className="text-destructive mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                            ) : (
                                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                            )}
                                            <div className="min-w-0 space-y-0.5">
                                                <p className="text-sm leading-snug font-medium">{item.label}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {deviceMeta ? `${deviceMeta} • ` : ""}
                                                    <time dateTime={item.occurred_at_iso ?? undefined} title={item.occurred_at_iso ?? undefined}>
                                                        {item.occurred_at}
                                                    </time>
                                                </p>
                                            </div>
                                        </div>
                                        {isFailure && (
                                            <Badge variant="destructive" className="h-5 shrink-0 text-[10px] capitalize">
                                                {item.outcome}
                                            </Badge>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">No recent security events recorded yet.</p>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={confirmingLogout}
                onOpenChange={(open) => {
                    setConfirmingLogout(open);
                    if (!open) {
                        setLogoutForm({ password: "" });
                        setLogoutError(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Log Out Other Browser Sessions</DialogTitle>
                        <DialogDescription>
                            Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleLogoutOtherSessions}>
                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Input
                                    type="password"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                    value={logoutForm.password}
                                    onChange={(e) => {
                                        setLogoutForm({ password: e.target.value });
                                        if (logoutError) setLogoutError(null);
                                    }}
                                    required
                                    autoFocus
                                />
                                {logoutError && <p className="text-destructive text-xs">{logoutError}</p>}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setConfirmingLogout(false)} disabled={loggingOut}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={loggingOut || !logoutForm.password}>
                                {loggingOut ? (
                                    <>
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                        Signing out...
                                    </>
                                ) : (
                                    "Log Out Other Browser Sessions"
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
