import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Calendar, Minus, Plus } from "lucide-react";
import type { ReactNode } from "react";
import type { ValueOption } from "../../lib/class-defaults";

type SectionSlotInputProps = {
    sections: ValueOption[];
    semesters: ValueOption[];
    section: string;
    semester: string;
    schoolYear: string;
    maximumSlots: number;
    onSectionChange: (section: string) => void;
    onMaximumSlotsChange: (maximumSlots: number) => void;
};

function clampSlots(value: number): number {
    return Math.min(Math.max(value, 1), 200);
}

export function SectionSlotInput({
    sections,
    semesters,
    section,
    semester,
    schoolYear,
    maximumSlots,
    onSectionChange,
    onMaximumSlotsChange,
}: SectionSlotInputProps): ReactNode {
    const semesterLabel = semesters.find((option) => option.value === semester)?.label ?? semester;

    return (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {/* Section */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <Label id="section-label" className="text-foreground text-sm font-semibold">
                        Section
                    </Label>
                    <span className="text-muted-foreground text-xs">Section ID</span>
                </div>
                <div className="grid grid-cols-4 gap-2" role="group" aria-labelledby="section-label">
                    {sections.map((option) => {
                        const isSelected = section === option.value;
                        return (
                            <Button
                                key={option.value}
                                type="button"
                                variant="outline"
                                onClick={() => onSectionChange(option.value)}
                                aria-pressed={isSelected}
                                className={cn(
                                    "h-11 rounded-xl border text-base font-bold transition-all",
                                    isSelected
                                        ? "border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground ring-primary/20 shadow-xs ring-2"
                                        : "bg-card hover:bg-muted/50 hover:border-border/80",
                                )}
                            >
                                {option.value}
                            </Button>
                        );
                    })}
                </div>
                <p className="text-muted-foreground text-xs">Choose the cohort letter code.</p>
            </div>

            {/* Maximum Slots */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <Label htmlFor="maximum_slots" className="text-foreground text-sm font-semibold">
                        Maximum capacity
                    </Label>
                    <span className="text-muted-foreground text-xs">Max students</span>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11 shrink-0 rounded-xl"
                        aria-label="Decrease capacity"
                        onClick={() => onMaximumSlotsChange(clampSlots(maximumSlots - 5))}
                    >
                        <Minus className="size-4" />
                    </Button>
                    <div className="relative flex-1">
                        <Input
                            id="maximum_slots"
                            type="number"
                            min={1}
                            max={200}
                            value={maximumSlots}
                            onChange={(event) => onMaximumSlotsChange(clampSlots(Number(event.target.value) || 1))}
                            className="h-11 rounded-xl text-center text-base font-semibold tabular-nums"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11 shrink-0 rounded-xl"
                        aria-label="Increase capacity"
                        onClick={() => onMaximumSlotsChange(clampSlots(maximumSlots + 5))}
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
                <div className="text-muted-foreground flex items-center justify-between text-xs">
                    <span>1 to 200 student cap</span>
                    <button type="button" className="text-primary font-medium hover:underline" onClick={() => onMaximumSlotsChange(40)}>
                        Standard (40)
                    </button>
                </div>
            </div>

            {/* Term Period */}
            <div className="space-y-2 sm:col-span-2 lg:col-span-1">
                <div className="flex items-center justify-between">
                    <Label className="text-foreground text-sm font-semibold">Academic term</Label>
                    <span className="text-muted-foreground text-xs">Fixed for session</span>
                </div>
                <div className="bg-muted/20 flex min-h-11 items-center gap-2 rounded-xl border px-3.5 py-2">
                    <Calendar className="text-primary size-4 shrink-0" aria-hidden="true" />
                    <div className="min-w-0 flex-1">
                        <span className="text-foreground block truncate text-xs font-semibold">{schoolYear}</span>
                        <span className="text-muted-foreground block truncate text-[11px] font-medium">{semesterLabel}</span>
                    </div>
                </div>
                <p className="text-muted-foreground text-xs">Derived from active system settings.</p>
            </div>
        </div>
    );
}
