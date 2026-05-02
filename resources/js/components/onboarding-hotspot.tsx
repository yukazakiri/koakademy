import { AnimatePresence, motion } from "framer-motion";
import { useState } from "react";

import { cn } from "@/lib/utils";

interface OnboardingHotspotProps {
    targetId: string;
    title: string;
    description: string;
    stepNumber: number;
    totalSteps: number;
    onComplete?: () => void;
    onSkip?: () => void;
    placement?: "top" | "bottom" | "left" | "right";
    className?: string;
}

export function OnboardingHotspot({
    targetId,
    title,
    description,
    stepNumber,
    totalSteps,
    onComplete,
    onSkip,
    placement = "bottom",
    className,
}: OnboardingHotspotProps) {
    const [isVisible, setIsVisible] = useState(true);
    const [isPulsing, setIsPulsing] = useState(true);

    const handleComplete = () => {
        setIsVisible(false);
        onComplete?.();
    };

    const handleSkip = () => {
        setIsVisible(false);
        onSkip?.();
    };

    const placementClasses = {
        top: "bottom-full left-1/2 -translate-x-1/2 mb-3",
        bottom: "top-full left-1/2 -translate-x-1/2 mt-3",
        left: "right-full top-1/2 -translate-y-1/2 mr-3",
        right: "left-full top-1/2 -translate-y-1/2 ml-3",
    };

    const arrowClasses = {
        top: "top-full left-1/2 -translate-x-1/2 border-t-white dark:border-t-slate-900",
        bottom: "bottom-full left-1/2 -translate-x-1/2 border-b-white dark:border-b-slate-900",
        left: "left-full top-1/2 -translate-y-1/2 border-l-white dark:border-l-slate-900",
        right: "right-full top-1/2 -translate-y-1/2 border-r-white dark:border-r-slate-900",
    };

    if (!isVisible) return null;

    return (
        <div className={cn("relative inline-block", className)} data-hotspot-target={targetId}>
            {/* Pulse ring */}
            <AnimatePresence>
                {isPulsing && (
                    <motion.div
                        initial={{ scale: 1, opacity: 0.8 }}
                        animate={{ scale: 2, opacity: 0 }}
                        transition={{ duration: 1.5, repeat: Infinity, ease: "easeOut" }}
                        className="bg-primary/30 absolute inset-0 rounded-full"
                    />
                )}
            </AnimatePresence>

            {/* Hotspot dot */}
            <motion.button
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ type: "spring", stiffness: 500, damping: 30 }}
                onClick={() => setIsPulsing(false)}
                className="bg-primary relative z-10 flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white shadow-lg hover:scale-110 transition-transform"
            >
                {stepNumber}
            </motion.button>

            {/* Tooltip */}
            <motion.div
                initial={{ opacity: 0, y: 8, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ delay: 0.3, duration: 0.3 }}
                className={cn(
                    "absolute z-50 w-72 rounded-xl border bg-white p-4 shadow-xl dark:bg-slate-900 dark:border-slate-700",
                    placementClasses[placement]
                )}
            >
                {/* Arrow */}
                <div
                    className={cn(
                        "absolute h-0 w-0 border-8 border-transparent",
                        arrowClasses[placement]
                    )}
                />

                {/* Header */}
                <div className="mb-2 flex items-center justify-between">
                    <span className="text-primary text-xs font-semibold uppercase tracking-wider">
                        Step {stepNumber} of {totalSteps}
                    </span>
                    <button
                        onClick={handleSkip}
                        className="text-muted-foreground hover:text-foreground text-xs transition-colors"
                    >
                        Skip
                    </button>
                </div>

                {/* Title */}
                <h4 className="mb-1 text-sm font-bold">{title}</h4>

                {/* Description */}
                <p className="text-muted-foreground mb-3 text-xs leading-relaxed">{description}</p>

                {/* SVG Visual Guide */}
                <div className="bg-muted/50 mb-3 overflow-hidden rounded-lg p-2">
                    <svg viewBox="0 0 240 80" className="h-16 w-full">
                        <rect x="8" y="8" width="224" height="64" rx="8" fill="currentColor" className="text-primary/5" />
                        <circle cx="40" cy="40" r="12" fill="currentColor" className="text-primary/20" />
                        <rect x="64" y="28" width="120" height="8" rx="4" fill="currentColor" className="text-primary/15" />
                        <rect x="64" y="44" width="80" height="8" rx="4" fill="currentColor" className="text-primary/10" />
                        <motion.circle
                            cx="200"
                            cy="40"
                            r="8"
                            fill="currentColor"
                            className="text-primary"
                            animate={{ scale: [1, 1.2, 1] }}
                            transition={{ duration: 2, repeat: Infinity }}
                        />
                    </svg>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-end gap-2">
                    <button
                        onClick={handleSkip}
                        className="text-muted-foreground hover:text-foreground rounded-lg px-2 py-1 text-xs transition-colors"
                    >
                        Later
                    </button>
                    <button
                        onClick={handleComplete}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                    >
                        Got it
                    </button>
                </div>
            </motion.div>
        </div>
    );
}
