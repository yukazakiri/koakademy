import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Calendar, Minus, Plus } from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
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
    return Math.max(Math.trunc(value), 0);
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
    const [slotsDraft, setSlotsDraft] = useState<string>(String(maximumSlots));

    useEffect(() => {
        setSlotsDraft(String(maximumSlots));
    }, [maximumSlots]);

    const handleSlotsChange = (raw: string): void => {
        if (raw === "") {
            setSlotsDraft("");
            return;
        }
        if (!/^\d+$/.test(raw)) {
            return;
        }
        setSlotsDraft(raw);
        const parsed = Number.parseInt(raw, 10);
        if (Number.isFinite(parsed) && parsed >= 0) {
            onMaximumSlotsChange(parsed);
        }
    };

    const handleSlotsBlur = (): void => {
        const parsed = Number.parseInt(slotsDraft, 10);
        const clamped = Number.isFinite(parsed) ? clampSlots(parsed) : maximumSlots;
        setSlotsDraft(String(clamped));
        if (clamped !== maximumSlots) {
            onMaximumSlotsChange(clamped);
        }
    };

    return (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {/* Section */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <Label htmlFor="section-code-input" id="section-label" className="text-foreground text-sm font-semibold">
                        Section
                    </Label>
                    <span className="text-muted-foreground text-xs">Quick pick or custom</span>
                </div>
                <div className="flex flex-wrap gap-1.5" role="group" aria-labelledby="section-label">
                    {sections.map((option) => {
                        const isSelected = section.trim().toUpperCase() === option.value.toUpperCase();
                        return (
                            <Button
                                key={option.value}
                                type="button"
                                variant="outline"
                                onClick={() => onSectionChange(option.value)}
                                aria-pressed={isSelected}
                                className={cn(
                                    "h-9 min-w-10 rounded-lg border px-3 text-sm font-bold transition-all",
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
                <Input
                    id="section-code-input"
                    value={section}
                    maxLength={20}
                    placeholder="e.g. A, E, STEM-1"
                    onChange={(event) => onSectionChange(event.target.value)}
                    onBlur={(event) => onSectionChange(event.target.value.trim())}
                    className="h-10 rounded-xl font-mono text-sm font-semibold"
                />
                <p className="text-muted-foreground text-xs">Pick a preset or enter any section code (up to 20 chars).</p>
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
                            type="text"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            value={slotsDraft}
                            onChange={(event) => handleSlotsChange(event.target.value)}
                            onBlur={handleSlotsBlur}
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
                    <span>Existing classes: 0 means unlimited</span>
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
