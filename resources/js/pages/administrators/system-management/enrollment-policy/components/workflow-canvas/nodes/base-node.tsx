import { cn } from "@/lib/utils";
import React from "react";

export interface BaseNodeProps extends React.HTMLAttributes<HTMLDivElement> {
    selected?: boolean;
}

export const BaseNode = React.forwardRef<HTMLDivElement, BaseNodeProps>(({ className, selected, children, ...props }, ref) => {
    return (
        <div
            ref={ref}
            className={cn(
                "bg-card/95 text-card-foreground relative rounded-2xl border shadow-sm backdrop-blur-xs transition-all duration-150",
                "hover:border-foreground/30 hover:shadow-md",
                selected ? "border-primary ring-primary/40 shadow-md ring-2" : "border-border/70",
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
});
BaseNode.displayName = "BaseNode";

export function BaseNodeHeader({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn("border-border/40 flex items-center justify-between gap-2 border-b px-3.5 py-2.5", className)} {...props}>
            {children}
        </div>
    );
}

export function BaseNodeContent({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn("space-y-2 p-3.5", className)} {...props}>
            {children}
        </div>
    );
}

export function BaseNodeFooter({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                "border-border/40 bg-muted/20 text-muted-foreground flex items-center justify-between border-t px-3.5 py-2 text-[11px]",
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
}
