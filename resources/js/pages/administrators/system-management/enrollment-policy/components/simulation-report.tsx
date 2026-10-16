import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertTriangle, ArrowRight, BellRing, BookOpenCheck, CheckCircle2, CircleDashed, Coins, Layers3, Route, ShieldCheck } from "lucide-react";
import type { ReactNode } from "react";
import type { BlueprintStepId, Simulation } from "../types";

export function SimulationReport({ result, onFix }: { result: Simulation | null; onFix: (section: BlueprintStepId) => void }) {
    if (!result) {
        return (
            <Card className="bg-muted/10 border-dashed">
                <CardContent className="flex min-h-80 flex-col items-center justify-center px-6 text-center">
                    <div className="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-2xl">
                        <Route className="size-6" />
                    </div>
                    <h3 className="mt-4 font-semibold">Student journey preview</h3>
                    <p className="text-muted-foreground mt-2 max-w-sm text-sm leading-6 text-pretty">
                        Choose a representative student and run a simulation. Nothing is written to student records.
                    </p>
                </CardContent>
            </Card>
        );
    }

    if (result.error) {
        return (
            <Card className="border-destructive/40">
                <CardHeader>
                    <CardTitle className="text-destructive flex items-center gap-2">
                        <AlertTriangle className="size-5" /> Simulation could not finish
                    </CardTitle>
                    <CardDescription>Review the sample student fields and save the draft before trying again.</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const blockers = result.blockers ?? [];
    const passed = (result.eligibility ?? []).filter((check) => check.passed);
    const route = result.workflow_route ?? [];

    return (
        <Card className={blockers.length ? "border-amber-500/35" : "border-emerald-500/35"}>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            {blockers.length ? (
                                <AlertTriangle className="size-5 text-amber-600 dark:text-amber-400" />
                            ) : (
                                <ShieldCheck className="size-5 text-emerald-600 dark:text-emerald-400" />
                            )}
                            {blockers.length ? `${blockers.length} blocker${blockers.length === 1 ? "" : "s"} to fix` : "Policy checks passed"}
                        </CardTitle>
                        <CardDescription className="mt-1">
                            Preview checksum {result.checksum?.slice(0, 10) ?? "not available"} · no student data changed
                        </CardDescription>
                    </div>
                    <Badge variant={blockers.length ? "secondary" : "default"}>{blockers.length ? "Needs attention" : "Successful"}</Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                <p className="text-muted-foreground text-sm">
                    Dry run only. No actions, payments, class assignments, or emails were executed. {result.route_basis}
                </p>
                <JourneySection icon={Layers3} title="Matched blueprints">
                    <div className="flex flex-wrap gap-2">
                        {(result.matched_policies ?? []).map((policy) => (
                            <Badge key={policy.version_id} variant="outline" className="h-8 gap-1.5 px-3 font-normal">
                                {policy.policy_name ?? "Enrollment policy"} · v{policy.version ?? policy.version_id}
                            </Badge>
                        ))}
                        {!result.matched_policies?.length ? (
                            <span className="text-muted-foreground text-sm">No policy provenance reported.</span>
                        ) : null}
                    </div>
                </JourneySection>

                {blockers.length ? (
                    <JourneySection icon={AlertTriangle} title="What stops this student">
                        <div className="space-y-2">
                            {blockers.map((blocker) => (
                                <div key={blocker.key} className="flex flex-col gap-3 rounded-xl bg-amber-500/8 p-3 sm:flex-row sm:items-center">
                                    <AlertTriangle className="size-4 shrink-0 text-amber-700 dark:text-amber-300" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">{blocker.label ?? blocker.key.replaceAll("_", " ")}</p>
                                        {blocker.message ? <p className="text-muted-foreground mt-0.5 text-xs">{blocker.message}</p> : null}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-10 shrink-0"
                                        onClick={() => onFix(blocker.section ?? "eligibility")}
                                    >
                                        Review setting <ArrowRight className="size-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </JourneySection>
                ) : null}

                <JourneySection icon={CheckCircle2} title={`${passed.length} check${passed.length === 1 ? "" : "s"} passed`}>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {passed.map((check) => (
                            <div key={check.key} className="flex items-start gap-2 rounded-lg border p-3 text-sm">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <span>{check.label ?? check.key.replaceAll("_", " ")}</span>
                            </div>
                        ))}
                    </div>
                </JourneySection>

                <div className="grid gap-3 sm:grid-cols-3">
                    <SummaryCard
                        icon={BookOpenCheck}
                        label="Subjects and classes"
                        value={friendlyValue(result.assignment?.strategy, "Manual selection")}
                    />
                    <SummaryCard icon={Coins} label="Fee estimate" value={friendlyValue(result.billing?.strategy, "Not calculated")} />
                    <SummaryCard icon={BellRing} label="Messages" value={`${result.notifications?.length ?? 0} configured`} />
                </div>

                <JourneySection icon={Route} title="Expected approval route">
                    <ol className="relative space-y-0">
                        {route.map((step, index) => (
                            <li key={step.key} className="flex min-h-12 items-start gap-3">
                                <span className="bg-primary/10 text-primary relative z-10 flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-semibold">
                                    {index + 1}
                                </span>
                                <div className="-ml-[1.65rem] min-w-0 flex-1 border-l pb-4 pl-4">
                                    <p className="ml-6 text-sm font-medium">{step.label}</p>
                                    {step.terminal ? <p className="text-muted-foreground mt-0.5 ml-6 text-xs">Final outcome</p> : null}
                                </div>
                            </li>
                        ))}
                        {route.length === 0 ? (
                            <li className="text-muted-foreground flex items-center gap-2 text-sm">
                                <CircleDashed className="size-4" /> The workflow route could not be determined for this sample.
                            </li>
                        ) : null}
                    </ol>
                </JourneySection>
            </CardContent>
        </Card>
    );
}

function JourneySection({ icon: Icon, title, children }: { icon: typeof Layers3; title: string; children: ReactNode }) {
    return (
        <section>
            <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                <Icon className="text-primary size-4" /> {title}
            </h4>
            {children}
        </section>
    );
}

function SummaryCard({ icon: Icon, label, value }: { icon: typeof Layers3; label: string; value: string }) {
    return (
        <div className="bg-muted/20 rounded-xl border p-4">
            <Icon className="text-primary size-4" />
            <p className="text-muted-foreground mt-3 text-xs">{label}</p>
            <p className="mt-1 truncate text-sm font-semibold" title={value}>
                {value}
            </p>
        </div>
    );
}

function friendlyValue(value: unknown, fallback: string): string {
    if (!value) return fallback;
    return String(value).split(".").at(-1)?.replaceAll("_", " ") ?? fallback;
}
