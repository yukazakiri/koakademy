import { AnimatePresence, motion } from "framer-motion";
import type { LucideIcon } from "lucide-react";
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CalendarDays,
    Check,
    GraduationCap,
    MessagesSquare,
    Sparkles,
    Stars,
    Trophy,
    Users,
    Zap,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface OnboardingStat {
    label: string;
    value: string;
}

interface OnboardingStep {
    id: string;
    title: string;
    description: string;
    highlights: string[];
    stats: OnboardingStat[];
    badge: string;
    accent: string;
    icon: LucideIcon;
    image?: string | null;
}

interface OnboardingStepData {
    id?: string;
    title: string;
    summary: string;
    highlights: string[];
    stats: OnboardingStat[];
    badge: string;
    accent: string;
    icon: string;
    image?: string | null;
}

export interface OnboardingFeatureData {
    featureKey: string;
    name: string;
    audience: string;
    summary: string | null;
    badge: string | null;
    accent: string | null;
    ctaLabel: string | null;
    ctaUrl: string | null;
    steps: OnboardingStepData[];
}

interface OnboardingExperienceProps {
    variant: "faculty" | "student";
    userId?: number | string | null;
    force?: boolean;
    enabled?: boolean;
    features?: OnboardingFeatureData[];
    onDismiss?: (featureKey: string) => void;
    className?: string;
}

type AccentKey = "primary" | "indigo" | "rose" | "emerald" | "amber" | "sky" | "purple";

const accents: Record<
    AccentKey,
    {
        iconBg: string;
        iconText: string;
        checkBg: string;
        checkText: string;
        badgeBg: string;
        badgeText: string;
        statBg: string;
        statBorder: string;
        statText: string;
        dotActive: string;
        ctaBg: string;
        glowBg: string;
        headerBg: string;
    }
> = {
    primary: {
        iconBg: "bg-primary/10",
        iconText: "text-primary",
        checkBg: "bg-primary/10",
        checkText: "text-primary",
        badgeBg: "bg-primary/10",
        badgeText: "text-primary",
        statBg: "bg-primary/5",
        statBorder: "border-primary/15",
        statText: "text-primary",
        dotActive: "bg-primary",
        ctaBg: "bg-primary text-primary-foreground hover:bg-primary/90",
        glowBg: "bg-primary",
        headerBg: "bg-primary/5",
    },
    indigo: {
        iconBg: "bg-indigo-500/10",
        iconText: "text-indigo-600 dark:text-indigo-400",
        checkBg: "bg-indigo-500/10",
        checkText: "text-indigo-600 dark:text-indigo-400",
        badgeBg: "bg-indigo-500/10",
        badgeText: "text-indigo-600 dark:text-indigo-400",
        statBg: "bg-indigo-500/5",
        statBorder: "border-indigo-500/15",
        statText: "text-indigo-600 dark:text-indigo-400",
        dotActive: "bg-indigo-500",
        ctaBg: "bg-indigo-600 text-white hover:bg-indigo-700",
        glowBg: "bg-indigo-500",
        headerBg: "bg-indigo-500/5",
    },
    rose: {
        iconBg: "bg-rose-500/10",
        iconText: "text-rose-600 dark:text-rose-400",
        checkBg: "bg-rose-500/10",
        checkText: "text-rose-600 dark:text-rose-400",
        badgeBg: "bg-rose-500/10",
        badgeText: "text-rose-600 dark:text-rose-400",
        statBg: "bg-rose-500/5",
        statBorder: "border-rose-500/15",
        statText: "text-rose-600 dark:text-rose-400",
        dotActive: "bg-rose-500",
        ctaBg: "bg-rose-600 text-white hover:bg-rose-700",
        glowBg: "bg-rose-500",
        headerBg: "bg-rose-500/5",
    },
    emerald: {
        iconBg: "bg-emerald-500/10",
        iconText: "text-emerald-600 dark:text-emerald-400",
        checkBg: "bg-emerald-500/10",
        checkText: "text-emerald-600 dark:text-emerald-400",
        badgeBg: "bg-emerald-500/10",
        badgeText: "text-emerald-600 dark:text-emerald-400",
        statBg: "bg-emerald-500/5",
        statBorder: "border-emerald-500/15",
        statText: "text-emerald-600 dark:text-emerald-400",
        dotActive: "bg-emerald-500",
        ctaBg: "bg-emerald-600 text-white hover:bg-emerald-700",
        glowBg: "bg-emerald-500",
        headerBg: "bg-emerald-500/5",
    },
    amber: {
        iconBg: "bg-amber-500/10",
        iconText: "text-amber-600 dark:text-amber-400",
        checkBg: "bg-amber-500/10",
        checkText: "text-amber-600 dark:text-amber-400",
        badgeBg: "bg-amber-500/10",
        badgeText: "text-amber-600 dark:text-amber-400",
        statBg: "bg-amber-500/5",
        statBorder: "border-amber-500/15",
        statText: "text-amber-600 dark:text-amber-400",
        dotActive: "bg-amber-500",
        ctaBg: "bg-amber-600 text-white hover:bg-amber-700",
        glowBg: "bg-amber-500",
        headerBg: "bg-amber-500/5",
    },
    sky: {
        iconBg: "bg-sky-500/10",
        iconText: "text-sky-600 dark:text-sky-400",
        checkBg: "bg-sky-500/10",
        checkText: "text-sky-600 dark:text-sky-400",
        badgeBg: "bg-sky-500/10",
        badgeText: "text-sky-600 dark:text-sky-400",
        statBg: "bg-sky-500/5",
        statBorder: "border-sky-500/15",
        statText: "text-sky-600 dark:text-sky-400",
        dotActive: "bg-sky-500",
        ctaBg: "bg-sky-600 text-white hover:bg-sky-700",
        glowBg: "bg-sky-500",
        headerBg: "bg-sky-500/5",
    },
    purple: {
        iconBg: "bg-purple-500/10",
        iconText: "text-purple-600 dark:text-purple-400",
        checkBg: "bg-purple-500/10",
        checkText: "text-purple-600 dark:text-purple-400",
        badgeBg: "bg-purple-500/10",
        badgeText: "text-purple-600 dark:text-purple-400",
        statBg: "bg-purple-500/5",
        statBorder: "border-purple-500/15",
        statText: "text-purple-600 dark:text-purple-400",
        dotActive: "bg-purple-500",
        ctaBg: "bg-purple-600 text-white hover:bg-purple-700",
        glowBg: "bg-purple-500",
        headerBg: "bg-purple-500/5",
    },
};

const resolveAccentKey = (accentClass: string): AccentKey => {
    const raw = accentClass.replace("text-", "").replace("-500", "").replace("-600", "");
    if (raw === "primary") return "primary";
    if (raw === "indigo") return "indigo";
    if (raw === "rose") return "rose";
    if (raw === "emerald") return "emerald";
    if (raw === "amber") return "amber";
    if (raw === "sky") return "sky";
    if (raw === "purple") return "purple";
    return "primary";
};

const facultySteps: OnboardingStep[] = [
    {
        id: "faculty-flow",
        title: "Your command center",
        description: "Everything you need for today is in one focused view — classes, alerts, and quick actions.",
        highlights: ["Dashboard stats at a glance", "Today's schedule and upcoming classes", "Quick actions for class work"],
        stats: [
            { label: "Classes", value: "4 today" },
            { label: "Alerts", value: "2 items" },
            { label: "Actions", value: "Ready" },
        ],
        badge: "Dashboard",
        accent: "text-primary",
        icon: Sparkles,
    },
    {
        id: "faculty-classes",
        title: "Class tools built in",
        description: "Open any class to manage students, grades, and attendance — no switching between pages.",
        highlights: ["Class roster and student profiles", "Grades and attendance workflows", "Materials and resources"],
        stats: [
            { label: "Roster", value: "Live" },
            { label: "Grades", value: "Fast" },
            { label: "Attendance", value: "Tracked" },
        ],
        badge: "Classes",
        accent: "text-emerald-500",
        icon: BookOpen,
    },
    {
        id: "faculty-insights",
        title: "Spot trends instantly",
        description: "Performance snapshots and progress signals — no digging through reports.",
        highlights: ["Student performance trends", "Exportable grade reports", "Progress signals at a glance"],
        stats: [
            { label: "Trends", value: "Weekly" },
            { label: "Reports", value: "One click" },
            { label: "Visibility", value: "High" },
        ],
        badge: "Insights",
        accent: "text-sky-500",
        icon: Trophy,
    },
    {
        id: "faculty-community",
        title: "Stay in sync",
        description: "Post announcements and updates that reach your entire class instantly.",
        highlights: ["Announcements and notices", "Classwide updates", "Help and support access"],
        stats: [
            { label: "Reach", value: "Classwide" },
            { label: "Updates", value: "Instant" },
            { label: "Support", value: "Ready" },
        ],
        badge: "Connect",
        accent: "text-amber-500",
        icon: MessagesSquare,
    },
];

const iconMap: Record<string, LucideIcon> = {
    sparkles: Sparkles,
    "calendar-days": CalendarDays,
    "check-circle-2": Check,
    "clipboard-list": BookOpen,
    "graduation-cap": GraduationCap,
    "messages-square": MessagesSquare,
    stars: Stars,
    trophy: Trophy,
    users: Users,
    zap: Zap,
    "book-open": BookOpen,
};

const resolveIcon = (name: string): LucideIcon => iconMap[name] ?? Sparkles;

const studentSteps: OnboardingStep[] = [
    {
        id: "student-hub",
        title: "Welcome to your hub",
        description: "Your classes, grades, and key stats — all in one place. No hunting, no guessing.",
        highlights: ["Semester overview and quick stats", "Clearance and balance at a glance", "Digital ID card access"],
        stats: [
            { label: "Subjects", value: "6 enrolled" },
            { label: "Clearance", value: "Pending" },
            { label: "Balance", value: "Updated" },
        ],
        badge: "Dashboard",
        accent: "text-primary",
        icon: Stars,
    },
    {
        id: "student-schedule",
        title: "Know where to be",
        description: "A weekly class matrix and daily focus view so you never miss a room or time.",
        highlights: ["Weekly class matrix at a glance", "Room and instructor info on each slot", "Daily schedule with one tap"],
        stats: [
            { label: "Next class", value: "9:30 AM" },
            { label: "Room", value: "Lab 3" },
            { label: "Day view", value: "Focus" },
        ],
        badge: "Schedule",
        accent: "text-indigo-500",
        icon: CalendarDays,
    },
    {
        id: "student-growth",
        title: "Track your grades",
        description: "Subject-by-subject grade cards and performance charts so you always know where you stand.",
        highlights: ["Subject grade breakdowns", "Visual performance trends", "Goal-aware progress tracking"],
        stats: [
            { label: "GWA", value: "1.75" },
            { label: "Trend", value: "Up" },
            { label: "Subjects", value: "6" },
        ],
        badge: "Grades",
        accent: "text-rose-500",
        icon: GraduationCap,
    },
    {
        id: "student-ready",
        title: "Stay in the loop",
        description: "Announcements and reminders — you will never miss a deadline or update again.",
        highlights: ["Real-time announcements feed", "Smart deadline reminders", "Quick access to help and support"],
        stats: [
            { label: "Alerts", value: "2 new" },
            { label: "Reminders", value: "Active" },
            { label: "Support", value: "Ready" },
        ],
        badge: "Updates",
        accent: "text-emerald-500",
        icon: Zap,
    },
];

export function OnboardingExperience({ variant, userId, force = false, enabled = true, features, onDismiss, className }: OnboardingExperienceProps) {
    const fallbackSteps = useMemo(() => (variant === "student" ? studentSteps : facultySteps), [variant]);
    const featureSteps = useMemo(() => {
        if (!features || features.length === 0) return [];

        return features.flatMap((feature) =>
            feature.steps.map((step, index) => ({
                id: step.id ?? `${feature.featureKey}-${index}`,
                title: step.title,
                description: step.summary,
                highlights: step.highlights ?? [],
                stats: step.stats ?? [],
                badge: step.badge ?? feature.badge ?? "Feature",
                accent: step.accent ?? feature.accent ?? "text-primary",
                image: step.image,
                icon: resolveIcon(step.icon),
            })),
        );
    }, [features]);
    const steps = useMemo(() => {
        if (featureSteps.length > 0) return featureSteps;
        return fallbackSteps;
    }, [featureSteps, fallbackSteps]);

    const [currentIndex, setCurrentIndex] = useState(0);
    const [isOpen, setIsOpen] = useState(false);
    const [direction, setDirection] = useState(1);
    const contentRef = useRef<HTMLDivElement>(null);
    const touchStartXRef = useRef<number | null>(null);
    const touchStartYRef = useRef<number | null>(null);
    const swipeActionRef = useRef<"next" | "prev" | null>(null);

    useEffect(() => {
        if (steps.length > 0 && currentIndex >= steps.length) {
            setCurrentIndex(steps.length - 1);
        }
    }, [steps.length, currentIndex]);

    const storageKey = useMemo(() => {
        const safeId = userId ?? "guest";
        return `dccp.onboarding.${variant}.${safeId}`;
    }, [variant, userId]);

    useEffect(() => {
        if (typeof window === "undefined") return;
        if (!enabled) {
            setIsOpen(false);
            return;
        }
        if (force || (features && features.length > 0)) {
            setIsOpen(true);
            return;
        }
        const completed = window.localStorage.getItem(storageKey);
        setIsOpen(!completed);
    }, [force, storageKey, features, enabled]);

    useEffect(() => {
        if (!isOpen || typeof window === "undefined") return;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [isOpen]);

    const step = steps[currentIndex];
    const isLastStep = currentIndex === steps.length - 1;

    const goToStep = useCallback(
        (index: number) => {
            setDirection(index > currentIndex ? 1 : -1);
            setCurrentIndex(index);
        },
        [currentIndex],
    );

    const handleNext = useCallback(() => {
        if (isLastStep) {
            handleFinish();
            return;
        }
        setDirection(1);
        setCurrentIndex((prev) => Math.min(prev + 1, steps.length - 1));
    }, [isLastStep, steps.length, currentIndex]);

    const handlePrevious = useCallback(() => {
        setDirection(-1);
        setCurrentIndex((prev) => Math.max(prev - 1, 0));
    }, []);

    const handleDismiss = useCallback(() => {
        if (typeof window !== "undefined" && !force && (!features || features.length === 0)) {
            window.localStorage.setItem(storageKey, "true");
        }
        if (features && features.length > 0 && onDismiss) {
            features.forEach((feature) => onDismiss(feature.featureKey));
        }
        setIsOpen(false);
    }, [features, force, onDismiss, storageKey]);

    const handleFinish = useCallback(() => {
        handleDismiss();
    }, [handleDismiss]);

    const handleSkip = useCallback(() => {
        handleDismiss();
    }, [handleDismiss]);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") handleSkip();
            if (event.key === "ArrowRight") handleNext();
            if (event.key === "ArrowLeft") handlePrevious();
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [handleSkip, handleNext, handlePrevious]);

    // Touch / swipe — only trigger if horizontal swipe exceeds vertical
    const handleTouchStart = (e: React.TouchEvent) => {
        touchStartXRef.current = e.targetTouches[0].clientX;
        touchStartYRef.current = e.targetTouches[0].clientY;
        swipeActionRef.current = null;
    };

    const handleTouchMove = (e: React.TouchEvent) => {
        if (touchStartXRef.current === null || touchStartYRef.current === null) return;
        const dx = e.targetTouches[0].clientX - touchStartXRef.current;
        const dy = e.targetTouches[0].clientY - touchStartYRef.current;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 30) {
            swipeActionRef.current = dx < 0 ? "next" : "prev";
        }
    };

    const handleTouchEnd = () => {
        if (swipeActionRef.current === "next" && !isLastStep) handleNext();
        if (swipeActionRef.current === "prev" && currentIndex > 0) handlePrevious();
        touchStartXRef.current = null;
        touchStartYRef.current = null;
        swipeActionRef.current = null;
    };

    if (!isOpen || !step) return null;

    const Icon = step.icon;
    const accent = accents[resolveAccentKey(step.accent)];

    const slideVariants = {
        enter: (dir: number) => ({ x: dir > 0 ? 60 : -60, opacity: 0 }),
        center: { x: 0, opacity: 1 },
        exit: (dir: number) => ({ x: dir > 0 ? -60 : 60, opacity: 0 }),
    };

    return (
        <div
            className={cn("fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-6", className)}
            role="dialog"
            aria-modal="true"
            aria-labelledby="onboarding-title"
        >
            {/* Backdrop */}
            <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                className="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                onClick={handleSkip}
            />

            <AnimatePresence mode="wait" custom={direction}>
                <motion.div
                    key={step.id}
                    custom={direction}
                    variants={slideVariants}
                    initial="enter"
                    animate="center"
                    exit="exit"
                    transition={{ type: "spring", stiffness: 400, damping: 35, mass: 0.8 }}
                    onTouchStart={handleTouchStart}
                    onTouchMove={handleTouchMove}
                    onTouchEnd={handleTouchEnd}
                    className="sm:border-border/50 bg-background relative z-10 flex h-full w-full flex-col sm:h-auto sm:max-h-[88vh] sm:max-w-[480px] sm:rounded-2xl sm:border sm:shadow-2xl"
                >
                    {/* Accent header strip */}
                    <div className={cn("relative shrink-0 px-5 pt-6 pb-5 sm:px-7 sm:pt-7 sm:pb-6", accent.headerBg)}>
                        {/* Decorative glow */}
                        <div
                            className={cn("pointer-events-none absolute -top-16 -right-16 h-32 w-32 rounded-full opacity-15 blur-3xl", accent.glowBg)}
                        />

                        <div className="relative">
                            {/* Close button — top right */}
                            <button
                                onClick={handleSkip}
                                className="text-muted-foreground hover:bg-foreground/5 hover:text-foreground absolute -top-1 -right-1 rounded-lg p-1.5 transition-colors"
                                aria-label="Close"
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            {/* Icon + badge + title */}
                            <div className="flex items-start gap-3.5 sm:items-center">
                                <div className={cn("flex h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:h-11 sm:w-11", accent.iconBg)}>
                                    <Icon className={cn("h-5 w-5 sm:h-5.5 sm:w-5.5", accent.iconText)} />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="mb-0.5 flex items-center gap-2">
                                        <span
                                            className={cn(
                                                "inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-semibold tracking-wider uppercase",
                                                accent.badgeBg,
                                                accent.badgeText,
                                            )}
                                        >
                                            {step.badge}
                                        </span>
                                        <span className="text-muted-foreground/50 text-[10px] font-medium">
                                            Step {currentIndex + 1} / {steps.length}
                                        </span>
                                    </div>
                                    <h2 id="onboarding-title" className="text-foreground text-lg font-bold tracking-tight sm:text-xl">
                                        {step.title}
                                    </h2>
                                </div>
                            </div>

                            <p className="text-muted-foreground mt-3 text-sm leading-relaxed sm:text-[15px]">{step.description}</p>
                        </div>
                    </div>

                    {/* Scrollable content */}
                    <div ref={contentRef} className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 sm:px-7 sm:py-6">
                        {/* Highlights */}
                        <div className="space-y-2.5">
                            {step.highlights.map((highlight, i) => (
                                <motion.div
                                    key={highlight}
                                    initial={{ opacity: 0, x: -10 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    transition={{ delay: 0.06 * i, duration: 0.2 }}
                                    className="flex items-center gap-2.5"
                                >
                                    <div
                                        className={cn(
                                            "flex h-5 w-5 shrink-0 items-center justify-center rounded-full",
                                            accent.checkBg,
                                            accent.checkText,
                                        )}
                                    >
                                        <Check className="h-3 w-3" strokeWidth={3} />
                                    </div>
                                    <span className="text-foreground/85 text-sm">{highlight}</span>
                                </motion.div>
                            ))}
                        </div>

                        {/* Stats cards */}
                        {step.stats.length > 0 && !step.image && (
                            <div className="mt-5 grid grid-cols-3 gap-2.5 sm:gap-3">
                                {step.stats.map((stat) => (
                                    <div
                                        key={stat.label}
                                        className={cn("rounded-xl border p-2.5 text-center sm:p-3", accent.statBorder, accent.statBg)}
                                    >
                                        <p className={cn("text-base leading-tight font-bold sm:text-lg", accent.statText)}>{stat.value}</p>
                                        <p className="text-muted-foreground mt-0.5 text-[9px] font-semibold tracking-wider uppercase sm:text-[10px]">
                                            {stat.label}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Feature image */}
                        {step.image && (
                            <div className="border-border/40 mt-5 overflow-hidden rounded-xl border">
                                <img src={step.image} alt={step.title} className="h-40 w-full object-cover sm:h-48" />
                            </div>
                        )}

                        {/* Feature CTA */}
                        {features && features.length > 0 && features[0]?.ctaUrl && (
                            <div className="mt-5">
                                <Button asChild className="w-full" size="lg">
                                    <a href={features[0].ctaUrl} target="_blank" rel="noreferrer">
                                        {features[0].ctaLabel || "Try it now"}
                                        <ArrowRight className="ml-1.5 h-4 w-4" />
                                    </a>
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Footer — progress dots + nav */}
                    <div className="border-border/40 shrink-0 border-t px-5 pt-4 pb-5 sm:px-7 sm:pb-6">
                        {/* Progress dots — tappable */}
                        <div className="mb-4 flex items-center justify-center gap-2">
                            {steps.map((_, i) => (
                                <button
                                    key={i}
                                    onClick={() => goToStep(i)}
                                    className={cn(
                                        "h-2 rounded-full transition-all duration-250",
                                        i === currentIndex ? cn("w-6", accent.dotActive) : "bg-foreground/15 w-2",
                                    )}
                                    aria-label={`Go to step ${i + 1}`}
                                />
                            ))}
                        </div>

                        {/* Nav buttons */}
                        <div className="flex items-center justify-between gap-3">
                            <Button variant="ghost" size="sm" className="text-muted-foreground" onClick={handleSkip}>
                                Skip tour
                            </Button>

                            <div className="flex items-center gap-2">
                                {currentIndex > 0 && (
                                    <Button variant="outline" size="sm" onClick={handlePrevious}>
                                        <ArrowLeft className="mr-1 h-3.5 w-3.5" />
                                        Back
                                    </Button>
                                )}
                                <Button size="sm" className={cn(isLastStep && accent.ctaBg)} onClick={handleNext}>
                                    {isLastStep ? "Get started" : "Next"}
                                    {!isLastStep && <ArrowRight className="ml-1 h-3.5 w-3.5" />}
                                </Button>
                            </div>
                        </div>
                    </div>
                </motion.div>
            </AnimatePresence>
        </div>
    );
}
