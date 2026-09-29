"use client";

import { Button } from "@/components/ui/button";
import { MessageFooter } from "@/components/ui/message";
import { cn } from "@/lib/utils";
import { CheckIcon, CopyIcon, RefreshCwIcon } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

/**
 * Chat message action footers adapted from the ReUI `c-message-4` example
 * ("Reply actions in the footer": copy + regenerate controls).
 *
 * @see https://reui.io/components/message/c-message-4
 * @see https://reui.io/components/message
 */

function useCopiedFlag(timeoutMs = 1600) {
    const [copied, setCopied] = React.useState(false);

    React.useEffect(() => {
        if (!copied) return;
        const id = window.setTimeout(() => setCopied(false), timeoutMs);
        return () => window.clearTimeout(id);
    }, [copied, timeoutMs]);

    return [copied, setCopied] as const;
}

function copyText(content: string, onCopied: () => void) {
    // Optional-chained twice: a page served over plain http has no
    // navigator.clipboard at all. The label flips either way.
    void navigator.clipboard?.writeText?.(content);
    onCopied();
    toast.success("Copied to clipboard.");
}

interface MessageActionsBaseProps {
    content: string;
    disabled?: boolean;
    className?: string;
}

export function UserMessageActions({
    content,
    disabled = false,
    className,
    onResend,
}: MessageActionsBaseProps & { onResend: () => void }) {
    const [copied, setCopied] = useCopiedFlag();

    return (
        <MessageFooter className={cn("gap-0.5 px-0", className)}>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={copied ? "Message copied" : "Copy message"}
                title={copied ? "Copied" : "Copy"}
                disabled={disabled}
                onClick={() => copyText(content, () => setCopied(true))}
            >
                {copied ? <CheckIcon aria-hidden="true" /> : <CopyIcon aria-hidden="true" />}
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label="Resend message"
                title="Resend"
                disabled={disabled}
                onClick={onResend}
            >
                <RefreshCwIcon aria-hidden="true" />
            </Button>
        </MessageFooter>
    );
}

export function AssistantMessageActions({
    content,
    disabled = false,
    className,
    onRegenerate,
}: MessageActionsBaseProps & { onRegenerate: () => void }) {
    const [copied, setCopied] = useCopiedFlag();

    return (
        <MessageFooter className={cn("gap-0.5 px-0", className)}>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={copied ? "Reply copied" : "Copy reply"}
                title={copied ? "Copied" : "Copy"}
                disabled={disabled}
                onClick={() => copyText(content, () => setCopied(true))}
            >
                {copied ? <CheckIcon aria-hidden="true" /> : <CopyIcon aria-hidden="true" />}
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label="Regenerate reply"
                title="Regenerate"
                disabled={disabled}
                onClick={onRegenerate}
            >
                <RefreshCwIcon aria-hidden="true" />
            </Button>
        </MessageFooter>
    );
}
