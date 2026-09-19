"use client";

import { cn } from "@/lib/utils";
import { memo, type ComponentProps } from "react";
import { Streamdown } from "streamdown";

export type ResponseProps = ComponentProps<typeof Streamdown>;

/**
 * ElevenLabs UI Response component built on top of streamdown.
 * Provides reliable streaming markdown rendering with smooth character animations,
 * formatted code blocks, tables, lists, and typography.
 *
 * @see https://ui.elevenlabs.io/docs/components/response
 */
export const Response = memo(
    ({ className, ...props }: ResponseProps) => (
        <Streamdown
            className={cn(
                "size-full [&>*:first-child]:mt-0 [&>*:last-child]:mb-0 leading-relaxed text-sm text-foreground",
                className
            )}
            {...props}
        />
    ),
    (prevProps, nextProps) => prevProps.children === nextProps.children
);

Response.displayName = "Response";

export default Response;
