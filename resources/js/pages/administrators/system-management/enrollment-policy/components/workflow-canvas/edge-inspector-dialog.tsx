import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { useEffect, useState } from "react";
import { operatorDefaults } from "../../configuration";
import type { Option, RegistryItem, Transition, WorkflowStep } from "../../types";
import { OptionSelect, SchemaFields } from "../schema-fields";

export function EdgeInspectorDialog({
    open,
    onOpenChange,
    transition,
    sourceStep,
    steps,
    rules,
    options,
    onSave,
    onDelete,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transition: Transition | null;
    sourceStep: WorkflowStep | null;
    steps: WorkflowStep[];
    rules: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onSave: (sourceStepKey: string, updatedTransition: Transition) => void;
    onDelete?: (sourceStepKey: string, transitionKey: string) => void;
}) {
    const [draft, setDraft] = useState<Transition | null>(transition);

    useEffect(() => {
        setDraft(transition);
    }, [transition]);

    if (!draft || !sourceStep) return null;

    const condition = draft.conditions[0];
    const destinationOptions = steps.filter((s) => s.key !== sourceStep.key && !s.entry).map((s) => ({ value: s.key, label: s.label }));

    const handleRuleChange = (handler: string) => {
        const ruleItem = rules[handler];
        setDraft({
            ...draft,
            conditions: [
                {
                    key: condition?.key ?? `condition_${draft.key}`,
                    handler,
                    configuration: operatorDefaults(ruleItem),
                },
                ...draft.conditions.slice(1),
            ],
        });
    };

    const handleSave = () => {
        onSave(sourceStep.key, draft);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="text-base font-semibold">Configure Transition</DialogTitle>
                    <DialogDescription className="text-xs">
                        Define where applicants go after completing <span className="text-foreground font-semibold">{sourceStep.label}</span>.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-xs">
                    {/* Fallback Switch */}
                    <div className="border-border/60 bg-muted/20 flex items-center justify-between rounded-xl border p-3">
                        <div className="space-y-0.5">
                            <Label htmlFor="edge-fallback" className="text-xs font-semibold">
                                Protected Otherwise Path
                            </Label>
                            <p className="text-muted-foreground text-[11px]">Default path taken when no conditional rules match.</p>
                        </div>
                        <Switch
                            id="edge-fallback"
                            checked={draft.fallback}
                            onCheckedChange={(checked) =>
                                setDraft({
                                    ...draft,
                                    fallback: checked,
                                    label: checked ? "Otherwise" : draft.label,
                                    conditions: checked ? [] : draft.conditions,
                                })
                            }
                        />
                    </div>

                    {/* Transition Label */}
                    <div className="space-y-1.5">
                        <Label className="text-xs font-medium">Path Label</Label>
                        <Input
                            value={draft.label}
                            onChange={(e) => setDraft({ ...draft, label: e.target.value })}
                            className="h-8.5 text-xs"
                            placeholder="e.g. Standard Clearance"
                        />
                    </div>

                    {/* Destination Step */}
                    <div className="space-y-1.5">
                        <Label className="text-xs font-medium">Destination Step</Label>
                        <OptionSelect
                            value={draft.to}
                            onChange={(to) => setDraft({ ...draft, to })}
                            options={destinationOptions}
                            placeholder="Select destination step"
                            allowEmpty={false}
                        />
                    </div>

                    {/* Conditional Rules (if not fallback) */}
                    {!draft.fallback && (
                        <div className="border-border/60 bg-muted/15 space-y-3 rounded-xl border p-3.5">
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold">Condition Rule</Label>
                                <OptionSelect
                                    value={condition?.handler ?? ""}
                                    onChange={handleRuleChange}
                                    options={Object.values(rules).map((r) => ({
                                        value: r.key,
                                        label: r.label,
                                    }))}
                                    placeholder="Choose eligibility rule"
                                    allowEmpty={false}
                                />
                            </div>

                            {condition && rules[condition.handler] && (
                                <div className="border-border/40 space-y-2 border-t pt-2">
                                    <SchemaFields
                                        item={rules[condition.handler]}
                                        value={condition.configuration}
                                        options={options}
                                        onChange={(configuration) =>
                                            setDraft({
                                                ...draft,
                                                conditions: [{ ...condition, configuration }, ...draft.conditions.slice(1)],
                                            })
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    )}

                    {/* Audited Reason Switch */}
                    <div className="border-border/50 bg-background flex items-center justify-between rounded-xl border p-3">
                        <div className="space-y-0.5">
                            <Label htmlFor="edge-reason" className="text-xs font-medium">
                                Audited Reason Required
                            </Label>
                            <p className="text-muted-foreground text-[11px]">Require staff to write an explanation when moving through this path.</p>
                        </div>
                        <Switch
                            id="edge-reason"
                            checked={draft.requires_reason ?? false}
                            onCheckedChange={(checked) => setDraft({ ...draft, requires_reason: checked })}
                        />
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    {onDelete && !draft.fallback && (
                        <Button type="button" variant="destructive" size="sm" onClick={() => onDelete(sourceStep.key, draft.key)}>
                            Delete route
                        </Button>
                    )}
                    <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)} className="h-8 text-xs">
                        Cancel
                    </Button>
                    <Button type="button" size="sm" onClick={handleSave} className="h-8 text-xs font-semibold">
                        Save Transition
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
