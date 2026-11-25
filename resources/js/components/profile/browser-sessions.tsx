import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { router } from "@inertiajs/react";
import { Loader2, Monitor, Smartphone } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

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
    paths: {
        browser_sessions_logout: string;
    };
}

export function BrowserSessions({ sessions, paths }: BrowserSessionsProps) {
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
