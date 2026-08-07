import { ClassScheduleVisualizer } from "@/components/administrators/classes/schedule-visualizer";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { CalendarPlus, DoorOpen } from "lucide-react";
import type { ReactNode } from "react";
import { createDefaultSchedule, type ClassSchedule, type EntityOption, type ValueOption } from "../../lib/class-defaults";

type ScheduleGridProps = {
    schedules: ClassSchedule[];
    rooms: EntityOption[];
    dayOptions: ValueOption[];
    primaryRoomId: number;
    onSchedulesChange: (schedules: ClassSchedule[]) => void;
};

export function ScheduleGrid({ schedules, rooms, dayOptions, primaryRoomId, onSchedulesChange }: ScheduleGridProps): ReactNode {
    const activeDays = new Set(schedules.map((schedule) => schedule.day_of_week).filter(Boolean)).size;

    const addBlock = (): void => {
        onSchedulesChange([...schedules, createDefaultSchedule(primaryRoomId)]);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 rounded-xl border bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{schedules.length} blocks</Badge>
                    <Badge variant="outline">{activeDays} active days</Badge>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {schedules.length > 1 ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onSchedulesChange(schedules.map((schedule) => ({ ...schedule, room_id: primaryRoomId })))}
                        >
                            <DoorOpen className="size-4" />
                            Apply primary room to all
                        </Button>
                    ) : null}
                    <Button type="button" onClick={addBlock}>
                        <CalendarPlus className="size-4" />
                        Add block
                    </Button>
                </div>
            </div>

            <ClassScheduleVisualizer
                schedules={schedules}
                rooms={rooms}
                dayOptions={dayOptions}
                onScheduleChange={(index, schedule) => onSchedulesChange(schedules.map((item, itemIndex) => (itemIndex === index ? schedule : item)))}
                onDuplicateSchedule={(index) => {
                    const schedule = schedules[index];
                    if (schedule) {
                        onSchedulesChange([...schedules, { ...schedule }]);
                    }
                }}
                onRemoveSchedule={(index) => onSchedulesChange(schedules.filter((_, itemIndex) => itemIndex !== index))}
                canRemoveSchedule={schedules.length > 1}
            />
        </div>
    );
}
