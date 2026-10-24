import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions, DeliveryMode } from "../../lib/class-defaults";
import { DeliveryModePicker } from "../fields/delivery-mode-picker";
import { FacultyRoomPicker } from "../fields/faculty-room-picker";
import { FormSection } from "../form-section";

type TeachingStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    onDataChange: (data: Partial<ClassFormData>) => void;
};

export function TeachingStep({ data, options, onDataChange }: TeachingStepProps): ReactNode {
    const isOnline = data.delivery_mode === "online";

    const changeMode = (delivery_mode: DeliveryMode): void => {
        if (delivery_mode === "online") {
            onDataChange({
                delivery_mode,
                room_id: null,
                schedules: data.schedules.map((schedule) => ({ ...schedule, room_id: null })),
            });
            return;
        }

        onDataChange({ delivery_mode });
    };

    return (
        <FormSection title="How and who teaches" description="Choose where the class meets and who teaches it.">
            <div className="space-y-6">
                <DeliveryModePicker value={data.delivery_mode} onChange={changeMode} />
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
