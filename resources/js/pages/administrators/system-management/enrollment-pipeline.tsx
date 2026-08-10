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
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { AlertTriangle, ArrowRight, CheckCircle2, Layers3, ListChecks, ShieldCheck, Sparkles, Users } from "lucide-react";
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

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="pipeline"
            heading="Admissions & Enrollment"
            description="Design, test, and publish the enrollment journey with safe, future-only changes."
        >
            <div className="space-y-6">
                <RolloutBanner rollout={enrollment_rollout} canUpdate={canUpdate} />

                <OverviewDashboard
                    policies={enrollment_policies}
                    rollout={enrollment_rollout}
                    completedSteps={editor.completedSteps.length}
                    selectedPolicy={editor.policy}
                    onRecommendedAction={() => editor.setCurrentStep(recommendedStep(editor.completedSteps))}
                />

                <div className="grid items-start gap-5 2xl:grid-cols-[19rem_minmax(0,1fr)]">
                    <aside className="space-y-4 2xl:sticky 2xl:top-4">
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-base">Blueprints</CardTitle>
                                        <CardDescription className="mt-1">Choose what you want to configure.</CardDescription>
                                    </div>
                                    <Badge variant="secondary">{enrollment_policies.length}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {enrollment_policies.map((policy) => (
                                    <PolicyPicker
                                        key={policy.id}
                                        policy={policy}
                                        selected={editor.policy?.id === policy.id}
                                        onSelect={() => editor.setPolicyId(policy.id)}
                                    />
                                ))}
                                {enrollment_policies.length === 0 ? (
                                    <div className="text-muted-foreground rounded-xl border border-dashed p-4 text-sm leading-6">
                                        Start with a global blueprint. Legacy enrollment remains active until you explicitly activate policies.
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>

                        <CreatePolicyDialog
                            presets={enrollment_presets}
                            options={enrollment_operator_options}
                            hasPublishedGlobalPolicy={has_global_published_policy}
                            canUpdate={canUpdate}
                        />

                        <div className="bg-muted/25 rounded-xl border p-4">
                            <p className="flex items-center gap-2 text-sm font-semibold">
                                <ShieldCheck className="text-primary size-4" /> Safe by default
                            </p>
                            <p className="text-muted-foreground mt-2 text-xs leading-5 text-pretty">
                                Drafts and simulations do not change student records. Published changes apply only to future matching enrollments.
                            </p>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        {!editor.policy || !editor.version ? (
                            <EmptyWorkspace onCreate={() => undefined} />
                        ) : (
                            <div className="space-y-4">
                                <div className="bg-card flex flex-col gap-3 rounded-2xl border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="truncate text-lg font-semibold">{editor.policy.name}</h2>
                                            <Badge variant={editor.version.state === "draft" ? "secondary" : "default"}>
                                                Version {editor.version.version} · {editor.version.state}
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Stage {blueprintSteps.findIndex((step) => step.id === editor.currentStep) + 1} of {blueprintSteps.length}:{" "}
                                            {current.title}
                                        </p>
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <span className={cn("size-2 rounded-full", editor.dirty ? "bg-amber-500" : "bg-emerald-500")} />
                                        {editor.dirty ? "Unsaved changes" : "Latest draft saved"}
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
                                    {editor.currentStep === "scope" ? (
                                        <ScopeSection
                                            policy={editor.policy}
                                            version={editor.version}
                                            inheritance={editor.inheritanceData}
                                            loading={editor.loadingInheritance}
                                            documentationUrl={enrollment_documentation_url}
                                        />
                                    ) : null}
                                    {editor.currentStep === "eligibility" ? (
                                        <EligibilitySection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    ) : null}
                                    {editor.currentStep === "documents" ? (
                                        <DocumentsSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    ) : null}
                                    {editor.currentStep === "assignment" ? (
                                        <AssignmentSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    ) : null}
                                    {editor.currentStep === "billing" ? (
                                        <BillingSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                            onHelp={editor.openHelp}
                                        />
                                    ) : null}
                                    {editor.currentStep === "workflow" ? (
                                        <WorkflowSection
                                            effective={editor.effectiveConfiguration}
                                            local={editor.localConfiguration}
                                            registry={enrollment_registry}
                                            options={enrollment_operator_options}
                                            inheritance={editor.inheritanceData}
                                            onChange={editor.updateConfiguration}
                                        />
                                    ) : null}
                                    {editor.currentStep === "publish" ? (
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
                                    ) : null}
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
        <Alert className={cn("rounded-2xl", active ? "border-emerald-500/30 bg-emerald-500/5" : "border-amber-500/30 bg-amber-500/5")}>
            <ShieldCheck className="size-4" />
            <AlertTitle className="flex flex-wrap items-center gap-2">
                {label}
                <Badge variant="outline">{rollout.legacy_enrollments} legacy</Badge>
                <Badge variant="outline">{rollout.policy_enrollments} policy</Badge>
            </AlertTitle>
            <AlertDescription className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <span className="max-w-3xl">
                    {active
                        ? "New enrollments use published blueprints. Existing enrollments remain pinned to their original runtime and policy snapshot."
                        : "Your deployed workflow remains authoritative. Review the compatibility report, simulate a global blueprint, then activate explicitly."}
                </span>
                {active && canUpdate ? (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button size="sm" variant="outline" className="h-10 shrink-0">
                                Use legacy for future enrollments
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Return future enrollments to legacy?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This affects only enrollments created after deactivation. Existing policy enrollments remain pinned and
                                    operational.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Keep policies active</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() => router.post(deactivate.url(), { confirmation: "return new enrollments to legacy" })}
                                >
                                    Use legacy for future enrollments
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                ) : null}
            </AlertDescription>
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
        ? "Create your global blueprint"
        : completedSteps < blueprintSteps.length - 1
          ? "Continue the guided setup"
          : rollout.active
            ? "Review your next policy change"
            : "Test the blueprint and prepare activation";

    return (
        <section aria-labelledby="workspace-overview" className="space-y-3">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-primary text-xs font-semibold tracking-wide uppercase">Workspace overview</p>
                    <h2 id="workspace-overview" className="mt-1 text-xl font-semibold tracking-tight">
                        Enrollment readiness at a glance
                    </h2>
                </div>
                <Button variant="ghost" className="hidden h-10 sm:inline-flex" onClick={onRecommendedAction}>
                    {nextAction} <ArrowRight className="size-4" />
                </Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <OverviewCard
                    icon={ShieldCheck}
                    label="Rollout status"
                    value={rollout.active ? "Policy engine active" : rollout.ready ? "Ready to activate" : "Legacy active"}
                    tone={rollout.active ? "emerald" : "amber"}
                />
                <OverviewCard icon={Layers3} label="Policy coverage" value={`${policies.length} blueprint${policies.length === 1 ? "" : "s"}`} />
                <OverviewCard icon={ListChecks} label="Setup progress" value={`${remaining} stage${remaining === 1 ? "" : "s"} remaining`} />
                <OverviewCard
                    icon={warningCount ? AlertTriangle : CheckCircle2}
                    label="Needs attention"
                    value={warningCount ? `${warningCount} warning${warningCount === 1 ? "" : "s"}` : "No rollout warnings"}
                    tone={warningCount ? "amber" : "emerald"}
                />
            </div>
            <Button variant="outline" className="h-11 w-full justify-between sm:hidden" onClick={onRecommendedAction}>
                {nextAction} <ArrowRight className="size-4" />
            </Button>
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
        <Card className="shadow-none">
            <CardContent className="flex min-h-24 items-start gap-3 p-4">
                <div
                    className={cn(
                        "bg-primary/8 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl",
                        tone === "amber" && "bg-amber-500/10 text-amber-700 dark:text-amber-300",
                        tone === "emerald" && "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                    )}
                >
                    <Icon className="size-4" />
                </div>
                <div className="min-w-0">
                    <p className="text-muted-foreground text-xs">{label}</p>
                    <p className="mt-1 text-sm leading-5 font-semibold text-pretty">{value}</p>
                </div>
            </CardContent>
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
                "w-full rounded-xl border p-3 text-left transition-[border-color,background-color,box-shadow,scale] duration-150 active:scale-[0.98] motion-reduce:transition-none",
                selected ? "border-primary bg-primary/5 shadow-sm" : "hover:border-primary/35 hover:bg-muted/40",
            )}
        >
            <span className="flex items-start justify-between gap-2">
                <span className="line-clamp-2 text-sm font-semibold">{policy.name}</span>
                {selected ? <CheckCircle2 className="text-primary size-4 shrink-0" /> : null}
            </span>
            <span className="mt-2 flex flex-wrap gap-1">
                <Badge variant="outline" className="text-[10px] font-normal">
                    {scopeValues.length ? scopeValues.slice(0, 2).join(" · ") : "Global"}
                </Badge>
                <Badge variant={draft ? "secondary" : policy.active_version_id ? "default" : "outline"} className="text-[10px]">
                    {draft ? "Draft" : policy.active_version_id ? "Published" : "Setup"}
                </Badge>
            </span>
        </button>
    );
}

function EmptyWorkspace({ onCreate }: { onCreate: () => void }) {
    return (
        <Card className="border-dashed">
            <CardContent className="flex min-h-[34rem] flex-col items-center justify-center px-6 text-center">
                <div className="bg-primary/10 text-primary flex size-14 items-center justify-center rounded-2xl">
                    <Sparkles className="size-7" />
                </div>
                <h2 className="mt-5 text-xl font-semibold">Build your first enrollment blueprint</h2>
                <p className="text-muted-foreground mt-2 max-w-md text-sm leading-6 text-pretty">
                    Choose a template, confirm who it covers, and follow seven guided stages. Your live enrollment process stays unchanged until
                    activation.
                </p>
                <div className="text-muted-foreground mt-6 flex items-center gap-2 text-sm">
                    <Users className="size-4" /> Designed for school administrators—no JSON or code required
                </div>
                <Button className="mt-6 h-11" onClick={onCreate} disabled>
                    Use “New blueprint” to begin
                </Button>
            </CardContent>
        </Card>
    );
}

function recommendedStep(completed: string[]): BlueprintStepId {
    return blueprintSteps.find((step) => !completed.includes(step.id))?.id ?? "publish";
}
