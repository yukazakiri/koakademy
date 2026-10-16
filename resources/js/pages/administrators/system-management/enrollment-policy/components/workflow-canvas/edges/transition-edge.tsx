import { BaseEdge, EdgeLabelRenderer, getBezierPath, type EdgeProps } from "@xyflow/react";
import type { TransitionEdgeData, WorkflowEdge } from "../types";

export function TransitionEdge({ sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, markerEnd, data }: EdgeProps<WorkflowEdge>) {
    const [path, x, y] = getBezierPath({ sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition });
    const route = data as TransitionEdgeData | undefined;
    return (
        <>
            <BaseEdge
                path={path}
                markerEnd={markerEnd}
                style={{ stroke: "var(--muted-foreground)", strokeWidth: 1.5, strokeDasharray: route?.isFallback ? undefined : "6 4" }}
            />
            {route && (
                <EdgeLabelRenderer>
                    <button
                        type="button"
                        disabled={route.readOnly}
                        className="nodrag nopan bg-background text-foreground hover:bg-muted flex max-w-44 flex-col gap-1 border p-2 text-left text-xs transition-colors"
                        style={{
                            position: "absolute",
                            transform: `translate(-50%, -50%) translate(${x}px, ${y}px)`,
                            pointerEvents: "all",
                            borderRadius: "var(--radius)",
                        }}
                        onClick={() => route.onEdit(route.sourceKey, route.transition.key)}
                        aria-label={`Edit route ${route.transition.label}`}
                    >
                        <span className="font-medium">{route.transition.label}</span>
                        <span className="text-muted-foreground">
                            {route.isFallback ? "Otherwise / default" : (route.ruleLabel ?? "Unconditional route")}
                            {route.transition.requires_reason ? "; reason required" : ""}
                        </span>
                    </button>
                </EdgeLabelRenderer>
            )}
        </>
    );
}
