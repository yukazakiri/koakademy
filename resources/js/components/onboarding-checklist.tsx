import { AnimatePresence, motion } from "framer-motion";
import { Check, ChevronDown, ChevronUp, Sparkles } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

import { useOnboarding } from "./onboarding-context";

export function OnboardingChecklistWidget() {
    const { checklist, progress, toggleChecklistItem, openOnboarding, isLoading } = useOnboarding();
    const [isExpanded, setIsExpanded] = useState(true);

    if (isLoading || progress.isDismissed) return null;

    const completedCount = checklist.filter((item) => progress.checklistState[item.id]).length;
    const totalCount = checklist.length;
    const progressPercent = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;
    const isComplete = completedCount === totalCount && totalCount > 0;

    if (isComplete) {
        return (
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className="border-primary/20 bg-primary/5 rounded-xl border p-4"
            >
                <div className="flex items-center gap-3">
                    <div className="bg-primary flex h-10 w-10 items-center justify-center rounded-full">
                        <Sparkles className="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold">You are all set!</p>
                        <p className="text-muted-foreground text-xs">You have completed the onboarding checklist.</p>
                    </div>
                </div>
            </motion.div>
        );
    }

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="border-border/60 bg-background rounded-xl border shadow-sm"
        >
            {/* Header */}
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex w-full items-center justify-between p-4"
            >
                <div className="flex items-center gap-3">
                    <div className="relative">
                        <svg className="h-10 w-10 -rotate-90" viewBox="0 0 36 36">
                            <path
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="3"
                                className="text-muted/20"
                            />
                            <motion.path
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="3"
                                strokeDasharray={`${progressPercent}, 100`}
                                className="text-primary"
                                initial={{ strokeDasharray: "0, 100" }}
                                animate={{ strokeDasharray: `${progressPercent}, 100` }}
                                transition={{ duration: 0.8, ease: "easeOut" }}
                            />
                        </svg>
                        <span className="absolute inset-0 flex items-center justify-center text-[10px] font-bold">
                            {progressPercent}%
                        </span>
                    </div>
                    <div className="text-left">
                        <p className="text-sm font-semibold">Getting Started</p>
                        <p className="text-muted-foreground text-xs">
                            {completedCount} of {totalCount} completed
                        </p>
                    </div>
                </div>
                {isExpanded ? (
                    <ChevronUp className="text-muted-foreground h-4 w-4" />
                ) : (
                    <ChevronDown className="text-muted-foreground h-4 w-4" />
                )}
            </button>

            {/* Checklist Items */}
            <AnimatePresence>
                {isExpanded && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: "auto", opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.3 }}
                        className="overflow-hidden"
                    >
                        <div className="space-y-1 border-t px-4 py-3">
                            {checklist.map((item, index) => {
                                const isCompleted = progress.checklistState[item.id] ?? false;

                                return (
                                    <motion.div
                                        key={item.id}
                                        initial={{ opacity: 0, x: -10 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        transition={{ delay: index * 0.1 }}
                                        className={cn(
                                            "flex items-start gap-3 rounded-lg p-2 transition-colors",
                                            isCompleted ? "bg-muted/30" : "hover:bg-muted/50"
                                        )}
                                    >
                                        <button
                                            onClick={() => toggleChecklistItem(item.id)}
                                            className={cn(
                                                "mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition-colors",
                                                isCompleted
                                                    ? "bg-primary border-primary"
                                                    : "border-muted-foreground/30 hover:border-primary/50"
                                            )}
                                        >
                                            <AnimatePresence>
                                                {isCompleted && (
                                                    <motion.div
                                                        initial={{ scale: 0 }}
                                                        animate={{ scale: 1 }}
                                                        exit={{ scale: 0 }}
                                                    >
                                                        <Check className="h-3 w-3 text-white" strokeWidth={3} />
                                                    </motion.div>
                                                )}
                                            </AnimatePresence>
                                        </button>

                                        <div className="min-w-0 flex-1">
                                            <p
                                                className={cn(
                                                    "text-sm font-medium",
                                                    isCompleted && "text-muted-foreground line-through"
                                                )}
                                            >
                                                {item.label}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{item.description}</p>

                                            {item.actionRoute && !isCompleted && (
                                                <Button
                                                    variant="link"
                                                    size="sm"
                                                    className="h-auto p-0 text-xs"
                                                    asChild
                                                >
                                                    <a href={item.actionRoute}>{item.actionLabel ?? "Go"}</a>
                                                </Button>
                                            )}
                                        </div>
                                    </motion.div>
                                );
                            })}
                        </div>

                        {/* Footer */}
                        <div className="border-t px-4 py-3">
                            <Button variant="outline" size="sm" className="w-full text-xs" onClick={openOnboarding}>
                                <Sparkles className="mr-1.5 h-3.5 w-3.5" />
                                Take the full tour
                            </Button>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </motion.div>
    );
}
