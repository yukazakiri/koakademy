import { Button } from "@/components/ui/button";
import { Copy } from "lucide-react";
import { toast } from "sonner";
import React from "react";

export type TextEntryProps = {
    label: string;
    value?: string | number | null;
    icon?: React.ReactNode;
    copyable?: boolean;
};

export function TextEntry({ label, value, icon, copyable }: TextEntryProps) {
    return (
        <div className="flex flex-col gap-1">
            <dt className="text-muted-foreground text-xs font-medium tracking-wider uppercase">{label}</dt>
            <dd className="flex items-center gap-2 text-sm font-semibold">
                {icon && <span className="text-muted-foreground">{icon}</span>}
                {value ?? "—"}
                {copyable && value && (
                    <Button variant="ghost" size="icon" className="ml-1 h-4 w-4" onClick={() => navigator.clipboard.writeText(String(value)).then(() => toast.success(`${label} copied to clipboard!`))}>
                        <span className="sr-only">Copy</span>
                        <Copy className="h-3 w-3" />
                    </Button>
                )}
            </dd>
        </div>
    );
}
