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
        <fieldset className="grid gap-3.5 sm:grid-cols-2">
            <legend className="sr-only">Academic level</legend>
            {(
                [
                    {
                        value: "college",
                        title: "College",
                        subtitle: "Higher Education",
                        description: "Undergraduate programs, majors, and pooled academic subjects",
                        icon: collegeIcon,
                    },
                    {
                        value: "shs",
                        title: "Senior High School",
                        subtitle: "Basic Education (K-12)",
                        description: "Grades 11 and 12 with academic tracks, strands, and subjects",
                        icon: shsIcon,
                    },
                ] as const
            ).map((option) => {
                const isSelected = value === option.value;

                return (
                    <label
                        key={option.value}
                        className={cn(
                            "relative flex cursor-pointer items-start gap-4 rounded-xl border p-4.5 transition-all outline-none",
                            "hover:border-primary/40 hover:bg-muted/30",
                            "has-focus-visible:ring-ring/50 has-focus-visible:ring-3",
                            isSelected ? "border-primary bg-primary/[0.04] ring-primary/20 shadow-xs ring-1" : "border-border bg-card",
                        )}
                    >
                        <input
                            type="radio"
                            name="class-classification"
                            value={option.value}
                            checked={isSelected}
                            onChange={() => onChange(option.value)}
                            className="sr-only"
                        />

                        <div
                            className={cn(
                                "flex size-11 shrink-0 items-center justify-center rounded-xl border transition-colors",
                                isSelected ? "border-primary/20 bg-primary/10 text-primary" : "border-border/60 bg-muted/50 text-muted-foreground",
                            )}
                            aria-hidden="true"
                        >
                            {option.icon}
                        </div>

                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="text-foreground text-base font-semibold">{option.title}</span>
                                <span className="text-muted-foreground text-xs font-medium">({option.subtitle})</span>
                            </div>
                            <p className="text-muted-foreground text-xs leading-relaxed text-pretty">{option.description}</p>
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
        </fieldset>
    );
}
