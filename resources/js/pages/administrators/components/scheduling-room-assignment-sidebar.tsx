import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Building2 } from "lucide-react";

type RoomOption = { id: number; name: string; class_code: string | null };

type ScheduleEntry = {
    id?: number;
    day_of_week: string;
    time_range: string;
    room_id?: number | null;
};

type ClassScheduleData = {
    subject_code: string;
    section: string;
    schedules: ScheduleEntry[];
};

type SchedulingRoomAssignmentSidebarProps = {
    rooms: RoomOption[];
    localData: ClassScheduleData[];
    selectedScheduleForRoom: number | null;
    onAssignRoom: (scheduleId: number, roomId: number | null) => void;
    onClearSelection: () => void;
};

export default function SchedulingRoomAssignmentSidebar({
    rooms,
    localData,
    selectedScheduleForRoom,
    onAssignRoom,
    onClearSelection,
}: SchedulingRoomAssignmentSidebarProps) {
    return (
        <div className="w-[200px] shrink-0">
            <Card className="h-full">
                <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-1.5 text-xs">
                        <Building2 className="h-3.5 w-3.5" /> Rooms
                    </CardTitle>
                    <CardDescription className="text-[10px]">Click to assign selected schedule</CardDescription>
                </CardHeader>
                <CardContent className="pt-0">
                    <ScrollArea className="h-[580px]">
                        <div className="space-y-1.5 pr-2">
                            {rooms.map((room) => {
                                const roomSchedules = localData.flatMap((c) =>
                                    c.schedules
                                        .filter((s) => s.room_id === room.id)
                                        .map((s) => ({ ...s, subject_code: c.subject_code, section: c.section })),
                                );

                                return (
                                    <button
                                        key={room.id}
                                        type="button"
                                        onClick={() => {
                                            if (selectedScheduleForRoom) {
                                                onAssignRoom(selectedScheduleForRoom, room.id);
                                                onClearSelection();
                                            }
                                        }}
                                        className={`w-full rounded-lg border p-2 text-left transition-colors ${
                                            selectedScheduleForRoom
                                                ? "border-primary/50 bg-primary/5 hover:bg-primary/10 cursor-pointer"
                                                : "border-border bg-muted/30 cursor-default"
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="truncate text-xs font-medium">{room.name}</span>
                                            <span className="text-muted-foreground bg-background rounded px-1 text-[10px]">
                                                {roomSchedules.length}
                                            </span>
                                        </div>
                                        {roomSchedules.length > 0 && (
                                            <div className="mt-1 space-y-0.5">
                                                {roomSchedules.slice(0, 3).map((s, i) => (
                                                    <div key={`${room.id}-${i}`} className="text-muted-foreground truncate text-[9px]">
                                                        {s.subject_code} — {s.day_of_week.slice(0, 3)} {s.time_range}
                                                    </div>
                                                ))}
                                                {roomSchedules.length > 3 && (
                                                    <div className="text-muted-foreground text-[9px]">+{roomSchedules.length - 3} more</div>
                                                )}
                                            </div>
                                        )}
                                    </button>
                                );
                            })}

                            <button
                                type="button"
                                onClick={() => {
                                    if (selectedScheduleForRoom) {
                                        onAssignRoom(selectedScheduleForRoom, null);
                                        onClearSelection();
                                    }
                                }}
                                className={`w-full rounded-lg border border-dashed p-2 text-left transition-colors ${
                                    selectedScheduleForRoom
                                        ? "border-destructive/50 bg-destructive/5 hover:bg-destructive/10 cursor-pointer"
                                        : "border-border bg-muted/30 cursor-default"
                                }`}
                            >
                                <span className="text-muted-foreground text-xs">No Room</span>
                            </button>
                        </div>
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    );
}
