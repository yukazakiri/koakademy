import { cn } from "@/lib/utils";
import { Check } from "lucide-react";
import type { ReactNode } from "react";
import type { Classification } from "../../lib/class-defaults";

type ClassificationPickerProps = {
    value: Classification;
    onChange: (value: Classification) => void;
    collegeIcon: ReactNode;
    shsIcon: ReactNode;
};

export function ClassificationPicker({ value, onChange, collegeIcon, shsIcon }: ClassificationPickerProps): ReactNode {
    return (
        <fieldset className="grid gap-4 sm:grid-cols-2">
            <legend className="sr-only">Academic level</legend>
            {(
                [
                    { value: "college", title: "College", description: "Undergraduate programs and subjects", icon: collegeIcon },
                    { value: "shs", title: "Senior high school", description: "Grades 11 and 12, tracks and strands", icon: shsIcon },
                ] as const
            ).map((option) => (
                <label
                    key={option.value}
                    className={cn(
                        "has-focus-visible:ring-ring/50 relative flex cursor-pointer items-start gap-3 rounded-lg border p-4 has-focus-visible:ring-3",
                        value === option.value ? "border-primary bg-primary/5" : "bg-background hover:bg-muted/50",
                    )}
                >
                    <input
                        type="radio"
                        name="class-classification"
                        value={option.value}
                        checked={value === option.value}
                        onChange={() => onChange(option.value)}
                        className="sr-only"
                    />
                    <span className="text-muted-foreground mt-1" aria-hidden="true">
                        {option.icon}
                    </span>
                    <span className="min-w-0 flex-1 space-y-1">
                        <span className="block text-base font-medium">{option.title}</span>
                        <span className="text-muted-foreground block text-sm">{option.description}</span>
                    </span>
                    {value === option.value ? <Check className="text-primary mt-1 size-4 shrink-0" aria-hidden="true" /> : null}
                </label>
            ))}
        </fieldset>
    );
}
