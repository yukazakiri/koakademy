import { GlobalCommandContent } from "@/components/global-command-palette";
import { getStudentPortalNavigation, type StudentPortalClass } from "@/components/student/student-navigation";
import { Command } from "@/components/ui/command";
import { Drawer, DrawerContent, DrawerDescription, DrawerTitle } from "@/components/ui/drawer";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import { IconCalendar, IconHome, IconReceipt, IconSchool, IconSpeakerphone } from "@tabler/icons-react";
import { motion } from "framer-motion";
import { useCallback, useMemo, useRef, useState } from "react";

interface StudentBottomNavPageProps {
    auth?: {
        user?: User | null;
    };
    featureFlags?: {
        enabledRoutes?: Record<string, boolean>;
    };
    studentClasses?: StudentPortalClass[];
}

/**
 * Ordered so Dashboard sits at index 2 (visual center of five items).
 * Left pair: Academics, Schedule · Center: Home · Right pair: Tuition, News
 */
const MOBILE_NAV_ORDER = [
    { id: "classes", label: "Classes", icon: IconSchool, url: "/student/classes" },
    { id: "schedule", label: "Schedule", icon: IconCalendar, url: "/student/schedule" },
    { id: "dashboard", label: "Home", icon: IconHome, url: "/student/dashboard", center: true },
    { id: "tuition", label: "Tuition", icon: IconReceipt, url: "/student/tuition" },
    { id: "announcements", label: "News", icon: IconSpeakerphone, url: "/student/announcements" },
] as const;

export function StudentBottomNav() {
    const { url, props } = usePage<StudentBottomNavPageProps>();
    const [searchOpen, setSearchOpen] = useState(false);
    const resolvedUser = props.auth?.user;
    const enabledRoutes = props.featureFlags?.enabledRoutes ?? {};
    const studentClasses = props.studentClasses ?? [];

    // We still call the canonical navigation to read disabled / feature-flag state
    const canonicalNav = useMemo(() => getStudentPortalNavigation(enabledRoutes, studentClasses), [enabledRoutes, studentClasses]);
    const disabledMap = useMemo(() => {
        const m: Record<string, { disabled: boolean; tooltip?: string }> = {};
        canonicalNav.forEach((item) => {
            m[item.id] = { disabled: !!item.disabled, tooltip: item.disabledTooltip };
        });
        return m;
    }, [canonicalNav]);

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
                        <DrawerTitle className="sr-only">Search your academics</DrawerTitle>
                        <DrawerDescription className="sr-only">
                            Find enrolled classes, subjects, courses, and student enrollment records.
                        </DrawerDescription>
                        <div className="flex h-full flex-1 overflow-hidden p-2">
                            <Command className="h-full flex-1 border-none bg-transparent" shouldFilter={false}>
                                <GlobalCommandContent
                                    user={resolvedUser}
                                    isOpen={searchOpen}
                                    onSelect={() => setSearchOpen(false)}
                                    searchPlaceholder="Search classes, subjects, courses…"
                                    listClassName="h-full max-h-none"
                                />
                            </Command>
                        </div>
                    </DrawerContent>
                ) : null}

                {/* ── Bottom bar ── */}
                <div
                    className="safe-area-inset-bottom border-border/50 bg-background/98 border-t shadow-[0_-10px_40px_rgba(0,0,0,0.15)] backdrop-blur-xl"
                    onTouchStart={handleTouchStart}
                    onTouchEnd={handleTouchEnd}
                >
                    {/* Swipe-up grab handle */}
                    {resolvedUser ? (
                        <div className="pointer-events-none flex items-center justify-center pt-2 select-none">
                            <span className="bg-muted-foreground/25 block h-1 w-10 rounded-full" />
                        </div>
                    ) : null}

                    <div className="mx-auto flex max-w-md items-end justify-around px-2 pb-3">
                        {MOBILE_NAV_ORDER.map((item) => {
                            const active = isActive(item.url);
                            const info = disabledMap[item.id];
                            const disabled = info?.disabled ?? false;
                            const Icon = item.icon;
                            const isCenter = "center" in item && item.center;

                            if (isCenter) {
                                return (
                                    <Link key={item.id} href={item.url} className="group relative -mt-8 flex flex-col items-center" aria-label="Home">
                                        {/* Outer glow ring */}
                                        <motion.span
                                            animate={active ? { scale: 1.2, opacity: 0.3 } : { scale: 1, opacity: 0 }}
                                            transition={{ type: "spring", stiffness: 300, damping: 20 }}
                                            className="absolute -inset-3 rounded-full bg-primary/40 blur-xl"
                                        />

                                        {/* Button Circle */}
                                        <motion.div
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.92 }}
                                            className={cn(
                                                "relative flex h-14 w-14 items-center justify-center rounded-2xl border-2 transition-all duration-300 overflow-hidden",
                                                active
                                                    ? "border-primary bg-primary text-primary-foreground shadow-primary/40 shadow-lg"
                                                    : "border-border/70 bg-background text-foreground/70 shadow-xl",
                                            )}
                                        >
                                            {/* Inner gradient for active state */}
                                            {active && (
                                                <div className="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent" />
                                            )}
                                            <Icon className="relative size-7" stroke={2.3} />
                                        </motion.div>

                                        <span
                                            className={cn(
                                                "mt-1.5 text-[11px] font-bold tracking-tight transition-all duration-200",
                                                active ? "text-primary scale-105" : "text-foreground/60",
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
                                        "relative flex w-16 flex-col items-center justify-end rounded-2xl px-1 pt-2 pb-1 transition-all duration-200",
                                        active && "bg-primary/8",
                                        disabled && "pointer-events-none opacity-40",
                                    )}
                                    aria-disabled={disabled}
                                    title={disabled ? info?.tooltip : item.label}
                                >
                                    <motion.div
                                        whileHover={{ scale: 1.1 }}
                                        whileTap={{ scale: 0.95 }}
                                        className={cn(
                                            "flex h-9 w-9 items-center justify-center rounded-xl transition-all duration-200",
                                            active ? "bg-primary/10 text-primary" : "text-foreground/60",
                                        )}
                                    >
                                        <Icon className="size-[24px]" stroke={active ? 2.3 : 1.8} />
                                    </motion.div>
                                    <span
                                        className={cn(
                                            "mt-1 max-w-full truncate text-[11px] leading-tight font-semibold transition-colors",
                                            active ? "text-primary" : "text-foreground/60",
                                        )}
                                    >
                                        {item.label}
                                    </span>

                                    {/* Dot sits in a fixed-height slot so it never shifts siblings */}
                                    <div className="flex h-2.5 items-center justify-center">
                                        {active && (
                                            <motion.div
                                                layoutId="student-bottom-nav-dot"
                                                className="bg-primary h-1.5 w-4 rounded-full"
                                                transition={{ type: "spring", stiffness: 400, damping: 25 }}
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
