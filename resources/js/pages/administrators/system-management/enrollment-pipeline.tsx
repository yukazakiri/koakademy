import { deactivate } from "@/actions/App/Http/Controllers/AdministratorEnrollmentPolicyController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock,
    FileCode2,
    FileSpreadsheet,
    Layers,
    Layers3,
    ListChecks,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Users,
    Workflow,
    X,
} from "lucide-react";
import { useMemo, useState } from "react";

import { BlueprintShell } from "./enrollment-policy/components/blueprint-shell";
import { CreatePolicyDialog } from "./enrollment-policy/components/create-policy-dialog";
import { PolicyHelpDrawer } from "./enrollment-policy/components/help-drawer";
import {
    AssignmentSection,
    BillingSection,
    DocumentsSection,
    EligibilitySection,
    PublishSection,
    ScopeSection,
    WorkflowSection,
} from "./enrollment-policy/components/sections";
import { blueprintSteps } from "./enrollment-policy/configuration";
import type { BlueprintStepId, EnrollmentPolicyPageProps, Policy } from "./enrollment-policy/types";
import { usePolicyEditor } from "./enrollment-policy/use-policy-editor";
import SystemManagementLayout from "./layout";

export default function EnrollmentBlueprintWorkspace({
    user,
    access,
    enrollment_policies,
    enrollment_registry,
    enrollment_rollout,
    enrollment_presets,
    enrollment_operator_options,
    has_global_published_policy,
    enrollment_documentation_url,
}: EnrollmentPolicyPageProps) {
    const canUpdate = access.sections.pipeline?.can_update ?? false;
    const editor = usePolicyEditor(enrollment_policies);
    const current = blueprintSteps.find((step) => step.id === editor.currentStep) ?? blueprintSteps[0];
    const [policySearch, setPolicySearch] = useState("");

    const filteredPolicies = useMemo(() => {
        const q = policySearch.trim().toLowerCase();
        if (!q) return enrollment_policies;
        return enrollment_policies.filter(
            (p) => p.name.toLowerCase().includes(q) || Object.values(p.scope).some((v) => String(v).toLowerCase().includes(q)),
        );
    }, [enrollment_policies, policySearch]);

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="pipeline"
            heading="Admissions & Enrollment Engine"
            description="Architect, simulate, and publish structured applicant journeys, academic clearance, and tuition billing blueprints."
        >
            <div className="space-y-6">
                {/* Engine Rollout Status Alert */}
                <RolloutBanner rollout={enrollment_rollout} canUpdate={canUpdate} />

                {/* Telemetry Readiness Overview */}
                <OverviewDashboard
                    policies={enrollment_policies}
                    rollout={enrollment_rollout}
                    completedSteps={editor.completedSteps.length}
                    selectedPolicy={editor.policy}
                    onRecommendedAction={() => editor.setCurrentStep(recommendedStep(editor.completedSteps))}
                />

                {/* Master Detail Workspace */}
                <div className="grid items-start gap-5 2xl:grid-cols-[18.5rem_minmax(0,1fr)]">
                    {/* Left Blueprint Selector Rail */}
                    <aside className="space-y-4 2xl:sticky 2xl:top-4">
                        <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                            <CardHeader className="p-4 pb-3 border-b border-border/40">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <Layers className="size-4 text-primary" />
                                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-foreground">
                                            Policy Blueprints
                                        </CardTitle>
                                    </div>
                                    <Badge variant="outline" className="font-mono text-[10px] px-1.5 h-4.5 border-border/60">
                                        {enrollment_policies.length}
                                    </Badge>
                                </div>
                            </CardHeader>

                            <CardContent className="p-3 space-y-2">
                                {enrollment_policies.length > 3 && (
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={policySearch}
                                            onChange={(e) => setPolicySearch(e.target.value)}
                                            placeholder="Filter blueprints..."
                                            className="h-8 pl-8 text-xs bg-background/70"
                                        />
                                    </div>
                                )}

                                <div className="space-y-1.5 max-h-[360px] overflow-y-auto pr-0.5">
                                    {filteredPolicies.map((policy) => (
                                        <PolicyPicker
                                            key={policy.id}
                                            policy={policy}
                                            selected={editor.policy?.id === policy.id}
                                            onSelect={() => editor.setPolicyId(policy.id)}
                                        />
                                    ))}
                                    {filteredPolicies.length === 0 && (
                                        <div className="text-center py-6 text-xs text-muted-foreground">
                                            No matching blueprints found.
                                        </div>
                                    )}
                                </div>

                                <div className="pt-2 border-t border-border/40">
                                    <CreatePolicyDialog
                                        presets={enrollment_presets}
                                        options={enrollment_operator_options}
                                        hasPublishedGlobalPolicy={has_global_published_policy}
                                        canUpdate={canUpdate}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Safe by Default Notice */}
                        <div className="rounded-xl border border-border/50 bg-muted/20 p-3.5 space-y-1.5">
                            <p className="flex items-center gap-2 text-xs font-semibold text-foreground">
                                <ShieldCheck className="size-4 text-emerald-500" />
                                <span>Zero-Risk Simulation</span>
                            </p>
                            <p className="text-[11px] text-muted-foreground leading-relaxed">
                                Drafts and simulation tests never mutate active student records. Published rules apply only to future enrollments.
                            </p>
                        </div>
                    </aside>

                    {/* Right Blueprint Studio Workspace */}
                    <main className="min-w-0">
                        {!editor.policy || !editor.version ? (
                            <EmptyWorkspace onCreate={() => undefined} />
                        ) : (
                            <div className="space-y-4">
                                {/* Blueprint Header Status Strip */}
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-border/60 bg-card/75 p-4 shadow-xs backdrop-blur-xs">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-base font-bold text-foreground truncate">{editor.policy.name}</h2>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    "text-[10px] font-mono px-2 h-5",
                                                    editor.version.state === "draft"
                                                        ? "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                        : "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                                                )}
                                            >
                                                v{editor.version.version} • {editor.version.state.toUpperCase()}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-0.5">
                                            Stage {blueprintSteps.findIndex((step) => step.id === editor.currentStep) + 1} of{" "}
                                            {blueprintSteps.length}: <span className="font-semibold text-foreground/80">{current.title}</span>
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2 self-start sm:self-center">
                                        <div className="flex items-center gap-1.5 rounded-lg border border-border/50 bg-background/50 px-2.5 py-1 text-xs text-muted-foreground">
                                            <span
                                                className={cn(
                                                    "size-2 rounded-full",
                                                    editor.dirty ? "bg-amber-500 animate-pulse" : "bg-emerald-500",
                                                )}
                                            />
                                            <span className="font-medium text-[11px]">
                                                {editor.dirty ? "Unsaved edits" : "Draft synced"}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <BlueprintShell
                                    currentStep={editor.currentStep}
                                    completedSteps={editor.completedSteps}
                                    dirty={editor.dirty}
                                    saving={editor.saving}
                                    canUpdate={canUpdate}
                                    onStepChange={editor.setCurrentStep}
                                    onSave={editor.save}
                                >
                                    {editor.currentStep === "scope" && (
                                        <ScopeSection
                                            policy={editor.policy}
                                            version={editor.version}
                                            inheritance={editor.inheritanceData}
                                            loading={editor.loadingInheritance}
                                            documentationUrl={enrollment_documentation_url}
                                        />
                                    )}
                                    {editor.currentStep === "eligibility" && (
                                        <EligibilitySection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    )}
                                    {editor.currentStep === "documents" && (
                                        <DocumentsSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    )}
                                    {editor.currentStep === "assignment" && (
                                        <AssignmentSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    )}
                                    {editor.currentStep === "billing" && (
                                        <BillingSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    )}
                                    {editor.currentStep === "workflow" && (
                                        <WorkflowSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                        />
                                    )}
                                    {editor.currentStep === "publish" && (
                                        <PublishSection
                                            key={`${editor.policy.id}-${editor.version.id}`}
                                            policy={editor.policy}
                                            version={editor.version}
                                            options={enrollment_operator_options}
                                            rollout={enrollment_rollout}
                                            dirty={editor.dirty}
                                            notes={editor.notes}
                                            setNotes={editor.setNotes}
                                            simulationResult={editor.simulation}
                                            setSimulationResult={editor.setSimulation}
                                            canUpdate={canUpdate}
                                            onFix={editor.setCurrentStep}
                                        />
                                    )}
                                </BlueprintShell>
                            </div>
                        )}
                    </main>
                </div>
            </div>

            <PolicyHelpDrawer
                topic={editor.helpTopic}
                open={editor.helpOpen}
                onOpenChange={editor.setHelpOpen}
                documentationUrl={enrollment_documentation_url}
            />
        </SystemManagementLayout>
    );
}

function RolloutBanner({ rollout, canUpdate }: { rollout: EnrollmentPolicyPageProps["enrollment_rollout"]; canUpdate: boolean }) {
    const active = rollout.state === "active";
    const ready = rollout.state === "ready";
    const label = active ? "Policy engine active" : ready ? "Ready to activate" : "Legacy enrollment active";

    return (
        <Alert
            className={cn(
                "rounded-xl border shadow-xs p-4",
                active
                    ? "border-emerald-500/30 bg-emerald-500/5 text-emerald-950 dark:text-emerald-200"
                    : ready
                      ? "border-sky-500/30 bg-sky-500/5 text-sky-950 dark:text-sky-200"
                      : "border-amber-500/30 bg-amber-500/5 text-amber-950 dark:text-amber-200",
            )}
        >
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div className="flex items-start gap-3">
                    <ShieldCheck className="size-4.5 mt-0.5 text-primary shrink-0" />
                    <div>
                        <AlertTitle className="text-sm font-semibold flex items-center gap-2">
                            <span>{label}</span>
                            <Badge variant="outline" className="font-mono text-[10px] h-4.5 border-border/60">
                                {rollout.policy_enrollments} policy records
                            </Badge>
                            <Badge variant="outline" className="font-mono text-[10px] h-4.5 border-border/60">
                                {rollout.legacy_enrollments} legacy records
                            </Badge>
                        </AlertTitle>
                        <AlertDescription className="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                            {active
                                ? "New admissions follow matching blueprints. Prior enrollments remain locked to their pinned policy snapshot."
                                : "Authoritative workflow is active. Simulate your blueprint, verify calculations, then switch live mode."}
                        </AlertDescription>
                    </div>
                </div>

                {active && canUpdate && (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button size="sm" variant="outline" className="h-8 text-xs shrink-0 self-start sm:self-center bg-background">
                                Revert Future Enrollments to Legacy
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle className="text-base font-semibold">Revert future admissions to legacy?</AlertDialogTitle>
                                <AlertDialogDescription className="text-xs">
                                    This applies solely to new registrations submitted after deactivation. Already enrolled students keep their
                                    pinned policy snapshot.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel className="text-xs h-8">Keep Policies Active</AlertDialogCancel>
                                <AlertDialogAction
                                    className="text-xs h-8"
                                    onClick={() => router.post(deactivate.url(), { confirmation: "return new enrollments to legacy" })}
                                >
                                    Revert to Legacy
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}
            </div>
        </Alert>
    );
}

function OverviewDashboard({
    policies,
    rollout,
    completedSteps,
    selectedPolicy,
    onRecommendedAction,
}: {
    policies: Policy[];
    rollout: EnrollmentPolicyPageProps["enrollment_rollout"];
    completedSteps: number;
    selectedPolicy: Policy | null;
    onRecommendedAction: () => void;
}) {
    const warningCount = rollout.errors.length + rollout.migration_warnings;
    const remaining = Math.max(0, blueprintSteps.length - completedSteps);
    const nextAction = !selectedPolicy
        ? "Create global blueprint"
        : completedSteps < blueprintSteps.length - 1
          ? "Continue guided setup"
          : rollout.active
            ? "Inspect next policy change"
            : "Test blueprint & activate";

    return (
        <section aria-labelledby="workspace-overview" className="space-y-3">
            <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Admissions Pipeline Telemetry
                    </span>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    className="hidden sm:inline-flex h-8 gap-1 text-xs text-primary hover:text-primary"
                    onClick={onRecommendedAction}
                >
                    <span>{nextAction}</span>
                    <ArrowRight className="size-3.5" />
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <OverviewCard
                    icon={ShieldCheck}
                    label="Engine State"
                    value={rollout.active ? "Engine active" : rollout.ready ? "Ready to activate" : "Legacy active"}
                    tone={rollout.active ? "emerald" : "amber"}
                />
                <OverviewCard
                    icon={Layers3}
                    label="Active Coverage"
                    value={`${policies.length} blueprint${policies.length === 1 ? "" : "s"} defined`}
                />
                <OverviewCard
                    icon={ListChecks}
                    label="Stage Progression"
                    value={`${remaining} stage${remaining === 1 ? "" : "s"} remaining`}
                />
                <OverviewCard
                    icon={warningCount ? AlertTriangle : CheckCircle2}
                    label="Health Diagnostics"
                    value={warningCount ? `${warningCount} warning${warningCount === 1 ? "" : "s"}` : "Clean (No warnings)"}
                    tone={warningCount ? "amber" : "emerald"}
                />
            </div>
        </section>
    );
}

function OverviewCard({
    icon: Icon,
    label,
    value,
    tone = "default",
}: {
    icon: typeof ShieldCheck;
    label: string;
    value: string;
    tone?: "default" | "amber" | "emerald";
}) {
    return (
        <Card className="border-border/60 bg-card/70 shadow-xs p-3.5 flex items-start gap-3">
            <div
                className={cn(
                    "flex size-9 shrink-0 items-center justify-center rounded-xl",
                    tone === "amber" && "bg-amber-500/10 text-amber-600 dark:text-amber-400",
                    tone === "emerald" && "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
                    tone === "default" && "bg-primary/10 text-primary",
                )}
            >
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <p className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">{label}</p>
                <p className="mt-0.5 text-xs font-semibold text-foreground truncate">{value}</p>
            </div>
        </Card>
    );
}

function PolicyPicker({ policy, selected, onSelect }: { policy: Policy; selected: boolean; onSelect: () => void }) {
    const scopeValues = Object.values(policy.scope);
    const draft = policy.versions.some((version) => version.state === "draft");

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                "w-full rounded-xl border p-2.5 text-left transition-all outline-none",
                selected
                    ? "border-primary bg-primary/5 shadow-xs ring-1 ring-primary/20"
                    : "border-border/50 hover:bg-muted/50 hover:border-border",
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <span className="line-clamp-1 text-xs font-semibold text-foreground">{policy.name}</span>
                {selected && <CheckCircle2 className="size-3.5 text-primary shrink-0 mt-0.5" />}
            </div>

            <div className="mt-1.5 flex flex-wrap gap-1">
                <Badge variant="outline" className="text-[9.5px] px-1 h-4 font-mono border-border/50">
                    {scopeValues.length ? scopeValues.slice(0, 2).join(" · ") : "Global"}
                </Badge>
                <Badge
                    variant="outline"
                    className={cn(
                        "text-[9.5px] px-1 h-4",
                        draft
                            ? "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                            : policy.active_version_id
                              ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                              : "border-border/60 text-muted-foreground",
                    )}
                >
                    {draft ? "Draft" : policy.active_version_id ? "Published" : "Setup"}
                </Badge>
            </div>
        </button>
    );
}

function EmptyWorkspace({ onCreate }: { onCreate: () => void }) {
    return (
        <Card className="border-dashed border-border/70 bg-card/40">
            <CardContent className="flex min-h-[28rem] flex-col items-center justify-center p-8 text-center">
                <div className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Sparkles className="size-6" />
                </div>
                <h2 className="mt-4 text-base font-semibold text-foreground">Create your first admissions blueprint</h2>
                <p className="mt-1.5 max-w-md text-xs text-muted-foreground leading-relaxed">
                    Choose a template, define coverage scope, and step through the 7 guided stages. Your live admission workflow continues uninterrupted until published.
                </p>
                <div className="mt-4 flex items-center gap-2 text-xs text-muted-foreground font-medium">
                    <Users className="size-3.5" />
                    <span>Visual administrator studio — zero code or raw schema editing</span>
                </div>
            </CardContent>
        </Card>
    );
}

function recommendedStep(completed: string[]): BlueprintStepId {
    return blueprintSteps.find((step) => !completed.includes(step.id))?.id ?? "publish";
}
