import type { Action, Transition, WorkflowStep } from "../../types";
import type { GraphValidationError } from "./types";

export function validateWorkflowGraph(steps: WorkflowStep[]): {
    issues: GraphValidationError[];
    nodeIssues: Record<string, string[]>;
} {
    const issues: GraphValidationError[] = [];
    const nodeIssues: Record<string, string[]> = {};
    const add = (message: string, nodeKey?: string, severity: "error" | "warning" = "error") => {
        issues.push({ message, nodeKey, severity });
        if (nodeKey) (nodeIssues[nodeKey] ??= []).push(message);
    };
    const keys = new Set<string>();
    for (const step of steps) {
        if (typeof step.key !== "string" || !step.key.trim() || keys.has(step.key)) add("Stage keys must be present and unique.", step.key);
        keys.add(step.key);
    }
    const entries = steps.filter((step) => step.entry);
    if (entries.length !== 1) add("Workflow needs exactly one submission entry stage.");
    if (!steps.some((step) => step.terminal)) add("Workflow needs at least one final outcome.");

    for (const step of steps) {
        const actionKeys = step.actions.map((action) => action.key);
        if (actionKeys.some((key) => typeof key !== "string" || !key.trim()) || new Set(actionKeys).size !== actionKeys.length) {
            add("Actions need unique keys within each stage so payloads reach the correct action.", step.key);
        }
        const transitionKeys = step.transitions.map((transition) => transition.key);
        if (transitionKeys.some((key) => typeof key !== "string" || !key.trim()) || new Set(transitionKeys).size !== transitionKeys.length) {
            add("Routes need unique keys within each stage.", step.key);
        }
        if (step.terminal) {
            if (step.transitions.length) add("Final outcomes cannot have outgoing routes.", step.key);
            if (!["completed", "rejected", "cancelled"].includes(step.outcome ?? "")) add("Choose a final outcome.", step.key);
        } else {
            if (step.transitions.filter((transition) => transition.fallback).length !== 1) {
                add("Each staff gate needs exactly one Otherwise route.", step.key);
            } else if (!step.transitions.at(-1)?.fallback) {
                add("Otherwise must be last, after conditional routes.", step.key);
            }
        }
        for (const transition of step.transitions) {
            if (!keys.has(transition.to)) add(`Route '${transition.label}' points to missing stage '${transition.to}'.`, step.key);
            if (transition.to === step.key) add("A stage cannot route to itself.", step.key);
            if (!transition.fallback && !transition.conditions.length) {
                add(`Route '${transition.label}' has no conditions; it will always match when selected.`, step.key, "warning");
            }
        }
    }

    const visited = new Set<string>();
    const visiting = new Set<string>();
    const walk = (key: string) => {
        if (visiting.has(key)) {
            add("Workflow cycles are not supported.", key);
            return;
        }
        if (visited.has(key)) return;
        visiting.add(key);
        const step = steps.find((item) => item.key === key);
        for (const route of step?.transitions ?? []) if (keys.has(route.to)) walk(route.to);
        visiting.delete(key);
        visited.add(key);
    };
    if (entries.length === 1) {
        walk(entries[0].key);
        for (const step of steps) if (!visited.has(step.key)) add("Stage is unreachable from submission.", step.key);
    }
    return { issues, nodeIssues };
}

export function appendTransition(transitions: Transition[], route: Transition): Transition[] {
    return [
        ...transitions.filter((item) => !item.fallback),
        ...(!route.fallback ? [route] : []),
        ...(route.fallback ? [route] : transitions.filter((item) => item.fallback)),
    ];
}

export function replaceTransition(transitions: Transition[], route: Transition): Transition[] {
    const next = transitions.map((item) => (item.key === route.key ? route : { ...item, fallback: route.fallback ? false : item.fallback }));
    return [...next.filter((item) => !item.fallback), ...next.filter((item) => item.fallback)];
}

export function appendActions(existing: Action[], additions: Action[]): Action[] {
    const keys = new Set(existing.map((action) => action.key));
    return [
        ...existing,
        ...additions.map((action) => {
            const seed = action.key || action.handler.replaceAll(".", "_");
            let key = seed;
            let index = 2;
            while (keys.has(key)) key = `${seed}_${index++}`;
            keys.add(key);
            return { ...action, key, configuration: { ...action.configuration } };
        }),
    ];
}

export function removeStep(steps: WorkflowStep[], key: string): WorkflowStep[] {
    const target = steps.find((step) => step.key === key);
    if (!target || target.entry || (target.terminal && steps.filter((step) => step.terminal).length === 1)) return steps;
    return steps.filter((step) => step.key !== key).map((step) => ({ ...step, transitions: step.transitions.filter((route) => route.to !== key) }));
}

export function canConnectSteps(steps: WorkflowStep[], source: string, target: string): boolean {
    const from = steps.find((step) => step.key === source);
    const to = steps.find((step) => step.key === target);
    if (!from || !to || from.terminal || to.entry || source === target || from.transitions.some((route) => route.to === target)) return false;
    const seen = new Set<string>();
    const reachesSource = (key: string): boolean => {
        if (key === source) return true;
        if (seen.has(key)) return false;
        seen.add(key);
        return steps.find((step) => step.key === key)?.transitions.some((route) => reachesSource(route.to)) ?? false;
    };
    return !reachesSource(target);
}
