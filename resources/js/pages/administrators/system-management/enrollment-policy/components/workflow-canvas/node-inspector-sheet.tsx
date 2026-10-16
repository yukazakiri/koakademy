import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ArrowDown, ArrowUp, CheckCircle2, Edit3, Flag, GitBranch, Layers, Mail, Plus, SlidersHorizontal, Trash2, Users, Zap } from "lucide-react";
import { useEffect, useState } from "react";
import { operatorDefaults, slug } from "../../configuration";
import type { Action, Option, RegistryItem, Transition, WorkflowStep } from "../../types";
import { SchemaFields } from "../schema-fields";
import { appendActions, appendTransition, canConnectSteps } from "./workflow-model";

export function NodeInspectorSheet({
    open,
    onOpenChange,
    step,
    steps,
    actions,
    options,
    onSave,
    onDelete,
    onEditTransition,
    onOpenActionInspector,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    step: WorkflowStep | null;
    steps: WorkflowStep[];
    actions: Record<string, RegistryItem>;
    rules?: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onSave: (updatedStep: WorkflowStep) => void;
    onDelete: (stepKey: string) => void;
    onEditTransition: (sourceKey: string, transitionKey: string) => void;
    onOpenActionInspector?: (parentStepKey: string, actionIndex: number) => void;
}) {
    const [draft, setDraft] = useState<WorkflowStep | null>(step);
    const [activeTab, setActiveTab] = useState("general");

    useEffect(() => {
        setDraft(step);
    }, [step]);

    if (!draft) return null;

    const notifyAction = draft.actions.find((a) => a.handler === "enrollment.notify");
    const hasNotification = Boolean(notifyAction);
    const notificationType = String(
        (notifyAction?.configuration as Record<string, unknown> | undefined)?.notification ?? (draft.terminal ? "assessment" : "status_update"),
    );

    const handleNotifyToggle = (checked: boolean) => {
        let nextActions = [...draft.actions];
        if (checked) {
            const defaultType = draft.terminal
                ? "assessment"
                : draft.key.includes("academic") || draft.key.includes("dean")
                  ? "academic_verified"
                  : "status_update";
            nextActions = appendActions(nextActions, [
                {
                    key: "notify_student",
                    handler: "enrollment.notify",
                    configuration: { notification: defaultType },
                },
            ]);
        } else {
            nextActions = nextActions.filter((a) => a.handler !== "enrollment.notify");
        }
        const updated = { ...draft, actions: nextActions };
        setDraft(updated);
        onSave(updated);
    };

    const handleNotificationTypeChange = (type: string) => {
        const nextActions = draft.actions.map((a) => {
            if (a.handler !== "enrollment.notify") return a;
            return {
                ...a,
                configuration: { ...a.configuration, notification: type },
            };
        });
        const updated = { ...draft, actions: nextActions };
        setDraft(updated);
        onSave(updated);
    };

    const handleAddTransition = () => {
        const availableDestinations = steps.filter((s) => canConnectSteps(steps, draft.key, s.key));
        if (availableDestinations.length === 0) return;

        const destKey = availableDestinations[0].key;
        let key = `branch_${draft.key}_${draft.transitions.length + 1}`;
        while (draft.transitions.some((transition) => transition.key === key)) key += "_next";
        const newTrans: Transition = {
            key,
            label: `Path to ${destKey}`,
            to: destKey,
            fallback: draft.transitions.length === 0,
            requires_reason: false,
            conditions: [],
        };
        const updated = { ...draft, transitions: appendTransition(draft.transitions, newTrans) };
        setDraft(updated);
        onSave(updated);
        onEditTransition(draft.key, newTrans.key);
    };

    const handleReorderAction = (fromIdx: number, toIdx: number) => {
        if (fromIdx < 0 || toIdx < 0 || toIdx >= draft.actions.length) return;
        const nextActions = [...draft.actions];
        const [moved] = nextActions.splice(fromIdx, 1);
        nextActions.splice(toIdx, 0, moved);
        const updated = { ...draft, actions: nextActions };
        setDraft(updated);
        onSave(updated);
    };

    const handleDeleteAction = (idx: number) => {
        const nextActions = draft.actions.filter((_, i) => i !== idx);
        const updated = { ...draft, actions: nextActions };
        setDraft(updated);
        onSave(updated);
    };

    const handleAddQuickAction = (handler: string) => {
        const item = actions[handler];
        if (!item) return;
        const newAction: Action = {
            key: slug(handler),
            handler,
            configuration: operatorDefaults(item),
        };
        const updated = { ...draft, actions: appendActions(draft.actions, [newAction]) };
        setDraft(updated);
        onSave(updated);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-[440px] flex-col overflow-y-auto p-0 sm:max-w-[480px]">
                {/* Header with ReUI IconTile */}
                <SheetHeader className="border-border/40 bg-card/75 shrink-0 border-b p-5">
                    <div className="flex items-start gap-3">
                        <IconTile
                            variant="soft"
                            size="default"
                            className={draft.entry ? "text-emerald-500" : draft.terminal ? "text-rose-500" : "text-primary"}
                        >
                            {draft.entry ? (
                                <CheckCircle2 className="size-5" />
                            ) : draft.terminal ? (
                                <Flag className="size-5" />
                            ) : (
                                <Layers className="size-5" />
                            )}
                        </IconTile>

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <SheetTitle className="text-foreground text-base font-bold">{draft.label}</SheetTitle>
                                {draft.entry && (
                                    <Badge variant="success-light" size="sm">
                                        Entry stage
                                    </Badge>
                                )}
                                {draft.terminal && (
                                    <Badge variant="destructive-light" size="sm">
                                        Terminal
                                    </Badge>
                                )}
                            </div>
                            <SheetDescription className="text-muted-foreground mt-0.5 text-xs">
                                Step key: <span className="text-foreground font-mono">{draft.key}</span> • Status:{" "}
                                <span className="text-foreground font-medium">{draft.status}</span>
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                {/* Tabbed Navigation Bar */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="flex min-h-0 flex-1 flex-col">
                    <div className="border-border/40 bg-muted/20 border-b px-5 pt-2">
                        <TabsList className="h-8 w-full justify-start gap-1 bg-transparent p-0">
                            <TabsTrigger
                                value="general"
                                className="data-[state=active]:bg-background h-8 rounded-lg text-xs font-semibold data-[state=active]:shadow-2xs"
                            >
                                <SlidersHorizontal className="mr-1.5 size-3.5" /> General
                            </TabsTrigger>
                            <TabsTrigger
                                value="roles"
                                className="data-[state=active]:bg-background h-8 rounded-lg text-xs font-semibold data-[state=active]:shadow-2xs"
                            >
                                <Users className="mr-1.5 size-3.5" /> Staff Roles
                            </TabsTrigger>
                            <TabsTrigger
                                value="actions"
                                className="data-[state=active]:bg-background h-8 rounded-lg text-xs font-semibold data-[state=active]:shadow-2xs"
                            >
                                <Zap className="mr-1.5 size-3.5" /> On entry ({draft.actions.length})
                            </TabsTrigger>
                            {!draft.terminal && (
                                <TabsTrigger
                                    value="routing"
                                    className="data-[state=active]:bg-background h-8 rounded-lg text-xs font-semibold data-[state=active]:shadow-2xs"
                                >
                                    <GitBranch className="mr-1.5 size-3.5" /> Routes ({draft.transitions.length})
                                </TabsTrigger>
                            )}
                        </TabsList>
                    </div>

                    <div className="flex-1 overflow-y-auto p-5 text-xs">
                        {/* TAB 1: General & Outcome Configuration */}
                        <TabsContent value="general" className="m-0 space-y-5">
                            <div className="space-y-3.5">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold">Step Label</Label>
                                    <Input
                                        value={draft.label}
                                        onChange={(e) => {
                                            const updated = { ...draft, label: e.target.value };
                                            setDraft(updated);
                                            onSave(updated);
                                        }}
                                        className="h-8.5 text-xs font-medium"
                                        placeholder="e.g. Document Verification"
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-semibold">Reporting Status</Label>
                                        <Input
                                            value={draft.status}
                                            onChange={(e) => {
                                                const updated = { ...draft, status: e.target.value };
                                                setDraft(updated);
                                                onSave(updated);
                                            }}
                                            className="h-8.5 text-xs"
                                            placeholder="e.g. Under Review"
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-semibold">Technical Key</Label>
                                        <Input value={draft.key} className="h-8.5 font-mono text-[11px]" readOnly />
                                    </div>
                                </div>
                            </div>

                            {/* Terminal Journey Toggle */}
                            <div className="border-border/60 bg-muted/20 space-y-3 rounded-xl border p-3.5">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="terminal-switch" className="text-xs font-semibold">
                                            Conclude Enrollment Journey
                                        </Label>
                                        <p className="text-muted-foreground text-[11px]">
                                            Marks this node as a final destination without outgoing transitions.
                                        </p>
                                    </div>
                                    <Switch
                                        id="terminal-switch"
                                        checked={draft.terminal}
                                        onCheckedChange={(terminal) => {
                                            const updated: WorkflowStep = {
                                                ...draft,
                                                terminal,
                                                outcome: terminal ? (draft.outcome ?? "completed") : undefined,
                                                transitions: terminal ? [] : draft.transitions,
                                            };
                                            setDraft(updated);
                                            onSave(updated);
                                        }}
                                    />
                                </div>

                                {draft.terminal && (
                                    <div className="border-border/40 space-y-1.5 border-t pt-2">
                                        <Label className="text-xs font-semibold">Final Outcome</Label>
                                        <Select
                                            value={draft.outcome ?? "completed"}
                                            onValueChange={(outcome) => {
                                                const updated = { ...draft, outcome: outcome as WorkflowStep["outcome"] };
                                                setDraft(updated);
                                                onSave(updated);
                                            }}
                                        >
                                            <SelectTrigger className="h-8.5 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="completed">Enrollment Completed (Success)</SelectItem>
                                                <SelectItem value="rejected">Enrollment Rejected</SelectItem>
                                                <SelectItem value="cancelled">Enrollment Cancelled</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>

                            {/* Automated Email Confirmation Settings */}
                            <div className="border-border/60 bg-muted/20 space-y-3 rounded-xl border p-3.5">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-1.5">
                                            <Mail className="text-primary size-3.5" />
                                            <Label htmlFor="notify-switch" className="text-xs font-semibold">
                                                Send Email Confirmation
                                            </Label>
                                        </div>
                                        <p className="text-muted-foreground text-[11px]">
                                            Email applicant on entering this stage, after preceding actions succeed.
                                        </p>
                                    </div>
                                    <Switch id="notify-switch" checked={hasNotification} onCheckedChange={handleNotifyToggle} />
                                </div>

                                {hasNotification && (
                                    <div className="border-border/40 space-y-2 border-t pt-2.5">
                                        <div className="space-y-1">
                                            <Label className="text-muted-foreground text-[11px] font-medium">Confirmation Email Template</Label>
                                            <Select value={notificationType} onValueChange={handleNotificationTypeChange}>
                                                <SelectTrigger className="bg-background h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="assessment" className="text-xs">
                                                        Official Enrollment & Assessment Certificate (PDF)
                                                    </SelectItem>
                                                    <SelectItem value="academic_verified" className="text-xs">
                                                        Academic Clearance Notice (Subjects & Fees breakdown)
                                                    </SelectItem>
                                                    <SelectItem value="status_update" className="text-xs">
                                                        Status Progression Notice (General status update)
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <p className="text-muted-foreground bg-background/80 border-border/40 rounded-lg border p-2 text-[10px] leading-relaxed">
                                            {notificationType === "assessment" &&
                                                "Generates official assessment certificate PDF and emails student with ID & portal credentials."}
                                            {notificationType === "academic_verified" &&
                                                "Emails student their verified curriculum load, approved tuition breakdown, and cashier payment instructions."}
                                            {notificationType === "status_update" &&
                                                `Emails student that their enrollment progression is now: ${draft.status}`}
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Delete Step Trigger */}
                            {!draft.entry && (!draft.terminal || steps.filter((stage) => stage.terminal).length > 1) && (
                                <div className="border-border/40 border-t pt-2">
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="h-8 w-full gap-1.5 text-xs"
                                        onClick={() => {
                                            onDelete(draft.key);
                                            onOpenChange(false);
                                        }}
                                    >
                                        <Trash2 className="size-3.5" /> Remove this Step from Journey
                                    </Button>
                                </div>
                            )}
                        </TabsContent>

                        {/* TAB 2: Staff Roles Selection (Fixed, no overlapping badges) */}
                        <TabsContent value="roles" className="m-0 space-y-4">
                            <div className="border-border/60 bg-muted/15 space-y-3 rounded-xl border p-4">
                                <div>
                                    <Label className="text-foreground text-xs font-semibold">Authorized Staff Roles</Label>
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">
                                        Choose who may leave this stage through its routes. Entry actions run before this staff gate.
                                    </p>
                                </div>

                                <MultiSelect
                                    options={options.roles ?? []}
                                    selected={draft.authorized_role_ids ?? []}
                                    onChange={(authorizedRoleIds) => {
                                        const updated = { ...draft, authorized_role_ids: authorizedRoleIds };
                                        setDraft(updated);
                                        onSave(updated);
                                    }}
                                    placeholder="Choose responsible staff roles"
                                    maxCount={4}
                                />

                                <div className="bg-background/70 border-border/40 text-muted-foreground space-y-1 rounded-lg border p-2.5 text-[11px]">
                                    <div className="text-foreground flex items-center justify-between font-semibold">
                                        <span>Selected Roles:</span>
                                        <span>{(draft.authorized_role_ids ?? []).length} role(s)</span>
                                    </div>
                                    <p className="text-[10px] leading-relaxed">
                                        {(draft.authorized_role_ids ?? []).length === 0
                                            ? "No roles selected. Publishing creates a permission granted to no roles; only permission overrides can advance this stage."
                                            : "Only staff with the designated roles above may advance students through this stage."}
                                    </p>
                                </div>
                            </div>
                        </TabsContent>

                        {/* TAB 3: Sequential Sub-Actions Pipeline */}
                        <TabsContent value="actions" className="m-0 space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label className="text-foreground text-xs font-semibold">Sequential Sub-Action Pipeline</Label>
                                    <p className="text-muted-foreground text-[11px]">
                                        Runs in this order on entering the stage. Entry stage runs on submission, before staff review.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                {draft.actions.map((act, actIdx) => (
                                    <div
                                        key={`${act.key}-${actIdx}`}
                                        className="border-border/60 bg-background/80 space-y-2 rounded-xl border p-3 shadow-2xs"
                                    >
                                        <div className="flex items-center justify-between gap-1">
                                            <div className="flex min-w-0 items-center gap-1.5">
                                                <span className="bg-primary/10 text-primary border-primary/20 flex size-5 shrink-0 items-center justify-center rounded-md border font-mono text-[10px] font-bold">
                                                    #{actIdx + 1}
                                                </span>
                                                <span className="text-foreground truncate text-xs font-semibold">
                                                    {actions[act.handler]?.label ?? act.handler}
                                                </span>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-0.5">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={actIdx === 0}
                                                    className="text-muted-foreground hover:text-foreground size-5 disabled:opacity-30"
                                                    onClick={() => handleReorderAction(actIdx, actIdx - 1)}
                                                    title="Move earlier"
                                                >
                                                    <ArrowUp className="size-3" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={actIdx === draft.actions.length - 1}
                                                    className="text-muted-foreground hover:text-foreground size-5 disabled:opacity-30"
                                                    onClick={() => handleReorderAction(actIdx, actIdx + 1)}
                                                    title="Move later"
                                                >
                                                    <ArrowDown className="size-3" />
                                                </Button>

                                                {onOpenActionInspector && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-foreground size-5"
                                                        onClick={() => onOpenActionInspector(draft.key, actIdx)}
                                                        title="Configure action"
                                                    >
                                                        <Edit3 className="size-3" />
                                                    </Button>
                                                )}

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-muted-foreground hover:text-destructive size-5"
                                                    onClick={() => handleDeleteAction(actIdx)}
                                                    title="Remove action"
                                                >
                                                    <Trash2 className="size-3" />
                                                </Button>
                                            </div>
                                        </div>

                                        {/* Inline schema fields for easy tuning */}
                                        <div className="border-border/30 border-t pt-1">
                                            <SchemaFields
                                                item={actions[act.handler]}
                                                value={act.configuration}
                                                options={options}
                                                onChange={(cfg) => {
                                                    const nextActions = [...draft.actions];
                                                    nextActions[actIdx] = { ...act, configuration: cfg };
                                                    const updated = { ...draft, actions: nextActions };
                                                    setDraft(updated);
                                                    onSave(updated);
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}

                                {draft.actions.length === 0 && (
                                    <div className="border-border/70 text-muted-foreground rounded-xl border border-dashed p-5 text-center text-xs">
                                        No automated actions configured for this step.
                                    </div>
                                )}
                            </div>

                            {/* Add Sub-Action Dropdown */}
                            <div className="border-border/40 border-t pt-2">
                                <Select onValueChange={handleAddQuickAction}>
                                    <SelectTrigger className="bg-background h-8 text-xs">
                                        <SelectValue placeholder="+ Attach new sub-action..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.values(actions).map((item) => (
                                            <SelectItem key={item.key} value={item.key} className="text-xs">
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </TabsContent>

                        {/* TAB 4: Outbound Transitions & Routing */}
                        {!draft.terminal && (
                            <TabsContent value="routing" className="m-0 space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <Label className="text-foreground text-xs font-semibold">Outbound Routing Paths</Label>
                                        <p className="text-muted-foreground text-[11px]">
                                            Define conditional criteria or fallback routes to downstream steps.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1 px-2.5 text-[11px]"
                                        onClick={handleAddTransition}
                                    >
                                        <Plus className="size-3" /> Add Branch
                                    </Button>
                                </div>

                                <div className="space-y-2">
                                    {draft.transitions.map((trans) => (
                                        <div
                                            key={trans.key}
                                            className="border-border/50 bg-background/70 flex items-center justify-between rounded-xl border p-3 text-xs"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-1.5">
                                                    <GitBranch className="text-primary size-3.5 shrink-0" />
                                                    <span className="text-foreground truncate font-semibold">
                                                        {trans.label || (trans.fallback ? "Otherwise" : "Branch")}
                                                    </span>
                                                </div>
                                                <span className="text-muted-foreground ml-5 block truncate text-[11px]">
                                                    Routes to: <span className="text-foreground font-medium">{trans.to}</span>
                                                </span>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 px-2 text-xs"
                                                    onClick={() => onEditTransition(draft.key, trans.key)}
                                                >
                                                    Edit Rule
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={trans.fallback}
                                                    aria-label={`Delete route ${trans.label}`}
                                                    className="text-muted-foreground hover:text-destructive size-7"
                                                    onClick={() => {
                                                        const updated = {
                                                            ...draft,
                                                            transitions: draft.transitions.filter((t) => t.key !== trans.key),
                                                        };
                                                        setDraft(updated);
                                                        onSave(updated);
                                                    }}
                                                >
                                                    <Trash2 className="size-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}

                                    {draft.transitions.length === 0 && (
                                        <div className="border-border/70 text-muted-foreground rounded-xl border border-dashed p-5 text-center text-xs">
                                            No outbound paths configured.
                                        </div>
                                    )}
                                </div>
                            </TabsContent>
                        )}
                    </div>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}
