import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
    Background,
    BackgroundVariant,
    Connection,
    Controls,
    MiniMap,
    Panel,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
    useReactFlow,
} from "@xyflow/react";
import {
    AlertTriangle,
    ArrowDownUp,
    ArrowRightLeft,
    CheckCircle2,
    Flag,
    HelpCircle,
    MailCheck,
    Maximize2,
    PanelLeft,
    Plus,
    RefreshCw,
    ShieldAlert,
} from "lucide-react";
import React, { useCallback, useEffect, useMemo, useState } from "react";
import type { Action, Transition, WorkflowStep } from "../../types";
import { ActionInspectorSheet } from "./action-inspector-sheet";
import { EdgeInspectorDialog } from "./edge-inspector-dialog";
import { ActionEdge } from "./edges/action-edge";
import { TransitionEdge } from "./edges/transition-edge";
import { EmailTemplateEditorDialog } from "./email-template-editor-dialog";
import { computeAutoLayout } from "./layout";
import { NodeInspectorSheet } from "./node-inspector-sheet";
import { ActionNode } from "./nodes/action-node";
import { StepNode } from "./nodes/step-node";
import { TerminalNode } from "./nodes/terminal-node";
import type { LayoutDirection, WorkflowCanvasProps, WorkflowEdge, WorkflowNode } from "./types";
import { validateWorkflowGraph, workflowToGraph } from "./workflow-graph-adapter";
import { PALETTE_TEMPLATES, WorkflowPalette, type PaletteTemplate } from "./workflow-palette";

const nodeTypes = {
    stepNode: StepNode,
    terminalNode: TerminalNode,
    actionNode: ActionNode,
};

const edgeTypes = {
    transitionEdge: TransitionEdge,
    actionEdge: ActionEdge,
};

function uniqueKey(seed: string, steps: WorkflowStep[]): string {
    const existing = new Set(steps.map((s) => s.key));
    let candidate = seed;
    let counter = 2;
    while (existing.has(candidate)) candidate = `${seed}_${counter++}`;
    return candidate;
}

function WorkflowCanvasInner({ steps, actions, rules, options, onChange }: WorkflowCanvasProps) {
    const { fitView } = useReactFlow();
    const [direction, setDirection] = useState<LayoutDirection>("LR");
    const [selectedStepKey, setSelectedStepKey] = useState<string | null>(null);
    const [inspectorOpen, setInspectorOpen] = useState(false);
    const [actionInspectorOpen, setActionInspectorOpen] = useState(false);
    const [globalEmailEditorOpen, setGlobalEmailEditorOpen] = useState(false);
    const [inspectingAction, setInspectingAction] = useState<{
        stepKey: string;
        actionIndex: number;
    } | null>(null);
    const [paletteOpen, setPaletteOpen] = useState(false);

    const [edgeModalOpen, setEdgeModalOpen] = useState(false);
    const [editingEdge, setEditingEdge] = useState<{
        sourceStepKey: string;
        transitionKey: string;
    } | null>(null);

    const [nodes, setNodes, onNodesChange] = useNodesState<WorkflowNode>([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState<WorkflowEdge>([]);

    const selectedStep = useMemo(() => steps.find((s) => s.key === selectedStepKey) ?? null, [steps, selectedStepKey]);

    const editingTransition = useMemo(() => {
        if (!editingEdge) return null;
        const source = steps.find((s) => s.key === editingEdge.sourceStepKey);
        return source?.transitions.find((t) => t.key === editingEdge.transitionKey) ?? null;
    }, [steps, editingEdge]);

    const editingSourceStep = useMemo(() => {
        if (!editingEdge) return null;
        return steps.find((s) => s.key === editingEdge.sourceStepKey) ?? null;
    }, [steps, editingEdge]);

    const handleEditStep = useCallback((stepKey: string) => {
        setActionInspectorOpen(false);
        setSelectedStepKey(stepKey);
        setInspectorOpen(true);
    }, []);

    const handleEditAction = useCallback((parentStepKey: string, actionIndex: number) => {
        setInspectorOpen(false);
        setInspectingAction({ stepKey: parentStepKey, actionIndex });
        setActionInspectorOpen(true);
    }, []);

    const handleDeleteAction = useCallback(
        (parentStepKey: string, actionIndex: number) => {
            const nextSteps = steps.map((s) => {
                if (s.key !== parentStepKey) return s;
                const nextActions = s.actions.filter((_, idx) => idx !== actionIndex);
                return { ...s, actions: nextActions };
            });
            onChange(nextSteps);
        },
        [steps, onChange],
    );

    const inspectingStep = useMemo(() => {
        if (!inspectingAction) return null;
        return steps.find((s) => s.key === inspectingAction.stepKey) ?? null;
    }, [steps, inspectingAction]);

    const handleUpdateAction = useCallback(
        (parentStepKey: string, actionIndex: number, updatedAction: Action) => {
            const nextSteps = steps.map((s) => {
                if (s.key !== parentStepKey) return s;
                const nextActions = [...s.actions];
                nextActions[actionIndex] = updatedAction;
                return { ...s, actions: nextActions };
            });
            onChange(nextSteps);
        },
        [steps, onChange],
    );

    const handleReorderAction = useCallback(
        (parentStepKey: string, fromIndex: number, toIndex: number) => {
            if (fromIndex < 0 || toIndex < 0) return;
            const nextSteps = steps.map((s) => {
                if (s.key !== parentStepKey) return s;
                const nextActions = [...s.actions];
                if (toIndex >= nextActions.length) return s;
                const [moved] = nextActions.splice(fromIndex, 1);
                nextActions.splice(toIndex, 0, moved);
                return { ...s, actions: nextActions };
            });
            onChange(nextSteps);

            if (inspectingAction && inspectingAction.stepKey === parentStepKey) {
                if (inspectingAction.actionIndex === fromIndex) {
                    setInspectingAction({ stepKey: parentStepKey, actionIndex: toIndex });
                } else if (fromIndex < toIndex && inspectingAction.actionIndex > fromIndex && inspectingAction.actionIndex <= toIndex) {
                    setInspectingAction({ stepKey: parentStepKey, actionIndex: inspectingAction.actionIndex - 1 });
                } else if (fromIndex > toIndex && inspectingAction.actionIndex >= toIndex && inspectingAction.actionIndex < fromIndex) {
                    setInspectingAction({ stepKey: parentStepKey, actionIndex: inspectingAction.actionIndex + 1 });
                }
            }
        },
        [steps, onChange, inspectingAction],
    );

    const handleDeleteStep = useCallback(
        (stepKey: string) => {
            if (steps.length <= 2) return;
            const nextSteps = steps
                .filter((s) => s.key !== stepKey)
                .map((step, idx) => ({
                    ...step,
                    entry: idx === 0,
                    transitions: step.transitions.filter((t) => t.to !== stepKey),
                }));
            onChange(nextSteps);
        },
        [steps, onChange],
    );

    const handleEditTransition = useCallback((sourceKey: string, transitionKey: string) => {
        setEditingEdge({ sourceStepKey: sourceKey, transitionKey });
        setEdgeModalOpen(true);
    }, []);

    const handleDeleteTransition = useCallback(
        (sourceKey: string, transitionKey: string) => {
            const nextSteps = steps.map((s) => {
                if (s.key !== sourceKey) return s;
                return {
                    ...s,
                    transitions: s.transitions.filter((t) => t.key !== transitionKey),
                };
            });
            onChange(nextSteps);
        },
        [steps, onChange],
    );

    const syncGraph = useCallback(
        (currentSteps: WorkflowStep[], currentDir: LayoutDirection, doFit = false) => {
            const { nodes: rawNodes, edges: rawEdges } = workflowToGraph({
                steps: currentSteps,
                roles: options.roles ?? [],
                actions,
                rules,
                direction: currentDir,
                onEditStep: handleEditStep,
                onDeleteStep: handleDeleteStep,
                onEditTransition: handleEditTransition,
                onDeleteTransition: handleDeleteTransition,
                onEditAction: handleEditAction,
                onDeleteAction: handleDeleteAction,
                onReorderAction: handleReorderAction,
            });

            const { nodes: layoutedNodes, edges: layoutedEdges } = computeAutoLayout(rawNodes, rawEdges, currentDir);

            setNodes(layoutedNodes);
            setEdges(layoutedEdges);

            if (doFit) {
                setTimeout(() => fitView({ padding: 0.28, duration: 400 }), 50);
            }
        },
        [
            actions,
            rules,
            options.roles,
            handleEditStep,
            handleDeleteStep,
            handleEditTransition,
            handleDeleteTransition,
            handleEditAction,
            handleDeleteAction,
            handleReorderAction,
            setNodes,
            setEdges,
            fitView,
        ],
    );

    // Initial load sync with fit view
    useEffect(() => {
        syncGraph(steps, direction, true);
    }, []);

    // Subsequent updates sync without resetting zoom
    useEffect(() => {
        syncGraph(steps, direction, false);
    }, [steps, direction, syncGraph]);

    const handleAutoLayout = () => {
        syncGraph(steps, direction, true);
    };

    const toggleDirection = () => {
        const next = direction === "LR" ? "TB" : "LR";
        setDirection(next);
        syncGraph(steps, next, true);
    };

    const handleFitView = () => {
        fitView({ padding: 0.28, duration: 350 });
    };

    // User draws an edge between nodes
    const onConnect = useCallback(
        (connection: Connection) => {
            if (!connection.source || !connection.target || connection.source === connection.target) return;

            const sourceStep = steps.find((s) => s.key === connection.source);
            if (!sourceStep || sourceStep.terminal) return;

            // Check if connection already exists
            const alreadyExists = sourceStep.transitions.some((t) => t.to === connection.target);
            if (alreadyExists) return;

            const hasFallback = sourceStep.transitions.some((t) => t.fallback);
            const targetStep = steps.find((s) => s.key === connection.target);

            const newTransition: Transition = {
                key: `trans_${sourceStep.key}_${connection.target}_${Date.now()}`,
                label: hasFallback ? `When path to ${targetStep?.label ?? connection.target}` : "Otherwise",
                to: connection.target,
                fallback: !hasFallback,
                requires_reason: false,
                conditions: [],
            };

            const nextSteps = steps.map((s) => {
                if (s.key !== sourceStep.key) return s;
                return {
                    ...s,
                    transitions: [...s.transitions, newTransition],
                };
            });

            onChange(nextSteps);
        },
        [steps, onChange],
    );

    const addTemplateNode = useCallback(
        (template: PaletteTemplate) => {
            if (template.category === "subaction" && template.defaultActions) {
                // Attach sub-action to currently selected step, or the last non-terminal step, or the first step
                const targetKey = selectedStepKey ?? steps.find((s) => !s.terminal)?.key ?? steps[0]?.key;
                if (!targetKey) return;

                const actionsToAdd = JSON.parse(JSON.stringify(template.defaultActions));
                const nextSteps = steps.map((s) => {
                    if (s.key !== targetKey) return s;
                    return {
                        ...s,
                        actions: [...s.actions, ...actionsToAdd],
                    };
                });
                onChange(nextSteps);
                setSelectedStepKey(targetKey);
                setInspectorOpen(true);
                return;
            }

            const isTerminal = template.category === "terminal";
            const seedKey = isTerminal ? `terminal_${template.outcome ?? "outcome"}` : template.type.replace(/_screening|_clearance|_evaluation/, "");
            const key = uniqueKey(seedKey, steps);

            const templateActions = template.defaultActions ? JSON.parse(JSON.stringify(template.defaultActions)) : [];

            if (isTerminal) {
                const newStep: WorkflowStep = {
                    key,
                    label: template.label,
                    entry: false,
                    terminal: true,
                    status: template.defaultStatus,
                    outcome: template.outcome ?? "completed",
                    actions: templateActions,
                    transitions: [],
                };
                onChange([...steps, newStep]);
            } else {
                const terminalIndex = steps.findIndex((s) => s.terminal);
                const insertAt = terminalIndex >= 0 ? terminalIndex : steps.length;
                const targetKey = steps[insertAt]?.key ?? key;

                const newStep: WorkflowStep = {
                    key,
                    label: template.label,
                    entry: steps.length === 0,
                    terminal: false,
                    status: template.defaultStatus,
                    actions: templateActions,
                    transitions:
                        targetKey !== key
                            ? [
                                  {
                                      key: `trans_${key}_${targetKey}`,
                                      label: "Otherwise",
                                      to: targetKey,
                                      fallback: true,
                                      requires_reason: false,
                                      conditions: [],
                                  },
                              ]
                            : [],
                };

                const nextSteps = [...steps];
                nextSteps.splice(insertAt, 0, newStep);
                onChange(nextSteps);
            }

            setSelectedStepKey(key);
            setInspectorOpen(true);
        },
        [steps, onChange],
    );

    // HTML5 Drag and drop handlers
    const onDragOver = useCallback((event: React.DragEvent) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
    }, []);

    const onDrop = useCallback(
        (event: React.DragEvent) => {
            event.preventDefault();
            const templateType = event.dataTransfer.getData("application/reactflow-template");
            if (!templateType) return;

            const template = PALETTE_TEMPLATES.find((t) => t.type === templateType);
            if (!template) return;

            addTemplateNode(template);
        },
        [addTemplateNode],
    );

    const handleSaveStep = (updatedStep: WorkflowStep) => {
        const nextSteps = steps.map((s) => (s.key === updatedStep.key ? updatedStep : s));
        onChange(nextSteps);
    };

    const handleSaveTransition = (sourceStepKey: string, updatedTransition: Transition) => {
        const nextSteps = steps.map((s) => {
            if (s.key !== sourceStepKey) return s;
            return {
                ...s,
                transitions: s.transitions.map((t) => (t.key === updatedTransition.key ? updatedTransition : t)),
            };
        });
        onChange(nextSteps);
    };

    const { issues } = useMemo(() => validateWorkflowGraph(steps), [steps]);
    const errorCount = issues.filter((i) => i.severity === "error").length;
    const warningCount = issues.filter((i) => i.severity === "warning").length;

    return (
        <div
            className="border-border/70 bg-card/40 relative flex h-[780px] w-full overflow-hidden rounded-2xl border shadow-xs backdrop-blur-xs"
            onDragOver={onDragOver}
            onDrop={onDrop}
        >
            {/* Overlay Draggable Component Palette (Floats without squeezing the canvas) */}
            {paletteOpen && <WorkflowPalette onAddTemplate={(tmpl) => addTemplateNode(tmpl)} onClose={() => setPaletteOpen(false)} />}

            {/* Full-Width Interactive Flow Canvas */}
            <div className="relative h-full w-full min-w-0 flex-1">
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    nodeTypes={nodeTypes}
                    edgeTypes={edgeTypes}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    onNodeClick={(_, node) => {
                        if (node.type === "actionNode") {
                            const actData = node.data as import("./types").ActionNodeData;
                            handleEditAction(actData.parentStepKey, actData.actionIndex);
                        } else {
                            handleEditStep(node.id);
                        }
                    }}
                    fitView
                    fitViewOptions={{ padding: 0.28 }}
                    minZoom={0.25}
                    maxZoom={1.5}
                    snapToGrid
                    snapGrid={[16, 16]}
                    defaultEdgeOptions={{ type: "transitionEdge" }}
                    proOptions={{ hideAttribution: true }}
                    className="bg-dot-pattern"
                >
                    <Background variant={BackgroundVariant.Dots} gap={24} size={1} className="opacity-30" />

                    <Controls
                        position="bottom-left"
                        showInteractive={false}
                        className="!bg-card/90 !border-border/60 [&>button]:!border-border/40 overflow-hidden !rounded-xl !shadow-sm"
                    />

                    <MiniMap
                        position="bottom-right"
                        nodeStrokeWidth={2}
                        nodeColor={(node) => {
                            if (node.type === "terminalNode") return "var(--primary)";
                            return "var(--muted-foreground)";
                        }}
                        zoomable
                        pannable
                        className="!border-border/60 !bg-card/85 hidden !h-20 !w-32 !rounded-xl !border !shadow-sm sm:block"
                    />

                    {/* Clean Centered Floating Pill Toolbar (Fixed nowrap: never wraps over nodes!) */}
                    <Panel position="top-center" className="m-3">
                        <div className="border-border/70 bg-card/95 flex items-center gap-1.5 rounded-full border px-2 py-1 whitespace-nowrap shadow-md backdrop-blur-md">
                            <Button
                                type="button"
                                variant={paletteOpen ? "secondary" : "ghost"}
                                size="sm"
                                className="h-7 gap-1.5 rounded-full px-2.5 text-xs font-semibold"
                                onClick={() => setPaletteOpen(!paletteOpen)}
                                title={paletteOpen ? "Close catalog" : "Open catalog of draggable blocks"}
                            >
                                <PanelLeft className="size-3.5" />
                                <span>Blocks</span>
                            </Button>

                            <div className="bg-border/60 mx-0.5 h-3.5 w-px" />

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 gap-1 rounded-full px-2 text-xs font-medium"
                                onClick={() => {
                                    const template = PALETTE_TEMPLATES[0];
                                    addTemplateNode(template);
                                }}
                                title="Quickly add an approval step"
                            >
                                <Plus className="text-primary size-3.5" /> Step
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 gap-1 rounded-full px-2 text-xs font-medium"
                                onClick={() => {
                                    const terminalTemplate = PALETTE_TEMPLATES.find((t) => t.type === "terminal_completed") ?? PALETTE_TEMPLATES[6];
                                    addTemplateNode(terminalTemplate);
                                }}
                                title="Quickly add a terminal outcome"
                            >
                                <Flag className="size-3.5 text-emerald-600 dark:text-emerald-400" /> Outcome
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 gap-1 rounded-full px-2 text-xs font-medium"
                                onClick={() => setGlobalEmailEditorOpen(true)}
                                title="Open interactive email template designer"
                            >
                                <MailCheck className="size-3.5 text-sky-500" /> Emails
                            </Button>

                            <div className="bg-border/60 mx-0.5 h-3.5 w-px" />

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground hover:text-foreground h-7 gap-1 rounded-full px-2 text-xs font-medium"
                                onClick={handleAutoLayout}
                                title="Auto align nodes via Dagre"
                            >
                                <RefreshCw className="size-3" /> Auto Align
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground hover:text-foreground h-7 gap-1 rounded-full px-2 text-xs font-medium"
                                onClick={toggleDirection}
                                title={`Toggle orientation (Currently ${direction === "LR" ? "Horizontal" : "Vertical"})`}
                            >
                                {direction === "LR" ? (
                                    <>
                                        <ArrowRightLeft className="size-3" /> Horiz
                                    </>
                                ) : (
                                    <>
                                        <ArrowDownUp className="size-3" /> Vert
                                    </>
                                )}
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground hover:text-foreground size-7 rounded-full"
                                onClick={handleFitView}
                                title="Zoom to fit all nodes"
                            >
                                <Maximize2 className="size-3.5" />
                            </Button>

                            <div className="bg-border/60 mx-0.5 h-3.5 w-px" />

                            {/* Graph Health Popover */}
                            <Popover>
                                <PopoverTrigger asChild>
                                    <button
                                        type="button"
                                        className="bg-background/80 hover:bg-muted flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium shadow-2xs backdrop-blur-xs transition-colors"
                                    >
                                        {errorCount === 0 && warningCount === 0 ? (
                                            <>
                                                <CheckCircle2 className="size-3 text-emerald-600 dark:text-emerald-400" />
                                                <span className="text-foreground">Valid</span>
                                            </>
                                        ) : (
                                            <>
                                                <AlertTriangle className="size-3 text-amber-600 dark:text-amber-400" />
                                                <span className="text-foreground font-semibold">
                                                    {errorCount + warningCount} alert{errorCount + warningCount > 1 ? "s" : ""}
                                                </span>
                                            </>
                                        )}
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent className="w-80 space-y-2 p-3 text-xs">
                                    <div className="flex items-center justify-between border-b pb-2">
                                        <span className="text-foreground font-semibold">Graph Health Check</span>
                                        <Badge variant="outline" className="text-[10px]">
                                            {steps.length} Steps
                                        </Badge>
                                    </div>

                                    {issues.length === 0 ? (
                                        <p className="text-muted-foreground py-1 text-[11px] leading-relaxed">
                                            All steps have connected outgoing paths, fallback safety paths, and reach a final terminal outcome.
                                        </p>
                                    ) : (
                                        <div className="max-h-48 space-y-1.5 overflow-y-auto pr-1">
                                            {issues.map((issue, idx) => (
                                                <div
                                                    key={idx}
                                                    className="bg-muted/40 border-border/40 flex items-start gap-1.5 rounded-lg border p-1.5 text-[11px]"
                                                >
                                                    {issue.severity === "error" ? (
                                                        <ShieldAlert className="text-destructive mt-0.5 size-3.5 shrink-0" />
                                                    ) : (
                                                        <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-amber-500" />
                                                    )}
                                                    <span className="text-muted-foreground leading-tight">{issue.message}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </PopoverContent>
                            </Popover>

                            {/* Legend Popover */}
                            <Popover>
                                <PopoverTrigger asChild>
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-foreground hover:bg-muted flex size-6 cursor-pointer items-center justify-center rounded-full transition-colors"
                                        title="Flow notation legend"
                                    >
                                        <HelpCircle className="size-3.5" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent className="w-64 space-y-2 p-3 text-xs">
                                    <span className="text-foreground block border-b pb-1.5 font-semibold">Pipeline Notation</span>
                                    <div className="text-muted-foreground space-y-2 text-[11px]">
                                        <div className="flex items-center gap-2">
                                            <span className="size-2 rounded-full bg-emerald-500" />
                                            <span>Entry: Starting intake point</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="bg-primary size-2 rounded-full" />
                                            <span>Approval: Staff review & actions</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="size-2 rounded-full bg-indigo-500" />
                                            <span>Outcome: Matriculation terminal</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="bg-muted-foreground h-0.5 w-3.5" />
                                            <span>Solid line: Normal Otherwise path</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="border-primary h-0.5 w-3.5 border-b border-dashed" />
                                            <span>Dashed line: Conditional rule path</span>
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </Panel>
                </ReactFlow>
            </div>

            {/* Step Inspector Slide-out */}
            <NodeInspectorSheet
                open={inspectorOpen}
                onOpenChange={setInspectorOpen}
                step={selectedStep}
                steps={steps}
                actions={actions}
                rules={rules}
                options={options}
                onSave={handleSaveStep}
                onDelete={handleDeleteStep}
                onEditTransition={handleEditTransition}
                onOpenActionInspector={handleEditAction}
            />

            {/* Dedicated Sub-Action Inspector Slide-out */}
            <ActionInspectorSheet
                open={actionInspectorOpen}
                onOpenChange={setActionInspectorOpen}
                step={inspectingStep}
                actionIndex={inspectingAction?.actionIndex ?? null}
                actions={actions}
                options={options}
                onUpdateAction={handleUpdateAction}
                onDeleteAction={handleDeleteAction}
                onReorderAction={handleReorderAction}
            />

            {/* Transition Dialog */}
            <EdgeInspectorDialog
                open={edgeModalOpen}
                onOpenChange={setEdgeModalOpen}
                transition={editingTransition}
                sourceStep={editingSourceStep}
                steps={steps}
                rules={rules}
                options={options}
                onSave={handleSaveTransition}
            />

            {/* Interactive Email Template Editor Dialog from Toolbar */}
            <EmailTemplateEditorDialog
                open={globalEmailEditorOpen}
                onOpenChange={setGlobalEmailEditorOpen}
                step={selectedStep ?? steps[0] ?? null}
                action={(selectedStep ?? steps[0])?.actions.find((a) => a.handler === "enrollment.notify") ?? null}
                onSave={(updatedConfig) => {
                    const targetKey = selectedStep?.key ?? steps[0]?.key;
                    if (!targetKey) return;
                    const nextSteps = steps.map((s) => {
                        if (s.key !== targetKey) return s;
                        const existingIdx = s.actions.findIndex((a) => a.handler === "enrollment.notify");
                        const nextActions = [...s.actions];
                        if (existingIdx >= 0) {
                            nextActions[existingIdx] = {
                                ...nextActions[existingIdx],
                                configuration: {
                                    ...nextActions[existingIdx].configuration,
                                    ...updatedConfig,
                                },
                            };
                        } else {
                            nextActions.push({
                                key: `notify_${s.key}`,
                                handler: "enrollment.notify",
                                configuration: updatedConfig,
                            });
                        }
                        return { ...s, actions: nextActions };
                    });
                    onChange(nextSteps);
                }}
            />
        </div>
    );
}

export function WorkflowCanvas(props: WorkflowCanvasProps) {
    return (
        <ReactFlowProvider>
            <WorkflowCanvasInner {...props} />
        </ReactFlowProvider>
    );
}
