import { updateEnrollmentPipeline } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { Link, router } from "@inertiajs/react";
import { ArrowDown, ArrowUp, ArrowUpRight, Layers, Loader2, Plus, RotateCcw, Save, SlidersHorizontal, Trash2, Workflow } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { resolvePipelineColor } from "./enrollment-utils";
import type { EnrollmentPipeline, EnrollmentWorkspacePreferences, PipelineMeta } from "./types";

type EditablePipelineStep = {
    key: string;
    status: string;
    label: string;
    color: string;
    action_type: "standard" | "department_verification" | "cashier_verification";
    allowed_roles: string[];
};

type EnrollmentConfigSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    preferences: EnrollmentWorkspacePreferences;
    onPreferencesChange: (next: EnrollmentWorkspacePreferences) => void;
    onResetPreferences: () => void;
    pipeline: EnrollmentPipeline;
    pipelineMeta?: PipelineMeta;
    onPipelineSaved?: () => void;
};

const COLOR_OPTIONS = [
    { value: "amber", label: "Amber" },
    { value: "blue", label: "Blue" },
    { value: "green", label: "Green" },
    { value: "purple", label: "Violet" },
    { value: "red", label: "Rose" },
    { value: "gray", label: "Neutral" },
];

const ACTION_TYPE_OPTIONS = [
    { value: "standard", label: "Standard stage" },
    { value: "department_verification", label: "Department verification" },
    { value: "cashier_verification", label: "Cashier payment verification" },
] as const;

export function EnrollmentConfigSheet({
    open,
    onOpenChange,
    preferences,
    onPreferencesChange,
    onResetPreferences,
    pipeline,
    pipelineMeta,
    onPipelineSaved,
}: EnrollmentConfigSheetProps) {
    const isPolicyEngine = pipelineMeta?.engine === "policy_v1";
    const canConfigurePipeline = Boolean(pipelineMeta?.can_configure);
    const configureUrl = pipelineMeta?.configure_url ?? "/administrators/system-management/enrollment-pipeline";

    const [steps, setSteps] = useState<EditablePipelineStep[]>([]);
    const [submittedLabel, setSubmittedLabel] = useState(pipeline.submitted_label || "Submitted");
    const [savingPipeline, setSavingPipeline] = useState(false);

    useEffect(() => {
        if (!open) return;
        setSubmittedLabel(pipeline.submitted_label || "Submitted");
        setSteps(
            pipeline.steps.map((step, index) => ({
                key: step.key || `step_${index + 1}`,
                status: step.status,
                label: step.label || step.status,
                color: step.color || "blue",
                action_type: (step.action_type as EditablePipelineStep["action_type"]) || "standard",
                allowed_roles: Array.isArray(step.allowed_roles) ? step.allowed_roles : [],
            })),
        );
    }, [open, pipeline]);

    const updatePref = <K extends keyof EnrollmentWorkspacePreferences>(key: K, value: EnrollmentWorkspacePreferences[K]) => {
        onPreferencesChange({ ...preferences, [key]: value });
    };

    const handleStepChange = <K extends keyof EditablePipelineStep>(index: number, field: K, value: EditablePipelineStep[K]) => {
        setSteps((prev) => prev.map((step, i) => (i === index ? { ...step, [field]: value } : step)));
    };

    const handleMoveStep = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= steps.length) return;
        setSteps((prev) => {
            const copy = [...prev];
            const [item] = copy.splice(index, 1);
            copy.splice(target, 0, item);
            return copy;
        });
    };

    const handleAddStep = () => {
        const idx = steps.length + 1;
        setSteps((prev) => [
            ...prev,
            {
                key: `custom_step_${idx}`,
                status: `Stage ${idx}`,
                label: `Stage ${idx}`,
                color: "blue",
                action_type: "standard",
                allowed_roles: [],
            },
        ]);
    };

    const handleRemoveStep = (index: number) => {
        if (steps.length <= 1) {
            toast.error("At least one pipeline stage is required.");
            return;
        }
        setSteps((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSaveLegacyPipeline = (event: React.FormEvent) => {
        event.preventDefault();
        if (isPolicyEngine || !canConfigurePipeline) return;

        const cleaned = steps.map((step, index) => {
            const status = step.status.trim();
            const label = step.label.trim() || status;
            const key = step.key.trim() || `step_${index + 1}`;
            return {
                key,
                status,
                label,
                color: step.color,
                action_type: step.action_type,
                allowed_roles: step.allowed_roles,
                next_step_key: index < steps.length - 1 ? steps[index + 1].key || `step_${index + 2}` : null,
            };
        });

        if (cleaned.some((s) => !s.status)) {
            toast.error("Every pipeline stage needs a status name.");
            return;
        }

        const uniqueStatuses = new Set(cleaned.map((s) => s.status.toLowerCase()));
        if (uniqueStatuses.size !== cleaned.length) {
            toast.error("Stage status names must be unique.");
            return;
        }

        setSavingPipeline(true);
        router.put(
            updateEnrollmentPipeline.url(),
            {
                submitted_label: submittedLabel.trim() || "Submitted",
                steps: cleaned,
                entry_step_key: cleaned[0].key,
                completion_step_key: cleaned[cleaned.length - 1].key,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Enrollment pipeline updated.");
                    onPipelineSaved?.();
                    onOpenChange(false);
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(typeof firstError === "string" ? firstError : "Could not save pipeline configuration.");
                },
                onFinish: () => setSavingPipeline(false),
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-xl">
                <SheetHeader className="border-border/70 bg-card/60 shrink-0 border-b px-5 py-4">
                    <div className="flex items-center gap-2.5">
                        <IconTile variant="soft" size="sm">
                            <SlidersHorizontal />
                        </IconTile>
                        <div>
                            <SheetTitle className="text-base font-semibold tracking-tight">Configure Enrollments</SheetTitle>
                            <SheetDescription className="text-xs">
                                Customize your workspace layout and inspect or configure your active enrollment pipeline.
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <Tabs defaultValue="workspace" className="flex min-h-0 flex-1 flex-col">
                    <div className="border-border/70 shrink-0 border-b px-5 py-2.5">
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="workspace" className="gap-1.5 text-xs">
                                <Layers className="size-3.5" />
                                Workspace
                            </TabsTrigger>
                            <TabsTrigger value="pipeline" className="gap-1.5 text-xs">
                                <Workflow className="size-3.5" />
                                Pipeline ({pipeline.steps.length})
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    {/* TAB 1: WORKSPACE PREFERENCES */}
                    <TabsContent value="workspace" className="mt-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <Frame spacing="sm">
                            <FrameHeader>
                                <FrameTitle className="text-xs font-semibold tracking-wide uppercase">Layout &amp; density</FrameTitle>
                                <FrameDescription className="text-xs">
                                    Saved per browser so your enrollment workspace opens the way you work best.
                                </FrameDescription>
                            </FrameHeader>
                            <FramePanel className="space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Default view</Label>
                                        <Select
                                            value={preferences.defaultView}
                                            onValueChange={(v) => updatePref("defaultView", v as "table" | "board")}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="table">Pipeline directory</SelectItem>
                                                <SelectItem value="board">Workflow board</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Row &amp; card density</Label>
                                        <Select
                                            value={preferences.density}
                                            onValueChange={(v) => updatePref("density", v as "comfortable" | "compact")}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="comfortable">Comfortable</SelectItem>
                                                <SelectItem value="compact">Compact</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="text-xs">Completed lane preview limit (Board)</Label>
                                    <Select
                                        value={String(preferences.completedPreviewLimit)}
                                        onValueChange={(v) => updatePref("completedPreviewLimit", Number(v))}
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="3">Latest 3 enrolled</SelectItem>
                                            <SelectItem value="4">Latest 4 enrolled</SelectItem>
                                            <SelectItem value="6">Latest 6 enrolled</SelectItem>
                                            <SelectItem value="10">Latest 10 enrolled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </FramePanel>
                        </Frame>

                        <Frame spacing="sm">
                            <FrameHeader>
                                <FrameTitle className="text-xs font-semibold tracking-wide uppercase">Visual modules</FrameTitle>
                            </FrameHeader>
                            <FramePanel className="divide-y p-0">
                                {[
                                    {
                                        key: "showPipelineStrip" as const,
                                        title: "Term pipeline ribbon",
                                        desc: "Proportional progress bar, stage filter pills, unpaid balance, and applicants link.",
                                    },
                                    {
                                        key: "showDepartmentChips" as const,
                                        title: "Department scope chips",
                                        desc: "Quick department pills generated from current-term enrollments.",
                                    },
                                    {
                                        key: "showTuitionBars" as const,
                                        title: "Tuition settlement bars",
                                        desc: "Visual payment progress micro-bars inside table rows and board cards.",
                                    },
                                    {
                                        key: "collapseCompletedLane" as const,
                                        title: "Keep completed lane compact on Board",
                                        desc: "Focus visual space on active bottlenecks while summarizing enrolled records.",
                                    },
                                ].map((item) => (
                                    <div key={item.key} className="flex items-center justify-between gap-3 px-3.5 py-3">
                                        <div className="min-w-0">
                                            <p className="text-xs font-medium">{item.title}</p>
                                            <p className="text-muted-foreground text-[11px]">{item.desc}</p>
                                        </div>
                                        <Switch
                                            checked={preferences[item.key]}
                                            onCheckedChange={(checked) => updatePref(item.key, checked)}
                                            aria-label={item.title}
                                        />
                                    </div>
                                ))}
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    {/* TAB 2: ACTIVE PIPELINE & INLINE CONFIGURATOR */}
                    <TabsContent value="pipeline" className="mt-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <Frame spacing="sm">
                            <FrameHeader className="flex-row items-center justify-between gap-2">
                                <div>
                                    <FrameTitle className="text-xs font-semibold tracking-wide uppercase">Active workflow engine</FrameTitle>
                                    <FrameDescription className="text-xs">
                                        {isPolicyEngine
                                            ? `${pipelineMeta?.policy_name ?? "Published Enrollment Policy"} · v${pipelineMeta?.policy_version ?? 1}`
                                            : "Legacy sequential pipeline stored in system settings"}
                                    </FrameDescription>
                                </div>
                                <Badge variant={isPolicyEngine ? "primary-light" : "secondary"} radius="full" size="lg">
                                    {isPolicyEngine ? "Dynamic Policy v1" : "Legacy Pipeline"}
                                </Badge>
                            </FrameHeader>
                            <FramePanel className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    {isPolicyEngine
                                        ? `Managing ${pipelineMeta?.active_policies_count ?? 1} active policy blueprint(s). Use the Blueprint Studio for visual node transitions, rules, billing strategies, and simulations.`
                                        : "Edit stages below for fast changes, or open the full Blueprint Studio to configure dynamic rules, branching transitions, and email templates."}
                                </p>
                                <Link
                                    href={configureUrl}
                                    className={buttonVariants({ variant: "outline", size: "xs", className: "shrink-0 gap-1.5 text-xs" })}
                                >
                                    Blueprint Studio
                                    <ArrowUpRight className="size-3.5" />
                                </Link>
                            </FramePanel>
                        </Frame>

                        {isPolicyEngine || !canConfigurePipeline ? (
                            <Frame spacing="sm">
                                <FrameHeader>
                                    <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                                        Configured stages ({pipeline.steps.length})
                                    </FrameTitle>
                                </FrameHeader>
                                <FramePanel className="divide-y p-0">
                                    {pipeline.steps.map((step, index) => {
                                        const tone = resolvePipelineColor(step.color);
                                        return (
                                            <div key={step.key || step.status} className="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                                <div className="flex min-w-0 items-center gap-2.5">
                                                    <span className="text-muted-foreground w-4 font-mono text-xs">{index + 1}.</span>
                                                    <span className={cn("size-2 shrink-0 rounded-full", tone.dot)} aria-hidden="true" />
                                                    <div className="min-w-0">
                                                        <p className="truncate text-xs font-semibold">{step.label || step.status}</p>
                                                        <p className="text-muted-foreground truncate text-[11px]">
                                                            Status: {step.status} · {(step.action_type ?? "standard").replace(/_/g, " ")}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    {index === 0 && (
                                                        <Badge variant="outline" size="xs" radius="full">
                                                            Entry
                                                        </Badge>
                                                    )}
                                                    {step.is_completion && (
                                                        <Badge variant="success-light" size="xs" radius="full">
                                                            Completion
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </FramePanel>
                            </Frame>
                        ) : (
                            <form id="inline-pipeline-form" onSubmit={handleSaveLegacyPipeline} className="space-y-3">
                                <Alert variant="info">
                                    <Workflow className="size-4" />
                                    <AlertTitle>Quick Pipeline Editor</AlertTitle>
                                    <AlertDescription className="text-xs">
                                        Changes immediately adapt the Pipeline Ribbon, Table filters, Kanban lanes, and Dossier Stepper.
                                    </AlertDescription>
                                </Alert>

                                <div className="space-y-1.5">
                                    <Label htmlFor="cfg-submitted-label" className="text-xs">
                                        Submitted stage label
                                    </Label>
                                    <Input
                                        id="cfg-submitted-label"
                                        value={submittedLabel}
                                        onChange={(e) => setSubmittedLabel(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                <div className="space-y-2.5">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-xs font-semibold">Pipeline stages ({steps.length})</Label>
                                        <Button type="button" variant="outline" size="xs" className="h-7 gap-1 text-xs" onClick={handleAddStep}>
                                            <Plus className="size-3" />
                                            Add stage
                                        </Button>
                                    </div>

                                    {steps.map((step, index) => {
                                        const tone = resolvePipelineColor(step.color);
                                        return (
                                            <Frame key={`${step.key}-${index}`} spacing="xs">
                                                <FramePanel className="space-y-2.5 p-3">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <div className="flex items-center gap-2">
                                                            <span className={cn("size-2 rounded-full", tone.dot)} aria-hidden="true" />
                                                            <span className="text-xs font-semibold">Stage {index + 1}</span>
                                                            {index === 0 && (
                                                                <Badge variant="outline" size="xs" radius="full">
                                                                    Entry
                                                                </Badge>
                                                            )}
                                                            {index === steps.length - 1 && (
                                                                <Badge variant="success-light" size="xs" radius="full">
                                                                    Completion
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-0.5">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon-xs"
                                                                disabled={index === 0}
                                                                onClick={() => handleMoveStep(index, -1)}
                                                                title="Move up"
                                                            >
                                                                <ArrowUp className="size-3" />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon-xs"
                                                                disabled={index === steps.length - 1}
                                                                onClick={() => handleMoveStep(index, 1)}
                                                                title="Move down"
                                                            >
                                                                <ArrowDown className="size-3" />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon-xs"
                                                                disabled={steps.length <= 1}
                                                                onClick={() => handleRemoveStep(index)}
                                                                className="text-muted-foreground hover:text-destructive"
                                                                title="Remove stage"
                                                            >
                                                                <Trash2 className="size-3" />
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-2">
                                                        <div className="space-y-1">
                                                            <Label className="text-[11px]">Status value</Label>
                                                            <Input
                                                                value={step.status}
                                                                onChange={(e) => handleStepChange(index, "status", e.target.value)}
                                                                placeholder="e.g. Pending"
                                                                className="h-7 text-xs"
                                                            />
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label className="text-[11px]">Display label</Label>
                                                            <Input
                                                                value={step.label}
                                                                onChange={(e) => handleStepChange(index, "label", e.target.value)}
                                                                placeholder="e.g. Department Review"
                                                                className="h-7 text-xs"
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-2">
                                                        <div className="space-y-1">
                                                            <Label className="text-[11px]">Action behavior</Label>
                                                            <Select
                                                                value={step.action_type}
                                                                onValueChange={(v) =>
                                                                    handleStepChange(index, "action_type", v as EditablePipelineStep["action_type"])
                                                                }
                                                            >
                                                                <SelectTrigger className="h-7 text-xs">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {ACTION_TYPE_OPTIONS.map((opt) => (
                                                                        <SelectItem key={opt.value} value={opt.value}>
                                                                            {opt.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label className="text-[11px]">Stage color</Label>
                                                            <Select value={step.color} onValueChange={(v) => handleStepChange(index, "color", v)}>
                                                                <SelectTrigger className="h-7 text-xs">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {COLOR_OPTIONS.map((opt) => (
                                                                        <SelectItem key={opt.value} value={opt.value}>
                                                                            {opt.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                </FramePanel>
                                            </Frame>
                                        );
                                    })}
                                </div>

                                <Button type="submit" size="sm" disabled={savingPipeline} className="w-full gap-1.5">
                                    {savingPipeline ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                    Save pipeline configuration
                                </Button>
                            </form>
                        )}
                    </TabsContent>
                </Tabs>

                <SheetFooter className="border-border/70 bg-card/60 shrink-0 flex-row items-center justify-between gap-2 border-t px-5 py-3">
                    <Button type="button" variant="ghost" size="sm" className="text-muted-foreground gap-1.5 text-xs" onClick={onResetPreferences}>
                        <RotateCcw className="size-3.5" />
                        Reset workspace prefs
                    </Button>
                    <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)}>
                        Done
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
