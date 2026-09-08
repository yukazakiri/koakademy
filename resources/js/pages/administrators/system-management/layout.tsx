import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { AdminLink } from "@/lib/admin-navigation";
import { cn } from "@/lib/utils";
import { Head, Link } from "@inertiajs/react";
import { motion, useReducedMotion } from "framer-motion";
import {
    ChevronRight,
    Compass,
    Grid,
    Lock,
    PanelLeft,
    PanelLeftClose,
    Settings2,
    SlidersHorizontal,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";

import type { User } from "@/types/user";
import {
    categoryThemes,
    getSiblingSettingsItems,
    getSystemSettingsItem,
    getSystemSettingsStatus,
    SystemSettingsNavigation,
} from "./settings-catalog";
import type { SystemManagementAccess, SystemManagementSectionKey } from "./types";

interface SystemManagementLayoutProps {
    user: User;
    access: SystemManagementAccess;
    activeSection: SystemManagementSectionKey;
    heading: string;
    description: string;
    children: ReactNode;
}

export default function SystemManagementLayout({
    user,
    access,
    activeSection,
    heading,
    description,
    children,
}: SystemManagementLayoutProps) {
    const reducedMotion = useReducedMotion();
    const item = getSystemSettingsItem(activeSection);
    const categoryTheme = categoryThemes[item.group];

    const categoryLabel =
        item.group === "academic_operations"
            ? "Academic Operations"
            : item.group === "integrations"
              ? "Access & Integrations"
              : item.group[0].toUpperCase() + item.group.slice(1);

    const canUpdateActiveSection = access.sections[activeSection]?.can_update ?? false;
    const status = getSystemSettingsStatus(item, access);
    const StatusIcon = status.icon;
    const siblingItems = getSiblingSettingsItems(activeSection, access);

    const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(() => {
        if (typeof window !== "undefined") {
            return localStorage.getItem("system_mgmt_sidebar_collapsed") === "true";
        }
        return false;
    });

    useEffect(() => {
        if (typeof window !== "undefined") {
            localStorage.setItem("system_mgmt_sidebar_collapsed", String(isSidebarCollapsed));
        }
    }, [isSidebarCollapsed]);

    const transitionConfig = reducedMotion
        ? { duration: 0 }
        : { type: "spring" as const, bounce: 0.1, duration: 0.28 };

    return (
        <AdminLayout user={user} title="System Settings">
            <Head title={`System Settings • ${heading}`} />

            <div className="system-settings mx-auto w-full max-w-[94rem] space-y-5">
                {/* Sleek Top Navigation Bar & Action Rail */}
                <div className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-card/60 p-4 shadow-xs backdrop-blur-md sm:p-5">
                    {/* Breadcrumbs & Controls */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/40 pb-3">
                        <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <AdminLink
                                href="/administrators/system-management"
                                prefetch
                                cacheFor="30s"
                                className="font-medium transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring rounded-sm px-1 py-0.5"
                            >
                                System Management
                            </AdminLink>
                            <ChevronRight className="size-3.5 text-muted-foreground/60 shrink-0" aria-hidden="true" />
                            <span className="font-medium text-foreground/80">{categoryLabel}</span>
                            <ChevronRight className="size-3.5 text-muted-foreground/60 shrink-0" aria-hidden="true" />
                            <span className="font-semibold text-foreground truncate">{item.label}</span>
                        </nav>

                        <div className="flex items-center gap-1.5">
                            <TooltipProvider delay={150}>
                                <Tooltip>
                                    <TooltipTrigger
                                        render={
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setIsSidebarCollapsed((prev) => !prev)}
                                                className="hidden lg:inline-flex h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
                                                aria-label={isSidebarCollapsed ? "Expand settings rail" : "Collapse settings rail"}
                                            >
                                                {isSidebarCollapsed ? (
                                                    <PanelLeft className="size-4" />
                                                ) : (
                                                    <PanelLeftClose className="size-4" />
                                                )}
                                            </Button>
                                        }
                                    />
                                    <TooltipContent side="bottom" className="text-xs">
                                        {isSidebarCollapsed ? "Expand settings rail" : "Collapse settings rail"}
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <Link
                                href="/administrators/system-management"
                                prefetch
                                cacheFor="30s"
                                className="hidden sm:inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-border/60 bg-background/50 hover:bg-muted/80 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <Grid className="size-3.5" />
                                <span>All Settings</span>
                            </Link>

                            <SystemSettingsNavigation access={access} activeSection={activeSection} mobile />
                        </div>
                    </div>

                    {/* Section Hero Block */}
                    <div className="flex flex-col gap-4 pt-1 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-start gap-3.5 min-w-0">
                            <div
                                className={cn(
                                    "relative mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-xl border shadow-xs transition-colors",
                                    categoryTheme.accentBg,
                                    categoryTheme.accentBorder,
                                    categoryTheme.accentText,
                                )}
                            >
                                <item.icon className="size-5" aria-hidden="true" />
                            </div>

                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={cn("text-[11px] font-semibold tracking-wider uppercase", categoryTheme.accentText)}>
                                        {categoryLabel}
                                    </span>
                                    <span className="text-muted-foreground/40">•</span>
                                    <Badge
                                        variant="outline"
                                        className={cn("h-5 gap-1.5 px-2 text-[11px] font-normal border-border/60 bg-background/60", status.badgeClass)}
                                    >
                                        <span className={cn("size-1.5 rounded-full shrink-0", status.dotColor)} aria-hidden="true" />
                                        {status.label}
                                    </Badge>
                                </div>

                                <h1 className="mt-1 text-xl font-bold tracking-tight text-foreground sm:text-2xl lg:text-[1.65rem]">
                                    {heading}
                                </h1>
                                <p className="mt-1 max-w-3xl text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                    {description}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Sibling Scope Bar (Category Quick Jump) */}
                    {siblingItems.length > 1 && (
                        <div className="border-t border-border/40 pt-3">
                            <div className="flex items-center gap-1 overflow-x-auto pb-0.5 scrollbar-none">
                                <span className="text-[11px] font-medium text-muted-foreground/70 pr-2 shrink-0 flex items-center gap-1">
                                    <SlidersHorizontal className="size-3" />
                                    <span>In this category:</span>
                                </span>
                                {siblingItems.map((sibling) => {
                                    const isCurrent = sibling.key === activeSection;
                                    const SiblingIcon = sibling.icon;

                                    return (
                                        <Link
                                            key={sibling.key}
                                            href={sibling.href}
                                            prefetch
                                            cacheFor="30s"
                                            className={cn(
                                                "group inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition-all shrink-0 border outline-none",
                                                isCurrent
                                                    ? cn(
                                                          "border-border/80 bg-background text-foreground shadow-xs font-semibold",
                                                          categoryTheme.accentBorder,
                                                      )
                                                    : "border-transparent text-muted-foreground hover:bg-muted/70 hover:text-foreground",
                                            )}
                                        >
                                            <SiblingIcon
                                                className={cn(
                                                    "size-3.5 transition-colors",
                                                    isCurrent ? categoryTheme.accentText : "text-muted-foreground/70 group-hover:text-foreground",
                                                )}
                                                aria-hidden="true"
                                            />
                                            <span>{sibling.label}</span>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* Master Detail Workspace */}
                <div
                    className={cn(
                        "grid gap-6 items-start transition-[grid-template-columns] duration-200 ease-out",
                        isSidebarCollapsed ? "lg:grid-cols-[3rem_minmax(0,1fr)]" : "lg:grid-cols-[16rem_minmax(0,1fr)] xl:grid-cols-[17rem_minmax(0,1fr)]",
                    )}
                >
                    <SystemSettingsNavigation
                        access={access}
                        activeSection={activeSection}
                        collapsed={isSidebarCollapsed}
                    />

                    <main className="min-w-0 space-y-6">
                        {!canUpdateActiveSection && (
                            <Alert className="border-amber-500/30 bg-amber-500/5 text-amber-900 dark:text-amber-200">
                                <Lock className="size-4 text-amber-600 dark:text-amber-400" />
                                <AlertTitle className="text-sm font-semibold">Read-only configuration</AlertTitle>
                                <AlertDescription className="text-xs text-muted-foreground">
                                    Your account role has permissions to review this institutional configuration, but editing permissions are restricted.
                                </AlertDescription>
                            </Alert>
                        )}

                        <motion.fieldset
                            initial={reducedMotion ? false : { opacity: 0, y: 6 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={transitionConfig}
                            disabled={!canUpdateActiveSection}
                            className="min-w-0 space-y-6 border-0 p-0 disabled:cursor-not-allowed"
                        >
                            {children}
                        </motion.fieldset>
                    </main>
                </div>
            </div>
        </AdminLayout>
    );
}
