import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Link } from "@inertiajs/react";
import { ArrowRight, CircleAlert, CircleCheck, ClipboardCheck, Landmark, ShieldCheck, UserCheck, Users, Wrench } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { DeskQueueItem, DeskTone } from "./types";

type AttentionQueueProps = {
    items: DeskQueueItem[];
    title?: string;
    description?: string;
};

type AlertVariant = "default" | "destructive" | "warning" | "success" | "info" | "invert";

/**
 * Desk tone -> ReUI Alert/Badge variant. Keeps severity consistent across every desk.
 */
const TONE_VARIANT: Record<DeskTone, AlertVariant> = {
    success: "success",
    warning: "warning",
    info: "info",
    neutral: "default",
};

/**
 * Named by desk key rather than Lucide's kebab-case so the backend can ship a stable token.
 * Anything unrecognised falls back to a neutral icon instead of rendering nothing.
 */
const ICONS: Record<string, LucideIcon> = {
    "banknote": Landmark,
    "briefcase": CircleAlert,
    "clipboard-check": ClipboardCheck,
    help: CircleAlert,
    "shield-check": ShieldCheck,
    tools: Wrench,
    "user-check": UserCheck,
    users: Users,
};

function iconFor(key?: string): LucideIcon {
    return (key && ICONS[key]) || CircleCheck;
}

/**
 * The prioritized action list at the top of a desk.
 *
 * First paint, so this deliberately links out rather than embedding a record list: each row
 * answers "is there something I need to do" and hands the user to the screen that resolves it.
 */
export function AttentionQueue({ items, title = "Needs attention", description = "Items waiting on this desk." }: AttentionQueueProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <FramePanel>
            <FrameHeader>
                <FrameTitle>{title}</FrameTitle>
                <FrameDescription>{description}</FrameDescription>
            </FrameHeader>

            <div className="grid gap-2 px-5 pb-5">
                {items.map((item) => {
                    const Icon = iconFor(item.icon);

                    return (
                        <Alert key={item.id} variant={TONE_VARIANT[item.severity]}>
                            <IconTile variant="soft">
                                <Icon aria-hidden="true" className="size-4" />
                            </IconTile>
                            <AlertTitle>{item.title}</AlertTitle>
                            {item.description ? <AlertDescription>{item.description}</AlertDescription> : null}
                            <AlertAction>
                                <Badge variant={TONE_VARIANT[item.severity]}>{item.count}</Badge>
                                <Button variant="outline" size="sm" render={<Link href={item.href} />}>
                                    Open
                                    <ArrowRight aria-hidden="true" className="size-3.5" />
                                </Button>
                            </AlertAction>
                        </Alert>
                    );
                })}
            </div>
        </FramePanel>
    );
}