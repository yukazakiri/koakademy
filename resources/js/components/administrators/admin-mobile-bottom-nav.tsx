"use client";

import { triggerGlobalCommandPalette } from "@/components/global-command-palette";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import {
    IconBooks,
    IconBriefcase,
    IconCash,
    IconDashboard,
    IconHelp,
    IconSchool,
    IconServer,
    IconTools,
    IconUser,
} from "@tabler/icons-react";
import { AnimatePresence, motion } from "framer-motion";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { getRoutesForRoleWithModules, type ModuleAdminRoute, type RouteSection } from "@/config/admin-routes";

const SECTION_ICONS: Record<RouteSection, React.ElementType> = {
    core: IconDashboard,
    academic: IconSchool,
    student_services: IconUser,
    finance: IconCash,
    hr: IconBriefcase,
    system: IconServer,
    library: IconBooks,
    inventory: IconTools,
    support: IconHelp,
};

const SECTION_LABELS: Record<RouteSection, string> = {
    core: "Overview",
    academic: "Academics",
    student_services: "Registrar",
    finance: "Finance",
    hr: "HR",
    system: "System",
    library: "Library",
    inventory: "Property",
    support: "Support",
};

const PRIORITY_IDS: RouteSection[] = ["core", "academic", "student_services", "finance", "system"];

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

function IconMore() {
    return (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="1" />
            <circle cx="12" cy="5" r="1" />
            <circle cx="12" cy="19" r="1" />
        </svg>
    );
}

interface SectionItem {
    id: RouteSection;
    label: string;
    link: string;
}

export function AdminMobileBottomNav() {
    const { props, url: currentUrl } = usePage<{
        auth?: { user?: User | null };
        user?: User;
        moduleAdminRoutes?: ModuleAdminRoute[];
    }>();
    const user = (props.auth as any)?.user || ((props as any).user as User | undefined);
    const moduleAdminRoutes = props.moduleAdminRoutes ?? [];
    const [showMore, setShowMore] = useState(false);
    const visible = useScrollDirection();
    const touchStart = useRef<{ x: number; y: number; time: number } | null>(null);

    // Build sections from allowed routes
    const sections = useMemo<SectionItem[]>(() => {
        if (!user) return [];
        const allowed = getRoutesForRoleWithModules(user.role, user.permissions ?? [], moduleAdminRoutes).filter((r) => !r.disabled);
        const map = new Map<RouteSection, SectionItem>();
        for (const route of allowed) {
            const section = (route.section || "core") as RouteSection;
            if (!map.has(section)) {
                map.set(section, {
                    id: section,
                    label: SECTION_LABELS[section],
                    link: route.link,
                });
            }
        }
        return Array.from(map.values());
    }, [user, moduleAdminRoutes]);

    const priority = useMemo(() => {
        const ordered = PRIORITY_IDS.map((id) => sections.find((s) => s.id === id)).filter(Boolean) as SectionItem[];
        return ordered.slice(0, 5);
    }, [sections]);

    const more = useMemo(() => sections.filter((s) => !PRIORITY_IDS.includes(s.id)), [sections]);

    const activeId = useMemo(() => {
        for (const section of sections) {
            if (currentUrl.startsWith(section.link)) return section.id;
        }
        return sections[0]?.id ?? null;
    }, [currentUrl, sections]);

    const handleTouchStart = useCallback((e: React.TouchEvent) => {
        const t = e.touches[0];
        touchStart.current = { x: t.clientX, y: t.clientY, time: Date.now() };
    }, []);

    const handleTouchEnd = useCallback((e: React.TouchEvent) => {
        if (!touchStart.current) return;
        const t = e.changedTouches[0];
        const dx = Math.abs(t.clientX - touchStart.current.x);
        const dy = touchStart.current.y - t.clientY;
        const dt = Date.now() - touchStart.current.time;
        if ((dy > 60 && dx < 40 && dt < 400) || (dy > 30 && dy / dt > 0.4 && dx < 40)) {
            triggerGlobalCommandPalette();
        }
        touchStart.current = null;
    }, []);

    if (!user || sections.length === 0) return null;

    return (
        <>
            <motion.nav
                initial={false}
                animate={{ y: visible ? 0 : 100 }}
                transition={{ type: "spring", stiffness: 400, damping: 32 }}
                className="md:hidden fixed bottom-0 left-0 right-0 z-50 isolate"
                onTouchStart={handleTouchStart}
                onTouchEnd={handleTouchEnd}
            >
                {/* Single cohesive glass backdrop */}
                <div className="absolute inset-0 bg-background/80 backdrop-blur-2xl backdrop-saturate-150 border-t border-border/40 shadow-[0_-4px_24px_rgba(0,0,0,0.06)] dark:shadow-[0_-4px_24px_rgba(0,0,0,0.25)]" />

                {/* Compact nav row */}
                <div
                    className="relative flex items-center justify-around px-1"
                    style={{ paddingBottom: "max(env(safe-area-inset-bottom), 6px)", paddingTop: "6px" }}
                >
                    {priority.map((section) => {
                        const Icon = SECTION_ICONS[section.id];
                        const isActive = activeId === section.id;

                        return (
                            <Link
                                key={section.id}
                                href={section.link}
                                onClick={() => triggerHaptic()}
                                className={cn(
                                    "relative flex flex-1 flex-col items-center justify-center gap-[3px] py-1.5 select-none transition-colors",
                                    isActive ? "text-primary" : "text-muted-foreground hover:text-foreground"
                                )}
                            >
                                {isActive && (
                                    <motion.div
                                        layoutId="admin-nav-pill"
                                        className="absolute inset-x-1.5 inset-y-0.5 bg-primary/10 rounded-xl"
                                        transition={{ type: "spring", stiffness: 500, damping: 35 }}
                                    />
                                )}

                                <motion.div
                                    className="relative z-10"
                                    animate={{ scale: isActive ? 1.06 : 1, y: isActive ? -1 : 0 }}
                                    transition={{ type: "spring", stiffness: 500, damping: 30 }}
                                >
                                    <Icon
                                        className="size-5 transition-colors duration-200"
                                        stroke={isActive ? 2.2 : 1.5}
                                    />
                                </motion.div>

                                <span
                                    className={cn(
                                        "relative z-10 text-[10px] font-semibold tracking-wide transition-colors duration-200",
                                        isActive ? "text-primary" : "text-muted-foreground/70"
                                    )}
                                >
                                    {section.label}
                                </span>
                            </Link>
                        );
                    })}

                    {/* More button */}
                    {more.length > 0 && (
                        <button
                            onClick={() => {
                                triggerHaptic();
                                setShowMore(true);
                            }}
                            className="relative flex flex-1 flex-col items-center justify-center gap-[3px] py-1.5 select-none text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <IconMore />
                            <span className="text-[10px] font-semibold tracking-wide text-muted-foreground/70">More</span>
                        </button>
                    )}
                </div>
            </motion.nav>

            {/* More sections sheet */}
            <AnimatePresence>
                {showMore && (
                    <motion.div
                        initial={{ y: "100%" }}
                        animate={{ y: 0 }}
                        exit={{ y: "100%" }}
                        transition={{ type: "spring", stiffness: 400, damping: 35 }}
                        className="fixed inset-x-0 bottom-0 z-[60] bg-background/95 backdrop-blur-2xl border-t rounded-t-2xl md:hidden"
                        onTouchStart={(e) => {
                            const t = e.touches[0];
                            touchStart.current = { x: t.clientX, y: t.clientY, time: Date.now() };
                        }}
                        onTouchEnd={(e) => {
                            if (!touchStart.current) return;
                            const t = e.changedTouches[0];
                            if (t.clientY - touchStart.current.y > 80) setShowMore(false);
                            touchStart.current = null;
                        }}
                    >
                        <div className="flex justify-center pt-2 pb-1">
                            <div className="h-1 w-9 rounded-full bg-foreground/10" />
                        </div>
                        <div className="flex items-center justify-between px-4 py-2 border-b">
                            <span className="text-sm font-medium">All Sections</span>
                            <button
                                onClick={() => setShowMore(false)}
                                className="text-muted-foreground hover:text-foreground p-1"
                            >
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M18 6L6 18M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="grid grid-cols-4 gap-1 px-2 py-3 max-h-[50vh] overflow-y-auto">
                            {sections.map((section) => {
                                const Icon = SECTION_ICONS[section.id];
                                const isActive = activeId === section.id;

                                return (
                                    <Link
                                        key={section.id}
                                        href={section.link}
                                        onClick={() => {
                                            triggerHaptic();
                                            setShowMore(false);
                                        }}
                                        className={cn(
                                            "flex flex-col items-center gap-1.5 py-2.5 px-1 rounded-xl transition-colors",
                                            isActive ? "text-primary bg-primary/10" : "text-muted-foreground hover:bg-accent"
                                        )}
                                    >
                                        <Icon className="size-5" stroke={isActive ? 2 : 1.5} />
                                        <span className="text-[10px] font-medium text-center leading-tight">{section.label}</span>
                                    </Link>
                                );
                            })}
                        </div>
                        <div style={{ paddingBottom: "max(env(safe-area-inset-bottom), 8px)" }} />
                    </motion.div>
                )}
            </AnimatePresence>
        </>
    );
}
