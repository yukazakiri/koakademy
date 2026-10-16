import type { Edge, Node } from "@xyflow/react";
import type { Option, RegistryItem, Rule, Transition, WorkflowStep } from "../../types";

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
    actions: Record<string, RegistryItem>;
    onEditAction: (stepKey: string, actionIndex: number) => void;
    onReorderAction: (stepKey: string, fromIndex: number, toIndex: number) => void;
    readOnly: boolean;
};

export type TriggerNodeData = {
    direction: LayoutDirection;
    channels: string[];
    ruleCount: number;
};

export type TransitionEdgeData = {
    transition: Transition;
    sourceKey: string;
    targetKey: string;
    isFallback: boolean;
    ruleLabel?: string;
    onEdit: (sourceKey: string, transitionKey: string) => void;
    onDelete: (sourceKey: string, transitionKey: string) => void;
    readOnly: boolean;
};

export type WorkflowNode = Node<StepNodeData, "stepNode"> | Node<TriggerNodeData, "triggerNode">;

export type WorkflowEdge = Edge<TransitionEdgeData, "transitionEdge"> | SubmissionEdge;
export type SubmissionEdge = Edge<Record<string, never>, "smoothstep">;

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
    entryRules?: Rule[];
    readOnly?: boolean;
    onChange: (steps: WorkflowStep[]) => void;
    /** Navigates to the full-page email template editor for a workflow step. */
    onEditEmailTemplate?: (stepKey: string) => void;
};
