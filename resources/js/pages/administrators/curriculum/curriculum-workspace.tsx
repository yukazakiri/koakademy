import { IconTile } from "@/components/reui/icon-tile";
import { cn } from "@/lib/utils";
import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

/** Shared curriculum surfaces keep page and form hierarchy consistent. */
export function Workspace({ children }: { children: ReactNode }) {
    return <div className="flex min-w-0 flex-col gap-6 pb-6">{children}</div>;
}

export function WorkspaceMetric({ icon: Icon, label, value, detail }: { icon: LucideIcon; label: string; value: number | string; detail: string }) {
    return (
        <div className="bg-card flex items-start gap-4 rounded-xl border p-5">
            <IconTile variant="soft">
                <Icon />
            </IconTile>
            <div className="min-w-0">
                <p className="text-muted-foreground text-xs font-medium">{label}</p>
                <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">{value}</p>
                <p className="text-muted-foreground mt-1 text-xs leading-5">{detail}</p>
            </div>
        </div>
    );
}

export function FormSection({
    title,
    description,
    children,
    className,
    id,
}: {
    title: string;
    description: string;
    children: ReactNode;
    className?: string;
    id?: string;
}) {
    return (
        <section id={id} className={cn("bg-card scroll-mt-20 rounded-xl border p-5 sm:p-6", className)}>
            <div className="mb-5 border-b pb-4">
                <h3 className="text-sm font-semibold">{title}</h3>
                <p className="text-muted-foreground mt-1 text-sm leading-5">{description}</p>
            </div>
            <div className="space-y-5">{children}</div>
        </section>
    );
}
