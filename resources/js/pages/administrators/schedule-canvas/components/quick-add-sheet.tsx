import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from "@/components/ui/sheet";
import { Check, Loader2, Search } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { isOnlineDeliveryMode, type DeliveryMode } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, RoomOption } from "../../components/scheduling-types";

export type QuickAddTarget = {
    day?: string;
    startTime?: string;
    endTime?: string;
    roomId?: number | null;
    classId?: number;
};

interface QuickAddSheetProps {
    target: QuickAddTarget | null;
    classes: ClassScheduleData[];
    rooms: RoomOption[];
    days: string[];
    onClose: () => void;
    onCreate: (
        classItem: ClassScheduleData,
        payload: {
            days: string[];
            start_time: string;
            end_time: string;
            room_id: number | null;
            delivery_mode: DeliveryMode;
        },
    ) => Promise<void>;
}

export default function QuickAddSheet({
    target,
    classes,
    rooms,
    days,
    onClose,
    onCreate,
}: QuickAddSheetProps) {
    const [search, setSearch] = useState("");
    const [selectedClassId, setSelectedClassId] = useState<number | null>(null);
    const [selectedDays, setSelectedDays] = useState<string[]>(["Monday"]);
    const [startTime, setStartTime] = useState("08:00");
    const [endTime, setEndTime] = useState("09:30");
    const [deliveryMode, setDeliveryMode] = useState<DeliveryMode>("face_to_face");
    const [roomId, setRoomId] = useState<string>("none");
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!target) return;
        setSearch("");
        setSelectedClassId(target.classId ?? classes[0]?.id ?? null);
        setSelectedDays([target.day && days.includes(target.day) ? target.day : (days[0] ?? "Monday")]);
        setStartTime(target.startTime ?? "08:00");
        setEndTime(target.endTime ?? "09:30");
        setDeliveryMode("face_to_face");
        setRoomId(target.roomId != null ? String(target.roomId) : "none");
    }, [target, classes, days]);

    const filteredClasses = useMemo(() => {
        const q = search.trim().toLowerCase();
        return classes
            .filter((cls) => {
                if (!q) return true;
                return (
                    cls.subject_code.toLowerCase().includes(q) ||
                    cls.subject_title.toLowerCase().includes(q) ||
                    (cls.faculty_name ?? "").toLowerCase().includes(q) ||
                    (cls.section ?? "").toLowerCase().includes(q)
                );
            })
            .sort(
                (a, b) =>
                    Number(Boolean(b.is_unscheduled)) - Number(Boolean(a.is_unscheduled)) ||
                    a.subject_code.localeCompare(b.subject_code),
            )
            .slice(0, 24);
    }, [classes, search]);

    const selectedClass = useMemo(
        () => classes.find((c) => c.id === selectedClassId) ?? null,
        [classes, selectedClassId],
    );

    const toggleDay = (day: string) => {
        setSelectedDays((prev) =>
            prev.includes(day) ? (prev.length > 1 ? prev.filter((d) => d !== day) : prev) : [...prev, day],
        );
    };

    const isOnline = isOnlineDeliveryMode(deliveryMode);
    const valid = Boolean(selectedClass && selectedDays.length > 0 && startTime && endTime && endTime > startTime);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedClass || !valid || saving) return;
        setSaving(true);
        try {
            await onCreate(selectedClass, {
                days: selectedDays,
                start_time: startTime,
                end_time: endTime,
                room_id: isOnline || roomId === "none" ? null : Number(roomId),
                delivery_mode: deliveryMode,
            });
            onClose();
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={Boolean(target)} onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
                <SheetHeader className="border-border/60 border-b px-5 py-4 pr-11 text-left">
                    <SheetTitle className="text-base font-semibold">Schedule Class Session</SheetTitle>
                    <SheetDescription className="text-xs">
                        Pick a class and set its weekly day, time, and room right here.
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
                    <div className="flex-1 space-y-4 overflow-y-auto p-5">
                        {/* Class Picker */}
                        <div className="space-y-2">
                            <Label className="text-xs font-medium">Class</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search subject code, title, or instructor…"
                                    className="h-8 rounded-xl pl-8 text-xs"
                                />
                            </div>
                            <div className="border-border/60 max-h-40 space-y-1 overflow-y-auto rounded-xl border p-1.5">
                                {filteredClasses.map((cls) => {
                                    const active = cls.id === selectedClassId;
                                    return (
                                        <button
                                            key={cls.id}
                                            type="button"
                                            onClick={() => {
                                                setSelectedClassId(cls.id);
                                                if (roomId === "none" && cls.room_id != null) {
                                                    setRoomId(String(cls.room_id));
                                                }
                                            }}
                                            className={`flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs transition-colors ${
                                                active
                                                    ? "bg-foreground text-background"
                                                    : "hover:bg-muted/60 text-foreground"
                                            }`}
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold">
                                                    {cls.subject_code} · Sec {cls.section}
                                                </p>
                                                <p
                                                    className={`truncate text-[11px] ${
                                                        active ? "text-background/75" : "text-muted-foreground"
                                                    }`}
                                                >
                                                    {cls.subject_title}
                                                </p>
                                            </div>
                                            {cls.is_unscheduled && (
                                                <Badge
                                                    variant={active ? "outline" : "secondary"}
                                                    className={`shrink-0 text-[10px] ${
                                                        active ? "border-background/40 text-background" : ""
                                                    }`}
                                                >
                                                    Needs time
                                                </Badge>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Day(s) */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-medium">Day(s)</Label>
                            <div className="flex flex-wrap gap-1.5">
                                {days.map((day) => {
                                    const active = selectedDays.includes(day);
                                    return (
                                        <button
                                            key={day}
                                            type="button"
                                            onClick={() => toggleDay(day)}
                                            className={`rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                active
                                                    ? "bg-foreground text-background border-foreground"
                                                    : "bg-background hover:bg-muted border-border/70 text-foreground"
                                            }`}
                                        >
                                            {day.slice(0, 3)}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Start / End Time */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="quick-add-start" className="text-xs font-medium">
                                    Start time
                                </Label>
                                <Input
                                    id="quick-add-start"
                                    type="time"
                                    value={startTime}
                                    onChange={(e) => setStartTime(e.target.value)}
                                    className="h-9 rounded-xl text-xs tabular-nums"
                                    required
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="quick-add-end" className="text-xs font-medium">
                                    End time
                                </Label>
                                <Input
                                    id="quick-add-end"
                                    type="time"
                                    value={endTime}
                                    onChange={(e) => setEndTime(e.target.value)}
                                    className="h-9 rounded-xl text-xs tabular-nums"
                                    required
                                />
                            </div>
                        </div>

                        {/* Delivery Mode */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-medium">Delivery mode</Label>
                            <Select value={deliveryMode} onValueChange={(v) => setDeliveryMode(v as DeliveryMode)}>
                                <SelectTrigger className="h-9 rounded-xl text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="face_to_face">Face-to-Face (On-Campus)</SelectItem>
                                    <SelectItem value="online_synchronous">Online (Synchronous)</SelectItem>
                                    <SelectItem value="online_asynchronous">Online (Asynchronous)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Room */}
                        {!isOnline && (
                            <div className="space-y-1.5">
                                <Label className="text-xs font-medium">Room</Label>
                                <Select value={roomId} onValueChange={setRoomId}>
                                    <SelectTrigger className="h-9 rounded-xl text-xs">
                                        <SelectValue placeholder="Choose a room" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem value="none">No room assigned</SelectItem>
                                        {rooms.map((room) => (
                                            <SelectItem key={room.id} value={String(room.id)}>
                                                {room.name} ({room.capacity ?? 40} seats)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    <SheetFooter className="border-border/60 flex-row items-center justify-between border-t px-5 py-3.5">
                        <Button type="button" variant="ghost" size="sm" className="h-8 rounded-lg text-xs" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" size="sm" disabled={!valid || saving} className="h-8 gap-1.5 rounded-lg text-xs">
                            {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
                            Add session
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
