import { cn } from "@/lib/utils";
import { Monitor, School } from "lucide-react";
import type { ReactNode } from "react";
import type { DeliveryMode } from "../../lib/class-defaults";

type DeliveryModePickerProps = {
    value: DeliveryMode;
    onChange: (value: DeliveryMode) => void;
};

const choices: Array<{ value: DeliveryMode; title: string; hint: string; icon: ReactNode }> = [
    {
        value: "face_to_face",
        title: "Face to face",
        hint: "Students come to a room on campus.",
        icon: <School className="size-5" aria-hidden="true" />,
    },
    { value: "online", title: "Online", hint: "No room is needed. Students join remotely.", icon: <Monitor className="size-5" aria-hidden="true" /> },
];

export function DeliveryModePicker({ value, onChange }: DeliveryModePickerProps): ReactNode {
    return (
        <fieldset className="space-y-3">
            <legend className="text-sm font-medium">How is this class taught?</legend>
            <div className="grid gap-3 sm:grid-cols-2">
                {choices.map((choice) => {
                    const selected = value === choice.value;

                    return (
                        <label
                            key={choice.value}
                            className={cn(
                                "has-focus-visible:ring-ring/50 flex cursor-pointer items-start gap-3 rounded-lg border p-4 has-focus-visible:ring-3",
                                selected ? "border-primary bg-primary/5" : "hover:bg-muted/50",
                            )}
                        >
                            <input
                                type="radio"
                                name="delivery_mode"
                                value={choice.value}
                                checked={selected}
                                onChange={() => onChange(choice.value)}
                                className="sr-only"
                            />
                            <span className={cn("mt-0.5", selected ? "text-primary" : "text-muted-foreground")}>{choice.icon}</span>
                            <span className="space-y-1">
                                <span className="block text-sm font-semibold">{choice.title}</span>
                                <span className="text-muted-foreground block text-sm">{choice.hint}</span>
                            </span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
