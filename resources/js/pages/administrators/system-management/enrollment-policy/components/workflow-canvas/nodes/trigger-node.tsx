import { Badge } from "@/components/reui/badge";
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Zap } from "lucide-react";
import type { TriggerNodeData, WorkflowNode } from "../types";

const channels: Record<string, string> = { public: "Public form", administrator: "Staff registration", continuing: "Continuing student", api: "API" };

export function TriggerNode({ data }: NodeProps<WorkflowNode>) {
    const { direction, channels: allowedChannels, ruleCount } = data as TriggerNodeData;
    return (
        <Frame spacing="sm" className="w-[360px]" data-workflow-trigger>
            <FrameHeader>
                <FrameTitle className="flex items-center gap-2">
                    <Zap className="size-4" /> Enrollment submitted
                </FrameTitle>
            </FrameHeader>
            <FramePanel className="flex flex-col gap-3">
                <Badge variant="primary-light">Submission trigger</Badge>
                <p className="text-muted-foreground text-sm">New enrollment selects a published policy and pins its version.</p>
                <div className="flex flex-wrap gap-2">
                    {allowedChannels.map((channel) => (
                        <Badge key={channel} variant="outline">
                            {channels[channel]}
                        </Badge>
                    ))}
                </div>
                {!allowedChannels.length && <p className="text-xs">No allowed submission channels.</p>}
                <p className="text-muted-foreground text-xs">
                    {ruleCount} policy rule{ruleCount === 1 ? "" : "s"}; entry checks run before actions. Completion checks run at final transition.
                </p>
            </FramePanel>
            <Handle
                type="source"
                position={direction === "LR" ? Position.Right : Position.Bottom}
                className="!bg-primary !size-3"
                isConnectable={false}
            />
        </Frame>
    );
}
