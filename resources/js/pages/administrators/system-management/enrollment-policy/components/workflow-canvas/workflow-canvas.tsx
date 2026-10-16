import { Badge } from "@/components/reui/badge";
import { Frame, FrameHeader, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
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
import { ArrowDownUp, ArrowRightLeft, Flag, MailCheck, Maximize2, PanelLeft, Plus, RefreshCw, Route } from "lucide-react";
import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import type { Action, Transition, WorkflowStep } from "../../types";
import { OptionSelect } from "../schema-fields";
import { ActionInspectorSheet } from "./action-inspector-sheet";
import { EdgeInspectorDialog } from "./edge-inspector-dialog";
import { TransitionEdge } from "./edges/transition-edge";

import { computeAutoLayout } from "./layout";
import { NodeInspectorSheet } from "./node-inspector-sheet";
import { StepNode } from "./nodes/step-node";
import { TriggerNode } from "./nodes/trigger-node";
import type { LayoutDirection, WorkflowCanvasProps, WorkflowEdge, WorkflowNode } from "./types";
import { validateWorkflowGraph, workflowToGraph } from "./workflow-graph-adapter";
import { appendActions, appendTransition, canConnectSteps, removeStep, replaceTransition } from "./workflow-model";
import { PALETTE_TEMPLATES, WorkflowPalette, type PaletteTemplate } from "./workflow-palette";

const nodeTypes = {
    stepNode: StepNode,
    triggerNode: TriggerNode,
};

const edgeTypes = {
    transitionEdge: TransitionEdge,
};
const NO_ENTRY_RULES: NonNullable<WorkflowCanvasProps["entryRules"]> = [];

function uniqueKey(seed: string, steps: WorkflowStep[]): string {
    const existing = new Set(steps.map((s) => s.key));
    let candidate = seed;
    let counter = 2;
    while (existing.has(candidate)) candidate = `${seed}_${counter++}`;
    return candidate;
}

function WorkflowCanvasInner({
    steps,
    actions,
    rules,
    options,
    entryRules = NO_ENTRY_RULES,
    readOnly = false,
    onChange,
    onEditEmailTemplate,
}: WorkflowCanvasProps) {
    const { fitView, setViewport } = useReactFlow();
    const canvasRef = useRef<HTMLDivElement>(null);
    const [direction, setDirection] = useState<LayoutDirection>(() =>
        typeof window !== "undefined" && window.matchMedia("(max-width: 640px)").matches ? "TB" : "LR",
    );
    const [selectedStepKey, setSelectedStepKey] = useState<string | null>(null);
    const [inspectorOpen, setInspectorOpen] = useState(false);
    const [actionInspectorOpen, setActionInspectorOpen] = useState(false);
    const [inspectingAction, setInspectingAction] = useState<{
        stepKey: string;
        actionIndex: number;
    } | null>(null);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [pendingRouteSource, setPendingRouteSource] = useState("");

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
        setSelectedStepKey(parentStepKey);
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
            onChange(removeStep(steps, stepKey));
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
                entryRules,
                readOnly,
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
                setTimeout(() => {
                    const trigger = layoutedNodes.find((node) => node.type === "triggerNode");
                    const width = canvasRef.current?.clientWidth ?? 0;
                    if (trigger && width > 0 && width < 640) {
                        setViewport({ x: (width - 360 * 0.8) / 2 - trigger.position.x * 0.8, y: 72 - trigger.position.y * 0.8, zoom: 0.8 });
                    } else {
                        fitView({ padding: 0.15, maxZoom: 0.9 });
                    }
                }, 50);
            }
        },
        [
            actions,
            rules,
            options.roles,
            entryRules,
            readOnly,
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
            setViewport,
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
        fitView({ padding: 0.15, maxZoom: 0.9 });
    };

    // User draws an edge between nodes
    const onConnect = useCallback(
        (connection: Connection) => {
            if (readOnly || !connection.source || !connection.target || !canConnectSteps(steps, connection.source, connection.target)) return;

            const sourceStep = steps.find((s) => s.key === connection.source);
            if (!sourceStep || sourceStep.terminal) return;

            // Check if connection already exists
            const alreadyExists = sourceStep.transitions.some((t) => t.to === connection.target);
            if (alreadyExists) return;

            const hasFallback = sourceStep.transitions.some((t) => t.fallback);
            const targetStep = steps.find((s) => s.key === connection.target);

            const newTransition: Transition = {
                key: `trans_${sourceStep.key}_${connection.target}`,
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
                    transitions: appendTransition(s.transitions, newTransition),
                };
            });

            onChange(nextSteps);
        },
        [steps, onChange, readOnly],
    );

    const addTemplateNode = useCallback(
        (template: PaletteTemplate) => {
            if (readOnly) return;
            if (template.category === "subaction" && template.defaultActions) {
                // Attach sub-action to currently selected step, or the last non-terminal step, or the first step
                const targetKey = selectedStepKey ?? steps.find((s) => !s.terminal)?.key ?? steps[0]?.key;
                if (!targetKey) return;

                const actionsToAdd = JSON.parse(JSON.stringify(template.defaultActions));
                const nextSteps = steps.map((s) => {
                    if (s.key !== targetKey) return s;
                    return {
                        ...s,
                        actions: appendActions(s.actions, actionsToAdd),
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
        [steps, onChange, selectedStepKey, readOnly],
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
                transitions: replaceTransition(s.transitions, updatedTransition),
            };
        });
        onChange(nextSteps);
    };

    const { issues } = useMemo(() => validateWorkflowGraph(steps), [steps]);
    const errorCount = issues.filter((i) => i.severity === "error").length;
    const warningCount = issues.filter((i) => i.severity === "warning").length;

    return (
        <Frame spacing="default" className="min-w-0" data-workflow-canvas>
            <FrameHeader>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-col gap-1">
                        <FrameTitle>Execution map</FrameTitle>
                        <p className="text-muted-foreground text-xs">
                            Submission runs entry actions. Each route waits for staff, then runs destination actions in order.
                        </p>
                    </div>
                    <Badge variant="outline">{readOnly ? "Read only" : "Draft editor"}</Badge>
                </div>
            </FrameHeader>
            <div className="relative flex h-[780px] w-full min-w-0 overflow-hidden" onDragOver={onDragOver} onDrop={onDrop}>
                {/* Overlay Draggable Component Palette (Floats without squeezing the canvas) */}
                {paletteOpen && <WorkflowPalette onAddTemplate={(tmpl) => addTemplateNode(tmpl)} onClose={() => setPaletteOpen(false)} />}

                {/* Full-Width Interactive Flow Canvas */}
                <div ref={canvasRef} className="relative h-full w-full min-w-0 flex-1">
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={nodeTypes}
                        edgeTypes={edgeTypes}
                        onNodesChange={onNodesChange}
                        onEdgesChange={onEdgesChange}
                        onConnect={onConnect}
                        onNodeClick={(_, node) => {
                            if (!readOnly && node.type !== "triggerNode") {
                                handleEditStep(node.id);
                            }
                        }}
                        fitView
                        fitViewOptions={{ padding: 0.15, minZoom: 0.65, maxZoom: 0.9 }}
                        minZoom={0.4}
                        maxZoom={1.5}
                        nodesConnectable={!readOnly}
                        deleteKeyCode={null}
                        snapToGrid
                        snapGrid={[16, 16]}
                        defaultEdgeOptions={{ type: "transitionEdge" }}
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
                            nodeColor={(node) => (node.type === "triggerNode" ? "var(--primary)" : "var(--muted-foreground)")}
                            zoomable
                            pannable
                            className="!border-border/60 !bg-card/85 hidden !h-20 !w-32 !rounded-xl !border !shadow-sm sm:block"
                        />

                        {/* Toolbar scrolls inside canvas on narrow screens. */}
                        <Panel position="top-left" className="!inset-x-0 !top-0 !m-0 max-w-full overflow-x-auto">
                            <div className="bg-card flex w-max items-center gap-2 border-b p-2 whitespace-nowrap [&_button]:text-xs">
                                <Button
                                    type="button"
                                    disabled={readOnly}
                                    variant="ghost"
                                    size="sm"
                                    aria-pressed={paletteOpen}
                                    onClick={() => setPaletteOpen(!paletteOpen)}
                                    title={paletteOpen ? "Close catalog" : "Open catalog of draggable blocks"}
                                >
                                    <PanelLeft />
                                    <span>Blocks</span>
                                </Button>

                                <div className="bg-border/60 mx-0.5 h-3.5 w-px" />

                                <Button
                                    type="button"
                                    disabled={readOnly}
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        const template = PALETTE_TEMPLATES[0];
                                        addTemplateNode(template);
                                    }}
                                    title="Quickly add an approval step"
                                >
                                    <Plus /> Step
                                </Button>

                                <Button
                                    type="button"
                                    disabled={readOnly}
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        const terminalTemplate =
                                            PALETTE_TEMPLATES.find((t) => t.type === "terminal_completed") ?? PALETTE_TEMPLATES[6];
                                        addTemplateNode(terminalTemplate);
                                    }}
                                    title="Quickly add a terminal outcome"
                                >
                                    <Flag /> Outcome
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={readOnly || steps.length < 2}
                                    onClick={() => {
                                        setPendingRouteSource(selectedStepKey ?? steps.find((step) => !step.terminal)?.key ?? "");
                                        setEdgeModalOpen(true);
                                        setEditingEdge(null);
                                    }}
                                >
                                    <Route /> Route
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={readOnly || !onEditEmailTemplate}
                                    onClick={() => onEditEmailTemplate?.(selectedStep?.key ?? steps[0]?.key ?? "")}
                                    title="Open the email template designer"
                                >
                                    <MailCheck /> Emails
                                </Button>

                                <div className="bg-border/60 mx-0.5 h-3.5 w-px" />

                                <Button type="button" variant="ghost" size="sm" onClick={handleAutoLayout} title="Auto align nodes via Dagre">
                                    <RefreshCw /> Auto Align
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={toggleDirection}
                                    title={`Toggle orientation (Currently ${direction === "LR" ? "Horizontal" : "Vertical"})`}
                                >
                                    {direction === "LR" ? (
                                        <>
                                            <ArrowRightLeft /> Horizontal
                                        </>
                                    ) : (
                                        <>
                                            <ArrowDownUp /> Vertical
                                        </>
                                    )}
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={handleFitView}
                                    title="Zoom to fit all nodes"
                                    aria-label="Fit workflow"
                                >
                                    <Maximize2 />
                                </Button>

                                <div className="bg-border/60 mx-0.5 h-3.5 w-px" />

                                {/* Graph Health Popover */}
                                <Popover>
                                    {/* Installed Popover is Radix; its trigger uses asChild even with Base UI buttons. */}
                                    <PopoverTrigger asChild>
                                        <Button type="button" variant="ghost" size="sm">
                                            {errorCount === 0 && warningCount === 0 ? (
                                                <>
                                                    <Badge variant="success-light">Graph checked</Badge>
                                                </>
                                            ) : (
                                                <>
                                                    <Badge variant="warning-light">
                                                        {errorCount + warningCount} alert{errorCount + warningCount > 1 ? "s" : ""}
                                                    </Badge>
                                                </>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="flex w-80 flex-col gap-3 text-xs">
                                        <div className="flex items-center justify-between border-b pb-2">
                                            <span className="text-foreground font-semibold">Graph Health Check</span>
                                            <Badge variant="outline">{steps.length} stages</Badge>
                                        </div>

                                        {issues.length === 0 ? (
                                            <p className="text-muted-foreground leading-relaxed">
                                                Structure checked only. Test Run checks policy rules; payment, class capacity, and action payloads are
                                                enforced at runtime.
                                            </p>
                                        ) : (
                                            <div className="flex max-h-48 flex-col gap-3 overflow-y-auto">
                                                {issues.map((issue, idx) => (
                                                    <div key={idx} className="flex flex-col gap-1">
                                                        <Badge variant={issue.severity === "error" ? "destructive-light" : "warning-light"}>
                                                            {issue.severity}
                                                        </Badge>
                                                        <span className="text-muted-foreground leading-tight">{issue.message}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </Panel>
                    </ReactFlow>
                    {steps.length === 0 && (
                        <div className="bg-background absolute inset-0 flex flex-col items-center justify-center gap-3 text-sm">
                            <p>No enrollment stages configured.</p>
                            {!readOnly && (
                                <Button type="button" onClick={() => addTemplateNode(PALETTE_TEMPLATES[0])}>
                                    Add entry stage
                                </Button>
                            )}
                        </div>
                    )}
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
                    onEditEmailTemplate={onEditEmailTemplate}
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
                    onDelete={(source, key) => {
                        handleDeleteTransition(source, key);
                        setEdgeModalOpen(false);
                    }}
                />
                <Dialog open={edgeModalOpen && !editingEdge} onOpenChange={setEdgeModalOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Connect stages</DialogTitle>
                            <DialogDescription>Choose a source, then a destination. Conditional routes stay before Otherwise.</DialogDescription>
                        </DialogHeader>
                        <label className="flex flex-col gap-2 text-sm">
                            From stage
                            <OptionSelect
                                value={pendingRouteSource}
                                onChange={setPendingRouteSource}
                                options={steps.filter((step) => !step.terminal).map((step) => ({ value: step.key, label: step.label }))}
                                placeholder="Source stage"
                                allowEmpty={false}
                            />
                        </label>
                        <div className="flex flex-col gap-2">
                            {steps
                                .filter((step) => canConnectSteps(steps, pendingRouteSource, step.key))
                                .map((step) => (
                                    <Button
                                        key={step.key}
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            onConnect({ source: pendingRouteSource, target: step.key, sourceHandle: null, targetHandle: null });
                                            setEdgeModalOpen(false);
                                        }}
                                    >
                                        To {step.label}
                                    </Button>
                                ))}
                        </div>
                        <Button type="button" variant="ghost" onClick={() => setEdgeModalOpen(false)}>
                            Cancel
                        </Button>
                    </DialogContent>
                </Dialog>
            </div>
        </Frame>
    );
}

export function WorkflowCanvas(props: WorkflowCanvasProps) {
    return (
        <ReactFlowProvider>
            <WorkflowCanvasInner {...props} />
        </ReactFlowProvider>
    );
}
