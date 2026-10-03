import { AttentionQueue } from "@/components/dashboards/attention-queue";
import { DeskAdaptations } from "@/components/dashboards/desk-adaptations";
import { KpiStrip } from "@/components/dashboards/kpi-strip";
import { QueueTable } from "@/components/dashboards/queue-table";
import { TrendCard } from "@/components/dashboards/trend-card";
import type { DeskContext, DeskKpi, DeskMeta, DeskOption, DeskQueueItem, DeskScope, DeskTable, DeskTrend } from "@/components/dashboards/types";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import {
    Banknote,
    BookOpen,
    Building2,
    CalendarDays,
    Clock,
    Globe2,
    GraduationCap,
    HeartHandshake,
    Landmark,
    LayoutDashboard,
    Server,
    Users,
    type LucideIcon,
} from "lucide-react";

type DeskShellProps = {
    desk: DeskMeta;
    desks: DeskOption[];
    context: DeskContext;
    scope?: DeskScope | null;
    kpis: DeskKpi[];
    queues: DeskQueueItem[];
    /**
     * Deferred: absent until the desk-secondary group resolves on first paint, so these are
     * optional on purpose. Defaulting to [] stops a `.map()` on undefined during
     * the window between first paint and the deferred request completing.
     */
    trends?: DeskTrend[];
    tables?: DeskTable[];
};

const DESK_ICONS: Record<string, LucideIcon> = {
    executive: Landmark,
    accounting: Banknote,
    registrar: GraduationCap,
    academic: BookOpen,
    hr: Users,
    "student-affairs": HeartHandshake,
    "it-admin": Server,
};

function getDeskIcon(id: string): LucideIcon {
    return DESK_ICONS[id] ?? LayoutDashboard;
}

/**
 * Modernized administrative desk shell.
 *
 * Includes top breadcrumbs navigation, ReUI Frame header chrome, elevated switcher pill capsule
 * with glowing active state and full keyboard accessibility, semantic scope and context badges,
 * and role-tailored layout variations.
 */
export function DeskShell({ desk, desks, context, scope, kpis, queues, trends, tables }: DeskShellProps) {
    const switchDesk = (url: string) => {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const CurrentIcon = getDeskIcon(desk.id);
    const isExecutive = desk.id === "executive";

    return (
        <div className="flex flex-col gap-6">
            {/* Top Breadcrumbs Navigation */}
            <Breadcrumb className="px-0.5">
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink href="/administrators/dashboard">Dashboard</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbLink href={desks[0]?.url || "/administrators/desks/executive"}>Work Desks</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage className="text-foreground font-semibold">{desk.title} Desk</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>

            {/* Elevated Desk Header Frame */}
            <Frame variant="default" spacing="default" className="shadow-xs">
                <FramePanel className="bg-card">
                    <FrameHeader className="border-border/40 border-b pb-4 sm:pb-5">
                        <div className="flex flex-col justify-between gap-5 xl:flex-row xl:items-center">
                            {/* Desk Identity */}
                            <div className="flex items-start gap-4">
                                <IconTile variant="soft" size="lg" className="text-primary mt-0.5 hidden shrink-0 sm:inline-flex">
                                    <CurrentIcon className="size-5" />
                                </IconTile>
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <FrameTitle className="text-foreground text-xl font-bold tracking-tight sm:text-2xl">
                                            {desk.title} Desk
                                        </FrameTitle>
                                        <Badge
                                            variant="primary-light"
                                            size="sm"
                                            radius="full"
                                            className="font-mono text-[10px] font-semibold tracking-wider uppercase"
                                        >
                                            Role Desk
                                        </Badge>
                                    </div>
                                    <FrameDescription className="text-muted-foreground max-w-2xl text-sm leading-normal">
                                        {desk.description}
                                    </FrameDescription>
                                </div>
                            </div>

                            {/* Elevated Desk Switcher Pill Capsule */}
                            {desks.length > 1 ? (
                                <div className="flex flex-col gap-2 self-start xl:self-center">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                            Switch Active Desk:
                                        </span>
                                    </div>
                                    <div
                                        role="tablist"
                                        aria-label="Administrative Desks"
                                        className="bg-muted/60 dark:bg-muted/40 border-border/60 inline-flex flex-wrap gap-1 rounded-xl border p-1 shadow-2xs backdrop-blur-xs"
                                    >
                                        {desks.map((option) => {
                                            const isActive = option.id === desk.id;
                                            const OptionIcon = getDeskIcon(option.id);

                                            return (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    role="tab"
                                                    aria-selected={isActive}
                                                    aria-current={isActive ? "page" : undefined}
                                                    onClick={() => switchDesk(option.url)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === "Enter" || e.key === " ") {
                                                            e.preventDefault();
                                                            switchDesk(option.url);
                                                        }
                                                    }}
                                                    className={cn(
                                                        "group focus-visible:ring-primary relative inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition-all duration-200 outline-none select-none focus-visible:ring-2 focus-visible:ring-offset-1",
                                                        isActive
                                                            ? "bg-background text-foreground ring-border/60 font-bold shadow-xs ring-1"
                                                            : "text-muted-foreground hover:bg-background/60 hover:text-foreground font-medium",
                                                    )}
                                                >
                                                    <OptionIcon
                                                        className={cn(
                                                            "size-3.5 shrink-0 transition-transform group-hover:scale-110",
                                                            isActive ? "text-primary" : "text-muted-foreground/80",
                                                        )}
                                                    />
                                                    <span>{option.title}</span>
                                                    {isActive && (
                                                        <span
                                                            className="bg-primary size-1.5 animate-pulse rounded-full shadow-[0_0_8px_rgba(99,102,241,0.6)]"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    </FrameHeader>

                    {/* Semantic Scope & Context Badges with IconTiles */}
                    <FrameFooter className="pt-3">
                        <div className="flex flex-wrap items-center gap-3 text-xs">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Scope & Context:</span>
                            <div className="flex flex-wrap items-center gap-2.5">
                                {scope ? (
                                    <div className="inline-flex items-center gap-1.5">
                                        <IconTile size="xs" variant="soft" className="text-primary">
                                            <Building2 className="size-3" />
                                        </IconTile>
                                        <Badge variant="primary-light" size="sm" radius="full" className="gap-1.5 font-medium">
                                            <span>{scope.name}</span>
                                            <span className="font-mono text-[10px] font-semibold opacity-75">[{scope.code}]</span>
                                        </Badge>
                                    </div>
                                ) : (
                                    <div className="inline-flex items-center gap-1.5">
                                        <IconTile size="xs" variant="outline" className="text-muted-foreground">
                                            <Globe2 className="size-3" />
                                        </IconTile>
                                        <Badge variant="outline" size="sm" radius="full" className="text-muted-foreground">
                                            Institution-wide
                                        </Badge>
                                    </div>
                                )}

                                <div className="inline-flex items-center gap-1.5">
                                    <IconTile size="xs" variant="soft" className="text-sky-600 dark:text-sky-400">
                                        <CalendarDays className="size-3" />
                                    </IconTile>
                                    <Badge variant="info-light" size="sm" radius="full" className="font-medium">
                                        {context.period_label}
                                    </Badge>
                                </div>

                                <div className="inline-flex items-center gap-1.5">
                                    <IconTile size="xs" variant="soft" className="text-amber-600 dark:text-amber-400">
                                        <Clock className="size-3" />
                                    </IconTile>
                                    <Badge variant="warning-light" size="sm" radius="full" className="font-medium">
                                        {context.range_label}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </FrameFooter>
                </FramePanel>
            </Frame>

            {/* First Paint: Headline KPIs (Divided ribbon for executive desk, responsive card grid for others) */}
            <KpiStrip kpis={kpis} caption={`Headline figures for ${context.period_label}.`} layout={isExecutive ? "divided" : "grid"} />

            {/* Desk-Specific Adaptations (Cashier drawer & gauge, workload alerts, telemetry, velocity) */}
            <DeskAdaptations desk={desk} kpis={kpis} queues={queues} trends={trends} tables={tables} scope={scope} context={context} />

            {/* Operational Attention Queue */}
            <AttentionQueue items={queues} />

            {/* Deferred Payload: Trends */}
            {trends && trends.length > 0 ? (
                <div className={cn("grid gap-6", trends.length > 1 ? "grid-cols-1 xl:grid-cols-2" : "grid-cols-1")}>
                    {trends.map((trend) => (
                        <TrendCard key={trend.id} trend={trend} range={context.range} />
                    ))}
                </div>
            ) : null}

            {/* Deferred Payload: Status Tables */}
            {tables && tables.length > 0 ? (
                <div className={cn("grid gap-6", tables.length > 1 ? "grid-cols-1 xl:grid-cols-2" : "grid-cols-1")}>
                    {tables.map((table) => (
                        <QueueTable key={table.id} table={table} />
                    ))}
                </div>
            ) : null}
        </div>
    );
}
