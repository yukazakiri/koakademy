import dagre from "@dagrejs/dagre";
import type { LayoutDirection, StepNodeData, WorkflowEdge, WorkflowNode } from "./types";

export function computeAutoLayout(nodes: WorkflowNode[], edges: WorkflowEdge[], direction: LayoutDirection = "LR") {
    const graph = new dagre.graphlib.Graph();
    graph.setDefaultEdgeLabel(() => ({}));
    graph.setGraph({ rankdir: direction, nodesep: 100, ranksep: 190, marginx: 32, marginy: 32, align: "UL" });
    for (const node of nodes) {
        const height =
            node.type === "triggerNode"
                ? 240
                : 258 + (node.data as StepNodeData).step.actions.length * 72 + Math.min((node.data as StepNodeData).validationErrors.length, 1) * 70;
        graph.setNode(node.id, { width: 360, height });
    }
    for (const edge of edges) {
        if (graph.hasNode(edge.source) && graph.hasNode(edge.target)) graph.setEdge(edge.source, edge.target);
    }
    dagre.layout(graph);
    return {
        nodes: nodes.map((node) => {
            const position = graph.node(node.id);
            return { ...node, position: { x: position.x - position.width / 2, y: position.y - position.height / 2 } };
        }),
        edges,
    };
}
