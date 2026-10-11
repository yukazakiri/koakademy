import dagre from "@dagrejs/dagre";
import type { LayoutDirection, WorkflowEdge, WorkflowNode } from "./types";

const NODE_WIDTH = {
    stepNode: 310,
    terminalNode: 260,
    actionNode: 260,
};

const NODE_HEIGHT = {
    stepNode: 190,
    terminalNode: 140,
    actionNode: 80,
};

export function computeAutoLayout(
    nodes: WorkflowNode[],
    edges: WorkflowEdge[],
    direction: LayoutDirection = "LR",
): { nodes: WorkflowNode[]; edges: WorkflowEdge[] } {
    const dagreGraph = new dagre.graphlib.Graph();
    dagreGraph.setDefaultEdgeLabel(() => ({}));

    dagreGraph.setGraph({
        rankdir: direction,
        nodesep: direction === "LR" ? 90 : 100,
        ranksep: direction === "LR" ? 140 : 120,
        marginx: 50,
        marginy: 50,
    });

    const stepNodes = nodes.filter((n) => n.type === "stepNode" || n.type === "terminalNode");
    const actionNodes = nodes.filter((n) => n.type === "actionNode");
    const transitionEdges = edges.filter((e) => e.type === "transitionEdge");

    for (const node of stepNodes) {
        const width = node.type === "terminalNode" ? NODE_WIDTH.terminalNode : NODE_WIDTH.stepNode;
        const height = node.type === "terminalNode" ? NODE_HEIGHT.terminalNode : NODE_HEIGHT.stepNode;
        dagreGraph.setNode(node.id, { width, height });
    }

    for (const edge of transitionEdges) {
        dagreGraph.setEdge(edge.source, edge.target);
    }

    dagre.layout(dagreGraph);

    // Compute parent positions map
    const parentPositions = new Map<string, { x: number; y: number; width: number; height: number; isTerminal: boolean }>();

    const layoutedStepNodes: WorkflowNode[] = stepNodes.map((node) => {
        const nodeWithPosition = dagreGraph.node(node.id);
        const width = node.type === "terminalNode" ? NODE_WIDTH.terminalNode : NODE_WIDTH.stepNode;
        const height = node.type === "terminalNode" ? NODE_HEIGHT.terminalNode : NODE_HEIGHT.stepNode;

        const posX = nodeWithPosition ? nodeWithPosition.x - width / 2 : node.position.x;
        const posY = nodeWithPosition ? nodeWithPosition.y - height / 2 : node.position.y;

        parentPositions.set(node.id, {
            x: posX,
            y: posY,
            width,
            height,
            isTerminal: node.type === "terminalNode",
        });

        return {
            ...node,
            position: { x: posX, y: posY },
        };
    });

    // Group action nodes by parentStepKey in order of actionIndex
    const actionsByParent = new Map<string, WorkflowNode[]>();
    for (const act of actionNodes) {
        const parentKey = (act.data as import("./types").ActionNodeData).parentStepKey;
        if (!actionsByParent.has(parentKey)) actionsByParent.set(parentKey, []);
        actionsByParent.get(parentKey)!.push(act);
    }

    // Sort action nodes for each parent by their actionIndex
    actionsByParent.forEach((acts) => {
        acts.sort((a, b) => {
            const idxA = (a.data as import("./types").ActionNodeData).actionIndex;
            const idxB = (b.data as import("./types").ActionNodeData).actionIndex;
            return idxA - idxB;
        });
    });

    const layoutedActionNodes: WorkflowNode[] = [];
    actionsByParent.forEach((acts, parentKey) => {
        const parent = parentPositions.get(parentKey);
        acts.forEach((actNode, idx) => {
            if (parent) {
                // In LR mode: Center action node directly beneath parent step card
                // Parent width is 310 (or 260), Action node width is 260
                const centerOffsetX = (parent.width - NODE_WIDTH.actionNode) / 2;
                const posX = direction === "LR" ? parent.x + centerOffsetX : parent.x + parent.width + 45;

                // Vertical stack: 45px below parent bottom, each subsequent action spaced by 105px
                const posY = direction === "LR" ? parent.y + parent.height + 45 + idx * 105 : parent.y + idx * 105;

                layoutedActionNodes.push({
                    ...actNode,
                    position: { x: posX, y: posY },
                });
            } else {
                layoutedActionNodes.push(actNode);
            }
        });
    });

    return { nodes: [...layoutedStepNodes, ...layoutedActionNodes], edges };
}
