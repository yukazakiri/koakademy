import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

export interface OnboardingChecklistItem {
    id: string;
    label: string;
    description: string;
    actionRoute?: string;
    actionLabel?: string;
    isCompleted: boolean;
}

export interface OnboardingProgress {
    completedSteps: string[];
    checklistState: Record<string, boolean>;
    currentStepIndex: number;
    isDismissed: boolean;
    startedAt?: string;
    completedAt?: string;
}

interface OnboardingContextValue {
    variant: string;
    userId: string | number | null;
    isOpen: boolean;
    currentStepIndex: number;
    totalSteps: number;
    checklist: OnboardingChecklistItem[];
    progress: OnboardingProgress;
    isLoading: boolean;
    openOnboarding: () => void;
    closeOnboarding: () => void;
    goToStep: (index: number) => void;
    nextStep: () => void;
    previousStep: () => void;
    completeStep: (stepId: string) => void;
    toggleChecklistItem: (itemId: string) => void;
    dismissOnboarding: () => void;
    trackEvent: (event: string, metadata?: Record<string, unknown>) => void;
}

const OnboardingContext = createContext<OnboardingContextValue | null>(null);

export function useOnboarding(): OnboardingContextValue {
    const ctx = useContext(OnboardingContext);
    if (!ctx) {
        throw new Error("useOnboarding must be used within OnboardingProvider");
    }
    return ctx;
}

interface OnboardingProviderProps {
    variant: string;
    userId?: string | number | null;
    children: React.ReactNode;
    checklist: OnboardingChecklistItem[];
    totalSteps: number;
    enabled?: boolean;
}

export function OnboardingProvider({
    variant,
    userId,
    children,
    checklist,
    totalSteps,
    enabled = true,
}: OnboardingProviderProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [currentStepIndex, setCurrentStepIndex] = useState(0);
    const [isLoading, setIsLoading] = useState(true);
    const [progress, setProgress] = useState<OnboardingProgress>({
        completedSteps: [],
        checklistState: {},
        currentStepIndex: 0,
        isDismissed: false,
    });

    const storageKey = useMemo(() => `dccp.onboarding.${variant}.${userId ?? "guest"}`, [variant, userId]);

    // Load progress from backend or localStorage
    useEffect(() => {
        if (!enabled) {
            setIsLoading(false);
            return;
        }

        const loadProgress = async () => {
            try {
                const response = await fetch(`/onboarding/progress?variant=${variant}`, {
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
                    },
                });

                if (response.ok) {
                    const data = (await response.json()) as { progress?: OnboardingProgress };
                    if (data.progress) {
                        setProgress(data.progress);
                        setCurrentStepIndex(data.progress.currentStepIndex ?? 0);
                        if (data.progress.isDismissed) {
                            setIsOpen(false);
                        }
                        setIsLoading(false);
                        return;
                    }
                }
            } catch {
                // Fallback to localStorage
            }

            // Fallback: check localStorage
            const stored = window.localStorage.getItem(storageKey);
            if (stored) {
                try {
                    const parsed = JSON.parse(stored) as OnboardingProgress;
                    setProgress(parsed);
                    setCurrentStepIndex(parsed.currentStepIndex ?? 0);
                    if (parsed.isDismissed) {
                        setIsOpen(false);
                    }
                } catch {
                    // Invalid stored data
                }
            } else {
                // No prior progress — auto-start for new users
                setIsOpen(true);
            }
            setIsLoading(false);
        };

        loadProgress();
    }, [variant, userId, enabled, storageKey]);

    // Persist progress
    const persistProgress = useCallback(
        async (updated: OnboardingProgress) => {
            setProgress(updated);
            window.localStorage.setItem(storageKey, JSON.stringify(updated));

            try {
                await fetch("/onboarding/progress", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
                    },
                    body: JSON.stringify({
                        variant,
                        completed_steps: updated.completedSteps,
                        checklist_state: updated.checklistState,
                        current_step_index: updated.currentStepIndex,
                        is_dismissed: updated.isDismissed,
                    }),
                });
            } catch {
                // Silent fail - localStorage is fallback
            }
        },
        [variant, storageKey]
    );

    const openOnboarding = useCallback(() => setIsOpen(true), []);

    const closeOnboarding = useCallback(() => setIsOpen(false), []);

    const goToStep = useCallback(
        (index: number) => {
            setCurrentStepIndex(index);
            const updated = { ...progress, currentStepIndex: index };
            persistProgress(updated);
        },
        [progress, persistProgress]
    );

    const nextStep = useCallback(() => {
        setCurrentStepIndex((prev) => {
            const next = Math.min(prev + 1, totalSteps - 1);
            const updated = { ...progress, currentStepIndex: next };
            persistProgress(updated);
            return next;
        });
    }, [totalSteps, progress, persistProgress]);

    const previousStep = useCallback(() => {
        setCurrentStepIndex((prev) => {
            const next = Math.max(prev - 1, 0);
            const updated = { ...progress, currentStepIndex: next };
            persistProgress(updated);
            return next;
        });
    }, [progress, persistProgress]);

    const completeStep = useCallback(
        (stepId: string) => {
            const updated = {
                ...progress,
                completedSteps: [...new Set([...progress.completedSteps, stepId])],
            };
            persistProgress(updated);
        },
        [progress, persistProgress]
    );

    const toggleChecklistItem = useCallback(
        (itemId: string) => {
            const updated = {
                ...progress,
                checklistState: {
                    ...progress.checklistState,
                    [itemId]: !progress.checklistState[itemId],
                },
            };
            persistProgress(updated);
        },
        [progress, persistProgress]
    );

    const dismissOnboarding = useCallback(() => {
        const updated = { ...progress, isDismissed: true };
        persistProgress(updated);
        setIsOpen(false);
    }, [progress, persistProgress]);

    const trackEvent = useCallback((event: string, metadata?: Record<string, unknown>) => {
        // Analytics tracking - can be integrated with analytics service
        if (typeof window !== "undefined" && "gtag" in window) {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            (window as any).gtag?.("event", `onboarding_${event}`, {
                event_category: "onboarding",
                variant,
                ...metadata,
            });
        }

        // Console log for development
        if (import.meta.env.DEV) {
            console.log("[Onboarding]", event, metadata);
        }
    }, [variant]);

    const value = useMemo(
        () => ({
            variant,
            userId: userId ?? null,
            isOpen,
            currentStepIndex,
            totalSteps,
            checklist,
            progress,
            isLoading,
            openOnboarding,
            closeOnboarding,
            goToStep,
            nextStep,
            previousStep,
            completeStep,
            toggleChecklistItem,
            dismissOnboarding,
            trackEvent,
        }),
        [
            variant,
            userId,
            isOpen,
            currentStepIndex,
            totalSteps,
            checklist,
            progress,
            isLoading,
            openOnboarding,
            closeOnboarding,
            goToStep,
            nextStep,
            previousStep,
            completeStep,
            toggleChecklistItem,
            dismissOnboarding,
            trackEvent,
        ]
    );

    return <OnboardingContext.Provider value={value}>{children}</OnboardingContext.Provider>;
}
