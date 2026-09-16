import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
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
    disabled?: boolean;
}

export function ApprovalCard({ approval, onDecision, disabled = false }: ApprovalCardProps) {
    const [rejecting, setRejecting] = React.useState(false);
    const [rejectReason, setRejectReason] = React.useState("");

    const formatToolName = (tool: string) => {
        return tool.replace(/([A-Z])/g, " $1").trim();
    };

    return (
        <Card className="border-amber-500/30 bg-amber-500/5 dark:bg-amber-500/10 shadow-sm overflow-hidden my-3">
            <CardHeader className="py-3 px-4 bg-amber-500/10 dark:bg-amber-500/15 border-b border-amber-500/20">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-xs font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                        <ShieldAlert className="size-4" />
                        Confirmation Required: {formatToolName(approval.tool)}
                    </CardTitle>
                    <Badge variant="outline" className="text-[10px] bg-amber-500/10 text-amber-600 border-amber-500/30">
                        Approval Gate
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="p-4 space-y-3">
                <p className="text-xs text-foreground/90 font-medium">
                    {approval.reason || "This operation alters official records and requires confirmation."}
                </p>

                {approval.arguments && Object.keys(approval.arguments).length > 0 && (
                    <div className="rounded-md border bg-background/80 p-2.5 space-y-1 font-mono text-[11px] overflow-x-auto max-h-36">
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
            </CardContent>

            <CardFooter className="py-2.5 px-4 bg-muted/20 border-t flex items-center justify-end gap-2">
                {rejecting ? (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-xs h-8"
                            onClick={() => setRejecting(false)}
                            disabled={disabled}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            className="text-xs h-8 gap-1.5"
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
                            className="text-xs h-8 text-destructive hover:bg-destructive/10 gap-1.5"
                            onClick={() => setRejecting(true)}
                            disabled={disabled}
                        >
                            <X className="size-3.5" />
                            Reject
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="text-xs h-8 bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                            onClick={() => onDecision(approval.id, "approve")}
                            disabled={disabled}
                        >
                            <Check className="size-3.5" />
                            Approve & Execute
                        </Button>
                    </>
                )}
            </CardFooter>
        </Card>
    );
}
