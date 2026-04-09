import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Switch } from "@/components/ui/switch";
import { router } from "@inertiajs/react";
import axios from "axios";
import { Calendar, Mail, Plug, Video } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface IntegrationsTabProps {
    connectedAccounts: Record<string, boolean>;
}

export function IntegrationsTab({ connectedAccounts }: IntegrationsTabProps) {
    const [integrations, setIntegrations] = useState({
        googleCalendar: connectedAccounts["google"] || false,
        microsoftOutlook: connectedAccounts["microsoft"] || false,
        zoom: connectedAccounts["zoom"] || false,
    });
    const [confirmingUnsync, setConfirmingUnsync] = useState(false);

    const handleGoogleCalendarToggle = (checked: boolean) => {
        if (checked) {
            window.location.href = "/integrations/google/connect";
        } else {
            if (confirm("Are you sure you want to disconnect Google Calendar?")) {
                router.post(
                    "/integrations/google/disconnect",
                    {},
                    {
                        onSuccess: () => setIntegrations({ ...integrations, googleCalendar: false }),
                    },
                );
            }
        }
    };

    const handleSyncGoogleCalendar = () => {
        const toastId = toast.loading("Syncing schedule...");
        axios
            .post("/integrations/google/sync")
            .then((response) => {
                toast.dismiss(toastId);
                toast.success(response.data.message);
            })
            .catch((error) => {
                toast.dismiss(toastId);
                toast.error("Failed to sync calendar.");
                console.error(error);
            });
    };

    const handleUnsyncGoogleCalendar = () => {
        setConfirmingUnsync(true);
    };

    const executeUnsyncGoogleCalendar = () => {
        const toastId = toast.loading("Unsyncing schedule...");
        axios
            .post("/integrations/google/unsync")
            .then((response) => {
                toast.dismiss(toastId);
                toast.success(response.data.message);
                setConfirmingUnsync(false);
            })
            .catch((error) => {
                toast.dismiss(toastId);
                toast.error("Failed to unsync calendar.");
                console.error(error);
                setConfirmingUnsync(false);
            });
    };

    return (
        <div className="grid gap-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Plug className="h-5 w-5" />
                        Connected Apps
                    </CardTitle>
                    <CardDescription>Manage third-party integrations</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6">
                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <Calendar className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="font-medium">Google Calendar</p>
                                <p className="text-muted-foreground text-sm">Sync your schedule and meetings</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {integrations.googleCalendar && (
                                <>
                                    <Button variant="outline" size="sm" onClick={handleSyncGoogleCalendar}>
                                        Sync Now
                                    </Button>
                                    <Button variant="outline" size="sm" onClick={handleUnsyncGoogleCalendar}>
                                        Unsync Events
                                    </Button>
                                </>
                            )}
                            <Switch checked={integrations.googleCalendar} onCheckedChange={handleGoogleCalendarToggle} />
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <Mail className="h-5 w-5 text-blue-800 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="font-medium">Microsoft Outlook</p>
                                <p className="text-muted-foreground text-sm">Sync emails and calendar events</p>
                            </div>
                        </div>
                        <Switch
                            checked={integrations.microsoftOutlook}
                            onCheckedChange={(c) => setIntegrations({ ...integrations, microsoftOutlook: c })}
                        />
                    </div>

                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <Video className="h-5 w-5 text-blue-500" />
                            </div>
                            <div>
                                <p className="font-medium">Zoom</p>
                                <p className="text-muted-foreground text-sm">Auto-generate meeting links</p>
                            </div>
                        </div>
                        <Switch checked={integrations.zoom} onCheckedChange={(c) => setIntegrations({ ...integrations, zoom: c })} />
                    </div>
                </CardContent>
            </Card>

            <Dialog open={confirmingUnsync} onOpenChange={setConfirmingUnsync}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Unsync Google Calendar Events</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove all synced events from your Google Calendar? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setConfirmingUnsync(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={executeUnsyncGoogleCalendar}>
                            Remove Events
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
