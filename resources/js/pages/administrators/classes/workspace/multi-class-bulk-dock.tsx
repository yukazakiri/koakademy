import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import {
    CalendarClock,
    GitCompare,
    Loader2,
    Send,
    SlidersHorizontal,
    UserCheck,
    UserPlus,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import type { ClassRow } from "../columns";
import { postBulkClassAction, WorkspaceApiError } from "./api";

export interface MultiClassBulkDockProps {
    selectedClasses: ClassRow[];
    faculties: Array<{ id: string | number; label: string }>;
    canUpdate: boolean;
    onClear: () => void;
    onCompare: (ids: [number, number]) => void;
    onCompleted: () => void;
}

type ActiveBulkDialog = "enroll_cohort" | "apply_pattern" | "assign_faculty" | "set_capacity" | null;

const PATTERN_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;

function parseIdentifiers(raw: string): string[] {
    return Array.from(
        new Set(
            raw
                .split(/[\s,;]+/)
                .map((s) => s.trim())
                .filter((s) => s.length > 0),
        ),
    );
}

export function MultiClassBulkDock({
    selectedClasses,
    faculties,
    canUpdate,
    onClear,
    onCompare,
    onCompleted,
}: MultiClassBulkDockProps) {
    const [activeDialog, setActiveDialog] = React.useState<ActiveBulkDialog>(null);
    const [submitting, setSubmitting] = React.useState(false);
    const [publishingDrafts, setPublishingDrafts] = React.useState(false);

    // Enroll Cohort dialog state
    const [cohortText, setCohortText] = React.useState("");
    const [cohortForceOverload, setCohortForceOverload] = React.useState(false);
    const [cohortOverrideReason, setCohortOverrideReason] = React.useState("");

    // Batch Pattern dialog state
    const [patternPreset, setPatternPreset] = React.useState<"mwf" | "tth" | "custom">("mwf");
    const [customDays, setCustomDays] = React.useState<string[]>(["Monday", "Wednesday", "Friday"]);
    const [patternStart, setPatternStart] = React.useState("08:00");
    const [patternEnd, setPatternEnd] = React.useState("09:00");
    const [patternComponent, setPatternComponent] = React.useState<"lecture" | "lab">("lecture");
    const [patternDelivery, setPatternDelivery] = React.useState("face_to_face");

    // Assign Faculty dialog state
    const [selectedFacultyId, setSelectedFacultyId] = React.useState<string>("");

    // Set Capacity dialog state
    const [capacityInput, setCapacityInput] = React.useState<string>("40");

    if (selectedClasses.length === 0) {
        return null;
    }

    const classIds = selectedClasses.map((c) => c.id);
    const cohortTokens = parseIdentifiers(cohortText);

    const handlePresetChange = (preset: "mwf" | "tth" | "custom") => {
        setPatternPreset(preset);
        if (preset === "mwf") {
            setCustomDays(["Monday", "Wednesday", "Friday"]);
            setPatternStart("08:00");
            setPatternEnd("09:00");
        } else if (preset === "tth") {
            setCustomDays(["Tuesday", "Thursday"]);
            setPatternStart("10:00");
            setPatternEnd("11:30");
        }
    };

    const toggleCustomDay = (day: string) => {
        setCustomDays((prev) => (prev.includes(day) ? prev.filter((d) => d !== day) : [...prev, day]));
    };

    const handlePublishDrafts = async () => {
        if (!canUpdate) return;
        setPublishingDrafts(true);
        try {
            const response = await postBulkClassAction({
                class_ids: classIds,
                action: "publish_drafts",
                notify_enrolled: true,
            });
            toast.success(response.message);
            onCompleted();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to publish draft schedules.");
        } finally {
            setPublishingDrafts(false);
        }
    };

    const handleSubmitCohort = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!canUpdate || cohortTokens.length === 0) return;
        if (cohortForceOverload && cohortOverrideReason.trim().length < 3) {
            toast.error("Provide an audit override reason when forcing overload.");
            return;
        }

        setSubmitting(true);
        try {
            const response = await postBulkClassAction({
                class_ids: classIds,
                action: "enroll_cohort",
                student_identifiers: cohortTokens,
                force_overload: cohortForceOverload,
                override_reason: cohortForceOverload ? cohortOverrideReason.trim() : null,
            });
            toast.success(response.message);
            setActiveDialog(null);
            setCohortText("");
            setCohortOverrideReason("");
            setCohortForceOverload(false);
            onCompleted();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to enroll cohort.");
        } finally {
            setSubmitting(false);
        }
    };

    const handleSubmitPattern = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!canUpdate) return;
        if (patternPreset === "custom" && customDays.length === 0) {
            toast.error("Select at least one day of the week.");
            return;
        }
        if (patternEnd <= patternStart) {
            toast.error("End time must be after start time.");
            return;
        }

        setSubmitting(true);
        try {
            const response = await postBulkClassAction({
                class_ids: classIds,
                action: "apply_pattern",
                pattern: patternPreset,
                days: patternPreset === "custom" ? customDays : undefined,
                start_time: patternStart,
                end_time: patternEnd,
                component_type: patternComponent,
                delivery_mode: patternDelivery,
            });
            toast.success(response.message);
            setActiveDialog(null);
            onCompleted();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to apply schedule pattern.");
        } finally {
            setSubmitting(false);
        }
    };

    const handleSubmitFaculty = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!canUpdate) return;
        setSubmitting(true);
        try {
            const response = await postBulkClassAction({
                class_ids: classIds,
                action: "assign_faculty",
                faculty_id: selectedFacultyId === "unassign" || !selectedFacultyId ? null : selectedFacultyId,
            });
            toast.success(response.message);
            setActiveDialog(null);
            setSelectedFacultyId("");
            onCompleted();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to assign faculty.");
        } finally {
            setSubmitting(false);
        }
    };

    const handleSubmitCapacity = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!canUpdate) return;
        const parsed = Number(capacityInput);
        if (!Number.isInteger(parsed) || parsed < 0 || parsed > 1000) {
            toast.error("Capacity must be a whole number between 0 and 1000.");
            return;
        }

        setSubmitting(true);
        try {
            const response = await postBulkClassAction({
                class_ids: classIds,
                action: "set_capacity",
                maximum_slots: parsed,
            });
            toast.success(response.message);
            setActiveDialog(null);
            onCompleted();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to update capacity.");
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            {/* Floating Sticky Charcoal Bottom Dock */}
            <div className="pointer-events-none fixed inset-x-0 bottom-5 z-40 flex justify-center px-4">
                <div
                    role="region"
                    aria-label="Bulk class actions"
                    className="pointer-events-auto flex max-w-full flex-wrap items-center gap-1.5 rounded-2xl border border-zinc-800 bg-zinc-950/95 px-3 py-2 text-zinc-50 shadow-2xl backdrop-blur-md dark:border-zinc-700 dark:bg-zinc-900/95"
                >
                    <Badge className="bg-amber-500 text-zinc-950 hover:bg-amber-500 tabular-nums">
                        {selectedClasses.length} selected
                    </Badge>

                    <div className="mx-1 hidden h-4 w-px bg-zinc-800 sm:block" aria-hidden="true" />

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        disabled={!canUpdate}
                        onClick={() => setActiveDialog("enroll_cohort")}
                        className="text-zinc-100 hover:bg-zinc-800 hover:text-white"
                    >
                        <UserPlus className="size-3.5" aria-hidden="true" />
                        Enroll Cohort
                    </Button>

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        disabled={!canUpdate}
                        onClick={() => setActiveDialog("apply_pattern")}
                        className="text-zinc-100 hover:bg-zinc-800 hover:text-white"
                    >
                        <CalendarClock className="size-3.5" aria-hidden="true" />
                        Batch Pattern
                    </Button>

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        disabled={!canUpdate || publishingDrafts}
                        onClick={() => void handlePublishDrafts()}
                        className="text-emerald-300 hover:bg-zinc-800 hover:text-emerald-200"
                    >
                        {publishingDrafts ? (
                            <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                        ) : (
                            <Send className="size-3.5" aria-hidden="true" />
                        )}
                        Publish Drafts
                    </Button>

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        disabled={!canUpdate}
                        onClick={() => setActiveDialog("assign_faculty")}
                        className="text-zinc-100 hover:bg-zinc-800 hover:text-white"
                    >
                        <UserCheck className="size-3.5" aria-hidden="true" />
                        Assign Faculty
                    </Button>

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        disabled={!canUpdate}
                        onClick={() => setActiveDialog("set_capacity")}
                        className="text-zinc-100 hover:bg-zinc-800 hover:text-white"
                    >
                        <SlidersHorizontal className="size-3.5" aria-hidden="true" />
                        Set Capacity
                    </Button>

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        disabled={selectedClasses.length !== 2}
                        onClick={() => {
                            if (selectedClasses.length === 2) {
                                onCompare([selectedClasses[0].id, selectedClasses[1].id]);
                            }
                        }}
                        className="text-zinc-100 hover:bg-zinc-800 hover:text-white disabled:text-zinc-500"
                    >
                        <GitCompare className="size-3.5" aria-hidden="true" />
                        Compare
                    </Button>

                    <div className="mx-1 hidden h-4 w-px bg-zinc-800 sm:block" aria-hidden="true" />

                    <Button
                        type="button"
                        size="xs"
                        variant="ghost"
                        onClick={onClear}
                        className="text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                    >
                        <X className="size-3.5" aria-hidden="true" />
                        Clear
                    </Button>
                </div>
            </div>

            {/* Dialog 1: Enroll Cohort */}
            <Dialog open={activeDialog === "enroll_cohort"} onOpenChange={(o) => !o && setActiveDialog(null)}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={(e) => void handleSubmitCohort(e)} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Enroll Cohort into {selectedClasses.length} Class(es)</DialogTitle>
                            <DialogDescription>
                                Paste student IDs, LRNs, or email addresses to batch-enroll across all selected sections.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="bulk-cohort-ids">Student Identifiers</Label>
                                    <span className="text-muted-foreground text-xs tabular-nums">
                                        {cohortTokens.length} unique
                                    </span>
                                </div>
                                <Textarea
                                    id="bulk-cohort-ids"
                                    value={cohortText}
                                    onChange={(e) => setCohortText(e.target.value)}
                                    placeholder="2024-00101&#10;2024-00102, 2024-00103"
                                    rows={5}
                                    className="font-mono text-xs"
                                />
                            </div>

                            <label className="flex cursor-pointer items-center gap-2 text-sm">
                                <Checkbox
                                    checked={cohortForceOverload}
                                    onCheckedChange={(v) => setCohortForceOverload(Boolean(v))}
                                />
                                <span>Force capacity overload where needed</span>
                            </label>

                            {cohortForceOverload ? (
                                <div className="space-y-1.5">
                                    <Label htmlFor="bulk-cohort-reason">
                                        Audit Override Reason <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="bulk-cohort-reason"
                                        value={cohortOverrideReason}
                                        onChange={(e) => setCohortOverrideReason(e.target.value)}
                                        placeholder="e.g., Block section enrollment approved by Registrar"
                                    />
                                </div>
                            ) : null}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActiveDialog(null)} disabled={submitting}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={submitting || cohortTokens.length === 0}>
                                {submitting ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
                                Enroll {cohortTokens.length} Student(s)
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialog 2: Batch Schedule Pattern */}
            <Dialog open={activeDialog === "apply_pattern"} onOpenChange={(o) => !o && setActiveDialog(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={(e) => void handleSubmitPattern(e)} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Apply Schedule Pattern</DialogTitle>
                            <DialogDescription>
                                Apply a draft meeting pattern to {selectedClasses.length} selected class(es).
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>Pattern Preset</Label>
                                <div className="grid grid-cols-3 gap-1.5">
                                    {(
                                        [
                                            { id: "mwf", label: "MWF" },
                                            { id: "tth", label: "TTh" },
                                            { id: "custom", label: "Custom" },
                                        ] as const
                                    ).map((p) => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => handlePresetChange(p.id)}
                                            className={cn(
                                                "rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors",
                                                patternPreset === p.id
                                                    ? "border-primary bg-primary text-primary-foreground"
                                                    : "border-border bg-background text-foreground hover:bg-muted",
                                            )}
                                        >
                                            {p.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {patternPreset === "custom" ? (
                                <div className="space-y-1.5">
                                    <Label>Days</Label>
                                    <div className="flex flex-wrap gap-1.5">
                                        {PATTERN_DAYS.map((day) => {
                                            const active = customDays.includes(day);
                                            return (
                                                <button
                                                    key={day}
                                                    type="button"
                                                    onClick={() => toggleCustomDay(day)}
                                                    className={cn(
                                                        "rounded-md border px-2 py-1 text-xs font-medium transition-colors",
                                                        active
                                                            ? "border-primary bg-primary/15 text-primary"
                                                            : "border-border text-muted-foreground hover:text-foreground",
                                                    )}
                                                >
                                                    {day.slice(0, 3)}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ) : null}

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="bulk-start-time">Start Time</Label>
                                    <Input
                                        id="bulk-start-time"
                                        type="time"
                                        value={patternStart}
                                        onChange={(e) => setPatternStart(e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="bulk-end-time">End Time</Label>
                                    <Input
                                        id="bulk-end-time"
                                        type="time"
                                        value={patternEnd}
                                        onChange={(e) => setPatternEnd(e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label>Component</Label>
                                    <Select
                                        value={patternComponent}
                                        onValueChange={(v) => setPatternComponent(v === "lab" ? "lab" : "lecture")}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="lecture">Lecture</SelectItem>
                                            <SelectItem value="lab">Laboratory</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Delivery Mode</Label>
                                    <Select value={patternDelivery} onValueChange={setPatternDelivery}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="face_to_face">Face to face</SelectItem>
                                            <SelectItem value="online_synchronous">Online (Sync)</SelectItem>
                                            <SelectItem value="online">Online (Async)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActiveDialog(null)} disabled={submitting}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
                                Apply Pattern
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialog 3: Assign Faculty */}
            <Dialog open={activeDialog === "assign_faculty"} onOpenChange={(o) => !o && setActiveDialog(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={(e) => void handleSubmitFaculty(e)} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Assign Faculty</DialogTitle>
                            <DialogDescription>
                                Assign an instructor to {selectedClasses.length} selected class(es).
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-1.5">
                            <Label>Instructor</Label>
                            <Select value={selectedFacultyId} onValueChange={setSelectedFacultyId}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Choose faculty member..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unassign">Unassign Faculty (TBA)</SelectItem>
                                    {faculties.map((f) => (
                                        <SelectItem key={f.id} value={String(f.id)}>
                                            {f.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActiveDialog(null)} disabled={submitting}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={submitting || !selectedFacultyId}>
                                {submitting ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
                                Save Faculty
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialog 4: Set Capacity */}
            <Dialog open={activeDialog === "set_capacity"} onOpenChange={(o) => !o && setActiveDialog(null)}>
                <DialogContent className="sm:max-w-sm">
                    <form onSubmit={(e) => void handleSubmitCapacity(e)} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Set Maximum Capacity</DialogTitle>
                            <DialogDescription>
                                Update seat capacity across {selectedClasses.length} selected class(es).
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-1.5">
                            <Label htmlFor="bulk-capacity-input">Maximum Slots</Label>
                            <Input
                                id="bulk-capacity-input"
                                type="number"
                                min={0}
                                max={1000}
                                value={capacityInput}
                                onChange={(e) => setCapacityInput(e.target.value)}
                                className="tabular-nums"
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActiveDialog(null)} disabled={submitting}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
                                Update Capacity
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
