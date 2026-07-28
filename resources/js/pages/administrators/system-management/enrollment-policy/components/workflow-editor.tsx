import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from "@dnd-kit/core";
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ArrowDown, ArrowUp, GitBranch, GripVertical, Plus, Trash2 } from "lucide-react";
import { operatorDefaults, slug } from "../configuration";
import type { Option, RegistryItem, Transition, WorkflowStep } from "../types";
import { OptionSelect, SchemaFields } from "./schema-fields";

export function WorkflowEditor({
    steps,
    actions,
    rules,
    options,
    onChange,
}: {
    steps: WorkflowStep[];
    actions: Record<string, RegistryItem>;
    rules: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onChange: (steps: WorkflowStep[]) => void;
}) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const setStep = (index: number, value: WorkflowStep) => {
        const next = [...steps];
        next[index] = value;
        onChange(normalizeEntries(next));
    };

    const move = (index: number, direction: number) => {
        const destination = index + direction;
        if (destination < 0 || destination >= steps.length) return;
        onChange(normalizeEntries(arrayMove(steps, index, destination)));
    };

    const dragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const oldIndex = steps.findIndex((step) => step.key === active.id);
        const newIndex = steps.findIndex((step) => step.key === over.id);
        if (oldIndex >= 0 && newIndex >= 0) onChange(normalizeEntries(arrayMove(steps, oldIndex, newIndex)));
    };

    const addStep = () => {
        const terminalIndex = steps.findIndex((step) => step.terminal);
        const insertAt = terminalIndex >= 0 ? terminalIndex : steps.length;
        const key = uniqueStepKey("new_approval", steps);
        const destination = steps[insertAt]?.key ?? steps.at(-1)?.key ?? key;
        const step: WorkflowStep = {
            key,
            label: "New approval",
            entry: steps.length === 0,
            terminal: false,
            status: "Pending",
            actions: [],
            transitions: destination === key ? [] : [fallbackTransition(key, destination)],
        };
        const next = [...steps];
        next.splice(insertAt, 0, step);
        onChange(normalizeEntries(next));
    };

    return (
        <section className="space-y-5">
            <div className="max-w-2xl">
                <h2 className="text-2xl font-semibold tracking-tight text-balance">Approvals and staff actions</h2>
                <p className="text-muted-foreground mt-2 text-sm leading-6 text-pretty">
                    Think of this as the student&apos;s approval journey. Staff complete each card from top to bottom; conditional paths are evaluated
                    in order and the protected Otherwise path always handles everyone else.
                </p>
            </div>

            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={dragEnd}>
                <SortableContext items={steps.map((step) => step.key)} strategy={verticalListSortingStrategy}>
                    <div className="before:bg-border relative space-y-4 before:absolute before:top-8 before:bottom-8 before:left-[1.42rem] before:w-px">
                        {steps.map((step, index) => (
                            <SortableStep
                                key={step.key}
                                step={step}
                                index={index}
                                steps={steps}
                                actions={actions}
                                rules={rules}
                                options={options}
                                onChange={(value) => setStep(index, value)}
                                onMove={(direction) => move(index, direction)}
                                onRemove={() => onChange(normalizeEntries(steps.filter((_, itemIndex) => itemIndex !== index)))}
                            />
                        ))}
                    </div>
                </SortableContext>
            </DndContext>

            <Button type="button" variant="outline" className="h-11 border-dashed" onClick={addStep}>
                <Plus className="size-4" /> Add an approval step
            </Button>
        </section>
    );
}

function SortableStep({
    step,
    index,
    steps,
    actions,
    rules,
    options,
    onChange,
    onMove,
    onRemove,
}: {
    step: WorkflowStep;
    index: number;
    steps: WorkflowStep[];
    actions: Record<string, RegistryItem>;
    rules: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onChange: (step: WorkflowStep) => void;
    onMove: (direction: number) => void;
    onRemove: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: step.key });
    const style = { transform: CSS.Transform.toString(transform), transition };
    const fallback = step.transitions.find((item) => item.fallback);
    const branches = step.transitions.filter((item) => !item.fallback);
    const selectedActions = step.actions.map((action) => action.handler);

    const updateTransitions = (conditional: Transition[], otherwise = fallback) => {
        onChange({
            ...step,
            transitions: step.terminal ? [] : [...conditional, otherwise ?? fallbackTransition(step.key, nextDestination(step, steps))],
        });
    };

    return (
        <article
            ref={setNodeRef}
            style={style}
            className={cn("bg-background relative z-10 ml-12 rounded-2xl border p-4 shadow-sm sm:p-5", isDragging && "z-30 opacity-90 shadow-xl")}
        >
            <div className="bg-primary text-primary-foreground absolute top-5 -left-12 flex size-8 items-center justify-center rounded-xl text-sm font-semibold shadow-sm">
                {index + 1}
            </div>
            <div className="flex items-start gap-2">
                <button
                    type="button"
                    className="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring flex size-10 shrink-0 touch-none items-center justify-center rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                    aria-label={`Reorder ${step.label}. Use arrow keys while focused.`}
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-5" />
                </button>
                <div className="min-w-0 flex-1">
                    <Input
                        className="h-10 border-0 px-1 text-base font-semibold shadow-none focus-visible:ring-1"
                        value={step.label}
                        aria-label={`Step ${index + 1} name`}
                        onChange={(event) => onChange({ ...step, label: event.target.value })}
                    />
                    <p className="text-muted-foreground mt-0.5 px-1 text-xs">
                        {step.entry
                            ? "Student journey starts here"
                            : step.terminal
                              ? `Final outcome: ${step.outcome ?? "completed"}`
                              : "Approval step"}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-0.5">
                    <Button type="button" size="icon" variant="ghost" className="size-10" disabled={index === 0} onClick={() => onMove(-1)}>
                        <ArrowUp className="size-4" /> <span className="sr-only">Move up</span>
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-10"
                        disabled={index === steps.length - 1}
                        onClick={() => onMove(1)}
                    >
                        <ArrowDown className="size-4" /> <span className="sr-only">Move down</span>
                    </Button>
                </div>
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-2">
                <div className="space-y-2">
                    <Label>Who can complete this step?</Label>
                    <MultiSelect
                        options={options.roles ?? []}
                        selected={step.authorized_role_ids ?? []}
                        onChange={(authorizedRoleIds) => onChange({ ...step, authorized_role_ids: authorizedRoleIds })}
                        placeholder="Choose responsible staff roles"
                    />
                    <p className="text-muted-foreground text-xs leading-5">
                        Permissions are frozen when published, so future role changes do not alter active enrollments.
                    </p>
                </div>
                <div className="space-y-2">
                    <Label>What happens when staff approve?</Label>
                    <MultiSelect
                        options={Object.values(actions).map((action) => ({ value: action.key, label: action.label }))}
                        selected={selectedActions}
                        onChange={(handlers) =>
                            onChange({
                                ...step,
                                actions: handlers.map(
                                    (handler) =>
                                        step.actions.find((action) => action.handler === handler) ?? {
                                            key: slug(handler),
                                            handler,
                                            configuration: operatorDefaults(actions[handler]),
                                        },
                                ),
                            })
                        }
                        placeholder="Choose staff actions"
                    />
                </div>
            </div>

            {step.actions.map((action, actionIndex) => (
                <div key={`${action.key}-${actionIndex}`} className="bg-muted/15 mt-4 rounded-xl border border-dashed p-4">
                    <p className="text-sm font-semibold">{actions[action.handler]?.label ?? action.handler}</p>
                    <SchemaFields
                        item={actions[action.handler]}
                        value={action.configuration}
                        options={options}
                        onChange={(configuration) => {
                            const nextActions = [...step.actions];
                            nextActions[actionIndex] = { ...action, configuration };
                            onChange({ ...step, actions: nextActions });
                        }}
                    />
                </div>
            ))}

            <div className="bg-muted/35 mt-5 flex flex-wrap items-center gap-3 rounded-xl p-3">
                <Switch
                    id={`terminal-${step.key}`}
                    checked={step.terminal}
                    onCheckedChange={(terminal) =>
                        onChange({
                            ...step,
                            terminal,
                            outcome: terminal ? (step.outcome ?? "completed") : undefined,
                            transitions: terminal ? [] : [fallbackTransition(step.key, nextDestination(step, steps))],
                        })
                    }
                />
                <Label htmlFor={`terminal-${step.key}`}>This ends the enrollment journey</Label>
                {step.terminal ? (
                    <Select
                        value={step.outcome ?? "completed"}
                        onValueChange={(outcome) => onChange({ ...step, outcome: outcome as WorkflowStep["outcome"] })}
                    >
                        <SelectTrigger className="ml-auto h-10 w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="completed">Enrollment completed</SelectItem>
                            <SelectItem value="rejected">Enrollment rejected</SelectItem>
                            <SelectItem value="cancelled">Enrollment cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                ) : null}
            </div>

            {!step.terminal ? (
                <details className="mt-5 rounded-xl border">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-semibold">
                        <GitBranch className="text-primary size-4" /> Conditional destinations
                        <span className="text-muted-foreground ml-auto text-xs font-normal">{branches.length} custom path(s)</span>
                    </summary>
                    <div className="space-y-3 border-t p-4">
                        {branches.map((branch, branchIndex) => (
                            <BranchEditor
                                key={branch.key}
                                branch={branch}
                                steps={steps}
                                currentStep={step}
                                rules={rules}
                                options={options}
                                onChange={(value) => updateTransitions(branches.map((item, index) => (index === branchIndex ? value : item)))}
                                onRemove={() => updateTransitions(branches.filter((_, index) => index !== branchIndex))}
                            />
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-10"
                            onClick={() => {
                                const rule = Object.values(rules)[0];
                                if (!rule) return;
                                updateTransitions([
                                    ...branches,
                                    {
                                        key: `branch_${step.key}_${branches.length + 1}`,
                                        label: `Conditional path ${branches.length + 1}`,
                                        to: fallback?.to ?? nextDestination(step, steps),
                                        fallback: false,
                                        requires_reason: false,
                                        conditions: [
                                            {
                                                key: `condition_${step.key}_${branches.length + 1}`,
                                                handler: rule.key,
                                                configuration: operatorDefaults(rule),
                                            },
                                        ],
                                    },
                                ]);
                            }}
                        >
                            <Plus className="size-4" /> Add “When…” path
                        </Button>

                        <div className="bg-muted rounded-xl p-4">
                            <Label className="mb-2 block">Otherwise, continue to</Label>
                            <OptionSelect
                                value={fallback?.to ?? ""}
                                onChange={(to) => updateTransitions(branches, { ...(fallback ?? fallbackTransition(step.key, to)), to })}
                                options={destinationOptions(steps, step.key)}
                                placeholder="Choose the normal next step"
                                allowEmpty={false}
                            />
                            <p className="text-muted-foreground mt-2 text-xs leading-5">
                                Protected fallback: every student who does not match a custom path goes here.
                            </p>
                            <label className="mt-3 flex items-center gap-2 text-sm">
                                <Switch
                                    checked={fallback?.requires_reason ?? false}
                                    onCheckedChange={(requiresReason) =>
                                        updateTransitions(branches, {
                                            ...(fallback ?? fallbackTransition(step.key, nextDestination(step, steps))),
                                            requires_reason: requiresReason,
                                        })
                                    }
                                />
                                Require an audited reason for this transition
                            </label>
                        </div>
                    </div>
                </details>
            ) : null}

            <details className="mt-3">
                <summary className="text-muted-foreground min-h-10 cursor-pointer py-2 text-sm font-medium">Advanced reporting details</summary>
                <div className="bg-muted/30 grid gap-3 rounded-xl p-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Reporting status</Label>
                        <Input className="h-11" value={step.status} onChange={(event) => onChange({ ...step, status: event.target.value })} />
                    </div>
                    <div className="space-y-2">
                        <Label>Technical identifier</Label>
                        <Input
                            className="h-11 font-mono text-xs"
                            value={step.key}
                            onChange={(event) => onChange({ ...step, key: slug(event.target.value) })}
                        />
                    </div>
                </div>
            </details>

            {steps.length > 2 && !step.entry ? (
                <div className="mt-3 flex justify-end">
                    <Button type="button" variant="ghost" size="sm" className="text-destructive hover:text-destructive h-10" onClick={onRemove}>
                        <Trash2 className="size-4" /> Remove step
                    </Button>
                </div>
            ) : null}
        </article>
    );
}

function BranchEditor({
    branch,
    steps,
    currentStep,
    rules,
    options,
    onChange,
    onRemove,
}: {
    branch: Transition;
    steps: WorkflowStep[];
    currentStep: WorkflowStep;
    rules: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onChange: (branch: Transition) => void;
    onRemove: () => void;
}) {
    const condition = branch.conditions[0];

    return (
        <div className="rounded-xl border p-4">
            <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <div className="space-y-2">
                    <Label>When this is true</Label>
                    <OptionSelect
                        value={condition?.handler ?? ""}
                        onChange={(handler) =>
                            onChange({
                                ...branch,
                                conditions: [
                                    {
                                        key: condition?.key ?? `condition_${branch.key}`,
                                        handler,
                                        configuration: operatorDefaults(rules[handler]),
                                    },
                                ],
                            })
                        }
                        options={Object.values(rules).map((rule) => ({ value: rule.key, label: rule.label }))}
                        placeholder="Choose a condition"
                        allowEmpty={false}
                    />
                </div>
                <div className="space-y-2">
                    <Label>Continue to</Label>
                    <OptionSelect
                        value={branch.to}
                        onChange={(to) => onChange({ ...branch, to })}
                        options={destinationOptions(steps, currentStep.key)}
                        placeholder="Choose destination"
                        allowEmpty={false}
                    />
                </div>
                <Button type="button" variant="ghost" size="icon" className="text-destructive size-10 self-end" onClick={onRemove}>
                    <Trash2 className="size-4" /> <span className="sr-only">Remove conditional path</span>
                </Button>
            </div>
            {condition ? (
                <SchemaFields
                    item={rules[condition.handler]}
                    value={condition.configuration}
                    options={options}
                    onChange={(configuration) => onChange({ ...branch, conditions: [{ ...condition, configuration }] })}
                />
            ) : null}
            <label className="mt-3 flex items-center gap-2 text-sm">
                <Switch
                    checked={branch.requires_reason ?? false}
                    onCheckedChange={(requiresReason) => onChange({ ...branch, requires_reason: requiresReason })}
                />
                Require an audited reason for this path
            </label>
        </div>
    );
}

function normalizeEntries(steps: WorkflowStep[]): WorkflowStep[] {
    return steps.map((step, index) => ({ ...step, entry: index === 0 }));
}

function nextDestination(step: WorkflowStep, steps: WorkflowStep[]): string {
    const index = steps.findIndex((item) => item.key === step.key);
    return steps[index + 1]?.key ?? steps.find((item) => item.key !== step.key)?.key ?? step.key;
}

function fallbackTransition(stepKey: string, to: string): Transition {
    return { key: `otherwise_${stepKey}`, label: "Otherwise", to, fallback: true, requires_reason: false, conditions: [] };
}

function destinationOptions(steps: WorkflowStep[], currentKey: string): Option[] {
    return steps.filter((step) => step.key !== currentKey).map((step) => ({ value: step.key, label: step.label }));
}

function uniqueStepKey(seed: string, steps: WorkflowStep[]): string {
    const existing = new Set(steps.map((step) => step.key));
    let candidate = seed;
    let counter = 2;
    while (existing.has(candidate)) candidate = `${seed}_${counter++}`;
    return candidate;
}
