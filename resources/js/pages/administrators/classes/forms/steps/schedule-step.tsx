import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Info } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions, ClassSchedule } from "../../lib/class-defaults";
import { ScheduleGrid } from "../fields/schedule-grid";

type ScheduleStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    onDataChange: (data: Partial<ClassFormData>) => void;
};

export function ScheduleStep({ data, options, onDataChange }: ScheduleStepProps): ReactNode {
    const updateSchedules = (schedules: ClassSchedule[]): void => {
        onDataChange({ schedules });
    };

    return (
        <div className="space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle>Weekly schedule</CardTitle>
                    <CardDescription>When the class meets and where. Drag a block on the grid, or add one by hand.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Alert>
                        <Info className="size-4" />
                        <AlertDescription>
                            Two blocks cannot overlap on the same day, and a room cannot host two classes at once. Conflicts are marked in red
                            before you save.
                        </AlertDescription>
                    </Alert>

                    <ScheduleGrid
                        schedules={data.schedules}
                        rooms={options.rooms}
                        dayOptions={options.day_of_week}
                        primaryRoomId={data.room_id}
                        onSchedulesChange={updateSchedules}
                    />
                </CardContent>
            </Card>
        </div>
    );
}
