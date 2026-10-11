import type { Edge, Node } from "@xyflow/react";
import type { Action, Option, RegistryItem, Transition, WorkflowStep } from "../types";

export type LayoutDirection = "LR" | "TB";

export type StepNodeData = {
    step: WorkflowStep;
    index: number;
    isEntry: boolean;
    isTerminal: boolean;
    roleLabels: string[];
    actionLabels: string[];
    validationErrors: string[];
    direction: LayoutDirection;
    onEdit: (stepKey: string) => void;
    onDelete: (stepKey: string) => void;
    onAttachAction?: (stepKey: string, actionHandler: string) => void;
};

export type TerminalNodeData = {
    step: WorkflowStep;
    index: number;
    outcome: "completed" | "rejected" | "cancelled";
    direction: LayoutDirection;
    onEdit: (stepKey: string) => void;
    onDelete: (stepKey: string) => void;
    onAttachAction?: (stepKey: string, actionHandler: string) => void;
};

export type ActionNodeData = {
    parentStepKey: string;
    actionIndex: number;
    totalActions: number;
    action: Action;
    handlerItem?: RegistryItem;
    summary: string;
    iconType: string;
    onEdit: (parentStepKey: string, actionIndex: number) => void;
    onDelete: (parentStepKey: string, actionIndex: number) => void;
    onReorder: (parentStepKey: string, fromIndex: number, toIndex: number) => void;
};

export type TransitionEdgeData = {
    transition: Transition;
    sourceKey: string;
    targetKey: string;
    isFallback: boolean;
    ruleLabel?: string;
    onEdit: (sourceKey: string, transitionKey: string) => void;
    onDelete: (sourceKey: string, transitionKey: string) => void;
};

export type ActionEdgeData = {
    parentStepKey: string;
    actionKey: string;
    label?: string;
    isChained?: boolean;
};

export type WorkflowNode = Node<StepNodeData, "stepNode"> | Node<TerminalNodeData, "terminalNode"> | Node<ActionNodeData, "actionNode">;

export type WorkflowEdge = Edge<TransitionEdgeData, "transitionEdge"> | Edge<ActionEdgeData, "actionEdge">;

export type GraphValidationError = {
    nodeKey?: string;
    message: string;
    severity: "warning" | "error";
};

export type WorkflowCanvasProps = {
    steps: WorkflowStep[];
    actions: Record<string, RegistryItem>;
    rules: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onChange: (steps: WorkflowStep[]) => void;
    /** Navigates to the full-page email template editor for a workflow step. */
    onEditEmailTemplate?: (stepKey: string) => void;
};
