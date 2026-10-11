import { BaseEdge, EdgeLabelRenderer, getSmoothStepPath, MarkerType, type EdgeProps } from "@xyflow/react";
import type { WorkflowEdge } from "../types";

export function ActionEdge({ sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, style = {}, data }: EdgeProps<WorkflowEdge>) {
    const [edgePath, labelX, labelY] = getSmoothStepPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
        borderRadius: 8,
    });

    const isChained = data?.isChained ?? false;
    const label = data?.label ?? (isChained ? "Then" : "1st Action");

    return (
        <>
            <BaseEdge
                path={edgePath}
                markerEnd={{
                    type: MarkerType.ArrowClosed,
                    width: 12,
                    height: 12,
                    color: isChained ? "var(--primary)" : "var(--primary)",
                }}
                style={{
                    ...style,
                    strokeWidth: 1.75,
                    stroke: "var(--primary)",
                    strokeDasharray: isChained ? "4 3" : undefined,
                    opacity: 0.85,
                }}
            />

            <EdgeLabelRenderer>
                <div
                    style={{
                        position: "absolute",
                        transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
                        pointerEvents: "none",
                    }}
                    className="nodrag nopan border-primary/40 bg-background/95 text-primary flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-bold shadow-2xs backdrop-blur-xs"
                >
                    <span>{label}</span>
                    <span className="text-[10px] leading-none">↓</span>
                </div>
            </EdgeLabelRenderer>
        </>
    );
}
