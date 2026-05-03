"use client";

import { PortalSidebar } from "@/components/portal-sidebar";
import { triggerGlobalCommandPalette } from "@/components/global-command-palette";
import { cn } from "@/lib/utils";
import { Link, usePage } from "@inertiajs/react";
import {
    IconCalendar,
    IconDashboard,
    IconReceipt,
    IconSchool,
    IconSpeakerphone,
} from "@tabler/icons-react";
import { motion, useMotionValue, useSpring, useTransform } from "framer-motion";
import { useCallback, useEffect, useRef, useState, type ComponentProps } from "react";

interface PageProps {
    featureFlags?: {
        enabledRoutes?: Record<string, boolean>;
    };
    [key: string]: unknown;
}

const navItems = [
    {
        id: "dashboard",
        title: "Home",
        icon: IconDashboard,
        url: "/student/dashboard",
    },
    {
        id: "classes",
        title: "Academics",
        icon: IconSchool,
        url: "/student/classes",
    },
    {
        id: "schedule",
        title: "Schedule",
        icon: IconCalendar,
        url: "/student/schedule",
    },
    {
        id: "tuition",
        title: "Tuition",
        icon: IconReceipt,
        url: "/student/tuition",
    },
    {
        id: "announcements",
        title: "News",
        icon: IconSpeakerphone,
        url: "/student/announcements",
    },
];

function triggerHaptic() {
    if (typeof navigator !== "undefined" && "vibrate" in navigator) {
        navigator.vibrate(8);
    }
}

function useScrollDirection() {
    const [visible, setVisible] = useState(true);
    const lastScrollY = useRef(0);
    const ticking = useRef(false);

    useEffect(() => {
        const handleScroll = () => {
            if (!ticking.current) {
                requestAnimationFrame(() => {
                    const currentScrollY = window.scrollY;
                    const delta = currentScrollY - lastScrollY.current;

                    if (delta > 8 && currentScrollY > 60) {
                        setVisible(false);
                    } else if (delta < -4) {
                        setVisible(true);
                    }

                    lastScrollY.current = currentScrollY;
                    ticking.current = false;
                });
                ticking.current = true;
            }
        };

        window.addEventListener("scroll", handleScroll, { passive: true });
        return () => window.removeEventListener("scroll", handleScroll);
    }, []);

    return visible;
}

function StudentMobileNav() {
    const { url: currentUrl, props: pageProps } = usePage<PageProps>();
    const enabledRoutes = pageProps.featureFlags?.enabledRoutes || {};
    const pathname = currentUrl.split("?")[0];
    const visible = useScrollDirection();
    const navRef = useRef<HTMLElement>(null);

    const touchStart = useRef<{ x: number; y: number; time: number } | null>(null);
    const isDragging = useRef(false);
    const dragY = useMotionValue(0);
    const smoothDragY = useSpring(dragY, { stiffness: 400, damping: 30 });
    const dragOpacity = useTransform(smoothDragY, [0, -80], [1, 0.4]);
    const dragScale = useTransform(smoothDragY, [0, -80], [1, 0.95]);

    const isRouteActive = useCallback((link: string) => {
        return !!(link && link !== "#" && (pathname === link || pathname.startsWith(`${link}/`)));
    }, [pathname]);

    const activeIndex = navItems.findIndex((item) => isRouteActive(item.url));

    const handleTouchStart = useCallback((e: React.TouchEvent) => {
        const t = e.touches[0];
        touchStart.current = { x: t.clientX, y: t.clientY, time: Date.now() };
        isDragging.current = false;
    }, []);

    const handleTouchMove = useCallback((e: React.TouchEvent) => {
        if (!touchStart.current) return;
        const t = e.touches[0];
        const dx = Math.abs(t.clientX - touchStart.current.x);
        const dy = touchStart.current.y - t.clientY;

        if (dy > 10 && dx < 30) {
            isDragging.current = true;
            dragY.set(-dy);
        }
    }, [dragY]);

    const handleTouchEnd = useCallback((e: React.TouchEvent) => {
        if (!touchStart.current) return;
        const t = e.changedTouches[0];
        const dx = Math.abs(t.clientX - touchStart.current.x);
        const dy = touchStart.current.y - t.clientY;
        const dt = Date.now() - touchStart.current.time;
        const velocity = dy / dt;

        dragY.set(0);

        if ((dy > 60 && dx < 40 && dt < 400) || (dy > 30 && velocity > 0.4 && dx < 40)) {
            triggerGlobalCommandPalette();
        }

        touchStart.current = null;
        isDragging.current = false;
    }, [dragY]);

    return (
        <motion.nav
            ref={navRef}
            initial={false}
            animate={{ y: visible ? 0 : 100 }}
            transition={{ type: "spring", stiffness: 400, damping: 32 }}
            style={{ opacity: dragOpacity, scale: dragScale }}
            className="md:hidden fixed bottom-0 left-0 right-0 z-50 isolate"
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
        >
            {/* Glass backdrop */}
            <div className="absolute inset-0 bg-background/70 backdrop-blur-2xl backdrop-saturate-150 border-t border-border/50 shadow-[0_-4px_20px_rgba(0,0,0,0.05)] dark:shadow-[0_-4px_20px_rgba(0,0,0,0.2)]" />

            {/* Drag handle */}
            <div className="relative flex justify-center pt-2 pointer-events-none">
                <motion.div
                    className="h-1 w-9 rounded-full bg-foreground/10"
                    animate={{ scaleX: isDragging.current ? 1.3 : 1 }}
                    transition={{ type: "spring", stiffness: 500, damping: 30 }}
                />
            </div>

            {/* Nav items */}
            <div
                className="relative flex items-end justify-around px-2 pt-1"
                style={{ paddingBottom: "max(env(safe-area-inset-bottom), 8px)" }}
            >
                {navItems.map((item, index) => {
                    const isEnabled = enabledRoutes[item.id] !== false;
                    const isActive = activeIndex === index;
                    const isDisabled = !isEnabled;

                    return (
                        <Link
                            key={item.id}
                            href={isDisabled ? "#" : item.url}
                            onClick={() => {
                                if (!isDisabled) triggerHaptic();
                            }}
                            className={cn(
                                "relative flex flex-col items-center justify-center gap-1 w-full py-2 select-none",
                                isDisabled && "pointer-events-none opacity-30"
                            )}
                        >
                            {/* Active background pill */}
                            {isActive && (
                                <motion.div
                                    layoutId="mobile-nav-pill"
                                    className="absolute inset-x-2 top-1 bottom-1 bg-primary/10 rounded-2xl"
                                    transition={{
                                        type: "spring",
                                        stiffness: 500,
                                        damping: 35,
                                    }}
                                />
                            )}

                            <motion.div
                                className="relative z-10"
                                animate={{
                                    scale: isActive ? 1.08 : 1,
                                    y: isActive ? -1 : 0,
                                }}
                                transition={{
                                    type: "spring",
                                    stiffness: 500,
                                    damping: 30,
                                }}
                            >
                                <item.icon
                                    className={cn(
                                        "size-[22px] transition-colors duration-200",
                                        isActive ? "text-primary" : "text-muted-foreground"
                                    )}
                                    stroke={isActive ? 2.2 : 1.5}
                                />
                            </motion.div>

                            <motion.span
                                className={cn(
                                    "relative z-10 text-[10px] font-semibold tracking-wide transition-colors duration-200",
                                    isActive ? "text-primary" : "text-muted-foreground/70"
                                )}
                                animate={{
                                    scale: isActive ? 1.02 : 1,
                                }}
                                transition={{
                                    type: "spring",
                                    stiffness: 500,
                                    damping: 30,
                                }}
                            >
                                {item.title}
                            </motion.span>
                        </Link>
                    );
                })}
            </div>
        </motion.nav>
    );
}

export function StudentSidebar(props: ComponentProps<typeof PortalSidebar>) {
    return (
        <>
            <PortalSidebar {...props} />
            <StudentMobileNav />
        </>
    );
}
