import { AnimatePresence, motion } from "framer-motion";
import { ArrowLeft, ArrowRight, Sparkles, X } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

import { cn } from "@/lib/utils";

import { useOnboarding } from "./onboarding-context";

export interface TourStep {
    id: string;
    target: string;
    title: string;
    description: string;
    placement?: "top" | "bottom" | "left" | "right";
    ahaMoment?: string;
}

interface OnboardingTourProps {
    steps: TourStep[];
    className?: string;
}

function useElementRect(selector: string, deps: unknown[]) {
    const [rect, setRect] = useState<DOMRect | null>(null);

    useEffect(() => {
        const measure = () => {
            const el = document.querySelector(selector);
            if (el) {
                setRect(el.getBoundingClientRect());
            } else {
                setRect(null);
            }
        };

        measure();
        window.addEventListener("resize", measure);
        window.addEventListener("scroll", measure, true);

        const interval = setInterval(measure, 600);

        return () => {
            window.removeEventListener("resize", measure);
            window.removeEventListener("scroll", measure, true);
            clearInterval(interval);
        };
    }, [selector, ...deps]);

    return rect;
}

function computeTooltipPosition(
    targetRect: DOMRect,
    tooltipWidth: number,
    tooltipHeight: number,
    preferred: string,
    gap = 16,
): { top: number; left: number; placement: string } {
    const pad = 12;
    let top = 0;
    let left = 0;
    let placement = preferred;

    const vw = window.innerWidth;
    const vh = window.innerHeight;

    const placeAbove = () => {
        top = targetRect.top - tooltipHeight - gap;
        left = targetRect.left + targetRect.width / 2 - tooltipWidth / 2;
    };
    const placeBelow = () => {
        top = targetRect.bottom + gap;
        left = targetRect.left + targetRect.width / 2 - tooltipWidth / 2;
    };
    const placeLeft = () => {
        top = targetRect.top + targetRect.height / 2 - tooltipHeight / 2;
        left = targetRect.left - tooltipWidth - gap;
    };
    const placeRight = () => {
        top = targetRect.top + targetRect.height / 2 - tooltipHeight / 2;
        left = targetRect.right + gap;
    };

    switch (preferred) {
        case "top":
            placeAbove();
            break;
        case "bottom":
            placeBelow();
            break;
        case "left":
            placeLeft();
            break;
        case "right":
            placeRight();
            break;
        default:
            placeBelow();
    }

    // Boundary checks with auto-flip
    if (left < pad) left = pad;
    if (left + tooltipWidth > vw - pad) left = vw - tooltipWidth - pad;
    if (top < pad) top = pad;
    if (top + tooltipHeight > vh - pad) top = vh - tooltipHeight - pad;

    // If still off-screen after clamping, try flipping placement
    if (preferred === "bottom" && targetRect.bottom + gap + tooltipHeight > vh - pad) {
        placement = "top";
        placeAbove();
    } else if (preferred === "top" && targetRect.top - tooltipHeight - gap < pad) {
        placement = "bottom";
        placeBelow();
    } else if (preferred === "right" && targetRect.right + gap + tooltipWidth > vw - pad) {
        placement = "left";
        placeLeft();
    } else if (preferred === "left" && targetRect.left - tooltipWidth - gap < pad) {
        placement = "right";
        placeRight();
    }

    // Final clamp
    if (left < pad) left = pad;
    if (left + tooltipWidth > vw - pad) left = vw - tooltipWidth - pad;
    if (top < pad) top = pad;
    if (top + tooltipHeight > vh - pad) top = vh - tooltipHeight - pad;

    return { top, left, placement };
}

export function OnboardingTour({ steps, className }: OnboardingTourProps) {
    const { isOpen, currentStepIndex, nextStep, previousStep, dismissOnboarding, completeStep, trackEvent } = useOnboarding();

    const currentStep = steps[currentStepIndex];
    const isLastStep = currentStepIndex === steps.length - 1;

    const tooltipRef = useRef<HTMLDivElement>(null);
    const [tooltipSize, setTooltipSize] = useState({ width: 320, height: 180 });

    const targetRect = useElementRect(currentStep?.target ?? "", [currentStepIndex, isOpen]);

    // Scroll target into view
    useEffect(() => {
        if (!isOpen || !currentStep) return;
        const el = document.querySelector(currentStep.target);
        if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    }, [isOpen, currentStep]);

    // Measure tooltip
    useEffect(() => {
        if (tooltipRef.current) {
            const r = tooltipRef.current.getBoundingClientRect();
            setTooltipSize({ width: Math.max(r.width, 280), height: Math.max(r.height, 120) });
        }
    }, [currentStep, targetRect]);

    // If target missing for >2s, skip this step
    const [missingTargetMs, setMissingTargetMs] = useState(0);
    useEffect(() => {
        if (!isOpen || !currentStep) return;
        if (targetRect) {
            setMissingTargetMs(0);
            return;
        }
        const t = setInterval(() => setMissingTargetMs((p) => p + 500), 500);
        return () => clearInterval(t);
    }, [isOpen, currentStep, targetRect]);

    useEffect(() => {
        if (missingTargetMs > 2000 && currentStep) {
            completeStep(currentStep.id);
            nextStep();
            setMissingTargetMs(0);
        }
    }, [missingTargetMs, currentStep, completeStep, nextStep]);

    const handleNext = useCallback(() => {
        if (currentStep) completeStep(currentStep.id);
        trackEvent(isLastStep ? "completed" : "step_advanced", { step: currentStepIndex + 1 });
        if (isLastStep) {
            dismissOnboarding();
        } else {
            nextStep();
        }
    }, [isLastStep, currentStep, completeStep, nextStep, dismissOnboarding, trackEvent, currentStepIndex]);

    const handlePrev = useCallback(() => {
        trackEvent("step_back", { step: currentStepIndex - 1 });
        previousStep();
    }, [previousStep, trackEvent, currentStepIndex]);

    const handleSkip = useCallback(() => {
        trackEvent("skipped", { step: currentStepIndex });
        dismissOnboarding();
    }, [dismissOnboarding, trackEvent, currentStepIndex]);

    // Keyboard nav
    useEffect(() => {
        if (!isOpen) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === "Escape") handleSkip();
            if (e.key === "ArrowRight") handleNext();
            if (e.key === "ArrowLeft" && currentStepIndex > 0) handlePrev();
        };
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [isOpen, handleNext, handlePrev, handleSkip, currentStepIndex]);

    if (!isOpen || !currentStep) return null;

    const tPos = targetRect
        ? computeTooltipPosition(targetRect, tooltipSize.width, tooltipSize.height, currentStep.placement ?? "bottom")
        : { top: window.innerHeight / 2 - tooltipSize.height / 2, left: window.innerWidth / 2 - tooltipSize.width / 2, placement: "bottom" };

    const overallProgress = steps.length > 0 ? ((currentStepIndex + 1) / steps.length) * 100 : 0;

    return (
        <div className={cn("pointer-events-none fixed inset-0 z-50", className)}>
            {/* Dim overlay — pointer-events-none so UI stays interactive */}
            <div className="absolute inset-0 bg-black/40" />

            {/* Spotlight box around target */}
            {targetRect && (
                <motion.div
                    className="pointer-events-none absolute z-10 rounded-xl"
                    initial={false}
                    animate={{
                        top: targetRect.top - 8,
                        left: targetRect.left - 8,
                        width: targetRect.width + 16,
                        height: targetRect.height + 16,
                    }}
                    transition={{ type: "spring", stiffness: 350, damping: 32 }}
                    style={{ boxShadow: "0 0 0 9999px rgba(0, 0, 0, 0.4)" }}
                />
            )}

            {/* Progress bar */}
            <div className="pointer-events-none absolute top-0 right-0 left-0 h-1 bg-transparent">
                <motion.div
                    className="bg-primary h-full"
                    initial={{ width: 0 }}
                    animate={{ width: `${overallProgress}%` }}
                    transition={{ duration: 0.4, ease: "easeOut" }}
                />
            </div>

            {/* Tooltip */}
            <AnimatePresence mode="wait">
                <motion.div
                    key={currentStep.id}
                    ref={tooltipRef}
                    initial={{ opacity: 0, scale: 0.92, y: 12 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.92, y: -12 }}
                    transition={{ duration: 0.25, ease: "easeOut" }}
                    className="pointer-events-auto bg-background absolute z-20 w-80 rounded-xl border p-4 shadow-2xl"
                    style={{ top: tPos.top, left: tPos.left }}
                >
                    {/* Close */}
                    <button
                        onClick={handleSkip}
                        className="text-muted-foreground hover:text-foreground hover:bg-muted absolute top-2 right-2 rounded-md p-1 transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>

                    {/* Header */}
                    <div className="mb-2 flex items-center gap-2">
                        <span className="bg-primary/10 text-primary inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider">
                            Step {currentStepIndex + 1} / {steps.length}
                        </span>
                        {currentStep.ahaMoment && (
                            <span className="bg-amber-500/10 text-amber-600 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider">
                                <Sparkles className="h-3 w-3" />
                                Aha!
                            </span>
                        )}
                    </div>

                    {/* Title */}
                    <h3 className="text-foreground mb-1 text-sm font-bold">{currentStep.title}</h3>

                    {/* Description */}
                    <p className="text-muted-foreground mb-3 text-xs leading-relaxed">{currentStep.description}</p>

                    {/* Aha moment */}
                    {currentStep.ahaMoment && (
                        <div className="border-amber-500/20 bg-amber-500/5 mb-3 rounded-lg border p-2.5">
                            <div className="mb-1 flex items-center gap-1.5">
                                <Sparkles className="h-3.5 w-3.5 text-amber-600" />
                                <span className="text-amber-700 text-[10px] font-bold uppercase tracking-wide">Aha Moment</span>
                            </div>
                            <p className="text-amber-800 text-xs leading-relaxed">{currentStep.ahaMoment}</p>
                        </div>
                    )}

                    {/* Progress dots */}
                    <div className="mb-3 flex items-center justify-center gap-1.5">
                        {steps.map((s, i) => (
                            <button
                                key={s.id}
                                onClick={() => {
                                    if (i < currentStepIndex) {
                                        for (let j = currentStepIndex; j > i; j--) previousStep();
                                    } else if (i > currentStepIndex) {
                                        for (let j = currentStepIndex; j < i; j++) {
                                            completeStep(steps[j].id);
                                            nextStep();
                                        }
                                    }
                                }}
                                className={cn(
                                    "h-2 rounded-full transition-all duration-200",
                                    i === currentStepIndex ? "bg-primary w-5" : i < currentStepIndex ? "bg-emerald-500 w-2" : "bg-muted-foreground/20 w-2",
                                )}
                                aria-label={`Go to step ${i + 1}`}
                            />
                        ))}
                    </div>

                    {/* Footer actions */}
                    <div className="flex items-center justify-between gap-3">
                        <button
                            onClick={handleSkip}
                            className="text-muted-foreground hover:text-foreground rounded-md px-2 py-1 text-xs transition-colors"
                        >
                            Skip tour
                        </button>
                        <div className="flex items-center gap-2">
                            {currentStepIndex > 0 && (
                                <button
                                    onClick={handlePrev}
                                    className="text-muted-foreground hover:text-foreground hover:bg-muted flex items-center gap-1 rounded-md px-2 py-1.5 text-xs transition-colors"
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                    Back
                                </button>
                            )}
                            <button
                                onClick={handleNext}
                                className="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                            >
                                {isLastStep ? "Get started" : "Next"}
                                {!isLastStep && <ArrowRight className="h-3.5 w-3.5" />}
                            </button>
                        </div>
                    </div>
                </motion.div>
            </AnimatePresence>
        </div>
    );
}
