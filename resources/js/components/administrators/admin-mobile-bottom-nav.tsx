"use client";

import { GlobalCommandContent } from "@/components/global-command-palette";
import { Command } from "@/components/ui/command";
import { Drawer, DrawerContent, DrawerDescription, DrawerTitle, DrawerTrigger } from "@/components/ui/drawer";
import { getRoutesForRole, type AdminRoute } from "@/config/admin-routes";
import { useIsMobile } from "@/hooks/use-mobile";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { useMemo, useState } from "react";

const ADMIN_PRIMARY_ROUTE_PRIORITY = [
    "admin-dashboard",
    "admin-students",
    "admin-classes",
    "admin-faculty",
    "admin-enrollments",
    "admin-finance-overview",
    "admin-users",
    "admin-library",
] as const;

export function AdminMobileBottomNav() {
    const isMobile = useIsMobile();
    const { props, url } = usePage();
    const user = (props.auth as any)?.user || ((props as any).user as User | undefined);
    const [drawerOpen, setDrawerOpen] = useState(false);

    const primaryRoutes = useMemo(() => {
        if (!user) {
            return [];
        }

        const allowedRoutes = getRoutesForRole(user.role, user.permissions ?? []).filter((route) => !route.disabled);
        const prioritizedRoutes = ADMIN_PRIMARY_ROUTE_PRIORITY.map((id) => allowedRoutes.find((route) => route.id === id)).filter(
            Boolean,
        ) as AdminRoute[];

        return (prioritizedRoutes.length > 0 ? prioritizedRoutes : allowedRoutes).slice(0, 4);
    }, [user]);

    if (!isMobile || !user) {
        return null;
    }

    const isActiveRoute = (link: string): boolean => {
        return url.startsWith(link);
    };

    const getNavLabel = (route: AdminRoute): string => {
        if (route.id === "admin-dashboard") {
            return "Home";
        }

        return route.title.replace(" Management", "").replace(" Directory", "").replace(" Records", "");
    };

    const primaryRouteGridClass =
        primaryRoutes.length >= 4
            ? "grid-cols-4"
            : primaryRoutes.length === 3
              ? "grid-cols-3"
              : primaryRoutes.length === 2
                ? "grid-cols-2"
                : "grid-cols-1";

    return (
        <>
            <div className="safe-area-inset-bottom fixed inset-x-0 bottom-0 z-40 md:hidden">
                <Drawer open={drawerOpen} onOpenChange={setDrawerOpen}>
                    <DrawerContent className="z-[100] h-[85vh] max-h-[85vh] rounded-t-[2rem] px-0 pt-0 outline-none">
                        <DrawerTitle className="sr-only">Admin navigation search</DrawerTitle>
                        <DrawerDescription className="sr-only">Search and access all sections of the administrator portal.</DrawerDescription>
                        <div className="flex h-full flex-1 overflow-hidden p-2">
                            <Command className="h-full flex-1 border-none bg-transparent" shouldFilter={false}>
                                <GlobalCommandContent
                                    user={user}
                                    isOpen={drawerOpen}
                                    onSelect={() => setDrawerOpen(false)}
                                    listClassName="h-full max-h-none"
                                />
                            </Command>
                        </div>
                    </DrawerContent>

                    <motion.nav
                        initial={{ y: 100, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        transition={{ type: "spring", stiffness: 260, damping: 20 }}
                        className="border-border/60 bg-background/96 border-t backdrop-blur-xl"
                    >
                        <div className="mx-auto max-w-xl px-3 pt-2 pb-2">
                            <DrawerTrigger asChild>
                                <button
                                    type="button"
                                    className="mb-2 flex w-full flex-col items-center gap-1 rounded-xl py-1.5"
                                    aria-label="Open admin search"
                                >
                                    <span className="bg-muted-foreground/35 block h-1 w-12 rounded-full" />
                                    <span className="text-muted-foreground text-[11px] font-medium">Open search</span>
                                </button>
                            </DrawerTrigger>

                            <div className={cn("grid gap-1", primaryRouteGridClass)}>
                                {primaryRoutes.map((route) => {
                                    const active = isActiveRoute(route.link);

                                    return (
                                        <Link
                                            key={route.id}
                                            href={route.link}
                                            className="relative flex min-w-0 flex-col items-center gap-1 rounded-2xl px-1 py-2.5 transition-colors"
                                        >
                                            <div
                                                className={cn(
                                                    "flex h-9 w-9 items-center justify-center rounded-2xl transition-all duration-200",
                                                    active
                                                        ? "bg-primary text-primary-foreground shadow-primary/30 shadow-lg"
                                                        : "bg-muted/50 text-muted-foreground",
                                                )}
                                            >
                                                <span className="flex items-center justify-center [&_svg]:size-5">{route.icon}</span>
                                            </div>
                                            <span
                                                className={cn(
                                                    "max-w-full truncate text-[10px] font-medium transition-colors",
                                                    active ? "text-primary" : "text-muted-foreground",
                                                )}
                                            >
                                                {getNavLabel(route)}
                                            </span>

                                            {active && (
                                                <motion.div
                                                    layoutId="bottom-nav-indicator"
                                                    className="bg-primary absolute top-0 h-1 w-8 rounded-full"
                                                    transition={{ type: "spring", stiffness: 400, damping: 30 }}
                                                />
                                            )}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    </motion.nav>
                </Drawer>
            </div>

            <div className="h-0 md:hidden" />
        </>
    );
}
