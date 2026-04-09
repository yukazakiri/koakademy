"use client";

import { Link, usePage } from "@inertiajs/react";
import { IconChevronRight, IconCirclePlusFilled, IconMail, type Icon } from "@tabler/icons-react";
import { AnimatePresence, LayoutGroup, motion } from "framer-motion";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuItem as SidebarMenuSubItem,
    SidebarSeparator,
    useSidebar,
} from "@/components/ui/sidebar";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

export type NavItem = {
    id?: string;
    title: string;
    url: string;
    icon?: Icon;
    disabled?: boolean;
    disabledTooltip?: string;
    separator?: boolean;
    badge?: React.ReactNode;
    items?: NavSubItem[];
};

export type NavSubItem = {
    title: string;
    url: string;
    icon?: Icon;
    disabled?: boolean;
    disabledTooltip?: string;
    badge?: React.ReactNode;
    description?: string;
    accentColor?: string;
};

const TAILWIND_COLOR_MAP: Record<string, string> = {
    "bg-red-500": "#ef4444",
    "bg-red-600": "#dc2626",
    "bg-orange-500": "#f97316",
    "bg-orange-600": "#ea580c",
    "bg-amber-500": "#f59e0b",
    "bg-amber-600": "#d97706",
    "bg-yellow-500": "#eab308",
    "bg-yellow-600": "#ca8a04",
    "bg-lime-500": "#84cc16",
    "bg-lime-600": "#65a30d",
    "bg-green-500": "#22c55e",
    "bg-green-600": "#16a34a",
    "bg-emerald-500": "#10b981",
    "bg-emerald-600": "#059669",
    "bg-teal-500": "#14b8a6",
    "bg-teal-600": "#0d9488",
    "bg-cyan-500": "#06b6d4",
    "bg-cyan-600": "#0891b2",
    "bg-sky-500": "#0ea5e9",
    "bg-sky-600": "#0284c7",
    "bg-blue-500": "#3b82f6",
    "bg-blue-600": "#2563eb",
    "bg-indigo-500": "#6366f1",
    "bg-indigo-600": "#4f46e5",
    "bg-violet-500": "#8b5cf6",
    "bg-violet-600": "#7c3aed",
    "bg-purple-500": "#a855f7",
    "bg-purple-600": "#9333ea",
    "bg-fuchsia-500": "#d946ef",
    "bg-fuchsia-600": "#c026d3",
    "bg-pink-500": "#ec4899",
    "bg-pink-600": "#db2777",
    "bg-rose-500": "#f43f5e",
    "bg-rose-600": "#e11d48",
};

function normalizeColor(color: string | undefined): string {
    if (!color) return "#3b82f6";

    if (color.startsWith("#")) return color;

    if (color.startsWith("bg-")) {
        return TAILWIND_COLOR_MAP[color] || "#3b82f6";
    }

    return "#3b82f6";
}

export function NavMain({ items, showQuickActions = true }: { items: NavItem[]; showQuickActions?: boolean }) {
    const { state } = useSidebar();
    const { url: currentUrl } = usePage();
    const isCollapsed = state === "collapsed";
    const [openCollapsible, setOpenCollapsible] = useState<string | null>(null);

    const isRouteActive = (link: string) => {
        return !!(link && link !== "#" && (currentUrl === link || currentUrl.startsWith(`${link}/`)));
    };

    const hasActiveSubItem = (item: NavItem) => {
        return item.items?.some((subItem) => isRouteActive(subItem.url)) ?? false;
    };

    useEffect(() => {
        items.forEach((item, index) => {
            const itemId = item.id || `nav-${index}`;
            if (hasActiveSubItem(item) && openCollapsible !== itemId) {
                setOpenCollapsible(itemId);
            }
        });
    }, [currentUrl, items]);

    const handleOpenChange = (itemId: string, open: boolean) => {
        if (open) {
            setOpenCollapsible(itemId);
        } else {
            const item = items.find((i, idx) => (i.id || `nav-${idx}`) === itemId);
            if (item && !hasActiveSubItem(item)) {
                setOpenCollapsible(null);
            }
        }
    };

    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                {showQuickActions && (
                    <SidebarMenu>
                        <SidebarMenuItem className="flex items-center gap-2">
                            <SidebarMenuButton
                                tooltip="Quick Create"
                                className="bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground min-w-8 duration-200 ease-linear"
                            >
                                <IconCirclePlusFilled />
                                <span>Quick Create</span>
                            </SidebarMenuButton>
                            <Button size="icon" className="size-8 group-data-[collapsible=icon]:opacity-0" variant="outline">
                                <IconMail />
                                <span className="sr-only">Inbox</span>
                            </Button>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                <SidebarMenu>
                    <LayoutGroup id="sidebar-navigation">
                        {items.map((item, index) => {
                            const itemId = item.id || `nav-${index}`;
                            const isOpen = !isCollapsed && openCollapsible === itemId;
                            const hasSubRoutes = !!item.items?.length;
                            const isDisabled = item.disabled;
                            const hasSeparator = item.separator;
                            const isActive = isRouteActive(item.url);
                            const hasActiveChild = hasActiveSubItem(item);

                            return (
                                <div key={itemId}>
                                    {hasSeparator && <SidebarSeparator className="my-2" />}
                                    <SidebarMenuItem>
                                        {hasSubRoutes ? (
                                            <Collapsible
                                                open={isOpen || hasActiveChild}
                                                onOpenChange={(open) => handleOpenChange(itemId, open)}
                                                className="w-full"
                                            >
                                                <CollapsibleTrigger asChild>
                                                    <div className="relative">
                                                        <SidebarMenuButton
                                                            isActive={isOpen || hasActiveChild}
                                                            tooltip={item.title}
                                                            className={cn(
                                                                "relative z-10 transition-all duration-200",
                                                                hasActiveChild && !isOpen && "bg-sidebar-accent/30",
                                                            )}
                                                        >
                                                            {item.icon && (
                                                                <div
                                                                    className={cn(
                                                                        "flex items-center justify-center rounded-md p-1 transition-colors",
                                                                        (isOpen || hasActiveChild) && "bg-primary/10 text-primary",
                                                                    )}
                                                                >
                                                                    <item.icon className="size-4" />
                                                                </div>
                                                            )}
                                                            <span className="flex-1 text-sm font-medium">{item.title}</span>
                                                            {item.badge && <span className="ml-1">{item.badge}</span>}
                                                            <motion.div
                                                                initial={false}
                                                                animate={{ rotate: isOpen || hasActiveChild ? 90 : 0 }}
                                                                transition={{ duration: 0.2, ease: "easeInOut" }}
                                                            >
                                                                <IconChevronRight className="text-muted-foreground size-4" />
                                                            </motion.div>
                                                        </SidebarMenuButton>
                                                        {(isOpen || hasActiveChild) && !isCollapsed && (
                                                            <motion.div
                                                                layoutId={`active-sidebar-${itemId}`}
                                                                className="bg-sidebar-accent/40 border-sidebar-border/50 absolute inset-0 z-0 rounded-lg border"
                                                                initial={{ opacity: 0, scale: 0.98 }}
                                                                animate={{ opacity: 1, scale: 1 }}
                                                                exit={{ opacity: 0, scale: 0.98 }}
                                                                transition={{ duration: 0.15 }}
                                                            />
                                                        )}
                                                    </div>
                                                </CollapsibleTrigger>

                                                {!isCollapsed && (
                                                    <AnimatePresence initial={false}>
                                                        {(isOpen || hasActiveChild) && (
                                                            <CollapsibleContent forceMount>
                                                                <motion.div
                                                                    initial={{ height: 0, opacity: 0 }}
                                                                    animate={{ height: "auto", opacity: 1 }}
                                                                    exit={{ height: 0, opacity: 0 }}
                                                                    transition={{ duration: 0.2, ease: "easeInOut" }}
                                                                    className="overflow-hidden"
                                                                >
                                                                    <SidebarMenuSub className="border-primary/20 relative my-1 ml-2 border-l-2 pl-3">
                                                                        {item.items?.map((subItem, subIndex) => {
                                                                            const isSubDisabled = subItem.disabled;
                                                                            const isSubActive = isRouteActive(subItem.url);
                                                                            const subItemId = `${itemId}-${subIndex}`;

                                                                            if (isSubDisabled) {
                                                                                return (
                                                                                    <SidebarMenuSubItem key={subItemId} className="h-auto">
                                                                                        <TooltipProvider>
                                                                                            <Tooltip>
                                                                                                <TooltipTrigger asChild>
                                                                                                    <span className="text-muted-foreground/50 flex cursor-not-allowed items-center gap-2 rounded-md px-3 py-2 text-sm">
                                                                                                        {subItem.title}
                                                                                                    </span>
                                                                                                </TooltipTrigger>
                                                                                                <TooltipContent side="right">
                                                                                                    <p>{subItem.disabledTooltip || "Coming Soon"}</p>
                                                                                                </TooltipContent>
                                                                                            </Tooltip>
                                                                                        </TooltipProvider>
                                                                                    </SidebarMenuSubItem>
                                                                                );
                                                                            }

                                                                            const hasDescription = !!subItem.description;
                                                                            const normalizedAccentColor = normalizeColor(subItem.accentColor);
                                                                            const gradientStyle = {
                                                                                background: `linear-gradient(90deg, ${normalizedAccentColor}15 0%, transparent 100%)`,
                                                                                borderLeft: `3px solid ${normalizedAccentColor}`,
                                                                            };

                                                                            const content = (
                                                                                <span className="min-w-0 flex-1 truncate text-sm">
                                                                                    {subItem.title}
                                                                                </span>
                                                                            );

                                                                            const badgeContent = subItem.badge && (
                                                                                <span className="flex-shrink-0">{subItem.badge}</span>
                                                                            );

                                                                            return (
                                                                                <SidebarMenuSubItem key={subItemId} className="relative h-auto">
                                                                                    {hasDescription ? (
                                                                                        <TooltipProvider delayDuration={300}>
                                                                                            <Tooltip>
                                                                                                <TooltipTrigger asChild>
                                                                                                    <SidebarMenuSubButton
                                                                                                        asChild
                                                                                                        className={cn(
                                                                                                            "relative z-10 rounded-md transition-all duration-150",
                                                                                                            isSubActive && "font-medium",
                                                                                                        )}
                                                                                                        isActive={isSubActive}
                                                                                                        style={gradientStyle}
                                                                                                    >
                                                                                                        <Link
                                                                                                            href={subItem.url}
                                                                                                            className="flex items-center gap-2 px-3 py-2"
                                                                                                        >
                                                                                                            {content}
                                                                                                            {badgeContent}
                                                                                                        </Link>
                                                                                                    </SidebarMenuSubButton>
                                                                                                </TooltipTrigger>
                                                                                                <TooltipContent side="right" className="max-w-xs">
                                                                                                    <p className="font-medium">
                                                                                                        {subItem.description}
                                                                                                    </p>
                                                                                                </TooltipContent>
                                                                                            </Tooltip>
                                                                                        </TooltipProvider>
                                                                                    ) : (
                                                                                        <SidebarMenuSubButton
                                                                                            asChild
                                                                                            className={cn(
                                                                                                "relative z-10 rounded-md transition-all duration-150",
                                                                                                isSubActive && "font-medium",
                                                                                            )}
                                                                                            isActive={isSubActive}
                                                                                            style={gradientStyle}
                                                                                        >
                                                                                            <Link
                                                                                                href={subItem.url}
                                                                                                className="flex items-center gap-2 px-3 py-2"
                                                                                            >
                                                                                                {content}
                                                                                                {badgeContent}
                                                                                            </Link>
                                                                                        </SidebarMenuSubButton>
                                                                                    )}
                                                                                </SidebarMenuSubItem>
                                                                            );
                                                                        })}
                                                                    </SidebarMenuSub>
                                                                </motion.div>
                                                            </CollapsibleContent>
                                                        )}
                                                    </AnimatePresence>
                                                )}
                                            </Collapsible>
                                        ) : isDisabled ? (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <SidebarMenuButton className="text-muted-foreground/50">
                                                            {item.icon && <item.icon />}
                                                            <span className="text-sm font-medium">{item.title}</span>
                                                        </SidebarMenuButton>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="right">
                                                        <p>{item.disabledTooltip || "Coming Soon"}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ) : (
                                            <div className="relative">
                                                <SidebarMenuButton
                                                    tooltip={item.title}
                                                    asChild
                                                    isActive={isActive}
                                                    className={cn("relative z-10 transition-all duration-200", isActive && "bg-sidebar-accent")}
                                                >
                                                    <Link href={item.url}>
                                                        {item.icon && (
                                                            <div
                                                                className={cn(
                                                                    "flex items-center justify-center rounded-md p-1 transition-colors",
                                                                    isActive && "bg-primary/10 text-primary",
                                                                )}
                                                            >
                                                                <item.icon className="size-4" />
                                                            </div>
                                                        )}
                                                        <span
                                                            className={cn(
                                                                "relative z-10 text-sm font-medium",
                                                                isActive ? "text-sidebar-accent-foreground" : "",
                                                            )}
                                                        >
                                                            {item.title}
                                                        </span>
                                                        {item.badge && <span className="ml-1">{item.badge}</span>}
                                                    </Link>
                                                </SidebarMenuButton>
                                                {isActive && (
                                                    <motion.div
                                                        layoutId="active-sidebar-item"
                                                        className="bg-sidebar-accent border-sidebar-border/50 absolute inset-0 z-0 rounded-lg border"
                                                        transition={{
                                                            type: "spring",
                                                            stiffness: 300,
                                                            damping: 30,
                                                        }}
                                                    />
                                                )}
                                            </div>
                                        )}
                                    </SidebarMenuItem>
                                </div>
                            );
                        })}
                    </LayoutGroup>
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
