import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Ban, CheckCircle2, Edit3, Trash2, XCircle } from "lucide-react";
import type { WorkflowNode } from "../types";
import { BaseNode, BaseNodeContent, BaseNodeHeader } from "./base-node";

const outcomeConfig = {
    completed: {
        label: "Completed",
        icon: CheckCircle2,
        badgeClass: "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        nodeClass: "border-emerald-500/50 bg-emerald-500/5",
    },
    rejected: {
        label: "Rejected",
        icon: XCircle,
        badgeClass: "border-destructive/40 bg-destructive/10 text-destructive",
        nodeClass: "border-destructive/40 bg-destructive/5",
    },
    cancelled: {
        label: "Cancelled",
        icon: Ban,
        badgeClass: "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300",
        nodeClass: "border-amber-500/40 bg-amber-500/5",
    },
};

export function TerminalNode({ data, selected }: NodeProps<WorkflowNode>) {
    const { step, outcome, direction, onEdit, onDelete } = data as import("../types").TerminalNodeData;

    const config = outcomeConfig[outcome] ?? outcomeConfig.completed;
    const Icon = config.icon;

    const targetPosition = direction === "LR" ? Position.Left : Position.Top;

    return (
        <BaseNode selected={selected} className={cn("w-[260px] text-xs transition-shadow", config.nodeClass)}>
            {/* Input Handle */}
            <Handle
                type="target"
                position={targetPosition}
                className={cn(
                    "!border-background !bg-primary !size-3 !border-2 transition-transform hover:!scale-125",
                    direction === "LR" ? "!-left-1.5" : "!-top-1.5",
                )}
            />

            {/* Header */}
            <BaseNodeHeader>
                <div className="flex min-w-0 items-center gap-2">
                    <Icon className="text-foreground size-4 shrink-0" />
                    <span className="text-foreground truncate text-[13px] font-semibold" title={step.label}>
                        {step.label}
                    </span>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-foreground size-6"
                        onClick={(e) => {
                            e.stopPropagation();
                            onEdit(step.key);
                        }}
                        title="Edit terminal node"
                    >
                        <Edit3 className="size-3" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-destructive size-6"
                        onClick={(e) => {
                            e.stopPropagation();
                            onDelete(step.key);
                        }}
                        title="Remove terminal node"
                    >
                        <Trash2 className="size-3" />
                    </Button>
                </div>
            </BaseNodeHeader>

            {/* Content */}
            <BaseNodeContent className="space-y-2">
                <div className="flex items-center justify-between text-[11px]">
                    <span className="text-muted-foreground">Journey Outcome:</span>
                    <Badge variant="outline" className={cn("h-5 px-2 text-[10px] font-semibold", config.badgeClass)}>
                        {config.label}
                    </Badge>
                </div>

                <p className="text-muted-foreground text-[10px] leading-relaxed">
                    Terminal step. The student workflow concludes here without further transitions.
                </p>
            </BaseNodeContent>

            {/* Sub-action Handle (attachable terminal sub-actions) */}
            <Handle
                type="source"
                position={direction === "LR" ? Position.Bottom : Position.Right}
                id="action-source"
                className={cn(
                    "!border-background !bg-primary/70 !size-2.5 !border-2 transition-transform hover:!scale-125",
                    direction === "LR" ? "!-bottom-1.5 !left-1/2 -translate-x-1/2" : "!top-1/2 !-right-1.5 -translate-y-1/2",
                )}
                title="Attach sub-actions (Sync student, notification, outcome)"
            />
        </BaseNode>
    );
}
