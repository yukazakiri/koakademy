import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useForm } from "@inertiajs/react";
import { format } from "date-fns";
import { useEffect, useMemo } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

interface CreateSessionDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classId: number;
    date: Date | null;
    schedules: any[];
}

export function CreateSessionDialog({ open, onOpenChange, classId, date, schedules }: CreateSessionDialogProps) {
    const dayOfWeek = date ? format(date, "EEEE") : "";

    // Filter schedules relevant to this day
    const validSchedules = useMemo(() => {
        return schedules.filter((s) => s.day_of_week.toLowerCase() === dayOfWeek.toLowerCase());
    }, [schedules, dayOfWeek]);

    const { data, setData, post, processing, reset, errors } = useForm({
        schedule_id: "",
        session_date: "",
        topic: "",
        notes: "",
        type: "regular", // 'regular' or 'makeup'
    });

    useEffect(() => {
        if (open && date) {
            setData({
                ...data,
                session_date: format(date, "yyyy-MM-dd"),
                schedule_id: validSchedules.length > 0 ? String(validSchedules[0].id) : "",
            });
        }
    }, [open, date, validSchedules]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.schedule_id) {
            toast.error("Please select a schedule.");
            return;
        }

        post(route("classes.attendance.store", { class: classId }), {
            onSuccess: () => {
                toast.success("Session created successfully.");
                onOpenChange(false);
                reset();
            },
            onError: (err) => {
                console.error(err);
                toast.error("Failed to create session.");
            },
        });
    };

    if (!date) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Create Session</DialogTitle>
                        <DialogDescription>
                            Initialize a session for {format(date, "MMMM d, yyyy")} ({dayOfWeek}).
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label>Schedule</Label>
                            {validSchedules.length > 1 ? (
                                <Select value={data.schedule_id} onValueChange={(val) => setData("schedule_id", val)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select schedule" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {validSchedules.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.start_time} - {s.end_time} ({s.room_id || "No Room"})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : validSchedules.length === 1 ? (
                                <Input disabled value={`${validSchedules[0].start_time} - ${validSchedules[0].end_time}`} />
                            ) : (
                                <p className="text-destructive text-sm">No schedule found for this day.</p>
                            )}
                            {errors.schedule_id && <p className="text-destructive text-xs">{errors.schedule_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="topic">Topic (Optional)</Label>
                            <Input
                                id="topic"
                                value={data.topic}
                                onChange={(e) => setData("topic", e.target.value)}
                                placeholder="e.g. Introduction to Physics"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes (Optional)</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) => setData("notes", e.target.value)}
                                placeholder="Any internal notes..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || validSchedules.length === 0}>
                            Create Session
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
