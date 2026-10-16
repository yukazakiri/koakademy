import { Badge } from "@/components/reui/badge";
import { Frame, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { ArrowDown, ArrowUp, CheckCircle2, Pencil, Users, Zap } from "lucide-react";
import type { StepNodeData, WorkflowNode } from "../types";

export function StepNode({ data, selected }: NodeProps<WorkflowNode>) {
    const { step, roleLabels, validationErrors, direction, onEdit, actions, onEditAction, onReorderAction, readOnly } = data as StepNodeData;
    return (
        <Frame spacing="sm" className={cn("w-[360px]", selected && "ring-primary ring-2")} data-workflow-stage={step.key}>
            <Handle type="target" position={direction === "LR" ? Position.Left : Position.Top} className="!bg-primary !size-3" />
            <FrameHeader>
                <div className="flex items-center justify-between gap-2">
                    <div className="flex min-w-0 flex-col gap-1">
                        <span className="text-muted-foreground text-xs">
                            {step.entry ? "Entry stage" : step.terminal ? "Final stage" : "Workflow stage"}
                        </span>
                        <FrameTitle className="truncate" title={step.label}>
                            {step.label}
                        </FrameTitle>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={readOnly}
                        aria-label={`Configure ${step.label}`}
                        onClick={(event) => {
                            event.stopPropagation();
                            onEdit(step.key);
                        }}
                    >
                        <Pencil />
                    </Button>
                </div>
            </FrameHeader>
            <FramePanel className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className="text-muted-foreground">Status on entry</span>
                    <Badge variant="outline">{step.status}</Badge>
                </div>
                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                    <Zap className="size-4" /> Run on entry, in order
                </div>
                <ol className="nodrag nopan flex flex-col gap-2" aria-label={`Actions on entering ${step.label}`}>
                    {step.actions.map((action, index) => (
                        <li
                            key={action.key ?? index}
                            className="flex min-h-16 items-center gap-2 border-b pb-2 last:border-0"
                            data-workflow-action={action.key}
                        >
                            <span className="text-muted-foreground w-5 shrink-0 text-xs tabular-nums">{index + 1}.</span>
                            <button
                                type="button"
                                className="hover:text-primary flex min-w-0 flex-1 flex-col gap-1 text-left text-sm transition-colors"
                                disabled={readOnly}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onEditAction(step.key, index);
                                }}
                            >
                                <span className="truncate">{actions[action.handler]?.label ?? action.handler}</span>
                                <span className="text-muted-foreground truncate text-xs">{action.key}</span>
                            </button>
                            {!readOnly && (
                                <div className="flex shrink-0">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Move ${action.key} up`}
                                        disabled={index === 0}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onReorderAction(step.key, index, index - 1);
                                        }}
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Move ${action.key} down`}
                                        disabled={index === step.actions.length - 1}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onReorderAction(step.key, index, index + 1);
                                        }}
                                    >
                                        <ArrowDown />
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
                {!step.actions.length && <p className="text-muted-foreground text-xs">No automatic actions configured.</p>}
                {validationErrors.length > 0 && (
                    <p role="alert" className="text-destructive text-xs">
                        {validationErrors[0]}
                    </p>
                )}
            </FramePanel>
            <FrameFooter>
                {step.terminal ? (
                    <div className="flex flex-col gap-2 text-xs">
                        <span className="text-muted-foreground">After every action succeeds</span>
                        <Badge variant={step.outcome === "completed" ? "success-light" : "destructive-light"}>
                            <CheckCircle2 /> {step.outcome ?? "Choose outcome"}
                        </Badge>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2 text-xs">
                        <span className="flex items-center gap-2 font-medium">
                            <Users className="size-4" /> Wait for staff action
                        </span>
                        <span className="text-muted-foreground">
                            {roleLabels.length ? roleLabels.join(", ") : step.permission ? `Requires ${step.permission}` : "Authenticated staff"}
                        </span>
                        <span>{step.transitions.map((route) => route.label).join(" / ") || "Connect a destination"}</span>
                    </div>
                )}
            </FrameFooter>
            {!step.terminal && (
                <Handle
                    type="source"
                    id="transition-source"
                    position={direction === "LR" ? Position.Right : Position.Bottom}
                    className="!bg-primary !size-3"
                />
            )}
        </Frame>
    );
}
