import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { router } from "@inertiajs/react";
import { Mail, Monitor } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface BrowserSessionsProps {
    sessions: Array<{
        id: string;
        ip_address: string;
        is_current_device: boolean;
        last_active: string;
        user_agent: string;
    }>;
    paths: {
        browser_sessions_logout: string;
    };
}

export function BrowserSessions({ sessions, paths }: BrowserSessionsProps) {
    const [confirmingLogout, setConfirmingLogout] = useState(false);
    const [logoutForm, setLogoutForm] = useState({ password: "" });
    const [notifications, setNotifications] = useState({
        email_digest: true,
        security_alerts: true,
    });

    const handleLogoutOtherSessions = (e: React.FormEvent) => {
        e.preventDefault();
        router.delete(paths.browser_sessions_logout, {
            data: logoutForm,
            onSuccess: () => {
                setConfirmingLogout(false);
                setLogoutForm({ password: "" });
                toast.success("Logged out of other browser sessions.");
            },
            onError: () => {
                toast.error("Incorrect password.");
            },
        });
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Monitor className="h-5 w-5" />
                        Browser Sessions
                    </CardTitle>
                    <CardDescription>Manage and log out your active sessions</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-muted-foreground text-sm">
                        If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions
                        are listed below; however, this list may not be exhaustive.
                    </p>
                    <div className="space-y-4">
                        {sessions.map((session) => (
                            <div key={session.id} className="flex items-center gap-3">
                                <Monitor className="text-muted-foreground h-8 w-8" />
                                <div className="flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium">{session.user_agent.split("(")[0] || "Unknown Device"}</p>
                                        {session.is_current_device && (
                                            <Badge variant="secondary" className="h-5 text-[10px]">
                                                This device
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        {session.ip_address} — {session.last_active}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                    <Separator />
                    <div className="pt-2">
                        <Button variant="outline" onClick={() => setConfirmingLogout(true)}>
                            Log Out Other Browser Sessions
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Mail className="h-5 w-5" />
                        Email Preferences
                    </CardTitle>
                    <CardDescription>Manage your notifications</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between space-x-2">
                        <Label htmlFor="email-digest" className="flex flex-col space-y-1">
                            <span>Weekly Digest</span>
                            <span className="text-muted-foreground text-xs font-normal">Summary of weekly activities</span>
                        </Label>
                        <Switch
                            id="email-digest"
                            checked={notifications.email_digest}
                            onCheckedChange={(c) => setNotifications({ ...notifications, email_digest: c })}
                        />
                    </div>
                    <div className="flex items-center justify-between space-x-2">
                        <Label htmlFor="security-alerts" className="flex flex-col space-y-1">
                            <span>Security Alerts</span>
                            <span className="text-muted-foreground text-xs font-normal">Get notified about security incidents</span>
                        </Label>
                        <Switch
                            id="security-alerts"
                            checked={notifications.security_alerts}
                            onCheckedChange={(c) => setNotifications({ ...notifications, security_alerts: c })}
                            disabled
                        />
                    </div>
                </CardContent>
            </Card>

            <Dialog open={confirmingLogout} onOpenChange={setConfirmingLogout}>
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
                                    placeholder="Password"
                                    value={logoutForm.password}
                                    onChange={(e) => setLogoutForm({ password: e.target.value })}
                                    required
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setConfirmingLogout(false)}>
                                Cancel
                            </Button>
                            <Button type="submit">Log Out Other Browser Sessions</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
