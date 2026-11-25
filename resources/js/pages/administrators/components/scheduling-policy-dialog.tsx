import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { CalendarClock, Loader2 } from "lucide-react";
import { useEffect, useState } from "react";
import type { SchedulingPolicy } from "../classes/lib/class-defaults";

export const DEFAULT_SCHEDULING_POLICY: SchedulingPolicy = {
    default_online_delivery_mode: "online",
    check_room_conflicts: true,
    check_faculty_conflicts: true,
    check_student_cohort_conflicts: true,
    check_synchronous_online_conflicts: true,
    check_asynchronous_online_conflicts: false,
    allow_sunday_scheduling: false,
};

type SchedulingPolicyDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    policy: SchedulingPolicy;
    onSave: (policy: SchedulingPolicy) => Promise<void>;
};

export default function SchedulingPolicyDialog({ open, onOpenChange, policy, onSave }: SchedulingPolicyDialogProps) {
    const [draft, setDraft] = useState<SchedulingPolicy>(policy);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setDraft(policy);
        }
    }, [open, policy]);

    const update = <K extends keyof SchedulingPolicy>(key: K, value: SchedulingPolicy[K]) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await onSave(draft);
            onOpenChange(false);
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(nextOpen) => !saving && onOpenChange(nextOpen)}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <CalendarClock className="text-primary size-5" aria-hidden="true" />
                        Scheduling &amp; Online Delivery Rules
                    </DialogTitle>
                    <DialogDescription>
                        Control which delivery modes trigger room, faculty, and student/cohort conflict checks across Class Management and the
                        Schedule Planner.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-2">
                    <div className="space-y-2 rounded-lg border p-3.5">
                        <Label htmlFor="planner-default-online-mode" className="text-sm font-semibold">
                            Default Online Delivery Format
                        </Label>
                        <p className="text-muted-foreground text-xs">
                            Determines whether online classes default to asynchronous (self-paced) or synchronous (live).
                        </p>
                        <Select
                            value={draft.default_online_delivery_mode}
                            onValueChange={(value) => update("default_online_delivery_mode", value as "online" | "online_synchronous")}
                        >
                            <SelectTrigger id="planner-default-online-mode" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="online">Online (Asynchronous) — Self-paced</SelectItem>
                                <SelectItem value="online_synchronous">Online (Synchronous) — Live sessions</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3.5">
                        <div className="space-y-0.5">
                            <Label className="text-sm font-medium">Check Room Conflicts (Face to Face)</Label>
                            <p className="text-muted-foreground text-xs">
                                Block overlapping physical room assignments for campus sessions. Online classes never reserve rooms.
                            </p>
                        </div>
                        <Switch
                            checked={draft.check_room_conflicts}
                            onCheckedChange={(checked) => update("check_room_conflicts", checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3.5">
                        <div className="space-y-0.5">
                            <Label className="text-sm font-medium">Check Faculty Time Conflicts</Label>
                            <p className="text-muted-foreground text-xs">
                                Prevent an instructor from being double-booked across conflict-checked delivery modes.
                            </p>
                        </div>
                        <Switch
                            checked={draft.check_faculty_conflicts}
                            onCheckedChange={(checked) => update("check_faculty_conflicts", checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3.5">
                        <div className="space-y-0.5">
                            <Label className="text-sm font-medium">Check Student &amp; Cohort Conflicts</Label>
                            <p className="text-muted-foreground text-xs">
                                Prevent overlapping sessions for the same student cohort (course, year/grade, section) and enrolled students.
                            </p>
                        </div>
                        <Switch
                            checked={draft.check_student_cohort_conflicts}
                            onCheckedChange={(checked) => update("check_student_cohort_conflicts", checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3.5">
                        <div className="space-y-0.5">
                            <Label className="text-sm font-medium">Check Synchronous Online Time Conflicts</Label>
                            <p className="text-muted-foreground text-xs">
                                Enforce faculty and student/cohort time checks for live online sessions while still skipping room checks.
                            </p>
                        </div>
                        <Switch
                            checked={draft.check_synchronous_online_conflicts}
                            onCheckedChange={(checked) => update("check_synchronous_online_conflicts", checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3.5">
                        <div className="space-y-0.5">
                            <Label className="text-sm font-medium">Check Asynchronous Online Time Conflicts</Label>
                            <p className="text-muted-foreground text-xs">
                                Off by default. Enable only if self-paced asynchronous online classes should also be checked for faculty and
                                student/cohort overlaps.
                            </p>
                        </div>
                        <Switch
                            checked={draft.check_asynchronous_online_conflicts}
                            onCheckedChange={(checked) => update("check_asynchronous_online_conflicts", checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3.5">
                        <div className="space-y-0.5">
                            <Label className="text-sm font-medium">Allow Sunday Scheduling</Label>
                            <p className="text-muted-foreground text-xs">
                                Off by default (Monday–Saturday). Enable to include Sunday as a schedulable day in the Schedule Studio and session
                                forms.
                            </p>
                        </div>
                        <Switch
                            checked={draft.allow_sunday_scheduling}
                            onCheckedChange={(checked) => update("allow_sunday_scheduling", checked)}
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" disabled={saving} onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" disabled={saving} onClick={() => void handleSave()}>
                        {saving ? <Loader2 className="mr-1.5 size-4 animate-spin" /> : null}
                        Save conflict rules
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
