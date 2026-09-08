import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { Check, ChevronLeft, ChevronRight, CircleDashed, Loader2, Save } from "lucide-react";
import type { ReactNode } from "react";
import { blueprintSteps } from "../configuration";
import type { BlueprintStepId } from "../types";

export function BlueprintShell({
    currentStep,
    completedSteps,
    dirty,
    saving,
    canUpdate,
    onStepChange,
    onSave,
    children,
}: {
    currentStep: BlueprintStepId;
    completedSteps: BlueprintStepId[];
    dirty: boolean;
    saving: boolean;
    canUpdate: boolean;
    onStepChange: (step: BlueprintStepId) => void;
    onSave: (continueToNext?: boolean) => void;
    children: ReactNode;
}) {
    const currentIndex = blueprintSteps.findIndex((step) => step.id === currentStep);
    const progress = Math.round((completedSteps.length / blueprintSteps.length) * 100);
    const previous = blueprintSteps[currentIndex - 1];
    const next = blueprintSteps[currentIndex + 1];

    return (
        <section className="overflow-hidden rounded-2xl border border-border/60 bg-card/75 shadow-xs backdrop-blur-xs">
            <div className="grid min-h-[680px] xl:grid-cols-[16rem_minmax(0,1fr)]">
                {/* Stepper Rail */}
                <aside className="border-b border-border/50 bg-muted/20 p-4 xl:border-r xl:border-b-0 xl:p-5">
                    <div className="mb-4 space-y-2">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-semibold uppercase tracking-wider text-muted-foreground text-[10px]">
                                Blueprint Progress
                            </span>
                            <span className="font-mono font-semibold text-foreground text-xs">{progress}%</span>
                        </div>
                        <Progress value={progress} className="h-1.5 bg-muted/60" />
                    </div>

                    {/* Desktop Vertical Stepper / Mobile Horizontal Track */}
                    <nav
                        aria-label="Enrollment policy workflow stages"
                        className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-2 xl:block xl:space-y-1.5 xl:overflow-visible xl:pb-0 scrollbar-none"
                    >
                        {blueprintSteps.map((step, index) => {
                            const isCurrent = step.id === currentStep;
                            const isComplete = completedSteps.includes(step.id);

                            return (
                                <button
                                    type="button"
                                    key={step.id}
                                    onClick={() => onStepChange(step.id)}
                                    aria-current={isCurrent ? "step" : undefined}
                                    className={cn(
                                        "group flex min-h-11 min-w-[12.5rem] items-start gap-3 rounded-xl p-2.5 text-left transition-all outline-none xl:w-full xl:min-w-0",
                                        isCurrent
                                            ? "border border-border/80 bg-background text-foreground shadow-xs font-medium"
                                            : "border border-transparent text-muted-foreground hover:bg-background/60 hover:text-foreground",
                                    )}
                                >
                                    <span
                                        className={cn(
                                            "mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg text-xs font-semibold transition-colors",
                                            isCurrent && "bg-primary text-primary-foreground shadow-xs",
                                            !isCurrent && isComplete && "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
                                            !isCurrent && !isComplete && "bg-muted/80 text-muted-foreground",
                                        )}
                                    >
                                        {isComplete ? <Check className="size-3.5 stroke-[2.5]" /> : index + 1}
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-1">
                                            <span className="block text-xs font-semibold tracking-tight text-foreground truncate">
                                                {step.shortTitle}
                                            </span>
                                            {isCurrent && (
                                                <span className="size-1.5 rounded-full bg-primary shrink-0" />
                                            )}
                                        </div>
                                        <span className="mt-0.5 hidden text-[11px] leading-relaxed text-muted-foreground line-clamp-1 xl:block">
                                            {step.description}
                                        </span>
                                    </div>
                                </button>
                            );
                        })}
                    </nav>
                </aside>

                {/* Content Workspace Area */}
                <div className="flex min-w-0 flex-col justify-between">
                    <div className="flex-1 p-4 sm:p-6 lg:p-7">{children}</div>

                    {/* Sticky Action Footer */}
                    <footer className="sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 border-t border-border/50 bg-card/90 px-4 py-3.5 backdrop-blur-md sm:px-6">
                        <div className="flex items-center gap-2">
                            {previous && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8.5 gap-1.5 text-xs bg-background"
                                    onClick={() => onStepChange(previous.id)}
                                >
                                    <ChevronLeft className="size-3.5" />
                                    <span>Back</span>
                                </Button>
                            )}

                            <Badge
                                variant="outline"
                                className={cn(
                                    "h-7 gap-1.5 px-2 text-[11px] font-normal border-border/60",
                                    dirty ? "bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30" : "bg-muted/40 text-muted-foreground",
                                )}
                            >
                                <span className={cn("size-1.5 rounded-full", dirty ? "bg-amber-500" : "bg-emerald-500")} />
                                <span>{dirty ? "Unsaved changes" : "Draft saved"}</span>
                            </Badge>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8.5 gap-1.5 text-xs bg-background"
                                disabled={!canUpdate || saving || !dirty}
                                onClick={() => onSave(false)}
                            >
                                {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                <span>{saving ? "Saving..." : "Save Draft"}</span>
                            </Button>

                            {next && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-8.5 gap-1.5 text-xs shadow-xs"
                                    disabled={!canUpdate || saving}
                                    onClick={() => onSave(true)}
                                >
                                    <span>Save & Continue</span>
                                    <ChevronRight className="size-3.5" />
                                </Button>
                            )}
                        </div>
                    </footer>
                </div>
            </div>
        </section>
    );
}
