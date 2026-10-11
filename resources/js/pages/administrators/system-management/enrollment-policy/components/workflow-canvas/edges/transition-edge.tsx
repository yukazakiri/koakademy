import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { BaseEdge, EdgeLabelRenderer, getBezierPath, type EdgeProps } from "@xyflow/react";
import { FileText, GitBranch, X } from "lucide-react";
import type { WorkflowEdge } from "../types";

export function TransitionEdge({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    style = {},
    markerEnd,
    data,
}: EdgeProps<WorkflowEdge>) {
    const [edgePath, labelX, labelY] = getBezierPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
    });

    if (!data) {
        return <BaseEdge path={edgePath} markerEnd={markerEnd} style={style} />;
    }

    const { transition, sourceKey, isFallback, ruleLabel, onEdit, onDelete } = data;

    return (
        <>
            <BaseEdge
                path={edgePath}
                markerEnd={markerEnd}
                style={{
                    ...style,
                    strokeWidth: isFallback ? 1.75 : 2,
                    stroke: isFallback ? "var(--muted-foreground)" : "var(--primary)",
                    strokeDasharray: isFallback ? undefined : "5 4",
                }}
            />

            <EdgeLabelRenderer>
                <div
                    style={{
                        position: "absolute",
                        transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
                        pointerEvents: "all",
                    }}
                    className="nodrag nopan border-border/80 bg-background/95 hover:border-foreground/40 group flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] shadow-xs backdrop-blur-xs transition-all hover:scale-105 hover:shadow-md"
                >
                    <button
                        type="button"
                        onClick={() => onEdit(sourceKey, transition.key)}
                        className="text-foreground hover:text-primary flex cursor-pointer items-center gap-1 font-medium transition-colors"
                        title={isFallback ? "Edit protected fallback transition" : `Edit condition: ${ruleLabel ?? "custom rule"}`}
                    >
                        {isFallback ? (
                            <span className="text-muted-foreground font-medium">Otherwise</span>
                        ) : (
                            <div className="text-primary flex items-center gap-1">
                                <GitBranch className="size-3 shrink-0" />
                                <span className="max-w-[120px] truncate font-semibold">{ruleLabel || "Condition"}</span>
                            </div>
                        )}

                        {transition.requires_reason && (
                            <Badge
                                variant="outline"
                                className="ml-0.5 h-3.5 border-amber-500/40 px-1 text-[8px] text-amber-700 dark:text-amber-300"
                                title="Requires audited reason"
                            >
                                <FileText className="mr-0.5 size-2" /> Reason
                            </Badge>
                        )}
                    </button>

                    <div className="border-border/50 flex items-center border-l pl-0.5 opacity-70 transition-opacity group-hover:opacity-100">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-destructive size-4 p-0"
                            onClick={() => onDelete(sourceKey, transition.key)}
                            title="Delete transition"
                        >
                            <X className="size-2.5" />
                            <span className="sr-only">Delete transition</span>
                        </Button>
                    </div>
                </div>
            </EdgeLabelRenderer>
        </>
    );
}
