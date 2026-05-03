import { GlobalCommandContent } from "@/components/global-command-palette";
import { Command } from "@/components/ui/command";
import { Drawer, DrawerContent, DrawerDescription, DrawerTitle } from "@/components/ui/drawer";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import {
    IconCalendar,
    IconChecklist,
    IconDashboard,
    IconSchool,
    IconSpeakerphone,
} from "@tabler/icons-react";
import { type FacultyPortalClass } from "@/components/faculty/faculty-navigation";
import { motion } from "framer-motion";
import { useCallback, useRef, useState } from "react";

interface FacultyBottomNavPageProps {
    auth?: {
        user?: User | null;
    };
    featureFlags?: {
        enabledRoutes?: Record<string, boolean>;
    };
    facultyClasses?: FacultyPortalClass[];
}

/**
 * Ordered so Dashboard sits at index 2 (visual center of five items).
 * Left pair: Action Center, Classes · Center: Home · Right pair: Schedule, News
 */
const MOBILE_NAV_ORDER = [
    { id: "action-center", label: "Tasks", icon: IconChecklist, url: "/faculty/action-center" },
    { id: "classes", label: "Classes", icon: IconSchool, url: "/faculty/classes" },
    { id: "dashboard", label: "Home", icon: IconDashboard, url: "/faculty/dashboard", center: true },
    { id: "schedule", label: "Schedule", icon: IconCalendar, url: "/faculty/schedule" },
    { id: "announcements", label: "News", icon: IconSpeakerphone, url: "/faculty/announcements" },
] as const;

export function FacultyBottomNav() {
    const { url, props } = usePage<FacultyBottomNavPageProps>();
    const [searchOpen, setSearchOpen] = useState(false);
    const resolvedUser = props.auth?.user;
    const enabledRoutes = props.featureFlags?.enabledRoutes ?? {};

    // Disabled-state lookup from feature flags
    const disabledMap: Record<string, boolean> = {
        "action-center": enabledRoutes["action-center"] === false || !enabledRoutes["action-center"],
    };

    const touchStart = useRef<{ x: number; y: number; time: number } | null>(null);

    const isActive = (href: string): boolean => url === href || url.startsWith(`${href}/`);

    const handleTouchStart = useCallback((e: React.TouchEvent) => {
        const t = e.touches[0];
        touchStart.current = { x: t.clientX, y: t.clientY, time: Date.now() };
    }, []);

    const handleTouchEnd = useCallback(
        (e: React.TouchEvent) => {
            if (!touchStart.current) return;
            const t = e.changedTouches[0];
            const dx = t.clientX - touchStart.current.x;
            const dy = touchStart.current.y - t.clientY;
            const dt = Date.now() - touchStart.current.time;
            if (dy > 40 && Math.abs(dx) < 60 && dt < 400 && resolvedUser) {
                setSearchOpen(true);
            }
            touchStart.current = null;
        },
        [resolvedUser],
    );

    return (
        <nav className="fixed right-0 bottom-0 left-0 z-50 md:hidden">
            {/* Search drawer — opens on swipe-up */}
            <Drawer open={searchOpen} onOpenChange={setSearchOpen}>
                {resolvedUser ? (
                    <DrawerContent className="z-[100] h-[85vh] max-h-[85vh] rounded-t-[2rem] px-0 pt-0 outline-none">
                        <DrawerTitle className="sr-only">Search faculty portal</DrawerTitle>
                        <DrawerDescription className="sr-only">
                            Search classes, students, schedules, and faculty tools.
                        </DrawerDescription>
                        <div className="flex h-full flex-1 overflow-hidden p-2">
                            <Command className="h-full flex-1 border-none bg-transparent" shouldFilter={false}>
                                <GlobalCommandContent
                                    user={resolvedUser}
                                    isOpen={searchOpen}
                                    onSelect={() => setSearchOpen(false)}
                                    searchPlaceholder="Search classes, students, schedules…"
                                    listClassName="h-full max-h-none"
                                />
                            </Command>
                        </div>
                    </DrawerContent>
                ) : null}

                {/* ── Bottom bar ── */}
                <div
                    className="safe-area-inset-bottom border-border/40 bg-background/90 border-t backdrop-blur-2xl"
                    onTouchStart={handleTouchStart}
                    onTouchEnd={handleTouchEnd}
                >
                    {/* Swipe-up grab handle */}
                    {resolvedUser ? (
                        <div className="pointer-events-none flex select-none items-center justify-center pt-1.5">
                            <span className="bg-muted-foreground/20 block h-[3px] w-8 rounded-full" />
                        </div>
                    ) : null}

                    <div className="mx-auto flex max-w-md items-end justify-around px-2 pb-1.5">
                        {MOBILE_NAV_ORDER.map((item) => {
                            const active = isActive(item.url);
                            const disabled = disabledMap[item.id] ?? false;
                            const Icon = item.icon;
                            const isCenter = "center" in item && item.center;

                            if (isCenter) {
                                return (
                                    <Link
                                        key={item.id}
                                        href={item.url}
                                        className="group relative -mt-5 flex flex-col items-center"
                                        aria-label="Home"
                                    >
                                        {/* Outer glow ring */}
                                        <span
                                            className={cn(
                                                "absolute top-0.5 h-12 w-12 rounded-full transition-all duration-300",
                                                active
                                                    ? "bg-primary/20 scale-110 blur-sm"
                                                    : "bg-transparent scale-100",
                                            )}
                                        />
                                        {/* Elevated pill */}
                                        <span
                                            className={cn(
                                                "relative z-10 flex h-12 w-12 items-center justify-center rounded-full shadow-lg transition-all duration-300",
                                                active
                                                    ? "bg-primary text-primary-foreground shadow-primary/40"
                                                    : "bg-muted text-foreground shadow-black/10 dark:shadow-black/30",
                                            )}
                                        >
                                            <Icon className="size-6" stroke={active ? 2.2 : 1.6} />
                                        </span>
                                        <span
                                            className={cn(
                                                "mt-1 text-[10px] font-semibold tracking-wide transition-colors",
                                                active ? "text-primary" : "text-muted-foreground",
                                            )}
                                        >
                                            {item.label}
                                        </span>
                                    </Link>
                                );
                            }

                            return (
                                <Link
                                    key={item.id}
                                    href={disabled ? "#" : item.url}
                                    className={cn(
                                        "relative flex w-14 flex-col items-center justify-end px-1 pt-1.5 pb-0.5 transition-colors",
                                        disabled && "pointer-events-none opacity-40",
                                    )}
                                    aria-disabled={disabled}
                                    title={item.label}
                                >
                                    <div
                                        className={cn(
                                            "flex h-7 w-7 items-center justify-center transition-all duration-200",
                                            active ? "text-primary" : "text-muted-foreground",
                                        )}
                                    >
                                        <Icon className="size-[22px]" stroke={active ? 2 : 1.5} />
                                    </div>
                                    <span
                                        className={cn(
                                            "mt-0.5 max-w-full truncate text-[10px] font-medium leading-tight transition-colors",
                                            active ? "text-primary" : "text-muted-foreground",
                                        )}
                                    >
                                        {item.label}
                                    </span>

                                    {/* Dot sits in a fixed-height slot so it never shifts siblings */}
                                    <div className="flex h-2.5 items-center justify-center">
                                        {active && (
                                            <motion.div
                                                layoutId="faculty-bottom-nav-dot"
                                                className="bg-primary h-1 w-1 rounded-full"
                                                transition={{ type: "spring", stiffness: 500, damping: 30 }}
                                            />
                                        )}
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </Drawer>
        </nav>
    );
}
