import { Button } from "@/components/ui/button";
import { Combobox } from "@/components/ui/combobox";
import type { ReactNode } from "react";
import type { EntityOption } from "../../lib/class-defaults";

type FacultyRoomPickerProps = {
    faculties: EntityOption[];
    rooms: EntityOption[];
    facultyId: string | null;
    roomId: number | null;
    isOnline?: boolean;
    onFacultyChange: (facultyId: string | null) => void;
    onRoomChange: (roomId: number | null) => void;
};

function toComboboxOptions(options: EntityOption[]): { label: string; value: string }[] {
    return options.map((option) => ({ label: option.label, value: String(option.id) }));
}

export function FacultyRoomPicker({
    faculties,
    rooms,
    facultyId,
    roomId,
    isOnline = false,
    onFacultyChange,
    onRoomChange,
}: FacultyRoomPickerProps): ReactNode {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="space-y-2">
                <Combobox
                    label="Faculty (optional)"
                    options={toComboboxOptions(faculties)}
                    value={facultyId ?? undefined}
                    onValueChange={(value) => onFacultyChange(value || null)}
                    placeholder="Assign a faculty member"
                    searchPlaceholder="Search faculty"
                    emptyText="No faculty member found."
                />
                {facultyId ? (
                    <Button type="button" variant="ghost" className="h-11 px-3" onClick={() => onFacultyChange(null)}>
                        Clear faculty assignment
                    </Button>
                ) : null}
            </div>
            {isOnline ? (
                <div className="space-y-2">
                    <p className="text-sm font-medium">Room</p>
                    <p className="text-muted-foreground bg-muted/40 rounded-lg border border-dashed p-3 text-sm">Online classes do not use a room.</p>
                </div>
            ) : (
                <Combobox
                    label="Primary room (optional)"
                    options={[{ value: "0", label: "Assign a room later" }, ...toComboboxOptions(rooms)]}
                    value={String(roomId || 0)}
                    onValueChange={(value) => onRoomChange(Number(value) || null)}
                    placeholder="Choose the main room"
                    searchPlaceholder="Search rooms"
                    emptyText="No room found."
                />
            )}
        </div>
    );
}
