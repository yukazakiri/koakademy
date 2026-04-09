import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import { IconCalendarPlus } from "@tabler/icons-react";
import { FormEvent, useEffect, useState } from "react";
import { toast } from "sonner";

interface ScheduleOption {
    id: string;
    label: string;
    day: string;
    room?: string | null;
}

interface CreateSessionDialogProps {
    classId: number;
    scheduleOptions: ScheduleOption[];
    defaultDate: string;
    classStartDate?: Date | null;
    /** Controlled mode: external open state */
    externalOpen?: boolean;
    /** Controlled mode: callback when open state changes */
    onOpenChange?: (open: boolean) => void;
    /** Pre-filled date for calendar click */
    prefilledDate?: string | null;
}

export function CreateSessionDialog({
    classId,
    scheduleOptions,
    defaultDate,
    classStartDate,
    externalOpen,
    onOpenChange,
    prefilledDate,
}: CreateSessionDialogProps) {
    // Support both controlled and uncontrolled modes
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = externalOpen !== undefined;
    const isOpen = isControlled ? externalOpen : internalOpen;
    const setIsOpen = isControlled ? (onOpenChange ?? (() => {})) : setInternalOpen;

    // Use prefilledDate if provided, otherwise use defaultDate
    const effectiveDate = prefilledDate ?? defaultDate;

    const form = useForm({
        session_date: effectiveDate,
        schedule_id: scheduleOptions[0]?.id ? String(scheduleOptions[0].id) : "",
        topic: "",
        notes: "",
        default_status: "present" as "present" | "late" | "absent" | "excused",
        mark_all: true,
        is_no_meeting: false,
        no_meeting_reason: "",
    });

    // Update form when prefilledDate changes (for calendar clicks)
    useEffect(() => {
        if (prefilledDate) {
            form.setData("session_date", prefilledDate);
        }
    }, [prefilledDate]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/faculty/classes/${classId}/attendance/sessions`, {
            onSuccess: () => {
                setIsOpen(false);
                form.reset();
                toast.success("Session created successfully");
            },
            onError: () => {
                toast.error("Failed to create session");
            },
        });
    };

    // Render trigger button only in uncontrolled mode
    const triggerButton = !isControlled ? (
        <DialogTrigger asChild>
            <Button size="sm" className="rounded-full">
                <IconCalendarPlus className="mr-2 size-4" />
                New Session
            </Button>
        </DialogTrigger>
    ) : null;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            {triggerButton}
            <DialogContent className="bg-background text-foreground sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Create Attendance Session</DialogTitle>
                    <DialogDescription>Start a new roll call or log a past session.</DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-4">
                        <div className="space-y-2">
                            <Label>Schedule Block</Label>
                            <Select value={form.data.schedule_id} onValueChange={(value) => form.setData("schedule_id", value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select schedule" />
                                </SelectTrigger>
                                <SelectContent>
                                    {scheduleOptions.map((option) => (
                                        <SelectItem key={option.id} value={option.id}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.schedule_id && <p className="text-destructive text-sm">{form.errors.schedule_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Session Date</Label>
                            <Input
                                type="date"
                                min={classStartDate ? classStartDate.toISOString().split("T")[0] : undefined}
                                value={form.data.session_date}
                                onChange={(e) => form.setData("session_date", e.target.value)}
                            />
                            {form.errors.session_date && <p className="text-destructive text-sm">{form.errors.session_date}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Topic (Optional)</Label>
                            <Input
                                placeholder="e.g. Chapter 1 Quiz"
                                value={form.data.topic}
                                onChange={(e) => form.setData("topic", e.target.value)}
                            />
                            {form.errors.topic && <p className="text-destructive text-sm">{form.errors.topic}</p>}
                        </div>

                        {!form.data.is_no_meeting && (
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div className="space-y-0.5">
                                    <Label className="text-base">Auto-mark Present</Label>
                                    <p className="text-muted-foreground text-xs">Mark all students as present by default</p>
                                </div>
                                <Switch checked={form.data.mark_all} onCheckedChange={(checked) => form.setData("mark_all", checked)} />
                            </div>
                        )}

                        <div className="bg-muted/20 flex items-center justify-between rounded-lg border p-3">
                            <div className="space-y-0.5">
                                <Label className="text-base">No Meeting</Label>
                                <p className="text-muted-foreground text-xs">Mark as holiday or cancelled</p>
                            </div>
                            <Switch checked={form.data.is_no_meeting} onCheckedChange={(checked) => form.setData("is_no_meeting", checked)} />
                        </div>

                        {form.data.is_no_meeting && (
                            <div className="space-y-2">
                                <Label>Reason</Label>
                                <Input
                                    placeholder="e.g. Public Holiday"
                                    value={form.data.no_meeting_reason}
                                    onChange={(e) => form.setData("no_meeting_reason", e.target.value)}
                                />
                                {form.errors.no_meeting_reason && <p className="text-destructive text-sm">{form.errors.no_meeting_reason}</p>}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? "Creating..." : "Create Session"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
