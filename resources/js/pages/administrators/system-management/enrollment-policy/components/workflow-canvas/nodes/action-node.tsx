import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import {
    ArrowDown,
    ArrowUp,
    BookOpen,
    Coins,
    CreditCard,
    Edit3,
    FileText,
    Flag,
    GraduationCap,
    Mail,
    Receipt,
    Trash2,
    UserCheck,
    Zap,
} from "lucide-react";
import type { WorkflowNode } from "../types";
import { BaseNode } from "./base-node";

const actionMeta: Record<string, { label: string; icon: typeof Zap; color: string; bg: string }> = {
    "enrollment.notify": {
        label: "Confirmation Email",
        icon: Mail,
        color: "text-sky-500",
        bg: "border-sky-500/50 bg-sky-500/5 hover:border-sky-500",
    },
    "enrollment.calculate_tuition": {
        label: "Calculate Tuition",
        icon: Coins,
        color: "text-amber-500",
        bg: "border-amber-500/50 bg-amber-500/5 hover:border-amber-500",
    },
    "enrollment.assign_subjects": {
        label: "Assign Subjects",
        icon: BookOpen,
        color: "text-indigo-500",
        bg: "border-indigo-500/50 bg-indigo-500/5 hover:border-indigo-500",
    },
    "enrollment.assign_classes": {
        label: "Assign Classes",
        icon: GraduationCap,
        color: "text-violet-500",
        bg: "border-violet-500/50 bg-violet-500/5 hover:border-violet-500",
    },
    "enrollment.assign_additional_fees": {
        label: "Additional Fees",
        icon: Receipt,
        color: "text-orange-500",
        bg: "border-orange-500/50 bg-orange-500/5 hover:border-orange-500",
    },
    "enrollment.verify_academic": {
        label: "Academic Clearance",
        icon: UserCheck,
        color: "text-emerald-500",
        bg: "border-emerald-500/50 bg-emerald-500/5 hover:border-emerald-500",
    },
    "enrollment.verify_payment": {
        label: "Payment Verification",
        icon: CreditCard,
        color: "text-emerald-500",
        bg: "border-emerald-500/50 bg-emerald-500/5 hover:border-emerald-500",
    },
    "enrollment.sync_student": {
        label: "Sync Student State",
        icon: UserCheck,
        color: "text-teal-500",
        bg: "border-teal-500/50 bg-teal-500/5 hover:border-teal-500",
    },
    "enrollment.set_outcome": {
        label: "Terminal Outcome",
        icon: Flag,
        color: "text-rose-500",
        bg: "border-rose-500/50 bg-rose-500/5 hover:border-rose-500",
    },
    "enrollment.generate_assessment": {
        label: "Generate Assessment PDF",
        icon: FileText,
        color: "text-blue-500",
        bg: "border-blue-500/50 bg-blue-500/5 hover:border-blue-500",
    },
};

export function ActionNode({ data, selected }: NodeProps<WorkflowNode>) {
    const { parentStepKey, actionIndex, totalActions, action, handlerItem, summary, onEdit, onDelete, onReorder } =
        data as import("../types").ActionNodeData;

    const meta = actionMeta[action.handler] ?? {
        label: handlerItem?.label ?? action.handler,
        icon: Zap,
        color: "text-primary",
        bg: "border-primary/50 bg-primary/5 hover:border-primary",
    };
    const Icon = meta.icon;
    const isFirst = actionIndex === 0;
    const isLast = actionIndex === totalActions - 1;

    return (
        <BaseNode selected={selected} className={cn("relative w-[260px] rounded-xl border-dashed text-xs shadow-xs transition-shadow", meta.bg)}>
            {/* Input handle from parent step or previous action */}
            <Handle
                type="target"
                position={Position.Top}
                id="action-target"
                className="!border-background !bg-primary -top-1.5 !left-1/2 !size-2.5 -translate-x-1/2 !border-2"
            />

            <div className="space-y-1.5 p-2.5">
                <div className="flex items-center justify-between gap-1">
                    <div className="flex min-w-0 items-center gap-1.5">
                        {/* Execution sequence order badge */}
                        <span
                            className={cn(
                                "flex size-5 shrink-0 items-center justify-center rounded-md font-mono text-[10px] font-bold shadow-2xs",
                                isFirst ? "bg-primary text-primary-foreground" : "bg-muted/90 text-foreground border-border/60 border",
                            )}
                            title={`Execution order: #${actionIndex + 1} of ${totalActions}`}
                        >
                            #{actionIndex + 1}
                        </span>

                        <div className="bg-background/80 border-border/50 flex size-5 shrink-0 items-center justify-center rounded-md border">
                            <Icon className={cn("size-3", meta.color)} />
                        </div>
                        <span className="text-foreground truncate text-[11px] font-semibold">{meta.label}</span>
                    </div>

                    {/* Order Controls & Action Buttons */}
                    <div className="flex shrink-0 items-center gap-0.5">
                        {totalActions > 1 && (
                            <div className="bg-background/80 border-border/50 p-0.2 flex items-center rounded-md border">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={isFirst}
                                    className="text-muted-foreground hover:text-foreground size-4.5 p-0 disabled:opacity-30"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onReorder(parentStepKey, actionIndex, actionIndex - 1);
                                    }}
                                    title="Move earlier in execution order"
                                >
                                    <ArrowUp className="size-2.5" />
                                    <span className="sr-only">Move earlier</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={isLast}
                                    className="text-muted-foreground hover:text-foreground size-4.5 p-0 disabled:opacity-30"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onReorder(parentStepKey, actionIndex, actionIndex + 1);
                                    }}
                                    title="Move later in execution order"
                                >
                                    <ArrowDown className="size-2.5" />
                                    <span className="sr-only">Move later</span>
                                </Button>
                            </div>
                        )}

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-foreground size-5"
                            onClick={(e) => {
                                e.stopPropagation();
                                onEdit(parentStepKey, actionIndex);
                            }}
                            title="Configure action"
                        >
                            <Edit3 className="size-2.5" />
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-destructive size-5"
                            onClick={(e) => {
                                e.stopPropagation();
                                onDelete(parentStepKey, actionIndex);
                            }}
                            title="Detach action"
                        >
                            <Trash2 className="size-2.5" />
                        </Button>
                    </div>
                </div>

                <div className="text-muted-foreground bg-background/70 border-border/40 flex items-center justify-between rounded-md border px-2 py-1 font-mono text-[10px]">
                    <span className="max-w-[170px] truncate">{summary}</span>
                    <Badge variant="outline" className="border-border/50 h-3.5 px-1 font-mono text-[8px] uppercase">
                        {isFirst ? "1st" : "then"}
                    </Badge>
                </div>
            </div>

            {/* Output handle to chain to the next sequential action */}
            {!isLast && (
                <Handle
                    type="source"
                    position={Position.Bottom}
                    id="action-source"
                    className="!border-background !bg-primary -bottom-1.5 !left-1/2 !size-2.5 -translate-x-1/2 !border-2"
                />
            )}
        </BaseNode>
    );
}
