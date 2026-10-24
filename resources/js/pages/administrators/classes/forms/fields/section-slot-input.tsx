import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Minus, Plus } from "lucide-react";
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
        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            <div className="space-y-2">
                <Label id="section-label">Section</Label>
                <div className="grid grid-cols-4 gap-2" role="group" aria-labelledby="section-label">
                    {sections.map((option) => (
                        <Button
                            key={option.value}
                            type="button"
                            variant="outline"
                            onClick={() => onSectionChange(option.value)}
                            aria-pressed={section === option.value}
                            className={cn(
                                "bg-card h-11 rounded-lg border text-base font-semibold",
                                section === option.value &&
                                    "border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground",
                            )}
                        >
                            {option.value}
                        </Button>
                    ))}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="maximum_slots">Maximum slots</Label>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label="Decrease capacity"
                        onClick={() => onMaximumSlotsChange(clampSlots(maximumSlots - 1))}
                    >
                        <Minus className="size-4" />
                    </Button>
                    <Input
                        id="maximum_slots"
                        type="number"
                        min={1}
                        max={200}
                        value={maximumSlots}
                        onChange={(event) => onMaximumSlotsChange(clampSlots(Number(event.target.value) || 1))}
                        className="h-11 text-center"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label="Increase capacity"
                        onClick={() => onMaximumSlotsChange(clampSlots(maximumSlots + 1))}
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="space-y-2">
                <Label>Term</Label>
                <div className="bg-muted/30 flex min-h-11 flex-wrap items-center gap-2 rounded-lg border px-3">
                    <Badge variant="secondary">{semesterLabel}</Badge>
                    <Badge variant="outline">{schoolYear}</Badge>
                </div>
            </div>
        </div>
    );
}
