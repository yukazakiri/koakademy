import { CheckCircle2 } from "lucide-react";
import { cn } from "@/lib/utils";
import React from "react";

interface VisualRadioButtonProps extends React.HTMLAttributes<HTMLDivElement> {
    title: string;
    description?: string;
    icon?: React.ReactNode;
    checked?: boolean;
    disabled?: boolean;
    onSelect?: () => void;
}

export function VisualRadioButton({
    title,
    description,
    icon,
    checked = false,
    disabled = false,
    onSelect,
    className,
    ...props
}: VisualRadioButtonProps) {
    return (
        <div
            onClick={() => {
                if (!disabled && onSelect) onSelect();
            }}
            className={cn(
                "relative flex cursor-pointer rounded-xl border p-4 shadow-sm transition-all duration-200",
                checked
                    ? "border-primary bg-primary/5 ring-1 ring-primary/20"
                    : "border-border/60 hover:border-primary/50 hover:bg-muted/30",
                disabled && "cursor-not-allowed opacity-50",
                className
            )}
            {...props}
        >
            <div className="flex w-full items-start gap-3">
                {icon && (
                    <div className={cn("mt-0.5 rounded-lg p-2", checked ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground")}>
                        {icon}
                    </div>
                )}
                <div className="flex flex-col flex-1 gap-1">
                    <div className="flex items-center justify-between">
                        <span className={cn("font-medium leading-none tracking-tight", checked ? "text-primary" : "text-foreground")}>
                            {title}
                        </span>
                        {checked && <CheckCircle2 className="h-4 w-4 text-primary" />}
                    </div>
                    {description && (
                        <p className="text-sm text-muted-foreground leading-snug mt-1.5 line-clamp-2">
                            {description}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
