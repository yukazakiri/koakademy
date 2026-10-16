import { MarkerType } from "@xyflow/react";
import type { Option, RegistryItem, Rule, WorkflowStep } from "../../types";
import type { GraphValidationError, LayoutDirection, WorkflowEdge, WorkflowNode } from "./types";
import { validateWorkflowGraph } from "./workflow-model";

export { validateWorkflowGraph } from "./workflow-model";

export function workflowToGraph({
    steps,
    roles,
    actions,
    rules,
    direction,
    entryRules = [],
    readOnly = false,
    onEditStep,
    onDeleteStep,
    onEditTransition,
    onDeleteTransition,
    onEditAction,
    onReorderAction,
}: {
    steps: WorkflowStep[];
    roles: Option[];
    actions: Record<string, RegistryItem>;
    rules: Record<string, RegistryItem>;
    direction: LayoutDirection;
    entryRules?: Rule[];
    readOnly?: boolean;
    onEditStep: (key: string) => void;
    onDeleteStep: (key: string) => void;
    onEditTransition: (key: string, route: string) => void;
    onDeleteTransition: (key: string, route: string) => void;
    onEditAction: (key: string, index: number) => void;
    onDeleteAction: (key: string, index: number) => void;
    onReorderAction: (key: string, from: number, to: number) => void;
}): { nodes: WorkflowNode[]; edges: WorkflowEdge[]; validationIssues: GraphValidationError[] } {
    const { issues, nodeIssues } = validateWorkflowGraph(steps);
    const roleMap = new Map(roles.map((role) => [role.value, role.label]));
    const nodes: WorkflowNode[] = steps.map((step, index) => ({
        id: step.key,
        type: "stepNode",
        position: { x: 0, y: 0 },
        data: {
            step,
            index,
            isEntry: step.entry,
            isTerminal: step.terminal,
            roleLabels: (step.authorized_role_ids ?? []).map((id) => roleMap.get(id) ?? id),
            actionLabels: step.actions.map((action) => actions[action.handler]?.label ?? action.handler),
            validationErrors: nodeIssues[step.key] ?? [],
            direction,
            actions,
            readOnly,
            onEdit: onEditStep,
            onDelete: onDeleteStep,
            onEditAction,
            onReorderAction,
        },
    }));
    const edges: WorkflowEdge[] = [];
    const entry = steps.find((step) => step.entry);
    if (entry) {
        let triggerId = "trigger:submission";
        while (steps.some((step) => step.key === triggerId)) triggerId += ":trigger";
        const channelRules = entryRules.filter((rule) => rule.enabled !== false && rule.handler === "availability.channel");
        const channels = ["public", "administrator", "continuing", "api"].filter((channel) =>
            channelRules.every((rule) => !Array.isArray(rule.configuration.allowed) || rule.configuration.allowed.includes(channel)),
        );
        nodes.unshift({
            id: triggerId,
            type: "triggerNode",
            position: { x: 0, y: 0 },
            data: { direction, channels, ruleCount: entryRules.filter((rule) => rule.enabled !== false).length },
            connectable: false,
            deletable: false,
        });
        edges.push({
            id: `submission:${entry.key}`,
            source: triggerId,
            target: entry.key,
            type: "smoothstep",
            label: "On submission",
            deletable: false,
            markerEnd: { type: MarkerType.ArrowClosed, color: "var(--primary)" },
            style: { stroke: "var(--primary)" },
        });
    }
    for (const step of steps) {
        for (const [index, transition] of step.transitions.entries()) {
            const firstCondition = transition.conditions[0];
            edges.push({
                id: `route:${step.key}:${transition.key}:${index}`,
                source: step.key,
                target: transition.to,
                sourceHandle: "transition-source",
                type: "transitionEdge",
                deletable: false,
                markerEnd: { type: MarkerType.ArrowClosed, color: "var(--muted-foreground)" },
                data: {
                    transition,
                    sourceKey: step.key,
                    targetKey: transition.to,
                    isFallback: transition.fallback,
                    ruleLabel: firstCondition ? (rules[firstCondition.handler]?.label ?? firstCondition.handler) : undefined,
                    onEdit: readOnly ? () => {} : onEditTransition,
                    onDelete: readOnly ? () => {} : onDeleteTransition,
                    readOnly,
                },
            });
        }
    }
    return { nodes, edges, validationIssues: issues };
}
