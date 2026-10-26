import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions } from "../../lib/class-defaults";
import { ScheduleGrid } from "../fields/schedule-grid";
import { FormSection } from "../form-section";

type ScheduleStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    onDataChange: (data: Partial<ClassFormData>) => void;
};

export function ScheduleStep({ data, options, onDataChange }: ScheduleStepProps): ReactNode {
    return (
        <FormSection title="When does it meet?" description="Add the weekly meeting times, or skip this and schedule the class later.">
            <ScheduleGrid
                schedules={data.schedules}
                rooms={options.rooms}
                dayOptions={options.day_of_week}
                primaryRoomId={data.room_id}
                defaultDeliveryMode={data.delivery_mode}
                onSchedulesChange={(schedules) => onDataChange({ schedules })}
            />
        </FormSection>
    );
}
