import { getFacultyPortalNavigation, type FacultyPortalClass } from "@/components/faculty/faculty-navigation";
import { GlobalCommandContent } from "@/components/global-command-palette";
import { Command } from "@/components/ui/command";
import { Drawer, DrawerContent, DrawerDescription, DrawerTitle, DrawerTrigger } from "@/components/ui/drawer";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { useMemo, useState } from "react";

interface FacultyBottomNavPageProps {
    auth?: {
        user?: User | null;
    };
    featureFlags?: {
        enabledRoutes?: Record<string, boolean>;
    };
    facultyClasses?: FacultyPortalClass[];
}

const FACULTY_PRIMARY_ROUTE_IDS = ["dashboard", "action-center", "classes", "schedule", "announcements"] as const;

export function FacultyBottomNav() {
    const { url, props } = usePage<FacultyBottomNavPageProps>();
    const [searchOpen, setSearchOpen] = useState(false);
    const resolvedUser = props.auth?.user;
    const enabledRoutes = props.featureFlags?.enabledRoutes ?? {};
    const facultyClasses = props.facultyClasses ?? [];
    const mainNavItems = useMemo(
        () =>
            getFacultyPortalNavigation(enabledRoutes, facultyClasses).filter(
                (item): item is NonNullable<typeof item> =>
                    Boolean(item.id) && FACULTY_PRIMARY_ROUTE_IDS.includes(item.id as (typeof FACULTY_PRIMARY_ROUTE_IDS)[number]),
            ),
        [enabledRoutes, facultyClasses],
    );

    const isActive = (href: string): boolean => {
        return url === href || url.startsWith(`${href}/`);
    };

    return (
        <nav className="fixed right-0 bottom-0 left-0 z-50 md:hidden">
            <Drawer open={searchOpen} onOpenChange={setSearchOpen}>
                {resolvedUser ? (
                    <DrawerContent className="z-[100] h-[85vh] max-h-[85vh] rounded-t-[2rem] px-0 pt-0 outline-none">
                        <DrawerTitle className="sr-only">Search faculty portal</DrawerTitle>
                        <DrawerDescription className="sr-only">Search classes, students, schedules, and faculty tools.</DrawerDescription>
                        <div className="flex h-full flex-1 overflow-hidden p-2">
                            <Command className="h-full flex-1 border-none bg-transparent" shouldFilter={false}>
                                <GlobalCommandContent
                                    user={resolvedUser}
                                    isOpen={searchOpen}
                                    onSelect={() => setSearchOpen(false)}
                                    searchPlaceholder="Search classes, students, schedules, or faculty tools..."
                                    listClassName="h-full max-h-none"
                                />
                            </Command>
                        </div>
                    </DrawerContent>
                ) : null}

                <div className="safe-area-inset-bottom border-border/60 bg-background/96 border-t backdrop-blur-xl">
                    <div className="mx-auto max-w-xl px-3 pt-2 pb-2">
                        {resolvedUser ? (
                            <DrawerTrigger asChild>
                                <button
                                    type="button"
                                    className="mb-2 flex w-full flex-col items-center gap-1 rounded-xl py-1.5"
                                    aria-label="Open faculty search"
                                >
                                    <span className="bg-muted-foreground/35 block h-1 w-12 rounded-full" />
                                    <span className="text-muted-foreground text-[11px] font-medium">Open search</span>
                                </button>
                            </DrawerTrigger>
                        ) : null}

                        <div className="grid grid-cols-5 gap-1">
                            {mainNavItems.map((item) => {
                                const Icon = item.icon;
                                const active = isActive(item.url);
                                const disabled = item.disabled;

                                return (
                                    <Link
                                        key={item.id}
                                        href={disabled ? "#" : item.url}
                                        className={cn(
                                            "relative flex min-w-0 flex-col items-center gap-1 rounded-2xl px-1 py-2.5 transition-colors",
                                            disabled && "pointer-events-none opacity-45",
                                        )}
                                        aria-disabled={disabled}
                                        title={disabled ? item.disabledTooltip : item.title}
                                    >
                                        <div
                                            className={cn(
                                                "flex h-9 w-9 items-center justify-center rounded-2xl transition-all duration-200",
                                                active
                                                    ? "bg-primary text-primary-foreground shadow-primary/30 shadow-lg"
                                                    : "bg-muted/50 text-muted-foreground",
                                            )}
                                        >
                                            {Icon ? <Icon className="h-5 w-5" /> : null}
                                        </div>
                                        <span
                                            className={cn(
                                                "max-w-full truncate text-[10px] font-medium transition-colors",
                                                active ? "text-primary" : "text-muted-foreground",
                                            )}
                                        >
                                            {item.title === "Dashboard" ? "Home" : item.title.replace(/^My /, "")}
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
                </div>
            </Drawer>
        </nav>
    );
}
