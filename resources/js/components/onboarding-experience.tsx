import { AnimatePresence, motion } from "framer-motion";
import type { LucideIcon } from "lucide-react";
import {
    ArrowRight,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    GraduationCap,
    MessagesSquare,
    Sparkles,
    Stars,
    Trophy,
    Users,
    Zap,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";

interface OnboardingStat {
    label: string;
    value: string;
}

interface OnboardingStep {
    id: string;
    title: string;
    summary: string;
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

const facultySteps: OnboardingStep[] = [
    {
        id: "faculty-flow",
        title: "Your faculty command board",
        summary: "Everything you need to run today lives in one focused view.",
        highlights: ["Dashboard stats and alerts", "Today’s schedule and upcoming classes", "Quick actions for class work"],
        stats: [
            { label: "Classes", value: "4 today" },
            { label: "Alerts", value: "2 items" },
            { label: "Actions", value: "Ready" },
        ],
        badge: "Faculty Overview",
        accent: "text-primary",
        icon: Sparkles,
    },
    {
        id: "faculty-classes",
        title: "Class tools at your fingertips",
        summary: "Open any class to manage students, grades, and attendance.",
        highlights: ["Class roster and profiles", "Grades + attendance workflows", "Materials and resources"],
        stats: [
            { label: "Roster", value: "Live" },
            { label: "Grades", value: "Fast" },
            { label: "Attendance", value: "Tracked" },
        ],
        badge: "Class Management",
        accent: "text-emerald-500",
        icon: ClipboardList,
    },
    {
        id: "faculty-insights",
        title: "Stay ahead with insights",
        summary: "Spot trends, progress, and gaps without digging for data.",
        highlights: ["Performance snapshots", "Student progress signals", "Exportable reports"],
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
        title: "Keep everyone in sync",
        summary: "Announcements and updates go out fast with built-in tools.",
        highlights: ["Announcements and notices", "Classwide updates", "Help and support access"],
        stats: [
            { label: "Reach", value: "Classwide" },
            { label: "Updates", value: "Instant" },
            { label: "Support", value: "Ready" },
        ],
        badge: "Communication",
        accent: "text-amber-500",
        icon: MessagesSquare,
    },
];

const iconMap: Record<string, LucideIcon> = {
    sparkles: Sparkles,
    "calendar-days": CalendarDays,
    "check-circle-2": CheckCircle2,
    "clipboard-list": ClipboardList,
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
        title: "Your student home base",
        summary: "See your classes, status, and quick stats at a glance.",
        highlights: ["Semester overview and progress", "Quick stats for clearance + balance", "Digital ID card access"],
        stats: [
            { label: "Subjects", value: "6 enrolled" },
            { label: "Clearance", value: "Pending" },
            { label: "Balance", value: "Updated" },
        ],
        badge: "Student Overview",
        accent: "text-primary",
        icon: Stars,
    },
    {
        id: "student-schedule",
        title: "Schedule, simplified",
        summary: "Plan your week with visual schedules and daily focus views.",
        highlights: ["Weekly class matrix", "Rooms and instructors", "Daily focus timeline"],
        stats: [
            { label: "Next", value: "9:30 AM" },
            { label: "Room", value: "Lab 3" },
            { label: "Mode", value: "Focus" },
        ],
        badge: "Schedule",
        accent: "text-indigo-500",
        icon: CalendarDays,
    },
    {
        id: "student-growth",
        title: "Track your performance",
        summary: "Grades and progress charts show where you’re winning.",
        highlights: ["Subject grade tracker", "Performance charts", "Goal-aware progress"],
        stats: [
            { label: "GWA", value: "1.75" },
            { label: "Trend", value: "Up 8%" },
            { label: "Goals", value: "On track" },
        ],
        badge: "Performance",
        accent: "text-rose-500",
        icon: GraduationCap,
    },
    {
        id: "student-ready",
        title: "Never miss an update",
        summary: "Announcements and reminders keep you on top of deadlines.",
        highlights: ["Announcements feed", "Smart reminders", "Help and support access"],
        stats: [
            { label: "Alerts", value: "2 new" },
            { label: "Reminders", value: "Active" },
            { label: "Support", value: "Ready" },
        ],
        badge: "Stay Informed",
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
                summary: step.summary,
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
        if (featureSteps.length > 0) {
            return featureSteps;
        }

        return fallbackSteps;
    }, [featureSteps, fallbackSteps]);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [isOpen, setIsOpen] = useState(false);

    // Ensure currentIndex stays within bounds when steps changes
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
    const progress = Math.round(((currentIndex + 1) / steps.length) * 100);
    const isLastStep = currentIndex === steps.length - 1;

    const handleNext = () => {
        if (isLastStep) {
            handleFinish();
            return;
        }

        setCurrentIndex((prev) => Math.min(prev + 1, steps.length - 1));
    };

    const handlePrevious = () => {
        setCurrentIndex((prev) => Math.max(prev - 1, 0));
    };

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
            if (event.key === "Escape") {
                handleSkip();
            }
        };

        window.addEventListener("keydown", handleKeyDown);

        return () => {
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, [handleSkip]);

    if (!isOpen) {
        return null;
    }

    // Guard against undefined step (can happen during re-renders when steps array changes)
    if (!step) {
        return null;
    }

    const Icon = step.icon;

    return (
        <div
            className={cn("fixed inset-0 z-50 flex items-center justify-center", className)}
            role="dialog"
            aria-modal="true"
            aria-labelledby="onboarding-title"
        >
            <div className="bg-background/80 absolute inset-0 backdrop-blur-md" />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_hsl(var(--primary)/0.2),_transparent_55%)] opacity-60" />

            <AnimatePresence mode="wait">
                <motion.div
                    key={step.id}
                    initial={{ opacity: 0, y: 20, scale: 0.98 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: -10, scale: 0.98 }}
                    transition={{ duration: 0.35, ease: "easeOut" }}
                    className="relative z-10 w-full max-w-5xl px-4"
                >
                    <div className="border-border/60 bg-card/80 relative overflow-hidden rounded-3xl border shadow-2xl shadow-black/10 backdrop-blur-xl">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_bottom,_hsl(var(--accent)/0.25),_transparent_65%)]" />
                        <div className="relative grid gap-10 p-8 md:grid-cols-[1.1fr_0.9fr] md:p-10">
                            <div className="flex flex-col justify-between gap-8">
                                <div className="space-y-6">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Badge variant="secondary" className="rounded-full px-4 py-1 text-xs font-semibold">
                                            {step.badge}
                                        </Badge>
                                        <Badge variant="outline" className="rounded-full px-4 py-1 text-xs">
                                            Step {currentIndex + 1} of {steps.length}
                                        </Badge>
                                    </div>

                                    <div className="space-y-3">
                                        <h2 id="onboarding-title" className="text-foreground text-3xl font-bold tracking-tight">
                                            {step.title}
                                        </h2>
                                        <p className="text-muted-foreground text-base">{step.summary}</p>
                                    </div>

                                    <div className="space-y-3">
                                        {step.highlights.map((highlight) => (
                                            <div key={highlight} className="flex items-start gap-3 text-sm">
                                                <div className="bg-primary/10 text-primary mt-0.5 flex h-6 w-6 items-center justify-center rounded-full">
                                                    <CheckCircle2 className="h-4 w-4" />
                                                </div>
                                                <span className="text-foreground/90">{highlight}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    <div className="flex items-center gap-4">
                                        <Progress value={progress} className="h-2 flex-1" />
                                        <span className="text-muted-foreground text-xs font-semibold">{progress}%</span>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button variant="ghost" className="text-muted-foreground" onClick={handleSkip}>
                                            Skip for now
                                        </Button>
                                        <div className="flex flex-1 justify-end gap-2">
                                            <Button variant="outline" onClick={handlePrevious} disabled={currentIndex === 0}>
                                                Back
                                            </Button>
                                            <Button onClick={handleNext}>
                                                {isLastStep ? "Launch dashboard" : "Next"}
                                                <ArrowRight className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="relative">
                                <div className="bg-primary/20 absolute -top-20 -right-20 h-40 w-40 rounded-full blur-3xl" />
                                <div className="bg-accent/40 absolute -bottom-16 left-10 h-32 w-32 rounded-full blur-3xl" />

                                <div className="border-border/70 bg-background/70 relative flex h-full flex-col justify-between gap-6 overflow-hidden rounded-3xl border p-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-2xl">
                                                <Icon className={cn("h-6 w-6", step.accent)} />
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-xs tracking-[0.2em] uppercase">
                                                    {variant === "student" ? "Student View" : "Faculty View"}
                                                </p>
                                                <p className="text-foreground text-lg font-semibold">Live Preview</p>
                                            </div>
                                        </div>
                                        <Badge variant="secondary" className="gap-1 rounded-full px-3 py-1 text-xs">
                                            <Sparkles className="h-3.5 w-3.5" />
                                            {variant === "student" ? "Momentum" : "Command"}
                                        </Badge>
                                    </div>

                                    {step.image ? (
                                        <div className="border-border/70 bg-muted/40 overflow-hidden rounded-2xl border">
                                            <img src={step.image} alt={step.title} className="h-44 w-full object-cover" />
                                        </div>
                                    ) : (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            {step.stats.map((stat) => (
                                                <div key={stat.label} className="border-border/70 bg-background/80 rounded-2xl border p-4 shadow-sm">
                                                    <p className="text-muted-foreground text-xs tracking-widest uppercase">{stat.label}</p>
                                                    <p className="text-foreground mt-2 text-2xl font-semibold">{stat.value}</p>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    <div className="border-border/70 bg-muted/40 rounded-2xl border border-dashed p-4">
                                        <div className="flex items-start gap-3">
                                            <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-2xl">
                                                <BookOpen className="h-5 w-5" />
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-foreground text-sm font-semibold">Quick win</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {variant === "student"
                                                        ? "Pin your schedule for faster planning each morning."
                                                        : "Pin your top classes for one-tap access."}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="from-primary/10 via-background/70 to-accent/20 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-gradient-to-r p-4">
                                        <div>
                                            <p className="text-muted-foreground text-xs tracking-widest uppercase">
                                                {features && features.length > 0 ? "Feature" : "Ready in"}
                                            </p>
                                            <p className="text-foreground text-lg font-semibold">
                                                {features && features.length > 0 ? (features[0]?.name ?? "Quick tour") : "Under 2 minutes"}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            {features && features[0]?.ctaUrl ? (
                                                <Button asChild size="sm">
                                                    <a href={features[0].ctaUrl} target="_blank" rel="noreferrer">
                                                        {features[0].ctaLabel || "Open feature"}
                                                    </a>
                                                </Button>
                                            ) : (
                                                <div className="text-primary flex items-center gap-2">
                                                    <Users className="h-5 w-5" />
                                                    <span className="text-sm font-semibold">Guided</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </motion.div>
            </AnimatePresence>
        </div>
    );
}
