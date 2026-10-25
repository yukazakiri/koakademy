import { Button } from "@/components/ui/button";
import { Combobox } from "@/components/ui/combobox";
import { MapPin, Monitor, UserCheck, X } from "lucide-react";
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
            {/* Faculty Picker */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <span className="text-foreground flex items-center gap-1.5 text-sm font-semibold">
                        <UserCheck className="text-primary size-4" />
                        Assigned instructor
                    </span>
                    <span className="text-muted-foreground text-xs font-normal">Optional</span>
                </div>

                <div className="relative">
                    <Combobox
                        label="Faculty"
                        options={toComboboxOptions(faculties)}
                        value={facultyId ?? undefined}
                        onValueChange={(value) => onFacultyChange(value || null)}
                        placeholder="Search & assign faculty member"
                        searchPlaceholder="Search by name or email..."
                        emptyText="No faculty member found."
                    />
                </div>

                {facultyId ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground hover:text-destructive h-8 px-2 text-xs"
                        onClick={() => onFacultyChange(null)}
                    >
                        <X className="mr-1 size-3" />
                        Clear instructor assignment
                    </Button>
                ) : (
                    <p className="text-muted-foreground text-xs">Can be left unassigned and delegated later.</p>
                )}
            </div>

            {/* Room Picker */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <span className="text-foreground flex items-center gap-1.5 text-sm font-semibold">
                        {isOnline ? <Monitor className="size-4 text-indigo-500" /> : <MapPin className="text-primary size-4" />}
                        Default room
                    </span>
                    <span className="text-muted-foreground text-xs font-normal">Optional</span>
                </div>

                {isOnline ? (
                    <div className="bg-muted/30 text-muted-foreground flex min-h-11 items-center gap-2.5 rounded-xl border border-dashed px-3.5 py-2.5 text-xs">
                        <Monitor className="size-4 shrink-0 text-indigo-500" />
                        <span>Online class: Meetings run virtually without reserving physical classrooms.</span>
                    </div>
                ) : (
                    <>
                        <Combobox
                            label="Default room"
                            options={[{ value: "0", label: "Assign room later" }, ...toComboboxOptions(rooms)]}
                            value={String(roomId || 0)}
                            onValueChange={(value) => onRoomChange(Number(value) || null)}
                            placeholder="Choose the primary campus room"
                            searchPlaceholder="Search rooms or buildings..."
                            emptyText="No room found."
                        />
                        <p className="text-muted-foreground text-xs">Applied as default when adding new campus meetings.</p>
                    </>
                )}
            </div>
        </div>
    );
}
