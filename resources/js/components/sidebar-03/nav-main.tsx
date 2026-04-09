"use client";

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import {
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
import { Link, usePage } from "@inertiajs/react";
import { ChevronDown, ChevronUp } from "lucide-react";
import React, { useState } from "react";

export type Route = {
    id: string;
    title: string;
    icon?: React.ReactNode;
    link: string;
    disabled?: boolean;
    disabledTooltip?: string;
    separator?: boolean;
    badge?: React.ReactNode;
    subs?: {
        title: string;
        link: string;
        icon?: React.ReactNode;
        disabled?: boolean;
        disabledTooltip?: string;
        badge?: React.ReactNode;
    }[];
};

import { LayoutGroup, motion } from "framer-motion";

export default function DashboardNavigation({ routes }: { routes: Route[] }) {
    const { state } = useSidebar();
    const { url } = usePage();
    const isCollapsed = state === "collapsed";
    const [openCollapsible, setOpenCollapsible] = useState<string | null>(null);

    // Helper to determine active state
    const isRouteActive = (link: string) => {
        return !!(link && link !== "#" && (url === link || url.startsWith(`${link}/`)));
    };

    return (
        <SidebarMenu>
            <LayoutGroup id="sidebar-navigation">
                {routes.map((route) => {
                    const isOpen = !isCollapsed && openCollapsible === route.id;
                    const hasSubRoutes = !!route.subs?.length;
                    const isDisabled = route.disabled;
                    const hasSeparator = route.separator;
                    const isActive = isRouteActive(route.link);

                    return (
                        <React.Fragment key={route.id}>
                            {hasSeparator && <SidebarSeparator className="my-2" />}
                            <SidebarMenuItem>
                                {hasSubRoutes ? (
                                    <Collapsible open={isOpen} onOpenChange={(open) => setOpenCollapsible(open ? route.id : null)} className="w-full">
                                        <CollapsibleTrigger asChild>
                                            <div className="relative">
                                                <SidebarMenuButton
                                                    isActive={isOpen}
                                                    tooltip={route.title}
                                                    className="relative z-10 transition-colors"
                                                >
                                                    {route.icon}
                                                    {!isCollapsed && <span className="ml-2 flex-1 text-sm font-medium">{route.title}</span>}
                                                    {!isCollapsed && route.badge && <span className="ml-1">{route.badge}</span>}
                                                    {!isCollapsed && hasSubRoutes && (
                                                        <span className="ml-auto">
                                                            {isOpen ? <ChevronUp className="size-4" /> : <ChevronDown className="size-4" />}
                                                        </span>
                                                    )}
                                                </SidebarMenuButton>
                                                {/* Animated background for Open/Active parent */}
                                                {isOpen && !isCollapsed && (
                                                    <motion.div
                                                        layoutId="active-sidebar-parent"
                                                        className="bg-sidebar-accent/50 absolute inset-0 z-0 rounded-lg"
                                                        initial={{ opacity: 0 }}
                                                        animate={{ opacity: 1 }}
                                                        exit={{ opacity: 0 }}
                                                    />
                                                )}
                                            </div>
                                        </CollapsibleTrigger>

                                        {!isCollapsed && (
                                            <CollapsibleContent>
                                                <SidebarMenuSub className="relative my-1 ml-3.5">
                                                    {/* Vertical line for hierarchy is handled by SidebarMenuSub styling usually */}
                                                    {route.subs?.map((subRoute) => {
                                                        const isSubDisabled = subRoute.disabled;
                                                        const isSubActive = isRouteActive(subRoute.link);

                                                        if (isSubDisabled) {
                                                            return (
                                                                <SidebarMenuSubItem key={`${route.id}-${subRoute.title}`} className="h-auto">
                                                                    {/* Disabled item logic */}
                                                                    <TooltipProvider>
                                                                        <Tooltip>
                                                                            <TooltipTrigger asChild>
                                                                                <span className="text-muted-foreground/50 flex cursor-not-allowed items-center rounded-md px-4 py-1.5 text-sm font-medium">
                                                                                    {subRoute.title}
                                                                                </span>
                                                                            </TooltipTrigger>
                                                                            <TooltipContent side="right">
                                                                                <p>{subRoute.disabledTooltip || "Coming Soon"}</p>
                                                                            </TooltipContent>
                                                                        </Tooltip>
                                                                    </TooltipProvider>
                                                                </SidebarMenuSubItem>
                                                            );
                                                        }

                                                        return (
                                                            <SidebarMenuSubItem key={`${route.id}-${subRoute.title}`} className="relative h-auto">
                                                                <SidebarMenuSubButton
                                                                    asChild
                                                                    className="relative z-10 bg-transparent transition-colors hover:bg-transparent data-[active=true]:bg-transparent"
                                                                >
                                                                    <Link href={subRoute.link}>
                                                                        {/* We need to span the text to put it above the background */}
                                                                        <span
                                                                            className={cn(
                                                                                "relative z-10",
                                                                                isSubActive ? "text-sidebar-accent-foreground font-medium" : "",
                                                                            )}
                                                                        >
                                                                            {subRoute.title}
                                                                        </span>
                                                                    </Link>
                                                                </SidebarMenuSubButton>
                                                                {isSubActive && (
                                                                    <motion.div
                                                                        layoutId="active-sidebar-subitem"
                                                                        className="bg-sidebar-accent absolute inset-0 z-0 rounded-md"
                                                                        transition={{
                                                                            type: "spring",
                                                                            stiffness: 300,
                                                                            damping: 30,
                                                                        }}
                                                                    />
                                                                )}
                                                            </SidebarMenuSubItem>
                                                        );
                                                    })}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        )}
                                    </Collapsible>
                                ) : isDisabled ? (
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <SidebarMenuButton className="text-muted-foreground/50">
                                                    {route.icon}
                                                    {!isCollapsed && <span className="ml-2 text-sm font-medium">{route.title}</span>}
                                                </SidebarMenuButton>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                <p>{route.disabledTooltip || "Coming Soon"}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                ) : (
                                    <div className="relative">
                                        <SidebarMenuButton
                                            tooltip={route.title}
                                            asChild
                                            className="relative z-10 bg-transparent transition-colors hover:bg-transparent data-[active=true]:bg-transparent"
                                        >
                                            <Link href={route.link}>
                                                {route.icon}
                                                {!isCollapsed && (
                                                    <span
                                                        className={cn(
                                                            "relative z-10 ml-2 text-sm font-medium",
                                                            isActive ? "text-sidebar-accent-foreground" : "",
                                                        )}
                                                    >
                                                        {route.title}
                                                    </span>
                                                )}
                                            </Link>
                                        </SidebarMenuButton>
                                        {isActive && (
                                            <motion.div
                                                layoutId="active-sidebar-item"
                                                className="bg-sidebar-accent absolute inset-0 z-0 rounded-lg"
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
                        </React.Fragment>
                    );
                })}
            </LayoutGroup>
        </SidebarMenu>
    );
}
