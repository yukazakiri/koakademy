import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Monitor } from "lucide-react";
import { useState } from "react";
import { scheduleMinutes, scheduledHours } from "./scheduling-readiness";
import type { ClassScheduleData, RoomOption, ScheduleEntry } from "./scheduling-types";

const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const apiTime = (time: string) => {
    const minutes = scheduleMinutes(time);
    return `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;
};

export type SessionValues = {
    delivery_mode: "face_to_face" | "online";
    days: string[];
    start_time: string;
    end_time: string;
    room_id: number | null;
    component_type: "lecture" | "lab";
};

export default function SchedulingSessionDialog({
    classItem,
    session,
    rooms,
    onClose,
    onSave,
    onDelete,
}: {
    classItem: ClassScheduleData;
    session?: ScheduleEntry;
    rooms: RoomOption[];
    onClose: () => void;
    onSave: (values: SessionValues) => Promise<void>;
    onDelete: () => Promise<void>;
}) {
    const [selectedDays, setSelectedDays] = useState(session ? [session.day_of_week] : ["Monday"]);
    const [start, setStart] = useState(session ? apiTime(session.start_time) : "08:00");
    const [end, setEnd] = useState(session ? apiTime(session.end_time) : "09:00");
    const [room, setRoom] = useState<number | null>(session ? (session.room_id ?? null) : (classItem.room_id ?? null));
    const [component, setComponent] = useState<"lecture" | "lab">(session?.component_type === "lab" ? "lab" : "lecture");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deliveryMode, setDeliveryMode] = useState<"face_to_face" | "online">(session?.delivery_mode ?? classItem.delivery_mode ?? "face_to_face");
    const online = deliveryMode === "online";
    const valid = selectedDays.length > 0 && start && end && end > start;

    const perform = async (action: () => Promise<void>) => {
        setBusy(true);
        setError("");
        try {
            await action();
            onClose();
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : "Unable to save this session. Please try again.");
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto sm:max-w-lg"
                showCloseButton={!busy}
                onEscapeKeyDown={(event) => busy && event.preventDefault()}
                onPointerDownOutside={(event) => busy && event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>{session ? "Edit session" : "Add class times"}</DialogTitle>
                    <DialogDescription>
                        {classItem.subject_code} · Section {classItem.section}
                        <br />
                        {classItem.subject_title}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (valid && !busy)
                            void perform(() =>
                                onSave({
                                    delivery_mode: deliveryMode,
                                    days: selectedDays,
                                    start_time: start,
                                    end_time: end,
                                    room_id: online ? null : room,
                                    component_type: component,
                                }),
                            );
                    }}
                >
                    <fieldset disabled={busy} className="space-y-4">
                        <fieldset className="space-y-2">
                            <legend className="mb-2 text-sm font-medium">{session ? "Day" : "Meeting days"}</legend>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                {days.map((day) => (
                                    <Button
                                        key={day}
                                        type="button"
                                        variant={selectedDays.includes(day) ? "default" : "outline"}
                                        aria-pressed={selectedDays.includes(day)}
                                        aria-label={day}
                                        onClick={() =>
                                            setSelectedDays((previous) =>
                                                session
                                                    ? [day]
                                                    : previous.includes(day)
                                                      ? previous.filter((value) => value !== day)
                                                      : [...previous, day],
                                            )
                                        }
                                    >
                                        {day.slice(0, 3)}
                                    </Button>
                                ))}
                            </div>
                            {!session && <p className="text-muted-foreground text-xs">Choose every day this class meets at the same time.</p>}
                        </fieldset>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="session-start">Start time</Label>
                                <Input id="session-start" type="time" required value={start} onChange={(event) => setStart(event.target.value)} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="session-end">End time</Label>
                                <Input id="session-end" type="time" required value={end} onChange={(event) => setEnd(event.target.value)} />
                            </div>
                        </div>
                        {start && end && end <= start && (
                            <p role="alert" className="text-destructive text-sm">
                                End time must be after start time.
                            </p>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="session-delivery">Meeting type</Label>
                            <Select
                                value={deliveryMode}
                                onValueChange={(value) => {
                                    setDeliveryMode(value as "face_to_face" | "online");
                                    if (value === "online") setRoom(null);
                                }}
                            >
                                <SelectTrigger id="session-delivery" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="face_to_face">Face to face</SelectItem>
                                    <SelectItem value="online">Online</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-sm">Other meetings can use a different type.</p>
                        </div>
                        {online ? (
                            <p className="bg-muted/40 flex items-center gap-2 rounded-lg border p-4 text-sm">
                                <Monitor className="size-4 shrink-0" aria-hidden="true" />
                                Online meeting · No room reserved
                            </p>
                        ) : (
                            <div className="space-y-2">
                                <Label htmlFor="session-room">Room</Label>
                                <Select
                                    value={room === null ? "none" : String(room)}
                                    onValueChange={(value) => setRoom(value === "none" ? null : Number(value))}
                                >
                                    <SelectTrigger id="session-room" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Assign later</SelectItem>
                                        {rooms.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>
                                                {item.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        {((classItem.lab_hours ?? 0) > 0 || session?.component_type === "lab") && (
                            <div className="space-y-2">
                                <Label htmlFor="session-component">Session type</Label>
                                <Select value={component} onValueChange={(value) => setComponent(value as "lecture" | "lab")}>
                                    <SelectTrigger id="session-component" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="lecture">Lecture</SelectItem>
                                        <SelectItem value="lab">Laboratory</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </fieldset>
                    <p className="text-muted-foreground text-sm">
                        {Number(scheduledHours(classItem.schedules).toFixed(2))} of {classItem.required_hours ?? 3} weekly hours scheduled. Times
                        repeat each week.
                    </p>
                    {error && (
                        <p role="alert" className="text-destructive text-sm">
                            {error}
                        </p>
                    )}
                    {confirmDelete ? (
                        <div className="border-destructive/30 space-y-3 rounded-lg border p-4">
                            <p className="text-sm">Remove this {session?.day_of_week} session? Other sessions will stay scheduled.</p>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="destructive" disabled={busy} onClick={() => void perform(onDelete)}>
                                    {busy ? "Removing…" : "Remove session"}
                                </Button>
                                <Button type="button" variant="outline" disabled={busy} onClick={() => setConfirmDelete(false)}>
                                    Keep session
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <DialogFooter className="gap-2 sm:justify-between">
                            {session && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive"
                                    disabled={busy}
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    Remove session
                                </Button>
                            )}
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={busy || !valid}>
                                    {busy ? "Saving…" : session ? "Save changes" : "Add times"}
                                </Button>
                            </div>
                        </DialogFooter>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}
