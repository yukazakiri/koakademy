import { store } from "@/actions/App/Http/Controllers/AdministratorEnrollmentPolicyController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import { useForm } from "@inertiajs/react";
import { ArrowRight, Check, Layers3, Plus, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import type { Option, Preset } from "../types";
import { OptionSelect } from "./schema-fields";

export function CreatePolicyDialog({
    presets,
    options,
    hasPublishedGlobalPolicy,
    canUpdate,
}: {
    presets: Record<string, Preset>;
    options: Record<string, Option[]>;
    hasPublishedGlobalPolicy: boolean;
    canUpdate: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: "",
        preset: Object.keys(presets)[0] ?? "legacy",
        inherit: true,
        school_id: "",
        student_type: "",
        course_id: "",
        school_year: "",
        semester: "",
        change_notes: "Initial enrollment blueprint",
    });
    const hasScope = Boolean(form.data.school_id || form.data.student_type || form.data.course_id || form.data.school_year || form.data.semester);
    const canInherit = !hasScope || hasPublishedGlobalPolicy;
    const scopeSentence = useMemo(() => {
        const labels = [
            optionLabel(options.schools, form.data.school_id),
            optionLabel(options.student_types, form.data.student_type),
            optionLabel(options.programs, form.data.course_id),
            form.data.school_year ? `school year ${form.data.school_year}` : "",
            form.data.semester ? `semester ${form.data.semester}` : "",
        ].filter(Boolean);

        return labels.length
            ? `This blueprint applies to ${labels.join(", ")}.`
            : "This is the global blueprint used as the school-wide starting point.";
    }, [form.data, options]);

    const submit = () => {
        form.transform((data) => ({
            ...data,
            inherit: hasScope ? data.inherit : false,
            school_id: data.school_id || null,
            student_type: data.student_type || null,
            course_id: data.course_id || null,
            school_year: data.school_year || null,
            semester: data.semester || null,
        }));
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="h-11" disabled={!canUpdate}>
                    <Plus className="size-4" /> New blueprint
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto p-0 sm:max-w-3xl">
                <DialogHeader className="bg-muted/30 border-b px-6 py-6 text-left">
                    <div className="bg-primary text-primary-foreground mb-1 flex size-10 items-center justify-center rounded-xl">
                        <Layers3 className="size-5" />
                    </div>
                    <DialogTitle className="text-xl">Create an enrollment blueprint</DialogTitle>
                    <DialogDescription>Start from a working template, then choose exactly which students it covers.</DialogDescription>
                </DialogHeader>

                <div className="space-y-7 px-6 py-6">
                    <div className="space-y-2">
                        <Label htmlFor="blueprint-name">Blueprint name</Label>
                        <Input
                            id="blueprint-name"
                            className="h-11"
                            value={form.data.name}
                            onChange={(event) => form.setData("name", event.target.value)}
                            placeholder="Example: College enrollment — first semester"
                            autoFocus
                        />
                    </div>

                    <section className="space-y-3">
                        <div>
                            <h3 className="text-sm font-semibold">Choose a starting template</h3>
                            <p className="text-muted-foreground mt-1 text-sm">Everything can be changed later using guided controls.</p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {Object.entries(presets).map(([key, preset], index) => {
                                const selected = form.data.preset === key;
                                return (
                                    <button
                                        key={key}
                                        type="button"
                                        aria-pressed={selected}
                                        onClick={() => form.setData("preset", key)}
                                        className={cn(
                                            "relative min-h-32 rounded-xl border p-4 text-left transition-[border-color,background-color,box-shadow,scale] duration-150 active:scale-[0.98] motion-reduce:transition-none",
                                            selected
                                                ? "border-primary bg-primary/5 shadow-[0_0_0_1px_hsl(var(--primary))]"
                                                : "hover:border-primary/40 hover:bg-muted/40",
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="text-sm font-semibold">{preset.label}</span>
                                            {selected ? (
                                                <span className="bg-primary text-primary-foreground flex size-5 shrink-0 items-center justify-center rounded-full">
                                                    <Check className="size-3" />
                                                </span>
                                            ) : null}
                                        </div>
                                        <p className="text-muted-foreground mt-1.5 text-xs leading-relaxed text-pretty">{preset.description}</p>
                                        <PresetFlowBadges presetKey={key} />
                                        {index === 0 ? (
                                            <Badge variant="secondary" className="mt-3 h-5 gap-1 text-[10px]">
                                                <Sparkles className="size-2.5" /> Recommended for initial setup
                                            </Badge>
                                        ) : null}
                                    </button>
                                );
                            })}
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div>
                            <h3 className="text-sm font-semibold">Who should this apply to?</h3>
                            <p className="text-muted-foreground mt-1 text-sm">Leave everything blank to create the global school-wide blueprint.</p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <OptionSelect
                                value={form.data.school_id}
                                onChange={(value) => form.setData("school_id", value)}
                                options={options.schools}
                                placeholder="Every school"
                            />
                            <OptionSelect
                                value={form.data.student_type}
                                onChange={(value) => form.setData("student_type", value)}
                                options={options.student_types}
                                placeholder="Every student type"
                            />
                            <OptionSelect
                                value={form.data.course_id}
                                onChange={(value) => form.setData("course_id", value)}
                                options={options.programs}
                                placeholder="Every program"
                            />
                            <div className="grid grid-cols-[1fr_8rem] gap-3">
                                <Input
                                    className="h-11"
                                    value={form.data.school_year}
                                    onChange={(event) => form.setData("school_year", event.target.value)}
                                    placeholder="School year"
                                    aria-label="School year"
                                />
                                <OptionSelect
                                    value={form.data.semester}
                                    onChange={(value) => form.setData("semester", value)}
                                    options={
                                        options.periods ?? [
                                            { value: "1", label: "1st" },
                                            { value: "2", label: "2nd" },
                                        ]
                                    }
                                    placeholder="Any term"
                                />
                            </div>
                        </div>

                        <div className="bg-primary/6 text-primary dark:bg-primary/10 rounded-xl p-4 text-sm leading-6">
                            <strong>Coverage preview:</strong> {scopeSentence}
                        </div>

                        {hasScope ? (
                            <label className="flex min-h-16 items-center justify-between gap-4 rounded-xl border p-4">
                                <span>
                                    <span className="block text-sm font-semibold">Inherit school-wide settings</span>
                                    <span className="text-muted-foreground mt-1 block text-xs leading-5">
                                        Recommended. Store only what is different for this group.
                                    </span>
                                </span>
                                <Switch
                                    checked={form.data.inherit}
                                    disabled={!canInherit}
                                    onCheckedChange={(checked) => form.setData("inherit", checked)}
                                />
                            </label>
                        ) : null}

                        {hasScope && !hasPublishedGlobalPolicy ? (
                            <Alert variant="destructive">
                                <AlertTitle>Publish the global blueprint first</AlertTitle>
                                <AlertDescription>
                                    Scoped blueprints need a stable school-wide starting point before they can inherit settings.
                                </AlertDescription>
                            </Alert>
                        ) : null}
                    </section>

                    {form.hasErrors ? <p className="text-destructive text-sm">{Object.values(form.errors)[0]}</p> : null}
                </div>

                <DialogFooter className="bg-background/95 sticky bottom-0 border-t px-6 py-4 backdrop-blur">
                    <Button variant="outline" className="h-11" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button className="h-11" disabled={!form.data.name || form.processing || (hasScope && !canInherit)} onClick={submit}>
                        Create guided draft <ArrowRight className="size-4" />
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const PRESET_PIPELINE_FLOWS: Record<string, string[]> = {
    legacy: ["Intake", "Dept Review", "Cashier", "Enrolled"],
    registrar_cashier: ["Intake", "Registrar", "Cashier", "Enrolled"],
    manual: ["Intake", "Manual Review", "Sectioning", "Enrolled"],
    automatic_curriculum: ["Curriculum", "Dean Clearance", "Cashier", "Enrolled"],
    no_payment: ["Intake", "Academic Clearance", "Enrolled"],
};

function PresetFlowBadges({ presetKey }: { presetKey: string }) {
    const stages = PRESET_PIPELINE_FLOWS[presetKey] ?? ["Intake", "Approval", "Cashier", "Enrolled"];

    return (
        <div className="flex flex-wrap items-center gap-1 pt-2">
            {stages.map((stage, i) => (
                <span key={i} className="inline-flex items-center gap-1 text-[10px]">
                    <span className="bg-muted text-foreground border-border/50 rounded-md border px-1.5 py-0.5 font-medium">{stage}</span>
                    {i < stages.length - 1 && <ArrowRight className="text-muted-foreground size-2.5 shrink-0" />}
                </span>
            ))}
        </div>
    );
}

function optionLabel(options: Option[] | undefined, value: string): string {
    return options?.find((option) => option.value === value)?.label ?? "";
}
