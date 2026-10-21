import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Info } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions } from "../../lib/class-defaults";
import { FacultyRoomPicker } from "../fields/faculty-room-picker";

type TeachingStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    onDataChange: (data: Partial<ClassFormData>) => void;
};

export function TeachingStep({ data, options, onDataChange }: TeachingStepProps): ReactNode {
    const updateRoom = (room_id: number): void => {
        onDataChange({
            room_id,
            schedules: data.schedules.map((schedule, index) => (index === 0 ? { ...schedule, room_id } : schedule)),
        });
    };

    return (
        <div className="space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle>Teaching assignment</CardTitle>
                    <CardDescription>Who teaches the class and where students should go first.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <FacultyRoomPicker
                        faculties={options.faculties}
                        rooms={options.rooms}
                        facultyId={data.faculty_id}
                        roomId={data.room_id}
                        onFacultyChange={(faculty_id) => onDataChange({ faculty_id })}
                        onRoomChange={updateRoom}
                    />

                    <p className="text-muted-foreground flex items-start gap-2 text-xs">
                        <Info className="mt-0.5 size-3.5 shrink-0" />
                        The primary room is applied to the first schedule block. Every block can still be given its own room on the schedule
                        step.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
