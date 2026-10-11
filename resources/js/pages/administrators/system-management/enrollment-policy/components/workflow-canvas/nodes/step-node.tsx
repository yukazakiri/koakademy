import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { AlertTriangle, ArrowRight, Edit3, GitBranch, ShieldCheck, Trash2, Users, Zap } from "lucide-react";
import type { WorkflowNode } from "../types";
import { BaseNode, BaseNodeContent, BaseNodeFooter, BaseNodeHeader } from "./base-node";

export function StepNode({ data, selected }: NodeProps<WorkflowNode>) {
    if (data.step.terminal) return null;

    const { step, index, isEntry, roleLabels, actionLabels, validationErrors, direction, onEdit, onDelete } = data as import("../types").StepNodeData;

    const targetPosition = direction === "LR" ? Position.Left : Position.Top;
    const sourcePosition = direction === "LR" ? Position.Right : Position.Bottom;

    const branchesCount = step.transitions.filter((t) => !t.fallback).length;
    const fallback = step.transitions.find((t) => t.fallback);

    return (
        <BaseNode
            selected={selected}
            className={cn(
                "w-[310px] text-xs transition-shadow",
                isEntry && "border-primary/60 shadow-primary/5 shadow-md",
                validationErrors.length > 0 && "border-amber-500/70 dark:border-amber-400/60",
            )}
        >
            {/* Input Handle (inbound transitions) */}
            {!isEntry && (
                <Handle
                    type="target"
                    position={targetPosition}
                    className={cn(
                        "!border-background !bg-primary !size-3 !border-2 transition-transform hover:!scale-125",
                        direction === "LR" ? "!-left-1.5" : "!-top-1.5",
                    )}
                />
            )}

            {/* Header */}
            <BaseNodeHeader>
                <div className="flex min-w-0 items-center gap-2">
                    <span
                        className={cn(
                            "flex size-5 shrink-0 items-center justify-center rounded-md text-[10px] font-bold",
                            isEntry ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground",
                        )}
                    >
                        {index + 1}
                    </span>
                    <span className="text-foreground truncate text-[13px] font-semibold" title={step.label}>
                        {step.label}
                    </span>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    {isEntry ? (
                        <Badge
                            variant="outline"
                            className="h-5 border-emerald-500/40 bg-emerald-500/10 px-1.5 text-[9px] font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            <ShieldCheck className="mr-0.5 size-2.5" /> Start
                        </Badge>
                    ) : null}

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-foreground size-6"
                        onClick={(e) => {
                            e.stopPropagation();
                            onEdit(step.key);
                        }}
                        title="Edit step configuration"
                    >
                        <Edit3 className="size-3" />
                    </Button>

                    {!isEntry ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-destructive size-6"
                            onClick={(e) => {
                                e.stopPropagation();
                                onDelete(step.key);
                            }}
                            title="Remove step"
                        >
                            <Trash2 className="size-3" />
                        </Button>
                    ) : null}
                </div>
            </BaseNodeHeader>

            {/* Content */}
            <BaseNodeContent>
                {/* Status Pill */}
                <div className="flex items-center justify-between text-[11px]">
                    <span className="text-muted-foreground">Status:</span>
                    <span className="text-foreground bg-muted/50 border-border/40 rounded-md border px-2 py-0.5 font-medium">
                        {step.status || "Pending"}
                    </span>
                </div>

                {/* Authorized Roles */}
                <div className="space-y-1">
                    <div className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                        <Users className="size-3 shrink-0" />
                        <span>Authorized Staff:</span>
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {roleLabels.length > 0 ? (
                            roleLabels.slice(0, 2).map((role) => (
                                <Badge key={role} variant="secondary" className="h-4.5 px-1.5 text-[10px] font-normal">
                                    {role}
                                </Badge>
                            ))
                        ) : (
                            <span className="text-muted-foreground text-[10px] italic">All staff roles</span>
                        )}
                        {roleLabels.length > 2 && (
                            <Badge variant="secondary" className="h-4.5 px-1 text-[9px]">
                                +{roleLabels.length - 2}
                            </Badge>
                        )}
                    </div>
                </div>

                {/* Automated Actions */}
                {actionLabels.length > 0 && (
                    <div className="text-muted-foreground flex items-center gap-1.5 pt-0.5 text-[11px]">
                        <Zap className="size-3 shrink-0 text-amber-500" />
                        <span className="truncate">
                            {actionLabels.length} automated action{actionLabels.length > 1 ? "s" : ""}
                        </span>
                    </div>
                )}

                {/* Validation Warnings */}
                {validationErrors.length > 0 && (
                    <div className="space-y-0.5 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2 text-[10px] text-amber-800 dark:text-amber-300">
                        <div className="flex items-center gap-1 font-semibold">
                            <AlertTriangle className="size-3 shrink-0" />
                            <span>Attention required</span>
                        </div>
                        <p className="text-[10px] leading-tight">{validationErrors[0]}</p>
                    </div>
                )}
            </BaseNodeContent>

            {/* Footer with Routing Summary */}
            <BaseNodeFooter>
                <div className="flex items-center gap-1 text-[10px]">
                    <GitBranch className="text-primary size-3 shrink-0" />
                    <span>{branchesCount > 0 ? `${branchesCount} branch${branchesCount > 1 ? "es" : ""}` : "Direct path"}</span>
                </div>

                {fallback && (
                    <div className="text-muted-foreground flex items-center gap-0.5 font-mono text-[10px]" title={`Default path: ${fallback.to}`}>
                        <span>next:</span>
                        <span className="text-foreground max-w-[80px] truncate font-medium">{fallback.to}</span>
                        <ArrowRight className="size-2.5" />
                    </div>
                )}
            </BaseNodeFooter>

            {/* Output Handle (outbound transitions) */}
            <Handle
                type="source"
                position={sourcePosition}
                id="transition-source"
                className={cn(
                    "!border-background !bg-primary !size-3 !border-2 transition-transform hover:!scale-125",
                    direction === "LR" ? "!-right-1.5" : "!-bottom-1.5",
                )}
            />

            {/* Sub-action Handle (attachable sub-actions) */}
            <Handle
                type="source"
                position={direction === "LR" ? Position.Bottom : Position.Right}
                id="action-source"
                className={cn(
                    "!border-background !bg-primary/70 !size-2.5 !border-2 transition-transform hover:!scale-125",
                    direction === "LR" ? "!-bottom-1.5 !left-1/2 -translate-x-1/2" : "!top-1/2 !-right-1.5 -translate-y-1/2",
                )}
                title="Attach sub-actions (Email notifications, tuition calc, class assign)"
            />
        </BaseNode>
    );
}
