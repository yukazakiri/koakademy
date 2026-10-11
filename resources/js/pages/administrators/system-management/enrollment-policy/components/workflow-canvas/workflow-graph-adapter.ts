import { MarkerType } from "@xyflow/react";
import type { Action, Option, RegistryItem, WorkflowStep } from "../types";
import type { GraphValidationError, LayoutDirection, WorkflowEdge, WorkflowNode } from "./types";

function getActionSummary(action: Action): string {
    const cfg = (action.configuration ?? {}) as Record<string, unknown>;
    switch (action.handler) {
        case "enrollment.notify":
            if (cfg.notification === "assessment") return "Email: Assessment Certificate (PDF)";
            if (cfg.notification === "academic_verified") return "Email: Academic Clearance Notice";
            if (cfg.notification === "status_update") return "Email: Status Progression Notice";
            return `Email: ${String(cfg.notification ?? "Notice")}`;
        case "enrollment.calculate_tuition":
            return cfg.discount_percentage ? `Tuition: ${cfg.discount_percentage}% discount` : "Calculates tuition ledger";
        case "enrollment.assign_subjects":
            return cfg.allow_cross_program_subjects ? "Assigns cross-program curriculum" : "Assigns curriculum subjects";
        case "enrollment.assign_classes":
            return cfg.mode === "first_available" ? "First available class slot" : "Reserves selected class slots";
        case "enrollment.verify_payment":
            return cfg.receipt_mode === "required" ? "Receipt required, records ledger" : "Payment verification";
        case "enrollment.verify_academic":
            return String(cfg.status ?? "Verified by Dept Head");
        case "enrollment.sync_student":
            return `Syncs account (${String(cfg.attribute ?? "status")}: ${String(cfg.value ?? "enrolled")})`;
        case "enrollment.set_outcome":
            return `Sets outcome: ${String(cfg.outcome ?? "completed")}`;
        case "enrollment.generate_assessment":
            return "Generates PDF assessment file";
        case "enrollment.assign_additional_fees":
            return "Attaches submitted extra fees";
        default:
            return action.key;
    }
}

export function validateWorkflowGraph(steps: WorkflowStep[]): {
    issues: GraphValidationError[];
    nodeIssues: Record<string, string[]>;
} {
    const issues: GraphValidationError[] = [];
    const nodeIssues: Record<string, string[]> = {};

    const addNodeIssue = (key: string, msg: string, severity: "warning" | "error" = "warning") => {
        if (!nodeIssues[key]) nodeIssues[key] = [];
        nodeIssues[key].push(msg);
        issues.push({ nodeKey: key, message: msg, severity });
    };

    const stepKeys = new Set(steps.map((s) => s.key));
    const targetKeys = new Set<string>();
    const hasTerminal = steps.some((s) => s.terminal);

    if (!hasTerminal && steps.length > 0) {
        issues.push({
            message: "Workflow has no terminal step. Journey will have no completion outcome.",
            severity: "error",
        });
    }

    steps.forEach((step, index) => {
        // Collect targets
        for (const t of step.transitions) {
            targetKeys.add(t.to);

            if (!stepKeys.has(t.to)) {
                addNodeIssue(step.key, `Transition '${t.label}' points to unknown destination '${t.to}'.`, "error");
            }
        }

        // Dead end check
        if (!step.terminal && step.transitions.length === 0) {
            addNodeIssue(step.key, "Dead-end step: No outgoing path. Students will be stuck here.", "error");
        }

        // Conditional without fallback check
        const hasBranches = step.transitions.some((t) => !t.fallback);
        const hasFallback = step.transitions.some((t) => t.fallback);
        if (hasBranches && !hasFallback) {
            addNodeIssue(step.key, "Missing 'Otherwise' fallback path. Students not matching conditions will be trapped.", "warning");
        }

        // Unreachable check (except entry step 0)
        if (index > 0 && !targetKeys.has(step.key)) {
            // Check if any step transitions to this
            const hasInbound = steps.some((other) => other.transitions.some((t) => t.to === step.key));
            if (!hasInbound) {
                addNodeIssue(step.key, "Unreachable step: No upstream step routes here.", "warning");
            }
        }
    });

    // Action integrity checks for EnrollmentService compatibility
    const allActions = steps.flatMap((s) => s.actions);
    const hasAcademicVerification = allActions.some((a) => a.handler === "enrollment.verify_academic");
    const hasPaymentVerification = allActions.some((a) => a.handler === "enrollment.verify_payment");

    if (!hasAcademicVerification && steps.length > 1) {
        issues.push({
            message: "Missing 'Record academic verification' action. Head of Dept verification in EnrollmentService will not find a matching step.",
            severity: "warning",
        });
    }

    if (!hasPaymentVerification && steps.length > 1) {
        issues.push({
            message: "Missing 'Record payment verification' action. Cashier verification in EnrollmentService will not find a matching step.",
            severity: "warning",
        });
    }

    const completedTerminals = steps.filter((s) => s.terminal && s.outcome === "completed");
    for (const term of completedTerminals) {
        const hasSync = term.actions.some((a) => a.handler === "enrollment.sync_student");
        if (!hasSync) {
            addNodeIssue(
                term.key,
                "Missing 'Synchronize student/account state' action. Students will not be marked as Enrolled in the database upon completion.",
                "warning",
            );
        }
    }

    return { issues, nodeIssues };
}

export function workflowToGraph({
    steps,
    roles,
    actions,
    rules,
    direction,
    onEditStep,
    onDeleteStep,
    onEditTransition,
    onDeleteTransition,
    onEditAction,
    onDeleteAction,
    onReorderAction,
}: {
    steps: WorkflowStep[];
    roles: Option[];
    actions: Record<string, RegistryItem>;
    rules: Record<string, RegistryItem>;
    direction: LayoutDirection;
    onEditStep: (stepKey: string) => void;
    onDeleteStep: (stepKey: string) => void;
    onEditTransition: (sourceKey: string, transitionKey: string) => void;
    onDeleteTransition: (sourceKey: string, transitionKey: string) => void;
    onEditAction: (parentStepKey: string, actionIndex: number) => void;
    onDeleteAction: (parentStepKey: string, actionIndex: number) => void;
    onReorderAction: (parentStepKey: string, fromIndex: number, toIndex: number) => void;
}): { nodes: WorkflowNode[]; edges: WorkflowEdge[]; validationIssues: GraphValidationError[] } {
    const { issues, nodeIssues } = validateWorkflowGraph(steps);

    const roleMap = new Map(roles.map((r) => [r.value, r.label]));

    const nodes: WorkflowNode[] = steps.map((step, index) => {
        const stepValidationErrors = nodeIssues[step.key] ?? [];

        if (step.terminal) {
            const terminalNode: WorkflowNode = {
                id: step.key,
                type: "terminalNode",
                position: { x: 0, y: 0 },
                data: {
                    step,
                    index,
                    outcome: step.outcome ?? "completed",
                    direction,
                    onEdit: onEditStep,
                    onDelete: onDeleteStep,
                },
            };
            return terminalNode;
        }

        const roleLabels = (step.authorized_role_ids ?? []).map((id) => roleMap.get(id) ?? id).filter(Boolean);

        const actionLabels = step.actions.map((act) => actions[act.handler]?.label ?? act.handler).filter(Boolean);

        const stepNode: WorkflowNode = {
            id: step.key,
            type: "stepNode",
            position: { x: 0, y: 0 },
            data: {
                step,
                index,
                isEntry: step.entry,
                isTerminal: false,
                roleLabels,
                actionLabels,
                validationErrors: stepValidationErrors,
                direction,
                onEdit: onEditStep,
                onDelete: onDeleteStep,
            },
        };

        return stepNode;
    });

    const edges: WorkflowEdge[] = [];

    // Generate chained sequential Action nodes & edges attached to each parent step
    steps.forEach((step) => {
        let previousNodeId = step.key;
        let previousHandleId = "action-source";

        step.actions.forEach((act, actIdx) => {
            const actionNodeId = `act-${step.key}-${actIdx}`;
            nodes.push({
                id: actionNodeId,
                type: "actionNode",
                position: { x: 0, y: 0 },
                data: {
                    parentStepKey: step.key,
                    actionIndex: actIdx,
                    totalActions: step.actions.length,
                    action: act,
                    handlerItem: actions[act.handler],
                    summary: getActionSummary(act),
                    iconType: act.handler,
                    onEdit: onEditAction,
                    onDelete: onDeleteAction,
                    onReorder: onReorderAction,
                },
            });

            // Chained Sequential Edge:
            // 1st action connects from parent step -> action 1
            // Subsequent actions connect from action i-1 -> action i
            const isChained = actIdx > 0;
            edges.push({
                id: `action-edge-${previousNodeId}-${actionNodeId}`,
                source: previousNodeId,
                sourceHandle: previousHandleId,
                target: actionNodeId,
                targetHandle: "action-target",
                type: "actionEdge",
                data: {
                    parentStepKey: step.key,
                    actionKey: act.key,
                    label: isChained ? `Then (#${actIdx + 1})` : "Execute #1",
                    isChained,
                },
            });

            previousNodeId = actionNodeId;
            previousHandleId = "action-source";
        });
    });

    steps.forEach((step) => {
        step.transitions.forEach((trans, transIdx) => {
            const firstCond = trans.conditions[0];
            const ruleLabel = firstCond ? (rules[firstCond.handler]?.label ?? firstCond.handler.replaceAll("_", " ")) : undefined;

            const edgeId = `edge-${step.key}-${trans.key || transIdx}-${trans.to}`;

            edges.push({
                id: edgeId,
                source: step.key,
                target: trans.to,
                type: "transitionEdge",
                animated: !trans.fallback,
                markerEnd: {
                    type: MarkerType.ArrowClosed,
                    width: 16,
                    height: 16,
                    color: trans.fallback ? "var(--muted-foreground)" : "var(--primary)",
                },
                data: {
                    transition: trans,
                    sourceKey: step.key,
                    targetKey: trans.to,
                    isFallback: trans.fallback,
                    ruleLabel,
                    onEdit: onEditTransition,
                    onDelete: onDeleteTransition,
                },
            });
        });
    });

    return { nodes, edges, validationIssues: issues };
}
