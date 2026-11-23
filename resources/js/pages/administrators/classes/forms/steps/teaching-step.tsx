import { Button } from "@/components/ui/button";
import { RefreshCw } from "lucide-react";
import type { ReactNode } from "react";
import {
    deliveryModeLabel,
    isOnlineDeliveryMode,
    type ClassFormData,
    type ClassFormOptions,
    type DeliveryMode,
} from "../../lib/class-defaults";
import { DeliveryModePicker } from "../fields/delivery-mode-picker";
import { FacultyRoomPicker } from "../fields/faculty-room-picker";
import { FormSection } from "../form-section";

type TeachingStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    onDataChange: (data: Partial<ClassFormData>) => void;
};

export function TeachingStep({ data, options, onDataChange }: TeachingStepProps): ReactNode {
    const isOnline = isOnlineDeliveryMode(data.delivery_mode);
    const mismatchedSchedulesCount = data.schedules.filter(
        (schedule) => (schedule.delivery_mode ?? "face_to_face") !== data.delivery_mode,
    ).length;

    const changeMode = (delivery_mode: DeliveryMode): void => {
        if (isOnlineDeliveryMode(delivery_mode)) {
            onDataChange({
                delivery_mode,
                room_id: null,
            });
            return;
        }

        onDataChange({ delivery_mode });
    };

    const applyModeToExistingMeetings = (): void => {
        onDataChange({
            schedules: data.schedules.map((schedule) => ({
                ...schedule,
                delivery_mode: data.delivery_mode,
                room_id: isOnline ? null : schedule.room_id,
            })),
        });
    };

    return (
        <FormSection
            title="Teaching defaults"
            description="Choose an instructor and the starting settings for new meetings. Existing meetings keep their settings unless you apply the format below."
        >
            <div className="space-y-6">
                <DeliveryModePicker value={data.delivery_mode} onChange={changeMode} />

                {mismatchedSchedulesCount > 0 ? (
                    <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed p-3.5 text-xs">
                        <p className="text-muted-foreground text-pretty">
                            {mismatchedSchedulesCount} existing {mismatchedSchedulesCount === 1 ? "meeting uses" : "meetings use"} a different format.
                            {isOnline ? " Apply this online format to release their campus rooms and room/time checks." : ""}
                        </p>
                        <Button type="button" variant="outline" size="sm" className="h-8 gap-1.5 text-xs" onClick={applyModeToExistingMeetings}>
                            <RefreshCw className="size-3.5" aria-hidden="true" />
                            Apply {deliveryModeLabel(data.delivery_mode)} to all meetings
                        </Button>
                    </div>
                ) : null}

                <FacultyRoomPicker
                    faculties={options.faculties}
                    rooms={options.rooms}
                    facultyId={data.faculty_id}
                    roomId={data.room_id}
                    isOnline={isOnline}
                    onFacultyChange={(faculty_id) => onDataChange({ faculty_id })}
                    onRoomChange={(room_id) => onDataChange({ room_id })}
                />
            </div>
        </FormSection>
    );
}
