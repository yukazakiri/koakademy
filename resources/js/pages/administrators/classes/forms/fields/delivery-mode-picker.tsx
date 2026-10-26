import { cn } from "@/lib/utils";
import { Check, Monitor, School } from "lucide-react";
import type { ReactNode } from "react";
import type { DeliveryMode } from "../../lib/class-defaults";

type DeliveryModePickerProps = {
    value: DeliveryMode;
    onChange: (value: DeliveryMode) => void;
};

const choices: Array<{
    value: DeliveryMode;
    title: string;
    badge: string;
    hint: string;
    icon: ReactNode;
}> = [
    {
        value: "face_to_face",
        title: "Face to face",
        badge: "Campus",
        hint: "Sessions meet in a physical campus classroom or lab.",
        icon: <School className="size-5" aria-hidden="true" />,
    },
    {
        value: "online",
        title: "Online",
        badge: "Remote",
        hint: "Sessions meet via video link. No campus room is reserved.",
        icon: <Monitor className="size-5" aria-hidden="true" />,
    },
];

export function DeliveryModePicker({ value, onChange }: DeliveryModePickerProps): ReactNode {
    return (
        <fieldset className="space-y-3">
            <div className="space-y-1">
                <legend className="text-foreground text-sm font-semibold">Default meeting format</legend>
                <p className="text-muted-foreground text-xs text-pretty">
                    Sets the default delivery type for this class and new schedule slots. Individual sessions can be adjusted.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                {choices.map((choice) => {
                    const isSelected = value === choice.value;

                    return (
                        <label
                            key={choice.value}
                            className={cn(
                                "relative flex cursor-pointer items-start gap-3.5 rounded-xl border p-4 transition-all outline-none",
                                "hover:border-primary/40 hover:bg-muted/30",
                                "has-focus-visible:ring-ring/50 has-focus-visible:ring-3",
                                isSelected ? "border-primary bg-primary/[0.04] ring-primary/20 shadow-xs ring-1" : "border-border bg-card",
                            )}
                        >
                            <input
                                type="radio"
                                name="delivery_mode"
                                value={choice.value}
                                checked={isSelected}
                                onChange={() => onChange(choice.value)}
                                className="sr-only"
                            />

                            <div
                                className={cn(
                                    "flex size-10 shrink-0 items-center justify-center rounded-lg border transition-colors",
                                    isSelected
                                        ? "border-primary/20 bg-primary/10 text-primary"
                                        : "border-border/60 bg-muted/50 text-muted-foreground",
                                )}
                                aria-hidden="true"
                            >
                                {choice.icon}
                            </div>

                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex items-center gap-2">
                                    <span className="text-foreground text-sm font-semibold">{choice.title}</span>
                                    <span className="text-muted-foreground font-mono text-xs font-medium">({choice.badge})</span>
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed text-pretty">{choice.hint}</p>
                            </div>

                            {isSelected ? (
                                <div className="bg-primary text-primary-foreground flex size-5 shrink-0 items-center justify-center rounded-full shadow-2xs">
                                    <Check className="size-3 stroke-[2.5]" aria-hidden="true" />
                                </div>
                            ) : (
                                <div className="border-border/80 bg-background size-5 shrink-0 rounded-full border" aria-hidden="true" />
                            )}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
