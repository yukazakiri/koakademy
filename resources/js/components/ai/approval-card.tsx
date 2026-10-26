import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Check, ShieldAlert, X } from "lucide-react";
import * as React from "react";

export interface PendingToolApproval {
    id: string;
    tool: string;
    arguments?: Record<string, unknown>;
    reason?: string;
}

interface ApprovalCardProps {
    approval: PendingToolApproval;
    onDecision: (id: string, action: "approve" | "reject", result?: string) => void;
    onApproveAll?: () => void;
    disabled?: boolean;
    decision?: "approve" | "reject";
}

export function ApprovalCard({ approval, onDecision, onApproveAll, disabled = false, decision }: ApprovalCardProps) {
    const [rejecting, setRejecting] = React.useState(false);
    const [rejectReason, setRejectReason] = React.useState("");

    const formatToolName = (tool: string) => {
        return tool.replace(/([A-Z])/g, " $1").trim();
    };

    return (
        <div className="my-3 overflow-hidden rounded-xl border border-warning/30 bg-warning/5 dark:bg-warning/10 shadow-xs">
            <div className="flex items-center justify-between border-b border-warning/20 bg-warning/10 dark:bg-warning/15 px-3.5 py-2.5">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-warning-foreground">
                    <ShieldAlert className="size-4 text-warning" />
                    <span>Confirmation Required: {formatToolName(approval.tool)}</span>
                </div>
                <Badge variant="warning-light" size="xs" radius="full">
                    Approval Gate
                </Badge>
            </div>

            <div className="p-3.5 space-y-2.5">
                {decision && (
                    <p role="status" className="text-xs text-muted-foreground">
                        {decision === "approve" ? "Approval" : "Rejection"} selected. Choose an option for each remaining action to send all decisions together.
                    </p>
                )}
                <p className="text-xs text-foreground/90 font-medium leading-relaxed">
                    {approval.reason || "This operation alters official records and requires confirmation."}
                </p>

                {approval.arguments && Object.keys(approval.arguments).length > 0 && (
                    <div className="rounded-lg border border-border bg-background/80 p-2.5 space-y-1 font-mono text-[11px] overflow-x-auto max-h-36">
                        {Object.entries(approval.arguments).map(([key, val]) => (
                            <div key={key} className="flex gap-2">
                                <span className="text-muted-foreground">{key}:</span>
                                <span className="text-foreground">{typeof val === "object" ? JSON.stringify(val) : String(val)}</span>
                            </div>
                        ))}
                    </div>
                )}

                {rejecting && (
                    <div className="space-y-1.5 pt-1">
                        <Input
                            type="text"
                            placeholder="Optional reason for rejection (returned to agent)..."
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            className="text-xs h-8"
                            autoFocus
                        />
                    </div>
                )}
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-warning/15 bg-muted/30 px-3.5 py-2">
                {rejecting ? (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-xs h-7.5"
                            onClick={() => setRejecting(false)}
                            disabled={disabled}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            className="text-xs h-7.5 gap-1.5"
                            onClick={() => onDecision(approval.id, "reject", rejectReason.trim() || undefined)}
                            disabled={disabled}
                        >
                            <X className="size-3.5" />
                            Confirm Rejection
                        </Button>
                    </>
                ) : (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="text-xs h-7.5 text-destructive hover:bg-destructive/10 gap-1.5"
                            onClick={() => setRejecting(true)}
                            disabled={disabled}
                        >
                            <X className="size-3.5" />
                            Reject
                        </Button>
                        {onApproveAll && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="text-xs h-7.5 border-success/40 text-success hover:bg-success/10 gap-1.5"
                                onClick={onApproveAll}
                                disabled={disabled}
                                title="Approve all pending sensitive actions"
                            >
                                <Check className="size-3.5" />
                                Approve All
                            </Button>
                        )}
                        <Button
                            type="button"
                            size="sm"
                            className="text-xs h-7.5 bg-success hover:bg-success/90 text-white gap-1.5"
                            onClick={() => onDecision(approval.id, "approve")}
                            disabled={disabled}
                        >
                            <Check className="size-3.5" />
                            Approve & Execute
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}
